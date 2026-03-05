# Customer Design Branding API

This document describes the customer-facing API used to manage branding assets (general branding, packaging labels, hang tags, and neck tags) for a vendor.

All endpoints are versioned under `/api/v1` and live in the `customers` group.

Base path for this API:

- `/api/v1/customers/design-branding`

## Authentication

- Guard: `customer`
- Auth type: Bearer token or session, depending on how the API is consumed
- All endpoints below require an authenticated customer.

If the customer is not authenticated, the API returns `401 Unauthorized`.

## Types and Image Rules

The `type` field controls how many images are required and what the asset represents.

Allowed values:

- `branding` – Generic branding asset. Only front image is required.
- `packaging_label` – Packaging label design. Requires front and back images.
- `hang_tag` – Hang tag design. Requires front and back images.
- `neck_tag` – Neck tag design. Only front image is required.

Image field rules:

- `image` (front) is always required.
- `back_image` is required only when `type` is `packaging_label` or `hang_tag`.
- `back_image` is optional for `branding` and `neck_tag` but is usually not needed.

All images:

- Accepted types: `jpg`, `jpeg`, `png`, `webp`
- Maximum size: `10MB` (per file)

## Endpoints

### 1. List Brandings

- **Method**: `GET`
- **Path**: `/api/v1/customers/design-branding`
- **Route name**: `api.v1.customer.design-branding.index`

Returns paginated branding assets for the authenticated vendor.

#### Query Parameters

- `per_page` (optional, integer)
  - Default: `15`
  - Min: `1`
  - Max: `100`
- `page` (optional, integer)
  - Default: `1`
  - Min: `1`
- `type` (optional, string)
  - Filter by asset type.
  - Allowed: `branding`, `packaging_label`, `hang_tag`, `neck_tag`

#### Response: 200 OK

```json
{
  "success": true,
  "data": {
    "current_page": 1,
    "per_page": 15,
    "last_page": 1,
    "total": 2,
    "items": [
      {
        "id": 1,
        "name": "Premium Packaging Label",
        "url": "https://cdn.example.com/vendor/design-branding/2026/02/20/front-image.webp",
        "width": 1200,
        "height": 800,
        "back_url": "https://cdn.example.com/vendor/design-branding/2026/02/20/back-image.webp",
        "back_width": 1200,
        "back_height": 800,
        "created_at": "2026-02-20T10:00:00.000000Z"
      },
      {
        "id": 2,
        "name": "Neck Tag",
        "url": "https://cdn.example.com/vendor/design-branding/2026/02/20/neck-tag.webp",
        "width": 600,
        "height": 400,
        "back_url": null,
        "back_width": null,
        "back_height": null,
        "created_at": "2026-02-20T10:05:00.000000Z"
      }
    ]
  }
}
```

#### Error Responses

- `500 Internal Server Error` – On unexpected exceptions. The payload includes:

```json
{
  "success": false,
  "message": "An error occurred while fetching the brandings."
}
```

### 2. Upload Branding Asset

- **Method**: `POST`
- **Path**: `/api/v1/customers/design-branding/upload`
- **Route name**: `api.v1.customer.design-branding.upload`
- **Content-Type**: `multipart/form-data`

Creates a new branding record for the authenticated vendor.

#### Request Fields

- `name` (string, required)
  - Human-friendly name for this asset.
  - Example: `"Premium Packaging Label"`, `"Front Hang Tag"`, `"Neck Tag A"`

- `type` (string, optional)
  - Allowed: `branding`, `packaging_label`, `hang_tag`, `neck_tag`
  - Default: `branding` when not provided.

- `image` (file, required)
  - Front image file.
  - Required for all `type` values.

- `back_image` (file, conditional)
  - Back image file.
  - Required when `type` is `packaging_label` or `hang_tag`.
  - Optional for `branding` and `neck_tag`.

Both images must be one of `jpg`, `jpeg`, `png`, `webp` and be at most `10MB`.

#### Behavior

- Files are stored under:
  - `vendor/design-branding/{Y}/{m}/{d}`
- For each image:
  - A slugified name plus a UUID is used to avoid collisions.
  - If the image is a supported type, dimensions are extracted and saved.
