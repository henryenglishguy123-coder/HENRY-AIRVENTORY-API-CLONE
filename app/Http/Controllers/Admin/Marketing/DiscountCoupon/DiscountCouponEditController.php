<?php

namespace App\Http\Controllers\Admin\Marketing\DiscountCoupon;

use App\Http\Controllers\Controller;
use App\Models\Marketing\Discount\DiscountCoupon;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class DiscountCouponEditController extends Controller
{
    public function edit($id)
    {
        $discountCoupon = DiscountCoupon::with([
            'products:id',
            'products.info:catalog_product_id,name',
            'categories:id',
            'categories.meta:catalog_category_id,name',
            'suppliers:id',
            'suppliers.business:factory_id,company_name',
            'customers:id,first_name',
        ])->findOrFail($id);

        $formatSelect2Items = function (Collection $items, string $idField = 'id', string $textField = 'name') {
            return $items->map(function ($item) use ($idField, $textField) {
                $getValue = function ($object, $field) {
                    foreach (explode('.', $field) as $part) {
                        if (! isset($object->$part)) {
                            return null;
                        }
                        $object = $object->$part;
                    }

                    return $object;
                };

                return [
                    'id' => $getValue($item, $idField),
                    'text' => $getValue($item, $textField),
                ];
            })->filter(fn ($entry) => ! empty($entry['text']))->values();
        };

        $preselectedProducts = $formatSelect2Items($discountCoupon->products ?? collect(), 'id', 'info.name');
        $preselectedCategories = $formatSelect2Items($discountCoupon->categories ?? collect(), 'id', 'meta.name');
        $preselectedSuppliers = $formatSelect2Items($discountCoupon->suppliers ?? collect(), 'id', 'business.company_name');
        $preselectedCustomers = $formatSelect2Items($discountCoupon->customers ?? collect(), 'id', 'first_name');
        $applyTo = $discountCoupon->apply_to ?? null;
        if (! $applyTo) {
            if ($preselectedProducts->count() > 0) {
                $applyTo = 'products';
            } elseif ($preselectedCategories->count() > 0) {
                $applyTo = 'categories';
            } elseif ($preselectedSuppliers->count() > 0) {
                $applyTo = 'suppliers';
            }
        }
        $min_requirement_type = 'none';
        if ($discountCoupon->min_qty > 0) {
            $min_requirement_type = 'quantity';
        } elseif ($discountCoupon->min_price > 0) {
            $min_requirement_type = 'value';
        }
        $discountCoupon->min_requirement_type = $min_requirement_type;

        return view('admin.marketing.discount-coupon.edit', compact(
            'discountCoupon',
            'preselectedProducts',
            'preselectedCategories',
            'preselectedSuppliers',
            'preselectedCustomers',
            'applyTo',
        ));
    }

    public function update(Request $request, $id)
    {
        $coupon = DiscountCoupon::findOrFail($id);

        if ($request->filled('end_date') && ! $request->filled('end_time')) {
            $request->merge(['end_time' => '00:00']);
        }

        // Build rules with conditional max_uses_per_customer validation
        $rules = [
            'title' => ['required', 'string', 'max:100'],
            'code' => [
                'required',
                'string',
                'max:20',
                'regex:/^[A-Z0-9]+$/',
                Rule::unique('discount_coupons', 'code')->ignore($coupon->id),
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
            'customers' => [Rule::requiredIf($request->eligibility === 'Specific Customers'), 'array'],
            'min_requirement_type' => ['required', Rule::in(['none', 'quantity', 'value'])],
            'min_qty' => [Rule::requiredIf($request->min_requirement_type === 'quantity'), 'nullable', 'integer', 'min:1'],
            'min_price' => [Rule::requiredIf($request->min_requirement_type === 'value'), 'nullable', 'numeric', 'min:0.01'],
            'start_date' => ['required', 'date'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'status' => ['required', Rule::in(['Active', 'Inactive'])],
            'max_uses' => ['nullable', 'integer', 'min:1'],
            'max_uses_per_customer' => ['nullable', 'integer', 'min:1'],
            'apply_to_radio' => [
                Rule::requiredIf(fn () => $request->discount_type === 'Product' && ($request->filled('products') || $request->filled('categories') || $request->filled('suppliers'))),
                Rule::in(['products', 'categories', 'suppliers']),
            ],
            'products' => [Rule::requiredIf(fn () => $request->discount_type === 'Product' && $request->apply_to_radio === 'products'), 'array'],
            'categories' => [Rule::requiredIf(fn () => $request->discount_type === 'Product' && $request->apply_to_radio === 'categories'), 'array'],
            'suppliers' => [Rule::requiredIf(fn () => $request->discount_type === 'Product' && $request->apply_to_radio === 'suppliers'), 'array'],
        ];

        // Add conditional lte:max_uses validation only if max_uses is provided
        if ($request->filled('max_uses')) {
            $rules['max_uses_per_customer'][] = 'lte:max_uses';
        }

        $validated = $request->validate($rules, [
            'code.unique' => __('This coupon code is already taken.'),
            'code.regex' => __('The code may only contain uppercase letters and numbers.'),
            'amount_type.in' => __('For order-level discounts, only percentage is allowed.'),
            'amount_value.max' => __('Percentage cannot exceed 100.'),
            'apply_to_radio.required' => __('Please select where the discount applies (Products, Categories, or Suppliers) if applying to specific items.'),
            'products.required' => __('Please select at least one product.'),
            'categories.required' => __('Please select at least one category.'),
            'suppliers.required' => __('Please select at least one supplier.'),
            'end_time.required' => __('The end time is required if an end date is set.'),
            'max_uses_per_customer.lte' => __('Max uses per customer cannot be greater than total max uses.'),
        ]);

        // # Cleaning up data for storage

        // 1. Handle Product vs. Order discount type clean up
        if ($request->discount_type === 'Product') {
            if (isset($validated['apply_to_radio'])) {
                if ($validated['apply_to_radio'] === 'products') {
                    $validated['categories'] = $validated['suppliers'] = [];
                } elseif ($validated['apply_to_radio'] === 'categories') {
                    $validated['products'] = $validated['suppliers'] = [];
                } elseif ($validated['apply_to_radio'] === 'suppliers') {
                    $validated['products'] = $validated['categories'] = [];
                }
            }
        } else {
            $validated['products'] = $validated['categories'] = $validated['suppliers'] = [];
        }

        $validated['apply_to'] = $request->apply_to_radio ?? null;
        unset($validated['apply_to_radio']);

        // 2. Handle Minimum Requirement clean up
        if ($request->min_requirement_type === 'none') {
            $validated['min_qty'] = null;
            $validated['min_price'] = null;
        } elseif ($request->min_requirement_type === 'quantity') {
            $validated['min_price'] = null;
        } elseif ($request->min_requirement_type === 'value') {
            $validated['min_qty'] = null;
        }
        unset($validated['min_requirement_type']);

        // 3. Handle Eligibility clean up
        if ($request->eligibility === 'All Customers') {
            $validated['customers'] = [];
        }
        // $validated['customers'] field is used for syncing, but we need to ensure it's not present if not specific
        if (! isset($validated['customers'])) {
            $validated['customers'] = [];
        }

        // 4. Combine Date and Time fields for storage
        $validated['start_date'] = "{$validated['start_date']} {$validated['start_time']}";
        $validated['end_date'] = $request->filled(['end_date', 'end_time'])
             ? "{$request->end_date} {$request->end_time}"
             : null;
        unset($validated['start_time'], $validated['end_time']);

        // Extract relationship data before removing from validated
        $productsData = $validated['products'] ?? [];
        $categoriesData = $validated['categories'] ?? [];
        $suppliersData = $validated['suppliers'] ?? [];
        $customersData = $validated['customers'] ?? [];

        // 5. Remove relationship keys from validated array before update
        $validated = Arr::except($validated, ['products', 'categories', 'suppliers', 'customers']);

        // 6. Execute update and all sync operations in a transaction
        return DB::transaction(function () use ($coupon, $validated, $request, $productsData, $categoriesData, $suppliersData, $customersData) {
            // Update the main coupon record
            $coupon->update($validated);

            // Sync Product-related relationships using validated data
            if ($request->discount_type === 'Product' && isset($coupon->apply_to)) {
                if ($coupon->apply_to === 'products') {
                    $coupon->products()->sync($productsData);
                    $coupon->categories()->detach();
                    $coupon->suppliers()->detach();
                } elseif ($coupon->apply_to === 'categories') {
                    $coupon->categories()->sync($categoriesData);
                    $coupon->products()->detach();
                    $coupon->suppliers()->detach();
                } elseif ($coupon->apply_to === 'suppliers') {
                    $coupon->suppliers()->sync($suppliersData);
                    $coupon->products()->detach();
                    $coupon->categories()->detach();
                }
            } else {
                $coupon->products()->detach();
                $coupon->categories()->detach();
                $coupon->suppliers()->detach();
            }

            if ($request->eligibility === 'Specific Customers') {
                $coupon->customers()->sync($customersData);
            } else {
                $coupon->customers()->detach();
            }

            return response()->json(['success' => true]);
        });
    }
}
