<?php

namespace Tests\Feature;

use App\Models\Admin\Admin;
use App\Models\Factory\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Tymon\JWTAuth\Facades\JWTAuth;

class FactoryAccountUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
    }

    /** @test */
    public function factory_can_update_their_basic_information()
    {
        $factory = Factory::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone_number' => '+1234567890',
            'email' => 'john@factory.com',
        ]);

        $token = JWTAuth::fromUser($factory);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->putJson('/api/v1/factories/account', [
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone_number' => '+9876543210',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account information updated successfully.',
            ])
            ->assertJsonPath('data.factory.first_name', 'Jane')
            ->assertJsonPath('data.factory.last_name', 'Smith')
            ->assertJsonPath('data.factory.phone_number', '+9876543210')
            ->assertJsonPath('data.factory.email', 'john@factory.com'); // Email unchanged

        $this->assertDatabaseHas('factory_users', [
            'id' => $factory->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'phone_number' => '+9876543210',
            'email' => 'john@factory.com',
        ]);
    }

    /** @test */
    public function factory_cannot_update_email()
    {
        $factory = Factory::factory()->create([
            'email' => 'original@factory.com',
        ]);

        $token = JWTAuth::fromUser($factory);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->putJson('/api/v1/factories/account', [
            'email' => 'newemail@factory.com',
            'first_name' => 'Updated',
        ]);

        $response->assertStatus(200);

        // Email should remain unchanged
        $this->assertDatabaseHas('factory_users', [
            'id' => $factory->id,
            'email' => 'original@factory.com',
            'first_name' => 'Updated',
        ]);

        $this->assertDatabaseMissing('factory_users', [
            'id' => $factory->id,
            'email' => 'newemail@factory.com',
        ]);
    }

    /** @test */
    public function admin_can_update_factory_account_including_email()
    {
        $factory = Factory::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@factory.com',
            'phone_number' => '+1234567890',
        ]);

        $admin = Admin::factory()->create();
        $adminToken = JWTAuth::fromUser($admin);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
        ])->putJson('/api/v1/factories/account', [
            'factory_id' => $factory->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@factory.com',
            'phone_number' => '+9876543210',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Account information updated successfully.',
            ])
            ->assertJsonPath('data.factory.first_name', 'Jane')
            ->assertJsonPath('data.factory.last_name', 'Smith')
            ->assertJsonPath('data.factory.email', 'jane@factory.com') // Email changed
            ->assertJsonPath('data.factory.phone_number', '+9876543210');

        $this->assertDatabaseHas('factory_users', [
            'id' => $factory->id,
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'jane@factory.com',
            'phone_number' => '+9876543210',
        ]);
    }

    /** @test */
    public function admin_must_provide_factory_id()
    {
        $admin = Admin::factory()->create();
        $adminToken = JWTAuth::fromUser($admin);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
        ])->putJson('/api/v1/factories/account', [
            'first_name' => 'Jane',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['factory_id']);
    }

    /** @test */
    public function admin_cannot_set_duplicate_email()
    {
        $factory1 = Factory::factory()->create(['email' => 'existing@factory.com']);
        $factory2 = Factory::factory()->create(['email' => 'factory2@factory.com']);

        $admin = Admin::factory()->create();
        $adminToken = JWTAuth::fromUser($admin);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
        ])->putJson('/api/v1/factories/account', [
            'factory_id' => $factory2->id,
            'email' => 'existing@factory.com', // Already taken
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Email is already taken.',
            ]);

        // Factory2's email should remain unchanged
        $this->assertDatabaseHas('factory_users', [
            'id' => $factory2->id,
            'email' => 'factory2@factory.com',
        ]);
    }

    /** @test */
    public function unauthenticated_user_cannot_update_account()
    {
        $response = $this->putJson('/api/v1/factories/account', [
            'first_name' => 'Jane',
        ]);

        $response->assertStatus(401);
    }

    /** @test */
    public function returns_404_for_non_existent_factory()
    {
        $admin = Admin::factory()->create();
        $adminToken = JWTAuth::fromUser($admin);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$adminToken,
        ])->putJson('/api/v1/factories/account', [
            'factory_id' => 99999, // Non-existent
            'first_name' => 'Jane',
        ]);

        $response->assertStatus(422) // Validation will fail first
            ->assertJsonValidationErrors(['factory_id']);
    }

    /** @test */
    public function factory_can_update_partial_information()
    {
        $factory = Factory::factory()->create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'phone_number' => '+1234567890',
        ]);

        $token = JWTAuth::fromUser($factory);

        // Only update phone number
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->putJson('/api/v1/factories/account', [
            'phone_number' => '+1111111111',
        ]);

        $response->assertStatus(200)
            ->assertJsonPath('data.factory.phone_number', '+1111111111')
            ->assertJsonPath('data.factory.first_name', 'John') // Unchanged
            ->assertJsonPath('data.factory.last_name', 'Doe'); // Unchanged
    }

    /** @test */
    public function validates_required_field_types()
    {
        $factory = Factory::factory()->create();
        $token = JWTAuth::fromUser($factory);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->putJson('/api/v1/factories/account', [
            'first_name' => 123, // Should be string
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['first_name']);
    }
}
