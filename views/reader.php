<!-- Reader View - Complete Interface -->
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
    
    <!-- Timeline Panel -->
    <div class="timeline-panel">
        <button class="timeline-nav-btn timeline-nav-prev" onclick="navigateTimelinePrev()" title="Vorig event">
            <i class="bi bi-chevron-left"></i>
        </button>
        <div id="timeline"></div>
        <button class="timeline-nav-btn timeline-nav-next" onclick="navigateTimelineNext()" title="Volgend event">
            <i class="bi bi-chevron-right"></i>
        </button>
    </div>
</div>

<style>
/* Reader Layout */
.reader-layout {
    display: grid;
    grid-template-columns: 2fr 4px 1fr;
    grid-template-rows: 1fr 4px 250px;
    height: calc(100vh - 56px);  /* Fixed: Was 120px, should be actual header height */
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
}

#map {
    height: 100%;
    width: 100%;
}

#timeline {
    height: 100%;
    width: 100%;
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

/* Timeline navigation */
.timeline-nav-btn {
    position: absolute;
    top: 50%;
    transform: translateY(-50%);
    z-index: 100;
    width: 36px;
    height: 36px;
    border-radius: 50%;
    border: none;
    background: #2c5282;
    color: white;
    font-size: 1.2rem;
    cursor: pointer;
    display: flex;
    align-items: center;
    justify-content: center;
    box-shadow: 0 2px 8px rgba(0,0,0,0.2);
}

.timeline-nav-btn:hover {
    background: #1a365d;
}

.timeline-nav-prev { left: 10px; }
.timeline-nav-next { right: 10px; }

/* Responsive */
@media (max-width: 992px) {
    .reader-layout {
        grid-template-columns: 1fr;
        grid-template-rows: 400px 300px 250px;
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
