<?php

namespace App\Services;

use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use App\Models\MailLog;
// optional, used if you create mail log feature

class MailService {
    /**
    * Send any mailable to one or many recipients.
    *
    * @param string|array $recipients Email or array of emails
    * @param Mailable $mailable      Instance of a Mailable
    * @param array $options         [ 'queue' => bool, 'cc' => [], 'bcc' => [], 'from' => [ 'address' => '', 'name' => '' ] ]
    * @return bool                  True on success, false on failure
    */
    public static function send( $recipients, Mailable $mailable, array $options = [] ): bool {
        $recipients = ( array ) $recipients;
        $queue = $options[ 'queue' ] ?? false;
        $cc = $options[ 'cc' ] ?? [];
        $bcc = $options[ 'bcc' ] ?? [];
        $from = $options[ 'from' ] ?? null;

        try {
            foreach ( $recipients as $email ) {
                $mailer = Mail::to( $email );

                if ( !empty( $cc ) ) {
                    $mailer = $mailer->cc( $cc );
                }
                if ( !empty( $bcc ) ) {
                    $mailer = $mailer->bcc( $bcc );
                }
                if ( $from && isset( $from[ 'address' ] ) ) {
                    $mailable->from( $from[ 'address' ], $from[ 'name' ] ?? null );
                }

                if ( $queue ) {
                    $mailer->queue( $mailable );
                } else {
                    $mailer->send( $mailable );
                }
            }

            return true;
        } catch ( \Exception $e ) {
            Log::error( 'Mail sending failed', [
                'recipients' => $recipients,
                'mail_type' => get_class( $mailable ),
                'error' => $e->getMessage(),
                'time' => now()->toDateTimeString(),
            ] );

            return false;
        }
    }

    /**
    * Attempt to extract subject from mailable ( best-effort ).
    */
    protected static function extractSubject( Mailable $mailable ): ?string {
        // If the mailable has a public property 'subject', use it
        if ( property_exists( $mailable, 'subject' ) && !empty( $mailable->subject ) ) {
            return $mailable->subject;
        }

        // If there is a subject method or a build returns it, fallback to null
        return null;
    }
}
