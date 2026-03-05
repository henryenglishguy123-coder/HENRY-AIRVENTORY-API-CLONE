<?php

namespace App\Services\Customer\Cart\Actions;

use App\Models\Customer\Cart\Cart;
use App\Models\Customer\Cart\CartAddress;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Customer\Vendor;
use App\Models\Sales\Order\Item\SalesOrderItem;
use App\Models\Sales\Order\SalesOrder;
use App\Services\Customer\Cart\CartService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReorderAction
{
    public function __construct(
        protected CartService $cartService,
        protected AddTemplateToCartAction $addTemplateToCartAction
    ) {}

    /**
     * Reorder items from a previous order into the customer's active cart.
     *
     * @param  Vendor     $customer  The authenticated customer
     * @param  SalesOrder $order     The original order to reorder from
     * @return array{cart: \App\Models\Customer\Cart\Cart, added: array, skipped: array}
     */
    public function execute(Vendor $customer, SalesOrder $order): array
    {
        // Authorization is handled by the Controller via Policy

        return DB::transaction(function () use ($customer, $order) {
            // Load order with all necessary relationships
            $order->load([
                'items.options',
                'shippingAddress',
            ]);

            $added = [];
            $skipped = [];

            foreach ($order->items as $orderItem) {
                try {
                    $result = $this->processOrderItem($customer, $orderItem);

                    if ($result['success']) {
                        $added[] = $result['data'];
                    } else {
                        $skipped[] = $result['data'];
                    }
                } catch (\Exception $e) {
                    Log::warning('Reorder: failed to add item', [
                        'order_id' => $order->id,
                        'order_item_id' => $orderItem->id,
                        'error' => $e->getMessage(),
                    ]);

                    $skipped[] = [
                        'order_item_id' => $orderItem->id,
                        'product_name' => $orderItem->product_name ?? $orderItem->catalog_name,
                        'sku' => $orderItem->sku,
                        'reason' => $e->getMessage(),
                    ];
                }
            }

            // Copy shipping address from old order to cart
            $cart = $this->cartService->getActiveCart($customer->id);
            $this->copyShippingAddress($order, $cart);

            // Reload cart with all relationships
            $cart->refresh()->load([
                'items.options',
                'items.designImages',
                'totals',
                'errors',
                'address',
                'discount',
                'items.template.product.children',
            ]);

            return [
                'cart' => $cart,
                'added' => $added,
                'skipped' => $skipped,
            ];
        });
    }

    /**
     * Process a single order item for reorder.
     */
    protected function processOrderItem(Vendor $customer, SalesOrderItem $orderItem): array
    {
        // Check if template still exists and belongs to this customer
        if (!$orderItem->template_id) {
            return [
                'success' => false,
                'data' => [
                    'order_item_id' => $orderItem->id,
                    'product_name' => $orderItem->product_name ?? $orderItem->catalog_name,
                    'sku' => $orderItem->sku,
                    'reason' => 'No template associated with this item.',
                ],
            ];
        }

        $template = VendorDesignTemplate::find($orderItem->template_id);

        if (!$template) {
            return [
                'success' => false,
                'data' => [
                    'order_item_id' => $orderItem->id,
                    'product_name' => $orderItem->product_name ?? $orderItem->catalog_name,
                    'sku' => $orderItem->sku,
                    'reason' => 'Template no longer exists.',
                ],
            ];
        }

        if ($template->vendor_id !== $customer->id) {
            return [
                'success' => false,
                'data' => [
                    'order_item_id' => $orderItem->id,
                    'product_name' => $orderItem->product_name ?? $orderItem->catalog_name,
                    'sku' => $orderItem->sku,
                    'reason' => 'Template does not belong to this customer.',
                ],
            ];
        }

        // Collect option IDs from the original order item
        $selectedOptions = $orderItem->options
            ->pluck('option_id')
            ->filter()
            ->values()
            ->toArray();

        if (empty($selectedOptions)) {
            return [
                'success' => false,
                'data' => [
                    'order_item_id' => $orderItem->id,
                    'product_name' => $orderItem->product_name ?? $orderItem->catalog_name,
                    'sku' => $orderItem->sku,
                    'reason' => 'No product options found for this item.',
                ],
            ];
        }

        // Build data payload matching AddTemplateItemRequest format
        $data = [
            'template_id' => $orderItem->template_id,
            'product_id' => $orderItem->product_id,
            'selected_options' => $selectedOptions,
            'qty' => $orderItem->qty,
        ];

        // Delegate to AddTemplateToCartAction (handles pricing, variant, cart creation)
        // AddTemplateToCartAction::execute() throws ValidationException on failure
        // and returns the updated Cart model on success
        $cart = $this->addTemplateToCartAction->execute($customer, $data);

        if (!$cart) {
            return [
                'success' => false,
                'data' => [
                    'order_item_id' => $orderItem->id,
                    'product_name' => $orderItem->product_name ?? $orderItem->catalog_name,
                    'sku' => $orderItem->sku,
                    'reason' => 'Failed to add item to cart.',
                ],
            ];
        }

        return [
            'success' => true,
            'data' => [
                'order_item_id' => $orderItem->id,
                'product_name' => $orderItem->product_name ?? $orderItem->catalog_name,
                'sku' => $orderItem->sku,
                'qty' => $orderItem->qty,
            ],
        ];
    }

    /**
     * Copy shipping address from the original order to the cart.
     */
    protected function copyShippingAddress(SalesOrder $order, Cart $cart): void
    {
        $shippingAddress = $order->shippingAddress;

        if (!$shippingAddress) {
            return;
        }

        CartAddress::updateOrCreate(
            ['cart_id' => $cart->id],
            [
                'first_name' => $shippingAddress->first_name,
                'last_name' => $shippingAddress->last_name,
                'email' => $shippingAddress->email,
                'phone' => $shippingAddress->phone,
                'address_line_1' => $shippingAddress->address_line_1,
                'address_line_2' => $shippingAddress->address_line_2,
                'city' => $shippingAddress->city,
                'state' => $shippingAddress->state,
                'state_id' => $shippingAddress->state_id,
                'postal_code' => $shippingAddress->postal_code,
                'country' => $shippingAddress->country,
                'country_id' => $shippingAddress->country_id,
            ]
        );
    }
}
