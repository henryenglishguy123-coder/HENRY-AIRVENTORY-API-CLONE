# Route Index

Generated from `php artisan route:list --except-vendor --json`.
Generated at (UTC): **2026-02-25 05:01:04**

## Summary

- Total routes: `251`
- API routes: `146`
- Web routes: `105`
- Admin web routes: `104`
- Other web/system routes: `1`

## API Routes (`/api/...`)

### `admin` (26 routes)

| Method | URI | Name | Action | Middleware |
| --- | --- | --- | --- | --- |
| `GET\|HEAD` | `api/v1/admin/customers` | `admin.customers.index` | `App\Http\Controllers\Api\V1\Admin\Customer\CustomerController@index` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `POST` | `api/v1/admin/customers/wallet/fund` | `admin.customers.wallet.fund` | `App\Http\Controllers\Api\V1\Admin\Customer\CustomerController@fund` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `GET\|HEAD` | `api/v1/admin/factories` | `admin.factories.index` | `App\Http\Controllers\Api\V1\Admin\Factory\FactoryController@index` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `POST` | `api/v1/admin/factories` | `admin.factories.store` | `App\Http\Controllers\Api\V1\Admin\Factory\FactoryController@store` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `GET\|HEAD` | `api/v1/admin/factories-status/statuses` | `admin.factories-status.statuses` | `App\Http\Controllers\Api\V1\Admin\Factory\FactoryAccountStatusController@getStatuses` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `GET\|HEAD` | `api/v1/admin/factories-status/{factory}/completeness` | `admin.factories-status.completeness` | `App\Http\Controllers\Api\V1\Admin\Factory\FactoryAccountStatusController@getFactoryCompleteness` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `PUT` | `api/v1/admin/factories-status/{factory}/update` | `admin.factories-status.update` | `App\Http\Controllers\Api\V1\Admin\Factory\FactoryAccountStatusController@updateStatus` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `DELETE` | `api/v1/admin/factories/{factory}` | `admin.factories.destroy` | `App\Http\Controllers\Api\V1\Admin\Factory\FactoryController@destroy` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `GET\|HEAD` | `api/v1/admin/factories/{factory}` | `admin.factories.show` | `App\Http\Controllers\Api\V1\Admin\Factory\FactoryController@show` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `PUT\|PATCH` | `api/v1/admin/factories/{factory}` | `admin.factories.update` | `App\Http\Controllers\Api\V1\Admin\Factory\FactoryController@update` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `GET\|HEAD` | `api/v1/admin/logout` | `admin.logout.get` | `App\Http\Controllers\Api\V1\Admin\AdminJWTAuthController@logout` | `api, web` |
| `POST` | `api/v1/admin/logout` | `admin.logout` | `App\Http\Controllers\Api\V1\Admin\AdminJWTAuthController@logout` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api, web` |
| `GET\|HEAD` | `api/v1/admin/me` | `admin.me` | `App\Http\Controllers\Api\V1\Admin\AdminJWTAuthController@me` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `POST` | `api/v1/admin/mint-token` | `admin.mint-token` | `App\Http\Controllers\Api\V1\Admin\AdminJWTAuthController@mintToken` | `api, web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `api/v1/admin/orders` | `admin.orders.index` | `App\Http\Controllers\Api\V1\Sales\Order\SalesOrderController@index` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `GET\|HEAD` | `api/v1/admin/orders/{order}` | `admin.orders.show` | `App\Http\Controllers\Api\V1\Sales\Order\SalesOrderDetailController@show` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `POST` | `api/v1/admin/refresh` | `admin.refresh` | `App\Http\Controllers\Api\V1\Admin\AdminJWTAuthController@refresh` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `GET\|HEAD` | `api/v1/admin/sales-routing` | `admin.sales-routing-api.index` | `App\Http\Controllers\Api\V1\Factory\FactorySalesRoutingApiController@index` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `POST` | `api/v1/admin/sales-routing` | `admin.sales-routing-api.store` | `App\Http\Controllers\Api\V1\Factory\FactorySalesRoutingApiController@store` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `DELETE` | `api/v1/admin/sales-routing/{factory}` | `admin.sales-routing-api.destroy` | `App\Http\Controllers\Api\V1\Factory\FactorySalesRoutingApiController@destroy` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `PUT` | `api/v1/admin/sales-routing/{factory}` | `admin.sales-routing-api.update` | `App\Http\Controllers\Api\V1\Factory\FactorySalesRoutingApiController@update` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `GET\|HEAD` | `api/v1/admin/shipping-rates` | `admin.shipping-rates.index` | `App\Http\Controllers\Api\V1\Factory\FactoryShippingRateController@index` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `POST` | `api/v1/admin/shipping-rates` | `admin.shipping-rates.store` | `App\Http\Controllers\Api\V1\Factory\FactoryShippingRateController@store` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `GET\|HEAD` | `api/v1/admin/shipping-rates/export` | `admin.shipping-rates.export` | `App\Http\Controllers\Api\V1\Factory\FactoryShippingRateController@export` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `POST` | `api/v1/admin/shipping-rates/import` | `admin.shipping-rates.import` | `App\Http\Controllers\Api\V1\Factory\FactoryShippingRateController@import` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |
| `DELETE` | `api/v1/admin/shipping-rates/{id}` | `admin.shipping-rates.destroy` | `App\Http\Controllers\Api\V1\Factory\FactoryShippingRateController@destroy` | `api, Illuminate\Auth\Middleware\Authenticate:admin_api` |

### `auth` (1 routes)

| Method | URI | Name | Action | Middleware |
| --- | --- | --- | --- | --- |
| `GET\|HEAD` | `api/v1/auth/me` | `auth.me` | `App\Http\Controllers\Api\V1\Auth\AuthMeController@me` | `api, App\Http\Middleware\AuthAnyUser` |

### `callbacks` (1 routes)

| Method | URI | Name | Action | Middleware |
| --- | --- | --- | --- | --- |
| `GET\|POST\|HEAD` | `api/v1/callbacks/{channel}/installed` | `callbacks.installed` | `App\Http\Controllers\Api\V1\Callbacks\StoreCallbackController@installed` | `api` |

