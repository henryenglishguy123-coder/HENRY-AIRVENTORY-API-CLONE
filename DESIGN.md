# Cart Template Items Feature – Design and Implementation (feat/cart-template-items)

**Branch:** `feat/cart-template-items`  
**Base branch for comparison:** `dev`  
**Commit:** `5a7ec5f80c7535719b06acfc8f29b5a16cd5b977`  
**Application:** Laravel 12 API backend for vendor-driven e‑commerce / production

---

## 1. Branch Overview

This branch introduces and refines the **“cart template items”** feature, allowing a vendor (customer) to add a product that is configured from a saved **design template** into their cart, with correct inventory routing and pricing.

Core responsibilities:
- Validate and authorize requests to add template-based items to a cart.
- Resolve the correct **product variant** based on selected attribute options.
- Ensure the selected variant is **in stock** in at least one factory.
- Resolve a **unit price** that includes pricing margins and factory-specific prices.
- Persist cart items, options, and totals in a consistent domain model.
- Expose the cart state through a dedicated API resource.

High-level flow when adding a template item:

```text
Client
  │
  │ POST /api/v1/customer/cart/items/template
  │  (AddTemplateItemRequest)
  ▼
CartItemController@addTemplateItem
  ▼
AddTemplateToCartAction::execute
  ├─ CartService::getActiveCart
  ├─ CatalogProduct / VendorDesignTemplate lookups
  ├─ CatalogAttributeOption validation
  ├─ CartVariantResolver::resolve
  ├─ InventoryService / factoriesWithStock()
  ├─ CartPricingService::resolveUnitPrice
  ├─ CartPricingService::getFulfillmentFactoryId
  ├─ CartItem create / update (+ options)
  └─ CartTotalsService::recalculate
        └─ CartTotal model
  ▼
CartResource (response payload)
```

---

## 2. Architecture and Component Interactions

### 2.1 Main Components

- **HTTP Layer**
  - `CartItemController`
  - `AddTemplateItemRequest`
  - `CartResource`

- **Domain Services (Cart)**
  - `AddTemplateToCartAction`
  - `CartService`
  - `CartVariantResolver`
  - `CartPricingService`
  - `PrintingCostService`
  - `InventoryService`
  - `CartTotalsService`

- **Models**
  - `Cart`, `CartItem`, `CartItemOption`, `CartTotal`, `CartSource`, `CartAddress`
  - `VendorDesignTemplate`
  - `CatalogProduct`, `CatalogProductInventory`, `CatalogProductPriceWithMargin`

These components are wired together using standard Laravel dependency injection. Controllers depend on **actions/services**, not directly on models wherever possible.

### 2.2 Data Flow – Add Template Item

1. **Request validation and authorization**
   - `AddTemplateItemRequest` validates the payload.
   - `CartItemController` checks that the vendor **does not** add their own template.

2. **Variant resolution**
   - `AddTemplateToCartAction` loads the base product and template.
   - Validates that selected attribute options exist and match exactly one child variant.

3. **Inventory and pricing**
   - `InventoryService` and `CatalogProduct::factoriesWithStock()` ensure stock exists.
   - `PrintingCostService::calculatePrintingCost()` computes printing costs for the chosen factory.
   - `CartPricingService::resolveUnitPrice()` combines base product price, printing cost (plus markup), and factory selection into a final unit price.

4. **Cart persistence**
   - `CartService` provides the active cart (per vendor).
   - `CartItem` / `CartItemOption` rows are created or updated.

5. **Totals and response**
   - `CartTotalsService` recalculates monetary totals.
   - `CartResource` serializes the cart for the API response.

---

## 3. File-by-File Documentation

### 3.1 HTTP Layer

#### 3.1.1 CartItemController
**Path:** `app/Http/Controllers/Api/V1/Customer/Cart/CartItemController.php`

**Purpose / Responsibilities**
- Handle the API endpoint that adds a template-based item to the customer’s cart.
- Enforce that a vendor **cannot add their own template**.
- Delegate business logic to `AddTemplateToCartAction`.

**Key Method**

