<?php
/**
 * API/PROFILES.PHP - Complete Profile Management
 * 
 * Endpoints:
 * - profiles: List all profiles
 * - create_profile: Create new profile
 * - update_profile: Update existing profile
 * - delete_profile: Delete profile
 * 
 * UPDATES:
 * - Added UNIQUE constraint checks
 * - Accepts both 'id' and 'profiel_id' parameter names (flexible)
 * - Better error messages
 * - Improved validation
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
            http_response_code(500);
            echo json_encode(['error' => 'Fout bij ophalen profielen']);
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
        
        // Validate input
        if (!isset($data['naam']) || trim($data['naam']) === '') {
            http_response_code(400);
            echo json_encode(['error' => 'Naam is verplicht']);
            exit;
        }
        
        $naam = trim($data['naam']);
        $beschrijving = isset($data['beschrijving']) ? trim($data['beschrijving']) : null;
        
        try {
            // ✅ CHECK: Does name already exist?
            $checkStmt = $db->prepare("SELECT COUNT(*) FROM Profielen WHERE Profiel_Naam = ?");
            $checkStmt->execute([$naam]);
            
            if ($checkStmt->fetchColumn() > 0) {
                http_response_code(409); // Conflict
                echo json_encode([
                    'success' => false,
                    'error' => 'Een profiel met deze naam bestaat al. Kies een andere naam.'
                ]);
                exit;
            }
            
            // Insert new profile
            $stmt = $db->prepare("
                INSERT INTO Profielen (Profiel_Naam, Beschrijving, Aangemaakt_Op) 
                VALUES (?, ?, datetime('now'))
            ");
            
            $stmt->execute([$naam, $beschrijving]);
            
            $newId = $db->lastInsertId();
            
            echo json_encode([
                'success' => true,
                'id' => (string)$newId,
                'message' => 'Profiel aangemaakt'
            ]);
            
        } catch (PDOException $e) {
            error_log("Create profile error: " . $e->getMessage());
            
            // Check if it's a UNIQUE constraint error
            if (strpos($e->getMessage(), 'UNIQUE constraint') !== false) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'error' => 'Een profiel met deze naam bestaat al'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Database fout bij aanmaken profiel'
                ]);
            }
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
        
        // ✅ FIX: Accept both 'id' and 'profiel_id' parameter names
        $profielId = null;
        if (isset($data['id'])) {
            $profielId = (int)$data['id'];
        } elseif (isset($data['profiel_id'])) {
            $profielId = (int)$data['profiel_id'];
        }
        
        // Validate
        if (!$profielId || !isset($data['naam'])) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Profiel ID en naam zijn verplicht'
            ]);
            exit;
        }
        
        if ($profielId <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Ongeldig profiel ID'
            ]);
            exit;
        }
        
        $naam = trim($data['naam']);
        $beschrijving = isset($data['beschrijving']) ? trim($data['beschrijving']) : null;
        
        if ($naam === '') {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Naam mag niet leeg zijn'
            ]);
            exit;
        }
        
        try {
            // ✅ CHECK: Profile exists?
            $checkStmt = $db->prepare("SELECT Profiel_Naam FROM Profielen WHERE Profiel_ID = ?");
            $checkStmt->execute([$profielId]);
            $existing = $checkStmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$existing) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Profiel niet gevonden'
                ]);
                exit;
            }
            
            // ✅ CHECK: Another profile with same name? (exclude current profile)
            $checkStmt = $db->prepare("
                SELECT COUNT(*) 
                FROM Profielen 
                WHERE Profiel_Naam = ? AND Profiel_ID != ?
            ");
            $checkStmt->execute([$naam, $profielId]);
            
            if ($checkStmt->fetchColumn() > 0) {
                http_response_code(409); // Conflict
                echo json_encode([
                    'success' => false,
                    'error' => 'Een ander profiel met deze naam bestaat al'
                ]);
                exit;
            }
            
            // Update the profile
            $stmt = $db->prepare("
                UPDATE Profielen 
                SET Profiel_Naam = ?, 
                    Beschrijving = ?
                WHERE Profiel_ID = ?
            ");
            
            $result = $stmt->execute([$naam, $beschrijving, $profielId]);
            
            if ($result) {
                echo json_encode([
                    'success' => true,
                    'id' => (string)$profielId,
                    'message' => 'Profiel bijgewerkt'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Profiel kon niet worden bijgewerkt'
                ]);
            }
            
        } catch (PDOException $e) {
            error_log("Update profile error: " . $e->getMessage());
            
            // Check if it's a UNIQUE constraint error
            if (strpos($e->getMessage(), 'UNIQUE constraint') !== false) {
                http_response_code(409);
                echo json_encode([
                    'success' => false,
                    'error' => 'Een profiel met deze naam bestaat al'
                ]);
            } else {
                http_response_code(500);
                echo json_encode([
                    'success' => false,
                    'error' => 'Database fout bij bijwerken profiel'
                ]);
            }
        }
        break;
    
    // ============= DELETE PROFILE =============
    case 'delete_profile':
        $profielId = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if ($profielId <= 0) {
            http_response_code(400);
            echo json_encode([
                'success' => false,
                'error' => 'Ongeldig profiel ID'
            ]);
            exit;
        }
        
        try {
            // Check if profile exists
            $stmt = $db->prepare("SELECT Profiel_Naam FROM Profielen WHERE Profiel_ID = ?");
            $stmt->execute([$profielId]);
            $profile = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$profile) {
                http_response_code(404);
                echo json_encode([
                    'success' => false,
                    'error' => 'Profiel niet gevonden'
                ]);
                exit;
            }
            
            // Delete profile (CASCADE will delete related Opmaak records)
            $stmt = $db->prepare("DELETE FROM Profielen WHERE Profiel_ID = ?");
            $stmt->execute([$profielId]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Profiel verwijderd'
            ]);
            
        } catch (PDOException $e) {
            error_log("Delete profile error: " . $e->getMessage());
            http_response_code(500);
            echo json_encode([
                'success' => false,
                'error' => 'Database fout bij verwijderen profiel'
            ]);
        }
        break;
    
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown profile endpoint: ' . $endpoint]);
}
?>