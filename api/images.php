<?php
/**
 * API/IMAGES.PHP - Image Management API Endpoints
 */

// Get database connection
$db = Database::getInstance()->getConnection();

$endpoint = $_GET['api'];
$images_dir = 'images';

// Ensure images directory exists
if (!is_dir($images_dir)) {
    mkdir($images_dir, 0755, true);
}

switch ($endpoint) {
    
    // ============= ALL IMAGES =============
    case 'all_images':
        try {
            $stmt = $db->query("
                SELECT a.*, 
                       v.Bijbelboeknaam, 
                       v.Hoofdstuknummer, 
                       v.Versnummer
                FROM Afbeeldingen a
                LEFT JOIN Verzen v ON a.Vers_ID = v.Vers_ID
                ORDER BY a.Geupload_Op DESC
            ");
            
            $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode($images);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
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
                SELECT a.*, 
                       v.Bijbelboeknaam, 
                       v.Hoofdstuknummer, 
                       v.Versnummer
                FROM Afbeeldingen a
                LEFT JOIN Verzen v ON a.Vers_ID = v.Vers_ID
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
    
    // ============= SAVE IMAGE (Upload NEW or UPDATE existing) =============
    case 'save_image':
    case 'upload_image':
    case 'update_image':
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            echo json_encode(['error' => 'POST required']);
            exit;
        }
        
        $imageId = isset($_POST['image_id']) ? (int)$_POST['image_id'] : 0;
        $caption = isset($_POST['caption']) ? $_POST['caption'] : null;
        $versId = isset($_POST['vers_id']) && $_POST['vers_id'] !== '' ? (int)$_POST['vers_id'] : null;
        $file = isset($_FILES['image']) ? $_FILES['image'] : null;
        
        // For new uploads, file is required
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
                
                // Update caption if provided
                if ($caption !== null) {
                    $updates[] = "Caption = ?";
                    $params[] = $caption;
                }
                
                // Update verse link
                if ($versId !== null) {
                    $updates[] = "Vers_ID = ?";
                    $params[] = $versId;
                } else if (isset($_POST['vers_id']) && $_POST['vers_id'] === '') {
                    // Explicitly remove verse link
                    $updates[] = "Vers_ID = NULL";
                }
                
                // If new file uploaded, update file info
                if ($file && $file['error'] === UPLOAD_ERR_OK) {
                    // Validate file type
                    $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                    if (!in_array($file['type'], $allowed)) {
                        http_response_code(400);
                        echo json_encode(['error' => 'Invalid file type. Only JPG, PNG, GIF, WEBP allowed']);
                        exit;
                    }
                    
                    // Generate filename
                    $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
                    $filepath = $images_dir . '/' . $filename;
                    
                    // Move uploaded file
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $updates[] = "Bestandspad = ?";
                        $updates[] = "Bestandsnaam = ?";
                        $updates[] = "Originele_Naam = ?";
                        $params[] = $filepath;
                        $params[] = $filename;
                        $params[] = basename($file['name']);
                        
                        // Delete old file (optional)
                        $stmt = $db->prepare("SELECT Bestandspad FROM Afbeeldingen WHERE Afbeelding_ID = ?");
                        $stmt->execute([$imageId]);
                        $old = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($old && file_exists($old['Bestandspad'])) {
                            @unlink($old['Bestandspad']);
                        }
                    } else {
                        http_response_code(500);
                        echo json_encode(['error' => 'Failed to save file']);
                        exit;
                    }
                }
                
                // Execute update if there are changes
                if (!empty($updates)) {
                    $params[] = $imageId;
                    $sql = "UPDATE Afbeeldingen SET " . implode(', ', $updates) . " WHERE Afbeelding_ID = ?";
                    $stmt = $db->prepare($sql);
                    $stmt->execute($params);
                }
                
                echo json_encode([
                    'success' => true, 
                    'image_id' => $imageId, 
                    'action' => 'updated'
                ]);
                
            } else {
                // ===== INSERT NEW IMAGE =====
                
                if (!$file || $file['error'] !== UPLOAD_ERR_OK) {
                    http_response_code(400);
                    echo json_encode(['error' => 'File upload failed']);
                    exit;
                }
                
                // Validate file type
                $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
                if (!in_array($file['type'], $allowed)) {
                    http_response_code(400);
                    echo json_encode(['error' => 'Invalid file type']);
                    exit;
                }
                
                // Generate filename
                $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file['name']));
                $filepath = $images_dir . '/' . $filename;
                
                // Move uploaded file
                if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                    http_response_code(500);
                    echo json_encode(['error' => 'Failed to save file']);
                    exit;
                }
                
                // Insert into database
                $stmt = $db->prepare("
                    INSERT INTO Afbeeldingen 
                    (Bestandspad, Bestandsnaam, Originele_Naam, Caption, Vers_ID, Geupload_Op) 
                    VALUES (?, ?, ?, ?, ?, datetime('now'))
                ");
                
                $stmt->execute([
                    $filepath,
                    $filename,
                    basename($file['name']),
                    $caption,
                    $versId
                ]);
                
                $newId = $db->lastInsertId();
                
                echo json_encode([
                    'success' => true, 
                    'image_id' => $newId, 
                    'action' => 'created'
                ]);
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
            // Get file path before deleting
            $stmt = $db->prepare("SELECT Bestandspad FROM Afbeeldingen WHERE Afbeelding_ID = ?");
            $stmt->execute([$id]);
            $image = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if ($image) {
                // Delete from database
                $stmt = $db->prepare("DELETE FROM Afbeeldingen WHERE Afbeelding_ID = ?");
                $stmt->execute([$id]);
                
                // Delete file from disk (with error suppression in case file doesn't exist)
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
                SELECT a.*, 
                       v.Bijbelboeknaam, 
                       v.Hoofdstuknummer, 
                       v.Versnummer
                FROM Afbeeldingen a
                LEFT JOIN Verzen v ON a.Vers_ID = v.Vers_ID
                WHERE a.Vers_ID = ?
                ORDER BY a.Geupload_Op DESC
            ");
            
            $stmt->execute([$versId]);
            $images = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            echo json_encode($images);
            
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
    
    default:
        http_response_code(404);
        echo json_encode(['error' => 'Unknown image endpoint: ' . $endpoint]);
}