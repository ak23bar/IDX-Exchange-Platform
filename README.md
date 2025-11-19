# 🏠 Full-Stack MLS Property Platform

> **Pro-bono Real Estate Search Platform** | Built with SD6 Team @ IDXExchange  
> *Fall 2025 Cohort - Software Development Internship*

[![PHP](https://img.shields.io/badge/PHP-7.2+-777BB4?style=flat&logo=php&logoColor=white)](https://www.php.net/)
[![MySQL](https://img.shields.io/badge/MySQL-8.0+-4479A1?style=flat&logo=mysql&logoColor=white)](https://www.mysql.com/)
[![Python](https://img.shields.io/badge/Python-3.x-3776AB?style=flat&logo=python&logoColor=white)](https://www.python.org/)
[![Linux](https://img.shields.io/badge/Linux-Nginx-FCC624?style=flat&logo=linux&logoColor=black)](https://www.linux.org/)

## 📋 Overview

A sophisticated full-stack real estate platform engineered to integrate live MLS (Multiple Listing Service) data from the **CRMLS (California Regional MLS)** via the **Trestle API**. The platform provides advanced property search, filtering, and visualization capabilities for California real estate listings.

**Team Lead:** Akbar Aman  
**Team:** SD6 (6 members)  
**Organization:** IDXExchange  
**Project Status:** Active Development  
**Live Demo:** [https://akbar.califorsale.org/search.php](https://akbar.califorsale.org/search.php)

---

## ✨ Key Features

### 🔍 Advanced Search & Filtering
- **Multi-criteria filtering**: City, ZIP code, price range, bedrooms, bathrooms, square footage
- **Real-time search** with dynamic query building
- **Sort capabilities**: Price, size, date updated (ascending/descending)
- **Smart pagination** for large result sets

### 🎨 User Experience
- **Dual view modes**: Grid and List layouts
- **Interactive property modals** with image galleries
- **Session-based favorites** system
- **Recently viewed properties** tracking
- **Keyboard shortcuts** for power users
- **Responsive design** optimized for all devices

### 📊 Data Management
- **CSV export** functionality for data analysis
- **Real-time statistics dashboard**
- **Live data integration** from CRMLS via Trestle API
- **1,000+ active listings** in database

### 🔐 Backend Infrastructure
- **OAuth 2.0 authentication** with Trestle API
- **Automated token refresh** system
- **Cron job scheduling** for hourly data synchronization
- **Custom MySQL schema** optimized for property data
- **PDO-based secure database operations**

---

## 🛠️ Technology Stack

### Frontend
- **HTML5** / **CSS3** (Modern gradient UI with flexbox/grid)
- **Vanilla JavaScript** (ES6+)
- **Responsive Design** (Mobile-first approach)

### Backend
- **PHP 7.2+** (Server-side logic, API integration)
- **MySQL 8.0+** (Relational database for property listings)
- **Python 3.x** (Data processing scripts)

### DevOps & Infrastructure
- **Linux VPS** (Production environment)
- **Nginx** (Web server)
- **cPanel** (Hosting management)
- **Cron** (Scheduled task automation)

### APIs & Integration
- **Trestle API** (CoreLogic CRMLS data provider)
- **OAuth 2.0** (Secure API authentication)
- **OData Protocol** (Property data retrieval)

---

## 🏗️ Architecture

```
┌─────────────────────────────────────────────────────────────┐
│                        User Interface                        │
│                   (index.html, search.php)                   │
└───────────────────────────┬─────────────────────────────────┘
                            │
                            ▼
┌─────────────────────────────────────────────────────────────┐
│                     Application Layer                        │
│              (PHP Business Logic + Session Mgmt)             │
└───────────────────────────┬─────────────────────────────────┘
                            │
        ┌───────────────────┼───────────────────┐
        ▼                   ▼                   ▼
┌──────────────┐    ┌──────────────┐    ┌──────────────┐
│  MySQL DB    │    │  Trestle API │    │  Cron Jobs   │
│ (1000+ rows) │    │  Integration │    │  (Hourly)    │
└──────────────┘    └──────────────┘    └──────────────┘
        │                   │                   │
        └───────────────────┴───────────────────┘
                            │
                            ▼
                ┌──────────────────────┐
                │  Property Data Sync  │
                │  (generate_token.php │
                │  fetch_property.php) │
                └──────────────────────┘
```

---

## 📁 Project Structure

```
IDXExchange/
├── index.html              # Landing page with hero section
├── search.php              # Main property search interface (1110 lines)
├── style.css               # Global styles and responsive design
├── htaccess                # Apache configuration for PHP handler
├── api/
│   ├── generate_token.php  # OAuth token generation & refresh
│   ├── fetch_property.php  # Property data fetcher from Trestle API
│   └── README.txt          # API documentation
├── docs/
│   ├── DATABASE.md         # Database schema documentation
│   ├── API.md              # API integration guide
│   └── DEPLOYMENT.md       # Deployment instructions
├── .env.example            # Environment variables template
├── .gitignore              # Git ignore patterns
├── LICENSE                 # MIT License
├── CONTRIBUTING.md         # Contribution guidelines
└── README.md               # This file
```

---

## 🗄️ Database Schema

**Database:** `boxgra6_cali`  
**Primary Table:** `rets_property`

### Core Fields

| Field | Type | Description |
|-------|------|-------------|
| `L_ListingID` | VARCHAR(50) PK | Unique listing identifier |
| `L_Address` | VARCHAR(255) | Property street address |
| `L_City` | VARCHAR(100) | City name |
| `L_Zip` | VARCHAR(10) | ZIP code |
| `L_SystemPrice` | DECIMAL(12,2) | Listing price (USD) |
| `L_Keyword2` | VARCHAR(10) | Number of bedrooms |
| `LM_Int2_3` | INT | Number of bathrooms |
| `LM_Dec_3` | DECIMAL(10,2) | Square footage |
| `L_Photos` | JSON | Array of image URLs |
| `L_UpdateDate` | TIMESTAMP | Last update timestamp |

### Supporting Tables

- **`token`**: Stores OAuth access tokens for API authentication
- **`app_state`**: Maintains application state (API offset, sync status)

---

## 🚀 Installation & Setup

### Prerequisites

- **PHP 7.2+** with extensions: `pdo_mysql`, `curl`, `json`
- **MySQL 8.0+**
- **Web server** (Apache/Nginx)
- **Composer** (for dependency management)
- **Trestle API credentials** (Client ID & Secret)

### Step 1: Clone Repository

```bash
git clone https://github.com/yourusername/idx-exchange-platform.git
cd idx-exchange-platform
```

### Step 2: Configure Environment

```bash
cp .env.example .env
nano .env  # Edit with your credentials
```

**Required environment variables:**
```ini
# Database Configuration
DB_HOST=localhost
DB_NAME=your_database_name
DB_USER=your_database_user
DB_PASS=your_database_password

# Trestle API Credentials
TRESTLE_CLIENT_ID=your_client_id
TRESTLE_CLIENT_SECRET=your_client_secret
TRESTLE_TOKEN_URL=https://api-trestle.corelogic.com/trestle/oidc/token
```

### Step 3: Database Setup

```bash
mysql -u root -p < docs/schema.sql
```

Or import via phpMyAdmin/cPanel.

### Step 4: Set Up Cron Jobs

Add to crontab (`crontab -e`):

```cron
# Refresh OAuth token every 55 minutes
*/55 * * * * /usr/bin/php /path/to/api/generate_token.php >> /var/log/token_refresh.log 2>&1

# Fetch property data every hour
0 * * * * /usr/bin/php /path/to/api/fetch_property.php >> /var/log/property_sync.log 2>&1
```

### Step 5: Configure Web Server

**Apache (.htaccess):**
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteBase /
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteRule ^(.*)$ search.php [L,QSA]
</IfModule>
```

**Nginx:**
```nginx
location / {
    try_files $uri $uri/ /search.php?$query_string;
}

location ~ \.php$ {
    include fastcgi_params;
    fastcgi_pass unix:/var/run/php/php7.2-fpm.sock;
    fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
}
```

### Step 6: Launch

```bash
# Start web server
sudo systemctl start nginx  # or apache2

# Verify installation
curl http://localhost/
```

---

## 🔧 Configuration

### Security Best Practices

1. **Never commit credentials** to version control
2. Store sensitive data in `.env` or outside web root (`/home/user/.idx_secrets.php`)
3. Use **prepared statements** for all database queries (PDO)
4. Implement **rate limiting** for API calls
5. Enable **HTTPS** in production

### Performance Optimization

- **Enable MySQL query caching**
- Use **CDN** for static assets
- Implement **Redis/Memcached** for session storage
- **Optimize images** (compress, lazy-load)
- Enable **gzip compression**

---

## 📊 API Integration

### Trestle API Workflow

1. **Authentication**: `generate_token.php` requests OAuth token
2. **Token Storage**: Cached in MySQL `token` table with expiration
3. **Data Retrieval**: `fetch_property.php` fetches listings using OData queries
4. **Database Sync**: Upserts property data into `rets_property` table
5. **State Management**: Maintains offset in `app_state` for pagination

### Sample API Request

```php
GET https://api-trestle.corelogic.com/trestle/odata/Property
    ?$filter=PropertyType eq 'Residential'
    &$skip=0
    &$top=100
    &$orderby=ModificationTimestamp desc

Headers:
  Authorization: Bearer {access_token}
  Accept: application/json
```

---

## 👥 Team & Contributors

### SD6 Team (Fall 2025)
- **Akbar Aman** - Team Lead, Backend Engineer
- [Team Member 2] - Frontend Developer
- [Team Member 3] - Database Administrator
- [Team Member 4] - API Integration Specialist
- [Team Member 5] - QA Engineer
- [Team Member 6] - DevOps Engineer

### How to Contribute

See [CONTRIBUTING.md](CONTRIBUTING.md) for guidelines on:
- Code style standards
- Branch naming conventions
- Pull request process
- Issue reporting

---

## 📈 Roadmap

### Phase 1: Core Functionality ✅
- [x] Basic property search interface
- [x] Database integration
- [x] API authentication system
- [x] Cron job automation

### Phase 2: Enhanced Features (In Progress)
- [ ] User authentication & profiles
- [ ] Saved searches & alerts
- [ ] Map-based property visualization
- [ ] Advanced analytics dashboard

### Phase 3: Scalability
- [ ] Redis caching layer
- [ ] Elasticsearch integration
- [ ] Microservices architecture
- [ ] Docker containerization

### Phase 4: Mobile
- [ ] Progressive Web App (PWA)
- [ ] React Native mobile app
- [ ] Push notifications

---

## 🐛 Known Issues

- Token refresh may fail if Trestle API is down → Implement retry logic
- Large photo arrays can slow page load → Add lazy loading
- Session favorites are not persistent → Migrate to database storage

See [Issues](https://github.com/yourusername/idx-exchange-platform/issues) for tracking.

---

## 📄 License

This project is licensed under the **MIT License** - see [LICENSE](LICENSE) file for details.

---

## 🙏 Acknowledgments

- **IDXExchange** for project sponsorship and mentorship
- **CoreLogic/CRMLS** for Trestle API access
- **SD6 Team** for collaboration and dedication
- Open-source community for inspiration

---

## 📞 Contact

**Akbar Aman**  
Team Lead, SD6 @ IDXExchange  
📧 Email: [your.email@example.com]  
🔗 LinkedIn: [linkedin.com/in/yourprofile]  
🌐 Live Demo: [https://akbar.califorsale.org/search.php](https://akbar.califorsale.org/search.php)

---

## 🌟 Star this repo if you find it useful!

**Built with ❤️ by SD6 Team @ IDXExchange**
