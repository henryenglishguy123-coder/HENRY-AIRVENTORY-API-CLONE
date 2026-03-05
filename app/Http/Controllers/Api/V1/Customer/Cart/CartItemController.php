<?php

namespace App\Http\Controllers\Api\V1\Customer\Cart;

use App\Http\Controllers\Api\V1\Customer\Account\AccountController;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\Customer\Cart\AddTemplateItemRequest;
use App\Http\Resources\Api\V1\Cart\TemplateItemResource;
use App\Http\Resources\Api\V1\Customer\CartResource;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Services\Customer\Cart\Actions\AddTemplateToCartAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CartItemController extends Controller
{
    public function addTemplateItem(
        AddTemplateItemRequest $request,
        AddTemplateToCartAction $action
    ): JsonResponse {
        $customer = app(AccountController::class)->resolveCustomer($request);
        $template = VendorDesignTemplate::findOrFail($request->template_id);
        if ($template->vendor_id !== $customer->id) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to add this template to your cart.'),
            ], 403);
        }
        $cart = $action->execute($customer, $request->validated());

        return response()->json([
            'success' => true,
            'message' => __('Item added to cart successfully.'),
            'data' => CartResource::make($cart),
        ]);
    }

    public function getTemplateItem(VendorDesignTemplate $template, Request $request)
    {
        $customer = app(AccountController::class)->resolveCustomer($request);

        if ($template->vendor_id !== $customer->id) {
            return response()->json([
                'success' => false,
                'message' => __('You are not authorized to view this template.'),
            ], Response::HTTP_FORBIDDEN);
        }

        $template->load([
            'designImages',
            'product.children.attributes.option.attribute',
            'product.info',
        ]);

        $variations = $this->buildVariations($template);

        return (new TemplateItemResource($template, $variations))
            ->additional([
                'success' => true,
            ]);

    }

    protected function buildVariations(VendorDesignTemplate $template): array
    {
        $children = $template->product?->children;
        if (! $children || $children->isEmpty()) {
            return [];
        }
        $attributes = [];
        foreach ($children as $child) {
            foreach ($child->attributes as $attributeValue) {
                $attribute = $attributeValue->attribute;
                $option = $attributeValue->option;
                if (! $attribute || ! $option) {
                    continue;
                }
                $code = $attribute->attribute_code;
                $optionId = $option->option_id;
                if (! $code || isset($attributes[$code][$optionId])) {
                    continue;
                }
                $data = [
                    'id' => $optionId,
                    'key' => $option->key,
                    'value' => $option->option_value,
                ];
                if ($code == 'color') {
                    $data['image'] = getImageUrl($template->designImages->firstWhere('color_id', $optionId)?->image);
                }
                $attributes[$code][$optionId] = $data;
            }
        }

        return collect($attributes)
            ->map(fn ($options) => array_values($options))
            ->toArray();
    }
}
