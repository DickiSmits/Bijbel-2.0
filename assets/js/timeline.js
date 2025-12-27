/**
 * Bijbelreader - Timeline Module
 * Vis.js timeline functionaliteit voor gebeurtenissen
 */

// Global timeline variabelen
let timeline;
let timelineItems;
let timelineGroups;
let originalTimelineItems = {};
let visibleGroupIds = new Set();
let allTimelineEvents = [];
let currentTimelineItem = null;

/**
 * Initialiseer de Vis.js timeline
 */
function initTimeline() {
    console.log('Initializing timeline...');
    
    const container = document.getElementById('timeline');
    if (!container) {
        console.error('Timeline container not found');
        return;
    }
    
    timelineItems = new vis.DataSet();
    timelineGroups = new vis.DataSet();
    
    const options = {
        height: '300px',
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
        margin: {
            item: {
                horizontal: 5,
                vertical: 3
            },
            axis: 5
        },
        showTooltips: false
    };
    
    timeline = new vis.Timeline(container, timelineItems, timelineGroups, options);
    
    // Add click handler
    timeline.on('click', function (properties) {
        if (properties.item) {
            const item = timeline.itemsData.get(properties.item);
            if (item) {
                timeline.setSelection([properties.item]);
                showTimelinePopup(item);
                updateNavButtons();
            }
        }
    });
    
    timeline.on('select', function (properties) {
        if (properties.items.length > 0) {
            const item = timeline.itemsData.get(properties.items[0]);
            showTimelinePopup(item);
        }
        updateNavButtons();
    });
    
    console.log('Timeline initialized');
}

/**
 * Laad timeline events en groups
 */
async function loadTimelineEvents() {
    console.log('Loading timeline events...');
    
    // Load groups first
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
        
        visibleGroupIds = new Set(groupData.map(g => g.id));
        timeline.setGroups(groupData);
    }
    
    // Load events
    const events = await apiCall('timeline');
    if (events) {
        allTimelineEvents = events;
        
        const items = events.map(event => {
            // Parse dates
            let startDate = null;
            let endDate = null;
            
            try {
                if (event.Start_Datum && event.Start_Datum.startsWith('-')) {
                    const year = parseInt(event.Start_Datum);
                    startDate = new Date(year, 0, 1);
                } else if (event.Start_Datum) {
                    startDate = new Date(event.Start_Datum);
                } else {
                    return null; // Skip events without start date
                }
                
                if (event.End_Datum && event.End_Datum.startsWith('-')) {
                    const year = parseInt(event.End_Datum);
                    endDate = new Date(year, 11, 31);
                } else if (event.End_Datum) {
                    endDate = new Date(event.End_Datum);
                }
            } catch (e) {
                console.warn('Date parse error for event:', event.Titel, e);
                return null;
            }
            
            // Determine type based on end date
            let itemType = event.Type || 'box';
            if (itemType === 'range' && !endDate) {
                itemType = 'box';
            }
            
            const item = {
                id: event.Event_ID,
                content: event.Titel,
                start: startDate,
                type: itemType,
                style: `background-color: ${event.Kleur}; color: ${event.Tekst_Kleur || getContrastColor(event.Kleur)};`,
                className: 'timeline-event',
                title: event.Beschrijving || '',
                vers_id_start: event.Vers_ID_Start,
                vers_id_end: event.Vers_ID_End,
                description: event.Beschrijving || '',
                color: event.Kleur,
                textColor: event.Tekst_Kleur,
                groupName: event.Groep_Naam || '',
                startDatum: event.Start_Datum,
                endDatum: event.End_Datum
            };
            
            if (endDate) {
                item.end = endDate;
            }
            
            if (event.Group_ID) {
                item.group = event.Group_ID;
            }
            
            return item;
        }).filter(item => item !== null);
        
        console.log(`Loading ${items.length} timeline events`);
        timeline.setItems(items);
        
        // Cache original styles
        originalTimelineItems = {};
        items.forEach(item => {
            originalTimelineItems[item.id] = {
                style: item.style,
                className: item.className
            };
        });
        
        setTimeout(() => updateNavButtons(), 100);
    }
}

/**
 * Toon timeline event popup
 */
