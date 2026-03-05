# Airventory API

Airventory API is a Laravel 12 backend for a multi-role B2B print-commerce platform.

It supports:
- Customer (vendor) onboarding, wallets, carts, templates, orders, and connected stores.
- Factory onboarding, business setup, inventory, order fulfillment, and label settings.
- Admin web panel and admin API for operations, catalog, and routing.
- Shopify and WooCommerce integrations (webhooks, sync jobs, callbacks).

## Documentation

- Main documentation hub: [docs/README.md](docs/README.md)
- Architecture deep dive: [COMPREHENSIVE_ARCHITECTURE_GUIDE.md](COMPREHENSIVE_ARCHITECTURE_GUIDE.md)
- Environment variables reference: [docs/ENVIRONMENT_VARIABLES.md](docs/ENVIRONMENT_VARIABLES.md)
- Full route inventory: [docs/ROUTE_INDEX.md](docs/ROUTE_INDEX.md)

## Stack

- PHP `8.2+`
- Laravel `12.x`
- JWT auth via `tymon/jwt-auth`
- Queue + dashboard via `laravel/horizon`
- Integrations: Stripe, Google OAuth, Shopify, WooCommerce
- Optional storage: AWS S3 via Flysystem

## Route Surface (Current)

Generated from `php artisan route:list --except-vendor --json`:
- Total routes: `251`
- API routes (`/api/...`): `146`
- Web/admin routes: `105`

Main API prefixes under `/api/v1`:
- `customers` (`56` routes)
- `factories` (`28` routes)
- `admin` (`26` routes)
- `catalog` (`11` routes)
- `shopify` (`9` routes)
- `woocommerce` (`4` routes)
- `location` (`3` routes)
- `config`, `payment-settings`, `auth`, `callbacks`, `store`, `webhooks`

## Authentication Model

Configured guards (`config/auth.php`):
- `admin`: web session guard for admin panel.
- `admin_api`: JWT guard for admin API routes.
- `customer`: JWT guard for customer/vendor API routes.
- `factory`: JWT guard for factory API routes.

Custom middleware aliases (`bootstrap/app.php`):
- `auth.customer_or_admin`: allows `customer` OR `admin_api`.
- `auth.any`: allows `customer`, `factory`, OR `admin_api`.

## Local Development

### Prerequisites

- PHP `8.2+`
- Composer
- Database (`sqlite` for quick start, or MySQL/Postgres/SQL Server)
- Redis (recommended for queue + Horizon)

### Setup

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
php artisan migrate
php artisan serve
```

Optional (for background processing):

```bash
php artisan horizon
php artisan schedule:work
```

If you prefer a one-step bootstrap:

```bash
composer run setup
```

## Daily Commands

```bash
# Tests
composer test

# Route inventory
php artisan route:list --except-vendor

# Queue workers (without Horizon)
php artisan queue:work

# Scheduled task preview
php artisan schedule:list

# Lint / format
./vendor/bin/pint
```

## Scheduled Jobs

Defined in `routes/console.php`:
- `woocommerce:check-connections` at `00:30` daily.
- `report:daily-vendor` at `01:00` daily.
- `orders:sync-missing` using `ORDER_SYNC_SCHEDULE` (default `0 2 * * *`) when `ORDER_SYNC_ENABLED=true`.

## Integrations

### Shopify

Routes live in `routes/shopify.php` under `/api/v1/shopify`:
- Health: `GET /api/v1/shopify/health`
- Webhooks: orders, products, uninstall
- Fulfillment callbacks
- GDPR endpoints

### WooCommerce

Routes live in `routes/woocommerce.php` under `/api/v1/woocommerce`:
- Health: `GET /api/v1/woocommerce/health`
- Webhooks: orders, products, uninstall

### Stripe

- Webhook endpoint: `POST /api/v1/webhooks/stripe`
- Secret key config: `STRIPE_WEBHOOK_SECRET`

## Admin Panel

- Login entry point: `/admin/login`
- Authenticated web routes are under `/admin/*`
- Horizon UI uses middleware `['web', 'auth:admin']`

## Existing Detailed API Docs

Specialized endpoint documentation is available in [docs/](docs/) (factory auth/registration, inventory, label settings, customer search, branding, reorder, webhook sync, and more). Start at [docs/README.md](docs/README.md).
