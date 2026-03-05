# Factory Authentication API Documentation

This document describes the authentication endpoints for the Factory module.

## Base URL
All endpoints are prefixed with `/api/v1/factories`

## Authentication
Most endpoints require JWT authentication. Include the token in the Authorization header:
```
Authorization: Bearer {your_jwt_token}
```

---

## Endpoints

### 1. Login

Authenticates a factory user and returns a JWT token.

**Endpoint:** `POST /api/v1/factories/login`

**Authentication:** Not required

**Request Body:**
```json
{
  "email": "factory@example.com",
  "password": "password123",
  "remember": false
}
```

**Parameters:**
- `email` (string, required): Factory email address
- `password` (string, required): Password (minimum 8 characters)
- `remember` (boolean, optional): If true, extends token lifetime

**Success Response (200 OK):**
```json
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
  "factory": {
    "name": "John Doe",
    "email": "factory@example.com"
  }
}
```

**Error Responses:**

- **401 Unauthorized** - Invalid credentials
```json
{
  "message": "Invalid credentials."
}
```

- **403 Forbidden** - Email not verified
```json
{
  "message": "Email address not verified. Please verify your email before logging in."
}
```

- **403 Forbidden** - Account inactive
```json
{
  "message": "Your account is inactive. Please contact support."
}
```

- **429 Too Many Requests** - Rate limit exceeded
```json
{
  "message": "Too many login attempts. Please try again in 60 seconds."
}
```

**Rate Limiting:** 6 attempts per minute per email/IP combination

---

### 2. Logout

Invalidates the current JWT token.

**Endpoint:** `POST /api/v1/factories/logout`

**Authentication:** Required (JWT)

**Request Body:** None

**Success Response (200 OK):**
```json
{
  "message": "Successfully logged out."
}
```

---

### 3. Verify Email

Verifies a factory user's email address using a verification code and automatically logs them in by returning a JWT token.

**Endpoint:** `POST /api/v1/factories/verify-email`

**Authentication:** Not required

**Request Body:**
```json
{
  "email": "factory@example.com",
  "code": "123456"
}
```

**Parameters:**
- `email` (string, required): Factory email address
- `code` (string, required): 6-digit verification code sent via email

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "factory": {
      "name": "John Doe",
      "email": "factory@example.com"
    }
  },
  "message": "Email verified successfully"
}
```

**Success Response (200 OK) - Already Verified:**
```json
{
  "success": true,
  "data": {
    "token": "eyJ0eXAiOiJKV1QiLCJhbGc...",
    "factory": {
      "name": "John Doe",
      "email": "factory@example.com"
    }
  },
  "message": "Email already verified."
}
```

**Error Responses:**

- **422 Unprocessable Entity** - Factory not found
```json
{
  "success": false,
  "data": null,
  "message": "Factory not found."
}
```

- **422 Unprocessable Entity** - Invalid verification code
```json
{
  "success": false,
  "data": null,
  "message": "Invalid verification code."
}
```

- **422 Unprocessable Entity** - Verification code expired
```json
{
  "success": false,
  "data": null,
  "message": "Verification code has expired."
}
```

**Note:** After successful email verification, the user is automatically logged in and receives a JWT token for immediate access. The verification code expires in 15 minutes from generation.

---

### 4. Resend OTP

Resends the email verification code (OTP) to an unverified factory email address.

**Endpoint:** `POST /api/v1/factories/resend-otp`

**Authentication:** Not required

**Request Body:**
```json
{
  "email": "factory@example.com"
}
```

**Parameters:**
- `email` (string, required): Factory email address (must exist and not be verified)

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": null,
  "message": "Verification code has been resent to your email."
}
```

**Error Responses:**

- **422 Unprocessable Entity** - Email already verified
```json
{
  "success": false,
  "data": null,
  "message": "Email is already verified."
}
```

- **422 Unprocessable Entity** - Email not found
```json
{
  "message": "The email field is invalid.",
  "errors": {
    "email": ["No factory account found with this email address."]
  }
}
```

- **422 Unprocessable Entity** - Invalid email format
```json
{
  "message": "The email field must be a valid email address.",
  "errors": {
    "email": ["The email field must be a valid email address."]
  }
}
```

**Note:** A new 6-digit verification code will be generated and sent to the email address. The code expires in 15 minutes.

---

### 5. Forgot Password

Sends a password reset link to the factory's email address.

**Endpoint:** `POST /api/v1/factories/forgot-password`

**Authentication:** Not required

**Request Body:**
```json
{
  "email": "factory@example.com"
}
```

