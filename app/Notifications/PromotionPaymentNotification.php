<?php

namespace App\Notifications;

use App\Models\Promotion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PromotionPaymentNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Promotion $promotion;

    public function __construct(Promotion $promotion)
    {
        $this->promotion = $promotion;
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $matched = $this->promotion->manual_review_status === 'ocr_checked'
            && str_contains((string) $this->promotion->ocr_note, '[MATCH]');

        return [
            'promotion_id' => $this->promotion->id,
            'message'       => 'Bukti transfer manual dari ' . ($this->promotion->seller->name ?? '-')
                . ' untuk produk ' . ($this->promotion->product->nama_barang ?? '-')
                . ' perlu ditinjau.',
            'ocr_matched'   => $matched,
            'type'          => 'admin_promotion_payment',
        ];
    }
}
