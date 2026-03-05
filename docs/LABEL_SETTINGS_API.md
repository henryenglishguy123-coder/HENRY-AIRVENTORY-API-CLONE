# Label Settings API - Frontend Developer Guide

## Overview

This API allows factories to manage packaging label and hang tag pricing settings. Each factory can configure **ONE packaging label** and **ONE hang tag** with front and back pricing.

---

## Authentication

All endpoints require **Factory Authentication** using JWT token in the Authorization header:

```
Authorization: Bearer {jwt_token}
```

---

## Base URL

```
/api/v1/factories/label-settings
```

---

## Endpoints

### 1. Get Packaging Label Settings

**Endpoint:**

```
GET /api/v1/factories/label-settings/packaging-label
```

**Description:** Retrieve the current packaging label pricing configuration for the authenticated factory.

**Headers:**

```
Authorization: Bearer {jwt_token}
Accept: application/json
```

**Response (Success - 200):**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "factory_id": 5,
        "front_price": "150.50",
        "back_price": "120.25",
        "is_active": true,
        "created_at": "2024-02-21T10:30:00Z",
        "updated_at": "2024-02-21T10:30:00Z"
    },
    "message": "Packaging label settings retrieved successfully."
}
```

**Response (No Settings Found - 200):**

```json
{
    "success": true,
    "data": {
        "factory_id": 5,
        "front_price": 0,
        "back_price": 0,
        "is_active": false
    },
    "message": "No packaging label settings found."
}
```

**Response (Unauthorized - 401):**

```json
{
    "success": false,
    "data": null,
    "message": "Unauthorized"
}
```

**cURL Example:**

```bash
curl -X GET "http://localhost:8000/api/v1/factories/label-settings/packaging-label" \
  -H "Authorization: Bearer eyJhbGci..." \
  -H "Accept: application/json"
```

**JavaScript/Axios Example:**

```javascript
const token = localStorage.getItem("factory_token");

axios
    .get("/api/v1/factories/label-settings/packaging-label", {
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: "application/json",
        },
    })
    .then((response) => {
        console.log("Packaging Label:", response.data.data);
    })
    .catch((error) => {
        console.error("Error:", error.response.data.message);
    });
```

---

### 2. Update Packaging Label Settings

**Endpoint:**

```
PUT /api/v1/factories/label-settings/packaging-label
```

**Description:** Create or update the packaging label pricing configuration. If settings don't exist, they will be automatically created.

**Headers:**

```
Authorization: Bearer {jwt_token}
Content-Type: application/json
Accept: application/json
```

**Request Body:**

```json
{
    "front_price": 150.5,
    "back_price": 120.25,
    "is_active": true
}
```

**Field Validation:**
| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| front_price | Number | Yes | Min: 0 |
| back_price | Number | Yes | Min: 0 |
| is_active | Boolean | No | Default: false |

**Response (Success - 200):**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "factory_id": 5,
        "front_price": "150.50",
        "back_price": "120.25",
        "is_active": true,
        "created_at": "2024-02-21T10:30:00Z",
        "updated_at": "2024-02-21T10:35:00Z"
    },
    "message": "Packaging label settings updated successfully."
}
```

**Response (Validation Error - 422):**

```json
{
    "success": false,
    "data": null,
    "message": "Failed to update label settings: Validation error",
    "errors": {
        "front_price": ["The front_price field is required."],
        "back_price": ["The back_price field must be a number."]
    }
}
```

**cURL Example:**

```bash
curl -X PUT "http://localhost:8000/api/v1/factories/label-settings/packaging-label" \
  -H "Authorization: Bearer eyJhbGci..." \
  -H "Content-Type: application/json" \
  -d '{
    "front_price": 150.50,
    "back_price": 120.25,
    "is_active": true
  }'
```

**JavaScript/Axios Example:**

```javascript
const token = localStorage.getItem("factory_token");

axios
    .put(
        "/api/v1/factories/label-settings/packaging-label",
        {
            front_price: 150.5,
            back_price: 120.25,
            is_active: true,
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
        console.log("Updated:", response.data.data);
        // Handle success
    })
    .catch((error) => {
        console.error("Errors:", error.response.data.errors);
    });
```