- `addTemplateItem(AddTemplateItemRequest $request, AddTemplateToCartAction $action): JsonResponse`
  - **Input:** Validated request containing `template_id`, `product_id`, `selected_options`, `qty`.
  - **Authorization rule:**  
    - Loads `VendorDesignTemplate` by `template_id`.
    - If `template->vendor_id === $request->user()->id`, returns HTTP 403 with message:
      - `"You cannot add your own template to the cart."`
  - **When authorized:**
    - Calls `$action->execute($request->user(), $request->validated())`.
    - Wraps result in `CartResource` and responds with HTTP 200.

**Example Usage**

Request:

```http
POST /api/v1/customers/cart/items/template
Authorization: Bearer <JWT>
Content-Type: application/json

{
  "template_id": 10,
  "product_id": 123,
  "selected_options": [101, 202],
  "qty": 3
}
```

Response (success):

```json
{
  "success": true,
  "message": "Item added to cart successfully.",
  "data": {
    "id": 1,
    "items": [ /* cart items */ ],
    "totals": { /* monetary totals */ },
    "errors": [],
    "address": null
  }
}
```

---

#### 3.1.2 AddTemplateItemRequest
**Path:** `app/Http/Requests/Api/V1/Customer/Cart/AddTemplateItemRequest.php`

**Purpose / Responsibilities**
- Validate the request body for the “add template item to cart” endpoint.
- Guarantee that downstream logic receives normalized, safe data.

**Validation Rules**

- `template_id`
  - `required`, `integer`, `exists:vendor_design_templates,id`
- `product_id`
  - `required`, `integer`, `exists:catalog_products,id`
- `selected_options`
  - `required`, `array`, `min:1`
- `selected_options.*`
  - `required`, `integer`, `exists:catalog_attribute_options,option_id`
- `qty`
  - `required`, `integer`, `min:1`

**Input / Output**
- **Input:** Raw HTTP request JSON.
- **Output:** `$request->validated()` returns a clean array with the above fields correctly typed (IDs as ints, qty as positive int).

---

#### 3.1.3 CartResource
**Path:** `app/Http/Resources/Api/V1/Customer/CartResource.php`

**Purpose / Responsibilities**
- Present a structured JSON representation of a `Cart` instance for API responses.

**Structure**

- Top-level keys:
  - `id` – cart ID.
  - `items` – collection of cart items (see below).
  - `totals` – associated `CartTotal` model.
  - `errors` – cart errors, if any.
  - `address` – shipping/billing cart address if present.

- Each item in `items`:
  - `id`
  - `product_title`
  - `sku`
  - `qty`
  - `unit_price`
  - `line_total`
  - `options` – related `CartItemOption` collection.
  - `fulfillment_factory`
    - `company_name` – comes from the factory business relation if available.

**Example Snippet**

```json
{
  "id": 1,
  "items": [
    {
      "id": 5,
      "product_title": "Custom T‑Shirt – Blue / L",
      "sku": "TSHIRT-BLUE-L",
      "qty": 3,
      "unit_price": "19.9900",
      "line_total": "59.9700",
      "options": [ /* attribute options */ ],
      "fulfillment_factory": {
        "company_name": "Acme Factory"
      }
    }
  ],
  "totals": {
    "subtotal": "59.9700",
    "tax_total": "0.0000",
    "discount_total": "0.0000",
    "shipping_total": "0.0000",
    "grand_total": "59.9700",
    "calculated_at": "2025-01-17T12:00:00Z"
  },
  "errors": [],
  "address": null
}
```

---

### 3.2 Cart Services

#### 3.2.1 AddTemplateToCartAction
**Path:** `app/Services/Customer/Cart/Actions/AddTemplateToCartAction.php`

**Purpose / Responsibilities**
- Orchestrate the full “add template item to cart” workflow in a single DB transaction:
  - Load dependencies (cart, product, template, options).
  - Validate variant and inventory.
  - Resolve pricing.
  - Create or update cart item and options.
  - Recalculate cart totals.

**Constructor Dependencies**
- `CartService $cartService`
- `CartVariantResolver $variantResolver`
- `CartPricingService $pricingService`
- `CartTotalsService $totalsService`

**Public Method**

