# Inventory API (Assigned Products)

Endpoints for listing, updating, importing, and exporting factory-assigned products. These routes are defined under:
- /api/v1/factories/inventory, /api/v1/factories/inventory/update, /api/v1/factories/inventory/export, /api/v1/factories/inventory/import

## Overview
- Base path: `/api/v1/factories`
- Feature: Assigned Products
- Endpoints:
-  - `GET /inventory` — List assigned products
-  - `POST /inventory/update` — Bulk update price, stock status, quantity
-  - `GET /inventory/export` — Export CSV of assigned products
-  - `POST /inventory/import` — Import CSV to update price/stock/quantity
- Authentication: Factory or Admin token
  - Factory users: context resolved by token
  - Admin users: must pass `factory_id` (GET: query; POST/CSV: body or query)
-  - Context resolution logic: see app/Http/Controllers/Api/V1/Catalog/Inventory/InventoryController.php lines 17–26

## Common Response Envelope
All JSON responses use a consistent envelope:
```json
{
  "success": true,
  "data": {},
  "links": {},
  "meta": {},
  "message": "..."
}
```

---

## 1) List Assigned Products
GET `/api/v1/factories/inventory`

### Authentication
- Factory token OR Admin token
- Admin must include `factory_id` in query

### Query Parameters
| Name | Type | Default | Description |
|------|------|---------|-------------|
| factory_id | integer | — | Admin-only: factory context |
| per_page | integer | 20 | Page size (1–100) |
| sort_by | string | id | One of: `id`, `sku` |
| sort_dir | string | desc | `asc` or `desc` |
| q | string | — | Search by SKU, variant name, or parent name |
| stock_status | integer | — | `1` in-stock, `0` out-of-stock |
| manage_inventory | string | — | `yes/no/1/0/true/false` |
| sku_exact | string | — | Exact match on SKU |
| parent_id | integer | — | Filter by parent product ID |
| option | string | — | Filter by option key or attribute value |
| sale_only | boolean | false | Only items with sale price (factory or base) |
| has_factory_price | boolean | false | Only items with a factory price row |
| in_stock | boolean | — | Alias for stock_status filter (true=1, false=0) |

### Success Response (200)
```json
{
  "success": true,
  "data": [
    {
      "id": 123,
      "name": "Variant Name",
      "sku": "SKU-001",
      "image": "https://cdn.example/image.jpg",
      "manage_inventory": true,
      "quantity": 50,
      "stock_status": 1,
      "regular_price": 1299.0,
      "sale_price": 1199.0,
      "options": "Color / Size"
    }
  ],
  "links": {
    "first": "https://api.example.com/api/v1/factories/inventory?page=1",
    "last": "https://api.example.com/api/v1/factories/inventory?page=5",
    "prev": null,
    "next": "https://api.example.com/api/v1/factories/inventory?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 5,
    "path": "https://api.example.com/api/v1/factories/inventory",
    "per_page": 20,
    "to": 20,
    "total": 100,
    "has_more": true,
    "total_pages": 5,
    "sort_by": "id",
    "sort_dir": "desc",
    "filters": {
      "q": "shirt",
      "stock_status": 1,
      "manage_inventory": "yes",
      "sku_exact": null,
      "parent_id": null,
      "option": "red",
      "sale_only": false,
      "has_factory_price": false,
      "in_stock": true
    }
  },
  "message": "Factory products retrieved successfully."
}
```

### Data Fields
| Field | Type | Notes |
|------|------|-------|
| id | integer | Variant ID |
| name | string | Variant display name |
| sku | string | Exact SKU |
| image | string | URL from parent or variant first image |
| manage_inventory | boolean | From parent product's manage_inventory setting |
| quantity | integer or null | Null when manage_inventory = false |
| stock_status | integer | 1 in-stock, 0 out-of-stock |
| regular_price | number or null | Factory-specific price if set, else base price |
| sale_price | number or null | Factory-specific sale price if set, else base sale price |
| options | string | Joined attribute keys/values (e.g., "Color / Size") |

