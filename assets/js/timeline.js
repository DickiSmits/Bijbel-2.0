/**
 * TIMELINE.JS - Vis Timeline functionaliteit
 */

let timeline = null;
let timelineItems = null;
let timelineGroups = null;

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

// Make global
window.initTimeline = initTimeline;
window.timeline = timeline;

console.log('Timeline.js loaded');
