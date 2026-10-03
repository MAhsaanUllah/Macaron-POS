<?php

// Router for php -S (spawned without -t; cwd is the public docroot).
// Resolves the public dir from MACARON_PUBLIC (set by PhpServer) or,
// as a fallback, from either location: dev (desktop/server.php beside
// ../public) or packaged (server.php staged at the app root with public/).
$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));
$root = getenv('MACARON_PUBLIC') ?: (is_dir(__DIR__.'/public') ? __DIR__.'/public' : __DIR__.'/../public');

// Uploads (item photos, shop logo) live under APP_STORAGE_PATH in the desktop
// shell; public/storage points at the repo's web-dev tree, so serve /storage
// straight from the data dir instead.
$storageRoot = getenv('APP_STORAGE_PATH') ? realpath(getenv('APP_STORAGE_PATH').'/app/public') : false;
if ($storageRoot && str_starts_with($uri, '/storage/')) {
    $file = realpath($storageRoot.DIRECTORY_SEPARATOR.substr($uri, strlen('/storage/')));
    if ($file && str_starts_with($file, $storageRoot.DIRECTORY_SEPARATOR) && is_file($file)) {
        $mimes = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp', 'gif' => 'image/gif', 'svg' => 'image/svg+xml', 'ico' => 'image/x-icon'];
        header('Content-Type: '.($mimes[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
        header('Content-Length: '.(string) filesize($file));
        header('Cache-Control: max-age=86400');
        readfile($file);
    } else {
        http_response_code(404);
    }
    exit;
}

// Vite assets are content-hashed and immutable, but php -S sends no cache
// headers on its own, so every shell page load re-downloaded the whole build
// (5MB icon font included) and fonts re-rasterized into a visible blink.
$buildRoot = realpath($root.'/build');
if ($buildRoot && str_starts_with($uri, '/build/')) {
    $file = realpath($buildRoot.DIRECTORY_SEPARATOR.substr($uri, strlen('/build/')));
    if ($file && str_starts_with($file, $buildRoot.DIRECTORY_SEPARATOR) && is_file($file)) {
        $mimes = ['css' => 'text/css', 'js' => 'text/javascript', 'woff2' => 'font/woff2', 'woff' => 'font/woff', 'png' => 'image/png', 'svg' => 'image/svg+xml', 'json' => 'application/json'];
        header('Content-Type: '.($mimes[strtolower(pathinfo($file, PATHINFO_EXTENSION))] ?? 'application/octet-stream'));
        header('Content-Length: '.(string) filesize($file));
        header('Cache-Control: public, max-age=31536000, immutable');
        readfile($file);
    } else {
        http_response_code(404);
    }
    exit;
}

if ($uri !== '/' && file_exists($root.$uri) && ! is_dir($root.$uri)) {
    return false; // serve static file as-is from the docroot
}
$_SERVER['SCRIPT_NAME'] = '/index.php';
require $root.'/index.php';
