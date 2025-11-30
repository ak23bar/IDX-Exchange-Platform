<?php
/**
 * City Autocomplete API
 * Returns city suggestions based on partial input for autocomplete
 */

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Get query parameter
$query = isset($_GET['q']) ? trim($_GET['q']) : '';

if (empty($query) || strlen($query) < 2) {
    echo json_encode(['suggestions' => []]);
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
    echo json_encode(['error' => 'Database connection failed']);
    exit;
}

try {
    // First, try exact/partial match (faster)
    $searchTerm = '%' . $query . '%';
    
    $sql = "SELECT DISTINCT L_City, COUNT(*) as property_count
            FROM rets_property
            WHERE L_City LIKE :search
            GROUP BY L_City
            ORDER BY property_count DESC, L_City ASC
            LIMIT 20";
    
    $st = $pdo->prepare($sql);
    $st->bindValue(':search', $searchTerm);
    $st->execute();
    $results = $st->fetchAll();
    
    // If we have results, use them; otherwise, get all cities for fuzzy matching
    if (empty($results)) {
        // Get all cities for fuzzy matching
        $sqlAll = "SELECT DISTINCT L_City, COUNT(*) as property_count
                   FROM rets_property
                   GROUP BY L_City
                   ORDER BY property_count DESC, L_City ASC";
        $stAll = $pdo->prepare($sqlAll);
        $stAll->execute();
        $allCities = $stAll->fetchAll();
        
        // Calculate Levenshtein distance for fuzzy matching
        $queryLower = strtolower($query);
        $scoredCities = [];
        
        foreach ($allCities as $row) {
            $city = $row['L_City'];
            $cityLower = strtolower($city);
            
            // Calculate similarity using Levenshtein distance
            $distance = levenshtein($queryLower, $cityLower);
            $maxLen = max(strlen($queryLower), strlen($cityLower));
            $similarity = $maxLen > 0 ? (1 - ($distance / $maxLen)) : 0;
            
            // Also check if query is a substring (partial match)
            $isSubstring = strpos($cityLower, $queryLower) !== false || strpos($queryLower, $cityLower) !== false;
            if ($isSubstring) {
                $similarity = max($similarity, 0.7); // Boost substring matches
            }
            
            // Only include cities with reasonable similarity (>= 0.5) or if query is very short
            if ($similarity >= 0.5 || strlen($query) <= 2) {
                $scoredCities[] = [
                    'city' => $city,
                    'property_count' => (int)$row['property_count'],
                    'similarity' => $similarity,
                    'distance' => $distance
                ];
            }
        }
        
        // Sort by similarity (descending), then by property count (descending)
        usort($scoredCities, function($a, $b) {
            if (abs($a['similarity'] - $b['similarity']) < 0.01) {
                return $b['property_count'] - $a['property_count'];
            }
            return $b['similarity'] <=> $a['similarity'];
        });
        
        // Take top 10
        $results = array_slice($scoredCities, 0, 10);
        // Convert back to expected format
        $results = array_map(function($item) {
            return [
                'L_City' => $item['city'],
                'property_count' => $item['property_count']
            ];
        }, $results);
    }
    
    $suggestions = array_map(function($row) use ($query) {
        $city = $row['L_City'];
        $count = (int)$row['property_count'];
        
        // Highlight matching part (works for both exact and fuzzy matches)
        $highlighted = $city;
        $lowerQuery = strtolower($query);
        $lowerCity = strtolower($city);
        
        // Try to find the query as a substring first
        $pos = strpos($lowerCity, $lowerQuery);
        if ($pos !== false) {
            $before = substr($city, 0, $pos);
            $match = substr($city, $pos, strlen($query));
            $after = substr($city, $pos + strlen($query));
            $highlighted = $before . '<strong>' . $match . '</strong>' . $after;
        } else {
            // For fuzzy matches, highlight common characters
            // Find longest common substring
            $common = '';
            $maxLen = 0;
            for ($i = 0; $i < strlen($lowerCity); $i++) {
                for ($j = $i; $j < strlen($lowerCity); $j++) {
                    $substr = substr($lowerCity, $i, $j - $i + 1);
                    if (strpos($lowerQuery, $substr) !== false && strlen($substr) > $maxLen) {
                        $maxLen = strlen($substr);
                        $common = $substr;
                    }
                }
            }
            if ($common && strlen($common) >= 2) {
                $pos = strpos($lowerCity, $common);
                if ($pos !== false) {
                    $before = substr($city, 0, $pos);
                    $match = substr($city, $pos, strlen($common));
                    $after = substr($city, $pos + strlen($common));
                    $highlighted = $before . '<strong>' . $match . '</strong>' . $after;
                }
            }
        }
        
        return [
            'city' => $city,
            'display' => $highlighted,
            'count' => $count,
            'full_text' => $city . ' (' . number_format($count) . ' properties)'
        ];
    }, $results);
    
    echo json_encode([
        'success' => true,
        'query' => $query,
        'suggestions' => $suggestions
    ]);
    
} catch (Throwable $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Query failed', 'message' => $e->getMessage()]);
}

