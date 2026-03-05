# Customer Store Product Link API

## Overview

Link an existing product from a connected store (Shopify or WooCommerce) to an internal design template without changing the store’s content (images, description, title). This enables order and fulfillment tracking for that product and its variants.

## Base Path

All endpoints in this document are under:
- `/api/v1/customers/stores`

## Endpoint

### Link Existing Store Product

**Method:** `POST`  
**Path:** `/api/v1/customers/stores/link-existing-product`  
**Auth:** Required (Customer or Admin token)  

#### Purpose
- Associates an external product with a customer’s design template.
- Verifies the product exists in the store.
- Verifies all provided variant IDs exist under that product.
- Does not modify any content in the external store.
- Records SKUs from the source store for internal use:
  - Shopify: saves variant-level SKUs for matched variants.
  - WooCommerce: saves product-level SKU and variant-level SKUs for matched variants.

---

## Request

### Headers
```
Authorization: Bearer {token}
Accept: application/json
Content-Type: application/json
```

### Body
| Field | Type | Required | Description |
|------|------|----------|-------------|
| store_id | integer | yes | Connected store ID owned by the customer |
| external_product_id | string | yes | External product ID; Shopify gid format allowed (`gid://shopify/Product/{id}`) |
| product_id | integer | yes | Internal catalog product ID to link to |
| variants | array<object> | yes | List of variant mappings |
| variants[].product_variant_id | integer | yes | Internal catalog variant ID |
| variants[].external_variant_id | string | yes | External variant ID (Shopify variant ID or WooCommerce variation ID) |
| name | string | no | Optional override name (stored internally; does not change external product) |
| description | string | no | Optional override description (stored internally; does not change external product) |
| sync_images | array | no | Optional image metadata (stored internally; does not change external product) |

---

## Validation and Verification

1. Authenticated customer must own `store_id`.  
2. `product_id` must exist in internal catalog.  
3. The external product must exist in the store:
   - Shopify: product fetched via REST Admin API using numeric ID; `gid://shopify/Product/{id}` is normalized to `{id}` automatically.
   - WooCommerce: product fetched via REST API using numeric ID.
4. All provided `external_variant_id` values must exist within the external product:
   - Shopify: verified against `product.variants[].id`
   - WooCommerce: verified against `GET /products/{id}/variations` IDs
5. If any provided variant IDs are missing, the endpoint returns 422 with a list of `missing_variant_ids`.
6. If the product is already linked for this store, returns 409 with existing linkage info.
7. The internal design template must exist for `product_id` under the customer; otherwise returns 412.
8. Duplicate variant IDs in the request are ignored (deduplicated before verification).

---

## Behavior

- Normalization: Shopify `external_product_id` accepts either `gid://shopify/Product/{id}` or `{id}`; it is normalized to `{id}`.
- Verification: Fetches the external product and validates that all provided `external_variant_id` values exist under that product.
- Persistence:
  - Creates or updates the store override with provided name/description/sync_images (internal only).
  - Sets internal `external_product_id` on the store override.
  - Sets internal `sync_status` to `synced` after a successful link.
  - SKU handling:
    - Shopify: updates each linked store-override variant’s `sku` from source `product.variants[].sku` when present.
    - WooCommerce: updates store-override `sku` from source product’s `sku` when present; also updates each linked variant’s `sku` from variation `sku`.
    - Empty/missing SKUs are skipped; SKUs are truncated to 191 characters if longer.
- No external changes: The endpoint does not modify titles, descriptions, or images in the external store.

---

## Responses

### Success (200 OK)
```json
{
  "success": true,
  "message": "Product linked successfully.",
  "data": {
    "store_override_id": 1234,
    "external_product_id": "8057357500527",
    "variant_count": 3
  }
}
```

### Errors
| Status | Condition | Example Body |
|--------|-----------|--------------|
| 401 | Unauthenticated | `{ "success": false, "message": "Unauthenticated." }` |
| 404 | Store not found / not owned | `{ "success": false, "message": "Store not found." }` |
| 409 | Product already linked | `{ "success": false, "message": "Product already linked for this store.", "data": { "store_override_id": 12, "vendor_design_template_id": 34 } }` |
| 412 | No design template for product | `{ "success": false, "message": "No design template found for the selected product. Please create or select a design template first." }` |
| 422 | Product or variants invalid | `{ "success": false, "message": "One or more provided variants do not exist in the store product.", "data": { "missing_variant_ids": ["12345", "67890"] } }` |
| 502 | Store connector failed | `{ "success": false, "message": "Failed to verify product from store. Please check your connection and try again." }` |
| 500 | Internal error | `{ "success": false, "message": "Failed to link product. Please try again." }` |

---

## Shopify Notes

- `external_product_id` may be provided as `gid://shopify/Product/{id}` or `{id}`. The API normalizes to the numeric `{id}`.
- Variant verification uses `product.variants[].id` returned by Shopify.
- SKU: Shopify products typically have SKUs on variants only. The endpoint records variant SKUs for linked variants when present; product-level SKU is not set for Shopify.

## WooCommerce Notes

- `external_product_id` must be a numeric WooCommerce product ID.
- Variant verification uses the `GET /products/{id}/variations` collection and checks variation `id` values.
- SKU: WooCommerce products may have both product-level and variation-level SKUs. The endpoint records product-level SKU on the store override and variation-level SKUs on linked variants when present.

---

## Examples

### Shopify (cURL)
```bash
curl -X POST https://api.example.com/api/v1/customers/stores/link-existing-product \
  -H "Authorization: Bearer <token>" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "store_id": 110,
    "external_product_id": "gid://shopify/Product/8057357500527",
    "product_id": 14,
    "name": "Temp",
    "description": "<p>...</p>",
    "sync_images": [],
    "variants": [
      { "product_variant_id": 15, "external_variant_id": "44523190059119" }
    ]
  }'
```

### WooCommerce (cURL)
```bash
curl -X POST https://api.example.com/api/v1/customers/stores/link-existing-product \
  -H "Authorization: Bearer <token>" \
  -H "Accept: application/json" \
  -H "Content-Type: application/json" \
  -d '{
    "store_id": 210,
    "external_product_id": "12345",
    "product_id": 14,
    "variants": [
      { "product_variant_id": 15, "external_variant_id": "67890" }
    ]
  }'
```

### JavaScript (Fetch)
```javascript
async function linkExistingProduct(token, payload) {
  const res = await fetch('https://api.example.com/api/v1/customers/stores/link-existing-product', {
    method: 'POST',
    headers: {
      'Authorization': `Bearer ${token}`,
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify(payload)
  });
  const data = await res.json();
  if (!res.ok || data.success === false) {
    console.error('Linking failed:', data);
    return null;
  }
  return data.data;
}
```

---

## Implementation Reference

- Controller: `App\Http\Controllers\Api\V1\Customer\Store\StoreProductLinkController@linkExistingProduct`  
  app/Http/Controllers/Api/V1/Customer/Store/StoreProductLinkController.php
- Route: `POST /api/v1/customers/stores/link-existing-product`  
  routes/api.php
