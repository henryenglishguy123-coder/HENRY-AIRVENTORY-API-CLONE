<?php

use App\Http\Controllers\Admin\Auth\LoginController;
use App\Http\Controllers\Admin\Auth\NewPasswordController;
use App\Http\Controllers\Admin\Auth\PasswordResetLinkController;
use App\Http\Controllers\Admin\Catalog\Attributes\AddAttribute;
use App\Http\Controllers\Admin\Catalog\Attributes\AttributeActions;
use App\Http\Controllers\Admin\Catalog\Attributes\AttributeController;
use App\Http\Controllers\Admin\Catalog\Attributes\UpdateAttribute;
use App\Http\Controllers\Admin\Catalog\Category\AddCategory;
use App\Http\Controllers\Admin\Catalog\Category\CategoryActions;
use App\Http\Controllers\Admin\Catalog\Category\CategoryController;
use App\Http\Controllers\Admin\Catalog\Category\UpdateCategory;
use App\Http\Controllers\Admin\Catalog\DesignTemplate\CreateDesignTemplateController;
use App\Http\Controllers\Admin\Catalog\DesignTemplate\DesignTemplateController;
use App\Http\Controllers\Admin\Catalog\DesignTemplate\EditDesignTemplateController;
use App\Http\Controllers\Admin\Catalog\Industry\IndustryController;
use App\Http\Controllers\Admin\Catalog\Product\AddProductController;
use App\Http\Controllers\Admin\Catalog\Product\EditProductController;
use App\Http\Controllers\Admin\Catalog\Product\ProductBulkActionsController;
use App\Http\Controllers\Admin\Catalog\Product\ProductController;
use App\Http\Controllers\Admin\Catalog\Product\ProductDesignTemplateController;
use App\Http\Controllers\Admin\Catalog\Product\ProductDesignTemplateImageController;
use App\Http\Controllers\Admin\Catalog\Product\ProductMediaController;
use App\Http\Controllers\Admin\Catalog\Product\ProductToFactoryController;
use App\Http\Controllers\Admin\Catalog\ProductionTechnique\ProductionTechniqueController;
use App\Http\Controllers\Admin\Customer\CustomerBulkActionsController;
use App\Http\Controllers\Admin\Customer\CustomerController;
use App\Http\Controllers\Admin\Dashboard\DashboardController;
use App\Http\Controllers\Admin\Factory\FactoryController;
use App\Http\Controllers\Admin\Factory\FactorySalesRoutingController;
use App\Http\Controllers\Admin\Marketing\DiscountCoupon\DiscountCouponController;
use App\Http\Controllers\Admin\Marketing\DiscountCoupon\DiscountCouponCreateController;
use App\Http\Controllers\Admin\Marketing\DiscountCoupon\DiscountCouponEditController;
use App\Http\Controllers\Admin\Settings\Currency\CurrencyRateController;
use App\Http\Controllers\Admin\Settings\Currency\CurrencySettingController;
use App\Http\Controllers\Admin\Settings\General\Web\WebSettingController;
use App\Http\Controllers\Admin\Settings\Shipping\ShippingPartnerController as AdminShippingPartnerController;
use App\Http\Controllers\Admin\Settings\Shipping\ShippingRateController;
use App\Http\Controllers\Admin\Settings\Tax\TaxController;
use App\Http\Controllers\Admin\Settings\Tax\TaxRuleController;
use App\Http\Controllers\Admin\Settings\Tax\TaxZoneController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Redirect root
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => redirect()->route('admin.login'));

