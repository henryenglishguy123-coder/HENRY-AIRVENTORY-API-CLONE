# Factory Account Status Management API - Admin Guide

## Overview

This API allows admin users to manage factory account status (ENABLED, DISABLED, BLOCKED, SUSPENDED) and verification status (VERIFIED, PENDING, REJECTED, HOLD, PROCESSING) from the admin panel. Both statuses can be updated independently or together in a single request.

---

## Authentication

All endpoints require **Admin JWT Authentication** using the Authorization header:

```
Authorization: Bearer {admin_jwt_token}
```

---

## Base URL

```
/api/v1/admin/factories-status
```

---

## Endpoints

### 1. Get Available Statuses

**Endpoint:**

```
GET /api/v1/admin/factories-status/statuses
```

**Description:** Retrieve all available account status and verification status options for dropdowns/select inputs.

**Headers:**

```
Authorization: Bearer {admin_jwt_token}
Accept: application/json
```

**Response (Success - 200):**

```json
{
    "success": true,
    "data": {
        "account_statuses": [
            {
                "value": 0,
                "name": "DISABLED",
                "label": "Disabled",
                "color": "secondary"
            },
            {
                "value": 1,
                "name": "ENABLED",
                "label": "Enabled",
                "color": "success"
            },
            {
                "value": 2,
                "name": "BLOCKED",
                "label": "Blocked",
                "color": "danger"
            },
            {
                "value": 3,
                "name": "SUSPENDED",
                "label": "Suspended",
                "color": "warning"
            }
        ],
        "verification_statuses": [
            {
                "value": 0,
                "name": "REJECTED",
                "label": "Rejected",
                "color": "danger"
            },
            {
                "value": 1,
                "name": "VERIFIED",
                "label": "Verified",
                "color": "success"
            },
            {
                "value": 2,
                "name": "PENDING",
                "label": "Pending",
                "color": "dark"
            },
            {
                "value": 3,
                "name": "HOLD",
                "label": "Hold",
                "color": "secondary"
            },
            {
                "value": 4,
                "name": "PROCESSING",
                "label": "Processing",
                "color": "info"
            }
        ]
    },
    "message": "Status options retrieved successfully."
}
```

**cURL Example:**

```bash
curl -X GET "http://localhost:8000/api/v1/admin/factories-status/statuses" \
  -H "Authorization: Bearer {admin_jwt_token}" \
  -H "Accept: application/json"
```

---

### 2. Update Factory Status (Unified Endpoint)

**Endpoint:**

```
PUT /api/v1/admin/factories-status/{factory}/update
```

**Description:** Update the account status, verification status, or both in a single request. At least one of `account_status`, `account_verified`, or `verify_email` must be provided.

**URL Parameters:**
| Parameter | Type | Description |
|-----------|------|-------------|
| factory | Integer | Factory ID |

**Headers:**

```
Authorization: Bearer {admin_jwt_token}
Content-Type: application/json
Accept: application/json
```

**Request Body:**

```json
{
    "account_status": 1,
    "account_verified": 1,
    "verify_email": true,
    "reason": "Factory approved and activated"
}
```

**Field Validation:**
| Field | Type | Required | Values | Description |
|-------|------|----------|--------|-------------|
| account_status | Integer | No* | 0, 1, 2, 3 | 0=Disabled, 1=Enabled, 2=Blocked, 3=Suspended |
| account_verified | Integer | No* | 0, 1, 2, 3, 4 | 0=Rejected, 1=Verified, 2=Pending, 3=Hold, 4=Processing |
| verify_email | Boolean | No* | true/false | Mark factory email as verified by admin |
| reason | String | No | Max 500 chars | Optional reason for the status change |

\*At least one of `account_status`, `account_verified`, or `verify_email` is required.

> **Note:** Setting `account_verified` to `1` (VERIFIED) requires **both** a complete factory profile **and** `account_status` set to `1` (ENABLED). If these conditions are not met, the API returns a 422 response with details.

**Response (Success - 200):**

```json
{
    "success": true,
    "data": {
        "factory": {
            "id": 5,
            "first_name": "John",
            "last_name": "Doe",
            "email": "factory@example.com",
            "phone_number": "+1234567890",
            "account_status": "enabled",
            "account_verified": "verified",
            "email_verified_at": "2024-02-21T10:30:00Z",
            "last_login": "2024-02-20T15:45:30Z",
            "created_at": "2024-02-15T12:00:00Z",
            "updated_at": "2024-02-21T14:30:00Z"
        },
        "changes": {
            "account_status": {
                "old": "Disabled",
                "new": "Enabled"
            },
            "account_verified": {
                "old": "Pending",
                "new": "Verified"
            }
        },
        "reason": "Factory approved and activated"
    },
    "message": "Factory status updated successfully."
}
```

