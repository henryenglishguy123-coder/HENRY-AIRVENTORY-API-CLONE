<?php

namespace Tests\Feature;

use App\Models\Catalog\Industry\CatalogIndustry;
use App\Models\Catalog\Industry\CatalogIndustryMeta;
use App\Models\Customer\Vendor;
use App\Models\Factory\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthMeTest extends TestCase
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

    /**
     * Test auth/me with factory authentication returns correct data.
     */
    public function test_auth_me_with_factory_authentication_succeeds(): void
    {
        $industry = $this->createTestIndustry();

        $factory = Factory::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'factory@example.com',
            'phone_number' => '+1234567890',
            'password' => 'password123',
            'email_verified_at' => now(),
            'account_status' => 1,
            'account_verified' => 1,
        ]);
        $factory->industries()->attach($industry->id);

        $token = auth('factory')->login($factory);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'John Doe',
                'email' => 'factory@example.com',
                'role' => 'factory',
                'accountStatus' => 'enabled',
                'emailVerified' => true,
                'accountVerified' => 'verified',
            ])
            ->assertJsonMissing(['id', 'user_id', 'userId']);
    }

    /**
     * Test auth/me with customer authentication returns correct data.
     */
    public function test_auth_me_with_customer_authentication_succeeds(): void
    {
        $customer = Vendor::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'customer@example.com',
            'password' => 'password123',
            'email_verified_at' => now(),
            'account_status' => 1,
        ]);

        $token = auth('customer')->login($customer);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Jane Smith',
                'email' => 'customer@example.com',
                'role' => 'customer',
                'accountStatus' => 'enabled',
                'emailVerified' => true,
            ])
            ->assertJsonMissing(['id', 'user_id', 'userId', 'accountVerified']);
    }

    /**
     * Test auth/me with unverified factory email returns emailVerified false.
     */
    public function test_auth_me_with_unverified_factory_email_returns_false(): void
    {
        $industry = $this->createTestIndustry();

        $factory = Factory::create([
            'first_name' => 'Bob',
            'last_name' => 'Builder',
            'email' => 'unverified@factory.com',
            'phone_number' => '+1234567890',
            'password' => 'password123',
            'email_verified_at' => null,
            'account_status' => 1,
            'account_verified' => 0,
        ]);
        $factory->industries()->attach($industry->id);

        $token = auth('factory')->login($factory);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'name' => 'Bob Builder',
                'email' => 'unverified@factory.com',
                'role' => 'factory',
                'emailVerified' => false,
                'accountVerified' => 'rejected',
            ]);
    }

    /**
     * Test auth/me with inactive factory account returns inactive status.
     */
    public function test_auth_me_with_inactive_factory_returns_inactive_status(): void
    {
        $industry = $this->createTestIndustry();

        $factory = Factory::create([
            'first_name' => 'Inactive',
            'last_name' => 'User',
            'email' => 'inactive@factory.com',
            'phone_number' => '+1234567890',
            'password' => 'password123',
            'email_verified_at' => now(),
            'account_status' => 0, // Inactive
            'account_verified' => 1,
        ]);
        $factory->industries()->attach($industry->id);

        $token = auth('factory')->login($factory);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/auth/me');

        $response->assertStatus(200)
            ->assertJson([
                'accountStatus' => 'disabled',
            ]);
    }

    /**
     * Test auth/me without authentication returns 401.
     */
    public function test_auth_me_without_authentication_returns_401(): void
    {
        $response = $this->getJson('/api/v1/auth/me');

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Unauthenticated.',
            ]);
    }
}
