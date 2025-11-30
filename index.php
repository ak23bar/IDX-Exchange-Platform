<?php
/**
 * California Property Finder
 * Developed by Akbar Aman — SD6 Team, IDX Exchange Initiative
 * ------------------------------------------------------------
 * A sophisticated, PHP-based property search interface for
 * exploring single-family listings across California.
 *
 * Features:
 * - Advanced Filters: city, ZIP, price/sqft ranges, beds, baths
 * - Multi-sort capabilities with direction control
 * - Grid/List view toggle for flexible browsing
 * - Interactive property detail modals with image galleries
 * - Session-based favorites system
 * - CSV export functionality for data analysis
 * - Real-time statistics dashboard
 * - Recently viewed properties tracking
 * - Keyboard shortcuts for power users
 * - Responsive design with smooth animations
 *
 * Database Schema (boxgra6_cali):
 *   rets_property(
 *     L_ListingID (PK),
 *     L_Address,
 *     L_City,
 *     L_Zip,
 *     L_SystemPrice,
 *     L_Keyword2 (Beds),
 *     LM_Int2_3 (Baths),
 *     LM_Dec_3 (SqFt),
 *     L_Photos (JSON array of image URLs),
 *     L_UpdateDate
 *   )
 */

session_start();

// -------------------------
// 1) Database Configuration
// -------------------------
$DB_HOST = 'localhost';
$DB_NAME = 'boxgra6_cali';
$DB_USER = 'boxgra6_sd';
$DB_PASS = 'Real_estate650$';

// -------------------------
// 2) Establish PDO Connection
// -------------------------
$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
$options = [
  PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
  PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
  $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (Throwable $e) {
  http_response_code(500);
  echo "<h1>Database Connection Error</h1><pre>" . htmlspecialchars($e->getMessage()) . "</pre>";
  exit;
}

// -------------------------
// 3) Handle Favorites & Viewed Properties
// -------------------------
if (!isset($_SESSION['favorites'])) {
  $_SESSION['favorites'] = [];
}
if (!isset($_SESSION['viewed_properties'])) {
  $_SESSION['viewed_properties'] = [];
}
// Handle clear all favorites
if (isset($_GET['clear_all_favorites'])) {
  $_SESSION['favorites'] = [];
  
  // Return JSON for AJAX requests
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode([
      'success' => true,
      'favorites_count' => 0,
      'message' => 'All favorites cleared'
    ]);
    exit;
  }
  
  // Fallback for non-AJAX requests
  header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(array_diff_key($_GET, ['clear_all_favorites'=>'', 'show_favorites'=>''])));
  exit;
}

// Handle AJAX favorite toggle
if (isset($_GET['toggle_fav'])) {
  $lid = (string)trim($_GET['toggle_fav']); // Normalize to string
  
  // Ensure favorites array exists and is properly initialized
  if (!isset($_SESSION['favorites']) || !is_array($_SESSION['favorites'])) {
    $_SESSION['favorites'] = [];
  }
  
  // Normalize all existing favorites to strings BEFORE checking
  $_SESSION['favorites'] = array_map(function($id) {
    return (string)trim($id);
  }, $_SESSION['favorites']);
  $_SESSION['favorites'] = array_filter($_SESSION['favorites']); // Remove empty values
  $_SESSION['favorites'] = array_values($_SESSION['favorites']); // Reindex
  
  // Store state before toggle for debugging
  $was_in_favorites = in_array($lid, $_SESSION['favorites'], true);
  
  // Check if already in favorites (strict string comparison)
  $key = false;
  foreach ($_SESSION['favorites'] as $index => $fav_id) {
    if ((string)trim($fav_id) === (string)trim($lid)) {
      $key = $index;
      break;
    }
  }
  
  if ($key !== false) {
    // Remove from favorites - properly unset and reindex
    unset($_SESSION['favorites'][$key]);
    $_SESSION['favorites'] = array_values($_SESSION['favorites']); // Reindex array
    $is_favorite = false;
  } else {
    // Add to favorites (avoid duplicates)
    if (!in_array($lid, $_SESSION['favorites'], true)) {
      $_SESSION['favorites'][] = $lid;
    }
    $is_favorite = true;
  }
  
  // Return JSON for AJAX requests
  if (!empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) == 'xmlhttprequest') {
    header('Content-Type: application/json');
    echo json_encode([
      'success' => true,
      'is_favorite' => $is_favorite,
      'favorites_count' => count($_SESSION['favorites']),
      'debug' => [
        'listing_id' => $lid,
        'was_in_favorites' => $was_in_favorites,
        'now_in_favorites' => $is_favorite,
        'favorites_array' => $_SESSION['favorites']
      ]
    ]);
    exit;
  }
  
  // Fallback for non-AJAX requests
  header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(array_diff_key($_GET, ['toggle_fav'=>''])));
  exit;
}

// Track property views when modal is viewed
if (isset($_GET['view_property'])) {
  $lid = $_GET['view_property'];
  if (!in_array($lid, $_SESSION['viewed_properties'])) {
    $_SESSION['viewed_properties'][] = $lid;
    // Keep only last 20 viewed properties
    if (count($_SESSION['viewed_properties']) > 20) {
      array_shift($_SESSION['viewed_properties']);
    }
  }
  // Return JSON for AJAX
  header('Content-Type: application/json');
  echo json_encode(['success' => true]);
  exit;
}

// -------------------------
// 4) Handle CSV Export
// -------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  $city      = isset($_GET['city'])      ? trim($_GET['city'])      : '';
  $zip       = isset($_GET['zip'])       ? trim($_GET['zip'])       : '';
  $price_min = isset($_GET['price_min']) && $_GET['price_min'] !== '' ? (int)$_GET['price_min']  : '';
  $price_max = isset($_GET['price_max']) && $_GET['price_max'] !== '' ? (int)$_GET['price_max']  : '';
  $beds      = isset($_GET['beds'])      && $_GET['beds'] !== ''      ? (int)$_GET['beds']       : '';
  $baths     = isset($_GET['baths'])     && $_GET['baths'] !== ''     ? (int)$_GET['baths']      : '';
  $sqft_min  = isset($_GET['sqft_min'])  && $_GET['sqft_min'] !== ''  ? (int)$_GET['sqft_min']   : '';
  $sqft_max  = isset($_GET['sqft_max'])  && $_GET['sqft_max'] !== ''  ? (int)$_GET['sqft_max']   : '';
  
  $where  = [];
  $params = [];
  
  if ($city !== '') { $where[] = 'L_City LIKE :city'; $params[':city'] = "%$city%"; }
  if ($zip !== '') { $where[] = 'L_Zip = :zip'; $params[':zip'] = $zip; }
  if ($price_min !== '') { $where[] = 'L_SystemPrice >= :pmin'; $params[':pmin'] = $price_min; }
  if ($price_max !== '') { $where[] = 'L_SystemPrice <= :pmax'; $params[':pmax'] = $price_max; }
  if ($beds !== '') { $where[] = 'CAST(L_Keyword2 AS UNSIGNED) >= :beds'; $params[':beds'] = $beds; }
  if ($baths !== '') { $where[] = 'CAST(LM_Dec_3 AS UNSIGNED) >= :baths'; $params[':baths'] = $baths; }
  if ($sqft_min !== '') { $where[] = 'CAST(LM_Int2_3 AS UNSIGNED) >= :sqft_min'; $params[':sqft_min'] = $sqft_min; }
  if ($sqft_max !== '') { $where[] = 'CAST(LM_Int2_3 AS UNSIGNED) <= :sqft_max'; $params[':sqft_max'] = $sqft_max; }
  
  $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
  
  $sql = "SELECT L_ListingID, L_Address, L_City, L_Zip, L_SystemPrice, L_Keyword2 AS Beds, LM_Dec_3 AS Baths, LM_Int2_3 AS SqFt FROM rets_property $where_sql LIMIT 1000";
  $st = $pdo->prepare($sql);
  $st->execute($params);
  
  header('Content-Type: text/csv');
  header('Content-Disposition: attachment; filename="properties_' . date('Y-m-d') . '.csv"');
  $out = fopen('php://output', 'w');
  fputcsv($out, ['Listing ID', 'Address', 'City', 'ZIP', 'Price', 'Beds', 'Baths', 'SqFt']);
  while ($row = $st->fetch()) {
    fputcsv($out, $row);
  }
  fclose($out);
  exit;
}

// -------------------------
// 5) Handle Input Filters
// -------------------------
$city      = isset($_GET['city'])      ? trim($_GET['city'])      : '';
$zip       = isset($_GET['zip'])       ? trim($_GET['zip'])       : '';
$price_min = isset($_GET['price_min']) && $_GET['price_min'] !== '' ? (int)$_GET['price_min']  : '';
$price_max = isset($_GET['price_max']) && $_GET['price_max'] !== '' ? (int)$_GET['price_max']  : '';
$beds      = isset($_GET['beds'])      && $_GET['beds'] !== ''      ? (int)$_GET['beds']       : '';
$baths     = isset($_GET['baths'])     && $_GET['baths'] !== ''     ? (int)$_GET['baths']      : '';
$sqft_min  = isset($_GET['sqft_min'])  && $_GET['sqft_min'] !== ''  ? (int)$_GET['sqft_min']   : '';
$sqft_max  = isset($_GET['sqft_max'])  && $_GET['sqft_max'] !== ''  ? (int)$_GET['sqft_max']   : '';
$search    = isset($_GET['search'])    ? trim($_GET['search'])      : ''; // General search across all fields
$sort      = isset($_GET['sort'])      ? $_GET['sort']            : 'price_desc';
$view      = isset($_GET['view'])      ? $_GET['view']            : 'grid';
$page      = isset($_GET['page'])      ? max(1, (int)$_GET['page']) : 1;
$per_page  = 12;
$offset    = ($page - 1) * $per_page;
$show_favorites = isset($_GET['show_favorites']) && $_GET['show_favorites'] === '1';

// If there's a search parameter, clear all other filters to avoid conflicts
if ($search !== '') {
  $city = '';
  $zip = '';
  $price_min = '';
  $price_max = '';
  $beds = '';
  $baths = '';
  $sqft_min = '';
  $sqft_max = '';
}

// -------------------------
// 6) Build Query Conditions
// -------------------------
$where  = [];
$params = [];

if ($city !== '') {
  $where[] = 'L_City LIKE :city';
  $params[':city'] = "%$city%";
}
if ($zip !== '') {
  $where[] = 'L_Zip = :zip';
  $params[':zip'] = $zip;
}
if ($price_min !== '') {
  $where[] = 'L_SystemPrice >= :pmin';
  $params[':pmin'] = $price_min;
}
if ($price_max !== '') {
  $where[] = 'L_SystemPrice <= :pmax';
  $params[':pmax'] = $price_max;
}
if ($beds !== '') {
  $where[] = 'CAST(L_Keyword2 AS UNSIGNED) >= :beds';
  $params[':beds'] = $beds;
}
if ($baths !== '') {
  $where[] = 'CAST(LM_Dec_3 AS UNSIGNED) >= :baths';
  $params[':baths'] = $baths;
}
if ($sqft_min !== '') {
  $where[] = 'CAST(LM_Int2_3 AS UNSIGNED) >= :sqft_min';
  $params[':sqft_min'] = $sqft_min;
}
if ($sqft_max !== '') {
  $where[] = 'CAST(LM_Int2_3 AS UNSIGNED) <= :sqft_max';
  $params[':sqft_max'] = $sqft_max;
}

// General search across all database fields
if ($search !== '') {
  // Try to parse the search query to extract structured filters
  $words = preg_split('/\s+/', $search);
  $textParts = [];
  $extractedBeds = null;
  $extractedBaths = null;
  $extractedCity = null;
  
  foreach ($words as $word) {
    // Check for "X beds" or "X bed"
    if (preg_match('/^(\d+)\s*(bed|beds|br|bedroom|bedrooms)$/i', $word, $m)) {
      $extractedBeds = (int)$m[1];
      continue;
    }
    
    // Check for "X baths" or "X bath" or "X bathrooms"
    if (preg_match('/^(\d+)\s*(bath|baths|ba|bathroom|bathrooms)$/i', $word, $m)) {
      $extractedBaths = (int)$m[1];
      continue;
    }
    
    // Check for plain numbers
    if (preg_match('/^\d+$/', $word)) {
      $num = (int)$word;
      // If it's a small number and we don't have beds yet, assume beds
      if ($num <= 10 && $extractedBeds === null) {
        $extractedBeds = $num;
        continue;
      }
      // If we have beds but not baths and it's reasonable, assume baths
      if ($extractedBeds !== null && $extractedBaths === null && $num >= 1 && $num <= 20) {
        $extractedBaths = $num;
        continue;
      }
      // Skip other numbers for now
      continue;
    }
    
    // Otherwise, it's text (likely city name)
    $textParts[] = $word;
  }
  
  // If we extracted city, beds, or baths, use them as filters instead of general search
  if (!empty($textParts)) {
    $extractedCity = implode(' ', $textParts);
  }
  
  // Apply extracted filters
  if ($extractedCity && $city === '') {
    $where[] = 'L_City LIKE :extracted_city';
    $params[':extracted_city'] = '%' . $extractedCity . '%';
  }
  
  if ($extractedBeds !== null && $beds === '') {
    $where[] = 'CAST(L_Keyword2 AS UNSIGNED) >= :extracted_beds';
    $params[':extracted_beds'] = $extractedBeds;
  }
  
  if ($extractedBaths !== null && $baths === '') {
    $where[] = 'CAST(LM_Dec_3 AS UNSIGNED) >= :extracted_baths';
    $params[':extracted_baths'] = $extractedBaths;
  }
  
  // If we didn't extract structured filters, do general search
  if ($extractedCity === null && $extractedBeds === null && $extractedBaths === null) {
    $search_term = '%' . $search . '%';
    $search_param = ':general_search';
    
    // Search across all relevant fields: address, city, zip, listing ID
    $search_conditions = [
      "(L_Address LIKE $search_param)",
      "(L_City LIKE $search_param)",
      "(L_Zip LIKE $search_param)",
      "(L_ListingID LIKE $search_param)"
    ];
    
    // Combine all search conditions with OR (search matches if any field matches)
    $where[] = '(' . implode(' OR ', $search_conditions) . ')';
    $params[$search_param] = $search_term;
  }
}

