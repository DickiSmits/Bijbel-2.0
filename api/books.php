<?php
/**
 * API: Books
 * Geef alle bijbelboeken terug
 */

require_once __DIR__ . '/../config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->query("
        SELECT DISTINCT Bijbelboeknaam, MIN(Vers_ID) as First_ID 
        FROM De_Bijbel 
        GROUP BY Bijbelboeknaam 
        ORDER BY First_ID
    ");
    
    $books = $stmt->fetchAll();
    
    echo json_encode($books);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
