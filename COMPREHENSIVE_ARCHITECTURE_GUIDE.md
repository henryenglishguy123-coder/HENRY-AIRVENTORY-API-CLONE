# Airventory API - Comprehensive Architecture Guide

**Version:** 1.0  
**Last Updated:** February 2026  
**Laravel Version:** 12.x  
**PHP Version:** 8.2+

---

## Table of Contents

1. [Executive Summary](#executive-summary)
2. [Language Composition & Tech Stack](#language-composition--tech-stack)
3. [Project Directory Structure](#project-directory-structure)
4. [Architecture Overview](#architecture-overview)
5. [Authentication & Authorization](#authentication--authorization)
6. [API Response Format & Error Handling](#api-response-format--error-handling)
7. [Controllers Documentation](#controllers-documentation)
8. [Services Documentation](#services-documentation)
9. [Jobs/Agents Documentation](#jobsagents-documentation)
10. [Database Transaction Patterns](#database-transaction-patterns)
11. [Queue Management](#queue-management)
12. [Integration Patterns](#integration-patterns)
13. [Best Practices & Development Guidelines](#best-practices--development-guidelines)

---

## Executive Summary

**Airventory API** is a comprehensive B2B e-commerce platform built on Laravel 12, designed to connect manufacturers (factories) with retailers (customers/vendors). The platform provides:

- **Multi-tenant architecture** supporting three distinct user types: Factories, Customers, and Admins
- **E-commerce integration** with Shopify and WooCommerce stores
- **Product catalog management** with custom design templates and branding
- **Cart and order processing** with tax calculation, shipping rates, and payment processing
- **Wallet system** for customer payments and transactions
- **Asynchronous job processing** for store synchronization and order fulfillment

### Key Features

- Factory registration and product management
- Customer store connections and product synchronization
- Custom design template system with branding capabilities
- Multi-currency support with conversion
- Tax calculation with zone-based rules
- Stripe payment integration
- Excel import/export functionality
- Real-time order processing from connected stores

---

## Language Composition & Tech Stack

### Codebase Composition

- **PHP (58.8%)** - Backend API logic, controllers, services, jobs
- **Blade (12.8%)** - Email templates and admin panel views
- **JavaScript (11.6%)** - Frontend interactions and admin panel
- **CSS/SCSS/Less (16.8%)** - Styling for admin panel and emails

### Core Technologies

#### Backend Framework
- **Laravel 12.x** - PHP framework providing MVC architecture, routing, ORM, and more
- **PHP 8.2+** - Server-side language with modern features (enums, attributes, etc.)

#### Key Laravel Packages
- **tymon/jwt-auth** (^2.2) - JWT authentication for API endpoints
- **laravel/horizon** (^5.43) - Redis queue monitoring and management
- **maatwebsite/excel** (^3.1) - Excel import/export functionality
- **yajra/laravel-datatables** (12.0) - DataTables integration for admin panel

#### Third-Party Integrations
- **stripe/stripe-php** (^19.1) - Payment processing
- **google/apiclient** (^2.18) - Google OAuth integration
- **intervention/image** (^3.11) - Image processing and manipulation
- **league/flysystem-aws-s3-v3** (^3.0) - AWS S3 file storage

#### Development Tools
- **PHPUnit** (^11.5.3) - Unit and feature testing
- **Laravel Pint** (^1.24) - Code style formatting
- **Laravel Pail** (^1.2.2) - Log viewing
- **Laravel Sail** (^1.41) - Docker development environment

---

## Project Directory Structure

```
airventory-api/
├── app/
│   ├── Console/              # Artisan commands
│   ├── Enums/                # PHP enums for constants (OrderStatus, PaymentStatus, etc.)
│   ├── Exports/              # Excel export classes
│   ├── Helpers/              # Global helper functions (Format, PriceFormatter, etc.)
│   ├── Http/
│   │   ├── Controllers/      # HTTP request handlers
│   │   │   ├── Admin/        # Admin web panel controllers
│   │   │   ├── Api/V1/       # API v1 controllers
│   │   │   │   ├── Admin/    # Admin API controllers
│   │   │   │   ├── Catalog/  # Product catalog controllers
│   │   │   │   ├── Customer/ # Customer API controllers
│   │   │   │   ├── Factory/  # Factory API controllers
│   │   │   │   └── ...       # Other API controllers
│   │   │   └── Shopify/      # Shopify webhook controllers
│   │   ├── Middleware/       # Request middleware (auth, CORS, etc.)
│   │   └── Resources/        # API response resources (JSON transformers)
│   ├── Imports/              # Excel import classes
│   ├── Jobs/                 # Queued background jobs
│   │   ├── Shopify/          # Shopify sync jobs
│   │   └── WooCommerce/      # WooCommerce sync jobs
│   ├── Mail/                 # Email templates (Mailable classes)
│   ├── Models/               # Eloquent ORM models
│   │   ├── Admin/            # Admin models
│   │   ├── Catalog/          # Product catalog models
│   │   ├── Customer/         # Customer models
│   │   ├── Factory/          # Factory models
│   │   └── ...               # Other models
│   ├── Observers/            # Model event listeners
│   ├── Policies/             # Authorization policies
│   ├── Providers/            # Service providers
│   ├── Services/             # Business logic services
│   │   ├── Channels/         # Store integration services (Shopify, WooCommerce)
│   │   ├── Customer/         # Customer-related services (Cart, Wallet, etc.)
│   │   ├── Sales/            # Sales and order services
│   │   ├── Store/            # Store connection services
│   │   └── Tax/              # Tax calculation services
│   ├── Support/              # Utility classes
│   └── Traits/               # Reusable traits (ApiResponse, etc.)
├── bootstrap/                # Laravel bootstrap files
├── config/                   # Configuration files
├── database/
│   ├── factories/            # Model factories for testing
│   ├── migrations/           # Database migrations
│   └── seeders/              # Database seeders
├── docs/                     # Documentation files
│   ├── adr/                  # Architecture Decision Records
│   ├── migration/            # Migration guides
│   └── runbooks/             # Operational runbooks
├── public/                   # Publicly accessible files (index.php, assets)
├── resources/
│   ├── css/                  # Stylesheets
│   ├── js/                   # JavaScript files
│   └── views/                # Blade templates
├── routes/
│   ├── api.php               # API routes
│   ├── web.php               # Web routes (admin panel)
│   ├── shopify.php           # Shopify webhook routes
│   └── woocommerce.php       # WooCommerce webhook routes
├── storage/                  # Application storage (logs, cache, uploads)
├── tests/
│   ├── Feature/              # Feature tests
│   └── Unit/                 # Unit tests
├── composer.json             # PHP dependencies
├── package.json              # NPM dependencies
└── phpunit.xml               # PHPUnit configuration
```

---

## Architecture Overview

### Multi-Tenant Design

Airventory API implements a **multi-tenant architecture** with three distinct user types, each with their own authentication guard and permissions:

1. **Factories** - Manufacturers who provide products
2. **Customers/Vendors** - Retailers who sell products to end consumers
3. **Admins** - Platform administrators who manage the system

```
┌─────────────────────────────────────────────────────────────┐
│                     Airventory API                          │
├─────────────────────────────────────────────────────────────┤
│                                                             │
│  ┌───────────────┐  ┌───────────────┐  ┌───────────────┐  │
│  │   Factory     │  │   Customer    │  │     Admin     │  │
│  │   Portal      │  │   Portal      │  │     Panel     │  │
│  │ (Guard: factory)│ (Guard: customer)│ (Guard: admin_api)│
│  └───────┬───────┘  └───────┬───────┘  └───────┬───────┘  │
│          │                   │                   │          │
│          └───────────────────┼───────────────────┘          │
│                              │                              │
│  ┌───────────────────────────▼───────────────────────────┐ │
│  │         Controllers (Request Handlers)                 │ │
│  └───────────────────────────┬───────────────────────────┘ │
│                              │                              │
│  ┌───────────────────────────▼───────────────────────────┐ │
│  │         Services (Business Logic)                      │ │
│  └───────────────────────────┬───────────────────────────┘ │
│                              │                              │
│  ┌───────────────────────────▼───────────────────────────┐ │
│  │         Models (Data Layer)                            │ │
│  └───────────────────────────┬───────────────────────────┘ │
│                              │                              │
│  ┌───────────────────────────▼───────────────────────────┐ │
│  │         Database (MySQL/PostgreSQL)                    │ │
│  └────────────────────────────────────────────────────────┘ │
│                                                             │
└─────────────────────────────────────────────────────────────┘
```

### Request/Response Flow

```
1. Client Request
   ↓
2. Routing (routes/api.php, routes/web.php)
   ↓
3. Middleware (Authentication, Authorization, CORS)
   ↓
4. Controller (Validates input, calls services)
   ↓
5. Service Layer (Business logic, orchestration)
   ↓
6. Model Layer (Database interactions via Eloquent ORM)
   ↓
7. Database (Query execution)
   ↓
8. Model Layer (Returns data)
   ↓
9. Service Layer (Processes data)
   ↓
10. Controller (Formats response using ApiResponse trait)
    ↓
11. Response (JSON for API, HTML for web)
```

### Dependency Injection

Laravel's service container provides automatic dependency injection throughout the application:

- **Constructor Injection**: Services and dependencies are injected into controller constructors
- **Method Injection**: Dependencies can be type-hinted in controller methods
- **Service Providers**: Register services in `app/Providers/` for application-wide use

**Example:**
```php
class CartItemController extends Controller
{
    public function __construct(
        private CartService $cartService,
        private CartPricingService $pricingService
    ) {}

    public function store(Request $request)
    {
        $cart = $this->cartService->getActiveCart($request->user()->id);
        // Use injected services
    }
}
```

---

## Authentication & Authorization

### Authentication Guards

The application uses Laravel's built-in authentication system with three custom guards:

#### 1. Factory Guard (`factory`)
- Authenticates manufacturer/factory users
- Uses session-based authentication
- Routes: `/api/v1/factory/*`

#### 2. Customer Guard (`customer`)
- Authenticates retailer/vendor users
- Uses session-based authentication
- Routes: `/api/v1/customer/*`

#### 3. Admin API Guard (`admin_api`)
- Authenticates admin users via JWT tokens
- Token-based authentication using `tymon/jwt-auth`
- Routes: `/api/v1/admin/*`

### Middleware

#### Authentication Middleware

**Location:** `app/Http/Middleware/`

- **`AuthAnyUser`** - Checks if user is authenticated via any guard (customer, factory, admin_api)
- **`AuthCustomerOrAdmin`** - Restricts access to customer or admin guards only

**Usage in Routes:**
```php
// Requires any authenticated user
Route::middleware(['auth.any'])->group(function () {
    // Routes accessible by any authenticated user type
});

// Requires customer or admin
Route::middleware(['auth.customer.or.admin'])->group(function () {
    // Routes for customers and admins only
});
```

### Authorization Policies

**Location:** `app/Policies/`

- **`VendorDesignTemplatePolicy`** - Controls access to design templates
  - Ensures users can only access their own templates
  - Validates template ownership before operations

**Example:**
```php
class VendorDesignTemplatePolicy
{
    public function view(User $user, VendorDesignTemplate $template): bool
    {
        return $user->id === $template->vendor_id;
    }
}
```

### JWT Authentication Flow (Admin API)

1. **Mint Token**: Admin logs into web panel → calls `/api/v1/admin/mint-token` → receives JWT
2. **Use Token**: Client includes token in `Authorization: Bearer {token}` header
3. **Validate Token**: Middleware validates token and loads admin user
4. **Refresh Token**: Token can be refreshed before expiration

---

## API Response Format & Error Handling

### Standard Response Envelope

All API endpoints use the `ApiResponse` trait to maintain consistent response formatting.

**Location:** `app/Traits/ApiResponse.php`

#### Success Response

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {
    // Response data
  }
}
```

**PHP Method:**
```php
protected function successResponse(
    $data = [], 
    string $message = 'Success', 
    int $code = 200
): JsonResponse
```

#### Error Response

```json
{
  "success": false,
  "message": "Error message describing what went wrong",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

**PHP Method:**
```php
protected function errorResponse(
    string $message = 'Error', 
    int $code = 400, 
    array $errors = []
): JsonResponse
```

### HTTP Status Codes

| Code | Usage |
|------|-------|
| 200 | Successful GET, PUT, PATCH requests |
| 201 | Successful POST request (resource created) |
| 204 | Successful DELETE request (no content) |
| 400 | Bad request (generic client error) |
| 401 | Unauthorized (authentication required) |
| 403 | Forbidden (authenticated but not authorized) |
| 404 | Resource not found |
| 422 | Unprocessable entity (validation failed) |
| 500 | Internal server error |
| 503 | Service unavailable |

### Error Tracking

The application tracks errors at the model level for audit trails:

- **`CartError`** - Tracks cart operation errors
- **`SalesOrderError`** - Tracks order processing errors

These models store:
- Error type and message
- Related entity IDs
- Timestamp
- Request context

---

## Controllers Documentation

Controllers handle HTTP requests, validate input, and coordinate between services to generate responses.

### Factory Controllers

**Location:** `app/Http/Controllers/Api/V1/Factory/`

Factory controllers manage manufacturer registration, authentication, and account management.

#### Authentication Controllers

| Controller | Purpose | Key Methods |
|------------|---------|-------------|
| `RegistrationController` | Factory registration | `store()` - Register new factory |
| `LoginController` | Factory login | `login()` - Authenticate factory user |
| `ForgotPasswordController` | Password reset request | `sendResetLink()` - Email reset link |
| `ResetPasswordController` | Password reset | `reset()` - Update password with token |
| `SetPasswordController` | Initial password setup | `store()` - Set password after registration |
| `ResendOtpController` | OTP resend | `resend()` - Resend verification OTP |
| `AuthController` | Auth status | `check()` - Check authentication status |

#### Account Management Controllers

| Controller | Purpose | Key Methods |
|------------|---------|-------------|
| `AccountController` | Profile management | `show()` - Get profile<br>`update()` - Update profile |
| `BusinessInformationController` | Business details | `show()` - Get business info<br>`update()` - Update business info |
| `SecondaryContactController` | Secondary contacts | `index()` - List contacts<br>`store()` - Add contact |
| `FactoryAddressController` | Address management | `index()` - List addresses<br>`store()` - Add address |

#### Operational Controllers

| Controller | Purpose | Key Methods |
|------------|---------|-------------|
| `FactorySalesRoutingApiController` | Sales routing rules | `index()` - List routing rules<br>`store()` - Create routing rule |
| `FactoryShippingRateController` | Shipping rates | `index()` - List shipping rates<br>`store()` - Add shipping rate |

### Customer Controllers

**Location:** `app/Http/Controllers/Api/V1/Customer/`

Customer controllers handle vendor/retailer operations including cart, orders, and store connections.

#### Authentication Controllers

| Controller | Purpose | Key Methods |
|------------|---------|-------------|
| `SignupController` | Customer registration | `store()` - Register new customer |
| `SigninController` | Customer login | `login()` - Authenticate customer |
| `ForgotPasswordController` | Password reset | `sendResetLink()` - Email reset link |
| `ResetPasswordController` | Password reset | `reset()` - Update password |
| `GoogleAuthController` | Google OAuth | `redirect()` - Redirect to Google<br>`callback()` - Handle OAuth callback |
| `AuthController` | Auth status | `check()` - Check authentication |

#### Cart Controllers

| Controller | Purpose | Key Methods |
|------------|---------|-------------|
| `CartItemController` | Cart item management | `index()` - List cart items<br>`store()` - Add item to cart<br>`update()` - Update item quantity<br>`destroy()` - Remove item |
| `CartViewController` | Cart viewing | `show()` - Get cart details with totals |
| `CartAddressController` | Cart addresses | `update()` - Set shipping/billing address |
| `CartDiscountController` | Cart discounts | `store()` - Apply discount code<br>`destroy()` - Remove discount |

#### Account Controllers

| Controller | Purpose | Key Methods |
|------------|---------|-------------|
| `AccountController` | Profile management | `show()` - Get profile<br>`update()` - Update profile |
| `DashboardController` | Dashboard data | `index()` - Get dashboard stats |
| `BillingAddressController` | Billing addresses | `index()` - List addresses<br>`store()` - Add address |
| `ShippingAddressController` | Shipping addresses | `index()` - List addresses<br>`store()` - Add address |

#### Payment Controllers

| Controller | Purpose | Key Methods |
|------------|---------|-------------|
| `SavedPaymentMethodController` | Payment methods | `index()` - List saved methods<br>`store()` - Add payment method<br>`destroy()` - Remove method |
| `WalletPaymentController` | Wallet payments | `store()` - Process wallet payment |
| `WalletController` | Wallet management | `show()` - Get wallet balance |
| `WalletTransactionController` | Wallet transactions | `index()` - List transactions |

#### Store Integration Controllers

| Controller | Purpose | Key Methods |
|------------|---------|-------------|
| `StoreConnectionController` | Connect stores | `store()` - Connect Shopify/WooCommerce store |
| `ConnectedStoreController` | Manage connections | `index()` - List connected stores<br>`destroy()` - Disconnect store |

#### Template & Design Controllers

| Controller | Purpose | Key Methods |
|------------|---------|-------------|
| `TemplateController` | Template browsing | `index()` - List available templates<br>`show()` - Get template details |
| `VendorDesignTemplateStoreController` | Vendor templates | `index()` - List vendor's templates<br>`store()` - Push template to store |
| `TemplateActionController` | Template actions | `store()` - Add template to cart |
| `TemplateInfoController` | Template info | `show()` - Get template information |
| `DesignBrandingController` | Design branding | `store()` - Apply branding to design<br>`update()` - Update branding |
| `SaveDesignController` | Save designs | `store()` - Save custom design |
| `CustomerMediaGalleryController` | Media gallery | `index()` - List media<br>`store()` - Upload media |

### Admin Controllers

#### Admin API Controllers

**Location:** `app/Http/Controllers/Api/V1/Admin/`

| Controller | Purpose | Key Methods |
|------------|---------|-------------|
| `AdminJWTAuthController` | JWT token management | `mintToken()` - Generate JWT for admin |
| `CustomerController` | Customer management | `index()` - List customers<br>`show()` - Get customer details |
| `FactoryController` | Factory management | `index()` - List factories<br>`show()` - Get factory details |

#### Admin Web Controllers

**Location:** `app/Http/Controllers/Admin/`

These controllers render views for the admin web panel.

**Categories:**

1. **Authentication**
   - `LoginController`, `NewPasswordController`, `PasswordResetLinkController`

2. **Dashboard**
   - `DashboardController` - Admin dashboard with statistics

3. **Customer Management**
   - `CustomerController` - CRUD operations for customers
   - `CustomerBulkActionsController` - Bulk customer operations

4. **Catalog Management**
   - `IndustryController` - Industry categories
   - `CategoryController` - Product categories
   - `AttributeController` - Product attributes
   - `ProductController` - Product management
   - `ProductMediaController` - Product images/media
   - `ProductDesignTemplateController` - Design templates for products
   - `ProductToFactoryController` - Product-factory associations
   - `DesignTemplateController` - Template library

5. **Sales Management**
   - `OrderController` - Order viewing and management

6. **Settings**
   - `CurrencySettingController` - Currency configuration
   - `CurrencyRateController` - Exchange rates
   - `TaxController` - Tax settings
   - `TaxZoneController` - Tax zones
   - `TaxRuleController` - Tax rules
   - `ShippingRateController` - Shipping rate configuration
   - `WebSettingController` - General web settings

7. **Marketing**
   - `DiscountCouponController` - Discount code management
   - `DiscountCouponCreateController` - Create discount codes
   - `DiscountCouponEditController` - Edit discount codes

8. **Factory Management**
   - `FactorySalesRoutingController` - Factory routing rules

### Catalog Controllers

**Location:** `app/Http/Controllers/Api/V1/Catalog/`

Public-facing controllers for browsing the product catalog.

| Controller | Purpose | Key Methods |
|------------|---------|-------------|
| `IndustryController` | Industry listing | `index()` - List industries |
| `CategoryController` | Category listing | `index()` - List categories by industry |
| `CategoryDetailsController` | Category details | `show()` - Get category with products |
| `ProductFilterController` | Product filtering | `index()` - Filter products by criteria |
| `ProductCardController` | Product cards | `index()` - List products as cards |
| `ProductDetailsController` | Product details | `show()` - Get full product details |
| `ProductDesignerController` | Product designer | `show()` - Get designer configuration |
| `ProductDesignerImageController` | Designer images | `show()` - Get design template images |
| `InventoryController` | Inventory check | `show()` - Check product availability |

### Other API Controllers

#### Sales Order Controllers

**Location:** `app/Http/Controllers/Api/V1/Sales/Order/`

| Controller | Purpose | Key Methods |
|------------|---------|-------------|
| `SalesOrderController` | Order management | `index()` - List orders<br>`show()` - Get order details<br>`store()` - Create order |
| `SalesOrderPaymentController` | Order payments | `store()` - Process order payment |
| `SalesOrderDetailController` | Order details | `show()` - Get detailed order info |

#### Location Controllers

**Location:** `app/Http/Controllers/Api/V1/Location/`

| Controller | Purpose | Key Methods |
|------------|---------|-------------|
| `CountryController` | Country list | `index()` - List all countries |
| `StateController` | State/Province list | `index()` - List states by country |
| `FactoryCountryController` | Factory countries | `index()` - List countries with factories |

#### Webhook Controllers

| Controller | Purpose | Key Methods |
|------------|---------|-------------|
| `StripeWebhookController` | Stripe webhooks | `handleWebhook()` - Process Stripe events |
| `WooCommerceWebhookController` | WooCommerce webhooks | Handle WooCommerce store events |

#### Shopify Controllers

**Location:** `app/Http/Controllers/Shopify/`

| Controller | Purpose | Key Methods |
|------------|---------|-------------|
| `ShopifyWebhookController` | Shopify webhooks | Handle various Shopify events |
| `ShopifyFulfillmentController` | Fulfillment callbacks | Handle fulfillment service callbacks |
| `ShopifyGdprController` | GDPR compliance | Handle GDPR-related webhooks |

---

## Services Documentation

Services contain the core business logic of the application. They are injected into controllers and orchestrate operations across multiple models.

### Cart Services

**Location:** `app/Services/Customer/Cart/`

#### CartService

**Purpose:** Manages cart lifecycle and basic operations.

**Key Methods:**
- `getActiveCart(int $vendorId): Cart` - Get or create active cart for vendor
- `findActiveCart(int $vendorId): ?Cart` - Find existing active cart
- `getActiveCartForUpdate(int $vendorId): Cart` - Get cart with row-level lock for concurrent updates

**Usage:**
```php
$cart = $cartService->getActiveCart($customerId);
```

#### CartPricingService

**Purpose:** Calculates item pricing including quantity discounts and printing costs.

**Key Methods:**
- `calculateItemPricing(CartItem $item): array` - Calculate base price, printing cost, and total for cart item
- `applyQuantityDiscounts(float $basePrice, int $quantity): float` - Apply volume discounts

**Responsibilities:**
- Base product pricing
- Quantity-based discount calculations
- Printing cost integration

#### PrintingCostService

**Purpose:** Calculates custom printing/branding costs for products.

**Key Methods:**
- `calculatePrintingCost(CartItem $item): float` - Calculate total printing cost for item
- `getPrintingCostPerUnit(Design $design): float` - Get per-unit printing cost

**Factors Considered:**
- Number of print locations
- Print area size
- Color count
- Printing method

#### CartTotalsService

**Purpose:** Calculates cart-level totals including tax and shipping.

**Key Methods:**
- `calculateTotals(Cart $cart): array` - Calculate all cart totals
- `recalculateCart(Cart $cart): void` - Recalculate and save cart totals

**Returns:**
```php
[
    'subtotal' => 100.00,
    'discount' => 10.00,
    'tax' => 9.00,
    'shipping' => 15.00,
    'total' => 114.00
]
```

#### CartDiscountService

**Purpose:** Applies and validates discount codes.

**Key Methods:**
- `applyDiscount(Cart $cart, string $code): bool` - Apply discount code to cart
- `removeDiscount(Cart $cart): void` - Remove applied discount
- `validateDiscount(string $code, Cart $cart): bool` - Validate discount eligibility

**Validation Checks:**
- Code existence and active status
- Usage limits
- Expiration dates
- Minimum cart value
- Customer eligibility

#### CartShippingService

**Purpose:** Calculates shipping costs based on cart contents and destination.

**Key Methods:**
- `calculateShipping(Cart $cart): float` - Calculate shipping cost
- `getAvailableShippingMethods(Cart $cart): array` - List available shipping options

**Factors:**
- Destination address
- Cart weight and dimensions
- Factory locations
- Shipping rate rules

#### CartRoutingService

**Purpose:** Routes cart items to appropriate factories based on routing rules.

**Key Methods:**
- `routeCart(Cart $cart): array` - Determine which factory fulfills each item
- `getFactoryForProduct(Product $product, Address $destination): Factory` - Find best factory

**Routing Criteria:**
- Product availability
- Geographic proximity
- Factory capacity
- Routing rules

### Customer Services

**Location:** `app/Services/Customer/`

#### CustomerResolverService

**Purpose:** Resolves and retrieves customer information.

**Key Methods:**
- `resolveCustomer(Request $request): Customer` - Get authenticated customer from request
- `getCustomerWithRelations(int $customerId): Customer` - Load customer with common relationships

#### DesignBrandingService

**Purpose:** Applies custom branding to design templates.

**Key Methods:**
- `applyBranding(Design $design, array $brandingData): Design` - Apply branding to design
- `uploadBrandingAssets(array $files): array` - Upload logos and images

**Features:**
- Logo placement
- Color customization
- Text modifications
- Image uploads

#### VendorDesignTemplateStoreService

**Purpose:** Manages vendor design templates and store synchronization.

**Key Methods:**
- `pushTemplateToStore(VendorDesignTemplate $template, Store $store): void` - Sync template to connected store
- `listVendorTemplates(int $vendorId): Collection` - Get vendor's templates

**Responsibilities:**
- Template creation and management
- Store synchronization dispatch
- Template status tracking

### Store Connection Services

**Location:** `app/Services/Store/`

#### StoreConnectionService

**Purpose:** Manages connections to external e-commerce platforms.

**Key Methods:**
- `connect(string $platform, array $credentials): VendorConnectedStore` - Connect new store
- `disconnect(VendorConnectedStore $store): void` - Disconnect store
- `validateConnection(VendorConnectedStore $store): bool` - Test store connection

**Supported Platforms:**
- Shopify
- WooCommerce

#### StoreConfigService

**Purpose:** Manages store-specific configuration.

**Key Methods:**
- `getConfig(VendorConnectedStore $store, string $key): mixed` - Get configuration value
- `setConfig(VendorConnectedStore $store, string $key, mixed $value): void` - Set configuration

### Channel Integration Services

**Location:** `app/Services/Channels/`

These services handle integration with external e-commerce platforms.

#### Shopify Services

**Location:** `app/Services/Channels/Shopify/`

##### ShopifyConnector

**Purpose:** Core Shopify API client and connection handler.

**Key Methods:**
- `makeRequest(string $method, string $endpoint, array $data = []): array` - Make API request to Shopify
- `verifyWebhook(Request $request): bool` - Verify webhook authenticity
- `getAuthorizationUrl(string $shop): string` - Get OAuth URL

**Features:**
- OAuth 2.0 authentication
- Rate limit handling
- Webhook verification
- API versioning

##### ShopifyDataService

**Purpose:** Data transformation between Airventory and Shopify formats.

**Key Methods:**
- `transformProductToShopify(Product $product): array` - Convert product to Shopify format
- `transformShopifyOrder(array $shopifyOrder): array` - Parse Shopify order
- `syncVariations(Product $product, Store $store): void` - Sync product variations

**Responsibilities:**
- Data format conversion
- Field mapping
- Image handling
- Metafield management

##### ShopifyFulfillmentService

**Purpose:** Handles fulfillment service registration and callbacks.

**Key Methods:**
- `registerFulfillmentService(Store $store): void` - Register as fulfillment service
- `acceptFulfillmentRequest(array $payload): void` - Accept fulfillment
- `rejectFulfillmentRequest(array $payload, string $reason): void` - Reject fulfillment

##### ShopifyWebhookService

**Purpose:** Manages Shopify webhook registration and processing.

**Key Methods:**
- `registerWebhooks(Store $store): void` - Register required webhooks
- `processWebhook(string $topic, array $payload): void` - Process webhook event

**Webhook Topics:**
- `orders/create` - New order
- `products/update` - Product updated
- `app/uninstalled` - App uninstalled

##### OrderImportService (Shopify)

**Purpose:** Imports orders from Shopify into Airventory.

**Key Methods:**
- `importOrder(array $shopifyOrder, Store $store): SalesOrder` - Import Shopify order
- `syncOrderStatus(SalesOrder $order): void` - Sync order status back to Shopify

**Process:**
1. Parse Shopify order data
2. Map customer information
3. Create cart from order items
4. Convert cart to sales order
5. Record in transaction log

#### WooCommerce Services

**Location:** `app/Services/Channels/WooCommerce/`

##### WooCommerceConnector

**Purpose:** Core WooCommerce API client.

**Key Methods:**
- `makeRequest(string $method, string $endpoint, array $data = []): array` - Make API request
- `validateCredentials(array $credentials): bool` - Validate API credentials
- `testConnection(Store $store): bool` - Test store connection

**Features:**
- REST API authentication
- SSRF protection
- Batch request support
- Error handling

##### WooCommerceDataService

**Purpose:** Data transformation for WooCommerce.

**Key Methods:**
- `transformProductToWooCommerce(Product $product): array` - Convert product format
- `transformWooOrder(array $wooOrder): array` - Parse WooCommerce order
- `batchSyncVariations(Product $product, Store $store, int $batchSize = 50): void` - Sync variations in batches

**Constants:**
- `BATCH_SIZE = 50` - Default batch size for variation sync

**Features:**
- Currency conversion
- Variation batching
- SKU lookup before creation
- Price formatting

##### OrderImportService (WooCommerce)

**Purpose:** Imports orders from WooCommerce.

**Key Methods:**
- `importOrder(array $wooOrder, Store $store): SalesOrder` - Import WooCommerce order
- `mapOrderStatus(string $wooStatus): string` - Map WooCommerce status to internal status

### Order Services

**Location:** `app/Services/Sales/Order/`

#### CartToOrderService

**Purpose:** Converts shopping cart to sales order.

**Key Methods:**
- `convertCartToOrder(Cart $cart, array $paymentInfo): SalesOrder` - Convert cart to order

**Process:**
```
1. Validate cart (items, addresses, totals)
2. Begin database transaction
3. Create SalesOrder record
4. Create SalesOrderItem records
5. Apply discounts
6. Record payment
7. Update inventory
8. Mark cart as converted
9. Commit transaction
10. Dispatch fulfillment jobs
```

**Transaction Safety:** All operations wrapped in `DB::transaction()` with automatic rollback on failure.

#### OrderDiscountService

**Purpose:** Manages order-level discounts.

**Key Methods:**
- `applyOrderDiscount(SalesOrder $order, Discount $discount): void` - Apply discount to order
- `calculateDiscountAmount(SalesOrder $order, Discount $discount): float` - Calculate discount value

**Discount Types:**
- Percentage
- Fixed amount
- Free shipping
- Buy X Get Y

#### OrderPaymentService

**Purpose:** Processes order payments through various gateways.

**Key Methods:**
- `processPayment(SalesOrder $order, array $paymentData): Payment` - Process payment
- `refundPayment(Payment $payment, float $amount): Refund` - Process refund
- `authorizePayment(SalesOrder $order, array $paymentData): Payment` - Authorize (not capture) payment

**Payment Gateways:**
- Stripe (credit/debit cards)
- Wallet (customer wallet balance)

**Transaction Handling:**
```php
DB::transaction(function () use ($order, $paymentData) {
    // Process payment
    // Update order status
    // Record transaction
});
```

### Tax Services

**Location:** `app/Services/Tax/`

#### TaxResolverService

**Purpose:** Calculates taxes based on location and tax rules.

**Key Methods:**
- `calculateTax(Cart $cart): float` - Calculate tax for cart
- `getTaxRate(Address $address): float` - Get tax rate for address
- `getTaxBreakdown(Cart $cart): array` - Get detailed tax breakdown

**Tax Calculation:**
1. Determine tax zone from shipping address
2. Apply applicable tax rules
3. Calculate tax on subtotal minus discounts
4. Consider tax exemptions

#### OrderTaxService

**Purpose:** Handles tax application to orders.

**Key Methods:**
- `applyTaxToOrder(SalesOrder $order): void` - Apply tax to order
- `recalculateTax(SalesOrder $order): void` - Recalculate tax

### Currency Services

**Location:** `app/Services/Currency/`

#### CurrencyConversionService

**Purpose:** Handles currency conversion using exchange rates.

**Key Methods:**
- `convert(float $amount, string $fromCurrency, string $toCurrency): float` - Convert amount
- `getExchangeRate(string $fromCurrency, string $toCurrency): float` - Get current rate
- `formatPrice(float $amount, string $currency): string` - Format price with currency symbol

**Features:**
- Real-time rate retrieval
- Rate caching
- Multi-currency support
- Rounding rules

### Wallet Services

**Location:** `app/Services/Customer/Wallet/`

#### WalletService

**Purpose:** Manages customer wallet balance and transactions.

**Key Methods:**
- `getBalance(int $customerId): float` - Get wallet balance
- `addFunds(int $customerId, float $amount, string $source): WalletTransaction` - Add funds
- `deductFunds(int $customerId, float $amount, string $reason): WalletTransaction` - Deduct funds
- `canAfford(int $customerId, float $amount): bool` - Check if balance sufficient

**Transaction Types:**
- Deposit (payment)
- Withdrawal (order payment)
- Refund
- Adjustment

**Transaction Safety:** All wallet operations use database transactions with row-level locking.

### Payment Gateway Services

**Location:** `app/Services/Customer/Payments/`

#### PaymentGatewayManager

**Purpose:** Manages multiple payment gateways.

**Key Methods:**
- `getGateway(string $name): PaymentGatewayInterface` - Get gateway instance
- `listAvailableGateways(): array` - List enabled gateways

#### StripeGateway

**Purpose:** Stripe payment processing implementation.

**Key Methods:**
- `createPaymentIntent(float $amount, string $currency): PaymentIntent` - Create payment intent
- `confirmPayment(string $paymentIntentId): Payment` - Confirm payment
- `createCustomer(Customer $customer): string` - Create Stripe customer
- `attachPaymentMethod(string $customerId, string $paymentMethodId): void` - Save payment method

#### StripeCustomerService

**Purpose:** Manages Stripe customer records.

**Key Methods:**
- `getOrCreateStripeCustomer(Customer $customer): string` - Get or create Stripe customer ID
- `syncCustomerData(Customer $customer): void` - Sync customer data to Stripe

---

## Jobs/Agents Documentation

Background jobs handle asynchronous tasks like store synchronization and order processing. Jobs are queued using Laravel's queue system (typically Redis-backed) and processed by queue workers.

### Job Configuration

**Common Job Properties:**
- `$tries` - Number of retry attempts (typically 3)
- `$timeout` - Maximum execution time in seconds (60-300)
- `$backoff` - Retry delay in seconds (e.g., [30, 60, 120])
- `$queue` - Queue name (default, high, low priority)

### Shopify Jobs

**Location:** `app/Jobs/Shopify/`

#### SyncShopifyBaseProductJob

**Purpose:** Syncs base product to Shopify store.

**Properties:**
- Tries: 3
- Timeout: 180 seconds
- Backoff: [30, 60, 120]

**Process:**
1. Load product and template data
2. Transform product to Shopify format
3. Create or update product in Shopify via API
4. Save Shopify product ID
5. Dispatch variation sync jobs
6. Update sync status

**Dispatched by:** `VendorDesignTemplateStoreService` when pushing template to Shopify store

**Failure Handling:**
- Logs error with context
- Retries with exponential backoff
- Marks sync as failed after max attempts

#### SyncShopifyVariationBatchJob

**Purpose:** Syncs a batch of product variations to Shopify.

**Properties:**
- Tries: 3
- Timeout: 120 seconds
- Backoff: [30, 60, 120]

**Process:**
1. Receive batch of variation IDs
2. Transform each variation to Shopify format
3. Batch create/update variants via Shopify API
4. Record SKU mappings
5. Update sync status

**Batch Size:** Configurable, default 50 variations per job

**Idempotency:** Uses cache locks to prevent duplicate processing

#### ProcessShopifyOrderJob

**Purpose:** Processes incoming Shopify order webhook.

**Properties:**
- Tries: 3
- Timeout: 120 seconds
- Backoff: [30, 60, 120]

**Process:**
1. Receive Shopify order webhook payload
2. Verify webhook authenticity
3. Check for duplicate processing
4. Transform order data
5. Create customer cart
6. Convert cart to sales order
7. Record order in database
8. Notify relevant parties

**Webhook Topic:** `orders/create`

**Idempotency:** Checks for existing order by Shopify order ID before processing

#### ProcessShopifyProductJob

**Purpose:** Handles Shopify product update webhooks.

**Webhook Topics:**
- `products/update`
- `products/delete`

**Process:**
1. Receive product webhook
2. Find linked internal product
3. Update local data if necessary
4. Sync inventory changes

#### RegisterShopifyWebhooksJob

**Purpose:** Registers required webhooks with Shopify store.

**Properties:**
- Tries: 3
- Timeout: 60 seconds

**Process:**
1. List existing webhooks
2. Compare with required webhooks
3. Create missing webhooks
4. Update webhook URLs if changed
5. Store webhook IDs

**Required Webhooks:**
- `orders/create`
- `products/update`
- `app/uninstalled`

#### RegisterShopifyFulfillmentServiceJob

**Purpose:** Registers Airventory as a fulfillment service in Shopify.

**Process:**
1. Call Shopify fulfillment service API
2. Provide callback URLs
3. Store fulfillment service ID
4. Enable fulfillment service

**Callback URLs:**
- Fulfillment request URL
- Tracking number callback URL

#### FinalizeShopifySyncJob

**Purpose:** Runs after all product sync jobs complete successfully.

**Process:**
1. Verify all variations synced
2. Update store sync status to 'connected'
3. Send completion notification
4. Clean up temporary data

**Triggered by:** Laravel Bus batch completion callback

#### ProcessShopifyUninstallJob

**Purpose:** Handles Shopify app uninstallation.

**Webhook Topic:** `app/uninstalled`

**Process:**
1. Find store by shop domain
2. Mark store as disconnected
3. Cancel pending jobs
4. Archive order data
5. Clean up webhooks

### WooCommerce Jobs

**Location:** `app/Jobs/WooCommerce/`

#### SyncWooBaseProductJob

**Purpose:** Syncs base product to WooCommerce store.

**Properties:**
- Tries: 3
- Timeout: 180 seconds
- Backoff: [30, 60, 120]

**Process:**
1. Load product and template
2. Transform to WooCommerce format
3. Create/update parent product
4. Fetch variation data
5. Chunk variations into batches (default: 50)
6. Dispatch bus batch of `SyncWooVariationBatchJob`
7. Register batch callbacks

**Key Features:**
- Batched processing for large variation sets
- Resilient to individual batch failures
- Comprehensive logging

#### SyncWooVariationBatchJob

**Purpose:** Syncs a batch of variations to WooCommerce.

**Properties:**
- Tries: 3
- Timeout: 120 seconds
- Backoff: [30, 60, 120]

**Process:**
1. Receive variation batch (IDs and data)
2. Check for existing SKUs in WooCommerce
3. Transform variations to WooCommerce format
4. Batch create/update via WooCommerce API
5. Record SKU mappings
6. Update batch status

**Idempotency:**
- Uses cache locks with batch ID as key
- Prevents duplicate processing if job retries

**SKU Lookup:** Checks if SKU exists in WooCommerce before creating to avoid duplicates

#### ProcessWooCommerceOrderJob

**Purpose:** Processes WooCommerce order webhook.

**Properties:**
- Tries: 3
- Timeout: 120 seconds

**Process:**
1. Receive WooCommerce order webhook
2. Validate webhook signature
3. Check for duplicate order
4. Transform order data
5. Create internal sales order
6. Update WooCommerce order status

**Webhook Actions:**
- `order.created`
- `order.updated`

#### ProcessWooCommerceProductJob

**Purpose:** Handles WooCommerce product webhooks.

**Webhook Actions:**
- `product.updated`
- `product.deleted`

**Process:**
1. Receive product webhook
2. Find linked product by SKU
3. Update local inventory
4. Sync price changes if applicable

#### CheckWooCommerceConnectionJob

**Purpose:** Periodically verifies WooCommerce store connection.

**Schedule:** Runs every 6 hours for each connected store

**Process:**
1. Attempt API call to WooCommerce
2. If successful, update last_checked timestamp
3. If failed, increment failure count
4. If failures exceed threshold, mark as disconnected

#### RegisterWooCommerceWebhooksJob

**Purpose:** Registers webhooks with WooCommerce store.

**Process:**
1. Authenticate with WooCommerce
2. List existing webhooks
3. Create missing webhooks
4. Update webhook delivery URLs
5. Store webhook IDs

**Required Webhooks:**
- `order.created`
- `order.updated`
- `product.updated`

#### DeleteWooCommerceWebhooksJob

**Purpose:** Removes webhooks when store disconnected.

**Process:**
1. Fetch registered webhook IDs
2. Delete each webhook via API
3. Clean up local webhook records

#### FinalizeWooSyncJob

**Purpose:** Finalizes WooCommerce sync after all batches complete.

**Process:**
1. Verify all batches succeeded
2. Update store status to 'connected'
3. Log sync completion
4. Notify vendor
5. Clean up cache locks

**Triggered by:** Bus batch `then()` callback after all variation batches complete

### Batch Processing

**SyncBatchCompletion Job:**

**Purpose:** Generic batch completion handler.

**Process:**
1. Check batch status
2. Determine if all jobs succeeded
3. Execute platform-specific finalization
4. Handle partial failures

### Queue Configuration

**Queue Names:**
- `default` - Standard priority jobs
- `high` - Time-sensitive jobs (webhooks, payments)
- `low` - Background maintenance tasks

**Worker Configuration:**
```bash
# Start queue worker with retries and timeout
php artisan queue:work --tries=3 --timeout=120

# Use Laravel Horizon for monitoring (Redis)
php artisan horizon
```

**Horizon Dashboard:**
- URL: `/horizon`
- Features: Job monitoring, failed job retry, throughput metrics

---

## Database Transaction Patterns

Database transactions ensure data consistency when multiple related operations must succeed or fail together.

### Transaction Usage Locations

1. **CartService** - Creating/updating carts with row locking
2. **CartToOrderService** - Converting cart to order
3. **OrderPaymentService** - Processing payments
4. **WalletService** - Wallet balance operations
5. **OrderImportService** - Importing orders from external platforms

### Transaction Pattern

```php
use Illuminate\Support\Facades\DB;

DB::transaction(function () {
    // Multiple database operations
    // If any operation fails, all are rolled back
    $cart = Cart::create([...]);
    $cart->items()->createMany([...]);
    $cart->update(['total' => $total]);
    // Commit happens automatically if no exceptions
});
```

### Row-Level Locking

Used to prevent concurrent modifications:

```php
DB::transaction(function () use ($vendorId) {
    // Lock cart row for update
    $cart = Cart::where('vendor_id', $vendorId)
        ->where('status', 'active')
        ->lockForUpdate()
        ->first();
    
    // Perform operations on locked cart
    $cart->items()->create([...]);
});
```

**When to use:**
- Updating cart totals concurrently
- Processing wallet transactions
- Converting cart to order
- Inventory deduction

### Best Practices

1. **Keep transactions short** - Only include necessary operations
2. **Avoid external API calls** in transactions - They can timeout
3. **Use row locking** when updating shared resources
4. **Handle deadlocks** - Retry on deadlock detection
5. **Log transaction boundaries** - For debugging

---

## Queue Management

### Queue Configuration

**Queue Driver:** Redis (configured in `.env`)

```
QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1
REDIS_PORT=6379
```

### Queue Workers

**Starting Workers:**

```bash
# Basic worker
php artisan queue:work

# With options
php artisan queue:work --tries=3 --timeout=120 --sleep=3

# Specific queue
php artisan queue:work --queue=high,default,low
```

**Worker Options:**
- `--tries=3` - Retry failed jobs 3 times
- `--timeout=120` - Kill jobs running over 120 seconds
- `--sleep=3` - Wait 3 seconds when queue empty
- `--max-jobs=1000` - Process 1000 jobs then restart
- `--max-time=3600` - Run for 1 hour then restart

### Horizon

Laravel Horizon provides advanced queue monitoring.

**Starting Horizon:**
```bash
php artisan horizon
```

**Accessing Dashboard:**
- URL: `http://your-app/horizon`
- Features:
  - Real-time job throughput
  - Job metrics and failures
  - Failed job retry interface
  - Queue workload distribution

**Configuration:** `config/horizon.php`

```php
'environments' => [
    'production' => [
        'supervisor-1' => [
            'connection' => 'redis',
            'queue' => ['high', 'default', 'low'],
            'balance' => 'auto',
            'processes' => 10,
            'tries' => 3,
        ],
    ],
],
```

### Failed Jobs

**Viewing Failed Jobs:**
```bash
php artisan queue:failed
```

**Retrying Failed Jobs:**
```bash
# Retry specific job
php artisan queue:retry {job-id}

# Retry all failed jobs
php artisan queue:retry all
```

**Deleting Failed Jobs:**
```bash
php artisan queue:forget {job-id}
php artisan queue:flush  # Delete all failed jobs
```

### Job Monitoring

**Metrics to Monitor:**
- Queue size (jobs waiting)
- Processing rate (jobs/minute)
- Average job duration
- Failed job count
- Worker memory usage

**Alerting Thresholds:**
- Queue size > 1000 jobs
- Failed jobs > 50/hour
- Worker memory > 128MB

---

## Integration Patterns

### Shopify Integration

**Integration Type:** OAuth 2.0 App

**Flow:**
```
1. Customer initiates connection → GET /api/v1/customer/store/connect
2. Redirect to Shopify OAuth → User approves app
3. Shopify redirects to callback → GET /api/v1/callbacks/shopify
4. Exchange code for access token
5. Store credentials in vendor_connected_stores table
6. Register webhooks → RegisterShopifyWebhooksJob
7. Register fulfillment service → RegisterShopifyFulfillmentServiceJob
8. Store ready for sync
```

**Product Sync Flow:**
```
1. User pushes template to Shopify → POST /api/v1/customer/templates/{id}/push
2. Dispatch SyncShopifyBaseProductJob
3. Create/update product in Shopify
4. Dispatch SyncShopifyVariationBatchJob for variations
5. Process variations in parallel batches
6. Finalize sync → FinalizeShopifySyncJob
7. Update store status to 'connected'
```

**Order Processing:**
```
1. Customer places order on Shopify store
2. Shopify sends webhook → POST /shopify/webhook/orders/create
3. Verify webhook signature
4. Dispatch ProcessShopifyOrderJob
5. Import order into Airventory
6. Create sales order
7. Route to factory for fulfillment
8. Send fulfillment to Shopify when shipped
```

### WooCommerce Integration

**Integration Type:** REST API with API Key/Secret

**Connection Flow:**
```
1. Customer provides store URL + API credentials
2. Validate credentials → WooCommerceConnector::testConnection()
3. Store credentials (encrypted) in vendor_connected_stores
4. Register webhooks → RegisterWooCommerceWebhooksJob
5. Connection complete
```

**Product Sync (Batched):**
```
1. User initiates sync → VendorDesignTemplateStoreService::pushToStore()
2. Dispatch SyncWooBaseProductJob
3. Create parent product in WooCommerce
4. Chunk variations into batches of 50
5. Dispatch batch of SyncWooVariationBatchJob
6. Each batch:
   - Check for existing SKUs
   - Create/update variations
   - Use cache lock for idempotency
7. All batches complete → FinalizeWooSyncJob
8. Update store status
```

**Order Processing:**
```
1. Order placed on WooCommerce store
2. WooCommerce sends webhook → POST /woocommerce/webhook
3. Validate webhook signature
4. Dispatch ProcessWooCommerceOrderJob
5. Transform WooCommerce order data
6. Create internal sales order
7. Update order status in WooCommerce
```

### Integration Best Practices

1. **Rate Limiting**
   - Respect API rate limits (Shopify: 2 req/sec, WooCommerce: varies)
   - Implement exponential backoff on errors
   - Use batch endpoints when available

2. **Idempotency**
   - Use cache locks to prevent duplicate processing
   - Check for existing records by external ID
   - Store sync status to avoid re-processing

3. **Error Handling**
   - Log all API errors with context
   - Retry transient errors (network, rate limit)
   - Don't retry permanent errors (auth, not found)
   - Alert on repeated failures

4. **Webhook Security**
   - Verify webhook signatures
   - Use HTTPS for webhook URLs
   - Implement replay protection
   - Log all webhook receipts

5. **Data Consistency**
   - Use transactions for multi-step operations
   - Implement eventual consistency for syncs
   - Handle partial failures gracefully
   - Provide manual retry mechanisms

---

## Best Practices & Development Guidelines

### Code Organization

1. **Controllers** - Keep thin, delegate to services
   ```php
   // Good
   public function store(Request $request)
   {
       $validated = $request->validate([...]);
       $result = $this->cartService->addItem($validated);
       return $this->successResponse($result);
   }
   
   // Avoid - logic in controller
   public function store(Request $request)
   {
       $cart = Cart::where(...)->first();
       $item = CartItem::create([...]);
       $cart->total += $item->price;
       // ... more logic
   }
   ```

2. **Services** - Single responsibility, focused on one domain
   - One service per major entity (Cart, Order, Payment)
   - Services can call other services
   - No direct HTTP/request handling in services

3. **Models** - Use Eloquent relationships, avoid heavy logic
   ```php
   // Good - relationships
   public function items()
   {
       return $this->hasMany(CartItem::class);
   }
   
   // Avoid - business logic in models
   public function calculateTotal()
   {
       // Complex calculation logic
   }
   ```

4. **Jobs** - Idempotent, retryable, self-contained
   - Check for duplicates before processing
   - Use database transactions
   - Log failures with context
   - Set appropriate timeouts and retries

### Error Handling

1. **API Responses** - Always use ApiResponse trait
   ```php
   return $this->successResponse($data, 'Item added', 201);
   return $this->errorResponse('Invalid item', 400, $errors);
   ```

2. **Validation** - Use Form Requests for complex validation
   ```php
   public function store(AddCartItemRequest $request)
   {
       // Validation already done
   }
   ```

3. **Exceptions** - Let Laravel handle, add to Handler when needed
   - Use try-catch for specific error handling
   - Log unexpected exceptions
   - Return user-friendly messages

### Database

1. **Migrations** - Always reversible with `down()` method
2. **Indexes** - Add indexes for foreign keys and frequently queried columns
3. **Transactions** - Use for multi-step operations
4. **N+1 Queries** - Use eager loading (`with()`) to avoid

### Testing

1. **Unit Tests** - Test services in isolation
   ```php
   public function test_cart_total_calculation()
   {
       $cart = Cart::factory()->create();
       $service = new CartTotalsService();
       $totals = $service->calculateTotals($cart);
       $this->assertEquals(100.00, $totals['total']);
   }
   ```

2. **Feature Tests** - Test API endpoints end-to-end
   ```php
   public function test_can_add_item_to_cart()
   {
       $response = $this->post('/api/v1/customer/cart/items', [
           'product_id' => 1,
           'quantity' => 2,
       ]);
       $response->assertStatus(201);
   }
   ```

3. **Coverage** - Aim for >80% coverage on services and jobs

### Security

1. **Authentication** - Always protect routes with middleware
2. **Authorization** - Use policies for resource access
3. **Input Validation** - Validate all user input
4. **SQL Injection** - Use Eloquent/Query Builder (parameterized)
5. **XSS** - Blade auto-escapes, use `{!! !!}` carefully
6. **CSRF** - Enabled by default for web routes
7. **Rate Limiting** - Apply to API routes
8. **Secrets** - Never commit `.env`, use environment variables

### Performance

1. **Caching** - Cache expensive queries and API calls
2. **Queue Jobs** - Move long-running tasks to background
3. **Pagination** - Always paginate large result sets
4. **Database Optimization** - Use indexes, optimize queries
5. **Image Optimization** - Resize and compress uploaded images

### Documentation

1. **Code Comments** - Explain why, not what
2. **API Documentation** - Keep docs/ folder updated
3. **Changelog** - Document breaking changes
4. **README** - Keep setup instructions current

---

## Appendices

### Common Commands

```bash
# Development
composer install
npm install
php artisan key:generate
php artisan migrate
php artisan db:seed

# Queue Workers
php artisan queue:work
php artisan horizon
php artisan queue:restart

# Testing
php artisan test
php artisan test --filter=CartTest

# Code Quality
./vendor/bin/pint  # Format code
php artisan route:list  # List all routes
php artisan model:show Cart  # Show model details

# Maintenance
php artisan down  # Maintenance mode
php artisan up    # Exit maintenance mode
php artisan cache:clear
php artisan config:clear
php artisan view:clear
```

### Environment Variables

Key variables in `.env`:

```
APP_NAME=Airventory
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.airventory.com

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_DATABASE=airventory
DB_USERNAME=root
DB_PASSWORD=secret

QUEUE_CONNECTION=redis
REDIS_HOST=127.0.0.1

STRIPE_KEY=pk_...
STRIPE_SECRET=sk_...

SHOPIFY_API_KEY=...
SHOPIFY_API_SECRET=...

AWS_BUCKET=airventory-uploads
```

### Related Documentation

- [WooCommerce Sync Architecture](docs/WOOCOMMERCE_SYNC.md)
- [Factory Authentication API](docs/FACTORY_AUTHENTICATION_API.md)
- [Admin JWT Auth](docs/ADMIN_JWT_AUTH.md)
- [Inventory API](docs/INVENTORY_API.md)
- [Wallet Payment Fixes](docs/WALLET_PAYMENT_FIXES_SUMMARY.md)

### Support & Contribution

For questions or contributions, please refer to the project's issue tracker and contribution guidelines.

---

**Document Version:** 1.0  
**Last Updated:** February 2026  
**Maintained By:** Airventory Development Team
