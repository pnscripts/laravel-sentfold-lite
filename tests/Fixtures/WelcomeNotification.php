<?php

namespace PNScripts\SentfoldLite\Tests\Fixtures;

use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class WelcomeNotification extends Notification
{
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Welcome aboard')
            ->line('Your temporary password: Xy7-pass')
            ->action('Confirm', 'https://example.test/confirm?token=abc123&id=5');
    }
}
