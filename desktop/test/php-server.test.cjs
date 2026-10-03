const { test } = require('node:test');
const assert = require('node:assert');
const http = require('node:http');
const net = require('node:net');
const os = require('node:os');
const { spawn } = require('node:child_process');
const { PhpServer, getFreePort, waitForHttp, isPortAvailable, pickPort } = require('../php-server.cjs');

test('getFreePort returns an in-range TCP port', async () => {
  const port = await getFreePort();
  assert.ok(Number.isInteger(port), `expected integer, got ${port}`);
  assert.ok(port >= 1024 && port <= 65535, `port ${port} out of range`);
});

test('getFreePort does not reuse the previous port', async () => {
  const a = await getFreePort();
  const b = await getFreePort();
  assert.notStrictEqual(a, b);
});

test('isPortAvailable resolves false for a port we occupy ourselves', async () => {
  const srv = net.createServer();
  await new Promise((resolve) => srv.listen(0, '0.0.0.0', resolve));
  const port = srv.address().port;
  try {
    assert.strictEqual(await isPortAvailable(port), false);
  } finally {
    await new Promise((resolve) => srv.close(resolve));
  }
});

test('isPortAvailable resolves true for a freed getFreePort port, and pickPort(true, port) returns it', async () => {
  const port = await getFreePort(); // its listener is already closed again
  assert.strictEqual(await isPortAvailable(port), true);
  assert.strictEqual(await pickPort(true, port), port);
});

test('pickPort(true, occupied) falls back to a different free port', async () => {
  const srv = net.createServer();
  await new Promise((resolve) => srv.listen(0, '0.0.0.0', resolve));
  const occupied = srv.address().port;
  try {
    const port = await pickPort(true, occupied);
    assert.ok(Number.isInteger(port), `expected integer, got ${port}`);
    assert.notStrictEqual(port, occupied);
  } finally {
    await new Promise((resolve) => srv.close(resolve));
  }
});

test('pickPort(false) returns a free port number', async () => {
  const port = await pickPort(false);
  assert.ok(Number.isInteger(port) && port >= 1024 && port <= 65535, `port ${port} out of range`);
});

test('waitForHttp resolves against a 200 endpoint', async () => {
  const srv = http.createServer((_req, res) => { res.end('ok'); });
  await new Promise((resolve) => srv.listen(0, '127.0.0.1', resolve));
  const url = `http://127.0.0.1:${srv.address().port}/up`;
  try {
    await waitForHttp(url, 2000);
  } finally {
    await new Promise((resolve) => srv.close(resolve));
  }
});

test('waitForHttp rides out a slow (2.5s) first response instead of killing every attempt', async () => {
  const srv = http.createServer((_req, res) => { setTimeout(() => res.end('ok'), 2500); });
  await new Promise((resolve) => srv.listen(0, '127.0.0.1', resolve));
  const url = `http://127.0.0.1:${srv.address().port}/up`;
  try {
    await waitForHttp(url, 6000);
  } finally {
    await new Promise((resolve) => srv.close(resolve));
  }
});

test('waitForHttp rejects with the url in the message on timeout', async () => {
  const port = await getFreePort(); // nothing is listening -> connection refused until deadline
  const url = `http://127.0.0.1:${port}/up`;
  await assert.rejects(waitForHttp(url, 300), (err) => err.message.includes(url));
});

test('stop() resolves when the child has already exited', async () => {
  const server = new PhpServer({ phpPath: process.execPath, appPath: __dirname, router: '', env: {} });
  const child = spawn(process.execPath, ['-e', 'process.exit(0)'], { stdio: 'ignore' });
  await new Promise((resolve) => child.once('exit', resolve)); // deterministic: child is gone before stop()
  server.child = child;
  let timer;
  const timeout = new Promise((_resolve, reject) => {
    timer = setTimeout(() => reject(new Error('stop() did not resolve within 2s for an already-exited child')), 2000);
  });
  try {
    await Promise.race([server.stop(), timeout]);
  } finally {
    clearTimeout(timer);
  }
});

test('start() rejects with the spawn error when the php binary is missing', async () => {
  const port = await getFreePort();
  const server = new PhpServer({ phpPath: 'macaron-no-such-php.exe', appPath: __dirname, router: '', env: { MACARON_DATA_DIR: os.tmpdir() } });
  await assert.rejects(server.start('127.0.0.1', port), /ENOENT/);
  assert.strictEqual(server.port, port); // caller-provided port is used, not re-allocated
  assert.strictEqual(server.child, null); // failed child is cleared so stop() cannot hang
});
