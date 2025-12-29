<?php
/**
 * API: delete_note
 * Verwijder notitie
 */

require_once __DIR__ . '/../config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $note_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($note_id === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid ID']);
        exit;
    }
    
    $stmt = $db->prepare("DELETE FROM Notities WHERE Notitie_ID = ?");
    $stmt->execute([$note_id]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
