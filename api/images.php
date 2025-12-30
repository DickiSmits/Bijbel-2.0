<?php
/**
 * Images API
 * 
 * Handelt alle afbeelding-gerelateerde API calls af
 */

// Include dependencies
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../includes/Database.php';
require_once __DIR__ . '/../includes/helpers.php';

$db = Database::getInstance();
$endpoint = $_GET['api'];

switch ($endpoint) {
    case 'upload_image':
        uploadImage($db);
        break;
        
    case 'all_images':
        getAllImages($db);
        break;
        
    case 'get_image':
        getImage($db);
        break;
        
    case 'update_image':
        updateImage($db);
        break;
        
    case 'delete_image':
        deleteImage($db);
        break;
    
    case 'verse_images':
        getVerseImages($db);
        break;
        
    default:
        jsonError('Onbekende images endpoint');
}

/**
 * Upload nieuwe afbeelding
 */
function uploadImage($db) {
    if (!isset($_FILES['image'])) {
        jsonError('Geen afbeelding ontvangen');
    }
    
    $file = $_FILES['image'];
    
    // Valideer afbeelding
    if (!isValidImage($file)) {
        jsonError('Ongeldige afbeelding of bestand te groot (max ' . (MAX_UPLOAD_SIZE / 1024 / 1024) . 'MB)');
    }
    
    // Genereer veilige bestandsnaam
    $filename = generateSafeFilename($file['name']);
    $serverPath = IMAGES_DIR . '/' . $filename;     // Absolute path for file operations
    $relativePath = 'images/' . $filename;          // Relative path for database/display
    
    // Verplaats bestand
    if (!move_uploaded_file($file['tmp_name'], $serverPath)) {
        jsonError('Upload mislukt');
    }
    
    // Opslaan in database
    $versId = isset($_POST['vers_id']) && $_POST['vers_id'] !== '' ? (int)$_POST['vers_id'] : null;
    $caption = $_POST['caption'] ?? '';
    $uitlijning = $_POST['uitlijning'] ?? 'center';
    $breedte = isset($_POST['breedte']) ? (int)$_POST['breedte'] : 400;
    $hoogte = isset($_POST['hoogte']) && $_POST['hoogte'] !== '' ? (int)$_POST['hoogte'] : null;
    
    $sql = "INSERT INTO Afbeeldingen 
            (Bestandsnaam, Originele_Naam, Bestandspad, Vers_ID, Caption, 
             Uitlijning, Breedte, Hoogte) 
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
    
    $db->execute($sql, [
        $filename,
        $file['name'],
        $relativePath,  // Use relative path for database
        $versId,
        $caption,
        $uitlijning,
        $breedte,
        $hoogte
    ]);
    
    jsonSuccess([
        'id' => $db->lastInsertId(),
        'url' => $relativePath  // Return relative path
    ], 'Afbeelding geüpload');
}

/**
 * Haal alle afbeeldingen op
 */
function getAllImages($db) {
    $sql = "SELECT a.*, v.Bijbelboeknaam, v.Hoofdstuknummer, v.Versnummer 
            FROM Afbeeldingen a 
            LEFT JOIN De_Bijbel v ON a.Vers_ID = v.Vers_ID 
            ORDER BY a.Geupload_Op DESC";
    
    $images = $db->query($sql);
    jsonResponse($images);
}

/**
 * Haal enkele afbeelding op
 */
function getImage($db) {
    $afbeeldingId = (int)$_GET['id'];
    
    $sql = "SELECT a.*, v.Bijbelboeknaam, v.Hoofdstuknummer, v.Versnummer 
            FROM Afbeeldingen a 
            LEFT JOIN De_Bijbel v ON a.Vers_ID = v.Vers_ID 
            WHERE a.Afbeelding_ID = ?";
    
    $image = $db->queryOne($sql, [$afbeeldingId]);
    
    if (!$image) {
        jsonError('Afbeelding niet gevonden', 404);
    }
    
    jsonResponse($image);
}

/**
 * Update afbeelding metadata
 */
function updateImage($db) {
    $data = getJsonInput();
    validateRequired($data, ['afbeelding_id']);
    
    $sql = "UPDATE Afbeeldingen SET 
            Vers_ID=?, Caption=?, Uitlijning=?, Breedte=?, Hoogte=? 
            WHERE Afbeelding_ID=?";
    
    $db->execute($sql, [
        $data['vers_id'] ?? null,
        $data['caption'] ?? '',
        $data['uitlijning'] ?? 'center',
        $data['breedte'] ?? 400,
        $data['hoogte'] ?? null,
        $data['afbeelding_id']
    ]);
    
    jsonSuccess([], 'Afbeelding bijgewerkt');
}

