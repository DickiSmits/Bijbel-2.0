<?php
/**
 * API: update_timeline_group
 * Update timeline groep
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
    
    // Validate
    if (empty($data['group_id']) || empty($data['naam'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Group_id en naam zijn verplicht']);
        exit;
    }
    
    $stmt = $db->prepare("UPDATE Timeline_Groups SET 
        Groep_Naam = ?, 
        Beschrijving = ?, 
        Kleur = ?, 
        Volgorde = ? 
        WHERE Group_ID = ?");
    
    $stmt->execute([
        $data['naam'],
        $data['beschrijving'] ?? '',
        $data['kleur'] ?? '#3498db',
        $data['volgorde'] ?? 0,
        $data['group_id']
    ]);
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
