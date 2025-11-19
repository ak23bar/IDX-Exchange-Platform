# 🔒 Credential Sanitization Guide

## ⚠️ IMPORTANT: Before Pushing to GitHub

The following files contain **hardcoded database credentials** that must be removed before committing to version control:

### Files to Update:

1. **search.php** (lines ~38-41)
2. **api/generate_token.php** (lines ~10-13)
3. **api/fetch_property.php** (lines ~14-17)

---

## 📝 Step-by-Step Instructions

### Option 1: Use the Config File (Recommended)

**1. Create your secrets file outside web root:**

```bash
# Copy template
cp .idx_secrets.php.example /home/yourusername/.idx_secrets.php

# Edit with your credentials
nano /home/yourusername/.idx_secrets.php

# Set restrictive permissions
chmod 600 /home/yourusername/.idx_secrets.php
```

**2. Update `search.php`:**

Replace lines 38-41:
```php
// OLD (REMOVE THIS):
$DB_HOST = 'localhost';
$DB_NAME = 'boxgra6_cali';
$DB_USER = 'boxgra6_sd';
$DB_PASS = 'Real_estate650$';
```

With:
```php
// NEW (USE THIS):
require_once __DIR__ . '/config.php';
$pdo = get_database_connection();
// Remove the manual PDO connection code below (lines 48-56)
```

**3. Update `api/generate_token.php`:**

Replace lines 10-13:
```php
// OLD (REMOVE THIS):
$db_host = 'localhost';
$db_name = '';
$db_user = '';
$db_pass = '';
```

With:
```php
// NEW (USE THIS):
require_once __DIR__ . '/../config.php';
$pdo = get_database_connection();
$cfg = get_api_config();
// Remove the manual PDO connection code (lines 26-35)
```

**4. Update `api/fetch_property.php`:**

Replace lines 14-17:
```php
// OLD (REMOVE THIS):
$dbHost = 'localhost';
$dbName = '';
$dbUser = '';
$dbPass = '';
```

With:
```php
// NEW (USE THIS):
require_once __DIR__ . '/../config.php';
$pdo = get_database_connection();
// Remove the manual PDO connection code (lines 23-30)
```

---

### Option 2: Use Environment Variables

**1. Set environment variables in your shell:**

```bash
export DB_HOST=localhost
export DB_NAME=your_database
export DB_USER=your_user
export DB_PASS=your_password
export TRESTLE_CLIENT_ID=your_client_id
export TRESTLE_CLIENT_SECRET=your_client_secret
```

**2. Update PHP files to use `getenv()`:**

```php
$DB_HOST = getenv('DB_HOST') ?: 'localhost';
$DB_NAME = getenv('DB_NAME');
$DB_USER = getenv('DB_USER');
$DB_PASS = getenv('DB_PASS');
```

---

## ✅ Verification Checklist

Before committing to Git:

- [ ] All hardcoded passwords removed from PHP files
- [ ] `.idx_secrets.php` added to `.gitignore`
- [ ] `.env` added to `.gitignore` (if using)
- [ ] Config file tested locally
- [ ] No credentials in `git status` output
- [ ] Run: `git grep -i "password\|secret" *.php` (should return nothing sensitive)

---

## 🔍 Quick Check Command

Run this to find potential hardcoded credentials:

```bash
grep -rn "DB_PASS\|client_secret\|password" --include="*.php" . | grep -v "getenv\|config.php\|example"
```

If this returns results, **DO NOT COMMIT** until sanitized.

---

## 🚨 If Credentials Were Already Committed

If you accidentally committed credentials:

```bash
# 1. Remove from history (BE CAREFUL!)
git filter-branch --force --index-filter \
  "git rm --cached --ignore-unmatch api/generate_token.php" \
  --prune-empty --tag-name-filter cat -- --all

# 2. Force push (if already pushed to remote)
git push origin --force --all

# 3. Immediately change all compromised credentials!
```

**Better approach: Use BFG Repo-Cleaner:**
```bash
bfg --replace-text passwords.txt
git reflog expire --expire=now --all && git gc --prune=now --aggressive
git push --force
```

---

## 📚 Additional Security Resources

- [OWASP: Password Storage Cheat Sheet](https://cheatsheetseries.owasp.org/cheatsheets/Password_Storage_Cheat_Sheet.html)
- [GitHub: Removing Sensitive Data](https://docs.github.com/en/authentication/keeping-your-account-and-data-secure/removing-sensitive-data-from-a-repository)
- [Git-Secret Tool](https://git-secret.io/)

---

**Last Updated:** January 18, 2025  
**Maintained by:** SD6 Team @ IDXExchange
