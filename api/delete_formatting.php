<?php
/**
 * API: Delete Formatting
 * Verwijdert opgemaakte tekst voor een vers
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getConnection();
    
    // Get parameters from query string
    if (!isset($_GET['vers_id']) || !isset($_GET['profiel_id'])) {
        http_response_code(400);
        echo json_encode(['error' => 'vers_id en profiel_id zijn verplicht']);
        exit;
    }
    
    $vers_id = (int)$_GET['vers_id'];
    $profiel_id = (int)$_GET['profiel_id'];
    
    // Delete opmaak
    $stmt = $db->prepare("DELETE FROM Opmaak WHERE Profiel_ID = ? AND Vers_ID = ?");
    $stmt->execute([$profiel_id, $vers_id]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Opmaak verwijderd'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
