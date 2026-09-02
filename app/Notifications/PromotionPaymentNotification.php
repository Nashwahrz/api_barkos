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
        $matched = $this->promotion->status_peninjauan_manual === 'ocr_checked'
            && str_contains((string) $this->promotion->catatan_ocr, '[MATCH]');

        return [
            'promotion_id' => $this->promotion->id,
            'message'       => 'Bukti transfer manual dari ' . ($this->promotion->seller->nama ?? '-')
                . ' untuk produk ' . ($this->promotion->product->nama_barang ?? '-')
                . ' perlu ditinjau.',
            'ocr_matched'   => $matched,
            'type'          => 'admin_promotion_payment',
        ];
    }
}
