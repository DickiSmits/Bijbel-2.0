<?php
/**
 * API: save_timeline_group
 * Create or Update timeline group
 */

require_once __DIR__ . '/../config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    // Get JSON body
    $json = file_get_contents('php://input');
    $data = json_decode($json, true);
    
    if (!$data) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid JSON']);
        exit;
    }
    
    // Validate required fields
    if (empty($data['naam'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Naam is required']);
        exit;
    }
    
    // Extract fields
    $groupId = isset($data['group_id']) ? (int)$data['group_id'] : null;
    $naam = $data['naam'];
    $beschrijving = isset($data['beschrijving']) ? $data['beschrijving'] : null;
    $kleur = isset($data['kleur']) ? $data['kleur'] : '#3498db';
    $volgorde = isset($data['volgorde']) ? (int)$data['volgorde'] : 1;
    $zichtbaar = isset($data['zichtbaar']) ? (int)$data['zichtbaar'] : 1;
    
    if ($groupId) {
        // UPDATE existing group
        $stmt = $db->prepare("
            UPDATE Timeline_Groups 
            SET Groep_Naam = ?, 
                Beschrijving = ?, 
                Kleur = ?, 
                Volgorde = ?, 
                Zichtbaar = ?
            WHERE Group_ID = ?
        ");
        
        $stmt->execute([
            $naam,
            $beschrijving,
            $kleur,
            $volgorde,
            $zichtbaar,
            $groupId
        ]);
        
        echo json_encode([
            'success' => true,
            'group_id' => $groupId,
            'message' => 'Timeline group updated'
        ]);
        
    } else {
        // INSERT new group
        $stmt = $db->prepare("
            INSERT INTO Timeline_Groups 
            (Groep_Naam, Beschrijving, Kleur, Volgorde, Zichtbaar) 
            VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $naam,
            $beschrijving,
            $kleur,
            $volgorde,
            $zichtbaar
        ]);
        
        $newId = $db->lastInsertId();
        
        echo json_encode([
            'success' => true,
            'group_id' => $newId,
            'message' => 'Timeline group created'
        ]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
