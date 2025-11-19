# 🚀 Deployment Guide

## Overview

This guide covers deploying the Full-Stack MLS Property Platform to production environments including cPanel hosting, Linux VPS, and cloud platforms.

**Target Environments:**
- cPanel/Shared Hosting (Current Production)
- Linux VPS (Nginx/Apache)
- Cloud Platforms (AWS, Azure, GCP)

---

## 📋 Prerequisites

### Required Software

- **PHP 7.2+** with extensions:
  - `pdo_mysql`
  - `curl`
  - `json`
  - `mbstring`
  - `openssl`
- **MySQL 8.0+** or MariaDB 10.3+
- **Web Server**: Apache 2.4+ or Nginx 1.18+
- **Cron** job scheduler
- **Git** (for version control)
- **Composer** (optional, for dependencies)

### Required Credentials

- Database credentials (host, name, user, password)
- Trestle API credentials (client ID, client secret)
- SSH access to server
- Domain/subdomain with SSL certificate

---

## 🏢 cPanel Deployment (Current Production)

### Step 1: Prepare Files

```bash
# On local machine
git clone https://github.com/yourusername/idx-exchange-platform.git
cd idx-exchange-platform

# Remove .git directory (if uploading via FTP)
rm -rf .git

# Compress for upload
tar -czf idx-platform.tar.gz *
```

### Step 2: Upload via cPanel File Manager

1. Login to cPanel
2. Navigate to **File Manager**
3. Go to `public_html/` (or subdomain folder)
4. Click **Upload**
5. Upload `idx-platform.tar.gz`
6. Right-click → **Extract**
7. Delete tar.gz file after extraction

**Alternative: FTP Upload**
```bash
# Using lftp
lftp -u username,password ftp.yourdomain.com
cd public_html
mirror -R /local/path/idx-exchange-platform .
exit
```

### Step 3: Configure Database

**Via cPanel → MySQL Databases:**

1. **Create Database**
   - Database name: `username_cali`
   - Click "Create Database"

2. **Create User**
   - Username: `username_sd`
   - Password: (generate strong password)
   - Click "Create User"

3. **Assign User to Database**
   - Select database and user
   - Grant **ALL PRIVILEGES**
   - Click "Add"

4. **Import Schema**
   - Go to **phpMyAdmin**
   - Select `username_cali` database
   - Click **Import** tab
   - Upload `docs/schema.sql`
   - Click **Go**

### Step 4: Configure Environment

**Create config file outside web root:**

```bash
# SSH into server
ssh user@yourdomain.com

# Create secrets file
nano /home/username/.idx_secrets.php
```

**Content:**
```php
<?php
return [
    'trestle_client_id'     => 'your_client_id',
    'trestle_client_secret' => 'your_client_secret',
    'token_url'             => 'https://api-trestle.corelogic.com/trestle/oidc/token',
    'token_type'            => 'trestle'
];
```

**Set permissions:**
```bash
chmod 600 /home/username/.idx_secrets.php
```

### Step 5: Update Database Credentials

**Edit `search.php`:**
```php
$DB_HOST = 'localhost';
$DB_NAME = 'username_cali';
$DB_USER = 'username_sd';
$DB_PASS = 'your_database_password';
```

**Edit `api/generate_token.php`:**
```php
$db_host = 'localhost';
$db_name = 'username_cali';
$db_user = 'username_sd';
$db_pass = 'your_database_password';
```

**Edit `api/fetch_property.php`:**
```php
$dbHost = 'localhost';
$dbName = 'username_cali';
$dbUser = 'username_sd';
$dbPass = 'your_database_password';
```

### Step 6: Set Up Cron Jobs

**Via cPanel → Cron Jobs:**

1. **Token Refresh** (every 55 minutes)
   ```
   */55 * * * * /usr/bin/php /home/username/public_html/api/generate_token.php >> /home/username/logs/token.log 2>&1
   ```

2. **Property Sync** (hourly)
   ```
   0 * * * * /usr/bin/php /home/username/public_html/api/fetch_property.php >> /home/username/logs/sync.log 2>&1
   ```

