<?php

namespace App\Http\Requests\Api\V1\Factory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class SecondaryContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return Auth::guard('factory')->check() || Auth::guard('admin_api')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['nullable', 'email', 'max:255'],
            'phone_number' => ['required', 'string', 'max:20'],
        ];

        // Admin users must provide factory_id
        if (Auth::guard('admin_api')->check()) {
            $rules['factory_id'] = ['required', 'integer', 'exists:factory_users,id'];
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_name.required' => 'First name is required.',
            'last_name.required' => 'Last name is required.',
            'email.email' => 'Please provide a valid email address.',
            'phone_number.required' => 'Phone number is required.',
            'factory_id.required' => 'Factory ID is required for admin users.',
            'factory_id.exists' => 'The specified factory does not exist.',
        ];
    }
}
