/**
 * ADMIN DATA LOADING - Updated met DataTable
 * Vervang deze functies in admin.js
 */

// ============= TIMELINE LIST MET DATATABLE =============

async function loadTimelineList() {
    console.log('📅 Loading timeline events...');
    
    const events = await window.apiCall('timeline');
    
    if (!events) {
        console.log('No timeline events loaded');
        return;
    }
    
    console.log(`✅ Loaded ${events.length} timeline events`);
    
    // Create DataTable
    new window.DataTable('timelineList', events, {
        idField: 'Event_ID',
        columns: [
            { 
                label: 'Titel', 
                field: 'Titel' 
            },
            { 
                label: 'Start', 
                field: 'Start_Datum',
                format: (val) => val ? val.substring(0, 10) : '-'
            },
            { 
                label: 'Eind', 
                field: 'End_Datum',
                format: (val) => val ? val.substring(0, 10) : '-'
            },
            { 
                label: 'Bijbelvers', 
                field: 'Vers_ID_Start',
                format: (val, row) => {
                    if (!val && !row.Vers_ID_End) return '-';
                    let result = '';
                    if (val) result += `<span class="badge bg-info">${val}</span>`;
                    if (row.Vers_ID_End && row.Vers_ID_End !== val) {
                        result += ` - <span class="badge bg-info">${row.Vers_ID_End}</span>`;
                    }
                    return result || '-';
                }
            },
            { 
                label: 'Type', 
                field: 'Type' 
            },
            { 
                label: 'Kleur', 
                field: 'Kleur',
                format: (val) => `<span class="color-badge" style="background-color: ${val}"></span> ${val}`
            }
        ],
        onEdit: 'editTimeline',
        onDelete: 'deleteTimeline'
    });
}

// ============= TIMELINE GROUPS MET DATATABLE =============

async function loadTimelineGroups() {
    console.log('🏷️ Loading timeline groups...');
    
    const groups = await window.apiCall('timeline_groups');
    
    if (!groups) {
        console.log('No groups loaded');
        return;
    }
    
    console.log(`✅ Loaded ${groups.length} timeline groups`);
    
    // Fill dropdown (for new event form)
    const dropdown = document.getElementById('timelineGroup');
    if (dropdown) {
        dropdown.innerHTML = '<option value="">Geen groep</option>';
        groups.forEach(group => {
            const option = document.createElement('option');
            option.value = group.Group_ID;
            option.textContent = group.Groep_Naam;
            dropdown.appendChild(option);
        });
        console.log('✅ Filled dropdown with groups');
    }
    
    // Show groups list (optional - if you want a separate groups table)
    const list = document.getElementById('groupsList');
    if (list) {
        new window.DataTable('groupsList', groups, {
            idField: 'Group_ID',
            columns: [
                { 
                    label: 'Naam', 
                    field: 'Groep_Naam' 
                },
                { 
                    label: 'Kleur', 
                    field: 'Kleur',
                    format: (val) => `<span class="color-badge" style="background-color: ${val}"></span> ${val}`
                },
                { 
                    label: 'Volgorde', 
                    field: 'Volgorde' 
                },
                { 
                    label: 'Zichtbaar', 
                    field: 'Zichtbaar',
                    format: (val) => val ? '✅' : '❌'
                }
            ],
            onEdit: 'editTimelineGroup',
            onDelete: 'deleteTimelineGroup'
        });
        console.log('✅ Filled groups list with DataTable');
    }
}

// ============= LOCATIONS LIST MET DATATABLE =============

async function loadLocationList() {
    console.log('📍 Loading locations...');
    
    const locations = await window.apiCall('locations');
    
    if (!locations) {
        console.log('No locations loaded');
        return;
    }
    
    console.log(`✅ Loaded ${locations.length} locations`);
    
    new window.DataTable('locationList', locations, {
        idField: 'Locatie_ID',
        columns: [
            { 
                label: 'Naam', 
                field: 'Naam' 
            },
            { 
                label: 'Type', 
                field: 'Type' 
            },
            { 
                label: 'Latitude', 
                field: 'Latitude',
                format: (val) => val ? val.toFixed(4) : '-'
            },
            { 
                label: 'Longitude', 
                field: 'Longitude',
                format: (val) => val ? val.toFixed(4) : '-'
            },
            { 
                label: 'Beschrijving', 
                field: 'Beschrijving',
                format: (val) => {
                    if (!val) return '-';
                    return val.length > 50 ? val.substring(0, 50) + '...' : val;
                }
            }
        ],
        onEdit: 'editLocation',
        onDelete: 'deleteLocation'
    });
}

// ============= IMAGES LIST MET DATATABLE =============

async function loadImageList() {
    console.log('🖼️ Loading images...');
    
    const images = await window.apiCall('all_images');
    
    if (!images) {
        console.log('No images loaded');
        return;
    }
    
    console.log(`✅ Loaded ${images.length} images`);
    
    new window.DataTable('imageList', images, {
        idField: 'Afbeelding_ID',
        columns: [
            { 
                label: 'Preview', 
                field: 'Bestandspad',
                format: (val) => `<img src="${val}" style="width: 60px; height: 40px; object-fit: cover; border-radius: 4px;">`
            },
            { 
                label: 'Caption', 
                field: 'Caption',
                format: (val) => val || '<em class="text-muted">Geen caption</em>'
            },
            { 
                label: 'Bijbelvers', 
                field: 'Bijbelboeknaam',
                format: (val, row) => {
                    if (!val) return '-';
                    return `${val} ${row.Hoofdstuknummer}:${row.Versnummer}`;
                }
            },
            { 
                label: 'Geüpload', 
                field: 'Geupload_Op',
                format: (val) => val ? new Date(val).toLocaleDateString('nl-NL') : '-'
            }
        ],
        onEdit: 'editImage',
        onDelete: 'deleteImage'
    });
}

// ============= NOTES LIST (keep simple list, edit works differently) =============

async function loadNotes() {
    console.log('📋 Loading notes from database...');
    
    const notesData = await window.apiCall('notes');
    
    if (!notesData) {
        console.log('No notes loaded');
        window.notes = [];
        return;
    }
    
    // Store globally on window object (NOT local variable!)
    window.notes = notesData;
    
    console.log(`✅ Loaded ${window.notes.length} notes`);
    
    const notesList = document.getElementById('notesList');
    if (!notesList) return;
    
    if (window.notes.length === 0) {
        notesList.innerHTML = '<p class="text-muted text-center py-4">Nog geen notities</p>';
        return;
    }
    
    notesList.innerHTML = window.notes.map(note => `
        <div class="note-item ${window.currentNoteId === note.Notitie_ID ? 'active' : ''}" 
             onclick="selectNote(${note.Notitie_ID})">
            <div class="note-title">${note.Titel || 'Zonder titel'}</div>
            <div class="note-date">${new Date(note.Gewijzigd).toLocaleDateString('nl-NL')}</div>
        </div>
    `).join('');
}

// Make functions global
window.loadTimelineList = loadTimelineList;
window.loadTimelineGroups = loadTimelineGroups;
window.loadLocationList = loadLocationList;
window.loadImageList = loadImageList;
window.loadNotes = loadNotes;

console.log('✅ DataTable loading functions registered');
