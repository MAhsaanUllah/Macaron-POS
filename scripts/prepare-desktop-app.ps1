# prepare-desktop-app.ps1 - build desktop/staging/app for electron-builder
# (extraResources maps desktop/staging/app -> resources/app).
#
# Staging-first: the app tree is copied WITHOUT vendor and composer install
# --no-dev --working-dir=$stage runs INSIDE the staging copy, so the repo's own
# vendor/ (which needs dev packages for `artisan test`) is never touched.
$ErrorActionPreference = 'Stop'

$root = Split-Path $PSScriptRoot -Parent
$stage = Join-Path $root 'desktop\staging\app'
$phpExe = Join-Path $root 'desktop\runtime\php\php.exe'
if (-not (Test-Path $phpExe)) { throw "Bundled PHP missing at $phpExe - run scripts/get-php-runtime.ps1 first." }

if (Test-Path $stage) { Remove-Item $stage -Recurse -Force }
New-Item -ItemType Directory -Force -Path $stage | Out-Null

# Robocopy the app tree (no vendor: composer runs in $stage further down).
# /XJ never follows junction points - public\storage is a junction in dev and
# following it would copy dev uploads into the package. /XD drops dev runtime
# state, stale caches, and dev data backups; /XF drops the dev sqlite DB and
# its .bak-* siblings - none of it may ever ship.
$dirs = @('app', 'bootstrap', 'database', 'public', 'resources', 'routes', 'storage')
$excludeDirs = @(
    (Join-Path $root 'storage\logs'),
    (Join-Path $root 'storage\framework\cache'),
    (Join-Path $root 'storage\framework\sessions'),
    (Join-Path $root 'storage\framework\views'),
    (Join-Path $root 'storage\framework\testing'),
    (Join-Path $root 'storage\app'),
    (Join-Path $root 'bootstrap\cache'),
    (Join-Path $root 'database\backups')
)
foreach ($d in $dirs) {
    robocopy (Join-Path $root $d) (Join-Path $stage $d) /E /XJ /NFL /NDL /NJH /NJS /XD @excludeDirs /XF 'database.sqlite*'
    if ($LASTEXITCODE -ge 8) { throw "robocopy $d failed with exit code $LASTEXITCODE" }
}
foreach ($f in @('artisan', 'composer.json', 'composer.lock')) { Copy-Item (Join-Path $root $f) $stage }
# packaged desktop/main.cjs resolves the php -S router as resources/app/server.php
Copy-Item (Join-Path $root 'desktop\server.php') (Join-Path $stage 'server.php')
# Vite dev-server marker must never ship
if (Test-Path (Join-Path $stage 'public\hot')) { Remove-Item (Join-Path $stage 'public\hot') -Force }

# Empty writable runtime dirs; real contents live in userData (APP_STORAGE_PATH).
foreach ($p in @('bootstrap\cache', 'storage\framework\views', 'storage\framework\cache\data', 'storage\framework\sessions', 'storage\logs', 'storage\app\public')) {
    New-Item -ItemType Directory -Force -Path (Join-Path $stage $p) | Out-Null
}

# Composer: PATH first (Herd's composer.bat when a dev PATH is inherited),
# else Herd's composer.phar run through an explicit php binary (the bundled
# runtime php, which exists - checked at the top of this script).
$composerArgs = @('install', '--no-dev', '--prefer-dist', '--optimize-autoloader', '--no-interaction', "--working-dir=$stage")
if (Get-Command composer -ErrorAction SilentlyContinue) {
    Write-Host "composer install --no-dev in $stage (PATH composer)"
    & composer @composerArgs
} else {
    $phar = Join-Path $env:USERPROFILE '.config\herd\bin\composer.phar'
    if (-not (Test-Path $phar)) { throw "composer not found: no 'composer' on PATH and no composer.phar at $phar" }
    Write-Host "composer install --no-dev in $stage (php + $phar)"
    & $phpExe $phar @composerArgs
}
if ($LASTEXITCODE -ne 0) { throw "composer install failed with exit code $LASTEXITCODE" }

Write-Host "Smoke test: staged artisan booted by the bundled PHP"
& $phpExe (Join-Path $stage 'artisan') --version
if ($LASTEXITCODE -ne 0) { throw "staged artisan smoke failed with exit code $LASTEXITCODE" }

# The smoke (like composer's package:discover) boots Laravel, which writes
# bootstrap\cache\packages.php + services.php; both regenerate on any boot when
# missing. Ship the cache dir empty like a fresh Laravel deploy - the installed
# app regenerates them on first boot (per-user NSIS install keeps resources
# writable; verified during this build's smoke run).
Get-ChildItem -Path (Join-Path $stage 'bootstrap\cache') -File | Remove-Item -Force
Write-Host "Staging ready at $stage"
