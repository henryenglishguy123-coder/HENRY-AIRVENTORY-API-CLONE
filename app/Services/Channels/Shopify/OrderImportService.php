<?php

namespace App\Services\Channels\Shopify;

use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Customer\Cart\Cart;
use App\Models\Customer\Cart\CartAddress;
use App\Models\Customer\Cart\CartError;
use App\Models\Customer\Cart\CartItem;
use App\Models\Customer\Cart\CartSource;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplateStoreVariant;
use App\Models\Customer\Store\VendorConnectedStore;
use App\Models\Factory\FactoryShippingRate;
use App\Models\Location\Country;
use App\Models\Location\State;
use App\Services\Customer\Cart\CartDiscountService;
use App\Services\Customer\Cart\CartPricingService;
use App\Services\Customer\Cart\CartRoutingService;
use App\Services\Customer\Cart\CartTotalsService;
use App\Services\Customer\Wallet\WalletService;
use App\Services\Sales\Order\CartToOrderService;
use App\Services\Sales\Order\OrderPaymentService;
use App\Support\Customers\CustomerMeta;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class OrderImportService
{
    public function process(
        string $shopDomain,
        array $payload,
        CartRoutingService $cartRoutingService,
        CartToOrderService $cartToOrderService
    ): Collection {
        return DB::transaction(function () use ($shopDomain, $payload, $cartRoutingService, $cartToOrderService): Collection {
            return $this->doImport($shopDomain, $payload, $cartRoutingService, $cartToOrderService);
        });
    }

    private function doImport(
        string $shopDomain,
        array $payload,
        CartRoutingService $cartRoutingService,
        CartToOrderService $cartToOrderService
    ): Collection {
        if (isset($payload['id'])) {
            $alreadyImported = CartSource::where('platform', 'shopify')
                ->where('source_order_id', (string) $payload['id'])
                ->where('source', $shopDomain)
                ->exists();
            if ($alreadyImported) {
                Log::info('Shopify order already imported, skipping', [
                    'shop' => $shopDomain,
                    'source_order_id' => (string) $payload['id'],
                ]);

                return collect();
            }
        }

        $store = VendorConnectedStore::where('store_identifier', $shopDomain)->firstOrFail();
        $cart = Cart::create([
            'vendor_id' => $store->vendor_id,
            'status' => 'active',
        ]);

        $importResult = $this->importLineItems($cart, $payload['line_items'] ?? [], $shopDomain);
        $hasImportedItems = $importResult['importedAny'] ?? false;
        $hasMappedVariant = $importResult['hasMappedVariant'] ?? false;

        if (! $hasMappedVariant && ! $hasImportedItems) {
            CartError::where('cart_id', $cart->id)->delete();
            $cart->delete();

            Log::info('Shopify order skipped because no products matched', [
                'shop' => $shopDomain,
                'source_order_id' => isset($payload['id']) ? (string) $payload['id'] : null,
            ]);

            return collect();
        }

        CartSource::create([
            'cart_id' => $cart->id,
            'platform' => 'shopify',
            'source' => $shopDomain,
            'source_order_id' => (string) ($payload['id'] ?? ''),
            'source_order_number' => (string) ($payload['order_number'] ?? ''),
            'source_created_at' => $this->parseCreatedAt($payload['created_at'] ?? null),
            'payload' => $payload,
        ]);

        $this->setAddress($cart, $payload);
        $this->recalcRoutingAndTotals($cart, $cartRoutingService);

        return $this->convertOrAbort($cart, $cartToOrderService);
    }

    private function parseCreatedAt(?string $createdAt): ?Carbon
    {
        if (! $createdAt) {
            return null;
        }

        try {
            return Carbon::parse($createdAt)->setTimezone(config('app.timezone'));
        } catch (\Exception $e) {
            return null;
        }
    }

    private function importLineItems(Cart $cart, array $lineItems, string $shopDomain): array
    {
        $pricingService = app(CartPricingService::class);
        $importedAny = false;
        $hasMappedVariant = false;
        foreach ($lineItems as $item) {
            $shopifyVariantId = $item['variant_id'] ?? null;
            $sku = $item['sku'] ?? '';
            $storeVariant = null;

            if ($shopifyVariantId) {
                $storeVariant = VendorDesignTemplateStoreVariant::where('external_variant_id', $shopifyVariantId)->first();
            }
            if (! $storeVariant && $sku) {
                $parts = explode('-', $sku);
                $potentialId = end($parts);
                if (is_numeric($potentialId)) {
                    $storeVariant = VendorDesignTemplateStoreVariant::find($potentialId);
                    if ($storeVariant && $storeVariant->storeTemplate?->vendor_id !== $cart->vendor_id) {
                        $storeVariant = null;
                    }
                }
            }
            if (! $storeVariant) {
                Log::warning('Shopify store variant not found or unauthorized', [
                    'shop' => $shopDomain,
                    'sku' => $sku,
                    'variant_id' => $shopifyVariantId,
                    'vendor_id' => $cart->vendor_id,
                ]);
                CartError::create([
                    'cart_id' => $cart->id,
                    'sku' => $sku ?: null,
                    'factory_id' => null,
                    'error_code' => 'VARIANT_NOT_FOUND',
                    'error_message' => 'Store variant not found or unauthorized for vendor.',
                ]);

                continue;
            }

            $variantId = $storeVariant->catalog_product_id;
            $variantProduct = CatalogProduct::with('parent')->find($variantId);
            if (! $variantProduct || ! $variantProduct->parent) {
                Log::warning('Shopify variant or parent product missing', [
                    'shop' => $shopDomain,
                    'variant_id' => $variantId,
                ]);
                CartError::create([
                    'cart_id' => $cart->id,
                    'sku' => $variantProduct?->sku ?? $sku ?: null,
                    'factory_id' => null,
                    'error_code' => 'VARIANT_PARENT_MISSING',
                    'error_message' => 'Variant or parent product missing.',
                ]);

                continue;
            }

            $storeTemplate = $storeVariant->storeTemplate;
            $templateId = $storeTemplate ? $storeTemplate->vendor_design_template_id : null;
            $template = $templateId ? VendorDesignTemplate::find($templateId) : null;
            if (! $template) {
                Log::warning('Shopify template not found', [
                    'shop' => $shopDomain,
                    'template_id' => $templateId,
                    'variant_id' => $variantId,
                ]);
                CartError::create([
                    'cart_id' => $cart->id,
                    'sku' => $variantProduct->sku ?? $sku ?: null,
                    'factory_id' => null,
                    'error_code' => 'TEMPLATE_NOT_FOUND',
                    'error_message' => 'Design template not found for variant.',
                ]);

                continue;
            }

            $hasMappedVariant = true;

            try {
                $unitPrice = $pricingService->resolveUnitPrice($variantProduct, $template);
                $fulfillmentFactoryId = $pricingService->getFulfillmentFactoryId($variantProduct, $template);
            } catch (\Throwable $e) {
                Log::warning('Shopify pricing/factory resolution failed', [
                    'variant_id' => $variantId,
                    'template_id' => $templateId,
                    'error' => $e->getMessage(),
                ]);
                CartError::create([
                    'cart_id' => $cart->id,
                    'sku' => $variantProduct->sku ?? $sku ?: null,
                    'factory_id' => null,
                    'error_code' => 'PRICING_FAILED',
                    'error_message' => 'Pricing or factory resolution failed: '.$e->getMessage(),
                ]);

                continue;
            }

            if (! $fulfillmentFactoryId) {
                Log::warning('Shopify no fulfillment factory', [
                    'variant_id' => $variantId,
                    'template_id' => $templateId,
                ]);
                CartError::create([
                    'cart_id' => $cart->id,
                    'sku' => $variantProduct->sku ?? $sku ?: null,
                    'factory_id' => null,
                    'error_code' => 'NO_FACTORY',
                    'error_message' => 'No fulfillment factory available.',
                ]);

                continue;
            }

            $qty = (int) ($item['quantity'] ?? 1);
            $unitPriceStr = (string) $unitPrice;
            try {
                CartItem::create([
                    'cart_id' => $cart->id,
                    'product_id' => $variantProduct->parent->id,
                    'variant_id' => $variantId,
                    'template_id' => $templateId,
                    'packaging_label_id' => $storeTemplate->packaging_label_id,
                    'hang_tag_id' => $storeTemplate->hang_tag_id,
                    'sku' => $variantProduct->sku ?? $sku,
                    'product_title' => $item['name'] ?? $variantProduct->parent->name,
                    'qty' => $qty,
                    'unit_price' => $unitPriceStr,
                    'line_total' => bcmul($unitPriceStr, (string) $qty, 2),
                    'fulfillment_factory_id' => $fulfillmentFactoryId,
                ]);
            } catch (\Throwable $e) {
                Log::error('Failed to create cart item during Shopify import', [
                    'cart_id' => $cart->id,
                    'sku' => $variantProduct->sku ?? $sku,
                    'error' => $e->getMessage(),
                ]);
                CartError::create([
                    'cart_id' => $cart->id,
                    'sku' => $variantProduct->sku ?? $sku ?: null,
                    'factory_id' => $fulfillmentFactoryId,
                    'error_code' => 'ITEM_CREATE_FAILED',
                    'error_message' => 'Failed to create cart item.',
                ]);

                continue;
            }
            $importedAny = true;
        }
        if (! app()->runningInConsole()) {
            if ($importedAny) {
                $cart->update(['status' => 'imported']);
            } else {
                $cart->update(['status' => 'abandoned']);
            }
        }

        return [
            'importedAny' => $importedAny,
            'hasMappedVariant' => $hasMappedVariant,
        ];
    }

    private function setAddress(Cart $cart, array $payload): void
    {
        if (! isset($payload['shipping_address'])) {
            return;
        }
        $shipping = $payload['shipping_address'];
        $country = Country::where('iso2', $shipping['country_code'] ?? null)->first();
        $state = null;
        if ($country && ! empty($shipping['province_code'])) {
            $state = State::where('country_id', $country->id)->where('iso2', $shipping['province_code'])->first();
        }
        $address = CartAddress::create([
            'cart_id' => $cart->id,
            'first_name' => $shipping['first_name'] ?? '',
            'last_name' => $shipping['last_name'] ?? '',
            'email' => $payload['email'] ?? '',
            'phone' => $shipping['phone'] ?? '',
            'address_line_1' => $shipping['address1'] ?? '',
            'address_line_2' => $shipping['address2'] ?? null,
            'city' => $shipping['city'] ?? '',
            'state' => $shipping['province'] ?? '',
            'state_id' => $state?->id,
            'postal_code' => $shipping['zip'] ?? '',
            'country' => $shipping['country'] ?? '',
            'country_id' => $country?->id,
        ]);
        if (empty($address->email) || empty($address->phone)) {
            Log::warning('Shopify import: contact info missing (email/phone required for carriers)', [
                'cart_id' => $cart->id,
                'email_missing' => empty($address->email),
                'phone_missing' => empty($address->phone),
            ]);

            // NOTE: We do not create CartError here to avoid duplication.
            // recalcRoutingAndTotals() will check for contact info and create the error if needed.

            if (! app()->runningInConsole()) {
                $cart->update(['status' => 'hold']);
            }
        }
    }

    private function recalcRoutingAndTotals(Cart $cart, CartRoutingService $cartRoutingService): void
    {
        $cartRoutingService->processCartRouting($cart);
        $canRecalcTotals = true;

        // Check for MISSING_ADDRESS first
        if (! $cart->address || ! $cart->address->country_id) {
            $canRecalcTotals = false;

            // Only create MISSING_ADDRESS if not already created
            if (! CartError::where('cart_id', $cart->id)->where('error_code', 'MISSING_ADDRESS')->exists()) {
                CartError::create([
                    'cart_id' => $cart->id,
                    'sku' => null,
                    'factory_id' => null,
                    'error_code' => 'MISSING_ADDRESS',
                    'error_message' => 'Shipping address or country is missing.',
                ]);
            }
        } else {
            // Check for CONTACT_REQUIRED
            if (empty($cart->address->email) || empty($cart->address->phone)) {
                $canRecalcTotals = false;

                // Only create CONTACT_REQUIRED if not already created
                if (! CartError::where('cart_id', $cart->id)->where('error_code', 'CONTACT_REQUIRED')->exists()) {
                    CartError::create([
                        'cart_id' => $cart->id,
                        'sku' => null,
                        'factory_id' => null,
                        'error_code' => 'CONTACT_REQUIRED',
                        'error_message' => 'Email and phone are required for shipping carriers.',
                    ]);
                }
            }

            $country = Country::find($cart->address->country_id);
            if (! $country) {
                $canRecalcTotals = false;
            } else {
                if (! Schema::hasTable('factory_shipping_rates')) {
                    $canRecalcTotals = false;
                } else {
                    $itemsByFactory = $cart->items->groupBy('fulfillment_factory_id');
                    foreach ($itemsByFactory as $factoryId => $items) {
                        if (empty($factoryId)) {
                            $canRecalcTotals = false;
                            break;
                        }
                        $exists = FactoryShippingRate::where('factory_id', $factoryId)
                            ->where('country_code', $country->iso2)
                            ->exists();
                        if (! $exists) {
                            $canRecalcTotals = false;
                            CartError::create([
                                'cart_id' => $cart->id,
                                'sku' => null,
                                'factory_id' => $factoryId,
                                'error_code' => 'TOTALS_SKIPPED_FOR_FACTORY_COUNTRY',
                                'error_message' => "Missing shipping rate for factory {$factoryId} and country {$country->iso2}.",
                            ]);
                            break;
                        }
                    }
                }
            }
        }

        if ($canRecalcTotals) {
            app(CartDiscountService::class)->refreshDiscount($cart);
            app(CartTotalsService::class)->recalculate($cart);
        } else {
            Log::warning('Shopify import: totals recalculation skipped due to missing shipping rates or address', [
                'cart_id' => $cart->id,
            ]);
            if (! app()->runningInConsole()) {
                $cart->update(['status' => 'hold']);
            }

            // Only create TOTALS_SKIPPED if no other specific errors exist
            $hasSpecificErrors = CartError::where('cart_id', $cart->id)
                ->whereIn('error_code', ['MISSING_ADDRESS', 'CONTACT_REQUIRED', 'TOTALS_SKIPPED_FOR_FACTORY_COUNTRY'])
                ->exists();

            if (! $hasSpecificErrors) {
                CartError::create([
                    'cart_id' => $cart->id,
                    'sku' => null,
                    'factory_id' => null,
                    'error_code' => 'TOTALS_SKIPPED',
                    'error_message' => 'Totals recalculation skipped due to missing data.',
                ]);
            }
        }
    }

    private function convertOrAbort(Cart $cart, CartToOrderService $cartToOrderService): Collection
    {
        // 1. Check for any logged errors
        if (CartError::where('cart_id', $cart->id)->exists()) {
            Log::warning('Shopify import: conversion aborted due to existing cart errors', [
                'cart_id' => $cart->id,
            ]);

            return collect();
        }

        // 2. Check for empty cart
        if ($cart->items()->count() === 0) {
            Log::warning('Shopify import: cart has no items, conversion aborted', [
                'cart_id' => $cart->id,
                'vendor_id' => $cart->vendor_id,
            ]);
            CartError::create([
                'cart_id' => $cart->id,
                'sku' => null,
                'factory_id' => null,
                'error_code' => 'CART_EMPTY',
                'error_message' => 'Cart has no items, conversion aborted.',
            ]);

            return collect();
        }

        // Redundant checks removed: MISSING_ADDRESS and CONTACT_REQUIRED are already handled
        // by recalcRoutingAndTotals() and caught by the initial CartError::exists() check above.

        if (! app()->runningInConsole()) {
            $cart->update(['status' => 'converted']);
        }
        try {
            $orders = $cartToOrderService->convert($cart, 'shopify');

            // Invalidate cached order lists for customer and admin after new order(s) creation
            Cache::put("orders_version:customer_{$cart->vendor_id}", time());
            Cache::put('orders_version:admin_global', time());

            $enabled = filter_var(CustomerMeta::get($cart->vendor_id, 'auto_pay_enabled', false), FILTER_VALIDATE_BOOLEAN);
            if ($enabled && $orders->isNotEmpty()) {
                $totalRemaining = 0.0;
                foreach ($orders as $order) {
                    if ($order->payment_status === 'paid') {
                        continue;
                    }
                    $alreadyPaid = $order->payments()->where('payment_status', 'paid')->sum('amount');
                    $remaining = round($order->grand_total_inc_margin - $alreadyPaid, 2);
                    if ($remaining > 0) {
                        $totalRemaining += $remaining;
                    }
                }
                $balance = WalletService::getBalance($cart->vendor_id);
                if ($balance >= $totalRemaining && $totalRemaining > 0) {
                    app(OrderPaymentService::class)->processPayment($orders, ['use_wallet' => true]);
                }
            }

            return $orders;
        } catch (\Throwable $e) {
            Log::error('Shopify import: cart conversion failed', [
                'cart_id' => $cart->id,
                'error' => $e->getMessage(),
            ]);
            CartError::create([
                'cart_id' => $cart->id,
                'sku' => null,
                'factory_id' => null,
                'error_code' => 'CONVERT_FAILED',
                'error_message' => 'Cart conversion failed.',
            ]);
            throw $e;
        }
    }
}
