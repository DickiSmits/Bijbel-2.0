<!-- Reader View - Enhanced Timeline with Filter & Search -->
<div class="reader-layout" id="readerContainer">
    <!-- Bible Text Panel -->
    <div class="bible-panel" id="bibleText">
        <div class="text-center py-5 text-muted">
            <div class="spinner-border" role="status"></div>
            <p class="mt-2">Bijbeltekst wordt geladen...</p>
        </div>
    </div>
    
    <!-- Vertical Resize Handle -->
    <div class="resize-handle-v" id="verticalHandle"></div>
    
    <!-- Map Panel -->
    <div class="map-panel">
        <div id="map"></div>
    </div>
    
    <!-- Horizontal Resize Handle -->
    <div class="resize-handle-h" id="horizontalHandle"></div>
    
    <!-- Timeline Panel with Controls -->
    <div class="timeline-panel">
        <!-- Timeline Controls (Filter & Search) -->
        <div id="timelineFilterPanel" class="timeline-filter-panel"></div>
        
        <!-- Timeline Navigation -->
        <button class="timeline-nav-btn timeline-nav-prev" onclick="navigateTimelinePrev()" title="Vorige periode">
            <i class="bi bi-chevron-left"></i>
        </button>
        
        <!-- Timeline Container -->
        <div id="timeline"></div>
        
        <!-- Timeline Navigation -->
        <button class="timeline-nav-btn timeline-nav-next" onclick="navigateTimelineNext()" title="Volgende periode">
            <i class="bi bi-chevron-right"></i>
        </button>
        
        <!-- Fit Timeline Button -->
        <button class="timeline-fit-btn" onclick="fitTimelineWindow()" title="Toon alles">
            <i class="bi bi-arrows-fullscreen"></i>
        </button>
    </div>
</div>

<style>
/* Reader Layout */
.reader-layout {
    display: grid;
    grid-template-columns: 2fr 4px 1fr;
    grid-template-rows: 1fr 4px 300px;
    height: calc(100vh - 120px);
    gap: 0;
}

.bible-panel {
    grid-column: 1;
    grid-row: 1;
    overflow-y: auto;
    padding: 1rem;
    background: #fff;
}

.resize-handle-v {
    grid-column: 2;
    grid-row: 1;
    background: #dee2e6;
    cursor: col-resize;
}

.resize-handle-v:hover {
    background: #2c5282;
}

.map-panel {
    grid-column: 3;
    grid-row: 1;
}

.resize-handle-h {
    grid-column: 1 / -1;
    grid-row: 2;
    background: #dee2e6;
    cursor: row-resize;
}

.resize-handle-h:hover {
    background: #2c5282;
}

.timeline-panel {
    grid-column: 1 / -1;
    grid-row: 3;
    position: relative;
    background: #fff;
    display: flex;
    flex-direction: column;
}

#map {
    height: 100%;
    width: 100%;
}

#timeline {
    flex: 1;
    width: 100%;
}

/* Timeline Filter Panel */
.timeline-filter-panel {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 0.5rem 1rem;
}

.timeline-controls {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.timeline-search {
    position: relative;
    flex: 0 0 250px;
}

.timeline-search i {
    position: absolute;
    left: 10px;
    top: 50%;
    transform: translateY(-50%);
    color: #6c757d;
    pointer-events: none;
}

.timeline-search input {
    padding-left: 32px;
    padding-right: 32px;
    border-radius: 20px;
}

.timeline-search button {
    position: absolute;
    right: 5px;
    top: 50%;
    transform: translateY(-50%);
    border: none;
    border-radius: 50%;
    width: 24px;
    height: 24px;
    padding: 0;
    display: flex;
    align-items: center;
    justify-content: center;
}

.timeline-filters {
    display: flex;
    gap: 0.5rem;
    align-items: center;
    flex: 1;
}

.filter-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #6c757d;
    margin: 0;
    flex-shrink: 0;
}

.timeline-group-filters-wrapper {
    flex: 1;
    overflow-x: auto;
    overflow-y: hidden;
    max-width: 600px;
}

.timeline-group-filters-wrapper::-webkit-scrollbar {
    height: 6px;
}

.timeline-group-filters-wrapper::-webkit-scrollbar-track {
    background: #f1f1f1;
    border-radius: 3px;
}

.timeline-group-filters-wrapper::-webkit-scrollbar-thumb {
    background: #888;
    border-radius: 3px;
}

