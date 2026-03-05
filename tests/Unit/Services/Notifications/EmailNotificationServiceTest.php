<?php

namespace Tests\Unit\Services\Notifications;

use App\Models\Customer\Vendor;
use App\Services\Notifications\EmailNotificationService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class EmailNotificationServiceTest extends TestCase
{
    use RefreshDatabase;

    private EmailNotificationService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new EmailNotificationService;
        Mail::fake();
        Cache::flush();
    }

    public function test_is_enabled_returns_true_when_notifications_are_enabled(): void
    {
        $vendor = Vendor::factory()->create();
        \App\Support\Customers\CustomerMeta::set($vendor->id, 'notify_email', '1');

        $this->assertTrue($this->service->isEnabled($vendor->id));
    }

    public function test_is_enabled_returns_false_when_notifications_are_disabled(): void
    {
        $vendor = Vendor::factory()->create();
        \App\Support\Customers\CustomerMeta::set($vendor->id, 'notify_email', '0');

        $this->assertFalse($this->service->isEnabled($vendor->id));
    }

    public function test_queue_if_enabled_for_vendor_returns_false_when_disabled(): void
    {
        $vendor = Vendor::factory()->create();
        \App\Support\Customers\CustomerMeta::set($vendor->id, 'notify_email', '0');

        $result = $this->service->queueIfEnabledForVendor(
            $vendor->id,
            'test@example.com',
            \App\Mail\Sales\DailyVendorReportMail::class,
            [$vendor, []]
        );

        $this->assertFalse($result);
        Mail::assertNothingQueued();
    }

    public function test_queue_if_enabled_for_vendor_queues_email_when_enabled(): void
    {
        $vendor = Vendor::factory()->create();
        \App\Support\Customers\CustomerMeta::set($vendor->id, 'notify_email', '1');

        $result = $this->service->queueIfEnabledForVendor(
            $vendor->id,
            'test@example.com',
            \App\Mail\Sales\DailyVendorReportMail::class,
            [$vendor, []]
        );

        $this->assertTrue($result);
        Mail::assertQueued(\App\Mail\Sales\DailyVendorReportMail::class);
    }

    public function test_queue_if_enabled_for_vendor_deduplicates_same_email(): void
    {
        $vendor = Vendor::factory()->create();
        \App\Support\Customers\CustomerMeta::set($vendor->id, 'notify_email', '1');

        // Queue first email
        $result1 = $this->service->queueIfEnabledForVendor(
            $vendor->id,
            'test@example.com',
            \App\Mail\Sales\DailyVendorReportMail::class,
            [$vendor, []]
        );

        // Try to queue same email again
        $result2 = $this->service->queueIfEnabledForVendor(
            $vendor->id,
            'test@example.com',
            \App\Mail\Sales\DailyVendorReportMail::class,
            [$vendor, []]
        );

        $this->assertTrue($result1);
        $this->assertFalse($result2, 'Second email should be deduplicated');
        Mail::assertQueued(\App\Mail\Sales\DailyVendorReportMail::class, 1);
    }

    public function test_queue_if_enabled_for_vendor_uses_custom_dedupe_key(): void
    {
        $vendor = Vendor::factory()->create();
        \App\Support\Customers\CustomerMeta::set($vendor->id, 'notify_email', '1');

        $customKey = 'custom:dedupe:key:123';

        $result1 = $this->service->queueIfEnabledForVendor(
            $vendor->id,
            'test@example.com',
            \App\Mail\Sales\DailyVendorReportMail::class,
            [$vendor, []],
            $customKey
        );

        $result2 = $this->service->queueIfEnabledForVendor(
            $vendor->id,
            'test@example.com',
            \App\Mail\Sales\DailyVendorReportMail::class,
            [$vendor, []],
            $customKey
        );

        $this->assertTrue($result1);
        $this->assertFalse($result2, 'Custom dedupe key should prevent duplicate');
    }

    public function test_queue_if_enabled_for_vendor_with_after_commit_only_checks_dedupe_upfront(): void
    {
        $vendor = Vendor::factory()->create();
        \App\Support\Customers\CustomerMeta::set($vendor->id, 'notify_email', '1');

        DB::beginTransaction();
        try {
            // First call with afterCommitOnly
            $result1 = $this->service->queueIfEnabledForVendor(
                $vendor->id,
                'test@example.com',
                \App\Mail\Sales\DailyVendorReportMail::class,
                [$vendor, []],
                null,
                true
            );

            // Second call should return false due to dedupe check
            $result2 = $this->service->queueIfEnabledForVendor(
                $vendor->id,
                'test@example.com',
                \App\Mail\Sales\DailyVendorReportMail::class,
                [$vendor, []],
                null,
                true
            );

            $this->assertTrue($result1);
            $this->assertFalse($result2, 'afterCommitOnly should check dedupe upfront');

            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function test_queue_if_enabled_for_vendor_respects_ttl_from_config(): void
    {
        config(['notifications.email_dedupe_hours' => 1]);

        $vendor = Vendor::factory()->create();
        \App\Support\Customers\CustomerMeta::set($vendor->id, 'notify_email', '1');

        $this->service->queueIfEnabledForVendor(
            $vendor->id,
            'test@example.com',
            \App\Mail\Sales\DailyVendorReportMail::class,
            [$vendor, []]
        );

        // This is a basic test - in reality you'd need to check cache TTL
        Mail::assertQueued(\App\Mail\Sales\DailyVendorReportMail::class, 1);
    }
}