**Response (Incomplete Profile - 422):**

```json
{
    "success": false,
    "data": {
        "completeness": {
            "is_complete": false,
            "missing_fields": ["business_info.company_name"]
        }
    },
    "message": "Cannot verify factory status. Missing required fields."
}
```

**cURL Example:**

```bash
curl -X PUT "http://localhost:8000/api/v1/admin/factories-status/5/update" \
  -H "Authorization: Bearer {admin_jwt_token}" \
  -H "Content-Type: application/json" \
  -d '{
    "account_status": 1,
    "account_verified": 1,
    "reason": "Factory approved and activated"
  }'
```

**Axios Example:**

```javascript
const token = localStorage.getItem("admin_token");

axios
    .put(
        `/api/v1/admin/factories-status/${factoryId}/update`,
        {
            account_status: 1,    // ENABLED
            account_verified: 1,  // VERIFIED
            reason: "KYC verification completed",
        },
        {
            headers: {
                Authorization: `Bearer ${token}`,
                "Content-Type": "application/json",
                Accept: "application/json",
            },
        },
    )
    .then((response) => {
        console.log("Status updated:", response.data.data);
    })
    .catch((error) => {
        console.error("Error:", error.response?.data?.message);
    });
```

---

## Admin Panel UI Implementation Guide

### React Component Example

