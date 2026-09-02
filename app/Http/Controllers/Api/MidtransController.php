<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Promotion;
use Illuminate\Support\Facades\Log;
use App\Services\PromotionActivationService;

class MidtransController extends Controller
{
    public function __construct(private PromotionActivationService $promotionActivationService)
    {
    }

    public function webhook(Request $request)
    {
        $serverKey = config('midtrans.server_key');
        $payload = $request->getContent();
        $notification = json_decode($payload);

        if (!$notification) {
            return response()->json(['message' => 'Invalid notification'], 400);
        }

        $orderId = $notification->order_id;
        $statusCode = $notification->status_code;
        $grossAmount = $notification->gross_amount;
        $signatureKey = $notification->signature_key;

        // Verify signature
        $expectedSignatureKey = hash("sha512", $orderId . $statusCode . $grossAmount . $serverKey);

        if ($expectedSignatureKey != $signatureKey) {
            Log::warning('Midtrans Webhook: Invalid signature', ['order_id' => $orderId]);
            return response()->json(['message' => 'Invalid signature'], 403);
        }

        $transactionStatus = $notification->transaction_status;

        // Find promotion by order_id
        $promotion = Promotion::where('order_id', $orderId)->first();

        if (!$promotion) {
            return response()->json(['message' => 'Promotion not found'], 404);
        }

        if ($transactionStatus == 'capture' || $transactionStatus == 'settlement') {
            if ($promotion->status_pembayaran !== 'paid') {
                $this->promotionActivationService->activate($promotion);
                Log::info('Midtrans Webhook: Promotion activated', ['order_id' => $orderId]);
            }
        } else if ($transactionStatus == 'cancel' || $transactionStatus == 'deny' || $transactionStatus == 'expire') {
            $promotion->status_pembayaran = 'failed';
            $promotion->status = 'expired';
            $promotion->save();
            Log::info('Midtrans Webhook: Promotion failed/expired', ['order_id' => $orderId]);
        } else if ($transactionStatus == 'pending') {
            $promotion->status_pembayaran = 'pending';
            $promotion->save();
        }

        return response()->json(['message' => 'Success']);
    }
}
