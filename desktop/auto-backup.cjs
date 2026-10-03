const fs = require('node:fs');
const path = require('node:path');
const { spawn } = require('node:child_process');

function backupDatabase(dbPath, backupsDir, { keep = 10 } = {}) {
  if (!fs.existsSync(dbPath)) return null;
  fs.mkdirSync(backupsDir, { recursive: true });
  const stamp = new Date().toISOString().replace(/[:.]/g, '-');
  const dest = path.join(backupsDir, `database-${stamp}.sqlite`);
  fs.copyFileSync(dbPath, dest);
  const list = fs.readdirSync(backupsDir)
    .filter((f) => f.startsWith('database-') && f.endsWith('.sqlite'))
    .sort();
  while (list.length > keep) fs.rmSync(path.join(backupsDir, list.shift()));
  return dest;
}

function runMigrate({ phpPath, appPath, phpIni, env }) {
  return new Promise((resolve, reject) => {
    if (!phpPath) return resolve(); // test seam: null php skips artisan
    const args = [];
    if (phpIni) args.push('-c', phpIni);
    args.push('artisan', 'migrate', '--force');
    const child = spawn(phpPath, args, { cwd: appPath, env: { ...process.env, ...env }, windowsHide: true });
    let err = '';
    child.stderr.on('data', (d) => { err += d; });
    // A missing/unexecutable php binary arrives async as 'error', not as a non-zero exit.
    child.on('error', (spawnErr) => reject(new Error(`migrate failed: ${spawnErr.message}`)));
    child.on('exit', (code) => code === 0 ? resolve() : reject(new Error(`migrate failed (${code}): ${err.slice(0, 2000)}`)));
  });
}

async function backupAndMigrate(ctx) {
  const dbPath = path.join(ctx.dataDir, 'database.sqlite');
  if (fs.existsSync(dbPath)) {
    backupDatabase(dbPath, path.join(ctx.dataDir, 'backups'));
  }
  await runMigrate({ ...ctx, env: ctx.env || {} });
}

module.exports = { backupDatabase, runMigrate, backupAndMigrate };
