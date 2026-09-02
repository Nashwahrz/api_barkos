<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ProductDeletedByAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $productName;

    /**
     * Create a new notification instance.
     */
    public function __construct($productName)
    {
        $this->productName = $productName;
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
        return (new \NotificationChannels\WebPush\WebPushMessage)
            ->title('Produk Dihapus Admin')
            ->icon('/logo-lapak-kos.png')
            ->body("Produk Anda '{$this->productName}' telah dihapus oleh Admin.")
            ->action('Lihat', 'view')
            ->data(['url' => '/seller/products']);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'message' => "Produk Anda '{$this->productName}' telah dihapus oleh Admin.",
            'type'    => 'system',
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Produk Dihapus Admin - Lapak Kos')
            ->greeting('Halo ' . $notifiable->nama . '!')
            ->line("Produk Anda dengan nama '{$this->productName}' telah dihapus oleh Admin karena indikasi pelanggaran ketentuan Lapak Kos.")
            ->action('Lihat Produk Saya', config('services.frontend_url') . '/seller/products')
            ->line('Jika Anda merasa ini adalah kesalahan, silakan hubungi tim dukungan kami.');
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
