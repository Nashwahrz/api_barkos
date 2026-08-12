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

        if ($user && !$user->is_active) {
            $user->tokens()->delete();
            return response()->json([
                'message' => "Akun Anda telah dinonaktifkan oleh Admin karena indikasi pelanggaran.\n\nSilakan hubungi dukungan kami melalui email: kostmartpadang.com\n\nTemplate Pesan:\nHalo Admin Lapak Kos, akun saya dengan email {$user->email} telah dinonaktifkan. Saya ingin mengajukan banding/penjelasan terkait hal ini. Mohon bantuannya."
            ], 403);
        }

        // Throttle writes: only touch the timestamp if it's stale, so we're not
        // hitting the DB on every single poll request.
        if ($user && (!$user->last_active_at || $user->last_active_at->lt(now()->subSeconds(30)))) {
            $user->forceFill(['last_active_at' => now()])->saveQuietly();
        }

        return $next($request);
    }
}