### `catalog` (11 routes)

| Method | URI | Name | Action | Middleware |
| --- | --- | --- | --- | --- |
| `GET\|HEAD` | `api/v1/catalog/category` | `catalog.category.index` | `App\Http\Controllers\Api\V1\Catalog\Category\CategoryController@index` | `api` |
| `GET\|HEAD` | `api/v1/catalog/category/{slug}` | `catalog.category.show` | `App\Http\Controllers\Api\V1\Catalog\Category\CategoryDetailsController@show` | `api` |
| `POST` | `api/v1/catalog/designer/image/upload` | `catalog.designer.image.upload` | `App\Http\Controllers\Api\V1\Catalog\Designer\ProductDesignerImageController@uploadImage` | `api` |
| `POST` | `api/v1/catalog/designer/save` | `catalog.designer.` | `App\Http\Controllers\Api\V1\Customer\Designer\SaveDesignController@saveDesign` | `api` |
| `GET\|HEAD` | `api/v1/catalog/designer/{productSlug}/{factory?}` | `catalog.designer.index` | `App\Http\Controllers\Api\V1\Catalog\Designer\ProductDesignerController@index` | `api` |
| `GET\|HEAD` | `api/v1/catalog/industries` | `catalog.industries.index` | `App\Http\Controllers\Api\V1\Catalog\Industry\IndustryController@index` | `api` |
| `GET\|HEAD` | `api/v1/catalog/industries/{id}` | `catalog.industries.show` | `App\Http\Controllers\Api\V1\Catalog\Industry\IndustryController@show` | `api` |
| `GET\|HEAD` | `api/v1/catalog/product` | `catalog.products.index` | `App\Http\Controllers\Api\V1\Catalog\Product\ProductCardController@index` | `api` |
| `GET\|HEAD` | `api/v1/catalog/product/filters` | `catalog.products.filters` | `App\Http\Controllers\Api\V1\Catalog\Product\ProductFilterController@index` | `api` |
| `GET\|HEAD` | `api/v1/catalog/product/{slug}` | `catalog.products.show` | `App\Http\Controllers\Api\V1\Catalog\Product\ProductDetailsController@show` | `api` |
| `GET\|HEAD` | `api/v1/catalog/product/{slug}/design-template/colors` | `catalog.products.design-template.colors` | `App\Http\Controllers\Admin\Catalog\Product\ProductDesignTemplateImageController@designTemplateColors` | `api` |

### `config` (2 routes)

| Method | URI | Name | Action | Middleware |
| --- | --- | --- | --- | --- |
| `GET\|HEAD` | `api/v1/config/currencies` | `config.currencies` | `App\Http\Controllers\Api\V1\Settings\Currency\CurrencyController@index` | `api` |
| `GET\|HEAD` | `api/v1/config/panel` | `config.panel.index` | `App\Http\Controllers\Api\V1\Config\PanelConfigController@index` | `api` |

### `customers` (56 routes)

