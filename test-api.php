<?php
/**
 * Simple API Test
 * Direct test zonder dependencies
 */

header('Content-Type: application/json');

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo json_encode([
    'success' => true,
    'message' => 'API werkt!',
    'timestamp' => date('Y-m-d H:i:s'),
    'get_params' => $_GET,
    'server_info' => [
        'php_version' => phpversion(),
        'current_dir' => __DIR__,
        'config_exists' => file_exists(__DIR__ . '/config.php'),
        'db_exists' => file_exists(__DIR__ . '/NWT-Bijbel.db'),
    ]
], JSON_PRETTY_PRINT);
