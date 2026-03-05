# Factory Countries API Documentation

## Overview

This document describes the Factory Countries API endpoint, which retrieves a list of countries where verified and active factories are located. This is useful for filtering products or searching for manufacturers based on their geographical location.

## API Endpoints

### 1. Get Factory Countries

**Endpoint:** `GET /api/v1/location/factory-countries`

**Description:** Retrieves a list of unique countries associated with factories that have:
- `account_status = 1` (Active)
- `account_verified = 1` (Verified)

**Authentication:** Not required (Public endpoint)

#### Request

**Headers:**
```
Accept: application/json
```

**Parameters:** None

#### Response

**Status Code:** `200 OK`

**Body:**

```json
{
    "success": true,
    "data": [
        {
            "id": 1,
            "name": "United States",
            "iso3": "USA",
            "iso2": "US"
        },
        {
            "id": 2,
            "name": "Canada",
            "iso3": "CAN",
            "iso2": "CA"
        }
    ]
}
```

**Response Fields:**

| Field | Type | Description |
|-------|------|-------------|
| success | boolean | Request status |
| data | array | List of country objects |
| data[].id | integer | Country ID |
| data[].name | string | Country name |
| data[].iso3 | string | ISO 3-letter country code |
| data[].iso2 | string | ISO 2-letter country code |

---

## Error Handling

| Status Code | Description |
|-------------|-------------|
| 200 | Successful retrieval |
| 500 | Internal Server Error |
