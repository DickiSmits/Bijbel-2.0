/**
 * Bijbelreader - Timeline Module
 * Vis.js timeline voor events
 */

console.log('📦 Loading timeline.js...');

// Global timeline variabelen
window.timeline = null;
window.timelineItems = null;
window.timelineGroups = null;
window.currentTimelineItem = null;
window.visibleGroupIds = new Set();

/**
 * Initialiseer timeline
 */
window.initTimeline = function() {
    console.log('📅 Initializing timeline...');
    
    const container = document.getElementById('timeline');
    if (!container) {
        console.error('❌ Timeline element not found');
        return;
    }
    
    try {
        window.timelineItems = new vis.DataSet();
        window.timelineGroups = new vis.DataSet();
        
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
            showTooltips: false
        };
        
        window.timeline = new vis.Timeline(container, window.timelineItems, window.timelineGroups, options);
        
        // Event handlers
        window.timeline.on('click', function(properties) {
            if (properties.item) {
                const item = window.timelineItems.get(properties.item);
                if (item) {
                    window.timeline.setSelection([properties.item]);
                    window.showTimelinePopup(item);
                }
            }
        });
        
        console.log('✓ Timeline initialized');
    } catch (error) {
        console.error('❌ Error initializing timeline:', error);
    }
};

/**
 * Laad timeline events
 */
window.loadTimelineEvents = async function() {
    console.log('📊 Loading timeline events...');
    
    if (!window.timeline) {
        console.error('❌ Timeline not initialized');
        return;
    }
    
    try {
        // Load groups
        const groups = await window.apiCall('timeline_groups');
        if (groups) {
            const groupData = groups
                .filter(g => g.Zichtbaar === 1)
                .map(group => ({
                    id: group.Group_ID,
                    content: group.Groep_Naam,
                    order: group.Volgorde,
                    style: `background-color: ${group.Kleur}20; border-color: ${group.Kleur};`
                }));
            
            window.visibleGroupIds = new Set(groupData.map(g => g.id));
            window.timelineGroups.clear();
            window.timelineGroups.add(groupData);
            
            console.log('✓ Loaded', groupData.length, 'timeline groups');
        }
        
        // Load events
        const events = await window.apiCall('timeline');
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
                        startDate = null;
                    }
                    
                    if (startDate && isNaN(startDate.getTime())) {
                        startDate = null;
                    }
                    
                    if (endDate && endDate.startsWith('-')) {
                        const year = parseInt(endDate);
                        endDate = new Date(year, 11, 31);
                    } else if (endDate) {
                        endDate = new Date(endDate);
                    } else {
                        endDate = null;
                    }
                    
                    if (endDate && isNaN(endDate.getTime())) {
                        endDate = null;
                    }
                } catch (e) {
                    startDate = null;
                    endDate = null;
                }
                
                if (!startDate) return null;
                
                let itemType = event.Type || 'box';
                if (itemType === 'range' && !endDate) {
                    itemType = 'box';
                }
                
                const textColor = event.Tekst_Kleur || window.getContrastColor(event.Kleur || '#4A90E2');
                
                const item = {
                    id: event.Event_ID,
                    content: event.Titel,
                    start: startDate,
                    type: itemType,
                    style: `background-color: ${event.Kleur}; color: ${textColor};`,
                    className: 'timeline-event',
                    title: event.Beschrijving || '',
                    vers_id_start: event.Vers_ID_Start,
                    vers_id_end: event.Vers_ID_End,
                    description: event.Beschrijving || '',
                    color: event.Kleur,
                    textColor: textColor,
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
            
            window.timelineItems.clear();
            window.timelineItems.add(items);
            
            console.log('✓ Loaded', items.length, 'timeline events');
        }
    } catch (error) {
        console.error('❌ Error loading timeline events:', error);
    }
};

/**
 * Show timeline popup
 */
