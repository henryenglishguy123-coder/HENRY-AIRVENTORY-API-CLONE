<?php

namespace Tests\Unit\Services\Reports;

use App\Models\Customer\Vendor;
use App\Services\Reports\DailyVendorReportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class DailyVendorReportServiceTest extends TestCase
{
    use RefreshDatabase;

    private DailyVendorReportService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new DailyVendorReportService;
    }

    public function test_build_window_with_no_last_run_returns_24_hour_window(): void
    {
        Carbon::setTestNow('2024-01-15 12:00:00');

        [$from, $to] = $this->service->buildWindow(null);

        $this->assertEquals('2024-01-14 12:00:00', $from->toDateTimeString());
        $this->assertEquals('2024-01-15 12:00:00', $to->toDateTimeString());
    }

    public function test_build_window_with_last_run_uses_last_run_as_start(): void
    {
        Carbon::setTestNow('2024-01-15 12:00:00');
        $lastRun = Carbon::parse('2024-01-14 08:00:00');

        [$from, $to] = $this->service->buildWindow($lastRun);

        $this->assertEquals('2024-01-14 08:00:00', $from->toDateTimeString());
        $this->assertEquals('2024-01-15 12:00:00', $to->toDateTimeString());
    }

    public function test_metrics_for_vendor_returns_correct_structure(): void
    {
        $vendor = Vendor::factory()->create();
        $from = Carbon::parse('2024-01-14 00:00:00');
        $to = Carbon::parse('2024-01-15 00:00:00');

        $metrics = $this->service->metricsForVendor($vendor, $from, $to);

        $this->assertIsArray($metrics);
        $this->assertArrayHasKey('window', $metrics);
        $this->assertArrayHasKey('window_metrics', $metrics);
        $this->assertArrayHasKey('overall', $metrics);

        $this->assertEquals($from, $metrics['window']['from']);
        $this->assertEquals($to, $metrics['window']['to']);

        $this->assertArrayHasKey('total_orders', $metrics['window_metrics']);
        $this->assertArrayHasKey('paid_orders', $metrics['window_metrics']);
        $this->assertArrayHasKey('unpaid_orders', $metrics['window_metrics']);
        $this->assertArrayHasKey('shipped_orders', $metrics['window_metrics']);
        $this->assertArrayHasKey('exceptions', $metrics['window_metrics']);

        $this->assertArrayHasKey('unpaid_orders', $metrics['overall']);
        $this->assertArrayHasKey('exceptions', $metrics['overall']);
    }

    public function test_metrics_for_vendor_with_no_orders_returns_zero_counts(): void
    {
        $vendor = Vendor::factory()->create();
        $from = Carbon::parse('2024-01-14 00:00:00');
        $to = Carbon::parse('2024-01-15 00:00:00');

        $metrics = $this->service->metricsForVendor($vendor, $from, $to);

        $this->assertEquals(0, $metrics['window_metrics']['total_orders']);
        $this->assertEquals(0, $metrics['window_metrics']['paid_orders']);
        $this->assertEquals(0, $metrics['window_metrics']['unpaid_orders']);
        $this->assertEquals(0, $metrics['window_metrics']['shipped_orders']);
        $this->assertEquals(0, $metrics['window_metrics']['exceptions']);
        $this->assertEquals(0, $metrics['overall']['unpaid_orders']);
        $this->assertEquals(0, $metrics['overall']['exceptions']);
    }
}
