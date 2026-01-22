/**
 * READER RESIZE HANDLERS
 * Handles resizing of reader layout panels
 */

(function() {
    'use strict';
    
    // Constants
    const RESIZE_CONFIG = {
        MIN_LEFT_PERCENT: 20,
        MAX_LEFT_PERCENT: 80,
        MIN_TIMELINE_HEIGHT: 150,
        MAX_TIMELINE_HEIGHT: 500,
        TIMELINE_REDRAW_DELAY: 50,
        HANDLE_WIDTH: 4
    };
    
    /**
     * Initialize resize handles for reader layout
     */
    function initResizeHandles() {
        const readerLayout = document.querySelector('.reader-layout');
        const verticalHandle = document.getElementById('verticalHandle');
        const horizontalHandle = document.getElementById('horizontalHandle');
        
        if (!readerLayout) {
            console.warn('⚠️ Reader layout not found');
            return;
        }
        
        // Vertical resize (between bible text and map)
        if (verticalHandle) {
            setupVerticalResize(readerLayout, verticalHandle);
        }
        
        // Horizontal resize (between main area and timeline)
        if (horizontalHandle) {
            setupHorizontalResize(readerLayout, horizontalHandle);
        }
        
        console.log('✅ Resize handles initialized');
    }
    
    /**
     * Setup vertical resize handler
     */
    function setupVerticalResize(readerLayout, handle) {
        let isResizing = false;
        
        handle.addEventListener('mousedown', (e) => {
            isResizing = true;
            document.body.style.cursor = 'col-resize';
            document.body.style.userSelect = 'none';
            e.preventDefault();
        });
        
        document.addEventListener('mousemove', (e) => {
            if (!isResizing) return;
            
            const containerWidth = readerLayout.offsetWidth;
            const leftPercent = ((e.clientX - readerLayout.offsetLeft) / containerWidth) * 100;
            
            if (leftPercent > RESIZE_CONFIG.MIN_LEFT_PERCENT && leftPercent < RESIZE_CONFIG.MAX_LEFT_PERCENT) {
                const rightPercent = 100 - leftPercent;
                readerLayout.style.gridTemplateColumns = `${leftPercent}fr ${RESIZE_CONFIG.HANDLE_WIDTH}px ${rightPercent}fr`;
            }
        });
        
        document.addEventListener('mouseup', () => {
            if (isResizing) {
                isResizing = false;
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
            }
        });
    }
    
    /**
     * Setup horizontal resize handler
     */
    function setupHorizontalResize(readerLayout, handle) {
        let isResizing = false;
        
        handle.addEventListener('mousedown', (e) => {
            isResizing = true;
            document.body.style.cursor = 'row-resize';
            document.body.style.userSelect = 'none';
            e.preventDefault();
        });
        
        document.addEventListener('mousemove', (e) => {
            if (!isResizing) return;
            
            const containerHeight = readerLayout.offsetHeight;
            const topHeight = e.clientY - readerLayout.offsetTop;
            const timelineHeight = containerHeight - topHeight - RESIZE_CONFIG.HANDLE_WIDTH;
            
            if (timelineHeight >= RESIZE_CONFIG.MIN_TIMELINE_HEIGHT && 
                timelineHeight <= RESIZE_CONFIG.MAX_TIMELINE_HEIGHT) {
                readerLayout.style.gridTemplateRows = `1fr ${RESIZE_CONFIG.HANDLE_WIDTH}px ${timelineHeight}px`;
                
                // Redraw timeline if available
                if (window.timeline && window.timeline.redraw) {
                    setTimeout(() => window.timeline.redraw(), RESIZE_CONFIG.TIMELINE_REDRAW_DELAY);
                }
            }
        });
        
        document.addEventListener('mouseup', () => {
            if (isResizing) {
                isResizing = false;
                document.body.style.cursor = '';
                document.body.style.userSelect = '';
            }
        });
    }
    
    // Initialize when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initResizeHandles);
    } else {
        initResizeHandles();
    }
    
    // Make function globally available
    window.initResizeHandles = initResizeHandles;
    
})();
