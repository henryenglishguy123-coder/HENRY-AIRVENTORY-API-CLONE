<?php

namespace App\Http\Requests\Api\V1\Factory;

use Illuminate\Foundation\Http\FormRequest;

class ForgotPasswordRequest extends FormRequest
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
            'email' => ['required', 'email', 'exists:factory_users,email'],
        ];
    }

    /**
     * Custom messages.
     */
    public function messages(): array
    {
        return [
            'email.required' => __('The email address is required.'),
            'email.email' => __('Please enter a valid email address.'),
            'email.exists' => __('We can\'t find a factory with that email address.'),
        ];
    }
}
