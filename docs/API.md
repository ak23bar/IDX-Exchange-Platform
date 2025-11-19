# 🔌 API Integration Guide

## Overview

This platform integrates with the **Trestle API** by CoreLogic to retrieve real-time MLS (Multiple Listing Service) data from the California Regional MLS (CRMLS). The API provides access to property listings, photos, and metadata using the **OData protocol** with **OAuth 2.0** authentication.

**API Provider:** CoreLogic  
**API Name:** Trestle  
**Protocol:** OData v4  
**Authentication:** OAuth 2.0 (Client Credentials Flow)  
**Base URL:** `https://api-trestle.corelogic.com/trestle`

---

## 📋 Table of Contents

- [Authentication Flow](#authentication-flow)
- [Token Management](#token-management)
- [Property Data Retrieval](#property-data-retrieval)
- [API Endpoints](#api-endpoints)
- [Request/Response Examples](#requestresponse-examples)
- [Rate Limits](#rate-limits)
- [Error Handling](#error-handling)
- [Cron Job Setup](#cron-job-setup)

---

## 🔐 Authentication Flow

### OAuth 2.0 Client Credentials

The Trestle API uses **OAuth 2.0 Client Credentials Grant** for machine-to-machine authentication.

**Flow Diagram:**
```
┌────────────────┐
│ Application    │
│ (generate_     │
│  token.php)    │
└───────┬────────┘
        │
        │ 1. POST /oidc/token
        │    client_id + client_secret
        ▼
┌────────────────┐
│  Trestle API   │
│  Token Server  │
└───────┬────────┘
        │
        │ 2. Response
        │    access_token + expires_in
        ▼
┌────────────────┐
│  MySQL DB      │
│  token table   │
└────────────────┘
```

### Credentials Required

Store these in environment variables or config file outside web root:

```php
// .idx_secrets.php (outside web root: /home/user/.idx_secrets.php)
<?php
return [
    'trestle_client_id'     => 'your_client_id_here',
    'trestle_client_secret' => 'your_client_secret_here',
    'token_url'             => 'https://api-trestle.corelogic.com/trestle/oidc/token',
    'token_type'            => 'trestle'
];
```

---

## 🎫 Token Management

### Token Generation Script

**File:** `api/generate_token.php`

**Purpose:**
- Request new OAuth token from Trestle API
- Store token in database with expiration time
- Check existing token validity before requesting new one

**Execution:**
```bash
# Manual execution
php /path/to/api/generate_token.php

# Cron schedule (every 55 minutes)
*/55 * * * * /usr/bin/php /path/to/api/generate_token.php >> /var/log/token_refresh.log 2>&1
```

**Code Flow:**
```php
<?php
// 1. Load credentials
$cfg = require '/home/user/.idx_secrets.php';

// 2. Check cached token
$stmt = $pdo->prepare('
    SELECT access_token, expires_at 
    FROM token 
    WHERE token_type = :type 
      AND expires_at > NOW() + INTERVAL 2 MINUTE
');
$stmt->execute([':type' => 'trestle']);
$row = $stmt->fetch();

if ($row) {
    echo "Token still valid\n";
    exit;
}

// 3. Request new token
$response = http_post($cfg['token_url'], [
    'grant_type'    => 'client_credentials',
    'client_id'     => $cfg['trestle_client_id'],
    'client_secret' => $cfg['trestle_client_secret']
]);

// 4. Parse and store
$data = json_decode($response, true);
$access_token = $data['access_token'];
$expires_in   = $data['expires_in']; // seconds
$expires_at   = date('Y-m-d H:i:s', time() + $expires_in);

$stmt = $pdo->prepare('
    INSERT INTO token (token_type, access_token, expires_at)
    VALUES (:type, :token, :expires)
    ON DUPLICATE KEY UPDATE 
        access_token = VALUES(access_token),
        expires_at = VALUES(expires_at)
');
$stmt->execute([
    ':type'    => 'trestle',
    ':token'   => $access_token,
    ':expires' => $expires_at
]);
```

**Token Lifespan:**
- **Typical expiration:** 3600 seconds (1 hour)
- **Refresh schedule:** Every 55 minutes (5-minute buffer)
- **Storage:** MySQL `token` table

---

## 🏠 Property Data Retrieval

### Data Sync Script

**File:** `api/fetch_property.php`

**Purpose:**
- Fetch property listings from Trestle OData endpoint
- Parse JSON responses
- Upsert data into `rets_property` table
- Maintain pagination offset in `app_state`

**Execution:**
```bash
# Manual execution
php /path/to/api/fetch_property.php

# Cron schedule (hourly)
0 * * * * /usr/bin/php /path/to/api/fetch_property.php >> /var/log/property_sync.log 2>&1
```

**Data Flow:**
```
1. Read access_token from MySQL
2. Read current offset from app_state
3. Build OData query with filters
4. HTTP GET to Trestle API
5. Parse JSON response
6. For each property:
   - Extract fields
   - UPSERT into rets_property
7. Update offset in app_state
8. Log success/errors
```

---

## 🌐 API Endpoints

### 1. Token Endpoint

**URL:** `https://api-trestle.corelogic.com/trestle/oidc/token`  
**Method:** `POST`  
**Content-Type:** `application/x-www-form-urlencoded`

**Request:**
```http
POST /trestle/oidc/token HTTP/1.1
Host: api-trestle.corelogic.com
Content-Type: application/x-www-form-urlencoded

grant_type=client_credentials&client_id=YOUR_CLIENT_ID&client_secret=YOUR_CLIENT_SECRET
```

**Response:**
```json
{
  "access_token": "eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...",
  "token_type": "Bearer",
  "expires_in": 3600
}
```

---

### 2. Property Listings Endpoint

**URL:** `https://api-trestle.corelogic.com/trestle/odata/Property`  
**Method:** `GET`  
**Protocol:** OData v4  
**Authentication:** Bearer token

**Base Query:**
```http
GET /trestle/odata/Property?$filter=PropertyType eq 'Residential'&$top=100&$skip=0 HTTP/1.1
Host: api-trestle.corelogic.com
Authorization: Bearer eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...
Accept: application/json
```

**OData Query Parameters:**

| Parameter | Description | Example |
|-----------|-------------|---------|
| `$filter` | Filter results by criteria | `PropertyType eq 'Residential'` |
| `$top` | Limit number of results | `100` |
| `$skip` | Pagination offset | `0`, `100`, `200` |
| `$orderby` | Sort results | `ModificationTimestamp desc` |
| `$select` | Specify fields to return | `ListingId,Address,Price` |
| `$expand` | Include related entities | `Media,Rooms` |

**Common Filters:**
```odata
# Active residential properties in LA
$filter=PropertyType eq 'Residential' and City eq 'Los Angeles' and Status eq 'Active'

# Price range
$filter=ListPrice ge 500000 and ListPrice le 1000000

# Updated in last 24 hours
$filter=ModificationTimestamp gt 2025-01-17T00:00:00Z

# Multiple conditions
$filter=PropertyType eq 'Residential' and BedroomsTotal ge 3 and BathroomsTotalInteger ge 2
```

---

## 📨 Request/Response Examples

### Example 1: Fetch First 100 Properties

**cURL Request:**
```bash
curl -X GET \
  'https://api-trestle.corelogic.com/trestle/odata/Property?$top=100&$skip=0&$orderby=ModificationTimestamp desc' \
  -H 'Authorization: Bearer eyJhbGci...' \
  -H 'Accept: application/json'
```

**PHP Implementation:**
```php
<?php
$access_token = 'eyJhbGci...'; // From database
$url = 'https://api-trestle.corelogic.com/trestle/odata/Property';
$params = http_build_query([
    '$top'     => 100,
    '$skip'    => 0,
    '$orderby' => 'ModificationTimestamp desc'
]);

$ch = curl_init("$url?$params");
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_HTTPHEADER     => [
        'Authorization: Bearer ' . $access_token,
        'Accept: application/json'
    ]
]);

$response = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
curl_close($ch);

if ($http_code === 200) {
    $data = json_decode($response, true);
    $properties = $data['value']; // Array of property objects
}
```

**Response Structure:**
```json
{
  "@odata.context": "https://api-trestle.corelogic.com/trestle/odata/$metadata#Property",
  "@odata.count": 1247,
  "value": [
    {
      "ListingId": "CA12345678",
      "ListingKey": "12345678",
      "Address": {
        "StreetNumber": "123",
        "StreetName": "Main",
        "StreetSuffix": "Street",
        "City": "Los Angeles",
        "StateOrProvince": "CA",
        "PostalCode": "90001"
      },
      "ListPrice": 750000,
      "BedroomsTotal": 3,
      "BathroomsTotalInteger": 2,
      "LivingArea": 1850,
      "PropertyType": "Residential",
      "StandardStatus": "Active",
      "ModificationTimestamp": "2025-01-18T14:30:00Z",
      "Media": [
        {
          "MediaURL": "https://photos.example.com/img1.jpg",
          "Order": 1
        },
        {
          "MediaURL": "https://photos.example.com/img2.jpg",
          "Order": 2
        }
      ],
      "PublicRemarks": "Beautiful modern home with upgraded kitchen..."
    },
    // ... more properties
  ]
}
```

---

### Example 2: Filter by City and Price

**Request:**
```bash
curl -X GET \
  'https://api-trestle.corelogic.com/trestle/odata/Property?\$filter=City eq '"'"'San Diego'"'"' and ListPrice le 800000&\$top=50' \
  -H 'Authorization: Bearer eyJhbGci...' \
  -H 'Accept: application/json'
```

**PHP:**
```php
$filter = "City eq 'San Diego' and ListPrice le 800000";
$url = "https://api-trestle.corelogic.com/trestle/odata/Property";
$params = http_build_query([
    '$filter' => $filter,
    '$top'    => 50
]);
// ... rest of cURL code
```

---

## ⚠️ Rate Limits

### Current Limits (as of 2025)

| Metric | Limit |
|--------|-------|
| Requests per minute | 60 |
| Requests per hour | 1,000 |
| Requests per day | 10,000 |
| Concurrent requests | 5 |

**Best Practices:**
- Implement exponential backoff on rate limit errors
- Cache responses when possible
- Use `$skip` and `$top` for pagination (don't request all at once)
- Schedule sync jobs during off-peak hours

**Rate Limit Headers:**
```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 45
X-RateLimit-Reset: 1642521600
```

---

## 🐛 Error Handling

### HTTP Status Codes

| Code | Meaning | Action |
|------|---------|--------|
| 200 | Success | Process response |
| 401 | Unauthorized | Refresh token |
| 403 | Forbidden | Check API credentials |
| 429 | Too Many Requests | Wait and retry with backoff |
| 500 | Server Error | Log error, retry later |
| 503 | Service Unavailable | Wait 5 minutes, retry |

### Error Response Example

```json
{
  "error": {
    "code": "InvalidAuthenticationToken",
    "message": "Access token has expired.",
    "innerError": {
      "date": "2025-01-18T15:00:00",
      "request-id": "abc-123-def"
    }
  }
}
```

### PHP Error Handling

```php
function fetch_properties($access_token, $offset = 0) {
    $max_retries = 3;
    $attempt = 0;
    
    while ($attempt < $max_retries) {
        try {
            $response = http_get($url, $access_token);
            $code = $response['http_code'];
            
            if ($code === 200) {
                return json_decode($response['body'], true);
            }
            
            if ($code === 401) {
                // Token expired - regenerate
                exec('php /path/to/generate_token.php');
                $access_token = get_fresh_token();
                $attempt++;
                continue;
            }
            
            if ($code === 429) {
                // Rate limited - exponential backoff
                sleep(pow(2, $attempt) * 5);
                $attempt++;
                continue;
            }
            
            throw new Exception("HTTP $code: " . $response['body']);
            
        } catch (Exception $e) {
            error_log("API Error: " . $e->getMessage());
            $attempt++;
            sleep(5);
        }
    }
    
    throw new Exception("Max retries exceeded");
}
```

---

## ⏰ Cron Job Setup

### Recommended Schedule

**Token Refresh (every 55 minutes):**
```cron
*/55 * * * * /usr/bin/php /home/user/public_html/api/generate_token.php >> /var/log/idx/token.log 2>&1
```

**Property Sync (every hour at minute 0):**
```cron
0 * * * * /usr/bin/php /home/user/public_html/api/fetch_property.php >> /var/log/idx/sync.log 2>&1
```

**Daily Cleanup (2 AM):**
```cron
0 2 * * * /usr/bin/php /home/user/public_html/scripts/cleanup.php >> /var/log/idx/cleanup.log 2>&1
```

### Logging Best Practices

**Good log format:**
```php
function log_message($level, $message) {
    $timestamp = date('Y-m-d H:i:s');
    $log = "[$timestamp] [$level] $message\n";
    file_put_contents('/var/log/idx/sync.log', $log, FILE_APPEND);
}

log_message('INFO', 'Starting property sync');
log_message('SUCCESS', "Synced 100 properties, offset now at 200");
log_message('ERROR', 'Token refresh failed: ' . $error);
```

**Sample log output:**
```
[2025-01-18 10:00:01] [INFO] Starting property sync
[2025-01-18 10:00:15] [SUCCESS] Fetched 100 properties from offset 0
[2025-01-18 10:00:16] [INFO] Upserting properties into database
[2025-01-18 10:00:22] [SUCCESS] Upserted 100 properties
[2025-01-18 10:00:22] [INFO] Updated app_state offset to 100
[2025-01-18 10:00:22] [SUCCESS] Sync completed successfully
```

---

## 🧪 Testing the Integration

### Manual API Test

```bash
#!/bin/bash
# test_api.sh

TOKEN="your_access_token_here"
URL="https://api-trestle.corelogic.com/trestle/odata/Property"

echo "Testing Trestle API..."
curl -v -X GET \
  "$URL?\$top=1" \
  -H "Authorization: Bearer $TOKEN" \
  -H "Accept: application/json" \
  | jq '.'
```

### PHP Test Script

```php
<?php
// test_integration.php
require 'api/generate_token.php';
require 'api/fetch_property.php';

echo "Step 1: Generate token\n";
$token = generate_or_get_token();
echo "Token: " . substr($token, 0, 20) . "...\n\n";

echo "Step 2: Fetch 5 properties\n";
$properties = fetch_properties($token, 0, 5);
echo "Fetched " . count($properties) . " properties\n";

echo "\nFirst property:\n";
print_r($properties[0]);
```

---

## 📚 Additional Resources

- **Trestle API Documentation:** [CoreLogic Developer Portal](https://developer.corelogic.com)
- **OData v4 Specification:** [odata.org](https://www.odata.org/documentation/)
- **OAuth 2.0 RFC:** [RFC 6749](https://tools.ietf.org/html/rfc6749)

---

**Last Updated:** January 18, 2025  
**Maintained by:** SD6 Team @ IDXExchange
