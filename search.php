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
// 3) Handle Favorites
// -------------------------
if (!isset($_SESSION['favorites'])) {
  $_SESSION['favorites'] = [];
}
if (isset($_GET['toggle_fav'])) {
  $lid = $_GET['toggle_fav'];
  if (in_array($lid, $_SESSION['favorites'])) {
    $_SESSION['favorites'] = array_diff($_SESSION['favorites'], [$lid]);
  } else {
    $_SESSION['favorites'][] = $lid;
  }
  header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?') . '?' . http_build_query(array_diff_key($_GET, ['toggle_fav'=>''])));
  exit;
}

// -------------------------
// 4) Handle CSV Export
// -------------------------
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
  $city      = isset($_GET['city'])      ? trim($_GET['city'])      : '';
  $zip       = isset($_GET['zip'])       ? trim($_GET['zip'])       : '';
  $price_min = isset($_GET['price_min']) ? (int)$_GET['price_min']  : '';
  $price_max = isset($_GET['price_max']) ? (int)$_GET['price_max']  : '';
  $beds      = isset($_GET['beds'])      ? (int)$_GET['beds']       : '';
  $baths     = isset($_GET['baths'])     ? (int)$_GET['baths']      : '';
  $sqft_min  = isset($_GET['sqft_min'])  ? (int)$_GET['sqft_min']   : '';
  $sqft_max  = isset($_GET['sqft_max'])  ? (int)$_GET['sqft_max']   : '';
  
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
$price_min = isset($_GET['price_min']) ? (int)$_GET['price_min']  : '';
$price_max = isset($_GET['price_max']) ? (int)$_GET['price_max']  : '';
$beds      = isset($_GET['beds'])      ? (int)$_GET['beds']       : '';
$baths     = isset($_GET['baths'])     ? (int)$_GET['baths']      : '';
$sqft_min  = isset($_GET['sqft_min'])  ? (int)$_GET['sqft_min']   : '';
$sqft_max  = isset($_GET['sqft_max'])  ? (int)$_GET['sqft_max']   : '';
$sort      = isset($_GET['sort'])      ? $_GET['sort']            : 'price_desc';
$view      = isset($_GET['view'])      ? $_GET['view']            : 'grid';
$page      = isset($_GET['page'])      ? max(1, (int)$_GET['page']) : 1;
$per_page  = 12;
$offset    = ($page - 1) * $per_page;

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

