<?php
/**
 * NLP Query Parser API
 * Parses natural language property search queries using OpenAI GPT-4
 * Falls back to regex patterns if API unavailable
 */

header('Content-Type: application/json');

// Get query from POST or GET
$query = isset($_POST['query']) ? trim($_POST['query']) : (isset($_GET['query']) ? trim($_GET['query']) : '');

if (empty($query)) {
    echo json_encode(['error' => 'No query provided']);
    exit;
}

// Load OpenAI API key from environment or config
$openai_key = getenv('OPENAI_API_KEY');
if (!$openai_key && file_exists(__DIR__ . '/../config.php')) {
    require_once __DIR__ . '/../config.php';
    $config = get_api_config();
    $openai_key = $config['openai_api_key'] ?? '';
}

$result = [
    'original_query' => $query,
    'city' => '',
    'zip' => '',
    'price_min' => '',
    'price_max' => '',
    'beds' => '',
    'baths' => '',
    'sqft_min' => '',
    'sqft_max' => '',
    'keywords' => []
];

// Try OpenAI first if key available
if ($openai_key) {
    $result = parse_with_openai($query, $openai_key);
} else {
    $result = parse_with_regex($query);
}

echo json_encode($result);
exit;

/**
 * Parse query using OpenAI GPT-4
 */
function parse_with_openai($query, $api_key) {
    $prompt = 'You are a real estate search query parser. Extract structured data from the following property search query.

Query: "' . $query . '"

Return ONLY valid JSON with these fields (use empty string if not found):
{
  "city": "city name if mentioned",
  "zip": "zip code if mentioned",
  "price_min": "minimum price as integer, no $ or commas",
  "price_max": "maximum price as integer, no $ or commas",
  "beds": "minimum bedrooms as integer",
  "baths": "minimum bathrooms as integer",
  "sqft_min": "minimum square feet as integer",
  "sqft_max": "maximum square feet as integer",
  "keywords": ["array", "of", "important", "keywords"]
}

Examples:
"3 bedroom house in Los Angeles under 800k" → {"city":"Los Angeles","price_max":"800000","beds":"3","keywords":["house"]}
"2br condo San Diego 90210 between 500k-700k" → {"city":"San Diego","zip":"90210","price_min":"500000","price_max":"700000","beds":"2","keywords":["condo"]}
"spacious home with pool 2000+ sqft" → {"sqft_min":"2000","keywords":["spacious","pool"]}';

    $data = [
        'model' => 'gpt-4o-mini',
        'messages' => [
            ['role' => 'system', 'content' => 'You are a JSON-only API. Return only valid JSON, no explanations.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.1,
        'max_tokens' => 500
    ];

    $ch = curl_init('https://api.openai.com/v1/chat/completions');
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Bearer ' . $api_key
        ],
        CURLOPT_TIMEOUT => 10
    ]);

    $response = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    curl_close($ch);

    if ($http_code === 200 && $response) {
        $json = json_decode($response, true);
        if (isset($json['choices'][0]['message']['content'])) {
            $content = trim($json['choices'][0]['message']['content']);
            // Remove markdown code blocks if present
            $content = preg_replace('/```json\s*/', '', $content);
            $content = preg_replace('/```\s*$/', '', $content);
            
            $parsed = json_decode($content, true);
            if ($parsed) {
                $parsed['original_query'] = $query;
                $parsed['method'] = 'openai';
                return $parsed;
            }
        }
    }

    // Fallback to regex
    return parse_with_regex($query);
}

/**
 * Parse query using regex patterns (fallback)
 */
