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

    // Extract city (common CA cities)
    $cities = [
        'los angeles', 'la', 'san diego', 'san francisco', 'sf', 'sacramento',
        'san jose', 'fresno', 'long beach', 'oakland', 'bakersfield',
        'anaheim', 'santa ana', 'riverside', 'irvine', 'stockton',
        'chula vista', 'fremont', 'san bernardino', 'modesto', 'fontana',
        'oxnard', 'moreno valley', 'glendale', 'huntington beach', 'santa clarita',
        'garden grove', 'oceanside', 'rancho cucamonga', 'santa rosa', 'ontario',
        'pasadena', 'corona', 'elk grove', 'palmdale', 'salinas', 'pomona',
        'hayward', 'escondido', 'torrance', 'sunnyvale', 'orange', 'fullerton',
        'thousand oaks', 'visalia', 'simi valley', 'concord', 'roseville'
    ];
    
    foreach ($cities as $city) {
        if (strpos($lower, $city) !== false) {
            $result['city'] = ucwords($city);
            break;
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

    // Extract keywords
    $property_keywords = ['pool', 'garage', 'spacious', 'modern', 'updated', 'luxury', 
                         'condo', 'house', 'townhouse', 'family', 'school', 'downtown',
                         'view', 'ocean', 'mountain', 'new', 'renovated'];
    foreach ($property_keywords as $kw) {
        if (stripos($lower, $kw) !== false) {
            $result['keywords'][] = $kw;
        }
    }

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
