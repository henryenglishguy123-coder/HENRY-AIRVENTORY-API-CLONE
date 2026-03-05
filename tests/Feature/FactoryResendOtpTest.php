<?php

namespace Tests\Feature;

use App\Mail\Factory\VerificationCodeMail;
use App\Models\Catalog\Industry\CatalogIndustry;
use App\Models\Catalog\Industry\CatalogIndustryMeta;
use App\Models\Factory\Factory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class FactoryResendOtpTest extends TestCase
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
     * Test resend OTP with valid unverified email succeeds.
     */
    public function test_resend_otp_with_unverified_email_succeeds(): void
    {
        Mail::fake();

        $industry = $this->createTestIndustry();

        $factory = Factory::create([
            'first_name' => 'John',
            'last_name' => 'Doe',
            'email' => 'unverified@factory.com',
            'phone_number' => '+1234567890',
            'password' => 'password123',
            'email_verification_code' => '123456',
            'email_verification_code_expires_at' => now()->subMinutes(5), // Expired
            'email_verified_at' => null,
        ]);
        $factory->industries()->attach($industry->id);

        $response = $this->postJson('/api/v1/factories/resend-otp', [
            'email' => 'unverified@factory.com',
        ]);

        $response->assertStatus(200)
            ->assertJson([
                'success' => true,
                'message' => 'Verification code has been resent to your email.',
            ]);

        // Assert email was sent
        Mail::assertQueued(VerificationCodeMail::class);

        // Assert verification code was updated
        $factory->refresh();
        $this->assertNotEquals('123456', $factory->email_verification_code);
        $this->assertNotNull($factory->email_verification_code);
        $this->assertGreaterThan(now(), $factory->email_verification_code_expires_at);
    }

    /**
     * Test resend OTP with already verified email fails.
     */
    public function test_resend_otp_with_verified_email_fails(): void
    {
        Mail::fake();

        $industry = $this->createTestIndustry();

        $factory = Factory::create([
            'first_name' => 'Jane',
            'last_name' => 'Smith',
            'email' => 'verified@factory.com',
            'phone_number' => '+1234567890',
            'password' => 'password123',
            'email_verified_at' => now(),
        ]);
        $factory->industries()->attach($industry->id);

        $response = $this->postJson('/api/v1/factories/resend-otp', [
            'email' => 'verified@factory.com',
        ]);

        $response->assertStatus(422)
            ->assertJson([
                'success' => false,
                'message' => 'Email is already verified.',
            ]);

        // Assert no email was sent
        Mail::assertNotQueued(VerificationCodeMail::class);
    }

    /**
     * Test resend OTP with non-existent email fails.
     */
    public function test_resend_otp_with_nonexistent_email_fails(): void
    {
        $response = $this->postJson('/api/v1/factories/resend-otp', [
            'email' => 'nonexistent@factory.com',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test resend OTP with invalid email format fails.
     */
    public function test_resend_otp_with_invalid_email_fails(): void
    {
        $response = $this->postJson('/api/v1/factories/resend-otp', [
            'email' => 'not-an-email',
        ]);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }

    /**
     * Test resend OTP without email fails.
     */
    public function test_resend_otp_without_email_fails(): void
    {
        $response = $this->postJson('/api/v1/factories/resend-otp', []);

        $response->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    }
}
