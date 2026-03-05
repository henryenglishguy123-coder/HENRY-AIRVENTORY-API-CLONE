<?php

namespace Tests\Feature;

use App\Models\Catalog\Industry\CatalogIndustry;
use App\Models\Catalog\Industry\CatalogIndustryMeta;
use App\Models\Factory\Factory;
use App\Models\Factory\FactoryBusiness;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class FactoryBusinessInformationTest extends TestCase
{
    use RefreshDatabase;

    protected function createTestIndustry()
    {
        $industry = CatalogIndustry::create(['slug' => 'test-industry']);
        CatalogIndustryMeta::create([
            'catalog_industry_id' => $industry->id,
            'name' => 'Test Industry',
            'status' => 1,
        ]);

        return $industry;
    }

    protected function createVerifiedFactory(array $attributes = [])
    {
        $industry = $this->createTestIndustry();
        $factory = Factory::create(array_merge([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'john@factory.com',
            'phone_number' => '+1234567890',
            'password' => 'password123',
            'email_verified_at' => now(),
            'account_status' => 1,
        ], $attributes));
        $factory->industries()->attach($industry->id);

        return $factory;
    }

    /**
     * Test storing business information successfully.
     */
    public function test_store_business_information_succeeds(): void
    {
        Storage::fake('public');

        $factory = $this->createVerifiedFactory();
        $token = auth('factory')->login($factory);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/factories/business-information', [
            'company_name' => 'ITechPanel LLP',
            'registration_number' => 'REG123456',
            'tax_vat_number' => 'VAT789012',
            'registered_address' => '123 Main Street',
            'country_id' => 1,
            'state_id' => 1,
            'city' => 'Jaipur',
            'postal_code' => '302020',
            'registration_certificate' => UploadedFile::fake()->create('registration.pdf', 1000),
            'tax_certificate' => UploadedFile::fake()->create('tax.pdf', 1000),
            'import_export_certificate' => UploadedFile::fake()->create('import_export.pdf', 1000),
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Business information saved successfully.',
            ])
            ->assertJsonStructure([
                'success',
                'data' => [
                    'business' => [
                        'id',
                        'company_name',
                        'registration_number',
                        'tax_vat_number',
                        'registered_address',
                        'country_id',
                        'state_id',
                        'city',
                        'postal_code',
                        'registration_certificate',
                        'tax_certificate',
                        'import_export_certificate',
                    ],
                ],
                'message',
            ]);

        // Verify business record was created
        $this->assertDatabaseHas('factory_business', [
            'factory_id' => $factory->id,
            'company_name' => 'ITechPanel LLP',
            'city' => 'Jaipur',
        ]);

        // Verify basic_info_status was set
        $factory->refresh();
        $this->assertEquals('1', $factory->metaValue('basic_info_status'));

        // Verify files were uploaded
        Storage::disk('public')->assertExists($response->json('data.business.registration_certificate'));
        Storage::disk('public')->assertExists($response->json('data.business.tax_certificate'));
        Storage::disk('public')->assertExists($response->json('data.business.import_export_certificate'));
    }

    /**
     * Test storing business information without authentication fails.
     */
    public function test_store_business_information_without_auth_fails(): void
    {
        $response = $this->postJson('/api/v1/factories/business-information', [
            'company_name' => 'Test Company',
            'registered_address' => '123 Main Street',
            'country_id' => 1,
            'city' => 'Jaipur',
            'postal_code' => '302020',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test storing business information with missing required fields fails.
     */
    public function test_store_business_information_with_missing_fields_fails(): void
    {
        $factory = $this->createVerifiedFactory();
        $token = auth('factory')->login($factory);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/factories/business-information', [
            'company_name' => 'Test Company',
            // Missing required fields: registered_address, country_id, city, postal_code
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['registered_address', 'country_id', 'city', 'postal_code']);
    }

    /**
     * Test updating existing business information.
     */
    public function test_update_existing_business_information_succeeds(): void
    {
        $factory = $this->createVerifiedFactory();
        $token = auth('factory')->login($factory);

        // Create initial business information
        FactoryBusiness::create([
            'factory_id' => $factory->id,
            'company_name' => 'Old Company Name',
            'registered_address' => 'Old Address',
            'country_id' => 1,
            'city' => 'Old City',
            'postal_code' => '111111',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/factories/business-information', [
            'company_name' => 'New Company Name',
            'registered_address' => 'New Address',
            'country_id' => 2,
            'city' => 'New City',
            'postal_code' => '222222',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'business' => [
                        'company_name' => 'New Company Name',
                        'city' => 'New City',
                        'postal_code' => '222222',
                    ],
                ],
            ]);

        // Verify only one record exists (updated, not duplicated)
        $this->assertEquals(1, FactoryBusiness::where('factory_id', $factory->id)->count());
    }

    /**
     * Test retrieving business information.
     */
    public function test_show_business_information_succeeds(): void
    {
        $factory = $this->createVerifiedFactory();
        $token = auth('factory')->login($factory);

        FactoryBusiness::create([
            'factory_id' => $factory->id,
            'company_name' => 'Test Company',
            'registered_address' => '123 Main Street',
            'country_id' => 1,
            'city' => 'Jaipur',
            'postal_code' => '302020',
        ]);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/factories/business-information');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'business' => [
                        'company_name' => 'Test Company',
                        'city' => 'Jaipur',
                    ],
                ],
            ]);
    }

    /**
     * Test retrieving business information when none exists.
     */
    public function test_show_business_information_when_none_exists(): void
    {
        $factory = $this->createVerifiedFactory();
        $token = auth('factory')->login($factory);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/factories/business-information');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => null,
                'message' => 'No business information found.',
            ]);
    }

    /**
     * Test that verified factory cannot update business information.
     */
    public function test_verified_factory_cannot_update_business_information(): void
    {
        // Create factory with account_verified = 1 (verified)
        $factory = $this->createVerifiedFactory(['account_verified' => 1]);
        $token = auth('factory')->login($factory);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/factories/business-information', [
            'company_name' => 'Test Company',
            'registered_address' => '123 Main Street',
            'country_id' => 1,
            'city' => 'Jaipur',
            'postal_code' => '302020',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'success' => false,
                'message' => 'Business information cannot be updated after account verification.',
            ]);
    }

    /**
     * Test that unverified factory can update business information.
     */
    public function test_unverified_factory_can_update_business_information(): void
    {
        // Create factory with account_verified = 2 (pending)
        $factory = $this->createVerifiedFactory(['account_verified' => 2]);
        $token = auth('factory')->login($factory);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/factories/business-information', [
            'company_name' => 'Test Company',
            'registered_address' => '123 Main Street',
            'country_id' => 1,
            'city' => 'Jaipur',
            'postal_code' => '302020',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'business' => [
                        'company_name' => 'Test Company',
                    ],
                ],
            ]);
    }

    /**
     * Test that rejected factory can update business information.
     */
    public function test_rejected_factory_can_update_business_information(): void
    {
        // Create factory with account_verified = 0 (rejected)
        $factory = $this->createVerifiedFactory(['account_verified' => 0]);
        $token = auth('factory')->login($factory);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/factories/business-information', [
            'company_name' => 'Test Company After Rejection',
            'registered_address' => '456 New Street',
            'country_id' => 1,
            'city' => 'Mumbai',
            'postal_code' => '400001',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'data' => [
                    'business' => [
                        'company_name' => 'Test Company After Rejection',
                        'city' => 'Mumbai',
                    ],
                ],
            ]);
    }
}
