# Admin JWT Authentication

This document describes how to use JWT authentication for admin users in the Airventory API.

## Overview

The admin authentication system uses a **session-first approach** with optional JWT tokens for API access:

1. **Session-based Authentication** (Primary) - Admins login via `/admin/login` to access the admin panel
2. **JWT Token Generation** (Secondary) - After session login, admins can mint JWT tokens for API access

This approach ensures that all admin authentication goes through the secure session-based login, while providing JWT tokens for SPAs, mobile apps, and API integrations.

## Authentication Flow

1. Admin logs in via the traditional session-based login at `/admin/login`
2. After successful login, admin can request a JWT token via `/api/v1/admin/mint-token`
3. Admin uses the JWT token to access protected API endpoints
4. Token can be refreshed or invalidated as needed

## Setup

### 1. Generate JWT Secret

First, generate a JWT secret key:

```bash
php artisan jwt:secret
```

This will add `JWT_SECRET` to your `.env` file.

### 2. Environment Configuration

Ensure your `.env` file contains the following JWT configuration:

```env
JWT_SECRET=your_generated_secret_here
JWT_TTL=60                    # Token lifetime in minutes (default: 60)
JWT_REFRESH_TTL=20160         # Refresh token lifetime in minutes (default: 2 weeks)
JWT_ALGO=HS256                # Hashing algorithm
JWT_BLACKLIST_ENABLED=true    # Enable token blacklisting for logout
```

## API Endpoints

All JWT-related endpoints are prefixed with `/api/v1/admin`. **Note:** There is no separate JWT login endpoint. Admins must first login via the session-based `/admin/login` endpoint.

### 1. Mint Token (Requires Session Authentication)

Generate a JWT token for an admin who is already logged in via session.

**Endpoint:** `POST /api/v1/admin/mint-token`

**Prerequisites:**

-   Admin must be logged in via `/admin/login` (session-based)
-   Request must include valid session cookie

**Request Headers:**

```
Cookie: laravel_session=your_session_cookie
```

**Success Response (200):**

```json
{
    "success": true,
    "message": "JWT token generated successfully",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
}
```

**Error Response:**

-   `401 Unauthorized` - Not authenticated via session

### 2. Get Current Admin (Protected)

Get details of the currently authenticated admin.

**Endpoint:** `GET /api/v1/admin/me`

**Headers:**

```
Authorization: Bearer {your_jwt_token}
```

**Success Response (200):**

```json
{
    "success": true,
    "admin": {
        "id": 1,
        "name": "Admin Name",
        "email": "admin@example.com",
        "username": "admin",
        "user_type": "admin",
        "mobile": "1234567890"
    }
}
```

**Error Response:**

-   `401 Unauthorized` - Invalid or missing token

### 3. Refresh Token (Protected)

Generate a new JWT token using the current token.

**Endpoint:** `POST /api/v1/admin/refresh`

**Headers:**

```
Authorization: Bearer {your_jwt_token}
```

**Success Response (200):**

```json
{
    "success": true,
    "message": "Token refreshed successfully",
    "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9..."
}
```

**Error Responses:**

-   `401 Unauthorized` - Invalid, expired, or missing token

### 4. Logout (Protected)

Invalidate the current JWT token and terminate related admin sessions.

**Endpoint:**

-   `POST /api/v1/admin/logout` (recommended)
-   `GET /api/v1/admin/logout` (for compatibility with some clients)

**Authentication:**

-   JWT token via `Authorization: Bearer` header, or
-   `jwt_token` cookie (used as a fallback when the header is missing)

**Headers (header-based auth):**

```
Authorization: Bearer {your_jwt_token}
Accept: application/json
X-Requested-With: XMLHttpRequest
```

**Success Response (200):**

```json
{
    "success": true,
    "message": "Successfully logged out"
}
```

On success, the server also instructs the browser to clear the `jwt_token` cookie.

**Error Responses:**

-   `401 Unauthorized`
    -   No valid JWT presented (missing/invalid/expired token and no valid cookie)