- `execute($customer, array $data)`
  - **Input:**
    - `$customer` – authenticated vendor user (must at least have `id`).
    - `$data` – validated request data (`template_id`, `product_id`, `selected_options`, `qty`).
  - **Key Steps:**
    1. **Qty normalization**
       - Casts `$data['qty']` to int.
       - Throws `ValidationException` if qty `< 1`.
    2. **Cart and entities**
       - `CartService::getActiveCart($customer->id)` – ensures there is an active cart.
       - Loads `VendorDesignTemplate` and `CatalogProduct` via `findOrFail`.
    3. **Option validation**
       - Normalizes `selected_options` to unique sorted integers.
       - Loads `CatalogAttributeOption` records and verifies counts match.
    4. **Variant resolution**
       - `CartVariantResolver::resolve($product, $selectedOptions)` returns child variant.
       - Throws `ValidationException` if no matching variant exists.
    5. **Inventory check**
       - Uses `$variant->factoriesWithStock()` to ensure at least one factory has stock.
       - Throws `ValidationException` if no factories in stock.
    6. **Pricing**
       - `CartPricingService::resolveUnitPrice($variant, $template)` yields a positive float.
       - Any pricing-related failure becomes a user-facing `ValidationException`.
       - `CartPricingService::getFulfillmentFactoryId()` provides the chosen factory ID.
    7. **Cart item persistence**
       - If a matching `CartItem` exists (same cart, product, variant, template):
         - `increment('qty', $data['qty'])`.
         - Update `line_total = unit_price * qty` and `fulfillment_factory_id`.
       - Else:
         - Creates a new `CartItem` with:
           - `qty`, `unit_price`, `sku`, `product_title`, `line_total`.
         - Adds related `CartItemOption` rows from validated options.
    8. **Totals**
       - `CartTotalsService::recalculate($cart)` recomputes monetary totals.
    9. **Return value**
       - Returns refreshed cart with relevant relations loaded:
         - `items.options`
         - `items.fulfillmentFactory` and associated business
         - `totals`, `errors`, `address`

**Output**
- Returns a fully hydrated `Cart` model instance ready for serialization by `CartResource`.

---

#### 3.2.2 CartService
**Path:** `app/Services/Customer/Cart/CartService.php`

**Purpose / Responsibilities**
- Provide access to the active cart for a given vendor.

**Method**
- `getActiveCart(int $vendorId): Cart`
  - Finds or creates a cart with:
    - `vendor_id = $vendorId`
    - `status = 'active'`
  - Used by `AddTemplateToCartAction`.

---

#### 3.2.3 CartVariantResolver
**Path:** `app/Services/Customer/Cart/CartVariantResolver.php`

**Purpose / Responsibilities**
- Resolve a concrete **child variant** (`CatalogProduct`) given a parent product and a set of selected attribute options.

**Method**

- `resolve(CatalogProduct $parent, Collection $selectedOptions): ?CatalogProduct`
  - **Normalization:**
    - `$selectedOptions` is mapped to integers, sorted, and `.values()` is called to normalize key order and types.
  - **Query:**
    - Filters `children` that have attributes with `attribute_value` in the normalized options, and with a count equal to the number of options.
  - **Post-filtering:**
    - For each candidate child, builds `$childOptions` by:
      - Plucking `attribute_value`, casting to int, sorting, `values()`.
    - Returns the first child where:
      - `count($childOptions) === count($normalizedSelectedOptions)` and
      - `$childOptions->all() === $normalizedSelectedOptions->all()`.
  - Returns `null` if no exact match is found.

**Input / Output**
- **Input:** Parent product with configured attributes and a collection of option IDs (possibly strings).
- **Output:** Child `CatalogProduct` or `null`.

---

#### 3.2.4 CartPricingService
**Path:** `app/Services/Customer/Cart/CartPricingService.php`

**Purpose / Responsibilities**
- Resolve a **unit price** for a given variant and design template as an additive composition:
  - Base product price with margin (regular + sale).
  - Additional printing cost (computed by `PrintingCostService`, with global markup applied).
  - Factory-specific pricing and inventory routing.

**Dependencies**
- `InventoryService $inventoryService`

**Key Methods**

