<?php

namespace App\Notifications;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ForcePasswordResetNotification extends Notification
{
    use EmbedsAllocareLogo;

    public function __construct(public string $token) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $expireMinutes = (int) config(
            'auth.passwords.'.config('auth.defaults.passwords').'.expire',
            10
        );

        $resetUrl = url(route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ], false));

        $name = trim((string) ($notifiable->first_name ?? $notifiable->name ?? ''));

        $message = (new MailMessage)
            ->subject('Security update: reset your AlloCare password')
            ->view('emails.auth.force-password-reset', [
                'resetUrl' => $resetUrl,
                'expireMinutes' => $expireMinutes,
                'name' => $name !== '' ? $name : null,
                'appName' => config('app.name', 'AlloCare'),
                'logoSrc' => 'cid:allocare-logo',
            ]);

        return $this->withEmbeddedLogo($message);
    }
}
