<?php

namespace App\Http\Requests\Api\V1\Factory;

use Illuminate\Foundation\Http\FormRequest;

class RegistrationRequest extends FormRequest
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
            'firstname' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|email|unique:factory_users,email',
            'phone' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
            'password_confirmation' => 'required|string|min:8',
            'industry_id' => 'required|exists:catalog_industries,id',
        ];
    }

    /**
     * Custom messages.
     */
    public function messages(): array
    {
        return [
            'firstname.required' => __('First name is required.'),
            'firstname.string' => __('First name must be a valid string.'),
            'firstname.max' => __('First name cannot be longer than 255 characters.'),

            'lastname.required' => __('Last name is required.'),
            'lastname.string' => __('Last name must be a valid string.'),
            'lastname.max' => __('Last name cannot be longer than 255 characters.'),

            'email.required' => __('Email is required.'),
            'email.email' => __('Please enter a valid email address.'),
            'email.unique' => __('This email is already registered.'),

            'phone.required' => __('Phone is required.'),
            'phone.string' => __('Phone must be a valid string.'),

            'password.required' => __('Password is required.'),
            'password.string' => __('Password must be a valid string.'),
            'password.min' => __('Password must be at least 8 characters long.'),
            'password.confirmed' => __('Passwords do not match.'),

            'password_confirmation.required' => __('Please confirm your password.'),
            'password_confirmation.string' => __('Confirmation must be a valid string.'),
            'password_confirmation.min' => __('Confirmation password must be at least 8 characters.'),

            'industry_id.required' => __('Industry is required.'),
            'industry_id.exists' => __('Selected industry does not exist.'),
        ];
    }
}
