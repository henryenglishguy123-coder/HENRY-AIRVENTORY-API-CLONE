<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class SigninRequest extends FormRequest
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
            'email' => 'required|email',
            'password' => 'required|string|min:6',
            'remember' => 'sometimes|boolean',
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
            'password.required' => __('Password is required.'),
            'password.min' => __('Password must be at least :min characters.', ['min' => 6]),
        ];
    }
}
