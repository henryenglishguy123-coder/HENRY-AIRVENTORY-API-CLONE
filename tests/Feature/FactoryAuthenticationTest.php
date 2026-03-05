<?php

namespace Tests\Feature;

use App\Mail\Factory\FactoryPasswordChangedMail;
use App\Mail\Factory\FactoryResetPasswordMail;
use App\Models\Catalog\Industry\CatalogIndustry;
use App\Models\Catalog\Industry\CatalogIndustryMeta;
use App\Models\Factory\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FactoryAuthenticationTest extends TestCase
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
     * Test successful login with valid credentials
     */
    public function test_login_with_valid_credentials_succeeds(): void
    {
        $this->createVerifiedFactory([
            'email' => 'test@factory.com',
            'password' => 'SecurePass123',
        ]);

        $response = $this->postJson('/api/v1/factories/login', [
            'email' => 'test@factory.com',
            'password' => 'SecurePass123',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure([
                'token',
                'factory' => [
                    'name',
                    'email',
                ],
            ])
            ->assertJsonMissing(['id', 'user_id', 'userId']);

        $this->assertNotEmpty($response->json('token'));
    }

    /**
     * Test login with invalid credentials fails
     */
    public function test_login_with_invalid_credentials_fails(): void
    {
        $this->createVerifiedFactory([
            'email' => 'test@factory.com',
            'password' => 'CorrectPassword',
        ]);

        $response = $this->postJson('/api/v1/factories/login', [
            'email' => 'test@factory.com',
            'password' => 'WrongPassword',
        ]);

        $response->assertStatus(401)
            ->assertJson([
                'message' => 'Invalid credentials.',
            ]);
    }

    /**
     * Test login with unverified email fails
     */
    public function test_login_with_unverified_email_fails(): void
    {
        $this->createVerifiedFactory([
            'email' => 'unverified@factory.com',
            'password' => 'password123',
            'email_verified_at' => null,
        ]);

        $response = $this->postJson('/api/v1/factories/login', [
            'email' => 'unverified@factory.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Email address not verified. Please verify your email before logging in.',
            ]);
    }

    /**
     * Test login with inactive account fails
     */
    public function test_login_with_inactive_account_fails(): void
    {
        $this->createVerifiedFactory([
            'email' => 'inactive@factory.com',
            'password' => 'password123',
            'account_status' => 0,
        ]);

        $response = $this->postJson('/api/v1/factories/login', [
            'email' => 'inactive@factory.com',
            'password' => 'password123',
        ]);

        $response->assertStatus(403)
            ->assertJson([
                'message' => 'Your account is inactive. Please contact support.',
            ]);
    }

    /**
     * Test logout endpoint
     */
    public function test_logout_succeeds(): void
    {
        $factory = $this->createVerifiedFactory();
        $token = auth('factory')->login($factory);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/factories/logout');

        $response->assertStatus(200)
            ->assertJson([
                'message' => 'Successfully logged out.',
            ]);
    }

    /**
     * Test forgot password sends reset link
     */
    public function test_forgot_password_sends_reset_link(): void
    {
        Mail::fake();

        $factory = $this->createVerifiedFactory([
            'email' => 'reset@factory.com',
        ]);

        $response = $this->postJson('/api/v1/factories/forgot-password', [
            'email' => 'reset@factory.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'We have emailed your password reset link.',
            ]);

        Mail::assertQueued(FactoryResetPasswordMail::class);

        // Verify reset token was saved
        $factory->refresh();
        $this->assertNotNull($factory->metaValue('password_reset_token'));
        $this->assertNotNull($factory->metaValue('password_reset_expires_at'));
    }

    /**
     * Test forgot password with invalid email fails
     */
    public function test_forgot_password_with_invalid_email_fails(): void
    {
        $response = $this->postJson('/api/v1/factories/forgot-password', [
            'email' => 'nonexistent@factory.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test reset password with valid token succeeds
     */
    public function test_reset_password_with_valid_token_succeeds(): void
    {
        Mail::fake();

        $factory = $this->createVerifiedFactory([
            'email' => 'reset@factory.com',
        ]);

        // Set reset token
        $plainToken = 'test-reset-token-12345';
        $factory->setMetaValue('password_reset_token', hash('sha256', $plainToken));
        $factory->setMetaValue('password_reset_expires_at', Carbon::now()->addMinutes(60));

        $response = $this->postJson('/api/v1/factories/reset-password', [
            'email' => 'reset@factory.com',
            'token' => $plainToken,
            'password' => 'NewSecurePassword123',
            'password_confirmation' => 'NewSecurePassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Your password has been updated successfully. You may now sign in using your new credentials.',
            ]);

        Mail::assertQueued(FactoryPasswordChangedMail::class);

        // Verify password was updated
        $factory->refresh();
        $this->assertTrue(Hash::check('NewSecurePassword123', $factory->password));

        // Verify reset token was cleared
        $this->assertNull($factory->metaValue('password_reset_token'));
        $this->assertNull($factory->metaValue('password_reset_expires_at'));
    }

    /**
     * Test reset password with invalid token fails
     */
    public function test_reset_password_with_invalid_token_fails(): void
    {
        $factory = $this->createVerifiedFactory([
            'email' => 'reset@factory.com',
        ]);

        $plainToken = 'valid-token';
        $factory->setMetaValue('password_reset_token', hash('sha256', $plainToken));
        $factory->setMetaValue('password_reset_expires_at', Carbon::now()->addMinutes(60));

        $response = $this->postJson('/api/v1/factories/reset-password', [
            'email' => 'reset@factory.com',
            'token' => 'invalid-token',
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Invalid reset token.',
            ]);
    }

    /**
     * Test reset password with expired token fails
     */
    public function test_reset_password_with_expired_token_fails(): void
    {
        $factory = $this->createVerifiedFactory([
            'email' => 'reset@factory.com',
        ]);

        $plainToken = 'expired-token';
        $factory->setMetaValue('password_reset_token', hash('sha256', $plainToken));
        $factory->setMetaValue('password_reset_expires_at', Carbon::now()->subMinutes(10));

        $response = $this->postJson('/api/v1/factories/reset-password', [
            'email' => 'reset@factory.com',
            'token' => $plainToken,
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Reset token has expired.',
            ]);
    }

    /**
     * Test set password succeeds for authenticated factory
     */
    public function test_set_password_succeeds(): void
    {
        $factory = $this->createVerifiedFactory([
            'email' => 'newuser@factory.com',
            'password' => 'OldPassword123',
        ]);

        $token = auth('factory')->login($factory);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/factories/set-password', [
            'password' => 'NewPassword123',
            'password_confirmation' => 'NewPassword123',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Password has been updated successfully.',
            ]);

        // Verify password was updated
        $factory->refresh();
        $this->assertTrue(Hash::check('NewPassword123', $factory->password));
    }

    /**
     * Test set password without authentication fails
     */
    public function test_set_password_without_authentication_fails(): void
    {
        $response = $this->postJson('/api/v1/factories/set-password', [
            'password' => 'Password123',
            'password_confirmation' => 'Password123',
        ]);

        $response->assertStatus(401);
    }

    /**
     * Test password confirmation mismatch fails
     */
    public function test_password_confirmation_mismatch_fails(): void
    {
        $factory = $this->createVerifiedFactory([
            'email' => 'test@factory.com',
        ]);

        $token = auth('factory')->login($factory);

        $response = $this->withHeaders([
            'Authorization' => 'Bearer '.$token,
        ])->postJson('/api/v1/factories/set-password', [
            'password' => 'Password123',
            'password_confirmation' => 'DifferentPassword123',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    }

    /**
     * Test login updates last_login timestamp
     */
    public function test_login_updates_last_login_timestamp(): void
    {
        $factory = $this->createVerifiedFactory([
            'email' => 'test@factory.com',
            'password' => 'password123',
            'last_login' => null,
        ]);

        $this->postJson('/api/v1/factories/login', [
            'email' => 'test@factory.com',
            'password' => 'password123',
        ]);

        $factory->refresh();
        $this->assertNotNull($factory->last_login);
    }
}
