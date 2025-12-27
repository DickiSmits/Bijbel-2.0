<?php
/**
 * Notes API
 * 
 * Handelt alle notitie-gerelateerde API calls af
 */

$db = Database::getInstance();
$endpoint = $_GET['api'];

switch ($endpoint) {
    case 'notes':
        getNotes($db);
        break;
        
    case 'get_note':
        getNote($db);
        break;
        
    case 'save_note':
        saveNote($db);
        break;
        
    case 'delete_note':
        deleteNote($db);
        break;
        
    default:
        jsonError('Onbekende notes endpoint');
}

/**
 * Haal alle notities op
 */
function getNotes($db) {
    $sql = "SELECT * FROM Notities ORDER BY Gewijzigd DESC";
    $notes = $db->query($sql);
    jsonResponse($notes);
}

/**
 * Haal enkele notitie op
 */
function getNote($db) {
    $notitieId = (int)$_GET['id'];
    
    $sql = "SELECT * FROM Notities WHERE Notitie_ID = ?";
    $note = $db->queryOne($sql, [$notitieId]);
    
    if (!$note) {
        jsonError('Notitie niet gevonden', 404);
    }
    
    jsonResponse($note);
}

/**
 * Sla notitie op
 */
function saveNote($db) {
    $data = getJsonInput();
    
    if (!empty($data['notitie_id'])) {
        // Update bestaande notitie
        $sql = "UPDATE Notities SET 
                Titel = ?, Inhoud = ?, Gewijzigd = CURRENT_TIMESTAMP 
                WHERE Notitie_ID = ?";
        
        $db->execute($sql, [
            $data['titel'] ?? 'Nieuwe notitie',
            $data['inhoud'] ?? '',
            $data['notitie_id']
        ]);
        
        jsonSuccess(['notitie_id' => $data['notitie_id']], 'Notitie opgeslagen');
    } else {
        // Nieuwe notitie
        $sql = "INSERT INTO Notities (Titel, Inhoud) VALUES (?, ?)";
        
        $db->execute($sql, [
            $data['titel'] ?? 'Nieuwe notitie',
            $data['inhoud'] ?? ''
        ]);
        
        jsonSuccess(['notitie_id' => $db->lastInsertId()], 'Notitie aangemaakt');
    }
}

/**
 * Verwijder notitie
 */
function deleteNote($db) {
    $notitieId = (int)$_GET['id'];
    
    $sql = "DELETE FROM Notities WHERE Notitie_ID = ?";
    $db->execute($sql, [$notitieId]);
    
    jsonSuccess([], 'Notitie verwijderd');
}
