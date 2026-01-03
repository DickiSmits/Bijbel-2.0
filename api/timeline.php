<?php
/**
 * API/TIMELINE.PHP - Complete Timeline Management
 * 
 * Endpoints:
 * 
 * EVENTS:
 * - timeline: List all timeline events (optionally filtered by vers_id)
 * - create_timeline: Create new timeline event
 * - update_timeline: Update existing timeline event
 * - delete_timeline: Delete timeline event
 * 
 * GROUPS:
 * - timeline_groups: List all timeline groups
 * - create_timeline_group: Create new timeline group
 * - update_timeline_group: Update existing timeline group
 * - delete_timeline_group: Delete timeline group
 */

require_once __DIR__ . '/../config.php';

// Get database connection
try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

// Determine endpoint from api parameter
$endpoint = isset($_GET['api']) ? $_GET['api'] : 'timeline';

switch ($endpoint) {
    
    // ═════════════════════════════════════════════════════════════════════════
    // TIMELINE EVENTS
    // ═════════════════════════════════════════════════════════════════════════
    
    // ============= LIST TIMELINE EVENTS =============
    case 'timeline':
        try {
            $vers_id = isset($_GET['vers_id']) ? (int)$_GET['vers_id'] : null;
            
            if ($vers_id) {
                // Filter by verse
                $stmt = $db->prepare("
                    SELECT e.*, g.Groep_Naam, g.Kleur as Groep_Kleur,
                           v1.Bijbelboeknaam as Start_Boek, 
                           v1.Hoofdstuknummer as Start_Hoofdstuk, 
                           v1.Versnummer as Start_Vers,
                           v2.Bijbelboeknaam as End_Boek, 
                           v2.Hoofdstuknummer as End_Hoofdstuk, 
                           v2.Versnummer as End_Vers
                    FROM Timeline_Events e 
                    LEFT JOIN Timeline_Groups g ON e.Group_ID = g.Group_ID
                    LEFT JOIN De_Bijbel v1 ON e.Vers_ID_Start = v1.Vers_ID
                    LEFT JOIN De_Bijbel v2 ON e.Vers_ID_End = v2.Vers_ID
                    WHERE (e.Vers_ID_Start <= ? AND e.Vers_ID_End >= ?) 
                       OR (e.Vers_ID_Start = ? AND e.Vers_ID_End IS NULL)
                    ORDER BY e.Start_Datum
                ");
                $stmt->execute([$vers_id, $vers_id, $vers_id]);
            } else {
                // All events
                $stmt = $db->query("
                    SELECT e.*, g.Groep_Naam, g.Kleur as Groep_Kleur,
                           v1.Bijbelboeknaam as Start_Boek, 
                           v1.Hoofdstuknummer as Start_Hoofdstuk, 
                           v1.Versnummer as Start_Vers,
                           v2.Bijbelboeknaam as End_Boek, 
                           v2.Hoofdstuknummer as End_Hoofdstuk, 
                           v2.Versnummer as End_Vers
                    FROM Timeline_Events e 
                    LEFT JOIN Timeline_Groups g ON e.Group_ID = g.Group_ID
                    LEFT JOIN De_Bijbel v1 ON e.Vers_ID_Start = v1.Vers_ID
                    LEFT JOIN De_Bijbel v2 ON e.Vers_ID_End = v2.Vers_ID
                    ORDER BY e.Start_Datum
                ");
            }
            
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($events ?: []);
            
        } catch (Exception $e) {
            error_log("Timeline events query error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Fout bij ophalen timeline events']);
        }
        break;
    
    // ============= CREATE TIMELINE EVENT =============
    case 'create_timeline':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'POST required']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate
        if (!isset($data['titel']) || trim($data['titel']) === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Titel is verplicht'
            ]);
            exit;
        }
        
        if (!isset($data['start_datum']) || trim($data['start_datum']) === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Start datum is verplicht'
            ]);
            exit;
        }
        
        try {
            $stmt = $db->prepare("
                INSERT INTO Timeline_Events (
                    Titel, Beschrijving, Start_Datum, End_Datum, 
                    Kleur, Tekst_Kleur, Group_ID, 
                    Vers_ID_Start, Vers_ID_End
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $stmt->execute([
                trim($data['titel']),
                isset($data['beschrijving']) ? trim($data['beschrijving']) : null,
                trim($data['start_datum']),
                isset($data['end_datum']) ? trim($data['end_datum']) : null,
                isset($data['kleur']) ? trim($data['kleur']) : '#3498db',
                isset($data['tekst_kleur']) ? trim($data['tekst_kleur']) : '#ffffff',
                isset($data['group_id']) ? (int)$data['group_id'] : null,
                isset($data['vers_id_start']) ? (int)$data['vers_id_start'] : null,
                isset($data['vers_id_end']) ? (int)$data['vers_id_end'] : null
            ]);
            
            $newId = $db->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'id' => (string)$newId,
                'message' => 'Timeline event aangemaakt'
            ]);
            
        } catch (PDOException $e) {
            error_log("Create timeline event error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database fout bij aanmaken timeline event'
            ]);
        }
        break;
    
    // ============= UPDATE TIMELINE EVENT =============
    case 'update_timeline':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'POST required']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Accept both 'id' and 'event_id'
        $eventId = null;
        if (isset($data['id'])) {
            $eventId = (int)$data['id'];
        } elseif (isset($data['event_id'])) {
            $eventId = (int)$data['event_id'];
        }
        
        if (!$eventId || !isset($data['titel'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Event ID en titel zijn verplicht'
            ]);
            exit;
        }
        
        try {
            // Check if event exists
            $checkStmt = $db->prepare("SELECT Event_ID FROM Timeline_Events WHERE Event_ID = ?");
            $checkStmt->execute([$eventId]);
            
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Timeline event niet gevonden'
                ]);
                exit;
            }
            
            // Update event
            $stmt = $db->prepare("
                UPDATE Timeline_Events SET
                    Titel = ?,
                    Beschrijving = ?,
                    Start_Datum = ?,
                    End_Datum = ?,
                    Kleur = ?,
                    Tekst_Kleur = ?,
                    Group_ID = ?,
                    Vers_ID_Start = ?,
                    Vers_ID_End = ?
                WHERE Event_ID = ?
            ");
            
            $result = $stmt->execute([
                trim($data['titel']),
                isset($data['beschrijving']) ? trim($data['beschrijving']) : null,
                isset($data['start_datum']) ? trim($data['start_datum']) : null,
                isset($data['end_datum']) ? trim($data['end_datum']) : null,
                isset($data['kleur']) ? trim($data['kleur']) : '#3498db',
                isset($data['tekst_kleur']) ? trim($data['tekst_kleur']) : '#ffffff',
                isset($data['group_id']) ? (int)$data['group_id'] : null,
                isset($data['vers_id_start']) ? (int)$data['vers_id_start'] : null,
                isset($data['vers_id_end']) ? (int)$data['vers_id_end'] : null,
                $eventId
            ]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'id' => (string)$eventId,
                    'message' => 'Timeline event bijgewerkt'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Timeline event kon niet worden bijgewerkt'
                ]);
            }
            
        } catch (PDOException $e) {
            error_log("Update timeline event error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database fout bij bijwerken timeline event'
            ]);
        }
        break;
    
    // ============= DELETE TIMELINE EVENT =============
    case 'delete_timeline':
        $eventId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($eventId <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Ongeldig event ID'
            ]);
            exit;
        }
        
        try {
            // Check if event exists
            $stmt = $db->prepare("SELECT Titel FROM Timeline_Events WHERE Event_ID = ?");
            $stmt->execute([$eventId]);
            
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Timeline event niet gevonden'
                ]);
                exit;
            }
            
            // Delete event
            $stmt = $db->prepare("DELETE FROM Timeline_Events WHERE Event_ID = ?");
            $stmt->execute([$eventId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Timeline event verwijderd'
            ]);
            
        } catch (PDOException $e) {
            error_log("Delete timeline event error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database fout bij verwijderen timeline event'
            ]);
        }
        break;
    
    // ═════════════════════════════════════════════════════════════════════════
    // TIMELINE GROUPS
    // ═════════════════════════════════════════════════════════════════════════
    
    // ============= LIST TIMELINE GROUPS =============
    case 'timeline_groups':
        try {
            $stmt = $db->query("
                SELECT 
                    Group_ID,
                    Groep_Naam,
                    Kleur,
                    Volgorde,
                    Zichtbaar
                FROM Timeline_Groups
                ORDER BY Volgorde ASC, Groep_Naam ASC
            ");
            
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($groups ?: []);
            
        } catch (Exception $e) {
            error_log("Timeline groups query error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode(['error' => 'Fout bij ophalen timeline groepen']);
        }
        break;
    
    // ============= CREATE TIMELINE GROUP =============
    case 'create_timeline_group':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'POST required']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['naam']) || trim($data['naam']) === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Naam is verplicht'
            ]);
            exit;
        }
        
        $naam = trim($data['naam']);
        $kleur = isset($data['kleur']) ? trim($data['kleur']) : '#3498db';
        $volgorde = isset($data['volgorde']) ? (int)$data['volgorde'] : 1;
        
        try {
            // Check if name already exists
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM Timeline_Groups WHERE Groep_Naam = ?");
            $checkStmt->execute([$naam]);
            
            if ($checkStmt->fetchColumn() > 0) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'error' => 'Een timeline groep met deze naam bestaat al'
                ]);
                exit;
            }
            
            // Insert new group
            $stmt = $db->prepare("
                INSERT INTO Timeline_Groups (Groep_Naam, Kleur, Volgorde, Zichtbaar) 
                VALUES (?, ?, ?, 1)
            ");
            
            $stmt->execute([$naam, $kleur, $volgorde]);
            
            $newId = $db->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'id' => (string)$newId,
                'message' => 'Timeline groep aangemaakt'
            ]);
            
        } catch (PDOException $e) {
            error_log("Create timeline group error: " . $e->getMessage());
            
            if (strpos($e->getMessage(), 'UNIQUE constraint') !== false) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'error' => 'Een timeline groep met deze naam bestaat al'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Database fout bij aanmaken timeline groep'
                ]);
            }
        }
        break;
    
    // ============= UPDATE TIMELINE GROUP =============
    case 'update_timeline_group':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'POST required']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        // Accept both 'id' and 'group_id'
        $groupId = null;
        if (isset($data['id'])) {
            $groupId = (int)$data['id'];
        } elseif (isset($data['group_id'])) {
            $groupId = (int)$data['group_id'];
        }
        
        if (!$groupId || !isset($data['naam'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Groep ID en naam zijn verplicht'
            ]);
            exit;
        }
        
        $naam = trim($data['naam']);
        $kleur = isset($data['kleur']) ? trim($data['kleur']) : '#3498db';
        $volgorde = isset($data['volgorde']) ? (int)$data['volgorde'] : null;
        
        if ($naam === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Naam mag niet leeg zijn'
            ]);
            exit;
        }
        
        try {
            // Check if group exists
            $checkStmt = $db->prepare("SELECT Groep_Naam FROM Timeline_Groups WHERE Group_ID = ?");
            $checkStmt->execute([$groupId]);
            
            if (!$checkStmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Timeline groep niet gevonden'
                ]);
                exit;
            }
            
            // Check if another group with same name exists
            $checkStmt = $db->prepare("
                SELECT COUNT(*) 
                FROM Timeline_Groups 
                WHERE Groep_Naam = ? AND Group_ID != ?
            ");
            $checkStmt->execute([$naam, $groupId]);
            
            if ($checkStmt->fetchColumn() > 0) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'error' => 'Een andere timeline groep met deze naam bestaat al'
                ]);
                exit;
            }
            
            // Update group
            if ($volgorde !== null) {
                $stmt = $db->prepare("
                    UPDATE Timeline_Groups 
                    SET Groep_Naam = ?, Kleur = ?, Volgorde = ?
                    WHERE Group_ID = ?
                ");
                $result = $stmt->execute([$naam, $kleur, $volgorde, $groupId]);
            } else {
                $stmt = $db->prepare("
                    UPDATE Timeline_Groups 
                    SET Groep_Naam = ?, Kleur = ?
                    WHERE Group_ID = ?
                ");
                $result = $stmt->execute([$naam, $kleur, $groupId]);
            }
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'id' => (string)$groupId,
                    'message' => 'Timeline groep bijgewerkt'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Timeline groep kon niet worden bijgewerkt'
                ]);
            }
            
        } catch (PDOException $e) {
            error_log("Update timeline group error: " . $e->getMessage());
            
            if (strpos($e->getMessage(), 'UNIQUE constraint') !== false) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'error' => 'Een timeline groep met deze naam bestaat al'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Database fout bij bijwerken timeline groep'
                ]);
            }
        }
        break;
    
    // ============= DELETE TIMELINE GROUP =============
    case 'delete_timeline_group':
        $groupId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($groupId <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Ongeldig groep ID'
            ]);
            exit;
        }
        
        try {
            // Check if group exists
            $stmt = $db->prepare("SELECT Groep_Naam FROM Timeline_Groups WHERE Group_ID = ?");
            $stmt->execute([$groupId]);
            
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Timeline groep niet gevonden'
                ]);
                exit;
            }
            
            // Delete group
            $stmt = $db->prepare("DELETE FROM Timeline_Groups WHERE Group_ID = ?");
            $stmt->execute([$groupId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Timeline groep verwijderd'
            ]);
            
        } catch (PDOException $e) {
            error_log("Delete timeline group error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database fout bij verwijderen timeline groep'
            ]);
        }
        break;
    
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown timeline endpoint: ' . $endpoint]);
}
?>