<?php

namespace App\Services;

use App\Models\Otp;
use App\Mail\OtpMail;
use Illuminate\Support\Facades\Mail;
use Carbon\Carbon;

class OtpService
{
    const OTP_EXPIRY_MINUTES = 10;
    const OTP_LENGTH = 6;
    
    /**
     * Generate and send OTP
     */
    public function generateAndSendOtp(string $email, string $type = 'email_verification'): array
    {
        // Delete any existing OTPs for this email and type
        $this->deleteExistingOtps($email, $type);
        
        // Generate new OTP
        $otpCode = $this->generateOtpCode();
        
        // Save OTP to database
        $otp = Otp::create([
            'email' => $email,
            'otp' => $otpCode,
            'type' => $type,
            'expires_at' => Carbon::now()->addMinutes(self::OTP_EXPIRY_MINUTES),
        ]);
        
        // Send OTP via email
        Mail::to($email)->send(new OtpMail($otpCode, $type));
        
        return [
            'success' => true,
            'message' => 'OTP sent successfully',
            'expires_in_minutes' => self::OTP_EXPIRY_MINUTES
        ];
    }
    
    /**
     * Verify OTP
     */
    public function verifyOtp(string $email, string $otpCode, string $type = 'email_verification'): array
    {
        $otp = Otp::where('email', $email)
                  ->where('otp', $otpCode)
                  ->where('type', $type)
                  ->valid()
                  ->first();
        
        if (!$otp) {
            return [
                'success' => false,
                'message' => 'Invalid or expired OTP'
            ];
        }
        
        // Mark OTP as used
        $otp->markAsUsed();
        
        return [
            'success' => true,
            'message' => 'OTP verified successfully'
        ];
    }
    
    /**
     * Generate random OTP code
     */
    private function generateOtpCode(): string
    {
        return str_pad(random_int(0, 999999), self::OTP_LENGTH, '0', STR_PAD_LEFT);
    }
    
    /**
     * Delete existing OTPs for email and type
     */
    private function deleteExistingOtps(string $email, string $type): void
    {
        Otp::where('email', $email)->where('type', $type)->delete();
    }
    
    /**
     * Clean up expired OTPs (can be used in scheduled jobs)
     */
    public function cleanupExpiredOtps(): int
    {
        return Otp::where('expires_at', '<', now())->delete();
    }
}