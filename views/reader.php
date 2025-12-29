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
    
    <!-- Timeline Panel with Collapsible Filter -->
    <div class="timeline-panel">
        <!-- Filter Toggle Button (always visible) -->
        <div class="timeline-header">
            <button class="timeline-filter-toggle" onclick="toggleTimelineFilter()" title="Filter in/uitklappen - Aantal actieve groepen">
                <i class="bi bi-funnel"></i>
                <span id="visibleEventCount">0</span>
                <span class="filter-label-text">groepen</span>
            </button>
        </div>
        
        <!-- Filter Panel (collapsible, starts closed) -->
        <div id="timelineFilterPanel" class="timeline-filter-panel collapsed"></div>
        
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
    height: calc(100vh - 56px);  /* Fixed: exact header height */
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

/* Timeline Header with Filter Toggle */
.timeline-header {
    background: #2c5282;
    padding: 0.5rem 1rem;
    display: flex;
    align-items: center;
    flex-shrink: 0;
}

.timeline-filter-toggle {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 0.5rem 1rem;
    border-radius: 4px;
    cursor: pointer;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    font-size: 0.9rem;
    transition: all 0.2s;
}

.timeline-filter-toggle:hover {
    background: rgba(255, 255, 255, 0.3);
}

.timeline-filter-toggle i {
    font-size: 1.1rem;
}

#visibleEventCount {
    font-weight: bold;
    min-width: 20px;
    text-align: center;
}

.filter-label-text {
    font-size: 0.85rem;
    opacity: 0.9;
}

/* Timeline Filter Panel */
.timeline-filter-panel {
    background: #f8f9fa;
    border-bottom: 1px solid #dee2e6;
    padding: 0.75rem 1rem;
    flex-shrink: 0;
    max-height: 200px;
    overflow: hidden;
    transition: max-height 0.3s ease, padding 0.3s ease;
}

.timeline-filter-panel.collapsed {
    max-height: 0;
    padding: 0 1rem;
    border-bottom: none;
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

.timeline-event-count {
    font-size: 0.875rem;
    color: #6c757d;
    font-weight: 500;
    white-space: nowrap;
}

/* Timeline events - FORCE single line - AGGRESSIVE */
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

.vis-item.vis-range .vis-item-content {
    white-space: nowrap !important;
    display: block !important;
}

.vis-item-overflow {
    overflow: hidden !important;
}

/* Hide any internal divs/spans that create newlines */
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
    
    // Initialize resize handles
    initResizeHandles();
});

// Resize functionality
function initResizeHandles() {
    const readerLayout = document.querySelector('.reader-layout');
    const verticalHandle = document.getElementById('verticalHandle');
    const horizontalHandle = document.getElementById('horizontalHandle');
    
    if (!readerLayout) return;
    
    // Vertical resize (Bible/Map split)
    if (verticalHandle) {
        let isResizingVertical = false;
        let startX = 0;
        
        verticalHandle.addEventListener('mousedown', (e) => {
            isResizingVertical = true;
            startX = e.clientX;
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            e.preventDefault();
        });
        
        document.addEventListener('mousemove', (e) => {
            if (!isResizingVertical) return;
            
            const containerWidth = readerLayout.offsetWidth;
            const leftPercent = ((e.clientX - readerLayout.offsetLeft) / containerWidth) * 100;
            
            // Constrain between 20% and 80%
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
    
    // Horizontal resize (Top/Timeline split)
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
            const timelineHeight = containerHeight - topHeight - 4; // 4px for handle
            
            // Constrain timeline between 150px and 500px
            if (timelineHeight >= 150 && timelineHeight <= 500) {
                readerLayout.style.gridTemplateRows = `1fr 4px ${timelineHeight}px`;
                
                // Notify timeline to redraw
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

// Toggle timeline filter panel
function toggleTimelineFilter() {
    const panel = document.getElementById('timelineFilterPanel');
    if (!panel) return;
    
    const isCollapsed = panel.classList.contains('collapsed');
    
    if (isCollapsed) {
        panel.classList.remove('collapsed');
        localStorage.setItem('timelineFilterOpen', 'true');
    } else {
        panel.classList.add('collapsed');
        localStorage.setItem('timelineFilterOpen', 'false');
    }
    
    console.log('Timeline filter toggled:', isCollapsed ? 'opened' : 'closed');
}

// Make global
window.toggleTimelineFilter = toggleTimelineFilter;
</script>