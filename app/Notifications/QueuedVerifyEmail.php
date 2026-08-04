<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class QueuedVerifyEmail extends VerifyEmail implements ShouldQueue
{
    use Queueable;

    protected function buildMailMessage($url)
    {
        return (new MailMessage)
            ->subject('Verifikasi Alamat Email Kamu - Lapak Kos')
            ->greeting('Halo!')
            ->line('Terima kasih telah mendaftar di Lapak Kos. Klik tombol di bawah untuk memverifikasi alamat email kamu.')
            ->action('Verifikasi Email', $url)
            ->line('Jika kamu tidak membuat akun ini, kamu bisa mengabaikan email ini.')
            ->salutation('Salam, Tim Lapak Kos');
    }
}