- `resolveUnitPrice(CatalogProduct $variant, VendorDesignTemplate $template): float`
  - Loads `$prices = $variant->pricesWithMargin()->get()`.
  - If `$prices` is empty:
    - Throws a `ValidationException` with message:
      - `"Pricing for the selected product is not available."`
  - Determines `$factoryId` via `InventoryService::findFactoryWithStock($variant, $template)`.
  - Calls `getBaseProductPrice($prices, $factoryId)` to get a strictly positive base price:
    - Prefers a factory-specific price when available and > 0.
    - Falls back to the lowest positive price across all prices.
    - Throws a `ValidationException` if no positive price can be found.
  - Calls `PrintingCostService::calculatePrintingCost($variant, $template, $factoryId)` when a factory is present:
    - Returns `0.0` when the parent product has no printing price configuration at all.
    - Throws a `ValidationException` if printing prices exist but the computed total is `<= 0` (treated as misconfiguration).
  - Applies a global markup to the printing cost via `applyGlobalMarkup`.
  - Returns:
    - `unit_price = base_product_price + printing_cost_with_markup`.

- `getFulfillmentFactoryId(CatalogProduct $variant, VendorDesignTemplate $template): ?int`
  - Delegates to `InventoryService::findFactoryWithStock`.

- `getFactoriesWithStock(CatalogProduct $product): Collection`
  - Uses `InventoryService::getFactoryStockInfo($product)` and filters entries marked `in_stock`.

**Input / Output**
- **Input:** Variant product, related template.
- **Output:** Positive float for unit price; validation error on misconfigured pricing.

---

#### 3.2.5 PrintingCostService
**Path:** `app/Services/Customer/Cart/PrintingCostService.php`

**Purpose / Responsibilities**
- Calculate the additional **printing cost** for a template-based product, based on:
  - The parent product’s configured printing prices.
  - The template’s layer and technique assignments.
  - A specific factory where the product will be fulfilled.

**Method**

- `calculatePrintingCost(CatalogProduct $variant, VendorDesignTemplate $template, int $factoryId): float`
  - Retrieves printing prices from the parent product:
    - Resolves `$parent = $variant->parent`; if no parent exists, returns `0.0` early (no printing configuration).
    - If the parent exists but `printingPrices()->get()` returns an empty collection, also returns `0.0` early (no printing configuration).
  - Resolves layer data from the template:
    - For each layer, collects `layer_id`, `printing_technique_id`, and the provided `factory_id`.
  - Indexes printing prices by composite key:
    - `"{$layer_id}_{$technique_id}_{$factory_id}"`.
  - Sums the price for all relevant layer/technique/factory combinations.
  - If the resulting total is `<= 0`, throws a `ValidationException` on `product_id` indicating that printing pricing is not available.
    - Rationale: **missing configuration** (no parent or no printing prices) is treated as “no printing cost” and returns `0.0`, while **configured but invalid totals** (prices present but summing to `<= 0`) are treated as misconfiguration and surface an error.
  - Otherwise returns the total printing cost as a float.

**Input / Output**
- **Input:** Variant product, template, and chosen factory ID.
- **Output:** Non-negative float; 0.0 when the system has no printing prices, or a validation error when configured prices still result in a zero/negative total.

---

#### 3.2.5 InventoryService
**Path:** `app/Services/Customer/Cart/InventoryService.php`

**Purpose / Responsibilities**
- Provide helper methods around product inventory and factories.

**Key Methods**
- `findFactoryWithStock(CatalogProduct $variant, ?VendorDesignTemplate $template = null): ?int`
  - Finds factories with stock for the given variant.
  - Optionally prefers `template->factory_id` if it has stock.
  - Returns `factory_id` or `null`.

- `hasStockInFactory(CatalogProduct $variant, int $factoryId): bool`
  - Returns true if the given factory has quantity > 0 and `stock_status` indicates in stock.

- `getFactoryStockInfo(CatalogProduct $product): Collection`
  - Returns a collection of structures:
    - `factory_id`, `factory_name`, `quantity`, `stock_status` (localized label), `in_stock` (bool).

---

#### 3.2.6 CartTotalsService
**Path:** `app/Services/Customer/Cart/CartTotalsService.php`

**Purpose / Responsibilities**
- Compute and persist cart monetary totals.

