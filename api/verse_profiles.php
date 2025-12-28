<?php
/**
 * API Endpoint: verse_profiles
 * 
 * Geeft terug welke profielen een bewerking hebben voor een vers
 * 
 * GET ?api=verse_profiles&vers_id=123
 * 
 * Returns: [
 *   {Profiel_ID: 1, Profiel_Naam: "Studieversie", heeft_opmaak: true},
 *   {Profiel_ID: 2, Profiel_Naam: "Kinderversie", heeft_opmaak: true}
 * ]
 */

header('Content-Type: application/json');

try {
    $db_file = __DIR__ . '/../NWT-Bijbel.db';
    $db = new PDO("sqlite:$db_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $vers_id = isset($_GET['vers_id']) ? (int)$_GET['vers_id'] : null;
    
    if (!$vers_id) {
        echo json_encode(['error' => 'vers_id is verplicht']);
        exit;
    }
    
    // Haal alle profielen op die dit vers hebben bewerkt
    $stmt = $db->prepare("
        SELECT p.Profiel_ID, p.Profiel_Naam, 
               CASE WHEN o.Opgemaakte_Tekst IS NOT NULL THEN 1 ELSE 0 END as heeft_opmaak
        FROM Profielen p
        LEFT JOIN Opmaak o ON p.Profiel_ID = o.Profiel_ID AND o.Vers_ID = ?
        WHERE o.Opgemaakte_Tekst IS NOT NULL
        ORDER BY p.Profiel_Naam
    ");
    
    $stmt->execute([$vers_id]);
    $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode($profiles);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
