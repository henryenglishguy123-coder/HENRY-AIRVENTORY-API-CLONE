<?php

namespace App\Http\Requests\Api\V1\Customer\Account;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'first_name' => ['nullable', 'string', 'max:100'],
            'last_name' => ['nullable', 'string', 'max:100'],
            'phone' => ['nullable', 'string', 'max:30'],
            'notify_email' => ['nullable', 'boolean'],
            'password' => [
                'nullable',
                'confirmed',
                'min:6',
            ],
            'fulfillment_type' => ['sometimes', 'nullable', 'in:auto,manual'],
            'allow_split_orders' => ['sometimes', 'nullable', 'boolean'],
            'timezone' => [
                'sometimes',
                'timezone',
                'max:100',
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'first_name.string' => __('First name must be a valid text.'),
            'first_name.max' => __('First name cannot be longer than 100 characters.'),

            'last_name.string' => __('Last name must be a valid text.'),
            'last_name.max' => __('Last name cannot be longer than 100 characters.'),

            'phone.string' => __('Phone number must be a valid text.'),
            'phone.max' => __('Phone number cannot exceed 30 characters.'),

            'notify_email.boolean' => __('Notify email value must be true or false.'),

            'password.confirmed' => __('Password and confirm password do not match.'),
            'password.min' => __('Password must be at least 6 characters long.'),
            'fulfillment_type.in' => __('Fulfillment type must be either auto or manual.'),
            'allow_split_orders.boolean' => __('The split orders setting must be on or off.'),
            'timezone.timezone' => __('Timezone must be a valid IANA timezone (e.g. Asia/Kolkata).'),
            'timezone.max' => __('Timezone cannot be longer than 100 characters.'),
        ];
    }

    protected function prepareForValidation()
    {
        $merges = [];

        if ($this->has('notify_email')) {
            $merges['notify_email'] = filter_var($this->notify_email, FILTER_VALIDATE_BOOLEAN);
        }

        if ($this->has('allow_split_orders')) {
            $merges['allow_split_orders'] = filter_var($this->allow_split_orders, FILTER_VALIDATE_BOOLEAN);
        }

        if (! empty($merges)) {
            $this->merge($merges);
        }
    }
}
