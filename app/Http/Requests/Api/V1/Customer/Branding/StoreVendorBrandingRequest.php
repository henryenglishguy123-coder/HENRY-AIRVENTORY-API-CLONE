<?php

namespace App\Http\Requests\Api\V1\Customer\Branding;

use Illuminate\Foundation\Http\FormRequest;

class StoreVendorBrandingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->guard('customer')->check();
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'type' => ['nullable', 'string', 'in:branding,packaging_label,hang_tag,neck_tag'],
            'image' => [
                'required',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
            'back_image' => [
                'required_if:type,packaging_label,hang_tag',
                'file',
                'mimes:jpg,jpeg,png,webp',
                'max:10240',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('Please enter a name for your branding.'),
            'name.string' => __('The branding name must be a valid string.'),
            'name.max' => __('The branding name cannot exceed 255 characters.'),
            'image.required' => __('Please upload a branding image.'),
            'image.file' => __('The uploaded content must be a valid file.'),
            'image.mimes' => __('The image must be a file of type: jpg, jpeg, png or webp.'),
            'image.max' => __('The image size must not exceed 10MB.'),
            'back_image.required_if' => __('Please upload a back image for packaging labels and hang tags.'),
            'back_image.file' => __('The back image must be a valid file.'),
            'back_image.mimes' => __('The back image must be a file of type: jpg, jpeg, png or webp.'),
            'back_image.max' => __('The back image size must not exceed 10MB.'),
        ];
    }
}
