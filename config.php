<?php
/**
 * Database Configuration Loader
 * 
 * This file loads database credentials from environment variables or a secrets file.
 * Include this file in your PHP scripts instead of hardcoding credentials.
 * 
 * Usage:
 *   require_once 'config.php';
 *   $pdo = get_database_connection();
 */

// Determine secrets file location
$secrets_file = getenv('IDX_SECRETS_FILE') ?: '/home/' . get_current_user() . '/.idx_secrets.php';

// Load configuration from secrets file if it exists
if (file_exists($secrets_file)) {
    $config = require $secrets_file;
} else {
    // Fallback to environment variables
    $config = [
        'db_host' => getenv('DB_HOST') ?: 'localhost',
        'db_name' => getenv('DB_NAME') ?: '',
        'db_user' => getenv('DB_USER') ?: '',
        'db_pass' => getenv('DB_PASS') ?: '',
        'trestle_client_id' => getenv('TRESTLE_CLIENT_ID') ?: '',
        'trestle_client_secret' => getenv('TRESTLE_CLIENT_SECRET') ?: '',
        'token_url' => getenv('TRESTLE_TOKEN_URL') ?: 'https://api-trestle.corelogic.com/trestle/oidc/token',
        'token_type' => 'trestle'
    ];
}

/**
 * Get PDO database connection
 * 
 * @return PDO Database connection instance
 * @throws Exception If connection fails
 */
function get_database_connection() {
    global $config;
    
    $dsn = sprintf('mysql:host=%s;dbname=%s;charset=utf8mb4', 
        $config['db_host'], 
        $config['db_name']
    );
    
    $options = [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ];
    
    try {
        return new PDO($dsn, $config['db_user'], $config['db_pass'], $options);
    } catch (Throwable $e) {
        error_log('Database connection failed: ' . $e->getMessage());
        throw new Exception('Database connection error');
    }
}

/**
 * Get Trestle API configuration
 * 
 * @return array API configuration
 */
function get_api_config() {
    global $config;
    
    return [
        'client_id' => $config['trestle_client_id'],
        'client_secret' => $config['trestle_client_secret'],
        'token_url' => $config['token_url'],
        'token_type' => $config['token_type']
    ];
}
