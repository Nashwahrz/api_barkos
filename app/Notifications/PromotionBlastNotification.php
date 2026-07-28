<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class PromotionBlastNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $promotion;

    /**
     * Create a new notification instance.
     */
    public function __construct($promotion)
    {
        $this->promotion = $promotion;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        // Just database for in-app notification since mail is sent separately in the job
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'promotion_id' => $this->promotion->id,
            'product_id'   => $this->promotion->product_id,
            'message'      => 'Promo Spesial: ' . ($this->promotion->ad_title ?? $this->promotion->product->nama_barang) . ' kini tersedia! Cek sekarang sebelum kehabisan.',
            'type'         => 'promotion',
        ];
    }
}
