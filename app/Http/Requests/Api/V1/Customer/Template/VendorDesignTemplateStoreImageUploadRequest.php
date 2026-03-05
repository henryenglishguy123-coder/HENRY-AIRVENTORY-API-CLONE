<?php

namespace App\Http\Requests\Api\V1\Customer\Template;

use Illuminate\Foundation\Http\FormRequest;

class VendorDesignTemplateStoreImageUploadRequest extends FormRequest
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
            'store_id' => ['required', 'integer', 'exists:vendor_connected_stores,id'],
            'image' => ['required', 'image', 'mimes:jpeg,png,jpg,webp', 'max:10240'], // 10MB max
        ];
    }
}
