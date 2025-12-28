<?php
/**
 * Debug Script
 * Test of alle componenten werken
 */

// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<!DOCTYPE html><html><head><title>Bijbelreader Debug</title>";
echo "<style>body{font-family:monospace;padding:20px;} .ok{color:green;} .error{color:red;} .warning{color:orange;}</style>";
echo "</head><body>";
echo "<h1>Bijbelreader Setup Debug</h1>";

// Test 1: Check config.php
echo "<h2>1. Config.php</h2>";
if (file_exists('config.php')) {
    echo "<p class='ok'>✓ config.php bestaat</p>";
    require_once 'config.php';
    
    echo "<p>APP_NAME: " . (defined('APP_NAME') ? APP_NAME : '<span class="error">NOT DEFINED</span>') . "</p>";
    echo "<p>DB_FILE: " . (defined('DB_FILE') ? DB_FILE : '<span class="error">NOT DEFINED</span>') . "</p>";
    echo "<p>BASE_PATH: " . (defined('BASE_PATH') ? BASE_PATH : '<span class="error">NOT DEFINED</span>') . "</p>";
} else {
    echo "<p class='error'>✗ config.php NIET GEVONDEN!</p>";
    die("STOP: config.php is vereist");
}

// Test 2: Check includes
echo "<h2>2. Includes Directory</h2>";
$includes = ['Database.php', 'Auth.php', 'helpers.php'];
foreach ($includes as $file) {
    $path = 'includes/' . $file;
    if (file_exists($path)) {
        echo "<p class='ok'>✓ $file bestaat</p>";
    } else {
        echo "<p class='error'>✗ $file NIET GEVONDEN!</p>";
    }
}

// Test 3: Check if Database class loads
echo "<h2>3. Database Class</h2>";
try {
    if (class_exists('Database')) {
        echo "<p class='ok'>✓ Database class is geladen</p>";
        
        $db = Database::getInstance();
        echo "<p class='ok'>✓ Database instance verkregen</p>";
        
        // Test query
        $result = $db->query("SELECT COUNT(*) as count FROM De_Bijbel");
        if ($result && count($result) > 0) {
            echo "<p class='ok'>✓ Database query werkt! Aantal verzen: " . $result[0]['count'] . "</p>";
        } else {
            echo "<p class='error'>✗ Database query faalt</p>";
        }
        
    } else {
        echo "<p class='error'>✗ Database class NIET geladen</p>";
    }
} catch (Exception $e) {
    echo "<p class='error'>✗ Database error: " . $e->getMessage() . "</p>";
}

// Test 4: Check database file
echo "<h2>4. Database Bestand</h2>";
if (file_exists(DB_FILE)) {
    echo "<p class='ok'>✓ Database bestand bestaat: " . DB_FILE . "</p>";
    echo "<p>Grootte: " . filesize(DB_FILE) . " bytes</p>";
    echo "<p>Leesbaar: " . (is_readable(DB_FILE) ? '<span class="ok">JA</span>' : '<span class="error">NEE</span>') . "</p>";
    echo "<p>Schrijfbaar: " . (is_writable(DB_FILE) ? '<span class="ok">JA</span>' : '<span class="warning">NEE (alleen lezen)</span>') . "</p>";
} else {
    echo "<p class='error'>✗ Database bestand NIET GEVONDEN: " . DB_FILE . "</p>";
}

// Test 5: Check API directory
echo "<h2>5. API Directory</h2>";
$apiFiles = ['verses.php', 'profiles.php', 'timeline.php', 'locations.php', 'images.php', 'notes.php'];
foreach ($apiFiles as $file) {
    $path = 'api/' . $file;
    if (file_exists($path)) {
        echo "<p class='ok'>✓ $file bestaat</p>";
    } else {
        echo "<p class='error'>✗ $file NIET GEVONDEN!</p>";
    }
}

// Test 6: Check views directory
echo "<h2>6. Views Directory</h2>";
$viewFiles = ['reader.php'];
foreach ($viewFiles as $file) {
    $path = 'views/' . $file;
    if (file_exists($path)) {
        echo "<p class='ok'>✓ $file bestaat</p>";
    } else {
        echo "<p class='error'>✗ $file NIET GEVONDEN!</p>";
    }
}

// Test 7: Check assets
echo "<h2>7. Assets</h2>";
$assets = [
    'assets/css/styles.css',
    'assets/js/app.js',
    'assets/js/reader.js',
    'assets/js/map.js',
    'assets/js/timeline.js'
];
foreach ($assets as $asset) {
    if (file_exists($asset)) {
        echo "<p class='ok'>✓ $asset bestaat</p>";
    } else {
        echo "<p class='error'>✗ $asset NIET GEVONDEN!</p>";
    }
}

// Test 8: Test API call
echo "<h2>8. Test API Call</h2>";
echo "<p>Test URL: <a href='?api=books'>?api=books</a></p>";
echo "<p>Klik op de link hierboven om te testen of de API werkt</p>";

// Test 9: Directory permissions
echo "<h2>9. Directory Permissions</h2>";
$dirs = ['.', 'api', 'includes', 'views', 'assets', 'images'];
foreach ($dirs as $dir) {
    if (is_dir($dir)) {
        $perms = substr(sprintf('%o', fileperms($dir)), -4);
        $readable = is_readable($dir);
        $writable = is_writable($dir);
        echo "<p>$dir: $perms | Lezen: " . ($readable ? '<span class="ok">JA</span>' : '<span class="error">NEE</span>');
        echo " | Schrijven: " . ($writable ? '<span class="ok">JA</span>' : '<span class="warning">NEE</span>') . "</p>";
    } else {
        echo "<p class='error'>$dir bestaat niet!</p>";
    }
}

// Test 10: PHP version and extensions
echo "<h2>10. PHP Informatie</h2>";
echo "<p>PHP Versie: " . phpversion() . "</p>";
echo "<p>PDO SQLite: " . (extension_loaded('pdo_sqlite') ? '<span class="ok">GELADEN</span>' : '<span class="error">NIET GELADEN</span>') . "</p>";
echo "<p>Session support: " . (function_exists('session_start') ? '<span class="ok">JA</span>' : '<span class="error">NEE</span>') . "</p>";

echo "<hr>";
echo "<h2>Conclusie</h2>";
echo "<p>Als alle checks groen zijn (✓), dan zou de app moeten werken.</p>";
echo "<p>Rode checks (✗) moeten worden opgelost.</p>";
echo "<p><a href='index.php'>Ga naar de app →</a></p>";

echo "</body></html>";