-   `500 Internal Server Error`
    -   Partial failure when terminating sessions across different guards or clearing session data

## Usage Examples

### JavaScript/Fetch Example

```javascript
// Step 1: Login via session (on admin panel login page)
// This is handled by the existing /admin/login endpoint

// Step 2: Mint JWT token after session login
async function mintAdminToken() {
    const response = await fetch("/api/v1/admin/mint-token", {
        method: "POST",
        headers: {
            Accept: "application/json",
            "X-Requested-With": "XMLHttpRequest", // Important for Laravel to recognize as AJAX
        },
        credentials: "include", // Include session cookie
    });

    const data = await response.json();

    if (data.success) {
        // Store token in localStorage or secure storage
        localStorage.setItem("admin_token", data.token);
        return data.token;
    } else {
        throw new Error(data.message);
    }
}

// Get current admin
async function getCurrentAdmin() {
    const token = localStorage.getItem("admin_token");

    const response = await fetch("/api/v1/admin/me", {
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: "application/json",
        },
    });

    const data = await response.json();
    return data.admin;
}

// Logout
async function adminLogout() {
    const token = localStorage.getItem("admin_token");

    await fetch("/api/v1/admin/logout", {
        method: "POST",
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: "application/json",
        },
    });

    localStorage.removeItem("admin_token");
}
```

### jQuery AJAX Example (for Blade views)

If you're using jQuery in your admin panel Blade views:

```javascript
// Mint JWT token after session login
function mintAdminToken() {
    return $.ajax({
        url: "/api/v1/admin/mint-token",
        type: "POST",
        headers: {
            "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
            Accept: "application/json",
        },
        success: function (response) {
            if (response.success) {
                localStorage.setItem("admin_token", response.token);
                console.log("Token minted successfully");
                return response.token;
            }
        },
        error: function (xhr) {
            console.error("Failed to mint token:", xhr.responseJSON);
            alert("Failed to generate API token");
        },
    });
}

// Usage in your admin panel
$(document).ready(function () {
    // Call this after successful login or when needed
    $("#mint-token-btn").click(function () {
        mintAdminToken();
    });
});
```

### Axios Example (for Vue.js/React in admin panel)

```javascript
// Configure axios to include credentials
axios.defaults.withCredentials = true;
axios.defaults.headers.common["X-Requested-With"] = "XMLHttpRequest";

// Mint JWT token
async function mintAdminToken() {
    try {
        const response = await axios.post(
            "/api/v1/admin/mint-token",
            {},
            {
                headers: {
                    Accept: "application/json",
                },
            }
        );

        if (response.data.success) {
            localStorage.setItem("admin_token", response.data.token);
            return response.data.token;
        }
    } catch (error) {
        console.error("Error minting token:", error.response?.data);
        throw error;
    }
}
```

### cURL Examples

```bash
# Step 1: Login via session (handled by admin panel)
# Visit /admin/login in browser

# Step 2: Mint JWT token (after session login)
curl -X POST http://your-domain.com/api/v1/admin/mint-token \
  -H "Accept: application/json" \
  -b "laravel_session=YOUR_SESSION_COOKIE"

# Step 3: Use JWT token for API access
# Get current admin
curl -X GET http://your-domain.com/api/v1/admin/me \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"

# Refresh token
curl -X POST http://your-domain.com/api/v1/admin/refresh \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"

# Logout
curl -X POST http://your-domain.com/api/v1/admin/logout \
  -H "Authorization: Bearer YOUR_TOKEN_HERE" \
  -H "Accept: application/json"
```

### Next.js/React Example

