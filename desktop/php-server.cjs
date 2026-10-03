const { spawn, execFile } = require('node:child_process');
const net = require('node:net');
const http = require('node:http');
const fs = require('node:fs');

function getFreePort() {
  return new Promise((resolve, reject) => {
    const srv = net.createServer();
    srv.listen(0, '127.0.0.1', () => {
      const port = srv.address().port;
      srv.close(() => resolve(port));
    });
    srv.on('error', reject);
  });
}

// Stable port for LAN tablet access: keeps the one-time Macaron-LAN firewall
// rule valid across restarts and gives the owner a bookmarkable tablet URL.
const PREFERRED_LAN_PORT = 8756;

// Probe whether `port` can be bound on `bind`. Never rejects: a failed bind
// (EADDRINUSE, exclusions, permissions) resolves false.
function isPortAvailable(port, bind = '0.0.0.0') {
  return new Promise((resolve) => {
    const srv = net.createServer();
    srv.once('error', () => resolve(false));
    srv.listen(port, bind, () => {
      srv.close(() => resolve(true));
    });
  });
}

// Prefer the stable LAN port; fall back to an ephemeral one when it is taken.
async function pickPort(lanEnabled, preferred = PREFERRED_LAN_PORT) {
  if (lanEnabled && await isPortAvailable(preferred)) return preferred;
  return getFreePort();
}

function waitForHttp(url, timeoutMs = 15000, signal = null) {
  const deadline = Date.now() + timeoutMs;
  return new Promise((resolve, reject) => {
    let timer = null;
    const stop = (err) => {
      if (timer) clearTimeout(timer);
      reject(err);
    };
    const onAbort = () => stop(signal.reason instanceof Error ? signal.reason : new Error(`aborted waiting for ${url}`));
    const retry = () => {
      if (signal?.aborted) return onAbort();
      if (Date.now() > deadline) return stop(new Error(`server not ready at ${url}`));
      timer = setTimeout(tryOnce, 250);
    };
    const tryOnce = () => {
      const req = http.get(url, (res) => {
        res.resume();
        res.statusCode === 200 ? resolve() : retry();
      });
      req.on('error', retry);
      // A cold Laravel boot (AV scan, no opcache) can take seconds; killing each
      // attempt after 1s meant a healthy-but-slow server was never observed.
      req.setTimeout(8000, () => { req.destroy(); retry(); });
    };
    if (signal) {
      if (signal.aborted) return onAbort();
      signal.addEventListener('abort', onAbort, { once: true });
    }
    tryOnce();
  });
}

class PhpServer {
  constructor({ phpPath, appPath, router, env, iniPath }) {
    this.phpPath = phpPath;
    this.appPath = appPath;
    this.router = router;
    this.env = env;
    this.iniPath = iniPath;
    this.child = null;
    this.port = null;
    this.bind = '127.0.0.1';
  }

  async start(bind = '127.0.0.1', port = null) {
    if (this.child) throw new Error('already started');
    this.bind = bind;
    this.port = port ?? await getFreePort();
    // php -S refuses a router script combined with -t, so the docroot is set
    // via cwd and the router serves static files with return false.
    const args = [];
    if (this.iniPath) args.push('-c', this.iniPath);
    const publicDir = `${this.appPath}/public`;
    args.push('-S', `${bind}:${this.port}`, this.router);
    const child = spawn(this.phpPath, args, {
      cwd: publicDir,
      env: { ...process.env, ...this.env, MACARON_PUBLIC: publicDir },
      windowsHide: true,
      stdio: ['ignore', 'pipe', 'pipe'],
    });
    this.child = child;
    const log = (s) => fs.appendFileSync(`${this.env.MACARON_DATA_DIR}/desktop-shell.log`, s);
    child.stdout.on('data', (d) => log(`[php.out] ${d}`));
    child.stderr.on('data', (d) => log(`[php.err] ${d}`));
    // A missing/unexecutable php binary surfaces async as 'error'; cancel the
    // readiness poll and reject with that error instead of throwing uncaught.
    const controller = new AbortController();
    const spawnFailed = new Promise((_resolve, reject) => {
      child.on('error', (err) => {
        if (this.child === child) this.child = null;
        controller.abort(err);
        reject(err);
      });
    });
    await Promise.race([
      waitForHttp(`http://127.0.0.1:${this.port}/up`, 45000, controller.signal),
      spawnFailed,
    ]);
    return this.url();
  }

  url() { return `http://${this.bind}:${this.port}`; }

  stop() {
    if (!this.child) return Promise.resolve();
    const child = this.child;
    const pid = child.pid;
    this.child = null;
    // A child that already exited (crash, external kill) has exitCode set and
    // will never emit 'exit' again; waiting on it would deadlock restart-server.
    if (child.exitCode !== null) return Promise.resolve();
    const done = new Promise((resolve) => child.once('exit', () => resolve()));
    if (process.platform === 'win32') {
      execFile('taskkill', ['/pid', String(pid), '/T', '/F'], () => {});
    } else {
      child.kill('SIGTERM');
    }
    return done;
  }
}

module.exports = { PhpServer, getFreePort, waitForHttp, PREFERRED_LAN_PORT, isPortAvailable, pickPort };