**Method**
- `recalculate(Cart $cart): void`
  - Computes:
    - `subtotal` – sum of `items->line_total`.
    - `tax_total` – sum of `items->tax_amount`.
    - `discount_total` – currently `0`.
    - `shipping_total` – currently `0`.
    - `grand_total = subtotal + tax_total + shipping_total − discount_total`.
  - Upserts a `CartTotal` row for the cart with `calculated_at = now()`.

---

### 3.3 Cart Models

#### 3.3.1 Cart
**Path:** `app/Models/Customer/Cart/Cart.php`

**Purpose / Responsibilities**
- Represent a customer cart (per vendor).

**Key Fields**
- `vendor_id`
- `status` – e.g. `'active'`.

**Relationships**
- `items(): HasMany<CartItem>`
- `sources(): HasMany<CartSource>`
- `errors(): HasMany<CartError>`
- `totals(): HasOne<CartTotal>`
- `address()` – `HasOne<CartAddress>`

---

#### 3.3.2 CartItem
**Path:** `app/Models/Customer/Cart/CartItem.php`

**Purpose / Responsibilities**
- Represent an individual line item in the cart, including variant and template information.

**Fillable attributes**
- `cart_id`
- `product_id`
- `variant_id`
- `template_id`
- `fulfillment_factory_id`
- `sku`
- `product_title`
- `qty`
- `unit_price`
- `line_total`
- `tax_rate`
- `tax_amount`

**Casts**
- `qty` – `integer`
- `unit_price` – `decimal:4`
- `line_total` – `decimal:4`
- `tax_rate` – `decimal:4`
- `tax_amount` – `decimal:4`

**Relationships**
- `cart(): BelongsTo<Cart>`
- `product(): BelongsTo<CatalogProduct>`
- `variant(): BelongsTo<CatalogProduct>`
- `options(): HasMany<CartItemOption>`
- `fulfillmentFactory(): BelongsTo<Factory>`

---

#### 3.3.3 CartTotal
**Path:** `app/Models/Customer/Cart/CartTotal.php`

**Purpose / Responsibilities**
- Persist aggregate monetary totals per cart.

**Key Fields / Casts**
- Primary key: `cart_id`
- `subtotal`, `tax_total`, `discount_total`, `shipping_total`, `grand_total` – `decimal:4`.
- `calculated_at` – `datetime`.

**Relationship**
- `cart(): BelongsTo<Cart>`

---

#### 3.3.4 CartSource
**Path:** `app/Models/Customer/Cart/CartSource.php`

**Purpose / Responsibilities**
- Track the origin of a cart (platform, order number, external payload).

**Fields**
- `cart_id`
- `platform`
- `source_order_id`
- `source_order_number`
- `payload` (JSON; cast to `array`).

---

### 3.4 Catalog / Inventory Models

#### 3.4.1 CatalogProductInventory
**Path:** `app/Models/Catalog/Product/CatalogProductInventory.php`

**Purpose / Responsibilities**
- Represent per-factory inventory for a product.

**Key Fields**
- `product_id`
- `factory_id`
- `manage_inventory`
- `quantity`
- `min_quantity`
- `stock_status`

**Casts**
- `stock_status` – `integer` (ensures consistent comparison).

**Relationship**
- `factory(): BelongsTo<Factory>`

---

#### 3.4.2 CatalogProduct
**Path:** `app/Models/Catalog/Product/CatalogProduct.php`

**Relevant Methods for This Feature**

- `inventories()`
  - `HasMany<CatalogProductInventory>` for the product.

- `factoriesWithStock()`
  - Uses `inventories()` to retrieve factories where:
    - `quantity > 0`
    - `stock_status == 1`
  - Returns a collection of `Factory` models.

- `factoryInventory()`
  - Builds a per-factory inventory summary:
    - `factory` – factory model
    - `quantity`
    - `stock_status` – localized label `'In Stock'` vs `'Out of Stock'`, using **strict** `=== 1` comparison.
    - `in_stock` – boolean:
      - `quantity > 0 && stock_status === 1`
  - Uses the `stock_status` integer cast from `CatalogProductInventory` to avoid mismatches.

---

#### 3.4.3 CatalogProductPriceWithMargin
**Path:** `app/Models/Catalog/Product/CatalogProductPriceWithMargin.php`

**Purpose / Responsibilities**
- Represent product prices with margin calculations applied.

**Relevant Behavior**
- Accessors compute:
  - `regular_price` with markup.
  - `sale_price` with markup.
