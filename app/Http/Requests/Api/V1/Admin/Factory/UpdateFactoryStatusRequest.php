<?php

namespace App\Http\Requests\Api\V1\Admin\Factory;

use Illuminate\Foundation\Http\FormRequest;

class UpdateFactoryStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return auth('admin_api')->check() || auth('admin')->check();
    }

    /**
     * Get the validation rules that apply to the request.
     */
    public function rules(): array
    {
        return [
            'account_status' => ['nullable', 'integer', 'in:0,1,2,3'],
            'account_verified' => ['nullable', 'integer', 'in:0,1,2,3,4'],
            'verify_email' => ['nullable', 'boolean'],
            'reason' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * Custom validation messages
     */
    public function messages(): array
    {
        return [
            'account_status.in' => 'The account status must be a valid status value.',
            'account_verified.in' => 'The verification status must be a valid status value.',
            'reason.max' => 'The reason cannot exceed 500 characters.',
        ];
    }

    /**
     * Prepare the data for validation
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('verify_email')) {
            $this->merge([
                'verify_email' => $this->boolean('verify_email'),
            ]);
        }
    }

    /**
     * Add custom validation logic after standard rules pass.
     */
    public function withValidator(\Illuminate\Validation\Validator $validator): void
    {
        $validator->after(function ($validator) {
            $hasAction = $this->filled('account_status')
                || $this->filled('account_verified')
                || $this->has('verify_email');

            if (! $hasAction) {
                $validator->errors()->add(
                    'general',
                    'At least one of account_status, account_verified, or verify_email must be provided.'
                );
            }
        });
    }

    /**
     * Get custom attributes for validator errors
     */
    public function attributes(): array
    {
        return [
            'account_status' => 'account status',
            'account_verified' => 'verification status',
            'verify_email' => 'email verification',
        ];
    }
}