### Error Responses
- `400` (missing factory context)
```json
{ "success": false, "data": null, "message": "Factory context required" }
```
- `401/403` as applicable from authentication middleware

---

## 2) Bulk Update Assigned Products
POST `/api/v1/factories/inventory/update`

### Authentication
- Factory token OR Admin token
- Admin must include `factory_id` in JSON body or query (controller uses request context)

### Request Body
```json
{
  "items": [
    {
      "id": 123,
      "regular_price": 1299.0,
      "sale_price": 1199.0,
      "quantity": 48,
      "stock_status": "in"
    },
    {
      "sku": "SKU-002",
      "regular_price": 999.0,
      "sale_price": null,
      "quantity": 0,
      "stock_status": 0
    }
  ]
}
```

### Field Rules
- Identify variant via `id` or `sku`
- Editable fields:
  - `regular_price` (number or null)
  - `sale_price` (number or null)
  - `stock_status`:
    - Accepts `in/out` or `1/0`
  - `quantity`:
    - Updated only if the parent product has `manage_inventory = 1`
    - If `manage_inventory = 0`, quantity is ignored, stock_status can still be updated

### Success Response (200)
```json
{
  "success": true,
  "message": "Factory products updated successfully.",
  "data": { "updated": 2 }
}
```

### Validation Errors (422)
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "items.0.id": ["The selected id is invalid."]
  }
}
```

### Other Errors
- `400` (factory context missing)
- `500` (unexpected server error)

---

## 3) Export Assigned Products (CSV)
GET `/api/v1/factories/inventory/export`

### Authentication
- Factory token OR Admin token
- Admin must include `factory_id`

### Filters
All filters supported by List (index) are supported for Export (q, stock_status, manage_inventory, sku_exact, parent_id, option, sale_only, has_factory_price, in_stock).

### Response
- `200` with `text/csv` stream download
- Filename: `factory_products_{factory_id}.csv`
- Columns:
  - `variant_id, sku, name, parent_name, quantity, stock_status, regular_price, sale_price`

### Example Row
```
variant_id,sku,name,parent_name,quantity,stock_status,regular_price,sale_price
123,SKU-001,Variant Name,Parent Name,50,1,1299,1199
```

### Errors
- `400` (factory context missing)

---

## 4) Import Assigned Products (CSV)
POST `/api/v1/factories/inventory/import`

### Authentication
- Factory token OR Admin token
- Admin must include `factory_id`

### Upload
- `multipart/form-data` with `file` field
- CSV header can include:
  - `variant_id` or `sku` (identifier)
  - `regular_price` (number)
  - `sale_price` (number)
  - `quantity` (integer) — applied only if parent `manage_inventory = 1`
-  - `stock_status` — accepted values (case-insensitive):
-    - In-stock: `"in"`, `"instock"`, `"1"`
-    - Out-of-stock: `"out"`, `"outofstock"`, `"0"`
-    - Other/unknown string tokens are coerced by the API via integer casting; non-numeric strings become `0` (out-of-stock). See `stock_status` handling in the controller for exact behavior.
- Accepted MIME types: `text/plain`, `text/csv`, `application/csv`, `application/vnd.ms-excel`

### CSV Template
```
variant_id,sku,regular_price,sale_price,quantity,stock_status
123,SKU-001,1299,1199,50,in
124,SKU-002,,,0,out
```

### Success Response (200)
```json
{
  "success": true,
  "message": "Factory products imported successfully.",
  "data": { "processed": 25 }
}
```

### Errors
- `400` (factory context missing)
- `500` (unexpected server error)

---

## Status Codes
| Code | Description |
|------|-------------|
| 200 OK | Request successful |
| 400 Bad Request | Missing factory context |
| 401 Unauthorized | Invalid or missing authentication token |
| 403 Forbidden | Forbidden — insufficient permissions |
| 422 Unprocessable Entity | Validation error (update) |
| 500 Internal Server Error | Unexpected error |
