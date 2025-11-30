# 🗄️ Database Architecture

## Overview

The Full-Stack MLS Property Platform uses a **MySQL 8.0+** relational database to store property listings, authentication tokens, and application state. The database is optimized for read-heavy workloads with frequent property searches and filtering operations.

**Database Name:** `boxgra6_cali`  
**Character Set:** `utf8mb4`  
**Collation:** `utf8mb4_unicode_ci`

---

## 📊 Schema Diagram

```
┌─────────────────────────────────────────────────────────────┐
│                       rets_property                          │
├─────────────────────────────────────────────────────────────┤
│ PK  L_ListingID        VARCHAR(50)                          │
│     L_Address          VARCHAR(255)                         │
│     L_City             VARCHAR(100)                         │
│     L_Zip              VARCHAR(10)                          │
│     L_SystemPrice      DECIMAL(12,2)                        │
│     L_Keyword2         VARCHAR(10)      -- Bedrooms         │
│     LM_Int2_3          INT              -- Bathrooms        │
│     LM_Dec_3           DECIMAL(10,2)    -- Sqft             │
│     L_Photos           JSON             -- Image URLs       │
│     L_UpdateDate       TIMESTAMP                            │
│     L_ListDate         TIMESTAMP                            │
│     L_PropertyType     VARCHAR(50)                          │
│     L_Status           VARCHAR(20)                          │
│     created_at         TIMESTAMP DEFAULT CURRENT_TIMESTAMP  │
└─────────────────────────────────────────────────────────────┘
                                │
                                │
        ┌───────────────────────┼───────────────────────┐
        │                       │                       │
        ▼                       ▼                       ▼
┌──────────────┐        ┌──────────────┐      ┌──────────────┐
│    token     │        │  app_state   │      │ search_log   │
├──────────────┤        ├──────────────┤      ├──────────────┤
│ PK id        │        │ PK k         │      │ PK id        │
│    type      │        │    v         │      │    query     │
│    token     │        │    updated   │      │    filters   │
│    expires   │        └──────────────┘      │    count     │
└──────────────┘                              │    timestamp │
                                              └──────────────┘
```

---

## 📋 Table Details

### 1. `rets_property` (Primary Table)

Stores all MLS property listings retrieved from the Trestle API.

```sql
CREATE TABLE rets_property (
  -- Primary Key
  L_ListingID VARCHAR(50) PRIMARY KEY COMMENT 'Unique MLS listing ID',
  
  -- Address Information
  L_Address VARCHAR(255) NOT NULL COMMENT 'Street address',
  L_City VARCHAR(100) NOT NULL COMMENT 'City name',
  L_Zip VARCHAR(10) NOT NULL COMMENT 'ZIP code',
  L_County VARCHAR(50) DEFAULT NULL COMMENT 'County name',
  
  -- Pricing
  L_SystemPrice DECIMAL(12,2) NOT NULL COMMENT 'Current listing price (USD)',
  L_OriginalPrice DECIMAL(12,2) DEFAULT NULL COMMENT 'Original list price',
  
  -- Property Details
  L_Keyword2 VARCHAR(10) DEFAULT NULL COMMENT 'Number of bedrooms',
  LM_Int2_3 INT DEFAULT NULL COMMENT 'Number of bathrooms',
  LM_Dec_3 DECIMAL(10,2) DEFAULT NULL COMMENT 'Square footage',
  L_PropertyType VARCHAR(50) DEFAULT 'Residential' COMMENT 'Property type',
  L_YearBuilt INT DEFAULT NULL COMMENT 'Year constructed',
  L_LotSize DECIMAL(10,2) DEFAULT NULL COMMENT 'Lot size (acres)',
  
  -- Media
  L_Photos JSON DEFAULT NULL COMMENT 'Array of image URLs',
  L_VirtualTourURL VARCHAR(500) DEFAULT NULL COMMENT 'Virtual tour link',
  
  -- Status & Dates
  L_Status VARCHAR(20) DEFAULT 'Active' COMMENT 'Listing status',
  L_UpdateDate TIMESTAMP NULL DEFAULT NULL COMMENT 'Last updated by MLS',
  L_ListDate TIMESTAMP NULL DEFAULT NULL COMMENT 'Date listed',
  L_CloseDate TIMESTAMP NULL DEFAULT NULL COMMENT 'Closing date if sold',
  
  -- Description
  L_Description TEXT DEFAULT NULL COMMENT 'Property description',
  L_PublicRemarks TEXT DEFAULT NULL COMMENT 'Agent remarks',
  
  -- Internal Tracking
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT 'Record creation time',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  -- Indexes for Performance
  INDEX idx_city (L_City),
  INDEX idx_zip (L_Zip),
  INDEX idx_price (L_SystemPrice),
  INDEX idx_beds (L_Keyword2),
  INDEX idx_sqft (LM_Dec_3),
  INDEX idx_update (L_UpdateDate),
  INDEX idx_status (L_Status),
  INDEX idx_city_price (L_City, L_SystemPrice)
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='MLS property listings from CRMLS via Trestle API';
```

