<?php

namespace App\Services;

use App\Jobs\SendPromotionBlastJob;
use App\Models\Promotion;
use App\Models\User;
use Carbon\Carbon;

class PromotionActivationService
{
    /**
     * Mark a promotion as paid, compute its active window, and flag the product as promoted.
     * Shared by the Midtrans webhook, the dev force-paid bypass, and manual-transfer approval.
     */
    public function activate(Promotion $promotion): void
    {
        if ($promotion->payment_status === 'paid') {
            return;
        }

        $package = $promotion->package;
        $product = $promotion->product;

        $startDate = Carbon::now();
        if ($product->is_promoted && $product->promoted_until && Carbon::parse($product->promoted_until)->isFuture()) {
            $startDate = Carbon::parse($product->promoted_until);
        }
        $endDate = $startDate->copy()->addDays($package->duration_days);

        $promotion->payment_status = 'paid';
        $promotion->start_at = $startDate;
        $promotion->end_at = $endDate;
        $promotion->target_user_ids = $this->rollRandomRecipients($promotion);
        $promotion->save();

        $product->update([
            'is_promoted' => true,
            'promoted_until' => $endDate,
        ]);

        SendPromotionBlastJob::dispatchAfterResponse($promotion);
    }

    /**
     * Pick a fresh set of random recipient user IDs, sized by the promotion's
     * package `random_recipient_count`. Returns null when the package has no
     * cap configured — callers then treat the blast as going to everyone.
     */
    public function rollRandomRecipients(Promotion $promotion): ?array
    {
        $count = $promotion->package?->random_recipient_count;
        if (!$count || $count <= 0) {
            return null;
        }

        return User::where('id', '!=', $promotion->product->user_id)
            ->whereNotNull('email')
            ->inRandomOrder()
            ->limit($count)
            ->pluck('id')
            ->toArray();
    }
}
