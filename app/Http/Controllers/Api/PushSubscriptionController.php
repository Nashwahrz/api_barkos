<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use NotificationChannels\WebPush\PushSubscription;

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
     * Renew a subscription whose endpoint the browser has rotated (pushsubscriptionchange).
     * Called from the service worker, which has no access to the user's auth token —
     * the old endpoint value (cryptographically random, unguessable) authorizes the update.
     */
    public function renew(Request $request)
    {
        $request->validate([
            'old_endpoint' => 'required|string',
            'endpoint'     => 'required|string',
            'keys.auth'    => 'required|string',
            'keys.p256dh'  => 'required|string',
        ]);

        $subscription = PushSubscription::findByEndpoint($request->old_endpoint);

        if (!$subscription || !$subscription->subscribable) {
            return response()->json(['message' => 'Subscription lama tidak ditemukan.'], 404);
        }

        $subscription->subscribable->updatePushSubscription(
            $request->endpoint,
            $request->keys['p256dh'],
            $request->keys['auth']
        );

        return response()->json(['message' => 'Berhasil memperbarui langganan notifikasi.']);
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
