<?php
/**
 * API: delete_timeline_group
 * Verwijder timeline groep
 */

require_once __DIR__ . '/../config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $group_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($group_id === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid ID']);
        exit;
    }
    
    $stmt = $db->prepare("DELETE FROM Timeline_Groups WHERE Group_ID = ?");
    $stmt->execute([$group_id]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
