# Customer Reorder API

## Overview

The Reorder API allows customers to quickly reorder items from a previous order. Items are added to the customer's active cart (or a new one is created) with the same template, product options, and quantities from the original order. The shipping address from the original order is also copied. The customer then only needs to proceed to payment.

---

## Endpoint

```
POST /api/v1/customers/orders/{order}/reorder
```

### Authentication

Requires `auth.customer_or_admin` middleware.

- **Customer**: Authenticated via `customer` guard
- **Admin**: Must provide `customer_id` in the request body

### URL Parameters

| Parameter | Type    | Required | Description                    |
| --------- | ------- | -------- | ------------------------------ |
| `order`   | integer | Yes      | The ID of the order to reorder |

### Request Body

The request body is **optional** for customers authenticated via the `customer` guard. For admin users authenticated via `admin_api`, the `customer_id` field is **required**.

| Field         | Type    | Required             | Description                                                                                                                                                                                                                    |
| ------------- | ------- | -------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| `customer_id` | integer | **Yes** (Admin only) | ID of the customer to act on behalf of. Ignored when authenticated as a customer via the `customer` guard. Required when using `admin_api` guard (part of `auth.customer_or_admin` middleware). Must exist in `vendors` table. |

> [!NOTE]
> No item arrays or quantity overrides are needed — all item details (template, product, options, quantities) are automatically read from the original order.

#### Example: Customer Request

```http
POST /api/v1/customers/orders/42/reorder
Authorization: Bearer <customer_token>
Content-Type: application/json

{}
```

#### Example: Admin Request

```http
POST /api/v1/customers/orders/42/reorder
Authorization: Bearer <admin_token>
Content-Type: application/json

{
  "customer_id": 15
}
```

---

## Response

### Success (200)

```json
{
  "success": true,
  "message": "Order reordered successfully.",
  "data": {
    "cart": {
      "id": 42,
      "vendor_id": 1,
      "status": "active",
      "items": [ ... ],
      "totals": { ... },
      "address": { ... },
      "discount": null,
      "errors": []
    },
    "added_items": [
      {
        "order_item_id": 10,
        "product_name": "Classic T-Shirt",
        "sku": "TS-BLK-M",
        "qty": 2
      }
    ],
    "skipped_items": []
  }
}
```

### Partial Success (200)

When some items cannot be reordered (e.g., template deleted):

```json
{
  "success": true,
  "message": "Order partially reordered. Some items were skipped.",
  "data": {
    "cart": { ... },
    "added_items": [ ... ],
    "skipped_items": [
      {
        "order_item_id": 11,
        "product_name": "Vintage Hoodie",
        "sku": "VH-RED-L",
        "reason": "Template no longer exists."
      }
    ]
  }
}
```

### Error Responses

#### 401 Unauthenticated

```json
{
    "message": "Unauthenticated."
}
```

#### 403 Unauthorized

```json
{
    "success": false,
    "message": "You are not authorized to reorder this order."
}
```

#### 404 Not Found

```json
{
    "message": "Resource not found."
}
```

#### 500 Internal Server Error

```json
{
    "success": false,
    "message": "Failed to reorder. Please try again."
}
```

---

## Skip Reasons

An item may be skipped for any of the following reasons:

| Reason                                     | Description                                                                           |
| ------------------------------------------ | ------------------------------------------------------------------------------------- |
| No template associated with this item.     | The original order item had no `template_id`                                          |
| Template no longer exists.                 | The design template was deleted                                                       |
| Template does not belong to this customer. | Template ownership mismatch                                                           |
| No product options found for this item.    | Original item had no recorded product options                                         |
| Pricing/variant/fulfillment errors         | Product variant discontinued, price not available, or fulfillment service unavailable |

---

## Behavior Details

### ⚠️ Important Notes

> [!WARNING]
> **Shipping Address Overwrite**: When reordering, the **current cart's shipping address will be replaced** by the shipping address from the original order. If the customer has an active cart with a different address, it will be overwritten. Customers should verify the shipping address before proceeding to checkout.

1. **Active Cart**: Uses the customer's existing active cart or creates a new one
2. **Duplicate Items**: If the same template, product, and variant combination already exists in the cart, the quantity is updated instead of creating a duplicate.
3. **Pricing**: Resolved at current prices (not the original order's prices)
4. **Transaction**: The entire operation runs inside a database transaction
