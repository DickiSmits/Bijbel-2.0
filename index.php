<?php
/**
 * MINIMAL INDEX.PHP - Test Singleton Pattern API Routing
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load config
require_once __DIR__ . '/config.php';

// ============= API ROUTING - KOMT EERST! =============
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    $endpoint = $_GET['api'];
    $apiFile = __DIR__ . '/api/' . $endpoint . '.php';
    
    if (file_exists($apiFile)) {
        require_once $apiFile;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'API endpoint niet gevonden: ' . $endpoint]);
    }
    
    exit; // ← KRITIEK! Stop hier voor API calls
}

// ============= NORMALE PAGINA REQUEST =============
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bijbelreader - API Test (Singleton Pattern)</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            max-width: 1000px;
            margin: 50px auto;
            padding: 20px;
        }
        .success { color: #4CAF50; font-weight: bold; }
        .error { color: #f44336; font-weight: bold; }
        .test-link {
            display: inline-block;
            margin: 5px;
            padding: 8px 15px;
            background: #2196F3;
            color: white;
            text-decoration: none;
            border-radius: 4px;
        }
        .test-link:hover {
            background: #1976D2;
        }
        pre {
            background: #f5f5f5;
            padding: 15px;
            border-radius: 4px;
            overflow-x: auto;
        }
    </style>
</head>
<body>
    <h1>✅ Index.php Werkt! (Singleton Pattern Fix)</h1>
    
    <h2>🔧 Database Singleton Pattern Test</h2>
    <?php
    try {
        // Test Singleton pattern
        $db = Database::getInstance()->getConnection();
        echo '<p class="success">✅ Database::getInstance() werkt!</p>';
        
        // Test query
        $stmt = $db->query("SELECT COUNT(*) as count FROM De_Bijbel");
        $result = $stmt->fetch();
        echo '<p class="success">✅ Database query werkt! Aantal verzen: ' . number_format($result['count']) . '</p>';
        
    } catch (Exception $e) {
        echo '<p class="error">❌ Database error: ' . $e->getMessage() . '</p>';
    }
    ?>
    
    <h2>📡 Test API Endpoints (Moet JSON tonen, NIET HTML)</h2>
    <p>Klik op een link. Als je <strong>JSON</strong> ziet, werkt de API! Als je HTML ziet, werkt het NIET.</p>
    
    <div>
        <a href="?api=books" class="test-link" target="_blank">📚 Books API</a>
        <a href="?api=profiles" class="test-link" target="_blank">👤 Profiles API</a>
        <a href="?api=timeline" class="test-link" target="_blank">📅 Timeline API</a>
        <a href="?api=locations" class="test-link" target="_blank">📍 Locations API</a>
        <a href="?api=chapters&boek=Genesis" class="test-link" target="_blank">📖 Chapters API</a>
        <a href="?api=verses&boek=Genesis&hoofdstuk=1&limit=5" class="test-link" target="_blank">📝 Verses API</a>
        <a href="?api=timeline_groups" class="test-link" target="_blank">🏷️ Timeline Groups</a>
    </div>
    
    <h2>🧪 JavaScript API Test</h2>
    <button onclick="testAllAPIs()" style="padding: 10px 20px; font-size: 16px; cursor: pointer;">
        Test Alle APIs
    </button>
    <pre id="testResults">Klik op de knop om te testen...</pre>
    
    <script>
    async function testAllAPIs() {
        const results = document.getElementById('testResults');
        results.textContent = 'Bezig met testen...\n\n';
        
        const apis = ['books', 'profiles', 'timeline', 'locations', 'timeline_groups'];
        
        for (const api of apis) {
            try {
                const response = await fetch('?api=' + api);
                const contentType = response.headers.get('content-type');
                
                results.textContent += `📡 ${api}:\n`;
                results.textContent += `   Status: ${response.status}\n`;
                results.textContent += `   Content-Type: ${contentType}\n`;
                
                if (contentType && contentType.includes('application/json')) {
                    const data = await response.json();
                    if (Array.isArray(data)) {
                        results.textContent += `   ✅ JSON OK! (${data.length} items)\n`;
                    } else if (data.error) {
                        results.textContent += `   ⚠️  JSON Error: ${data.error}\n`;
                    } else {
                        results.textContent += `   ✅ JSON OK!\n`;
                    }
                } else {
                    results.textContent += `   ❌ FOUT: Content-Type is niet JSON!\n`;
                }
                
            } catch (error) {
                results.textContent += `   ❌ CRASH: ${error.message}\n`;
            }
            
            results.textContent += '\n';
        }
        
        results.textContent += '=== TEST COMPLEET ===';
    }
    </script>
    
    <h2>ℹ️ Informatie</h2>
    <p>Deze minimale index.php test of:</p>
    <ul>
        <li>✅ Database Singleton pattern werkt</li>
        <li>✅ API routing correct is (API calls VOOR normale pagina)</li>
        <li>✅ API endpoints JSON teruggeven (NIET HTML)</li>
        <li>✅ exit; na API call voorkomt HTML output</li>
    </ul>
    
    <p><strong>Als alle tests slagen, vervang dan je huidige index.php met de volledige versie!</strong></p>
</body>
</html>
