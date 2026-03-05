# Factory Business Information API Documentation

## Overview

This document describes the API endpoints for managing factory business information during the registration completion process. These endpoints are used to complete Step 1 of factory onboarding when `account_verified` status is "pending".

**Important Authorization Rules:**
- **Factory users** can only update business information if their account is NOT verified (i.e., `account_verified` is rejected, pending, hold, or processing)
- **Admin users** can update business information anytime, regardless of verification status

## Base URL

All endpoints are prefixed with `/api/v1/factories`

## Authentication

All endpoints require JWT authentication with a valid factory token.

```
Authorization: ******
```

---

## Endpoints

### 1. Store/Update Business Information

Stores or updates the factory's business information. This is the first step in completing factory registration when the account verification status is pending.

**Endpoint:** `POST /api/v1/factories/business-information`

**Authentication:** Required (Factory JWT)

**Request Headers:**
```
Authorization: ******
Content-Type: multipart/form-data
Accept: application/json
```

**Request Body (Form Data):**

| Field | Type | Required | Description |
|-------|------|----------|-------------|
| factory_id | integer | **Yes (Admin only)** | Factory ID when admin is updating (must exist in factory_users table) |
| company_name | string | Yes | Company/business name (max 255 chars) |
| registration_number | string | No | Business registration number (max 55 chars) |
| tax_vat_number | string | No | Tax/VAT number (max 55 chars) |
| registered_address | string | Yes | Registered business address (max 255 chars) |
| country_id | integer | Yes | Country ID (must exist in countries table) |
| state_id | integer | No | State/region ID (must exist in states table) |
| city | string | Yes | City name (max 255 chars) |
| postal_code | string | Yes | ZIP/Postal code (max 10 chars) |
| registration_certificate | file | No | Registration certificate (PDF, JPG, JPEG, PNG, max 5MB) |
| tax_certificate | file | No | Tax certificate (PDF, JPG, JPEG, PNG, max 5MB) |
| import_export_certificate | file | No | Import/Export certificate (PDF, JPG, JPEG, PNG, max 5MB) |

**Note:** 
- **Factory users**: factory_id is automatically retrieved from authentication token
- **Admin users**: factory_id must be provided in the request body

**Success Response (200 OK):**
```json
{
  "success": true,
  "data": {
    "business": {
      "id": 1,
      "company_name": "ITechPanel LLP",
      "registration_number": "REG123456",
      "tax_vat_number": "VAT789012",
      "registered_address": "123 Main Street, Jaipur, Rajasthan",
      "country_id": 1,
      "state_id": 1,
      "city": "Jaipur",
      "postal_code": "302020",
      "registration_certificate": "factory/certificates/abc123.pdf",
      "tax_certificate": "factory/certificates/def456.pdf",
      "import_export_certificate": "factory/certificates/ghi789.pdf"
    }
  },
  "message": "Business information saved successfully."
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

- **403 Forbidden** - Account already verified (factory users only)
```json
{
  "success": false,
  "data": null,
  "message": "Business information cannot be updated after account verification."
}
```

- **422 Unprocessable Entity** - Validation error
```json
{
  "message": "The company name field is required. (and 3 more errors)",
  "errors": {
    "company_name": ["Company name is required."],
    "registered_address": ["Registered address is required."],
    "country_id": ["Country is required."],
    "city": ["City is required."]
  }
}
```

**Notes:**
- If business information already exists for the factory, it will be updated (if allowed)
- **Factory users** can only update if `account_verified` is NOT 1 (verified)
- **Admin users** can update anytime regardless of verification status
- Upon successful save, `basic_info_status = 1` is set in factory_metas table
- Files are stored in the `storage/app/public/factory/certificates` directory
- Maximum file size for certificates is 5MB
- Accepted file formats: PDF, JPG, JPEG, PNG

---

### 2. Get Business Information

Retrieves the factory's business information.

**Endpoint:** `GET /api/v1/factories/business-information`

**Authentication:** Required (Factory or Admin JWT)

**Request Headers:**
```
Authorization: ******
Accept: application/json
```

**Query Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| factory_id | integer | **Yes (Admin only)** | Factory ID when admin is retrieving data |

**Note:** 
- **Factory users**: factory_id is automatically retrieved from authentication token (no query parameter needed)
- **Admin users**: factory_id must be provided as query parameter

**Request Body:** None

**Success Response (200 OK) - With Data:**
```json
{
  "success": true,
  "data": {
    "business": {
      "id": 1,
      "company_name": "ITechPanel LLP",
      "registration_number": "REG123456",
      "tax_vat_number": "VAT789012",
      "registered_address": "123 Main Street",
      "country_id": 1,
      "state_id": 1,
      "city": "Jaipur",
      "postal_code": "302020",
      "registration_certificate": "factory/certificates/abc123.pdf",
      "tax_certificate": "factory/certificates/def456.pdf",
      "import_export_certificate": "factory/certificates/ghi789.pdf"
    }
  },
  "message": "Business information retrieved successfully."
}
```

**Success Response (200 OK) - No Data:**
```json
{
  "success": true,
  "data": null,
  "message": "No business information found."
}
```

**Error Responses:**

- **400 Bad Request** - Admin didn't provide factory_id (GET only)
```json
{
  "success": false,
  "data": null,
  "message": "Factory ID is required for admin users."
}
```

- **401 Unauthorized** - Not authenticated
```json
{
  "success": false,
  "data": null,
  "message": "Unauthenticated."
}
```

- **404 Not Found** - Factory not found (when admin provides invalid factory_id)
```json
{
  "success": false,
  "data": null,
  "message": "Factory not found."
}
```

---

## Registration Flow

### Step 1: Business Information

1. Factory user logs in or verifies email (receives JWT token)
2. User submits business information via POST `/api/v1/factories/business-information`
3. System validates and stores the information
4. System sets `basic_info_status = 1` in factory_metas table
5. User can proceed to next registration step

### Checking Completion Status

Use the `/api/v1/auth/me` endpoint to check if basic info is completed:

```bash
curl -X GET https://api.example.com/api/v1/auth/me \
  -H "Authorization: ******"
