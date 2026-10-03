<?php

namespace App\Http\Middleware;

use App\Models\SystemConfig;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureSetupCompleted
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Don't intercept setup routes
        if ($request->is('setup') || $request->is('setup/*')) {
            return $next($request);
        }

        $config = SystemConfig::first();
        if (! $config || ! $config->is_setup_completed) {
            return redirect()->route('setup.index');
        }

        return $next($request);
    }
}
