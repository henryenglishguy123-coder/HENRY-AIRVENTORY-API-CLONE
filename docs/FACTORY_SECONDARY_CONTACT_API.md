# Factory Secondary Contact API

This API allows factories and admins to manage secondary contact information for factory accounts. The secondary contact serves as an alternate contact person for the factory.

## Endpoints

### POST /api/v1/factories/secondary-contact

Stores or updates secondary contact information for a factory.

**Authentication:** Required (Factory or Admin token)

**Request Headers:**
```
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**

**For Factory Users:**
```json
{
  "first_name": "string (required, max: 255)",
  "last_name": "string (required, max: 255)",
  "email": "string (optional, must be valid email, max: 255)",
  "phone_number": "string (required, max: 20)"
}
```

**For Admin Users:**
```json
{
  "factory_id": "integer (required, must exist in factory_users table)",
  "first_name": "string (required, max: 255)",
  "last_name": "string (required, max: 255)",
  "email": "string (optional, must be valid email, max: 255)",
  "phone_number": "string (required, max: 20)"
}
```

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "secondary_contact": {
      "first_name": "John",
      "last_name": "Doe",
      "email": "john.doe@example.com",
      "phone_number": "+1234567890"
    }
  },
  "message": "Secondary contact information saved successfully."
}
```

**Error Responses:**

**404 Not Found** (Admin user with invalid factory_id):
```json
{
  "success": false,
  "data": null,
  "message": "Factory not found."
}
```

**422 Unprocessable Entity** (Validation errors):
```json
{
  "message": "The given data was invalid.",
  "errors": {
    "first_name": ["First name is required."],
    "phone_number": ["Phone number is required."]
  }
}
```

**500 Internal Server Error:**
```json
{
  "success": false,
  "data": null,
  "message": "Failed to save secondary contact information."
}
```

---

### GET /api/v1/factories/secondary-contact

Retrieves secondary contact information for a factory.

**Authentication:** Required (Factory or Admin token)

**Request Headers:**
```
Authorization: Bearer {token}
```

**Query Parameters:**

**For Admin Users:**
- `factory_id` (required): The ID of the factory

**For Factory Users:**
- No query parameters needed (uses authenticated factory ID)

**Success Response (200 OK) - With Contact:**
```json
{
  "success": true,
  "data": {
    "secondary_contact": {
      "first_name": "John",
      "last_name": "Doe",
      "email": "john.doe@example.com",
      "phone_number": "+1234567890"
    }
  },
  "message": "Secondary contact information retrieved successfully."
}
```

**Success Response (200 OK) - No Contact:**
```json
{
  "success": true,
  "data": {
    "secondary_contact": null
  },
  "message": "No secondary contact information found."
}
```

**Error Responses:**

**400 Bad Request** (Admin user without factory_id):
```json
{
  "success": false,
  "data": null,
  "message": "factory_id parameter is required for admin users."
}
```

**404 Not Found** (Admin user with invalid factory_id):
```json
{
  "success": false,
  "data": null,
  "message": "Factory not found."
}
```

**500 Internal Server Error:**
```json
{
  "success": false,
  "data": null,
  "message": "Failed to retrieve secondary contact information."
}
```

---

## Usage Examples

### JavaScript (Factory User)

**Store/Update Secondary Contact:**
```javascript
const response = await fetch('https://api.example.com/api/v1/factories/secondary-contact', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${factoryToken}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    first_name: 'John',
    last_name: 'Doe',
    email: 'john.doe@example.com',
    phone_number: '+1234567890'
  })
});

const data = await response.json();
console.log(data);
```

**Retrieve Secondary Contact:**
```javascript
const response = await fetch('https://api.example.com/api/v1/factories/secondary-contact', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${factoryToken}`
  }
});

const data = await response.json();
console.log(data.data.secondary_contact);
```

### JavaScript (Admin User)

**Store/Update Secondary Contact:**
```javascript
const response = await fetch('https://api.example.com/api/v1/factories/secondary-contact', {
  method: 'POST',
  headers: {
    'Authorization': `Bearer ${adminToken}`,
    'Content-Type': 'application/json'
  },
  body: JSON.stringify({
    factory_id: 5,
    first_name: 'Jane',
    last_name: 'Smith',
    phone_number: '+9876543210'
  })
});

