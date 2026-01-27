<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\Document;

class ClientDocumentSentNotification extends Notification
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(public Document $document)
    {
        //
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Client Sent a Document')
            ->line('A client has sent you a document for review.')
            ->line('Title: ' . $this->document->title)
            ->line('Comment: ' . ($this->document->comment ?? 'None'))
            ->action(
                'Download Document',
                route('documents.download', $this->document->id)
            );
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
         return [
            'document_id' => $this->document->id,
            'title' => $this->document->title,
            'sent_by' => 'client',
        ];
    }
}
