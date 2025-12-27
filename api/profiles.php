<?php
/**
 * Profiles API
 * 
 * Handelt alle profiel en opmaak-gerelateerde API calls af
 */

$db = Database::getInstance();
$endpoint = $_GET['api'];

switch ($endpoint) {
    case 'profiles':
        getProfiles($db);
        break;
        
    case 'create_profile':
        createProfile($db);
        break;
        
    case 'delete_profile':
        deleteProfile($db);
        break;
        
    case 'save_formatting':
        saveFormatting($db);
        break;
        
    case 'all_formatting':
        getAllFormatting($db);
        break;
        
    case 'delete_formatting':
        deleteFormatting($db);
        break;
        
    default:
        jsonError('Onbekende profiles endpoint');
}

/**
 * Haal alle profielen op
 */
function getProfiles($db) {
    $sql = "SELECT * FROM Profielen ORDER BY Profiel_Naam";
    $profiles = $db->query($sql);
    jsonResponse($profiles);
}

/**
 * Maak nieuw profiel aan
 */
function createProfile($db) {
    $data = getJsonInput();
    validateRequired($data, ['naam']);
    
    $sql = "INSERT INTO Profielen (Profiel_Naam, Beschrijving) VALUES (?, ?)";
    $db->execute($sql, [
        $data['naam'],
        $data['beschrijving'] ?? ''
    ]);
    
    jsonSuccess([
        'id' => $db->lastInsertId()
    ], 'Profiel aangemaakt');
}

/**
 * Verwijder profiel
 */
function deleteProfile($db) {
    // Vereist admin rechten
    if (!Auth::isAdmin()) {
        jsonError('Niet geautoriseerd', 403);
    }
    
    $profielId = (int)$_GET['id'];
    
    // Verwijder ook alle opmaak voor dit profiel
    $db->execute("DELETE FROM Opmaak WHERE Profiel_ID = ?", [$profielId]);
    
    // Verwijder profiel
    $db->execute("DELETE FROM Profielen WHERE Profiel_ID = ?", [$profielId]);
    
    jsonSuccess([], 'Profiel verwijderd');
}

/**
 * Sla opmaak op voor een vers
 */
function saveFormatting($db) {
    $data = getJsonInput();
    validateRequired($data, ['profiel_id', 'vers_id', 'tekst']);
    
    $sql = "INSERT OR REPLACE INTO Opmaak 
            (Profiel_ID, Vers_ID, Opgemaakte_Tekst, Laatst_Gewijzigd) 
            VALUES (?, ?, ?, CURRENT_TIMESTAMP)";
    
    $db->execute($sql, [
        $data['profiel_id'],
        $data['vers_id'],
        $data['tekst']
    ]);
    
    jsonSuccess([], 'Opmaak opgeslagen');
}

/**
 * Haal alle bewerkte verzen op
 */
function getAllFormatting($db) {
    $sql = "SELECT o.*, p.Profiel_Naam, 
            v.Bijbelboeknaam, v.Hoofdstuknummer, v.Versnummer, v.Tekst as Originele_Tekst
            FROM Opmaak o
            JOIN Profielen p ON o.Profiel_ID = p.Profiel_ID
            JOIN De_Bijbel v ON o.Vers_ID = v.Vers_ID
            ORDER BY v.Bijbelboeknaam, v.Hoofdstuknummer, v.Versnummer";
    
    $formatting = $db->query($sql);
    jsonResponse($formatting);
}

/**
 * Verwijder opmaak voor een vers
 */
function deleteFormatting($db) {
    $profielId = (int)$_GET['profiel_id'];
    $versId = (int)$_GET['vers_id'];
    
    $sql = "DELETE FROM Opmaak WHERE Profiel_ID = ? AND Vers_ID = ?";
    $db->execute($sql, [$profielId, $versId]);
    
    jsonSuccess([], 'Opmaak verwijderd');
}
