<?php
/**
 * API: All Formatting
 * Haalt alle bewerkte verzen op met profiel en vers info
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getConnection();
    
    // Get all formatted verses with profile and verse info
    $stmt = $db->query("SELECT o.*, p.Profiel_Naam, 
                       v.Bijbelboeknaam, v.Hoofdstuknummer, v.Versnummer, v.Tekst as Originele_Tekst
                       FROM Opmaak o
                       JOIN Profielen p ON o.Profiel_ID = p.Profiel_ID
                       JOIN De_Bijbel v ON o.Vers_ID = v.Vers_ID
                       ORDER BY v.Bijbelboeknaam, v.Hoofdstuknummer, v.Versnummer");
    
    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
