<?php

namespace Tests\Feature;

use App\Models\Factory\Factory;
use App\Models\Factory\HangTag;
use App\Models\Factory\PackagingLabel;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LabelSettingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->artisan('migrate');
    }

    // ── Packaging Label ─────────────────────────────────────────────────────────

    /** @test */
    public function factory_can_retrieve_packaging_label_settings_when_they_exist()
    {
        $factory = Factory::factory()->create([
            'email_verified' => true,
            'account_verified' => 2,
        ]);

        PackagingLabel::create([
            'factory_id' => $factory->id,
            'front_price' => 150.50,
            'back_price' => 120.25,
            'is_active' => true,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->getJson('/api/v1/factories/label-settings/packaging-label');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Packaging label settings retrieved successfully.',
            ])
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'factory_id', 'front_price', 'back_price', 'is_active'],
                'message',
            ]);
    }

    /** @test */
    public function factory_receives_defaults_when_no_packaging_label_settings_exist()
    {
        $factory = Factory::factory()->create([
            'email_verified' => true,
            'account_verified' => 2,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->getJson('/api/v1/factories/label-settings/packaging-label');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'factory_id' => $factory->id,
                    'front_price' => 0,
                    'back_price' => 0,
                    'is_active' => false,
                ],
                'message' => 'No packaging label settings found.',
            ]);
    }

    /** @test */
    public function factory_can_create_packaging_label_settings_on_first_update()
    {
        $factory = Factory::factory()->create([
            'email_verified' => true,
            'account_verified' => 2,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->putJson('/api/v1/factories/label-settings/packaging-label', [
                'front_price' => 150.50,
                'back_price' => 120.25,
                'is_active' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Packaging label settings updated successfully.',
            ]);

        $this->assertDatabaseHas('factory_packaging_labels', [
            'factory_id' => $factory->id,
        ]);
    }

    /** @test */
    public function factory_can_update_existing_packaging_label_settings()
    {
        $factory = Factory::factory()->create([
            'email_verified' => true,
            'account_verified' => 2,
        ]);

        PackagingLabel::create([
            'factory_id' => $factory->id,
            'front_price' => 100.00,
            'back_price' => 80.00,
            'is_active' => false,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->putJson('/api/v1/factories/label-settings/packaging-label', [
                'front_price' => 200.00,
                'back_price' => 160.00,
                'is_active' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Packaging label settings updated successfully.',
            ]);

        $this->assertDatabaseHas('factory_packaging_labels', [
            'factory_id' => $factory->id,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function packaging_label_update_fails_validation_when_front_price_is_missing()
    {
        $factory = Factory::factory()->create([
            'email_verified' => true,
            'account_verified' => 2,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->putJson('/api/v1/factories/label-settings/packaging-label', [
                'back_price' => 120.25,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['front_price']);
    }

    /** @test */
    public function packaging_label_update_fails_validation_when_back_price_is_missing()
    {
        $factory = Factory::factory()->create([
            'email_verified' => true,
            'account_verified' => 2,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->putJson('/api/v1/factories/label-settings/packaging-label', [
                'front_price' => 150.50,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['back_price']);
    }

    /** @test */
    public function packaging_label_update_fails_validation_when_price_is_negative()
    {
        $factory = Factory::factory()->create([
            'email_verified' => true,
            'account_verified' => 2,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->putJson('/api/v1/factories/label-settings/packaging-label', [
                'front_price' => -10,
                'back_price' => 120.25,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['front_price']);
    }

    /** @test */
    public function unauthenticated_request_cannot_access_packaging_label_settings()
    {
        $response = $this->getJson('/api/v1/factories/label-settings/packaging-label');

        $response->assertStatus(401);
    }

    /** @test */
    public function unauthenticated_request_cannot_update_packaging_label_settings()
    {
        $response = $this->putJson('/api/v1/factories/label-settings/packaging-label', [
            'front_price' => 150.50,
            'back_price' => 120.25,
        ]);

        $response->assertStatus(401);
    }

    // ── Hang Tag ─────────────────────────────────────────────────────────────────

    /** @test */
    public function factory_can_retrieve_hang_tag_settings_when_they_exist()
    {
        $factory = Factory::factory()->create([
            'email_verified' => true,
            'account_verified' => 2,
        ]);

        HangTag::create([
            'factory_id' => $factory->id,
            'front_price' => 75.00,
            'back_price' => 50.00,
            'is_active' => true,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->getJson('/api/v1/factories/label-settings/hang-tag');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Hang tag settings retrieved successfully.',
            ])
            ->assertJsonStructure([
                'success',
                'data' => ['id', 'factory_id', 'front_price', 'back_price', 'is_active'],
                'message',
            ]);
    }

    /** @test */
    public function factory_receives_defaults_when_no_hang_tag_settings_exist()
    {
        $factory = Factory::factory()->create([
            'email_verified' => true,
            'account_verified' => 2,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->getJson('/api/v1/factories/label-settings/hang-tag');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'factory_id' => $factory->id,
                    'front_price' => 0,
                    'back_price' => 0,
                    'is_active' => false,
                ],
                'message' => 'No hang tag settings found.',
            ]);
    }

    /** @test */
    public function factory_can_create_hang_tag_settings_on_first_update()
    {
        $factory = Factory::factory()->create([
            'email_verified' => true,
            'account_verified' => 2,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->putJson('/api/v1/factories/label-settings/hang-tag', [
                'front_price' => 75.00,
                'back_price' => 50.00,
                'is_active' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Hang tag settings updated successfully.',
            ]);

        $this->assertDatabaseHas('factory_hang_tags', [
            'factory_id' => $factory->id,
        ]);
    }

    /** @test */
    public function factory_can_update_existing_hang_tag_settings()
    {
        $factory = Factory::factory()->create([
            'email_verified' => true,
            'account_verified' => 2,
        ]);

        HangTag::create([
            'factory_id' => $factory->id,
            'front_price' => 50.00,
            'back_price' => 30.00,
            'is_active' => false,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->putJson('/api/v1/factories/label-settings/hang-tag', [
                'front_price' => 90.00,
                'back_price' => 60.00,
                'is_active' => true,
            ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Hang tag settings updated successfully.',
            ]);

        $this->assertDatabaseHas('factory_hang_tags', [
            'factory_id' => $factory->id,
            'is_active' => true,
        ]);
    }

    /** @test */
    public function hang_tag_update_fails_validation_when_front_price_is_missing()
    {
        $factory = Factory::factory()->create([
            'email_verified' => true,
            'account_verified' => 2,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->putJson('/api/v1/factories/label-settings/hang-tag', [
                'back_price' => 50.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['front_price']);
    }

    /** @test */
    public function hang_tag_update_fails_validation_when_back_price_is_missing()
    {
        $factory = Factory::factory()->create([
            'email_verified' => true,
            'account_verified' => 2,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->putJson('/api/v1/factories/label-settings/hang-tag', [
                'front_price' => 75.00,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['back_price']);
    }

    /** @test */
    public function hang_tag_update_fails_validation_when_price_is_negative()
    {
        $factory = Factory::factory()->create([
            'email_verified' => true,
            'account_verified' => 2,
        ]);

        $response = $this->actingAs($factory, 'factory')
            ->putJson('/api/v1/factories/label-settings/hang-tag', [
                'front_price' => 75.00,
                'back_price' => -5,
            ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['back_price']);
    }

    /** @test */
    public function unauthenticated_request_cannot_access_hang_tag_settings()
    {
        $response = $this->getJson('/api/v1/factories/label-settings/hang-tag');

        $response->assertStatus(401);
    }

    /** @test */
    public function unauthenticated_request_cannot_update_hang_tag_settings()
    {
        $response = $this->putJson('/api/v1/factories/label-settings/hang-tag', [
            'front_price' => 75.00,
            'back_price' => 50.00,
        ]);

        $response->assertStatus(401);
    }
}
