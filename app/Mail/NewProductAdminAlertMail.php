<?php

namespace App\Mail;

use App\Models\Product;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class NewProductAdminAlertMail extends Mailable
{
    use Queueable, SerializesModels;

    public $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Produk Baru Diunggah: ' . $this->product->nama_barang,
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.product.admin_new_product_alert',
            with: [
                'product' => $this->product,
            ],
        );
    }
}
