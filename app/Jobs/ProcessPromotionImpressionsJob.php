<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessPromotionImpressionsJob implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public function __construct(public array $productIds)
    {
        //
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        if (empty($this->productIds)) {
            return;
        }

        $promotions = \App\Models\Promotion::whereIn('product_id', $this->productIds)
            ->active()
            ->get();

        foreach ($promotions as $promotion) {
            $promotion->increment('current_impressions');

            if ($promotion->max_impressions !== null && $promotion->current_impressions >= $promotion->max_impressions) {
                $promotion->update(['status' => 'expired']);
                if ($promotion->product) {
                    $promotion->product->update([
                        'is_promoted' => false,
                        'promoted_until' => null,
                    ]);
                }
            }
        }
    }
}
