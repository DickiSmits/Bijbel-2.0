<!DOCTYPE html>
<html lang="nl" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= APP_NAME ?> - Reader</title>
    
    <!-- Bootstrap & Icons -->
    <link href="<?= BOOTSTRAP_CSS ?>" rel="stylesheet">
    <link href="<?= BOOTSTRAP_ICONS ?>" rel="stylesheet">
    
    <!-- Map & Timeline Libraries -->
    <link rel="stylesheet" href="<?= LEAFLET_CSS ?>" />
    <link rel="stylesheet" href="<?= VIS_CSS ?>" />
    
    <!-- Custom CSS -->
    <link href="assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="?mode=reader">
                <i class="bi bi-book"></i> <?= APP_NAME ?>
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
                    
                    <input type="search" id="searchInput" class="form-control form-control-sm" 
                           placeholder="Zoeken..." style="width: 160px;">
                    
                    <select id="profileSelect" class="form-select form-select-sm" style="width: 160px;">
                        <option value="">Geen opmaak</option>
                    </select>
                    
                    <button class="btn btn-outline-light btn-sm" onclick="toggleTimelineFilter()" 
                            title="Timeline filter">
                        <i class="bi bi-funnel"></i> <span id="groupFilterCount">0</span>
                    </button>
                </div>
            </div>
            
            <div class="d-flex align-items-center gap-2">
                <?php if ($isAdmin): ?>
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

    <!-- Timeline Filter Panel -->
    <div class="collapse bg-light border-bottom" id="timelineFilterPanel">
        <div class="container-fluid py-2">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0"><i class="bi bi-tags"></i> Timeline Filter & Zoeken</h6>
                <button class="btn btn-sm btn-outline-secondary" onclick="toggleTimelineFilter()">
                    <i class="bi bi-x"></i> Sluiten
                </button>
            </div>
            
            <!-- Timeline Search -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="readerTimelineSearch" class="form-control" 
                               placeholder="Zoek in timeline events..." 
                               onkeyup="searchTimelineEvents(this.value)">
                        <button class="btn btn-outline-secondary" type="button" onclick="clearTimelineSearch()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Search Results -->
            <div id="timelineSearchResults" class="mb-3 d-none">
                <div class="card">
                    <div class="card-header py-1 px-2">
                        <small class="fw-bold"><span id="timelineSearchCount">0</span> resultaten</small>
                    </div>
                    <div class="card-body p-0" style="max-height: 200px; overflow-y: auto;">
                        <div id="timelineSearchResultsList"></div>
                    </div>
                </div>
            </div>
            
            <!-- Group Toggles -->
            <div class="mb-2">
                <small class="text-muted fw-bold">Groepen tonen/verbergen:</small>
            </div>
            <div id="timelineGroupToggles" class="d-flex flex-wrap gap-2"></div>
        </div>
    </div>
    
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
            <button class="timeline-nav-btn timeline-nav-prev" onclick="navigateTimelinePrev()">
                <i class="bi bi-chevron-left"></i>
            </button>
            <div id="timeline"></div>
            <button class="timeline-nav-btn timeline-nav-next" onclick="navigateTimelineNext()">
                <i class="bi bi-chevron-right"></i>
            </button>
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
                <button class="btn btn-sm btn-primary" id="timelinePopupGoToVerse" 
                        onclick="goToTimelineVerse()">
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

    <!-- Scripts -->
    <script src="<?= BOOTSTRAP_JS ?>"></script>
    <script src="<?= LEAFLET_JS ?>"></script>
    <script src="<?= VIS_JS ?>"></script>
    
    <!-- Custom JavaScript -->
    <script src="assets/js/app.js"></script>
    <script src="assets/js/reader.js"></script>
    <script src="assets/js/map.js"></script>
    <script src="assets/js/timeline.js"></script>
</body>
</html>
