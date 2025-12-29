<?php
/**
 * API: get_location
 * Haal één locatie op
 */

require_once __DIR__ . '/../config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $location_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($location_id === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid ID']);
        exit;
    }
    
    $stmt = $db->prepare("SELECT * FROM Locaties WHERE Locatie_ID = ?");
    $stmt->execute([$location_id]);
    $location = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$location) {
        http_response_code(404);
        echo json_encode(['error' => 'Location not found']);
        exit;
    }
    
    echo json_encode($location);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
