<?php

namespace App\Jobs;

use App\Models\Promotion;
use App\Models\User;
use App\Notifications\PromotionPaymentNotification;
use App\Services\PaymentProofVerificationService;
use Illuminate\Bus\Queueable as BusQueueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class ProcessPromotionPaymentProofJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, BusQueueable, SerializesModels;

    public Promotion $promotion;

    public function __construct(Promotion $promotion)
    {
        $this->promotion = $promotion;
    }

    public function handle(PaymentProofVerificationService $verificationService): void
    {
        try {
            $absolutePath = Storage::disk('public')->path($this->promotion->jalur_bukti_manual);
            $result = $verificationService->verify($absolutePath, (float) $this->promotion->jumlah_dibayar);

            $prefix = $result['matched'] ? '[MATCH] ' : '[TIDAK COCOK] ';
            $this->promotion->update([
                'status_peninjauan_manual' => 'ocr_checked',
                'catatan_ocr' => $prefix . str_replace("\n", ' ', $result['text']),
            ]);
        } catch (\Exception $e) {
            Log::error('Promotion payment proof OCR check failed.', ['promotion_id' => $this->promotion->id, 'error' => $e->getMessage()]);
            $this->promotion->update([
                'status_peninjauan_manual' => 'ocr_checked',
                'catatan_ocr' => '[GAGAL DICEK] ' . $e->getMessage(),
            ]);
        }

        try {
            User::where('role', 'super_admin')
                ->whereNotNull('email')
                ->chunk(100, function ($admins) {
                    foreach ($admins as $admin) {
                        try {
                            $admin->notify(new PromotionPaymentNotification($this->promotion->fresh()));
                        } catch (\Exception $e) {
                            Log::error('Failed to notify admin of promotion payment proof: ' . $admin->email, ['error' => $e->getMessage()]);
                        }
                    }
                });
        } catch (\Exception $e) {
            Log::error('Promotion payment proof admin notification job failed.', ['error' => $e->getMessage()]);
        }
    }
}
