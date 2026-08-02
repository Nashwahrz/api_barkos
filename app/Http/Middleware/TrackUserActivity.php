<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        // Throttle writes: only touch the timestamp if it's stale, so we're not
        // hitting the DB on every single poll request.
        if ($user && (!$user->last_active_at || $user->last_active_at->lt(now()->subSeconds(30)))) {
            $user->forceFill(['last_active_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
