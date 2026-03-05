<?php

namespace Tests\Feature;

use App\Mail\Factory\VerificationCodeMail;
use App\Models\Catalog\Industry\CatalogIndustry;
use App\Models\Catalog\Industry\CatalogIndustryMeta;
use App\Models\Factory\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FactoryRegistrationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test factory registration with valid data succeeds.
     */
    public function test_registration_with_valid_data_succeeds(): void
    {
        Mail::fake();

        // Create test industry
        $industry = CatalogIndustry::create(['slug' => 'test-industry']);
        CatalogIndustryMeta::create([
            'catalog_industry_id' => $industry->id,
            'name' => 'Test Industry',
            'status' => 1,
        ]);

        $response = $this->postJson('/api/v1/factories/register', [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'industry_id' => $industry->id,
        ]);

        $response->assertStatus(201)
            ->assertJsonStructure([
                'success',
                'message',
                'data',
            ])
            ->assertJson([
                'success' => true,
                'data' => null,
            ]);

        // Assert factory was created in database
        $this->assertDatabaseHas('factory_users', [
            'email' => 'john@example.com',
            'first_name' => 'John',
            'last_name' => 'Doe',
        ]);

        // Assert factory-industry relationship was created
        $factory = Factory::where('email', 'john@example.com')->first();
        $this->assertDatabaseHas('factory_industries', [
            'factory_id' => $factory->id,
            'catalog_industry_id' => $industry->id,
        ]);

        // Assert email was sent
        Mail::assertQueued(VerificationCodeMail::class);
    }

    /**
     * Test registration with duplicate email fails.
     */
    public function test_registration_with_duplicate_email_fails(): void
    {
        // Create test industry
        $industry = CatalogIndustry::create(['slug' => 'test-industry']);
        CatalogIndustryMeta::create([
            'catalog_industry_id' => $industry->id,
            'name' => 'Test Industry',
            'status' => 1,
        ]);

        // Create existing factory
        $existingFactory = Factory::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'john@example.com',
            'phone_number' => '+9876543210',
            'password' => 'password123',
        ]);
        $existingFactory->industries()->attach($industry->id);

        $response = $this->postJson('/api/v1/factories/register', [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'industry_id' => $industry->id,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test registration with invalid industry_id fails.
     */
    public function test_registration_with_invalid_industry_id_fails(): void
    {
        $response = $this->postJson('/api/v1/factories/register', [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'industry_id' => 99999,
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['industry_id']);
    }

    /**
     * Test verification with correct code succeeds and returns login token.
     */
    public function test_verification_with_correct_code_succeeds(): void
    {
        // Create test industry
        $industry = CatalogIndustry::create(['slug' => 'test-industry']);
        CatalogIndustryMeta::create([
            'catalog_industry_id' => $industry->id,
            'name' => 'Test Industry',
            'status' => 1,
        ]);

        $factory = Factory::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone_number' => '+1234567890',
            'password' => 'password123',
            'email_verification_code' => '123456',
            'email_verification_code_expires_at' => now()->addMinutes(15),
            'email_verified_at' => null,
        ]);
        $factory->industries()->attach($industry->id);

        $response = $this->postJson('/api/v1/factories/verify-email', [
            'email' => 'john@example.com',
            'code' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'factory' => [
                        'name',
                        'email',
                    ],
                ],
            ])
            ->assertJson([
                'success' => true,
            ])
            ->assertJsonMissing(['id', 'user_id', 'userId']);

        // Assert token is present
        $this->assertNotEmpty($response->json('data.token'));

        // Assert factory is verified
        $factory->refresh();
        $this->assertNotNull($factory->email_verified_at);
        $this->assertNull($factory->email_verification_code);
        $this->assertNull($factory->email_verification_code_expires_at);
    }

    /**
     * Test verification with incorrect code fails.
     */
    public function test_verification_with_incorrect_code_fails(): void
    {
        // Create test industry
        $industry = CatalogIndustry::create(['slug' => 'test-industry']);
        CatalogIndustryMeta::create([
            'catalog_industry_id' => $industry->id,
            'name' => 'Test Industry',
            'status' => 1,
        ]);

        $factory = Factory::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone_number' => '+1234567890',
            'password' => 'password123',
            'email_verification_code' => '123456',
            'email_verification_code_expires_at' => now()->addMinutes(15),
            'email_verified_at' => null,
        ]);
        $factory->industries()->attach($industry->id);

        $response = $this->postJson('/api/v1/factories/verify-email', [
            'email' => 'john@example.com',
            'code' => '654321',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid verification code.',
            ]);
    }

    /**
     * Test verification with expired code fails.
     */
    public function test_verification_with_expired_code_fails(): void
    {
        // Create test industry
        $industry = CatalogIndustry::create(['slug' => 'test-industry']);
        CatalogIndustryMeta::create([
            'catalog_industry_id' => $industry->id,
            'name' => 'Test Industry',
            'status' => 1,
        ]);

        $factory = Factory::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone_number' => '+1234567890',
            'password' => 'password123',
            'email_verification_code' => '123456',
            'email_verification_code_expires_at' => now()->subMinutes(1), // Expired
            'email_verified_at' => null,
        ]);
        $factory->industries()->attach($industry->id);

        $response = $this->postJson('/api/v1/factories/verify-email', [
            'email' => 'john@example.com',
            'code' => '123456',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Verification code has expired.',
            ]);
    }

    /**
     * Test verification with already verified email returns login token.
     */
    public function test_verification_with_already_verified_email_returns_token(): void
    {
        // Create test industry
        $industry = CatalogIndustry::create(['slug' => 'test-industry']);
        CatalogIndustryMeta::create([
            'catalog_industry_id' => $industry->id,
            'name' => 'Test Industry',
            'status' => 1,
        ]);

        $factory = Factory::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@example.com',
            'phone_number' => '+1234567890',
            'password' => 'password123',
            'email_verification_code' => '123456',
            'email_verification_code_expires_at' => now()->addMinutes(15),
            'email_verified_at' => now(), // Already verified
        ]);
        $factory->industries()->attach($industry->id);

        $response = $this->postJson('/api/v1/factories/verify-email', [
            'email' => 'john@example.com',
            'code' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'data' => [
                    'token',
                    'factory',
                ],
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Email already verified.',
            ])
            ->assertJsonMissing(['id', 'user_id', 'userId']);

        // Assert token is present
        $this->assertNotEmpty($response->json('data.token'));
    }
}
