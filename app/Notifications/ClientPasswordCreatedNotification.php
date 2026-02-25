<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientPasswordCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public string $password;

    public function __construct(string $password)
    {
        $this->password = $password;
    }

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Your Account Password Has Been Set')
            ->greeting('Hello ' . $notifiable->first_name)
            ->line('Your account password has been created.')
            ->line('Login using the password below:')
            ->line('Password: ' . $this->password)
            ->action('Login Now', url('/login'))
            ->line('Please change your password after login.');
    }
}