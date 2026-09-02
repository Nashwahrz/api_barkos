<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chat extends Model
{
    use HasFactory;

    protected $table = 'obrolan';
    protected $primaryKey = 'id_obrolan';
    protected $fillable = [
        'id_pengirim',
        'id_penerima',
        'id_produk',
        'pesan',
        'id_balasan',
        'sudah_dibaca'
    ];

    /**
     * Get the user who sent the message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_pengirim', 'id');
    }

    /**
     * Get the user who received the message.
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'id_penerima', 'id');
    }

    /**
     * Get the product associated with the chat.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class, 'id_produk', 'id_produk');
    }

    /**
     * Get the message this chat is replying to, if any.
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Chat::class, 'id_balasan', 'id_obrolan');
    }
}
