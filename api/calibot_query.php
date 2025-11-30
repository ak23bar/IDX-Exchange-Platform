<?php
/**
 * Calibot Query API
 * REST API endpoint for ElevenLabs Calibot agent to query property database
 * 
 * Endpoint: /api/calibot_query.php
 * Method: POST
 * Content-Type: application/json
 * 
 * Request Body:
 * {
 *   "action": "search" | "stats" | "property_details" | "city_stats",
 *   "params": { ... }
 * }
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Only allow POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Database Configuration
$DB_HOST = 'localhost';
$DB_NAME = 'boxgra6_cali';
$DB_USER = 'boxgra6_sd';
$DB_PASS = 'Real_estate650$';

// Establish PDO Connection
$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed', 'message' => $e->getMessage()]);
    exit;
}

// Get request body
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data || !isset($data['action'])) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid request. Missing "action" parameter.']);
    exit;
}

$action = $data['action'];
$params = $data['params'] ?? [];

// Helper function to format money
function formatMoney($n) {
    return '$' . number_format((float)$n, 0);
}

// Helper function to build WHERE clause
function buildWhereClause($params, &$where, &$bindParams) {
    if (isset($params['city']) && $params['city'] !== '') {
        $where[] = 'L_City LIKE :city';
        $bindParams[':city'] = '%' . trim($params['city']) . '%';
    }
    if (isset($params['zip']) && $params['zip'] !== '') {
        $where[] = 'L_Zip = :zip';
        $bindParams[':zip'] = trim($params['zip']);
    }
    if (isset($params['price_min']) && $params['price_min'] !== '') {
        $where[] = 'L_SystemPrice >= :pmin';
        $bindParams[':pmin'] = (int)$params['price_min'];
    }
    if (isset($params['price_max']) && $params['price_max'] !== '') {
        $where[] = 'L_SystemPrice <= :pmax';
        $bindParams[':pmax'] = (int)$params['price_max'];
    }
    if (isset($params['beds']) && $params['beds'] !== '') {
        $where[] = 'CAST(L_Keyword2 AS UNSIGNED) >= :beds';
        $bindParams[':beds'] = (int)$params['beds'];
    }
    if (isset($params['baths']) && $params['baths'] !== '') {
        $where[] = 'CAST(LM_Dec_3 AS UNSIGNED) >= :baths';
        $bindParams[':baths'] = (float)$params['baths'];
    }
    if (isset($params['sqft_min']) && $params['sqft_min'] !== '') {
        $where[] = 'CAST(LM_Int2_3 AS UNSIGNED) >= :sqft_min';
        $bindParams[':sqft_min'] = (int)$params['sqft_min'];
    }
    if (isset($params['sqft_max']) && $params['sqft_max'] !== '') {
        $where[] = 'CAST(LM_Int2_3 AS UNSIGNED) <= :sqft_max';
        $bindParams[':sqft_max'] = (int)$params['sqft_max'];
    }
}

try {
    switch ($action) {
        case 'search':
            // Search properties with filters
            $where = [];
            $bindParams = [];
            buildWhereClause($params, $where, $bindParams);
            
            $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
            $limit = isset($params['limit']) ? min((int)$params['limit'], 50) : 10;
            
            $sql = "SELECT 
                L_ListingID,
                L_Address,
                L_City,
                L_Zip,
                L_SystemPrice,
                L_Keyword2 AS Beds,
                LM_Int2_3 AS SqFt,
                LM_Dec_3 AS Baths
            FROM rets_property
            $where_sql
            ORDER BY L_SystemPrice DESC
            LIMIT :limit";
            
            $st = $pdo->prepare($sql);
            foreach ($bindParams as $k => $v) {
                $st->bindValue($k, $v);
            }
            $st->bindValue(':limit', $limit, PDO::PARAM_INT);
            $st->execute();
            $results = $st->fetchAll();
            
            // Format results
            $formatted = array_map(function($r) {
                return [
                    'listing_id' => $r['L_ListingID'],
                    'address' => trim($r['L_Address'] . ', ' . $r['L_City'] . ', CA ' . $r['L_Zip']),
                    'price' => formatMoney($r['L_SystemPrice']),
                    'price_raw' => (int)$r['L_SystemPrice'],
                    'beds' => $r['Beds'] ? (int)$r['Beds'] : null,
                    'baths' => $r['Baths'] ? (float)$r['Baths'] : null,
                    'sqft' => $r['SqFt'] ? (int)$r['SqFt'] : null,
                    'city' => $r['L_City'],
                    'zip' => $r['L_Zip']
                ];
            }, $results);
            
            echo json_encode([
                'success' => true,
                'count' => count($formatted),
                'properties' => $formatted
            ]);
            break;
            
        case 'stats':
            // Get overall statistics
            $where = [];
            $bindParams = [];
            buildWhereClause($params, $where, $bindParams);
            $where_sql = $where ? ('WHERE ' . implode(' AND ', $where)) : '';
            
            $sql = "SELECT 
                COUNT(*) as total_count,
                AVG(L_SystemPrice) as avg_price,
                MIN(L_SystemPrice) as min_price,
                MAX(L_SystemPrice) as max_price,
                AVG(CAST(LM_Int2_3 AS UNSIGNED)) as avg_sqft,
                AVG(CAST(L_Keyword2 AS UNSIGNED)) as avg_beds
            FROM rets_property $where_sql";
            
            $st = $pdo->prepare($sql);
            foreach ($bindParams as $k => $v) {
                $st->bindValue($k, $v);
            }
            $st->execute();
            $stats = $st->fetch();
            
            echo json_encode([
                'success' => true,
                'stats' => [
                    'total_properties' => (int)$stats['total_count'],
                    'average_price' => formatMoney($stats['avg_price']),
                    'average_price_raw' => (float)$stats['avg_price'],
                    'min_price' => formatMoney($stats['min_price']),
                    'min_price_raw' => (int)$stats['min_price'],
                    'max_price' => formatMoney($stats['max_price']),
                    'max_price_raw' => (int)$stats['max_price'],
                    'average_sqft' => (int)round($stats['avg_sqft']),
                    'average_beds' => round($stats['avg_beds'], 1)
                ]
            ]);
            break;
            
        case 'city_stats':
            // Get statistics by city
            $city = isset($params['city']) ? trim($params['city']) : '';
            if (!$city) {
                http_response_code(400);
                echo json_encode(['error' => 'City parameter required']);
                exit;
            }
            
            $sql = "SELECT 
                L_City,
                COUNT(*) as total_count,
                AVG(L_SystemPrice) as avg_price,
                MIN(L_SystemPrice) as min_price,
                MAX(L_SystemPrice) as max_price,
                AVG(CAST(LM_Int2_3 AS UNSIGNED)) as avg_sqft
            FROM rets_property
            WHERE L_City LIKE :city
            GROUP BY L_City
            ORDER BY total_count DESC
            LIMIT 10";
            
            $st = $pdo->prepare($sql);
            $st->bindValue(':city', '%' . $city . '%');
            $st->execute();
            $results = $st->fetchAll();
            
            $formatted = array_map(function($r) {
                return [
                    'city' => $r['L_City'],
                    'total_properties' => (int)$r['total_count'],
                    'average_price' => formatMoney($r['avg_price']),
                    'average_price_raw' => (float)$r['avg_price'],
                    'min_price' => formatMoney($r['min_price']),
                    'max_price' => formatMoney($r['max_price']),
                    'average_sqft' => (int)round($r['avg_sqft'])
                ];
            }, $results);
            
            echo json_encode([
                'success' => true,
                'cities' => $formatted
            ]);
            break;
            
        case 'property_details':
            // Get details for a specific property
            $listing_id = isset($params['listing_id']) ? trim($params['listing_id']) : '';
            if (!$listing_id) {
                http_response_code(400);
                echo json_encode(['error' => 'listing_id parameter required']);
                exit;
            }
            
            $sql = "SELECT 
                L_ListingID,
                L_Address,
                L_City,
                L_Zip,
                L_SystemPrice,
                L_Keyword2 AS Beds,
                LM_Int2_3 AS SqFt,
                LM_Dec_3 AS Baths,
                L_Photos,
                L_UpdateDate
            FROM rets_property
            WHERE L_ListingID = :listing_id
            LIMIT 1";
            
            $st = $pdo->prepare($sql);
            $st->bindValue(':listing_id', $listing_id);
            $st->execute();
            $property = $st->fetch();
            
            if (!$property) {
                http_response_code(404);
                echo json_encode(['error' => 'Property not found']);
                exit;
            }
            
            // Parse photos
            $photos = [];
            if ($property['L_Photos']) {
                $photos_arr = json_decode($property['L_Photos'], true);
                if (is_array($photos_arr)) {
                    foreach ($photos_arr as $item) {
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
                }
            }
            
            $price_per_sqft = null;
            if ($property['SqFt'] && $property['L_SystemPrice']) {
                $price_per_sqft = formatMoney($property['L_SystemPrice'] / $property['SqFt']) . '/ft²';
            }
            
            echo json_encode([
                'success' => true,
                'property' => [
                    'listing_id' => $property['L_ListingID'],
                    'address' => trim($property['L_Address'] . ', ' . $property['L_City'] . ', CA ' . $property['L_Zip']),
                    'price' => formatMoney($property['L_SystemPrice']),
                    'price_raw' => (int)$property['L_SystemPrice'],
                    'price_per_sqft' => $price_per_sqft,
                    'beds' => $property['Beds'] ? (int)$property['Beds'] : null,
                    'baths' => $property['Baths'] ? (float)$property['Baths'] : null,
                    'sqft' => $property['SqFt'] ? (int)$property['SqFt'] : null,
                    'city' => $property['L_City'],
                    'zip' => $property['L_Zip'],
                    'photo_count' => count($photos),
                    'last_updated' => $property['L_UpdateDate']
                ]
            ]);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Unknown action. Valid actions: search, stats, city_stats, property_details']);
            exit;
    }
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database query failed', 'message' => $e->getMessage()]);
}

