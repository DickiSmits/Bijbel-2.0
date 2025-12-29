/**
 * TIMELINE.JS - Enhanced with Filter & Search
 */

let timeline = null;
let timelineItems = null;
let timelineGroups = null;
let allTimelineEvents = []; // Store all events
let activeGroupFilters = new Set(); // Active group filters
let searchQuery = '';

// Initialize timeline
function initTimeline() {
    console.log('🕐 Initializing timeline...');
    
    const container = document.getElementById('timeline');
    if (!container) {
        console.warn('Timeline element not found');
        return;
    }
    
    // Create datasets
    timelineItems = new vis.DataSet();
    timelineGroups = new vis.DataSet();
    
    // Timeline options
    const options = {
        height: '250px',
        orientation: 'top',
        zoomMin: 1000 * 60 * 60 * 24 * 365, // 1 year
        zoomMax: 1000 * 60 * 60 * 24 * 365 * 100, // 100 years
        zoomable: true,
        moveable: true,
        horizontalScroll: true,
        groupOrder: 'order',
        stack: true,
        selectable: true,
        multiselect: false,
        showTooltips: true,
        tooltip: {
            followMouse: true,
            overflowMethod: 'cap'
        }
    };
    
    // Create timeline
    timeline = new vis.Timeline(container, timelineItems, timelineGroups, options);
    
    // Add click handler for events
    timeline.on('select', function (properties) {
        if (properties.items.length > 0) {
            const eventId = properties.items[0];
            const event = allTimelineEvents.find(e => e.Event_ID === eventId);
            if (event) {
                onTimelineEventClick(event);
            }
        }
    });
    
    // Load data
    loadTimelineData();
    
    console.log('✅ Timeline initialized');
}

// Load timeline data
async function loadTimelineData() {
    console.log('📅 Loading timeline data...');
    
    // Load groups
    const groups = await apiCall('timeline_groups');
    if (groups) {
        const groupData = groups
            .filter(g => g.Zichtbaar === 1)
            .map(group => ({
                id: group.Group_ID,
                content: group.Groep_Naam,
                order: group.Volgorde,
                style: `background-color: ${group.Kleur}20; border-color: ${group.Kleur};`,
                kleur: group.Kleur
            }));
        
        timelineGroups.clear();
        timelineGroups.add(groupData);
        
        // Create filter UI
        createFilterUI(groupData);
        
        console.log(`✅ Loaded ${groupData.length} timeline groups`);
    }
    
    // Load events
    const events = await apiCall('timeline');
    if (events) {
        allTimelineEvents = events; // Store for filtering
        
        const items = processTimelineEvents(events);
        
        timelineItems.clear();
        timelineItems.add(items);
        
        console.log(`✅ Loaded ${items.length} timeline events`);
    }
}

// Process events into timeline format
function processTimelineEvents(events) {
    return events.map(event => {
        let startDate = event.Start_Datum;
        let endDate = event.End_Datum;
        
        // Parse dates
        try {
            if (startDate && startDate.startsWith('-')) {
                const year = parseInt(startDate);
                startDate = new Date(year, 0, 1);
            } else if (startDate) {
                startDate = new Date(startDate);
            } else {
                return null;
            }
            
            if (endDate && endDate.startsWith('-')) {
                const year = parseInt(endDate);
                endDate = new Date(year, 11, 31);
            } else if (endDate) {
                endDate = new Date(endDate);
            }
        } catch (e) {
            console.warn('Date parse error:', event.Titel, e);
            return null;
        }
        
        if (!startDate) return null;
        
        const itemType = endDate ? 'range' : 'point';
        
        const item = {
            id: event.Event_ID,
            content: event.Titel,
            start: startDate,
            type: itemType,
            style: `background-color: ${event.Kleur}; color: ${event.Tekst_Kleur || '#ffffff'};`,
            className: 'timeline-event',
            title: event.Beschrijving || event.Titel,
            vers_id_start: event.Vers_ID_Start,
            vers_id_end: event.Vers_ID_End,
            group_id: event.Group_ID
        };
        
        if (endDate) {
            item.end = endDate;
        }
        
        if (event.Group_ID) {
            item.group = event.Group_ID;
        }
        
        return item;
    }).filter(item => item !== null);
}

// Create filter & search UI
function createFilterUI(groups) {
    const panel = document.getElementById('timelineFilterPanel');
    if (!panel) return;
    
    let html = `
        <div class="timeline-controls">
            <div class="timeline-search">
                <i class="bi bi-search"></i>
                <input type="text" 
                       id="timelineSearchInput" 
                       class="form-control form-control-sm" 
                       placeholder="Zoek events..."
                       autocomplete="off">
                <button class="btn btn-sm btn-outline-secondary" 
                        onclick="clearTimelineSearch()" 
                        id="clearSearchBtn" 
                        style="display: none;">
                    <i class="bi bi-x"></i>
                </button>
            </div>
            
            <div class="timeline-filters">
                <label class="filter-label">Groepen:</label>
                <div class="timeline-group-filters">
    `;
    
    groups.forEach(group => {
        html += `
            <label class="group-filter-btn" title="${group.content}">
                <input type="checkbox" 
                       class="group-filter-checkbox" 
                       value="${group.id}" 
                       checked
                       onchange="toggleGroupFilter(${group.id})">
                <span class="group-badge" style="background-color: ${group.kleur};">
                    ${group.content}
                </span>
            </label>
        `;
    });
    
    html += `
                </div>
                <button class="btn btn-sm btn-outline-secondary" onclick="toggleAllGroups(true)">
                    <i class="bi bi-check-all"></i> Alles
                </button>
                <button class="btn btn-sm btn-outline-secondary" onclick="toggleAllGroups(false)">
                    <i class="bi bi-x-circle"></i> Niets
                </button>
            </div>
            
            <div class="timeline-info">
                <span id="timelineEventCount">${allTimelineEvents.length}</span> events
            </div>
        </div>
    `;
    
    panel.innerHTML = html;
    
    // Attach search handler
    const searchInput = document.getElementById('timelineSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value.toLowerCase();
            filterTimeline();
            
            // Show/hide clear button
            const clearBtn = document.getElementById('clearSearchBtn');
            if (clearBtn) {
                clearBtn.style.display = searchQuery ? 'block' : 'none';
            }
        });
    }
}

