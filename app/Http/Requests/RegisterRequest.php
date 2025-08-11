<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Http\Exceptions\HttpResponseException;

class RegisterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

   public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:8|confirmed',
            'state' => 'required|string|max:255',
            'country' => 'required|string|max:255',
        ];
    }

    public function messages(): array
    {
        return [
            // First Name validation messages
            'first_name.required' => 'First name is required.',
            'first_name.string' => 'First name must be a valid text.',
            'first_name.max' => 'First name cannot exceed 255 characters.',
            
            // Last Name validation messages
            'last_name.required' => 'Last name is required.',
            'last_name.string' => 'Last name must be a valid text.',
            'last_name.max' => 'Last name cannot exceed 255 characters.',
            
            // Email validation messages
            'email.required' => 'Email address is required.',
            'email.string' => 'Email must be a valid text.',
            'email.email' => 'Please enter a valid email address.',
            'email.max' => 'Email address cannot exceed 255 characters.',
            'email.unique' => 'This email address is already registered.',
            
            // Password validation messages
            'password.required' => 'Password is required.',
            'password.string' => 'Password must be a valid text.',
            'password.min' => 'Password must be at least 8 characters long.',
            'password.confirmed' => 'Password confirmation does not match.',
            
            // State validation messages
            'state.required' => 'State is required.',
            'state.string' => 'State must be a valid text.',
            'state.max' => 'State cannot exceed 255 characters.',
            
            // Country validation messages
            'country.required' => 'Country is required.',
            'country.string' => 'Country must be a valid text.',
            'country.max' => 'Country cannot exceed 255 characters.',
        ];
    }

    /**
     * Handle a failed validation attempt.
     *
     * @param  \Illuminate\Contracts\Validation\Validator  $validator
     * @return void
     *
     * @throws \Illuminate\Http\Exceptions\HttpResponseException
     */
    protected function failedValidation(Validator $validator)
    {
        throw new HttpResponseException(response()->json([
            'success' => false,
            'message' => 'Validation failed',
            'errors' => $validator->errors(),
            'data' => null
        ], 422));
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array
     */
    public function attributes(): array
    {
        return [
            'first_name' => 'first name',
            'last_name' => 'last name',
            'email' => 'email address',
            'password' => 'password',
            'password_confirmation' => 'password confirmation',
            'state' => 'state',
            'country' => 'country',
        ];
    }
}