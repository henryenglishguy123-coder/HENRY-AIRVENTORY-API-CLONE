# Factory Addresses API Documentation

## Overview

This API allows factories to manage their addresses (facility and distribution centers) during the registration process. Multiple addresses can be added, and there are two types of addresses supported:
- **facility**: Factory/manufacturing facility address
- **dist_center**: Distribution center address

## Authorization Rules

### Factory Users
- Can add/update/delete addresses **only** when account is NOT verified (`account_verified != 1`)
- Allowed statuses: rejected (0), pending (2), hold (3), processing (4)
- Blocked status: verified (1) - returns 403 Forbidden
- `factory_id` is automatically retrieved from authentication token

### Admin Users
- Can add/update/delete addresses **anytime**, regardless of verification status
- Must provide `factory_id` in request body (POST/PUT) or query parameter (GET/DELETE)
- Can manage addresses for any factory

## Endpoints

### 1. Create Factory Address

**Endpoint:** `POST /api/v1/factories/addresses`

**Authentication:** Required (Factory or Admin token)

**Request Body (Factory User):**
```json
{
  "type": "facility",
  "address": "Bandela Colony, Ganipur Road, Sikrai",
  "country_id": "101",
  "state_id": "4014",
  "city": "Dausa",
  "postal_code": "303508"
}
```

**Request Body (Admin User):**
```json
{
  "factory_id": 5,
  "type": "dist_center",
  "address": "Bandela Colony, Ganipur Road, Sikrai",
  "country_id": "101",
  "state_id": "4014",
  "city": "Dausa",
  "postal_code": "303508"
}
```

**Parameters:**
| Field | Type | Required | Description |
|-------|------|----------|-------------|
| factory_id | integer | Yes (Admin only) | Factory ID (admin users must provide) |
| type | string | Yes | Address type: "facility" or "dist_center" |
| address | string | Yes | Full street address (max 255 chars) |
| country_id | string | Yes | Country identifier (max 25 chars) |
| state_id | string | Yes | State/region identifier (max 25 chars) |
| city | string | Yes | City name (max 100 chars) |
| postal_code | string | Yes | Postal/ZIP code (max 10 chars) |

**Success Response (201 Created):**
```json
{
  "success": true,
  "data": {
    "address": {
      "id": 1,
      "factory_id": 5,
      "type": "facility",
      "address": "Bandela Colony, Ganipur Road, Sikrai",
      "country_id": "101",
      "state_id": "4014",
      "city": "Dausa",
      "postal_code": "303508",
      "created_at": "2026-01-16T10:30:00.000000Z",
      "updated_at": "2026-01-16T10:30:00.000000Z"
    }
  },
  "message": "Address added successfully."
}
```

**Notes:**
- Sets `addresses_status = 1` in factory_metas table when first address is added
- Multiple addresses of the same type are allowed

---

### 2. Get All Factory Addresses

**Endpoint:** `GET /api/v1/factories/addresses`

**Authentication:** Required (Factory or Admin token)

**Query Parameters (Admin only):**
| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| factory_id | integer | Yes (Admin) | Factory ID to retrieve addresses for |

**Factory User Request:**
```
GET /api/v1/factories/addresses
Authorization: Bearer <factory_token>
```

**Admin User Request:**
```
GET /api/v1/factories/addresses?factory_id=5
Authorization: Bearer <admin_token>
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "addresses": [
      {
        "id": 1,
        "factory_id": 5,
        "type": "facility",
        "address": "Bandela Colony, Ganipur Road, Sikrai",
        "country_id": "101",
        "state_id": "4014",
        "city": "Dausa",
        "postal_code": "303508",
        "created_at": "2026-01-16T10:30:00.000000Z",
        "updated_at": "2026-01-16T10:30:00.000000Z"
      },
      {
        "id": 2,
        "factory_id": 5,
        "type": "dist_center",
        "address": "Another Address",
        "country_id": "101",
        "state_id": "4014",
        "city": "Jaipur",
        "postal_code": "302020",
        "created_at": "2026-01-16T10:35:00.000000Z",
        "updated_at": "2026-01-16T10:35:00.000000Z"
      }
    ]
  },
  "message": "Addresses retrieved successfully."
}
```

---

### 3. Update Factory Address

**Endpoint:** `PUT /api/v1/factories/addresses/{id}`

**Authentication:** Required (Factory or Admin token)

**URL Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Address ID to update |

**Request Body (Factory User):**
```json
{
  "type": "dist_center",
  "address": "Updated Address",
  "country_id": "101",
  "state_id": "4014",
  "city": "Jaipur",
  "postal_code": "302020"
}
```

**Request Body (Admin User):**
```json
{
  "factory_id": 5,
  "type": "dist_center",
  "address": "Updated Address",
  "country_id": "101",
  "state_id": "4014",
  "city": "Jaipur",
  "postal_code": "302020"
}
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "address": {
      "id": 1,
      "factory_id": 5,
      "type": "dist_center",
      "address": "Updated Address",
      "country_id": "101",
      "state_id": "4014",
      "city": "Jaipur",
      "postal_code": "302020",
      "created_at": "2026-01-16T10:30:00.000000Z",
      "updated_at": "2026-01-16T10:40:00.000000Z"
    }
  },
  "message": "Address updated successfully."
}
```

---

### 4. Delete Factory Address

**Endpoint:** `DELETE /api/v1/factories/addresses/{id}`

