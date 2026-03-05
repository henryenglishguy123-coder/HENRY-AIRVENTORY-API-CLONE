<?php

namespace App\Http\Requests\Api\V1\Customer;

use Illuminate\Foundation\Http\FormRequest;

class SignupRequest extends FormRequest
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
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'required|email|unique:vendors,email',
            'password' => 'required|string|min:6|confirmed',
            'password_confirmation' => 'required|string|min:6',
        ];
    }

    /**
     * Custom messages.
     */
    public function messages(): array
    {
        return [
            'first_name.required' => __('First name is required.'),
            'first_name.string' => __('First name must be a valid string.'),
            'first_name.max' => __('First name cannot be longer than 100 characters.'),

            'last_name.string' => __('Last name must be a valid string.'),
            'last_name.max' => __('Last name cannot be longer than 100 characters.'),

            'email.required' => __('Email is required.'),
            'email.email' => __('Please enter a valid email address.'),
            'email.unique' => __('This email is already registered.'),

            'password.required' => __('Password is required.'),
            'password.string' => __('Password must be a valid string.'),
            'password.min' => __('Password must be at least 6 characters long.'),
            'password.confirmed' => __('Passwords do not match.'),

            'password_confirmation.required' => __('Please confirm your password.'),
            'password_confirmation.string' => __('Confirmation must be a valid string.'),
            'password_confirmation.min' => __('Confirmation password must be at least 6 characters.'),
        ];
    }
}
