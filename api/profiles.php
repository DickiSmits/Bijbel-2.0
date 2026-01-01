<?php
/**
 * API/PROFILES.PHP - Complete Profile Management
 * 
 * Endpoints:
 * - profiles: List all profiles
 * - create_profile: Create new profile
 * - update_profile: Update existing profile
 * - delete_profile: Delete profile
 */

// Get database connection
try {
    $db = Database::getInstance()->getConnection();
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error: ' . $e->getMessage()]);
    exit;
}

$endpoint = $_GET['api'];

switch ($endpoint) {
    
    // ============= LIST PROFILES =============
    case 'profiles':
        try {
            $stmt = $db->query("
                SELECT 
                    Profiel_ID,
                    Profiel_Naam,
                    Beschrijving,
                    Actief,
                    Aangemaakt_Op as Aangemaakt
                FROM Profielen
                ORDER BY Profiel_Naam ASC
            ");
            
            $profiles = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($profiles ?: []);
            
        } catch (Exception $e) {
            error_log("Profiles query error: " . $e->getMessage());
            echo json_encode([]);
        }
        break;
    
    // ============= CREATE PROFILE =============
    case 'create_profile':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'POST required']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['naam']) || trim($data['naam']) === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Naam is verplicht']);
            exit;
        }
        
        try {
            $stmt = $db->prepare("
                INSERT INTO Profielen (Profiel_Naam, Beschrijving, Aangemaakt_Op) 
                VALUES (?, ?, datetime('now'))
            ");
            
            $stmt->execute([
                $data['naam'],
                isset($data['beschrijving']) ? $data['beschrijving'] : null
            ]);
            
            $newId = $db->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'id' => $newId,
                'message' => 'Profiel aangemaakt'
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
    
    // ============= UPDATE PROFILE =============
    case 'update_profile':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'POST required']);
            exit;
        }
        
        $data = json_decode(file_get_contents('php://input'), true);
        
        if (!isset($data['profiel_id']) || !isset($data['naam'])) {
            http_response_code(400);
            echo json_encode(['error' => 'Profiel ID en naam zijn verplicht']);
            exit;
        }
        
        $profielId = (int)$data['profiel_id'];
        
        if ($profielId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Ongeldig profiel ID']);
            exit;
        }
        
        try {
            $stmt = $db->prepare("
                UPDATE Profielen 
                SET Profiel_Naam = ?, Beschrijving = ? 
                WHERE Profiel_ID = ?
            ");
            
            $stmt->execute([
                $data['naam'],
                isset($data['beschrijving']) ? $data['beschrijving'] : null,
                $profielId
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Profiel bijgewerkt'
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
    
    // ============= DELETE PROFILE =============
    case 'delete_profile':
        $profielId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($profielId <= 0) {
            http_response_code(400);
            echo json_encode(['error' => 'Ongeldig profiel ID']);
            exit;
        }
        
        try {
            // Check if profile exists
            $stmt = $db->prepare("SELECT Profiel_Naam FROM Profielen WHERE Profiel_ID = ?");
            $stmt->execute([$profielId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$profile) {
                http_response_code(404);
                echo json_encode(['error' => 'Profiel niet gevonden']);
                exit;
            }
            
            // Delete profile (CASCADE will delete related Opmaak records)
            $stmt = $db->prepare("DELETE FROM Profielen WHERE Profiel_ID = ?");
            $stmt->execute([$profielId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Profiel verwijderd'
            ]);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
    
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown profile endpoint: ' . $endpoint]);
}
