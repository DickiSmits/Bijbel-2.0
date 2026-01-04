/**
 * TIMELINE ADMIN FUNCTIONS
 * Functies voor het beheren van timeline events en groepen via admin panel
 */

// Initialize timeline admin when tab is activated
function initTimelineAdmin() {
    console.log('📅 Initializing timeline admin...');
    loadTimelineGroups();
    loadTimelineEvents();
    initTimelineVerseSelectors();
    
    // Setup search
    const searchInput = document.getElementById('eventSearch');
    if (searchInput) {
        searchInput.addEventListener('input', searchTimelineEvents);
    }
    
    // Initialize Quill editor for beschrijving if not already initialized
    if (typeof timelineBeschrijvingQuill === 'undefined' && document.getElementById('timelineBeschrijvingEditor')) {
        window.timelineBeschrijvingQuill = new Quill('#timelineBeschrijvingEditor', {
            theme: 'snow',
            modules: {
                toolbar: [
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['clean']
                ]
            }
        });
    }
}

/**
 * Initialize verse selectors for timeline events
 */
async function initTimelineVerseSelectors() {
    try {
        // Load books for both start and end selectors
        const response = await fetch('?api=books');
        const books = await response.json();
        
        const startBoek = document.getElementById('timelineStartBoek');
        const endBoek = document.getElementById('timelineEndBoek');
        
        if (startBoek) {
            startBoek.innerHTML = '<option value="">Kies boek...</option>';
            books.forEach(book => {
                startBoek.innerHTML += `<option value="${book.Bijbelboeknaam}">${book.Bijbelboeknaam}</option>`;
            });
            
            // Add change handler
            startBoek.addEventListener('change', () => loadTimelineChapters('Start'));
        }
        
        if (endBoek) {
            endBoek.innerHTML = '<option value="">Kies boek...</option>';
            books.forEach(book => {
                endBoek.innerHTML += `<option value="${book.Bijbelboeknaam}">${book.Bijbelboeknaam}</option>`;
            });
            
            // Add change handler  
            endBoek.addEventListener('change', () => loadTimelineChapters('End'));
        }
        
    } catch (error) {
        console.error('❌ Load books error:', error);
    }
}

/**
 * Load chapters for timeline verse selector
 */
async function loadTimelineChapters(prefix) {
    const boek = document.getElementById(`timeline${prefix}Boek`).value;
    const hoofdstukSelect = document.getElementById(`timeline${prefix}Hoofdstuk`);
    const versSelect = document.getElementById(`timeline${prefix}Vers`);
    
    hoofdstukSelect.innerHTML = '<option value="">Hoofdstuk</option>';
    versSelect.innerHTML = '<option value="">Vers</option>';
    
    if (!boek) return;
    
    try {
        const response = await fetch(`?api=chapters&boek=${encodeURIComponent(boek)}`);
        const chapters = await response.json();
        
        chapters.forEach(ch => {
            hoofdstukSelect.innerHTML += `<option value="${ch.Hoofdstuknummer}">Hoofdstuk ${ch.Hoofdstuknummer}</option>`;
        });
        
        // Add change handler
        hoofdstukSelect.addEventListener('change', () => loadTimelineVerses(prefix));
        
    } catch (error) {
        console.error('❌ Load chapters error:', error);
    }
}

/**
 * Load verses for timeline verse selector  
 */
async function loadTimelineVerses(prefix) {
    const boek = document.getElementById(`timeline${prefix}Boek`).value;
    const hoofdstuk = document.getElementById(`timeline${prefix}Hoofdstuk`).value;
    const versSelect = document.getElementById(`timeline${prefix}Vers`);
    
    versSelect.innerHTML = '<option value="">Vers</option>';
    
    if (!boek || !hoofdstuk) return;
    
    try {
        const response = await fetch(`?api=verses&boek=${encodeURIComponent(boek)}&hoofdstuk=${hoofdstuk}`);
        const verses = await response.json();
        
        verses.forEach(vers => {
            versSelect.innerHTML += `<option value="${vers.Vers_ID}">${vers.Versnummer}</option>`;
        });
        
        // Add change handler to store Vers_ID in hidden input
        versSelect.addEventListener('change', () => {
            const versId = versSelect.value;
            document.getElementById(`timelineVers${prefix}`).value = versId;
        });
        
    } catch (error) {
        console.error('❌ Load verses error:', error);
    }
}

