# Airventory API Documentation Hub

This directory contains functional API docs, architecture notes, runbooks, and integration references.

## Start Here

- Project overview and setup: [../README.md](../README.md)
- Architecture deep dive: [../COMPREHENSIVE_ARCHITECTURE_GUIDE.md](../COMPREHENSIVE_ARCHITECTURE_GUIDE.md)
- Environment variables: [ENVIRONMENT_VARIABLES.md](ENVIRONMENT_VARIABLES.md)
- Full route inventory: [ROUTE_INDEX.md](ROUTE_INDEX.md)

## Recommended Reading Paths

### Backend engineer onboarding

1. [../README.md](../README.md)
2. [ENVIRONMENT_VARIABLES.md](ENVIRONMENT_VARIABLES.md)
3. [../COMPREHENSIVE_ARCHITECTURE_GUIDE.md](../COMPREHENSIVE_ARCHITECTURE_GUIDE.md)
4. [ROUTE_INDEX.md](ROUTE_INDEX.md)

### API consumer (frontend/integration)

1. [AUTH_ME_API.md](AUTH_ME_API.md)
2. Auth docs for your actor type (customer/factory/admin)
3. Domain-specific docs (cart/orders/branding/templates/etc.)

### Operations

1. [ORDER_SYNC_CRON.md](ORDER_SYNC_CRON.md)
2. [runbooks/woocommerce-sync-ops.md](runbooks/woocommerce-sync-ops.md)
3. [WOOCOMMERCE_SYNC.md](WOOCOMMERCE_SYNC.md)
4. [migration/woocommerce-sync-v2-migration.md](migration/woocommerce-sync-v2-migration.md)

## Authentication and Identity

- [ADMIN_JWT_AUTH.md](ADMIN_JWT_AUTH.md)
- [AJAX_TOKEN_EXAMPLE.md](AJAX_TOKEN_EXAMPLE.md)
- [AUTH_ME_API.md](AUTH_ME_API.md)
- [FACTORY_AUTHENTICATION_API.md](FACTORY_AUTHENTICATION_API.md)
- [FACTORY_REGISTRATION_API.md](FACTORY_REGISTRATION_API.md)

## Factory APIs

- [FACTORY_ACCOUNT_UPDATE_API.md](FACTORY_ACCOUNT_UPDATE_API.md)
- [FACTORY_ADDRESSES_API.md](FACTORY_ADDRESSES_API.md)
- [FACTORY_BUSINESS_INFORMATION_API.md](FACTORY_BUSINESS_INFORMATION_API.md)
- [FACTORY_COUNTRIES_API.md](FACTORY_COUNTRIES_API.md)
- [FACTORY_SECONDARY_CONTACT_API.md](FACTORY_SECONDARY_CONTACT_API.md)
- [LABEL_SETTINGS_API.md](LABEL_SETTINGS_API.md)
- [LABEL_SETTINGS_IMPLEMENTATION.md](LABEL_SETTINGS_IMPLEMENTATION.md)
- [factory_orders_api.md](factory_orders_api.md)

## Customer APIs

- [CUSTOMER_SEARCH_API.md](CUSTOMER_SEARCH_API.md)
- [CUSTOMER_REORDER_API.md](CUSTOMER_REORDER_API.md)
- [CUSTOMER_STORE_PRODUCT_LINK_API.md](CUSTOMER_STORE_PRODUCT_LINK_API.md)
- [customer-design-branding-api.md](customer-design-branding-api.md)
- [VENDOR_DESIGN_BRANDING_API.md](VENDOR_DESIGN_BRANDING_API.md)
- [WALLET_PAYMENT_FIXES_SUMMARY.md](WALLET_PAYMENT_FIXES_SUMMARY.md)

## Catalog, Inventory, and Product Design

- [INVENTORY_API.md](INVENTORY_API.md)
- [channels-sku-lookup-before-create.md](channels-sku-lookup-before-create.md)

## Admin APIs

- [ADMIN_FACTORY_STATUS_MANAGEMENT_API.md](ADMIN_FACTORY_STATUS_MANAGEMENT_API.md)

## Integrations

- [WOOCOMMERCE_SYNC.md](WOOCOMMERCE_SYNC.md)
- [ORDER_SYNC_CRON.md](ORDER_SYNC_CRON.md)
- [runbooks/woocommerce-sync-ops.md](runbooks/woocommerce-sync-ops.md)
- [migration/woocommerce-sync-v2-migration.md](migration/woocommerce-sync-v2-migration.md)
- [adr/001-woocommerce-sync-batching.md](adr/001-woocommerce-sync-batching.md)

## Notes

- Route definitions are in `routes/api.php`, `routes/shopify.php`, `routes/woocommerce.php`, and `routes/web.php`.
- For the latest endpoint list, regenerate route docs with `php artisan route:list --except-vendor --json`.
