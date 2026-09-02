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
        if ($promotion->status_pembayaran === 'paid') {
            return;
        }

        $package = $promotion->package;
        $product = $promotion->product;

        $startDate = Carbon::now();
        if ($product->dipromosikan && $product->dipromosikan_hingga && Carbon::parse($product->dipromosikan_hingga)->isFuture()) {
            $startDate = Carbon::parse($product->dipromosikan_hingga);
        }
        $endDate = $startDate->copy()->addDays($package->durasi_hari);

        $promotion->status_pembayaran = 'paid';
        $promotion->mulai_pada = $startDate;
        $promotion->berakhir_pada = $endDate;
        $promotion->id_pengguna_target = $this->rollRandomRecipients($promotion);
        $promotion->save();

        $product->update([
            'dipromosikan' => true,
            'dipromosikan_hingga' => $endDate,
        ]);

        SendPromotionBlastJob::dispatchAfterResponse($promotion);
    }

    /**
     * Pick a fresh set of random recipient user IDs, sized by the promotion's
     * package `jumlah_penerima_acak`. Returns null when the package has no
     * cap configured — callers then treat the blast as going to everyone.
     */
    public function rollRandomRecipients(Promotion $promotion): ?array
    {
        $count = $promotion->package?->jumlah_penerima_acak;
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
