<?php
/**
 * API: Save Formatting
 * Slaat opgemaakte tekst op voor een vers in een profiel
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getConnection();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    // Validatie
    if (!isset($input['vers_id']) || !isset($input['profiel_id']) || !isset($input['tekst'])) {
        http_response_code(400);
        echo json_encode(['error' => 'vers_id, profiel_id en tekst zijn verplicht']);
        exit;
    }
    
    $vers_id = (int)$input['vers_id'];
    $profiel_id = (int)$input['profiel_id'];
    $tekst = $input['tekst'];
    
    // Insert or replace opmaak
    $stmt = $db->prepare("INSERT OR REPLACE INTO Opmaak 
                         (Profiel_ID, Vers_ID, Opgemaakte_Tekst, Laatst_Gewijzigd) 
                         VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
    $stmt->execute([$profiel_id, $vers_id, $tekst]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Opmaak opgeslagen'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
