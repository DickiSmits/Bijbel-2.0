<?php
/**
 * Timeline API
 * 
 * Handelt alle timeline-gerelateerde API calls af
 */

$db = Database::getInstance();
$endpoint = $_GET['api'];

switch ($endpoint) {
    case 'timeline':
        getTimelineEvents($db);
        break;
        
    case 'save_timeline':
        saveTimelineEvent($db);
        break;
        
    case 'delete_timeline':
        deleteTimelineEvent($db);
        break;
        
    case 'get_timeline':
        getTimelineEvent($db);
        break;
        
    case 'timeline_groups':
        getTimelineGroups($db);
        break;
        
    case 'create_timeline_group':
        createTimelineGroup($db);
        break;
        
    case 'update_timeline_group':
        updateTimelineGroup($db);
        break;
        
    case 'delete_timeline_group':
        deleteTimelineGroup($db);
        break;
        
    case 'toggle_timeline_group':
        toggleTimelineGroup($db);
        break;
        
    default:
        jsonError('Onbekende timeline endpoint');
}

/**
 * Haal timeline events op
 */
function getTimelineEvents($db) {
    $versId = isset($_GET['vers_id']) ? (int)$_GET['vers_id'] : null;
    
    $sql = "SELECT e.*, g.Groep_Naam, g.Kleur as Groep_Kleur,
            v1.Bijbelboeknaam as Start_Boek, v1.Hoofdstuknummer as Start_Hoofdstuk, 
            v1.Versnummer as Start_Vers,
            v2.Bijbelboeknaam as End_Boek, v2.Hoofdstuknummer as End_Hoofdstuk, 
            v2.Versnummer as End_Vers
            FROM Timeline_Events e 
            LEFT JOIN Timeline_Groups g ON e.Group_ID = g.Group_ID
            LEFT JOIN De_Bijbel v1 ON e.Vers_ID_Start = v1.Vers_ID
            LEFT JOIN De_Bijbel v2 ON e.Vers_ID_End = v2.Vers_ID";
    
    $params = [];
    
    if ($versId) {
        $sql .= " WHERE (e.Vers_ID_Start <= ? AND e.Vers_ID_End >= ?) 
                  OR (e.Vers_ID_Start = ? AND e.Vers_ID_End IS NULL)";
        $params = [$versId, $versId, $versId];
    }
    
    $sql .= " ORDER BY e.Start_Datum";
    
    $events = $db->query($sql, $params);
    jsonResponse($events);
}

/**
 * Sla timeline event op
 */
function saveTimelineEvent($db) {
    $data = getJsonInput();
    validateRequired($data, ['titel', 'start_datum']);
    
    if (isset($data['event_id']) && $data['event_id']) {
        // Update bestaand event
        $sql = "UPDATE Timeline_Events SET 
                Titel=?, Beschrijving=?, Start_Datum=?, End_Datum=?, 
                Vers_ID_Start=?, Vers_ID_End=?, Type=?, Kleur=?, Tekst_Kleur=?, Group_ID=? 
                WHERE Event_ID=?";
        
        $db->execute($sql, [
            $data['titel'],
            $data['beschrijving'] ?? '',
            $data['start_datum'],
            $data['end_datum'] ?? null,
            $data['vers_id_start'] ?? null,
            $data['vers_id_end'] ?? null,
            $data['type'] ?? 'point',
            $data['kleur'] ?? '#4A90E2',
            $data['tekst_kleur'] ?? null,
            $data['group_id'] ?? null,
            $data['event_id']
        ]);
    } else {
        // Nieuw event
        $sql = "INSERT INTO Timeline_Events 
                (Titel, Beschrijving, Start_Datum, End_Datum, Vers_ID_Start, 
                Vers_ID_End, Type, Kleur, Tekst_Kleur, Group_ID) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $db->execute($sql, [
            $data['titel'],
            $data['beschrijving'] ?? '',
            $data['start_datum'],
            $data['end_datum'] ?? null,
            $data['vers_id_start'] ?? null,
            $data['vers_id_end'] ?? null,
            $data['type'] ?? 'point',
            $data['kleur'] ?? '#4A90E2',
            $data['tekst_kleur'] ?? null,
            $data['group_id'] ?? null
        ]);
    }
    
    jsonSuccess([], 'Timeline event opgeslagen');
}

/**
 * Verwijder timeline event
 */
function deleteTimelineEvent($db) {
    $eventId = (int)$_GET['id'];
    
    $sql = "DELETE FROM Timeline_Events WHERE Event_ID = ?";
    $db->execute($sql, [$eventId]);
    
    jsonSuccess([], 'Timeline event verwijderd');
}

/**
 * Haal enkel timeline event op
 */
function getTimelineEvent($db) {
    $eventId = (int)$_GET['id'];
    
    $sql = "SELECT * FROM Timeline_Events WHERE Event_ID = ?";
    $event = $db->queryOne($sql, [$eventId]);
    
    if (!$event) {
        jsonError('Event niet gevonden', 404);
    }
    
    jsonResponse($event);
}

/**
 * Haal timeline groepen op
 */
function getTimelineGroups($db) {
    $sql = "SELECT * FROM Timeline_Groups ORDER BY Volgorde, Groep_Naam";
    $groups = $db->query($sql);
    jsonResponse($groups);
}

/**
 * Maak nieuwe timeline groep aan
 */
function createTimelineGroup($db) {
    $data = getJsonInput();
    validateRequired($data, ['naam']);
    
    $sql = "INSERT INTO Timeline_Groups 
            (Groep_Naam, Beschrijving, Kleur, Zichtbaar, Volgorde) 
            VALUES (?, ?, ?, ?, ?)";
    
    $db->execute($sql, [
        $data['naam'],
        $data['beschrijving'] ?? '',
        $data['kleur'] ?? '#4A90E2',
        $data['zichtbaar'] ?? 1,
        $data['volgorde'] ?? 0
    ]);
    
    jsonSuccess([
        'id' => $db->lastInsertId()
    ], 'Groep aangemaakt');
}

/**
 * Update timeline groep
 */
function updateTimelineGroup($db) {
    $data = getJsonInput();
    validateRequired($data, ['group_id', 'naam']);
    
    $sql = "UPDATE Timeline_Groups SET 
            Groep_Naam=?, Beschrijving=?, Kleur=?, Zichtbaar=?, Volgorde=? 
            WHERE Group_ID=?";
    
    $db->execute($sql, [
        $data['naam'],
        $data['beschrijving'] ?? '',
        $data['kleur'] ?? '#4A90E2',
        $data['zichtbaar'] ?? 1,
        $data['volgorde'] ?? 0,
        $data['group_id']
    ]);
    
    jsonSuccess([], 'Groep bijgewerkt');
}

/**
 * Verwijder timeline groep
 */
function deleteTimelineGroup($db) {
    $groupId = (int)$_GET['id'];
    
    $sql = "DELETE FROM Timeline_Groups WHERE Group_ID = ?";
    $db->execute($sql, [$groupId]);
    
    jsonSuccess([], 'Groep verwijderd');
}

/**
 * Toggle zichtbaarheid van timeline groep
 */
function toggleTimelineGroup($db) {
    $groupId = (int)$_GET['id'];
    
    $sql = "UPDATE Timeline_Groups SET Zichtbaar = 1 - Zichtbaar WHERE Group_ID = ?";
    $db->execute($sql, [$groupId]);
    
    jsonSuccess([], 'Groep zichtbaarheid aangepast');
}
