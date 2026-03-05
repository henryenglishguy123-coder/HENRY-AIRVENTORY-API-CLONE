# Customer Search API Documentation

## Overview

The Customer Search API provides a unified search endpoint allowing customers to search across their orders, templates, connected stores, and the catalog in a single request.

## Endpoint

GET /api/v1/customers/search

## Authentication

- **Required**: Yes
- **Middleware**: `auth.customer_or_admin`
- **Supported Guards**: Customer, Admin

---

## Request Parameters

| Parameter | Type    | Required | Default | Validation                             | Description          |
| --------- | ------- | -------- | ------- | -------------------------------------- | -------------------- |
| `q`            | string     | Yes      | -       | min:1, max:255                         | Search query |
| `type`         | string     | No       | `all`   | in:all,orders,templates,stores,catalog | Resource type filter |
| `page`         | integer    | No       | `1`     | min:1                                  | Page number |
| `per_page`     | integer    | No       | `10`    | min:1, max:50                          | Items per page |
| `min_price`    | number     | No       | -       | min:0                                  | Catalog: minimum price |
| `max_price`    | number     | No       | -       | min:0                                  | Catalog: maximum price |
| `category`     | string/int | No       | -       | -                                       | Catalog: category slug or ID |
| `brand`        | string/int | No       | -       | -                                       | Catalog: brand name or option_id |
| `available`    | boolean    | No       | -       | boolean                                 | Catalog: in-stock filter |
| `start_date`   | date       | No       | -       | date                                    | Orders/Templates: start date (YYYY-MM-DD) |
| `end_date`     | date       | No       | -       | date                                    | Orders/Templates: end date (YYYY-MM-DD) |
| `status`       | string     | No       | -       | -                                       | Orders: order status |
| `payment_status` | string   | No       | -       | -                                       | Orders: payment status |
| `platform`     | string     | No       | -       | in:shopify,woocommerce                  | Stores: platform filter |

---

## Response Structure

### Success Response (200)

```json
{
  "status": true,
  "data": {
    "query": "hoodie",
    "type": "all",
    "results": {
    "orders": {
        "total": 12,
        "items": [],
        "pagination": {
          "total": 12,
          "count": 10,
          "per_page": 10,
          "current_page": 1,
          "total_pages": 2
        },
        "hasMore": true
    },
    "templates": {
        "total": 3,
        "items": [],
        "pagination": {
          "total": 3,
          "count": 3,
          "per_page": 10,
          "current_page": 1,
          "total_pages": 1
        },
        "hasMore": false
    },
    "stores": {
        "total": 2,
        "items": [],
        "pagination": {
          "total": 2,
          "count": 2,
          "per_page": 10,
          "current_page": 1,
          "total_pages": 1
        },
        "hasMore": false
    },
    "catalog": {
        "total": 15,
        "items": [],
        "pagination": {
          "total": 15,
          "count": 10,
          "per_page": 10,
          "current_page": 1,
          "total_pages": 2
        },
        "hasMore": true
      }
    }
    }
}
```

### Order Item

```json
{
    "id": 123,
    "type": "order",
    "order_number": "ORD-2024-0001",
    "status": "processing",
    "payment_status": "paid",
    "total": {
        "raw": 150.0,
        "formatted": "$150.00"
    },
    "created_at": "2024-02-16T10:30:00Z",
    "source": {
        "platform": "shopify",
        "name": "My Shopify Store"
    },
    "customer_name": "John Doe"
}
```

### Template Item

```json
{
    "id": 456,
    "type": "template",
    "title": "Custom Hoodie Design",
    "product_name": "Unisex Hoodie",
    "product_slug": "unisex-hoodie",
    "stores": [
        {
            "id": 1,
            "name": "My Shopify Store"
        }
    ],
    "updated_at": "2024-02-15T14:20:00Z"
}
```

### Store Item

```json
{
    "id": 789,
    "type": "store",
    "name": "My Shopify Store",
    "identifier": "mystore.myshopify.com",
    "platform": "shopify",
    "status": "active",
    "created_at": "2024-01-10T08:00:00Z"
}
```

### Catalog Item

```json
{
    "id": 321,
    "type": "catalog",
    "name": "Unisex Hoodie",
    "slug": "unisex-hoodie",
    "sku": "HOODIE-UNI-001",
    "category": {
        "id": 5,
        "name": "Apparel",
        "slug": "apparel"
    },
    "image": "https://example.com/images/hoodie.jpg"
}
```

---

## Searchable Fields

### Orders

- Order number
- Customer name (billing & shipping)
- Customer email (billing & shipping)
- Customer phone (billing & shipping)
- Store/source name
- Source order number

### Templates

- Template title
- Associated catalog product name
- Connected store names

### Stores

