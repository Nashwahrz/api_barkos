<?php

namespace App\Models;

use Laravel\Sanctum\HasApiTokens;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'asal_kampus', 'role', 'google_id', 'avatar', 'google_token', 'email_verified_at', 'phone', 'is_active', 'latitude', 'longitude'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens;

    /**
     * Get the products owned by the user.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'user_id');
    }

    /**
     * Get the chats sent by the user.
     */
    public function sentChats(): HasMany
    {
        return $this->hasMany(Chat::class, 'sender_id');
    }

    /**
     * Get the chats received by the user.
     */
    public function receivedChats(): HasMany
    {
        return $this->hasMany(Chat::class, 'receiver_id');
    }

    /**
     * Get all transactions where the user is the buyer.
     */
    public function buyerTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'buyer_id');
    }

    /**
     * Get all transactions where the user is the seller.
     */
    public function sellerTransactions(): HasMany
    {
        return $this->hasMany(Transaction::class, 'seller_id');
    }

    /**
     * Get all promotions purchased by this user (as seller).
     */
    public function promotions(): HasMany
    {
        return $this->hasMany(Promotion::class, 'seller_id');
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
            'is_active'         => 'boolean',
            'latitude'          => 'decimal:7',
            'longitude'         => 'decimal:7',
        ];
    }

    /**
     * Send the email verification notification.
     *
     * @return void
     */
    public function sendEmailVerificationNotification()
    {
        $this->notify(new \App\Notifications\QueuedVerifyEmail);
    }
}
