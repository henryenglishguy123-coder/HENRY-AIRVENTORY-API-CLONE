<?php

namespace App\Http\Requests\Admin\Catalog\Product;

use Illuminate\Foundation\Http\FormRequest;

class StoreProductRequest extends FormRequest
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
     */
    public function rules(): array
    {
        return [
            // --- Basic Information ---
            'name' => ['required', 'string', 'max:255'],
            'sku' => ['required', 'string', 'max:100', 'unique:catalog_products,sku'],
            'category_id' => ['required', 'integer', 'exists:catalog_categories,id'],
            'status' => ['required', 'in:0,1'],
            'price' => ['required', 'numeric', 'gt:0.0001'],
            'sale_price' => ['nullable', 'numeric', 'gt:0.001', 'lt:price'],
            'weight' => ['required', 'numeric', 'gt:0'],
            'stock_status' => ['required', 'in:0,1'],
            'manage_inventory' => ['nullable', 'boolean'],
            'quantity' => ['exclude_unless:manage_inventory,1', 'required', 'integer', 'min:0'],
            'attributes' => ['nullable', 'array'],
            'short_description' => ['nullable', 'string'],
            'description' => ['nullable', 'string'],
            'meta_title' => ['nullable', 'string', 'max:60'],
            'meta_description' => ['nullable', 'string', 'max:160'],
            'gallery' => ['nullable', 'json'],
            'variants' => ['required', 'array', 'min:1'],
            'variants.*.name' => ['required', 'string', 'max:255'],
            'variants.*.sku' => ['required', 'distinct', 'string', 'max:100'],
            'variants.*.stock' => ['nullable', 'integer', 'min:0'],
            'variants.*.weight' => ['nullable', 'numeric', 'min:0'],
            'variants.*.attributes' => ['required', 'array'], // Ensure variant has connected attributes
        ];
    }

    /**
     * Custom error messages.
     */
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
            'quantity.required' => __('Total quantity is required when inventory tracking is enabled.'),
            'quantity.integer' => __('Quantity must be a whole number.'),
            'variants.required' => __('You must generate at least one product variant.'),
            'variants.min' => __('Please generate at least one product variant to proceed.'),
            'variants.*.sku.required' => __('Every variant must have a SKU.'),
            'variants.*.sku.distinct' => __('Duplicate SKUs found in your variants. Each variant must have a unique SKU.'),
            'variants.*.stock.integer' => __('Variant stock must be a whole number.'),
        ];
    }

    public function attributes(): array
    {
        $attributes = [
            'category_id' => __('Category'),
            'manage_inventory' => __('Inventory Tracking'),
            'sale_price' => __('Sale Price'),
            'stock_status' => __('Stock Status'),
            'attributes' => __('Specifications'),
        ];
        if ($this->has('variants') && is_array($this->variants)) {
            foreach ($this->variants as $key => $val) {
                $num = $key + 1;
                $attributes["variants.{$key}.sku"] = __("Variant #{$num} SKU");
                $attributes["variants.{$key}.price"] = __("Variant #{$num} Price");
                $attributes["variants.{$key}.sale_price"] = __("Variant #{$num} Sale Price");
                $attributes["variants.{$key}.stock"] = __("Variant #{$num} Stock");
                $attributes["variants.{$key}.weight"] = __("Variant #{$num} Weight");
            }
        }

        return $attributes;
    }

    /**
     * Prepare data for validation.
     */
    protected function prepareForValidation()
    {
        $this->merge([
            'manage_inventory' => $this->boolean('manage_inventory'),
        ]);
        if ($this->has('sale_price') && $this->sale_price === '') {
            $this->merge(['sale_price' => null]);
        }
        if ($this->has('variants') && is_array($this->variants)) {
            $variants = $this->variants;
            foreach ($variants as $key => $variant) {
                if (isset($variant['sale_price']) && $variant['sale_price'] === '') {
                    $variants[$key]['sale_price'] = null;
                }
            }
            $this->merge(['variants' => $variants]);
        }
    }
}
