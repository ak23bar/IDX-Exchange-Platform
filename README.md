# California Property Finder - Complete Project Documentation

**Developed by:** Akbar Aman — SD6 Team Lead, IDX Exchange Pro-bono Initiative  
**Status:** Complete & Production Ready  
**Demo Date:** Final Presentation

---

## Table of Contents

1. [Project Overview](#project-overview)
2. [Features & Functionality](#features--functionality)
3. [Technology Stack](#technology-stack)
4. [Database Schema](#database-schema)
5. [Installation & Setup](#installation--setup)
6. [API Documentation](#api-documentation)
7. [Frontend Architecture](#frontend-architecture)
8. [Backend Architecture](#backend-architecture)
9. [User Experience Features](#user-experience-features)
10. [Demo Walkthrough](#demo-walkthrough)
11. [File Structure](#file-structure)
12. [Configuration Guide](#configuration-guide)
13. [Troubleshooting](#troubleshooting)

---

## Project Overview

The California Property Finder is a comprehensive, production-ready web application for searching and exploring real estate listings across California. Built as part of the IDX Exchange Pro-bono Initiative, this project demonstrates full-stack development capabilities with PHP, MySQL, JavaScript, and modern web APIs.

### Key Highlights

- **Complete Property Search System**: Advanced filtering, sorting, and search capabilities
- **Interactive Maps**: Google Maps integration with property visualization
- **AI Assistant**: ElevenLabs Conversational AI (Calibot) for intelligent property assistance
- **Favorites Management**: Complete bookmarking system with session persistence
- **Responsive Design**: Mobile-first approach with smooth animations
- **Real-time Suggestions**: Smart autocomplete for cities and property queries
- **Data Export**: CSV export functionality for analysis

---

## Features & Functionality

### 1. Advanced Search & Filtering

**Smart Search Bar**
- Real-time search suggestions for cities, beds, baths, and combinations
- Examples: "Los Angeles", "4 beds Los angeles", "Los Angeles 4 beds 8 baths"
- General search across all database fields (address, city, zip, price, beds, baths, sqft)
- All filters automatically cleared on new search

**Filter Options**
- **City**: Text input with autocomplete suggestions
- **ZIP Code**: Exact match filtering
- **Price Range**: Min/Max price filters
- **Bedrooms**: Minimum bedroom count
- **Bathrooms**: Minimum bathroom count
- **Square Footage**: Min/Max square footage range

**Search Behavior**
- Filters are cleared when performing a new general search
- City-only filtering via autocomplete suggestions
- General search searches across all fields simultaneously
- Smart number detection (prices, beds, baths, sqft)

### 2. Interactive Maps

**Google Maps Integration**
- Property location visualization with markers
- Multiple map types: Roadmap, Satellite, Hybrid
- Automatic geocoding for property addresses
- Interactive map controls and zoom
- Map view in property detail modals

**Map Features**
- Click property cards to view on map
- Modal maps show exact property location
- Responsive map sizing for all screen sizes

### 3. AI Assistant (Calibot)

**ElevenLabs Conversational AI**
- Embedded widget for property search assistance
- Configured with concise, data-driven responses
- Can query property database via API endpoints
- Positioned via ElevenLabs dashboard settings
- Natural language understanding for property queries

**Calibot Capabilities**
- Answer questions about property data
- Provide statistics and market insights
- Assist with search queries
- Database-driven responses

### 4. Favorites Management

**Complete Favorites System**
- Heart icon toggle on all property cards
- AJAX-based add/remove without page reload
- Session-based persistence
- "View Favorites" filter button
- "Clear All Favorites" functionality
- Real-time favorites count display
- Works in both grid and list views

**Favorites Features**
- Persistent across page navigation
- Instant UI updates
- Favorites-only view mode
- Bulk clear option

### 5. Property Display

**View Modes**
- **Grid View**: Card-based layout with images
- **List View**: Compact list with key details
- Seamless toggle between views
- View preference persists in session

**Property Cards**
- High-quality property images
- Key details: Price, Beds, Baths, SqFt, Address
- Heart icon for favorites
- Click to view full details
- Responsive card sizing

**Property Detail Modals**
- Full-screen image galleries
- Image navigation with arrows and dots
- Interactive Google Maps
- Complete property information
- Price per square foot calculation
- Close button and keyboard shortcuts (ESC)

### 6. Sorting & Pagination

**Sorting Options**
- Price (Ascending/Descending)
- Bedrooms (Ascending/Descending)
- Square Footage (Ascending/Descending)
- Sort preference persists

**Pagination**
- 12 properties per page
- Page navigation controls
- Current page indicator
- Total results count
- Efficient database queries with LIMIT/OFFSET

### 7. Statistics Dashboard

**Real-time Market Analytics**
- Total properties matching search
- Average price
- Minimum price
- Maximum price
- Average square footage
- Updates dynamically with filters

### 8. Data Export

**CSV Export**
- Export search results to CSV
- Includes all property details
- Date-stamped filenames
- Compatible with Excel and Google Sheets

### 9. Smart Recommendations

**Property Recommendations**
- Based on viewing history
- Based on favorites
- Based on current search criteria
- Price range matching (±20%)
- City and filter matching

---

## Technology Stack

### Backend
- **PHP 7.2+**: Server-side logic and database interaction
- **PDO (PHP Data Objects)**: Secure database access
- **MySQL 8.0+**: Relational database management
- **Session Management**: PHP sessions for user state

### Frontend
- **HTML5**: Semantic markup
- **CSS3**: Modern styling with animations
- **Vanilla JavaScript (ES6+)**: No framework dependencies
- **AJAX**: Asynchronous data fetching
- **Responsive Design**: Mobile-first approach

### External APIs
- **Google Maps JavaScript API**: Mapping and geocoding
- **Google Maps Geocoding API**: Address to coordinates
- **ElevenLabs Conversational AI**: AI assistant widget

### Architecture
- **Single-Page Application**: AJAX-based navigation
- **RESTful API Endpoints**: Clean API design
- **MVC-like Structure**: Separation of concerns
- **Session-based State**: User preferences and favorites

---

## Database Schema

### Primary Table: `rets_property`

**Database**: `boxgra6_cali`  
**Character Set**: `utf8mb4`  
**Collation**: `utf8mb4_unicode_ci`

#### Table Structure

```sql
CREATE TABLE rets_property (
  -- Primary Key
  L_ListingID VARCHAR(50) PRIMARY KEY COMMENT 'Unique MLS listing ID',
  
  -- Address Information
  L_Address VARCHAR(255) NOT NULL COMMENT 'Street address',
  L_City VARCHAR(100) NOT NULL COMMENT 'City name',
  L_Zip VARCHAR(10) NOT NULL COMMENT 'ZIP code',
  
  -- Pricing
  L_SystemPrice DECIMAL(12,2) NOT NULL COMMENT 'Current listing price (USD)',
  
  -- Property Details
  L_Keyword2 VARCHAR(10) DEFAULT NULL COMMENT 'Number of bedrooms',
  LM_Int2_3 INT DEFAULT NULL COMMENT 'Number of bathrooms',
  LM_Dec_3 DECIMAL(10,2) DEFAULT NULL COMMENT 'Square footage',
  
  -- Media
  L_Photos JSON DEFAULT NULL COMMENT 'Array of image URLs',
  
  -- Status & Dates
  L_UpdateDate TIMESTAMP NULL DEFAULT NULL COMMENT 'Last updated by MLS',
  
  -- Indexes for Performance
  INDEX idx_city (L_City),
  INDEX idx_zip (L_Zip),
  INDEX idx_price (L_SystemPrice),
  INDEX idx_beds (L_Keyword2),
  INDEX idx_sqft (LM_Dec_3),
  INDEX idx_city_price (L_City, L_SystemPrice)
);
```

#### Column Usage

| Column | Type | Usage | Example |
|--------|------|-------|---------|
| `L_ListingID` | VARCHAR(50) | Primary key, unique identifier | "12345678" |
| `L_Address` | VARCHAR(255) | Street address | "123 Main St" |
| `L_City` | VARCHAR(100) | City name, searchable | "Los Angeles" |
| `L_Zip` | VARCHAR(10) | ZIP code, exact match | "90210" |
| `L_SystemPrice` | DECIMAL(12,2) | Listing price, range filtering | 750000.00 |
| `L_Keyword2` | VARCHAR(10) | Bedrooms count | "3" |
| `LM_Int2_3` | INT | Bathrooms count | 2 |
| `LM_Dec_3` | DECIMAL(10,2) | Square footage | 2500.00 |
| `L_Photos` | JSON | Array of image URLs | `["url1", "url2"]` |
| `L_UpdateDate` | TIMESTAMP | Last update time | 2024-01-15 10:30:00 |

#### Key Relationships

- **Primary Key**: `L_ListingID` - Used for favorites, viewed properties, and detail views
- **Search Indexes**: City, ZIP, Price, Beds, SqFt for fast filtering
- **Composite Index**: City + Price for optimized city searches with price filters

---

## Installation & Setup

### Prerequisites

- PHP 7.2 or higher with PDO MySQL extension
- MySQL 8.0 or higher
- Web server (Apache/Nginx)
- Google Maps API key
- ElevenLabs account (optional, for Calibot)

### Step 1: Database Setup

1. Create the database:
```sql
CREATE DATABASE boxgra6_cali CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

2. Import or create the `rets_property` table with the schema above

3. Populate with property data (from MLS feed or import)

### Step 2: Configuration

Edit `index.php` (lines 41-44) with your database credentials:

```php
$DB_HOST = 'localhost';
$DB_NAME = 'boxgra6_cali';
$DB_USER = 'your_username';
$DB_PASS = 'your_password';
```

### Step 3: Google Maps API

1. Get API key from [Google Cloud Console](https://console.cloud.google.com/)
2. Enable "Maps JavaScript API" and "Geocoding API"
3. Replace API key in `index.php` (around line 475):

```html
<script async defer src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&callback=initGoogleMaps"></script>
```

### Step 4: Calibot AI (Optional)

1. Create account at [ElevenLabs](https://elevenlabs.io/)
2. Create a Conversational AI agent
3. Configure using `calibot-persona.md` content
4. Update agent ID in `index.php` (around line 2770):

```html
<elevenlabs-convai agent-id="YOUR_AGENT_ID"></elevenlabs-convai>
```

### Step 5: File Permissions

```bash
chmod 755 index.php
chmod 755 api/*.php
```

### Step 6: Deploy

1. Upload all files to web server
2. Ensure PHP sessions directory is writable
3. Test database connection
4. Access via web browser

---

## API Documentation

### Public Endpoints

#### 1. Search Suggestions API

**Endpoint**: `GET /api/search_suggestions.php?q=query`

**Description**: Returns search suggestions for cities, beds, baths, and combinations

**Parameters**:
- `q` (required): Search query string (min 2 characters)

**Response Format**:
```json
{
  "success": true,
  "query": "los angeles 4",
  "suggestions": [
    {
      "type": "city_beds",
      "text": "Los Angeles 4 beds",
      "display": "Los Angeles - 4 beds (1,234 properties)",
      "city": "Los Angeles",
      "beds": 4,
      "count": 1234
    }
  ]
}
```

**Example**:
```
GET /api/search_suggestions.php?q=los%20angeles%204
```

#### 2. City Autocomplete API

**Endpoint**: `GET /api/city_autocomplete.php?q=query`

**Description**: Returns city suggestions with property counts and fuzzy matching

**Parameters**:
- `q` (required): City name query (min 2 characters)

**Response Format**:
```json
{
  "suggestions": [
    {
      "city": "Los Angeles",
      "display": "Los Angeles, CA",
      "count": 5432
    }
  ]
}
```

#### 3. NLP Parser API

**Endpoint**: `GET /api/parse_nlp.php?query=text`

**Description**: Parses natural language queries into structured filters

**Parameters**:
- `query` (required): Natural language search query

**Response Format**:
```json
{
  "city": "Los Angeles",
  "beds": 3,
  "baths": 2,
  "price_max": 800000,
  "keywords": ["ocean view"]
}
```

### Internal Endpoints

#### 4. Toggle Favorite

**Endpoint**: `GET /?toggle_fav=listing_id`

**Description**: Toggle favorite status for a property (AJAX)

**Response**: JSON with success status and favorites count

#### 5. Clear All Favorites

**Endpoint**: `GET /?clear_all_favorites=1`

**Description**: Clear all favorites from session

**Response**: JSON with success status

#### 6. View Favorites

**Endpoint**: `GET /?show_favorites=1`

**Description**: Filter to show only favorited properties

#### 7. CSV Export

**Endpoint**: `GET /?export=csv&[filters]`

**Description**: Export current search results as CSV

**Response**: CSV file download

---

## Frontend Architecture

### JavaScript Structure

**Main Components**:
1. **Search Handler**: Smart search input with autocomplete
2. **Favorites Manager**: AJAX toggle and state management
3. **Modal System**: Property detail modals with galleries
4. **Map Integration**: Google Maps initialization and markers
5. **View Toggle**: Grid/List view switching
6. **Pagination**: Page navigation and URL management

### Key JavaScript Functions

- `fetchSearchSuggestions(query)`: Fetch search suggestions from API
- `selectSuggestion(suggestion)`: Apply suggestion filters
- `performGeneralSearch(query)`: Execute general search
- `toggleFavorite(listingId)`: Toggle favorite status
- `clearAllFavorites()`: Clear all favorites
- `showPropertyModal(listingId)`: Display property details
- `initGoogleMaps()`: Initialize Google Maps

### CSS Architecture

- **Responsive Design**: Mobile-first with breakpoints
- **Component-based**: Modular CSS for components
- **Animations**: Smooth transitions and hover effects
- **Grid System**: Flexible grid for property cards
- **Modal Styling**: Full-screen modals with overlays

---

## Backend Architecture

### PHP Structure

**Main Components**:
1. **Database Connection**: PDO with error handling
2. **Session Management**: Favorites and viewed properties
3. **Query Builder**: Dynamic SQL construction
4. **Filter Processing**: Input validation and sanitization
5. **Pagination Logic**: Page calculation and offset
6. **Statistics Calculation**: Aggregate queries
7. **CSV Export**: Data formatting and download

### Key PHP Functions

- Filter processing: City, ZIP, price, beds, baths, sqft
- General search: Multi-field search with OR logic
- Favorites handling: Session-based storage
- Statistics: Aggregate calculations
- Recommendations: Smart property suggestions

### Security Features

- **PDO Prepared Statements**: SQL injection prevention
- **Input Sanitization**: XSS prevention
- **Session Security**: Secure session handling
- **Error Handling**: Graceful error messages

---

## User Experience Features

### Responsive Design

- **Mobile**: Optimized for phones (320px+)
- **Tablet**: Optimized for tablets (768px+)
- **Desktop**: Full-featured desktop experience (1024px+)

### Accessibility

- Keyboard navigation support
- ARIA labels where appropriate
- Semantic HTML structure
- Focus indicators

### Performance

- Efficient database queries with indexes
- AJAX for dynamic updates
- Image lazy loading
- Optimized CSS and JavaScript

### User Feedback

- Loading states
- Error messages
- Success confirmations
- Real-time updates

---

## Demo Walkthrough

### 1. Homepage & Search

1. **Landing Page**: Beautiful hero section with search bar
2. **Smart Search**: Type "Los Angeles" to see city suggestions
3. **Complex Queries**: Try "4 beds Los angeles" or "Los Angeles 4 beds 8 baths"
4. **General Search**: Type any property detail and press Enter

### 2. Filtering & Results

1. **Advanced Filters**: Click "Advanced Search" to show filters
2. **Apply Filters**: Set city, price range, beds, baths, sqft
3. **View Results**: Properties displayed in grid or list view
4. **Statistics**: View market statistics above results

### 3. Property Interaction

1. **View Details**: Click any property card
2. **Image Gallery**: Navigate through property photos
3. **Map View**: See property location on Google Maps
4. **Add to Favorites**: Click heart icon to favorite

### 4. Favorites Management

1. **View Favorites**: Click "View Favorites" button
2. **Remove Favorites**: Click heart again to unfavorite
3. **Clear All**: Use "Clear All" button to remove all
4. **Favorites Count**: See count in header

### 5. Sorting & Pagination

1. **Sort Options**: Use dropdown to sort by price, beds, sqft
2. **Pagination**: Navigate through pages
3. **View Toggle**: Switch between grid and list views

### 6. Export & Analysis

1. **CSV Export**: Click "Export CSV" button
2. **Download**: File downloads with all property data
3. **Analysis**: Open in Excel or Google Sheets

### 7. AI Assistant (Calibot)

1. **Open Widget**: Click Calibot widget (if configured)
2. **Ask Questions**: "How many properties in Los Angeles?"
3. **Get Insights**: Receive data-driven responses
4. **Search Help**: Get assistance with search queries

---

## File Structure

```
IDX/
├── index.php                      # Main application file (~2800 lines)
│   ├── Database connection
│   ├── Session management
│   ├── Filter processing
│   ├── Query building
│   ├── HTML structure
│   ├── CSS styling
│   └── JavaScript functionality
│
├── api/
│   ├── search_suggestions.php     # General search suggestions API
│   ├── city_autocomplete.php      # City autocomplete with fuzzy matching
│   ├── parse_nlp.php             # Natural language query parser
│   ├── calibot_query.php         # Calibot database query API
│   └── fetch_property.php        # Property data fetcher
│
├── config.php                     # Database configuration loader
├── calibot-persona.md            # Calibot AI agent configuration
├── favicon_IDX.ico               # Site favicon
├── title_image.jpg               # Hero section background
└── README.md                     # This file
```

---

## Configuration Guide

### Database Configuration

**Location**: `index.php` (lines 41-44)

```php
$DB_HOST = 'localhost';
$DB_NAME = 'boxgra6_cali';
$DB_USER = 'your_username';
$DB_PASS = 'your_password';
```

### Google Maps Configuration

**Location**: `index.php` (around line 475)

```html
<script async defer src="https://maps.googleapis.com/maps/api/js?key=YOUR_API_KEY&callback=initGoogleMaps"></script>
```

**Required APIs**:
- Maps JavaScript API
- Geocoding API

### Calibot Configuration

**Location**: `index.php` (around line 2770)

```html
<elevenlabs-convai agent-id="YOUR_AGENT_ID"></elevenlabs-convai>
```

**Setup**:
1. Create agent on ElevenLabs
2. Use `calibot-persona.md` for configuration
3. Update agent ID in HTML

### Session Configuration

**Location**: `index.php` (line 36)

```php
session_start();
```

**Session Variables**:
- `$_SESSION['favorites']`: Array of favorite listing IDs
- `$_SESSION['viewed_properties']`: Array of recently viewed listing IDs

---

## Troubleshooting

### Common Issues

**1. Database Connection Error**
- Check database credentials
- Verify MySQL service is running
- Ensure PDO MySQL extension is installed

**2. No Search Suggestions**
- Check API endpoint accessibility
- Verify database connection in API files
- Check browser console for JavaScript errors

**3. Maps Not Loading**
- Verify Google Maps API key is valid
- Check API key restrictions
- Ensure required APIs are enabled

**4. Favorites Not Persisting**
- Check PHP session configuration
- Verify session directory is writable
- Check browser cookie settings

**5. Images Not Displaying**
- Verify image URLs in database
- Check CORS settings if using external images
- Verify JSON format of L_Photos column

### Debug Mode

Add `?debug=1` to URL to see detailed error messages (development only).

---

## Performance Optimization

### Database Optimization

- Indexes on frequently searched columns
- Composite indexes for common filter combinations
- LIMIT/OFFSET for pagination
- Prepared statements for query efficiency

### Frontend Optimization

- Debounced search input
- Lazy loading for images
- AJAX for dynamic updates
- Minimal DOM manipulation

### Caching Strategy

- Session-based caching for favorites
- Database query optimization
- Browser caching for static assets

---

## Security Considerations

### Input Validation

- All user input sanitized
- SQL injection prevention via PDO
- XSS prevention with htmlspecialchars
- Type casting for numeric inputs

### Session Security

- Secure session configuration
- Session data validation
- CSRF protection considerations

### API Security

- Input validation on all endpoints
- Error message sanitization
- Rate limiting considerations

---

## Future Enhancements

### Potential Additions

- User authentication system
- Saved searches
- Email alerts for new properties
- Advanced analytics dashboard
- Property comparison tool
- Virtual tour integration
- Mortgage calculator
- Neighborhood information

---

## Acknowledgments

- **IDX Exchange** for project sponsorship and mentorship
- **SD6 Team** for collaboration and dedication
- **Google Maps** for mapping services
- **ElevenLabs** for conversational AI technology

---

## License

MIT License - See LICENSE file for details

---

## Contact & Support

**Developer**: Akbar Aman  
**Team**: SD6, IDX Exchange Initiative  
**Project Status**: Complete & Production Ready

---

**Built with ❤️ by SD6 Team @ IDX Exchange**

---

## Recent Updates & Improvements

### Search & Autocomplete Enhancements
- **Improved City Autocomplete**: Enhanced fuzzy matching with Levenshtein distance for misspelled city names
- **General Search Suggestions API**: New endpoint (`api/search_suggestions.php`) for comprehensive search suggestions
- **Smart Query Parsing**: Improved NLP parser for better extraction of city, beds, baths from natural language queries
- **Dropdown Positioning**: Fixed autocomplete dropdown to properly push content down instead of overlapping

### UI/UX Improvements
- **Dynamic Hero Padding**: Autocomplete dropdown now dynamically adjusts hero section padding to prevent content overlap
- **Better Error Handling**: Enhanced error handling in API endpoints with consistent JSON responses
- **Improved Search Flow**: All filters automatically cleared on new general search for cleaner user experience

### API Enhancements
- **City Autocomplete API**: Added fuzzy matching for better suggestion accuracy
- **Search Suggestions API**: New comprehensive endpoint for multi-criteria search suggestions
- **NLP Parser**: Improved keyword extraction and city matching with database-driven fuzzy matching

### Technical Improvements
- **Code Organization**: Better separation of concerns in API endpoints
- **Error Handling**: Consistent error response formats across all APIs
- **Performance**: Optimized database queries for autocomplete suggestions

*Last Updated: December 2024*
