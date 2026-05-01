<?php

namespace App\Http\Controllers\Api;

use App\Models\User;
use Illuminate\Auth\Events\Verified;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class EmailVerificationController extends Controller
{
    /**
     * Handle an email verification request.
     *
     * This endpoint is hit by the SPA (frontend) after the user clicks the
     * link in their email. Laravel's `signed` middleware has already validated
     * the URL signature before this method is reached.
     *
     * On success it returns JSON so the frontend can react accordingly.
     */
    public function verify(Request $request, $id, $hash): JsonResponse
    {
        $user = User::findOrFail($id);

        // Double-check the hash matches the user's email
        if (!hash_equals((string) $hash, sha1($user->getEmailForVerification()))) {
            return response()->json([
                'status'  => 'error',
                'message' => 'Link verifikasi tidak valid.',
            ], 403);
        }

        // Already verified – return success so the SPA can redirect gracefully
        if ($user->hasVerifiedEmail()) {
            return response()->json([
                'status'  => 'already_verified',
                'message' => 'Email sudah terverifikasi sebelumnya.',
            ]);
        }

        // Mark as verified – this writes email_verified_at to the database
        if ($user->markEmailAsVerified()) {
            event(new Verified($user));
        }

        return response()->json([
            'status'  => 'success',
            'message' => 'Email berhasil diverifikasi.',
        ]);
    }

    /**
     * Resend the email verification notification.
     */
    public function resend(Request $request): JsonResponse
    {
        if ($request->user()->hasVerifiedEmail()) {
            return response()->json([
                'message' => 'Email sudah terverifikasi.',
            ]);
        }

        $request->user()->sendEmailVerificationNotification();

        return response()->json([
            'message' => 'Link verifikasi telah dikirim ulang.',
        ]);
    }
}
