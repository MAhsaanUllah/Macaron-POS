<?php

use App\Http\Middleware\CheckOpenShift;
use App\Http\Middleware\CheckRole;
use App\Http\Middleware\EnsureSetupCompleted;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

$app = Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->alias([
            'open.shift' => CheckOpenShift::class,
            'role' => CheckRole::class,
        ]);
        $middleware->web(append: [EnsureSetupCompleted::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();

if (($storagePath = env('APP_STORAGE_PATH')) && is_dir($storagePath)) {
    $app->useStoragePath($storagePath);
}

return $app;
