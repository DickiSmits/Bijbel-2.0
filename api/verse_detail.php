<?php
/**
 * API: Verse Detail
 * Geef gedetailleerde informatie over een specifiek vers
 */

require_once __DIR__ . '/../config.php';

try {
    $vers_id = isset($_GET['vers_id']) ? (int)$_GET['vers_id'] : null;
    $profiel_id = isset($_GET['profiel_id']) ? (int)$_GET['profiel_id'] : null;
    
    if (!$vers_id) {
        throw new Exception('vers_id parameter ontbreekt');
    }
    
    $db = Database::getInstance()->getConnection();
    
    if ($profiel_id) {
        $stmt = $db->prepare("
            SELECT v.*, o.Opgemaakte_Tekst 
            FROM De_Bijbel v 
            LEFT JOIN Opmaak o ON v.Vers_ID = o.Vers_ID AND o.Profiel_ID = ?
            WHERE v.Vers_ID = ?
        ");
        $stmt->execute([$profiel_id, $vers_id]);
    } else {
        $stmt = $db->prepare("
            SELECT v.*, NULL as Opgemaakte_Tekst 
            FROM De_Bijbel v 
            WHERE v.Vers_ID = ?
        ");
        $stmt->execute([$vers_id]);
    }
    
    $verse = $stmt->fetch();
    
    if (!$verse) {
        throw new Exception('Vers niet gevonden');
    }
    
    echo json_encode($verse);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