```jsx
import React, { useState, useEffect } from "react";
import axios from "axios";

const FactoryStatusManager = ({ factoryId }) => {
    const [factory, setFactory] = useState(null);
    const [statuses, setStatuses] = useState({
        account_statuses: [],
        verification_statuses: [],
    });
    const [loading, setLoading] = useState(true);
    const [updating, setUpdating] = useState(false);
    const [reason, setReason] = useState("");
    const [selectedAccountStatus, setSelectedAccountStatus] = useState(null);
    const [selectedVerificationStatus, setSelectedVerificationStatus] =
        useState(null);
    const [error, setError] = useState(null);
    const [success, setSuccess] = useState(null);

    const token = localStorage.getItem("admin_token");
    const headers = {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
    };

    useEffect(() => {
        fetchData();
    }, []);

    const fetchData = async () => {
        try {
            setLoading(true);
            const [statusesRes, factoryRes] = await Promise.all([
                axios.get("/api/v1/admin/factories-status/statuses", {
                    headers,
                }),
                axios.get(`/api/v1/admin/factories/${factoryId}`, { headers }),
            ]);

            setStatuses(statusesRes.data.data);
            setFactory(factoryRes.data.data);
            setSelectedAccountStatus(factoryRes.data.data.account_status);
            setSelectedVerificationStatus(
                factoryRes.data.data.account_verified,
            );
            setError(null);
        } catch (err) {
            setError(err.response?.data?.message || "Failed to load data");
        } finally {
            setLoading(false);
        }
    };

    const handleUpdateAccountStatus = async () => {
        if (selectedAccountStatus === factory.account_status && !reason) {
            setError("No changes to save");
            return;
        }

        try {
            setUpdating(true);
            const response = await axios.put(
                `/api/v1/admin/factories-status/${factoryId}/update`,
                {
                    account_status: selectedAccountStatus,
                    reason: reason || null,
                },
                { headers },
            );

            setFactory(response.data.data.factory);
            setSuccess("Account status updated successfully!");
            setReason("");
            setTimeout(() => setSuccess(null), 3000);
        } catch (err) {
            setError(
                err.response?.data?.errors
                    ? JSON.stringify(err.response.data.errors)
                    : err.message,
            );
        } finally {
            setUpdating(false);
        }
    };

    const handleUpdateVerificationStatus = async () => {
        if (
            selectedVerificationStatus === factory.account_verified &&
            !reason
        ) {
            setError("No changes to save");
            return;
        }

        try {
            setUpdating(true);
            const response = await axios.put(
                `/api/v1/admin/factories-status/${factoryId}/update`,
                {
                    account_verified: selectedVerificationStatus,
                    reason: reason || null,
                },
                { headers },
            );

            setFactory(response.data.data.factory);
            setSuccess("Verification status updated successfully!");
            setReason("");
            setTimeout(() => setSuccess(null), 3000);
        } catch (err) {
            setError(
                err.response?.data?.errors
                    ? JSON.stringify(err.response.data.errors)
                    : err.message,
            );
        } finally {
            setUpdating(false);
        }
    };

    const handleUpdateBothStatuses = async () => {
        if (
            selectedAccountStatus === factory.account_status &&
            selectedVerificationStatus === factory.account_verified &&
            !reason
        ) {
            setError("No changes to save");
            return;
        }

        try {
            setUpdating(true);

            // Build payload with only changed fields
            const payload = {};

            if (selectedAccountStatus !== factory.account_status) {
                payload.account_status = selectedAccountStatus;
            }
            if (selectedVerificationStatus !== factory.account_verified) {
                payload.account_verified = selectedVerificationStatus;
            }
            if (reason) {
                payload.reason = reason;
            }

            const response = await axios.put(
                `/api/v1/admin/factories-status/${factoryId}/update`,
                payload,
                { headers },
            );

            setFactory(response.data.data.factory);
            setSuccess("Both statuses updated successfully!");
            setReason("");
            setTimeout(() => setSuccess(null), 3000);
        } catch (err) {
            setError(
                err.response?.data?.errors
                    ? JSON.stringify(err.response.data.errors)
                    : err.message,
            );
        } finally {
            setUpdating(false);
        }
    };

    if (loading) return <div className="alert alert-info">Loading...</div>;

    const accountStatusObj = statuses.account_statuses.find(
        (s) => s.value === selectedAccountStatus,
    );
    const verificationStatusObj = statuses.verification_statuses.find(
        (s) => s.value === selectedVerificationStatus,
    );

    return (
        <div className="card">
            <div className="card-header">
                <h3 className="card-title">Factory Status Management</h3>
            </div>
            <div className="card-body">
                {error && (
                    <div className="alert alert-danger alert-dismissible fade show">
                        {error}
                    </div>
                )}
                {success && (
                    <div className="alert alert-success alert-dismissible fade show">
                        {success}
                    </div>
                )}

                <div className="row mb-4">
                    <div className="col-md-6">
                        <div className="form-group">
                            <label className="form-label">Account Status</label>
                            <select
                                className="form-control"
                                value={selectedAccountStatus}
                                onChange={(e) =>
                                    setSelectedAccountStatus(
                                        parseInt(e.target.value),
                                    )
                                }
                                disabled={updating}
                            >
                                {statuses.account_statuses.map((status) => (
                                    <option
                                        key={status.value}
                                        value={status.value}
                                    >
                                        {status.label}
                                    </option>
                                ))}
                            </select>
                            {accountStatusObj && (
                                <small
                                    className={`badge bg-${accountStatusObj.color}`}
                                >
                                    {accountStatusObj.label}
                                </small>
                            )}
                        </div>
                    </div>

                    <div className="col-md-6">
                        <div className="form-group">
                            <label className="form-label">
                                Verification Status
                            </label>
                            <select
                                className="form-control"
                                value={selectedVerificationStatus}
                                onChange={(e) =>
                                    setSelectedVerificationStatus(
                                        parseInt(e.target.value),
                                    )
                                }
                                disabled={updating}
                            >
                                {statuses.verification_statuses.map(
                                    (status) => (
                                        <option
                                            key={status.value}
                                            value={status.value}
                                        >
                                            {status.label}
                                        </option>
                                    ),
                                )}
                            </select>
                            {verificationStatusObj && (
                                <small
                                    className={`badge bg-${verificationStatusObj.color}`}
                                >
                                    {verificationStatusObj.label}
                                </small>
                            )}
                        </div>
                    </div>
                </div>

                <div className="form-group mb-4">
                    <label className="form-label">Reason (Optional)</label>
                    <textarea
                        className="form-control"
                        rows="3"
                        placeholder="Enter reason for status change..."
                        value={reason}
                        onChange={(e) => setReason(e.target.value)}
                        disabled={updating}
                        maxLength="500"
                    />
                    <small className="form-text text-muted">
                        {reason.length}/500 characters
                    </small>
                </div>

                <div className="form-group">
                    <button
                        className="btn btn-primary me-2"
                        onClick={handleUpdateBothStatuses}
                        disabled={updating}
                    >
                        {updating ? "Updating..." : "Update Both Statuses"}
                    </button>
                    <button
                        className="btn btn-outline-secondary me-2"
                        onClick={handleUpdateAccountStatus}
                        disabled={updating}
                    >
                        {updating
                            ? "Updating..."
                            : "Update Account Status Only"}
                    </button>
                    <button
                        className="btn btn-outline-secondary"
                        onClick={handleUpdateVerificationStatus}
                        disabled={updating}
                    >
                        {updating
                            ? "Updating..."
                            : "Update Verification Status Only"}
                    </button>
                </div>
            </div>
        </div>
    );
};

export default FactoryStatusManager;
```

