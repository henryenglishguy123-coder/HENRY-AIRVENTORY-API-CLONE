<?php

namespace App\Http\Requests\Api\V1\Customer;

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
            'email' => ['required', 'email', 'exists:vendors,email'],
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
            'email.exists' => __('We can\'t find a user with that email address.'),
        ];
    }
}