3. **Database Cleanup** (daily at 2 AM)
   ```
   0 2 * * * /usr/bin/php /home/username/public_html/scripts/cleanup.php >> /home/username/logs/cleanup.log 2>&1
   ```

**Create log directory:**
```bash
mkdir -p /home/username/logs
chmod 755 /home/username/logs
```

### Step 7: Configure .htaccess

**Verify `.htaccess` file:**
```apache
# PHP Version (if needed)
<IfModule mime_module>
  AddHandler application/x-httpd-ea-php72 .php .php7 .phtml
</IfModule>

# Security Headers
<IfModule mod_headers.c>
  Header set X-Content-Type-Options "nosniff"
  Header set X-Frame-Options "SAMEORIGIN"
  Header set X-XSS-Protection "1; mode=block"
</IfModule>

# Deny access to sensitive files
<FilesMatch "^\.">
  Order allow,deny
  Deny from all
</FilesMatch>

# Protect config files
<Files "*.php">
  <IfModule mod_authz_core.c>
    Require all denied
  </IfModule>
</Files>

# Allow only index.html and search.php
<FilesMatch "^(index\.html|search\.php)$">
  <IfModule mod_authz_core.c>
    Require all granted
  </IfModule>
</FilesMatch>

# Enable compression
<IfModule mod_deflate.c>
  AddOutputFilterByType DEFLATE text/html text/plain text/xml text/css text/javascript application/javascript
</IfModule>
```

### Step 8: Install SSL Certificate

**Via cPanel → SSL/TLS:**

