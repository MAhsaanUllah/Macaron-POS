<?php

namespace App\Http\Middleware;

use App\Models\Shift;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckOpenShift
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Check if there is an open shift
        $activeShift = Shift::where('user_id', $request->user()->id)
            ->where('status', 'open')
            ->latest()
            ->first();

        if (! $activeShift && ! $request->is('shift/open') && ! $request->is('shift/store')) {
            return redirect()->route('shift.open');
        }

        return $next($request);
    }
}
