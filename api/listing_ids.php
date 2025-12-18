<?php
/**
 * Property Sitemap - List all listing IDs for AI agent
 * Simple text format that won't trigger ModSecurity
 */
header('Content-Type: text/plain');

$DB_HOST = 'localhost';
$DB_NAME = 'boxgra6_cali';
$DB_USER = 'boxgra6_sd';
$DB_PASS = 'Real_estate650$';

try {
    $pdo = new PDO("mysql:host=$DB_HOST;dbname=$DB_NAME;charset=utf8mb4", $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    ]);
    
    $stmt = $pdo->query("SELECT L_ListingID, L_City, L_SystemPrice, L_Keyword2 AS Beds FROM rets_property ORDER BY L_City LIMIT 2000");
    
    echo "# California Property Listing IDs\n";
    echo "# Generated: " . date('Y-m-d H:i:s') . "\n";
    echo "# Format: listing_id|city|price|beds\n";
    echo "# To view: https://akbar.califorsale.org/?view_property={listing_id}\n\n";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo $row['L_ListingID'] . '|' . $row['L_City'] . '|' . $row['L_SystemPrice'] . '|' . ($row['Beds'] ?? '0') . "\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
