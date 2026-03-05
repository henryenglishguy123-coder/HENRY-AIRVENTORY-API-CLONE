<?php

namespace App\Http\Requests\Admin\Catalog\Product;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $productId = $this->route('product');
        // Normalize if model instance
        if (is_object($productId) && method_exists($productId, 'getKey')) {
            $productId = $productId->getKey();
        }

        $variants = $this->input('variants', []);
        $existingVariantIds = is_array($variants)
            ? collect($variants)->pluck('id')->filter()->all()
            : [];

        return [
            'name' => ['required', 'string', 'max:255'],
            'sku' => [
                'required',
                'string',
                'max:100',
                Rule::unique('catalog_products', 'sku')->ignore($productId),
            ],
            'category_id' => ['required', 'integer', 'exists:catalog_categories,id'],
            'status' => ['required', 'in:0,1'],
            'price' => ['required', 'numeric', 'gt:0.0001'],
            'sale_price' => ['nullable', 'numeric', 'gt:0.0001', 'lt:price'],
            'weight' => ['required', 'numeric', 'gt:0'],
            'stock_status' => ['required', 'in:0,1'],
            'manage_inventory' => ['nullable', 'boolean'],
            'quantity' => ['required_if:manage_inventory,1', 'nullable', 'integer', 'min:0'],
            'short_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:60'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'gallery' => ['nullable', 'json'],
            'attributes' => ['nullable', 'array'],
            'attributes.*' => ['nullable'],
            'variants' => ['nullable', 'array'],
            'variants.*.id' => ['nullable', 'integer'],
            'variants.*.name' => ['required_with:variants', 'string', 'max:255'],
            'variants.*.sku' => [
                'required_with:variants',
                'distinct',
                'string',
                'max:100',
                Rule::unique('catalog_products', 'sku')->where(function ($query) use ($productId, $existingVariantIds) {
                    $idsToIgnore = array_filter(array_merge([$productId], $existingVariantIds));
                    if (! empty($idsToIgnore)) {
                        $query->whereNotIn('id', $idsToIgnore);
                    }
                }),
            ],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.attributes' => ['required_with:variants', 'array'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => __('The product name is required.'),
            'sku.required' => __('The main SKU is required.'),
            'sku.unique' => __('This SKU is already used by another product.'),
            'category_id.required' => __('Please select a valid category.'),
            'price.required' => __('The regular price is required.'),
            'price.gt' => __('The regular price must be greater than 0.0001.'),
            'sale_price.gt' => __('The sale price must be greater than 0.001.'),
            'sale_price.lt' => __('The sale price cannot be higher than the regular price.'),
            'weight.required' => __('The product weight is required.'),
            'weight.gt' => __('Weight must be greater than 0.'),
            'quantity.required_if' => __('Total quantity is required when inventory tracking is enabled.'),
            'quantity.integer' => __('Quantity must be a whole number.'),
        ];
    }

    public function attributes(): array
    {
        return [
            'attributes' => __('Specifications'),
        ];
    }

    protected function prepareForValidation()
    {
        $this->merge([
            'manage_inventory' => $this->boolean('manage_inventory'),
        ]);

        if ($this->has('sale_price') && $this->sale_price === '') {
            $this->merge(['sale_price' => null]);
        }
    }
}