**Sample Data:**
```sql
INSERT INTO rets_property VALUES (
  'CA12345678',                    -- L_ListingID
  '123 Main Street',               -- L_Address
  'Los Angeles',                   -- L_City
  '90001',                         -- L_Zip
  'Los Angeles',                   -- L_County
  750000.00,                       -- L_SystemPrice
  799000.00,                       -- L_OriginalPrice
  '3',                             -- L_Keyword2 (Beds)
  2,                               -- LM_Int2_3 (Baths)
  1850.00,                         -- LM_Dec_3 (Sqft)
  'Residential',                   -- L_PropertyType
  2015,                            -- L_YearBuilt
  0.25,                            -- L_LotSize
  '["https://example.com/img1.jpg", "https://example.com/img2.jpg"]', -- L_Photos
  'https://tour.example.com/123',  -- L_VirtualTourURL
  'Active',                        -- L_Status
  '2025-01-15 14:30:00',          -- L_UpdateDate
  '2025-01-10 09:00:00',          -- L_ListDate
  NULL,                            -- L_CloseDate
  'Beautiful modern home...',      -- L_Description
  'Move-in ready...',              -- L_PublicRemarks
  CURRENT_TIMESTAMP,               -- created_at
  CURRENT_TIMESTAMP                -- updated_at
);
```

---

### 2. `token` (API Authentication)

Stores OAuth 2.0 access tokens for Trestle API authentication.

```sql
CREATE TABLE token (
  id INT AUTO_INCREMENT PRIMARY KEY,
  token_type VARCHAR(50) NOT NULL UNIQUE COMMENT 'Token identifier (e.g., trestle)',
  access_token TEXT NOT NULL COMMENT 'Bearer token',
  expires_at TIMESTAMP NOT NULL COMMENT 'Token expiration time (UTC)',
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_expires (expires_at),
  INDEX idx_type (token_type)
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='OAuth tokens for API authentication';
```

**Sample Data:**
```sql
INSERT INTO token (token_type, access_token, expires_at) VALUES (
  'trestle',
  'eyJhbGciOiJSUzI1NiIsInR5cCI6IkpXVCJ9...',
  '2025-01-18 15:00:00'
);
```

**Usage Pattern:**
```php
// Check if token is still valid (with 2-minute buffer)
$stmt = $pdo->prepare('
  SELECT access_token, expires_at 
  FROM token 
  WHERE token_type = :type 
    AND expires_at > NOW() + INTERVAL 2 MINUTE
  LIMIT 1
');
$stmt->execute([':type' => 'trestle']);
$token = $stmt->fetch();
```

---

### 3. `app_state` (Application State)

Key-value store for application-level configuration and state management.

```sql
CREATE TABLE app_state (
  k VARCHAR(64) PRIMARY KEY COMMENT 'State key',
  v VARCHAR(255) NOT NULL COMMENT 'State value',
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Application state and configuration';
```

**Common Keys:**
```sql
INSERT INTO app_state (k, v) VALUES
('api_offset', '0'),              -- Current API pagination offset
('last_sync', '2025-01-18 10:00:00'),  -- Last successful sync
('sync_status', 'completed'),     -- Current sync status
('total_properties', '1247'),     -- Total properties in DB
('sync_errors', '0');             -- Error count
```

**Helper Functions:**
```php
function state_get(PDO $pdo, string $key, $default = null) {
    $stmt = $pdo->prepare('SELECT v FROM app_state WHERE k = :k');
    $stmt->execute([':k' => $key]);
    $value = $stmt->fetchColumn();
    return ($value === false) ? $default : $value;
}

function state_set(PDO $pdo, string $key, string $value): void {
    $stmt = $pdo->prepare('
        INSERT INTO app_state (k, v) VALUES (:k, :v)
        ON DUPLICATE KEY UPDATE v = VALUES(v)
    ');
    $stmt->execute([':k' => $key, ':v' => $value]);
}
```

---

### 4. `search_log` (Analytics - Optional)

Tracks user search queries for analytics and optimization.

