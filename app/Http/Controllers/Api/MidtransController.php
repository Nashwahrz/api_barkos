<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use App\Models\Promotion;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class MidtransController extends Controller
{
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
            if ($promotion->payment_status !== 'paid') {
                $promotion->payment_status = 'paid';
                
                // Calculate end date based on package duration now that it's paid
                $package = $promotion->package;
                $product = $promotion->product;
                
                $startDate = Carbon::now();
                // If already promoted, extend from current end date
                if ($product->is_promoted && $product->promoted_until && Carbon::parse($product->promoted_until)->isFuture()) {
                    $startDate = Carbon::parse($product->promoted_until);
                }
                $endDate = $startDate->copy()->addDays($package->duration_days);

                $promotion->start_at = $startDate;
                $promotion->end_at = $endDate;
                $promotion->save();

                // Update product
                $product->update([
                    'is_promoted' => true,
                    'promoted_until' => $endDate,
                ]);

                Log::info('Midtrans Webhook: Promotion activated', ['order_id' => $orderId]);
            }
        } else if ($transactionStatus == 'cancel' || $transactionStatus == 'deny' || $transactionStatus == 'expire') {
            $promotion->payment_status = 'failed';
            $promotion->status = 'expired';
            $promotion->save();
            Log::info('Midtrans Webhook: Promotion failed/expired', ['order_id' => $orderId]);
        } else if ($transactionStatus == 'pending') {
            $promotion->payment_status = 'pending';
            $promotion->save();
        }

        return response()->json(['message' => 'Success']);
    }
}
