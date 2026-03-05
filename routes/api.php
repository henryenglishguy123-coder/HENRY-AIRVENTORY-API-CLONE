<?php

use App\Http\Controllers\Admin\Catalog\Product\ProductDesignTemplateImageController;
use App\Http\Controllers\Admin\Catalog\ProductionTechnique\ProductionTechniqueController;
use App\Http\Controllers\Admin\Order\OrderShipmentController;
use App\Http\Controllers\Api\V1\Admin\AdminJWTAuthController;
use App\Http\Controllers\Api\V1\Admin\Communication\AdminOrderMessageController;
use App\Http\Controllers\Api\V1\Admin\Customer\CustomerController;
use App\Http\Controllers\Api\V1\Admin\Factory\FactoryAccountStatusController;
use App\Http\Controllers\Api\V1\Admin\Factory\FactoryController;
use App\Http\Controllers\Api\V1\Auth\AuthMeController;
use App\Http\Controllers\Api\V1\Callbacks\StoreCallbackController;
use App\Http\Controllers\Api\V1\Catalog\Category\CategoryController;
use App\Http\Controllers\Api\V1\Catalog\Category\CategoryDetailsController;
use App\Http\Controllers\Api\V1\Catalog\Designer\ProductDesignerController;
use App\Http\Controllers\Api\V1\Catalog\Designer\ProductDesignerImageController;
use App\Http\Controllers\Api\V1\Catalog\Industry\IndustryController;
use App\Http\Controllers\Api\V1\Catalog\Inventory\InventoryController;
use App\Http\Controllers\Api\V1\Catalog\Product\ProductCardController;
use App\Http\Controllers\Api\V1\Catalog\Product\ProductDetailsController;
use App\Http\Controllers\Api\V1\Catalog\Product\ProductFilterController;
use App\Http\Controllers\Api\V1\Config\PanelConfigController;
use App\Http\Controllers\Api\V1\Customer\Account\AccountController;
use App\Http\Controllers\Api\V1\Customer\Address\BillingAddressController;
use App\Http\Controllers\Api\V1\Customer\Address\ShippingAddressController;
use App\Http\Controllers\Api\V1\Customer\AuthController;
use App\Http\Controllers\Api\V1\Customer\Branding\DesignBrandingController;
use App\Http\Controllers\Api\V1\Customer\Cart\CartAddressController;
use App\Http\Controllers\Api\V1\Customer\Cart\CartDiscountController;
use App\Http\Controllers\Api\V1\Customer\Cart\CartItemController;
use App\Http\Controllers\Api\V1\Customer\Cart\CartViewController;
use App\Http\Controllers\Api\V1\Customer\Cart\ReorderController;
use App\Http\Controllers\Api\V1\Customer\CustomerOrderMessageController;
use App\Http\Controllers\Api\V1\Customer\DashboardController;
use App\Http\Controllers\Api\V1\Customer\Designer\SaveDesignController;
use App\Http\Controllers\Api\V1\Customer\ForgotPasswordController;
use App\Http\Controllers\Api\V1\Customer\Gallery\CustomerMediaGalleryController;
use App\Http\Controllers\Api\V1\Customer\GoogleAuthController;
use App\Http\Controllers\Api\V1\Customer\Payment\SavedPaymentMethodController;
use App\Http\Controllers\Api\V1\Customer\Payment\WalletPaymentController;
use App\Http\Controllers\Api\V1\Customer\ResetPasswordController;
use App\Http\Controllers\Api\V1\Customer\SearchController;
use App\Http\Controllers\Api\V1\Customer\SigninController;
use App\Http\Controllers\Api\V1\Customer\SignupController;
use App\Http\Controllers\Api\V1\Customer\Store\ConnectedStoreController;
use App\Http\Controllers\Api\V1\Customer\Store\StoreConnectionController;
use App\Http\Controllers\Api\V1\Customer\Store\StoreProductLinkController;
use App\Http\Controllers\Api\V1\Customer\Template\TemplateActionController;
use App\Http\Controllers\Api\V1\Customer\Template\TemplateController;
use App\Http\Controllers\Api\V1\Customer\Template\TemplateInfoController;
use App\Http\Controllers\Api\V1\Customer\Template\VendorDesignTemplateStoreController;
use App\Http\Controllers\Api\V1\Customer\Wallet\WalletController;
use App\Http\Controllers\Api\V1\Customer\Wallet\WalletTransactionController;
use App\Http\Controllers\Api\V1\Factory\AccountController as FactoryAccountController;
use App\Http\Controllers\Api\V1\Factory\AuthController as FactoryAuthController;
use App\Http\Controllers\Api\V1\Factory\BusinessInformationController;
use App\Http\Controllers\Api\V1\Factory\FactoryAddressController;
use App\Http\Controllers\Api\V1\Factory\FactoryOrderMessageController;
use App\Http\Controllers\Api\V1\Factory\FactorySalesRoutingApiController;
use App\Http\Controllers\Api\V1\Factory\FactoryShippingRateController;
use App\Http\Controllers\Api\V1\Factory\ForgotPasswordController as FactoryForgotPasswordController;
use App\Http\Controllers\Api\V1\Factory\GoogleAuthController as FactoryGoogleAuthController;
use App\Http\Controllers\Api\V1\Factory\LabelSettingController;
use App\Http\Controllers\Api\V1\Factory\LoginController as FactoryLoginController;
use App\Http\Controllers\Api\V1\Factory\RegistrationController;
use App\Http\Controllers\Api\V1\Factory\ResendOtpController;
use App\Http\Controllers\Api\V1\Factory\ResetPasswordController as FactoryResetPasswordController;
use App\Http\Controllers\Api\V1\Factory\SecondaryContactController;
use App\Http\Controllers\Api\V1\Factory\SetPasswordController as FactorySetPasswordController;
use App\Http\Controllers\Api\V1\Location\CountryController;
use App\Http\Controllers\Api\V1\Location\FactoryCountryController;
use App\Http\Controllers\Api\V1\Location\StateController;
use App\Http\Controllers\Api\V1\Payment\PaymentSettingController;
use App\Http\Controllers\Api\V1\Sales\Order\SalesOrderController;
use App\Http\Controllers\Api\V1\Sales\Order\SalesOrderDetailController;
use App\Http\Controllers\Api\V1\Sales\Order\SalesOrderPaymentController;
use App\Http\Controllers\Api\V1\Settings\Currency\CurrencyController;
use App\Http\Controllers\Api\V1\Shipping\ShippingPartnerController;
use App\Http\Controllers\Api\V1\Store\StoreChannelController;
use App\Http\Controllers\Api\V1\Webhook\StripeWebhookController;
use App\Http\Controllers\Webhooks\ShippingWebhookController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| All routes in this file automatically have the "api" middleware group.
| We'll namespace controllers under App\Http\Controllers\API.
|
*/

