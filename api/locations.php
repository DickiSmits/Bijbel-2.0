<?php
/**
 * Locations API
 * 
 * Handelt alle locatie-gerelateerde API calls af
 */

$db = Database::getInstance();
$endpoint = $_GET['api'];

switch ($endpoint) {
    case 'locations':
        getLocations($db);
        break;
        
    case 'get_location':
        getLocation($db);
        break;
        
    case 'save_location':
        saveLocation($db);
        break;
        
    case 'delete_location':
        deleteLocation($db);
        break;
        
    case 'link_verse_location':
        linkVerseLocation($db);
        break;
        
    default:
        jsonError('Onbekende locations endpoint');
}

/**
 * Haal locaties op
 */
function getLocations($db) {
    $versId = isset($_GET['vers_id']) ? (int)$_GET['vers_id'] : null;
    
    if ($versId) {
        $sql = "SELECT l.*, vl.Context 
                FROM Locaties l 
                JOIN Vers_Locaties vl ON l.Locatie_ID = vl.Locatie_ID 
                WHERE vl.Vers_ID = ?";
        $locations = $db->query($sql, [$versId]);
    } else {
        $sql = "SELECT l.*, 
                GROUP_CONCAT(v.Bijbelboeknaam || ' ' || v.Hoofdstuknummer || ':' || v.Versnummer, ', ') as Gekoppelde_Verzen
                FROM Locaties l 
                LEFT JOIN Vers_Locaties vl ON l.Locatie_ID = vl.Locatie_ID
                LEFT JOIN De_Bijbel v ON vl.Vers_ID = v.Vers_ID
                GROUP BY l.Locatie_ID";
        $locations = $db->query($sql);
    }
    
    jsonResponse($locations);
}

/**
 * Haal enkele locatie op
 */
function getLocation($db) {
    $locatieId = (int)$_GET['id'];
    
    $sql = "SELECT * FROM Locaties WHERE Locatie_ID = ?";
    $location = $db->queryOne($sql, [$locatieId]);
    
    if (!$location) {
        jsonError('Locatie niet gevonden', 404);
    }
    
    jsonResponse($location);
}

/**
 * Sla locatie op
 */
function saveLocation($db) {
    $data = getJsonInput();
    validateRequired($data, ['naam', 'latitude', 'longitude']);
    
    if (isset($data['locatie_id']) && $data['locatie_id']) {
        // Update bestaande locatie
        $sql = "UPDATE Locaties SET 
                Naam=?, Latitude=?, Longitude=?, Beschrijving=?, Type=?, Icon=? 
                WHERE Locatie_ID=?";
        
        $db->execute($sql, [
            $data['naam'],
            $data['latitude'],
            $data['longitude'],
            $data['beschrijving'] ?? '',
            $data['type'] ?? 'stad',
            $data['icon'] ?? 'marker',
            $data['locatie_id']
        ]);
    } else {
        // Nieuwe locatie
        $sql = "INSERT INTO Locaties 
                (Naam, Latitude, Longitude, Beschrijving, Type, Icon) 
                VALUES (?, ?, ?, ?, ?, ?)";
        
        $db->execute($sql, [
            $data['naam'],
            $data['latitude'],
            $data['longitude'],
            $data['beschrijving'] ?? '',
            $data['type'] ?? 'stad',
            $data['icon'] ?? 'marker'
        ]);
    }
    
    jsonSuccess([], 'Locatie opgeslagen');
}

/**
 * Verwijder locatie
 */
function deleteLocation($db) {
    $locatieId = (int)$_GET['id'];
    
    // Verwijder eerst koppelingen
    $db->execute("DELETE FROM Vers_Locaties WHERE Locatie_ID = ?", [$locatieId]);
    
    // Verwijder locatie
    $db->execute("DELETE FROM Locaties WHERE Locatie_ID = ?", [$locatieId]);
    
    jsonSuccess([], 'Locatie verwijderd');
}

/**
 * Koppel vers aan locatie
 */
function linkVerseLocation($db) {
    $data = getJsonInput();
    validateRequired($data, ['vers_id', 'locatie_id']);
    
    $sql = "INSERT OR IGNORE INTO Vers_Locaties 
            (Vers_ID, Locatie_ID, Context) 
            VALUES (?, ?, ?)";
    
    $db->execute($sql, [
        $data['vers_id'],
        $data['locatie_id'],
        $data['context'] ?? ''
    ]);
    
    jsonSuccess([], 'Koppeling aangemaakt');
}
