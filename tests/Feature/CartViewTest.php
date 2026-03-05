<?php

namespace Tests\Feature;

use App\Models\Customer\Cart\Cart;
use App\Models\Customer\Cart\CartAddress;
use App\Models\Customer\Cart\CartItem;
use App\Models\Customer\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CartViewTest extends TestCase
{
    use RefreshDatabase;

    protected function createCustomer(): Vendor
    {
        return Vendor::create([
            'first_name' => 'Test',
            'last_name' => 'Customer',
            'email' => 'customer@example.com',
            'password' => 'password123',
            'account_status' => 1,
        ]);
    }

    public function test_successful_cart_retrieval(): void
    {
        $customer = $this->createCustomer();
        $token = auth('customer')->login($customer);

        $cart = Cart::create([
            'vendor_id' => $customer->id,
            'status' => 'active',
        ]);

        $address = CartAddress::create([
            'cart_id' => $cart->id,
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+123456789',
            'address_line_1' => '123 Main St',
            'city' => 'City',
            'postal_code' => '12345',
        ]);

        \App\Models\Catalog\Product\CatalogProduct::create([
            'id' => 1,
            'type' => 'simple',
            'slug' => 'product-1',
            'sku' => 'SKU-1',
            'status' => 1,
        ]);

        $item = CartItem::create([
            'cart_id' => $cart->id,
            'product_id' => 1,
            'variant_id' => null,
            'template_id' => null,
            'sku' => 'SKU-1',
            'product_title' => 'Product 1',
            'qty' => 2,
            'unit_price' => 10.00,
            'line_total' => 20.00,
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/customers/cart/view');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'id',
                    'items',
                    'shipments',
                    'totals' => [
                        'subtotal',
                        'grand_total',
                    ],
                    'address',
                ],
            ]);

        $responseData = $response->json('data');

        $this->assertEquals($address->email, $responseData['address']['email']);
        $this->assertEquals($item->sku, $responseData['items'][0]['sku']);
    }

    public function test_empty_cart_returns_empty_items(): void
    {
        $customer = $this->createCustomer();
        $token = auth('customer')->login($customer);

        $cart = Cart::create([
            'vendor_id' => $customer->id,
            'status' => 'active',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/customers/cart/view');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
            ]);

        $this->assertEquals(0, count($response->json('data.items')));
    }

    public function test_unauthenticated_request_returns_401(): void
    {
        $response = $this->getJson('/api/v1/customers/cart/view');

        $response->assertStatus(401)
            ->assertJson([
                'success' => false,
                'message' => 'Unauthorized',
            ]);
    }

    public function test_cart_not_found_returns_404(): void
    {
        $customer = $this->createCustomer();
        $token = auth('customer')->login($customer);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/customers/cart/view');

        $response->assertStatus(404)
            ->assertJson([
                'success' => false,
                'message' => 'Cart not found.',
            ]);
    }
}
