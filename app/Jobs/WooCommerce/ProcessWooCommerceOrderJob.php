<?php

declare(strict_types=1);

namespace App\Jobs\WooCommerce;

use App\Services\Channels\WooCommerce\OrderImportService;
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

class ProcessWooCommerceOrderJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public array $backoff = [30, 60, 120];

    /**
     * Create a new job instance.
     */
    public function __construct(
        public int $storeId,
        public string $topic,
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
        Log::info("Processing WooCommerce Order Webhook: {$this->topic}", [
            'store_id' => $this->storeId,
            'order_id' => $this->payload['id'] ?? 'unknown',
        ]);

        try {
            DB::transaction(function () use ($cartRoutingService, $cartToOrderService, $orderImportService) {
                $orderImportService->process(
                    $this->storeId,
                    $this->payload,
                    $cartRoutingService,
                    $cartToOrderService
                );
            });
        } catch (Throwable $e) {
            Log::error('Failed to process WooCommerce Order', [
                'store_id' => $this->storeId,
                'order_id' => $this->payload['id'] ?? 'unknown',
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }
    }
}
