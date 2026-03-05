# Factory Registration API Documentation

## Overview

This document describes the Factory Registration API endpoints that allow new factories to register on the platform and verify their email addresses using a 6-digit verification code system.

## Table of Contents

- [Architecture](#architecture)
- [Database Schema](#database-schema)
- [API Endpoints](#api-endpoints)
    - [Registration Endpoint](#1-factory-registration)
    - [Email Verification Endpoint](#2-email-verification)
- [Response Format](#response-format)
- [Error Handling](#error-handling)
- [Security Considerations](#security-considerations)
- [Testing](#testing)
- [Code Examples](#code-examples)

---

## Architecture

### Design Pattern

The Factory Registration API follows a **pivot table architecture** for managing factory-industry relationships:

- **Factory Model**: Extends `Authenticatable` with JWT authentication support
- **FactoryIndustry Model**: Pivot model for many-to-many relationships
- **Industry Relationship**: Uses `belongsToMany` for scalability

### Key Components

1. **Controllers**
    - `RegistrationController`: Handles factory registration
    - `AuthController`: Handles email verification

2. **Form Requests**
    - `RegistrationRequest`: Validates registration data
    - `VerifyEmailRequest`: Validates verification code

3. **Resources**
    - `FactoryResource`: Formats API responses

4. **Mail**
    - `VerificationCodeMail`: Sends verification code emails

5. **Models**
    - `Factory`: Main factory model with JWT support
    - `FactoryIndustry`: Pivot model for factory-industry relationships

---

## Database Schema

### Tables

#### 1. `factory_users`

Main table storing factory account information.

| Column                             | Type            | Attributes                  | Description                       |
| ---------------------------------- | --------------- | --------------------------- | --------------------------------- |
| id                                 | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique factory identifier         |
| first_name                         | VARCHAR(255)    | NOT NULL                    | Factory owner's first name        |
| last_name                          | VARCHAR(255)    | NOT NULL                    | Factory owner's last name         |
| email                              | VARCHAR(255)    | NOT NULL, UNIQUE            | Factory email address             |
| phone_number                       | VARCHAR(255)    | NOT NULL                    | Factory phone number              |
| password                           | VARCHAR(255)    | NOT NULL                    | Hashed password                   |
| email_verification_code            | VARCHAR(10)     | NULLABLE                    | 6-digit verification code         |
| email_verification_code_expires_at | TIMESTAMP       | NULLABLE                    | Code expiration time (15 minutes) |
| email_verified_at                  | TIMESTAMP       | NULLABLE                    | Email verification timestamp      |
| source                             | VARCHAR(255)    | DEFAULT 'registration'      | Registration source               |
| account_status                     | TINYINT         | DEFAULT 1                   | Account status                    |
| created_at                         | TIMESTAMP       |                             | Record creation time              |
| updated_at                         | TIMESTAMP       |                             | Last update time                  |
| deleted_at                         | TIMESTAMP       | NULLABLE                    | Soft delete timestamp             |

#### 2. `factory_industries`

Pivot table for factory-industry relationships (many-to-many).

| Column              | Type            | Attributes                  | Description                      |
| ------------------- | --------------- | --------------------------- | -------------------------------- |
| id                  | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique identifier                |
| factory_id          | BIGINT UNSIGNED | FOREIGN KEY, NOT NULL       | References factory_users.id      |
| catalog_industry_id | BIGINT UNSIGNED | FOREIGN KEY, NOT NULL       | References catalog_industries.id |

**Constraints:**

- UNIQUE constraint on `(factory_id, catalog_industry_id)`
- CASCADE delete on both foreign keys
- No timestamps

---

## API Endpoints

### Base URL

```
/api/v1/factories
```

### 1. Factory Registration

**Endpoint:** `POST /api/v1/factories/register`

**Description:** Creates a new factory account and sends a 6-digit verification code to the provided email address.

#### Request

**Headers:**

```
Content-Type: application/json
Accept: application/json
```

**Body:**

```json
{
    "firstname": "John",
    "lastname": "Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "password": "password123",
    "password_confirmation": "password123",
    "industry_id": 1
}
```

**Parameters:**

| Parameter             | Type    | Required | Validation                   | Description                     |
| --------------------- | ------- | -------- | ---------------------------- | ------------------------------- |
| firstname             | string  | Yes      | max:255                      | Factory owner's first name      |
| lastname              | string  | Yes      | max:255                      | Factory owner's last name       |
| email                 | string  | Yes      | email, unique:factory_users  | Valid email address             |
| phone                 | string  | Yes      | string                       | Phone number                    |
| password              | string  | Yes      | min:8, confirmed             | Password (minimum 8 characters) |
| password_confirmation | string  | Yes      | min:8                        | Password confirmation           |
| industry_id           | integer | Yes      | exists:catalog_industries,id | Valid industry ID               |

#### Success Response

**Status Code:** `201 Created`

```json
{
    "success": true,
    "data": {
        "factory": {
            "id": 1,
            "firstname": "John",
            "lastname": "Doe",
            "email": "john@example.com",
            "phone": "+1234567890",
            "industry_id": 1,
            "email_verified_at": null,
            "created_at": "2025-12-20T14:00:00.000000Z",
            "updated_at": "2025-12-20T14:00:00.000000Z"
        }
    },
    "message": "Registration successful. Please check your email for verification code."
}
```

#### Error Responses

**Validation Error (422):**

```json
{
    "message": "The email has already been taken.",
    "errors": {
        "email": ["This email is already registered."]
    }
}
```

**Server Error (500):**

```json
{
    "success": false,
    "data": null,
    "message": "Registration failed, please try again later."
}
```

#### Process Flow

1. Validate request data
2. Generate 6-digit verification code (100000-999999)
3. Create factory record in database
4. Associate factory with industry via pivot table
5. Queue verification email
6. Commit database transaction
7. Return success response

---

### 2. Email Verification

**Endpoint:** `POST /api/v1/factories/verify-email`

**Description:** Verifies the factory's email address using the 6-digit code sent during registration.

#### Request

**Headers:**

```
Content-Type: application/json
Accept: application/json
```

**Body:**

```json
{
    "email": "john@example.com",
    "code": "123456"
}
```

**Parameters:**

| Parameter | Type   | Required | Validation                  | Description               |
| --------- | ------ | -------- | --------------------------- | ------------------------- |
| email     | string | Yes      | email, exists:factory_users | Registered email address  |
| code      | string | Yes      | string, size:6              | 6-digit verification code |

#### Success Response

**Status Code:** `200 OK`

```json
{
    "success": true,
    "data": {
        "factory": {
            "id": 1,
            "email_verified_at": "2025-12-20T14:05:00.000000Z"
        }
    },
    "message": "Email verified successfully"
}
```

#### Error Responses

**Factory Not Found (422):**

```json
{
    "success": false,
    "data": null,
    "message": "Factory not found."
}
```

**Invalid Code (422):**

```json
{
    "success": false,
    "data": null,
    "message": "Invalid verification code."
}
```

**Expired Code (422):**

```json
{
    "success": false,
    "data": null,
    "message": "Verification code has expired."
}
```

**Already Verified (200):**

```json
{
    "success": true,
    "data": {
        "factory": {
            "id": 1,
            "email_verified_at": "2025-12-20T14:05:00.000000Z"
        }
    },
    "message": "Email already verified."
}
```

#### Process Flow

1. Validate request data
2. Acquire database lock on factory record
3. Check if factory exists
4. Check if email already verified
5. Validate verification code
6. Check code expiration (15 minutes)
7. Update `email_verified_at` timestamp
8. Clear verification code and expiry
9. Commit transaction
10. Return success response

---

## Response Format

All API endpoints follow a standardized response format:

### Success Response Structure

```json
{
    "success": true,
    "data": {
        // Response data
    },
    "message": "Success message"
}
```

### Error Response Structure

```json
{
    "success": false,
    "data": null,
    "message": "Error message"
}
```

### HTTP Status Codes

| Code | Constant                             | Usage                               |
| ---- | ------------------------------------ | ----------------------------------- |
| 200  | Response::HTTP_OK                    | Successful operation                |
| 201  | Response::HTTP_CREATED               | Resource created successfully       |
| 422  | Response::HTTP_UNPROCESSABLE_ENTITY  | Validation or business logic errors |
| 500  | Response::HTTP_INTERNAL_SERVER_ERROR | Server errors                       |

---

## Error Handling

### Database Transactions

All operations use database transactions with proper rollback on failure:

```php
DB::beginTransaction();
try {
    // Operation
    DB::commit();
} catch (\Throwable $e) {
    DB::rollBack();
    // Error response
}
```

### Row Locking

Email verification uses pessimistic locking to prevent race conditions:

```php
$factory = Factory::where('email', $request->email)
    ->lockForUpdate()
    ->first();
```

### Debug Mode

When `app.debug` is enabled, detailed error messages are returned:

```json
{
    "success": false,
    "data": null,
    "message": "Registration failed: SQLSTATE[23000] ..."
}
```

In production, generic messages are returned for security.

---

## Security Considerations

### 1. Password Hashing

Passwords are automatically hashed using Laravel's `Hash::make()` via model mutator:

```php
public function setPasswordAttribute($value)
{
    if ($value && strlen($value) > 0) {
        $this->attributes['password'] = Hash::make($value);
    }
}
```

### 2. Verification Code Generation

Codes are generated using cryptographically secure random integers:

```php
$verificationCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
```

### 3. Code Expiration

Verification codes expire after 15 minutes:

```php
'email_verification_code_expires_at' => Carbon::now()->addMinutes(15)
```

### 4. Email Uniqueness

Email addresses are validated as unique in the database:

```php
'email' => 'required|email|unique:factory_users,email'
```

### 5. Hidden Fields

Sensitive fields are hidden from API responses:

```php
protected $hidden = ['password', 'remember_token'];
```

### 6. JWT Authentication

Factory model implements JWT authentication for future secured endpoints:

```php
class Factory extends Authenticatable implements JWTSubject
```

---

## Testing

### Test Coverage

Comprehensive test suite covers:

1. **Registration Tests**
    - ✅ Registration with valid data
    - ✅ Registration with duplicate email
    - ✅ Registration with invalid industry_id

2. **Verification Tests**
    - ✅ Verification with valid code
    - ✅ Verification with invalid code
    - ✅ Verification with expired code
    - ✅ Already verified email

### Running Tests

```bash
# Run all factory registration tests
php artisan test --filter FactoryRegistrationTest

# Run specific test
php artisan test --filter test_registration_with_valid_data_succeeds
```

### Test Example

```php
public function test_registration_with_valid_data_succeeds(): void
{
    Mail::fake();

    $industry = CatalogIndustry::create(['slug' => 'test-industry']);

    $response = $this->postJson('/api/v1/factories/register', [
        'firstname' => 'John',
        'lastname' => 'Doe',
        'email' => 'john@example.com',
        'phone' => '+1234567890',
        'password' => 'password123',
        'password_confirmation' => 'password123',
        'industry_id' => $industry->id,
    ]);

    $response->assertStatus(201)
        ->assertJson(['success' => true]);

    Mail::assertQueued(VerificationCodeMail::class);
}
```

---

## Code Examples

### cURL Examples

#### Registration

```bash
curl -X POST http://localhost:8000/api/v1/factories/register \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "firstname": "John",
    "lastname": "Doe",
    "email": "john@example.com",
    "phone": "+1234567890",
    "password": "password123",
    "password_confirmation": "password123",
    "industry_id": 1
  }'
```

#### Email Verification

```bash
curl -X POST http://localhost:8000/api/v1/factories/verify-email \
  -H "Content-Type: application/json" \
  -H "Accept: application/json" \
  -d '{
    "email": "john@example.com",
    "code": "123456"
  }'
```

### JavaScript Examples

#### Registration (Fetch API)

```javascript
const registerFactory = async () => {
    try {
        const response = await fetch(
            "http://localhost:8000/api/v1/factories/register",
            {
                method: "POST",
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                },
                body: JSON.stringify({
                    firstname: "John",
                    lastname: "Doe",
                    email: "john@example.com",
                    phone: "+1234567890",
                    password: "password123",
                    password_confirmation: "password123",
                    industry_id: 1,
                }),
            },
        );

        const data = await response.json();

        if (data.success) {
            console.log("Registration successful:", data.message);
            // Redirect to verification page
        } else {
            console.error("Registration failed:", data.message);
        }
    } catch (error) {
        console.error("Network error:", error);
    }
};
```

#### Email Verification (Axios)

```javascript
import axios from "axios";

const verifyEmail = async (email, code) => {
    try {
        const response = await axios.post(
            "http://localhost:8000/api/v1/factories/verify-email",
            {
                email: email,
                code: code,
            },
            {
                headers: {
                    "Content-Type": "application/json",
                    Accept: "application/json",
                },
            },
        );

        if (response.data.success) {
            console.log("Email verified successfully");
            // Redirect to dashboard or login
        }
    } catch (error) {
        if (error.response) {
            console.error("Verification failed:", error.response.data.message);
        }
    }
};
```

### PHP Examples

#### Registration (Guzzle)

```php
use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'http://localhost:8000',
    'headers' => [
        'Accept' => 'application/json',
        'Content-Type' => 'application/json'
    ]
]);

try {
    $response = $client->post('/api/v1/factories/register', [
        'json' => [
            'firstname' => 'John',
            'lastname' => 'Doe',
            'email' => 'john@example.com',
            'phone' => '+1234567890',
            'password' => 'password123',
            'password_confirmation' => 'password123',
            'industry_id' => 1
        ]
    ]);

    $data = json_decode($response->getBody(), true);

    if ($data['success']) {
        echo "Registration successful: " . $data['message'];
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

---

## Email Template

The verification email is sent using a Markdown template located at:
`resources/views/emails/factory/verification_code.blade.php`

### Email Content

```
Hey {Name} 👋

Welcome to Airventory Factory!
We're excited to have you onboard.

Please use the following verification code to verify your email address:

┌─────────────┐
│   123456    │
└─────────────┘

This code will expire in 15 minutes.

If you didn't create this account, no worries — you can safely ignore this email.

Thanks,
Team Airventory
```

---

## File Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   │           └── Factory/
│   │               ├── RegistrationController.php
│   │               └── AuthController.php
│   ├── Requests/
│   │   └── Api/
│   │       └── V1/
│   │           └── Factory/
│   │               ├── RegistrationRequest.php
│   │               └── VerifyEmailRequest.php
│   └── Resources/
│       └── Api/
│           └── V1/
│               └── Factory/
│                   └── FactoryResource.php
├── Mail/
│   └── Factory/
│       └── VerificationCodeMail.php
└── Models/
    └── Factory/
        ├── Factory.php
        └── FactoryIndustry.php

database/
└── migrations/
    ├── 2025_12_20_132400_add_email_verification_columns_to_factory_users_table.php
    └── 2025_12_20_143200_create_factory_industries_table.php

resources/
└── views/
    └── emails/
        └── factory/
            └── verification_code.blade.php

routes/
└── api.php

tests/
└── Feature/
    └── FactoryRegistrationTest.php
```

---

## Future Enhancements

### Planned Features

1. **Multi-Industry Support**
    - Allow factories to register under multiple industries
    - The pivot table architecture already supports this

2. **Resend Verification Code**
    - Add endpoint to resend verification codes
    - Implement rate limiting

3. **Social Login Integration**
    - OAuth support for Google, LinkedIn
    - Merge with email-based accounts

4. **Two-Factor Authentication**
    - Optional 2FA for enhanced security
    - SMS or authenticator app support

5. **Account Recovery**
    - Password reset functionality
    - Account deactivation/reactivation

### Scalability Considerations

- **Queue Processing**: Verification emails are queued for better performance
- **Database Indexing**: Proper indexes on email and industry_id columns
- **Caching**: Consider caching industry lists for faster lookups
- **Rate Limiting**: Implement rate limiting on registration endpoint

---

## Support

For issues or questions:

- **GitHub Issues**: [Repository Issues](https://github.com/itechpanelllp/airventory-api/issues)
- **Email**: support@airventory.io

---

## Changelog

### Version 1.0.0 (2025-12-20)

- Initial implementation of factory registration API
- Email verification with 6-digit codes
- Pivot table architecture for factory-industry relationships
- Standardized API response format
- Comprehensive test coverage
- JWT authentication support

---

**Last Updated**: December 20, 2025  
**API Version**: 1.0.0  
**Author**: Copilot AI Agent
