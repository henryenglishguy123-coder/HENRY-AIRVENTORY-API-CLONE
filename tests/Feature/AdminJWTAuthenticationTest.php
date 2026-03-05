<?php

namespace Tests\Feature;

use App\Models\Admin\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminJWTAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        if (! \Illuminate\Support\Facades\Schema::hasTable('users')) {
            \Illuminate\Support\Facades\Schema::create('users', function (\Illuminate\Database\Schema\Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('email')->unique();
                $table->string('username')->unique();
                $table->string('password');
                $table->string('mobile')->nullable();
                $table->string('user_type')->default('customer');
                $table->boolean('is_blocked')->default(0);
                $table->boolean('is_active')->default(1);
                $table->timestamp('email_verified_at')->nullable();
                $table->timestamp('last_login_at')->nullable();
                $table->rememberToken();
                $table->timestamps();
                $table->softDeletes();
            });
        }
    }

    /**
     * Create a test admin user.
     */
    protected function createTestAdmin(array $attributes = [])
    {
        return User::create(array_merge([
            'name' => 'Test Admin',
            'email' => 'admin@test.com',
            'username' => 'testadmin',
            'password' => Hash::make('password123'),
            'mobile' => '1234567890',
            'user_type' => 'admin',
            'is_blocked' => 0,
            'is_active' => 1,
        ], $attributes));
    }

    /**
     * Test that session-authenticated admin can mint JWT token.
     */
    public function test_session_authenticated_admin_can_mint_jwt_token(): void
    {
        $admin = $this->createTestAdmin([
            'email' => 'admin@example.com',
        ]);

        // Authenticate via session
        $this->actingAs($admin, 'admin');

        // Mint JWT token
        $response = $this->postJson('/api/v1/admin/mint-token');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'token',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'JWT token generated successfully',
            ]);

        $this->assertNotEmpty($response->json('token'));
    }

    /**
     * Test that unauthenticated user cannot mint JWT token.
     */
    public function test_unauthenticated_user_cannot_mint_jwt_token(): void
    {
        $response = $this->postJson('/api/v1/admin/mint-token');

        $response->assertStatus(401);
    }

    /**
     * Test admin can access 'me' endpoint with valid JWT token.
     */
    public function test_admin_can_access_me_endpoint_with_valid_token(): void
    {
        $admin = $this->createTestAdmin([
            'name' => 'John Doe',
            'email' => 'john@example.com',
        ]);

        $token = auth('admin_api')->login($admin);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/admin/me');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'admin' => [
                    'id' => $admin->id,
                    'name' => 'John Doe',
                    'email' => 'john@example.com',
                ],
            ]);
    }

    /**
     * Test 'me' endpoint without token returns unauthorized.
     */
    public function test_me_endpoint_without_token_returns_unauthorized(): void
    {
        $response = $this->getJson('/api/v1/admin/me');

        $response->assertStatus(401);
    }

    /**
     * Test admin logout invalidates JWT token.
     */
    public function test_admin_logout_invalidates_token(): void
    {
        $admin = $this->createTestAdmin();
        $token = auth('admin_api')->login($admin);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/admin/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully logged out',
            ]);

        // Try to access protected endpoint with the same token
        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/admin/me');

        $response->assertStatus(401);
    }

    public function test_admin_logout_via_get_route_is_supported(): void
    {
        $admin = $this->createTestAdmin();
        $token = auth('admin_api')->login($admin);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/admin/logout');

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully logged out',
            ]);
    }

    public function test_admin_logout_clears_admin_session_and_jwt(): void
    {
        $admin = $this->createTestAdmin([
            'email' => 'admin@example.com',
        ]);

        $this->actingAs($admin, 'admin');

        $mintResponse = $this->postJson('/api/v1/admin/mint-token');

        $mintResponse->assertStatus(200);
        $token = $mintResponse->json('token');

        $logoutResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/admin/logout');

        $logoutResponse->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Successfully logged out',
            ]);

        $meResponse = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->getJson('/api/v1/admin/me');

        $meResponse->assertStatus(401);

        $mintAfterLogout = $this->postJson('/api/v1/admin/mint-token');

        $mintAfterLogout->assertStatus(401);
    }


    /**
     * Test admin can refresh JWT token.
     */
    public function test_admin_can_refresh_token(): void
    {
        $admin = $this->createTestAdmin();
        $token = auth('admin_api')->login($admin);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/admin/refresh');

        $response->assertStatus(200)
            ->assertJsonStructure([
                'success',
                'message',
                'token',
            ])
            ->assertJson([
                'success' => true,
                'message' => 'Token refreshed successfully',
            ]);

        $this->assertNotEmpty($response->json('token'));
        $this->assertNotEquals($token, $response->json('token'));
    }
}
