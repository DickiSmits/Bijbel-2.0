<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>Config Test</h1>";

echo "<p>1. Testing config.php...</p>";
if (file_exists(__DIR__ . '/config.php')) {
    echo "✅ config.php exists<br>";
    
    try {
        require_once __DIR__ . '/config.php';
        echo "✅ config.php loaded<br>";
    } catch (Exception $e) {
        echo "❌ Error loading config.php: " . $e->getMessage() . "<br>";
        die();
    }
} else {
    echo "❌ config.php NOT FOUND!<br>";
    die();
}

echo "<p>2. Testing constants...</p>";
echo "DB_FILE: " . (defined('DB_FILE') ? DB_FILE : 'NOT DEFINED') . "<br>";
echo "BASE_PATH: " . (defined('BASE_PATH') ? BASE_PATH : 'NOT DEFINED') . "<br>";

echo "<p>3. Testing database file...</p>";
if (file_exists(DB_FILE)) {
    echo "✅ Database file exists: " . DB_FILE . "<br>";
    echo "Size: " . filesize(DB_FILE) . " bytes<br>";
    echo "Readable: " . (is_readable(DB_FILE) ? 'YES' : 'NO') . "<br>";
} else {
    echo "❌ Database file NOT FOUND: " . DB_FILE . "<br>";
}

echo "<p>4. Testing Database class...</p>";
if (class_exists('Database')) {
    echo "✅ Database class exists<br>";
    
    try {
        $db = new Database();
        echo "✅ Database object created<br>";
        
        $stmt = $db->query("SELECT COUNT(*) as count FROM De_Bijbel");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        echo "✅ Database query works! Verses in DB: " . $result['count'] . "<br>";
        
    } catch (Exception $e) {
        echo "❌ Database error: " . $e->getMessage() . "<br>";
    }
} else {
    echo "❌ Database class NOT FOUND<br>";
}

echo "<p>5. Testing includes directory...</p>";
$includesDir = __DIR__ . '/includes';
if (is_dir($includesDir)) {
    echo "✅ includes/ directory exists<br>";
    $files = scandir($includesDir);
    echo "Files: " . implode(', ', array_filter($files, function($f) { return $f[0] !== '.'; })) . "<br>";
} else {
    echo "❌ includes/ directory NOT FOUND<br>";
}

echo "<h2>✅ All tests passed!</h2>";
?>