---

### 3. Get Hang Tag Settings

**Endpoint:**

```
GET /api/v1/factories/label-settings/hang-tag
```

**Description:** Retrieve the current hang tag pricing configuration for the authenticated factory.

**Headers:**

```
Authorization: Bearer {jwt_token}
Accept: application/json
```

**Response (Success - 200):**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "factory_id": 5,
        "front_price": "75.00",
        "back_price": "50.99",
        "is_active": true,
        "created_at": "2024-02-21T10:30:00Z",
        "updated_at": "2024-02-21T10:30:00Z"
    },
    "message": "Hang tag settings retrieved successfully."
}
```

**Response (No Settings Found - 200):**

```json
{
    "success": true,
    "data": {
        "factory_id": 5,
        "front_price": 0,
        "back_price": 0,
        "is_active": false
    },
    "message": "No hang tag settings found."
}
```

**JavaScript/Axios Example:**

```javascript
const token = localStorage.getItem("factory_token");

axios
    .get("/api/v1/factories/label-settings/hang-tag", {
        headers: {
            Authorization: `Bearer ${token}`,
            Accept: "application/json",
        },
    })
    .then((response) => {
        console.log("Hang Tag:", response.data.data);
    })
    .catch((error) => {
        console.error("Error:", error.response.data.message);
    });
```

---

### 4. Update Hang Tag Settings

**Endpoint:**

```
PUT /api/v1/factories/label-settings/hang-tag
```

**Description:** Create or update the hang tag pricing configuration.

**Headers:**

```
Authorization: Bearer {jwt_token}
Content-Type: application/json
Accept: application/json
```

**Request Body:**

```json
{
    "front_price": 75.0,
    "back_price": 50.99,
    "is_active": true
}
```

**Field Validation:**
| Field | Type | Required | Constraints |
|-------|------|----------|-------------|
| front_price | Number | Yes | Min: 0 |
| back_price | Number | Yes | Min: 0 |
| is_active | Boolean | No | Default: false |

**Response (Success - 200):**

```json
{
    "success": true,
    "data": {
        "id": 1,
        "factory_id": 5,
        "front_price": "75.00",
        "back_price": "50.99",
        "is_active": true,
        "created_at": "2024-02-21T10:30:00Z",
        "updated_at": "2024-02-21T10:40:00Z"
    },
    "message": "Hang tag settings updated successfully."
}
```

**JavaScript/Axios Example:**

```javascript
const token = localStorage.getItem("factory_token");

axios
    .put(
        "/api/v1/factories/label-settings/hang-tag",
        {
            front_price: 75.0,
            back_price: 50.99,
            is_active: true,
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
        console.log("Updated:", response.data.data);
    })
    .catch((error) => {
        console.error("Errors:", error.response.data.errors);
    });
```

---

## Frontend Implementation Examples

### React Component Example

```jsx
import React, { useState, useEffect } from "react";
import axios from "axios";

