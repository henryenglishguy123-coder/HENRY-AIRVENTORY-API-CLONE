# Factory Account Update API

## Overview
This API allows factory users to update their account information (first name, last name, phone number), while admin users can update all factory account fields including email.

---

## Authentication
All endpoints require authentication via JWT token in the Authorization header.

**Header:**
```
Authorization: Bearer {token}
```

---

## Endpoint

### Update Factory Account

**URL:** `PUT /api/v1/factories/account`

**Authentication:** Required (factory or admin_api guard)

**Description:** Updates factory account information with different permissions for factory users vs admin users.

---

## Authorization Rules

### Factory Users
- ✅ Can update: `first_name`, `last_name`, `phone_number`
- ❌ Cannot update: `email` (security measure)
- Uses factory_id from authenticated token automatically

### Admin Users
- ✅ Can update: `first_name`, `last_name`, `phone_number`, `email`
- Must provide `factory_id` in request body
- Can change email for any factory
- Validates email uniqueness before updating

---

## Request Parameters

### Factory User Request

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| first_name | string | No | Factory owner's first name (max: 255 chars) |
| last_name | string | No | Factory owner's last name (max: 255 chars) |
| phone_number | string | No | Factory contact phone number (max: 20 chars) |

**Note:** Factory users cannot include `email` or `factory_id` parameters.

### Admin User Request

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| factory_id | integer | **Yes** | ID of the factory to update |
| first_name | string | No | Factory owner's first name (max: 255 chars) |
| last_name | string | No | Factory owner's last name (max: 255 chars) |
| phone_number | string | No | Factory contact phone number (max: 20 chars) |
| email | string | No | Factory email address (must be unique) |

---

## Response

### Success Response (200 OK)

```json
{
  "success": true,
  "data": {
    "factory": {
      "id": 1,
      "first_name": "Updated First Name",
      "last_name": "Updated Last Name",
      "email": "factory@example.com",
      "phone_number": "+1234567890"
    }
  },
  "message": "Account information updated successfully."
}
```

### Error Responses

#### 401 Unauthorized
```json
{
  "message": "Unauthenticated."
}
```

#### 404 Not Found (Factory doesn't exist)
```json
{
  "success": false,
  "data": null,
  "message": "Factory not found."
}
```

#### 422 Validation Error (Admin without factory_id)
```json
{
  "message": "The factory id field is required.",
  "errors": {
    "factory_id": [
      "Factory ID is required for admin users."
    ]
  }
}
```

#### 422 Validation Error (Email already taken)
```json
{
  "success": false,
  "data": null,
  "message": "Email is already taken."
}
```

#### 422 Validation Error (Invalid data type)
```json
{
  "message": "The first name must be a string.",
  "errors": {
    "first_name": [
      "First name must be a valid string."
    ]
  }
}
```

#### 500 Server Error
```json
{
  "success": false,
  "data": null,
  "message": "An error occurred while updating account information.",
  "error": "Error details..."
}
```

---

## Usage Examples

### Factory User - Update Basic Info

**Request:**
```bash
curl -X PUT https://api.example.com/api/v1/factories/account \
  -H "Authorization: Bearer {factory_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "first_name": "John",
    "last_name": "Smith",
    "phone_number": "+1234567890"
  }'
```

**JavaScript (Fetch API):**
```javascript
const updateFactoryAccount = async (data) => {
  const response = await fetch('/api/v1/factories/account', {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${factoryToken}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      first_name: data.firstName,
      last_name: data.lastName,
      phone_number: data.phoneNumber
    })
  });
  
  const result = await response.json();
  return result;
};

// Usage
updateFactoryAccount({
  firstName: 'John',
  lastName: 'Smith',
  phoneNumber: '+1234567890'
});
```

---

### Factory User - Partial Update

You can update only specific fields:

**Request:**
```bash
curl -X PUT https://api.example.com/api/v1/factories/account \
  -H "Authorization: Bearer {factory_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "phone_number": "+9876543210"
  }'
```

This will only update the phone number, leaving other fields unchanged.

---

### Admin User - Update Factory Account (Including Email)

**Request:**
```bash
curl -X PUT https://api.example.com/api/v1/factories/account \
  -H "Authorization: Bearer {admin_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "factory_id": 5,
    "first_name": "Jane",
    "last_name": "Doe",
    "email": "newemail@factory.com",
    "phone_number": "+1234567890"
  }'
```

**JavaScript (Fetch API):**
```javascript
const adminUpdateFactoryAccount = async (factoryId, data) => {
  const response = await fetch('/api/v1/factories/account', {
    method: 'PUT',
    headers: {
      'Authorization': `Bearer ${adminToken}`,
      'Content-Type': 'application/json',
    },
    body: JSON.stringify({
      factory_id: factoryId,
      first_name: data.firstName,
      last_name: data.lastName,
      email: data.email,
      phone_number: data.phoneNumber
    })
  });
  
  const result = await response.json();
  return result;
};

// Usage
adminUpdateFactoryAccount(5, {
  firstName: 'Jane',
  lastName: 'Doe',
  email: 'newemail@factory.com',
  phoneNumber: '+1234567890'
});
```

---

## Security Considerations

1. **Email Protection for Factory Users:**
   - Factory users cannot change their own email address
   - This prevents unauthorized email changes and maintains account security
   - Only admins can update factory email addresses

2. **Email Uniqueness:**
   - When admin updates email, the system validates that no other factory is using that email
   - Prevents duplicate email addresses in the system

3. **Authentication Required:**
   - All requests must be authenticated with a valid JWT token
   - Unauthenticated requests receive 401 error

4. **Factory Validation:**
   - Admin requests validate that the specified factory_id exists
   - Non-existent factories return validation errors

5. **Data Type Validation:**
   - All fields are validated for correct data types
   - Invalid data types return 422 validation errors

---

## Notes

- Factory users use their authenticated token to identify which account to update (no factory_id needed)
- Admin users must explicitly specify factory_id in the request
- At least one field should be provided for update (first_name, last_name, or phone_number for factories; those plus email for admins)
- Fields not included in the request remain unchanged
- The response always includes the complete updated factory object
- Phone number format is not strictly validated, allowing international formats

---

## Related Endpoints

- `POST /api/v1/factories/signup` - Register new factory account
- `POST /api/v1/factories/login` - Factory login
- `GET /api/v1/auth/me` - Get authenticated user information
- `PUT /api/v1/factories/business-information` - Update factory business information
- `PUT /api/v1/factories/addresses/{id}` - Update factory addresses
