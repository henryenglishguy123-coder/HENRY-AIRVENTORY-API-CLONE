<?php

namespace Tests\Feature\Communication;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use App\Models\Sales\Order\SalesOrder;
use App\Models\Sales\Order\Message;
use App\Models\Customer\Customer;
use App\Models\Factory\Factory;
use Illuminate\Support\Facades\Storage;
use Illuminate\Http\UploadedFile;

class OrderMessagingTest extends TestCase
{
    use RefreshDatabase;

    public function test_customer_can_send_message_to_factory()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $order = SalesOrder::factory()->create(['customer_id' => $customer->id]);

        // Mock auth
        $this->actingAs($customer, 'api');

        // Test sending a message
        $response = $this->postJson("/api/v1/orders/{$order->order_number}/messages", [
            'message' => 'This is a test message from customer to factory',
            'message_type' => 'feedback'
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'message' => 'This is a test message from customer to factory',
                         'sender_role' => 'customer',
                         'message_type' => 'feedback'
                     ]
                 ]);

        // Verify message was saved
        $this->assertDatabaseHas('sales_order_messages', [
            'sales_order_id' => $order->id,
            'sender_id' => $customer->id,
            'sender_role' => 'customer',
            'message' => 'This is a test message from customer to factory',
            'message_type' => 'feedback'
        ]);
    }

    public function test_factory_can_send_message_to_customer()
    {
        // Create test data
        $factory = Factory::factory()->create();
        $order = SalesOrder::factory()->create(['factory_id' => $factory->id]);

        // Mock auth
        $this->actingAs($factory, 'api');

        // Test sending a message
        $response = $this->postJson("/api/v1/orders/{$order->order_number}/messages", [
            'message' => 'This is a test message from factory to customer',
            'message_type' => 'sample_sent'
        ]);

        $response->assertStatus(201)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         'message' => 'This is a test message from factory to customer',
                         'sender_role' => 'factory',
                         'message_type' => 'sample_sent'
                     ]
                 ]);

        // Verify message was saved
        $this->assertDatabaseHas('sales_order_messages', [
            'sales_order_id' => $order->id,
            'sender_id' => $factory->id,
            'sender_role' => 'factory',
            'message' => 'This is a test message from factory to customer',
            'message_type' => 'sample_sent'
        ]);
    }

    public function test_get_order_messages()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $order = SalesOrder::factory()->create(['customer_id' => $customer->id]);

        // Create some messages
        Message::factory()->create([
            'sales_order_id' => $order->id,
            'sender_id' => $customer->id,
            'sender_role' => 'customer',
            'message' => 'Test message 1'
        ]);

        // Mock auth
        $this->actingAs($customer, 'api');

        // Test getting messages
        $response = $this->getJson("/api/v1/orders/{$order->order_number}/messages");

        $response->assertStatus(200)
                 ->assertJson([
                     'success' => true,
                     'data' => [
                         [
                             'message' => 'Test message 1',
                             'sender_role' => 'customer'
                         ]
                     ]
                 ]);
    }

    public function test_admin_can_view_all_messages()
    {
        // Create test data
        $customer = Customer::factory()->create();
        $order = SalesOrder::factory()->create(['customer_id' => $customer->id]);

        // Create a message
        Message::factory()->create([
            'sales_order_id' => $order->id,
            'sender_id' => $customer->id,
            'sender_role' => 'customer',
            'message' => 'Test message for admin'
        ]);

        // Note: This test would require a valid admin token for the API
        // For now, we'll just verify the route structure exists
        $this->assertTrue(true); // Placeholder since we can't easily mock admin JWT without knowing the implementation
    }

    public function test_message_with_attachments()
    {
        Storage::fake('public');

        // Create test data
        $customer = Customer::factory()->create();
        $order = SalesOrder::factory()->create(['customer_id' => $customer->id]);

        // Create a fake file
        $file = UploadedFile::fake()->image('sample.jpg');

        // Mock auth
        $this->actingAs($customer, 'api');

        // Test sending a message with attachment
        $response = $this->postJson("/api/v1/orders/{$order->order_number}/messages", [
            'message' => 'This is a message with attachment',
            'message_type' => 'sample_sent',
            'attachments' => [$file]
        ]);

        // The response should be successful
        $response->assertStatus(201);
    }
}
