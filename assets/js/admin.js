/**
 * ADMIN.JS - TIMELINE PROFESSIONAL EDITOR (FIXED)
 * Add these functions to your admin.js
 * 
 * NOTE: Remove "let timelineDescQuill = null;" if you already have it declared!
 */

// ============= TIMELINE QUILL EDITOR =============

// NOTE: If you already have "let timelineDescQuill = null;" at the top of admin.js,
// then SKIP this line and just add the functions below!
// Only uncomment this if you DON'T have it yet:
// let timelineDescQuill = null;

/**
 * Initialize Timeline Beschrijving Quill Editor
 * Call this in your initAdminMode() or when timeline section is shown
 */
function initTimelineEditor() {
    const container = document.getElementById('timelineBeschrijvingEditor');
    if (!container) {
        console.warn('⚠️ timelineBeschrijvingEditor not found');
        return;
    }
    
    // Check if already initialized
    if (timelineDescQuill) {
        console.log('✅ Timeline editor already initialized');
        return;
    }
    
    timelineDescQuill = new Quill('#timelineBeschrijvingEditor', {
        theme: 'snow',
        placeholder: 'Beschrijving van het event...',
        modules: {
            toolbar: [
                [{ 'header': [1, 2, 3, false] }],
                [{ 'font': [] }],
                ['bold', 'italic', 'underline', 'strikethrough'],
                [{ 'color': [] }, { 'background': [] }],
                [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                ['link'],
                ['clean']
            ]
        }
    });
    
    console.log('✅ Timeline Beschrijving editor initialized');
}

// ============= VERSE SELECTOR CASCADE =============

/**
 * Load books into timeline verse selectors
 */
async function loadTimelineVerseBooks() {
    const books = await window.apiCall('books');
    if (!books) return;
    
    const startBoekSelect = document.getElementById('timelineStartBoek');
    const endBoekSelect = document.getElementById('timelineEndBoek');
    
    if (!startBoekSelect || !endBoekSelect) return;
    
    const bookOptions = books.map(book => 
        `<option value="${book.Bijbelboeknaam}">${book.Bijbelboeknaam}</option>`
    ).join('');
    
    startBoekSelect.innerHTML = '<option value="">Kies boek...</option>' + bookOptions;
    endBoekSelect.innerHTML = '<option value="">Kies boek...</option>' + bookOptions;
    
    console.log(`✅ Loaded ${books.length} books into timeline verse selectors`);
}

/**
 * Setup verse selector event listeners
 */
function setupTimelineVerseSelectors() {
    // Start Vers - Boek change
    const startBoek = document.getElementById('timelineStartBoek');
    if (startBoek) {
        startBoek.addEventListener('change', async (e) => {
            const boek = e.target.value;
            const hoofdstukSelect = document.getElementById('timelineStartHoofdstuk');
            const versSelect = document.getElementById('timelineStartVers');
            
            hoofdstukSelect.innerHTML = '<option value="">Hoofdstuk</option>';
            versSelect.innerHTML = '<option value="">Vers</option>';
            
            if (!boek) return;
            
            const chapters = await window.apiCall(`chapters&boek=${encodeURIComponent(boek)}`);
            if (chapters) {
                hoofdstukSelect.innerHTML = '<option value="">Hoofdstuk</option>' +
                    chapters.map(ch => `<option value="${ch.Hoofdstuknummer}">${ch.Hoofdstuknummer}</option>`).join('');
            }
        });
    }
    
    // Start Vers - Hoofdstuk change
    const startHoofdstuk = document.getElementById('timelineStartHoofdstuk');
    if (startHoofdstuk) {
        startHoofdstuk.addEventListener('change', async (e) => {
            const hoofdstuk = e.target.value;
            const boek = document.getElementById('timelineStartBoek').value;
            const versSelect = document.getElementById('timelineStartVers');
            
            versSelect.innerHTML = '<option value="">Vers</option>';
            
            if (!boek || !hoofdstuk) return;
            
            const verses = await window.apiCall(`verses&boek=${encodeURIComponent(boek)}&hoofdstuk=${hoofdstuk}&limit=999`);
            if (verses) {
                versSelect.innerHTML = '<option value="">Vers</option>' +
                    verses.map(v => `<option value="${v.Vers_ID}">${v.Versnummer}</option>`).join('');
            }
        });
    }
    
    // Eind Vers - Boek change
    const endBoek = document.getElementById('timelineEndBoek');
    if (endBoek) {
        endBoek.addEventListener('change', async (e) => {
            const boek = e.target.value;
            const hoofdstukSelect = document.getElementById('timelineEndHoofdstuk');
            const versSelect = document.getElementById('timelineEndVers');
            
            hoofdstukSelect.innerHTML = '<option value="">Hoofdstuk</option>';
            versSelect.innerHTML = '<option value="">Vers</option>';
            
            if (!boek) return;
            
            const chapters = await window.apiCall(`chapters&boek=${encodeURIComponent(boek)}`);
            if (chapters) {
                hoofdstukSelect.innerHTML = '<option value="">Hoofdstuk</option>' +
                    chapters.map(ch => `<option value="${ch.Hoofdstuknummer}">${ch.Hoofdstuknummer}</option>`).join('');
            }
        });
    }
    
    // Eind Vers - Hoofdstuk change
    const endHoofdstuk = document.getElementById('timelineEndHoofdstuk');
    if (endHoofdstuk) {
        endHoofdstuk.addEventListener('change', async (e) => {
            const hoofdstuk = e.target.value;
            const boek = document.getElementById('timelineEndBoek').value;
            const versSelect = document.getElementById('timelineEndVers');
            
            versSelect.innerHTML = '<option value="">Vers</option>';
            
            if (!boek || !hoofdstuk) return;
            
            const verses = await window.apiCall(`verses&boek=${encodeURIComponent(boek)}&hoofdstuk=${hoofdstuk}&limit=999`);
            if (verses) {
                versSelect.innerHTML = '<option value="">Vers</option>' +
                    verses.map(v => `<option value="${v.Vers_ID}">${v.Versnummer}</option>`).join('');
            }
        });
    }
    
    console.log('✅ Timeline verse selectors ready');
}

// ============= TIMELINE SAVE FUNCTION =============

async function saveTimeline() {
    const eventId = document.getElementById('timelineEventId')?.value;
    const titel = document.getElementById('timelineTitel')?.value;
    const groupId = document.getElementById('timelineGroup')?.value || null;
    const startDatum = document.getElementById('timelineStartDatum')?.value;
    const endDatum = document.getElementById('timelineEndDatum')?.value || null;
    const kleur = document.getElementById('timelineKleur')?.value || '#3498db';
    const tekstKleur = document.getElementById('timelineTekstKleur')?.value || '#ffffff';
    
    // Get beschrijving from Quill
    const beschrijving = timelineDescQuill ? timelineDescQuill.root.innerHTML : '';
    
    // Get verse IDs from selectors
    const versIdStart = document.getElementById('timelineStartVers')?.value || null;
    const versIdEnd = document.getElementById('timelineEndVers')?.value || null;
    
    if (!titel || !startDatum) {
        window.showNotification('Vul titel en start datum in', true);
        return;
    }
    
    const data = {
        titel,
        beschrijving,
        group_id: groupId,
        start_datum: startDatum,
        end_datum: endDatum,
        kleur,
        tekst_kleur: tekstKleur,
        vers_id_start: versIdStart,
        vers_id_end: versIdEnd
    };
    
    if (eventId) {
        data.event_id = eventId;
    }
    
    const result = await window.apiCall('save_timeline', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    
    if (result && result.success) {
        window.showNotification('Timeline event opgeslagen!');
        clearTimelineForm();
        if (typeof loadTimelineList === 'function') {
            loadTimelineList();
        }
    }
}

// ============= TIMELINE EDIT FUNCTION =============

async function editTimeline(eventId) {
    // Load event data
    const result = await window.apiCall(`get_timeline&id=${eventId}`);
    
    if (!result) {
        window.showNotification('Event niet gevonden', true);
        return;
    }
    
    // Fill basic fields
    document.getElementById('timelineEventId').value = result.Event_ID || '';
    document.getElementById('timelineTitel').value = result.Titel || '';
    document.getElementById('timelineGroup').value = result.Group_ID || '';
    document.getElementById('timelineStartDatum').value = result.Start_Datum || '';
    document.getElementById('timelineEndDatum').value = result.End_Datum || '';
    document.getElementById('timelineKleur').value = result.Kleur || '#3498db';
    document.getElementById('timelineTekstKleur').value = result.Tekst_Kleur || '#ffffff';
    
    // Fill Quill editor
    if (timelineDescQuill) {
        timelineDescQuill.root.innerHTML = result.Beschrijving || '';
    }
    
    // Fill verse selectors (if verse IDs are present)
    if (result.Vers_ID_Start) {
        await fillVerseSelector('start', result.Vers_ID_Start);
    }
    
    if (result.Vers_ID_End) {
        await fillVerseSelector('end', result.Vers_ID_End);
    }
    
    // Scroll to form
    document.querySelector('#section-timeline .card').scrollIntoView({ behavior: 'smooth' });
    
    window.showNotification('Event geladen voor bewerking');
}

/**
 * Fill verse selector with data from verse ID
 */
async function fillVerseSelector(type, versId) {
    // Get verse data
    const verse = await window.apiCall(`verse_detail&vers_id=${versId}`);
    if (!verse) return;
    
    const prefix = type === 'start' ? 'timelineStart' : 'timelineEnd';
    
    // Set boek
    const boekSelect = document.getElementById(prefix + 'Boek');
    if (boekSelect) {
        boekSelect.value = verse.Bijbelboeknaam;
        
        // Trigger change to load chapters
        const chapters = await window.apiCall(`chapters&boek=${encodeURIComponent(verse.Bijbelboeknaam)}`);
        const hoofdstukSelect = document.getElementById(prefix + 'Hoofdstuk');
        
        if (chapters && hoofdstukSelect) {
            hoofdstukSelect.innerHTML = '<option value="">Hoofdstuk</option>' +
                chapters.map(ch => `<option value="${ch.Hoofdstuknummer}">${ch.Hoofdstuknummer}</option>`).join('');
            
            hoofdstukSelect.value = verse.Hoofdstuknummer;
            
            // Load verses
            const verses = await window.apiCall(`verses&boek=${encodeURIComponent(verse.Bijbelboeknaam)}&hoofdstuk=${verse.Hoofdstuknummer}&limit=999`);
            const versSelect = document.getElementById(prefix + 'Vers');
            
            if (verses && versSelect) {
                versSelect.innerHTML = '<option value="">Vers</option>' +
                    verses.map(v => `<option value="${v.Vers_ID}">${v.Versnummer}</option>`).join('');
                
                versSelect.value = versId;
            }
        }
    }
}

// ============= CLEAR FORM FUNCTION =============

function clearTimelineForm() {
    document.getElementById('timelineEventId').value = '';
    document.getElementById('timelineTitel').value = '';
    document.getElementById('timelineGroup').value = '';
    document.getElementById('timelineStartDatum').value = '';
    document.getElementById('timelineEndDatum').value = '';
    document.getElementById('timelineKleur').value = '#cd8989';
    document.getElementById('timelineTekstKleur').value = '#ffffff';
    
    // Clear Quill
    if (timelineDescQuill) {
        timelineDescQuill.setText('');
    }
    
    // Clear verse selectors
    document.getElementById('timelineStartBoek').value = '';
    document.getElementById('timelineStartHoofdstuk').innerHTML = '<option value="">Hoofdstuk</option>';
    document.getElementById('timelineStartVers').innerHTML = '<option value="">Vers</option>';
    
    document.getElementById('timelineEndBoek').value = '';
    document.getElementById('timelineEndHoofdstuk').innerHTML = '<option value="">Hoofdstuk</option>';
    document.getElementById('timelineEndVers').innerHTML = '<option value="">Vers</option>';
    
    window.showNotification('Formulier geleegd');
}

// ============= DELETE TIMELINE FUNCTION =============

async function deleteTimeline(eventId) {
    if (!confirm('Weet je zeker dat je dit event wilt verwijderen?')) return;
    
    const result = await window.apiCall(`delete_timeline&id=${eventId}`);
    
    if (result && result.success) {
        window.showNotification('Timeline event verwijderd');
        if (typeof loadTimelineList === 'function') {
            loadTimelineList();
        }
    }
}

// ============= INITIALIZATION =============

/**
 * Initialize timeline editor when section is shown
 * Call this in your showAdminSection('timeline') function
 */
async function initTimelineSection() {
    initTimelineEditor();
    await loadTimelineVerseBooks();
    setupTimelineVerseSelectors();
    
    if (typeof loadTimelineGroups === 'function') {
        await loadTimelineGroups();
    }
    
    if (typeof loadTimelineList === 'function') {
        await loadTimelineList();
    }
}

// ============= MAKE GLOBAL =============

window.initTimelineEditor = initTimelineEditor;
window.loadTimelineVerseBooks = loadTimelineVerseBooks;
window.setupTimelineVerseSelectors = setupTimelineVerseSelectors;
window.saveTimeline = saveTimeline;
window.editTimeline = editTimeline;
window.clearTimelineForm = clearTimelineForm;
window.deleteTimeline = deleteTimeline;
window.initTimelineSection = initTimelineSection;
window.fillVerseSelector = fillVerseSelector;

console.log('✅ Timeline professional editor functions loaded');