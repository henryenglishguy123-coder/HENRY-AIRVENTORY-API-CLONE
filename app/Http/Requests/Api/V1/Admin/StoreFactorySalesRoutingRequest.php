<?php

namespace App\Http\Requests\Api\V1\Admin;

use Illuminate\Foundation\Http\FormRequest;

class StoreFactorySalesRoutingRequest extends FormRequest
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
            'factory_id' => ['required', 'exists:factory_users,id'],
            'priority' => ['required', 'integer', 'min:1'],
            'country_ids' => ['required', 'array', 'min:1'],
            'country_ids.*' => ['exists:countries,id'],
        ];
    }
}