// Toggle group filter
function toggleGroupFilter(groupId) {
    const checkbox = document.querySelector(`.group-filter-checkbox[value="${groupId}"]`);
    
    if (checkbox && checkbox.checked) {
        activeGroupFilters.delete(groupId);
    } else {
        activeGroupFilters.add(groupId);
    }
    
    filterTimeline();
}

// Toggle all groups
function toggleAllGroups(show) {
    const checkboxes = document.querySelectorAll('.group-filter-checkbox');
    
    if (show) {
        activeGroupFilters.clear();
        checkboxes.forEach(cb => cb.checked = true);
    } else {
        checkboxes.forEach(cb => {
            activeGroupFilters.add(parseInt(cb.value));
            cb.checked = false;
        });
    }
    
    filterTimeline();
}

// Clear search
function clearTimelineSearch() {
    const searchInput = document.getElementById('timelineSearchInput');
    if (searchInput) {
        searchInput.value = '';
        searchQuery = '';
        filterTimeline();
        
        const clearBtn = document.getElementById('clearSearchBtn');
        if (clearBtn) clearBtn.style.display = 'none';
    }
}

// Filter timeline based on groups and search
function filterTimeline() {
    if (!timeline || !timelineItems) return;
    
    // Filter events
    let filteredEvents = allTimelineEvents.filter(event => {
        // Group filter
        if (activeGroupFilters.size > 0 && activeGroupFilters.has(event.Group_ID)) {
            return false;
        }
        
        // Search filter
        if (searchQuery && !event.Titel.toLowerCase().includes(searchQuery)) {
            if (!event.Beschrijving || !event.Beschrijving.toLowerCase().includes(searchQuery)) {
                return false;
            }
        }
        
        return true;
    });
    
    // Update timeline
    const items = processTimelineEvents(filteredEvents);
    timelineItems.clear();
    timelineItems.add(items);
    
    // Update count
    const countEl = document.getElementById('timelineEventCount');
    if (countEl) {
        countEl.textContent = items.length;
    }
    
    console.log(`Filtered: ${items.length} of ${allTimelineEvents.length} events`);
}

// Handle timeline event click
function onTimelineEventClick(event) {
    console.log('Timeline event clicked:', event.Titel);
    
    // Show event details
    showTimelineEventDetails(event);
    
    // If event has verse references, navigate to verse
    if (event.Vers_ID_Start) {
        // TODO: Navigate to verse in bible text
        console.log('Navigate to verse:', event.Vers_ID_Start);
    }
}

// Show event details in modal/panel
function showTimelineEventDetails(event) {
    // Simple notification for now
    let details = `<strong>${event.Titel}</strong>`;
    if (event.Beschrijving) {
        details += `<br>${event.Beschrijving}`;
    }
    
    // Create temporary detail display
    const existing = document.getElementById('timelineEventDetail');
    if (existing) existing.remove();
    
    const detail = document.createElement('div');
    detail.id = 'timelineEventDetail';
    detail.className = 'timeline-event-detail';
    detail.innerHTML = `
        <button class="btn-close" onclick="this.parentElement.remove()"></button>
        ${details}
    `;
    
    const panel = document.querySelector('.timeline-panel');
    if (panel) {
        panel.appendChild(detail);
        
        // Auto-remove after 10 seconds
        setTimeout(() => {
            if (detail.parentElement) detail.remove();
        }, 10000);
    }
}

// Navigation functions
function navigateTimelinePrev() {
    if (!timeline) return;
    
    const range = timeline.getWindow();
    const interval = range.end - range.start;
    const newStart = new Date(range.start.getTime() - interval / 2);
    const newEnd = new Date(range.end.getTime() - interval / 2);
    
    timeline.setWindow(newStart, newEnd, { animation: true });
}

function navigateTimelineNext() {
    if (!timeline) return;
    
    const range = timeline.getWindow();
    const interval = range.end - range.start;
    const newStart = new Date(range.start.getTime() + interval / 2);
    const newEnd = new Date(range.end.getTime() + interval / 2);
    
    timeline.setWindow(newStart, newEnd, { animation: true });
}

// Fit timeline to show all events
function fitTimelineWindow() {
    if (timeline) {
        timeline.fit({ animation: true });
    }
}

// Make global
window.initTimeline = initTimeline;
window.timeline = timeline;
window.toggleGroupFilter = toggleGroupFilter;
window.toggleAllGroups = toggleAllGroups;
window.clearTimelineSearch = clearTimelineSearch;
window.navigateTimelinePrev = navigateTimelinePrev;
window.navigateTimelineNext = navigateTimelineNext;
window.fitTimelineWindow = fitTimelineWindow;

console.log('✅ Timeline.js loaded (Enhanced)');
