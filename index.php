<?php
/**
 * Bijbelreader - Main Entry Point
 * 
 * Dit bestand handelt alle requests af en routeert naar de juiste
 * API endpoints of views.
 */

// Load configuratie
require_once __DIR__ . '/config.php';
require_once INCLUDES_PATH . '/helpers.php';

// Zorg dat images directory bestaat
if (!file_exists(IMAGES_DIR)) {
    mkdir(IMAGES_DIR, 0755, true);
}

// Start sessie
Auth::startSession();

// ============= API ROUTING =============
if (isset($_GET['api'])) {
    $endpoint = $_GET['api'];
    $apiFile = API_PATH . '/' . getApiFile($endpoint);
    
    if (file_exists($apiFile)) {
        require_once $apiFile;
    } else {
        jsonError('Onbekende API endpoint', 404);
    }
    exit;
}

// ============= AUTHENTICATION HANDLING =============
if (isset($_POST['login'])) {
    if (Auth::login($_POST['password'] ?? '')) {
        header('Location: ?mode=admin');
        exit;
    } else {
        $loginError = 'Onjuist wachtwoord';
    }
}

if (isset($_GET['logout'])) {
    Auth::logout();
    header('Location: ?mode=reader');
    exit;
}

// ============= PAGE ROUTING =============
$mode = $_GET['mode'] ?? 'reader';
$isAdmin = Auth::isAdmin();

// Admin mode vereist login
if ($mode === 'admin' && !$isAdmin) {
    $mode = 'login';
}

// Load de juiste view
switch ($mode) {
    case 'reader':
        view('reader', ['isAdmin' => $isAdmin]);
        break;
        
    case 'admin':
        view('admin', ['isAdmin' => $isAdmin]);
        break;
        
    case 'login':
        view('login', ['loginError' => $loginError ?? null]);
        break;
        
    default:
        header('Location: ?mode=reader');
        exit;
}

/**
 * Helper: Bepaal welk API bestand geladen moet worden
 */
function getApiFile($endpoint) {
    // Map API endpoints naar bestanden
    $mapping = [
        // Verses
        'verses' => 'verses.php',
        'books' => 'verses.php',
        'chapters' => 'verses.php',
        'search' => 'verses.php',
        'verse_detail' => 'verses.php',
        'verse_images' => 'verses.php',
        'get_vers_id' => 'verses.php',
        'get_vers_info' => 'verses.php',
        
        // Profiles & Formatting
        'profiles' => 'profiles.php',
        'create_profile' => 'profiles.php',
        'delete_profile' => 'profiles.php',
        'save_formatting' => 'profiles.php',
        'all_formatting' => 'profiles.php',
        'delete_formatting' => 'profiles.php',
        
        // Timeline
        'timeline' => 'timeline.php',
        'save_timeline' => 'timeline.php',
        'delete_timeline' => 'timeline.php',
        'get_timeline' => 'timeline.php',
        'timeline_groups' => 'timeline.php',
        'create_timeline_group' => 'timeline.php',
        'update_timeline_group' => 'timeline.php',
        'delete_timeline_group' => 'timeline.php',
        'toggle_timeline_group' => 'timeline.php',
        
        // Locations
        'locations' => 'locations.php',
        'get_location' => 'locations.php',
        'save_location' => 'locations.php',
        'delete_location' => 'locations.php',
        'link_verse_location' => 'locations.php',
        
        // Images
        'upload_image' => 'images.php',
        'all_images' => 'images.php',
        'get_image' => 'images.php',
        'update_image' => 'images.php',
        'delete_image' => 'images.php',
        
        // Notes
        'notes' => 'notes.php',
        'get_note' => 'notes.php',
        'save_note' => 'notes.php',
        'delete_note' => 'notes.php',
        
        // Import/Export
        'export' => 'import-export.php',
        'import' => 'import-export.php',
    ];
    
    return $mapping[$endpoint] ?? 'unknown.php';
}
