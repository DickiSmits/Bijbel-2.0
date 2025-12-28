<?php
/**
 * BIJBELREADER APPLICATIE - MVC Versie
 * 
 * Gebruikt:
 * - Singleton Database pattern (Database::getInstance())
 * - Modulaire API endpoints in /api/
 * - Views in /views/ (reader.php, admin.php, login.php)
 * - JavaScript modules in /assets/js/
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load configuration
require_once __DIR__ . '/config.php';

// ============================================================================
// CRITICAL: API ROUTING EERST! Voor HTML rendering!
// ============================================================================
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
    
    exit; // ← STOP! Geen HTML na API call
}

// ============================================================================
// LOGIN/LOGOUT HANDLING
// ============================================================================
if (isset($_POST['login'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: ?mode=admin');
        exit;
    } else {
        $login_error = 'Onjuist wachtwoord';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header('Location: ?mode=reader');
    exit;
}

// Check admin access
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// ============================================================================
// MODE DETECTION
// ============================================================================
$requested_mode = isset($_GET['mode']) ? $_GET['mode'] : 'reader';

// Protect admin mode
if ($requested_mode === 'admin' && !$is_admin) {
    $mode = 'login';
} else {
    $mode = $requested_mode;
}

// ============================================================================
// DATABASE INITIALIZATION
// ============================================================================
try {
    $db = Database::getInstance()->getConnection();
    
    // Create Notities table if not exists
    $db->exec("CREATE TABLE IF NOT EXISTS Notities (
        Notitie_ID INTEGER PRIMARY KEY AUTOINCREMENT,
        Titel TEXT,
        Inhoud TEXT,
        Aangemaakt DATETIME DEFAULT CURRENT_TIMESTAMP,
        Gewijzigd DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Migration: Add Tekst_Kleur column
    try {
        $columns = $db->query("PRAGMA table_info(Timeline_Events)")->fetchAll(PDO::FETCH_ASSOC);
        $hasTextColor = false;
        foreach ($columns as $col) {
            if ($col['name'] === 'Tekst_Kleur') {
                $hasTextColor = true;
                break;
            }
        }
        if (!$hasTextColor) {
            $db->exec("ALTER TABLE Timeline_Events ADD COLUMN Tekst_Kleur TEXT");
        }
    } catch (Exception $e) {
        // Table might not exist yet
    }
    
} catch (Exception $e) {
    die('Database initialisatie mislukt: ' . $e->getMessage());
}

// Ensure images directory exists
if (!is_dir('images')) {
    mkdir('images', 0755, true);
}
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bijbelreader<?php echo $mode === 'admin' ? ' - Admin' : ''; ?></title>
    
    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    
    <!-- Vis Timeline CSS -->
    <link rel="stylesheet" href="https://unpkg.com/vis-timeline@7.7.3/styles/vis-timeline-graph2d.min.css" />
    
    <!-- Quill Editor (alleen admin) -->
    <?php if ($mode === 'admin'): ?>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <link rel="stylesheet" href="assets/css/style.css">
    <?php endif; ?>
    
    <!-- Custom CSS (optioneel) -->
    <?php if (file_exists(__DIR__ . '/assets/css/style.css')): ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <?php endif; ?>
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="?mode=reader">
                <i class="bi bi-book"></i> Bijbelreader
            </a>
            
            <?php if ($mode === 'reader'): ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarContent">
                <div class="d-flex flex-wrap align-items-center gap-2 me-auto ms-3">
                    <select id="bookSelect" class="form-select form-select-sm" style="width: 180px;">
                        <option value="">Kies een boek...</option>
                    </select>
                    
                    <select id="chapterSelect" class="form-select form-select-sm" style="width: 140px;">
                        <option value="">Hoofdstuk</option>
                    </select>
                    
                    <input type="search" id="searchInput" class="form-control form-control-sm" placeholder="Zoeken..." style="width: 160px;">
                    
                    <select id="profileSelect" class="form-select form-select-sm" style="width: 160px;">
                        <option value="">Geen opmaak</option>
                    </select>
                    
                    <button class="btn btn-outline-light btn-sm" onclick="toggleTimelineFilter()" title="Timeline filter">
                        <i class="bi bi-funnel"></i> <span id="groupFilterCount">0</span>
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="d-flex align-items-center gap-2">
                <?php if ($is_admin): ?>
                <a href="?logout" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Uitloggen
                </a>
                <?php endif; ?>
                
                <a href="?mode=<?php echo $mode === 'admin' ? 'reader' : 'admin'; ?>" class="btn btn-outline-light btn-sm">
                    <?php if ($mode === 'admin'): ?>
                        <i class="bi bi-eye"></i> Reader
                    <?php else: ?>
                        <i class="bi bi-gear"></i> Admin
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </nav>

    <?php
    // Load view
    $viewFile = __DIR__ . '/views/' . $mode . '.php';
    
    if (file_exists($viewFile)) {
        require_once $viewFile;
    } else {
        echo '<div class="container mt-5">';
        echo '<div class="alert alert-danger">View niet gevonden: ' . htmlspecialchars($mode) . '.php</div>';
        echo '<p>Zorg dat /views/' . htmlspecialchars($mode) . '.php bestaat!</p>';
        echo '</div>';
    }
    ?>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Libraries -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/vis-timeline@7.7.3/standalone/umd/vis-timeline-graph2d.min.js"></script>
    
    <?php if ($mode === 'admin'): ?>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <?php endif; ?>
    
    <!-- App Scripts -->
    <script>
        const mode = '<?php echo $mode; ?>';
        const isAdmin = <?php echo $is_admin ? 'true' : 'false'; ?>;
    </script>
    
    <?php if (file_exists(__DIR__ . '/assets/js/app.js')): ?>
    <script src="assets/js/app.js"></script>
    <?php endif; ?>
    
    <?php if ($mode === 'reader'): ?>
        <?php if (file_exists(__DIR__ . '/assets/js/reader.js')): ?>
        <script src="assets/js/reader.js"></script>
        <?php endif; ?>
        <?php if (file_exists(__DIR__ . '/assets/js/map.js')): ?>
        <script src="assets/js/map.js"></script>
        <?php endif; ?>
        <?php if (file_exists(__DIR__ . '/assets/js/timeline.js')): ?>
        <script src="assets/js/timeline.js"></script>
        <?php endif; ?>
        
        <!-- ================================================================
             MULTI-PROFIEL INDICATOR SYSTEEM
             Lichtblauwe versnummers voor verzen bewerkt in andere profielen
             ================================================================ -->
        <script>
        (function() {
            'use strict';
            
            // Global variabele voor profiel mappings
            let chapterProfileMappings = {};
            
            /**
             * Laad welke profielen bewerkingen hebben voor huidige hoofdstuk
             */
            async function loadChapterProfiles() {
                // Check if currentBook and currentChapter are available
                if (typeof currentBook === 'undefined' || typeof currentChapter === 'undefined') {
                    console.warn('⚠️ currentBook/currentChapter not defined yet');
                    return;
                }
                
                if (!currentBook || !currentChapter) {
                    chapterProfileMappings = {};
                    return;
                }
                
                try {
                    const data = await apiCall(`chapter_profiles&boek=${encodeURIComponent(currentBook)}&hoofdstuk=${currentChapter}`);
                    chapterProfileMappings = data || {};
                    
                    console.log(`📊 Loaded profile mappings for ${Object.keys(chapterProfileMappings).length} verses`);
                    
                    // Update versnummers met indicator
                    updateVerseNumberIndicators();
                } catch (error) {
                    console.error('Error loading chapter profiles:', error);
                }
            }
            
            /**
             * Update versnummers met multi-profiel indicator
             */
            function updateVerseNumberIndicators() {
                // Check if currentProfile is available
                const currentProfileId = (typeof currentProfile !== 'undefined' && currentProfile) 
                    ? parseInt(currentProfile) 
                    : null;
                
                // Loop door alle verzen in de DOM
                document.querySelectorAll('.verse').forEach(verseElement => {
                    const versNumber = verseElement.querySelector('.verse-number');
                    if (!versNumber) return;
                    
                    const versnummer = versNumber.textContent.trim();
                    
                    // Check of dit vers bewerkingen heeft
                    const profiles = chapterProfileMappings[versnummer] || [];
                    
                    if (profiles.length === 0) {
                        // Geen bewerkingen - verwijder classes
                        versNumber.classList.remove('has-other-profiles');
                        versNumber.removeAttribute('title');
                        return;
                    }
                    
                    // Filter andere profielen (niet het huidige)
                    const otherProfiles = currentProfileId 
                        ? profiles.filter(p => p.Profiel_ID !== currentProfileId)
                        : profiles;
                    
                    if (otherProfiles.length > 0) {
                        // Heeft bewerkingen in andere profielen - maak lichtblauw
                        versNumber.classList.add('has-other-profiles');
                        
                        // Maak tooltip tekst
                        const tooltipText = otherProfiles.map(p => p.Profiel_Naam).join(', ');
                        versNumber.setAttribute('title', `Bewerkt in: ${tooltipText}`);
                        
                        console.log(`✏️ Vers ${versnummer} heeft bewerkingen in: ${tooltipText}`);
                    } else {
                        // Alleen huidige profiel - verwijder indicator
                        versNumber.classList.remove('has-other-profiles');
                        versNumber.removeAttribute('title');
                    }
                });
            }
            
            // ================================================================
            // HOOK INTO EXISTING EVENTS
            // ================================================================
            
            /**
             * Wait for DOM and other scripts to load
             */
            window.addEventListener('DOMContentLoaded', function() {
                console.log('🔵 Multi-profiel indicator systeem initializing...');
                
                // Wacht tot reader.js geladen is en variabelen beschikbaar zijn
                const checkInterval = setInterval(function() {
                    if (typeof apiCall !== 'undefined' && typeof currentBook !== 'undefined') {
                        clearInterval(checkInterval);
                        console.log('✅ Multi-profiel indicator systeem ready!');
                        
                        // Hook into profile select change
                        const profileSelect = document.getElementById('profileSelect');
                        if (profileSelect) {
                            profileSelect.addEventListener('change', function() {
                                setTimeout(() => updateVerseNumberIndicators(), 500);
                            });
                        }
                        
                        // Hook into chapter select change
                        const chapterSelect = document.getElementById('chapterSelect');
                        if (chapterSelect) {
                            chapterSelect.addEventListener('change', function() {
                                chapterProfileMappings = {}; // Reset mappings
                            });
                        }
                    }
                }, 100); // Check elke 100ms
                
                // Stop after 5 seconds to prevent infinite loop
                setTimeout(() => clearInterval(checkInterval), 5000);
            });
            
            // Export functions to window for external access
            window.loadChapterProfiles = loadChapterProfiles;
            window.updateVerseNumberIndicators = updateVerseNumberIndicators;
            
        })();
        </script>
        
    <?php elseif ($mode === 'admin'): ?>
        <?php if (file_exists(__DIR__ . '/assets/js/admin.js')): ?>
        <script src="assets/js/admin.js"></script>
        <?php endif; ?>
    <?php endif; ?>
</body>
</html>
