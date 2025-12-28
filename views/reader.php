<?php
/**
 * Reader View - Hoofdweergave voor bijbellezen
 */
?>
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bijbelreader</title>
    
    <!-- Bootstrap CSS -->
    <link href="<?= BOOTSTRAP_CSS ?>" rel="stylesheet">
    <link href="<?= BOOTSTRAP_ICONS ?>" rel="stylesheet">
    
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="<?= LEAFLET_CSS ?>">
    
    <!-- Vis Timeline CSS -->
    <link rel="stylesheet" href="<?= VIS_CSS ?>">
    
    <!-- Custom CSS -->
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="?">
                <i class="bi bi-book"></i> Bijbelreader
            </a>
            
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
                </div>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <?php if ($is_admin): ?>
                <a href="?logout" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Uitloggen
                </a>
                <?php endif; ?>
                
                <a href="?mode=admin" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-gear"></i> Admin
                </a>
            </div>
        </div>
    </nav>

    <!-- Reader Layout -->
    <div class="reader-layout" id="readerContainer">
        <div class="bible-panel" id="bibleText">
            <div class="text-center py-5 text-muted">
                <div class="spinner-border" role="status"></div>
                <p class="mt-2">Bijbeltekst wordt geladen...</p>
            </div>
        </div>
        
        <div class="resize-handle-v" id="verticalHandle"></div>
        
        <div class="map-panel">
            <div id="map"></div>
        </div>
        
        <div class="resize-handle-h" id="horizontalHandle"></div>
        
        <div class="timeline-panel">
            <div id="timeline"></div>
        </div>
    </div>

    <!-- Timeline Event Popup -->
    <div class="timeline-popup" id="timelinePopup">
        <div class="timeline-popup-content">
            <button class="timeline-popup-close" onclick="closeTimelinePopup()">
                <i class="bi bi-x-lg"></i>
            </button>
            <div class="timeline-popup-header">
                <h5 id="timelinePopupTitle">Event Titel</h5>
                <div class="timeline-popup-meta">
                    <span id="timelinePopupDate" class="badge bg-secondary"></span>
                    <span id="timelinePopupGroup" class="badge"></span>
                </div>
            </div>
            <div class="timeline-popup-body">
                <div id="timelinePopupDescription"></div>
            </div>
            <div class="timeline-popup-footer">
                <button class="btn btn-sm btn-primary" id="timelinePopupGoToVerse" onclick="goToTimelineVerse()">
                    <i class="bi bi-book"></i> Ga naar vers
                </button>
            </div>
        </div>
    </div>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <img id="lightboxImage" src="" alt="">
    </div>

    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="notificationToast" class="toast" role="alert">
            <div class="toast-header">
                <i class="bi bi-info-circle me-2"></i>
                <strong class="me-auto">Melding</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body"></div>
        </div>
    </div>

    <!-- External Libraries -->
    <script src="<?= BOOTSTRAP_JS ?>"></script>
    <script src="<?= LEAFLET_JS ?>"></script>
    <script src="<?= VIS_JS ?>"></script>
    
    <!-- CRITICAL: Set mode BEFORE loading custom scripts -->
    <script>
        const mode = 'reader';
        console.log('✅ Mode set to:', mode);
    </script>
    
    <!-- Custom Scripts - EXACT ORDER -->
    <script src="assets/js/app.js"></script>
    <script src="assets/js/map.js"></script>
    <script src="assets/js/timeline.js"></script>
    <script src="assets/js/reader.js"></script>
    
    <!-- Auto-start after all scripts loaded -->
    <script>
        console.log('🔄 Starting Bijbelreader...');
        
        // Wait for DOM to be ready
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', startApp);
        } else {
            startApp();
        }
        
        function startApp() {
            console.log('🚀 DOM ready, checking functions...');
            console.log('- apiCall:', typeof window.apiCall);
            console.log('- initReader:', typeof window.initReader);
            console.log('- initMap:', typeof window.initMap);
            console.log('- initTimeline:', typeof window.initTimeline);
            
            if (typeof window.initReader === 'function') {
                console.log('✅ Starting reader...');
                window.initReader();
            } else {
                console.error('❌ initReader not found!');
                alert('Fout: JavaScript modules niet correct geladen. Herlaad de pagina.');
            }
        }
    </script>
</body>
</html>
