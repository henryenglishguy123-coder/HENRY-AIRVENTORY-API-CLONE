# Authentication API Documentation

## Overview

This document describes the unified authentication endpoints that work across all user types (customers, factories, and admins). These endpoints provide a consistent way to retrieve authenticated user information.

## Table of Contents

- [Endpoints](#endpoints)
  - [Get Authenticated User Info (auth/me)](#1-get-authenticated-user-info)
- [Response Format](#response-format)
- [Security](#security)
- [Testing](#testing)

---

## Base URL

All endpoints are prefixed with `/api/v1/auth`

---

## Endpoints

### 1. Get Authenticated User Info

Returns information about the currently authenticated user. This endpoint works with customer, factory, or admin authentication tokens.

**Endpoint:** `GET /api/v1/auth/me`

**Authentication:** Required (JWT - customer, factory, or admin token)

**Request Headers:**
```
Authorization: Bearer {your_jwt_token}
Accept: application/json
```

**Request Body:** None

#### Success Response (200 OK)

**Factory User:**
```json
{
  "name": "John Doe",
  "email": "factory@example.com",
  "role": "factory",
  "accountStatus": "enabled",
  "emailVerified": true,
  "accountVerified": "verified"
}
```

**Customer User:**
```json
{
  "name": "Jane Smith",
  "email": "customer@example.com",
  "role": "customer",
  "accountStatus": "enabled",
  "emailVerified": true
}
```

**Admin User:**
```json
{
  "name": "Admin User",
  "email": "admin@example.com",
  "role": "admin",
  "accountStatus": "enabled",
  "emailVerified": true
}
```

#### Response Fields

| Field | Type | Description |
|-------|------|-------------|
| name | string | User's full name (first name + last name) |
| email | string | User's email address |
| role | string | User type: "customer", "factory", or "admin" |
| accountStatus | string | Account status: "disabled", "enabled", "blocked", or "suspended" |
| emailVerified | boolean | Whether the user's email has been verified |
| accountVerified | string | Account verification status (factories only): "rejected", "verified", "pending", "hold", or "processing" |

#### Error Response (401 Unauthorized)

```json
{
  "message": "Unauthenticated."
}
```

---

## Response Format

### Success Response Structure

All successful responses return a JSON object with the user information fields listed above.

**Important:** The response **never** includes:
- User ID (`id`, `userId`, or `user_id`)
- Password or password hashes
- Internal identifiers
- Sensitive data

### Account Status Values

| Value | Description |
|-------|-------------|
| disabled | Account has been disabled |
| enabled | Account is active and fully functional |
| blocked | Account has been blocked |
| suspended | Account has been temporarily suspended |

### Account Verification Status Values (Factory Users Only)

| Value | Description |
|-------|-------------|
| rejected | Account verification was rejected |
| verified | Account has been verified |
| pending | Account verification is pending |
| hold | Account verification is on hold |
| processing | Account verification is being processed |

---

## Security

### Privacy Protection

1. **No User ID Exposure:** User IDs are never exposed in API responses for security reasons
2. **Role-Based Access:** The endpoint automatically detects the user type from the JWT token
3. **Token Validation:** All requests must include a valid JWT token
4. **Multi-Guard Support:** Works seamlessly with customer, factory, and admin authentication guards

### Token Requirements

- Valid JWT token must be provided in the Authorization header
- Token must not be expired
- Token must be associated with an active user account

---

## Testing

### Test Coverage

Comprehensive test suite available in `tests/Feature/AuthMeTest.php` covering:
- ✅ Factory authentication
- ✅ Customer authentication
- ✅ Admin authentication (if applicable)
- ✅ Unverified email status
- ✅ Inactive account status
- ✅ Unauthenticated requests
- ✅ No user ID exposure

### Running Tests

```bash
# Run all auth/me tests
php artisan test --filter AuthMeTest

# Run specific test
php artisan test --filter test_auth_me_with_factory_authentication_succeeds
```

---

## Example Usage

### cURL Example

```bash
curl -X GET https://api.example.com/api/v1/auth/me \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..." \
  -H "Accept: application/json"
```

### JavaScript Example (Fetch API)

```javascript
const getUserInfo = async (token) => {
  try {
    const response = await fetch('https://api.example.com/api/v1/auth/me', {
      method: 'GET',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      }
    });
    
    const data = await response.json();
    
    console.log('User info:', data);
    console.log('Role:', data.role);
    console.log('Email verified:', data.emailVerified);
    
    return data;
  } catch (error) {
    console.error('Error fetching user info:', error);
  }
};
```

### JavaScript Example (Axios)

```javascript
import axios from 'axios';

const getUserInfo = async (token) => {
  try {
    const response = await axios.get(
      'https://api.example.com/api/v1/auth/me',
      {
        headers: {
          'Authorization': `Bearer ${token}`,
          'Accept': 'application/json'
        }
      }
    );
    
    return response.data;
  } catch (error) {
    if (error.response?.status === 401) {
      console.error('User not authenticated');
    }
    throw error;
  }
};
```

### PHP Example (Guzzle)

```php
use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'https://api.example.com',
    'headers' => [
        'Accept' => 'application/json'
    ]
]);

try {
    $response = $client->get('/api/v1/auth/me', [
        'headers' => [
            'Authorization' => 'Bearer ' . $token
        ]
    ]);
    
    $userData = json_decode($response->getBody(), true);
    
    echo "User: " . $userData['name'] . "\n";
    echo "Role: " . $userData['role'] . "\n";
    echo "Email verified: " . ($userData['emailVerified'] ? 'Yes' : 'No') . "\n";
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

---

## HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 OK | Request successful, user information returned |
| 401 Unauthorized | No valid authentication token provided |

---

## Support

For issues or questions:

- **GitHub Issues**: [Repository Issues](https://github.com/itechpanelllp/airventory-api/issues)
- **Email**: support@airventory.io

---

**Last Updated**: January 16, 2026  
**API Version**: 1.0.0  
**Author**: Copilot AI Agent
