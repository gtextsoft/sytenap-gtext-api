<?php

namespace App\Notifications;

use App\Models\Estate;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use Illuminate\Notifications\Messages\MailMessage;

class AdminEstateAssignedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public ?Estate $estate,
        public string $password
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('You have been assigned as an Estate Admin')
            ->greeting('Hello ' . $notifiable->first_name . ',')
            ->line('You have been created as an admin on our platform.')
            //->line('Estate Assigned: **' . $this->estate->title . '**')
            ->line(
                    $this->estate
                        ? 'Estate Assigned: ' . $this->estate->title
                        : 'Role Assigned: Legal Officer'
                )
            ->line('Login Details:')
            ->line('Email: ' . $notifiable->email)
            ->line('Password: ' . $this->password)
            ->line('Please log in and change your password immediately.')
            ->action('Login Now', url('/login'))
            ->salutation('Regards, ' . config('app.name'));
    }
}
