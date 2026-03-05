<?php

namespace App\Http\Requests\Api\V1\Customer\Template;

use Illuminate\Foundation\Http\FormRequest;

class VendorDesignTemplateStoreUpdateRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        // Authorization is handled in the Controller/Policy via Vendor ID check
        // We can double-check here if we want to resolve the customer early,
        // but typically the controller does the ownership check on the route model.
        // For now, return true as we use Policy/Controller checks.
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        // Get the template from the route to validate against its vendor_id
        // Route model binding ensures this exists, but we check for safety
        $template = $this->route('template');
        $vendorId = $template?->vendor_id;

        return [
            'store_id' => [
                'required',
                \Illuminate\Validation\Rule::exists('vendor_connected_stores', 'id')
                    ->where('vendor_id', $vendorId), // Ensure store belongs to the template owner
            ],
            'name' => 'nullable|string|max:255',
            'description' => 'nullable|string',
            'primary_image' => 'nullable',
            'sync_images' => 'nullable|array',
            'sync_images.*' => 'nullable',
            'variants' => 'nullable|array',
            'variants.*.catalog_product_id' => 'required|exists:catalog_products,id',
            'variants.*.sku' => 'nullable|string|max:255',
            'variants.*.markup' => 'nullable|numeric|min:0',
            'variants.*.markup_type' => 'nullable|in:percentage,profit',
            'variants.*.external_variant_id' => 'nullable|string|max:255',
            'variants.*.is_enabled' => 'nullable|boolean',
            'hang_tag_id' => 'nullable|integer',
            'packaging_label_id' => 'nullable|integer',
        ];
    }
}