// ═════════════════════════════════════════════════════════════════════════
// TIMELINE EVENTS
// ═════════════════════════════════════════════════════════════════════════

/**
 * Save timeline event (create or update)
 */
async function saveTimeline() {
    const eventId = document.getElementById('timelineEventId').value;
    const titel = document.getElementById('timelineTitel').value.trim();
    const beschrijving = timelineBeschrijvingQuill ? timelineBeschrijvingQuill.root.innerHTML : '';
    const startDatum = document.getElementById('timelineStartDatum').value;
    const endDatum = document.getElementById('timelineEndDatum').value;
    const groupId = document.getElementById('timelineGroup').value;
    const versIdStart = document.getElementById('timelineVersStart').value;
    const versIdEnd = document.getElementById('timelineVersEnd').value;
    const kleur = document.getElementById('timelineKleur').value;
    const tekstKleur = document.getElementById('timelineTekstKleur').value;
    
    // Validate
    if (!titel) {
        showNotification('error', 'Titel is verplicht');
        return;
    }
    
    if (!startDatum) {
        showNotification('error', 'Start datum is verplicht');
        return;
    }
    
    // Build data object
    const data = {
        titel: titel,
        beschrijving: beschrijving,
        start_datum: startDatum,
        end_datum: endDatum || null,
        group_id: groupId || null,
        vers_id_start: versIdStart || null,
        vers_id_end: versIdEnd || null,
        kleur: kleur,
        tekst_kleur: tekstKleur
    };
    
    // Add ID if updating
    if (eventId) {
        data.event_id = eventId;
    }
    
    try {
        console.log('💾 Saving timeline event:', data);
        
        const response = await fetch('?api=save_timeline', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', result.message || 'Timeline event opgeslagen');
            clearTimelineForm();
            loadTimelineEvents();
        } else {
            showNotification('error', result.error || 'Fout bij opslaan');
        }
        
    } catch (error) {
        console.error('❌ Save timeline error:', error);
        showNotification('error', 'Netwerk fout bij opslaan');
    }
}

/**
 * Load all timeline events
 */
let allTimelineEventsAdmin = []; // Store for filtering

async function loadTimelineEvents() {
    try {
        const response = await fetch('?api=timeline');
        const events = await response.json();
        
        console.log('📅 Loaded timeline events:', events.length);
        
        // Store globally for filtering
        allTimelineEventsAdmin = events;
        
        // Update count
        const countEl = document.getElementById('eventsCount');
        if (countEl) countEl.textContent = events.length;
        
        // Render table
        renderTimelineEventsTable(events);
        
    } catch (error) {
        console.error('❌ Load timeline events error:', error);
        const tbody = document.getElementById('timelineEventsList');
        if (tbody) {
            tbody.innerHTML = '<tr><td colspan="5" class="text-center text-danger py-3">Error loading events</td></tr>';
        }
    }
}

/**
 * Render timeline events table
 */
