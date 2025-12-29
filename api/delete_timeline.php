<?php
/**
 * API Endpoint: delete_timeline
 * DELETE timeline event by ID
 */

require_once __DIR__ . '/../config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $event_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($event_id === 0) {
        echo json_encode(['error' => 'Invalid ID']);
        exit;
    }
    
    $stmt = $db->prepare("DELETE FROM Timeline_Events WHERE Event_ID = ?");
    $stmt->execute([$event_id]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
