<?php
/**
 * Test: Check Opmaak table content
 */

require_once __DIR__ . '/config.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getConnection();
    
    // Count total opmaak entries
    $countStmt = $db->query("SELECT COUNT(*) as count FROM Opmaak");
    $count = $countStmt->fetch()['count'];
    
    // Get sample entries with profile and verse info
    $stmt = $db->query("
        SELECT 
            o.Opmaak_ID,
            o.Profiel_ID,
            p.Profiel_Naam,
            o.Vers_ID,
            v.Bijbelboeknaam,
            v.Hoofdstuknummer,
            v.Versnummer,
            SUBSTR(o.Opgemaakte_Tekst, 1, 100) as Opgemaakte_Preview,
            SUBSTR(v.Tekst, 1, 100) as Origineel_Preview
        FROM Opmaak o
        JOIN Profielen p ON o.Profiel_ID = p.Profiel_ID
        JOIN De_Bijbel v ON o.Vers_ID = v.Vers_ID
        LIMIT 10
    ");
    
    $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'total_opmaak_entries' => $count,
        'sample_entries' => $entries,
        'message' => $count > 0 ? "✅ Opmaak tabel heeft $count entries" : "❌ Opmaak tabel is LEEG!"
    ], JSON_PRETTY_PRINT);
    
} catch (Exception $e) {
    echo json_encode(['error' => $e->getMessage()]);
}
