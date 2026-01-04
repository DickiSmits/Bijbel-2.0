<?php
/**
 * API: save_timeline
 * Create of update timeline event
 */

require_once __DIR__ . '/../config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Get JSON body
    $data = json_decode(file_get_contents('php://input'), true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }
    
    // Validate required fields
    if (empty($data['titel']) || empty($data['start_datum'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Titel en start_datum zijn verplicht']);
        exit;
    }
    
    if (isset($data['event_id']) && $data['event_id']) {
        // UPDATE existing event
        $stmt = $db->prepare("UPDATE Timeline_Events SET 
            Titel = ?, 
            Beschrijving = ?, 
            Start_Datum = ?, 
            End_Datum = ?, 
            Vers_ID_Start = ?, 
            Vers_ID_End = ?, 
            Type = ?, 
            Kleur = ?, 
            Tekst_Kleur = ?, 
            Group_ID = ? 
            WHERE Event_ID = ?");
        
        $stmt->execute([
            $data['titel'],
            $data['beschrijving'] ?? null,
            $data['start_datum'],
            $data['end_datum'] ?? null,
            $data['vers_id_start'] ?? null,
            $data['vers_id_end'] ?? null,
            $data['type'] ?? 'event',
            $data['kleur'] ?? '#3498db',
            $data['tekst_kleur'] ?? '#ffffff',
            $data['group_id'] ?? null,
            $data['event_id']
        ]);
        
        echo json_encode(['success' => true, 'event_id' => $data['event_id']]);
        
    } else {
        // INSERT new event
        $stmt = $db->prepare("INSERT INTO Timeline_Events 
            (Titel, Beschrijving, Start_Datum, End_Datum, Vers_ID_Start, Vers_ID_End, Type, Kleur, Tekst_Kleur, Group_ID) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $data['titel'],
            $data['beschrijving'] ?? null,
            $data['start_datum'],
            $data['end_datum'] ?? null,
            $data['vers_id_start'] ?? null,
            $data['vers_id_end'] ?? null,
            $data['type'] ?? 'event',
            $data['kleur'] ?? '#3498db',
            $data['tekst_kleur'] ?? '#ffffff',
            $data['group_id'] ?? null
        ]);
        
        echo json_encode(['success' => true, 'event_id' => $db->lastInsertId()]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