---

## Status Values Reference

### Account Status Codes

| Code | Name      | Label     | Color     | Description                      |
| ---- | --------- | --------- | --------- | -------------------------------- |
| 0    | DISABLED  | Disabled  | secondary | Account is disabled              |
| 1    | ENABLED   | Enabled   | success   | Account is active and enabled    |
| 2    | BLOCKED   | Blocked   | danger    | Account is permanently blocked   |
| 3    | SUSPENDED | Suspended | warning   | Account is temporarily suspended |

### Verification Status Codes

| Code | Name       | Label      | Color     | Description                     |
| ---- | ---------- | ---------- | --------- | ------------------------------- |
| 0    | REJECTED   | Rejected   | danger    | Verification was rejected       |
| 1    | VERIFIED   | Verified   | success   | Account is verified             |
| 2    | PENDING    | Pending    | dark      | Awaiting verification review    |
| 3    | HOLD       | Hold       | secondary | Verification on hold            |
| 4    | PROCESSING | Processing | info      | Verification is being processed |

---

## Error Handling

All error responses follow this format:

```json
{
    "success": false,
    "data": null,
    "message": "Error description",
    "errors": {
        "field_name": ["Error message"]
    }
}
```

### Common Errors

| Status | Message               | Cause                                            |
| ------ | --------------------- | ------------------------------------------------ |
| 401    | Unauthorized          | Missing or invalid JWT token                     |
| 404    | Not Found             | Factory ID doesn't exist                         |
| 422    | Validation failed     | Invalid status values or missing required fields |
| 500    | Internal Server Error | Database or server error                         |

---

## Logging & Audit Trail

All status changes are automatically logged with:

- Factory ID
- Old status
- New status
- Reason (if provided)
- Admin user who made the change
- Timestamp

Logs can be found in:

```
storage/logs/laravel.log
```

---

## Best Practices

1. **Always Provide Reason:**
    - Include a reason when changing status for audit purposes
    - This helps track why changes were made

2. **Batch Updates:**
    - Use the unified update endpoint to update both account and verification status in one request
    - More efficient than two separate requests

3. **Verify Before Blocking:**
    - Always review factory information before blocking
    - Consider suspending first if uncertain

4. **Status Transitions:**
    - PENDING → PROCESSING → VERIFIED (typical flow)
    - PENDING → HOLD (if documents need review)
    - PENDING → REJECTED (if verification fails)

5. **Account Status Logic:**
    - ENABLED: Factory can operate normally
    - DISABLED: Account is turned off
    - BLOCKED: Permanent block, manual review needed
    - SUSPENDED: Temporary block, can be restored

---

## Testing with cURL

```bash
# Get available statuses
curl -X GET "http://localhost:8000/api/v1/admin/factories-status/statuses" \
  -H "Authorization: Bearer {token}"

# Update account status only
curl -X PUT "http://localhost:8000/api/v1/admin/factories-status/5/update" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"account_status": 1}'

# Update verification status only
curl -X PUT "http://localhost:8000/api/v1/admin/factories-status/5/update" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{"account_verified": 1}'

# Update both statuses
curl -X PUT "http://localhost:8000/api/v1/admin/factories-status/5/update" \
  -H "Authorization: Bearer {token}" \
  -H "Content-Type: application/json" \
  -d '{
    "account_status": 1,
    "account_verified": 1,
    "reason": "KYC verified"
  }'
```

---

## Frequently Asked Questions

**Q: Can I update only one status without changing the other?**
A: Yes, use the unified `/{factory}/update` endpoint and only include the field you want to change.

**Q: What happens when I block a factory?**
A: The factory cannot log in or perform any API operations. Their account is locked until an admin changes the status.

**Q: How do I restore a suspended account?**
A: Change the account_status back to ENABLED. You can also include a reason explaining why the suspension was lifted.

**Q: Are status changes logged?**
A: Yes, all changes are logged with the admin user ID, timestamp, old/new values, and reason.

**Q: Can a factory change their own status?**
A: No, only admins can change status through this API. Factories use other endpoints for their own account management.
