<?php

namespace App\Http\Requests\Api\V1\Customer;

use App\Enums\Order\OrderStatus;
use App\Enums\Order\PaymentStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CustomerDashboardRequest extends FormRequest
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
            'period' => [
                'nullable',
                Rule::in(['7_days', '30_days', '3_months', 'custom']),
            ],

            'start_date' => [
                'required_if:period,custom',
                'date',
                'before_or_equal:end_date',
            ],

            'end_date' => [
                'required_if:period,custom',
                'date',
                'after_or_equal:start_date',
            ],

            'currency' => [
                'nullable',
                'string',
                Rule::exists('currencies', 'code')->where('is_allowed', 1),
            ],

            'order_status' => [
                'nullable',
                'string',
                Rule::in(OrderStatus::values()),
            ],

            'payment_status' => [
                'nullable',
                'string',
                Rule::in(PaymentStatus::values()),
            ],
        ];
    }

    public function messages(): array
    {
        return [
            'period.in' => __('Invalid period selected.'),
            'start_date.required_if' => __('Start date is required for custom period.'),
            'end_date.required_if' => __('End date is required for custom period.'),
            'start_date.before_or_equal' => __('Start date must be before end date.'),
            'end_date.after_or_equal' => __('End date must be after start date.'),
        ];
    }
}
