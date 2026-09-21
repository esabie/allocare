<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Symfony\Component\Mime\Email;

trait EmbedsAllocareLogo
{
    protected function withEmbeddedLogo(MailMessage $message): MailMessage
    {
        $logoPath = public_path('images/login-logo.png');

        if (! is_readable($logoPath)) {
            return $message;
        }

        return $message->withSymfonyMessage(function (Email $email) use ($logoPath) {
            $email->embedFromPath($logoPath, 'allocare-logo', 'image/png');
        });
    }
}