.timeline-group-filters-wrapper::-webkit-scrollbar-thumb:hover {
    background: #555;
}

.timeline-group-filters {
    display: flex;
    gap: 0.25rem;
    padding: 0.25rem 0;
}

.timeline-filter-actions {
    display: flex;
    gap: 0.25rem;
    flex-shrink: 0;
}

.group-filter-btn {
    margin: 0;
    cursor: pointer;
    position: relative;
}

.group-filter-checkbox {
    position: absolute;
    opacity: 0;
    pointer-events: none;
}

.group-badge {
    display: inline-block;
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.75rem;
    font-weight: 600;
    color: #fff;
    transition: all 0.2s;
    cursor: pointer;
    user-select: none;
}

.group-filter-checkbox:not(:checked) + .group-badge {
    opacity: 0.3;
    filter: grayscale(0.8);
}

.group-badge:hover {
    transform: scale(1.05);
    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
}

.timeline-info {
    font-size: 0.875rem;
    color: #6c757d;
    margin-left: auto;
}

.timeline-info span {
    font-weight: 600;
    color: #2c5282;
}

/* Timeline Navigation Buttons */
.timeline-nav-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 100;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: rgba(44, 82, 130, 0.9);
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    transition: all 0.2s;
}

.timeline-nav-btn:hover {
    background: #1a365d;
    transform: translateY(-50%) scale(1.1);
}

.timeline-nav-prev { 
    left: 10px; 
}

.timeline-nav-next { 
    right: 10px; 
}

/* Fit Timeline Button */
.timeline-fit-btn {
    position: absolute;
    bottom: 10px;
    right: 10px;
    z-index: 100;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: rgba(44, 82, 130, 0.9);
    color: white;
    font-size: 1rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    transition: all 0.2s;
}

.timeline-fit-btn:hover {
    background: #1a365d;
    transform: scale(1.1);
}

/* Timeline Event Detail */
.timeline-event-detail {
    position: absolute;
    top: 70px;
    right: 50px;
    background: white;
    border: 1px solid #dee2e6;
    border-radius: 8px;
    padding: 1rem;
    max-width: 400px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
    z-index: 200;
    animation: slideIn 0.3s;
}

@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateY(-10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.timeline-event-detail .btn-close {
    position: absolute;
    top: 0.5rem;
    right: 0.5rem;
}

/* Verse styling */
.verse {
    padding: 0.5rem;
    border-radius: 4px;
    cursor: pointer;
    transition: background-color 0.2s;
}

.verse:hover {
    background-color: #f8f9fa;
}

.verse.active {
    background-color: #e3f2fd;
    border-left: 3px solid #2c5282;
}

.verse-number {
    font-weight: bold;
    color: #2c5282;
    margin-right: 0.5rem;
    font-size: 0.85em;
    vertical-align: super;
}

.chapter-header {
    font-size: 1.5rem;
    font-weight: bold;
    color: #2c5282;
    padding: 1rem;
    margin: 0 -1rem 1rem -1rem;
    border-bottom: 2px solid #dee2e6;
    position: sticky;
    top: 0;
    background: #fff;
    z-index: 100;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

/* Responsive */
@media (max-width: 992px) {
    .reader-layout {
        grid-template-columns: 1fr;
        grid-template-rows: 400px 300px 300px;
    }
    
    .bible-panel {
        grid-column: 1;
        grid-row: 1;
    }
    
    .resize-handle-v {
        display: none;
    }
    
    .map-panel {
        grid-column: 1;
        grid-row: 2;
    }
    
    .resize-handle-h {
        display: none;
    }
    
    .timeline-panel {
        grid-column: 1;
        grid-row: 3;
    }
    
    .timeline-controls {
        flex-direction: column;
        align-items: stretch;
    }
    
    .timeline-search {
        flex: 1 1 auto;
    }
    
    .timeline-filters {
        flex-direction: column;
        align-items: flex-start;
    }
}
</style>

<script>
// Initialize reader when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('Reader view loaded');
    
    // Initialize reader
    if (typeof initReader === 'function') {
        initReader();
    } else {
        console.warn('reader.js not loaded - reader functionality may be limited');
    }
    
    // Initialize map
    if (typeof initMap === 'function') {
        initMap();
    } else {
        console.warn('map.js not loaded - map functionality disabled');
    }
    
    // Initialize timeline
    if (typeof initTimeline === 'function') {
        initTimeline();
    } else {
        console.warn('timeline.js not loaded - timeline functionality disabled');
    }
});
</script>
