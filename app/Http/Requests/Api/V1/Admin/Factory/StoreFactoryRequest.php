<?php

namespace App\Http\Requests\Api\V1\Admin\Factory;

use Illuminate\Foundation\Http\FormRequest;

class StoreFactoryRequest extends FormRequest
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
        return [
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', \Illuminate\Validation\Rule::unique('factory_users', 'email')->whereNull('deleted_at')],
            'phone_number' => ['nullable', 'string', 'max:20'],
            'password' => ['required', 'string', 'min:8'],
            'industry_id' => ['required', 'integer', 'exists:catalog_industries,id'],
        ];
    }
}
