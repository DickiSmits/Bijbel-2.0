<?php
/**
 * Test: Direct query like verses.php does
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getConnection();
    
    // Get first profile ID
    $profileStmt = $db->query("SELECT Profiel_ID FROM Profielen LIMIT 1");
    $profile = $profileStmt->fetch();
    
    if (!$profile) {
        echo json_encode(['error' => 'Geen profielen gevonden in database!']);
        exit;
    }
    
    $profiel_id = $profile['Profiel_ID'];
    
    // Query EXACTLY like verses.php does
    $query = "SELECT v.Vers_ID, v.Boeknummer, v.Bijbelboeknaam, v.Hoofdstuknummer, 
              v.Versnummer, v.Tekst, o.Opgemaakte_Tekst
              FROM De_Bijbel v
              LEFT JOIN Opmaak o ON v.Vers_ID = o.Vers_ID AND o.Profiel_ID = ?
              ORDER BY v.Vers_ID 
              LIMIT 10";
    
    $stmt = $db->prepare($query);
    $stmt->execute([$profiel_id]);
    $verses = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Analyze results
    $with_opmaak = 0;
    $without_opmaak = 0;
    
    foreach ($verses as $verse) {
        if ($verse['Opgemaakte_Tekst']) {
            $with_opmaak++;
        } else {
            $without_opmaak++;
        }
    }
    
    echo json_encode([
        'profiel_id_used' => $profiel_id,
        'total_verses' => count($verses),
        'with_opmaak' => $with_opmaak,
        'without_opmaak' => $without_opmaak,
        'first_3_verses' => array_slice($verses, 0, 3),
        'message' => $with_opmaak > 0 
            ? "✅ Opmaak werkt! $with_opmaak van " . count($verses) . " verzen heeft opmaak"
            : "⚠️ Geen opmaak gevonden voor profiel $profiel_id in eerste 10 verzen"
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
