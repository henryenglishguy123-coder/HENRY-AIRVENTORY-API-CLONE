<?php

namespace App\Http\Requests\Api\V1\Admin\Factory;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use App\Models\Catalog\Industry\CatalogIndustry;

class UpdateFactoryRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * @return bool
     */
    public function authorize()
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array
     */
    public function rules()
    {
        $factoryId = $this->route('factory'); // Assuming the route parameter is 'factory' or the ID

        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email',
                Rule::unique('factory_users', 'email')->ignore($factoryId)->whereNull('deleted_at'),
            ],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'password' => ['nullable', 'string', 'min:8'], // Password optional on update
            'industry_id' => ['required', 'integer', Rule::exists(CatalogIndustry::class, 'id')],
        ];
    }
}
