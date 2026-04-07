<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ClientPortalAccessNotification extends Notification 
{
    use Queueable;

    // Default password is now hardcoded as per requirement
    private const DEFAULT_PASSWORD = '123456789';

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('🎉 Welcome! Your Portal Access is Ready')
            ->greeting('Hello ' . $notifiable->first_name . ',')
            ->line('Great news! You can now log in to your client portal to access your purchased property details.')
            ->line('')
            ->line('🔑 Your Login Credentials:')
            ->line('Email: ' . $notifiable->email)
            ->line('Default Password: ' . self::DEFAULT_PASSWORD)
            ->line('')
            ->line('⚠️ For security, please change your password immediately after your first login.')
            ->line('')
            ->line('🎬 New to the platform? Watch our quick explainer video to learn how to navigate your portal:')
            ->line('')
            ->line('📺 Click to Watch Explainer Video', 'https://www.loom.com/share/799c102e23c7477f9612d68b52652b11')
            ->action('🚀 Login to Your Portal', 'https://portal.gtextland.com/sign-in')
            ->line('If you have any questions, reply to this email or contact our support team.')
            ->salutation('Best regards')
            ->line('The Gtext Team');
    }
}