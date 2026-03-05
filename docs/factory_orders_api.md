# Factory Orders API Documentation

This document explicitly details the Order Listing and Order Details APIs available for Factory authenticated users. These endpoints supply factory-relevant order amounts (un-marked up Base Totals), while hiding internal arrays such as gateway `payments` and `source` objects which are irrelevant to the manufacturing workflow.

---

## 1. Get Factory Orders (List)

Retrieve a paginated list of all sales orders assigned to the authenticated factory.

- **Method:** `GET`
- **Endpoint:** `/api/v1/factories/orders`
- **Authentication:** `Bearer <Factory JWT Token>`

### Supported Query Parameters

| Parameter        | Type      | Description                                                            |
| ---------------- | --------- | ---------------------------------------------------------------------- |
| `page`           | `integer` | Page number for pagination. (Default: 1)                               |
| `per_page`       | `integer` | Number of records per page. (Default: 20)                              |
| `order_number`   | `string`  | Search by specific Order Number.                                       |
| `search`         | `string`  | Search globally by Order Number, Name, Phone, or Email.                |
| `status`         | `string`  | Filter by order status (e.g. `processing`, `completed`).               |
| `payment_status` | `string`  | Filter by payment status (e.g. `paid`, `pending`).                     |
| `start_date`     | `date`    | Filter starting from this date (Y-m-d).                                |
| `end_date`       | `date`    | Filter up to this date (Y-m-d).                                        |
| `sort_by`        | `string`  | Column to sort by (`created_at`, `grand_total`, `order_status`, `id`). |
| `sort_dir`       | `string`  | Sorting direction (`asc` or `desc`).                                   |

### Successful Response Format

```json
{
    "status": true,
    "message": "Orders retrieved successfully",
    "data": [
        {
            "id": 100,
            "order_number": "AIO-0000100",
            "recipient_name": "John Doe",
            "created_at": "2026-02-24T18:00:00.000000Z",
            "price": "$12.00",
            "order_status": "processing",
            "payment_status": "paid",
            "grand_total": "12.0000"
        }
    ],
    "pagination": {
        "total": 1,
        "count": 1,
        "per_page": 20,
        "current_page": 1,
        "total_pages": 1
    },
    "filters": { ... },
    "sorting": { ... }
}
```

> **Note**: For factory users, `price` and `grand_total` render un-marked up amounts, and the external `source` context payload is omitted.

---

## 2. Get Factory Order Details

Retrieve the complete details, breakdown of costs, and item definitions of a single order.

- **Method:** `GET`
- **Endpoint:** `/api/v1/factories/orders/{orderNumber}`
- **Authentication:** `Bearer <Factory JWT Token>`

### Supported URL Parameters

| Parameter       | Type                  | Description                                                               |
| --------------- | --------------------- | ------------------------------------------------------------------------- |
| `{orderNumber}` | `string` or `integer` | The unique `order_number` (AIO-000...) or the Database `id` of the order. |

### Successful Response Format

```json
{
    "status": true,
    "data": {
        "id": 100,
        "order_number": "AIO-0000100",
        "status": "processing",
        "payment_status": "paid",
        "shipping_method": "flatrate",
        "shipping_address": {
            "first_name": "John",
            "last_name": "Doe",
            "email": "johndoe@example.com",
            "phone": "+1234567890",
            "address_line_1": "123 Street",
            "city": "Metropolis",
            "state": "NY",
            "country": "US",
            "zip_code": "10001"
        },
        "billing_address": { ... },
        "breakdown": {
            "subtotal": "$10.00",
            "discount": "$0.00",
            "shipping": "$2.00",
            "tax": "$0.00",
            "total": "$12.00"
        },
        "items": [
            {
                "name": "Custom T-Shirt",
                "sku": "TSHIRT-01",
                "options": [...],
                "designs": [...],
                "price": "$10.00",
                "quantity": 1,
                "subtotal": "$10.00",
                "branding": {
                    "packaging_label": null,
                    "hang_tag": null
                }
            }
        ],
        "created_at": "2026-02-24T18:00:00.000000Z"
    }
}
```

> **Note**: For factory users, the following customizations are strictly enforced on this endpoint payload:
>
> - `payments` array tracking internal gateway transactions is visually hidden.
> - `source` object denoting origin external connections is visually hidden.
> - `breakdown.subtotal` outputs the un-marked up `base_subtotal_before_discount` field.
> - `breakdown.discount` outputs the un-marked up `base_discount` field.
> - `breakdown.tax` outputs the un-marked up `grand_subtotal_tax` field.
> - `breakdown.total` outputs the un-marked up `grand_total` field.
> - `items.*.price` outputs the un-marked up `row_price` field.
> - `items.*.subtotal` outputs the un-marked up `subtotal` field.
