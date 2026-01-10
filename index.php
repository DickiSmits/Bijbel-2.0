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
    $imageEndpoints = ['all_images', 'upload_image', 'get_image', 'save_image', 'update_image', 'delete_image', 'verse_images'];
    
    // Notes endpoints router - alle notes-gerelateerde endpoints gebruiken notes.php
    $notesEndpoints = ['notes', 'get_note', 'save_note', 'delete_note'];
    
    // Profile endpoints router - alle profile-gerelateerde endpoints gebruiken profiles.php
    $profileEndpoints = ['profiles', 'create_profile', 'update_profile', 'delete_profile'];
    
    // Formatting endpoints router
    $formattingEndpoints = ['save_formatting', 'delete_formatting'];
    
    // ✅ NIEUW: Timeline endpoints router - alle timeline-gerelateerde endpoints gebruiken timeline.php
    $timelineEndpoints = [
        'timeline', 'save_timeline', 'create_timeline', 'update_timeline', 'delete_timeline',
        'timeline_groups', 'create_timeline_group', 'update_timeline_group', 'delete_timeline_group'
    ];
    
    if (in_array($endpoint, $imageEndpoints)) {
        $apiFile = __DIR__ . '/api/images.php';
    } elseif (in_array($endpoint, $notesEndpoints)) {
        $apiFile = __DIR__ . '/api/notes.php';
    } elseif (in_array($endpoint, $profileEndpoints)) {
        $apiFile = __DIR__ . '/api/profiles.php';
    } elseif (in_array($endpoint, $formattingEndpoints)) {
        $apiFile = __DIR__ . '/api/save_formatting.php';
    } elseif (in_array($endpoint, $timelineEndpoints)) {
        // ✅ NIEUW: Route timeline endpoints naar timeline.php
        $apiFile = __DIR__ . '/api/timeline.php';
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
                    
                    <!-- Search with navigation -->
                    <div class="input-group input-group-sm" style="width: 280px;">
                        <input type="search" id="searchInput" class="form-control form-control-sm" placeholder="Zoeken...">
                        <button class="btn btn-outline-light" onclick="navigateSearchPrev()" title="Vorige resultaat" id="searchPrevBtn" disabled>
                            <i class="bi bi-chevron-up"></i>
                        </button>
                        <button class="btn btn-outline-light" onclick="navigateSearchNext()" title="Volgend resultaat" id="searchNextBtn" disabled>
                            <i class="bi bi-chevron-down"></i>
                        </button>
                        <span class="input-group-text" id="searchCounter" style="display: none;">0/0</span>
                    </div>
                    
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
        echo '<div class="alert alert-danger">View bestand niet gevonden: ' . htmlspecialchars($mode) . '.php</div>';
        echo '</div>';
    }
    ?>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/vis-timeline@7.7.3/standalone/umd/vis-timeline-graph2d.min.js"></script>
    
    
    <?php if ($mode === 'admin'): ?>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    <?php endif; ?>
    
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
        
        <script src="assets/js/timeline.js"></script>
        
// ============================================
// VERVANG DE SCRIPT SECTIE IN INDEX.PHP (regel 250-442)
// Met deze verbeterde versie met KLIKBARE PROFIELEN
// ============================================

