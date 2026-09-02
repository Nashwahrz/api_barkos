<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class OfferNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $offer;
    protected $message;
    protected $type;

    /**
     * Create a new notification instance.
     */
    public function __construct($offer, $message, $type)
    {
        $this->offer = $offer;
        $this->message = $message;
        $this->type = $type;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database', 'mail', \NotificationChannels\WebPush\WebPushChannel::class];
    }

    /**
     * Get the web push representation of the notification.
     */
    public function toWebPush($notifiable, $notification)
    {
        $url = '/seller/offers';
        if ($this->type === 'offer_accepted' || $this->type === 'offer_rejected') {
            $url = '/offers';
        }

        return (new \NotificationChannels\WebPush\WebPushMessage)
            ->title('💰 Penawaran Lapak Kos')
            ->icon('/logo-lapak-kos.png')
            ->body($this->message)
            ->action('Lihat', 'view')
            ->data(['url' => $url]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'offer_id'   => $this->offer->id,
            'product_id' => $this->offer->product_id,
            'message'    => $this->message,
            'type'       => $this->type,
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pemberitahuan Penawaran Lapak Kos: ' . $this->offer->product->nama_barang)
            ->greeting('Halo ' . $notifiable->nama . '!')
            ->line($this->message)
            ->action('Lihat Detail', config('services.frontend_url') . '/seller/offers')
            ->line('Terima kasih telah menggunakan Lapak Kos!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}
