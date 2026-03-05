<?php

namespace App\Http\Requests\Api\V1\Customer\Address;

use Illuminate\Foundation\Http\FormRequest;

class ShippingAddressRequest extends FormRequest
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
        return [
            'first_name' => 'nullable|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'nullable|email',
            'phone' => 'nullable|string|max:20',
            'address' => 'required|string|max:255',
            'country_id' => 'required|integer|exists:countries,id',
            'state_id' => 'nullable|integer|exists:states,id',
            'city' => 'required|string|max:100',
            'postal_code' => 'required|string|max:10',
            'is_default' => 'nullable|boolean',
        ];
    }

    public function messages(): array
    {
        return [
            // names
            'first_name.string' => __('First name must be valid text.'),
            'first_name.max' => __('First name may not exceed 100 characters.'),

            'last_name.string' => __('Last name must be valid text.'),
            'last_name.max' => __('Last name may not exceed 100 characters.'),

            // email & phone
            'email.email' => __('Please enter a valid email address.'),
            'phone.string' => __('Phone number must be valid text.'),
            'phone.max' => __('Phone number may not exceed 20 characters.'),

            // address
            'address.required' => __('Address is required.'),
            'address.string' => __('Address must be valid text.'),
            'address.max' => __('Address may not exceed 255 characters.'),

            // country
            'country_id.required' => __('Country is required.'),
            'country_id.integer' => __('Country ID must be an integer.'),
            'country_id.exists' => __('Selected country is invalid.'),

            // state
            'state_id.integer' => __('State ID must be an integer.'),
            'state_id.exists' => __('Selected state is invalid.'),

            // city
            'city.required' => __('City is required.'),
            'city.string' => __('City must be valid text.'),
            'city.max' => __('City may not exceed 100 characters.'),

            // postal code
            'postal_code.required' => __('Postal code is required.'),
            'postal_code.string' => __('Postal code must be valid text.'),
            'postal_code.max' => __('Postal code may not exceed 10 characters.'),

            // default
            'is_default.boolean' => __('Default flag must be true or false.'),
        ];
    }
}