1. Navigate to **SSL/TLS Status**
2. Find your domain
3. Click **Run AutoSSL** (for free Let's Encrypt)
4. Wait for installation to complete

**Or manually upload certificate:**
1. Go to **SSL/TLS → Manage SSL Sites**
2. Upload certificate files
3. Click **Install Certificate**

### Step 9: Test Deployment

```bash
# Test token generation
php /home/username/public_html/api/generate_token.php

# Test property fetch
php /home/username/public_html/api/fetch_property.php

# Check database
mysql -u username_sd -p username_cali -e "SELECT COUNT(*) FROM rets_property;"
```

**Browser tests:**
- Visit `https://yourdomain.com` (landing page)
- Visit `https://yourdomain.com/search.php` (property search)
- Test filters and search functionality
- Check browser console for errors

---

## 🐧 Linux VPS Deployment (Nginx)

### Step 1: System Setup

```bash
# Update system
sudo apt update && sudo apt upgrade -y

# Install required packages
sudo apt install -y nginx mysql-server php7.4-fpm php7.4-mysql php7.4-curl php7.4-json php7.4-mbstring git

# Start services
sudo systemctl start nginx
sudo systemctl start mysql
sudo systemctl start php7.4-fpm

# Enable auto-start
sudo systemctl enable nginx
sudo systemctl enable mysql
sudo systemctl enable php7.4-fpm
```

### Step 2: Configure MySQL

```bash
# Secure MySQL installation
sudo mysql_secure_installation

# Create database and user
sudo mysql -u root -p
```

```sql
CREATE DATABASE idx_california CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'idx_user'@'localhost' IDENTIFIED BY 'strong_password_here';
GRANT ALL PRIVILEGES ON idx_california.* TO 'idx_user'@'localhost';
FLUSH PRIVILEGES;
EXIT;
```

### Step 3: Deploy Application

```bash
# Create application directory
sudo mkdir -p /var/www/idx-platform
cd /var/www/idx-platform

# Clone repository
sudo git clone https://github.com/yourusername/idx-exchange-platform.git .

# Set ownership
sudo chown -R www-data:www-data /var/www/idx-platform

# Set permissions
sudo find /var/www/idx-platform -type d -exec chmod 755 {} \;
sudo find /var/www/idx-platform -type f -exec chmod 644 {} \;

# Create secrets file
sudo nano /etc/idx-secrets.php
# (Add credentials as shown in cPanel section)
sudo chmod 600 /etc/idx-secrets.php
sudo chown www-data:www-data /etc/idx-secrets.php
```

### Step 4: Configure Nginx

```bash
sudo nano /etc/nginx/sites-available/idx-platform
```

**Configuration:**
```nginx
server {
    listen 80;
    listen [::]:80;
    server_name yourdomain.com www.yourdomain.com;
    
    # Redirect to HTTPS
    return 301 https://$server_name$request_uri;
}

server {
    listen 443 ssl http2;
    listen [::]:443 ssl http2;
    server_name yourdomain.com www.yourdomain.com;
    
    root /var/www/idx-platform;
    index index.html search.php;
    
    # SSL Configuration
    ssl_certificate /etc/letsencrypt/live/yourdomain.com/fullchain.pem;
    ssl_certificate_key /etc/letsencrypt/live/yourdomain.com/privkey.pem;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers HIGH:!aNULL:!MD5;
    ssl_prefer_server_ciphers on;
    
    # Security Headers
    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;
    add_header X-XSS-Protection "1; mode=block" always;
    add_header Strict-Transport-Security "max-age=31536000; includeSubDomains" always;
    
    # Logging
    access_log /var/log/nginx/idx-access.log;
    error_log /var/log/nginx/idx-error.log;
    
    # Deny access to hidden files
    location ~ /\. {
        deny all;
    }
    
    # Deny access to API scripts via browser
    location ~ ^/api/.*\.php$ {
        deny all;
    }
    
    # PHP handling
    location ~ \.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
    
    # Static files caching
    location ~* \.(jpg|jpeg|png|gif|ico|css|js)$ {
        expires 30d;
        add_header Cache-Control "public, immutable";
    }
    
    # Gzip compression
    gzip on;
    gzip_vary on;
    gzip_types text/plain text/css text/xml text/javascript application/json application/javascript;
}
```

**Enable site:**
```bash
sudo ln -s /etc/nginx/sites-available/idx-platform /etc/nginx/sites-enabled/
sudo nginx -t  # Test configuration
sudo systemctl reload nginx
```

### Step 5: Install SSL with Let's Encrypt

```bash
# Install certbot
sudo apt install -y certbot python3-certbot-nginx

# Obtain certificate
sudo certbot --nginx -d yourdomain.com -d www.yourdomain.com

# Auto-renewal (already configured by certbot)
sudo certbot renew --dry-run
```

### Step 6: Set Up Cron Jobs

```bash
sudo crontab -e
```

**Add:**
```cron
# Token refresh every 55 minutes
*/55 * * * * /usr/bin/php /var/www/idx-platform/api/generate_token.php >> /var/log/idx/token.log 2>&1

# Property sync hourly
0 * * * * /usr/bin/php /var/www/idx-platform/api/fetch_property.php >> /var/log/idx/sync.log 2>&1

# Database backup daily at 2 AM
0 2 * * * /usr/bin/mysqldump -u idx_user -p'password' idx_california | gzip > /backups/idx_$(date +\%Y\%m\%d).sql.gz
```

**Create log directory:**
```bash
sudo mkdir -p /var/log/idx
sudo chown www-data:www-data /var/log/idx
```

---

## ☁️ Cloud Platform Deployment

### AWS EC2 (Amazon Linux 2)

**Launch instance:**
```bash
# Install LAMP stack
sudo yum update -y
sudo amazon-linux-extras install -y lamp-mariadb10.2-php7.2 php7.2
sudo yum install -y httpd mariadb-server

# Start services
sudo systemctl start httpd
sudo systemctl start mariadb
sudo systemctl enable httpd
sudo systemctl enable mariadb

# Deploy application (follow Linux VPS steps)
```

**RDS Database (optional):**
```bash
# Update connection in PHP files
$DB_HOST = 'your-rds-endpoint.region.rds.amazonaws.com';
$DB_NAME = 'idx_california';
$DB_USER = 'admin';
$DB_PASS = 'your_password';
```

### Google Cloud Platform (Compute Engine)

```bash
# Create instance
gcloud compute instances create idx-platform \
    --machine-type=e2-medium \
    --image-family=debian-11 \
    --image-project=debian-cloud \
    --boot-disk-size=20GB

# SSH into instance
gcloud compute ssh idx-platform

# Follow Linux VPS deployment steps
```

### Microsoft Azure (App Service)

**Using Azure CLI:**
```bash
# Create resource group
az group create --name idx-platform-rg --location eastus

# Create App Service plan
az appservice plan create --name idx-plan --resource-group idx-platform-rg --sku B1 --is-linux

# Create web app
az webapp create --resource-group idx-platform-rg --plan idx-plan --name idx-platform --runtime "PHP|7.4"

# Deploy code
az webapp deployment source config --name idx-platform --resource-group idx-platform-rg --repo-url https://github.com/yourusername/idx-exchange-platform --branch main --manual-integration

# Configure environment variables
az webapp config appsettings set --resource-group idx-platform-rg --name idx-platform --settings \
  DB_HOST='your-mysql-host' \
  DB_NAME='idx_california' \
  DB_USER='idx_user' \
  DB_PASS='password'
```

---

## 🔒 Security Checklist

- [ ] SSL certificate installed and HTTPS enforced
- [ ] Database credentials stored outside web root
- [ ] `.env` or secrets file with restrictive permissions (600)
- [ ] All input validated and sanitized
- [ ] Prepared statements used for all SQL queries
- [ ] Security headers configured (CSP, X-Frame-Options, etc.)
- [ ] Directory listing disabled
- [ ] Error reporting disabled in production
- [ ] Regular backups automated
- [ ] Firewall configured (only ports 80, 443, 22 open)
- [ ] SSH key authentication enabled (password auth disabled)
- [ ] Fail2ban or similar intrusion prevention installed
- [ ] File upload restrictions in place
- [ ] API rate limiting implemented

---

## 📊 Monitoring & Maintenance

### Log Monitoring

```bash
# Check error logs
tail -f /var/log/nginx/idx-error.log
tail -f /var/log/idx/sync.log

# Check cron job status
grep CRON /var/log/syslog

# Monitor database size
mysql -u root -p -e "SELECT table_name, ROUND(((data_length + index_length) / 1024 / 1024), 2) AS 'Size (MB)' FROM information_schema.tables WHERE table_schema = 'idx_california';"
```

### Performance Monitoring

```bash
# Check server resources
htop

# Monitor MySQL
mysqladmin -u root -p processlist
mysqladmin -u root -p status

# Nginx status
curl http://localhost/nginx_status
```

### Automated Backups

**Backup script:**
```bash
#!/bin/bash
# /usr/local/bin/idx-backup.sh

BACKUP_DIR="/backups/idx"
DATE=$(date +%Y%m%d_%H%M%S)

# Database backup
mysqldump -u idx_user -p'password' idx_california | gzip > "$BACKUP_DIR/db_$DATE.sql.gz"

# Files backup
tar -czf "$BACKUP_DIR/files_$DATE.tar.gz" /var/www/idx-platform

# Keep only last 30 days
find $BACKUP_DIR -name "*.gz" -mtime +30 -delete

echo "Backup completed: $DATE"
```

**Schedule:**
```cron
0 3 * * * /usr/local/bin/idx-backup.sh >> /var/log/idx/backup.log 2>&1
```

---

## 🆘 Troubleshooting

### Common Issues

**1. PHP files downloading instead of executing:**
```bash
# Apache: Enable PHP module
sudo a2enmod php7.4
sudo systemctl restart apache2

# Nginx: Check PHP-FPM
sudo systemctl status php7.4-fpm
```

**2. Database connection errors:**
```bash
# Check MySQL is running
sudo systemctl status mysql

# Test connection
mysql -u idx_user -p -h localhost idx_california

# Check permissions
mysql -u root -p -e "SHOW GRANTS FOR 'idx_user'@'localhost';"
```

**3. Cron jobs not running:**
```bash
# Check cron service
sudo systemctl status cron

# Verify crontab
crontab -l

# Check logs
grep CRON /var/log/syslog
```

**4. 502 Bad Gateway (Nginx):**
```bash
# Check PHP-FPM socket
ls -l /var/run/php/php7.4-fpm.sock

# Check Nginx error log
tail -f /var/log/nginx/error.log
```

---

## 📞 Support

**Team Lead:** Akbar Aman  
**Email:** [your.email@example.com]  
**GitHub Issues:** [github.com/yourusername/idx-exchange-platform/issues](https://github.com/yourusername/idx-exchange-platform/issues)

---

**Last Updated:** January 18, 2025  
**Maintained by:** SD6 Team @ IDXExchange