```typescript
// lib/auth.ts
const API_BASE = process.env.NEXT_PUBLIC_API_URL;

// Mint token after admin has logged in via session
export async function mintToken() {
    const response = await fetch(`${API_BASE}/api/v1/admin/mint-token`, {
        method: "POST",
        credentials: "include", // Include session cookie
    });

    const data = await response.json();

    if (!data.success) {
        throw new Error(data.message);
    }

    return data.token;
}

export async function getMe(token: string) {
    const response = await fetch(`${API_BASE}/api/v1/admin/me`, {
        headers: {
            Authorization: `Bearer ${token}`,
        },
    });

    const data = await response.json();

    if (!data.success) {
        throw new Error("Unauthorized");
    }

    return data.admin;
}
```

## Session-Based Login (Required First Step)

All admins must first login via the traditional session-based admin panel:

-   **Login Page:** `/admin/login`
-   **Login Endpoint:** `POST /admin/login` (session-based)
-   **Dashboard:** `/admin/dashboard`
-   **Logout:** `GET /admin/logout`

After session login, admins can mint JWT tokens for API access via `/api/v1/admin/mint-token`.

## Security Considerations

1. **Session-First Approach:**

    - All authentication must go through the secure session-based login
    - JWT tokens can only be minted after successful session authentication
    - This ensures centralized authentication and audit logging

2. **Token Storage:**

    - Store JWT tokens securely (httpOnly cookies for web, secure storage for mobile)
    - Never expose tokens in URLs or logs

3. **Token Expiration:**

    - Default token lifetime is 60 minutes
    - Use the refresh endpoint to obtain new tokens
    - Refresh tokens are valid for 2 weeks by default

4. **Token Blacklisting:**

    - Logout invalidates tokens via blacklist
    - Ensure `JWT_BLACKLIST_ENABLED=true` in production

5. **HTTPS:**
    - Always use HTTPS in production to prevent token interception

## Troubleshooting

### "Unauthenticated" error when minting token via AJAX

This is a common issue when calling `/api/v1/admin/mint-token` from the admin panel via AJAX. Here are the solutions:

**For Fetch API:**

```javascript
fetch("/api/v1/admin/mint-token", {
    method: "POST",
    headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest", // Important!
    },
    credentials: "include", // Must include to send session cookie
});
```

**For jQuery:**

```javascript
$.ajax({
    url: "/api/v1/admin/mint-token",
    type: "POST",
    headers: {
        "X-CSRF-TOKEN": $('meta[name="csrf-token"]').attr("content"),
        Accept: "application/json",
    },
    // jQuery automatically includes credentials
});
```

**For Axios:**

```javascript
axios.defaults.withCredentials = true; // Enable credentials globally
axios.post(
    "/api/v1/admin/mint-token",
    {},
    {
        headers: {
            Accept: "application/json",
        },
    }
);
```

**Important points:**

-   The request MUST include credentials (session cookie)
-   Make sure you're already logged in via `/admin/login`
-   Ensure your CSRF token is valid (for POST requests)
-   The `web` middleware is applied to the mint-token route to enable session handling

### "Unauthenticated" error when minting token (general)

-   Ensure you're logged in via `/admin/login` first
-   Check that your session cookie is valid
-   Verify session hasn't expired
-   Clear browser cookies and login again if issue persists

### "Invalid credentials" error

-   This error should only occur during session login at `/admin/login`
-   Verify email and password are correct
-   Check that the user exists in the `users` table

### "Unauthenticated" error on protected routes

-   Verify token is included in Authorization header
-   Check token hasn't expired
-   Ensure token format is: `Bearer {token}`
-   Try refreshing the token
-   If all else fails, mint a new token via `/api/v1/admin/mint-token`

### Token refresh fails

-   Token may have exceeded refresh window
-   Mint a new token via `/api/v1/admin/mint-token`
-   Check `JWT_REFRESH_TTL` configuration

## Testing

Run the admin JWT authentication test suite:

```bash
php artisan test --filter AdminJWTAuthenticationTest
```

## Additional Resources

-   [tymon/jwt-auth Documentation](https://github.com/tymondesigns/jwt-auth)
-   [Laravel Authentication Documentation](https://laravel.com/docs/authentication)
-   [JWT.io - JWT Debugger](https://jwt.io/)