function parse_with_regex($query) {
    $result = [
        'original_query' => $query,
        'method' => 'regex',
        'city' => '',
        'zip' => '',
        'price_min' => '',
        'price_max' => '',
        'beds' => '',
        'baths' => '',
        'sqft_min' => '',
        'sqft_max' => '',
        'keywords' => []
    ];

    $lower = strtolower($query);

    // Extract city using fuzzy matching from database
    // First try common abbreviations and exact matches
    $cityAbbrevs = [
        'la' => 'Los Angeles',
        'sf' => 'San Francisco',
        'sd' => 'San Diego',
        'sj' => 'San Jose',
        'sac' => 'Sacramento'
    ];
    
    foreach ($cityAbbrevs as $abbrev => $fullName) {
        if (preg_match('/\b' . preg_quote($abbrev, '/') . '\b/i', $query)) {
            $result['city'] = $fullName;
            break; // Found abbreviation, continue with other parsing
        }
    }
    
    // If city not found via abbreviation, try database fuzzy matching
    if (empty($result['city'])) {
        // Try to find city in database with fuzzy matching
        try {
            // Load database config
            $DB_HOST = 'localhost';
            $DB_NAME = 'boxgra6_cali';
            $DB_USER = 'boxgra6_sd';
            $DB_PASS = 'Real_estate650$';
            
            $dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            ];
            
            $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
            
            // Extract potential city name from query (words that might be city names)
            // Look for 2-3 word sequences that might be city names
            $words = preg_split('/\s+/', $query);
            $potentialCityQueries = [];
            
            // Single word
            if (count($words) >= 1 && strlen($words[0]) >= 3) {
                $potentialCityQueries[] = $words[0];
            }
            
            // Two words
            if (count($words) >= 2) {
                $potentialCityQueries[] = $words[0] . ' ' . $words[1];
            }
            
            // Three words
            if (count($words) >= 3) {
                $potentialCityQueries[] = $words[0] . ' ' . $words[1] . ' ' . $words[2];
            }
            
            // Try exact/partial match first
            $foundCity = null;
            foreach ($potentialCityQueries as $cityQuery) {
                $searchTerm = '%' . $cityQuery . '%';
                $sql = "SELECT DISTINCT L_City FROM rets_property WHERE L_City LIKE :search LIMIT 1";
                $st = $pdo->prepare($sql);
                $st->bindValue(':search', $searchTerm);
                $st->execute();
                $row = $st->fetch();
                if ($row) {
                    $foundCity = $row['L_City'];
                    break;
                }
            }
            
            // If no exact match, try fuzzy matching
            if (!$foundCity && !empty($potentialCityQueries)) {
                $cityQuery = $potentialCityQueries[0]; // Use first potential city
                $sqlAll = "SELECT DISTINCT L_City FROM rets_property ORDER BY L_City";
                $stAll = $pdo->prepare($sqlAll);
                $stAll->execute();
                $allCities = $stAll->fetchAll();
                
                $queryLower = strtolower($cityQuery);
                $bestMatch = null;
                $bestSimilarity = 0;
                
                foreach ($allCities as $row) {
                    $city = $row['L_City'];
                    $cityLower = strtolower($city);
                    
                    // Calculate similarity using Levenshtein distance
                    $distance = levenshtein($queryLower, $cityLower);
                    $maxLen = max(strlen($queryLower), strlen($cityLower));
                    $similarity = $maxLen > 0 ? (1 - ($distance / $maxLen)) : 0;
                    
                    // Boost substring matches
                    if (strpos($cityLower, $queryLower) !== false || strpos($queryLower, $cityLower) !== false) {
                        $similarity = max($similarity, 0.7);
                    }
                    
                    if ($similarity > $bestSimilarity && $similarity >= 0.6) {
                        $bestSimilarity = $similarity;
                        $bestMatch = $city;
                    }
                }
                
                if ($bestMatch) {
                    $foundCity = $bestMatch;
                }
            }
            
            if ($foundCity) {
                $result['city'] = $foundCity;
            }
        } catch (Exception $e) {
            // Fallback to hardcoded list if database fails
            $cities = [
                'los angeles', 'la', 'san diego', 'san francisco', 'sf', 'sacramento',
                'san jose', 'fresno', 'long beach', 'oakland', 'bakersfield',
                'anaheim', 'santa ana', 'riverside', 'irvine', 'stockton'
            ];
            
            foreach ($cities as $city) {
                if (strpos($lower, $city) !== false) {
                    $result['city'] = ucwords($city);
                    break;
                }
            }
        }
    }

    // Extract ZIP code
    if (preg_match('/\b(\d{5})\b/', $query, $m)) {
        $result['zip'] = $m[1];
    }

    // Extract price
    // "under 800k", "below 1.5m", "500k-700k", "between 600000 and 800000"
    if (preg_match('/under|below|max|<\s*(\$?\s*[\d,.]+[km]?)/i', $query, $m)) {
        $result['price_max'] = parse_price($m[1]);
    }
    if (preg_match('/above|over|min|>\s*(\$?\s*[\d,.]+[km]?)/i', $query, $m)) {
        $result['price_min'] = parse_price($m[1]);
    }
    if (preg_match('/(\$?\s*[\d,.]+[km]?)\s*-\s*(\$?\s*[\d,.]+[km]?)/i', $query, $m)) {
        $result['price_min'] = parse_price($m[1]);
        $result['price_max'] = parse_price($m[2]);
    }
    if (preg_match('/between\s+(\$?\s*[\d,.]+[km]?)\s+and\s+(\$?\s*[\d,.]+[km]?)/i', $query, $m)) {
        $result['price_min'] = parse_price($m[1]);
        $result['price_max'] = parse_price($m[2]);
    }

    // Extract bedrooms
    if (preg_match('/(\d+)\s*(bed|br|bedroom)/i', $query, $m)) {
        $result['beds'] = $m[1];
    }

    // Extract bathrooms
    if (preg_match('/(\d+(?:\.\d+)?)\s*(bath|ba|bathroom)/i', $query, $m)) {
        $result['baths'] = $m[1];
    }

    // Extract square feet
    if (preg_match('/(\d+)\+?\s*sq\s*ft|(\d+)\+?\s*sqft|(\d+)\+?\s*square feet/i', $query, $m)) {
        $result['sqft_min'] = $m[1] ?: ($m[2] ?: $m[3]);
    }
    if (preg_match('/(\d+)\s*-\s*(\d+)\s*sq\s*ft/i', $query, $m)) {
        $result['sqft_min'] = $m[1];
        $result['sqft_max'] = $m[2];
    }

    // Extract keywords - handle phrases first, then individual words
    $property_phrases = [
        'ocean view', 'oceanfront', 'beachfront', 'waterfront',
        'mountain view', 'city view', 'panoramic view',
        'swimming pool', 'heated pool', 'pool',
        'garage', 'parking',
        'spacious', 'modern', 'updated', 'luxury', 'renovated', 'new',
        'condo', 'condominium', 'house', 'townhouse', 'apartment',
        'family', 'school', 'downtown', 'downtown'
    ];
    
    // First, check for phrases (multi-word keywords)
    foreach ($property_phrases as $phrase) {
        if (stripos($lower, $phrase) !== false) {
            $result['keywords'][] = $phrase;
            // Remove the phrase from the query to avoid duplicate extraction
            $lower = str_ireplace($phrase, '', $lower);
        }
    }
    
    // Then check for individual important words that weren't part of phrases
    $property_keywords = ['pool', 'garage', 'spacious', 'modern', 'updated', 'luxury', 
                         'condo', 'house', 'townhouse', 'family', 'school', 'downtown',
                         'view', 'ocean', 'mountain', 'new', 'renovated', 'beach', 'water'];
    foreach ($property_keywords as $kw) {
        if (stripos($lower, $kw) !== false && !in_array($kw, $result['keywords'])) {
            // Check if this keyword isn't already part of a phrase we extracted
            $is_part_of_phrase = false;
            foreach ($result['keywords'] as $extracted) {
                if (stripos($extracted, $kw) !== false) {
                    $is_part_of_phrase = true;
                    break;
                }
            }
            if (!$is_part_of_phrase) {
                $result['keywords'][] = $kw;
            }
        }
    }
    
    // Remove duplicates and empty values
    $result['keywords'] = array_values(array_unique(array_filter($result['keywords'])));

    return $result;
}

/**
 * Parse price from various formats
 */
function parse_price($str) {
    $str = strtolower(trim($str));
    $str = str_replace(['$', ',', ' '], '', $str);
    
    if (strpos($str, 'k') !== false) {
        return (int)(floatval(str_replace('k', '', $str)) * 1000);
    }
    if (strpos($str, 'm') !== false) {
        return (int)(floatval(str_replace('m', '', $str)) * 1000000);
    }
    return (int)$str;
}
