<?php
/**
 * API: Verses
 * Geef bijbelverzen terug met lazy loading support
 */

require_once __DIR__ . '/../config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Parameters
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
    $boek = isset($_GET['boek']) ? $_GET['boek'] : null;
    $hoofdstuk = isset($_GET['hoofdstuk']) ? $_GET['hoofdstuk'] : null;
    $profiel_id = isset($_GET['profiel_id']) ? (int)$_GET['profiel_id'] : null;
    $vers_id = isset($_GET['vers_id']) ? (int)$_GET['vers_id'] : null;
    $after_vers_id = isset($_GET['after_vers_id']) ? (int)$_GET['after_vers_id'] : null;
    $before_vers_id = isset($_GET['before_vers_id']) ? (int)$_GET['before_vers_id'] : null;
    
    // Build query
    $query = "SELECT v.Vers_ID, v.Boeknummer, v.Bijbelboeknaam, v.Hoofdstuknummer, 
              v.Versnummer, v.Tekst";
    
    if ($profiel_id) {
        $query .= ", o.Opgemaakte_Tekst";
    }
    
    $query .= " FROM De_Bijbel v";
    
    if ($profiel_id) {
        $query .= " LEFT JOIN Opmaak o ON v.Vers_ID = o.Vers_ID AND o.Profiel_ID = ?";
    }
    
    $where = [];
    $params = [];
    
    if ($profiel_id) {
        $params[] = $profiel_id;
    }
    
    if ($vers_id) {
        $where[] = "v.Vers_ID = ?";
        $params[] = $vers_id;
    }
    
    if ($after_vers_id) {
        $where[] = "v.Vers_ID > ?";
        $params[] = $after_vers_id;
    }
    
    if ($before_vers_id) {
        $where[] = "v.Vers_ID < ?";
        $params[] = $before_vers_id;
    }
    
    if ($boek) {
        $where[] = "v.Bijbelboeknaam = ?";
        $params[] = $boek;
    }
    
    if ($hoofdstuk) {
        $where[] = "v.Hoofdstuknummer = ?";
        $params[] = $hoofdstuk;
    }
    
    if ($where) {
        $query .= " WHERE " . implode(" AND ", $where);
    }
    
    // Order by
    if ($before_vers_id) {
        $query .= " ORDER BY v.Vers_ID DESC LIMIT ? OFFSET ?";
    } else {
        $query .= " ORDER BY v.Vers_ID LIMIT ? OFFSET ?";
    }
    $params[] = $limit;
    $params[] = $offset;
    
    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $verses = $stmt->fetchAll();
    
    // Reverse for backward loading
    if ($before_vers_id) {
        $verses = array_reverse($verses);
    }
    
    echo json_encode($verses);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
