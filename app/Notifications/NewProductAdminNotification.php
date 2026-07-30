<?php

namespace App\Notifications;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class NewProductAdminNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected Product $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Mail is sent separately via NewProductAdminAlertMail in the job.
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'product_id' => $this->product->id,
            'message'    => 'Penjual ' . ($this->product->user->name ?? '-') . ' mengunggah produk baru: ' . $this->product->nama_barang,
            'type'       => 'admin_new_product',
        ];
    }
}
