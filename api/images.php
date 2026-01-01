<?php
/**
 * API/IMAGES.PHP - PERFECT VERSION
 * 
 * Database schema:
 * - Table: Afbeeldingen (with columns: Afbeelding_ID, Bestandsnaam, etc.)
 * - Table: De_Bijbel (NOT "Verzen"!)
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
$images_dir = 'images';

if (!is_dir($images_dir)) {
    @mkdir($images_dir, 0755, true);
}

switch ($endpoint) {
    
    // ============= ALL IMAGES =============
    case 'all_images':
        try {
            $stmt = $db->query("
                SELECT 
                    a.Afbeelding_ID,
                    a.Bestandsnaam,
                    a.Originele_Naam,
                    a.Bestandspad,
                    a.Caption,
                    a.Vers_ID,
                    a.Geupload_Op,
                    a.Uitlijning,
                    a.Breedte,
                    a.Hoogte,
                    v.Bijbelboeknaam,
                    v.Hoofdstuknummer,
                    v.Versnummer
                FROM Afbeeldingen a
                LEFT JOIN De_Bijbel v ON a.Vers_ID = v.Vers_ID
                ORDER BY a.Geupload_Op DESC
            ");
            
            $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($images ?: []);
            
        } catch (Exception $e) {
            error_log("Images query error: " . $e->getMessage());
            echo json_encode([]);
        }
        break;
    
    // ============= GET IMAGE =============
    case 'get_image':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID required']);
            exit;
        }
        
        try {
            $stmt = $db->prepare("
                SELECT 
                    a.*,
                    v.Bijbelboeknaam,
                    v.Hoofdstuknummer,
                    v.Versnummer
                FROM Afbeeldingen a
                LEFT JOIN De_Bijbel v ON a.Vers_ID = v.Vers_ID
                WHERE a.Afbeelding_ID = ?
            ");
            
            $stmt->execute([$id]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($image) {
                echo json_encode($image);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Image not found']);
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
    
    // ============= SAVE IMAGE =============
    case 'save_image':
    case 'upload_image':
    case 'update_image':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'POST required']);
            exit;
        }
        
        $imageId = isset($_POST['image_id']) ? (int)$_POST['image_id'] : 0;
        $caption = isset($_POST['caption']) ? $_POST['caption'] : '';
        $versId = isset($_POST['vers_id']) && $_POST['vers_id'] !== '' ? (int)$_POST['vers_id'] : null;
        $file = isset($_FILES['image']) ? $_FILES['image'] : null;
        
        // NEW: Get layout & dimensions
        $uitlijning = isset($_POST['uitlijning']) ? $_POST['uitlijning'] : 'center';
        $breedte = isset($_POST['breedte']) && $_POST['breedte'] !== '' ? (int)$_POST['breedte'] : 400;
        $hoogte = isset($_POST['hoogte']) && $_POST['hoogte'] !== '' ? (int)$_POST['hoogte'] : null;
        
        // Validate uitlijning
        if (!in_array($uitlijning, ['left', 'center', 'right'])) {
            $uitlijning = 'center';
        }
        
        if (!$imageId && !$file) {
            http_response_code(400);
            echo json_encode(['error' => 'Image file required for new uploads']);
            exit;
        }
        
        try {
            if ($imageId) {
                // ===== UPDATE EXISTING IMAGE =====
                $updates = [];
                $params = [];
                
                $updates[] = "Caption = ?";
                $params[] = $caption;
                
                if ($versId !== null) {
                    $updates[] = "Vers_ID = ?";
                    $params[] = $versId;
                } else {
                    $updates[] = "Vers_ID = NULL";
                }
                
                // NEW: Update layout & dimensions
                $updates[] = "Uitlijning = ?";
                $params[] = $uitlijning;
                
                $updates[] = "Breedte = ?";
                $params[] = $breedte;
                
                if ($hoogte !== null) {
                    $updates[] = "Hoogte = ?";
                    $params[] = $hoogte;
                } else {
                    $updates[] = "Hoogte = NULL";
                }
                
                if ($file && $file['error'] === UPLOAD_ERR_OK) {
                    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    $finfo = finfo_open(FILEINFO_MIME_TYPE);
                    $mimeType = finfo_file($finfo, $file['tmp_name']);
                    finfo_close($finfo);
                    
                    if (!in_array($mimeType, $allowed)) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid file type']);
                        exit;
                    }
                    
                    $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
                    $filepath = $images_dir . '/' . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $updates[] = "Bestandspad = ?";
                        $updates[] = "Bestandsnaam = ?";
                        $updates[] = "Originele_Naam = ?";
                        $params[] = $filepath;
                        $params[] = $filename;
                        $params[] = basename($file['name']);
                        
                        $stmt = $db->prepare("SELECT Bestandspad FROM Afbeeldingen WHERE Afbeelding_ID = ?");
                        $stmt->execute([$imageId]);
                        $old = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($old && file_exists($old['Bestandspad'])) {
                            @unlink($old['Bestandspad']);
                        }
                    }
                }
                
                if (!empty($updates)) {
                    $params[] = $imageId;
                    $sql = "UPDATE Afbeeldingen SET " . implode(', ', $updates) . " WHERE Afbeelding_ID = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                }
                
                echo json_encode(['success' => true, 'image_id' => $imageId, 'action' => 'updated']);
                
            } else {
                // ===== INSERT NEW IMAGE =====
                
                if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                    http_response_code(400);
                    echo json_encode(['error' => 'File upload failed']);
                    exit;
                }
                
                $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                $finfo = finfo_open(FILEINFO_MIME_TYPE);
                $mimeType = finfo_file($finfo, $file['tmp_name']);
                finfo_close($finfo);
                
                if (!in_array($mimeType, $allowed)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid file type']);
                    exit;
                }
                
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
                $filepath = $images_dir . '/' . $filename;
                
                if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to save file']);
                    exit;
                }
                
                // NEW: Insert with layout & dimensions
                $stmt = $db->prepare("
                    INSERT INTO Afbeeldingen 
                    (Bestandspad, Bestandsnaam, Originele_Naam, Caption, Vers_ID, Geupload_Op, Uitlijning, Breedte, Hoogte) 
                    VALUES (?, ?, ?, ?, ?, datetime('now'), ?, ?, ?)
                ");
                
                $stmt->execute([
                    $filepath,
                    $filename,
                    basename($file['name']),
                    $caption,
                    $versId,
                    $uitlijning,
                    $breedte,
                    $hoogte
                ]);
                
                $newId = $db->lastInsertId();
                
                echo json_encode(['success' => true, 'image_id' => $newId, 'action' => 'created']);
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
    
    // ============= DELETE IMAGE =============
    case 'delete_image':
        $id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
        
        if (!$id) {
            http_response_code(400);
            echo json_encode(['error' => 'ID required']);
            exit;
        }
        
        try {
            $stmt = $db->prepare("SELECT Bestandspad FROM Afbeeldingen WHERE Afbeelding_ID = ?");
            $stmt->execute([$id]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($image) {
                $stmt = $db->prepare("DELETE FROM Afbeeldingen WHERE Afbeelding_ID = ?");
                $stmt->execute([$id]);
                
                if (file_exists($image['Bestandspad'])) {
                    @unlink($image['Bestandspad']);
                }
                
                echo json_encode(['success' => true]);
            } else {
                http_response_code(404);
                echo json_encode(['error' => 'Image not found']);
            }
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
    
    // ============= VERSE IMAGES =============
    case 'verse_images':
        $versId = isset($_GET['vers_id']) ? (int)$_GET['vers_id'] : 0;
        
        if (!$versId) {
            http_response_code(400);
            echo json_encode(['error' => 'Vers ID required']);
            exit;
        }
        
        try {
            $stmt = $db->prepare("
                SELECT 
                    a.*,
                    v.Bijbelboeknaam,
                    v.Hoofdstuknummer,
                    v.Versnummer
                FROM Afbeeldingen a
                LEFT JOIN De_Bijbel v ON a.Vers_ID = v.Vers_ID
                WHERE a.Vers_ID = ?
                ORDER BY a.Geupload_Op DESC
            ");
            
            $stmt->execute([$versId]);
            $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($images ?: []);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
    
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown image endpoint: ' . $endpoint]);
}