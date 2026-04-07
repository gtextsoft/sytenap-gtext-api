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
            // ✅ Video button
            ->action('📺 Watch Explainer Video', 'https://www.loom.com/share/799c102e23c7477f9612d68b52652b11')
            // ✅ Login button
            //->action('🚀 Login to Your Portal', 'https://portal.gtextland.com/sign-in')
            ->line('')
            ->line('If you have any questions, contact us at cfu@gtexthome.com or call +234 703 193 0951. We’re here to help!')
            ->salutation('Best regards, The GTEXT Land Team');
    }
   
}