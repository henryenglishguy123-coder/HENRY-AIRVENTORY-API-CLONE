<?php

namespace App\Http\Requests\Admin\Catalog\Product;

use Illuminate\Foundation\Http\FormRequest;

class ProductMediaUploadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth()->guard('admin')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'file' => 'required|file|mimes:jpeg,png,jpg,webp,mp4,mov,avi,webm|max:204800', // 200MB max
        ];
    }

    public function messages(): array
    {
        return [
            'file.max' => __('The file size must not exceed 200MB.'),
            'file.mimes' => __('The file must be a file of type: jpeg, png, jpg, webp, mp4, mov, avi, webm.'),
        ];
    }
}
