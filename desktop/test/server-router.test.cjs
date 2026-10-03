const { test } = require('node:test');
const assert = require('node:assert');
const fs = require('node:fs');
const http = require('node:http');
const os = require('node:os');
const path = require('node:path');
const { spawn, spawnSync } = require('node:child_process');
const { getFreePort } = require('../php-server.cjs');

// The desktop shell keeps uploads under APP_STORAGE_PATH (per-machine data dir),
// which public/storage never points at — the router must serve them directly.
// Needs a real php binary: set MACARON_DEV_PHP (same var desktop:dev requires).
function findPhp() {
  for (const candidate of [process.env.MACARON_DEV_PHP, 'php']) {
    if (!candidate) continue;
    const probe = spawnSync(candidate, ['-v'], { stdio: 'ignore' });
    if (!probe.error && probe.status === 0) return candidate;
  }
  return null;
}

test('router serves /storage files from APP_STORAGE_PATH, 404s misses and traversal', async (t) => {
  const php = findPhp();
  if (!php) return t.skip('no php binary (set MACARON_DEV_PHP to run this test)');

  const storage = fs.mkdtempSync(path.join(os.tmpdir(), 'macaron-storage-'));
  fs.mkdirSync(path.join(storage, 'app', 'public', 'items'), { recursive: true });
  fs.writeFileSync(path.join(storage, 'app', 'public', 'items', 'probe.png'), 'PNGDATA');
  // Reachable via ../ from app/public unless the prefix guard rejects it.
  fs.writeFileSync(path.join(storage, 'app', 'secret.txt'), 'top-secret');

  const repoRoot = path.join(__dirname, '..', '..');
  const port = await getFreePort();
  const child = spawn(php, ['-S', `127.0.0.1:${port}`, path.join(__dirname, '..', 'server.php')], {
    cwd: path.join(repoRoot, 'public'),
    env: { ...process.env, APP_STORAGE_PATH: storage, MACARON_PUBLIC: path.join(repoRoot, 'public') },
    stdio: 'ignore',
  });

  const url = (p) => `http://127.0.0.1:${port}${p}`;
  try {
    for (let i = 0; i < 50; i++) {
      try { await fetch(url('/storage/items/probe.png')); break; } catch { await new Promise((r) => setTimeout(r, 100)); }
    }

    const served = await fetch(url('/storage/items/probe.png'));
    assert.strictEqual(served.status, 200);
    assert.strictEqual(await served.text(), 'PNGDATA');
    assert.strictEqual(served.headers.get('content-type'), 'image/png');

    const miss = await fetch(url('/storage/items/nope.png'));
    assert.strictEqual(miss.status, 404);

    // fetch()/URL normalizes %2e%2e away, so send the raw path over node:http.
    const traversal = await new Promise((resolve, reject) => {
      const req = http.request({ host: '127.0.0.1', port, path: '/storage/%2e%2e/secret.txt' }, (res) => {
        res.resume();
        resolve(res.statusCode);
      });
      req.on('error', reject);
      req.end();
    });
    assert.strictEqual(traversal, 404);
  } finally {
    child.kill();
    fs.rmSync(storage, { recursive: true, force: true });
  }
});

// Hashed build assets must be cached immutably; without those headers a shell
// navigation re-downloads the whole build (5MB icon font) and blinks.
test('router serves /build assets with immutable cache headers, 404s misses and traversal', async (t) => {
  const php = findPhp();
  if (!php) return t.skip('no php binary (set MACARON_DEV_PHP to run this test)');

  // A temp public root keeps the test independent of whatever `npm run build` last emitted.
  const root = fs.mkdtempSync(path.join(os.tmpdir(), 'macaron-public-'));
  fs.mkdirSync(path.join(root, 'build', 'assets'), { recursive: true });
  fs.writeFileSync(path.join(root, 'build', 'assets', 'app-test.css'), 'body{}');
  fs.writeFileSync(path.join(root, 'secret.txt'), 'top-secret');

  const repoRoot = path.join(__dirname, '..', '..');
  const port = await getFreePort();
  const child = spawn(php, ['-S', `127.0.0.1:${port}`, path.join(__dirname, '..', 'server.php')], {
    cwd: path.join(repoRoot, 'public'),
    env: { ...process.env, MACARON_PUBLIC: root },
    stdio: 'ignore',
  });

  const url = (p) => `http://127.0.0.1:${port}${p}`;
  try {
    for (let i = 0; i < 50; i++) {
      try { await fetch(url('/build/assets/app-test.css')); break; } catch { await new Promise((r) => setTimeout(r, 100)); }
    }

    const served = await fetch(url('/build/assets/app-test.css'));
    assert.strictEqual(served.status, 200);
    assert.strictEqual(await served.text(), 'body{}');
    // php appends its default_charset to text/* mime types.
    assert.ok(served.headers.get('content-type').startsWith('text/css'), served.headers.get('content-type'));
    assert.strictEqual(served.headers.get('cache-control'), 'public, max-age=31536000, immutable');

    const miss = await fetch(url('/build/assets/nope.css'));
    assert.strictEqual(miss.status, 404);

    const traversal = await new Promise((resolve, reject) => {
      const req = http.request({ host: '127.0.0.1', port, path: '/build/%2e%2e/secret.txt' }, (res) => {
        res.resume();
        resolve(res.statusCode);
      });
      req.on('error', reject);
      req.end();
    });
    assert.strictEqual(traversal, 404);
  } finally {
    child.kill();
    fs.rmSync(root, { recursive: true, force: true });
  }
});
