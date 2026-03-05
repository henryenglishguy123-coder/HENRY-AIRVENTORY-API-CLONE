<?php

namespace App\Http\Requests\Api\V1\Factory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class UpdateAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        $rules = [
            'first_name' => 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone_number' => 'sometimes|string|max:20',
        ];

        // Admin-specific validations
        if ($this->isAdmin()) {
            $rules['factory_id'] = 'required|integer|exists:factory_users,id';
            $rules['email'] = 'sometimes|email|max:255';
        }

        return $rules;
    }

    /**
     * Get custom error messages
     */
    public function messages(): array
    {
        return [
            'factory_id.required' => 'Factory ID is required for admin users.',
            'factory_id.exists' => 'The specified factory does not exist.',
            'first_name.string' => 'First name must be a valid string.',
            'last_name.string' => 'Last name must be a valid string.',
            'phone_number.string' => 'Phone number must be a valid string.',
            'email.email' => 'Please provide a valid email address.',
        ];
    }

    /**
     * Check if authenticated user is admin
     */
    private function isAdmin(): bool
    {
        return Auth::guard('admin_api')->check();
    }
}
