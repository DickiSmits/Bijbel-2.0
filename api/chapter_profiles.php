<?php
/**
 * API Endpoint: chapter_profiles
 * 
 * Geeft voor alle verzen in een hoofdstuk terug welke profielen bewerkingen hebben
 * 
 * GET ?api=chapter_profiles&boek=Genesis&hoofdstuk=1
 * 
 * Returns: {
 *   "1": [{Profiel_ID: 1, Profiel_Naam: "Studie"}, {Profiel_ID: 2, Profiel_Naam: "Kind"}],
 *   "2": [{Profiel_ID: 1, Profiel_Naam: "Studie"}],
 *   ...
 * }
 */

header('Content-Type: application/json');

try {
    $db_file = __DIR__ . '/../NWT-Bijbel.db';
    $db = new PDO("sqlite:$db_file");
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    $boek = isset($_GET['boek']) ? $_GET['boek'] : null;
    $hoofdstuk = isset($_GET['hoofdstuk']) ? $_GET['hoofdstuk'] : null;
    
    if (!$boek || !$hoofdstuk) {
        echo json_encode(['error' => 'boek en hoofdstuk zijn verplicht']);
        exit;
    }
    
    // Haal alle vers IDs op voor dit hoofdstuk
    $stmt = $db->prepare("
        SELECT Vers_ID, Versnummer
        FROM De_Bijbel
        WHERE Bijbelboeknaam = ? AND Hoofdstuknummer = ?
        ORDER BY CAST(Versnummer AS INTEGER)
    ");
    $stmt->execute([$boek, $hoofdstuk]);
    $verses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($verses)) {
        echo json_encode([]);
        exit;
    }
    
    // Maak lijst van vers IDs
    $vers_ids = array_column($verses, 'Vers_ID');
    $placeholders = implode(',', array_fill(0, count($vers_ids), '?'));
    
    // Haal alle profiel-bewerkingen op voor deze verzen
    $stmt = $db->prepare("
        SELECT v.Versnummer, p.Profiel_ID, p.Profiel_Naam
        FROM Opmaak o
        JOIN Profielen p ON o.Profiel_ID = p.Profiel_ID
        JOIN De_Bijbel v ON o.Vers_ID = v.Vers_ID
        WHERE o.Vers_ID IN ($placeholders)
          AND o.Opgemaakte_Tekst IS NOT NULL
          AND o.Opgemaakte_Tekst != ''
        ORDER BY v.Versnummer, p.Profiel_Naam
    ");
    $stmt->execute($vers_ids);
    $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Groepeer per versnummer
    $result = [];
    foreach ($mappings as $mapping) {
        $versnummer = $mapping['Versnummer'];
        if (!isset($result[$versnummer])) {
            $result[$versnummer] = [];
        }
        $result[$versnummer][] = [
            'Profiel_ID' => (int)$mapping['Profiel_ID'],
            'Profiel_Naam' => $mapping['Profiel_Naam']
        ];
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
