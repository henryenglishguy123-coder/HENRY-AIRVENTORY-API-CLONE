<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\WooCommerce\CheckWooCommerceConnectionJob;
use App\Models\Customer\Store\VendorConnectedStore;
use Illuminate\Console\Command;

class CheckWooCommerceConnections extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'woocommerce:check-connections';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check connectivity for all active WooCommerce stores';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $this->info('Starting WooCommerce connection checks...');

        $stores = VendorConnectedStore::where('channel', 'woocommerce')
            ->where('status', 'connected')
            ->get();

        $count = $stores->count();
        $this->info("Found {$count} connected WooCommerce stores.");

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        foreach ($stores as $store) {
            CheckWooCommerceConnectionJob::dispatch($store->id);
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info('Dispatched connection check jobs for all stores.');
    }
}
