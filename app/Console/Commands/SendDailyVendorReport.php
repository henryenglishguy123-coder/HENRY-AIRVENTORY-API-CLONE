<?php

namespace App\Console\Commands;

use App\Mail\Sales\DailyVendorReportMail;
use App\Models\Customer\Vendor;
use App\Models\Customer\VendorBillingAddress;
use App\Models\Customer\VendorShippingAddress;
use App\Services\Notifications\EmailNotificationService;
use App\Services\Reports\DailyVendorReportService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SendDailyVendorReport extends Command
{
    protected $signature = 'report:daily-vendor';

    protected $description = 'Send daily vendor report emails';

    public function __construct(
        protected DailyVendorReportService $reportService,
        protected EmailNotificationService $emailService
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $lock = Cache::lock('daily_vendor_report:mutex', 600);
        if (! $lock->get()) {
            $this->warn('Daily vendor report is already running.');

            return self::FAILURE;
        }
        try {
            $lastRunAt = Cache::get('daily_vendor_report:last_run_at');
            [$from, $to] = $this->reportService->buildWindow($lastRunAt);

            $sentCount = 0;
            $failedVendors = Cache::get('daily_vendor_report:failed_vendor_ids', []);
            $failedWindowFrom = Cache::get('daily_vendor_report:failed_window_from');
            $vendorQuery = Vendor::query()
                ->whereNotNull('email')
                ->select(['id', 'first_name', 'last_name', 'email']);

            $vendorQuery->chunk(200, function ($vendors) use ($from, $to, &$sentCount, &$failedVendors, $failedWindowFrom) {
                $vendorIds = $vendors->pluck('id')->all();
                $billing = VendorBillingAddress::whereIn('vendor_id', $vendorIds)
                    ->where('is_default', true)
                    ->pluck('email', 'vendor_id');
                $shipping = VendorShippingAddress::whereIn('vendor_id', $vendorIds)
                    ->where('is_default', true)
                    ->pluck('email', 'vendor_id');

                foreach ($vendors as $vendor) {
                    try {
                        $effectiveFrom = (in_array($vendor->id, $failedVendors, true) && $failedWindowFrom)
                            ? $failedWindowFrom
                            : $from;
                        $metrics = $this->reportService->metricsForVendor($vendor, $effectiveFrom, $to);
                        $recipients = array_filter([
                            $vendor->email,
                            $billing[$vendor->id] ?? null,
                            $shipping[$vendor->id] ?? null,
                        ], fn ($e) => is_string($e) && filter_var($e, FILTER_VALIDATE_EMAIL));
                        $recipients = array_values(array_unique($recipients));

                        if (empty($recipients)) {
                            Log::warning('Daily vendor report: no valid recipients', [
                                'vendor_id' => $vendor->id,
                            ]);

                            continue;
                        }

                        $vendorSentCount = 0;
                        foreach ($recipients as $recipient) {
                            $normalizedRecipient = strtolower(trim($recipient));
                            $recipientHash = hash('sha256', $normalizedRecipient);
                            $key = 'daily_vendor_report:'.$vendor->id.':'.$recipientHash.':'.$effectiveFrom->format('YmdHis');
                            $sent = $this->emailService->queueIfEnabledForVendor(
                                $vendor->id,
                                $recipient,
                                DailyVendorReportMail::class,
                                [$vendor, $metrics],
                                $key
                            );
                            if ($sent) {
                                $sentCount++;
                                $vendorSentCount++;
                            }
                        }

                        if ($vendorSentCount > 0) {
                            // Successfully queued at least one email for this vendor
                            $failedVendors = array_values(array_diff($failedVendors, [$vendor->id]));
                        }
                    } catch (\Throwable $e) {
                        Log::error('Daily vendor report failed', [
                            'vendor_id' => $vendor->id,
                            'error' => $e->getMessage(),
                            'trace' => $e->getTraceAsString(),
                        ]);
                        $failedVendors[] = $vendor->id;
                        $failedVendors = array_values(array_unique($failedVendors));
                    }
                }
            });

            Cache::put('daily_vendor_report:last_run_at', $to);
            if (! empty($failedVendors)) {
                Cache::put('daily_vendor_report:failed_vendor_ids', $failedVendors);
                if (! $failedWindowFrom) {
                    Cache::put('daily_vendor_report:failed_window_from', $from);
                }
            } else {
                Cache::forget('daily_vendor_report:failed_vendor_ids');
                Cache::forget('daily_vendor_report:failed_window_from');
            }
            Log::info('Daily vendor report execution completed', [
                'from' => $from->toDateTimeString(),
                'to' => $to->toDateTimeString(),
                'sent_count' => $sentCount,
                'failed_vendor_count' => count($failedVendors),
            ]);

            $this->info('Daily vendor report sent: '.$sentCount);

            return self::SUCCESS;
        } finally {
            try {
                $lock->release();
            } catch (\Throwable) {
                // swallow
            }
        }
    }
}
