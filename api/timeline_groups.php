<?php
/**
 * API: Timeline Groups
 * Geef timeline groepen terug
 */

require_once __DIR__ . '/../config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->query("
        SELECT * FROM Timeline_Groups 
        ORDER BY Volgorde, Groep_Naam
    ");
    
    $groups = $stmt->fetchAll();
    
    echo json_encode($groups);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
