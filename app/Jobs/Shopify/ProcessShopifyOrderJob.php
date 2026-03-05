<?php

declare(strict_types=1);

namespace App\Jobs\Shopify;

use App\Services\Channels\Shopify\OrderImportService;
use App\Services\Customer\Cart\CartRoutingService;
use App\Services\Sales\Order\CartToOrderService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class ProcessShopifyOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @var int[]
     */
    public $backoff = [30, 60, 120];

    /**
     * The number of seconds the job can run before timing out.
     *
     * @var int
     */
    public $timeout = 120;

    /**
     * Create a new job instance.
     */
    public function __construct(
        public string $shopDomain,
        public array $payload
    ) {}

    /**
     * Execute the job.
     */
    public function handle(
        CartRoutingService $cartRoutingService,
        CartToOrderService $cartToOrderService,
        OrderImportService $orderImportService
    ): void {
        Log::info('Processing Shopify Order', [
            'shop' => $this->shopDomain,
            'order_id' => $this->payload['id'] ?? 'unknown',
            'order_number' => $this->payload['order_number'] ?? 'unknown',
        ]);

        try {
            DB::transaction(function () use ($cartRoutingService, $cartToOrderService, $orderImportService) {
                $orderImportService->process($this->shopDomain, $this->payload, $cartRoutingService, $cartToOrderService);
            });
        } catch (Throwable $e) {
            Log::error('Failed to process Shopify Order', [
                'shop' => $this->shopDomain,
                'order_id' => $this->payload['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }

    // TODO: Legacy placeholder removed. The job now delegates entirely to
    // OrderImportService within a database transaction for atomicity.
}
