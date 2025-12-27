<?php
/**
 * Verses API
 * 
 * Handelt alle verse-gerelateerde API calls af
 */

$db = Database::getInstance();
$endpoint = $_GET['api'];

switch ($endpoint) {
    case 'verses':
        getVerses($db);
        break;
        
    case 'books':
        getBooks($db);
        break;
        
    case 'chapters':
        getChapters($db);
        break;
        
    case 'search':
        searchVerses($db);
        break;
        
    case 'verse_detail':
        getVerseDetail($db);
        break;
        
    case 'verse_images':
        getVerseImages($db);
        break;
        
    case 'get_vers_id':
        getVersId($db);
        break;
        
    case 'get_vers_info':
        getVersInfo($db);
        break;
        
    default:
        jsonError('Onbekende verses endpoint');
}

/**
 * Haal verzen op met lazy loading en filtering
 */
function getVerses($db) {
    $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
    $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : DEFAULT_LIMIT;
    $boek = $_GET['boek'] ?? null;
    $hoofdstuk = $_GET['hoofdstuk'] ?? null;
    $profielId = isset($_GET['profiel_id']) ? (int)$_GET['profiel_id'] : null;
    $versId = isset($_GET['vers_id']) ? (int)$_GET['vers_id'] : null;
    $afterVersId = isset($_GET['after_vers_id']) ? (int)$_GET['after_vers_id'] : null;
    $beforeVersId = isset($_GET['before_vers_id']) ? (int)$_GET['before_vers_id'] : null;
    
    $query = "SELECT v.Vers_ID, v.Boeknummer, v.Bijbelboeknaam, v.Hoofdstuknummer, 
             v.Versnummer, v.Tekst";
    
    if ($profielId) {
        $query .= ", o.Opgemaakte_Tekst";
    }
    
    $query .= " FROM De_Bijbel v";
    
    if ($profielId) {
        $query .= " LEFT JOIN Opmaak o ON v.Vers_ID = o.Vers_ID AND o.Profiel_ID = ?";
    }
    
    $where = [];
    $params = [];
    
    if ($profielId) {
        $params[] = $profielId;
    }
    
    if ($versId) {
        $where[] = "v.Vers_ID = ?";
        $params[] = $versId;
    }
    
    if ($afterVersId) {
        $where[] = "v.Vers_ID > ?";
        $params[] = $afterVersId;
    }
    
    if ($beforeVersId) {
        $where[] = "v.Vers_ID < ?";
        $params[] = $beforeVersId;
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
    
    // Voor backward loading, order DESC en reverse results
    if ($beforeVersId) {
        $query .= " ORDER BY v.Vers_ID DESC LIMIT ? OFFSET ?";
    } else {
        $query .= " ORDER BY v.Vers_ID LIMIT ? OFFSET ?";
    }
    
    $params[] = $limit;
    $params[] = $offset;
    
    $verses = $db->query($query, $params);
    
    // Reverse results voor backward loading
    if ($beforeVersId) {
        $verses = array_reverse($verses);
    }
    
    jsonResponse($verses);
}

/**
 * Haal alle bijbelboeken op
 */
function getBooks($db) {
    $sql = "SELECT DISTINCT Bijbelboeknaam, MIN(Vers_ID) as First_ID 
            FROM De_Bijbel 
            GROUP BY Bijbelboeknaam 
            ORDER BY First_ID";
    
    $books = $db->query($sql);
    jsonResponse($books);
}

/**
 * Haal hoofdstukken van een boek op
 */
function getChapters($db) {
    if (!isset($_GET['boek'])) {
        jsonError('Boek parameter ontbreekt');
    }
    
    $boek = $_GET['boek'];
    $sql = "SELECT DISTINCT Hoofdstuknummer 
            FROM De_Bijbel 
            WHERE Bijbelboeknaam = ? 
            ORDER BY CAST(Hoofdstuknummer AS INTEGER)";
    
    $chapters = $db->query($sql, [$boek]);
    jsonResponse($chapters);
}

/**
 * Zoek in bijbeltekst
 */
function searchVerses($db) {
    if (!isset($_GET['query']) || strlen($_GET['query']) < 2) {
        jsonError('Zoekterm te kort (minimaal 2 karakters)');
    }
    
    $query = $_GET['query'];
    $sql = "SELECT Vers_ID, Bijbelboeknaam, Hoofdstuknummer, 
            Versnummer, Tekst 
            FROM De_Bijbel 
            WHERE Tekst LIKE ? 
            LIMIT 100";
    
    $results = $db->query($sql, ['%' . $query . '%']);
    jsonResponse($results);
}

/**
 * Haal details van een specifiek vers op
 */
function getVerseDetail($db) {
    $versId = (int)$_GET['vers_id'];
    $profielId = isset($_GET['profiel_id']) ? (int)$_GET['profiel_id'] : null;
    
    if ($profielId) {
        $sql = "SELECT v.*, o.Opgemaakte_Tekst 
                FROM De_Bijbel v 
                LEFT JOIN Opmaak o ON v.Vers_ID = o.Vers_ID AND o.Profiel_ID = ?
                WHERE v.Vers_ID = ?";
        $verse = $db->queryOne($sql, [$profielId, $versId]);
    } else {
        $sql = "SELECT v.*, NULL as Opgemaakte_Tekst 
                FROM De_Bijbel v 
                WHERE v.Vers_ID = ?";
        $verse = $db->queryOne($sql, [$versId]);
    }
    
    if (!$verse) {
        jsonError('Vers niet gevonden', 404);
    }
    
    jsonResponse($verse);
}

/**
 * Haal afbeeldingen voor een vers op
 */
function getVerseImages($db) {
    $versId = (int)$_GET['vers_id'];
    
    $sql = "SELECT * FROM Afbeeldingen 
            WHERE Vers_ID = ? 
            ORDER BY Afbeelding_ID";
    
    $images = $db->query($sql, [$versId]);
    jsonResponse($images);
}

/**
 * Haal vers_id op basis van boek, hoofdstuk, vers
 */
function getVersId($db) {
    $boek = $_GET['boek'] ?? '';
    $hoofdstuk = $_GET['hoofdstuk'] ?? '';
    $vers = $_GET['vers'] ?? '';
    
    if (!$boek || !$hoofdstuk || !$vers) {
        jsonError('Boek, hoofdstuk en vers zijn verplicht');
    }
    
    $sql = "SELECT Vers_ID FROM De_Bijbel 
            WHERE Bijbelboeknaam = ? AND Hoofdstuknummer = ? AND Versnummer = ?";
    
    $result = $db->queryOne($sql, [$boek, $hoofdstuk, $vers]);
    
    if ($result) {
        jsonSuccess(['vers_id' => $result['Vers_ID']]);
    } else {
        jsonError('Vers niet gevonden');
    }
}

/**
 * Haal boek/hoofdstuk/vers op basis van vers_id
 */
function getVersInfo($db) {
    $versId = (int)$_GET['vers_id'];
    
    $sql = "SELECT Bijbelboeknaam, Hoofdstuknummer, Versnummer 
            FROM De_Bijbel 
            WHERE Vers_ID = ?";
    
    $result = $db->queryOne($sql, [$versId]);
    
    if ($result) {
        jsonResponse($result);
    } else {
        jsonError('Vers niet gevonden', 404);
    }
}
