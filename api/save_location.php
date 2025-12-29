<?php
/**
 * API: save_location
 * Create of update locatie
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
    if (empty($data['naam']) || !isset($data['latitude']) || !isset($data['longitude'])) {
        http_response_code(400);
        echo json_encode(['error' => 'Naam, latitude en longitude zijn verplicht']);
        exit;
    }
    
    if (isset($data['locatie_id']) && $data['locatie_id']) {
        // UPDATE existing location
        $stmt = $db->prepare("UPDATE Locaties SET 
            Naam = ?, 
            Type = ?, 
            Beschrijving = ?, 
            Latitude = ?, 
            Longitude = ? 
            WHERE Locatie_ID = ?");
        
        $stmt->execute([
            $data['naam'],
            $data['type'] ?? 'overig',
            $data['beschrijving'] ?? '',
            $data['latitude'],
            $data['longitude'],
            $data['locatie_id']
        ]);
        
        echo json_encode(['success' => true, 'locatie_id' => $data['locatie_id']]);
        
    } else {
        // INSERT new location
        $stmt = $db->prepare("INSERT INTO Locaties 
            (Naam, Type, Beschrijving, Latitude, Longitude) 
            VALUES (?, ?, ?, ?, ?)");
        
        $stmt->execute([
            $data['naam'],
            $data['type'] ?? 'overig',
            $data['beschrijving'] ?? '',
            $data['latitude'],
            $data['longitude']
        ]);
        
        echo json_encode(['success' => true, 'locatie_id' => $db->lastInsertId()]);
    }
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