**Authentication:** Required (Factory or Admin token)

**URL Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| id | integer | Address ID to delete |

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": null,
  "message": "Address deleted successfully."
}
```

---

## Error Responses

### 400 Bad Request
Returned when admin doesn't provide required factory_id

```json
{
  "success": false,
  "data": null,
  "message": "Factory ID is required for admin users."
}
```

### 401 Unauthorized
Returned when authentication token is missing or invalid

```json
{
  "message": "Unauthenticated."
}
```

### 403 Forbidden
Returned when factory account is already verified

```json
{
  "success": false,
  "data": null,
  "message": "Factory addresses cannot be updated after account verification."
}
```

Returned when trying to update/delete address that doesn't belong to the factory

```json
{
  "success": false,
  "data": null,
  "message": "Unauthorized to update this address."
}
```

### 404 Not Found
Returned when factory doesn't exist (admin requests)

```json
{
  "success": false,
  "data": null,
  "message": "Factory not found."
}
```

### 422 Validation Error
Returned when request validation fails

```json
{
  "message": "The type field is required. (and 5 more errors)",
  "errors": {
    "type": [
      "The type field is required."
    ],
    "address": [
      "The address field is required."
    ],
    "country_id": [
      "The country id field is required."
    ],
    "state_id": [
      "The state id field is required."
    ],
    "city": [
      "The city field is required."
    ],
    "postal_code": [
      "The postal code field is required."
    ]
  }
}
```

Invalid address type:

```json
{
  "message": "The type must be either facility or dist_center.",
  "errors": {
    "type": [
      "The type must be either facility or dist_center."
    ]
  }
}
```

### 500 Internal Server Error
Returned when an unexpected error occurs

```json
{
  "success": false,
  "data": null,
  "message": "Failed to add address: [error details]"
}
```

---

## Usage Examples

### JavaScript (Fetch API)

**Factory User - Create Address:**
```javascript
const response = await fetch('/api/v1/factories/addresses', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${factoryToken}`
  },
  body: JSON.stringify({
    type: 'facility',
    address: 'Bandela Colony, Ganipur Road, Sikrai',
    country_id: '101',
    state_id: '4014',
    city: 'Dausa',
    postal_code: '303508'
  })
});

const data = await response.json();
console.log(data);
```

**Admin User - Create Address:**
```javascript
const response = await fetch('/api/v1/factories/addresses', {
  method: 'POST',
  headers: {
    'Content-Type': 'application/json',
    'Authorization': `Bearer ${adminToken}`
  },
  body: JSON.stringify({
    factory_id: 5,
    type: 'dist_center',
    address: 'Distribution Center Address',
    country_id: '101',
    state_id: '4014',
    city: 'Jaipur',
    postal_code: '302020'
  })
});

const data = await response.json();
console.log(data);
```

**Factory User - Get All Addresses:**
```javascript
const response = await fetch('/api/v1/factories/addresses', {
  headers: {
    'Authorization': `Bearer ${factoryToken}`
  }
});

const data = await response.json();
console.log(data.data.addresses);
```

**Admin User - Get Factory Addresses:**
```javascript
const response = await fetch('/api/v1/factories/addresses?factory_id=5', {
  headers: {
    'Authorization': `Bearer ${adminToken}`
  }
});

const data = await response.json();
console.log(data.data.addresses);
```

### cURL

**Factory User - Create Address:**
```bash
curl -X POST https://api.example.com/api/v1/factories/addresses \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_FACTORY_TOKEN" \
  -d '{
    "type": "facility",
    "address": "Bandela Colony, Ganipur Road, Sikrai",
    "country_id": "101",
    "state_id": "4014",
    "city": "Dausa",
    "postal_code": "303508"
  }'
```

**Admin User - Create Address:**
```bash
curl -X POST https://api.example.com/api/v1/factories/addresses \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -d '{
    "factory_id": 5,
    "type": "dist_center",
    "address": "Distribution Center Address",
    "country_id": "101",
    "state_id": "4014",
    "city": "Jaipur",
    "postal_code": "302020"
  }'
```

**Update Address:**
```bash
curl -X PUT https://api.example.com/api/v1/factories/addresses/1 \
  -H "Content-Type: application/json" \
  -H "Authorization: Bearer YOUR_TOKEN" \
  -d '{
    "type": "dist_center",
    "address": "Updated Address",
    "country_id": "101",
    "state_id": "4014",
    "city": "Jaipur",
    "postal_code": "302020"
  }'
```

**Delete Address:**
```bash
curl -X DELETE https://api.example.com/api/v1/factories/addresses/1 \
  -H "Authorization: Bearer YOUR_TOKEN"
```

---

## Notes

1. **Multiple Addresses**: Factories can have multiple addresses of both types (facility and dist_center)
2. **Meta Update**: When the first address is added, `addresses_status = 1` is set in factory_metas table
3. **Authorization**: Factory users are restricted after verification, but admin users have full access anytime
4. **Cascade Delete**: All addresses are automatically deleted when a factory user is deleted
5. **Validation**: All fields are required, and type must be either "facility" or "dist_center"

---

## Security Considerations

- All endpoints require authentication via JWT token
- Factory users can only access their own addresses
- Admin users must provide valid factory_id and can access any factory's addresses
- Verified factories cannot modify their addresses (factory users only)
- Address ownership is validated before update/delete operations