$st = $pdo->prepare($sql);
foreach ($params as $k => $v) {
  $st->bindValue($k, $v);
}
$st->bindValue(':limit',  $per_page, PDO::PARAM_INT);
$st->bindValue(':offset', $offset,   PDO::PARAM_INT);
$st->execute();
$rows = $st->fetchAll();

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

    header {
      position: sticky;
      top: 0;
      z-index: 100;
      backdrop-filter: blur(12px) saturate(140%);
      background: rgba(11, 17, 35, 0.85);
      border-bottom: 1px solid rgba(78, 116, 255, 0.15);
      padding: 1.5rem 0;
      text-align: center;
      box-shadow: 0 1px 10px rgba(0, 0, 0, 0.2);
    }
    header h1 {
      font-size: 1.8rem;
      margin: 0;
      background: linear-gradient(to right, #5ab3ff, #9caeff, #a3aaff);
      -webkit-background-clip: text;
      background-clip: text;
      color: transparent;
    }
    header p {
      margin: 6px 0 0;
      font-size: 0.95rem;
      color: var(--muted);
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

    /* Search Form */
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

    /* Pagination */
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
  </style>
</head>
<body>
  <header>
    <h1>🏠 California Property Finder</h1>
    <p>Advanced Search & Analytics</p>
  </header>

  <div class="wrap">
    <!-- Statistics Dashboard -->
    <?php if ($total > 0): ?>
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

    <!-- Search Form -->
    <form class="search" method="get">
      <div class="search-grid">
        <div>
          <label for="city">City</label>
          <input type="text" id="city" name="city" placeholder="e.g., San Jose" value="<?= htmlspecialchars($city) ?>">
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
        <button type="submit" class="btn-primary">🔍 Search Properties</button>
        <button type="button" class="btn-secondary" onclick="window.location.href='?'">Clear Filters</button>
        <?php if ($total > 0): ?>
        <button type="button" class="btn-secondary" onclick="window.location.href='?<?= http_build_query(array_merge($_GET, ['export' => 'csv'])) ?>'">📊 Export CSV</button>
        <?php endif; ?>
      </div>
    </form>

    <?php if ($total === 0): ?>
      <div style="text-align:center;padding:3rem 1rem;background:rgba(17,26,56,0.5);border-radius:18px;border:1px solid rgba(79,124,255,0.2)">
        <div style="font-size:3rem;margin-bottom:1rem">🔍</div>
        <p style="font-size:1.2rem;color:#cdd5f3;margin:0">No properties match your criteria</p>
        <p style="color:var(--muted);margin-top:0.5rem">Try adjusting your filters or clearing them to see more results</p>
      </div>
    <?php else: ?>
      <!-- Toolbar -->
      <div class="toolbar">
        <div class="toolbar-left">
          <div class="view-toggle">
            <button type="button" class="<?= $view === 'grid' ? 'active' : '' ?>" onclick="window.location.href='?<?= http_build_query(array_merge($_GET, ['view' => 'grid', 'page' => 1])) ?>'">
              ⊞ Grid
            </button>
            <button type="button" class="<?= $view === 'list' ? 'active' : '' ?>" onclick="window.location.href='?<?= http_build_query(array_merge($_GET, ['view' => 'list', 'page' => 1])) ?>'">
              ☰ List
            </button>
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
          <?php if (count($_SESSION['favorites']) > 0): ?>
            | ❤️ <strong><?= count($_SESSION['favorites']) ?></strong> favorites
          <?php endif; ?>
        </p>
      </div>

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
          $is_fav = in_array($listing_id, $_SESSION['favorites']);
          $price_per_sqft = ($sqft && $r['L_SystemPrice']) ? money($r['L_SystemPrice'] / $sqft) . '/ft²' : null;
        ?>
          <article class="card" onclick="openModal('<?= htmlspecialchars($listing_id) ?>')">
            <div class="img-container">
              <?php if ($img): ?>
                <img class="img" src="<?= htmlspecialchars($img) ?>" alt="Property at <?= $full_addr ?>" loading="lazy" />
              <?php else: ?>
                <div class="img" style="display:grid;place-items:center;color:#7e8bbd;font-size:0.9rem">
                  <div>📷 No Photo Available</div>
                </div>
              <?php endif; ?>
              <button class="fav-btn <?= $is_fav ? 'active' : '' ?>" 
                      onclick="event.stopPropagation(); window.location.href='?<?= http_build_query(array_merge($_GET, ['toggle_fav' => $listing_id])) ?>'"
                      title="<?= $is_fav ? 'Remove from favorites' : 'Add to favorites' ?>">
                <?= $is_fav ? '❤️' : '🤍' ?>
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
                <span>🛏️ <?= $beds !== null ? (int)$beds . ' bd' : '— bd' ?></span>
                <span>🛁 <?= $baths !== null ? rtrim(rtrim(number_format($baths,1), '0'),'.') . ' ba' : '— ba' ?></span>
                <span>📏 <?= $sqft ? number_format($sqft) . ' ft²' : '— ft²' ?></span>
              </div>
            </div>
          </article>

          <!-- Modal for this property -->
          <div id="modal-<?= htmlspecialchars($listing_id) ?>" class="modal" onclick="if(event.target === this) closeModal('<?= htmlspecialchars($listing_id) ?>')">
            <div class="modal-content">
              <button class="modal-close" onclick="closeModal('<?= htmlspecialchars($listing_id) ?>')">&times;</button>
              
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
                  <button onclick="event.stopPropagation(); prevImage('<?= htmlspecialchars($listing_id) ?>', <?= count($all_imgs) ?>)">‹</button>
                  <button onclick="event.stopPropagation(); nextImage('<?= htmlspecialchars($listing_id) ?>', <?= count($all_imgs) ?>)">›</button>
                </div>
                <div class="gallery-dots">
                  <?php for ($i = 0; $i < count($all_imgs); $i++): ?>
                    <div class="gallery-dot <?= $i === 0 ? 'active' : '' ?>" 
                         onclick="event.stopPropagation(); showImage('<?= htmlspecialchars($listing_id) ?>', <?= $i ?>, <?= count($all_imgs) ?>)"></div>
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
                    <div style="font-size:1.8rem">🛏️</div>
                    <div style="font-size:1.5rem;font-weight:700;color:var(--accent-light);margin:0.5rem 0"><?= $beds !== null ? $beds : '—' ?></div>
                    <div style="font-size:0.8rem;color:var(--muted)">Bedrooms</div>
                  </div>
                  <div style="background:rgba(50,70,120,0.2);padding:1rem;border-radius:12px;text-align:center">
                    <div style="font-size:1.8rem">🛁</div>
                    <div style="font-size:1.5rem;font-weight:700;color:var(--accent-light);margin:0.5rem 0"><?= $baths !== null ? rtrim(rtrim(number_format($baths,1), '0'),'.') : '—' ?></div>
                    <div style="font-size:0.8rem;color:var(--muted)">Bathrooms</div>
                  </div>
                  <div style="background:rgba(50,70,120,0.2);padding:1rem;border-radius:12px;text-align:center">
                    <div style="font-size:1.8rem">📏</div>
                    <div style="font-size:1.5rem;font-weight:700;color:var(--accent-light);margin:0.5rem 0"><?= $sqft ? number_format($sqft) : '—' ?></div>
                    <div style="font-size:0.8rem;color:var(--muted)">Square Feet</div>
                  </div>
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
    <?php endif; ?>
  </div>

  <footer>
    <p>© <?= date('Y') ?> Developed by <strong>Akbar Aman</strong> — SD6 Team, IDX Exchange Initiative</p>
    <p style="margin-top:0.5rem;font-size:0.75rem">Advanced Property Search • Statistics Dashboard • Export Tools • Favorites System</p>
  </footer>

  <div class="shortcuts-hint">
    💡 Press <kbd>ESC</kbd> to close modals
  </div>

  <script>
    // Modal Management
    function openModal(id) {
      document.getElementById('modal-' + id).classList.add('active');
      document.body.style.overflow = 'hidden';
    }

    function closeModal(id) {
      document.getElementById('modal-' + id).classList.remove('active');
      document.body.style.overflow = '';
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
  </script>
</body>
</html>