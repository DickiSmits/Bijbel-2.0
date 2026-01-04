/**
 * TIMELINE.JS - Enhanced with Filter & Search
 */

window.timeline = null;
window.timelineItems = null;
window.timelineGroups = null;
window.allTimelineEvents = []; // Store all events
window.allTimelineGroupsData = []; // Store all groups data
window.activeGroupFilters = new Set(); // Active group filters
window.timelineSearchQuery = '';

// Initialize timeline
function initTimeline() {
    console.log('🕐 Initializing window.timeline...');
    
    const container = document.getElementById('timeline');
    if (!container) {
        console.warn('Timeline element not found');
        return;
    }
    
    // Create datasets
    window.timelineItems = new vis.DataSet();
    window.timelineGroups = new vis.DataSet();
    
    // Timeline options - compact with better tooltips and vertical scroll
    const options = {
        orientation: 'top',
        zoomMin: 1000 * 60 * 60 * 24 * 365, // 1 year
        zoomMax: 1000 * 60 * 60 * 24 * 365 * 100, // 100 years
        zoomable: true,
        moveable: true,
        horizontalScroll: true,
        verticalScroll: true,  // Enable vertical scrolling
        zoomKey: 'ctrlKey',    // Only zoom when Ctrl is pressed, otherwise scroll
        groupOrder: 'order',
        stack: true,
        stackSubgroups: true,
        selectable: true,
        multiselect: false,
        height: '500px',       // Fixed height enables vertical scroll
        margin: {
            item: {
                horizontal: 5,
                vertical: 2    // Smaller vertical margin = closer together
            }
        },
        tooltip: {
            followMouse: true,  // Follow mouse for better positioning
            overflowMethod: 'flip', // Flip tooltip if it doesn't fit
            delay: 100
        },
        // Custom tooltip template
        template: function(item) {
            if (!item) return '';
            
            // Find original event data
            const event = window.allTimelineEvents.find(e => e.Event_ID === item.id);
            if (!event) return item.content;
            
            let html = `<div style="padding: 6px 8px; max-width: 280px; font-size: 13px;">`;
            html += `<strong style="font-size: 14px; display: block; margin-bottom: 4px;">${event.Titel}</strong>`;
            
            if (event.Beschrijving) {
                const shortDesc = event.Beschrijving.length > 150 
                    ? event.Beschrijving.substring(0, 150) + '...' 
                    : event.Beschrijving;
                html += `<div style="margin-bottom: 4px;">${shortDesc}</div>`;
            }
            
            // Show date range
            if (event.Start_Datum) {
                html += `<small style="color: #666; display: block;">`;
                html += event.Start_Datum;
                if (event.End_Datum && event.End_Datum !== event.Start_Datum) {
                    html += ` → ${event.End_Datum}`;
                }
                html += `</small>`;
            }
            
            html += `</div>`;
            return html;
        }
    };
    
    // Create timeline - CSS handles height
    window.timeline = new vis.Timeline(container, timelineItems, timelineGroups, options);
    
    // Add click handler for events
    window.timeline.on('select', function (properties) {
        if (properties.items.length > 0) {
            const eventId = properties.items[0];
            const event = window.allTimelineEvents.find(e => e.Event_ID === eventId);
            if (event) {
                console.log('📍 Timeline event selected:', event.Titel);
                
                // Navigate to corresponding verse
                if (event.Vers_ID_Start && typeof window.selectVerse === 'function') {
                    console.log(`  → Navigating to verse ${event.Vers_ID_Start}`);
                    window.selectVerse(event.Vers_ID_Start);
                    
                    // Scroll verse into view
                    const verseElement = document.querySelector(`[data-vers-id="${event.Vers_ID_Start}"]`);
                    if (verseElement) {
                        verseElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }
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
        
        // Store for filtering
        allTimelineGroupsData = groupData;
        
        window.timelineGroups.clear();
        window.timelineGroups.add(groupData);
        
        // Create filter UI
        createFilterUI(groupData);
        
        console.log(`✅ Loaded ${groupData.length} timeline groups`);
    }
    
    // Load events
    const events = await apiCall('timeline');
    if (events) {
        allTimelineEvents = events; // Store for filtering
        
        const items = processTimelineEvents(events);
        
        window.timelineItems.clear();
        window.timelineItems.add(items);
        
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
        </div>
    `;
    
    panel.innerHTML = html;
    
    // Initially hide all events (filters start unchecked)
    // But restore saved filter state
    restoreFilterState();
    
    // Attach search handler
    const searchInput = document.getElementById('timelineSearchInput');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            timelineSearchQuery = e.target.value.toLowerCase();
            saveFilterState();  // Save search query
            filterTimeline();
            
            // Show/hide clear button
            const clearBtn = document.getElementById('clearSearchBtn');
            if (clearBtn) {
                clearBtn.style.display = timelineSearchQuery ? 'block' : 'none';
            }
        });
    }
    
    console.log(`✅ Created ${groups.length} group filter buttons`);
}

// Restore filter state from localStorage
function restoreFilterState() {
    console.log('📋 Restoring filter state...');
    
    // Get saved state
    const savedFilters = localStorage.getItem('timelineActiveFilters');
    const savedSearch = localStorage.getItem('timelineSearch');
    const panelOpen = localStorage.getItem('timelineFilterOpen');
    
    // Restore panel open/closed state - using inline styles now
    const panel = document.getElementById('timelineFilterPanel');
    if (panel && panelOpen === 'true') {
        // Panel will be restored by reader.php DOMContentLoaded
        // Nothing to do here
    }
    
    // Restore checkboxes and window.activeGroupFilters
    window.activeGroupFilters.clear();
    
    if (savedFilters) {
        try {
            const filters = JSON.parse(savedFilters);
            filters.forEach(id => window.activeGroupFilters.add(id));
            console.log('Restored filters:', filters);
        } catch (e) {
            console.warn('Could not parse saved filters:', e);
        }
    }
    
    // Update checkboxes based on window.activeGroupFilters
    const checkboxes = document.querySelectorAll('.group-filter-checkbox');
    checkboxes.forEach(cb => {
        const groupId = parseInt(cb.value);
        // Checked if NOT in window.activeGroupFilters (filters = hidden groups)
        cb.checked = !window.activeGroupFilters.has(groupId);
    });
    
    // Restore search query
    if (savedSearch) {
        timelineSearchQuery = savedSearch;
        const searchInput = document.getElementById('timelineSearchInput');
        if (searchInput) {
            searchInput.value = savedSearch;
            if (savedSearch) {
                const clearBtn = document.getElementById('clearSearchBtn');
                if (clearBtn) clearBtn.style.display = 'block';
            }
        }
    }
    
    // Apply filters
    filterTimeline();
    
    console.log('✅ Filter state restored');
}

// Toggle group filter
function toggleGroupFilter(groupId) {
    const checkbox = document.querySelector(`.group-filter-checkbox[value="${groupId}"]`);
    
    if (checkbox && checkbox.checked) {
        window.activeGroupFilters.delete(groupId);
    } else {
        window.activeGroupFilters.add(groupId);
    }
    
    // Save to localStorage
    saveFilterState();
    
    filterTimeline();
}

// Toggle all groups
function toggleAllGroups(show) {
    const checkboxes = document.querySelectorAll('.group-filter-checkbox');
    
    if (show) {
        window.activeGroupFilters.clear();
        checkboxes.forEach(cb => cb.checked = true);
    } else {
        checkboxes.forEach(cb => {
            window.activeGroupFilters.add(parseInt(cb.value));
            cb.checked = false;
        });
    }
    
    // Save to localStorage
    saveFilterState();
    
    filterTimeline();
}

// Save filter state to localStorage
function saveFilterState() {
    const filtersArray = Array.from(window.activeGroupFilters);
    localStorage.setItem('timelineActiveFilters', JSON.stringify(filtersArray));
    localStorage.setItem('timelineSearch', timelineSearchQuery || '');
}

// Clear search
function clearTimelineSearch() {
    const searchInput = document.getElementById('timelineSearchInput');
    if (searchInput) {
        searchInput.value = '';
        timelineSearchQuery = '';
        saveFilterState();  // Save cleared search
        filterTimeline();
        
        const clearBtn = document.getElementById('clearSearchBtn');
        if (clearBtn) clearBtn.style.display = 'none';
    }
}

// Filter timeline based on groups and search
function filterTimeline() {
    if (!timeline || !timelineItems || !timelineGroups) return;
    
    // Filter events
    let filteredEvents = window.allTimelineEvents.filter(event => {
        // Group filter
        if (window.activeGroupFilters.size > 0 && window.activeGroupFilters.has(event.Group_ID)) {
            return false;
        }
        
        // Search filter
        if (timelineSearchQuery && !event.Titel.toLowerCase().includes(timelineSearchQuery)) {
            if (!event.Beschrijving || !event.Beschrijving.toLowerCase().includes(timelineSearchQuery)) {
                return false;
            }
        }
        
        return true;
    });
    
    // Update timeline events
    const items = processTimelineEvents(filteredEvents);
    window.timelineItems.clear();
    window.timelineItems.add(items);
    
    // Update visible groups - hide groups that are filtered out
    const visibleGroups = window.allTimelineGroupsData.filter(group => {
        // Hide if group is in window.activeGroupFilters (disabled)
        return !window.activeGroupFilters.has(group.id);
    });
    
    // Clear and re-add only visible groups
    window.timelineGroups.clear();
    window.timelineGroups.add(visibleGroups);
    
    // Update count in navbar (aantal ACTIEVE groepen)
    const countEl = document.getElementById('groupFilterCount');
    if (countEl) {
        const totalGroups = window.allTimelineGroupsData.length;
        const activeGroups = totalGroups - window.activeGroupFilters.size;
        countEl.textContent = activeGroups;
    }
    
    console.log(`Filtered: ${items.length} events, ${visibleGroups.length} of ${allTimelineGroupsData.length} groups visible`);
}

// Navigation functions - jump to prev/next EVENT
function navigateTimelinePrev() {
    if (!timeline) return;
    
    // Get currently visible/filtered events
    const visibleItems = window.timelineItems.get();
    if (visibleItems.length === 0) return;
    
    // Sort by start date
    visibleItems.sort((a, b) => a.start - b.start);
    
    // Get current selection or window center
    const selection = window.timeline.getSelection();
    let currentIndex = -1;
    
    if (selection.length > 0) {
        // Find index of selected item
        currentIndex = visibleItems.findIndex(item => item.id === selection[0]);
    } else {
        // Find item closest to current window center
        const timeWindow = window.timeline.getWindow();
        const centerTime = timeWindow.start.getTime() + (timeWindow.end.getTime() - timeWindow.start.getTime()) / 2;
        
        currentIndex = visibleItems.findIndex(item => item.start.getTime() > centerTime);
        if (currentIndex === -1) currentIndex = visibleItems.length;
    }
    
    // Go to previous
    const prevIndex = currentIndex > 0 ? currentIndex - 1 : visibleItems.length - 1;
    const prevItem = visibleItems[prevIndex];
    
    // Select and focus
    window.timeline.setSelection(prevItem.id);
    window.timeline.focus(prevItem.id, { animation: true });
    
    console.log(`◀ Previous event: ${prevItem.content}`);
    
    // Navigate to corresponding verse
    const event = window.allTimelineEvents.find(e => e.Event_ID === prevItem.id);
    if (event && event.Vers_ID_Start && typeof window.selectVerse === 'function') {
        window.selectVerse(event.Vers_ID_Start);
        
        // Scroll verse into view
        const verseElement = document.querySelector(`[data-vers-id="${event.Vers_ID_Start}"]`);
        if (verseElement) {
            verseElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

function navigateTimelineNext() {
    if (!timeline) return;
    
    // Get currently visible/filtered events
    const visibleItems = window.timelineItems.get();
    if (visibleItems.length === 0) return;
    
    // Sort by start date
    visibleItems.sort((a, b) => a.start - b.start);
    
    // Get current selection or window center
    const selection = window.timeline.getSelection();
    let currentIndex = -1;
    
    if (selection.length > 0) {
        // Find index of selected item
        currentIndex = visibleItems.findIndex(item => item.id === selection[0]);
    } else {
        // Find item closest to current window center
        const timeWindow = window.timeline.getWindow();
        const centerTime = timeWindow.start.getTime() + (timeWindow.end.getTime() - timeWindow.start.getTime()) / 2;
        
        currentIndex = visibleItems.findIndex(item => item.start.getTime() >= centerTime);
        if (currentIndex === -1) currentIndex = -1;
    }
    
    // Go to next
    const nextIndex = currentIndex < visibleItems.length - 1 ? currentIndex + 1 : 0;
    const nextItem = visibleItems[nextIndex];
    
    // Select and focus
    window.timeline.setSelection(nextItem.id);
    window.timeline.focus(nextItem.id, { animation: true });
    
    console.log(`▶ Next event: ${nextItem.content}`);
    
    // Navigate to corresponding verse
    const event = window.allTimelineEvents.find(e => e.Event_ID === nextItem.id);
    if (event && event.Vers_ID_Start && typeof window.selectVerse === 'function') {
        window.selectVerse(event.Vers_ID_Start);
        
        // Scroll verse into view
        const verseElement = document.querySelector(`[data-vers-id="${event.Vers_ID_Start}"]`);
        if (verseElement) {
            verseElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
}

// Fit timeline to show all events
function fitTimelineWindow() {
    if (timeline) {
        window.timeline.fit({ animation: true });
    }
}

// Sync timeline to selected verse
function syncTimelineToVerse(versId) {
    if (!timeline || !window.allTimelineEvents) {
        console.log('⏭️ Timeline not ready for sync');
        return;
    }
    
    console.log(`🔗 Syncing timeline to verse ${versId}`);
    
    // Find events that contain this verse
    const matchingEvents = window.allTimelineEvents.filter(event => {
        const versIdNum = parseInt(versId);
        const startVers = parseInt(event.Vers_ID_Start);
        const endVers = parseInt(event.Vers_ID_End) || startVers;
        
        // Check if verse is in range
        return versIdNum >= startVers && versIdNum <= endVers;
    });
    
    if (matchingEvents.length > 0) {
        // Get currently visible events (filtered by groups)
        const visibleItems = window.timelineItems.get();
        const visibleEventIds = visibleItems.map(item => item.id);
        
        // Find first matching event that is visible
        const visibleMatch = matchingEvents.find(event => 
            visibleEventIds.includes(event.Event_ID)
        );
        
        if (visibleMatch) {
            console.log(`  ✅ Found visible event: ${visibleMatch.Titel}`);
            
            // Select and focus on timeline
            window.timeline.setSelection(visibleMatch.Event_ID);
            window.timeline.focus(visibleMatch.Event_ID, { animation: true });
        } else {
            console.log(`  ⚠️ Found ${matchingEvents.length} event(s) but none are visible (filtered out by groups)`);
            console.log(`     First event: ${matchingEvents[0].Titel}`);
        }
    } else {
        console.log(`  ℹ️ No timeline events found for verse ${versId}`);
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
window.syncTimelineToVerse = syncTimelineToVerse;

console.log('✅ Timeline.js loaded (Enhanced)');