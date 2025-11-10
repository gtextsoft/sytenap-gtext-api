<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ResetPasswordMail extends Mailable {
    use Queueable, SerializesModels;

    public $recipient;
    public $newPassword;

    /**
    * Create a new message instance.
    */

    public function __construct( User $recipient, string $newPassword ) {
        $this->recipient = $recipient;
        $this->newPassword = $newPassword;
    }

    /**
    * Define the message envelope ( subject, etc ).
    */

    public function envelope(): Envelope {
        return new Envelope(
            subject: 'Your New Password',
        );
    }

    /**
    * Define the content and template.
    */

    public function content(): Content {
        return new Content(
            markdown: 'emails.reset-password',
            with: [
                'recipientName' => $this->recipient->name ?? 'User',
                'newPassword' => $this->newPassword,
            ],
        );
    }

    /**
    * Define attachments ( none here ).
    */

    public function attachments(): array {
        return [];
    }
}