// Filter by favorites if requested
if ($show_favorites && !empty($_SESSION['favorites'])) {
  // Normalize all favorites to strings for consistent comparison
  $favorite_ids = array_map(function($id) {
    return (string)trim($id);
  }, $_SESSION['favorites']);
  $favorite_ids = array_filter($favorite_ids); // Remove empty values
  
  if (!empty($favorite_ids)) {
    $placeholders = [];
    foreach ($favorite_ids as $index => $fav_id) {
      $key = ':fav_' . $index;
      $placeholders[] = $key;
      $params[$key] = $fav_id;
    }
    $where[] = 'L_ListingID IN (' . implode(', ', $placeholders) . ')';
  } else {
    // No favorites, return empty result
    $where[] = '1 = 0'; // Always false condition
  }
}

$where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// -------------------------
// 7) Sort Logic
// -------------------------
$order_by = 'L_SystemPrice DESC';
switch ($sort) {
  case 'price_asc':  $order_by = 'L_SystemPrice ASC'; break;
  case 'price_desc': $order_by = 'L_SystemPrice DESC'; break;
  case 'beds_desc':  $order_by = 'CAST(L_Keyword2 AS UNSIGNED) DESC'; break;
  case 'beds_asc':   $order_by = 'CAST(L_Keyword2 AS UNSIGNED) ASC'; break;
  case 'sqft_desc':  $order_by = 'CAST(LM_Int2_3 AS UNSIGNED) DESC'; break;
  case 'sqft_asc':   $order_by = 'CAST(LM_Int2_3 AS UNSIGNED) ASC'; break;
}

// -------------------------
// 8) Statistics Query
// -------------------------
try {
  $sql_stats = "SELECT 
    COUNT(*) as total_count,
    AVG(L_SystemPrice) as avg_price,
    MIN(L_SystemPrice) as min_price,
    MAX(L_SystemPrice) as max_price,
    AVG(CAST(LM_Int2_3 AS UNSIGNED)) as avg_sqft
    FROM rets_property $where_sql";
  $st_stats = $pdo->prepare($sql_stats);
  $st_stats->execute($params);
  $stats = $st_stats->fetch();
} catch (Throwable $e) {
  error_log("Statistics query error: " . $e->getMessage());
  $stats = [
    'total_count' => 0,
    'avg_price' => 0,
    'min_price' => 0,
    'max_price' => 0,
    'avg_sqft' => 0
  ];
}

// -------------------------
// 9) Pagination Setup
// -------------------------
$total = (int)$stats['total_count'];
$total_pages = max(1, (int)ceil($total / $per_page));
if ($page > $total_pages) { $page = $total_pages; $offset = ($page - 1) * $per_page; }

// -------------------------
// 10) Fetch Results
// -------------------------
$sql = "
  SELECT
    L_ListingID,
    L_Address,
    L_City,
    L_Zip,
    L_SystemPrice,
    L_Keyword2   AS Beds,
    LM_Int2_3    AS SqFt,
    LM_Dec_3     AS Baths,
    L_Photos
  FROM rets_property
  $where_sql
  ORDER BY $order_by
  LIMIT :limit OFFSET :offset
";

try {
  $st = $pdo->prepare($sql);
  foreach ($params as $k => $v) {
    $st->bindValue($k, $v);
  }
  $st->bindValue(':limit',  $per_page, PDO::PARAM_INT);
  $st->bindValue(':offset', $offset,   PDO::PARAM_INT);
  $st->execute();
  $rows = $st->fetchAll();
} catch (Throwable $e) {
  error_log("Main query error: " . $e->getMessage() . " | SQL: " . $sql);
  $rows = [];
  // Show error in development, hide in production
  if (isset($_GET['debug'])) {
    echo "<div style='padding:2rem;background:#ff6b6b;color:white;margin:2rem;border-radius:8px;'>";
    echo "<h3>Database Query Error</h3>";
    echo "<p>" . htmlspecialchars($e->getMessage()) . "</p>";
    echo "<pre style='background:rgba(0,0,0,0.3);padding:1rem;border-radius:4px;overflow:auto;'>" . htmlspecialchars($sql) . "</pre>";
    echo "</div>";
  }
}

// -------------------------
// 10.5) Get Smart Recommendations
// -------------------------
$recommendations = [];
if (count($_SESSION['viewed_properties']) > 0 || count($_SESSION['favorites']) > 0 || $total > 0) {
  // Get properties user has interacted with
  $seed_props = array_merge($_SESSION['favorites'], $_SESSION['viewed_properties']);
  
  // If user searched, use current search criteria as seed
  if ($total > 0 && count($rows) > 0) {
    $avg_search_price = $stats['avg_price'] ?? 0;
    $rec_price_min = $avg_search_price * 0.8;
    $rec_price_max = $avg_search_price * 1.2;
    
    $rec_sql = "
      SELECT L_ListingID, L_Address, L_City, L_Zip, L_SystemPrice, L_Keyword2 AS Beds, 
             LM_Int2_3 AS SqFt, LM_Dec_3 AS Baths, L_Photos
      FROM rets_property
      WHERE L_SystemPrice BETWEEN :pmin AND :pmax
    ";
    
    if ($city !== '') {
      $rec_sql .= " AND L_City LIKE :city";
    }
    if ($zip !== '') {
      $rec_sql .= " AND L_Zip = :zip";
    }
    if ($beds !== '') {
      $rec_sql .= " AND CAST(L_Keyword2 AS UNSIGNED) >= :beds";
    }
    if ($baths !== '') {
      $rec_sql .= " AND CAST(LM_Dec_3 AS UNSIGNED) >= :baths";
    }
    if ($sqft_min !== '') {
      $rec_sql .= " AND CAST(LM_Int2_3 AS UNSIGNED) >= :sqft_min";
    }
    if ($sqft_max !== '') {
      $rec_sql .= " AND CAST(LM_Int2_3 AS UNSIGNED) <= :sqft_max";
    }
    
    // Exclude already displayed properties
    $exclude_ids = [];
    if (count($rows) > 0) {
      $exclude_ids = array_map(function($r) { return $r['L_ListingID']; }, $rows);
      if (count($exclude_ids) > 0) {
        $placeholders = implode(',', array_map(function($i) { return ":excl$i"; }, array_keys($exclude_ids)));
        $rec_sql .= " AND L_ListingID NOT IN ($placeholders)";
      }
    }
    
    $rec_sql .= " ORDER BY RAND() LIMIT 4";
    
    try {
      $rec_st = $pdo->prepare($rec_sql);
      $rec_st->bindValue(':pmin', $rec_price_min, PDO::PARAM_INT);
      $rec_st->bindValue(':pmax', $rec_price_max, PDO::PARAM_INT);
      if ($city !== '') $rec_st->bindValue(':city', "%$city%");
      if ($zip !== '') $rec_st->bindValue(':zip', $zip);
      if ($beds !== '') $rec_st->bindValue(':beds', $beds, PDO::PARAM_INT);
      if ($baths !== '') $rec_st->bindValue(':baths', $baths);
      if ($sqft_min !== '') $rec_st->bindValue(':sqft_min', $sqft_min, PDO::PARAM_INT);
      if ($sqft_max !== '') $rec_st->bindValue(':sqft_max', $sqft_max, PDO::PARAM_INT);
      
      if (count($exclude_ids) > 0) {
        foreach ($exclude_ids as $idx => $id) {
          $rec_st->bindValue(":excl$idx", $id);
        }
      }
      
      $rec_st->execute();
      $recommendations = $rec_st->fetchAll();
    } catch (Throwable $e) {
      // If recommendations query fails, just set empty array
      // Log error but don't break the page
      error_log('Recommendations query error: ' . $e->getMessage());
      $recommendations = [];
    }
  }
}

// -------------------------
// 11) Helper Functions
// -------------------------
function money($n) {
  return '$' . number_format((float)$n, 0);
}

function first_photo($json) {
  if (!$json) return '';
  $arr = json_decode($json, true);
  if (json_last_error() !== JSON_ERROR_NONE || !is_array($arr) || empty($arr)) return '';
  if (isset($arr[0])) {
    if (is_string($arr[0])) return $arr[0];
    if (is_array($arr[0])) {
      foreach (['url','URL','mediaUrl','MediaURL','PhotoUrl','photo','src'] as $k) {
        if (isset($arr[0][$k]) && is_string($arr[0][$k])) return $arr[0][$k];
      }
    }
  }
  return '';
}

function all_photos($json) {
  if (!$json) return [];
  $arr = json_decode($json, true);
  if (json_last_error() !== JSON_ERROR_NONE || !is_array($arr)) return [];
  $photos = [];
  foreach ($arr as $item) {
    if (is_string($item)) {
      $photos[] = $item;
    } elseif (is_array($item)) {
      foreach (['url','URL','mediaUrl','MediaURL','PhotoUrl','photo','src'] as $k) {
        if (isset($item[$k]) && is_string($item[$k])) {
          $photos[] = $item[$k];
          break;
        }
      }
    }
  }
  return $photos;
}
?>