- Store name
- Store identifier (domain/URL)
- Platform (shopify, woocommerce)

### Catalog Products

- Product name
- SKU
- Category name

> [!IMPORTANT]
> **PII Warning**: The searchable fields listed above for Orders contain Personally Identifiable Information (PII), including customer names, email addresses, and phone numbers. Ensure compliance with applicable privacy regulations when handling search queries and results.

---

## Search Query Logging & Privacy

### Query Logging

- **Logging Status**: All search queries are logged for debugging and audit purposes
- **Log Contents**: Logs include the search query text, customer ID, search type, timestamp, and any errors
- **Retention Period**: Search query logs are retained for 90 days, after which they are automatically purged
- **Anonymization**: Query logs are stored with pseudonymized customer identifiers; full customer PII is not duplicated in search logs

### Access Control

- **Log Access**: Search query logs are accessible only to authorized system administrators and security personnel
- **Audit Trail**: All log access is tracked and auditable
- **Internal Use Only**: Search logs are used exclusively for system debugging, performance optimization, and security monitoring

### GDPR & CCPA Compliance

- **Legal Basis**: Search query processing is performed under the "legitimate interest" basis for service provision and security
- **Data Subject Rights**: Customers may exercise their GDPR/CCPA rights including:
    - **Right to Access**: Request a copy of their search query logs
    - **Right to Rectification**: Request correction of inaccurate data
    - **Right to Erasure**: Request deletion of their search history (subject to legal retention requirements)
    - **Right to Data Portability**: Receive their search data in a machine-readable format
- **Privacy Contact**: Data subject requests should be submitted to privacy@airventory.com
- **Response Time**: Data subject requests are processed within 30 days as required by GDPR

### PII in Searchable Fields

As noted in the [Searchable Fields](#searchable-fields) section, the Orders search includes the following PII:

- Customer names (billing & shipping addresses)
- Email addresses (billing & shipping addresses)
- Phone numbers (billing & shipping addresses)

This PII is indexed for search purposes and subject to the same privacy protections as the source data.

---

## Example Requests

### Search All Resources (paginated)

```bash
curl -X GET "https://api.example.com/api/v1/customers/search?q=hoodie&page=1&per_page=10" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Search Only Orders

```bash
curl -X GET "https://api.example.com/api/v1/customers/search?q=john&type=orders&per_page=10&page=2&start_date=2026-01-01&end_date=2026-02-17&status=processing" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Search Only Templates

```bash
curl -X GET "https://api.example.com/api/v1/customers/search?q=custom&type=templates&page=1" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

### Search Catalog with Filters

```bash
curl -X GET "https://api.example.com/api/v1/customers/search?q=hoodie&type=catalog&brand=Nike&category=apparel&min_price=10&max_price=100&available=1&page=1&per_page=12" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -H "Accept: application/json"
```

---

## Error Responses

### Validation Error (422)

```json
{
    "status": false,
    "message": "Validation failed",
    "errors": {
        "q": ["The q field is required."]
    }
}
```

### Unauthorized (401)

```json
{
    "status": false,
    "message": "Unauthorized"
}
```

### Server Error (500)

```json
{
    "status": false,
    "message": "Search failed",
    "error": "Error details (debug mode only)"
}
```

---

## Use Cases

1. **Global Dashboard Search** - Search across all resources with `type=all`
2. **Order Lookup** - Find orders by number, customer name, or email
3. **Template Discovery** - Find templates by name or product
4. **Store Management** - Locate stores by name or platform
5. **Product Browser** - Search catalog for design products

---

## Performance Notes

- Uses pagination with `per_page` (max 50) and `page`
- Eager loads related data to avoid N+1 queries
- Caches results for 60 seconds for repeated queries
- All searches are scoped to the authenticated customer (catalog is public)

### Pagination Behavior

- Each resource section returns a `pagination` object with total/count/per_page/current_page/total_pages
- Use `hasMore` to know if more pages exist
- For exhaustive listings and richer filters, prefer the dedicated endpoints:
  - Orders: `/api/v1/customers/orders`
  - Templates: `/api/v1/customers/templates`
  - Stores: `/api/v1/customers/stores`
  - Catalog: `/api/v1/catalog/products`

---

## Rate Limiting

- Throttled via `customer-search` limiter: 60 requests/minute per customer/admin/IP

---

## Security

- Customers can only search their own data (except public catalog)
- Admins can search any customer's data
- All queries use Eloquent parameter binding (SQL injection protected)
- Rate limiting enforced for production use

---

## Implementation Notes

- Full-text search support can be enabled at the database layer (e.g., MySQL MATCH AGAINST) where available; the service falls back to `LIKE` matching for portability.
- Indexes for product tables are maintained (see database/migrations for catalog product indexes). Consider adding composite indexes on frequently queried order address fields for large datasets.
