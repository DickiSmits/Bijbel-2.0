<?php
/**
 * API: delete_image
 * Verwijder afbeelding (database + bestand)
 */

require_once __DIR__ . '/../config.php';

try {
    $db = Database::getInstance()->getConnection();
    
    $image_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
    
    if ($image_id === 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid ID']);
        exit;
    }
    
    // Get image path first
    $stmt = $db->prepare("SELECT Bestandspad FROM Afbeeldingen WHERE Afbeelding_ID = ?");
    $stmt->execute([$image_id]);
    $image = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Delete from database
    $stmt = $db->prepare("DELETE FROM Afbeeldingen WHERE Afbeelding_ID = ?");
    $stmt->execute([$image_id]);
    
    // Delete file from disk if exists
    if ($image && !empty($image['Bestandspad']) && file_exists($image['Bestandspad'])) {
        unlink($image['Bestandspad']);
    }
    
    echo json_encode(['success' => true]);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}
