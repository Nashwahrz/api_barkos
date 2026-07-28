<?php

namespace App\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Bus\Queueable as BusQueueable;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Mail;
use App\Mail\NewProductBlastMail;
use Illuminate\Support\Facades\Log;

class SendNewProductBlastJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, BusQueueable, SerializesModels;

    public $product;

    /**
     * Create a new job instance.
     */
    public function __construct(Product $product)
    {
        $this->product = $product;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        try {
            // Get the seller ID to exclude them from the blast
            $sellerId = $this->product->user_id;

            // Chunk users to avoid memory limit issues
            User::where('id', '!=', $sellerId)
                ->whereNotNull('email')
                ->chunk(100, function ($users) {
                    foreach ($users as $user) {
                        try {
                            Mail::to($user->email)->send(new NewProductBlastMail($this->product));
                            $user->notify(new \App\Notifications\NewProductBlastNotification($this->product));
                        } catch (\Exception $e) {
                            Log::error('Failed to send new product blast to user: ' . $user->email, ['error' => $e->getMessage()]);
                        }
                    }
                });

            Log::info('New product blast sent successfully.', ['product_id' => $this->product->id]);
        } catch (\Exception $e) {
            Log::error('New product blast job failed.', ['error' => $e->getMessage()]);
        }
    }
}
