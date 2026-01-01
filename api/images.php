<?php
/**
 * API/IMAGES.PHP - DEBUG VERSION
 * Shows exact errors for troubleshooting
 */

// Turn on error reporting
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display, we'll JSON encode them

// Catch all errors
$errors = [];

try {
    // Step 1: Check if Database class exists
    if (!class_exists('Database')) {
        $errors[] = "Step 1 FAILED: Database class not found. Config.php not loaded?";
        throw new Exception('Database class not found');
    }
    $errors[] = "Step 1 OK: Database class exists";
    
    // Step 2: Get instance
    $dbInstance = Database::getInstance();
    if (!$dbInstance) {
        $errors[] = "Step 2 FAILED: Database::getInstance() returned null";
        throw new Exception('getInstance failed');
    }
    $errors[] = "Step 2 OK: Database::getInstance() worked";
    
    // Step 3: Get connection
    $db = $dbInstance->getConnection();
    if (!$db) {
        $errors[] = "Step 3 FAILED: getConnection() returned null";
        throw new Exception('getConnection failed');
    }
    $errors[] = "Step 3 OK: Database connection established";
    
    // Step 4: Check if Afbeeldingen table exists
    $tableCheck = $db->query("SELECT name FROM sqlite_master WHERE type='table' AND name='Afbeeldingen'");
    if (!$tableCheck->fetch()) {
        $errors[] = "Step 4 WARNING: Afbeeldingen table does not exist";
        // Don't throw - we'll return empty array
        echo json_encode([
            'debug' => true,
            'errors' => $errors,
            'message' => 'Table does not exist - returning empty array',
            'data' => []
        ]);
        exit;
    }
    $errors[] = "Step 4 OK: Afbeeldingen table exists";
    
    // Step 5: Try to query
    $stmt = $db->query("
        SELECT a.*, 
               v.Bijbelboeknaam, 
               v.Hoofdstuknummer, 
               v.Versnummer
        FROM Afbeeldingen a
        LEFT JOIN Verzen v ON a.Vers_ID = v.Vers_ID
        ORDER BY a.Geupload_Op DESC
        LIMIT 10
    ");
    
    $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $errors[] = "Step 5 OK: Query executed - found " . count($images) . " images";
    
    // Success!
    echo json_encode([
        'debug' => true,
        'errors' => $errors,
        'message' => 'Success!',
        'count' => count($images),
        'data' => $images
    ]);
    
} catch (Exception $e) {
    // Error occurred
    $errors[] = "EXCEPTION: " . $e->getMessage();
    $errors[] = "FILE: " . $e->getFile() . " LINE: " . $e->getLine();
    
    http_response_code(500);
    echo json_encode([
        'debug' => true,
        'errors' => $errors,
        'exception' => $e->getMessage(),
        'trace' => $e->getTraceAsString()
    ]);
}