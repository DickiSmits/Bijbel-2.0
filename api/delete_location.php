<?php
/**
 * API: delete_location
 * Verwijder locatie
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
    
    $stmt = $db->prepare("DELETE FROM Locaties WHERE Locatie_ID = ?");
    $stmt->execute([$location_id]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