<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title>California Property Finder</title>
  <link rel="icon" type="image/x-icon" href="favicon_IDX.ico" />
  <script>
    // CRITICAL: Patch Array.forEach BEFORE any other scripts run
    // This fixes the ElevenLabs widget bug where it calls forEach on non-arrays
    (function() {
      'use strict';
      const originalForEach = Array.prototype.forEach;
      
      Array.prototype.forEach = function(callback, thisArg) {
        // Handle null/undefined
        if (this == null) {
          console.warn('[forEach patch] Called on null/undefined, returning');
          return;
        }
        
        // If it's already a proper array or array-like, use original
        if (Array.isArray(this)) {
          return originalForEach.call(this, callback, thisArg);
        }
        
        // Handle array-like objects (NodeList, HTMLCollection, etc.)
        if (this instanceof NodeList || 
            this instanceof HTMLCollection ||
            (typeof this === 'object' && 
             typeof this.length === 'number' && 
             this.length >= 0 &&
             !isNaN(this.length))) {
          try {
            return originalForEach.call(this, callback, thisArg);
          } catch(e) {
            // Fallback: convert to array
            try {
              const arr = Array.from(this);
              return originalForEach.call(arr, callback, thisArg);
            } catch(e2) {
              console.warn('[forEach patch] Conversion failed:', e2);
              return;
            }
          }
        }
        
        // Try to convert to array for other cases
        try {
          if (typeof this === 'object' && this !== null) {
            const arr = Array.from(this);
            return originalForEach.call(arr, callback, thisArg);
          }
        } catch(e) {
          // Silently fail to prevent breaking the widget
          console.warn('[forEach patch] Non-array-like object, skipping:', typeof this);
          return;
        }
        
        // Last resort: return without error
        console.warn('[forEach patch] Unhandled case, skipping');
        return;
      };
      
      console.log('[forEach patch] Array.prototype.forEach patched successfully');
    })();
    
    // Minimal error handler to prevent widget errors from breaking the page
    window.addEventListener('error', function(e) {
      if (e.message && e.message.includes('forEach') && 
          (e.filename?.includes('elevenlabs') || e.filename?.includes('content-all'))) {
        e.preventDefault();
        return true;
      }
    }, true);
    
    function initGoogleMaps() {
      console.log('Google Maps API loaded successfully');
      window.googleMapsLoaded = true;
    }
  </script>
  <script src="https://maps.googleapis.com/maps/api/js?key=AIzaSyDG9X7EQahSj96_k2tURw3IP7rlcNkNenw&callback=initGoogleMaps" async defer></script>
  <style>
    :root {
      --bg: #080d1a;
      --card: #101a33;
      --ink: #e6e9f5;
      --muted: #9aa4c7;
      --accent: #3a86ff;
      --accent-light: #6ca0ff;
      --success: #06d6a0;
      --warning: #ffd60a;
      --shadow: 0 8px 18px rgba(0, 0, 0, 0.35);
    }
    * { box-sizing: border-box; }
    body {
      margin: 0;
      font-family: "Inter", system-ui, -apple-system, Segoe UI, Roboto, Ubuntu;
      color: var(--ink);
      background: linear-gradient(160deg, #090e1d 0%, #111e3e 100%);
      min-height: 100vh;
    }

    /* Hero Section with Background Image */
    .hero {
      position: relative;
      background: linear-gradient(rgba(8, 13, 26, 0.75), rgba(8, 13, 26, 0.85)), url('title_image.jpg');
      background-size: cover;
      background-position: center;
      background-attachment: fixed;
      padding: 4rem 0 3rem;
      text-align: center;
      border-bottom: 1px solid rgba(78, 116, 255, 0.15);
      overflow: visible;
    }
    .hero::before {
      content: '';
      position: absolute;
      inset: 0;
      background: linear-gradient(to bottom, transparent 0%, rgba(8, 13, 26, 0.4) 100%);
      pointer-events: none;
    }
    .hero-inner {
      position: relative;
      z-index: 1;
      max-width: 800px;
      margin: 0 auto;
      padding: 0 1.5rem;
    }
    .hero h1 {
      font-size: 2.5rem;
      margin: 0 0 1rem;
      font-weight: 700;
      color: #ffffff;
      text-shadow: 0 2px 12px rgba(0, 0, 0, 0.5);
      letter-spacing: -0.5px;
    }
    
    /* Sticky Header for Navigation */
    header.sticky-nav {
      position: sticky;
      top: 0;
      z-index: 100;
      backdrop-filter: blur(12px) saturate(140%);
      background: rgba(11, 17, 35, 0.92);
      border-bottom: 1px solid rgba(78, 116, 255, 0.15);
      padding: 1rem 0;
      text-align: center;
      box-shadow: 0 1px 10px rgba(0, 0, 0, 0.2);
      display: block; /* Always visible */
    }
    header.sticky-nav .sticky-nav-content {
      max-width: 1200px;
      margin: 0 auto;
      padding: 0 1.5rem;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 1rem;
      position: relative;
    }
    header.sticky-nav .home-button {
      position: absolute;
      left: 1.5rem;
      display: flex;
      align-items: center;
      gap: 0.5rem;
      padding: 0.5rem 1rem;
      background: rgba(79, 124, 255, 0.15);
      border: 1px solid rgba(79, 124, 255, 0.3);
      border-radius: 8px;
      color: var(--accent-light);
      text-decoration: none;
      font-size: 0.9rem;
      font-weight: 500;
      transition: all 0.2s ease;
    }
    header.sticky-nav .home-button:hover {
      background: rgba(79, 124, 255, 0.25);
      border-color: var(--accent);
      transform: translateY(-1px);
    }
    header.sticky-nav .home-button svg {
      width: 18px;
      height: 18px;
    }
    header.sticky-nav h2 {
      font-size: 1.2rem;
      margin: 0;
      background: linear-gradient(to right, #5ab3ff, #9caeff, #a3aaff);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }

    .wrap {
      max-width: 1200px;
      margin: 0 auto;
      padding: 1.5rem;
    }

    /* Statistics Dashboard */
    .stats {
      display: grid;
      grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
      gap: 1rem;
      margin-bottom: 1.5rem;
    }
    .stat-card {
      background: rgba(17, 26, 56, 0.6);
      border: 1px solid rgba(79, 124, 255, 0.2);
      border-radius: 14px;
      padding: 1rem;
      text-align: center;
    }
    .stat-value {
      font-size: 1.5rem;
      font-weight: 700;
      color: var(--accent-light);
    }
    .stat-label {
      font-size: 0.8rem;
      color: var(--muted);
      margin-top: 4px;
    }

    /* Modern Smart Search */
    .smart-search-container {
      margin: 0;
      background: transparent;
      padding: 0;
    }
    .smart-search-container.in-content {
      margin-bottom: 2.5rem;
      background: rgba(17, 26, 56, 0.4);
      padding: 2rem;
      border-radius: 20px;
      border: 1px solid rgba(79, 124, 255, 0.15);
    }
    .search-input-wrapper {
      position: relative;
      margin-bottom: 1.5rem;
      z-index: 10000;
    }
    .hero .search-input-wrapper {
      margin-bottom: 1rem;
    }
    .search-icon {
      position: absolute;
      left: 20px;
      top: 50%;
      transform: translateY(-50%);
      color: rgba(154, 164, 199, 0.5);
      pointer-events: none;
    }
    .smart-search-input {
      width: 100%;
      padding: 18px 56px 18px 52px;
      border-radius: 14px;
      border: 2px solid rgba(79, 124, 255, 0.25);
      background: rgba(11, 17, 35, 0.8);
      color: var(--ink);
      font-size: 1rem;
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }
    .hero .smart-search-input {
      padding: 20px 56px 20px 52px;
      font-size: 1.05rem;
      background: rgba(255, 255, 255, 0.95);
      color: #1a202c;
      border-color: rgba(255, 255, 255, 0.3);
      box-shadow: 0 4px 20px rgba(0, 0, 0, 0.3);
    }
    .hero .smart-search-input::placeholder {
      color: rgba(26, 32, 44, 0.5);
    }
    .hero .smart-search-input:focus {
      background: rgba(255, 255, 255, 1);
      border-color: var(--accent);
      box-shadow: 0 0 0 4px rgba(58, 134, 255, 0.2), 0 4px 20px rgba(0, 0, 0, 0.3);
    }
    .smart-search-input:focus {
      border-color: var(--accent);
      outline: none;
      background: rgba(11, 17, 35, 0.95);
      box-shadow: 0 0 0 4px rgba(58, 134, 255, 0.1);
    }
    .smart-search-input::placeholder {
      color: rgba(154, 164, 199, 0.45);
    }
    .clear-btn {
      position: absolute;
      right: 16px;
      top: 50%;
      transform: translateY(-50%);
      background: transparent;
      border: none;
      color: rgba(154, 164, 199, 0.5);
      cursor: pointer;
      padding: 8px;
      border-radius: 6px;
      transition: all 0.2s;
    }
    .clear-btn:hover {
      background: rgba(79, 124, 255, 0.15);
      color: var(--accent-light);
    }
    
    /* Autocomplete Dropdown */
    .autocomplete-dropdown {
      background: rgba(11, 17, 35, 0.98);
      border: 1px solid rgba(79, 124, 255, 0.3);
      border-radius: 12px;
      box-shadow: 0 8px 32px rgba(0, 0, 0, 0.4);
      overflow: hidden;
      max-height: 400px;
      overflow-y: auto;
      position: absolute;
      top: calc(100% + 4px);
      left: 0;
      right: 0;
      z-index: 10001;
    }
    .autocomplete-header {
      padding: 12px 18px;
      font-size: 0.75rem;
      text-transform: uppercase;
      letter-spacing: 1px;
      color: var(--muted);
      font-weight: 600;
      background: rgba(17, 26, 56, 0.6);
      border-bottom: 1px solid rgba(79, 124, 255, 0.15);
    }
    .autocomplete-item {
      padding: 16px 18px;
      cursor: pointer;
      transition: all 0.2s;
      border-bottom: 1px solid rgba(79, 124, 255, 0.08);
    }
    .autocomplete-item:last-child {
      border-bottom: none;
    }
    .autocomplete-item:hover {
      background: rgba(58, 134, 255, 0.12);
    }
    .autocomplete-item.selected {
      background: rgba(58, 134, 255, 0.2);
      border-left: 3px solid var(--accent);
    }
    .city-suggestion {
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .city-suggestion .suggestion-action {
      font-size: 0.75rem;
      color: var(--muted);
    }
    .main-suggestion {
      background: rgba(17, 26, 56, 0.4);
    }
    .main-suggestion:hover {
      background: rgba(58, 134, 255, 0.15);
    }
    .suggestion-text {
      font-size: 1rem;
      color: var(--ink);
      margin-bottom: 10px;
      font-weight: 500;
    }
    .suggestion-filters {
      display: flex;
      flex-wrap: wrap;
      gap: 8px;
      margin-bottom: 8px;
    }
    .filter-tag {
      display: inline-flex;
      align-items: center;
      background: rgba(58, 134, 255, 0.15);
      border: 1px solid rgba(58, 134, 255, 0.3);
      border-radius: 8px;
      padding: 4px 10px;
      font-size: 0.8rem;
    }
    .tag-label {
      color: var(--muted);
      margin-right: 4px;
    }
    .tag-value {
      color: var(--accent-light);
      font-weight: 600;
    }
    .suggestion-action {
      font-size: 0.7rem;
      color: var(--muted);
      text-transform: uppercase;
      letter-spacing: 0.5px;
      margin-top: 8px;
    }
    
    /* Toggle Filters Button */
    .toggle-filters-btn {
      display: inline-flex;
      align-items: center;
      gap: 8px;
      padding: 12px 20px;
      background: rgba(17, 26, 56, 0.7);
      border: 1px solid rgba(79, 124, 255, 0.3);
      border-radius: 10px;
      color: var(--ink);
      font-size: 0.9rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.25s;
    }
    .hero .toggle-filters-btn {
      background: rgba(255, 255, 255, 0.15);
      border-color: rgba(255, 255, 255, 0.3);
      color: #ffffff;
      backdrop-filter: blur(10px);
    }
    .hero .toggle-filters-btn:hover {
      background: rgba(255, 255, 255, 0.25);
      border-color: rgba(255, 255, 255, 0.5);
    }
    .toggle-filters-btn:hover {
      background: rgba(58, 134, 255, 0.15);
      border-color: var(--accent);
    }
    .toggle-filters-btn.active {
      background: rgba(58, 134, 255, 0.2);
      border-color: var(--accent-light);
    }
    .toggle-filters-btn svg {
      transition: transform 0.25s;
    }
    .toggle-filters-btn.active svg {
      transform: rotate(180deg);
    }
    
    /* Filters Panel */
    .filters-panel {
      margin-top: 1.5rem;
      animation: slideDown 0.3s ease-out;
    }
    @keyframes slideDown {
      from {
        opacity: 0;
        transform: translateY(-10px);
      }
      to {
        opacity: 1;
        transform: translateY(0);
      }
    }
    
    .search {
      background: rgba(17, 26, 56, 0.7);
      border: 1px solid rgba(79, 124, 255, 0.2);
      padding: 1.5rem;
      border-radius: 18px;
      box-shadow: var(--shadow);
      margin-bottom: 1.5rem;
    }
    .search-grid {
      display: grid;
      gap: 1rem;
      grid-template-columns: repeat(auto-fit, minmax(160px, 1fr));
      margin-bottom: 1rem;
    }
    .search label {
      font-size: 0.8rem;
      color: var(--muted);
      display: block;
      margin-bottom: 5px;
      font-weight: 500;
    }
    .search input, .search select {
      width: 100%;
      padding: 10px 12px;
      border-radius: 10px;
      border: 1px solid #263363;
      background: #0d1430;
      color: var(--ink);
      font-size: 0.9rem;
    }
    .search input:focus, .search select:focus {
      border-color: var(--accent-light);
      outline: none;
      box-shadow: 0 0 0 3px rgba(58, 134, 255, 0.1);
    }
    .search-actions {
      display: flex;
      gap: 0.8rem;
      flex-wrap: wrap;
    }
    .search button {
      padding: 12px 20px;
      border: none;
      border-radius: 12px;
      font-weight: 600;
      cursor: pointer;
      transition: all 0.25s;
      font-size: 0.9rem;
    }
    .btn-primary {
      background: var(--accent);
      color: white;
    }
    .btn-primary:hover {
      background: var(--accent-light);
      transform: translateY(-2px);
      box-shadow: 0 4px 12px rgba(58, 134, 255, 0.3);
    }
    .btn-secondary {
      background: rgba(79, 124, 255, 0.15);
      color: var(--accent-light);
      border: 1px solid rgba(79, 124, 255, 0.3);
    }
    .btn-secondary:hover {
      background: rgba(79, 124, 255, 0.25);
    }

    /* Toolbar */
    .toolbar {
      display: flex;
      justify-content: space-between;
      align-items: center;
      margin-bottom: 1.5rem;
      flex-wrap: wrap;
      gap: 1rem;
    }
    .toolbar-left {
      display: flex;
      gap: 0.8rem;
      align-items: center;
    }
    .view-toggle {
      display: flex;
      background: rgba(17, 26, 56, 0.7);
      border: 1px solid rgba(79, 124, 255, 0.2);
      border-radius: 10px;
      overflow: hidden;
    }
    .view-toggle button {
      padding: 8px 16px;
      background: transparent;
      border: none;
      color: var(--muted);
      cursor: pointer;
      transition: all 0.2s;
    }
    .view-toggle button.active {
      background: var(--accent);
      color: white;
    }
    .view-toggle button:hover:not(.active) {
      background: rgba(79, 124, 255, 0.15);
    }
    .sort-select {
      padding: 8px 12px;
      border-radius: 10px;
      border: 1px solid #263363;
      background: #0d1430;
      color: var(--ink);
      font-size: 0.9rem;
    }

    /* Grid View */
    .grid {
      display: grid;
      gap: 1.2rem;
      grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
    }

    /* List View */
    .list {
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }
    .list .card {
      display: flex;
      flex-direction: row;
    }
    .list .img-container {
      width: 250px;
      flex-shrink: 0;
    }
    .list .img {
      width: 100%;
      height: 100%;
    }
    .list .pad {
      flex: 1;
      display: flex;
      flex-direction: column;
      justify-content: space-between;
    }

    /* Cards */
    .card {
      background: var(--card);
      border-radius: 18px;
      overflow: hidden;
      box-shadow: 0 6px 14px rgba(0, 0, 0, 0.25);
      transition: transform 0.2s ease, box-shadow 0.3s ease;
      position: relative;
      cursor: pointer;
    }
    .card:hover {
      transform: translateY(-5px);
      box-shadow: 0 10px 22px rgba(0, 0, 0, 0.4);
    }
    .img-container {
      position: relative;
    }
    .img {
      aspect-ratio: 4 / 3;
      width: 100%;
      object-fit: cover;
      background: #0d1733;
    }
    .fav-btn {
      position: absolute;
      top: 10px;
      right: 10px;
      background: rgba(0, 0, 0, 0.6);
      border: none;
      border-radius: 50%;
      width: 36px;
      height: 36px;
      display: grid;
      place-items: center;
      cursor: pointer;
      transition: all 0.2s;
      z-index: 10;
    }
    .fav-btn:hover {
      background: rgba(0, 0, 0, 0.8);
      transform: scale(1.1);
    }
    .fav-btn.active {
      color: #ff006e;
    }
    .pad {
      padding: 1rem 1.2rem 1.2rem;
    }
    .price {
      font-weight: 800;
      font-size: 1.3rem;
      color: var(--accent-light);
    }
    .addr {
      margin-top: 6px;
      font-size: 0.9rem;
      color: #c0cae0;
      line-height: 1.4;
    }
    .meta {
      margin-top: 10px;
      display: flex;
      flex-wrap: wrap;
      gap: 10px;
      font-size: 0.85rem;
      color: #b3bee3;
    }
    .meta span {
      background: rgba(50, 70, 120, 0.25);
      border-radius: 8px;
      padding: 4px 10px;
      display: inline-flex;
      align-items: center;
      gap: 5px;
    }

    /* Modal */
    .modal {
      display: none;
      position: fixed;
      inset: 0;
      background: rgba(0, 0, 0, 0.85);
      z-index: 1000;
      padding: 2rem;
      overflow-y: auto;
    }
    .modal.active {
      display: flex;
      justify-content: center;
      align-items: flex-start;
    }
    .modal-content {
      background: var(--card);
      border-radius: 18px;
      max-width: 900px;
      width: 100%;
      margin: 2rem auto;
      box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
      position: relative;
    }
    .modal-close {
      position: absolute;
      top: 1rem;
      right: 1rem;
      background: rgba(0, 0, 0, 0.6);
      border: none;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      color: white;
      font-size: 1.5rem;
      cursor: pointer;
      z-index: 10;
    }
    .modal-close:hover {
      background: rgba(0, 0, 0, 0.8);
    }
    .gallery {
      position: relative;
      height: 400px;
      background: #0d1733;
      border-radius: 18px 18px 0 0;
      overflow: hidden;
    }
    .gallery img {
      width: 100%;
      height: 100%;
      object-fit: contain;
    }
    .gallery-nav {
      position: absolute;
      top: 50%;
      transform: translateY(-50%);
      width: 100%;
      display: flex;
      justify-content: space-between;
      padding: 0 1rem;
      pointer-events: none;
    }
    .gallery-nav button {
      background: rgba(0, 0, 0, 0.6);
      border: none;
      border-radius: 50%;
      width: 40px;
      height: 40px;
      color: white;
      cursor: pointer;
      pointer-events: all;
    }
    .gallery-nav button:hover {
      background: rgba(0, 0, 0, 0.8);
    }
    .gallery-dots {
      position: absolute;
      bottom: 1rem;
      left: 50%;
      transform: translateX(-50%);
      display: flex;
      gap: 6px;
    }
    .gallery-dot {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      background: rgba(255, 255, 255, 0.4);
      cursor: pointer;
    }
    .gallery-dot.active {
      background: white;
    }
    .modal-body {
      padding: 2rem;
    }
    
    /* Map Container */
    .map-container {
      margin: 1.5rem 0;
      border-radius: 12px;
      overflow: hidden;
      border: 1px solid rgba(79, 124, 255, 0.2);
      background: rgba(17, 26, 56, 0.5);
    }
    .map-tabs {
      display: flex;
      background: rgba(11, 17, 35, 0.8);
      border-bottom: 1px solid rgba(79, 124, 255, 0.2);
    }
    .map-tab {
      flex: 1;
      padding: 12px 16px;
      border: none;
      background: transparent;
      color: var(--muted);
      font-size: 0.9rem;
      font-weight: 500;
      cursor: pointer;
      transition: all 0.25s;
      border-bottom: 2px solid transparent;
    }
    .map-tab:hover {
      background: rgba(58, 134, 255, 0.1);
      color: var(--ink);
    }
    .map-tab.active {
      color: var(--accent-light);
      border-bottom-color: var(--accent);
    }
    .property-map {
      width: 100%;
      height: 500px;
      background: #0d1733;
      border-radius: 0 0 12px 12px;
    }

    /* Pagination */
    .recommendations-section {
      margin-top: 3rem;
      padding: 2rem;
      background: rgba(17, 26, 56, 0.5);
      border-radius: 18px;
      border: 1px solid rgba(79, 124, 255, 0.2);
    }
    .recommendations-title {
      font-size: 1.5rem;
      margin: 0 0 1.5rem;
      color: var(--ink);
      display: flex;
      align-items: center;
      gap: 0.5rem;
    }
    .sparkle {
      font-size: 1.8rem;
      animation: sparkle 2s ease-in-out infinite;
    }
    @keyframes sparkle {
      0%, 100% { opacity: 1; transform: scale(1); }
      50% { opacity: 0.7; transform: scale(1.1); }
    }
    .recommendations-grid {
      display: grid;
      grid-template-columns: repeat(auto-fill, minmax(240px, 1fr));
      gap: 1rem;
    }
    .rec-card {
      background: rgba(11, 17, 35, 0.8);
      border-radius: 12px;
      overflow: hidden;
      cursor: pointer;
      transition: all 0.3s;
      border: 1px solid rgba(79, 124, 255, 0.15);
    }
    .rec-card:hover {
      transform: translateY(-4px);
      box-shadow: 0 8px 24px rgba(58, 134, 255, 0.3);
      border-color: var(--accent);
    }
    .rec-image {
      height: 160px;
      background-size: cover;
      background-position: center;
      position: relative;
    }
    .rec-image::after {
      content: 'View Details';
      position: absolute;
      bottom: 0;
      left: 0;
      right: 0;
      background: linear-gradient(to top, rgba(0,0,0,0.8), transparent);
      color: white;
      padding: 1rem 0.75rem 0.5rem;
      font-size: 0.75rem;
      font-weight: 600;
      opacity: 0;
      transition: opacity 0.3s;
    }
    .rec-card:hover .rec-image::after {
      opacity: 1;
    }
    .rec-content {
      padding: 1rem;
    }
    .rec-price {
      font-size: 1.3rem;
      font-weight: 700;
      color: var(--accent-light);
      margin-bottom: 0.5rem;
    }
    .rec-address {
      font-size: 0.9rem;
      color: var(--ink);
      margin-bottom: 0.25rem;
    }
    .rec-city {
      font-size: 0.8rem;
      color: var(--muted);
      margin-bottom: 0.5rem;
    }
    .rec-details {
      font-size: 0.75rem;
      color: var(--muted);
      padding-top: 0.5rem;
      border-top: 1px solid rgba(79, 124, 255, 0.15);
    }

    .pagination {
      display: flex;
      gap: 0.6rem;
      justify-content: center;
      margin: 2rem 0;
      flex-wrap: wrap;
    }
    .pagination a, .pagination span {
      padding: 0.6rem 1rem;
      border-radius: 10px;
      border: 1px solid rgba(79,124,255,0.3);
      color: var(--ink);
      text-decoration: none;
      font-size: 0.9rem;
      transition: all 0.2s;
    }
    .pagination a:hover {
      background: rgba(79,124,255,0.15);
      border-color: var(--accent-light);
    }
    .pagination .active {
      background: var(--accent);
      color: #fff;
      border-color: var(--accent);
    }

    footer {
      text-align: center;
      font-size: 0.85rem;
      color: var(--muted);
      padding: 2rem 0;
      border-top: 1px solid rgba(255,255,255,0.05);
      margin-top: 3rem;
    }
    footer strong {
      color: var(--accent-light);
    }

    /* Keyboard Shortcuts Help */
    .shortcuts-hint {
      position: fixed;
      bottom: 1rem;
      right: 1rem;
      background: rgba(17, 26, 56, 0.9);
      border: 1px solid rgba(79, 124, 255, 0.3);
      border-radius: 10px;
      padding: 0.5rem 1rem;
      font-size: 0.75rem;
      color: var(--muted);
      z-index: 50;
    }

    @media (max-width: 768px) {
      .list .card {
        flex-direction: column;
      }
      .list .img-container {
        width: 100%;
        height: 200px;
      }
      .shortcuts-hint {
        display: none;
      }
    }

    /* ElevenLabs Widget - Positioning handled via ElevenLabs dashboard */
    elevenlabs-convai {
      display: block !important;
      visibility: visible !important;
      opacity: 1 !important;
      z-index: 9999 !important;
    }
  </style>
</head>
<body>
  <!-- Hero Section with Background Image -->
  <section class="hero">
    <div class="hero-inner">
      <h1>Find Your Dream Home in California</h1>
      
      <!-- Natural Language Search -->
      <div class="smart-search-container">
        <div class="search-input-wrapper">
          <svg class="search-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
            <circle cx="11" cy="11" r="8"/>
            <path d="m21 21-4.35-4.35"/>
          </svg>
          <input 
            type="text" 
            id="smart-search-input" 
            class="smart-search-input" 
            placeholder="Search by city or any property detail (address, zip, price, beds, etc.)"
            autocomplete="off"
            value="<?= htmlspecialchars($search) ?>"
          />
          <button type="button" id="clear-search" class="clear-btn" style="display:none;">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="18" y1="6" x2="6" y2="18"/>
              <line x1="6" y1="6" x2="18" y2="18"/>
            </svg>
          </button>
          <div id="autocomplete-dropdown" class="autocomplete-dropdown" style="display:none;"></div>
        </div>
        
        <div class="search-actions">
          <button type="button" id="toggle-filters" class="toggle-filters-btn">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
              <line x1="4" y1="21" x2="4" y2="14"/>
              <line x1="4" y1="10" x2="4" y2="3"/>
              <line x1="12" y1="21" x2="12" y2="12"/>
              <line x1="12" y1="8" x2="12" y2="3"/>
              <line x1="20" y1="21" x2="20" y2="16"/>
              <line x1="20" y1="12" x2="20" y2="3"/>
              <line x1="1" y1="14" x2="7" y2="14"/>
              <line x1="9" y1="8" x2="15" y2="8"/>
              <line x1="17" y1="16" x2="23" y2="16"/>
            </svg>
            <span id="filter-toggle-text">Advanced Search</span>
          </button>
        </div>
      </div>
    </div>
  </section>

  <!-- Sticky Header (appears on scroll) -->
  <header class="sticky-nav" id="sticky-header">
    <div class="sticky-nav-content">
      <a href="?" class="home-button" title="Home - Clear all filters">
        <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
          <path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/>
          <polyline points="9 22 9 12 15 12 15 22"/>
        </svg>
        <span>Home</span>
      </a>
      <h2>California Property Finder</h2>
    </div>
  </header>

  <div class="wrap">
    <!-- Statistics Dashboard -->
    <?php 
    // Ensure $total is defined
    if (!isset($total)) {
      $total = 0;
    }
    if ($total > 0): ?>
    <div class="stats">
      <div class="stat-card">
        <div class="stat-value"><?= number_format($total) ?></div>
        <div class="stat-label">Total Properties</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= money($stats['avg_price']) ?></div>
        <div class="stat-label">Average Price</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= money($stats['min_price']) ?></div>
        <div class="stat-label">Lowest Price</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= money($stats['max_price']) ?></div>
        <div class="stat-label">Highest Price</div>
      </div>
      <div class="stat-card">
        <div class="stat-value"><?= number_format($stats['avg_sqft']) ?> ft²</div>
        <div class="stat-label">Average Sq Ft</div>
      </div>
    </div>
    <?php endif; ?>

    <!-- Advanced Filters (Collapsible) -->
    <div id="filters-panel" class="filters-panel" style="display:none;">
    <form class="search" method="get" id="search-form">
      <!-- Hidden field for general search -->
      <input type="hidden" id="search" name="search" value="<?= htmlspecialchars($search) ?>">
      <div class="search-grid">
        <div>
          <label for="city">City</label>
          <input type="text" id="city" name="city" placeholder="e.g., San Jose" value="<?= htmlspecialchars($city) ?>" list="city-suggestions" autocomplete="off">
          <datalist id="city-suggestions">
            <option value="Los Angeles">
            <option value="San Diego">
            <option value="San Jose">
            <option value="San Francisco">
            <option value="Fresno">
            <option value="Sacramento">
            <option value="Long Beach">
            <option value="Oakland">
            <option value="Bakersfield">
            <option value="Anaheim">
            <option value="Santa Ana">
            <option value="Riverside">
            <option value="Stockton">
            <option value="Irvine">
            <option value="Chula Vista">
            <option value="Fremont">
            <option value="San Bernardino">
            <option value="Modesto">
            <option value="Fontana">
            <option value="Santa Clarita">
          </datalist>
        </div>
        <div>
          <label for="zip">ZIP Code</label>
          <input type="text" id="zip" name="zip" placeholder="e.g., 95112" value="<?= htmlspecialchars($zip) ?>">
        </div>
        <div>
          <label for="price_min">Min Price</label>
          <input type="number" id="price_min" name="price_min" min="0" step="10000" placeholder="200,000" value="<?= htmlspecialchars($price_min) ?>">
        </div>
        <div>
          <label for="price_max">Max Price</label>
          <input type="number" id="price_max" name="price_max" min="0" step="10000" placeholder="1,500,000" value="<?= htmlspecialchars($price_max) ?>">
        </div>
        <div>
          <label for="beds">Min Bedrooms</label>
          <input type="number" id="beds" name="beds" min="0" step="1" placeholder="3" value="<?= htmlspecialchars($beds) ?>">
        </div>
        <div>
          <label for="baths">Min Bathrooms</label>
          <input type="number" id="baths" name="baths" min="0" step="0.5" placeholder="2" value="<?= htmlspecialchars($baths) ?>">
        </div>
        <div>
          <label for="sqft_min">Min Sq Ft</label>
          <input type="number" id="sqft_min" name="sqft_min" min="0" step="100" placeholder="1,000" value="<?= htmlspecialchars($sqft_min) ?>">
        </div>
        <div>
          <label for="sqft_max">Max Sq Ft</label>
          <input type="number" id="sqft_max" name="sqft_max" min="0" step="100" placeholder="5,000" value="<?= htmlspecialchars($sqft_max) ?>">
        </div>
      </div>
      <div class="search-actions">
        <button type="submit" class="btn-primary">Search Properties</button>
        <button type="button" class="btn-secondary" onclick="window.location.href='?'">Clear Filters</button>
        <?php if ($total > 0): ?>
        <button type="button" class="btn-secondary" onclick="window.location.href='?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>'">Export CSV</button>
        <?php endif; ?>
      </div>
    </form>
    </div>

    <?php if ($total === 0): ?>
      <div style="text-align:center;padding:3rem 1rem;background:rgba(17,26,56,0.5);border-radius:18px;border:1px solid rgba(79,124,255,0.2)">
        <p style="font-size:1.2rem;color:#cdd5f3;margin:0">No properties match your criteria</p>
        <p style="color:var(--muted);margin-top:0.5rem">Try adjusting your filters or clearing them to see more results</p>
      </div>
    <?php else: ?>
      <!-- Toolbar -->
      <div class="toolbar">
        <div class="toolbar-left">
          <div class="view-toggle">
            <button type="button" class="<?= $view === 'grid' ? 'active' : '' ?>" onclick="window.location.href='?<?= http_build_query(array_merge($_GET, ['view' => 'grid', 'page' => 1])) ?>'">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" style="vertical-align:middle;margin-right:4px">
                <rect x="3" y="3" width="7" height="7"/>
                <rect x="14" y="3" width="7" height="7"/>
                <rect x="3" y="14" width="7" height="7"/>
                <rect x="14" y="14" width="7" height="7"/>
              </svg>
              Grid
            </button>
            <button type="button" class="<?= $view === 'list' ? 'active' : '' ?>" onclick="window.location.href='?<?= http_build_query(array_merge($_GET, ['view' => 'list', 'page' => 1])) ?>'">
              <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px">
                <line x1="8" y1="6" x2="21" y2="6"/>
                <line x1="8" y1="12" x2="21" y2="12"/>
                <line x1="8" y1="18" x2="21" y2="18"/>
                <line x1="3" y1="6" x2="3.01" y2="6"/>
                <line x1="3" y1="12" x2="3.01" y2="12"/>
                <line x1="3" y1="18" x2="3.01" y2="18"/>
              </svg>
              List
            </button>
            <?php if (count($_SESSION['favorites']) > 0): ?>
              <?php if ($show_favorites): ?>
                <button type="button" class="active" onclick="window.location.href='?<?= http_build_query(array_diff_key($_GET, ['show_favorites'=>'', 'page'=>''])) ?>'" 
                        style="margin-left:0.5rem;background:rgba(79,124,255,0.2);border-color:var(--accent-light)">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                  </svg>
                  Viewing Favorites
                </button>
                <button type="button" onclick="clearAllFavorites()" 
                        style="margin-left:0.5rem;background:rgba(255,77,77,0.2);border-color:rgba(255,77,77,0.5);color:#ff6b6b"
                        title="Clear all favorites">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px">
                    <path d="M3 6h18M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>
                  </svg>
                  Clear All
                </button>
              <?php else: ?>
                <button type="button" onclick="window.location.href='?<?= http_build_query(array_merge(array_diff_key($_GET, ['page'=>'']), ['show_favorites' => '1'])) ?>'" 
                        style="margin-left:0.5rem">
                  <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:4px">
                    <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                  </svg>
                  View Favorites (<?= count($_SESSION['favorites']) ?>)
                </button>
              <?php endif; ?>
            <?php endif; ?>
          </div>
          <select class="sort-select" onchange="window.location.href='?<?= http_build_query(array_diff_key($_GET, ['sort'=>''])) ?>&sort=' + this.value + '&page=1'">
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: High to Low</option>
            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: Low to High</option>
            <option value="beds_desc" <?= $sort === 'beds_desc' ? 'selected' : '' ?>>Beds: Most to Least</option>
            <option value="beds_asc" <?= $sort === 'beds_asc' ? 'selected' : '' ?>>Beds: Least to Most</option>
            <option value="sqft_desc" <?= $sort === 'sqft_desc' ? 'selected' : '' ?>>Sq Ft: Largest First</option>
            <option value="sqft_asc" <?= $sort === 'sqft_asc' ? 'selected' : '' ?>>Sq Ft: Smallest First</option>
          </select>
        </div>
        <p style="margin:0;color:#bfc8eb;">
          <strong><?= min($per_page, $total - $offset) ?></strong> of <strong><?= number_format($total) ?></strong> properties
          | <strong class="favorites-count"><?= count($_SESSION['favorites']) ?></strong> favorites
          <?php if (count($_SESSION['favorites']) > 0): ?>
            <?php if ($show_favorites): ?>
              | <a href="?<?= http_build_query(array_diff_key($_GET, ['show_favorites'=>'', 'page'=>''])) ?>" 
                    style="color:var(--accent-light);text-decoration:underline;cursor:pointer;margin-left:0.5rem">
                  Show All Properties
                </a>
            <?php else: ?>
              | <a href="?<?= http_build_query(array_merge(array_diff_key($_GET, ['page'=>'']), ['show_favorites' => '1'])) ?>" 
                    style="color:var(--accent-light);text-decoration:underline;cursor:pointer;margin-left:0.5rem">
                  View Favorites (<?= count($_SESSION['favorites']) ?>)
                </a>
            <?php endif; ?>
          <?php endif; ?>
        </p>
      </div>

      <?php if ($show_favorites): ?>
        <div style="background:rgba(79,124,255,0.15);border:1px solid rgba(79,124,255,0.3);border-radius:12px;padding:1rem;margin-bottom:2rem;text-align:center">
          <p style="margin:0;color:var(--accent-light);font-size:1.1rem">
            <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor" stroke="currentColor" stroke-width="2" style="vertical-align:middle;margin-right:8px">
              <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
            </svg>
            Showing <strong><?= count($_SESSION['favorites']) ?></strong> favorite<?= count($_SESSION['favorites']) !== 1 ? 's' : '' ?>
          </p>
        </div>
      <?php endif; ?>

      <!-- Property Listings -->
      <div class="<?= $view === 'list' ? 'list' : 'grid' ?>">
        <?php foreach ($rows as $r):
          $listing_id = $r['L_ListingID'];
          $img = first_photo($r['L_Photos'] ?? '') ?: '';
          $all_imgs = all_photos($r['L_Photos'] ?? '');
          $price = money($r['L_SystemPrice'] ?? 0);
          $addr = htmlspecialchars(trim($r['L_Address'] ?? ''));
          $city_name = htmlspecialchars(trim($r['L_City'] ?? ''));
          $zip_code = htmlspecialchars(trim($r['L_Zip'] ?? ''));
          $full_addr = "$addr, $city_name, CA $zip_code";
          $beds = $r['Beds'] !== null ? (int)$r['Beds'] : null;
          $baths = $r['Baths'] !== null ? (float)$r['Baths'] : null;
          $sqft = $r['SqFt'] !== null ? (int)$r['SqFt'] : null;
          // Normalize listing ID and favorites for consistent comparison
          $normalized_listing_id = (string)trim($listing_id);
          $normalized_favorites = array_map(function($id) {
            return (string)trim($id);
          }, $_SESSION['favorites']);
          $is_fav = in_array($normalized_listing_id, $normalized_favorites, true);
          $price_per_sqft = ($sqft && $r['L_SystemPrice']) ? money($r['L_SystemPrice'] / $sqft) . '/ft²' : null;
        ?>
          <article class="card" onclick="openModal('<?= htmlspecialchars($normalized_listing_id) ?>')">
            <div class="img-container">
              <?php if ($img): ?>
                <img class="img" src="<?= htmlspecialchars($img) ?>" alt="Property at <?= $full_addr ?>" loading="lazy" />
              <?php else: ?>
                <div class="img" style="display:grid;place-items:center;color:#7e8bbd;font-size:0.9rem">
                  <div>No Photo Available</div>
                </div>
              <?php endif; ?>
              <button class="fav-btn <?= $is_fav ? 'active' : '' ?>" 
                      data-listing-id="<?= htmlspecialchars($normalized_listing_id) ?>"
                      onclick="event.stopPropagation(); toggleFavorite('<?= htmlspecialchars($normalized_listing_id) ?>', this, event)"
                      title="<?= $is_fav ? 'Remove from favorites' : 'Add to favorites' ?>">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="<?= $is_fav ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2">
                  <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                </svg>
              </button>
            </div>
            <div class="pad">
              <div>
                <div class="price"><?= htmlspecialchars($price) ?></div>
                <?php if ($price_per_sqft): ?>
                  <div style="font-size:0.75rem;color:var(--muted);margin-top:2px"><?= htmlspecialchars($price_per_sqft) ?></div>
                <?php endif; ?>
                <div class="addr"><?= $full_addr ?></div>
              </div>
              <div class="meta">
                <span><?= $beds !== null ? (int)$beds . ' beds' : '— beds' ?></span>
                <span><?= $baths !== null ? rtrim(rtrim(number_format($baths,1), '0'),'.') . ' baths' : '— baths' ?></span>
                <span><?= $sqft ? number_format($sqft) . ' sqft' : '— sqft' ?></span>
              </div>
            </div>
          </article>

          <!-- Modal for this property -->
          <div id="modal-<?= htmlspecialchars($normalized_listing_id) ?>" class="modal" onclick="if(event.target === this) closeModal('<?= htmlspecialchars($normalized_listing_id) ?>')">
            <div class="modal-content">
              <button class="modal-close" onclick="closeModal('<?= htmlspecialchars($normalized_listing_id) ?>')">&times;</button>
              
              <?php if (count($all_imgs) > 0): ?>
              <div class="gallery">
                <?php foreach ($all_imgs as $idx => $photo_url): ?>
                  <img id="gallery-img-<?= htmlspecialchars($listing_id) ?>-<?= $idx ?>" 
                       src="<?= htmlspecialchars($photo_url) ?>" 
                       alt="Photo <?= $idx + 1 ?>"
                       style="display: <?= $idx === 0 ? 'block' : 'none' ?>">
                <?php endforeach; ?>
                
                <?php if (count($all_imgs) > 1): ?>
                <div class="gallery-nav">
                  <button onclick="event.stopPropagation(); prevImage('<?= htmlspecialchars($normalized_listing_id) ?>', <?= count($all_imgs) ?>)">‹</button>
                  <button onclick="event.stopPropagation(); nextImage('<?= htmlspecialchars($normalized_listing_id) ?>', <?= count($all_imgs) ?>)">›</button>
                </div>
                <div class="gallery-dots">
                  <?php for ($i = 0; $i < count($all_imgs); $i++): ?>
                    <div class="gallery-dot <?= $i === 0 ? 'active' : '' ?>" 
                         onclick="event.stopPropagation(); showImage('<?= htmlspecialchars($normalized_listing_id) ?>', <?= $i ?>, <?= count($all_imgs) ?>)"></div>
                  <?php endfor; ?>
                </div>
                <?php endif; ?>
              </div>
              <?php endif; ?>
              
              <div class="modal-body">
                <h2 style="margin:0 0 1rem;color:var(--accent-light);font-size:1.8rem"><?= htmlspecialchars($price) ?></h2>
                <?php if ($price_per_sqft): ?>
                  <p style="margin:-0.5rem 0 1rem;color:var(--muted);font-size:0.9rem"><?= htmlspecialchars($price_per_sqft) ?></p>
                <?php endif; ?>
                
                <p style="font-size:1.1rem;color:#c0cae0;margin:0 0 1.5rem"><?= $full_addr ?></p>
                
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:1rem;margin-bottom:1.5rem">
                  <div style="background:rgba(50,70,120,0.2);padding:1rem;border-radius:12px;text-align:center">
                    <div style="font-size:1.5rem;font-weight:700;color:var(--accent-light);margin:0.5rem 0"><?= $beds !== null ? $beds : '—' ?></div>
                    <div style="font-size:0.8rem;color:var(--muted)">Bedrooms</div>
                  </div>
                  <div style="background:rgba(50,70,120,0.2);padding:1rem;border-radius:12px;text-align:center">
                    <div style="font-size:1.5rem;font-weight:700;color:var(--accent-light);margin:0.5rem 0"><?= $baths !== null ? rtrim(rtrim(number_format($baths,1), '0'),'.') : '—' ?></div>
                    <div style="font-size:0.8rem;color:var(--muted)">Bathrooms</div>
                  </div>
                  <div style="background:rgba(50,70,120,0.2);padding:1rem;border-radius:12px;text-align:center">
                    <div style="font-size:1.5rem;font-weight:700;color:var(--accent-light);margin:0.5rem 0"><?= $sqft ? number_format($sqft) : '—' ?></div>
                    <div style="font-size:0.8rem;color:var(--muted)">Square Feet</div>
                  </div>
                </div>
                
                <!-- Google Maps Section -->
                <div class="map-container">
                  <div class="map-tabs">
                    <button class="map-tab active" onclick="switchMapType(event, '<?= htmlspecialchars($normalized_listing_id) ?>', 'roadmap')">Map</button>
                    <button class="map-tab" onclick="switchMapType(event, '<?= htmlspecialchars($normalized_listing_id) ?>', 'satellite')">Satellite</button>
                    <button class="map-tab" onclick="switchMapType(event, '<?= htmlspecialchars($normalized_listing_id) ?>', 'hybrid')">Hybrid</button>
                  </div>
                  <div id="map-<?= htmlspecialchars($normalized_listing_id) ?>" 
                       class="property-map" 
                       data-address="<?= htmlspecialchars($full_addr) ?>"></div>
                </div>
                
                <div style="background:rgba(50,70,120,0.15);padding:1rem;border-radius:12px;border:1px solid rgba(79,124,255,0.2)">
                  <p style="margin:0;color:var(--muted);font-size:0.85rem"><strong>Listing ID:</strong> <?= htmlspecialchars($listing_id) ?></p>
                  <p style="margin:0.5rem 0 0;color:var(--muted);font-size:0.85rem"><strong>Photos:</strong> <?= count($all_imgs) ?> available</p>
                </div>
              </div>
            </div>
          </div>
        <?php endforeach; ?>
      </div>

      <!-- Pagination -->
      <nav class="pagination">
        <?php
          $window = 2;
          $start = max(1, $page - $window);
          $end   = min($total_pages, $page + $window);
          $qs = $_GET;
          $link = function($p, $label=null, $active=false) use ($qs) {
            $qs['page'] = $p;
            $url = '?' . http_build_query($qs);
            $label = $label ?? (string)$p;
            if ($active) return '<span class="active">' . htmlspecialchars($label) . '</span>';
            return '<a href="' . htmlspecialchars($url) . '">' . htmlspecialchars($label) . '</a>';
          };
          
          if ($page > 1) echo $link($page-1, '« Previous');
          if ($start > 1) {
            echo $link(1);
            if ($start > 2) echo '<span style="border:none">...</span>';
          }
          for ($p=$start; $p <= $end; $p++) echo $link($p, (string)$p, $p === $page);
          if ($end < $total_pages) {
            if ($end < $total_pages - 1) echo '<span style="border:none">...</span>';
            echo $link($total_pages);
          }
          if ($page < $total_pages) echo $link($page+1, 'Next »');
        ?>
      </nav>

      <!-- Smart Recommendations -->
      <?php if (count($recommendations) > 0): ?>
        <div class="recommendations-section">
          <h3 class="recommendations-title">
            You Might Also Like
          </h3>
          <div class="recommendations-grid">
            <?php foreach ($recommendations as $rec): 
              $rec_photo = first_photo($rec['L_Photos']);
              $rec_id = htmlspecialchars($rec['L_ListingID']);
              $rec_all_imgs = all_photos($rec['L_Photos'] ?? '');
              $rec_addr = htmlspecialchars(trim($rec['L_Address'] ?? ''));
              $rec_city = htmlspecialchars(trim($rec['L_City'] ?? ''));
              $rec_zip = htmlspecialchars(trim($rec['L_Zip'] ?? ''));
              $rec_full_addr = "$rec_addr, $rec_city, CA $rec_zip";
              $rec_beds = $rec['Beds'] !== null ? (int)$rec['Beds'] : null;
              $rec_baths = $rec['Baths'] !== null ? (float)$rec['Baths'] : null;
              $rec_sqft = $rec['SqFt'] !== null ? (int)$rec['SqFt'] : null;
              $rec_price = money($rec['L_SystemPrice'] ?? 0);
              $rec_price_per_sqft = ($rec_sqft && $rec['L_SystemPrice']) ? money($rec['L_SystemPrice'] / $rec_sqft) . '/ft²' : null;
              // Normalize recommendation ID and favorites for consistent comparison
              $normalized_rec_id = (string)trim($rec_id);
              $normalized_favorites = array_map(function($id) {
                return (string)trim($id);
              }, $_SESSION['favorites']);
              $rec_is_fav = in_array($normalized_rec_id, $normalized_favorites, true);
            ?>
              <div class="rec-card" onclick="openModal('<?= $rec_id ?>')">
                <div class="rec-image" style="background-image: url('<?= $rec_photo ?: 'https://via.placeholder.com/300x200?text=No+Image' ?>')">
                  <button class="fav-btn <?= $rec_is_fav ? 'active' : '' ?>" 
                          data-listing-id="<?= htmlspecialchars($rec_id) ?>"
                          onclick="event.stopPropagation(); toggleFavorite('<?= htmlspecialchars($rec_id) ?>', this, event)"
                          title="<?= $rec_is_fav ? 'Remove from favorites' : 'Add to favorites' ?>">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="<?= $rec_is_fav ? 'currentColor' : 'none' ?>" stroke="currentColor" stroke-width="2">
                      <path d="M20.84 4.61a5.5 5.5 0 0 0-7.78 0L12 5.67l-1.06-1.06a5.5 5.5 0 0 0-7.78 7.78l1.06 1.06L12 21.23l7.78-7.78 1.06-1.06a5.5 5.5 0 0 0 0-7.78z"/>
                    </svg>
                  </button>
                </div>
                <div class="rec-content">
                  <div class="rec-price"><?= $rec_price ?></div>
                  <div class="rec-address"><?= $rec_addr ?></div>
                  <div class="rec-city"><?= $rec_city ?></div>
                  <div class="rec-details">
                    <?= $rec_beds !== null ? $rec_beds : '—' ?> bd • <?= $rec_baths !== null ? rtrim(rtrim(number_format($rec_baths,1), '0'),'.') : '—' ?> ba • <?= $rec_sqft ? number_format($rec_sqft) : '—' ?> sqft
                  </div>
                </div>
              </div>

              <!-- Modal for recommendation property -->
              <div id="modal-<?= $rec_id ?>" class="modal" onclick="if(event.target === this) closeModal('<?= $rec_id ?>')">
                <div class="modal-content">
                  <button class="modal-close" onclick="closeModal('<?= $rec_id ?>')">&times;</button>
                  
                  <?php if (count($rec_all_imgs) > 0): ?>
                  <div class="gallery">
                    <?php foreach ($rec_all_imgs as $idx => $photo_url): ?>
                      <img id="gallery-img-<?= $rec_id ?>-<?= $idx ?>" 
                           src="<?= htmlspecialchars($photo_url) ?>" 
                           alt="Photo <?= $idx + 1 ?>"
                           style="display: <?= $idx === 0 ? 'block' : 'none' ?>">
                    <?php endforeach; ?>
                    
                    <?php if (count($rec_all_imgs) > 1): ?>
                    <div class="gallery-nav">
                      <button onclick="event.stopPropagation(); prevImage('<?= $rec_id ?>', <?= count($rec_all_imgs) ?>)">‹</button>
                      <button onclick="event.stopPropagation(); nextImage('<?= $rec_id ?>', <?= count($rec_all_imgs) ?>)">›</button>
                    </div>
                    <div class="gallery-dots">
                      <?php for ($i = 0; $i < count($rec_all_imgs); $i++): ?>
                        <div class="gallery-dot <?= $i === 0 ? 'active' : '' ?>" 
                             onclick="event.stopPropagation(); showImage('<?= $rec_id ?>', <?= $i ?>, <?= count($rec_all_imgs) ?>)"></div>
                      <?php endfor; ?>
                    </div>
                    <?php endif; ?>
                  </div>
                  <?php endif; ?>
                  
                  <div class="modal-body">
                    <h2 style="margin:0 0 1rem;color:var(--accent-light);font-size:1.8rem"><?= $rec_price ?></h2>
                    <?php if ($rec_price_per_sqft): ?>
                      <p style="margin:-0.5rem 0 1rem;color:var(--muted);font-size:0.9rem"><?= htmlspecialchars($rec_price_per_sqft) ?></p>
                    <?php endif; ?>
                    
                    <p style="font-size:1.1rem;color:#c0cae0;margin:0 0 1.5rem"><?= $rec_full_addr ?></p>
                    
                    <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(120px,1fr));gap:1rem;margin-bottom:1.5rem">
                      <div style="background:rgba(50,70,120,0.2);padding:1rem;border-radius:12px;text-align:center">
                        <div style="font-size:1.5rem;font-weight:700;color:var(--accent-light);margin:0.5rem 0"><?= $rec_beds !== null ? $rec_beds : '—' ?></div>
                        <div style="font-size:0.8rem;color:var(--muted)">Bedrooms</div>
                      </div>
                      <div style="background:rgba(50,70,120,0.2);padding:1rem;border-radius:12px;text-align:center">
                        <div style="font-size:1.5rem;font-weight:700;color:var(--accent-light);margin:0.5rem 0"><?= $rec_baths !== null ? rtrim(rtrim(number_format($rec_baths,1), '0'),'.') : '—' ?></div>
                        <div style="font-size:0.8rem;color:var(--muted)">Bathrooms</div>
                      </div>
                      <div style="background:rgba(50,70,120,0.2);padding:1rem;border-radius:12px;text-align:center">
                        <div style="font-size:1.5rem;font-weight:700;color:var(--accent-light);margin:0.5rem 0"><?= $rec_sqft ? number_format($rec_sqft) : '—' ?></div>
                        <div style="font-size:0.8rem;color:var(--muted)">Square Feet</div>
                      </div>
                    </div>
                    
                    <!-- Google Maps Section -->
                    <div class="map-container">
                      <div class="map-tabs">
                        <button class="map-tab active" onclick="switchMapType(event, '<?= $rec_id ?>', 'roadmap')">Map</button>
                        <button class="map-tab" onclick="switchMapType(event, '<?= $rec_id ?>', 'satellite')">Satellite</button>
                        <button class="map-tab" onclick="switchMapType(event, '<?= $rec_id ?>', 'hybrid')">Hybrid</button>
                      </div>
                      <div id="map-<?= $rec_id ?>" 
                           class="property-map" 
                           data-address="<?= htmlspecialchars($rec_full_addr) ?>"></div>
                    </div>
                    
                    <div style="background:rgba(50,70,120,0.15);padding:1rem;border-radius:12px;border:1px solid rgba(79,124,255,0.2)">
                      <p style="margin:0;color:var(--muted);font-size:0.85rem"><strong>Listing ID:</strong> <?= $rec_id ?></p>
                      <p style="margin:0.5rem 0 0;color:var(--muted);font-size:0.85rem"><strong>Photos:</strong> <?= count($rec_all_imgs) ?> available</p>
                    </div>
                  </div>
                </div>
              </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endif; ?>
    <?php endif; ?>
  </div>

  <footer>
    <p>© <?= date('Y') ?> Developed by <strong>Akbar Aman</strong> — SD6 Team Lead, IDX Exchange Pro-bono Initiative</p>
    <p style="margin-top:0.5rem;font-size:0.75rem">• California Property Finder • </p>
  </footer>

  <div class="shortcuts-hint">
    Press <kbd>ESC</kbd> to close modals
  </div>

  <script>
    // ==========================================
    // GOOGLE MAPS INTEGRATION
    // ==========================================
    let propertyMaps = {};
    let geocoder = null;

    // Initialize geocoder when Google Maps loads
    function initGoogleMaps() {
      if (typeof google !== 'undefined' && google.maps) {
        geocoder = new google.maps.Geocoder();
      }
    }

    // Initialize map for a specific property
    function initPropertyMap(listingId, address, mapType = 'roadmap') {
      if (!geocoder) {
        initGoogleMaps();
      }

      const mapElement = document.getElementById('map-' + listingId);
      if (!mapElement) return;
      
      // Check if Google Maps is loaded
      if (typeof google === 'undefined' || !google.maps) {
        mapElement.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--muted);font-size:0.9rem;padding:2rem;">Loading Google Maps...<br><small>If this persists, please enable Maps JavaScript API in Google Cloud Console</small></div>';
        setTimeout(() => initPropertyMap(listingId, address, mapType), 500);
        return;
      }
      
      if (!geocoder) {
        geocoder = new google.maps.Geocoder();
      }

      // Show loading
      mapElement.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--muted);font-size:0.9rem;padding:2rem;">Finding location...</div>';

      // Geocode the address
      geocoder.geocode({ address: address + ', California' }, function(results, status) {
        if (status === 'OK' && results[0]) {
          const location = results[0].geometry.location;
          
          // Clear loading
          mapElement.innerHTML = '';
          
          // Create or update map
          if (!propertyMaps[listingId]) {
            propertyMaps[listingId] = new google.maps.Map(mapElement, {
              center: location,
              zoom: 15,
              mapTypeId: mapType,
              mapTypeControl: false,
              streetViewControl: true,
              fullscreenControl: true,
              zoomControl: true,
              styles: mapType === 'roadmap' ? [
                { elementType: 'geometry', stylers: [{ color: '#0d1733' }] },
                { elementType: 'labels.text.stroke', stylers: [{ color: '#0d1733' }] },
                { elementType: 'labels.text.fill', stylers: [{ color: '#6ca0ff' }] },
                { featureType: 'road', elementType: 'geometry', stylers: [{ color: '#1a2847' }] },
                { featureType: 'water', elementType: 'geometry', stylers: [{ color: '#0a1428' }] }
              ] : []
            });

            // Add marker
            new google.maps.Marker({
              position: location,
              map: propertyMaps[listingId],
              title: address,
              animation: google.maps.Animation.DROP
            });
            
            // Trigger resize after map is created
            setTimeout(() => {
              if (propertyMaps[listingId]) {
                google.maps.event.trigger(propertyMaps[listingId], 'resize');
                propertyMaps[listingId].setCenter(location);
              }
            }, 100);
          } else {
            propertyMaps[listingId].setMapTypeId(mapType);
            google.maps.event.trigger(propertyMaps[listingId], 'resize');
            propertyMaps[listingId].setCenter(location);
          }
        } else {
          console.error('Geocoding failed:', status);
          mapElement.innerHTML = '<div style="display:flex;align-items:center;justify-content:center;height:100%;color:var(--muted);font-size:0.9rem;padding:2rem;text-align:center;">Unable to load map for this location.<br><small>' + address + '</small></div>';
        }
      });
    }

    // Switch map type (Map/Satellite/Hybrid)
    function switchMapType(event, listingId, mapType) {
      event.stopPropagation();
      
      // Update active tab
      const tabs = event.target.closest('.map-tabs').querySelectorAll('.map-tab');
      tabs.forEach(tab => tab.classList.remove('active'));
      event.target.classList.add('active');

      // Update map type
      if (propertyMaps[listingId]) {
        propertyMaps[listingId].setMapTypeId(mapType);
      }
    }

    // ==========================================
    // MODAL MANAGEMENT
    // ==========================================
    function openModal(id) {
      const modal = document.getElementById('modal-' + id);
      modal.classList.add('active');
      document.body.style.overflow = 'hidden';
      
      // Initialize map when modal opens
      const mapElement = document.getElementById('map-' + id);
      if (mapElement && !propertyMaps[id]) {
        const address = mapElement.getAttribute('data-address');
        // Delay to ensure modal is fully rendered
        setTimeout(() => {
          initPropertyMap(id, address);
        }, 100);
      }
    }

    function closeModal(id) {
      document.getElementById('modal-' + id).classList.remove('active');
      document.body.style.overflow = '';
    }

    // Favorites Toggle Function (AJAX)
    // Track pending requests to prevent double-clicks
    const pendingFavoriteToggles = new Set();
    
    function toggleFavorite(listingId, buttonElement, event) {
      // Prevent default behavior if event is provided
      if (event) {
        event.preventDefault();
        event.stopPropagation();
      }
      
      // Normalize listing ID (trim whitespace, ensure string)
      listingId = String(listingId).trim();
      
      // Prevent double-clicks and rapid toggles
      if (pendingFavoriteToggles.has(listingId)) {
        console.log('Toggle already in progress for:', listingId);
        return;
      }
      
      // Get current state from the button
      const isActive = buttonElement.classList.contains('active');
      const svg = buttonElement.querySelector('svg');
      const path = svg ? svg.querySelector('path') : null;
      
      // Mark as pending
      pendingFavoriteToggles.add(listingId);
      
      // Optimistically update UI
      if (isActive) {
        buttonElement.classList.remove('active');
        if (path) path.setAttribute('fill', 'none');
        buttonElement.title = 'Add to favorites';
      } else {
        buttonElement.classList.add('active');
        if (path) path.setAttribute('fill', 'currentColor');
        buttonElement.title = 'Remove from favorites';
      }
      
      // Build URL with current query parameters
      const urlParams = new URLSearchParams(window.location.search);
      urlParams.set('toggle_fav', listingId);
      
      // Make AJAX request
      fetch('?' + urlParams.toString(), {
        method: 'GET',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(response => {
        if (!response.ok) {
          throw new Error('Network response was not ok');
        }
        return response.json();
      })
      .then(data => {
        console.log('Toggle favorite response:', data);
        // Remove from pending set
        pendingFavoriteToggles.delete(listingId);
        
        if (data.success) {
          // Update all heart buttons for this listing based on server response
          // Use normalized listing ID for selector - try multiple formats
          const normalizedId = String(listingId).trim();
          const allButtons = document.querySelectorAll(`.fav-btn[data-listing-id="${normalizedId}"], .fav-btn[data-listing-id="${CSS.escape(normalizedId)}"]`);
          
          console.log(`Found ${allButtons.length} buttons for listing ${normalizedId}`);
          
          allButtons.forEach(btn => {
            // Force update based on server response, not current state
            if (data.is_favorite) {
              btn.classList.add('active');
              const btnSvg = btn.querySelector('svg');
              const btnPath = btnSvg ? btnSvg.querySelector('path') : null;
              if (btnPath) btnPath.setAttribute('fill', 'currentColor');
              btn.title = 'Remove from favorites';
            } else {
              btn.classList.remove('active');
              const btnSvg = btn.querySelector('svg');
              const btnPath = btnSvg ? btnSvg.querySelector('path') : null;
              if (btnPath) btnPath.setAttribute('fill', 'none');
              btn.title = 'Add to favorites';
            }
          });
          
          // Update favorites count in header if it exists
          const favCountEl = document.querySelector('.favorites-count');
          if (favCountEl && data.favorites_count !== undefined) {
            favCountEl.textContent = data.favorites_count;
            // Show/hide based on count
            if (data.favorites_count > 0) {
              favCountEl.parentElement.style.display = '';
            } else {
              favCountEl.parentElement.style.display = 'none';
            }
          }
          
          // If we're on favorites view and count is 0, redirect
          if (data.favorites_count === 0 && window.location.search.includes('show_favorites=1')) {
            setTimeout(() => {
              const urlParams = new URLSearchParams(window.location.search);
              urlParams.delete('show_favorites');
              window.location.href = '?' + urlParams.toString();
            }, 500);
          }
        } else {
          // Revert optimistic update on error
          pendingFavoriteToggles.delete(listingId);
          if (isActive) {
            buttonElement.classList.add('active');
            if (path) path.setAttribute('fill', 'currentColor');
            buttonElement.title = 'Remove from favorites';
          } else {
            buttonElement.classList.remove('active');
            if (path) path.setAttribute('fill', 'none');
            buttonElement.title = 'Add to favorites';
          }
          console.error('Failed to toggle favorite', data);
        }
      })
      .catch(error => {
        console.error('Error toggling favorite:', error);
        // Remove from pending set on error
        pendingFavoriteToggles.delete(listingId);
        // Revert optimistic update on error
        if (isActive) {
          buttonElement.classList.add('active');
          if (path) path.setAttribute('fill', 'currentColor');
          buttonElement.title = 'Remove from favorites';
        } else {
          buttonElement.classList.remove('active');
          if (path) path.setAttribute('fill', 'none');
          buttonElement.title = 'Add to favorites';
        }
      });
    }
    
    // Clear All Favorites Function
    function clearAllFavorites() {
      if (!confirm('Are you sure you want to clear all favorites? This cannot be undone.')) {
        return;
      }
      
      const urlParams = new URLSearchParams(window.location.search);
      urlParams.set('clear_all_favorites', '1');
      
      fetch('?' + urlParams.toString(), {
        method: 'GET',
        headers: {
          'X-Requested-With': 'XMLHttpRequest'
        }
      })
      .then(response => response.json())
      .then(data => {
        if (data.success) {
          // Remove active class from all favorite buttons
          document.querySelectorAll('.fav-btn.active').forEach(btn => {
            btn.classList.remove('active');
            const btnSvg = btn.querySelector('svg');
            const btnPath = btnSvg ? btnSvg.querySelector('path') : null;
            if (btnPath) btnPath.setAttribute('fill', 'none');
            btn.title = 'Add to favorites';
          });
          
          // Update favorites count
          const favCountEl = document.querySelector('.favorites-count');
          if (favCountEl) {
            favCountEl.textContent = '0';
            favCountEl.parentElement.style.display = 'none';
          }
          
          // Redirect if on favorites view
          if (window.location.search.includes('show_favorites=1')) {
            const newParams = new URLSearchParams(window.location.search);
            newParams.delete('show_favorites');
            newParams.delete('clear_all_favorites');
            window.location.href = '?' + newParams.toString();
          } else {
            // Reload to update UI
            window.location.reload();
          }
        } else {
          alert('Failed to clear favorites. Please try again.');
        }
      })
      .catch(error => {
        console.error('Error clearing favorites:', error);
        alert('Failed to clear favorites. Please try again.');
      });
    }

    // Image Gallery Navigation
    let currentImageIndex = {};

    function showImage(listingId, index, total) {
      if (!currentImageIndex[listingId]) currentImageIndex[listingId] = 0;
      
      // Hide current image
      const currentImg = document.getElementById('gallery-img-' + listingId + '-' + currentImageIndex[listingId]);
      if (currentImg) currentImg.style.display = 'none';
      
      // Show new image
      const newImg = document.getElementById('gallery-img-' + listingId + '-' + index);
      if (newImg) newImg.style.display = 'block';
      
      // Update dots
      const modal = document.getElementById('modal-' + listingId);
      const dots = modal.querySelectorAll('.gallery-dot');
      dots.forEach((dot, i) => {
        dot.classList.toggle('active', i === index);
      });
      
      currentImageIndex[listingId] = index;
    }

    function nextImage(listingId, total) {
      if (!currentImageIndex[listingId]) currentImageIndex[listingId] = 0;
      const nextIndex = (currentImageIndex[listingId] + 1) % total;
      showImage(listingId, nextIndex, total);
    }

    function prevImage(listingId, total) {
      if (!currentImageIndex[listingId]) currentImageIndex[listingId] = 0;
      const prevIndex = (currentImageIndex[listingId] - 1 + total) % total;
      showImage(listingId, prevIndex, total);
    }

    // Keyboard Shortcuts
    document.addEventListener('keydown', function(e) {
      // ESC to close any open modal
      if (e.key === 'Escape') {
        const activeModal = document.querySelector('.modal.active');
        if (activeModal) {
          activeModal.classList.remove('active');
          document.body.style.overflow = '';
        }
      }
      
      // Arrow keys for gallery navigation in active modal
      const activeModal = document.querySelector('.modal.active');
      if (activeModal && activeModal.id.startsWith('modal-')) {
        const listingId = activeModal.id.replace('modal-', '');
        const gallery = activeModal.querySelector('.gallery');
        if (gallery) {
          const total = gallery.querySelectorAll('img').length;
          if (e.key === 'ArrowRight') {
            e.preventDefault();
            nextImage(listingId, total);
          } else if (e.key === 'ArrowLeft') {
            e.preventDefault();
            prevImage(listingId, total);
          }
        }
      }
    });

    // Smooth scroll to top on page change
    if (window.location.search.includes('page=')) {
      window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    // Initialize all gallery indices
    document.querySelectorAll('.modal').forEach(modal => {
      const listingId = modal.id.replace('modal-', '');
      currentImageIndex[listingId] = 0;
    });

    // ==========================================
    // SMART SEARCH WITH LIVE AUTOCOMPLETE
    // ==========================================
    // Wait for DOM to be ready
    document.addEventListener('DOMContentLoaded', function() {
      const smartInput = document.getElementById('smart-search-input');
      const autocompleteDropdown = document.getElementById('autocomplete-dropdown');
      const clearBtn = document.getElementById('clear-search');
      const toggleFiltersBtn = document.getElementById('toggle-filters');
      const filtersPanel = document.getElementById('filters-panel');
      const filterToggleText = document.getElementById('filter-toggle-text');
      const searchForm = document.getElementById('search-form');

      // Check if elements exist
      if (!smartInput || !autocompleteDropdown || !clearBtn || !toggleFiltersBtn || !filtersPanel || !filterToggleText || !searchForm) {
        console.error('Search elements not found:', {
          smartInput: !!smartInput,
          autocompleteDropdown: !!autocompleteDropdown,
          clearBtn: !!clearBtn,
          toggleFiltersBtn: !!toggleFiltersBtn,
          filtersPanel: !!filtersPanel,
          filterToggleText: !!filterToggleText,
          searchForm: !!searchForm
        });
        return;
      }

      let cityAutocompleteTimeout = null;
      let citySuggestions = [];
      let selectedCityIndex = -1;
      let currentAbortController = null;
      let isCityMode = false; // Track if we're in city autocomplete mode

      // Helper function to adjust hero padding based on dropdown visibility
      function adjustHeroPadding() {
        const heroSection = document.querySelector('.hero');
        if (!heroSection) return;
        
        if (autocompleteDropdown && autocompleteDropdown.style.display === 'block' && autocompleteDropdown.offsetHeight > 0) {
          // Dropdown is visible - add padding to push header down
          const dropdownHeight = autocompleteDropdown.offsetHeight;
          const minPadding = 3; // Original hero bottom padding in rem
          heroSection.style.paddingBottom = Math.max(minPadding * 16, dropdownHeight + 40) + 'px';
        } else {
          // Dropdown is hidden - restore original padding
          heroSection.style.paddingBottom = '';
        }
      }

      // Toggle filters panel
      toggleFiltersBtn.addEventListener('click', function() {
      const isHidden = filtersPanel.style.display === 'none';
      filtersPanel.style.display = isHidden ? 'block' : 'none';
      filterToggleText.textContent = isHidden ? 'Hide Filters' : 'Show Filters';
      toggleFiltersBtn.classList.toggle('active', isHidden);
    });

    // Clear search
    clearBtn.addEventListener('click', function() {
      smartInput.value = '';
      clearBtn.style.display = 'none';
      autocompleteDropdown.style.display = 'none';
      citySuggestions = [];
      selectedCityIndex = -1;
      isCityMode = false;
      if (currentAbortController) {
        currentAbortController.abort();
        currentAbortController = null;
      }
      adjustHeroPadding();
    });

    // Show/hide clear button and handle autocomplete
    smartInput.addEventListener('input', function() {
      const value = this.value.trim();
      clearBtn.style.display = value ? 'block' : 'none';
      
      // Cancel any pending requests
      if (currentAbortController) {
        currentAbortController.abort();
        currentAbortController = null;
      }
      
      // Clear previous timeouts
      clearTimeout(cityAutocompleteTimeout);
      
      // Reset selection
      selectedCityIndex = -1;
      
      if (value.length === 0) {
        autocompleteDropdown.style.display = 'none';
        citySuggestions = [];
        isCityMode = false;
        adjustHeroPadding();
        return;
      }
      
      // Show city suggestions - simple word-by-word
      if (value.length >= 2) {
        // Extract text parts (ignore numbers and keywords)
        const words = value.trim().split(/\s+/);
        const textParts = words.filter(word => {
          const lower = word.toLowerCase();
          return /^[a-z]+$/i.test(word) && 
                 !/^\d+$/.test(word) &&
                 !/^(bed|beds|bath|baths|br|ba|bedroom|bedrooms|bathroom|bathrooms)$/i.test(lower);
        });
        
        if (textParts.length > 0) {
          const cityQuery = textParts.join(' ');
          isCityMode = true;
          cityAutocompleteTimeout = setTimeout(() => {
            fetchCitySuggestions(cityQuery);
          }, 200);
        } else {
          isCityMode = false;
          autocompleteDropdown.style.display = 'none';
          citySuggestions = [];
          adjustHeroPadding();
        }
      } else {
        isCityMode = false;
        autocompleteDropdown.style.display = 'none';
        citySuggestions = [];
        adjustHeroPadding();
      }
    });

    // Handle keyboard navigation
    smartInput.addEventListener('keydown', function(e) {
      if (e.key === 'Enter') {
        e.preventDefault();
        const value = smartInput.value.trim();
        
        if (citySuggestions.length > 0) {
          // Select city from autocomplete (use first if none selected)
          const cityToSelect = selectedCityIndex >= 0 
            ? citySuggestions[selectedCityIndex].city 
            : citySuggestions[0].city;
          selectCity(cityToSelect);
        } else if (value.length >= 2) {
          // Perform general search across all fields
          performGeneralSearch(value);
        }
      } else if (e.key === 'Escape') {
        autocompleteDropdown.style.display = 'none';
        citySuggestions = [];
        selectedCityIndex = -1;
        adjustHeroPadding();
      } else if (e.key === 'ArrowDown') {
        e.preventDefault();
        if (citySuggestions.length > 0) {
          selectedCityIndex = Math.min(selectedCityIndex + 1, citySuggestions.length - 1);
          highlightCitySuggestion();
        }
      } else if (e.key === 'ArrowUp') {
        e.preventDefault();
        if (citySuggestions.length > 0) {
          selectedCityIndex = Math.max(selectedCityIndex - 1, -1);
          highlightCitySuggestion();
        }
      }
    });

      // Close dropdown when clicking outside
      document.addEventListener('click', function(e) {
        if (!smartInput.contains(e.target) && !autocompleteDropdown.contains(e.target)) {
          autocompleteDropdown.style.display = 'none';
          adjustHeroPadding();
        }
      });

      // Fetch city suggestions from API - SIMPLIFIED
      function fetchCitySuggestions(query) {
        if (!query || query.length < 2) {
          if (autocompleteDropdown) {
            autocompleteDropdown.style.display = 'none';
            adjustHeroPadding();
          }
          return;
        }
        
        if (!autocompleteDropdown) {
          console.error('autocompleteDropdown element not found');
          return;
        }
        
        // Cancel previous request
        if (currentAbortController) {
          currentAbortController.abort();
        }
        currentAbortController = new AbortController();
      
        // Simple API call - use relative path from current page
        let apiUrl = 'api/city_autocomplete.php?q=' + encodeURIComponent(query);
        // If we're in a subdirectory, adjust path
        if (window.location.pathname.includes('/index.php')) {
          apiUrl = './api/city_autocomplete.php?q=' + encodeURIComponent(query);
        }
        console.log('Fetching city suggestions for:', query, 'URL:', apiUrl);
        
        fetch(apiUrl, {
          signal: currentAbortController.signal
        })
          .then(response => {
            if (!response || !response.ok) {
              throw new Error('API error: ' + (response ? response.status : 'no response'));
            }
            return response.json();
          })
          .then(data => {
            if (data.error) {
              console.error('API error:', data.error);
              autocompleteDropdown.style.display = 'none';
              citySuggestions = [];
              adjustHeroPadding();
              return;
            }
            
            if (data.suggestions && Array.isArray(data.suggestions) && data.suggestions.length > 0) {
              citySuggestions = data.suggestions;
              showCitySuggestions(query, data.suggestions);
            } else {
              autocompleteDropdown.style.display = 'none';
              citySuggestions = [];
              adjustHeroPadding();
            }
          })
          .catch(err => {
            if (err.name === 'AbortError') return;
            console.error('City autocomplete error:', err);
            if (autocompleteDropdown) {
              autocompleteDropdown.style.display = 'none';
              adjustHeroPadding();
            }
            citySuggestions = [];
          });
      }

      // Show city suggestions in dropdown
      function showCitySuggestions(query, suggestions) {
        if (!autocompleteDropdown) {
          console.error('autocompleteDropdown element not found');
          return;
        }
        
        if (!suggestions || suggestions.length === 0) {
          autocompleteDropdown.style.display = 'none';
          adjustHeroPadding();
          return;
        }

        console.log('Displaying', suggestions.length, 'city suggestions');
        let html = '<div class="autocomplete-header">Cities:</div>';
        
        suggestions.forEach((suggestion, index) => {
          const isSelected = index === selectedCityIndex;
          const city = suggestion.city || '';
          const count = suggestion.count || suggestion.property_count || 0;
          // Display may contain HTML (from API highlighting), so don't escape it
          const display = suggestion.display || city;
          
          html += `<div class="autocomplete-item city-suggestion ${isSelected ? 'selected' : ''}" 
                        data-city="${escapeHtml(city)}" 
                        data-index="${index}">`;
          html += '<div class="suggestion-text">' + display + '</div>';
          html += '<div class="suggestion-action">' + count + ' properties</div>';
          html += '</div>';
        });

        autocompleteDropdown.innerHTML = html;
        autocompleteDropdown.style.display = 'block';
        console.log('Dropdown displayed with', suggestions.length, 'suggestions');

        // Adjust hero padding after a brief delay to ensure dropdown is rendered
        setTimeout(adjustHeroPadding, 10);

        // Add click handlers
        autocompleteDropdown.querySelectorAll('.city-suggestion').forEach(item => {
          item.addEventListener('click', function() {
            const city = this.getAttribute('data-city');
            if (city) {
              selectCity(city);
            }
          });
        });
      }

      // OLD: Fetch general search suggestions from API (kept for reference, not used)
      function fetchSearchSuggestions(query) {
        // Create abort controller for this request
        currentAbortController = new AbortController();
      
        // Build API URL - use relative path
        const apiUrl = './api/search_suggestions.php?q=' + encodeURIComponent(query);
        
        fetch(apiUrl, {
          signal: currentAbortController.signal,
          headers: {
            'Accept': 'application/json'
          },
          method: 'GET'
        })
          .then(response => {
            // Check if we got a valid response object
            if (!response) {
              throw new Error('No response from server');
            }
            
            // Check if response has status property (might be missing on network errors)
            if (typeof response.status === 'undefined') {
              console.error('Response missing status property:', {
                response: response,
                url: apiUrl,
                type: typeof response
              });
              throw new Error('Invalid response from server - missing status');
            }
            
            // Check response status
            if (!response.ok) {
              const status = response.status || 'unknown';
              const statusText = response.statusText || 'Unknown error';
              console.error('API response not OK:', {
                status: status,
                statusText: statusText,
                url: apiUrl
              });
              throw new Error('HTTP error: ' + status + ' - ' + statusText);
            }
            
            // Try to parse JSON
            return response.text().then(text => {
              try {
                const data = JSON.parse(text);
                return data;
              } catch (e) {
                console.error('Failed to parse JSON response:', {
                  error: e.message,
                  text: text.substring(0, 200),
                  url: apiUrl
                });
                throw new Error('Invalid JSON response from server: ' + e.message);
              }
            });
          })
          .then(data => {
            // Handle both 'suggestions' and 'error' responses
            if (!data) {
              console.error('Empty response from API');
              autocompleteDropdown.style.display = 'none';
              citySuggestions = [];
              return;
            }
            
            if (data.error) {
              console.error('API error:', data.error);
              autocompleteDropdown.style.display = 'none';
              citySuggestions = [];
              return;
            }
            
            if (data.suggestions && Array.isArray(data.suggestions) && data.suggestions.length > 0) {
              citySuggestions = data.suggestions;
              showSearchSuggestions(query, data.suggestions);
            } else {
              // No suggestions, hide dropdown
              autocompleteDropdown.style.display = 'none';
              citySuggestions = [];
            }
          })
          .catch(err => {
            if (err.name === 'AbortError') {
              // Request was cancelled, ignore
              return;
            }
            console.error('Search suggestions error:', {
              error: err.message || err,
              name: err.name,
              url: apiUrl,
              stack: err.stack
            });
            autocompleteDropdown.style.display = 'none';
            citySuggestions = [];
          });
      }

      // Show search suggestions in dropdown
      function showSearchSuggestions(query, suggestions) {
        if (suggestions.length === 0) {
          autocompleteDropdown.style.display = 'none';
          return;
        }

        let html = '<div class="autocomplete-header">Search Suggestions:</div>';
        
        suggestions.forEach((suggestion, index) => {
          const isSelected = index === selectedCityIndex;
          html += `<div class="autocomplete-item city-suggestion ${isSelected ? 'selected' : ''}" 
                        data-index="${index}"
                        data-suggestion='${JSON.stringify(suggestion)}'>`;
          html += '<div class="suggestion-text">' + escapeHtml(suggestion.display) + '</div>';
          html += '</div>';
        });

        autocompleteDropdown.innerHTML = html;
        autocompleteDropdown.style.display = 'block';

        // Add click handlers
        autocompleteDropdown.querySelectorAll('.city-suggestion').forEach(item => {
          item.addEventListener('click', function() {
            const suggestionJson = this.getAttribute('data-suggestion');
            const suggestion = JSON.parse(suggestionJson);
            selectSuggestion(suggestion);
          });
        });
      }

      // Highlight selected suggestion
      function highlightCitySuggestion() {
        const items = autocompleteDropdown.querySelectorAll('.city-suggestion');
        items.forEach((item, index) => {
          if (index === selectedCityIndex) {
            item.classList.add('selected');
            item.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
          } else {
            item.classList.remove('selected');
          }
        });
      }

      // Perform general search across all database fields
      function performGeneralSearch(query) {
        // Hide dropdown
        autocompleteDropdown.style.display = 'none';
        citySuggestions = [];
        isCityMode = false;
        
        // Parse query to extract city, beds, baths, etc.
        const parsed = parseSearchQuery(query);
        
        // Build URL - CLEAR ALL FILTERS first
        const urlParams = new URLSearchParams();
        urlParams.set('page', '1');
        
        // Apply parsed filters
        if (parsed.city) {
          urlParams.set('city', parsed.city);
        }
        if (parsed.beds) {
          urlParams.set('beds', parsed.beds);
        }
        if (parsed.baths) {
          urlParams.set('baths', parsed.baths);
        }
        if (parsed.zip) {
          urlParams.set('zip', parsed.zip);
        }
        if (parsed.price_min) {
          urlParams.set('price_min', parsed.price_min);
        }
        if (parsed.price_max) {
          urlParams.set('price_max', parsed.price_max);
        }
        
        // If we couldn't parse anything, use general search
        if (!parsed.city && !parsed.beds && !parsed.baths && !parsed.zip && !parsed.price_min && !parsed.price_max) {
          urlParams.set('search', query);
        }
        
        // Clear all filter inputs in the form
        const cityInput = document.getElementById('city');
        const zipInput = document.getElementById('zip');
        const priceMinInput = document.getElementById('price_min');
        const priceMaxInput = document.getElementById('price_max');
        const bedsInput = document.getElementById('beds');
        const bathsInput = document.getElementById('baths');
        const sqftMinInput = document.getElementById('sqft_min');
        const sqftMaxInput = document.getElementById('sqft_max');
        
        if (cityInput) cityInput.value = parsed.city || '';
        if (zipInput) zipInput.value = parsed.zip || '';
        if (priceMinInput) priceMinInput.value = parsed.price_min || '';
        if (priceMaxInput) priceMaxInput.value = parsed.price_max || '';
        if (bedsInput) bedsInput.value = parsed.beds || '';
        if (bathsInput) bathsInput.value = parsed.baths || '';
        if (sqftMinInput) sqftMinInput.value = '';
        if (sqftMaxInput) sqftMaxInput.value = '';
        
        // Navigate to search results
        window.location.href = '?' + urlParams.toString();
      }
      
      // Parse search query to extract filters
      function parseSearchQuery(query) {
        const result = {};
        const words = query.split(/\s+/);
        const textParts = [];
        let bedsNum = null;
        let bathsNum = null;
        
        // Extract beds, baths, and text
        for (let i = 0; i < words.length; i++) {
          const word = words[i].toLowerCase();
          
          // Check for "X beds" or "X bed"
          if (/^\d+\s*(bed|beds|br|bedroom|bedrooms)$/i.test(words[i])) {
            bedsNum = parseInt(words[i].match(/\d+/)[0]);
            continue;
          }
          
          // Check for "X baths" or "X bath" or "X bathrooms"
          if (/^\d+\s*(bath|baths|ba|bathroom|bathrooms)$/i.test(words[i])) {
            bathsNum = parseInt(words[i].match(/\d+/)[0]);
            continue;
          }
          
          // Check for plain numbers
          if (/^\d+$/.test(words[i])) {
            const num = parseInt(words[i]);
            // If it's a small number and we don't have beds yet, assume beds
            if (num <= 10 && bedsNum === null) {
              bedsNum = num;
              continue;
            }
            // If we have beds but not baths and it's reasonable, assume baths
            if (bedsNum !== null && bathsNum === null && num >= 1 && num <= 20) {
              bathsNum = num;
              continue;
            }
            // If it's a larger number, might be price or sqft - skip for now
            continue;
          }
          
          // Check for price indicators
          if (/^(under|below|max|up\s*to|less\s*than)\s*\$?(\d+[km]?)/i.test(query.substring(query.indexOf(words[i])))) {
            const match = query.match(/(under|below|max|up\s*to|less\s*than)\s*\$?(\d+)([km]?)/i);
            if (match) {
              let price = parseInt(match[2]);
              if (match[3].toLowerCase() === 'k') price *= 1000;
              if (match[3].toLowerCase() === 'm') price *= 1000000;
              result.price_max = price;
              // Skip the price words
              i += match[0].split(/\s+/).length - 1;
              continue;
            }
          }
          
          // Check for ZIP code (5 digits)
          if (/^\d{5}$/.test(words[i])) {
            result.zip = words[i];
            continue;
          }
          
          // Otherwise, it's text (likely city name)
          textParts.push(words[i]);
        }
        
        // Join text parts as city
        if (textParts.length > 0) {
          result.city = textParts.join(' ');
        }
        
        if (bedsNum !== null) {
          result.beds = bedsNum;
        }
        if (bathsNum !== null) {
          result.baths = bathsNum;
        }
        
        return result;
      }

      // Select a suggestion (city, city+beds, city+beds+baths, etc.)
      function selectSuggestion(suggestion) {
        // Hide dropdown
        autocompleteDropdown.style.display = 'none';
        citySuggestions = [];
        isCityMode = false;
        
        // Build URL - CLEAR ALL FILTERS first (start fresh)
        const urlParams = new URLSearchParams();
        urlParams.set('page', '1');
        
        // Apply suggestion filters only
        if (suggestion.city) {
          urlParams.set('city', suggestion.city);
        }
        if (suggestion.beds) {
          urlParams.set('beds', suggestion.beds);
        }
        if (suggestion.baths) {
          urlParams.set('baths', suggestion.baths);
        }
        
        // Clear search input
        smartInput.value = '';
        
        // Navigate to filtered results
        window.location.href = '?' + urlParams.toString();
      }

      // Select a city and filter by city only
      function selectCity(city) {
        selectSuggestion({ city: city });
      }

      // NLP parsing removed - city autocomplete now directly performs search

      function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
      }
    }); // End DOMContentLoaded

    // ==========================================
    // STICKY HEADER ON SCROLL
    // ==========================================
    const stickyHeader = document.getElementById('sticky-header');
    const heroSection = document.querySelector('.hero');
    
    // Sticky header is now always visible, but we can still add scroll effects if needed
    // Removed the scroll-based visibility toggle since header is always visible now

    // ==========================================
    // INITIALIZE GOOGLE MAPS ON PAGE LOAD
    // ==========================================
    window.addEventListener('load', function() {
      // Wait for Google Maps API to load
      const checkGoogleMaps = setInterval(function() {
        if (typeof google !== 'undefined' && google.maps) {
          initGoogleMaps();
          clearInterval(checkGoogleMaps);
        }
      }, 100);
    });
  </script>

  <!-- ElevenLabs Conversational AI Widget - Calibot -->
  <elevenlabs-convai agent-id="agent_1301kb4tbstsf95rv1e5jxw4nz7k"></elevenlabs-convai>
  <script src="https://unpkg.com/@elevenlabs/convai-widget-embed" async type="text/javascript"></script>
</body>
</html>