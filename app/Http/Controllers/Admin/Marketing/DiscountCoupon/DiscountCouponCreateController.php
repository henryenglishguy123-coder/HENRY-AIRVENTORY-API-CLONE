<?php

namespace App\Http\Controllers\Admin\Marketing\DiscountCoupon;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Discount\DiscountCoupon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DiscountCouponCreateController extends Controller
{
    public function create()
    {
        return view('admin.marketing.discount-coupon.create');
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('discount_coupons', 'code'),
            ],
            'discount_type' => ['required', Rule::in(['Product', 'Order'])],
            'amount_type' => ['required', Rule::in(['Percentage'])],
            'amount_value' => [
                'required',
                'numeric',
                'min:0.01',
                function ($attribute, $value, $fail) use ($request) {
                    if ($request->amount_type === 'Percentage' && $value > 100) {
                        $fail(__('Percentage discount cannot exceed 100%'));
                    }
                },
            ],
            'eligibility' => ['required', Rule::in(['All Customers', 'Specific Customers'])],
            'customers' => ['required_if:eligibility,Specific Customers', 'array'],
            'customers.*' => ['exists:vendors,id'],
            'min_requirement_type' => ['required', Rule::in(['none', 'quantity', 'value'])],
            'min_qty' => ['required_if:min_requirement_type,quantity', 'nullable', 'numeric', 'min:1'],
            'min_price' => ['required_if:min_requirement_type,value', 'nullable', 'numeric', 'min:0.01'],
            'start_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'end_time' => ['required_with:end_date', 'nullable', 'date_format:H:i', function ($attribute, $value, $fail) use ($request) {
                if ($request->filled('end_date') && $request->end_date === $request->start_date && $request->filled('start_time')) {
                    $endTime = strtotime($request->end_time ?? '00:00');
                    $startTime = strtotime($request->start_time);
                    if ($endTime <= $startTime) {
                        $fail(__('End time must be after start time when the end date equals the start date.'));
                    }
                }
            }],
            'status' => ['required', Rule::in(['Active', 'Inactive', 'Expired'])],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_customer' => ['nullable', 'integer', 'min:1'],
            'apply_to_radio' => [
                Rule::requiredIf(fn () => $request->discount_type === 'Product'),
                Rule::in(['products', 'categories', 'suppliers']),
            ],
            'products' => [
                Rule::requiredIf(fn () => $request->discount_type === 'Product' && $request->apply_to_radio === 'products'),
                'array',
            ],
            'products.*' => ['exists:catalog_products,id'],
            'categories' => [
                Rule::requiredIf(fn () => $request->discount_type === 'Product' && $request->apply_to_radio === 'categories'),
                'array',
            ],
            'categories.*' => ['exists:catalog_categories,id'],
            'suppliers' => [
                Rule::requiredIf(fn () => $request->discount_type === 'Product' && $request->apply_to_radio === 'suppliers'),
                'array',
            ],
            'suppliers.*' => ['exists:factories,id'],
        ], [
            'code.unique' => __('This coupon code is already taken.'),
            'code.regex' => __('The code may only contain uppercase letters and numbers.'),
            'amount_value.max' => __('Percentage cannot exceed 100.'),
            'amount_type.in' => __('For order-level discounts, only percentage is allowed.'),
            'apply_to_radio.required' => __('Please select where the discount applies (Products, Categories, or Suppliers).'),
            'products.required' => __('Please select at least one product.'),
            'categories.required' => __('Please select at least one category.'),
            'suppliers.required' => __('Please select at least one supplier.'),
        ]);

        if ($request->discount_type === 'Product') {
            if ($request->apply_to_radio === 'products') {
                $validated['categories'] = $validated['suppliers'] = [];
            } elseif ($request->apply_to_radio === 'categories') {
                $validated['products'] = $validated['suppliers'] = [];
            } elseif ($request->apply_to_radio === 'suppliers') {
                $validated['products'] = $validated['categories'] = [];
            }
        } else {
            $validated['apply_to_radio'] = null;
            $validated['products'] = $validated['categories'] = $validated['suppliers'] = [];
        }

        if ($request->min_requirement_type === 'none') {
            $validated['min_qty'] = null;
            $validated['min_price'] = null;
        } elseif ($request->min_requirement_type === 'quantity') {
            $validated['min_price'] = null;
        } elseif ($request->min_requirement_type === 'value') {
            $validated['min_qty'] = null;
        }

        $validated['start_date'] = "{$validated['start_date']} {$validated['start_time']}";
        $validated['end_date'] = $request->end_date && $request->end_time
            ? "{$request->end_date} {$request->end_time}"
            : null;
        unset($validated['start_time'], $validated['end_time']);

        return DB::transaction(function () use ($validated, $request) {
            $coupon = DiscountCoupon::create($validated);

            if ($request->discount_type === 'Product') {
                if ($request->apply_to_radio === 'products' && $request->filled('products')) {
                    $coupon->products()->attach($validated['products'] ?? []);
                } elseif ($request->apply_to_radio === 'categories' && $request->filled('categories')) {
                    $coupon->categories()->attach($validated['categories'] ?? []);
                } elseif ($request->apply_to_radio === 'suppliers' && $request->filled('suppliers')) {
                    $coupon->suppliers()->attach($validated['suppliers'] ?? []);
                }
            }

            if ($request->eligibility === 'Specific Customers' && $request->filled('customers')) {
                $coupon->customers()->attach($validated['customers'] ?? []);
            }

            return response()->json(['success' => true]);
        });

    }
}
