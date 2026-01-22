<?php
/**
 * BIJBELREADER - MVC Versie
 * Met V3 Multi-Profiel Indicator: Auto-detect + Onbeperkt scrollen
 */

session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once __DIR__ . '/config.php';

// API ROUTING - Verbeterde versie met configuratie array
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    $endpoint = $_GET['api'];
    
    // API routing configuratie - makkelijker te onderhouden
    $apiRoutes = [
        'images.php' => ['all_images', 'upload_image', 'get_image', 'save_image', 'update_image', 'delete_image', 'verse_images'],
        'notes.php' => ['notes', 'get_note', 'save_note', 'delete_note'],
        'profiles.php' => ['profiles', 'create_profile', 'update_profile', 'delete_profile'],
        'save_formatting.php' => ['save_formatting', 'delete_formatting'],
        'timeline.php' => [
            'timeline', 'save_timeline', 'create_timeline', 'update_timeline', 'delete_timeline',
            'timeline_groups', 'create_timeline_group', 'update_timeline_group', 'delete_timeline_group'
        ]
    ];
    
    // Zoek endpoint in routing configuratie
    $apiFile = null;
    foreach ($apiRoutes as $file => $endpoints) {
        if (in_array($endpoint, $endpoints)) {
            $apiFile = __DIR__ . '/api/' . $file;
            break;
        }
    }
    
    // Fallback naar standaard endpoint bestand
    if (!$apiFile) {
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
    
    <?php if ($mode === 'reader'): ?>
    <link rel="stylesheet" href="assets/css/multi-profile-indicator.css">
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
        <script src="assets/js/multi-profile-indicator.js"></script>
        
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