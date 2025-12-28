<?php
/**
 * API: Locations
 * Geef locaties terug (optioneel gefilterd op vers_id)
 */

require_once __DIR__ . '/../config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $vers_id = isset($_GET['vers_id']) ? (int)$_GET['vers_id'] : null;
    
    if ($vers_id) {
        $stmt = $db->prepare("
            SELECT l.*, vl.Context 
            FROM Locaties l 
            JOIN Vers_Locaties vl ON l.Locatie_ID = vl.Locatie_ID 
            WHERE vl.Vers_ID = ?
        ");
        $stmt->execute([$vers_id]);
    } else {
        $stmt = $db->query("
            SELECT l.*, 
                   GROUP_CONCAT(v.Bijbelboeknaam || ' ' || v.Hoofdstuknummer || ':' || v.Versnummer, ', ') as Gekoppelde_Verzen
            FROM Locaties l 
            LEFT JOIN Vers_Locaties vl ON l.Locatie_ID = vl.Locatie_ID
            LEFT JOIN De_Bijbel v ON vl.Vers_ID = v.Vers_ID
            GROUP BY l.Locatie_ID
        ");
    }
    
    $locations = $stmt->fetchAll();
    
    echo json_encode($locations);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
