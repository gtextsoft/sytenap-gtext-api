<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Document;

class LegalDocumentSentNotification extends Notification
{
    use Queueable;



    public function __construct(public Document $document) {
         $this->document = $document;
    }

    public function via($notifiable)
    {
        return ['mail'];
    }

    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->subject('A Legal Document Has Been Sent to You')
            ->greeting('Hello ' . $notifiable->first_name)
            ->line('A legal document has been sent to you for review.')
            ->line('Document Title: ' . $this->document->title)
            ->when($this->document->comment, function ($mail) {
                $mail->line('Legal Comment:')
                     ->line($this->document->comment);
            })
            ->action('Download Document', route('documents.download', $this->document->id))
            ->line('You may also log in to your dashboard to access this document.')
            ->salutation('Legal Team');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            //
        ];
    }
}



