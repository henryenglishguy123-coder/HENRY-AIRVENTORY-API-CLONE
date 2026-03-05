<?php

namespace App\Http\Requests\Api\V1\Factory;

use Illuminate\Foundation\Http\FormRequest;

class SetPasswordRequest extends FormRequest
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
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ];
    }

    /**
     * Custom messages.
     */
    public function messages(): array
    {
        return [
            'password.required' => __('Password is required.'),
            'password.confirmed' => __('Password confirmation does not match.'),
            'password.min' => __('Password must be at least 8 characters.'),
        ];
    }
}
