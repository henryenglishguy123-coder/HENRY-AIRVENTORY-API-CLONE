<?php

namespace App\Http\Requests\Api\V1\Factory;

use Illuminate\Foundation\Http\FormRequest;

class VerifyEmailRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Validation rules.
     */
    public function rules(): array
    {
        return [
            'email' => 'required|email|exists:factory_users,email',
            'otp' => 'required|string|size:6',
        ];
    }

    /**
     * Custom messages.
     */
    public function messages(): array
    {
        return [
            'email.required' => __('Email is required.'),
            'email.email' => __('Please enter a valid email address.'),
            'email.exists' => __('Email not found.'),

            'otp.required' => __('Verification code is required.'),
            'otp.string' => __('Verification code must be a valid string.'),
            'otp.size' => __('Verification code must be 6 digits.'),
        ];
    }
}
