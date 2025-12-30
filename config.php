<?php
/**
 * Bijbelreader Configuratie
 * 
 * BELANGRIJKE OPMERKING:
 * Dit bestand bevat gevoelige informatie (wachtwoord).
 * Voeg dit bestand toe aan .gitignore!
 */

// Base paths
define('BASE_PATH', __DIR__);
define('DB_FILE', __DIR__ . '/NWT-Bijbel.db');
define('IMAGES_DIR', __DIR__ . '/images');

// Admin wachtwoord - VERANDER DIT!
define('ADMIN_PASSWORD', 'bijbel2024');

// CDN Links - CORRECT VERSIES
define('BOOTSTRAP_CSS', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css');
define('BOOTSTRAP_JS', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js');
define('BOOTSTRAP_ICONS', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css');

define('LEAFLET_CSS', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
define('LEAFLET_JS', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js');

define('VIS_CSS', 'https://unpkg.com/vis-timeline@7.7.3/styles/vis-timeline-graph2d.min.css');
define('VIS_JS', 'https://unpkg.com/vis-timeline@7.7.3/standalone/umd/vis-timeline-graph2d.min.js');

// Quill Editor (voor admin mode)
define('QUILL_CSS', 'https://cdn.quilljs.com/1.3.6/quill.snow.css');
define('QUILL_JS', 'https://cdn.quilljs.com/1.3.6/quill.min.js');

define('IMAGES_DIR', __DIR__ . '/images');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', [
    'image/jpeg',
    'image/jpg',
    'image/png',
    'image/gif',
    'image/webp'
]);



// Autoloader voor classes
spl_autoload_register(function ($class) {
    $file = BASE_PATH . '/includes/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});

// Require helpers
require_once BASE_PATH . '/includes/helpers.php';