```

Then check factory_metas for `basic_info_status = 1`.

---

## Example Usage

### JavaScript (Fetch API)

```javascript
const submitBusinessInfo = async (token, formData) => {
  try {
    const response = await fetch('https://api.example.com/api/v1/factories/business-information', {
      method: 'POST',
      headers: {
        'Authorization': `Bearer ${token}`,
        'Accept': 'application/json'
      },
      body: formData // FormData object with files and fields
    });
    
    const data = await response.json();
    
    if (data.success) {
      console.log('Business information saved:', data.data.business);
      // Proceed to next step
    } else {
      console.error('Error:', data.message);
    }
  } catch (error) {
    console.error('Network error:', error);
  }
};

// Usage
const formData = new FormData();
formData.append('company_name', 'ITechPanel LLP');
formData.append('registered_address', '123 Main Street');
formData.append('country_id', '1');
formData.append('city', 'Jaipur');
formData.append('postal_code', '302020');
formData.append('registration_certificate', fileInput.files[0]);

submitBusinessInfo(token, formData);
```

### cURL

```bash
curl -X POST https://api.example.com/api/v1/factories/business-information \
  -H "Authorization: ******" \
  -H "Accept: application/json" \
  -F "company_name=ITechPanel LLP" \
  -F "registration_number=REG123456" \
  -F "tax_vat_number=VAT789012" \
  -F "registered_address=123 Main Street" \
  -F "country_id=1" \
  -F "state_id=1" \
  -F "city=Jaipur" \
  -F "postal_code=302020" \
  -F "registration_certificate=@/path/to/registration.pdf" \
  -F "tax_certificate=@/path/to/tax.pdf" \
  -F "import_export_certificate=@/path/to/import_export.pdf"
```

---

## Testing

Comprehensive test suite available in `tests/Feature/FactoryBusinessInformationTest.php` covering:
- Successful business information storage
- Business information retrieval
- Update existing business information
- Validation errors
- Authentication requirements
- File upload handling

### Running Tests

```bash
# Run business information tests
php artisan test --filter FactoryBusinessInformationTest
```

---

## File Storage

Uploaded certificates are stored using Laravel's storage system:

- **Storage Path:** `storage/app/public/factory/certificates/`
- **Public URL:** Files can be accessed via the storage link
- **File Naming:** Automatically generated unique names

To create the storage symlink (if not already created):
```bash
php artisan storage:link
```

---

## Security Considerations

1. **Authentication:** All endpoints require valid factory JWT token
2. **Authorization:** 
   - Factory users can only update business information if account is not verified (account_verified != 1)
   - Admin users can update anytime regardless of verification status
3. **File Validation:** File types and sizes are strictly validated
4. **File Storage:** Files are stored in a secure directory
5. **Database Transactions:** All operations use database transactions
6. **One Record Per Factory:** UpdateOrCreate ensures one business record per factory

---

## HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 OK | Request successful |
| 401 Unauthorized | Invalid or missing authentication token |
| 403 Forbidden | Factory account already verified (cannot update) |
| 422 Unprocessable Entity | Validation error |
| 500 Internal Server Error | Server error |

---

**Last Updated**: January 16, 2026  
**API Version**: 1.0.0  
**Author**: Copilot AI Agent
