<?php
/**
 * API: get_timeline_group
 * Get single timeline group
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
    
    $stmt = $db->prepare("SELECT * FROM Timeline_Groups WHERE Group_ID = ?");
    $stmt->execute([$group_id]);
    $group = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$group) {
        http_response_code(404);
        echo json_encode(['error' => 'Group not found']);
        exit;
    }
    
    echo json_encode($group);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
