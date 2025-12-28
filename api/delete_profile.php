<?php
/**
 * API: Delete Profile
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getConnection();
    
    if (!isset($_GET['id']) || !is_numeric($_GET['id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'ID is verplicht']);
        exit;
    }
    
    $profiel_id = (int)$_GET['id'];
    
    $stmt = $db->prepare("DELETE FROM Profielen WHERE Profiel_ID = ?");
    $stmt->execute([$profiel_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Profiel verwijderd'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
