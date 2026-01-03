<?php
/**
 * API: Timeline Events
 * Geef timeline events terug (optioneel gefilterd op vers_id)
 */

require_once __DIR__ . '/../config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $vers_id = isset($_GET['vers_id']) ? (int)$_GET['vers_id'] : null;
    
    if ($vers_id) {
        $stmt = $db->prepare("
            SELECT e.*, g.Groep_Naam, g.Kleur as Groep_Kleur,
                   v1.Bijbelboeknaam as Start_Boek, 
                   v1.Hoofdstuknummer as Start_Hoofdstuk, 
                   v1.Versnummer as Start_Vers,
                   v2.Bijbelboeknaam as End_Boek, 
                   v2.Hoofdstuknummer as End_Hoofdstuk, 
                   v2.Versnummer as End_Vers
            FROM Timeline_Events e 
            LEFT JOIN Timeline_Groups g ON e.Group_ID = g.Group_ID
            LEFT JOIN De_Bijbel v1 ON e.Vers_ID_Start = v1.Vers_ID
            LEFT JOIN De_Bijbel v2 ON e.Vers_ID_End = v2.Vers_ID
            WHERE (e.Vers_ID_Start <= ? AND e.Vers_ID_End >= ?) 
               OR (e.Vers_ID_Start = ? AND e.Vers_ID_End IS NULL)
            ORDER BY e.Start_Datum
        ");
        $stmt->execute([$vers_id, $vers_id, $vers_id]);
    } else {
        $stmt = $db->query("
            SELECT e.*, g.Groep_Naam, g.Kleur as Groep_Kleur,
                   v1.Bijbelboeknaam as Start_Boek, 
                   v1.Hoofdstuknummer as Start_Hoofdstuk, 
                   v1.Versnummer as Start_Vers,
                   v2.Bijbelboeknaam as End_Boek, 
                   v2.Hoofdstuknummer as End_Hoofdstuk, 
                   v2.Versnummer as End_Vers
            FROM Timeline_Events e 
            LEFT JOIN Timeline_Groups g ON e.Group_ID = g.Group_ID
            LEFT JOIN De_Bijbel v1 ON e.Vers_ID_Start = v1.Vers_ID
            LEFT JOIN De_Bijbel v2 ON e.Vers_ID_End = v2.Vers_ID
            ORDER BY e.Start_Datum
        ");
    }
    
    $events = $stmt->fetchAll();
    
    echo json_encode($events);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