| Method | URI | Name | Action | Middleware |
| --- | --- | --- | --- | --- |
| `GET\|HEAD` | `api/v1/customers/account` | `customer.account.show` | `App\Http\Controllers\Api\V1\Customer\Account\AccountController@show` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `PATCH` | `api/v1/customers/account` | `customer.account.update` | `App\Http\Controllers\Api\V1\Customer\Account\AccountController@update` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `GET\|HEAD` | `api/v1/customers/address/billing` | `customer.address.billing.show` | `App\Http\Controllers\Api\V1\Customer\Address\BillingAddressController@show` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/address/billing` | `customer.address.billing.add` | `App\Http\Controllers\Api\V1\Customer\Address\BillingAddressController@store` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `GET\|HEAD` | `api/v1/customers/address/shipping` | `customer.address.shipping.show` | `App\Http\Controllers\Api\V1\Customer\Address\ShippingAddressController@show` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/address/shipping` | `customer.address.shipping.add` | `App\Http\Controllers\Api\V1\Customer\Address\ShippingAddressController@store` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `GET\|HEAD` | `api/v1/customers/cart/address` | `customer.cart.address.show` | `App\Http\Controllers\Api\V1\Customer\Cart\CartAddressController@show` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/cart/address` | `customer.cart.address.store` | `App\Http\Controllers\Api\V1\Customer\Cart\CartAddressController@store` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `DELETE` | `api/v1/customers/cart/discount` | `customer.cart.discount.remove` | `App\Http\Controllers\Api\V1\Customer\Cart\CartDiscountController@remove` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/cart/discount` | `customer.cart.discount.apply` | `App\Http\Controllers\Api\V1\Customer\Cart\CartDiscountController@apply` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/cart/items/template` | `customer.cart.items.template.add` | `App\Http\Controllers\Api\V1\Customer\Cart\CartItemController@addTemplateItem` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `GET\|HEAD` | `api/v1/customers/cart/template/{template}` | `customer.cart.item.get` | `App\Http\Controllers\Api\V1\Customer\Cart\CartItemController@getTemplateItem` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `GET\|HEAD` | `api/v1/customers/cart/view` | `customer.cart.view` | `App\Http\Controllers\Api\V1\Customer\Cart\CartViewController@view` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `GET\|HEAD` | `api/v1/customers/dashboard` | `customer.dashboard` | `App\Http\Controllers\Api\V1\Customer\DashboardController@index` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `GET\|HEAD` | `api/v1/customers/design-branding` | `customer.design-branding.index` | `App\Http\Controllers\Api\V1\Customer\Branding\DesignBrandingController@index` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/design-branding/upload` | `customer.design-branding.upload` | `App\Http\Controllers\Api\V1\Customer\Branding\DesignBrandingController@store` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `DELETE` | `api/v1/customers/design-branding/{id}` | `customer.design-branding.delete` | `App\Http\Controllers\Api\V1\Customer\Branding\DesignBrandingController@destroy` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `PUT` | `api/v1/customers/email/verify` | `customer.verification.verify` | `App\Http\Controllers\Api\V1\Customer\AuthController@verifyEmail` | `api` |
| `POST` | `api/v1/customers/forgot-password` | `customer.password.email` | `App\Http\Controllers\Api\V1\Customer\ForgotPasswordController@sendResetLink` | `api` |
| `POST` | `api/v1/customers/google/auth` | `customer.google.auth` | `App\Http\Controllers\Api\V1\Customer\GoogleAuthController@authenticate` | `api` |
| `GET\|HEAD` | `api/v1/customers/media-gallery` | `customer.media.gallery` | `App\Http\Controllers\Api\V1\Customer\Gallery\CustomerMediaGalleryController@index` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `GET\|HEAD` | `api/v1/customers/orders` | `customer.order.index` | `App\Http\Controllers\Api\V1\Sales\Order\SalesOrderController@index` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/orders` | `customer.order.store` | `App\Http\Controllers\Api\V1\Sales\Order\SalesOrderController@store` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/orders/pay` | `customer.order.pay` | `App\Http\Controllers\Api\V1\Sales\Order\SalesOrderPaymentController@pay` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `GET\|HEAD` | `api/v1/customers/orders/{order}` | `customer.order.show` | `App\Http\Controllers\Api\V1\Sales\Order\SalesOrderDetailController@show` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/orders/{order}/reorder` | `customer.order.reorder` | `App\Http\Controllers\Api\V1\Customer\Cart\ReorderController@reorder` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `GET\|HEAD` | `api/v1/customers/payment/saved-methods` | `customer.payment.saved-methods.index` | `App\Http\Controllers\Api\V1\Customer\Payment\SavedPaymentMethodController@index` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/payment/saved-methods` | `customer.payment.saved-methods.store` | `App\Http\Controllers\Api\V1\Customer\Payment\SavedPaymentMethodController@store` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `DELETE` | `api/v1/customers/payment/saved-methods/{id}` | `customer.payment.saved-methods.destroy` | `App\Http\Controllers\Api\V1\Customer\Payment\SavedPaymentMethodController@destroy` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/payment/setup-intent` | `customer.payment.setup-intent` | `App\Http\Controllers\Api\V1\Customer\Payment\SavedPaymentMethodController@createSetupIntent` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/reset-password` | `customer.password.reset` | `App\Http\Controllers\Api\V1\Customer\ResetPasswordController@reset` | `api` |
| `GET\|HEAD` | `api/v1/customers/search` | `customer.search` | `App\Http\Controllers\Api\V1\Customer\SearchController@search` | `api, App\Http\Middleware\AuthCustomerOrAdmin, Illuminate\Routing\Middleware\ThrottleRequests:customer-search` |
| `POST` | `api/v1/customers/signin` | `customer.signin` | `App\Http\Controllers\Api\V1\Customer\SigninController@signin` | `api` |
| `POST` | `api/v1/customers/signout` | `customer.signout` | `App\Http\Controllers\Api\V1\Customer\SigninController@signout` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/signup` | `customer.signup` | `App\Http\Controllers\Api\V1\Customer\SignupController@signup` | `api` |
| `GET\|HEAD` | `api/v1/customers/stores` | `customer.stores.index` | `App\Http\Controllers\Api\V1\Customer\Store\ConnectedStoreController@index` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/stores/link-existing-product` | `customer.stores.link-existing-product` | `App\Http\Controllers\Api\V1\Customer\Store\StoreProductLinkController@linkExistingProduct` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `GET\|HEAD` | `api/v1/customers/stores/product-lookup` | `customer.stores.product-lookup` | `App\Http\Controllers\Api\V1\Customer\Store\ConnectedStoreController@productLookup` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `DELETE` | `api/v1/customers/stores/{id}` | `customer.stores.disconnect` | `App\Http\Controllers\Api\V1\Customer\Store\ConnectedStoreController@disconnect` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/stores/{id}/check-connection` | `customer.stores.check-connection` | `App\Http\Controllers\Api\V1\Customer\Store\ConnectedStoreController@checkConnection` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/stores/{store_channel}/connect` | `customer.stores.connect` | `App\Http\Controllers\Api\V1\Customer\Store\StoreConnectionController@connect` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `GET\|HEAD` | `api/v1/customers/templates` | `customer.templates.index` | `App\Http\Controllers\Api\V1\Customer\Template\TemplateController@index` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/templates/store-images/upload` | `customer.templates.store-image.upload` | `App\Http\Controllers\Api\V1\Customer\Template\VendorDesignTemplateStoreController@uploadImage` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `DELETE` | `api/v1/customers/templates/store-images/{id}` | `customer.templates.store-image.destroy` | `App\Http\Controllers\Api\V1\Customer\Template\VendorDesignTemplateStoreController@destroyImage` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `DELETE` | `api/v1/customers/templates/{template}` | `customer.templates.destroy` | `App\Http\Controllers\Api\V1\Customer\Template\TemplateActionController@destroy` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `GET\|HEAD` | `api/v1/customers/templates/{template}` | `customer.templates.show` | `App\Http\Controllers\Api\V1\Customer\Template\TemplateController@show` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `GET\|HEAD` | `api/v1/customers/templates/{template}/details` | `customer.templates.details` | `App\Http\Controllers\Api\V1\Customer\Template\TemplateInfoController@show` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/templates/{template}/duplicate` | `customer.templates.duplicate` | `App\Http\Controllers\Api\V1\Customer\Template\TemplateController@duplicate` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/templates/{template}/save-draft` | `customer.templates.store-override.save-draft` | `App\Http\Controllers\Api\V1\Customer\Template\VendorDesignTemplateStoreController@saveDraft` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/templates/{template}/update` | `customer.templates.store-override.update` | `App\Http\Controllers\Api\V1\Customer\Template\VendorDesignTemplateStoreController@update` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/verify-reset-token` | `customer.password.verify` | `App\Http\Controllers\Api\V1\Customer\ForgotPasswordController@verifyToken` | `api` |
| `GET\|HEAD` | `api/v1/customers/wallet` | `customer.wallet.index` | `App\Http\Controllers\Api\V1\Customer\Wallet\WalletController@index` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/wallet/auto-pay/toggle` | `customer.wallet.auto-pay.toggle` | `App\Http\Controllers\Api\V1\Customer\Wallet\WalletController@toggleAutoPay` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/wallet/topup` | `customer.wallet.topup` | `App\Http\Controllers\Api\V1\Customer\Payment\WalletPaymentController@topup` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `POST` | `api/v1/customers/wallet/topup/confirm` | `customer.wallet.` | `App\Http\Controllers\Api\V1\Customer\Payment\WalletPaymentController@confirm` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |
| `GET\|HEAD` | `api/v1/customers/wallet/transactions` | `customer.wallet.transactions` | `App\Http\Controllers\Api\V1\Customer\Wallet\WalletTransactionController@transactions` | `api, App\Http\Middleware\AuthCustomerOrAdmin` |

### `factories` (28 routes)

| Method | URI | Name | Action | Middleware |
| --- | --- | --- | --- | --- |
| `PUT` | `api/v1/factories/account` | `factory.account.update` | `App\Http\Controllers\Api\V1\Factory\AccountController@update` | `api, App\Http\Middleware\AuthAnyUser` |
| `GET\|HEAD` | `api/v1/factories/addresses` | `factory.addresses.index` | `App\Http\Controllers\Api\V1\Factory\FactoryAddressController@index` | `api, App\Http\Middleware\AuthAnyUser` |
| `POST` | `api/v1/factories/addresses` | `factory.addresses.store` | `App\Http\Controllers\Api\V1\Factory\FactoryAddressController@store` | `api, App\Http\Middleware\AuthAnyUser` |
| `DELETE` | `api/v1/factories/addresses/{id}` | `factory.addresses.destroy` | `App\Http\Controllers\Api\V1\Factory\FactoryAddressController@destroy` | `api, App\Http\Middleware\AuthAnyUser` |
| `PUT` | `api/v1/factories/addresses/{id}` | `factory.addresses.update` | `App\Http\Controllers\Api\V1\Factory\FactoryAddressController@update` | `api, App\Http\Middleware\AuthAnyUser` |
| `GET\|HEAD` | `api/v1/factories/business-information` | `factory.business-information.show` | `App\Http\Controllers\Api\V1\Factory\BusinessInformationController@show` | `api, App\Http\Middleware\AuthAnyUser` |
| `POST` | `api/v1/factories/business-information` | `factory.business-information.store` | `App\Http\Controllers\Api\V1\Factory\BusinessInformationController@store` | `api, App\Http\Middleware\AuthAnyUser` |
| `POST` | `api/v1/factories/forgot-password` | `factory.password.email` | `App\Http\Controllers\Api\V1\Factory\ForgotPasswordController@sendResetLink` | `api` |
| `POST` | `api/v1/factories/google/auth` | `factory.google.auth` | `App\Http\Controllers\Api\V1\Factory\GoogleAuthController@authenticate` | `api` |
| `GET\|HEAD` | `api/v1/factories/inventory` | `factory.inventory.index` | `App\Http\Controllers\Api\V1\Catalog\Inventory\InventoryController@index` | `api, App\Http\Middleware\AuthAnyUser` |
| `GET\|HEAD` | `api/v1/factories/inventory/export` | `factory.inventory.export` | `App\Http\Controllers\Api\V1\Catalog\Inventory\InventoryController@export` | `api, App\Http\Middleware\AuthAnyUser` |
| `POST` | `api/v1/factories/inventory/import` | `factory.inventory.import` | `App\Http\Controllers\Api\V1\Catalog\Inventory\InventoryController@import` | `api, App\Http\Middleware\AuthAnyUser` |
| `POST` | `api/v1/factories/inventory/update` | `factory.inventory.update` | `App\Http\Controllers\Api\V1\Catalog\Inventory\InventoryController@update` | `api, App\Http\Middleware\AuthAnyUser` |
| `GET\|HEAD` | `api/v1/factories/label-settings/hang-tag` | `factory.label-settings.hang-tag.show` | `App\Http\Controllers\Api\V1\Factory\LabelSettingController@showHangTag` | `api, Illuminate\Auth\Middleware\Authenticate:factory` |
| `PUT` | `api/v1/factories/label-settings/hang-tag` | `factory.label-settings.hang-tag.update` | `App\Http\Controllers\Api\V1\Factory\LabelSettingController@updateHangTag` | `api, Illuminate\Auth\Middleware\Authenticate:factory` |
| `GET\|HEAD` | `api/v1/factories/label-settings/packaging-label` | `factory.label-settings.packaging-label.show` | `App\Http\Controllers\Api\V1\Factory\LabelSettingController@showPackagingLabel` | `api, Illuminate\Auth\Middleware\Authenticate:factory` |
| `PUT` | `api/v1/factories/label-settings/packaging-label` | `factory.label-settings.packaging-label.update` | `App\Http\Controllers\Api\V1\Factory\LabelSettingController@updatePackagingLabel` | `api, Illuminate\Auth\Middleware\Authenticate:factory` |
| `POST` | `api/v1/factories/login` | `factory.login` | `App\Http\Controllers\Api\V1\Factory\LoginController@login` | `api` |
| `POST` | `api/v1/factories/logout` | `factory.logout` | `App\Http\Controllers\Api\V1\Factory\LoginController@logout` | `api, Illuminate\Auth\Middleware\Authenticate:factory` |
| `GET\|HEAD` | `api/v1/factories/orders` | `factory.orders.index` | `App\Http\Controllers\Api\V1\Sales\Order\SalesOrderController@index` | `api, Illuminate\Auth\Middleware\Authenticate:factory` |
| `GET\|HEAD` | `api/v1/factories/orders/{order}` | `factory.orders.show` | `App\Http\Controllers\Api\V1\Sales\Order\SalesOrderDetailController@show` | `api, Illuminate\Auth\Middleware\Authenticate:factory` |
| `POST` | `api/v1/factories/register` | `factory.register` | `App\Http\Controllers\Api\V1\Factory\RegistrationController@register` | `api` |
| `POST` | `api/v1/factories/resend-otp` | `factory.resend-otp` | `App\Http\Controllers\Api\V1\Factory\ResendOtpController@resendOtp` | `api` |
| `POST` | `api/v1/factories/reset-password` | `factory.password.reset` | `App\Http\Controllers\Api\V1\Factory\ResetPasswordController@reset` | `api` |
| `GET\|HEAD` | `api/v1/factories/secondary-contact` | `factory.secondary-contact.show` | `App\Http\Controllers\Api\V1\Factory\SecondaryContactController@show` | `api, App\Http\Middleware\AuthAnyUser` |
| `POST` | `api/v1/factories/secondary-contact` | `factory.secondary-contact.store` | `App\Http\Controllers\Api\V1\Factory\SecondaryContactController@store` | `api, App\Http\Middleware\AuthAnyUser` |
| `POST` | `api/v1/factories/set-password` | `factory.password.set` | `App\Http\Controllers\Api\V1\Factory\SetPasswordController@setPassword` | `api, Illuminate\Auth\Middleware\Authenticate:factory` |
| `POST` | `api/v1/factories/verify-email` | `factory.verification.verify` | `App\Http\Controllers\Api\V1\Factory\AuthController@verifyEmail` | `api` |

### `location` (3 routes)

| Method | URI | Name | Action | Middleware |
| --- | --- | --- | --- | --- |
| `GET\|HEAD` | `api/v1/location/countries` | `location.countries.index` | `App\Http\Controllers\Api\V1\Location\CountryController@index` | `api` |
| `GET\|HEAD` | `api/v1/location/countries/{country}/states` | `location.states.index` | `App\Http\Controllers\Api\V1\Location\StateController@index` | `api` |
| `GET\|HEAD` | `api/v1/location/factory-countries` | `location.factory-countries.index` | `App\Http\Controllers\Api\V1\Location\FactoryCountryController@index` | `api` |

### `payment-settings` (2 routes)

| Method | URI | Name | Action | Middleware |
| --- | --- | --- | --- | --- |
| `GET\|HEAD` | `api/v1/payment-settings` | `payment-settings.index` | `App\Http\Controllers\Api\V1\Payment\PaymentSettingController@index` | `api` |
| `GET\|HEAD` | `api/v1/payment-settings/{payment_method}` | `payment-settings.show` | `App\Http\Controllers\Api\V1\Payment\PaymentSettingController@show` | `api` |

### `shopify` (9 routes)

| Method | URI | Name | Action | Middleware |
| --- | --- | --- | --- | --- |
| `POST` | `api/v1/shopify/fulfillment/callback` | `shopify.fulfillment.callback` | `App\Http\Controllers\Shopify\ShopifyFulfillmentController@callback` | `api` |
| `POST` | `api/v1/shopify/fulfillment/callback/fulfillment_order_notification` | `shopify.fulfillment.callback.notification` | `App\Http\Controllers\Shopify\ShopifyFulfillmentController@callback` | `api` |
| `POST` | `api/v1/shopify/gdpr/customers/data_request` | `shopify.gdpr.customers.data_request` | `App\Http\Controllers\Shopify\ShopifyGdprController@customersDataRequest` | `api` |
| `POST` | `api/v1/shopify/gdpr/customers/redact` | `shopify.gdpr.customers.redact` | `App\Http\Controllers\Shopify\ShopifyGdprController@customersRedact` | `api` |
| `POST` | `api/v1/shopify/gdpr/shop/redact` | `shopify.gdpr.shop.redact` | `App\Http\Controllers\Shopify\ShopifyGdprController@shopRedact` | `api` |
| `GET\|HEAD` | `api/v1/shopify/health` | `shopify.health` | `Closure` | `api` |
| `POST` | `api/v1/shopify/webhooks/orders` | `shopify.webhooks.orders` | `App\Http\Controllers\Shopify\ShopifyWebhookController@orders` | `api` |
| `POST` | `api/v1/shopify/webhooks/products` | `shopify.webhooks.products` | `App\Http\Controllers\Shopify\ShopifyWebhookController@products` | `api` |
| `POST` | `api/v1/shopify/webhooks/uninstall` | `shopify.webhooks.uninstall` | `App\Http\Controllers\Shopify\ShopifyWebhookController@uninstall` | `api` |

### `store` (1 routes)

| Method | URI | Name | Action | Middleware |
| --- | --- | --- | --- | --- |
| `GET\|HEAD` | `api/v1/store/channels` | `store.channels.index` | `App\Http\Controllers\Api\V1\Store\StoreChannelController@index` | `api` |

### `webhooks` (1 routes)

| Method | URI | Name | Action | Middleware |
| --- | --- | --- | --- | --- |
| `POST` | `api/v1/webhooks/stripe` | `webhooks.stripe` | `App\Http\Controllers\Api\V1\Webhook\StripeWebhookController@handle` | `api` |

### `woocommerce` (4 routes)

| Method | URI | Name | Action | Middleware |
| --- | --- | --- | --- | --- |
| `GET\|HEAD` | `api/v1/woocommerce/health` | `woocommerce.health` | `Closure` | `api` |
| `POST` | `api/v1/woocommerce/webhooks/orders` | `woocommerce.webhooks.orders` | `App\Http\Controllers\Api\V1\Webhook\WooCommerceWebhookController@orders` | `api` |
| `POST` | `api/v1/woocommerce/webhooks/products` | `woocommerce.webhooks.products` | `App\Http\Controllers\Api\V1\Webhook\WooCommerceWebhookController@products` | `api` |
| `POST` | `api/v1/woocommerce/webhooks/uninstall` | `woocommerce.webhooks.uninstall` | `App\Http\Controllers\Api\V1\Webhook\WooCommerceWebhookController@uninstall` | `api` |

### `{fallbackPlaceholder}` (1 routes)

| Method | URI | Name | Action | Middleware |
| --- | --- | --- | --- | --- |
| `GET\|HEAD` | `api/v1/{fallbackPlaceholder}` | `-` | `Closure` | `api` |

## Admin Web Routes (`/admin/...`)

| Method | URI | Name | Action | Middleware |
| --- | --- | --- | --- | --- |
| `GET\|HEAD` | `admin` | `admin.` | `Closure` | `web, Illuminate\Auth\Middleware\RedirectIfAuthenticated:admin` |
| `GET\|HEAD` | `admin/catalog/attributes` | `admin.catalog.attributes.index` | `App\Http\Controllers\Admin\Catalog\Attributes\AttributeController@index` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/catalog/attributes/add-attribute` | `admin.catalog.attributes.create` | `App\Http\Controllers\Admin\Catalog\Attributes\AddAttribute@create` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/attributes/bulk-action` | `admin.catalog.attributes.bulk-action` | `App\Http\Controllers\Admin\Catalog\Attributes\AttributeActions@bulkAction` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/attributes/data` | `admin.catalog.attributes.data` | `App\Http\Controllers\Admin\Catalog\Attributes\AttributeController@getAttributeData` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/attributes/delete-attribute-option-value` | `admin.catalog.attributes.option.value.delete` | `App\Http\Controllers\Admin\Catalog\Attributes\AttributeActions@deleteOptionValue` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/attributes/save-attribute` | `admin.catalog.attributes.store` | `App\Http\Controllers\Admin\Catalog\Attributes\AddAttribute@store` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/attributes/update-attribute-data` | `admin.catalog.attributes.update` | `App\Http\Controllers\Admin\Catalog\Attributes\UpdateAttribute@update` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/catalog/attributes/update-attribute/{id}` | `admin.catalog.attributes.edit` | `App\Http\Controllers\Admin\Catalog\Attributes\UpdateAttribute@edit` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/catalog/categories` | `admin.catalog.categories.index` | `App\Http\Controllers\Admin\Catalog\Category\CategoryController@index` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/catalog/categories/add-category` | `admin.catalog.categories.create` | `App\Http\Controllers\Admin\Catalog\Category\AddCategory@create` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/categories/bulk-action` | `admin.catalog.categories.bulk-action` | `App\Http\Controllers\Admin\Catalog\Category\CategoryActions@bulkAction` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/categories/data` | `admin.catalog.categories.data` | `App\Http\Controllers\Admin\Catalog\Category\CategoryController@getCategoryData` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/categories/save-category` | `admin.catalog.categories.store` | `App\Http\Controllers\Admin\Catalog\Category\AddCategory@store` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/categories/update-category-data` | `admin.catalog.categories.update` | `App\Http\Controllers\Admin\Catalog\Category\UpdateCategory@update` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/catalog/categories/update-category/{id}` | `admin.catalog.categories.edit` | `App\Http\Controllers\Admin\Catalog\Category\UpdateCategory@edit` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/catalog/categories/{id}/categories` | `admin.catalog.categories.industries.categories` | `App\Http\Controllers\Admin\Catalog\Category\CategoryActions@getCategories` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/catalog/design-template` | `admin.catalog.design-template.index` | `App\Http\Controllers\Admin\Catalog\DesignTemplate\DesignTemplateController@designTemplate` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/design-template/bulk-action` | `admin.catalog.design-template.bulk-action` | `App\Http\Controllers\Admin\Catalog\DesignTemplate\DesignTemplateController@bulkAction` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/catalog/design-template/create` | `admin.catalog.design-template.create` | `App\Http\Controllers\Admin\Catalog\DesignTemplate\CreateDesignTemplateController@create` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/design-template/data` | `admin.catalog.design-template.data` | `App\Http\Controllers\Admin\Catalog\DesignTemplate\DesignTemplateController@getDesignTemplateData` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `DELETE` | `admin/catalog/design-template/design-template/layer/{layer}` | `admin.catalog.design-template.layer.delete` | `App\Http\Controllers\Admin\Catalog\DesignTemplate\EditDesignTemplateController@destroy` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/design-template/store` | `admin.catalog.design-template.store` | `App\Http\Controllers\Admin\Catalog\DesignTemplate\CreateDesignTemplateController@store` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/design-template/upload-mockup` | `admin.catalog.design-template.upload-mockup` | `App\Http\Controllers\Admin\Catalog\DesignTemplate\DesignTemplateController@uploadMockup` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/catalog/design-template/{designTemplate}/edit` | `admin.catalog.design-template.edit` | `App\Http\Controllers\Admin\Catalog\DesignTemplate\EditDesignTemplateController@edit` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/design-template/{designTemplate}/update` | `admin.catalog.design-template.update` | `App\Http\Controllers\Admin\Catalog\DesignTemplate\EditDesignTemplateController@update` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/catalog/industries` | `admin.catalog.industries.index` | `App\Http\Controllers\Admin\Catalog\Industry\IndustryController@index` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/industries/bulk-action` | `admin.catalog.industries.bulkAction` | `App\Http\Controllers\Admin\Catalog\Industry\IndustryController@bulkAction` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/industries/checkCategoryCount` | `admin.catalog.industries.checkCategoryCount` | `App\Http\Controllers\Admin\Catalog\Industry\IndustryController@checkCategoryCount` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/industries/store` | `admin.catalog.industries.store` | `App\Http\Controllers\Admin\Catalog\Industry\IndustryController@store` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/catalog/product` | `admin.catalog.product.index` | `App\Http\Controllers\Admin\Catalog\Product\ProductController@index` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/catalog/product/add` | `admin.catalog.product.add` | `App\Http\Controllers\Admin\Catalog\Product\AddProductController@addProduct` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/product/assign-factories` | `admin.catalog.product.factory.assign` | `App\Http\Controllers\Admin\Catalog\Product\ProductToFactoryController@assignFactories` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/product/bulk-action` | `admin.catalog.product.bulk-action` | `App\Http\Controllers\Admin\Catalog\Product\ProductBulkActionsController@bulkAction` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `DELETE` | `admin/catalog/product/delete-media` | `admin.catalog.product.delete.media` | `App\Http\Controllers\Admin\Catalog\Product\ProductMediaController@removeFile` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/catalog/product/design-template/{template}/layers` | `admin.catalog.product.design-template.layers` | `App\Http\Controllers\Admin\Catalog\Product\ProductDesignTemplateController@layers` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/catalog/product/factories` | `admin.catalog.product.factories` | `App\Http\Controllers\Admin\Catalog\Product\ProductToFactoryController@index` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/product/store` | `admin.catalog.product.store` | `App\Http\Controllers\Admin\Catalog\Product\AddProductController@store` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/product/upload-media` | `admin.catalog.product.upload.media` | `App\Http\Controllers\Admin\Catalog\Product\ProductMediaController@uploadFiles` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/product/variant/image` | `admin.catalog.product.variant.image` | `App\Http\Controllers\Admin\Catalog\Product\EditProductController@updateVariantImage` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `DELETE` | `admin/catalog/product/variant/{id}` | `admin.catalog.product.variant.delete` | `App\Http\Controllers\Admin\Catalog\Product\EditProductController@deleteVariant` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/catalog/product/{productId}/product-info` | `admin.catalog.product.info` | `App\Http\Controllers\Admin\Catalog\Product\ProductToFactoryController@info` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/catalog/product/{product}/design-template` | `admin.catalog.product.design-template` | `App\Http\Controllers\Admin\Catalog\Product\ProductDesignTemplateController@index` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/product/{product}/design-template` | `admin.catalog.product.design-template.store` | `App\Http\Controllers\Admin\Catalog\Product\ProductDesignTemplateController@storeConfiguration` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/catalog/product/{product}/design-template/assign-image` | `admin.catalog.product.design-template.assign-image` | `App\Http\Controllers\Admin\Catalog\Product\ProductDesignTemplateImageController@assignImage` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/catalog/product/{product}/edit` | `admin.catalog.product.edit` | `App\Http\Controllers\Admin\Catalog\Product\EditProductController@edit` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/product/{product}/update` | `admin.catalog.product.update` | `App\Http\Controllers\Admin\Catalog\Product\EditProductController@update` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/catalog/product/{product}/upload-layer-image` | `admin.catalog.product.upload-layer-image` | `App\Http\Controllers\Admin\Catalog\Product\ProductDesignTemplateImageController@uploadLayerImage` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/customer` | `admin.customer.index` | `App\Http\Controllers\Admin\Customer\CustomerController@index` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/customer/bulk-action` | `admin.customer.bulk-action` | `App\Http\Controllers\Admin\Customer\CustomerBulkActionsController@bulkAction` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/customer/{customer}` | `admin.customer.show` | `App\Http\Controllers\Admin\Customer\CustomerController@show` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/customer/{customer}/stores` | `admin.customer.stores` | `App\Http\Controllers\Admin\Customer\CustomerController@stores` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/customer/{customer}/templates` | `admin.customer.templates` | `App\Http\Controllers\Admin\Customer\CustomerController@templates` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/customer/{customer}/wallet` | `admin.customer.wallet` | `App\Http\Controllers\Admin\Customer\CustomerController@wallet` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/dashboard` | `admin.dashboard` | `App\Http\Controllers\Admin\Dashboard\DashboardController@index` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/factories` | `admin.factories.index-web` | `App\Http\Controllers\Admin\Factory\FactoryController@index` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/factories/{id}/business-information` | `admin.factories.business-information` | `App\Http\Controllers\Admin\Factory\FactoryController@businessInformation` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/forgot-password` | `admin.password.request` | `App\Http\Controllers\Admin\Auth\PasswordResetLinkController@create` | `web, Illuminate\Auth\Middleware\RedirectIfAuthenticated:admin` |
| `POST` | `admin/forgot-password` | `admin.password.email` | `App\Http\Controllers\Admin\Auth\PasswordResetLinkController@store` | `web, Illuminate\Auth\Middleware\RedirectIfAuthenticated:admin` |
| `GET\|HEAD` | `admin/login` | `admin.login` | `App\Http\Controllers\Admin\Auth\LoginController@create` | `web, Illuminate\Auth\Middleware\RedirectIfAuthenticated:admin` |
| `POST` | `admin/login` | `admin.login.store` | `App\Http\Controllers\Admin\Auth\LoginController@store` | `web, Illuminate\Auth\Middleware\RedirectIfAuthenticated:admin` |
| `GET\|HEAD` | `admin/logout` | `admin.logout` | `App\Http\Controllers\Admin\Auth\LoginController@destroy` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/marketing/discount-coupons` | `admin.marketing.discount-coupons.index` | `App\Http\Controllers\Admin\Marketing\DiscountCoupon\DiscountCouponController@index` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/marketing/discount-coupons/api/search` | `admin.marketing.discount-coupons.api.search` | `App\Http\Controllers\Admin\Marketing\DiscountCoupon\DiscountCouponController@search` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/marketing/discount-coupons/bulk-action` | `admin.marketing.discount-coupons.bulk-action` | `App\Http\Controllers\Admin\Marketing\DiscountCoupon\DiscountCouponController@bulkAction` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/marketing/discount-coupons/check-code` | `admin.marketing.discount-coupons.check-code` | `App\Http\Controllers\Admin\Marketing\DiscountCoupon\DiscountCouponController@checkCode` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/marketing/discount-coupons/create` | `admin.marketing.discount-coupons.create` | `App\Http\Controllers\Admin\Marketing\DiscountCoupon\DiscountCouponCreateController@create` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/marketing/discount-coupons/data` | `admin.marketing.discount-coupons.data` | `App\Http\Controllers\Admin\Marketing\DiscountCoupon\DiscountCouponController@data` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/marketing/discount-coupons/generate-code` | `admin.marketing.discount-coupons.generate-code` | `App\Http\Controllers\Admin\Marketing\DiscountCoupon\DiscountCouponController@generateCode` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/marketing/discount-coupons/store` | `admin.marketing.discount-coupons.store` | `App\Http\Controllers\Admin\Marketing\DiscountCoupon\DiscountCouponCreateController@store` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `PUT` | `admin/marketing/discount-coupons/{id}` | `admin.marketing.discount-coupons.update` | `App\Http\Controllers\Admin\Marketing\DiscountCoupon\DiscountCouponEditController@update` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/marketing/discount-coupons/{id}/edit` | `admin.marketing.discount-coupons.edit` | `App\Http\Controllers\Admin\Marketing\DiscountCoupon\DiscountCouponEditController@edit` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/reset-password` | `admin.password.store` | `App\Http\Controllers\Admin\Auth\NewPasswordController@store` | `web, Illuminate\Auth\Middleware\RedirectIfAuthenticated:admin` |
| `GET\|HEAD` | `admin/reset-password/{token}` | `admin.password.reset` | `App\Http\Controllers\Admin\Auth\NewPasswordController@create` | `web, Illuminate\Auth\Middleware\RedirectIfAuthenticated:admin` |
| `GET\|HEAD` | `admin/sales-routing` | `admin.sales-routing.index` | `App\Http\Controllers\Admin\Factory\FactorySalesRoutingController@index` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/sales-routing/export/{type}` | `admin.sales-routing.export` | `App\Http\Controllers\Admin\Factory\FactorySalesRoutingController@export` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/sales-routing/import` | `admin.sales-routing.import` | `App\Http\Controllers\Admin\Factory\FactorySalesRoutingController@import` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/sales/orders` | `admin.sales.orders.index` | `App\Http\Controllers\Admin\Sales\Order\OrderController@index` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/sales/orders/{id}` | `admin.sales.orders.show` | `App\Http\Controllers\Admin\Sales\Order\OrderController@show` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/settings/currency` | `admin.settings.currency` | `App\Http\Controllers\Admin\Settings\Currency\CurrencySettingController@index` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `PUT` | `admin/settings/currency` | `admin.settings.currency.update` | `App\Http\Controllers\Admin\Settings\Currency\CurrencySettingController@update` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/settings/currency/rates` | `admin.settings.currency.rates` | `App\Http\Controllers\Admin\Settings\Currency\CurrencyRateController@index` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/settings/currency/rates/save` | `admin.settings.currency.rates.save` | `App\Http\Controllers\Admin\Settings\Currency\CurrencyRateController@saveManual` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/settings/currency/update-from-api` | `admin.settings.currency.rates.update` | `App\Http\Controllers\Admin\Settings\Currency\CurrencyRateController@update` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/settings/general/web` | `admin.settings.general.web` | `App\Http\Controllers\Admin\Settings\General\Web\WebSettingController@index` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/settings/general/web` | `admin.settings.general.web.update` | `App\Http\Controllers\Admin\Settings\General\Web\WebSettingController@update` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/settings/shipping-rates` | `admin.settings.shipping-rates` | `App\Http\Controllers\Admin\Settings\Shipping\ShippingRateController@shippingRates` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/settings/tax` | `admin.settings.tax.index` | `App\Http\Controllers\Admin\Settings\Tax\TaxController@index` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/settings/tax/bulk-action` | `admin.settings.tax.bulk-action` | `App\Http\Controllers\Admin\Settings\Tax\TaxController@bulkAction` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/settings/tax/data` | `admin.settings.tax.data` | `App\Http\Controllers\Admin\Settings\Tax\TaxController@data` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/settings/tax/rules` | `admin.settings.tax.rules.index` | `App\Http\Controllers\Admin\Settings\Tax\TaxRuleController@index` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/settings/tax/rules/bulk-action` | `admin.settings.tax.rules.bulk-action` | `App\Http\Controllers\Admin\Settings\Tax\TaxRuleController@bulkAction` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/settings/tax/rules/store` | `admin.settings.tax.rules.store` | `App\Http\Controllers\Admin\Settings\Tax\TaxRuleController@store` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `DELETE` | `admin/settings/tax/rules/{taxRule}` | `admin.settings.tax.rules.delete` | `App\Http\Controllers\Admin\Settings\Tax\TaxRuleController@destroy` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/settings/tax/rules/{taxRule}/update` | `admin.settings.tax.rules.update` | `App\Http\Controllers\Admin\Settings\Tax\TaxRuleController@update` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/settings/tax/store` | `admin.settings.tax.store` | `App\Http\Controllers\Admin\Settings\Tax\TaxController@store` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/settings/tax/zones` | `admin.settings.tax.zones.index` | `App\Http\Controllers\Admin\Settings\Tax\TaxZoneController@index` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/settings/tax/zones/bulk-action` | `admin.settings.tax.zones.bulk-action` | `App\Http\Controllers\Admin\Settings\Tax\TaxZoneController@bulkAction` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/settings/tax/zones/store` | `admin.settings.tax.zones.store` | `App\Http\Controllers\Admin\Settings\Tax\TaxZoneController@store` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `DELETE` | `admin/settings/tax/zones/{taxZone}` | `admin.settings.tax.zones.delete` | `App\Http\Controllers\Admin\Settings\Tax\TaxZoneController@destroy` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/settings/tax/zones/{taxZone}/update` | `admin.settings.tax.zones.update` | `App\Http\Controllers\Admin\Settings\Tax\TaxZoneController@update` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `DELETE` | `admin/settings/tax/{tax}` | `admin.settings.tax.delete` | `App\Http\Controllers\Admin\Settings\Tax\TaxController@destroy` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `POST` | `admin/settings/tax/{tax}/update` | `admin.settings.tax.update` | `App\Http\Controllers\Admin\Settings\Tax\TaxController@update` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |
| `GET\|HEAD` | `admin/wallet` | `admin.wallets` | `App\Http\Controllers\Admin\Customer\CustomerController@wallets` | `web, Illuminate\Auth\Middleware\Authenticate:admin` |

## Other Web/System Routes

| Method | URI | Name | Action | Middleware |
| --- | --- | --- | --- | --- |
| `GET\|HEAD` | `/` | `-` | `Closure` | `web` |

## Regenerate

```bash
php artisan route:list --except-vendor --json > /tmp/airventory-routes.json
# then regenerate this markdown index using the same script/workflow
```