/**
 * Verwijder afbeelding
 */
function deleteImage($db) {
    $afbeeldingId = (int)$_GET['id'];
    
    // Haal bestandspad op
    $sql = "SELECT Bestandspad FROM Afbeeldingen WHERE Afbeelding_ID = ?";
    $image = $db->queryOne($sql, [$afbeeldingId]);
    
    if ($image) {
        $path = $image['Bestandspad'];
        
        // Handle both absolute and relative paths
        if (strpos($path, '/') === 0) {
            // Absolute path (old format)
            $filePath = $path;
        } else {
            // Relative path (new format)
            $filePath = __DIR__ . '/../' . $path;
        }
        
        if (file_exists($filePath)) {
            unlink($filePath);
        }
    }
    
    // Verwijder uit database
    $db->execute("DELETE FROM Afbeeldingen WHERE Afbeelding_ID = ?", [$afbeeldingId]);
    
    jsonSuccess([], 'Afbeelding verwijderd');
}

/**
 * Haal afbeeldingen voor specifiek vers op
 */
function getVerseImages($db) {
    $versId = isset($_GET['vers_id']) ? (int)$_GET['vers_id'] : 0;
    $debug = isset($_GET['debug']) && $_GET['debug'] == '1';
    
    if (!$versId) {
        jsonError('Vers_ID vereist');
    }
    
    // Debug mode: return extensive info
    if ($debug) {
        $debugInfo = [
            'vers_id_parameter' => $versId,
            'tests' => []
        ];
        
        // Test 1: Table exists
        try {
            $tableCheck = $db->query("SHOW TABLES LIKE 'Afbeeldingen'");
            $debugInfo['tests']['table_exists'] = count($tableCheck) > 0;
        } catch (Exception $e) {
            $debugInfo['tests']['table_exists'] = 'ERROR: ' . $e->getMessage();
        }
        
        // Test 2: Total count
        try {
            $total = $db->queryOne("SELECT COUNT(*) as cnt FROM Afbeeldingen");
            $debugInfo['tests']['total_images'] = (int)$total['cnt'];
        } catch (Exception $e) {
            $debugInfo['tests']['total_images'] = 'ERROR: ' . $e->getMessage();
        }
        
        // Test 3: Count for this Vers_ID
        try {
            $count = $db->queryOne("SELECT COUNT(*) as cnt FROM Afbeeldingen WHERE Vers_ID = ?", [$versId]);
            $debugInfo['tests']['images_for_vers'] = (int)$count['cnt'];
        } catch (Exception $e) {
            $debugInfo['tests']['images_for_vers'] = 'ERROR: ' . $e->getMessage();
        }
        
        // Test 4: All images with Vers_ID (any)
        try {
            $withVers = $db->query("SELECT Afbeelding_ID, Bestandsnaam, Vers_ID FROM Afbeeldingen WHERE Vers_ID IS NOT NULL LIMIT 5");
            $debugInfo['tests']['sample_linked_images'] = $withVers;
        } catch (Exception $e) {
            $debugInfo['tests']['sample_linked_images'] = 'ERROR: ' . $e->getMessage();
        }
        
        // Test 5: Try the actual query
        $sql = "SELECT a.* FROM Afbeeldingen a WHERE a.Vers_ID = ? ORDER BY a.Geupload_Op ASC";
        try {
            $images = $db->query($sql, [$versId]);
            $debugInfo['tests']['query_result_count'] = count($images);
            $debugInfo['tests']['query_result_data'] = $images;
        } catch (Exception $e) {
            $debugInfo['tests']['query_result_count'] = 'ERROR: ' . $e->getMessage();
        }
        
        // Test 6: Table structure
        try {
            $columns = $db->query("SHOW COLUMNS FROM Afbeeldingen");
            $debugInfo['tests']['table_columns'] = array_map(function($col) {
                return $col['Field'];
            }, $columns);
        } catch (Exception $e) {
            $debugInfo['tests']['table_columns'] = 'ERROR: ' . $e->getMessage();
        }
        
        jsonResponse($debugInfo);
        return;
    }
    
    // Normal mode: return images
    $sql = "SELECT a.* 
            FROM Afbeeldingen a 
            WHERE a.Vers_ID = ?
            ORDER BY a.Geupload_Op ASC";
    
    $images = $db->query($sql, [$versId]);
    jsonResponse($images);
}