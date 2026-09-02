<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Chat;
use Illuminate\Support\Str;

class ChatNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $chat;

    /**
     * Create a new notification instance.
     */
    public function __construct(Chat $chat)
    {
        $this->chat = $chat;
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
        $senderName = $this->chat->sender->nama ?? 'Seseorang';
        $productName = $this->chat->product->nama_barang ?? 'Produk';
        $body = $senderName . ': "' . Str::limit($this->chat->pesan, 60) . '"';
        $url  = '/chat/' . $this->chat->id_produk . '/' . $this->chat->id_pengirim;

        return (new \NotificationChannels\WebPush\WebPushMessage)
            ->title('💬 Pesan Baru - ' . $productName)
            ->icon('/logo-lapak-kos.png')
            ->body($body)
            ->action('Balas', 'reply')
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
            'product_id' => $this->chat->id_produk,
            'message'    => "Pesan baru dari {$this->chat->sender->nama}: \"" . Str::limit($this->chat->pesan, 50) . "\"",
            'type'       => 'chat',
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $productName = $this->chat->product->nama_barang ?? 'Produk';
        
        return (new MailMessage)
            ->subject('Pesan Baru - Lapak Kos: ' . $productName)
            ->greeting('Halo ' . $notifiable->nama . '!')
            ->line('Anda menerima pesan baru dari ' . $this->chat->sender->nama . ' terkait produk "' . $productName . '".')
            ->line('Pesan: "' . Str::limit($this->chat->pesan, 100) . '"')
            ->action('Balas Pesan', config('services.frontend_url') . '/chat')
            ->line('Terima kasih telah menggunakan Lapak Kos!');
    }
}
