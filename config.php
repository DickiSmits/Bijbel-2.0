<?php
/**
 * Bijbelreader - Configuratie
 * 
 * Bevat alle configuratie-instellingen voor de applicatie
 */

// Foutafhandeling
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Applicatie configuratie
define('APP_NAME', 'Bijbelreader');
define('APP_VERSION', '2.0.0');
define('BASE_PATH', __DIR__);
define('BASE_URL', '');

// Database configuratie
define('DB_FILE', BASE_PATH . '/NWT-Bijbel.db');

// Admin configuratie
define('ADMIN_PASSWORD', 'bijbel2024'); // VERANDER DIT!

// Upload configuratie
define('IMAGES_DIR', BASE_PATH . '/images');
define('MAX_UPLOAD_SIZE', 5 * 1024 * 1024); // 5MB
define('ALLOWED_IMAGE_TYPES', ['image/jpeg', 'image/png', 'image/gif', 'image/webp']);

// Sessie configuratie
define('SESSION_NAME', 'bijbelreader_session');
define('SESSION_LIFETIME', 86400); // 24 uur

// API configuratie
define('API_RATE_LIMIT', 100); // Requests per minuut
define('DEFAULT_LIMIT', 50); // Standaard aantal resultaten

// Paden
define('VIEWS_PATH', BASE_PATH . '/views');
define('INCLUDES_PATH', BASE_PATH . '/includes');
define('API_PATH', BASE_PATH . '/api');
define('ASSETS_PATH', BASE_PATH . '/assets');

// URL's voor externe bibliotheken
define('BOOTSTRAP_CSS', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css');
define('BOOTSTRAP_JS', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js');
define('BOOTSTRAP_ICONS', 'https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css');
define('LEAFLET_CSS', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css');
define('LEAFLET_JS', 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js');
define('VIS_CSS', 'https://unpkg.com/vis-timeline@7.7.3/styles/vis-timeline-graph2d.min.css');
define('VIS_JS', 'https://unpkg.com/vis-timeline@7.7.3/standalone/umd/vis-timeline-graph2d.min.js');
define('QUILL_CSS', 'https://cdn.quilljs.com/1.3.6/quill.snow.css');
define('QUILL_JS', 'https://cdn.quilljs.com/1.3.6/quill.min.js');

// Timezone
date_default_timezone_set('Europe/Amsterdam');

// Autoloader voor classes
spl_autoload_register(function ($class) {
    $file = INCLUDES_PATH . '/' . $class . '.php';
    if (file_exists($file)) {
        require_once $file;
    }
});
