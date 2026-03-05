<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Customer\Designer\VendorDesignTemplateStore;
use Illuminate\Bus\Batch;

class SyncBatchCompletion
{
    /**
     * Create a new callback instance.
     */
    public function __construct(
        protected int $storeOverrideId,
        protected string $finalizeJobClass
    ) {}

    /**
     * Handle the batch completion.
     *
     * @return void
     */
    public function __invoke(Batch $batch)
    {
        $store = VendorDesignTemplateStore::find($this->storeOverrideId);

        if ($store && class_exists($this->finalizeJobClass)) {
            $this->finalizeJobClass::dispatch($store, $batch->hasFailures());
        }
    }
}
