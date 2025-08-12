<!DOCTYPE html>
<html>
<head>
    <title>{{ $type === 'email_verification' ? 'Email Verification' : 'OTP Code' }}</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .container { max-width: 600px; margin: 0 auto; padding: 20px; }
        .otp-code { font-size: 32px; font-weight: bold; text-align: center; 
                   background: #f4f4f4; padding: 20px; border-radius: 8px; 
                   letter-spacing: 8px; color: #007bff; }
        .warning { color: #dc3545; font-size: 14px; margin-top: 20px; }
    </style>
</head>
<body>
    
    <div class="container">
        <h2>{{ $type === 'email_verification' ? 'Email Verification' : 'OTP Code' }}</h2>
        
        <p>Your verification code is:</p>
        
        <div class="otp-code">{{ $otpCode }}</div>
        
        <p>This code will expire in 10 minutes.</p>
        
        <p class="warning">
            <strong>Security Notice:</strong> Never share this code with anyone. 
            Our team will never ask for your OTP code.
        </p>
        
        <p>If you didn't request this code, please ignore this email.</p>
    </div>
</body>
</html>