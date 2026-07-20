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
        return ['database', 'mail'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toDatabase(object $notifiable): array
    {
        return [
            'product_id' => $this->chat->product_id,
            'message'    => "Pesan baru dari {$this->chat->sender->name}: \"" . Str::limit($this->chat->message, 50) . "\"",
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
            ->greeting('Halo ' . $notifiable->name . '!')
            ->line('Anda menerima pesan baru dari ' . $this->chat->sender->name . ' terkait produk "' . $productName . '".')
            ->line('Pesan: "' . Str::limit($this->chat->message, 100) . '"')
            ->action('Balas Pesan', env('FRONTEND_URL', 'http://localhost:3000') . '/chat') 
            ->line('Terima kasih telah menggunakan Lapak Kos!');
    }
}
