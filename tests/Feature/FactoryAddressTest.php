<?php

namespace Tests\Feature;

use App\Models\Factory\Factory;
use App\Models\Factory\FactoryAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FactoryAddressTest extends TestCase
{
    use RefreshDatabase;

    protected function createFactory($verified = false)
    {
        return Factory::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'test@factory.com',
            'password' => bcrypt('password'),
            'phone_number' => '+1234567890',
            'email_verified' => true,
            'account_verified' => $verified ? 1 : 2, // 1 = verified, 2 = pending
            'account_status' => 1,
        ]);
    }

    public function test_factory_user_can_create_facility_address()
    {
        $factory = $this->createFactory(false);
        $this->actingAs($factory, 'factory');

        $response = $this->postJson('/api/v1/factories/addresses', [
            'type' => 'facility',
            'address' => 'Bandela Colony, Ganipur Road, Sikrai',
            'country_id' => '101',
            'state_id' => '4014',
            'city' => 'Dausa',
            'postal_code' => '303508',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Address added successfully.',
            ]);

        $this->assertDatabaseHas('factory_addresses', [
            'factory_id' => $factory->id,
            'type' => 'facility',
            'address' => 'Bandela Colony, Ganipur Road, Sikrai',
        ]);

        $this->assertDatabaseHas('factory_metas', [
            'factory_id' => $factory->id,
            'key' => 'addresses_status',
            'value' => '1',
        ]);
    }

    public function test_factory_user_can_create_distribution_center_address()
    {
        $factory = $this->createFactory(false);
        $this->actingAs($factory, 'factory');

        $response = $this->postJson('/api/v1/factories/addresses', [
            'type' => 'dist_center',
            'address' => 'Bandela Colony, Ganipur Road, Sikrai',
            'country_id' => '101',
            'state_id' => '4014',
            'city' => 'Dausa',
            'postal_code' => '303508',
        ]);

        $response->assertStatus(201)
            ->assertJson([
                'success' => true,
                'message' => 'Address added successfully.',
            ]);

        $this->assertDatabaseHas('factory_addresses', [
            'factory_id' => $factory->id,
            'type' => 'dist_center',
        ]);
    }

    public function test_factory_user_can_create_multiple_addresses()
    {
        $factory = $this->createFactory(false);
        $this->actingAs($factory, 'factory');

        // Add facility address
        $this->postJson('/api/v1/factories/addresses', [
            'type' => 'facility',
            'address' => 'Address 1',
            'country_id' => '101',
            'state_id' => '4014',
            'city' => 'Dausa',
            'postal_code' => '303508',
        ])->assertStatus(201);

        // Add distribution center address
        $this->postJson('/api/v1/factories/addresses', [
            'type' => 'dist_center',
            'address' => 'Address 2',
            'country_id' => '101',
            'state_id' => '4014',
            'city' => 'Dausa',
            'postal_code' => '303508',
        ])->assertStatus(201);

        $this->assertEquals(2, FactoryAddress::where('factory_id', $factory->id)->count());
    }

    public function test_verified_factory_cannot_create_address()
    {
        $factory = $this->createFactory(true); // verified
        $this->actingAs($factory, 'factory');

        $response = $this->postJson('/api/v1/factories/addresses', [
            'type' => 'facility',
            'address' => 'Bandela Colony, Ganipur Road, Sikrai',
            'country_id' => '101',
            'state_id' => '4014',
            'city' => 'Dausa',
            'postal_code' => '303508',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Factory addresses cannot be updated after account verification.',
            ]);
    }

    public function test_factory_user_can_retrieve_all_addresses()
    {
        $factory = $this->createFactory(false);
        $this->actingAs($factory, 'factory');

        // Create addresses
        FactoryAddress::create([
            'factory_id' => $factory->id,
            'type' => 'facility',
            'address' => 'Address 1',
            'country_id' => '101',
            'state_id' => '4014',
            'city' => 'Dausa',
            'postal_code' => '303508',
        ]);

        FactoryAddress::create([
            'factory_id' => $factory->id,
            'type' => 'dist_center',
            'address' => 'Address 2',
            'country_id' => '101',
            'state_id' => '4014',
            'city' => 'Dausa',
            'postal_code' => '303508',
        ]);

        $response = $this->getJson('/api/v1/factories/addresses');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Addresses retrieved successfully.',
            ])
            ->assertJsonCount(2, 'data.addresses');
    }

    public function test_factory_user_can_update_address()
    {
        $factory = $this->createFactory(false);
        $this->actingAs($factory, 'factory');

        $address = FactoryAddress::create([
            'factory_id' => $factory->id,
            'type' => 'facility',
            'address' => 'Old Address',
            'country_id' => '101',
            'state_id' => '4014',
            'city' => 'Dausa',
            'postal_code' => '303508',
        ]);

        $response = $this->putJson("/api/v1/factories/addresses/{$address->id}", [
            'type' => 'dist_center',
            'address' => 'Updated Address',
            'country_id' => '101',
            'state_id' => '4014',
            'city' => 'Jaipur',
            'postal_code' => '302020',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Address updated successfully.',
            ]);

        $this->assertDatabaseHas('factory_addresses', [
            'id' => $address->id,
            'type' => 'dist_center',
            'address' => 'Updated Address',
            'city' => 'Jaipur',
        ]);
    }

    public function test_verified_factory_cannot_update_address()
    {
        $factory = $this->createFactory(true); // verified
        $this->actingAs($factory, 'factory');

        $address = FactoryAddress::create([
            'factory_id' => $factory->id,
            'type' => 'facility',
            'address' => 'Address',
            'country_id' => '101',
            'state_id' => '4014',
            'city' => 'Dausa',
            'postal_code' => '303508',
        ]);

        $response = $this->putJson("/api/v1/factories/addresses/{$address->id}", [
            'type' => 'dist_center',
            'address' => 'Updated Address',
            'country_id' => '101',
            'state_id' => '4014',
            'city' => 'Jaipur',
            'postal_code' => '302020',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Factory addresses cannot be updated after account verification.',
            ]);
    }

    public function test_factory_user_can_delete_address()
    {
        $factory = $this->createFactory(false);
        $this->actingAs($factory, 'factory');

        $address = FactoryAddress::create([
            'factory_id' => $factory->id,
            'type' => 'facility',
            'address' => 'Address to delete',
            'country_id' => '101',
            'state_id' => '4014',
            'city' => 'Dausa',
            'postal_code' => '303508',
        ]);

        $response = $this->deleteJson("/api/v1/factories/addresses/{$address->id}");

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Address deleted successfully.',
            ]);

        $this->assertDatabaseMissing('factory_addresses', [
            'id' => $address->id,
        ]);
    }

    public function test_verified_factory_cannot_delete_address()
    {
        $factory = $this->createFactory(true); // verified
        $this->actingAs($factory, 'factory');

        $address = FactoryAddress::create([
            'factory_id' => $factory->id,
            'type' => 'facility',
            'address' => 'Address',
            'country_id' => '101',
            'state_id' => '4014',
            'city' => 'Dausa',
            'postal_code' => '303508',
        ]);

        $response = $this->deleteJson("/api/v1/factories/addresses/{$address->id}");

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Factory addresses cannot be deleted after account verification.',
            ]);
    }

    public function test_address_type_validation()
    {
        $factory = $this->createFactory(false);
        $this->actingAs($factory, 'factory');

        $response = $this->postJson('/api/v1/factories/addresses', [
            'type' => 'invalid_type',
            'address' => 'Address',
            'country_id' => '101',
            'state_id' => '4014',
            'city' => 'Dausa',
            'postal_code' => '303508',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type']);
    }

    public function test_required_fields_validation()
    {
        $factory = $this->createFactory(false);
        $this->actingAs($factory, 'factory');

        $response = $this->postJson('/api/v1/factories/addresses', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['type', 'address', 'country_id', 'state_id', 'city', 'postal_code']);
    }

    public function test_unauthenticated_user_cannot_access_addresses()
    {
        $response = $this->postJson('/api/v1/factories/addresses', [
            'type' => 'facility',
            'address' => 'Address',
            'country_id' => '101',
            'state_id' => '4014',
            'city' => 'Dausa',
            'postal_code' => '303508',
        ]);

        $response->assertStatus(401);
    }
}
