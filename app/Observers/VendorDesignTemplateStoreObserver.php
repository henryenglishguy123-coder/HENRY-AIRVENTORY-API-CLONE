<?php

namespace App\Observers;

use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Services\Template\TemplateDetailsService;

class VendorDesignTemplateStoreObserver
{
    public function __construct(private TemplateDetailsService $templateService) {}

    /**
     * Clear template cache when store overrides change.
     */
    public function saved(VendorDesignTemplateStore $store): void
    {
        $this->clearTemplateCache($store);
    }

    public function deleted(VendorDesignTemplateStore $store): void
    {
        $this->clearTemplateCache($store);
    }

    /**
     * Clear pricing cache for the template when store details change.
     */
    private function clearTemplateCache(VendorDesignTemplateStore $store): void
    {
        if ($store->vendor_design_template_id) {
            // Create a dummy template object with just the ID to clear cache
            $template = new \App\Models\Customer\Designer\VendorDesignTemplate;
            $template->id = $store->vendor_design_template_id;
            $this->templateService->clearCache($template);
        }
    }
}
