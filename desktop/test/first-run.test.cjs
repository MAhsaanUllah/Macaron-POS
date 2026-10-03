const { test } = require('node:test');
const assert = require('node:assert');
const fs = require('node:fs');
const os = require('node:os');
const path = require('node:path');
const { ensureFirstRun } = require('../first-run.cjs');

// Stub appPath inside a temp dir: the public/storage junction ensureFirstRun creates must never land in the repo.
function tempAppPath(dir) {
  const appPath = path.join(dir, 'app');
  fs.mkdirSync(path.join(appPath, 'public'), { recursive: true });
  return appPath;
}

test('ensureFirstRun creates db file + storage dirs on fresh dataDir', async () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'mpfr-'));
  const appPath = tempAppPath(dir);
  // phpPath null = test seam: runMigrate skips artisan, no PHP needed for this unit
  await ensureFirstRun({ appPath, dataDir: dir, phpPath: null });
  assert.ok(fs.existsSync(path.join(dir, 'database.sqlite')));
  for (const d of ['storage/framework/views', 'storage/framework/sessions', 'storage/framework/cache/data', 'storage/logs', 'storage/app/public', 'backups']) {
    assert.ok(fs.existsSync(path.join(dir, d)), `missing ${d}`);
  }
  const key = fs.readFileSync(path.join(dir, 'secret.key'), 'utf8').trim();
  assert.match(key, /^base64:[A-Za-z0-9+/=]+$/);
});

test('ensureFirstRun keeps an existing db untouched and reuses the stored key', async () => {
  const dir = fs.mkdtempSync(path.join(os.tmpdir(), 'mpfr-'));
  const appPath = tempAppPath(dir);
  fs.writeFileSync(path.join(dir, 'database.sqlite'), 'existing-sales');
  await ensureFirstRun({ appPath, dataDir: dir, phpPath: null });
  assert.strictEqual(fs.readFileSync(path.join(dir, 'database.sqlite'), 'utf8'), 'existing-sales');
  const key1 = fs.readFileSync(path.join(dir, 'secret.key'), 'utf8').trim();
  await ensureFirstRun({ appPath, dataDir: dir, phpPath: null });
  assert.strictEqual(fs.readFileSync(path.join(dir, 'secret.key'), 'utf8').trim(), key1);
});
