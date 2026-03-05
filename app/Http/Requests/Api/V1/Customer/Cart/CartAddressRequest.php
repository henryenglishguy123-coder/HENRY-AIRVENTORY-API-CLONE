<?php

namespace App\Http\Requests\Api\V1\Customer\Cart;

use Illuminate\Foundation\Http\FormRequest;

class CartAddressRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => 'required|string|max:100',
            'last_name' => 'nullable|string|max:100',
            'email' => 'nullable|email',
            'phone' => 'required|string|max:20|regex:/^[0-9+\-\s()]+$/',
            'address_line_1' => 'required|string|max:255',
            'address_line_2' => 'nullable|string|max:255',
            'city' => 'required|string|max:150',
            'state' => 'nullable|string|max:150|required_without:state_id',
            'state_id' => 'nullable|integer|exists:states,id|required_without:state',
            'postal_code' => 'required|string|max:20',
            'country_id' => 'required|integer|exists:countries,id',
        ];
    }

    public function messages(): array
    {
        return [
            // First name
            'first_name.required' => __('First name is required.'),
            'first_name.string' => __('First name must be valid text.'),
            'first_name.max' => __('First name may not exceed 100 characters.'),

            // Last name
            'last_name.string' => __('Last name must be valid text.'),
            'last_name.max' => __('Last name may not exceed 100 characters.'),

            // Email
            'email.email' => __('Please enter a valid email address.'),

            // Phone
            'phone.required' => __('Phone number is required.'),
            'phone.string' => __('Phone number must be valid text.'),
            'phone.max' => __('Phone number may not exceed 20 characters.'),
            'phone.regex' => __('Phone number format is invalid.'),

            // Address line 1
            'address_line_1.required' => __('Address line 1 is required.'),
            'address_line_1.string' => __('Address line 1 must be valid text.'),
            'address_line_1.max' => __('Address line 1 may not exceed 255 characters.'),

            // Address line 2
            'address_line_2.string' => __('Address line 2 must be valid text.'),
            'address_line_2.max' => __('Address line 2 may not exceed 255 characters.'),

            // City
            'city.required' => __('City is required.'),
            'city.string' => __('City must be valid text.'),
            'city.max' => __('City may not exceed 150 characters.'),

            // State (text)
            'state.string' => __('State must be valid text.'),
            'state.max' => __('State may not exceed 150 characters.'),

            // State ID
            'state_id.integer' => __('State ID must be an integer.'),
            'state_id.exists' => __('Selected state is invalid.'),

            // Postal code
            'postal_code.required' => __('Postal code is required.'),
            'postal_code.string' => __('Postal code must be valid text.'),
            'postal_code.max' => __('Postal code may not exceed 20 characters.'),

            // Country ID
            'country_id.required' => __('Country is required.'),
            'country_id.integer' => __('Country ID must be an integer.'),
            'country_id.exists' => __('Selected country is invalid.'),
        ];
    }
}
