<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class VerifyOtpRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'email' => 'required|string|email|exists:users,email',
            'otp' => 'required|string|size:6',
        ];
    }

    public function messages(): array
    {
        return [
            'email.exists' => 'User with this email does not exist.',
            'otp.size' => 'OTP must be exactly 6 digits.',
        ];
    }
}
