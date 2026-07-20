<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class TransactionNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $transaction;
    protected $message;
    protected $type;

    /**
     * Create a new notification instance.
     */
    public function __construct($transaction, $message, $type)
    {
        $this->transaction = $transaction;
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
            'transaction_id' => $this->transaction->id,
            'product_id'     => $this->transaction->product_id,
            'message'        => $this->message,
            'type'           => $this->type,
        ];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Pemberitahuan Pesanan Lapak Kos: ' . $this->transaction->product->nama_barang)
            ->greeting('Halo ' . $notifiable->name . '!')
            ->line($this->message)
            ->action('Lihat Detail', env('FRONTEND_URL', 'http://localhost:3000') . '/seller/orders')
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
