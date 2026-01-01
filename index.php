<?php
/**
 * BIJBELREADER - MVC Versie
 * Met V3 Multi-Profiel Indicator: Auto-detect + Onbeperkt scrollen
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

// API ROUTING
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    $endpoint = $_GET['api'];
    
    // Image endpoints router - alle image-gerelateerde endpoints gebruiken images.php
    $imageEndpoints = ['all_images', 'upload_image', 'get_image', 'update_image', 'delete_image', 'verse_images'];
    
    // Notes endpoints router - alle notes-gerelateerde endpoints gebruiken notes.php
    $notesEndpoints = ['notes', 'get_note', 'save_note', 'delete_note'];
    
    if (in_array($endpoint, $imageEndpoints)) {
        $apiFile = __DIR__ . '/api/images.php';
    } elseif (in_array($endpoint, $notesEndpoints)) {
        $apiFile = __DIR__ . '/api/notes.php';
    } else {
        $apiFile = __DIR__ . '/api/' . $endpoint . '.php';
    }
    
    if (file_exists($apiFile)) {
        require_once $apiFile;
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'API endpoint niet gevonden: ' . $endpoint]);
    }
    
    exit;
}


// LOGIN/LOGOUT
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

$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// MODE
$requested_mode = isset($_GET['mode']) ? $_GET['mode'] : 'reader';

if ($requested_mode === 'admin' && !$is_admin) {
    $mode = 'login';
} else {
    $mode = $requested_mode;
}

// DATABASE
try {
    $db = Database::getInstance()->getConnection();
    
    $db->exec("CREATE TABLE IF NOT EXISTS Notities (
        Notitie_ID INTEGER PRIMARY KEY AUTOINCREMENT,
        Titel TEXT,
        Inhoud TEXT,
        Aangemaakt DATETIME DEFAULT CURRENT_TIMESTAMP,
        Gewijzigd DATETIME DEFAULT CURRENT_TIMESTAMP
    )");
    
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
        // OK
    }
    
} catch (Exception $e) {
    die('Database error: ' . $e->getMessage());
}

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
    
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/vis-timeline@7.7.3/styles/vis-timeline-graph2d.min.css" />
    
    <?php if ($mode === 'admin'): ?>
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    <?php endif; ?>

    <?php if ($mode === 'admin'): ?>
    <link href="assets/css/admin-datatable.css" rel="stylesheet">
    <?php endif; ?>
    
    <?php if (file_exists(__DIR__ . '/assets/css/style.css')): ?>
    <link rel="stylesheet" href="assets/css/style.css">
    <?php endif; ?>
</head>
<body>
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
    $viewFile = __DIR__ . '/views/' . $mode . '.php';
    
    if (file_exists($viewFile)) {
        require_once $viewFile;
    } else {
        echo '<div class="container mt-5">';
        echo '<div class="alert alert-danger">View niet gevonden: ' . htmlspecialchars($mode) . '.php</div>';
        echo '</div>';
    }
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/vis-timeline@7.7.3/standalone/umd/vis-timeline-graph2d.min.js"></script>
    
    <?php if ($mode === 'admin'): ?>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <?php endif; ?>
 
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
        
        <!-- ==============================================================
             MULTI-PROFIEL INDICATOR V3
             - Auto-detect nieuwe hoofdstukken bij scrollen
             - Werkt ZONDER reader.js aanpassingen
             - Onbeperkt scrollen door alle boeken
             ============================================================== -->
        <script>
        (function() {
            'use strict';
            
            const profileCache = new Map();
            const processedChapters = new Set();
            
            async function loadProfilesForChapter(boek, hoofdstuk) {
                const cacheKey = `${boek}_${hoofdstuk}`;
                
                if (profileCache.has(cacheKey)) {
                    return profileCache.get(cacheKey);
                }
                
                try {
                    const data = await apiCall(`chapter_profiles&boek=${encodeURIComponent(boek)}&hoofdstuk=${hoofdstuk}`);
                    profileCache.set(cacheKey, data || {});
                    console.log(`📊 Loaded profiles for ${boek} ${hoofdstuk}: ${Object.keys(data || {}).length} verses`);
                    return data || {};
                } catch (error) {
                    console.error(`Error loading profiles for ${boek} ${hoofdstuk}:`, error);
                    return {};
                }
            }
            
            function updateVersesInChapter(chapterElement, profileMappings) {
                const currentProfileId = (typeof currentProfile !== 'undefined' && currentProfile) 
                    ? parseInt(currentProfile) 
                    : null;
                
                let nextElement = chapterElement.nextElementSibling;
                let versesUpdated = 0;
                
                while (nextElement && !nextElement.classList.contains('chapter-header')) {
                    if (nextElement.classList.contains('verse')) {
                        const versNumber = nextElement.querySelector('.verse-number');
                        
                        if (versNumber) {
                            const versnummer = versNumber.textContent.trim();
                            const profiles = profileMappings[versnummer] || [];
                            
                            if (profiles.length === 0) {
                                versNumber.classList.remove('has-other-profiles');
                                versNumber.removeAttribute('data-tooltip');
                                versNumber.removeAttribute('title');
                            } else {
                                const otherProfiles = currentProfileId 
                                    ? profiles.filter(p => p.Profiel_ID !== currentProfileId)
                                    : profiles;
                                
                                if (otherProfiles.length > 0) {
                                    versNumber.classList.add('has-other-profiles');
                                    const tooltipText = otherProfiles.map(p => p.Profiel_Naam).join(', ');
                                    versNumber.setAttribute('data-tooltip', tooltipText);
                                    versNumber.removeAttribute('title');
                                    versesUpdated++;
                                } else {
                                    versNumber.classList.remove('has-other-profiles');
                                    versNumber.removeAttribute('data-tooltip');
                                    versNumber.removeAttribute('title');
                                }
                            }
                        }
                    }
                    
                    nextElement = nextElement.nextElementSibling;
                }
                
                return versesUpdated;
            }
            
            async function processChapterHeader(chapterElement) {
                const headerText = chapterElement.textContent.trim();
                const parts = headerText.split(' ');
                
                if (parts.length < 2) {
                    console.warn('⚠️ Invalid chapter header format:', headerText);
                    return;
                }
                
                const hoofdstuk = parts[parts.length - 1];
                const boek = parts.slice(0, -1).join(' ');
                const chapterKey = `${boek}_${hoofdstuk}`;
                
                if (processedChapters.has(chapterKey)) {
                    return;
                }
                
                console.log(`🔍 Processing chapter: ${boek} ${hoofdstuk}`);
                processedChapters.add(chapterKey);
                
                const profileMappings = await loadProfilesForChapter(boek, hoofdstuk);
                const versesUpdated = updateVersesInChapter(chapterElement, profileMappings);
                
                console.log(`✅ Updated ${versesUpdated} verses in ${boek} ${hoofdstuk}`);
            }
            
            async function scanAndProcessAllChapters() {
                const headers = document.querySelectorAll('.chapter-header');
                console.log(`🔎 Scanning ${headers.length} chapter headers...`);
                
                for (const header of headers) {
                    await processChapterHeader(header);
                }
            }
            
            function setupMutationObserver() {
                const bibleText = document.getElementById('bibleText');
                
                if (!bibleText) {
                    console.warn('⚠️ bibleText element not found');
                    return;
                }
                
                const observer = new MutationObserver((mutations) => {
                    mutations.forEach(mutation => {
                        mutation.addedNodes.forEach(node => {
                            if (node.classList && node.classList.contains('chapter-header')) {
                                console.log('🆕 New chapter detected via MutationObserver!');
                                processChapterHeader(node);
                            }
                        });
                    });
                });
                
                observer.observe(bibleText, {
                    childList: true,
                    subtree: false
                });
                
                console.log('👀 MutationObserver active - watching for new chapters');
            }
            
            function reprocessAllVerses() {
                console.log('🔄 Reprocessing all verses...');
                
                const headers = document.querySelectorAll('.chapter-header');
                
                headers.forEach(header => {
                    const headerText = header.textContent.trim();
                    const parts = headerText.split(' ');
                    
                    if (parts.length >= 2) {
                        const hoofdstuk = parts[parts.length - 1];
                        const boek = parts.slice(0, -1).join(' ');
                        const cacheKey = `${boek}_${hoofdstuk}`;
                        
                        const profileMappings = profileCache.get(cacheKey) || {};
                        updateVersesInChapter(header, profileMappings);
                    }
                });
            }
            
            window.addEventListener('DOMContentLoaded', function() {
                console.log('🔵 Multi-profiel indicator V3 initializing...');
                console.log('   ✓ Auto-detect nieuwe hoofdstukken');
                console.log('   ✓ Onbeperkt scrollen support');
                console.log('   ✓ Werkt bij eerste load');
                
                const checkInterval = setInterval(function() {
                    if (typeof apiCall !== 'undefined') {
                        clearInterval(checkInterval);
                        
                        console.log('✅ Multi-profiel indicator V3 ready!');
                        
                        setupMutationObserver();
                        
                        setTimeout(() => {
                            scanAndProcessAllChapters();
                        }, 500);
                        
                        const profileSelect = document.getElementById('profileSelect');
                        if (profileSelect) {
                            profileSelect.addEventListener('change', function() {
                                console.log('👤 Profile changed - reprocessing...');
                                setTimeout(() => reprocessAllVerses(), 300);
                            });
                        }
                    }
                }, 100);
                
                setTimeout(() => clearInterval(checkInterval), 5000);
            });
            
            window.multiProfielDebug = {
                cache: profileCache,
                processed: processedChapters,
                scan: scanAndProcessAllChapters,
                reprocess: reprocessAllVerses
            };
            
        })();
        </script>
        
    <?php elseif ($mode === 'admin'): ?>
        <?php if (file_exists(__DIR__ . '/assets/js/admin.js')): ?>
        <script src="assets/js/admin.js"></script>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($mode === 'admin'): ?>
    <script src="assets/js/admin-extensions.js"></script>
    <script src="assets/js/admin-datatable-loaders.js"></script>
    <script src="assets/js/admin-timeline-groups.js"></script>
   
<?php endif; ?>
</body>
</html>