Route::prefix('v1')->group(function () {

    Route::prefix('config')->name('config.')->group(function () {
        Route::get('/panel', [PanelConfigController::class, 'index'])->name('panel.index');
        Route::get('/currencies', [CurrencyController::class, 'index'])->name('currencies');
    });
    Route::get('/payment-settings', [PaymentSettingController::class, 'index'])->name('payment-settings.index');
    Route::get('/payment-settings/{payment_method}', [PaymentSettingController::class, 'show'])->name('payment-settings.show');
    Route::prefix('store')->name('store.')->group(function () {
        Route::get('channels', [StoreChannelController::class, 'index'])->name('channels.index');
    });
    /*
    |--------------------------------------------------------------------------
    | Admin API Routes (JWT Authentication)
    |--------------------------------------------------------------------------
    */
    Route::prefix('admin')->name('admin.')->group(function () {
        Route::post('/mint-token', [AdminJWTAuthController::class, 'mintToken'])
            ->middleware(['web', 'auth:admin'])
            ->name('mint-token');

        // Allow GET /api/v1/admin/logout without JWT middleware but with web session
        Route::get('/logout', [AdminJWTAuthController::class, 'logout'])
            ->middleware(['web'])
            ->name('logout.get');

        // Protected routes (JWT authentication required)
        Route::middleware('auth:admin_api')->group(function () {
            Route::get('/me', [AdminJWTAuthController::class, 'me'])->name('me');
            Route::post('/logout', [AdminJWTAuthController::class, 'logout'])
                ->middleware(['web'])
                ->name('logout');
            Route::post('/refresh', [AdminJWTAuthController::class, 'refresh'])->name('refresh');
            Route::prefix('customers')->name('customers.')->group(function () {
                Route::get('/', [CustomerController::class, 'index'])->name('index');
                Route::post('/wallet/fund', [CustomerController::class, 'fund'])->name('wallet.fund');
            });
            Route::prefix('orders')->name('orders.')->group(function () {
                Route::get('/', [SalesOrderController::class, 'index'])->name('index');
                Route::get('/{order_number}', [SalesOrderDetailController::class, 'show'])->name('show');
                Route::post('/{order}/ship', [OrderShipmentController::class, 'ship'])->name('ship');
            });
            Route::prefix('shipments')->name('shipments.')->group(function () {
                Route::post('/{shipment}/cancel', [OrderShipmentController::class, 'cancel'])->name('cancel');
            });
            Route::get('/shipping/partners', [ShippingPartnerController::class, 'index'])->name('shipping.partners.index');
            Route::put('/shipping/partners/{partner}', [ShippingPartnerController::class, 'update'])->name('shipping.partners.update');
            Route::prefix('sales-routing')->name('sales-routing-api.')->group(function () {
                Route::get('/', [FactorySalesRoutingApiController::class, 'index'])->name('index');
                Route::post('/', [FactorySalesRoutingApiController::class, 'store'])->name('store');
                Route::put('/{factory}', [FactorySalesRoutingApiController::class, 'update'])->name('update');
                Route::delete('/{factory}', [FactorySalesRoutingApiController::class, 'destroy'])->name('destroy');
            });
            Route::apiResource('factories', FactoryController::class);

            // Admin messaging routes
            Route::prefix('communications')->name('communications.')->group(function () {
                Route::get('/', [AdminOrderMessageController::class, 'index'])->name('index');
                Route::post('/', [AdminOrderMessageController::class, 'store'])->name('store');
                Route::get('/stats', [AdminOrderMessageController::class, 'stats'])->name('stats');
                Route::get('/search', [AdminOrderMessageController::class, 'search'])->name('search');
                Route::get('/order/{order_number}', [AdminOrderMessageController::class, 'showByOrder'])->name('by-order');
            });

            Route::prefix('factories-status')->name('factories-status.')->group(function () {
                Route::get('/statuses', [FactoryAccountStatusController::class, 'getStatuses'])->name('statuses');
                Route::put('/{factory}/update', [FactoryAccountStatusController::class, 'updateStatus'])->name('update');
                Route::get('/{factory}/completeness', [FactoryAccountStatusController::class, 'getFactoryCompleteness'])->name('completeness');
            });
            Route::prefix('factories/{factory}/label-settings')->name('factories.label-settings.')->group(function () {
                Route::get('/packaging-label', [LabelSettingController::class, 'showPackagingLabel'])->name('packaging-label.show');
                Route::put('/packaging-label', [LabelSettingController::class, 'updatePackagingLabel'])->name('packaging-label.update');
                Route::get('/hang-tag', [LabelSettingController::class, 'showHangTag'])->name('hang-tag.show');
                Route::put('/hang-tag', [LabelSettingController::class, 'updateHangTag'])->name('hang-tag.update');
            });
            Route::prefix('shipping-rates')->name('shipping-rates.')->group(function () {
                Route::get('/', [FactoryShippingRateController::class, 'index'])->name('index');
                Route::get('/export', [FactoryShippingRateController::class, 'export'])->name('export');
                Route::post('import', [FactoryShippingRateController::class, 'import'])->name('import');
                Route::delete('/{id}', [FactoryShippingRateController::class, 'destroy'])->name('destroy');
                Route::post('/', [FactoryShippingRateController::class, 'store'])->name('store');
            });
            Route::prefix('production-techniques')->name('production-techniques.')->group(function () {
                Route::post('data', [ProductionTechniqueController::class, 'getProductionTechniqueData'])->name('data');
                Route::post('store', [ProductionTechniqueController::class, 'store'])->name('store');
                Route::post('update', [ProductionTechniqueController::class, 'update'])->name('update');
                Route::post('toggle-status/{production_technique}', [ProductionTechniqueController::class, 'toggleStatus'])->name('toggle-status');
                Route::delete('{production_technique}', [ProductionTechniqueController::class, 'destroy'])->name('delete');
                Route::post('restore/{production_technique}', [ProductionTechniqueController::class, 'restore'])->name('restore');
                Route::post('bulk-action', [ProductionTechniqueController::class, 'bulkAction'])->name('bulk-action');
            });
        });
    });

    Route::prefix('webhooks')->name('webhooks.')->group(function () {
        Route::post('stripe', [StripeWebhookController::class, 'handle'])->name('stripe');
        Route::post('shipping/{provider}', [ShippingWebhookController::class, 'handleTrackingUpdate'])
            ->middleware('throttle:webhook-shipping')
            ->name('shipping');
    });
    Route::prefix('callbacks')->name('callbacks.')->group(function () {
        Route::match(['get', 'post'], '{channel}/installed', [StoreCallbackController::class, 'installed'])
            ->name('installed');
    });

    // Auth/me endpoint - works with customer, factory, or admin authentication
    Route::prefix('auth')->name('auth.')->middleware('auth.any')->group(function () {
        Route::get('/me', [AuthMeController::class, 'me'])->name('me');
    });

    Route::prefix('customers')->name('customer.')->group(function () {
        Route::post('/signup', [SignupController::class, 'signup'])->name('signup');
        Route::post('/google/auth', [GoogleAuthController::class, 'authenticate'])->name('google.auth');
        Route::post('/signin', [SigninController::class, 'signin'])->name('signin');
        Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLink'])->name('password.email');
        Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.reset');
        Route::post('/verify-reset-token', [ForgotPasswordController::class, 'verifyToken'])->name('password.verify');
        Route::put('/email/verify', [AuthController::class, 'verifyEmail'])->name('verification.verify');
    });

    Route::prefix('factories')->name('factory.')->group(function () {
        Route::post('/register', [RegistrationController::class, 'register'])->name('register');
        Route::post('/google/auth', [FactoryGoogleAuthController::class, 'authenticate'])->name('google.auth');
        Route::post('/verify-email', [FactoryAuthController::class, 'verifyEmail'])->name('verification.verify');
        Route::post('/resend-otp', [ResendOtpController::class, 'resendOtp'])->name('resend-otp');
        Route::post('/login', [FactoryLoginController::class, 'login'])->name('login');
        Route::post('/forgot-password', [FactoryForgotPasswordController::class, 'sendResetLink'])->name('password.email');
        Route::post('/reset-password', [FactoryResetPasswordController::class, 'reset'])->name('password.reset');
    });

    Route::prefix('customers')->name('customer.')->middleware('auth.customer_or_admin')->group(function () {
        Route::post('/signout', [SigninController::class, 'signout'])->name('signout');

        // Unified search endpoint
        Route::get('/search', [SearchController::class, 'search'])
            ->middleware('throttle:customer-search')
            ->name('search');

        Route::get('/account', [AccountController::class, 'show'])->name('account.show');
        Route::patch('/account', [AccountController::class, 'update'])->name('account.update');
        Route::get('/address/shipping', [ShippingAddressController::class, 'show'])->name('address.shipping.show');
        Route::get('/address/billing', [BillingAddressController::class, 'show'])->name('address.billing.show');
        Route::post('/address/shipping', [ShippingAddressController::class, 'store'])->name('address.shipping.add');
        Route::post('/address/billing', [BillingAddressController::class, 'store'])->name('address.billing.add');
        Route::get('/design-branding', [DesignBrandingController::class, 'index'])->name('design-branding.index');
        Route::post('/design-branding/upload', [DesignBrandingController::class, 'store'])->name('design-branding.upload');
        Route::delete('/design-branding/{id}', [DesignBrandingController::class, 'destroy'])->name('design-branding.delete');
        Route::get('/media-gallery', [CustomerMediaGalleryController::class, 'index'])->name('media.gallery');
        Route::prefix('wallet')->name('wallet.')->group(function () {
            Route::get('/', [WalletController::class, 'index'])->name('index');
            Route::post('/auto-pay/toggle', [WalletController::class, 'toggleAutoPay'])->name('auto-pay.toggle');
            Route::get('/transactions', [WalletTransactionController::class, 'transactions'])->name('transactions');
            Route::post('/topup', [WalletPaymentController::class, 'topup'])->name('topup');
            Route::post('/topup/confirm', [WalletPaymentController::class, 'confirm']);
        });
        Route::prefix('templates')->name('templates.')->group(function () {
            Route::get('/', [TemplateController::class, 'index'])->name('index');
            Route::get('/{template}', [TemplateController::class, 'show'])->name('show');
            Route::post('/{template}/duplicate', [TemplateController::class, 'duplicate'])->name('duplicate');
            Route::delete('/{template}', [TemplateActionController::class, 'destroy'])->name('destroy');
            Route::get('{template}/details', [TemplateInfoController::class, 'show'])->name('details');
            Route::post('/store-images/upload', [VendorDesignTemplateStoreController::class, 'uploadImage'])->name('store-image.upload');
            Route::post('/{template}/update', [VendorDesignTemplateStoreController::class, 'update'])->name('store-override.update');
            Route::delete('/store-images/{id}', [VendorDesignTemplateStoreController::class, 'destroyImage'])->name('store-image.destroy');
            Route::post('/{template}/save-draft', [VendorDesignTemplateStoreController::class, 'saveDraft'])->name('store-override.save-draft');
        });
        Route::prefix('payment')->name('payment.')->group(function () {
            Route::post('/setup-intent', [SavedPaymentMethodController::class, 'createSetupIntent'])->name('setup-intent');
            Route::post('/saved-methods', [SavedPaymentMethodController::class, 'store'])->name('saved-methods.store');
            Route::get('/saved-methods', [SavedPaymentMethodController::class, 'index'])->name('saved-methods.index');
            Route::delete('/saved-methods/{id}', [SavedPaymentMethodController::class, 'destroy'])->name('saved-methods.destroy');
        });
        Route::prefix('stores')->name('stores.')->group(function () {
            Route::get('/', [ConnectedStoreController::class, 'index'])->name('index');
            Route::post('/{store_channel}/connect', [StoreConnectionController::class, 'connect'])->name('connect');
            Route::post('/{id}/check-connection', [ConnectedStoreController::class, 'checkConnection'])->name('check-connection');
            Route::delete('/{id}', [ConnectedStoreController::class, 'disconnect'])->name('disconnect');
            Route::get('/product-lookup', [ConnectedStoreController::class, 'productLookup'])->name('product-lookup');
            Route::post('/link-existing-product', [StoreProductLinkController::class, 'linkExistingProduct'])->name('link-existing-product');
        });
        Route::prefix('cart')->name('cart.')->group(function () {
            Route::get('/template/{template}', [CartItemController::class, 'getTemplateItem'])->name('item.get');
            Route::post('/items/template', [CartItemController::class, 'addTemplateItem'])->name('items.template.add');
            Route::get('/address', [CartAddressController::class, 'show'])->name('address.show');
            Route::post('/address', [CartAddressController::class, 'store'])->name('address.store');
            Route::get('/view', [CartViewController::class, 'view'])->name('view');
            Route::post('/discount', [CartDiscountController::class, 'apply'])->name('discount.apply');
            Route::delete('/discount', [CartDiscountController::class, 'remove'])->name('discount.remove');
        });
        Route::prefix('orders')->name('order.')->group(function () {
            Route::get('/', [SalesOrderController::class, 'index'])->name('index');
            Route::post('/', [SalesOrderController::class, 'store'])->name('store');
            Route::post('/pay', [SalesOrderPaymentController::class, 'pay'])->name('pay');
            Route::post('/{order_number}/reorder', [ReorderController::class, 'reorder'])->name('reorder');
            Route::get('/{order_number}', [SalesOrderDetailController::class, 'show'])->name('show');

            // Messaging routes for customer orders
            Route::prefix('{order_number}/messages')->name('messages.')->group(function () {
                Route::get('/', [CustomerOrderMessageController::class, 'index'])->name('index');
                Route::post('/', [CustomerOrderMessageController::class, 'store'])->name('store');
            });
        });

        // Customer order messaging history route
        Route::get('/orders/messages/history', [CustomerOrderMessageController::class, 'getOrderHistory'])->name('customer.orders.messages.history');
        Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');
    });

    Route::prefix('factories')->name('factory.')->middleware('auth:factory')->group(function () {
        Route::post('/logout', [FactoryLoginController::class, 'logout'])->name('logout');
        Route::post('/set-password', [FactorySetPasswordController::class, 'setPassword'])->name('password.set');
        Route::prefix('orders')->name('orders.')->group(function () {
            Route::get('/', [SalesOrderController::class, 'index'])->name('index');
            Route::get('/{order_number}', [SalesOrderDetailController::class, 'show'])->name('show');

            // Messaging routes for factory orders
            Route::prefix('{order_number}/messages')->name('messages.')->group(function () {
                Route::get('/', [FactoryOrderMessageController::class, 'index'])->name('index');
                Route::post('/', [FactoryOrderMessageController::class, 'store'])->name('store');
            });
        });

    });

    // Business Information - accessible by both factory and admin users
    Route::prefix('factories')->name('factory.')->middleware('auth.any')->group(function () {
        // Account management
        Route::put('/account', [FactoryAccountController::class, 'update'])->name('account.update');

        // Business Information
        Route::post('/business-information', [BusinessInformationController::class, 'store'])->name('business-information.store');
        Route::get('/business-information', [BusinessInformationController::class, 'show'])->name('business-information.show');
        Route::post('/shipping-partner', [BusinessInformationController::class, 'updateShippingPartner'])->name('shipping-partner.update');

        // Factory Addresses
        Route::post('/addresses', [FactoryAddressController::class, 'store'])->name('addresses.store');
        Route::get('/addresses', [FactoryAddressController::class, 'index'])->name('addresses.index');
        Route::put('/addresses/{id}', [FactoryAddressController::class, 'update'])->name('addresses.update');
        Route::delete('/addresses/{id}', [FactoryAddressController::class, 'destroy'])->name('addresses.destroy');

        // Inventory
        Route::get('/inventory', [InventoryController::class, 'index'])->name('inventory.index');
        Route::post('/inventory/update', [InventoryController::class, 'update'])->name('inventory.update');
        Route::get('/inventory/export', [InventoryController::class, 'export'])->name('inventory.export');
        Route::post('/inventory/import', [InventoryController::class, 'import'])->name('inventory.import');

        // Secondary Contact
        Route::post('/secondary-contact', [SecondaryContactController::class, 'store'])->name('secondary-contact.store');
        Route::get('/secondary-contact', [SecondaryContactController::class, 'show'])->name('secondary-contact.show');
    });

    // Label Settings - factory only (one per factory)
    Route::prefix('factories')->name('factory.')->middleware('auth:factory')->group(function () {
        Route::prefix('label-settings')->name('label-settings.')->group(function () {
            Route::get('/packaging-label', [LabelSettingController::class, 'showPackagingLabel'])->name('packaging-label.show');
            Route::put('/packaging-label', [LabelSettingController::class, 'updatePackagingLabel'])->name('packaging-label.update');
            Route::get('/hang-tag', [LabelSettingController::class, 'showHangTag'])->name('hang-tag.show');
            Route::put('/hang-tag', [LabelSettingController::class, 'updateHangTag'])->name('hang-tag.update');
        });
    });

    Route::prefix('catalog')->name('catalog.')->group(function () {
        Route::prefix('category')->name('category.')->group(function () {
            Route::get('/', [CategoryController::class, 'index'])->name('index');
            Route::get('/{slug}', [CategoryDetailsController::class, 'show'])->name('show');
        });
        Route::prefix('product')->name('products.')->group(function () {
            Route::get('/', [ProductCardController::class, 'index'])->name('index');
            Route::get('/filters', [ProductFilterController::class, 'index'])->name('filters');
            Route::get('/{slug}', [ProductDetailsController::class, 'show'])->name('show');
            Route::get('/{slug}/design-template/colors', [ProductDesignTemplateImageController::class, 'designTemplateColors'])
                ->name('design-template.colors');
        });
        Route::prefix('designer')->name('designer.')->group(function () {
            Route::post('/image/upload', [ProductDesignerImageController::class, 'uploadImage'])->name('image.upload');
            Route::get('/{productSlug}/{factory?}', [ProductDesignerController::class, 'index'])->name('index');
            Route::post('/save', [SaveDesignController::class, 'saveDesign']);
        });
        Route::prefix('industries')->name('industries.')->group(function () {
            Route::get('/', [IndustryController::class, 'index'])->name('index');
            Route::get('/{id}', [IndustryController::class, 'show'])->name('show');
        });
    });

    // General order messaging routes (accessible by customers and factories)
    Route::prefix('orders')->name('orders.')->middleware('auth.any')->group(function () {
        Route::prefix('{order_number}/messages')->name('messages.')->group(function () {
            Route::get('/', [\App\Http\Controllers\Api\V1\Sales\Order\OrderMessageController::class, 'index'])->name('index');
            Route::post('/', [\App\Http\Controllers\Api\V1\Sales\Order\OrderMessageController::class, 'store'])->name('store');
        });
    });

    Route::prefix('location')->name('location.')->group(function () {
        Route::get('/countries', [CountryController::class, 'index'])->name('countries.index');
        Route::get('/factory-countries', [FactoryCountryController::class, 'index'])->name('factory-countries.index');
        Route::get('/countries/{country}/states', [StateController::class, 'index'])->name('states.index');
    });
    Route::fallback(function () {
        return response()->json([
            'message' => 'Resource not found.',
        ], 404);
    });
});