```sql
CREATE TABLE search_log (
  id BIGINT AUTO_INCREMENT PRIMARY KEY,
  session_id VARCHAR(64) DEFAULT NULL COMMENT 'User session ID',
  search_query TEXT DEFAULT NULL COMMENT 'Full search query',
  filters JSON DEFAULT NULL COMMENT 'Applied filters',
  result_count INT DEFAULT 0 COMMENT 'Number of results',
  response_time_ms INT DEFAULT NULL COMMENT 'Query execution time',
  timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  user_ip VARCHAR(45) DEFAULT NULL COMMENT 'User IP address',
  
  INDEX idx_timestamp (timestamp),
  INDEX idx_session (session_id)
  
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
COMMENT='Search analytics and query logging';
```

---

## 🔍 Query Examples

### Common Search Queries

**1. Search by City with Price Range:**
```sql
SELECT 
  L_ListingID,
  L_Address,
  L_City,
  L_SystemPrice,
  L_Keyword2 AS bedrooms,
  LM_Int2_3 AS bathrooms,
  LM_Dec_3 AS sqft
FROM 
  rets_property
WHERE 
  L_City = 'Los Angeles'
  AND L_SystemPrice BETWEEN 500000 AND 1000000
  AND L_Status = 'Active'
ORDER BY 
  L_UpdateDate DESC
LIMIT 20;
```

**2. Multi-Filter Search:**
```sql
SELECT 
  L_ListingID,
  L_Address,
  L_City,
  L_Zip,
  L_SystemPrice,
  L_Keyword2 AS bedrooms,
  LM_Int2_3 AS bathrooms,
  LM_Dec_3 AS sqft,
  L_Photos,
  L_UpdateDate
FROM 
  rets_property
WHERE 
  L_City LIKE '%San Diego%'
  AND L_SystemPrice <= 800000
  AND CAST(L_Keyword2 AS UNSIGNED) >= 3
  AND LM_Int2_3 >= 2
  AND LM_Dec_3 >= 1500
  AND L_Status = 'Active'
ORDER BY 
  L_SystemPrice ASC
LIMIT 50;
```

**3. Get Property Statistics:**
```sql
SELECT 
  COUNT(*) AS total_listings,
  AVG(L_SystemPrice) AS avg_price,
  MIN(L_SystemPrice) AS min_price,
  MAX(L_SystemPrice) AS max_price,
  AVG(LM_Dec_3) AS avg_sqft
FROM 
  rets_property
WHERE 
  L_Status = 'Active'
  AND L_City = 'San Diego';
```

**4. Find Recent Updates:**
```sql
SELECT 
  L_ListingID,
  L_Address,
  L_City,
  L_SystemPrice,
  L_UpdateDate
FROM 
  rets_property
WHERE 
  L_UpdateDate >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
  AND L_Status = 'Active'
ORDER BY 
  L_UpdateDate DESC;
```

---

## ⚡ Performance Optimization

### 1. Indexing Strategy

**Current Indexes:**
- Primary key on `L_ListingID` (unique constraint)
- Single-column indexes on frequently filtered fields
- Composite index on `(L_City, L_SystemPrice)` for common queries

**Query Analysis:**
```sql
-- Check index usage
EXPLAIN SELECT * FROM rets_property 
WHERE L_City = 'Los Angeles' AND L_SystemPrice < 1000000;

-- Show index statistics
SHOW INDEX FROM rets_property;
```

### 2. Query Optimization Tips

✅ **DO:**
- Use prepared statements (prevents SQL injection + caching)
- Limit result sets with `LIMIT`
- Use `EXPLAIN` to analyze query performance
- Index frequently filtered columns
- Use `BETWEEN` instead of multiple `>=` and `<=`

❌ **AVOID:**
- `SELECT *` (specify needed columns)
- Functions on indexed columns in WHERE clause
- `OR` conditions (use `UNION` for better performance)
- Large `OFFSET` values (use cursor-based pagination)

### 3. Caching Strategy

```php
// Example: Cache frequently accessed data
$cache_key = "stats_" . md5($city . $priceRange);
$stats = apcu_fetch($cache_key);

if ($stats === false) {
    $stats = $pdo->query("SELECT COUNT(*), AVG(price) FROM ...")->fetch();
    apcu_store($cache_key, $stats, 3600); // Cache for 1 hour
}
```

---

## 🔄 Data Synchronization

### Sync Process Flow

