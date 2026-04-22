<?php

namespace App\Providers;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        VerifyEmail::createUrlUsing(function (object $notifiable) {
            $frontendUrl = env('FRONTEND_URL', 'http://localhost:3000');
            $id = $notifiable->getKey();
            $hash = sha1($notifiable->getEmailForVerification());

            // Generate the temporary signed route for the API
            $verifyUrl = \Illuminate\Support\Facades\URL::temporarySignedRoute(
                'verification.verify',
                \Illuminate\Support\Carbon::now()->addMinutes(\Illuminate\Support\Facades\Config::get('auth.verification.expire', 60)),
                [
                    'id' => $id,
                    'hash' => $hash,
                ]
            );

            // Extract signature and expires from the generated URL
            $parsedUrl = parse_url($verifyUrl);
            $query = $parsedUrl['query'] ?? '';

            // Return the frontend URL with the query parameters attached
            return $frontendUrl . "/auth/verify-email/{$id}/{$hash}?{$query}";
        });
    }
}
