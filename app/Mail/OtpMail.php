<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class OtpMail extends Mailable
{
    use Queueable, SerializesModels;

    public $otpCode;
    public $type;

    public function __construct(string $otpCode, string $type = 'email_verification')
    {
        $this->otpCode = $otpCode;
        $this->type = $type;
    }

    public function build()
    {
        $subject = $this->type === 'email_verification' 
                  ? 'Email Verification OTP' 
                  : 'OTP Code';

        return $this->subject($subject)
                   ->view('emails.otp')
                   ->with([
                       'otpCode' => $this->otpCode,
                       'type' => $this->type
                   ]);
    }
}