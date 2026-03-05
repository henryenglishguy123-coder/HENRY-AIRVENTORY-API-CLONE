<?php

namespace Tests\Feature\Commands;

use App\Mail\Sales\DailyVendorReportMail;
use App\Models\Customer\Vendor;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SendDailyVendorReportTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Cache::flush();
    }

    public function test_command_sends_report_to_vendors_with_email_notifications_enabled(): void
    {
        $vendor = Vendor::factory()->create(['email' => 'vendor@example.com']);

        // Enable email notifications for this vendor
        \App\Support\Customers\CustomerMeta::set($vendor->id, 'notify_email', '1');

        $this->artisan('report:daily-vendor')
            ->assertSuccessful();

        Mail::assertQueued(DailyVendorReportMail::class, function ($mail) use ($vendor) {
            return $mail->hasTo('vendor@example.com') && $mail->vendor->id === $vendor->id;
        });
    }

    public function test_command_does_not_send_report_when_notifications_disabled(): void
    {
        $vendor = Vendor::factory()->create(['email' => 'vendor@example.com']);

        // Disable email notifications for this vendor
        \App\Support\Customers\CustomerMeta::set($vendor->id, 'notify_email', '0');

        $this->artisan('report:daily-vendor')
            ->assertSuccessful();

        Mail::assertNotQueued(DailyVendorReportMail::class);
    }

    public function test_command_deduplicates_emails_within_same_window(): void
    {
        $vendor = Vendor::factory()->create(['email' => 'vendor@example.com']);
        \App\Support\Customers\CustomerMeta::set($vendor->id, 'notify_email', '1');

        // Run command twice
        $this->artisan('report:daily-vendor')->assertSuccessful();
        $this->artisan('report:daily-vendor')->assertSuccessful();

        // Should only queue one email due to deduplication
        Mail::assertQueued(DailyVendorReportMail::class, 1);
    }

    public function test_command_prevents_concurrent_execution(): void
    {
        // Acquire lock
        $lock = Cache::lock('daily_vendor_report:mutex', 600);
        $lock->get();

        try {
            $this->artisan('report:daily-vendor')
                ->expectsOutput('Daily vendor report is already running.')
                ->assertFailed();
        } finally {
            $lock->release();
        }
    }

    public function test_command_advances_last_run_timestamp_after_execution(): void
    {
        $vendor = Vendor::factory()->create(['email' => 'vendor@example.com']);
        \App\Support\Customers\CustomerMeta::set($vendor->id, 'notify_email', '1');

        $this->assertNull(Cache::get('daily_vendor_report:last_run_at'));

        $this->artisan('report:daily-vendor')->assertSuccessful();

        $this->assertNotNull(Cache::get('daily_vendor_report:last_run_at'));
    }

    public function test_command_logs_warning_when_vendor_has_no_valid_recipients(): void
    {
        $vendor = Vendor::factory()->create(['email' => null]);
        \App\Support\Customers\CustomerMeta::set($vendor->id, 'notify_email', '1');

        $this->artisan('report:daily-vendor')->assertSuccessful();

        Mail::assertNothingQueued();
    }
}
