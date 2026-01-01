/**
 * TIMELINE.JS - Vis Timeline functionaliteit met verse sync
 */

let timeline = null;
let timelineItems = null;
let timelineGroups = null;
let allTimelineEvents = []; // Store all events for verse matching

// Initialize timeline
function initTimeline() {
    console.log('Initializing timeline...');
    
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
        zoomMin: 1000 * 60 * 60 * 24 * 365,
        zoomMax: 1000 * 60 * 60 * 24 * 365 * 100,
        zoomable: true,
        moveable: true,
        horizontalScroll: true,
        groupOrder: 'order',
        stack: true,
        selectable: true,
        multiselect: false,
        showTooltips: false
    };
    
    // Create timeline
    timeline = new vis.Timeline(container, timelineItems, timelineGroups, options);
    
    // Load data
    loadTimelineData();
    
    console.log('Timeline initialized');
}

// Load timeline data
async function loadTimelineData() {
    // Load groups
    const groups = await apiCall('timeline_groups');
    if (groups) {
        const groupData = groups
            .filter(g => g.Zichtbaar === 1)
            .map(group => ({
                id: group.Group_ID,
                content: group.Groep_Naam,
                order: group.Volgorde,
                style: `background-color: ${group.Kleur}20; border-color: ${group.Kleur};`
            }));
        
        timelineGroups.clear();
        timelineGroups.add(groupData);
    }
    
    // Load events
    const events = await apiCall('timeline');
    if (events) {
        // Store events globally for verse matching
        allTimelineEvents = events;
        
        const items = events.map(event => {
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
                title: event.Beschrijving || '',
                vers_id_start: event.Vers_ID_Start,
                vers_id_end: event.Vers_ID_End
            };
            
            if (endDate) {
                item.end = endDate;
            }
            
            if (event.Group_ID) {
                item.group = event.Group_ID;
            }
            
            return item;
        }).filter(item => item !== null);
        
        timelineItems.clear();
        timelineItems.add(items);
        
        console.log(`Loaded ${items.length} timeline events`);
    }
}

/**
 * Find timeline events linked to a verse
 * @param {number} versId - The verse ID
 * @returns {array} - Array of matching events
 */
function findEventsForVerse(versId) {
    if (!versId || !allTimelineEvents || allTimelineEvents.length === 0) {
        return [];
    }
    
    return allTimelineEvents.filter(event => {
        // Check if verse is the start verse
        if (event.Vers_ID_Start && parseInt(event.Vers_ID_Start) === parseInt(versId)) {
            return true;
        }
        
        // Check if verse is the end verse
        if (event.Vers_ID_End && parseInt(event.Vers_ID_End) === parseInt(versId)) {
            return true;
        }
        
        // Check if verse is between start and end (for ranges)
        if (event.Vers_ID_Start && event.Vers_ID_End) {
            const start = parseInt(event.Vers_ID_Start);
            const end = parseInt(event.Vers_ID_End);
            const current = parseInt(versId);
            
            if (current >= start && current <= end) {
                return true;
            }
        }
        
        return false;
    });
}

/**
 * Sync timeline to verse selection
 * @param {number} versId - The selected verse ID
 */
function syncTimelineToVerse(versId) {
    if (!timeline || !timelineItems) {
        console.log('⏳ Timeline not initialized yet');
        return;
    }
    
    // Find events for this verse
    const matchingEvents = findEventsForVerse(versId);
    
    if (matchingEvents.length === 0) {
        console.log('📅 No timeline events for verse:', versId);
        return;
    }
    
    console.log(`📅 Found ${matchingEvents.length} timeline event(s) for verse ${versId}`);
    
    // Get the first matching event
    const event = matchingEvents[0];
    
    // Get the timeline item
    const item = timelineItems.get(event.Event_ID);
    
    if (!item) {
        console.warn('⚠️ Timeline item not found for event:', event.Event_ID);
        return;
    }
    
    // Select the item
    timeline.setSelection([event.Event_ID]);
    
    // Move timeline to show the event
    const startDate = item.start;
    const endDate = item.end || startDate;
    
    // Calculate center point
    const centerTime = new Date((startDate.getTime() + endDate.getTime()) / 2);
    
    // Move timeline with animation
    timeline.moveTo(centerTime, {
        animation: {
            duration: 1000,
            easingFunction: 'easeInOutQuad'
        }
    });
    
    console.log(`✅ Timeline moved to event: ${event.Titel} at ${centerTime.toISOString().substring(0, 10)}`);
}

/**
 * Reset timeline selection
 */
function resetTimelineSelection() {
    if (timeline) {
        timeline.setSelection([]);
    }
}

// Make functions global
window.initTimeline = initTimeline;
window.timeline = timeline;
window.syncTimelineToVerse = syncTimelineToVerse;
window.resetTimelineSelection = resetTimelineSelection;
window.findEventsForVerse = findEventsForVerse;

console.log('Timeline.js loaded');