const LabelSettingsForm = () => {
    const [packagingLabel, setPackagingLabel] = useState({
        front_price: 0,
        back_price: 0,
        is_active: false,
    });

    const [hangTag, setHangTag] = useState({
        front_price: 0,
        back_price: 0,
        is_active: false,
    });

    const [loading, setLoading] = useState(true);
    const [error, setError] = useState(null);

    const token = localStorage.getItem("factory_token");
    const headers = {
        Authorization: `Bearer ${token}`,
        "Content-Type": "application/json",
    };

    // Fetch settings on mount
    useEffect(() => {
        fetchSettings();
    }, []);

    const fetchSettings = async () => {
        try {
            setLoading(true);
            const [labelRes, tagRes] = await Promise.all([
                axios.get("/api/v1/factories/label-settings/packaging-label", {
                    headers,
                }),
                axios.get("/api/v1/factories/label-settings/hang-tag", {
                    headers,
                }),
            ]);

            setPackagingLabel(labelRes.data.data);
            setHangTag(tagRes.data.data);
            setError(null);
        } catch (err) {
            setError(err.response?.data?.message || "Failed to load settings");
        } finally {
            setLoading(false);
        }
    };

    const updatePackagingLabel = async (e) => {
        e.preventDefault();
        try {
            const response = await axios.put(
                "/api/v1/factories/label-settings/packaging-label",
                {
                    front_price: parseFloat(packagingLabel.front_price),
                    back_price: parseFloat(packagingLabel.back_price),
                    is_active: packagingLabel.is_active,
                },
                { headers },
            );
            setPackagingLabel(response.data.data);
            alert("Packaging label updated successfully!");
        } catch (err) {
            const errors = err.response?.data?.errors;
            if (errors) {
                alert("Validation errors: " + JSON.stringify(errors));
            } else {
                alert(err.response?.data?.message || "Update failed");
            }
        }
    };

    const updateHangTag = async (e) => {
        e.preventDefault();
        try {
            const response = await axios.put(
                "/api/v1/factories/label-settings/hang-tag",
                {
                    front_price: parseFloat(hangTag.front_price),
                    back_price: parseFloat(hangTag.back_price),
                    is_active: hangTag.is_active,
                },
                { headers },
            );
            setHangTag(response.data.data);
            alert("Hang tag updated successfully!");
        } catch (err) {
            const errors = err.response?.data?.errors;
            if (errors) {
                alert("Validation errors: " + JSON.stringify(errors));
            } else {
                alert(err.response?.data?.message || "Update failed");
            }
        }
    };

    if (loading) return <div>Loading settings...</div>;
    if (error) return <div>Error: {error}</div>;

    return (
        <div className="settings-container">
            <h1>Label Settings</h1>

            {/* Packaging Label Form */}
            <form onSubmit={updatePackagingLabel} className="form-group">
                <h2>Packaging Label Pricing</h2>

                <div>
                    <label>Front Price:</label>
                    <input
                        type="number"
                        step="0.01"
                        value={packagingLabel.front_price}
                        onChange={(e) =>
                            setPackagingLabel({
                                ...packagingLabel,
                                front_price: e.target.value,
                            })
                        }
                        placeholder="0.00"
                        required
                    />
                </div>

                <div>
                    <label>Back Price:</label>
                    <input
                        type="number"
                        step="0.01"
                        value={packagingLabel.back_price}
                        onChange={(e) =>
                            setPackagingLabel({
                                ...packagingLabel,
                                back_price: e.target.value,
                            })
                        }
                        placeholder="0.00"
                        required
                    />
                </div>

                <div>
                    <label>
                        <input
                            type="checkbox"
                            checked={packagingLabel.is_active}
                            onChange={(e) =>
                                setPackagingLabel({
                                    ...packagingLabel,
                                    is_active: e.target.checked,
                                })
                            }
                        />
                        Active
                    </label>
                </div>

                <button type="submit">Update Packaging Label</button>
            </form>

            {/* Hang Tag Form */}
            <form onSubmit={updateHangTag} className="form-group">
                <h2>Hang Tag Pricing</h2>

                <div>
                    <label>Front Price:</label>
                    <input
                        type="number"
                        step="0.01"
                        value={hangTag.front_price}
                        onChange={(e) =>
                            setHangTag({
                                ...hangTag,
                                front_price: e.target.value,
                            })
                        }
                        placeholder="0.00"
                        required
                    />
                </div>

                <div>
                    <label>Back Price:</label>
                    <input
                        type="number"
                        step="0.01"
                        value={hangTag.back_price}
                        onChange={(e) =>
                            setHangTag({
                                ...hangTag,
                                back_price: e.target.value,
                            })
                        }
                        placeholder="0.00"
                        required
                    />
                </div>

                <div>
                    <label>
                        <input
                            type="checkbox"
                            checked={hangTag.is_active}
                            onChange={(e) =>
                                setHangTag({
                                    ...hangTag,
                                    is_active: e.target.checked,
                                })
                            }
                        />
                        Active
                    </label>
                </div>

                <button type="submit">Update Hang Tag</button>
            </form>
        </div>
    );
};

