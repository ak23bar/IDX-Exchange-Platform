<?php
/**
 * Generate Static Properties JSON Export
 * Run this script periodically (via cron) to export all properties to JSON
 * ElevenLabs can then read this static file directly
 * 
 * Usage: php generate_properties_json.php
 * Or via browser: https://akbar.califorsale.org/api/generate_properties_json.php
 */

// Database Configuration
$DB_HOST = 'localhost';
$DB_NAME = 'boxgra6_cali';
$DB_USER = 'boxgra6_sd';
$DB_PASS = 'Real_estate650$';

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
$options = [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
];

try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, $options);
} catch (Throwable $e) {
    die(json_encode(['error' => 'Database connection failed', 'message' => $e->getMessage()]));
}

// Get all properties with relevant fields
$sql = "
    SELECT 
        L_ListingID as listing_id,
        L_Address as address,
        L_City as city,
        L_Zip as zip,
        L_SystemPrice as price,
        L_Keyword2 as beds,
        LM_Dec_3 as baths,
        LM_Int2_3 as sqft,
        L_Photos as photos
    FROM rets_property 
    ORDER BY L_City, L_SystemPrice
    LIMIT 5000
";

$stmt = $pdo->query($sql);
$properties = $stmt->fetchAll();

// Process properties
$processed = [];
foreach ($properties as $prop) {
    // Get first photo
    $photos = $prop['photos'] ? explode(',', $prop['photos']) : [];
    $first_photo = !empty($photos) ? trim($photos[0]) : null;
    
    $processed[] = [
        'listing_id' => trim($prop['listing_id']),
        'address' => trim($prop['address']),
        'city' => trim($prop['city']),
        'state' => 'CA',
        'zip' => trim($prop['zip']),
        'price' => (int)$prop['price'],
        'price_formatted' => '$' . number_format((float)$prop['price'], 0),
        'beds' => $prop['beds'] !== null ? (int)$prop['beds'] : null,
        'baths' => $prop['baths'] !== null ? (float)$prop['baths'] : null,
        'sqft' => $prop['sqft'] !== null ? (int)$prop['sqft'] : null,
        'photo' => $first_photo,
        'url' => 'https://akbar.califorsale.org/?view_property=' . urlencode($prop['listing_id'])
    ];
}

// Generate metadata
$metadata = [
    'generated_at' => date('Y-m-d H:i:s'),
    'total_properties' => count($processed),
    'cities' => array_values(array_unique(array_column($processed, 'city'))),
    'price_range' => [
        'min' => min(array_column($processed, 'price')),
        'max' => max(array_column($processed, 'price')),
        'avg' => round(array_sum(array_column($processed, 'price')) / count($processed))
    ]
];

// Create final structure
$output = [
    'metadata' => $metadata,
    'properties' => $processed
];

// Write to file
$json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
file_put_contents(__DIR__ . '/properties_database.json', $json);

// Also output to browser if accessed via HTTP
if (php_sapi_name() !== 'cli') {
    header('Content-Type: application/json');
    echo $json;
}

echo "\n✅ Generated properties_database.json with " . count($processed) . " properties\n";
echo "📁 File location: " . __DIR__ . "/properties_database.json\n";
echo "🌐 Public URL: https://akbar.califorsale.org/api/properties_database.json\n";
