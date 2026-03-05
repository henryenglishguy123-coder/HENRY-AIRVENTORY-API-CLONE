<?php

namespace App\Providers;

use App\Models\Catalog\DesignTemplate\CatalogDesignTemplate;
use App\Models\Catalog\DesignTemplate\CatalogDesignTemplateLayer;
use App\Models\Catalog\Product\CatalogProduct;
use App\Models\Catalog\Product\CatalogProductAttribute;
use App\Models\Catalog\Product\CatalogProductCategory;
use App\Models\Catalog\Product\CatalogProductDesignTemplate;
use App\Models\Catalog\Product\CatalogProductInfo;
use App\Models\Catalog\Product\CatalogProductInventory;
use App\Models\Catalog\Product\CatalogProductLayerImage;
use App\Models\Catalog\Product\CatalogProductPrice;
use App\Models\Catalog\Product\CatalogProductPriceWithMargin;
use App\Models\Catalog\Product\CatalogProductPrintingPrice;
use App\Models\Customer\Designer\VendorDesignTemplate;
use App\Models\Customer\Designer\VendorDesignTemplateStore;
use App\Observers\CatalogProductPriceObserver;
use App\Observers\CatalogProductPriceWithMarginObserver;
use App\Observers\CatalogProductPrintingPriceObserver;
use App\Observers\ProductCartListingCacheObserver;
use App\Observers\ProductDesignerFlushObserver;
use App\Observers\VendorDesignTemplateObserver;
use App\Observers\VendorDesignTemplateStoreObserver;
use App\Services\Image\ImageService;
use App\Services\StoreConfigService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Intervention\Image\Drivers\Gd\Driver as GdDriver;
use Intervention\Image\ImageManager;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->singleton(StoreConfigService::class, function ($app) {
            return new StoreConfigService;
        });

        // Register ImageService as singleton for dependency injection
        $this->app->singleton(ImageService::class, function ($app) {
            $imageManager = new ImageManager(new GdDriver);

            return new ImageService($imageManager);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Polymorphic mapping for messaging
        Relation::morphMap([
            'customer' => \App\Models\Customer\Vendor::class,
            'factory' => \App\Models\Factory\Factory::class,
            'admin' => \App\Models\Admin\User::class,
        ]);

        // Designer/Template observers
        CatalogProduct::observe(ProductDesignerFlushObserver::class);
        CatalogProductInfo::observe(ProductDesignerFlushObserver::class);
        CatalogProductInventory::observe(ProductDesignerFlushObserver::class);
        CatalogDesignTemplate::observe(ProductDesignerFlushObserver::class);
        CatalogDesignTemplateLayer::observe(ProductDesignerFlushObserver::class);
        CatalogProductLayerImage::observe(ProductDesignerFlushObserver::class);
        CatalogProductPrintingPrice::observe(ProductDesignerFlushObserver::class);
        CatalogProductDesignTemplate::observe(ProductDesignerFlushObserver::class);
        CatalogProductAttribute::observe(ProductDesignerFlushObserver::class);
        CatalogProductCategory::observe(ProductDesignerFlushObserver::class);
        CatalogProductPriceWithMargin::observe(ProductDesignerFlushObserver::class);
        CatalogProductPrice::observe(ProductDesignerFlushObserver::class);

        // Vendor template observer for cache clearing
        VendorDesignTemplate::observe(VendorDesignTemplateObserver::class);
        VendorDesignTemplateStore::observe(VendorDesignTemplateStoreObserver::class);
        CatalogProductPrintingPrice::observe(CatalogProductPrintingPriceObserver::class);
        CatalogProductPrice::observe(CatalogProductPriceObserver::class);
        CatalogProductPriceWithMargin::observe(CatalogProductPriceWithMarginObserver::class);

        // Product cart and listing cache clearing - handles product create/update/delete flows
        // Clears cache when any product data changes: details, pricing, images, design templates
        CatalogProduct::observe(ProductCartListingCacheObserver::class);
        CatalogProductInfo::observe(ProductCartListingCacheObserver::class);
        CatalogProductPrice::observe(ProductCartListingCacheObserver::class);
        CatalogProductPriceWithMargin::observe(ProductCartListingCacheObserver::class);
        CatalogProductPrintingPrice::observe(ProductCartListingCacheObserver::class);
        CatalogProductLayerImage::observe(ProductCartListingCacheObserver::class);
        CatalogProductAttribute::observe(ProductCartListingCacheObserver::class);
        CatalogProductDesignTemplate::observe(ProductCartListingCacheObserver::class);
        Blade::directive('storeconfig', function ($expression) {
            return "<?php echo e(app(\\App\Services\\StoreConfigService::class)->get({$expression})); ?>";
        });

        // Rate Limiter for WooCommerce Webhooks
        RateLimiter::for('woo-webhooks', function (Request $request) {
            return Limit::perMinute(100)->by($request->ip());
        });

        RateLimiter::for('customer-search', function (Request $request) {
            $key = $request->user('customer')?->id
                ?? $request->user('admin_api')?->id
                ?? $request->ip();

            return Limit::perMinute(60)->by($key);
        });
    }
}