window.showTimelinePopup = function(item) {
    window.currentTimelineItem = item;
    
    const popup = document.getElementById('timelinePopup');
    const titleEl = document.getElementById('timelinePopupTitle');
    const dateEl = document.getElementById('timelinePopupDate');
    const groupEl = document.getElementById('timelinePopupGroup');
    const descEl = document.getElementById('timelinePopupDescription');
    const goToVerseBtn = document.getElementById('timelinePopupGoToVerse');
    
    if (!popup || !titleEl || !dateEl || !descEl) return;
    
    titleEl.textContent = item.content;
    
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
    dateEl.textContent = dateStr || 'Geen datum';
    
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
    if (item.description && item.description.trim() !== '' && item.description !== '<p><br></p>') {
        descEl.innerHTML = item.description;
    } else {
        descEl.innerHTML = '<em class="text-muted">Geen beschrijving beschikbaar.</em>';
    }
    
    // Show/hide go to verse button
    if (goToVerseBtn) {
        if (item.vers_id_start) {
            goToVerseBtn.style.display = '';
        } else {
            goToVerseBtn.style.display = 'none';
        }
    }
    
    popup.classList.add('show');
};

/**
 * Close timeline popup
 */
window.closeTimelinePopup = function() {
    const popup = document.getElementById('timelinePopup');
    if (popup) {
        popup.classList.remove('show');
    }
    window.currentTimelineItem = null;
};

/**
 * Go to verse from timeline
 */
window.goToTimelineVerse = async function() {
    if (!window.currentTimelineItem || !window.currentTimelineItem.vers_id_start) return;
    
    const versId = window.currentTimelineItem.vers_id_start;
    console.log('Going to verse:', versId);
    
    window.closeTimelinePopup();
    
    // Check if verse already in DOM
    let verseElement = document.querySelector(`[data-vers-id="${versId}"]`);
    
    if (verseElement) {
        window.isAutoScrolling = true;
        verseElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
        window.selectVerse(versId, true);
        setTimeout(() => window.isAutoScrolling = false, 1000);
    } else {
        // Load the chapter containing this verse
        const versInfo = await window.apiCall(`get_vers_info&vers_id=${versId}`);
        
        if (!versInfo) {
            window.showNotification('Vers niet gevonden', true);
            return;
        }
        
        // Update selectors and load
        const bookSelect = document.getElementById('bookSelect');
        const chapterSelect = document.getElementById('chapterSelect');
        
        if (bookSelect && chapterSelect) {
            bookSelect.value = versInfo.Bijbelboeknaam;
            window.currentBook = versInfo.Bijbelboeknaam;
            
            // Load chapters
            const chapters = await window.apiCall(`chapters&boek=${encodeURIComponent(versInfo.Bijbelboeknaam)}`);
            chapterSelect.innerHTML = '<option value="">Hoofdstuk</option>';
            chapters.forEach(ch => {
                const option = document.createElement('option');
                option.value = ch.Hoofdstuknummer;
                option.textContent = ch.Hoofdstuknummer;
                chapterSelect.appendChild(option);
            });
            
            chapterSelect.value = versInfo.Hoofdstuknummer;
            window.currentChapter = versInfo.Hoofdstuknummer;
            
            // Load verses
            await window.loadVerses(false);
            
            // Scroll to verse
            setTimeout(() => {
                verseElement = document.querySelector(`[data-vers-id="${versId}"]`);
                if (verseElement) {
                    window.isAutoScrolling = true;
                    verseElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    window.selectVerse(versId, true);
                    setTimeout(() => window.isAutoScrolling = false, 1000);
                }
            }, 500);
        }
    }
};

/**
 * Update timeline focus
 */
window.updateTimelineFocus = function(versId) {
    if (!window.timeline || !versId) return;
    
    try {
        const items = window.timelineItems.get();
        
        const matchingItems = items.filter(item => {
            if (item.group && !window.visibleGroupIds.has(item.group)) {
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
            window.timeline.setSelection(ids);
            
            try {
                window.timeline.moveTo(matchingItems[0].start, {
                    animation: false
                });
            } catch (focusError) {
                console.warn('Timeline moveTo failed');
            }
        } else {
            window.timeline.setSelection([]);
        }
    } catch (error) {
        console.error('Error updating timeline focus:', error);
    }
};

console.log('✓ Timeline module loaded');
