<?php

namespace App\Jobs;

use App\Mail\NewProductAdminAlertMail;
use App\Models\Product;
use App\Models\User;
use App\Notifications\NewProductAdminNotification;
use Illuminate\Bus\Queueable as BusQueueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class NotifyAdminsOfNewProductJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, BusQueueable, SerializesModels;

    public Product $product;

    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    public function handle(): void
    {
        try {
            User::where('role', 'super_admin')
                ->whereNotNull('email')
                ->chunk(100, function ($admins) {
                    foreach ($admins as $admin) {
                        try {
                            Mail::to($admin->email)->send(new NewProductAdminAlertMail($this->product));
                            $admin->notify(new NewProductAdminNotification($this->product));
                        } catch (\Exception $e) {
                            Log::error('Failed to send new product admin alert to: ' . $admin->email, ['error' => $e->getMessage()]);
                        }
                    }
                });

            Log::info('New product admin alert sent successfully.', ['product_id' => $this->product->id]);
        } catch (\Exception $e) {
            Log::error('New product admin alert job failed.', ['error' => $e->getMessage()]);
        }
    }
}
