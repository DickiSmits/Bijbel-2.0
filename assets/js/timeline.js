/**
 * TIMELINE.JS - Enhanced with Filter & Search
 */

let timeline = null;
let timelineItems = null;
let timelineGroups = null;
let allTimelineEvents = []; // Store all events
let allTimelineGroupsData = []; // Store all groups data
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
        height: '100%',
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
        tooltip: {
            followMouse: false,
            overflowMethod: 'cap',
            delay: 100
        },
        // Custom tooltip template
        template: function(item) {
            if (!item) return '';
            
            // Find original event data
            const event = allTimelineEvents.find(e => e.Event_ID === item.id);
            if (!event) return item.content;
            
            let html = `<div style="padding: 8px; max-width: 300px;">`;
            html += `<strong style="font-size: 1.1em;">${event.Titel}</strong>`;
            
            if (event.Beschrijving) {
                html += `<br><span style="margin-top: 4px; display: block;">${event.Beschrijving}</span>`;
            }
            
            // Show date range
            if (event.Start_Datum) {
                html += `<br><small style="color: #666; margin-top: 4px; display: block;">`;
                html += event.Start_Datum;
                if (event.End_Datum && event.End_Datum !== event.Start_Datum) {
                    html += ` - ${event.End_Datum}`;
                }
                html += `</small>`;
            }
            
            html += `</div>`;
            return html;
        }
    };
    
    // Create timeline
    timeline = new vis.Timeline(container, timelineItems, timelineGroups, options);
    
    // Force timeline to fill container height
    setTimeout(() => {
        if (timeline) {
            const container = document.getElementById('timeline');
            if (container) {
                const height = container.clientHeight;
                timeline.setOptions({ height: height + 'px' });
                console.log('Timeline height set to:', height);
            }
        }
    }, 100);
    
    // Handle window resize
    window.addEventListener('resize', () => {
        if (timeline) {
            const container = document.getElementById('timeline');
            if (container) {
                const height = container.clientHeight;
                timeline.setOptions({ height: height + 'px' });
            }
        }
    });
    
    // Add click handler for events
    timeline.on('select', function (properties) {
        if (properties.items.length > 0) {
            const eventId = properties.items[0];
            const event = allTimelineEvents.find(e => e.Event_ID === eventId);
            if (event) {
                console.log('Timeline event selected:', event.Titel);
                // Could navigate to verse here if needed
                // if (event.Vers_ID_Start) { ... }
            }
        }
    });
    
    // Load data
    loadTimelineData().then(() => {
        // Fix timeline height after data loads
        setTimeout(() => {
            if (timeline) {
                const container = document.getElementById('timeline');
                if (container) {
                    const height = container.clientHeight;
                    timeline.setOptions({ height: height + 'px' });
                    timeline.redraw();
                    console.log('📏 Timeline height adjusted after data load:', height);
                }
            }
        }, 500);
    });
    
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
        
        // Store for filtering
        allTimelineGroupsData = groupData;
        
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
            content: event.Titel,  // Only title, no verse numbers
            start: startDate,
            type: itemType,
            style: `background-color: ${event.Kleur}; color: ${event.Tekst_Kleur || '#ffffff'};`,
            className: 'timeline-event',
            title: event.Beschrijving || event.Titel,
            vers_id_start: event.Vers_ID_Start,
            vers_id_end: event.Vers_ID_End,
            group_id: event.Group_ID,
            // Store original data for tooltip
            originalEvent: event
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
                <div class="timeline-group-filters-wrapper">
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
                </div>
                <div class="timeline-filter-actions">
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleAllGroups(true)" title="Alle groepen tonen">
                        <i class="bi bi-check-all"></i> Alles
                    </button>
                    <button class="btn btn-sm btn-outline-secondary" onclick="toggleAllGroups(false)" title="Alle groepen verbergen">
                        <i class="bi bi-x-circle"></i> Niets
                    </button>
                </div>
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
    
    console.log(`✅ Created ${groups.length} group filter buttons`);
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
    if (!timeline || !timelineItems || !timelineGroups) return;
    
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
    
    // Update timeline events
    const items = processTimelineEvents(filteredEvents);
    timelineItems.clear();
    timelineItems.add(items);
    
    // Update visible groups - hide groups that are filtered out
    const visibleGroups = allTimelineGroupsData.filter(group => {
        // Hide if group is in activeGroupFilters (disabled)
        return !activeGroupFilters.has(group.id);
    });
    
    // Clear and re-add only visible groups
    timelineGroups.clear();
    timelineGroups.add(visibleGroups);
    
    // Update count
    const countEl = document.getElementById('timelineEventCount');
    if (countEl) {
        countEl.textContent = items.length;
    }
    
    console.log(`Filtered: ${items.length} events, ${visibleGroups.length} of ${allTimelineGroupsData.length} groups visible`);
}

