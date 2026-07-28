<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class PushSubscriptionController extends Controller
{
    /**
     * Subscribe user to push notifications.
     */
    public function subscribe(Request $request)
    {
        $request->validate([
            'endpoint'    => 'required|string',
            'keys.auth'   => 'required|string',
            'keys.p256dh' => 'required|string',
        ]);

        $endpoint = $request->endpoint;
        $token = $request->keys['auth'];
        $key = $request->keys['p256dh'];

        $user = $request->user();
        
        $user->updatePushSubscription($endpoint, $key, $token);

        return response()->json(['message' => 'Berhasil mendaftarkan notifikasi.'], 200);
    }

    /**
     * Unsubscribe user from push notifications.
     */
    public function unsubscribe(Request $request)
    {
        $request->validate([
            'endpoint' => 'required|string',
        ]);

        $user = $request->user();
        $user->deletePushSubscription($request->endpoint);

        return response()->json(['message' => 'Berhasil mematikan notifikasi.'], 200);
    }
}
