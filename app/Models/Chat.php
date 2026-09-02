<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Chat extends Model
{
    use HasFactory;

    protected $table = 'obrolan';
    protected $fillable = [
        'sender_id',
        'receiver_id',
        'product_id',
        'pesan',
        'id_balasan',
        'sudah_dibaca'
    ];

    /**
     * Get the user who sent the message.
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }

    /**
     * Get the user who received the message.
     */
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'receiver_id');
    }

    /**
     * Get the product associated with the chat.
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Get the message this chat is replying to, if any.
     */
    public function replyTo(): BelongsTo
    {
        return $this->belongsTo(Chat::class, 'id_balasan');
    }
}
