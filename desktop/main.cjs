const { app, BrowserWindow, ipcMain, Menu, shell } = require('electron');
const path = require('node:path');
const fs = require('node:fs');
const os = require('node:os');
const { execFile } = require('node:child_process');
const { PhpServer, pickPort } = require('./php-server.cjs');
const { ensureFirstRun, appKey } = require('./first-run.cjs');   // Task 5
const { backupAndMigrate } = require('./auto-backup.cjs'); // Task 5

const packaged = app.isPackaged;
const appPath = packaged ? path.join(process.resourcesPath, 'app') : path.resolve(__dirname, '..');
const phpPath = packaged
  ? path.join(process.resourcesPath, 'php', 'php.exe')
  : (process.env.MACARON_DEV_PHP || 'php');
const phpIni = packaged ? path.join(process.resourcesPath, 'php', 'php.ini') : undefined;
const dataDir = packaged
  ? path.join(app.getPath('appData'), 'macaron')
  : (process.env.MACARON_DEV_DATA_DIR || path.join(__dirname, 'dev-data'));
const router = packaged ? path.join(appPath, 'server.php') : path.join(__dirname, 'server.php');
// asarUnpack ships desktop/** as real files; the icon must stay a real path.
const winIcon = path.join(__dirname, 'icon.png').replace('app.asar', 'app.asar.unpacked');

let server = null;
let win = null;

function lanIp() {
  for (const list of Object.values(os.networkInterfaces())) {
    for (const iface of list || []) {
      if (iface.family === 'IPv4' && !iface.internal) return iface.address;
    }
  }
  return '127.0.0.1';
}

function readLanEnabled() {
  try {
    return JSON.parse(fs.readFileSync(path.join(dataDir, 'desktop.json'), 'utf8')).lan_enabled === true;
  } catch { return false; }
}

// port and bind are real at build time so artisan and the php server share one env.
function buildEnv(port, bind) {
  return {
    APP_NAME: 'Macaron',
    APP_ENV: 'production',
    APP_DEBUG: 'false',
    APP_KEY: appKey(dataDir),
    APP_URL: `http://127.0.0.1:${port}`,
    DB_CONNECTION: 'sqlite',
    DB_DATABASE: path.join(dataDir, 'database.sqlite'),
    APP_STORAGE_PATH: path.join(dataDir, 'storage'),
    SESSION_DRIVER: 'file',
    SESSION_LIFETIME: '720',
    LOG_CHANNEL: 'single',
    MACARON_DATA_DIR: dataDir,
    MACARON_SHELL: '1',
    MACARON_LAN_URL: bind === '0.0.0.0' ? `http://${lanIp()}:${port}` : '',
  };
}

// Fresh PhpServer per (bind, port): start() refuses a reused instance, and the
// window must always load loopback even when php binds 0.0.0.0.
async function startServer(bind, port) {
  server = new PhpServer({ phpPath, appPath, router, iniPath: phpIni, env: buildEnv(port, bind) });
  await server.start(bind, port);
  return `http://127.0.0.1:${port}`;
}

async function boot() {
  // electron-builder's copyDir never creates empty directories, so the staged
  // empty bootstrap/cache never lands in resources/app; artisan needs it
  // writable (packages.php/services.php) or every command dies at bootstrap.
  fs.mkdirSync(path.join(appPath, 'bootstrap', 'cache'), { recursive: true });
  for (const dir of ['storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs', 'storage/app/public', 'backups']) {
    fs.mkdirSync(path.join(dataDir, dir), { recursive: true });
  }
  const bind = readLanEnabled() ? '0.0.0.0' : '127.0.0.1';
  const port = await pickPort(bind === '0.0.0.0');
  const env = buildEnv(port, bind);
  await ensureFirstRun({ phpPath, appPath, dataDir, phpIni }, env);      // Task 5
  await backupAndMigrate({ phpPath, appPath, dataDir, phpIni, env });    // Task 5
  openWindow(await startServer(bind, port));
}

function openWindow(url) {
  Menu.setApplicationMenu(null);
  win = new BrowserWindow({
    width: 1280, height: 800, show: false,
    title: 'Macaron',
    icon: winIcon,
    autoHideMenuBar: true,
    webPreferences: {
      preload: path.join(__dirname, 'preload.cjs'),
      contextIsolation: true,
      nodeIntegration: false,
    },
  });
  win.once('ready-to-show', () => { win.maximize(); win.show(); });
  win.webContents.on('before-input-event', (e, input) => {
    if (input.type === 'keyDown' && (input.key === 'F12' || input.key === 'F5' || (input.control && input.key.toLowerCase() === 'r'))) e.preventDefault();
  });
  win.on('closed', () => { win = null; if (server) server.stop(); app.quit(); });
  win.loadURL(url);
}

const gotLock = app.requestSingleInstanceLock();
if (!gotLock) app.quit();
else {
  app.on('second-instance', () => { if (win) win.focus(); });
  app.whenReady().then(() => boot().catch((err) => {
    // Never let logging mask the real failure: dataDir itself is a plausible failure point.
    try { fs.appendFileSync(path.join(dataDir, 'desktop-shell.log'), `[fatal] ${err.stack}\n`); } catch { /* swallow */ }
    const { dialog } = require('electron');
    dialog.showErrorBox('Macaron failed to start', String(err.message || err));
    if (server) server.stop();
    app.quit();
  }));
}