<script>
(function() {
    'use strict';
    
    const profileCache = new Map();
    const processedChapters = new Set();
    
    // 🆕 Store all profiles globally for quick lookup
    let allProfiles = [];
    
    // 🆕 Load all profiles once
    async function loadAllProfiles() {
        if (allProfiles.length > 0) return allProfiles;
        
        try {
            allProfiles = await apiCall('profiles');
            console.log(`📋 Loaded ${allProfiles.length} profiles for tooltips`);
            return allProfiles;
        } catch (error) {
            console.error('Error loading profiles:', error);
            return [];
        }
    }
    
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
    
    // 🆕 Switch to a different profile
    function switchToProfile(profielId, profielNaam) {
        console.log(`🔄 Switching to profile: ${profielNaam} (ID: ${profielId})`);
        
        const profileSelect = document.getElementById('profileSelect');
        if (profileSelect) {
            profileSelect.value = profielId;
            
            // Trigger change event to reload verses
            const event = new Event('change', { bubbles: true });
            profileSelect.dispatchEvent(event);
            
            // Show notification
            if (typeof showNotification === 'function') {
                showNotification(`Profiel gewisseld naar: ${profielNaam}`, false);
            }
            
            // Close any open tooltips
            closeAllTooltips();
        } else {
            console.warn('⚠️ Profile selector not found');
        }
    }
    
    // 🆕 Create clickable tooltip HTML
    function createTooltipHTML(profiles) {
        const items = profiles.map(p => {
            return `<span class="tooltip-profile-item" 
                          onclick="window.switchToProfile(${p.Profiel_ID}, '${escapeHtml(p.Profiel_Naam)}'); event.stopPropagation();"
                          title="Klik om naar dit profiel te wisselen">
                        ${escapeHtml(p.Profiel_Naam)}
                    </span>`;
        }).join('');
        
        return `<div class="tooltip-profile-list">
                    <div class="tooltip-header">Ook bewerkt in:</div>
                    ${items}
                </div>`;
    }
    
    // 🆕 Escape HTML for safety
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    // 🆕 Show tooltip on hover
    function showTooltip(versNumber) {
        const tooltipHTML = versNumber.getAttribute('data-tooltip-html');
        if (!tooltipHTML) return;
        
        // Remove any existing tooltip
        closeAllTooltips();
        
        // Create tooltip element
        const tooltip = document.createElement('div');
        tooltip.className = 'verse-profile-tooltip';
        tooltip.innerHTML = tooltipHTML;
        document.body.appendChild(tooltip);
        
        // Position tooltip
        const rect = versNumber.getBoundingClientRect();
        tooltip.style.position = 'fixed';
        tooltip.style.left = rect.left + 'px';
        tooltip.style.top = (rect.bottom + 5) + 'px';
        
        // Adjust if tooltip goes off-screen
        setTimeout(() => {
            const tooltipRect = tooltip.getBoundingClientRect();
            if (tooltipRect.right > window.innerWidth) {
                tooltip.style.left = (window.innerWidth - tooltipRect.width - 10) + 'px';
            }
            if (tooltipRect.bottom > window.innerHeight) {
                tooltip.style.top = (rect.top - tooltipRect.height - 5) + 'px';
            }
        }, 0);
        
        // Store reference for closing
        versNumber._activeTooltip = tooltip;
    }
    
    // 🆕 Close all tooltips
    function closeAllTooltips() {
        document.querySelectorAll('.verse-profile-tooltip').forEach(t => t.remove());
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
                    
                    // Remove old event listeners
                    const oldClone = versNumber.cloneNode(true);
                    versNumber.parentNode.replaceChild(oldClone, versNumber);
                    const freshVersNumber = oldClone;
                    
                    if (profiles.length === 0) {
                        freshVersNumber.classList.remove('has-other-profiles');
                        freshVersNumber.removeAttribute('data-tooltip-html');
                        freshVersNumber.removeAttribute('title');
                    } else {
                        const otherProfiles = currentProfileId 
                            ? profiles.filter(p => p.Profiel_ID !== currentProfileId)
                            : profiles;
                        
                        if (otherProfiles.length > 0) {
                            freshVersNumber.classList.add('has-other-profiles');
                            
                            // 🆕 Create HTML tooltip instead of plain text
                            const tooltipHTML = createTooltipHTML(otherProfiles);
                            freshVersNumber.setAttribute('data-tooltip-html', tooltipHTML);
                            
                            // Simple title for browsers without JS
                            const tooltipText = otherProfiles.map(p => p.Profiel_Naam).join(', ');
                            freshVersNumber.setAttribute('title', `Ook bewerkt in: ${tooltipText}`);
                            
                            // 🆕 Add hover listeners
                            freshVersNumber.addEventListener('mouseenter', function() {
                                showTooltip(this);
                            });
                            
                            freshVersNumber.addEventListener('mouseleave', function() {
                                setTimeout(() => {
                                    if (this._activeTooltip && !this._activeTooltip.matches(':hover')) {
                                        closeAllTooltips();
                                    }
                                }, 100);
                            });
                            
                            versesUpdated++;
                        } else {
                            freshVersNumber.classList.remove('has-other-profiles');
                            freshVersNumber.removeAttribute('data-tooltip-html');
                            freshVersNumber.removeAttribute('title');
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
    
    // 🆕 Close tooltips when clicking outside
    document.addEventListener('click', function(e) {
        if (!e.target.closest('.verse-profile-tooltip') && !e.target.closest('.has-other-profiles')) {
            closeAllTooltips();
        }
    });
    
    window.addEventListener('DOMContentLoaded', function() {
        console.log('🔵 Multi-profiel indicator V4 (CLICKABLE) initializing...');
        console.log('   ✓ Auto-detect nieuwe hoofdstukken');
        console.log('   ✓ Onbeperkt scrollen support');
        console.log('   ✓ Werkt bij eerste load');
        console.log('   ✓ 🆕 KLIKBARE PROFIELEN IN TOOLTIP!');
        
        // Load profiles first
        loadAllProfiles();
        
        const checkInterval = setInterval(function() {
            if (typeof apiCall !== 'undefined') {
                clearInterval(checkInterval);
                
                console.log('✅ Multi-profiel indicator V4 ready!');
                
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
    
    // 🆕 Make functions globally available
    window.switchToProfile = switchToProfile;
    window.closeAllTooltips = closeAllTooltips;
    
    window.multiProfielDebug = {
        cache: profileCache,
        processed: processedChapters,
        scan: scanAndProcessAllChapters,
        reprocess: reprocessAllVerses
    };
    
})();
</script>

<!-- 🆕 VOEG DEZE CSS TOE AAN INDEX.PHP (voor </head> tag) -->
<style>
/* Verse number met andere profielen - ZONDER STIP */
.verse-number.has-other-profiles {
    position: relative;
    cursor: help;
    color: #0066cc; /* Blauwer cijfer */
    text-decoration: underline;
    text-decoration-style: dotted;
    text-decoration-color: #0066cc;
    font-weight: bold;
}

/* Hover effect voor extra feedback */
.verse-number.has-other-profiles:hover {
    color: #003d99;
    text-decoration-color: #003d99;
}

/* Custom tooltip */
.verse-profile-tooltip {
    position: fixed;
    z-index: 10000;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 6px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
    padding: 0;
    min-width: 180px;
    max-width: 300px;
    font-size: 13px;
    animation: tooltipFadeIn 0.2s ease-out;
}

@keyframes tooltipFadeIn {
    from {
        opacity: 0;
        transform: translateY(-5px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.tooltip-header {
    padding: 8px 12px;
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    font-weight: 600;
    color: #495057;
    border-radius: 6px 6px 0 0;
    font-size: 12px;
}

.tooltip-profile-list {
    display: flex;
    flex-direction: column;
}

.tooltip-profile-item {
    padding: 8px 12px;
    cursor: pointer;
    transition: background-color 0.15s;
    color: #2c5282;
    font-weight: 500;
    border-bottom: 1px solid #f1f3f5;
}

.tooltip-profile-item:last-child {
    border-bottom: none;
    border-radius: 0 0 6px 6px;
}

.tooltip-profile-item:hover {
    background: #e3f2fd;
    color: #1a365d;
}

.tooltip-profile-item:active {
    background: #bbdefb;
}

/* Tooltip arrow (optional) */
.verse-profile-tooltip::before {
    content: '';
    position: absolute;
    top: -6px;
    left: 20px;
    width: 0;
    height: 0;
    border-left: 6px solid transparent;
    border-right: 6px solid transparent;
    border-bottom: 6px solid #dee2e6;
}

.verse-profile-tooltip::after {
    content: '';
    position: absolute;
    top: -5px;
    left: 21px;
    width: 0;
    height: 0;
    border-left: 5px solid transparent;
    border-right: 5px solid transparent;
    border-bottom: 5px solid white;
}
</style>
        
    <?php elseif ($mode === 'admin'): ?>
        <?php if (file_exists(__DIR__ . '/assets/js/admin.js')): ?>
        <script src="assets/js/admin.js"></script>
        <?php endif; ?>
    <?php endif; ?>
    <?php if ($mode === 'admin'): ?>
    <script src="assets/js/admin-extensions.js"></script>
    <script src="assets/js/admin-datatable-loaders.js"></script>
    <script src="assets/js/admin-timeline-groups.js"></script>
    <script src="assets/js/timeline-admin.js"></script>
    
   
<?php endif; ?>
</body>
</html>