function showTimelinePopup(item) {
    currentTimelineItem = item;
    
    const popup = document.getElementById('timelinePopup');
    if (!popup) return;
    
    const titleEl = document.getElementById('timelinePopupTitle');
    const dateEl = document.getElementById('timelinePopupDate');
    const groupEl = document.getElementById('timelinePopupGroup');
    const descEl = document.getElementById('timelinePopupDescription');
    const goToVerseBtn = document.getElementById('timelinePopupGoToVerse');
    
    // Set title
    if (titleEl) titleEl.textContent = item.content;
    
    // Format date
    let dateStr = '';
    if (item.startDatum) {
        const year = parseInt(item.startDatum);
        if (year < 0) {
            dateStr = Math.abs(year) + ' v.Chr.';
        } else {
            dateStr = year + ' n.Chr.';
        }
        
        if (item.endDatum && item.endDatum !== item.startDatum) {
            const endYear = parseInt(item.endDatum);
            if (endYear < 0) {
                dateStr += ' - ' + Math.abs(endYear) + ' v.Chr.';
            } else {
                dateStr += ' - ' + endYear + ' n.Chr.';
            }
        }
    }
    if (dateEl) dateEl.textContent = dateStr || 'Geen datum';
    
    // Set group badge
    if (groupEl) {
        if (item.groupName) {
            groupEl.textContent = item.groupName;
            groupEl.style.backgroundColor = item.color || '#666';
            groupEl.style.display = '';
        } else {
            groupEl.style.display = 'none';
        }
    }
    
    // Set description
    if (descEl) {
        if (item.description && item.description.trim() !== '' && item.description !== '<p><br></p>') {
            descEl.innerHTML = item.description;
        } else {
            descEl.innerHTML = '<em class="text-muted">Geen beschrijving beschikbaar.</em>';
        }
    }
    
    // Show/hide "go to verse" button
    if (goToVerseBtn) {
        goToVerseBtn.style.display = item.vers_id_start ? '' : 'none';
    }
    
    // Show popup
    popup.classList.add('show');
}

/**
 * Sluit timeline popup
 */
function closeTimelinePopup() {
    const popup = document.getElementById('timelinePopup');
    if (popup) {
        popup.classList.remove('show');
    }
    currentTimelineItem = null;
}

/**
 * Ga naar vers vanuit timeline
 */
async function goToTimelineVerse() {
    if (!currentTimelineItem || !currentTimelineItem.vers_id_start) return;
    
    const versId = currentTimelineItem.vers_id_start;
    closeTimelinePopup();
    
    // Check if verse is already in DOM
    let verseElement = document.querySelector(`[data-vers-id="${versId}"]`);
    
    if (verseElement) {
        // Scroll to verse
        window.isAutoScrolling = true;
        verseElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        if (typeof selectVerse === 'function') {
            selectVerse(versId, true);
        }
        setTimeout(() => { window.isAutoScrolling = false; }, 1000);
    } else {
        // Load chapter containing this verse
        const info = await apiCall(`get_vers_info&vers_id=${versId}`);
        if (info && typeof loadChapter === 'function') {
            loadChapter(info.Bijbelboeknaam, info.Hoofdstuknummer, versId);
        }
    }
}

/**
 * Update focus op timeline based op vers
 */
function updateTimelineFocus(versId) {
    if (!timeline || !versId) return;
    
    try {
        const items = timeline.itemsData.get();
        const matchingItems = items.filter(item => {
            if (item.group && !visibleGroupIds.has(item.group)) {
                return false;
            }
            
            if (item.vers_id_start && item.vers_id_end) {
                return versId >= item.vers_id_start && versId <= item.vers_id_end;
            } else if (item.vers_id_start) {
                return versId === item.vers_id_start;
            }
            return false;
        });
        
        if (matchingItems.length > 0) {
            const ids = matchingItems.map(item => item.id);
            timeline.setSelection(ids);
            
            try {
                const firstItem = matchingItems[0];
                timeline.moveTo(firstItem.start, { animation: false });
            } catch (focusError) {
                console.warn('Timeline moveTo failed');
            }
            
            updateNavButtons();
        } else {
            timeline.setSelection([]);
            updateNavButtons();
        }
    } catch (error) {
        console.error('Error updating timeline focus:', error);
    }
}

/**
 * Update navigation button states
 */
function updateNavButtons() {
    // Deze functie wordt aangeroepen maar doet nu niets
    // In de toekomst kan hier code komen voor prev/next knoppen
}

/**
 * Navigeer naar vorig timeline event
 */
function navigateTimelinePrev() {
    // TODO: Implementeer navigatie
}

/**
 * Navigeer naar volgend timeline event
 */
function navigateTimelineNext() {
    // TODO: Implementeer navigatie
}

// Export functies
if (typeof window !== 'undefined') {
    window.initTimeline = initTimeline;
    window.loadTimelineEvents = loadTimelineEvents;
    window.showTimelinePopup = showTimelinePopup;
    window.closeTimelinePopup = closeTimelinePopup;
    window.goToTimelineVerse = goToTimelineVerse;
    window.updateTimelineFocus = updateTimelineFocus;
    window.navigateTimelinePrev = navigateTimelinePrev;
    window.navigateTimelineNext = navigateTimelineNext;
}

console.log('Timeline module loaded');
