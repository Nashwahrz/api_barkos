<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Bus\Queueable as BusQueueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Promotion;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\PromotionBlastMail;
use Illuminate\Support\Facades\Log;

class SendPromotionBlastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, BusQueueable, SerializesModels;

    public $promotion;

    /**
     * Create a new job instance.
     */
    public function __construct(Promotion $promotion)
    {
        $this->promotion = $promotion;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get the seller ID to exclude them from the blast
            $sellerId = $this->promotion->product->user_id;

            $query = User::where('id', '!=', $sellerId)->whereNotNull('email');

            // When the package caps recipients, id_pengguna_target holds the random
            // selection rolled in PromotionActivationService; null means no cap (blast to all).
            if (!empty($this->promotion->id_pengguna_target)) {
                $query->whereIn('id', $this->promotion->id_pengguna_target);
            }

            // Chunk users to avoid memory limit issues if there are thousands of users
            $query->chunk(100, function ($users) {
                    foreach ($users as $user) {
                        try {
                            Mail::to($user->email)->send(new PromotionBlastMail($this->promotion));
                            $user->notify(new \App\Notifications\PromotionBlastNotification($this->promotion));
                        } catch (\Exception $e) {
                            Log::error('Failed to send promotion blast to user: ' . $user->email, ['error' => $e->getMessage()]);
                        }
                    }
                });

            Log::info('Promotion blast sent successfully.', ['promotion_id' => $this->promotion->id]);
        } catch (\Exception $e) {
            Log::error('Promotion blast job failed.', ['error' => $e->getMessage()]);
        }
    }
}
