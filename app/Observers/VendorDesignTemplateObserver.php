<?php

namespace App\Observers;

use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Services\Template\TemplateDetailsService;

class VendorDesignTemplateObserver
{
    public function __construct(private TemplateDetailsService $templateService) {}

    /**
     * Handle the VendorDesignTemplate "saved" event.
     */
    public function saved(VendorDesignTemplate $template): void
    {
        // Clear cache when template is updated
        $this->templateService->clearCache($template);
    }

    /**
     * Handle the VendorDesignTemplate "deleted" event.
     */
    public function deleted(VendorDesignTemplate $template): void
    {
        // Clear cache when template is deleted
        $this->templateService->clearCache($template);
    }

    /**
     * Handle the VendorDesignTemplate "restored" event.
     */
    public function restored(VendorDesignTemplate $template): void
    {
        // Clear cache when template is restored
        $this->templateService->clearCache($template);
    }

    /**
     * Handle the VendorDesignTemplate "force deleted" event.
     */
    public function forceDeleted(VendorDesignTemplate $template): void
    {
        // Clear cache when template is force deleted
        $this->templateService->clearCache($template);
    }
}