```
1. generate_token.php (Cron: */55 * * * *)
   └─> Check token expiration
   └─> Request new OAuth token if needed
   └─> Store in `token` table

2. fetch_property.php (Cron: 0 * * * *)
   └─> Read access token from `token` table
   └─> Fetch listings from Trestle API (OData)
   └─> Upsert into `rets_property` (100 records/batch)
   └─> Update `app_state` offset
   └─> Log sync status
```

### Upsert Logic

```sql
INSERT INTO rets_property (
  L_ListingID, L_Address, L_City, L_SystemPrice, ...
) VALUES (
  :id, :address, :city, :price, ...
)
ON DUPLICATE KEY UPDATE
  L_Address = VALUES(L_Address),
  L_SystemPrice = VALUES(L_SystemPrice),
  L_UpdateDate = VALUES(L_UpdateDate),
  updated_at = CURRENT_TIMESTAMP;
```

---

## 🛡️ Security Considerations

### 1. SQL Injection Prevention

✅ **Always use prepared statements:**
```php
// GOOD
$stmt = $pdo->prepare('SELECT * FROM rets_property WHERE L_City = :city');
$stmt->execute([':city' => $userInput]);

// BAD - NEVER DO THIS
$query = "SELECT * FROM rets_property WHERE L_City = '$userInput'";
```

### 2. Sensitive Data

🔒 **Never store in database:**
- API client secrets (use environment variables)
- User passwords in plaintext (use `password_hash()`)
- Credit card information

✅ **Store in database:**
- Access tokens (encrypted if possible)
- Hashed passwords
- Session IDs

### 3. Access Control

```sql
-- Create read-only user for search.php
CREATE USER 'app_reader'@'localhost' IDENTIFIED BY 'strong_password';
GRANT SELECT ON boxgra6_cali.rets_property TO 'app_reader'@'localhost';

-- Create write user for sync scripts
CREATE USER 'app_writer'@'localhost' IDENTIFIED BY 'strong_password';
GRANT SELECT, INSERT, UPDATE ON boxgra6_cali.* TO 'app_writer'@'localhost';
```

---

## 📦 Backup & Maintenance

### Backup Strategy

```bash
# Daily full backup
mysqldump -u root -p boxgra6_cali > backup_$(date +%Y%m%d).sql

# Compress
gzip backup_$(date +%Y%m%d).sql

# Automated backup script (add to cron: 0 2 * * *)
#!/bin/bash
BACKUP_DIR="/home/backups/mysql"
DATE=$(date +%Y%m%d_%H%M%S)
mysqldump -u backup_user -p'password' boxgra6_cali | gzip > "$BACKUP_DIR/cali_$DATE.sql.gz"
find $BACKUP_DIR -name "*.sql.gz" -mtime +30 -delete  # Keep 30 days
```

### Restore

```bash
# Decompress
gunzip backup_20250118.sql.gz

# Restore
mysql -u root -p boxgra6_cali < backup_20250118.sql
```

### Maintenance Tasks

```sql
-- Optimize tables monthly
OPTIMIZE TABLE rets_property;

-- Analyze tables for query optimization
ANALYZE TABLE rets_property;

-- Check table integrity
CHECK TABLE rets_property;

-- Clean up old search logs (keep 90 days)
DELETE FROM search_log WHERE timestamp < DATE_SUB(NOW(), INTERVAL 90 DAY);
```

---

## 📊 Monitoring Queries

```sql
-- Check database size
SELECT 
  table_name,
  ROUND(((data_length + index_length) / 1024 / 1024), 2) AS size_mb
FROM information_schema.tables
WHERE table_schema = 'boxgra6_cali'
ORDER BY size_mb DESC;

-- Row counts
SELECT COUNT(*) FROM rets_property WHERE L_Status = 'Active';

-- Recent sync status
SELECT * FROM app_state WHERE k LIKE 'sync%';

-- Token expiration check
SELECT 
  token_type,
  expires_at,
  TIMESTAMPDIFF(MINUTE, NOW(), expires_at) AS minutes_until_expiry
FROM token;
```

---

## 🔧 Troubleshooting

### Common Issues

**1. Token Expired:**
```sql
-- Manually trigger token refresh
DELETE FROM token WHERE token_type = 'trestle';
-- Then run: php api/generate_token.php
```

**2. Duplicate Entries:**
```sql
-- Find duplicates
SELECT L_ListingID, COUNT(*) 
FROM rets_property 
GROUP BY L_ListingID 
HAVING COUNT(*) > 1;
```

**3. Slow Queries:**
```sql
-- Enable slow query log
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;  -- Log queries > 2 seconds
```

---

**Last Updated:** January 18, 2025  
**Maintained by:** SD6 Team @ IDXExchange
