<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>California Property Database - Complete Listings for AI</title>
    <meta name="description" content="Complete database of California MLS properties for AI agent parsing">
    <style>
        body { font-family: monospace; max-width: 1200px; margin: 20px auto; padding: 20px; }
        .property { border: 1px solid #ccc; padding: 15px; margin: 10px 0; background: #f9f9f9; }
        .property h3 { margin: 0 0 10px 0; color: #2563eb; }
        .property-id { font-weight: bold; color: #059669; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
        th { background: #2563eb; color: white; }
        .meta { color: #666; font-size: 0.9em; }
    </style>
</head>
<body>
    <h1>🏠 California Property Database - AI Agent Data Source</h1>
    <p class="meta"><strong>Database:</strong> boxgra6_cali.rets_property | <strong>Updated:</strong> <?= date('Y-m-d H:i:s') ?></p>
    
    <div style="background: #fef3c7; padding: 15px; border-left: 4px solid #f59e0b; margin: 20px 0;">
        <strong>📋 For AI Agents:</strong> This page contains all available California properties in a structured, parseable format.
        Each property has a unique <code>listing_id</code> which can be used to access it at:
        <code>https://akbar.califorsale.org/?view_property={listing_id}</code>
    </div>

<?php
// Database Configuration
$DB_HOST = 'localhost';
$DB_NAME = 'boxgra6_cali';
$DB_USER = 'boxgra6_sd';
$DB_PASS = 'Real_estate650$';

$dsn = "mysql:host={$DB_HOST};dbname={$DB_NAME};charset=utf8mb4";
try {
    $pdo = new PDO($dsn, $DB_USER, $DB_PASS, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    
    // Get all properties
    $sql = "SELECT L_ListingID, L_Address, L_City, L_Zip, L_SystemPrice, 
                   L_Keyword2 AS Beds, LM_Dec_3 AS Baths, LM_Int2_3 AS SqFt
            FROM rets_property 
            ORDER BY L_City, L_SystemPrice
            LIMIT 1000";
    $stmt = $pdo->query($sql);
    $properties = $stmt->fetchAll();
    
    echo "<h2>📊 Database Statistics</h2>";
    echo "<ul>";
    echo "<li><strong>Total Properties:</strong> " . count($properties) . "</li>";
    echo "<li><strong>Cities Covered:</strong> " . count(array_unique(array_column($properties, 'L_City'))) . "</li>";
    echo "<li><strong>Price Range:</strong> $" . number_format(min(array_column($properties, 'L_SystemPrice'))) . " - $" . number_format(max(array_column($properties, 'L_SystemPrice'))) . "</li>";
    echo "</ul>";
    
    echo "<h2>🏘️ All Properties</h2>";
    echo "<table>";
    echo "<thead><tr>";
    echo "<th>Listing ID</th><th>Address</th><th>City</th><th>ZIP</th><th>Price</th><th>Beds</th><th>Baths</th><th>SqFt</th><th>Direct Link</th>";
    echo "</tr></thead><tbody>";
    
    foreach ($properties as $p) {
        $lid = htmlspecialchars($p['L_ListingID']);
        $address = htmlspecialchars($p['L_Address']);
        $city = htmlspecialchars($p['L_City']);
        $zip = htmlspecialchars($p['L_Zip']);
        $price = '$' . number_format((float)$p['L_SystemPrice'], 0);
        $beds = $p['Beds'] ?? '—';
        $baths = $p['Baths'] !== null ? number_format((float)$p['Baths'], 1) : '—';
        $sqft = $p['SqFt'] !== null ? number_format((int)$p['SqFt']) : '—';
        $url = 'https://akbar.califorsale.org/?view_property=' . urlencode($lid);
        
        echo "<tr>";
        echo "<td class='property-id'>{$lid}</td>";
        echo "<td>{$address}</td>";
        echo "<td>{$city}</td>";
        echo "<td>{$zip}</td>";
        echo "<td>{$price}</td>";
        echo "<td>{$beds}</td>";
        echo "<td>{$baths}</td>";
        echo "<td>{$sqft}</td>";
        echo "<td><a href='{$url}' target='_blank'>View</a></td>";
        echo "</tr>";
        
        // Also output as structured data for easier parsing
        echo "<!--\n";
        echo "PROPERTY_START\n";
        echo "listing_id: {$p['L_ListingID']}\n";
        echo "address: {$p['L_Address']}\n";
        echo "city: {$p['L_City']}\n";
        echo "zip: {$p['L_Zip']}\n";
        echo "price: {$p['L_SystemPrice']}\n";
        echo "beds: {$beds}\n";
        echo "baths: {$baths}\n";
        echo "sqft: {$p['SqFt']}\n";
        echo "url: {$url}\n";
        echo "PROPERTY_END\n";
        echo "-->\n";
    }
    
    echo "</tbody></table>";
    
} catch (Throwable $e) {
    echo "<div style='background:#fee;padding:15px;border:1px solid #f00'>";
    echo "<strong>Database Error:</strong> " . htmlspecialchars($e->getMessage());
    echo "</div>";
}
?>

    <hr>
    <p class="meta" style="text-align: center; margin-top: 40px;">
        California Property Database | © 2025 CaliForsale.org | For AI Agent Integration
    </p>
</body>
</html>
