const fs = require('node:fs');
const path = require('node:path');
const crypto = require('node:crypto');
const { runMigrate } = require('./auto-backup.cjs');

// Per-machine APP_KEY: created once, reused on every later launch.
function appKey(dataDir) {
  const keyFile = path.join(dataDir, 'secret.key');
  if (fs.existsSync(keyFile)) return fs.readFileSync(keyFile, 'utf8').trim();
  const key = 'base64:' + crypto.randomBytes(32).toString('base64');
  fs.writeFileSync(keyFile, key);
  return key;
}

async function ensureFirstRun({ appPath, dataDir, phpPath, phpIni }, extraEnv = {}) {
  const dbPath = path.join(dataDir, 'database.sqlite');
  const dirs = [
    path.join(dataDir, 'storage', 'framework', 'cache/data'),
    path.join(dataDir, 'storage', 'framework', 'sessions'),
    path.join(dataDir, 'storage', 'framework', 'views'),
    path.join(dataDir, 'storage', 'logs'),
    path.join(dataDir, 'storage', 'app/public'),
    path.join(dataDir, 'backups'),
  ];
  dirs.forEach((d) => fs.mkdirSync(d, { recursive: true }));

  // junction so public/storage works without symlink privileges (Windows-safe, no admin)
  const link = path.join(appPath, 'public', 'storage');
  const target = path.join(dataDir, 'storage', 'app/public');
  if (!fs.existsSync(link) && fs.existsSync(path.join(appPath, 'public'))) {
    const { execFile } = require('node:child_process');
    execFile('cmd', ['/c', 'mklink', '/J', link, target], () => {});
  }

  if (!fs.existsSync(dbPath)) {
    fs.writeFileSync(dbPath, '');
    appKey(dataDir);
    await runMigrate({ phpPath, appPath, phpIni, env: extraEnv });
  } else {
    appKey(dataDir);
  }
  return dbPath;
}

module.exports = { ensureFirstRun, appKey };
