<?php

namespace App\Http\Requests\Api\V1\Customer\Cart;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Handles validation for adding, updating, and removing a template-based cart item.
 * A qty of 0 is intentionally allowed to signal removal of an existing cart item.
 * The removal behavior is implemented in AddTemplateToCartAction.
 */
class AddTemplateItemRequest extends FormRequest
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
            'template_id' => ['required', 'integer', 'exists:vendor_design_templates,id'],
            'product_id' => ['required', 'integer', 'exists:catalog_products,id'],
            'selected_options' => ['required', 'array', 'min:1'],
            'selected_options.*' => ['required', 'integer', 'exists:catalog_attribute_options,option_id'],
            'qty' => ['required', 'integer', 'min:0'],
            'cart_item_id' => ['nullable', 'integer', 'exists:cart_items,id'],
        ];
    }
}