const data = await response.json();
console.log(data);
```

**Retrieve Secondary Contact:**
```javascript
const response = await fetch('https://api.example.com/api/v1/factories/secondary-contact?factory_id=5', {
  method: 'GET',
  headers: {
    'Authorization': `Bearer ${adminToken}`
  }
});

const data = await response.json();
console.log(data.data.secondary_contact);
```

### cURL (Factory User)

**Store/Update Secondary Contact:**
```bash
curl -X POST https://api.example.com/api/v1/factories/secondary-contact \
  -H "Authorization: Bearer YOUR_FACTORY_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "John",
    "last_name": "Doe",
    "email": "john.doe@example.com",
    "phone_number": "+1234567890"
  }'
```

**Retrieve Secondary Contact:**
```bash
curl -X GET https://api.example.com/api/v1/factories/secondary-contact \
  -H "Authorization: Bearer YOUR_FACTORY_TOKEN"
```

### cURL (Admin User)

**Store/Update Secondary Contact:**
```bash
curl -X POST https://api.example.com/api/v1/factories/secondary-contact \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "factory_id": 5,
    "first_name": "Jane",
    "last_name": "Smith",
    "phone_number": "+9876543210"
  }'
```

**Retrieve Secondary Contact:**
```bash
curl -X GET "https://api.example.com/api/v1/factories/secondary-contact?factory_id=5" \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

---

## Field Descriptions

### Request Fields

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| factory_id | integer | Admin only | ID of the factory (admin users only) |
| first_name | string | Yes | First name of the secondary contact |
| last_name | string | Yes | Last name of the secondary contact |
| email | string | No | Email address of the secondary contact (optional) |
| phone_number | string | Yes | Phone number of the secondary contact |

### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| success | boolean | Indicates if the request was successful |
| data | object | Contains the secondary contact information |
| data.secondary_contact | object/null | Secondary contact details or null if not found |
| message | string | Human-readable message about the operation |

---

## Validation Rules

- **first_name**: Required, string, maximum 255 characters
- **last_name**: Required, string, maximum 255 characters
- **email**: Optional, must be a valid email format if provided, maximum 255 characters
- **phone_number**: Required, string, maximum 20 characters
- **factory_id** (admin only): Required for admin users, must be an integer, must exist in factory_users table

---

## Authorization

### Factory Users
- Automatically uses the authenticated factory's ID from the token
- Can add, update, and retrieve their own secondary contact information
- **No restrictions** based on account verification status

### Admin Users
- Must provide `factory_id` in request body (POST) or query parameter (GET)
- Can manage secondary contact information for any factory
- Full access regardless of factory verification status

---

## Data Storage

- Secondary contact information is stored in the `factory_metas` table
- Storage key: `secondary_contact`
- Data format: JSON encoded string containing contact details
- One secondary contact per factory (updateOrCreate logic)

---

## Notes

1. **Email is Optional**: The email field is not required, allowing flexibility for contacts who may not have email addresses
2. **Single Contact Per Factory**: Only one secondary contact can be stored per factory. Subsequent POST requests will update the existing contact
3. **No Verification Restrictions**: Unlike business information and addresses, secondary contacts can be updated at any time by both factory users and admins
4. **Partial Updates Not Supported**: When updating, all required fields must be provided. The entire contact record is replaced
5. **JSON Storage**: Contact data is stored as JSON in factory_metas, making it easy to extend with additional fields in the future
6. **Transaction Safety**: All database operations are wrapped in transactions to ensure data integrity

---

## Security Considerations

- Authentication is required for all endpoints
- Factory users can only access their own secondary contact
- Admin users can access any factory's secondary contact by providing factory_id
- Input validation prevents injection attacks
- Email validation ensures proper format when provided
- Maximum field lengths prevent buffer overflow attacks

---

## Error Handling

The API uses standard HTTP status codes:

- **200 OK**: Request succeeded
- **400 Bad Request**: Missing required query parameters (admin users)
- **401 Unauthorized**: Missing or invalid authentication token
- **404 Not Found**: Factory not found (admin users with invalid factory_id)
- **422 Unprocessable Entity**: Validation errors in request data
- **500 Internal Server Error**: Server-side error occurred

All error responses include a descriptive message to help troubleshoot issues.