export default LabelSettingsForm;
```

---

## Vue 3 Component Example

```vue
<template>
    <div class="settings-container">
        <h1>Label Settings</h1>

        <div v-if="loading" class="loading">Loading settings...</div>
        <div v-if="error" class="error">{{ error }}</div>

        <template v-if="!loading">
            <!-- Packaging Label Form -->
            <form @submit.prevent="updatePackagingLabel" class="form-group">
                <h2>Packaging Label Pricing</h2>

                <div class="form-field">
                    <label>Front Price:</label>
                    <input
                        v-model.number="packagingLabel.front_price"
                        type="number"
                        step="0.01"
                        placeholder="0.00"
                        required
                    />
                </div>

                <div class="form-field">
                    <label>Back Price:</label>
                    <input
                        v-model.number="packagingLabel.back_price"
                        type="number"
                        step="0.01"
                        placeholder="0.00"
                        required
                    />
                </div>

                <div class="form-field">
                    <label>
                        <input
                            v-model="packagingLabel.is_active"
                            type="checkbox"
                        />
                        Active
                    </label>
                </div>

                <button type="submit">Update Packaging Label</button>
            </form>

            <!-- Hang Tag Form -->
            <form @submit.prevent="updateHangTag" class="form-group">
                <h2>Hang Tag Pricing</h2>

                <div class="form-field">
                    <label>Front Price:</label>
                    <input
                        v-model.number="hangTag.front_price"
                        type="number"
                        step="0.01"
                        placeholder="0.00"
                        required
                    />
                </div>

                <div class="form-field">
                    <label>Back Price:</label>
                    <input
                        v-model.number="hangTag.back_price"
                        type="number"
                        step="0.01"
                        placeholder="0.00"
                        required
                    />
                </div>

                <div class="form-field">
                    <label>
                        <input v-model="hangTag.is_active" type="checkbox" />
                        Active
                    </label>
                </div>

                <button type="submit">Update Hang Tag</button>
            </form>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted } from "vue";
import axios from "axios";

const packagingLabel = ref({
    front_price: 0,
    back_price: 0,
    is_active: false,
});

const hangTag = ref({
    front_price: 0,
    back_price: 0,
    is_active: false,
});

const loading = ref(true);
const error = ref(null);

const token = localStorage.getItem("factory_token");
const headers = {
    Authorization: `Bearer ${token}`,
    "Content-Type": "application/json",
};

const fetchSettings = async () => {
    try {
        loading.value = true;
        const [labelRes, tagRes] = await Promise.all([
            axios.get("/api/v1/factories/label-settings/packaging-label", {
                headers,
            }),
            axios.get("/api/v1/factories/label-settings/hang-tag", { headers }),
        ]);

        packagingLabel.value = labelRes.data.data;
        hangTag.value = tagRes.data.data;
        error.value = null;
    } catch (err) {
        error.value = err.response?.data?.message || "Failed to load settings";
    } finally {
        loading.value = false;
    }
};

const updatePackagingLabel = async () => {
    try {
        const response = await axios.put(
            "/api/v1/factories/label-settings/packaging-label",
            {
                front_price: packagingLabel.value.front_price,
                back_price: packagingLabel.value.back_price,
                is_active: packagingLabel.value.is_active,
            },
            { headers },
        );
        packagingLabel.value = response.data.data;
        alert("Packaging label updated successfully!");
    } catch (err) {
        const errors = err.response?.data?.errors;
        if (errors) {
            alert("Validation errors: " + JSON.stringify(errors));
        } else {
            alert(err.response?.data?.message || "Update failed");
        }
    }
};

const updateHangTag = async () => {
    try {
        const response = await axios.put(
            "/api/v1/factories/label-settings/hang-tag",
            {
                front_price: hangTag.value.front_price,
                back_price: hangTag.value.back_price,
                is_active: hangTag.value.is_active,
            },
            { headers },
        );
        hangTag.value = response.data.data;
        alert("Hang tag updated successfully!");
    } catch (err) {
        const errors = err.response?.data?.errors;
        if (errors) {
            alert("Validation errors: " + JSON.stringify(errors));
        } else {
            alert(err.response?.data?.message || "Update failed");
        }
    }
};