/*
|--------------------------------------------------------------------------
| Admin Guest Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware('guest:admin')->name('admin.')->group(function () {
    Route::get('/', fn () => redirect()->route('admin.login'));

    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->name('login.store');

    Route::get('forgot-password', [PasswordResetLinkController::class, 'create'])->name('password.request');
    Route::post('forgot-password', [PasswordResetLinkController::class, 'store'])->name('password.email');

    Route::get('reset-password/{token}', [NewPasswordController::class, 'create'])->name('password.reset');
    Route::post('reset-password', [NewPasswordController::class, 'store'])->name('password.store');
});

/*
|--------------------------------------------------------------------------
| Admin Authenticated Routes
|--------------------------------------------------------------------------
*/
Route::prefix('admin')->middleware('auth:admin')->name('admin.')->group(function () {

    Route::get('logout', [LoginController::class, 'destroy'])->name('logout');
    Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

    /*
    |--------------------------------------------------------------------------
    | Customers
    |--------------------------------------------------------------------------
    */
    Route::prefix('customer')->name('customer.')->group(function () {
        Route::get('/', [CustomerController::class, 'index'])->name('index');
        Route::post('bulk-action', [CustomerBulkActionsController::class, 'bulkAction'])->name('bulk-action');
        Route::get('{customer}', [CustomerController::class, 'show'])->name('show');
        Route::get('{customer}/wallet', [CustomerController::class, 'wallet'])->name('wallet');
        Route::get('{customer}/stores', [CustomerController::class, 'stores'])->name('stores');
        Route::get('{customer}/templates', [CustomerController::class, 'templates'])->name('templates');
    });
    Route::get('wallet', [CustomerController::class, 'wallets'])->name('wallets');

    /*
    |--------------------------------------------------------------------------
    | Settings
    |--------------------------------------------------------------------------
    */
    Route::prefix('settings')->name('settings.')->group(function () {
        Route::get('general/web', [WebSettingController::class, 'index'])->name('general.web');
        Route::post('general/web', [WebSettingController::class, 'update'])->name('general.web.update');

        Route::get('currency', [CurrencySettingController::class, 'index'])->name('currency');
        Route::put('currency', [CurrencySettingController::class, 'update'])->name('currency.update');

        Route::get('currency/rates', [CurrencyRateController::class, 'index'])->name('currency.rates');
        Route::post('currency/update-from-api', [CurrencyRateController::class, 'update'])->name('currency.rates.update');
        Route::post('currency/rates/save', [CurrencyRateController::class, 'saveManual'])->name('currency.rates.save');

        Route::get('/shipping-rates', [ShippingRateController::class, 'shippingRates'])->name('shipping-rates');
        Route::get('/shipping-partners', [AdminShippingPartnerController::class, 'index'])->name('shipping-partners');

        /*
        | Tax
        */
        Route::prefix('tax')->name('tax.')->group(function () {
            // Main Tax Page (View)
            Route::get('/', [TaxController::class, 'index'])->name('index');

            // Tax CRUD (API)
            Route::get('data', [TaxController::class, 'data'])->name('data');
            Route::post('store', [TaxController::class, 'store'])->name('store');
            Route::post('bulk-action', [TaxController::class, 'bulkAction'])->name('bulk-action');
            Route::post('{tax}/update', [TaxController::class, 'update'])->name('update');
            Route::delete('{tax}', [TaxController::class, 'destroy'])->name('delete');

            // Tax Zones CRUD (API)
            Route::prefix('zones')->name('zones.')->group(function () {
                Route::get('/', [TaxZoneController::class, 'index'])->name('index');
                Route::post('store', [TaxZoneController::class, 'store'])->name('store');
                Route::post('bulk-action', [TaxZoneController::class, 'bulkAction'])->name('bulk-action');
                Route::post('{taxZone}/update', [TaxZoneController::class, 'update'])->name('update');
                Route::delete('{taxZone}', [TaxZoneController::class, 'destroy'])->name('delete');
            });

            // Tax Rules CRUD (API)
            Route::prefix('rules')->name('rules.')->group(function () {
                Route::get('/', [TaxRuleController::class, 'index'])->name('index');
                Route::post('store', [TaxRuleController::class, 'store'])->name('store');
                Route::post('bulk-action', [TaxRuleController::class, 'bulkAction'])->name('bulk-action');
                Route::post('{taxRule}/update', [TaxRuleController::class, 'update'])->name('update');
                Route::delete('{taxRule}', [TaxRuleController::class, 'destroy'])->name('delete');
            });
        });
    });

    /*
    |--------------------------------------------------------------------------
    | Catalog
    |--------------------------------------------------------------------------
    */
    Route::prefix('catalog')->name('catalog.')->group(function () {

        /*
        | Products
        */
        Route::prefix('product')->name('product.')->group(function () {
            Route::get('/', [ProductController::class, 'index'])->name('index');
            Route::get('add', [AddProductController::class, 'addProduct'])->name('add');
            Route::post('store', [AddProductController::class, 'store'])->name('store');

            Route::get('{product}/edit', [EditProductController::class, 'edit'])->name('edit');
            Route::post('{product}/update', [EditProductController::class, 'update'])->name('update');

            Route::post('upload-media', [ProductMediaController::class, 'uploadFiles'])->name('upload.media');
            Route::delete('delete-media', [ProductMediaController::class, 'removeFile'])->name('delete.media');

            // Variant Management
            Route::post('variant/image', [EditProductController::class, 'updateVariantImage'])->name('variant.image');
            Route::delete('variant/{id}', [EditProductController::class, 'deleteVariant'])->name('variant.delete');

            Route::get('factories', [ProductToFactoryController::class, 'index'])->name('factories');
            Route::get('{productId}/product-info', [ProductToFactoryController::class, 'info'])->name('info');
            Route::post('assign-factories', [ProductToFactoryController::class, 'assignFactories'])->name('factory.assign');

            Route::post('bulk-action', [ProductBulkActionsController::class, 'bulkAction'])->name('bulk-action');
            Route::get('{product}/design-template', [ProductDesignTemplateController::class, 'index'])->name('design-template');
            Route::post('{product}/design-template', [ProductDesignTemplateController::class, 'storeConfiguration'])->name('design-template.store');
            Route::get('{product}/design-template/assign-image', [ProductDesignTemplateImageController::class, 'assignImage'])->name('design-template.assign-image');
            Route::post('{product}/upload-layer-image', [ProductDesignTemplateImageController::class, 'uploadLayerImage'])->name('upload-layer-image');
            Route::get('design-template/{template}/layers', [ProductDesignTemplateController::class, 'layers'])->name('design-template.layers');
        });

        /*
        | Industries
        */
        Route::prefix('industries')->name('industries.')->group(function () {
            Route::get('/', [IndustryController::class, 'index'])->name('index');
            Route::post('store', [IndustryController::class, 'store'])->name('store');
            Route::post('bulk-action', [IndustryController::class, 'bulkAction'])->name('bulkAction');
            Route::post('checkCategoryCount', [IndustryController::class, 'checkCategoryCount'])->name('checkCategoryCount');
        });

        /*
        | Design Templates
        */
        Route::prefix('design-template')->name('design-template.')->group(function () {
            Route::get('/', [DesignTemplateController::class, 'designTemplate'])->name('index');
            Route::post('data', [DesignTemplateController::class, 'getDesignTemplateData'])->name('data');

            Route::get('create', [CreateDesignTemplateController::class, 'create'])->name('create');
            Route::post('store', [CreateDesignTemplateController::class, 'store'])->name('store');

            Route::get('{designTemplate}/edit', [EditDesignTemplateController::class, 'edit'])->name('edit');
            Route::post('{designTemplate}/update', [EditDesignTemplateController::class, 'update'])->name('update');
            Route::delete('/design-template/layer/{layer}', [EditDesignTemplateController::class, 'destroy'])->name('layer.delete');

            Route::post('upload-mockup', [DesignTemplateController::class, 'uploadMockup'])->name('upload-mockup');
            Route::post('bulk-action', [DesignTemplateController::class, 'bulkAction'])->name('bulk-action');
        });

        /*
        | Categories
        */
        Route::prefix('categories')->name('categories.')->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('index');
            Route::get('{id}/categories', [CategoryActions::class, 'getCategories'])->name('industries.categories');
            Route::post('/data', [CategoryController::class, 'getCategoryData'])->name('data');
            Route::post('/bulk-action', [CategoryActions::class, 'bulkAction'])->name('bulk-action');
            Route::get('/add-category', [AddCategory::class, 'create'])->name('create');
            Route::post('/save-category', [AddCategory::class, 'store'])->name('store');
            Route::get('/update-category/{id}', [UpdateCategory::class, 'edit'])->name('edit');
            Route::post('/update-category-data', [UpdateCategory::class, 'update'])->name('update');
        });

        /*
        | Attributes
        */
        Route::prefix('attributes')->name('attributes.')->group(function () {
            Route::get('/', [AttributeController::class, 'index'])->name('index');
            Route::post('data', [AttributeController::class, 'getAttributeData'])->name('data');
            Route::post('/bulk-action', [AttributeActions::class, 'bulkAction'])->name('bulk-action');
            Route::post('/delete-attribute-option-value', [AttributeActions::class, 'deleteOptionValue'])->name('option.value.delete');
            Route::get('/add-attribute', [AddAttribute::class, 'create'])->name('create');
            Route::post('/save-attribute', [AddAttribute::class, 'store'])->name('store');
            Route::get('/update-attribute/{id}', [UpdateAttribute::class, 'edit'])->name('edit');
            Route::post('/update-attribute-data', [UpdateAttribute::class, 'update'])->name('update');
        });

        /*
        | Production Techniques
        */
        Route::prefix('production-techniques')->name('production-techniques.')->group(function () {
            Route::get('/', [ProductionTechniqueController::class, 'index'])->name('index');
            Route::get('create', [ProductionTechniqueController::class, 'create'])->name('create');
            Route::get('{production_technique}/edit', [ProductionTechniqueController::class, 'edit'])->name('edit');
        });

    });

    /*
    | Factories
    */
    Route::prefix('factories')->name('factories.')->group(function () {
        Route::get('/', [FactoryController::class, 'index'])->name('index-web');
        Route::get('/{id}/business-information', [FactoryController::class, 'businessInformation'])->name('business-information');
        Route::get('/{id}/branding', [FactoryController::class, 'branding'])->name('branding');
    });

    /*
    | Sales Routing
    */
    Route::get('/sales-routing', [FactorySalesRoutingController::class, 'index'])->name('sales-routing.index');
    Route::get('/sales-routing/export/{type}', [FactorySalesRoutingController::class, 'export'])->whereIn('type', ['csv', 'xlsx'])->name('sales-routing.export');
    Route::post('/sales-routing/import', [FactorySalesRoutingController::class, 'import'])->name('sales-routing.import');

    /*
    | Sales Orders (UI)
    */
    Route::group(['prefix' => 'sales/orders', 'as' => 'sales.orders.'], function () {
        Route::get('/', [\App\Http\Controllers\Admin\Sales\Order\OrderController::class, 'index'])->name('index');
        Route::get('/{id}', [\App\Http\Controllers\Admin\Sales\Order\OrderController::class, 'show'])->name('show');
    });

    /*
    | Communications
    */
    Route::group(['prefix' => 'communications', 'as' => 'communications.'], function () {
        Route::get('/', [\App\Http\Controllers\Admin\Communication\AdminCommunicationController::class, 'index'])->name('index');
    });

    Route::group(['prefix' => 'marketing', 'as' => 'marketing.'], function () {
        Route::get('/discount-coupons', [DiscountCouponController::class, 'index'])->name('discount-coupons.index');
        Route::get('/discount-coupons/data', [DiscountCouponController::class, 'data'])->name('discount-coupons.data');
        Route::get('/discount-coupons/create', [DiscountCouponCreateController::class, 'create'])->name('discount-coupons.create');
        Route::post('/discount-coupons/store', [DiscountCouponCreateController::class, 'store'])->name('discount-coupons.store');
        Route::get('/discount-coupons/generate-code', [DiscountCouponController::class, 'generateCode'])->name('discount-coupons.generate-code');
        Route::post('/discount-coupons/check-code', [DiscountCouponController::class, 'checkCode'])->name('discount-coupons.check-code');
        Route::get('/discount-coupons/api/search', [DiscountCouponController::class, 'search'])->name('discount-coupons.api.search');
        Route::get('/discount-coupons/{id}/edit', [DiscountCouponEditController::class, 'edit'])->name('discount-coupons.edit');
        Route::put('/discount-coupons/{id}', [DiscountCouponEditController::class, 'update'])->name('discount-coupons.update');
        Route::post('/discount-coupons/bulk-action', [DiscountCouponController::class, 'bulkAction'])->name('discount-coupons.bulk-action');
    });
});
