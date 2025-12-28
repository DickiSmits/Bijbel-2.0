<?php
/**
 * API: Chapters
 * Geef hoofdstukken van een boek terug
 */

require_once __DIR__ . '/../config.php';

try {
    $boek = isset($_GET['boek']) ? $_GET['boek'] : null;
    
    if (!$boek) {
        throw new Exception('Boek parameter ontbreekt');
    }
    
    $db = Database::getInstance()->getConnection();
    
    $stmt = $db->prepare("
        SELECT DISTINCT Hoofdstuknummer 
        FROM De_Bijbel 
        WHERE Bijbelboeknaam = ? 
        ORDER BY CAST(Hoofdstuknummer AS INTEGER)
    ");
    $stmt->execute([$boek]);
    
    $chapters = $stmt->fetchAll();
    
    echo json_encode($chapters);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
