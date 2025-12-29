<!-- Reader View - Complete Interface with Filter Panel -->
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
        <!-- Filter Panel (starts hidden) -->
        <div id="timelineFilterPanel" class="timeline-filter-panel" style="display: none;"></div>
        
        <!-- Timeline Navigation -->
        <button class="timeline-nav-btn timeline-nav-prev" onclick="navigateTimelinePrev()" title="Vorig event">
            <i class="bi bi-chevron-left"></i>
        </button>
        
        <!-- Timeline Container -->
        <div id="timeline"></div>
        
        <!-- Timeline Navigation -->
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
    height: calc(100vh - 56px);
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
    min-height: 0;
}

/* Timeline Filter Panel */
.timeline-filter-panel {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 0.75rem 1rem;
    flex-shrink: 0;
    overflow: hidden;
}

.timeline-controls {
    display: flex;
    gap: 1rem;
    align-items: center;
    flex-wrap: wrap;
}

.timeline-search {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 0 0 250px;
}

.timeline-search i {
    color: #6c757d;
}

.timeline-filters {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 1;
}

.filter-label {
    font-size: 0.875rem;
    font-weight: 600;
    color: #495057;
    margin: 0;
}

.timeline-group-filters-wrapper {
    display: flex;
    align-items: center;
    gap: 0.5rem;
    flex: 1;
}

.timeline-group-filters {
    display: flex;
    gap: 0.5rem;
    flex-wrap: wrap;
}

.group-filter-btn {
    display: inline-flex;
    align-items: center;
    margin: 0;
    cursor: pointer;
    user-select: none;
}

.group-filter-checkbox {
    margin-right: 0.25rem;
    cursor: pointer;
}

.group-badge {
    padding: 0.25rem 0.75rem;
    border-radius: 12px;
    font-size: 0.875rem;
    font-weight: 500;
    color: white;
    white-space: nowrap;
}

.group-filter-btn input:not(:checked) + .group-badge {
    opacity: 0.4;
}

/* Timeline events - single line */
.vis-item .vis-item-content {
    white-space: nowrap !important;
    overflow: hidden !important;
    text-overflow: ellipsis !important;
    max-width: 100% !important;
    line-height: 1.2 !important;
    display: block !important;
}

.vis-item .vis-item-content * {
    display: inline !important;
    white-space: nowrap !important;
}

.vis-item .vis-item-content br {
    display: none !important;
}

.vis-item .vis-item-content div {
    display: inline !important;
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
        console.warn('reader.js not loaded');
    }
    
    // Initialize map
    if (typeof initMap === 'function') {
        initMap();
    } else {
        console.warn('map.js not loaded');
    }
    
    // Initialize timeline
    if (typeof initTimeline === 'function') {
        initTimeline();
    } else {
        console.warn('timeline.js not loaded');
    }
    
    // Initialize resize handles
    initResizeHandles();
    
    // Restore filter panel state
    setTimeout(() => {
        restoreFilterPanelState();
    }, 500);
    
    // CRITICAL: Override toggleTimelineFilter AFTER everything loads
    // This ensures our version is final, not Bootstrap's version from index.php
    setTimeout(() => {
        console.log('🔧 Installing FINAL toggleTimelineFilter...');
        
        window.toggleTimelineFilter = function() {
            const panel = document.getElementById('timelineFilterPanel');
            if (!panel) {
                console.log('⚠️ Panel not found');
                return;
            }
            
            // Remove ALL Bootstrap classes
            panel.classList.remove('collapse', 'show', 'collapsing');
            
            // Use COMPUTED display (most reliable)
            const computed = window.getComputedStyle(panel).display;
            const isHidden = (computed === 'none');
            
            console.log('Toggle:', isHidden ? 'OPEN' : 'CLOSE');
            
            if (isHidden) {
                panel.style.display = 'block';
                panel.style.visibility = 'visible';
                panel.style.opacity = '1';
                localStorage.setItem('timelineFilterOpen', 'true');
                console.log('✅ OPENED');
            } else {
                panel.style.display = 'none';
                localStorage.setItem('timelineFilterOpen', 'false');
                console.log('✅ CLOSED');
            }
        };
        
        console.log('✅ Toggle function ready!');
    }, 1500); // Wait 1.5s to load AFTER index.php
});

// Resize functionality
function initResizeHandles() {
    const readerLayout = document.querySelector('.reader-layout');
    const verticalHandle = document.getElementById('verticalHandle');
    const horizontalHandle = document.getElementById('horizontalHandle');
    
    if (!readerLayout) return;
    
    // Vertical resize
    if (verticalHandle) {
        let isResizingVertical = false;
        
        verticalHandle.addEventListener('mousedown', (e) => {
            isResizingVertical = true;
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            e.preventDefault();
        });
        
        document.addEventListener('mousemove', (e) => {
            if (!isResizingVertical) return;
            
            const containerWidth = readerLayout.offsetWidth;
            const leftPercent = ((e.clientX - readerLayout.offsetLeft) / containerWidth) * 100;
            
            if (leftPercent > 20 && leftPercent < 80) {
                const rightPercent = 100 - leftPercent;
                readerLayout.style.gridTemplateColumns = `${leftPercent}fr 4px ${rightPercent}fr`;
            }
        });
        
        document.addEventListener('mouseup', () => {
            if (isResizingVertical) {
                isResizingVertical = false;
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
            }
        });
    }
    
    // Horizontal resize
    if (horizontalHandle) {
        let isResizingHorizontal = false;
        
        horizontalHandle.addEventListener('mousedown', (e) => {
            isResizingHorizontal = true;
            document.body.style.cursor = 'row-resize';
            document.body.style.userSelect = 'none';
            e.preventDefault();
        });
        
        document.addEventListener('mousemove', (e) => {
            if (!isResizingHorizontal) return;
            
            const containerHeight = readerLayout.offsetHeight;
            const topHeight = e.clientY - readerLayout.offsetTop;
            const timelineHeight = containerHeight - topHeight - 4;
            
            if (timelineHeight >= 150 && timelineHeight <= 500) {
                readerLayout.style.gridTemplateRows = `1fr 4px ${timelineHeight}px`;
                
                if (window.timeline && window.timeline.redraw) {
                    setTimeout(() => window.timeline.redraw(), 50);
                }
            }
        });
        
        document.addEventListener('mouseup', () => {
            if (isResizingHorizontal) {
                isResizingHorizontal = false;
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
            }
        });
    }
    
    console.log('✅ Resize handles initialized');
}

// Restore filter panel state
function restoreFilterPanelState() {
    const panelOpen = localStorage.getItem('timelineFilterOpen');
    const panel = document.getElementById('timelineFilterPanel');
    
    if (panel) {
        console.log('📋 Restoring panel state...');
        
        // Remove Bootstrap classes
        panel.classList.remove('collapse', 'show', 'collapsing');
        
        if (panelOpen === 'true') {
            panel.style.display = 'block';
            panel.style.visibility = 'visible';
            panel.style.opacity = '1';
            console.log('✅ Restored: OPEN');
        } else {
            panel.style.display = 'none';
            console.log('✅ Restored: CLOSED');
        }
    }
}
</script>