onMounted(() => {
    fetchSettings();
});
</script>

<style scoped>
.settings-container {
    max-width: 800px;
    margin: 0 auto;
    padding: 20px;
}

.form-group {
    border: 1px solid #ddd;
    padding: 20px;
    margin: 20px 0;
    border-radius: 8px;
}

.form-field {
    margin-bottom: 15px;
}

.form-field label {
    display: block;
    margin-bottom: 5px;
    font-weight: 500;
}

.form-field input {
    width: 100%;
    padding: 8px;
    border: 1px solid #ccc;
    border-radius: 4px;
}

button {
    background: #007bff;
    color: white;
    padding: 10px 20px;
    border: none;
    border-radius: 4px;
    cursor: pointer;
}

button:hover {
    background: #0056b3;
}

.loading,
.error {
    padding: 20px;
    margin: 20px 0;
    border-radius: 4px;
}

.error {
    background: #f8d7da;
    color: #721c24;
}

.loading {
    background: #d1ecf1;
    color: #0c5460;
}
</style>
```

---

## Error Handling

### Common Errors

**401 Unauthorized**

- Missing or invalid JWT token
- Token expired
- User not authenticated as a factory

**422 Validation Error**

- Invalid input data
- Missing required fields
- Invalid field values

**500 Internal Server Error**

- Server-side issue
- Database error

### Best Practices

```javascript
// Always wrap API calls in try-catch
try {
    const response = await axios.put(
        "/api/v1/factories/label-settings/packaging-label",
        data,
        { headers },
    );
    // Success handling
} catch (error) {
    // Check for validation errors
    if (error.response?.status === 422) {
        console.error("Validation Errors:", error.response.data.errors);
    }
    // Check for auth errors
    else if (error.response?.status === 401) {
        // Redirect to login
        window.location.href = "/login";
    }
    // Generic error
    else {
        console.error("Error:", error.response?.data?.message);
    }
}
```

---

## Data Types & Formats

### Decimal Values

All prices are returned as strings with 2 decimal places:

- `"150.50"` ✅ Correct
- `150.50` ❌ Not recommended

Convert to number before calculations:

```javascript
const price = parseFloat(data.front_price);
```

### Boolean Values

- `true` = Label/tag is active and usable
- `false` = Label/tag is inactive and should not be used

### Timestamps

All timestamps are in ISO 8601 format (UTC):

```
"2024-02-21T10:30:00Z"
```

---

## Testing the API

### Using Postman

1. **Set Environment Variable:**
    - Create variable: `factory_token` = your JWT token

2. **Create Request:**
    - Method: PUT
    - URL: `{{base_url}}/api/v1/factories/label-settings/packaging-label`
    - Headers: Add `Authorization: Bearer {{factory_token}}`
    - Body (JSON):

    ```json
    {
        "front_price": 150.5,
        "back_price": 120.25,
        "is_active": true
    }
    ```

3. **Send & Inspect Response**

---

## Troubleshooting

| Issue                    | Solution                                         |
| ------------------------ | ------------------------------------------------ |
| 401 Unauthorized         | Check token validity, ensure it hasn't expired   |
| CORS Error               | Verify frontend domain is whitelisted on backend |
| Invalid prices           | Ensure prices are numbers ≥ 0                    |
| Settings not persisting  | Check response status code (should be 200)       |
| Decimal precision issues | Use string comparison or parse to float          |

---

## Quick Reference

```
Packaging Label:
  GET  /label-settings/packaging-label
  PUT  /label-settings/packaging-label

Hang Tag:
  GET  /label-settings/hang-tag
  PUT  /label-settings/hang-tag
```

All endpoints require `Authorization: Bearer {token}` header.

Prices must be valid numbers ≥ 0.

One record per factory (auto-created on first update).