**Parameters:**
- `email` (string, required): Factory email address (must exist in database)

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": null,
  "message": "We have emailed your password reset link."
}
```

**Error Responses:**

- **422 Unprocessable Entity** - Invalid email
```json
{
  "message": "The email field must be a valid email address.",
  "errors": {
    "email": ["We can't find a factory with that email address."]
  }
}
```

- **429 Too Many Requests** - Rate limit exceeded
```json
{
  "message": "The email field is invalid.",
  "errors": {
    "email": ["Too many requests. Please try again in 60 seconds."]
  }
}
```

**Rate Limiting:** 5 attempts per minute per email/IP combination

**Note:** The reset link will be sent to `{factory_panel_url}/auth/reset-password?token={token}&email={email}` and expires in 60 minutes.

---

### 6. Reset Password

Resets the factory's password using a valid reset token.

**Endpoint:** `POST /api/v1/factories/reset-password`

**Authentication:** Not required

**Request Body:**
```json
{
  "email": "factory@example.com",
  "token": "reset_token_from_email",
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

**Parameters:**
- `email` (string, required): Factory email address
- `token` (string, required): Reset token from email
- `password` (string, required): New password (minimum 8 characters)
- `password_confirmation` (string, required): Must match password

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": null,
  "message": "Your password has been updated successfully. You may now sign in using your new credentials."
}
```

**Error Responses:**

- **404 Not Found** - Factory not found
```json
{
  "success": false,
  "data": null,
  "message": "No factory found with that email."
}
```

- **422 Unprocessable Entity** - Invalid token
```json
{
  "success": false,
  "data": null,
  "message": "Invalid reset token."
}
```

- **422 Unprocessable Entity** - Expired token
```json
{
  "success": false,
  "data": null,
  "message": "Reset token has expired."
}
```

- **422 Unprocessable Entity** - Password mismatch
```json
{
  "message": "The password field confirmation does not match.",
  "errors": {
    "password": ["Password confirmation does not match."]
  }
}
```

**Rate Limiting:** 10 attempts per minute per email

**Note:** A confirmation email will be sent after successful password reset.

---

### 7. Set Password

Updates the password for an authenticated factory account.

**Endpoint:** `POST /api/v1/factories/set-password`

**Authentication:** Required (JWT)

**Request Body:**
```json
{
  "password": "newpassword123",
  "password_confirmation": "newpassword123"
}
```

**Parameters:**
- `password` (string, required): New password (minimum 8 characters)
- `password_confirmation` (string, required): Must match password

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": null,
  "message": "Password has been updated successfully."
}
```

**Error Responses:**

- **401 Unauthorized** - Not authenticated
```json
{
  "success": false,
  "data": null,
  "message": "Unauthenticated."
}
```

- **422 Unprocessable Entity** - Validation error
```json
{
  "message": "The password field confirmation does not match.",
  "errors": {
    "password": ["Password confirmation does not match."]
  }
}
```

**Note:** This endpoint allows authenticated factory users to update their password.

---

## Security Features

1. **Password Hashing:** All passwords are hashed using Laravel's Hash facade (bcrypt)
2. **Rate Limiting:** All authentication endpoints have rate limiting to prevent brute force attacks
3. **Email Verification:** Login requires verified email address
4. **Account Status Check:** Inactive accounts cannot log in
5. **JWT Tokens:** Secure token-based authentication
6. **Token Expiration:** Tokens expire based on configuration (default: 60 minutes, or 43200 minutes with remember)
7. **Password Reset Tokens:** 
   - SHA-256 hashed
   - Expire in 60 minutes
   - One-time use (cleared after successful reset)
8. **Database Transactions:** All operations use transactions for data integrity
9. **Privacy Protection:** User IDs are never exposed in authentication responses (login, verify-email)
   - Only minimal data is returned: name, email, and token
   - Use the `/api/v1/auth/me` endpoint to get additional user information
10. **OTP Security:** 
    - Verification codes expire in 15 minutes
    - Codes are cryptographically secure random numbers
    - Can be resent for unverified emails only

## Token Lifetime

- **Standard Login:** 60 minutes (configurable via JWT_TTL)
- **Remember Me:** 43200 minutes / 30 days (configurable via JWT_REMEMBER_TTL)

## HTTP Status Codes

- **200 OK:** Request successful
- **401 Unauthorized:** Invalid credentials
- **403 Forbidden:** Access denied (unverified email, inactive account)
- **404 Not Found:** Resource not found
- **422 Unprocessable Entity:** Validation error
- **429 Too Many Requests:** Rate limit exceeded
- **500 Internal Server Error:** Server error

## Getting Detailed User Information

After authentication (login or email verification), the response includes only minimal data (name, email, token). To get more detailed user information including account status, email verification status, and account verification status, use the **auth/me** endpoint.

See [AUTH_ME_API.md](./AUTH_ME_API.md) for complete documentation.

**Example:**
```bash
curl -X GET https://api.example.com/api/v1/auth/me \
  -H "Authorization: Bearer {token_from_login}"
```

## Example Usage

### Complete Password Reset Flow

1. **Request Password Reset**
```bash
curl -X POST https://api.example.com/api/v1/factories/forgot-password \
  -H "Content-Type: application/json" \
  -d '{
    "email": "factory@example.com"
  }'
```

2. **User receives email with reset link** (check email)

3. **Reset Password**
```bash
curl -X POST https://api.example.com/api/v1/factories/reset-password \
  -H "Content-Type: application/json" \
  -d '{
    "email": "factory@example.com",
    "token": "token_from_email",
    "password": "newpassword123",
    "password_confirmation": "newpassword123"
  }'
```

4. **Login with New Password**
```bash
curl -X POST https://api.example.com/api/v1/factories/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "factory@example.com",
    "password": "newpassword123"
  }'
```

5. **Use Token for Authenticated Requests**
```bash
curl -X POST https://api.example.com/api/v1/factories/logout \
  -H "Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGc..."
```

## Testing

Comprehensive test suite available in the following test files:
- `tests/Feature/FactoryAuthenticationTest.php` - Login, logout, password reset
- `tests/Feature/FactoryRegistrationTest.php` - Registration and email verification  
- `tests/Feature/FactoryResendOtpTest.php` - Resend OTP functionality

### Test Coverage
- Login scenarios (valid, invalid, unverified, inactive)
- Logout functionality
- Email verification (valid code, invalid code, expired code, already verified)
- Resend OTP (unverified email, already verified, non-existent email)
- Password reset flow
- Token validation
- Rate limiting
- Error handling

### Running Tests
```bash
# Run all factory authentication tests
php artisan test --filter FactoryAuthenticationTest

# Run all factory registration tests
php artisan test --filter FactoryRegistrationTest

# Run all resend OTP tests
php artisan test --filter FactoryResendOtpTest
```