- Database write and file uploads are wrapped in a transaction-like flow:
  - If any upload fails, an exception is thrown and both images (front and back) are deleted if they were uploaded.
  - On successful creation, the vendor-specific branding cache is invalidated, so subsequent list calls show the new record immediately.

#### Response: 201 Created

```json
{
  "success": true,
  "message": "Branding uploaded successfully.",
  "data": {
    "id": 1,
    "name": "Premium Packaging Label",
    "url": "https://cdn.example.com/vendor/design-branding/2026/02/20/front-image.webp",
    "width": 1200,
    "height": 800,
    "back_url": "https://cdn.example.com/vendor/design-branding/2026/02/20/back-image.webp",
    "back_width": 1200,
    "back_height": 800
  }
}
```

For `neck_tag` or `branding` types where only a front image is provided, `back_url`, `back_width`, and `back_height` will be `null`.

#### Validation Errors: 422 Unprocessable Entity

Example when `back_image` is missing for `packaging_label`:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "back_image": [
      "Please upload a back image for packaging labels and hang tags."
    ]
  }
}
```

Example when `image` is missing:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "image": [
      "Please upload a branding image."
    ]
  }
}
```

#### Error Responses

- `500 Internal Server Error` – On unexpected exceptions (e.g. storage failures). Response:

```json
{
  "success": false,
  "message": "An error occurred while uploading the branding."
}
```

### 3. Delete Branding Asset

- **Method**: `DELETE`
- **Path**: `/api/v1/customers/design-branding/{id}`
- **Route name**: `api.v1.customer.design-branding.delete`

Deletes a branding record belonging to the authenticated vendor and removes the associated images from storage.

#### Path Parameter

- `id` (integer, required)
  - The ID of the branding record to delete.

#### Behavior

- Only deletes records where `vendor_id` matches the current customer.
- Inside a database transaction:
  - Deletes the front image file if it exists.
  - Deletes the back image file if it exists.
  - Deletes the database record.
  - Bumps the vendor branding cache version so future list calls no longer return the deleted record.

#### Response: 200 OK

```json
{
  "success": true,
  "message": "Branding deleted successfully."
}
```

#### Error Responses

- `404 Not Found` – If the branding does not exist for this vendor:

```json
{
  "success": false,
  "message": "Branding not found or you do not have permission to delete it."
}
```

- `500 Internal Server Error` – On unexpected exceptions during delete:

```json
{
  "success": false,
  "message": "An error occurred while deleting the branding."
}
```

## Example Usage

### Upload Packaging Label (Front + Back)

```bash
curl -X POST "https://your-domain.test/api/v1/customers/design-branding/upload" \
  -H "Authorization: Bearer <token>" \
  -F "name=Premium Packaging Label" \
  -F "type=packaging_label" \
  -F "image=@/path/to/front.png" \
  -F "back_image=@/path/to/back.png"
```

### Upload Hang Tag (Front + Back)

```bash
curl -X POST "https://your-domain.test/api/v1/customers/design-branding/upload" \
  -H "Authorization: Bearer <token>" \
  -F "name=Front Hang Tag" \
  -F "type=hang_tag" \
  -F "image=@/path/to/hang-tag-front.png" \
  -F "back_image=@/path/to/hang-tag-back.png"
```

### Upload Neck Tag (Single Image)

```bash
curl -X POST "https://your-domain.test/api/v1/customers/design-branding/upload" \
  -H "Authorization: Bearer <token>" \
  -F "name=Neck Tag" \
  -F "type=neck_tag" \
  -F "image=@/path/to/neck-tag.png"
```

### List All Branding Assets

```bash
curl -X GET "https://your-domain.test/api/v1/customers/design-branding?per_page=20&page=1" \
  -H "Authorization: Bearer <token>"
```

### List Only Packaging Labels

```bash
curl -X GET "https://your-domain.test/api/v1/customers/design-branding?type=packaging_label" \
  -H "Authorization: Bearer <token>"
```

### Delete a Branding Asset

```bash
curl -X DELETE "https://your-domain.test/api/v1/customers/design-branding/1" \
  -H "Authorization: Bearer <token>"
```