- `getEffectiveBasePrice()` and `getEffectivePriceAttribute()` help derive a single effective price; this is conceptually aligned with `CartPricingService`’s logic for choosing sale vs regular prices.

---

#### 3.4.4 VendorDesignTemplate
**Path:** `app/Models/Customer/Designer/VendorDesignTemplate.php`

**Role in Feature**
- Source of `template_id` used in `AddTemplateItemRequest`.
- Provides `vendor_id` used for **authorization** in `CartItemController`.
- May include relationships to printing or factory preferences (consumed by `InventoryService` when present).

---

## 4. API Specification (Cart Template Item)

### Endpoint
- `POST /api/v1/customers/cart/items/template`

### Authentication
- Requires authenticated **vendor** via JWT; implemented by the broader customer auth system (outside this branch).

### Request Body

```json
{
  "template_id": 10,
  "product_id": 123,
  "selected_options": [101, 202],
  "qty": 3
}
```

### Validation Errors
- `422 Unprocessable Entity` on:
  - Invalid or missing `template_id`, `product_id`, `selected_options`, `qty`.
  - Nonexistent option IDs.
  - Nonexistent matching variant.
  - Out-of-stock variants across all factories.
  - Missing or invalid pricing.

### Authorization Errors
- `403 Forbidden` when:
  - Vendor tries to add **their own** template:
    - `"You cannot add your own template to the cart."`

### Successful Response
- `200 OK` with:
  - `success: true`
  - `message: "Item added to cart successfully."`
  - `data`: `CartResource` payload as described above.

### Inventory behavior and reservations
- The current implementation performs **availability checks only** at add-to-cart time:
  - Uses `factoriesWithStock()` and `InventoryService::findFactoryWithStock()` to confirm at least one factory has stock.
  - It does **not** create any reservation or lock against that stock.
- At checkout time (outside the scope of this branch), callers must:
  - Re-validate stock for each item before committing an order.
  - Decide how to handle conflicts when stock has been consumed by other checkouts:
    - Recommended behavior: fail the checkout with a `409 Conflict` or `422 Unprocessable Entity` and return a clear error per line item.
    - Partial fulfillments are not handled by this feature; order creation should be atomic per order.
- This implies potential race conditions under high concurrency:
  - Cart operations may succeed while subsequent checkout attempts fail due to insufficient stock.
  - See the limitation **“No partial stock reservations”** in section 7 and the **Inventory reservations (near-term requirement)** item in section 8 for planned mitigation.

---
## 5. Versioning and Cross-References

- **Branch:** `feat/cart-template-items`
- **Base branch:** `dev`
- **Key Files:**
  - `app/Http/Controllers/Api/V1/Customer/Cart/CartItemController.php`
  - `app/Http/Requests/Api/V1/Customer/Cart/AddTemplateItemRequest.php`
  - `app/Http/Resources/Api/V1/Customer/CartResource.php`
  - `app/Services/Customer/Cart/Actions/AddTemplateToCartAction.php`
  - `app/Services/Customer/Cart/CartService.php`
  - `app/Services/Customer/Cart/CartVariantResolver.php`
  - `app/Services/Customer/Cart/CartPricingService.php`
  - `app/Services/Customer/Cart/PrintingCostService.php`
  - `app/Services/Customer/Cart/InventoryService.php`
  - `app/Services/Customer/Cart/CartTotalsService.php`
  - `app/Models/Customer/Cart/Cart.php`
  - `app/Models/Customer/Cart/CartItem.php`
  - `app/Models/Customer/Cart/CartTotal.php`
  - `app/Models/Customer/Cart/CartSource.php`
  - `database/migrations/2026_01_13_130536_update_cart_items_unique_key.php`
  - `database/migrations/2026_01_17_064252_update_cart_items_add_fulfillment_factory_id_column.php`
  - `app/Models/Catalog/Product/CatalogProduct.php`
  - `app/Models/Catalog/Product/CatalogProductInventory.php`
  - `app/Models/Catalog/Product/CatalogProductPriceWithMargin.php`
  - `app/Models/Customer/Designer/VendorDesignTemplate.php`

This document should be kept in sync with future changes to these files as the cart template items feature evolves.
