<?php
/**
 * API: Create Profile
 */

require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');

try {
    $db = Database::getInstance()->getConnection();
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['naam']) || empty(trim($input['naam']))) {
        http_response_code(400);
        echo json_encode(['error' => 'Naam is verplicht']);
        exit;
    }
    
    $naam = trim($input['naam']);
    $beschrijving = isset($input['beschrijving']) ? trim($input['beschrijving']) : '';
    
    $stmt = $db->prepare("INSERT INTO Profielen (Profiel_Naam, Beschrijving) VALUES (?, ?)");
    $stmt->execute([$naam, $beschrijving]);
    
    echo json_encode([
        'success' => true,
        'id' => $db->lastInsertId(),
        'message' => 'Profiel aangemaakt'
    ]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
