# Vendor Design Branding API Documentation

## Overview

This document describes the Vendor Design Branding API endpoints that allow vendors to upload, manage, and delete custom design branding assets. The API provides functionality for vendors to personalize their platform experience with custom images and visual elements.

## Table of Contents

- [Architecture](#architecture)
- [Database Schema](#database-schema)
- [API Endpoints](#api-endpoints)
  - [Upload Branding Endpoint](#1-upload-branding)
  - [Fetch Branding Endpoint](#2-fetch-branding)
  - [Delete Branding Endpoint](#3-delete-branding)
- [Response Format](#response-format)
- [Error Handling](#error-handling)
- [Security Considerations](#security-considerations)
- [Testing](#testing)
- [Code Examples](#code-examples)

---

## Architecture

### Design Pattern

The Vendor Design Branding API follows a **resource-based RESTful architecture**:

- **VendorDesignBranding Model**: Stores branding assets with metadata
- **Vendor Relationship**: Uses `belongsTo` for vendor ownership
- **JWT Authentication**: All endpoints require authenticated vendor access
- **File Storage**: Images are stored using Laravel's Storage facade with organized directory structure

### Key Components

1. **Controllers**
   - `DesignBrandingController`: Handles all branding operations (upload, fetch, delete)

2. **Form Requests**
   - `StoreVendorBrandingRequest`: Validates image upload and branding name

3. **Models**
   - `VendorDesignBranding`: Main branding model with vendor relationship

4. **Storage**
   - Organized file structure: `vendor/design-branding/{YYYY}/{MM}/{DD}/{filename}`
   - Automatic image dimension extraction
   - Cache-Control headers for optimal performance

---

## Database Schema

### Tables

#### 1. `vendor_design_branding`

Main table storing vendor branding assets and metadata.

| Column | Type | Attributes | Description |
|--------|------|------------|-------------|
| id | BIGINT UNSIGNED | PRIMARY KEY, AUTO_INCREMENT | Unique branding identifier |
| vendor_id | BIGINT UNSIGNED | FOREIGN KEY, NOT NULL | References vendors.id |
| name | VARCHAR(255) | NOT NULL | Branding asset name |
| image | TEXT | NOT NULL | File path to the branding image |
| width | UNSIGNED INT | NOT NULL | Image width in pixels |
| height | UNSIGNED INT | NOT NULL | Image height in pixels |
| created_at | TIMESTAMP | | Record creation time |
| updated_at | TIMESTAMP | | Last update time |

**Constraints:**
- FOREIGN KEY `vendor_id` references `vendors(id)` with CASCADE delete
- Automatically deletes branding when vendor is deleted

---

## API Endpoints

### Base URL

```
/api/v1/customers
```

### Authentication

All endpoints require JWT authentication with the `customer` guard. Include the JWT token in the Authorization header:

```
Authorization: Bearer {jwt_token}
```

---

### 1. Upload Branding

**Endpoint:** `POST /api/v1/customers/design-branding/upload`

**Description:** Uploads a new branding image for the authenticated vendor. The image is stored with automatic dimension extraction and organized in a date-based directory structure.

#### Request

**Headers:**
```
Content-Type: multipart/form-data
Accept: application/json
Authorization: Bearer {jwt_token}
```

**Body (Form Data):**
```
name: "Company Logo"
image: [Binary file data]
```

**Parameters:**

| Parameter | Type | Required | Validation | Description |
|-----------|------|----------|------------|-------------|
| name | string | Yes | max:255 | Descriptive name for the branding asset |
| image | file | Yes | mimes:jpg,jpeg,png,webp, max:10240 | Image file (max 10MB) |

#### Success Response

**Status Code:** `201 Created`

```json
{
  "success": true,
  "message": "Branding uploaded successfully.",
  "data": {
    "id": 1,
    "name": "Company Logo",
    "url": "http://localhost:8000/storage/vendor/design-branding/2025/12/23/company-logo-abc123.png",
    "width": 1920,
    "height": 1080
  }
}
```

#### Error Responses

**Validation Error (422):**
```json
{
  "message": "The image must be a file of type: jpg, jpeg, png or webp.",
  "errors": {
    "image": [
      "The image must be a file of type: jpg, jpeg, png or webp."
    ]
  }
}
```

**Unauthorized (401):**
```json
{
  "message": "Unauthenticated."
}
```

**Server Error (500):**
```json
{
  "success": false,
  "message": "An error occurred while uploading the branding."
}
```

#### Process Flow

1. Validate request data (name and image)
2. Extract image dimensions (width and height)
3. Generate unique filename with UUID
4. Create date-based folder structure (YYYY/MM/DD)
5. Upload file to storage with cache headers
6. Begin database transaction
7. Create branding record with metadata
8. Commit transaction
9. Return success response with branding details
10. On error: rollback transaction and delete uploaded file

---

### 2. Fetch Branding

**Endpoint:** `GET /api/v1/customers/design-branding`

**Description:** Retrieves all branding assets for the authenticated vendor, filtered by their vendor ID from the JWT token. Supports pagination and uses Redis caching for improved performance.

#### Request

**Headers:**
```
Accept: application/json
Authorization: Bearer {jwt_token}
```

**Query Parameters:**

| Parameter | Type | Required | Default | Description |
|-----------|------|----------|---------|-------------|
| page | integer | No | 1 | Page number for pagination |
| per_page | integer | No | 15 | Number of items per page (max: 100) |

**Example:**
```
GET /api/v1/customers/design-branding?page=1&per_page=20
```

#### Success Response

**Status Code:** `200 OK`

```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [
      {
        "id": 1,
        "name": "Company Logo",
        "url": "http://localhost:8000/storage/vendor/design-branding/2025/12/23/company-logo-abc123.png",
        "width": 1920,
        "height": 1080,
        "created_at": "2025-12-23T12:00:00.000000Z"
      },
      {
        "id": 2,
        "name": "Banner Image",
        "url": "http://localhost:8000/storage/vendor/design-branding/2025/12/23/banner-image-def456.jpg",
        "width": 2560,
        "height": 1440,
        "created_at": "2025-12-23T11:30:00.000000Z"
      }
    ],
    "first_page_url": "http://localhost:8000/api/v1/customers/design-branding?page=1",
    "from": 1,
    "last_page": 2,
    "last_page_url": "http://localhost:8000/api/v1/customers/design-branding?page=2",
    "links": [
      {
        "url": null,
        "label": "&laquo; Previous",
        "active": false
      },
      {
        "url": "http://localhost:8000/api/v1/customers/design-branding?page=1",
        "label": "1",
        "active": true
      },
      {
        "url": "http://localhost:8000/api/v1/customers/design-branding?page=2",
        "label": "2",
        "active": false
      },
      {
        "url": "http://localhost:8000/api/v1/customers/design-branding?page=2",
        "label": "Next &raquo;",
        "active": false
      }
    ],
    "next_page_url": "http://localhost:8000/api/v1/customers/design-branding?page=2",
    "path": "http://localhost:8000/api/v1/customers/design-branding",
    "per_page": 15,
    "prev_page_url": null,
    "to": 15,
    "total": 25
  }
}
```

**Empty Response:**
```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "data": [],
    "first_page_url": "http://localhost:8000/api/v1/customers/design-branding?page=1",
    "from": null,
    "last_page": 1,
    "last_page_url": "http://localhost:8000/api/v1/customers/design-branding?page=1",
    "links": [
      {
        "url": null,
        "label": "&laquo; Previous",
        "active": false
      },
      {
        "url": "http://localhost:8000/api/v1/customers/design-branding?page=1",
        "label": "1",
        "active": true
      },
      {
        "url": null,
        "label": "Next &raquo;",
        "active": false
      }
    ],
    "next_page_url": null,
    "path": "http://localhost:8000/api/v1/customers/design-branding",
    "per_page": 15,
    "prev_page_url": null,
    "to": null,
    "total": 0
  }
}
```

#### Error Responses

**Unauthorized (401):**
```json
{
  "message": "Unauthenticated."
}
```

**Server Error (500):**
```json
{
  "success": false,
  "message": "An error occurred while fetching the brandings."
}
```

#### Process Flow

1. Authenticate vendor via JWT token
2. Extract vendor ID from authenticated user
3. Parse pagination parameters (page, per_page)
4. Check Redis cache for cached results using vendor-specific cache key
5. If cache miss:
   - Query branding records where vendor_id matches
   - Order results by created_at descending (newest first)
   - Paginate results
   - Transform each record to include: id, name, width, height, created_at, url
   - Cache results in Redis with 5-minute TTL
6. Return paginated response with success flag

#### Caching Details

- **Cache Key Pattern:** `vendor:{vendor_id}:branding:page:{page}:per_page:{per_page}`
- **Cache TTL:** 300 seconds (5 minutes)
- **Cache Driver:** Redis
- **Cache Invalidation:** Automatic on create/delete operations
- **Cache Strategy:** The cache is cleared for common pagination combinations (first 10 pages with per_page values of 15, 20, 25, 50, 100) when a branding is created or deleted

---

### 3. Delete Branding

**Endpoint:** `DELETE /api/v1/customers/design-branding/{id}`

**Description:** Deletes a specific branding asset. Only the vendor who owns the branding can delete it, enforced by vendor ID verification.

#### Request

**Headers:**
```
Accept: application/json
Authorization: Bearer {jwt_token}
```

**URL Parameters:**

| Parameter | Type | Required | Description |
|-----------|------|----------|-------------|
| id | integer | Yes | The ID of the branding asset to delete |

#### Success Response

**Status Code:** `200 OK`

```json
{
  "success": true,
  "message": "Branding deleted successfully."
}
```

#### Error Responses

**Not Found / Unauthorized (404):**
```json
{
  "success": false,
  "message": "Branding not found or you do not have permission to delete it."
}
```

**Unauthorized (401):**
```json
{
  "message": "Unauthenticated."
}
```

**Server Error (500):**
```json
{
  "success": false,
  "message": "An error occurred while deleting the branding."
}
```

#### Process Flow

1. Authenticate vendor via JWT token
2. Extract vendor ID from authenticated user
3. Query branding record where id AND vendor_id match
4. If not found, return 404 error
5. Begin database transaction
6. Delete physical file from storage (if exists)
7. Delete database record
8. Commit transaction
9. Return success response
10. On error: rollback transaction and log error

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
  "message": "Error message"
}
```

### HTTP Status Codes

| Code | Constant | Usage |
|------|----------|-------|
| 200 | Response::HTTP_OK | Successful operation (GET, DELETE) |
| 201 | Response::HTTP_CREATED | Resource created successfully (POST) |
| 401 | Response::HTTP_UNAUTHORIZED | Authentication required or failed |
| 404 | Response::HTTP_NOT_FOUND | Resource not found or no permission |
| 422 | Response::HTTP_UNPROCESSABLE_ENTITY | Validation errors |
| 500 | Response::HTTP_INTERNAL_SERVER_ERROR | Server errors |

---

## Error Handling

### Database Transactions

All write operations use database transactions with proper rollback on failure:

```php
DB::transaction(function () use ($request, $file, $fullPath) {
    // Upload file
    // Create record
    // Return response
});
```

On exception, the transaction is automatically rolled back and the uploaded file is deleted if it exists.

### File Cleanup

If database transaction fails after file upload, the system automatically deletes the orphaned file:

```php
catch (Exception $e) {
    if (Storage::exists($fullPath)) {
        Storage::delete($fullPath);
    }
}
```

### Ownership Validation

Delete operation validates ownership before execution:

```php
$branding = VendorDesignBranding::where('id', $id)
    ->where('vendor_id', $vendorId)
    ->first();

if (!$branding) {
    // Return 404 error
}
```

### Error Logging

All errors are logged with context for debugging:

```php
Log::error('Branding upload failed: ' . $e->getMessage(), [
    'vendor_id' => $request->user('customer')->id,
    'trace' => $e->getTraceAsString()
]);
```

---

## Security Considerations

### 1. JWT Authentication

All endpoints require JWT authentication with the `customer` guard:

```php
$vendorId = auth('customer')->id();
```

Only authenticated vendors can access the API.

### 2. Ownership Verification

Fetch and delete operations filter by vendor_id to ensure vendors can only access their own branding:

```php
VendorDesignBranding::where('vendor_id', $vendorId)->get();
```

### 3. File Type Validation

Only specific image types are allowed (jpg, jpeg, png, webp):

```php
'image' => ['required', 'file', 'mimes:jpg,jpeg,png,webp', 'max:10240']
```

### 4. File Size Limitation

Maximum file size is limited to 10MB (10240 KB) to prevent abuse and storage issues.

### 5. Unique Filename Generation

Files are renamed with UUID to prevent conflicts and predictable paths:

```php
$safeName = Str::slug($originalName) . '-' . Str::uuid();
```

### 6. Cascading Deletes

Foreign key constraint ensures branding is deleted when vendor is deleted:

```php
$table->foreignId('vendor_id')
    ->constrained('vendors')
    ->cascadeOnDelete();
```

### 7. Storage Security

Files are stored with appropriate cache headers for performance:

```php
Storage::putFileAs($folderPath, $file, basename($fullPath), 
    ['Cache-Control' => 'public, max-age=604800']
);
```

### 8. Redis Cache Security

Cache keys are vendor-specific to prevent data leakage:

```php
protected function cacheKey($vendorId, $page, $perPage): string
{
    return "vendor:{$vendorId}:branding:page:{$page}:per_page:{$perPage}";
}
```

**Cache Features:**
- **Vendor Isolation:** Each vendor's data is cached separately using their unique vendor_id
- **Automatic Invalidation:** Cache is cleared on create/delete operations to maintain data consistency
- **TTL:** 5-minute cache expiration (300 seconds) balances performance and data freshness
- **Pagination-Aware:** Cache keys include page and per_page parameters to cache each pagination state separately

### 9. Pagination Limits

Maximum items per page is capped at 100 to prevent resource exhaustion:

```php
$perPage = min((int) $request->get('per_page', 15), 100);
```

---

## Testing

### Manual Testing Steps

1. **Setup**
   ```bash
   # Run migrations
   php artisan migrate
   
   # Create symbolic link for storage
   php artisan storage:link
   ```

2. **Upload Branding**
   - Authenticate as a vendor to get JWT token
   - Send POST request to `/api/v1/customers/design-branding/upload` with:
     - name: "Test Logo"
     - image: valid image file (jpg, png, webp)
   - Verify file is stored in `storage/app/public/vendor/design-branding/{date}/`
   - Verify record in `vendor_design_branding` table
   - Verify response includes id, name, url, width, height

3. **Fetch Branding**
   - Send GET request to `/api/v1/customers/design-branding`
   - Verify response contains paginated data structure
   - Verify only current vendor's branding is returned
   - Test pagination: `/api/v1/customers/design-branding?page=2&per_page=10`
   - Verify cache is working (check Redis for cache keys)

4. **Delete Branding**
   - Send DELETE request to `/api/v1/customers/design-branding/{id}`
   - Verify branding record is deleted from database
   - Verify physical file is deleted from storage
   - Verify cache is cleared after deletion
   - Verify 404 error when trying to delete another vendor's branding

5. **Validation Testing**
   - Test uploading non-image file (should fail with 422)
   - Test uploading file > 10MB (should fail with 422)
   - Test uploading without name (should fail with 422)
   - Test accessing without JWT token (should fail with 401)
   - Test invalid pagination parameters (negative page numbers, excessive per_page values)

6. **Cache Testing**
   - Upload a branding and verify cache is cleared
   - Fetch branding twice and verify second request uses cache (check Redis)
   - Delete a branding and verify cache is cleared
   - Verify different pagination states have separate cache entries

### Test Coverage Areas

1. **Upload Tests**
   - ✅ Upload with valid data
   - ✅ Upload with invalid file type
   - ✅ Upload with oversized file
   - ✅ Upload without authentication
   - ✅ Upload with missing name field
   - ✅ Cache invalidation after upload

2. **Fetch Tests**
   - ✅ Fetch all branding for authenticated vendor
   - ✅ Fetch returns empty paginated response when no branding exists
   - ✅ Fetch without authentication fails
   - ✅ Fetch only returns current vendor's branding
   - ✅ Pagination works correctly with page and per_page parameters
   - ✅ Cache is used for subsequent identical requests
   - ✅ Different pagination states have separate cache entries

3. **Delete Tests**
   - ✅ Delete own branding successfully
   - ✅ Delete removes file from storage
   - ✅ Delete another vendor's branding fails with 404
   - ✅ Delete non-existent branding fails with 404
   - ✅ Delete without authentication fails
   - ✅ Cache invalidation after delete

4. **Pagination Tests**
   - ✅ Default pagination (15 items per page)
   - ✅ Custom per_page parameter
   - ✅ Maximum per_page limit (100) is enforced
   - ✅ Page navigation (next_page_url, prev_page_url)
   - ✅ Correct total count and page metadata

---

## Code Examples

### cURL Examples

#### Upload Branding

```bash
curl -X POST http://localhost:8000/api/v1/customers/design-branding/upload \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {your_jwt_token}" \
  -F "name=Company Logo" \
  -F "image=@/path/to/logo.png"
```

#### Fetch All Branding

```bash
# Default pagination (page 1, 15 items per page)
curl -X GET http://localhost:8000/api/v1/customers/design-branding \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {your_jwt_token}"

# With custom pagination
curl -X GET "http://localhost:8000/api/v1/customers/design-branding?page=2&per_page=20" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {your_jwt_token}"
```

#### Delete Branding

```bash
curl -X DELETE http://localhost:8000/api/v1/customers/design-branding/1 \
  -H "Accept: application/json" \
  -H "Authorization: Bearer {your_jwt_token}"
```

---

### JavaScript Examples

#### Upload Branding (Fetch API with FormData)

```javascript
const uploadBranding = async (token, name, imageFile) => {
  const formData = new FormData();
  formData.append('name', name);
  formData.append('image', imageFile);
  
  try {
    const response = await fetch('http://localhost:8000/api/v1/customers/design-branding/upload', {
      method: 'POST',
      headers: {
        'Accept': 'application/json',
        'Authorization': `Bearer ${token}`
      },
      body: formData
    });
    
    const data = await response.json();
    
    if (data.success) {
      console.log('Upload successful:', data.data);
      console.log('Image URL:', data.data.url);
      console.log('Dimensions:', data.data.width, 'x', data.data.height);
    } else {
      console.error('Upload failed:', data.message);
    }
  } catch (error) {
    console.error('Network error:', error);
  }
};

// Usage with file input
const fileInput = document.getElementById('brandingImage');
const file = fileInput.files[0];
uploadBranding('your_jwt_token', 'Company Logo', file);
```

#### Fetch All Branding (Axios)

```javascript
import axios from 'axios';

const fetchBranding = async (token, page = 1, perPage = 15) => {
  try {
    const response = await axios.get(
      'http://localhost:8000/api/v1/customers/design-branding',
      {
        params: {
          page: page,
          per_page: perPage
        },
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${token}`
        }
      }
    );
    
    if (response.data.success) {
      const paginatedData = response.data.data;
      const brandings = paginatedData.data;
      
      console.log(`Page ${paginatedData.current_page} of ${paginatedData.last_page}`);
      console.log(`Total: ${paginatedData.total} branding assets`);
      console.log(`Showing ${paginatedData.from} to ${paginatedData.to}`);
      
      brandings.forEach(branding => {
        console.log(`${branding.name}: ${branding.url}`);
      });
      
      // Check if there's a next page
      if (paginatedData.next_page_url) {
        console.log('More pages available');
      }
    }
  } catch (error) {
    if (error.response) {
      console.error('Error:', error.response.data.message);
    }
  }
};

// Usage
fetchBranding('your_jwt_token', 1, 20); // Fetch page 1, 20 items per page
```

#### Delete Branding (Fetch API)

```javascript
const deleteBranding = async (token, brandingId) => {
  try {
    const response = await fetch(
      `http://localhost:8000/api/v1/customers/design-branding/${brandingId}`,
      {
        method: 'DELETE',
        headers: {
          'Accept': 'application/json',
          'Authorization': `Bearer ${token}`
        }
      }
    );
    
    const data = await response.json();
    
    if (data.success) {
      console.log('Deleted successfully:', data.message);
    } else {
      console.error('Delete failed:', data.message);
    }
  } catch (error) {
    console.error('Network error:', error);
  }
};
```

---

### PHP Examples

#### Upload Branding (Guzzle)

```php
use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'http://localhost:8000',
    'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $jwtToken
    ]
]);

try {
    $response = $client->post('/api/v1/customers/design-branding/upload', [
        'multipart' => [
            [
                'name' => 'name',
                'contents' => 'Company Logo'
            ],
            [
                'name' => 'image',
                'contents' => fopen('/path/to/logo.png', 'r'),
                'filename' => 'logo.png'
            ]
        ]
    ]);
    
    $data = json_decode($response->getBody(), true);
    
    if ($data['success']) {
        echo "Upload successful: " . $data['data']['url'];
        echo "\nDimensions: " . $data['data']['width'] . 'x' . $data['data']['height'];
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

#### Fetch All Branding (Guzzle)

```php
use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'http://localhost:8000',
    'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $jwtToken
    ]
]);

try {
    $response = $client->get('/api/v1/customers/design-branding');
    $data = json_decode($response->getBody(), true);
    
    if ($data['success']) {
        foreach ($data['data'] as $branding) {
            echo $branding['name'] . ': ' . $branding['url'] . "\n";
            echo 'Size: ' . $branding['width'] . 'x' . $branding['height'] . "\n\n";
        }
    }
} catch (\Exception $e) {
    echo "Error: " . $e->getMessage();
}
```

#### Delete Branding (Guzzle)

```php
use GuzzleHttp\Client;

$client = new Client([
    'base_uri' => 'http://localhost:8000',
    'headers' => [
        'Accept' => 'application/json',
        'Authorization' => 'Bearer ' . $jwtToken
    ]
]);

try {
    $response = $client->delete('/api/v1/customers/design-branding/1');
    $data = json_decode($response->getBody(), true);
    
    if ($data['success']) {
        echo "Deleted successfully: " . $data['message'];
    }
} catch (\GuzzleHttp\Exception\ClientException $e) {
    $response = $e->getResponse();
    $data = json_decode($response->getBody(), true);
    echo "Error: " . $data['message'];
}
```

---

### React Example (Complete Component with Pagination)

```jsx
import React, { useState, useEffect } from 'react';
import axios from 'axios';

const BrandingManager = ({ jwtToken }) => {
  const [brandings, setBrandings] = useState([]);
  const [pagination, setPagination] = useState({
    current_page: 1,
    last_page: 1,
    per_page: 15,
    total: 0
  });
  const [loading, setLoading] = useState(false);
  const [selectedFile, setSelectedFile] = useState(null);
  const [brandingName, setBrandingName] = useState('');
  const [currentPage, setCurrentPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  
  const API_BASE_URL = 'http://localhost:8000/api/v1/customers/design-branding';
  
  const axiosConfig = {
    headers: {
      'Accept': 'application/json',
      'Authorization': `Bearer ${jwtToken}`
    }
  };
  
  // Fetch branding when page or perPage changes
  useEffect(() => {
    fetchBrandings();
  }, [currentPage, perPage]);
  
  const fetchBrandings = async () => {
    setLoading(true);
    try {
      const response = await axios.get(API_BASE_URL, {
        ...axiosConfig,
        params: {
          page: currentPage,
          per_page: perPage
        }
      });
      if (response.data.success) {
        const paginatedData = response.data.data;
        setBrandings(paginatedData.data);
        setPagination({
          current_page: paginatedData.current_page,
          last_page: paginatedData.last_page,
          per_page: paginatedData.per_page,
          total: paginatedData.total,
          from: paginatedData.from,
          to: paginatedData.to
        });
      }
    } catch (error) {
      console.error('Failed to fetch brandings:', error);
    } finally {
      setLoading(false);
    }
  };
  
  const uploadBranding = async (e) => {
    e.preventDefault();
    
    if (!selectedFile || !brandingName) {
      alert('Please provide both name and image file');
      return;
    }
    
    const formData = new FormData();
    formData.append('name', brandingName);
    formData.append('image', selectedFile);
    
    setLoading(true);
    try {
      const response = await axios.post(
        `${API_BASE_URL}/upload`,
        formData,
        {
          headers: {
            ...axiosConfig.headers,
            'Content-Type': 'multipart/form-data'
          }
        }
      );
      
      if (response.data.success) {
        alert('Branding uploaded successfully!');
        setBrandingName('');
        setSelectedFile(null);
        fetchBrandings(); // Refresh list
      }
    } catch (error) {
      const message = error.response?.data?.message || 'Upload failed';
      alert(message);
    } finally {
      setLoading(false);
    }
  };
  
  const deleteBranding = async (id) => {
    if (!window.confirm('Are you sure you want to delete this branding?')) {
      return;
    }
    
    setLoading(true);
    try {
      const response = await axios.delete(
        `${API_BASE_URL}/${id}`,
        axiosConfig
      );
      
      if (response.data.success) {
        alert('Branding deleted successfully!');
        fetchBrandings(); // Refresh list
      }
    } catch (error) {
      const message = error.response?.data?.message || 'Delete failed';
      alert(message);
    } finally {
      setLoading(false);
    }
  };
  
  return (
    <div className="branding-manager">
      <h2>Vendor Design Branding</h2>
      
      {/* Upload Form */}
      <form onSubmit={uploadBranding} className="upload-form">
        <h3>Upload New Branding</h3>
        <div>
          <label>Name:</label>
          <input
            type="text"
            value={brandingName}
            onChange={(e) => setBrandingName(e.target.value)}
            placeholder="e.g., Company Logo"
            required
          />
        </div>
        <div>
          <label>Image:</label>
          <input
            type="file"
            accept="image/jpeg,image/png,image/webp"
            onChange={(e) => setSelectedFile(e.target.files[0])}
            required
          />
        </div>
        <button type="submit" disabled={loading}>
          {loading ? 'Uploading...' : 'Upload'}
        </button>
      </form>
      
      {/* Branding List */}
      <div className="branding-list">
        <div className="list-header">
          <h3>Your Branding Assets</h3>
          <div className="pagination-controls">
            <label>
              Items per page:
              <select 
                value={perPage} 
                onChange={(e) => {
                  setPerPage(Number(e.target.value));
                  setCurrentPage(1); // Reset to first page
                }}
              >
                <option value="15">15</option>
                <option value="20">20</option>
                <option value="25">25</option>
                <option value="50">50</option>
              </select>
            </label>
          </div>
        </div>
        
        {loading && <p>Loading...</p>}
        {!loading && brandings.length === 0 && <p>No branding assets yet.</p>}
        {!loading && brandings.length > 0 && (
          <>
            <div className="pagination-info">
              Showing {pagination.from} to {pagination.to} of {pagination.total} items
            </div>
            
            <div className="branding-grid">
              {brandings.map((branding) => (
                <div key={branding.id} className="branding-item">
                  <img src={branding.url} alt={branding.name} />
                  <h4>{branding.name}</h4>
                  <p>Dimensions: {branding.width} x {branding.height}</p>
                  <p>Uploaded: {new Date(branding.created_at).toLocaleDateString()}</p>
                  <button 
                    onClick={() => deleteBranding(branding.id)}
                    disabled={loading}
                  >
                    Delete
                  </button>
                </div>
              ))}
            </div>
            
            {/* Pagination Controls */}
            <div className="pagination">
              <button 
                onClick={() => setCurrentPage(currentPage - 1)}
                disabled={currentPage === 1 || loading}
              >
                Previous
              </button>
              
              <span className="page-info">
                Page {pagination.current_page} of {pagination.last_page}
              </span>
              
              <button 
                onClick={() => setCurrentPage(currentPage + 1)}
                disabled={currentPage === pagination.last_page || loading}
              >
                Next
              </button>
            </div>
          </>
        )}
      </div>
    </div>
  );
};

export default BrandingManager;
```

---

## File Structure

```
app/
├── Http/
│   ├── Controllers/
│   │   └── Api/
│   │       └── V1/
│   │           └── Customer/
│   │               └── Branding/
│   │                   └── DesignBrandingController.php
│   └── Requests/
│       └── Api/
│           └── V1/
│               └── Customer/
│                   └── Branding/
│                       └── StoreVendorBrandingRequest.php
└── Models/
    └── Customer/
        └── Branding/
            └── VendorDesignBranding.php

database/
└── migrations/
    └── 2025_12_23_092039_create_vendor_design_branding_table.php

routes/
└── api.php

storage/
└── app/
    └── public/
        └── vendor/
            └── design-branding/
                └── {YYYY}/
                    └── {MM}/
                        └── {DD}/
                            └── {filename}.{ext}
```

---

## Future Enhancements

### Planned Features

1. **Multiple Image Support**
   - Allow vendors to upload multiple branding variations
   - Support for different image types (logo, banner, favicon, etc.)
   - Category/type field for better organization

2. **Image Processing**
   - Automatic image optimization and compression
   - Generate multiple sizes/thumbnails
   - WebP conversion for better performance
   - Image cropping and editing capabilities

3. **Branding Templates**
   - Pre-defined branding templates
   - Color palette extraction from uploaded images
   - Theme customization based on branding

4. **Usage Analytics**
   - Track where branding is displayed
   - View count and impression metrics
   - A/B testing for different branding versions

5. **Batch Operations**
   - Upload multiple images at once
   - Bulk delete functionality
   - Batch rename or recategorize

6. **Advanced Validation**
   - Minimum/maximum dimension requirements
   - Aspect ratio validation
   - Color profile validation
   - Transparent background detection

### Scalability Considerations

- **CDN Integration**: Store uploaded images on CDN for faster delivery
- **Queue Processing**: Move image processing to background jobs
- **Database Indexing**: Add indexes on vendor_id and created_at for faster queries
- **Caching**: ✅ **Implemented** - Redis caching with 5-minute TTL for improved performance
- **Pagination**: ✅ **Implemented** - Efficient pagination to handle large datasets
- **Storage Optimization**: Implement automatic cleanup of old/unused branding assets
- **Rate Limiting**: Limit upload frequency to prevent abuse

### Performance Features Implemented

- ✅ **Redis Caching**: All fetch operations use Redis cache with vendor-specific keys
- ✅ **Pagination**: Supports configurable page size (max 100 items per page)
- ✅ **Cache Invalidation**: Automatic cache clearing on create/delete operations
- ✅ **Optimized Queries**: Vendor-specific filtering at the database level

---

## Support

For issues or questions:

- **GitHub Issues**: [Repository Issues](https://github.com/itechpanelllp/airventory-api/issues)
- **Email**: support@airventory.io
- **Documentation**: See `/docs` directory for more API documentation

---

## Changelog

### Version 1.1.0 (2025-12-23)

- **NEW**: Added pagination support for fetch operations
  - Configurable `per_page` parameter (default: 15, max: 100)
  - Full Laravel pagination metadata in response
- **NEW**: Implemented Redis caching for improved performance
  - 5-minute TTL (300 seconds) for cached results
  - Vendor-specific cache keys for data isolation
  - Automatic cache invalidation on create/delete operations
- **IMPROVED**: Enhanced scalability and performance
  - Reduced database load through intelligent caching
  - Efficient pagination for large datasets

### Version 1.0.0 (2025-12-23)

- Initial implementation of vendor design branding API
- Upload branding with automatic dimension extraction
- Fetch branding filtered by authenticated vendor
- Delete branding with ownership verification
- Organized file storage structure (date-based folders)
- JWT authentication for all endpoints
- Comprehensive validation and error handling
- Database transaction support for data integrity
- Automatic file cleanup on transaction failure

---

**Last Updated**: December 23, 2025  
**API Version**: 1.1.0  
**Maintained by**: Airventory Development Team
