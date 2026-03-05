<?php

namespace App\Http\Requests\Settings;

use Illuminate\Foundation\Http\FormRequest;

class CurrencySettingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check();
    }

    public function rules(): array
    {
        return [
            'default_currency_id' => [
                'required',
                'integer',
                'exists:currencies,id',
            ],
            'allowed_currency_ids' => [
                'required',
                'array',
                'min:1',
            ],

            'allowed_currency_ids.*' => [
                'integer',
                'exists:currencies,id',
            ],

            'fixer_io_api_status' => [
                'required',
                'in:0,1',
            ],
            'fixer_io_api_key' => [
                'required_if:fixer_io_api_status,1',
                'nullable',
                'string',
                'max:255',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'default_currency_id.required' => __('Please select a default currency.'),
            'default_currency_id.exists' => __('The selected currency does not exist.'),

            'allowed_currency_ids.required' => __('Please select at least one allowed currency.'),
            'allowed_currency_ids.min' => __('Please select at least one allowed currency.'),
            'allowed_currency_ids.*.exists' => __('One or more selected currencies are invalid.'),

            'fixer_io_api_status.required' => __('Please select Fixer API status.'),
            'fixer_io_api_status.in' => __('Invalid Fixer API status value.'),

            'fixer_io_api_key.required_if' => __('Fixer API key is required when Fixer API is enabled.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'default_currency_id' => __('Default Currency'),
            'allowed_currency_ids' => __('Allowed Currencies'),
            'fixer_io_api_status' => __('Fixer API status'),
            'fixer_io_api_key' => __('Fixer API key'),
        ];
    }
}
