<?php

namespace App\Http\Requests\Api\V1\Factory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Auth;

class FactoryAddressRequest extends FormRequest
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
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $rules = [
            'type' => 'required|string|in:facility,dist_center',
            'address' => 'required|string|max:255',
            'country_id' => 'required|string|max:25',
            'state_id' => 'required|string|max:25',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:10',
        ];

        // For admin users, factory_id is required
        if (Auth::guard('admin_api')->check()) {
            $rules['factory_id'] = 'required|integer|exists:factory_users,id';
        }

        return $rules;
    }

    /**
     * Get custom messages for validator errors.
     */
    public function messages(): array
    {
        return [
            'type.in' => 'The type must be either facility or dist_center.',
            'factory_id.required' => 'Factory ID is required for admin users.',
            'factory_id.exists' => 'The specified factory does not exist.',
        ];
    }
}