function renderTimelineEventsTable(events) {
    const tbody = document.getElementById('timelineEventsList');
    if (!tbody) return;
    
    if (events.length === 0) {
        tbody.innerHTML = '<tr><td colspan="5" class="text-center text-muted py-3">Geen events gevonden</td></tr>';
        return;
    }
    
    let html = '';
    
    events.forEach(event => {
        // Group badge
        const groupBadge = event.Groep_Naam 
            ? `<span class="badge" style="background-color: ${event.Groep_Kleur || '#6c757d'}; color: white;">${event.Groep_Naam}</span>`
            : '<span class="text-muted small">Geen groep</span>';
        
        // Date range
        const dateRange = event.End_Datum && event.End_Datum !== event.Start_Datum
            ? `${event.Start_Datum}<br><small class="text-muted">tot ${event.End_Datum}</small>`
            : event.Start_Datum;
        
        // Verse range
        let verseRange = '<span class="text-muted small">Geen vers</span>';
        if (event.Start_Boek) {
            const startRef = `${event.Start_Boek} ${event.Start_Hoofdstuk}:${event.Start_Vers}`;
            if (event.End_Boek && (event.End_Boek !== event.Start_Boek || event.End_Hoofdstuk !== event.Start_Hoofdstuk || event.End_Vers !== event.Start_Vers)) {
                const endRef = `${event.End_Boek} ${event.End_Hoofdstuk}:${event.End_Vers}`;
                verseRange = `<small>${startRef}<br>tot ${endRef}</small>`;
            } else {
                verseRange = `<small>${startRef}</small>`;
            }
        }
        
        // Title with color preview
        const colorBox = `<span style="display: inline-block; width: 12px; height: 12px; background-color: ${event.Kleur || '#3498db'}; border-radius: 2px; margin-right: 6px;"></span>`;
        
        html += `
            <tr data-event-id="${event.Event_ID}">
                <td>
                    ${colorBox}
                    <strong>${event.Titel}</strong>
                    ${event.Beschrijving ? `<br><small class="text-muted">${event.Beschrijving.substring(0, 100)}${event.Beschrijving.length > 100 ? '...' : ''}</small>` : ''}
                </td>
                <td>${groupBadge}</td>
                <td>${dateRange}</td>
                <td>${verseRange}</td>
                <td class="text-end">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="editTimelineEvent(${event.Event_ID})" title="Bewerken">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteTimelineEvent(${event.Event_ID})" title="Verwijderen">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    tbody.innerHTML = html;
}

/**
 * Search/filter timeline events
 */
function searchTimelineEvents() {
    const searchInput = document.getElementById('eventSearch');
    if (!searchInput) return;
    
    const query = searchInput.value.toLowerCase().trim();
    
    if (!query) {
        // No search - show all
        renderTimelineEventsTable(allTimelineEventsAdmin);
        return;
    }
    
    // Filter events
    const filtered = allTimelineEventsAdmin.filter(event => {
        return (
            event.Titel.toLowerCase().includes(query) ||
            (event.Beschrijving && event.Beschrijving.toLowerCase().includes(query)) ||
            (event.Groep_Naam && event.Groep_Naam.toLowerCase().includes(query)) ||
            (event.Start_Datum && event.Start_Datum.includes(query)) ||
            (event.End_Datum && event.End_Datum.includes(query)) ||
            (event.Start_Boek && event.Start_Boek.toLowerCase().includes(query))
        );
    });
    
    renderTimelineEventsTable(filtered);
}

/**
 * Clear event search
 */
function clearEventSearch() {
    const searchInput = document.getElementById('eventSearch');
    if (searchInput) {
        searchInput.value = '';
        searchTimelineEvents();
    }
}

/**
 * Edit timeline event
 */
async function editTimelineEvent(eventId) {
    try {
        const response = await fetch('?api=timeline');
        const events = await response.json();
        const event = events.find(e => e.Event_ID == eventId);
        
        if (!event) {
            showNotification('error', 'Event niet gevonden');
            return;
        }
        
        // Fill basic fields
        document.getElementById('timelineEventId').value = event.Event_ID;
        document.getElementById('timelineTitel').value = event.Titel || '';
        document.getElementById('timelineStartDatum').value = event.Start_Datum || '';
        document.getElementById('timelineEndDatum').value = event.End_Datum || '';
        document.getElementById('timelineGroup').value = event.Group_ID || '';
        document.getElementById('timelineVersStart').value = event.Vers_ID_Start || '';
        document.getElementById('timelineVersEnd').value = event.Vers_ID_End || '';
        document.getElementById('timelineKleur').value = event.Kleur || '#3498db';
        document.getElementById('timelineTekstKleur').value = event.Tekst_Kleur || '#ffffff';
        
        // Set Quill editor if available
        if (typeof timelineBeschrijvingQuill !== 'undefined') {
            timelineBeschrijvingQuill.root.innerHTML = event.Beschrijving || '';
        }
        
        // Fill verse selectors if verse info available
        if (event.Start_Boek) {
            document.getElementById('timelineStartBoek').value = event.Start_Boek;
            await loadTimelineChapters('Start');
            if (event.Start_Hoofdstuk) {
                document.getElementById('timelineStartHoofdstuk').value = event.Start_Hoofdstuk;
                await loadTimelineVerses('Start');
                if (event.Vers_ID_Start) {
                    document.getElementById('timelineStartVers').value = event.Vers_ID_Start;
                }
            }
        }
        
        if (event.End_Boek) {
            document.getElementById('timelineEndBoek').value = event.End_Boek;
            await loadTimelineChapters('End');
            if (event.End_Hoofdstuk) {
                document.getElementById('timelineEndHoofdstuk').value = event.End_Hoofdstuk;
                await loadTimelineVerses('End');
                if (event.Vers_ID_End) {
                    document.getElementById('timelineEndVers').value = event.Vers_ID_End;
                }
            }
        }
        
        // Scroll to form
        const formCard = document.querySelector('#section-timeline .card');
        if (formCard) {
            formCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
    } catch (error) {
        console.error('❌ Edit timeline event error:', error);
    }
}

/**
 * Delete timeline event
 */
async function deleteTimelineEvent(eventId) {
    if (!confirm('Weet je zeker dat je dit event wilt verwijderen?')) {
        return;
    }
    
    try {
        const response = await fetch(`?api=delete_timeline&id=${eventId}`);
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Timeline event verwijderd');
            loadTimelineEvents();
        } else {
            showNotification('error', result.error || 'Fout bij verwijderen');
        }
        
    } catch (error) {
        console.error('❌ Delete timeline event error:', error);
        showNotification('error', 'Netwerk fout bij verwijderen');
    }
}

/**
 * Clear timeline form
 */
function clearTimelineForm() {
    document.getElementById('timelineEventId').value = '';
    document.getElementById('timelineTitel').value = '';
    document.getElementById('timelineStartDatum').value = '';
    document.getElementById('timelineEndDatum').value = '';
    document.getElementById('timelineGroup').value = '';
    document.getElementById('timelineVersStart').value = '';
    document.getElementById('timelineVersEnd').value = '';
    document.getElementById('timelineKleur').value = '#3498db';
    document.getElementById('timelineTekstKleur').value = '#ffffff';
    
    // Reset verse selectors
    document.getElementById('timelineStartBoek').value = '';
    document.getElementById('timelineStartHoofdstuk').innerHTML = '<option value="">Hoofdstuk</option>';
    document.getElementById('timelineStartVers').innerHTML = '<option value="">Vers</option>';
    document.getElementById('timelineEndBoek').value = '';
    document.getElementById('timelineEndHoofdstuk').innerHTML = '<option value="">Hoofdstuk</option>';
    document.getElementById('timelineEndVers').innerHTML = '<option value="">Vers</option>';
    
    if (typeof timelineBeschrijvingQuill !== 'undefined') {
        timelineBeschrijvingQuill.root.innerHTML = '';
    }
}

// ═════════════════════════════════════════════════════════════════════════
// TIMELINE GROUPS
// ═════════════════════════════════════════════════════════════════════════

/**
 * Load timeline groups
 */
async function loadTimelineGroups() {
    try {
        const response = await fetch('?api=timeline_groups');
        const groups = await response.json();
        
        console.log('📁 Loaded timeline groups:', groups.length);
        
        // Fill dropdown
        const groupSelect = document.getElementById('timelineGroup');
        if (groupSelect) {
            groupSelect.innerHTML = '<option value="">Geen groep</option>';
            groups.forEach(group => {
                groupSelect.innerHTML += `<option value="${group.Group_ID}">${group.Groep_Naam}</option>`;
            });
        }
        
        // Fill groups list
        const container = document.getElementById('timelineGroupsList');
        if (!container) return;
        
        if (groups.length === 0) {
            container.innerHTML = '<p class="text-muted">Nog geen timeline groepen</p>';
            return;
        }
        
        let html = '<div class="list-group">';
        
        groups.forEach(group => {
            html += `
                <div class="list-group-item d-flex justify-content-between align-items-center">
                    <div>
                        <span class="badge me-2" style="background-color: ${group.Kleur}">&nbsp;&nbsp;&nbsp;</span>
                        <strong>${group.Groep_Naam}</strong>
                        <small class="text-muted ms-2">Volgorde: ${group.Volgorde || 1}</small>
                    </div>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="editTimelineGroup(${group.Group_ID})" title="Bewerken">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteTimelineGroup(${group.Group_ID})" title="Verwijderen">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </div>
            `;
        });
        
        html += '</div>';
        container.innerHTML = html;
        
    } catch (error) {
        console.error('❌ Load timeline groups error:', error);
    }
}

/**
 * Save timeline group (create or update)
 */
async function saveTimelineGroup() {
    const groupId = document.getElementById('groupId').value;
    const naam = document.getElementById('newGroupName').value.trim();
    const kleur = document.getElementById('newGroupColor').value;
    const volgorde = document.getElementById('newGroupOrder').value;
    
    if (!naam) {
        showNotification('error', 'Naam is verplicht');
        return;
    }
    
    try {
        const isUpdate = groupId && groupId !== '';
        const endpoint = isUpdate ? '?api=update_timeline_group' : '?api=create_timeline_group';
        
        const data = {
            naam: naam,
            kleur: kleur,
            volgorde: volgorde ? parseInt(volgorde) : 1
        };
        
        if (isUpdate) {
            data.id = parseInt(groupId);
        }
        
        const response = await fetch(endpoint, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json'
            },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', isUpdate ? 'Timeline groep bijgewerkt' : 'Timeline groep aangemaakt');
            clearGroupForm();
            loadTimelineGroups();
        } else {
            showNotification('error', result.error || 'Fout bij opslaan groep');
        }
        
    } catch (error) {
        console.error('❌ Save timeline group error:', error);
        showNotification('error', 'Netwerk fout bij opslaan groep');
    }
}

/**
 * Edit timeline group
 */
async function editTimelineGroup(groupId) {
    try {
        const response = await fetch('?api=timeline_groups');
        const groups = await response.json();
        const group = groups.find(g => g.Group_ID == groupId);
        
        if (!group) {
            showNotification('error', 'Groep niet gevonden');
            return;
        }
        
        // Fill form
        document.getElementById('groupId').value = group.Group_ID;
        document.getElementById('newGroupName').value = group.Groep_Naam || '';
        document.getElementById('newGroupColor').value = group.Kleur || '#3498db';
        document.getElementById('newGroupOrder').value = group.Volgorde || 1;
        
        // Update UI to edit mode
        document.getElementById('groupFormTitle').textContent = 'Timeline Groep Bewerken';
        document.getElementById('groupSaveBtnText').textContent = 'Bijwerken';
        document.querySelector('#groupSaveBtn i').className = 'bi bi-save';
        document.getElementById('groupEditActions').style.display = 'block';
        
        // Scroll to form
        const formCard = document.querySelector('#section-timeline .card.mt-3');
        if (formCard) {
            formCard.scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
    } catch (error) {
        console.error('❌ Edit timeline group error:', error);
        showNotification('error', 'Fout bij laden groep');
    }
}

/**
 * Clear group form
 */
function clearGroupForm() {
    document.getElementById('groupId').value = '';
    document.getElementById('newGroupName').value = '';
    document.getElementById('newGroupColor').value = '#3498db';
    document.getElementById('newGroupOrder').value = '1';
    
    // Reset UI to create mode
    document.getElementById('groupFormTitle').textContent = 'Nieuwe Timeline Groep';
    document.getElementById('groupSaveBtnText').textContent = 'Aanmaken';
    document.querySelector('#groupSaveBtn i').className = 'bi bi-plus';
    document.getElementById('groupEditActions').style.display = 'none';
}

/**
 * Cancel edit group
 */
function cancelEditGroup() {
    clearGroupForm();
}

/**
 * Delete timeline group
 */
async function deleteTimelineGroup(groupId) {
    if (!confirm('Weet je zeker dat je deze groep wilt verwijderen?')) {
        return;
    }
    
    try {
        const response = await fetch(`?api=delete_timeline_group&id=${groupId}`);
        const result = await response.json();
        
        if (result.success) {
            showNotification('success', 'Timeline groep verwijderd');
            loadTimelineGroups();
        } else {
            showNotification('error', result.error || 'Fout bij verwijderen');
        }
        
    } catch (error) {
        console.error('❌ Delete timeline group error:', error);
        showNotification('error', 'Netwerk fout bij verwijderen');
    }
}

// ═════════════════════════════════════════════════════════════════════════
// HELPER FUNCTIONS
// ═════════════════════════════════════════════════════════════════════════

/**
 * Show notification toast
 */
/**
 * Show notification toast
 * Supports both calling conventions:
 * - showNotification('success', 'message') - new style
 * - showNotification('message', false) - legacy admin.js style
 */
function showNotification(typeOrMessage, messageOrIsError) {
    const toast = document.getElementById('notificationToast');
    
    // Detect calling convention
    let type, message;
    
    if (typeOrMessage === 'success' || typeOrMessage === 'error' || typeOrMessage === 'warning') {
        // New style: showNotification('success', 'message')
        type = typeOrMessage;
        message = messageOrIsError;
    } else {
        // Legacy style: showNotification('message', isError)
        message = typeOrMessage;
        type = messageOrIsError ? 'error' : 'success';
    }
    
    if (!toast) {
        console.log(type.toUpperCase() + ':', message);
        return;
    }
    
    const toastBody = toast.querySelector('.toast-body');
    toastBody.textContent = message;
    
    // Set color based on type
    toast.classList.remove('bg-success', 'bg-danger', 'bg-warning');
    if (type === 'success') {
        toast.classList.add('bg-success', 'text-white');
    } else if (type === 'error') {
        toast.classList.add('bg-danger', 'text-white');
    } else if (type === 'warning') {
        toast.classList.add('bg-warning', 'text-dark');
    }
    
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
}

// Make functions globally available
window.initTimelineAdmin = initTimelineAdmin;
window.initTimelineVerseSelectors = initTimelineVerseSelectors;
window.loadTimelineChapters = loadTimelineChapters;
window.loadTimelineVerses = loadTimelineVerses;
window.saveTimeline = saveTimeline;
window.loadTimelineEvents = loadTimelineEvents;
window.renderTimelineEventsTable = renderTimelineEventsTable;
window.searchTimelineEvents = searchTimelineEvents;
window.clearEventSearch = clearEventSearch;
window.editTimelineEvent = editTimelineEvent;
window.deleteTimelineEvent = deleteTimelineEvent;
window.clearTimelineForm = clearTimelineForm;
window.loadTimelineGroups = loadTimelineGroups;
window.saveTimelineGroup = saveTimelineGroup;
window.editTimelineGroup = editTimelineGroup;
window.clearGroupForm = clearGroupForm;
window.cancelEditGroup = cancelEditGroup;
window.deleteTimelineGroup = deleteTimelineGroup;

console.log('✅ Timeline admin functions loaded');