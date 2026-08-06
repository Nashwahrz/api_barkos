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
use NotificationChannels\WebPush\HasPushSubscriptions;

#[Fillable(['name', 'email', 'password', 'asal_kampus', 'role', 'google_id', 'avatar', 'google_token', 'email_verified_at', 'phone', 'is_active', 'latitude', 'longitude', 'identity_document_path', 'is_identity_verified'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, HasApiTokens, HasPushSubscriptions;

    /**
     * Get the products owned by the user.
     */
    public function products(): HasMany
    {
        return $this->hasMany(Product::class, 'user_id');
    }

    /**
     * Get the reports received by the user's products.
     */
    public function receivedReports(): \Illuminate\Database\Eloquent\Relations\HasManyThrough
    {
        return $this->hasManyThrough(Report::class, Product::class, 'user_id', 'product_id', 'id', 'id');
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
     * Get the bank accounts owned by the user.
     */
    public function bankAccounts(): HasMany
    {
        return $this->hasMany(BankAccount::class);
    }

    /**
     * Get the favorites of the user.
     */
    public function favorites(): HasMany
    {
        return $this->hasMany(Favorite::class);
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
            'is_identity_verified' => 'boolean',
            'latitude'          => 'decimal:7',
            'longitude'         => 'decimal:7',
            'last_active_at'    => 'datetime',
        ];
    }

    /**
     * A user counts as online if they've hit the API within the last 30 seconds
     * (matches the chat page's 3s poll interval plus TrackUserActivity's throttle window).
     */
    public function isOnline(): bool
    {
        return $this->last_active_at !== null && $this->last_active_at->gt(now()->subSeconds(45));
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

    /**
     * Send the password reset notification.
     *
     * @param string $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        $this->notify(new \App\Notifications\QueuedResetPassword($token));
    }
}
