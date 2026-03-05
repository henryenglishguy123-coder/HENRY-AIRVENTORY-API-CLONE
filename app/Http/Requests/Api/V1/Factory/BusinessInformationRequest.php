<?php

namespace App\Http\Requests\Api\V1\Factory;

use Illuminate\Foundation\Http\FormRequest;

class BusinessInformationRequest extends FormRequest
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
            'company_name' => 'required|string|max:255',
            'registration_number' => 'nullable|string|max:55',
            'tax_vat_number' => 'nullable|string|max:55',
            'registered_address' => 'required|string|max:255',
            'country_id' => 'required|integer|exists:countries,id',
            'state_id' => 'nullable|integer|exists:states,id',
            'city' => 'required|string|max:255',
            'postal_code' => 'required|string|max:10',
            'registration_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'tax_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'import_export_certificate' => 'nullable|file|mimes:pdf,jpg,jpeg,png|max:5120',
            'shipping_partner_id' => 'nullable|integer|exists:shipping_partners,id',
        ];

        // If user is admin, factory_id is required in the request
        if (auth()->guard('admin_api')->check()) {
            $rules['factory_id'] = 'required|integer|exists:factory_users,id';
        }

        return $rules;
    }

    /**
     * Get custom error messages for validation.
     */
    public function messages(): array
    {
        return [
            'company_name.required' => __('Company name is required.'),
            'registered_address.required' => __('Registered address is required.'),
            'country_id.required' => __('Country is required.'),
            'country_id.exists' => __('Selected country is invalid.'),
            'state_id.exists' => __('Selected state is invalid.'),
            'city.required' => __('City is required.'),
            'postal_code.required' => __('Postal code is required.'),
            'registration_certificate.mimes' => __('Registration certificate must be a PDF, JPG, JPEG, or PNG file.'),
            'registration_certificate.max' => __('Registration certificate must not exceed 5MB.'),
            'tax_certificate.mimes' => __('Tax certificate must be a PDF, JPG, JPEG, or PNG file.'),
            'tax_certificate.max' => __('Tax certificate must not exceed 5MB.'),
            'import_export_certificate.mimes' => __('Import/Export certificate must be a PDF, JPG, JPEG, or PNG file.'),
            'import_export_certificate.max' => __('Import/Export certificate must not exceed 5MB.'),
            'factory_id.required' => __('Factory ID is required when admin is updating business information.'),
            'factory_id.exists' => __('Selected factory does not exist.'),
        ];
    }
}