// Navigation functions - jump to prev/next EVENT
function navigateTimelinePrev() {
    if (!timeline) return;
    
    // Get currently visible/filtered events
    const visibleItems = timelineItems.get();
    if (visibleItems.length === 0) return;
    
    // Sort by start date
    visibleItems.sort((a, b) => a.start - b.start);
    
    // Get current selection or window center
    const selection = timeline.getSelection();
    let currentIndex = -1;
    
    if (selection.length > 0) {
        // Find index of selected item
        currentIndex = visibleItems.findIndex(item => item.id === selection[0]);
    } else {
        // Find item closest to current window center
        const window = timeline.getWindow();
        const centerTime = window.start.getTime() + (window.end.getTime() - window.start.getTime()) / 2;
        
        currentIndex = visibleItems.findIndex(item => item.start.getTime() > centerTime);
        if (currentIndex === -1) currentIndex = visibleItems.length;
    }
    
    // Go to previous
    const prevIndex = currentIndex > 0 ? currentIndex - 1 : visibleItems.length - 1;
    const prevItem = visibleItems[prevIndex];
    
    // Select and focus
    timeline.setSelection(prevItem.id);
    timeline.focus(prevItem.id, { animation: true });
    
    console.log(`◀ Previous event: ${prevItem.content}`);
}

function navigateTimelineNext() {
    if (!timeline) return;
    
    // Get currently visible/filtered events
    const visibleItems = timelineItems.get();
    if (visibleItems.length === 0) return;
    
    // Sort by start date
    visibleItems.sort((a, b) => a.start - b.start);
    
    // Get current selection or window center
    const selection = timeline.getSelection();
    let currentIndex = -1;
    
    if (selection.length > 0) {
        // Find index of selected item
        currentIndex = visibleItems.findIndex(item => item.id === selection[0]);
    } else {
        // Find item closest to current window center
        const window = timeline.getWindow();
        const centerTime = window.start.getTime() + (window.end.getTime() - window.start.getTime()) / 2;
        
        currentIndex = visibleItems.findIndex(item => item.start.getTime() >= centerTime);
        if (currentIndex === -1) currentIndex = -1;
    }
    
    // Go to next
    const nextIndex = currentIndex < visibleItems.length - 1 ? currentIndex + 1 : 0;
    const nextItem = visibleItems[nextIndex];
    
    // Select and focus
    timeline.setSelection(nextItem.id);
    timeline.focus(nextItem.id, { animation: true });
    
    console.log(`▶ Next event: ${nextItem.content}`);
}

// Fit timeline to show all events
function fitTimelineWindow() {
    if (timeline) {
        timeline.fit({ animation: true });
    }
}

// Open timeline in fullscreen (new tab)
function openTimelineFullscreen() {
    // Open the fullscreen timeline page
    const baseUrl = window.location.origin + window.location.pathname.replace(/\/[^/]*$/, '');
    const url = baseUrl + '/timeline-fullscreen.html';
    
    const width = window.screen.width * 0.9;
    const height = window.screen.height * 0.9;
    const left = (window.screen.width - width) / 2;
    const top = (window.screen.height - height) / 2;
    
    window.open(url, 'TimelineFullscreen', 
        `width=${width},height=${height},left=${left},top=${top},resizable=yes,scrollbars=yes,toolbar=no,menubar=no`);
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
window.openTimelineFullscreen = openTimelineFullscreen;

console.log('✅ Timeline.js loaded (Enhanced)');
