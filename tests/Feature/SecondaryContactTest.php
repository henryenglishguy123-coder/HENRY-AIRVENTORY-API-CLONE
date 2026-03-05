<?php

namespace Tests\Feature;

use App\Models\Factory\Factory;
use App\Models\Factory\FactoryMetas;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SecondaryContactTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    /** @test */
    public function factory_can_store_secondary_contact()
    {
        $factory = Factory::factory()->create([
            'email_verified_at' => now(),
            'account_verified' => 2, // pending
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->postJson('/api/v1/factories/secondary-contact', [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john.doe@example.com',
                'phone_number' => '+1234567890',
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Secondary contact information saved successfully.',
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'secondary_contact' => [
                        'first_name',
                        'last_name',
                        'email',
                        'phone_number',
                    ],
                ],
                'message',
            ]);

        $this->assertDatabaseHas('factory_metas', [
            'factory_id' => $factory->id,
            'key' => 'secondary_contact',
        ]);
    }

    /** @test */
    public function factory_can_store_secondary_contact_without_email()
    {
        $factory = Factory::factory()->create([
            'email_verified_at' => now(),
            'account_verified' => 2,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->postJson('/api/v1/factories/secondary-contact', [
                'first_name' => 'Jane',
                'last_name' => 'Smith',
                'phone_number' => '+9876543210',
            ]);

        $response->assertStatus(200)
            ->assertJsonMissing(['email']);

        $meta = FactoryMetas::where('factory_id', $factory->id)
            ->where('key', 'secondary_contact')
            ->first();

        $contactData = json_decode($meta->value, true);
        $this->assertArrayNotHasKey('email', $contactData);
    }

    /** @test */
    public function factory_can_update_secondary_contact()
    {
        $factory = Factory::factory()->create([
            'email_verified_at' => now(),
            'account_verified' => 2,
        ]);

        // Create initial contact
        FactoryMetas::create([
            'factory_id' => $factory->id,
            'key' => 'secondary_contact',
            'value' => json_encode([
                'first_name' => 'Old',
                'last_name' => 'Name',
                'phone_number' => '+1111111111',
            ]),
        ]);

        // Update contact
        $response = $this->actingAs($factory, 'factory')
            ->postJson('/api/v1/factories/secondary-contact', [
                'first_name' => 'New',
                'last_name' => 'Name',
                'email' => 'new@example.com',
                'phone_number' => '+2222222222',
            ]);

        $response->assertStatus(200);

        $meta = FactoryMetas::where('factory_id', $factory->id)
            ->where('key', 'secondary_contact')
            ->first();

        $contactData = json_decode($meta->value, true);
        $this->assertEquals('New', $contactData['first_name']);
        $this->assertEquals('+2222222222', $contactData['phone_number']);
    }

    /** @test */
    public function factory_can_retrieve_secondary_contact()
    {
        $factory = Factory::factory()->create([
            'email_verified_at' => now(),
            'account_verified' => 2,
        ]);

        FactoryMetas::create([
            'factory_id' => $factory->id,
            'key' => 'secondary_contact',
            'value' => json_encode([
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'john@example.com',
                'phone_number' => '+1234567890',
            ]),
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->getJson('/api/v1/factories/secondary-contact');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'secondary_contact' => [
                        'first_name' => 'John',
                        'last_name' => 'Doe',
                        'email' => 'john@example.com',
                        'phone_number' => '+1234567890',
                    ],
                ],
            ]);
    }

    /** @test */
    public function returns_null_when_no_secondary_contact_exists()
    {
        $factory = Factory::factory()->create([
            'email_verified_at' => now(),
            'account_verified' => 2,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->getJson('/api/v1/factories/secondary-contact');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'secondary_contact' => null,
                ],
                'message' => 'No secondary contact information found.',
            ]);
    }

    /** @test */
    public function requires_first_name()
    {
        $factory = Factory::factory()->create([
            'email_verified_at' => now(),
            'account_verified' => 2,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->postJson('/api/v1/factories/secondary-contact', [
                'last_name' => 'Doe',
                'phone_number' => '+1234567890',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name']);
    }

    /** @test */
    public function requires_last_name()
    {
        $factory = Factory::factory()->create([
            'email_verified_at' => now(),
            'account_verified' => 2,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->postJson('/api/v1/factories/secondary-contact', [
                'first_name' => 'John',
                'phone_number' => '+1234567890',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['last_name']);
    }

    /** @test */
    public function requires_phone_number()
    {
        $factory = Factory::factory()->create([
            'email_verified_at' => now(),
            'account_verified' => 2,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->postJson('/api/v1/factories/secondary-contact', [
                'first_name' => 'John',
                'last_name' => 'Doe',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['phone_number']);
    }

    /** @test */
    public function validates_email_format_when_provided()
    {
        $factory = Factory::factory()->create([
            'email_verified_at' => now(),
            'account_verified' => 2,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->postJson('/api/v1/factories/secondary-contact', [
                'first_name' => 'John',
                'last_name' => 'Doe',
                'email' => 'invalid-email',
                'phone_number' => '+1234567890',
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /** @test */
    public function unauthenticated_request_fails()
    {
        $response = $this->postJson('/api/v1/factories/secondary-contact', [
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone_number' => '+1234567890',
        ]);

        $response->assertStatus(401);
    }
}
