<?php
/**
 * API: Profiles
 * Geef alle profielen terug
 */

require_once __DIR__ . '/../config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->query("
        SELECT * FROM Profielen 
        ORDER BY Profiel_Naam
    ");
    
    $profiles = $stmt->fetchAll();
    
    echo json_encode($profiles);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