ipcMain.handle('restart-server', async (_e, lanEnabled) => {
  fs.writeFileSync(path.join(dataDir, 'desktop.json'), JSON.stringify({ lan_enabled: !!lanEnabled }));
  if (server) await server.stop();
  const bind = lanEnabled ? '0.0.0.0' : '127.0.0.1';
  const port = await pickPort(lanEnabled);
  const url = await startServer(bind, port);
  if (lanEnabled) spawnFirewallRule(port);   // best-effort; never blocks the rebind
  if (win) win.loadURL(url);
  return url;
});

ipcMain.handle('shell-info', () => ({ version: app.getVersion(), lanUrl: readLanEnabled() && server ? `http://${lanIp()}:${server.port}` : '' }));

// Receipts are captured HTML fragments; Electron's native dialog can neither
// preview nor print them reliably, so render the fragment in a hidden window
// and print silently to the default system printer. When no physical printer
// exists (dev machines often only have "Microsoft Print to PDF"), silent print
// would pop a save dialog, so save a PDF and open it instead.
ipcMain.handle('print-html', async (_e, html) => {
  const log = (msg) => {
    try { fs.appendFileSync(path.join(dataDir, 'desktop-shell.log'), `[print] ${msg}\n`); } catch { /* swallow */ }
  };
  const dir = path.join(dataDir, 'receipts');
  const tmpHtml = path.join(dir, `.print-${Date.now()}.html`);
  const printWin = new BrowserWindow({
    show: false,
    webPreferences: { contextIsolation: true, nodeIntegration: false },
  });
  try {
    fs.mkdirSync(dir, { recursive: true });
    // Temp file beats a data: URL: no size limit, no intermittent ERR_FAILED.
    fs.writeFileSync(tmpHtml, String(html));
    await printWin.loadFile(tmpHtml);
    await new Promise((r) => setTimeout(r, 150));

    let printerNames = [];
    try { printerNames = (await printWin.webContents.getPrintersAsync()).map((p) => p.name); } catch { /* treat as none */ }
    const virtualPrinter = /pdf|xps|onenote|fax/i;
    const hasPhysicalPrinter = printerNames.some((n) => !virtualPrinter.test(n));

    if (hasPhysicalPrinter) {
      const printed = await Promise.race([
        new Promise((resolve) => {
          printWin.webContents.print({ silent: true, margins: { marginType: 'none' } }, (success, failureReason) => resolve({ success, failureReason }));
        }),
        new Promise((resolve) => setTimeout(() => resolve({ success: false, failureReason: 'timed out' }), 20000)),
      ]);
      if (printed.success) {
        log('printed to default printer');
        return { success: true };
      }
      log(`silent print failed (${printed.failureReason}); saving PDF instead`);
    } else {
      log(`no physical printer [${printerNames.join(', ') || 'none'}]; saving PDF`);
    }

    // Page size comes from the template itself: it renders at its roll width
    // (76mm receipt / 80mm KOT) and printToPDF rejects custom pageSize objects
    // on Windows ("Printing failed"), so bake the measured size into @page and
    // let preferCSSPageSize pick it up.
    const dims = await printWin.webContents.executeJavaScript(`(() => {
      const s = document.querySelector('style[media="print"]');
      if (s) s.media = 'all';
      const el = document.body.firstElementChild;
      const h = document.documentElement.scrollHeight;
      const w = el ? el.getBoundingClientRect().width : 0;
      if (s) s.media = 'print';
      return { h, w };
    })()`).catch(() => ({ h: 0, w: 0 }));
    const heightMm = Math.min(500, Math.max(60, (dims.h + 16) * 0.264583));
    const widthMm = Math.min(120, Math.max(50, dims.w * 0.264583 || 76));
    await printWin.webContents.executeJavaScript(`(() => {
      const s = document.querySelector('style[media="print"]');
      if (s) s.textContent = s.textContent.replace(/@page[^}]*}/, '@page { size: ${widthMm.toFixed(1)}mm ${heightMm.toFixed(1)}mm; margin: 0mm; }');
    })()`);
    const pdf = await printWin.webContents.printToPDF({
      preferCSSPageSize: true,
      printBackground: true,
      margins: { marginType: 'none' },
    });
    const file = path.join(dir, `receipt-${Date.now()}.pdf`);
    fs.writeFileSync(file, pdf);
    shell.openPath(file);
    return { success: true, viaPdf: file };
  } catch (err) {
    log(`print failed: ${err.message}`);
    return { success: false, reason: String(err.message || err) };
  } finally {
    try { fs.unlinkSync(tmpHtml); } catch { /* swallow */ }
    printWin.destroy();
  }
});

// One-time elevated netsh rule; the UAC decision only lands in the log, never blocks the rebind.
function spawnFirewallRule(port) {
  const bat = app.isPackaged
    ? path.join(process.resourcesPath, 'firewall-enable.bat')
    : path.join(__dirname, 'firewall-enable.bat');
  const ps = `Start-Process -FilePath '${bat.replace(/'/g, "''")}' -ArgumentList ${port} -Verb RunAs -WindowStyle Hidden`;
  execFile('powershell', ['-NoProfile', '-Command', ps], { windowsHide: true }, (err) => {
    fs.appendFileSync(path.join(dataDir, 'desktop-shell.log'), `[firewall] ${err ? `declined/failed: ${err.message}` : `rule added (port ${port})`}\n`);
  });
}

app.on('window-all-closed', async () => { if (server) await server.stop(); });
