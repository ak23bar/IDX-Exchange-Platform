<?php
/**
 * General Search Suggestions API
 * Returns search suggestions based on partial input for any query type
 * Handles city, beds, baths, and combinations
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
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'query' => $query,
        'suggestions' => []
    ], JSON_UNESCAPED_UNICODE);
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
    header('Content-Type: application/json');
    echo json_encode([
        'success' => false,
        'error' => 'Database connection failed',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

try {
    $suggestions = [];
    $queryLower = strtolower($query);
    
    // Extract potential components from query
    $words = preg_split('/\s+/', $query);
    
    // Try to extract numbers (beds, baths, price, sqft) and text parts
    $numbers = [];
    $textParts = [];
    $bedsNum = null;
    $bathsNum = null;
    
    foreach ($words as $word) {
        $wordLower = strtolower($word);
        // Check for bed/bath keywords with numbers
        if (preg_match('/^(\d+)\s*(bed|beds|br|bedroom|bedrooms)$/i', $word, $m)) {
            $bedsNum = (int)$m[1];
            $numbers[] = $bedsNum;
        } elseif (preg_match('/^(\d+)\s*(bath|baths|ba|bathroom|bathrooms)$/i', $word, $m)) {
            $bathsNum = (int)$m[1];
            $numbers[] = $bathsNum;
        } elseif (preg_match('/^\d+$/', $word)) {
            // Plain number - could be beds, baths, or other
            $num = (int)$word;
            $numbers[] = $num;
            // If we don't have beds yet and it's small, assume beds
            if ($bedsNum === null && $num <= 10) {
                $bedsNum = $num;
            }
            // If we have beds but not baths and it's reasonable, assume baths
            if ($bedsNum !== null && $bathsNum === null && $num >= 1 && $num <= 20) {
                $bathsNum = $num;
            }
        } else {
            $textParts[] = $word;
        }
    }
    
    // If we have numbers but no beds/baths assigned, assign them
    if ($bedsNum === null && !empty($numbers)) {
        foreach ($numbers as $num) {
            if ($num <= 10) {
                $bedsNum = $num;
                break;
            }
        }
    }
    if ($bathsNum === null && !empty($numbers) && $bedsNum !== null) {
        foreach ($numbers as $num) {
            if ($num != $bedsNum && $num >= 1 && $num <= 20) {
                $bathsNum = $num;
                break;
            }
        }
    }
    
    $textQuery = implode(' ', $textParts);
    
    // If we have text, search for cities
    if (!empty($textQuery) && strlen($textQuery) >= 2) {
        $citySearchTerm = '%' . $textQuery . '%';
        
        // Get cities matching the text
        $sql = "SELECT DISTINCT L_City, COUNT(*) as property_count
                FROM rets_property
                WHERE L_City LIKE :search
                GROUP BY L_City
                ORDER BY property_count DESC, L_City ASC
                LIMIT 10";
        
        $st = $pdo->prepare($sql);
        $st->bindValue(':search', $citySearchTerm);
        $st->execute();
        $cityResults = $st->fetchAll();
        
        // Build suggestions with combinations
        foreach ($cityResults as $row) {
            $city = $row['L_City'];
            $count = (int)$row['property_count'];
            
            // Base city suggestion
            $suggestions[] = [
                'type' => 'city',
                'text' => $city,
                'display' => $city . ' (' . number_format($count) . ' properties)',
                'city' => $city,
                'count' => $count
            ];
            
            // If we have beds number, create city + beds suggestion
            if ($bedsNum !== null && $bedsNum <= 10) {
                $bedsSql = "SELECT COUNT(*) as count 
                           FROM rets_property 
                           WHERE L_City LIKE :city 
                           AND CAST(L_Keyword2 AS UNSIGNED) >= :beds";
                $bedsSt = $pdo->prepare($bedsSql);
                $bedsSt->bindValue(':city', '%' . $city . '%');
                $bedsSt->bindValue(':beds', $bedsNum);
                $bedsSt->execute();
                $bedsResult = $bedsSt->fetch();
                $bedsCount = (int)$bedsResult['count'];
                
                if ($bedsCount > 0) {
                    $suggestions[] = [
                        'type' => 'city_beds',
                        'text' => $city . ' ' . $bedsNum . ' beds',
                        'display' => $city . ' - ' . $bedsNum . ' beds (' . number_format($bedsCount) . ' properties)',
                        'city' => $city,
                        'beds' => $bedsNum,
                        'count' => $bedsCount
                    ];
                    
                    // Add beds + baths combination if we have baths number
                    if ($bathsNum !== null && $bathsNum <= 20) {
                        $comboSql = "SELECT COUNT(*) as count 
                                    FROM rets_property 
                                    WHERE L_City LIKE :city 
                                    AND CAST(L_Keyword2 AS UNSIGNED) >= :beds
                                    AND CAST(LM_Dec_3 AS UNSIGNED) >= :baths";
                        $comboSt = $pdo->prepare($comboSql);
                        $comboSt->bindValue(':city', '%' . $city . '%');
                        $comboSt->bindValue(':beds', $bedsNum);
                        $comboSt->bindValue(':baths', $bathsNum);
                        $comboSt->execute();
                        $comboResult = $comboSt->fetch();
                        $comboCount = (int)$comboResult['count'];
                        
                        if ($comboCount > 0) {
                            $suggestions[] = [
                                'type' => 'city_beds_baths',
                                'text' => $city . ' ' . $bedsNum . ' beds ' . $bathsNum . ' baths',
                                'display' => $city . ' - ' . $bedsNum . ' beds, ' . $bathsNum . ' baths (' . number_format($comboCount) . ' properties)',
                                'city' => $city,
                                'beds' => $bedsNum,
                                'baths' => $bathsNum,
                                'count' => $comboCount
                            ];
                        }
                    }
                }
            }
            
            // If we have baths number (but no beds), create city + baths suggestion
            if ($bathsNum !== null && $bathsNum <= 20 && ($bedsNum === null || $bedsNum > 10)) {
                $bathsSql = "SELECT COUNT(*) as count 
                           FROM rets_property 
                           WHERE L_City LIKE :city 
                           AND CAST(LM_Dec_3 AS UNSIGNED) >= :baths";
                $bathsSt = $pdo->prepare($bathsSql);
                $bathsSt->bindValue(':city', '%' . $city . '%');
                $bathsSt->bindValue(':baths', $bathsNum);
                $bathsSt->execute();
                $bathsResult = $bathsSt->fetch();
                $bathsCount = (int)$bathsResult['count'];
                
                if ($bathsCount > 0) {
                    $suggestions[] = [
                        'type' => 'city_baths',
                        'text' => $city . ' ' . $bathsNum . ' baths',
                        'display' => $city . ' - ' . $bathsNum . ' baths (' . number_format($bathsCount) . ' properties)',
                        'city' => $city,
                        'baths' => $bathsNum,
                        'count' => $bathsCount
                    ];
                }
            }
        }
        
        // If no city matches but we have beds/baths numbers, suggest those
        if (empty($cityResults)) {
            if ($bedsNum !== null && $bedsNum <= 10) {
                $bedsSql = "SELECT COUNT(*) as count 
                           FROM rets_property 
                           WHERE CAST(L_Keyword2 AS UNSIGNED) >= :beds";
                $bedsSt = $pdo->prepare($bedsSql);
                $bedsSt->bindValue(':beds', $bedsNum);
                $bedsSt->execute();
                $bedsResult = $bedsSt->fetch();
                $bedsCount = (int)$bedsResult['count'];
                
                if ($bedsCount > 0) {
                    $suggestions[] = [
                        'type' => 'beds',
                        'text' => $bedsNum . ' beds',
                        'display' => $bedsNum . ' beds (' . number_format($bedsCount) . ' properties)',
                        'beds' => $bedsNum,
                        'count' => $bedsCount
                    ];
                    
                    // Add beds + baths if we have both
                    if ($bathsNum !== null && $bathsNum <= 20) {
                        $comboSql = "SELECT COUNT(*) as count 
                                    FROM rets_property 
                                    WHERE CAST(L_Keyword2 AS UNSIGNED) >= :beds
                                    AND CAST(LM_Dec_3 AS UNSIGNED) >= :baths";
                        $comboSt = $pdo->prepare($comboSql);
                        $comboSt->bindValue(':beds', $bedsNum);
                        $comboSt->bindValue(':baths', $bathsNum);
                        $comboSt->execute();
                        $comboResult = $comboSt->fetch();
                        $comboCount = (int)$comboResult['count'];
                        
                        if ($comboCount > 0) {
                            $suggestions[] = [
                                'type' => 'beds_baths',
                                'text' => $bedsNum . ' beds ' . $bathsNum . ' baths',
                                'display' => $bedsNum . ' beds, ' . $bathsNum . ' baths (' . number_format($comboCount) . ' properties)',
                                'beds' => $bedsNum,
                                'baths' => $bathsNum,
                                'count' => $comboCount
                            ];
                        }
                    }
                }
            }
        }
    } elseif (!empty($numbers)) {
        // Only numbers, suggest beds/baths
        foreach ($numbers as $num) {
            if ($num <= 10) {
                $bedsSql = "SELECT COUNT(*) as count 
                           FROM rets_property 
                           WHERE CAST(L_Keyword2 AS UNSIGNED) >= :beds";
                $bedsSt = $pdo->prepare($bedsSql);
                $bedsSt->bindValue(':beds', $num);
                $bedsSt->execute();
                $bedsResult = $bedsSt->fetch();
                $bedsCount = (int)$bedsResult['count'];
                
                if ($bedsCount > 0) {
                    $suggestions[] = [
                        'type' => 'beds',
                        'text' => $num . ' beds',
                        'display' => $num . ' beds (' . number_format($bedsCount) . ' properties)',
                        'beds' => $num,
                        'count' => $bedsCount
                    ];
                }
            }
        }
    }
    
    // Limit to top 10 suggestions
    $suggestions = array_slice($suggestions, 0, 10);
    
    // Always return success with suggestions array (even if empty)
    echo json_encode([
        'success' => true,
        'query' => $query,
        'suggestions' => $suggestions
    ], JSON_UNESCAPED_UNICODE);
    
} catch (Throwable $e) {
    http_response_code(500);
    error_log('Search suggestions API error: ' . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Query failed',
        'message' => $e->getMessage()
    ], JSON_UNESCAPED_UNICODE);
    exit;
}

