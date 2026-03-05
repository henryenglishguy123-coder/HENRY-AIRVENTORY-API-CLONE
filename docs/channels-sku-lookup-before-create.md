# Channels: SKU Lookup Before Create

## Overview
- Goal: prevent duplicate product creation on connected stores by checking for an existing product and its variants (by SKU) before create/link operations.
- Supported channels: Shopify, WooCommerce.
- Endpoint: GET /api/v1/customers/stores/product-lookup

## Why This Matters
- Many stores already have products with variant SKUs. Creating duplicates leads to confusion and broken mappings.
- A pre-create lookup returns normalized product data (title, options, variants with SKUs) so the UI can let users link to existing products or proceed to create.

## Prerequisites
- Customer auth: Bearer token from customer sign-in.
- Store identifier: Use store_id (preferred) or store_identifier (domain/key).
- Product identifier:
  - Shopify: gid://shopify/Product/{id} or numeric ID.
  - WooCommerce: numeric-only product ID (digits only).

## API Contract
- Route name: customer.stores.product-lookup
- Method: GET
- Query params:
  - store_id: number (required if store_identifier not sent)
  - store_identifier: string (optional alternative to store_id)
  - product_id: string (required)

### Request Examples

Shopify (GID):

```http
GET /api/v1/customers/stores/product-lookup?store_id=12&product_id=gid://shopify/Product/123
Authorization: Bearer <token>
```

Shopify (numeric):

```http
GET /api/v1/customers/stores/product-lookup?store_id=12&product_id=123
Authorization: Bearer <token>
```

WooCommerce:

```http
GET /api/v1/customers/stores/product-lookup?store_id=34&product_id=456
Authorization: Bearer <token>
```

### Response Shape (normalized)

Success found:

```json
{
  "success": true,
  "data": {
    "store": {
      "id": 12,
      "channel": "shopify",
      "domain": "shop.example"
    },
    "product": {
      "external_product_id": 123,
      "title": "Sample Product",
      "description": "<p>HTML description</p>",
      "primary_image": { "id": 9, "src": "https://image.example/9.jpg" },
      "options": [
        { "name": "Size", "values": ["S", "M"] }
      ],
      "variants": [
        {
          "id": 1,
          "title": "Default Title",
          "sku": "SKU-1",
          "options": { "Size": "S" }
        }
      ]
    }
  }
}
```

Not found (valid request, product missing):

```json
{
  "success": false,
  "message": "Product not found for this store.",
  "data": null
}
```

Ownership violation (looking up another customer’s store):

```json
{
  "message": "Resource not found."
}
```
Status: 404

Duplicate linking detected (already linked to a template/store):

```json
{
  "success": false,
  "data": {
    "store_override_id": 987,
    "vendor_design_template_id": 654
  }
}
```
Status: 409

## Channel-Specific Notes
- Shopify:
  - product_id accepts either GID or numeric; normalization returns numeric external_product_id.
  - Variants include Shopify variant IDs and SKUs for mapping.
- WooCommerce:
  - product_id must be digits only; invalid IDs short-circuit and return not found.
  - Variants are fetched separately and returned with SKUs and attribute option values.
- Unknown channels:
  - The API rejects unsupported channels to avoid silent mis-normalization.

## UI Guidance
- Before creating/linking:
  - Collect product_id from the store UI (Shopify product link or WooCommerce product edit screen).
  - Call product-lookup to retrieve normalized data and variant SKUs.
  - If success is true, present variants and allow linking to existing product.
  - If success is false, offer to proceed with create (or re-check inputs).
- Show clear messaging for:
  - Not found: suggest verifying the product ID format (WooCommerce must be numeric).
  - Duplicate linking: display existing linkage and let users navigate to edit or unlink.

## Error Handling Summary
- 200 + success=false: valid call but product missing.
- 404: store ownership violation or store not found.
- 409: duplicate link detected.
