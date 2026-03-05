# Order Messaging System Documentation

## Overview

The order messaging system facilitates communication between Customers, Factories, and Administrators regarding specific sales orders. It supports text messages and file attachments.

## Data Models

### Message (`App\Models\Sales\Order\Message`)

- `order_id`: Foreign key to `sales_orders`
- `sender_id`: Morph ID of the user sending the message
- `sender_type`: Morph type of the sender (`customer`, `factory`, `admin`)
- `sender_role`: Enum ('customer', 'factory', 'admin')
- `message`: The text content of the message
- `attachments`: JSON array of file metadata. Schema: `[{"url": "/storage/...", "name": "document.pdf", "extension": "pdf", "mime_type": "application/pdf"}]`. Required fields are `url`, `name`, `extension`, `mime_type`. Actual files are stored securely in S3 or compatible object storage.
- `metadata`: JSON field for extensibility, such as read receipts or frontend client IDs (e.g., `{"deliveryStatus":"delivered", "readAt":"2025-01-01T12:00:00Z", "clientId":"abc123"}`).

## Database Schema

- **Table**: `sales_order_messages`
- **Indexes**:
    - `sales_order_messages_order_id_index`
    - `sales_order_messages_sender_id_sender_type_index`
- **Foreign Keys**:
    - `order_id` references `sales_orders(id)` on delete cascade.
- **Soft Deletes**: Enabled via `deleted_at` column.
- **Attachments**: The metadata (path, name) is stored as a JSON array in the database. The actual file blobs reside in S3 or compatible object storage.

## API Endpoints

### Authentication

All requests must include a Bearer token in the `Authorization` header:
`Authorization: Bearer <your_jwt_token>`

### Unified Routes (Customer & Factory)

These routes handle messaging within the context of an order.

| Method | Endpoint                                 | Description                    | RBAC           |
| ------ | ---------------------------------------- | ------------------------------ | -------------- |
| GET    | `/api/v1/orders/{order_number}/messages` | List all messages for an order | Owner/Assigned |
| POST   | `/api/v1/orders/{order_number}/messages` | Send a new message             | Owner/Assigned |

**Query Parameters (GET):**

- `page`: Page number (default: 1)
- `limit`: Messages per page (default: 15, max: 100)
- `after_id`: (int) Returns messages with an ID greater than this value. Used for fetching new messages (polling).
- `before_id`: (int) Returns messages with an ID less than this value. Used for fetching older messages (infinite scroll).

**Request Schema (POST):**

- `message`: (string) message content. Required if no attachments.
- `attachments[]`: (file) Array of files. Required if no message.

### Admin Routes

| Method | Endpoint                                            | Description                   |
| ------ | --------------------------------------------------- | ----------------------------- |
| GET    | `/api/v1/admin/communications`                      | List all messages (Dashboard) |
| POST   | `/api/v1/admin/communications`                      | Send message to any order     |
| GET    | `/api/v1/admin/communications/stats`                | Get messaging statistics      |
| GET    | `/api/v1/admin/communications/search`               | Search messages across orders |
| GET    | `/api/v1/admin/communications/order/{order_number}` | View history for an order     |

**Admin Query Parameters (GET /communications & /communications/search):**

- `page`: Page number (default: 1)
- `limit`: Messages per page (default: 20, max: 100)
- **Search only**: `query` (search string), `sender_role` (filter by role), `message_type` (filter by type).
- **Example**: `/api/v1/admin/communications/search?query=delay&page=2&limit=50`
- **Response Format**: Paginated JSON object `{"success": true, "data": [...], "message": "...", "meta": {"current_page": 2, "total": 45, ...}}`

---

## Frontend Request Format

### File Transmission

Messages with attachments MUST be sent as `multipart/form-data`.

**Constraints:**

- **Max File Size**: 10MB per file.
- **Max File Count**: 5 files per message.
- **Allowed MIME Types**: `image/jpeg, image/png, application/pdf, application/msword, application/vnd.openxmlformats-officedocument.wordprocessingml.document, application/zip`.

### Processing Flow

1. Files are uploaded to the `FileUploadService`.
2. Upon successful upload, file metadata (path, name, size, type) is stored in the `attachments` JSON column.
3. If database persistence fails, uploaded files are automatically purged to prevent orphans.

---

## Security Considerations

### Access Control

- **Customers**: Can only view/send messages for orders they own.
- **Factories**: Can only view/send messages for orders assigned to them.
- **Admins**: Full access to all messaging data.

### Data Protection

- **XSS Prevention**: All user-generated content is escaped before rendering in Blade or JavaScript templates.
- **Rate Limiting**: API endpoints are throttled to prevent abuse (e.g., 60 requests/minute).
- **Sanitization**: Input is validated and sanitized at the controller level.
- **Audit Logging**: Major messaging actions are logged for compliance.
- **PII/Compliance**: Avoid sharing sensitive PII (Sensitive Personal Information) in messages.
- **Encryption**: Data is encrypted in transit (HTTPS).

### Error Handling

- Detailed exceptions are logged internally.
- Clients receive generic error messages to avoid leaking implementation details.
