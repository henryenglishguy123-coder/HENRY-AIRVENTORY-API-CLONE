<?php

namespace App\Http\Requests\Admin\Settings\Web;

use Illuminate\Foundation\Http\FormRequest;

class WebSettingUpdateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'store_logo' => 'nullable|image|max:10240',
            'store_favicon' => 'nullable|image|max:10240',

            'store_name' => 'required|string|min:2|max:255',
            'mobile' => 'required|numeric|digits_between:10,15',

            'default_country_id' => 'required|exists:countries,id',

            'allowed_country_id' => 'required|array',
            'allowed_country_id.*' => 'exists:countries,id',

            'meta_title' => 'nullable|string|max:60',
            'meta_description' => 'nullable|string|max:160',
        ];
    }

    public function messages(): array
    {
        return [
            // Images
            'store_logo.image' => __('The logo must be a valid image.'),
            'store_logo.max' => __('The logo size may not be greater than 10MB.'),
            'store_favicon.image' => __('The favicon must be a valid image.'),
            'store_favicon.max' => __('The favicon size may not be greater than 10MB.'),

            // Store Information
            'store_name.required' => __('Store name is required.'),
            'store_name.min' => __('Store name must contain at least 2 characters.'),
            'store_name.max' => __('Store name must not exceed 255 characters.'),

            // Mobile
            'mobile.required' => __('Mobile number is required.'),
            'mobile.numeric' => __('Mobile number must contain only digits.'),
            'mobile.digits_between' => __('Mobile number must be between 10 to 15 digits.'),

            // Default Country
            'default_country_id.required' => __('Please select a default country.'),
            'default_country_id.exists' => __('The selected default country is invalid.'),

            // Allowed Countries
            'allowed_country_id.required' => __('Please select at least one allowed country.'),
            'allowed_country_id.array' => __('Allowed countries must be an array value.'),
            'allowed_country_id.*.exists' => __('One or more selected allowed countries are invalid.'),

            // Meta
            'meta_title.max' => __('Meta title may not exceed 60 characters.'),
            'meta_description.max' => __('Meta description may not exceed 160 characters.'),
        ];
    }
}
