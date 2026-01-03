/**
 * ADMIN.JS - FIXED VERSION
 * Waits for HTML, checks elements exist, skips broken APIs
 */

console.log('Admin.js loaded');

// Global variables
let quill = null;
let timelineDescQuill = null;
let currentEditMode = localStorage.getItem('adminEditMode') || 'single';
let chapterEditors = {};

// ============= MAIN INITIALIZATION =============

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const mode = urlParams.get('mode');
    
    if (mode !== 'admin') {
        console.log('Not in admin mode, skipping admin.js initialization');
        return;
    }
    
    console.log('🎯 Admin mode detected - starting initialization...');
    
    // Step 1: Initialize Quill editors (wait a bit for HTML)
    setTimeout(() => {
        console.log('Step 1: Initializing Quill editors...');
        initQuillEditors();
    }, 200);
    
    // Step 2: Setup event listeners (wait for Quill)
    setTimeout(() => {
        console.log('Step 2: Setting up event listeners...');
        setupEventListeners();
    }, 400);
    
    // Step 3: Load initial data (wait for listeners)
    setTimeout(() => {
        console.log('Step 3: Loading initial data...');
        loadInitialData();
    }, 600);
    
    // Step 4: Restore settings (wait for data to load)
    setTimeout(() => {
        console.log('Step 4: Restoring editor settings...');
        restoreEditorSettings();
    }, 1200);
});

// ============= QUILL INITIALIZATION =============

function initQuillEditors() {
    if (typeof Quill === 'undefined') {
        console.error('❌ Quill not loaded!');
        return;
    }
    
    const Font = Quill.import('formats/font');
    Font.whitelist = ['serif', 'monospace', 'arial', 'times', 'courier', 'georgia', 'verdana', 'tahoma', 'trebuchet'];
    Quill.register(Font, true);
    
    const editorContainer = document.getElementById('editor-container');
    if (editorContainer) {
        quill = new Quill('#editor-container', {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                    [{ 'size': ['small', false, 'large', 'huge'] }],
                    [{ 'font': Font.whitelist }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'script': 'sub'}, { 'script': 'super' }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    [{ 'indent': '-1'}, { 'indent': '+1' }],
                    [{ 'align': [] }],
                    ['link', 'blockquote'],
                    ['clean']
                ]
            }
        });
        console.log('✅ Main Quill editor initialized');
        window.quill = quill;
    } else {
        console.warn('⚠️ editor-container not found');
    }
    
    const timelineDescContainer = document.getElementById('timelineBeschrijvingEditor');
    if (timelineDescContainer) {
        const timelineDescQuill = new Quill('#timelineBeschrijvingEditor', {
            theme: 'snow',
            placeholder: 'Beschrijving van het event...',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline'],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link'],
                    ['clean']
                ]
            }
        });
        console.log('✅ Timeline editor initialized');
        window.timelineDescQuill = timelineDescQuill;
    }
}

// ============= EVENT LISTENERS =============

function setupEventListeners() {
    // Book select
    const adminBookSelect = document.getElementById('adminBookSelect');
    if (adminBookSelect) {
        adminBookSelect.addEventListener('change', async (e) => {
            const book = e.target.value;
            console.log(`📖 Book changed: ${book}`);
            localStorage.setItem('adminBook', book);
            localStorage.removeItem('adminChapter');
            localStorage.removeItem('adminVerse');
            
            if (book) {
                await loadAdminChapters();
            } else {
                const chapterSelect = document.getElementById('adminChapterSelect');
                if (chapterSelect) chapterSelect.innerHTML = '<option value="">Kies hoofdstuk</option>';
            }
        });
        console.log('✅ Book select listener added');
    } else {
        console.warn('⚠️ adminBookSelect not found');
    }
    
    // Chapter select
    const adminChapterSelect = document.getElementById('adminChapterSelect');
    if (adminChapterSelect) {
        adminChapterSelect.addEventListener('change', async (e) => {
            const chapter = e.target.value;
            console.log(`📑 Chapter changed: ${chapter}`);
            localStorage.setItem('adminChapter', chapter);
            localStorage.removeItem('adminVerse');
            
            if (chapter) {
                await loadAdminVerses();
                if (currentEditMode === 'chapter') {
                    await loadChapterForEditing();
                }
            } else {
                const verseSelect = document.getElementById('adminVerseSelect');
                if (verseSelect) verseSelect.innerHTML = '<option value="">Kies vers</option>';
            }
        });
        console.log('✅ Chapter select listener added');
    } else {
        console.warn('⚠️ adminChapterSelect not found');
    }
    
    // Verse select
    const adminVerseSelect = document.getElementById('adminVerseSelect');
    if (adminVerseSelect) {
        adminVerseSelect.addEventListener('change', (e) => {
            const verse = e.target.value;
            console.log(`📝 Verse changed: ${verse}`);
            localStorage.setItem('adminVerse', verse);
            if (verse && currentEditMode === 'single') {
                loadVerse();
            }
        });
        console.log('✅ Verse select listener added');
    } else {
        console.warn('⚠️ adminVerseSelect not found');
    }
    
    // Profile select
    const editorProfileSelect = document.getElementById('editorProfileSelect');
    if (editorProfileSelect) {
        editorProfileSelect.addEventListener('change', (e) => {
            const profile = e.target.value;
            console.log(`👤 Profile changed: ${profile}`);
            localStorage.setItem('adminProfile', profile);
            
            if (currentEditMode === 'chapter') {
                loadChapterForEditing();
            } else if (currentEditMode === 'single') {
                const verse = document.getElementById('adminVerseSelect')?.value;
                if (verse) {
                    loadVerse();
                }
            }
        });
        console.log('✅ Profile select listener added');
    } else {
        console.warn('⚠️ editorProfileSelect not found');
    }
}

// ============= LOAD INITIAL DATA =============

async function loadInitialData() {
    // Load books
    const books = await window.apiCall('books');
    const bookSelect = document.getElementById('adminBookSelect');
    if (bookSelect && books) {
        bookSelect.innerHTML = '<option value="">Kies een boek...</option>';
        books.forEach(book => {
            const option = document.createElement('option');
            option.value = book.Bijbelboeknaam;
            option.textContent = book.Bijbelboeknaam;
            bookSelect.appendChild(option);
        });
        console.log(`✅ Loaded ${books.length} books`);
    }
    
    // Load profiles
    await loadProfiles();
}

// ============= RESTORE SETTINGS =============

async function restoreEditorSettings() {
    console.log('📋 Restoring editor settings...');
    
    const savedBook = localStorage.getItem('adminBook');
    const savedChapter = localStorage.getItem('adminChapter');
    const savedVerse = localStorage.getItem('adminVerse');
    const savedProfile = localStorage.getItem('adminProfile');
    const savedEditMode = localStorage.getItem('adminEditMode');
    
    console.log('Saved values:', { savedBook, savedChapter, savedVerse, savedProfile, savedEditMode });
    
    // Restore edit mode
    const editModeSingle = document.getElementById('editModeSingle');
    const editModeChapter = document.getElementById('editModeChapter');
    
    if (savedEditMode) {
        currentEditMode = savedEditMode;
        
        // Always show/hide divs based on mode, even if buttons don't exist
        if (savedEditMode === 'chapter') {
            document.getElementById('singleVerseEditor')?.classList.add('d-none');
            document.getElementById('chapterEditor')?.classList.remove('d-none');
            document.getElementById('verseSelectContainer')?.classList.add('d-none');
            console.log('✅ Restored to chapter mode (divs)');
            
            // Update radio buttons if they exist
            if (editModeChapter) {
                editModeChapter.checked = true;
                console.log('✅ Updated radio button');
            } else {
                console.warn('⚠️ Edit mode buttons not found, but divs updated');
            }
        } else {
            document.getElementById('singleVerseEditor')?.classList.remove('d-none');
            document.getElementById('chapterEditor')?.classList.add('d-none');
            document.getElementById('verseSelectContainer')?.classList.remove('d-none');
            console.log('✅ Restored to single mode (divs)');
            
            // Update radio buttons if they exist
            if (editModeSingle) {
                editModeSingle.checked = true;
                console.log('✅ Updated radio button');
            } else {
                console.warn('⚠️ Edit mode buttons not found, but divs updated');
            }
        }
    }
    
    // Restore profile
    const profileSelect = document.getElementById('editorProfileSelect');
    if (profileSelect && savedProfile) {
        profileSelect.value = savedProfile;
        console.log(`✅ Restored profile: ${savedProfile}`);
    }
    
    // Restore book
    const bookSelect = document.getElementById('adminBookSelect');
    if (bookSelect && savedBook) {
        bookSelect.value = savedBook;
        console.log(`✅ Restored book: ${savedBook}`);
        
        // Load chapters
        await loadAdminChapters();
        
        // Wait then restore chapter
        if (savedChapter) {
            setTimeout(async () => {
                const chapterSelect = document.getElementById('adminChapterSelect');
                if (chapterSelect) {
                    chapterSelect.value = savedChapter;
                    console.log(`✅ Restored chapter: ${savedChapter}`);
                    
                    // Load verses
                    await loadAdminVerses();
                    
                    // Restore verse or load chapter
                    if (currentEditMode === 'chapter') {
                        setTimeout(() => {
                            loadChapterForEditing();
                        }, 300);
                    } else if (savedVerse) {
                        setTimeout(() => {
                            const verseSelect = document.getElementById('adminVerseSelect');
                            if (verseSelect) {
                                verseSelect.value = savedVerse;
                                console.log(`✅ Restored verse: ${savedVerse}`);
                                loadVerse();
                            }
                        }, 300);
                    }
                }
            }, 300);
        }
    }
}

// ============= SET EDIT MODE =============

function setEditMode(mode) {
    console.log(`🔄 Switching to ${mode} mode`);
    currentEditMode = mode;
    localStorage.setItem('adminEditMode', mode);
    
    const singleEditor = document.getElementById('singleVerseEditor');
    const chapterEditor = document.getElementById('chapterEditor');
    const verseSelectContainer = document.getElementById('verseSelectContainer');
    
    if (mode === 'single') {
        singleEditor?.classList.remove('d-none');
        chapterEditor?.classList.add('d-none');
        verseSelectContainer?.classList.remove('d-none');
    } else {
        singleEditor?.classList.add('d-none');
        chapterEditor?.classList.remove('d-none');
        verseSelectContainer?.classList.add('d-none');
        
        const boek = document.getElementById('adminBookSelect')?.value;
        const hoofdstuk = document.getElementById('adminChapterSelect')?.value;
        if (boek && hoofdstuk) {
            loadChapterForEditing();
        }
    }
}
window.setEditMode = setEditMode;

// ============= LOAD CHAPTERS =============

async function loadAdminChapters() {
    const boek = document.getElementById('adminBookSelect')?.value;
    const chapterSelect = document.getElementById('adminChapterSelect');
    
    if (!chapterSelect) return;
    
    if (!boek) {
        chapterSelect.innerHTML = '<option value="">Kies hoofdstuk</option>';
        return;
    }
    
    console.log(`📑 Loading chapters for: ${boek}`);
    const chapters = await window.apiCall(`chapters&boek=${encodeURIComponent(boek)}`);
    
    chapterSelect.innerHTML = '<option value="">Kies hoofdstuk</option>';
    
    if (chapters) {
        chapters.forEach(ch => {
            const option = document.createElement('option');
            option.value = ch.Hoofdstuknummer;
            option.textContent = ch.Hoofdstuknummer;
            chapterSelect.appendChild(option);
        });
        console.log(`✅ Loaded ${chapters.length} chapters`);
    }
}
window.loadAdminChapters = loadAdminChapters;

// ============= LOAD VERSES =============

async function loadAdminVerses() {
    const boek = document.getElementById('adminBookSelect')?.value;
    const hoofdstuk = document.getElementById('adminChapterSelect')?.value;
    const verseSelect = document.getElementById('adminVerseSelect');
    
    if (!verseSelect) return;
    
    if (!boek || !hoofdstuk) {
        verseSelect.innerHTML = '<option value="">Kies vers</option>';
        return;
    }
    
    console.log(`📝 Loading verses for: ${boek} ${hoofdstuk}`);
    const verses = await window.apiCall(`verses&boek=${encodeURIComponent(boek)}&hoofdstuk=${hoofdstuk}&limit=999`);
    
    verseSelect.innerHTML = '<option value="">Kies vers</option>';
    
    if (verses) {
        verses.forEach(v => {
            const option = document.createElement('option');
            option.value = v.Vers_ID;
            option.textContent = v.Versnummer;
            verseSelect.appendChild(option);
        });
        console.log(`✅ Loaded ${verses.length} verses`);
    }
}
window.loadAdminVerses = loadAdminVerses;

// ============= LOAD SINGLE VERSE =============

async function loadVerse() {
    if (currentEditMode !== 'single') {
        console.log('Not in single mode, skipping');
        return;
    }
    
    const versId = document.getElementById('adminVerseSelect')?.value;
    const profielId = document.getElementById('editorProfileSelect')?.value;
    
    if (!versId) {
        console.log('No verse selected');
        return;
    }
    
    if (!quill) {
        console.error('❌ Quill not initialized');
        return;
    }
    
    console.log(`📖 Loading verse ${versId} (profile: ${profielId || 'none'})`);
    
    const params = `vers_id=${versId}` + (profielId ? `&profiel_id=${profielId}` : '');
    const verse = await window.apiCall('verse_detail&' + params);
    
    if (!verse) {
        console.error('❌ Verse not found');
        return;
    }
    
    // Set original text
    const originalTextEl = document.getElementById('originalText');
    if (originalTextEl) {
        originalTextEl.textContent = verse.Tekst;
        console.log('✅ Set original text');
    }
    
    // Load into editor
    quill.setText('');
    
    if (verse.Opgemaakte_Tekst && verse.Opgemaakte_Tekst.trim()) {
        quill.clipboard.dangerouslyPasteHTML(verse.Opgemaakte_Tekst);
        console.log('✅ Loaded formatted text');
    } else {
        quill.setText(verse.Tekst);
        console.log('✅ Loaded plain text');
    }
}
window.loadVerse = loadVerse;

// ============= LOAD CHAPTER =============


// ============================================================================
// COMPLETE loadChapterForEditing() FUNCTIE MET RESET KNOPPEN
// Vervang de hele functie in admin.js
// ============================================================================

async function loadChapterForEditing() {
    const boek = document.getElementById('adminBookSelect').value;
    const hoofdstuk = document.getElementById('adminChapterSelect').value;
    const profielId = document.getElementById('editorProfileSelect').value;
    
    const container = document.getElementById('chapterVersesContainer');
    if (!container) {
        console.log('❌ chapterVersesContainer not found');
        return;
    }
    
    if (!boek || !hoofdstuk) {
        container.innerHTML = '<div class="text-muted text-center py-4">Selecteer een boek en hoofdstuk om te beginnen</div>';
        return;
    }
    
    console.log(`📖 Loading chapter: ${boek} ${hoofdstuk}`);
    
    // Show loading
    container.innerHTML = '<div class="text-center py-4"><div class="spinner-border spinner-border-sm" role="status"></div> Laden...</div>';
    
    // Destroy existing Quill instances
    Object.values(chapterEditors).forEach(editor => {
        if (editor && editor.container) {
            editor.container.innerHTML = '';
        }
    });
    chapterEditors = {};
    
    // Fetch verses
    const params = profielId ? 
        `verses&boek=${encodeURIComponent(boek)}&hoofdstuk=${hoofdstuk}&profiel_id=${profielId}&limit=999` :
        `verses&boek=${encodeURIComponent(boek)}&hoofdstuk=${hoofdstuk}&limit=999`;
        
    const verses = await window.apiCall(params);
    chapterVersesData = verses || [];
    
    if (!verses || verses.length === 0) {
        container.innerHTML = '<div class="text-muted text-center py-4">Geen verzen gevonden</div>';
        return;
    }
    
    console.log(`✅ Loaded ${verses.length} verses`);
    
    // Update verse count
    document.getElementById('chapterVerseCount').textContent = verses.length;
    
    // Build HTML
    container.innerHTML = '';
    
    // ✅ FIX: Font registratie EENMALIG, VOOR DE LOOP!
    const Font = Quill.import('formats/font');
    Font.whitelist = ['serif', 'monospace', 'arial', 'times', 'courier', 'georgia', 'verdana', 'tahoma', 'trebuchet'];
    Quill.register(Font, true);
    
    // ✅ Nu door alle verzen loopen
    for (const verse of verses) {
        const hasFormatting = verse.Opgemaakte_Tekst && verse.Opgemaakte_Tekst.trim() !== '';
        
const verseItem = document.createElement('div');
verseItem.className = 'chapter-verse-item' + (hasFormatting ? ' has-formatting' : '');
verseItem.dataset.versId = verse.Vers_ID;
        
        // ✅ HTML met reset knop per vers
        verseItem.innerHTML = `
            <div class="chapter-verse-header">
                <span class="chapter-verse-number">${verse.Versnummer}</span>
                <span class="chapter-verse-original" title="${verse.Tekst}">${verse.Tekst.substring(0, 80)}${verse.Tekst.length > 80 ? '...' : ''}</span>
                <span class="chapter-verse-status badge ${hasFormatting ? 'bg-success' : 'bg-secondary'}">${hasFormatting ? 'Bewerkt' : 'Origineel'}</span>
                <button class="btn btn-sm btn-outline-secondary" onclick="resetChapterVerse(${verse.Vers_ID})" title="Reset naar origineel">
                    <i class="bi bi-arrow-counterclockwise"></i>
                </button>
            </div>
            <div class="chapter-verse-editor">
                <div id="chapter-editor-${verse.Vers_ID}"></div>
            </div>
        `;
        container.appendChild(verseItem);
        
        // Initialize Quill - ZONDER opnieuw Font te registreren!
        const editorId = `chapter-editor-${verse.Vers_ID}`;
        const quillInstance = new Quill(`#${editorId}`, {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'font': Font.whitelist }],
                    [{ 'size': ['small', false, 'large', 'huge'] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'script': 'sub'}, { 'script': 'super' }],
                    ['link', 'blockquote'],  
                    ['clean']
                ]
            }
        });
        
        // Set content
        if (hasFormatting) {
            quillInstance.clipboard.dangerouslyPasteHTML(verse.Opgemaakte_Tekst);
        } else {
            quillInstance.setText(verse.Tekst);
        }
        
        // Store original for change detection
        quillInstance.originalHtml = quillInstance.root.innerHTML;
        
        // Track changes
        quillInstance.on('text-change', () => {
            const currentHtml = quillInstance.root.innerHTML;
            const isModified = currentHtml !== quillInstance.originalHtml;
            verseItem.classList.toggle('modified', isModified);
        });
        
        chapterEditors[verse.Vers_ID] = quillInstance;
    }
    
    console.log(`✅ Created ${Object.keys(chapterEditors).length} Quill editors`);
}

window.loadChapterForEditing = loadChapterForEditing;

// ============= SAVE FUNCTIONS =============

async function saveFormatting() {
    const versId = document.getElementById('adminVerseSelect')?.value;
    const profielId = document.getElementById('editorProfileSelect')?.value;
    
    if (!versId || !profielId) {
        window.showNotification('Selecteer een vers en profiel', true);
        return;
    }
    
    if (!quill) {
        window.showNotification('Editor niet geïnitialiseerd', true);
        return;
    }
    
    const result = await window.apiCall('save_formatting', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ 
            vers_id: versId, 
            profiel_id: profielId, 
            tekst: quill.root.innerHTML 
        })
    });
    
    if (result?.success) {
        window.showNotification('Opmaak opgeslagen!');
    }
}
window.saveFormatting = saveFormatting;

async function saveAllChapterFormatting() {
    const profielId = document.getElementById('editorProfileSelect')?.value;
    
    if (!profielId) {
        window.showNotification('Selecteer eerst een profiel', true);
        return;
    }
    
    let savedCount = 0;
    
    for (const [versId, editor] of Object.entries(chapterEditors)) {
        const modified = editor.root.innerHTML !== editor.originalHtml;
        
        if (modified) {
            const result = await window.apiCall('save_formatting', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    vers_id: versId, 
                    profiel_id: profielId, 
                    tekst: editor.root.innerHTML 
                })
            });
            
            if (result?.success) {
                savedCount++;
                editor.originalHtml = editor.root.innerHTML;
                
                const item = document.querySelector(`[data-vers-id="${versId}"]`);
                if (item) {
                    item.classList.remove('modified');
                    item.classList.add('has-formatting');
                    const badge = item.querySelector('.chapter-verse-status');
                    if (badge) {
                        badge.className = 'chapter-verse-status badge bg-success';
                        badge.textContent = 'Bewerkt';
                    }
                }
            }
        }
    }
    
    if (savedCount > 0) {
        window.showNotification(`${savedCount} vers(en) opgeslagen!`);
    } else {
        window.showNotification('Geen wijzigingen om op te slaan');
    }
}
window.saveAllChapterFormatting = saveAllChapterFormatting;

function resetFormatting() {
    const originalText = document.getElementById('originalText')?.textContent;
    if (originalText && quill) {
        quill.setText(originalText);
        window.showNotification('Tekst gereset');
    }
}
window.resetFormatting = resetFormatting;

async function deleteFormatting() {
    const versId = document.getElementById('adminVerseSelect')?.value;
    const profielId = document.getElementById('editorProfileSelect')?.value;
    
    if (!versId || !profielId) {
        window.showNotification('Selecteer een vers en profiel', true);
        return;
    }
    
    if (!confirm('Weet je zeker dat je deze opmaak wilt verwijderen?')) return;
    
    const result = await window.apiCall(`delete_formatting&vers_id=${versId}&profiel_id=${profielId}`);
    if (result?.success) {
        window.showNotification('Opmaak verwijderd');
        if (quill) quill.setContents([]);
    }
}
window.deleteFormatting = deleteFormatting;

// ============= PROFILES =============

async function loadProfiles() {
    console.log('📋 Loading profiles...');
    const profiles = await window.apiCall('profiles');
    
    if (!profiles) {
        console.error('❌ No profiles received from API');
        return;
    }
    
    // 1. Fill dropdown (for editor)
    const select = document.getElementById('editorProfileSelect');
    if (select) {
        select.innerHTML = '<option value="">Kies profiel...</option>';
        profiles.forEach(p => {
            const option = document.createElement('option');
            option.value = p.Profiel_ID;
            option.textContent = p.Profiel_Naam;
            select.appendChild(option);
        });
        console.log(`✅ Filled dropdown with ${profiles.length} profiles`);
    }
    
    // 2. Fill profiles list (for profiles section)
    const list = document.getElementById('profilesList');
    if (list) {
        if (profiles.length === 0) {
            list.innerHTML = '<p class="text-muted fst-italic">Nog geen profielen aangemaakt</p>';
            return;
        }
        
        const table = document.createElement('table');
        table.className = 'table table-hover';
        table.innerHTML = `
            <thead>
                <tr>
                    <th>Naam</th>
                    <th>Beschrijving</th>
                    <th>Aangemaakt</th>
                    <th style="width: 100px;">Acties</th>
                </tr>
            </thead>
            <tbody id="profilesTableBody"></tbody>
        `;
        
        list.innerHTML = '';
        list.appendChild(table);
        
        const tbody = document.getElementById('profilesTableBody');
        
        profiles.forEach(profile => {
            const tr = document.createElement('tr');
            
            const createdDate = profile.Aangemaakt ? 
                new Date(profile.Aangemaakt).toLocaleDateString('nl-NL') : 
                '-';
            
            tr.innerHTML = `
                <td><strong>${profile.Profiel_Naam}</strong></td>
                <td>${profile.Beschrijving || '<span class="text-muted">-</span>'}</td>
                <td><small class="text-muted">${createdDate}</small></td>
                <td>
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" onclick="editProfile(${profile.Profiel_ID})" title="Bewerken">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="deleteProfile(${profile.Profiel_ID})" title="Verwijderen">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            `;
            
            tbody.appendChild(tr);
        });
        
        console.log(`✅ Filled list with ${profiles.length} profiles`);
    }
}

// ════════════════════════════════════════════════════════════════════════════
// PROFIEL EDIT & SAVE FUNCTIES
// Voeg toe aan admin.js (na loadProfiles functie)
// ════════════════════════════════════════════════════════════════════════════

// ─────────────────────────────────────────────────────────────────────────────
// EDIT PROFILE - Vul form met bestaand profiel
// ─────────────────────────────────────────────────────────────────────────────

async function editProfile(id) {
    console.log('✏️ Editing profile:', id);
    
    // Get profile data
    const profiles = await window.apiCall('profiles');
    if (!profiles) {
        window.showNotification('Fout bij ophalen profiel', true);
        return;
    }
    
    const profile = profiles.find(p => p.Profiel_ID == id);
    if (!profile) {
        window.showNotification('Profiel niet gevonden', true);
        return;
    }
    
    // ⭐ SET EDIT MODE
    window.editingProfileId = id;
    console.log('🔖 Edit mode ON - ID:', id);
    
    // Fill form with existing data
    document.getElementById('newProfileName').value = profile.Profiel_Naam;
    document.getElementById('newProfileDesc').value = profile.Beschrijving || '';
    
    // Change button appearance for edit mode
    const saveBtn = document.querySelector('button[onclick*="saveProfile"], button[onclick*="createProfile"]');
    if (saveBtn) {
        saveBtn.dataset.originalText = saveBtn.innerHTML;
        saveBtn.innerHTML = '<i class="bi bi-save"></i> Bijwerken';
        saveBtn.classList.remove('btn-primary');
        saveBtn.classList.add('btn-success');
    }
    
    // Scroll to form and focus
    const nameInput = document.getElementById('newProfileName');
    nameInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
    
    setTimeout(() => {
        nameInput.focus();
        nameInput.select(); // Select text for easy editing
    }, 300);
    
    // Show notification
    window.showNotification(`Bewerk "${profile.Profiel_Naam}" en klik Bijwerken`);
}
window.editProfile = editProfile;


// ─────────────────────────────────────────────────────────────────────────────
// SAVE PROFILE - UPDATE bestaand of CREATE nieuw profiel
// ─────────────────────────────────────────────────────────────────────────────

async function saveProfile() {
    console.log('💾 Save profile called...');
    
    const naam = document.getElementById('newProfileName')?.value;
    const beschrijving = document.getElementById('newProfileDesc')?.value;
    
    // Validate
    if (!naam || naam.trim() === '') {
        window.showNotification('Vul een naam in', true);
        return;
    }
    
    // Check if we're in edit mode
    const editingId = window.editingProfileId;
    
    if (editingId) {
        // ═══════════════════════════════════════════════════════════════════════
        // UPDATE MODE - Bewerk bestaand profiel
        // ═══════════════════════════════════════════════════════════════════════
        console.log('📝 UPDATE mode - Profile ID:', editingId);
        
        try {
            const result = await window.apiCall('update_profile', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    id: editingId,  // Backend accepteert nu 'id' OF 'profiel_id'
                    naam: naam.trim(), 
                    beschrijving: beschrijving?.trim() || '' 
                })
            });
            
            console.log('📥 Update result:', result);
            
            if (result?.success) {
                console.log('✅ Profile updated successfully!');
                window.showNotification('Profiel bijgewerkt!');
                
                // Clear form
                document.getElementById('newProfileName').value = '';
                document.getElementById('newProfileDesc').value = '';
                
                // Clear edit mode
                window.editingProfileId = null;
                console.log('🔖 Edit mode OFF');
                
                // Reset button to original state
                const saveBtn = document.querySelector('button[onclick*="saveProfile"], button[onclick*="createProfile"]');
                if (saveBtn && saveBtn.dataset.originalText) {
                    saveBtn.innerHTML = saveBtn.dataset.originalText;
                    saveBtn.classList.remove('btn-success');
                    saveBtn.classList.add('btn-primary');
                }
                
                // Reload profiles list
                await loadProfiles();
                
            } else {
                // Show error from backend
                const errorMsg = result?.error || 'Onbekende fout bij bijwerken profiel';
                console.error('❌ Update failed:', errorMsg);
                window.showNotification(errorMsg, true);
            }
            
        } catch (error) {
            console.error('❌ Exception during update:', error);
            window.showNotification('Fout bij bijwerken profiel: ' + error.message, true);
        }
        
    } else {
        // ═══════════════════════════════════════════════════════════════════════
        // CREATE MODE - Maak nieuw profiel
        // ═══════════════════════════════════════════════════════════════════════
        console.log('➕ CREATE mode - New profile');
        
        try {
            const result = await window.apiCall('create_profile', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    naam: naam.trim(), 
                    beschrijving: beschrijving?.trim() || '' 
                })
            });
            
            console.log('📥 Create result:', result);
            
            if (result?.success) {
                console.log('✅ Profile created successfully!');
                window.showNotification('Profiel aangemaakt!');
                
                // Clear form
                document.getElementById('newProfileName').value = '';
                document.getElementById('newProfileDesc').value = '';
                
                // Reload profiles list
                await loadProfiles();
                
            } else {
                // Show error from backend
                const errorMsg = result?.error || 'Onbekende fout bij aanmaken profiel';
                console.error('❌ Create failed:', errorMsg);
                window.showNotification(errorMsg, true);
            }
            
        } catch (error) {
            console.error('❌ Exception during create:', error);
            window.showNotification('Fout bij aanmaken profiel: ' + error.message, true);
        }
    }
}
window.saveProfile = saveProfile;


// ─────────────────────────────────────────────────────────────────────────────
// CANCEL EDIT - Annuleer bewerking (optional helper)
// ─────────────────────────────────────────────────────────────────────────────

function cancelEdit() {
    console.log('❌ Cancel edit');
    
    // Clear form
    document.getElementById('newProfileName').value = '';
    document.getElementById('newProfileDesc').value = '';
    
    // Clear edit mode
    window.editingProfileId = null;
    console.log('🔖 Edit mode OFF');
    
    // Reset button
    const saveBtn = document.querySelector('button[onclick*="saveProfile"], button[onclick*="createProfile"]');
    if (saveBtn && saveBtn.dataset.originalText) {
        saveBtn.innerHTML = saveBtn.dataset.originalText;
        saveBtn.classList.remove('btn-success');
        saveBtn.classList.add('btn-primary');
    }
    
    window.showNotification('Bewerken geannuleerd');
}
window.cancelEdit = cancelEdit;


// ════════════════════════════════════════════════════════════════════════════
// USAGE IN HTML:
// ════════════════════════════════════════════════════════════════════════════
// 
// Save button:
// <button onclick="saveProfile()" class="btn btn-primary">
//     <i class="bi bi-plus"></i> Aanmaken
// </button>
// 
// Edit button in table (per profile row):
// <button onclick="editProfile(${profile.Profiel_ID})" class="btn btn-outline-primary btn-sm">
//     <i class="bi bi-pencil"></i>
// </button>
// 
// Optional cancel button:
// <button onclick="cancelEdit()" class="btn btn-secondary">Annuleren</button>
// 
// ════════════════════════════════════════════════════════════════════════════





async function createProfile() {
    const naam = document.getElementById('newProfileName')?.value;
    const beschrijving = document.getElementById('newProfileDesc')?.value;
    
    if (!naam) {
        window.showNotification('Vul een naam in', true);
        return;
    }
    
    const result = await window.apiCall('create_profile', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ naam, beschrijving })
    });
    
    if (result?.success) {
        window.showNotification('Profiel aangemaakt!');
        document.getElementById('newProfileName').value = '';
        document.getElementById('newProfileDesc').value = '';
        await loadProfiles();
    }
}
window.createProfile = createProfile;

async function editProfile(id) {
    console.log('✏️ Editing profile:', id);
    
    // Get profile data
    const profiles = await window.apiCall('profiles');
    if (!profiles) {
        window.showNotification('Fout bij ophalen profiel', true);
        return;
    }
    
    const profile = profiles.find(p => p.Profiel_ID == id);
    if (!profile) {
        window.showNotification('Profiel niet gevonden', true);
        return;
    }
    
    // Fill form with existing data
    document.getElementById('newProfileName').value = profile.Profiel_Naam;
    document.getElementById('newProfileDesc').value = profile.Beschrijving || '';
    
    // Scroll to form
    document.getElementById('newProfileName').scrollIntoView({ behavior: 'smooth', block: 'center' });
    document.getElementById('newProfileName').focus();
    
    // Show notification
    window.showNotification(`Bewerk "${profile.Profiel_Naam}" en klik opnieuw op Aanmaken`, false);
    
    // Note: This is a simple edit - user edits and clicks create again
    // For a more advanced edit, you'd need an update_profile API endpoint
}
window.editProfile = editProfile;

async function deleteProfile(id) {
    if (!confirm('Weet je zeker dat je dit profiel wilt verwijderen?')) return;
    
    const result = await window.apiCall(`delete_profile&id=${id}`);
    if (result?.success) {
        window.showNotification('Profiel verwijderd');
        await loadProfiles();
    }
}
window.deleteProfile = deleteProfile;

// ============================================================================
// VOEG DEZE FUNCTIE TOE AAN ADMIN.JS (na saveAllChapterFormatting)
// ============================================================================

/**
 * Reset een enkel vers in chapter editor naar origineel
 */
/**
 * Reset een enkel vers in chapter editor naar origineel
 * INCLUSIEF verwijderen uit database!
 */
async function resetChapterVerse(versId) {
    const editor = chapterEditors[versId];
    if (!editor) {
        console.log('Editor not found for vers', versId);
        return;
    }
    
    // Vind de originele tekst in chapterVersesData
    const verseData = chapterVersesData.find(v => v.Vers_ID == versId);
    if (!verseData) {
        console.log('Verse data not found for', versId);
        return;
    }
    
    // Check of er opmaak in database staat
    const hadFormatting = verseData.Opgemaakte_Tekst && verseData.Opgemaakte_Tekst.trim() !== '';
    
    if (hadFormatting) {
        // ✅ NIEUW: Verwijder opmaak uit database
        const profielId = document.getElementById('editorProfileSelect').value;
        if (profielId) {
            const result = await window.apiCall(`delete_formatting&vers_id=${versId}&profiel_id=${profielId}`);
            if (result && result.success) {
                console.log(`✅ Opmaak verwijderd uit database voor vers ${versId}`);
                
                // Update lokale data
                verseData.Opgemaakte_Tekst = null;
            } else {
                console.error('❌ Fout bij verwijderen opmaak:', result);
                showNotification('Fout bij verwijderen opmaak', true);
                return;
            }
        }
    }
    
    // Reset naar originele tekst (ZONDER opmaak)
    editor.setText(verseData.Tekst);
    
    // Update originalHtml zodat change detection weer werkt
    editor.originalHtml = editor.root.innerHTML;
    
    // Update UI
    const verseItem = document.querySelector(`.chapter-verse-item[data-vers-id="${versId}"]`);
    if (verseItem) {
        // Verwijder modified class
        verseItem.classList.remove('modified');
        
        // Verwijder has-formatting class
        verseItem.classList.remove('has-formatting');
        
        // Update badge naar "Origineel" (grijs)
        const badge = verseItem.querySelector('.chapter-verse-status');
        if (badge) {
            badge.className = 'chapter-verse-status badge bg-secondary';
            badge.textContent = 'Origineel';
        }
    }
    
    showNotification('Vers gereset naar origineel en opmaak verwijderd');
}

/**
 * Reset ALLE verzen in chapter editor naar origineel
 * INCLUSIEF verwijderen uit database!
 */
async function resetAllChapterVerses() {
    if (!confirm('Weet je zeker dat je alle wijzigingen wilt resetten?\n\nDit verwijdert ook alle opmaak uit de database!')) {
        return;
    }
    
    let resetCount = 0;
    const profielId = document.getElementById('editorProfileSelect').value;
    
    if (!profielId) {
        showNotification('Selecteer eerst een profiel', true);
        return;
    }
    
    // Loop door alle editors
    for (const [versId, editor] of Object.entries(chapterEditors)) {
        const verseData = chapterVersesData.find(v => v.Vers_ID == versId);
        if (!verseData) continue;
        
        const currentHtml = editor.root.innerHTML;
        const isModified = currentHtml !== editor.originalHtml;
        const hadFormatting = verseData.Opgemaakte_Tekst && verseData.Opgemaakte_Tekst.trim() !== '';
        
        // Reset als modified OF als er opmaak in database staat
        if (isModified || hadFormatting) {
            // Verwijder uit database als er opmaak was
            if (hadFormatting) {
                const result = await window.apiCall(`delete_formatting&vers_id=${versId}&profiel_id=${profielId}`);
                if (result && result.success) {
                    verseData.Opgemaakte_Tekst = null;
                    console.log(`✅ Opmaak verwijderd voor vers ${versId}`);
                }
            }
            
            // Reset editor
            editor.setText(verseData.Tekst);
            editor.originalHtml = editor.root.innerHTML;
            
            // Update UI
            const verseItem = document.querySelector(`.chapter-verse-item[data-vers-id="${versId}"]`);
            if (verseItem) {
                verseItem.classList.remove('modified');
                verseItem.classList.remove('has-formatting');
                
                const badge = verseItem.querySelector('.chapter-verse-status');
                if (badge) {
                    badge.className = 'chapter-verse-status badge bg-secondary';
                    badge.textContent = 'Origineel';
                }
            }
            
            resetCount++;
        }
    }
    
    if (resetCount > 0) {
        showNotification(`${resetCount} vers(en) gereset en opmaak verwijderd`);
    } else {
        showNotification('Geen wijzigingen om te resetten');
    }
}

// Maak functies globally beschikbaar
window.resetChapterVerse = resetChapterVerse;
window.resetAllChapterVerses = resetAllChapterVerses;


/**
 * Reset ALLE verzen in chapter editor naar origineel
 */
/**
 * Reset een enkel vers in chapter editor naar origineel
 * INCLUSIEF verwijderen uit database!
 */
async function resetChapterVerse(versId) {
    const editor = chapterEditors[versId];
    if (!editor) {
        console.log('Editor not found for vers', versId);
        return;
    }
    
    // Vind de originele tekst in chapterVersesData
    const verseData = chapterVersesData.find(v => v.Vers_ID == versId);
    if (!verseData) {
        console.log('Verse data not found for', versId);
        return;
    }
    
    // Check of er opmaak in database staat
    const hadFormatting = verseData.Opgemaakte_Tekst && verseData.Opgemaakte_Tekst.trim() !== '';
    
    if (hadFormatting) {
        // ✅ NIEUW: Verwijder opmaak uit database
        const profielId = document.getElementById('editorProfileSelect').value;
        if (profielId) {
            const result = await window.apiCall(`delete_formatting&vers_id=${versId}&profiel_id=${profielId}`);
            if (result && result.success) {
                console.log(`✅ Opmaak verwijderd uit database voor vers ${versId}`);
                
                // Update lokale data
                verseData.Opgemaakte_Tekst = null;
            } else {
                console.error('❌ Fout bij verwijderen opmaak:', result);
                showNotification('Fout bij verwijderen opmaak', true);
                return;
            }
        }
    }
    
    // Reset naar originele tekst (ZONDER opmaak)
    editor.setText(verseData.Tekst);
    
    // Update originalHtml zodat change detection weer werkt
    editor.originalHtml = editor.root.innerHTML;
    
    // Update UI
    const verseItem = document.querySelector(`.chapter-verse-item[data-vers-id="${versId}"]`);
    if (verseItem) {
        // Verwijder modified class
        verseItem.classList.remove('modified');
        
        // Verwijder has-formatting class
        verseItem.classList.remove('has-formatting');
        
        // Update badge naar "Origineel" (grijs)
        const badge = verseItem.querySelector('.chapter-verse-status');
        if (badge) {
            badge.className = 'chapter-verse-status badge bg-secondary';
            badge.textContent = 'Origineel';
        }
    }
    
    showNotification('Vers gereset naar origineel en opmaak verwijderd');
}

/**
 * Reset ALLE verzen in chapter editor naar origineel
 * INCLUSIEF verwijderen uit database!
 */
async function resetAllChapterVerses() {
    if (!confirm('Weet je zeker dat je alle wijzigingen wilt resetten?\n\nDit verwijdert ook alle opmaak uit de database!')) {
        return;
    }
    
    let resetCount = 0;
    const profielId = document.getElementById('editorProfileSelect').value;
    
    if (!profielId) {
        showNotification('Selecteer eerst een profiel', true);
        return;
    }
    
    // Loop door alle editors
    for (const [versId, editor] of Object.entries(chapterEditors)) {
        const verseData = chapterVersesData.find(v => v.Vers_ID == versId);
        if (!verseData) continue;
        
        const currentHtml = editor.root.innerHTML;
        const isModified = currentHtml !== editor.originalHtml;
        const hadFormatting = verseData.Opgemaakte_Tekst && verseData.Opgemaakte_Tekst.trim() !== '';
        
        // Reset als modified OF als er opmaak in database staat
        if (isModified || hadFormatting) {
            // Verwijder uit database als er opmaak was
            if (hadFormatting) {
                const result = await window.apiCall(`delete_formatting&vers_id=${versId}&profiel_id=${profielId}`);
                if (result && result.success) {
                    verseData.Opgemaakte_Tekst = null;
                    console.log(`✅ Opmaak verwijderd voor vers ${versId}`);
                }
            }
            
            // Reset editor
            editor.setText(verseData.Tekst);
            editor.originalHtml = editor.root.innerHTML;
            
            // Update UI
            const verseItem = document.querySelector(`.chapter-verse-item[data-vers-id="${versId}"]`);
            if (verseItem) {
                verseItem.classList.remove('modified');
                verseItem.classList.remove('has-formatting');
                
                const badge = verseItem.querySelector('.chapter-verse-status');
                if (badge) {
                    badge.className = 'chapter-verse-status badge bg-secondary';
                    badge.textContent = 'Origineel';
                }
            }
            
            resetCount++;
        }
    }
    
    if (resetCount > 0) {
        showNotification(`${resetCount} vers(en) gereset en opmaak verwijderd`);
    } else {
        showNotification('Geen wijzigingen om te resetten');
    }
}

// Maak functies globally beschikbaar
window.resetChapterVerse = resetChapterVerse;
window.resetAllChapterVerses = resetAllChapterVerses;


// Maak functies globally beschikbaar
window.resetChapterVerse = resetChapterVerse;
window.resetAllChapterVerses = resetAllChapterVerses;

// ============= ADMIN SECTION SWITCHER =============

/**
 * Switch tussen admin sections (sidebar navigation)
 */
/**
 * ADMIN.JS - UPDATED showAdminSection FUNCTION
 * Replace your existing showAdminSection with this complete version
 */

function showAdminSection(section) {
    console.log('📂 Switching to section:', section);
    
    // Hide all sections
    document.querySelectorAll('.admin-section').forEach(s => {
        s.classList.add('d-none');
        s.classList.remove('d-block');
    });
    
    // Show selected section
    const selectedSection = document.getElementById('section-' + section);
    if (selectedSection) {
        selectedSection.classList.remove('d-none');
        selectedSection.classList.add('d-block');
        console.log('✅ Section shown:', section);
    } else {
        console.error('❌ Section not found:', 'section-' + section);
    }
    
    // Update sidebar active state
    document.querySelectorAll('.admin-sidebar .list-group-item').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Add active to clicked button
    if (event && event.target) {
        event.target.classList.add('active');
    }
    
    // Load data when section is opened (if functions exist)
    switch(section) {
        case 'profiles':
            if (typeof loadProfiles === 'function') {
                loadProfiles();
            }
            break;
            
        case 'timeline':
            // ✨ UPDATED: Initialize complete timeline editor
            if (typeof initTimelineSection === 'function') {
                initTimelineSection();
            } else {
                // Fallback to old method if new function not found
                console.warn('⚠️ initTimelineSection not found - using fallback');
                if (typeof loadTimelineList === 'function') {
                    loadTimelineList();
                }
                if (typeof loadTimelineGroups === 'function') {
                    loadTimelineGroups();
                }
            }
            break;
            
        case 'locations':
            if (typeof loadLocationList === 'function') {
                loadLocationList();
            }
            break;
            
        case 'images':
            // 🔴 UPDATED: Initialize complete image section
            if (typeof initImageSection === 'function') {
                initImageSection();
            } else {
                // Fallback to old method
                console.warn('⚠️ initImageSection not found - using fallback');
                if (typeof loadImageList === 'function') {
                    loadImageList();
                }
            }
            break;
            
        case 'notes':
            if (typeof initNotesEditor === 'function') {
                initNotesEditor();
            }
            break;
    }
}

// Make global
window.showAdminSection = showAdminSection;

console.log('✅ showAdminSection updated');

// Maak showAdminSection globally beschikbaar
window.showAdminSection = showAdminSection;

console.log('✅ Admin.js ready');
/**
 * DATA LOADING FUNCTIES - Toevoegen aan admin.js
 * Plaats dit NA de showAdminSection functie (rond regel 1100)
 */

// ============= TIMELINE DATA LOADING =============

async function loadTimelineList() {
    console.log('📅 Loading timeline events...');
    
    const events = await window.apiCall('timeline');
    const list = document.getElementById('timelineList');
    
    if (!list) {
        console.warn('⚠️ timelineList element not found');
        return;
    }
    
    if (!events || events.length === 0) {
        list.innerHTML = '<p class="text-muted fst-italic">Nog geen timeline events</p>';
        return;
    }
    
    const table = document.createElement('table');
    table.className = 'table table-hover table-sm';
    table.innerHTML = `
        <thead>
            <tr>
                <th>Titel</th>
                <th>Groep</th>
                <th>Datum</th>
                <th>Vers</th>
                <th style="width: 120px;">Acties</th>
            </tr>
        </thead>
        <tbody id="timelineTableBody"></tbody>
    `;
    
    list.innerHTML = '';
    list.appendChild(table);
    
    const tbody = document.getElementById('timelineTableBody');
    
    events.forEach(event => {
        const tr = document.createElement('tr');
        tr.className = 'timeline-item';
        
        const groupBadge = event.Groep_Naam ? 
            `<span class="badge" style="background: ${event.Groep_Kleur};">${event.Groep_Naam}</span>` : 
            '<span class="text-muted">-</span>';
        
        let verseRef = '-';
        if (event.Start_Boek) {
            verseRef = `${event.Start_Boek} ${event.Start_Hoofdstuk}:${event.Start_Vers}`;
            if (event.End_Boek) {
                verseRef += ` - ${event.End_Boek} ${event.End_Hoofdstuk}:${event.End_Vers}`;
            }
        }
        
        let dateDisplay = '-';
        if (event.Start_Datum) {
            dateDisplay = event.Start_Datum;
            if (event.End_Datum && event.End_Datum !== event.Start_Datum) {
                dateDisplay += ' tot ' + event.End_Datum;
            }
        }
        
        tr.innerHTML = `
            <td><strong>${event.Titel}</strong><br><small class="text-muted">${event.Beschrijving || ''}</small></td>
            <td>${groupBadge}</td>
            <td><small>${dateDisplay}</small></td>
            <td><small>${verseRef}</small></td>
            <td>
                <button class="btn btn-outline-primary btn-sm" onclick="editTimeline(${event.Event_ID})" title="Bewerk">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="deleteTimeline(${event.Event_ID})" title="Verwijder">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    
    console.log(`✅ Loaded ${events.length} timeline events`);
}

async function loadTimelineGroups() {
    console.log('🏷️ Loading timeline groups...');
    
    const groups = await window.apiCall('timeline_groups');
    
    if (!groups) {
        console.warn('⚠️ No timeline groups received');
        return;
    }
    
    // 1. Fill the groups list display
    const list = document.getElementById('groupsList');
    if (list) {
        if (groups.length === 0) {
            list.innerHTML = '<p class="text-muted fst-italic">Nog geen groepen</p>';
        } else {
            list.innerHTML = '';
            
            groups.forEach(group => {
                const chip = document.createElement('div');
                chip.style.cssText = `
                    display: inline-flex;
                    align-items: center;
                    gap: 0.5rem;
                    padding: 0.5rem 0.8rem;
                    margin: 0.25rem;
                    border-radius: 20px;
                    background: ${group.Kleur}20;
                    border: 2px solid ${group.Kleur};
                `;
                
                chip.innerHTML = `
                    <span style="font-weight: 500;">${group.Groep_Naam}</span>
                    <button onclick="editTimelineGroup(${group.Group_ID}, '${group.Groep_Naam.replace(/'/g, "\\'")}', '${group.Kleur}')" 
                            class="btn btn-sm btn-link p-0" title="Bewerk">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button onclick="deleteTimelineGroup(${group.Group_ID})" 
                            class="btn btn-sm btn-link text-danger p-0" title="Verwijder">
                        <i class="bi bi-trash"></i>
                    </button>
                `;
                
                list.appendChild(chip);
            });
            
            console.log(`✅ Filled groups list with ${groups.length} groups`);
        }
    }
    
    // 2. Fill the dropdown for new event form
    const dropdown = document.getElementById('timelineGroup');
    if (dropdown) {
        dropdown.innerHTML = '<option value="">Geen groep</option>';
        groups.forEach(group => {
            const option = document.createElement('option');
            option.value = group.Group_ID;
            option.textContent = group.Groep_Naam;
            dropdown.appendChild(option);
        });
        console.log(`✅ Filled dropdown with ${groups.length} groups`);
    }
}

// ============= LOCATIONS DATA LOADING =============

async function loadLocationList() {
    console.log('📍 Loading locations...');
    
    const locations = await window.apiCall('locations');
    const list = document.getElementById('locationList');
    
    if (!list) {
        console.warn('⚠️ locationList element not found');
        return;
    }
    
    if (!locations || locations.length === 0) {
        list.innerHTML = '<p class="text-muted fst-italic">Nog geen locaties</p>';
        return;
    }
    
    const table = document.createElement('table');
    table.className = 'table table-hover table-sm';
    table.innerHTML = `
        <thead>
            <tr>
                <th>Naam</th>
                <th>Type</th>
                <th>Coördinaten</th>
                <th>Beschrijving</th>
                <th style="width: 120px;">Acties</th>
            </tr>
        </thead>
        <tbody id="locationTableBody"></tbody>
    `;
    
    list.innerHTML = '';
    list.appendChild(table);
    
    const tbody = document.getElementById('locationTableBody');
    
    const typeIcons = {
        'stad': 'bi-building',
        'berg': 'bi-triangle',
        'rivier': 'bi-water',
        'zee': 'bi-tsunami',
        'regio': 'bi-map',
        'overig': 'bi-geo-alt'
    };
    
    locations.forEach(loc => {
        const tr = document.createElement('tr');
        const icon = typeIcons[loc.Type] || 'bi-geo-alt';
        
        tr.innerHTML = `
            <td><strong>${loc.Naam}</strong></td>
            <td><i class="bi ${icon}"></i> ${loc.Type}</td>
            <td><small>${loc.Latitude}, ${loc.Longitude}</small></td>
            <td><small class="text-muted">${loc.Beschrijving || '-'}</small></td>
            <td>
                <button class="btn btn-outline-primary btn-sm" onclick="editLocation(${loc.Locatie_ID})" title="Bewerk">
                    <i class="bi bi-pencil"></i>
                </button>
                <button class="btn btn-outline-danger btn-sm" onclick="deleteLocation(${loc.Locatie_ID})" title="Verwijder">
                    <i class="bi bi-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(tr);
    });
    
    console.log(`✅ Loaded ${locations.length} locations`);
}

// ============= IMAGES DATA LOADING =============

// ════════════════════════════════════════════════════════════════════════════
// 📊 NIEUWE loadImageList() FUNCTIE - MET TABEL EN UITLIJNING
// Vervang regel 1415-1466 in admin.js met deze versie
// ════════════════════════════════════════════════════════════════════════════

async function loadImageList() {
    console.log('🖼️ Loading images...');
    
    const images = await window.apiCall('all_images');
    const list = document.getElementById('imageList');
    
    if (!list) {
        console.warn('⚠️ imageList element not found');
        return;
    }
    
    if (!images || images.length === 0) {
        list.innerHTML = '<div class="col-12"><p class="text-muted fst-italic">Nog geen afbeeldingen geüpload</p></div>';
        return;
    }
    
    // ✅ NIEUW: Create TABLE instead of cards
    let html = `
        <div class="col-12">
            <div class="table-responsive">
                <table class="table table-hover table-sm align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 80px;">Voorbeeld</th>
                            <th>Bestandsnaam</th>
                            <th style="width: 110px;">Uitlijning</th>
                            <th style="width: 90px;">Afmetingen</th>
                            <th>Gekoppeld aan</th>
                            <th style="width: 120px;">Datum</th>
                            <th style="width: 130px;" class="text-center">Acties</th>
                        </tr>
                    </thead>
                    <tbody>
    `;
    
    images.forEach(img => {
        // Uitlijning icon en text
        let alignIcon = '';
        let alignText = '';
        let alignColor = '';
        
        switch(img.Uitlijning) {
            case 'left':
                alignIcon = '⬅️';
                alignText = 'Links';
                alignColor = 'text-primary';
                break;
            case 'right':
                alignIcon = '➡️';
                alignText = 'Rechts';
                alignColor = 'text-success';
                break;
            case 'center':
            default:
                alignIcon = '⬌';
                alignText = 'Midden';
                alignColor = 'text-secondary';
                break;
        }
        
        // Verse info
        let verseInfo = '<span class="text-muted">-</span>';
        if (img.Bijbelboeknaam && img.Hoofdstuknummer && img.Versnummer) {
            verseInfo = `<span class="text-primary"><i class="bi bi-book"></i> ${img.Bijbelboeknaam} ${img.Hoofdstuknummer}:${img.Versnummer}</span>`;
        }
        
        // Format date
        const date = img.Geupload_Op ? new Date(img.Geupload_Op).toLocaleDateString('nl-NL', { 
            day: '2-digit', 
            month: '2-digit', 
            year: 'numeric' 
        }) : '-';
        
        // Dimensions
        const width = img.Breedte || 400;
        const height = img.Hoogte || 'Auto';
        const dimensions = `${width}px${height !== 'Auto' ? ' × ' + height + 'px' : ''}`;
        
        // Caption tooltip
        const captionTitle = img.Caption ? img.Caption : img.Originele_Naam;
        
        html += `
            <tr>
                <td>
                    <img src="${img.Bestandspad}" 
                         alt="${img.Caption || img.Originele_Naam}" 
                         class="rounded"
                         style="width: 60px; height: 60px; object-fit: cover; cursor: pointer; border: 1px solid #dee2e6;"
                         onclick="window.open('${img.Bestandspad}', '_blank')"
                         title="Klik om volledig te openen">
                </td>
                <td>
                    <div style="max-width: 200px;">
                        <div class="text-truncate fw-semibold" title="${img.Originele_Naam}">
                            ${img.Originele_Naam}
                        </div>
                        ${img.Caption ? `<small class="text-muted text-truncate d-block" title="${img.Caption}"><i class="bi bi-chat-quote"></i> ${img.Caption}</small>` : ''}
                    </div>
                </td>
                <td>
                    <div class="d-flex align-items-center gap-1">
                        <span class="${alignColor}" style="font-size: 1.3em;" title="${alignText}">
                            ${alignIcon}
                        </span>
                        <small class="${alignColor} fw-semibold">${alignText}</small>
                    </div>
                </td>
                <td>
                    <small class="text-muted font-monospace">${dimensions}</small>
                </td>
                <td>
                    <small>${verseInfo}</small>
                </td>
                <td>
                    <small class="text-muted">${date}</small>
                </td>
                <td class="text-center">
                    <div class="btn-group btn-group-sm" role="group">
                        <button class="btn btn-outline-primary" 
                                onclick="editImage(${img.Afbeelding_ID})"
                                title="Bewerken">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <button class="btn btn-outline-danger" 
                                onclick="deleteImage(${img.Afbeelding_ID})"
                                title="Verwijderen">
                            <i class="bi bi-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    });
    
    html += `
                    </tbody>
                </table>
            </div>
        </div>
    `;
    
    list.innerHTML = html;
    
    console.log(`✅ Loaded ${images.length} images in table format`);
}

// ════════════════════════════════════════════════════════════════════════════
//  KLAAR! Dit vervangt regel 1415-1466 in admin.js
// ════════════════════════════════════════════════════════════════════════════

// ============= NOTES DATA LOADING =============

let notesQuill = null;
let currentNoteId = null;
// NOTE: notes array is on window.notes (set by admin-datatable-loaders.js)

async function initNotesEditor() {
    console.log('📝 Initializing notes editor...');
    
    // Check if Quill is available
    if (typeof Quill === 'undefined') {
        console.error('❌ Quill not loaded - cannot initialize notes editor');
        return;
    }
    
    // Initialize Quill editor if not already done
    const container = document.getElementById('notesEditorContainer');
    if (container && !notesQuill) {
        notesQuill = new Quill('#notesEditorContainer', {
            theme: 'snow',
            placeholder: 'Begin hier met schrijven...',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link'],
                    ['clean']
                ]
            }
        });
        console.log('✅ Notes Quill editor initialized');
        
        // Setup auto-save
        setupNotesAutoSave();
    }
    
    // Load notes from database
    await loadNotes();
}

// NOTE: loadNotes (and list rendering) is in admin-datatable-loaders.js
// It loads notes into window.notes and renders the list

function selectNote(noteId) {
    // Convert to number (onclick passes string)
    noteId = parseInt(noteId);
    currentNoteId = noteId;
    const note = window.notes?.find(n => n.Notitie_ID === noteId);
    
    if (!note) {
        console.error('Note not found:', noteId);
        console.log('Available notes:', window.notes);
        return;
    }
    
    // Show editor, hide empty state
    const emptyState = document.getElementById('emptyNotesState');
    const editorContent = document.getElementById('noteEditorContent');
    
    if (emptyState) emptyState.classList.add('d-none');
    if (editorContent) {
        editorContent.classList.remove('d-none');
        editorContent.classList.add('d-flex');
    }
    
    // Load note content
    const titleInput = document.getElementById('noteTitleInput');
    if (titleInput) {
        titleInput.value = note.Titel || '';
    }
    
    if (notesQuill) {
        if (note.Inhoud) {
            notesQuill.root.innerHTML = note.Inhoud;
        } else {
            notesQuill.setText('');
        }
    }
    
    // Reload list to update active state
    window.loadNotes();
    
    console.log('✅ Note selected:', noteId);
}

function setupNotesAutoSave() {
    if (!notesQuill) return;
    
    let autoSaveTimeout = null;
    
    notesQuill.on('text-change', () => {
        if (!currentNoteId) return;
        
        clearTimeout(autoSaveTimeout);
        
        const statusEl = document.getElementById('noteSaveStatus');
        if (statusEl) statusEl.textContent = 'Opslaan...';
        
        autoSaveTimeout = setTimeout(() => {
            saveCurrentNote();
        }, 2000);
    });
    
    // Also auto-save on title change
    const titleInput = document.getElementById('noteTitleInput');
    if (titleInput) {
        titleInput.addEventListener('input', () => {
            if (!currentNoteId) return;
            
            clearTimeout(autoSaveTimeout);
            
            const statusEl = document.getElementById('noteSaveStatus');
            if (statusEl) statusEl.textContent = 'Opslaan...';
            
            autoSaveTimeout = setTimeout(() => {
                saveCurrentNote();
            }, 2000);
        });
    }
}

async function createNewNote() {
    if (!notesQuill) {
        await initNotesEditor();
    }
    
    const result = await window.apiCall('save_note', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            titel: 'Nieuwe notitie',
            inhoud: ''
        })
    });
    
    if (result && result.success) {
        await window.loadNotes();
        selectNote(result.notitie_id);
        
        // Focus on title input
        setTimeout(() => {
            const titleInput = document.getElementById('noteTitleInput');
            if (titleInput) {
                titleInput.value = '';
                titleInput.focus();
            }
        }, 100);
        
        console.log('✅ New note created');
    }
}

async function saveCurrentNote() {
    if (!currentNoteId || !notesQuill) return;
    
    const titleInput = document.getElementById('noteTitleInput');
    const titel = titleInput ? titleInput.value || 'Naamloos' : 'Naamloos';
    const inhoud = notesQuill.root.innerHTML;
    
    const result = await window.apiCall('save_note', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            notitie_id: currentNoteId,
            titel: titel,
            inhoud: inhoud
        })
    });
    
    if (result && result.success) {
        // Reload notes list from database
        await window.loadNotes();
        
        const statusEl = document.getElementById('noteSaveStatus');
        if (statusEl) {
            statusEl.textContent = 'Opgeslagen';
            setTimeout(() => {
                statusEl.textContent = '';
            }, 2000);
        }
        
        console.log('✅ Note saved');
    }
}

async function deleteCurrentNote() {
    if (!currentNoteId) return;
    
    if (!confirm('Weet je zeker dat je deze notitie wilt verwijderen?')) return;
    
    const result = await window.apiCall(`delete_note&id=${currentNoteId}`);
    
    if (result && result.success) {
        currentNoteId = null;
        
        // Show empty state
        const emptyState = document.getElementById('emptyNotesState');
        const editorContent = document.getElementById('noteEditorContent');
        
        if (emptyState) emptyState.classList.remove('d-none');
        if (editorContent) {
            editorContent.classList.add('d-none');
            editorContent.classList.remove('d-flex');
        }
        
        // Reload notes list from database
        await window.loadNotes();
        showNotification('Notitie verwijderd');
        console.log('✅ Note deleted');
    }
}

// ============= EXPORT FUNCTIES =============

// Maak alle functies globally beschikbaar
window.loadTimelineList = loadTimelineList;
window.loadTimelineGroups = loadTimelineGroups;
window.loadLocationList = loadLocationList;
window.loadImageList = loadImageList;
window.initNotesEditor = initNotesEditor;
// window.loadNotes = loadNotes;  // ❌ Removed - loadNotes is in admin-datatable-loaders.js
window.selectNote = selectNote;
window.createNewNote = createNewNote;
window.saveCurrentNote = saveCurrentNote;
window.deleteCurrentNote = deleteCurrentNote;

/**
 * ADMIN.JS - TIMELINE VERSE LINKING PATCH
 * 
 * Vervang de saveTimeline en editTimeline functies met deze versies
 * Of voeg deze regels toe aan je bestaande functies
 */

// ============= UPDATED saveTimeline FUNCTION =============

async function saveTimeline() {
    const eventId = document.getElementById('timelineEventId')?.value;
    const titel = document.getElementById('timelineTitel')?.value;
    const beschrijving = document.getElementById('timelineBeschrijving')?.value || null;
    const groupId = document.getElementById('timelineGroup')?.value || null;
    const startDatum = document.getElementById('timelineStartDatum')?.value;
    const endDatum = document.getElementById('timelineEndDatum')?.value || null;
    const kleur = document.getElementById('timelineKleur')?.value || '#3498db';
    
    // ✨ NIEUW: Verse linking velden
    const versStart = document.getElementById('timelineVersStart')?.value || null;
    const versEnd = document.getElementById('timelineVersEnd')?.value || null;
    
    if (!titel || !startDatum) {
        window.showNotification('Vul titel en start datum in', true);
        return;
    }
    
    const data = {
        titel,
        beschrijving, // ✨ NIEUW
        group_id: groupId,
        start_datum: startDatum,
        end_datum: endDatum,
        kleur,
        vers_id_start: versStart, // ✨ NIEUW
        vers_id_end: versEnd      // ✨ NIEUW
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
        // Clear form
        document.getElementById('timelineEventId').value = '';
        document.getElementById('timelineTitel').value = '';
        document.getElementById('timelineBeschrijving').value = ''; // ✨ NIEUW
        document.getElementById('timelineStartDatum').value = '';
        document.getElementById('timelineEndDatum').value = '';
        document.getElementById('timelineVersStart').value = ''; // ✨ NIEUW
        document.getElementById('timelineVersEnd').value = '';   // ✨ NIEUW
        // Reload list
        loadTimelineList();
    }
}

// ============= UPDATED editTimeline FUNCTION =============

async function editTimeline(eventId) {
    // Load event data
    const result = await window.apiCall(`get_timeline&id=${eventId}`);
    
    if (!result) {
        window.showNotification('Event niet gevonden', true);
        return;
    }
    
    // Fill form
    document.getElementById('timelineEventId').value = result.Event_ID || '';
    document.getElementById('timelineTitel').value = result.Titel || '';
    document.getElementById('timelineBeschrijving').value = result.Beschrijving || ''; // ✨ NIEUW
    document.getElementById('timelineGroup').value = result.Group_ID || '';
    document.getElementById('timelineStartDatum').value = result.Start_Datum || '';
    document.getElementById('timelineEndDatum').value = result.End_Datum || '';
    document.getElementById('timelineKleur').value = result.Kleur || '#3498db';
    document.getElementById('timelineVersStart').value = result.Vers_ID_Start || ''; // ✨ NIEUW
    document.getElementById('timelineVersEnd').value = result.Vers_ID_End || '';     // ✨ NIEUW
    
    // Scroll to form
    document.querySelector('#section-timeline .card').scrollIntoView({ behavior: 'smooth' });
    
    window.showNotification('Event geladen voor bewerking');
}

// ============= MAKE GLOBAL =============

window.saveTimeline = saveTimeline;
window.editTimeline = editTimeline;

console.log('✅ Timeline verse linking functions updated');


async function editTimeline(eventId) {
    // Voor nu simpel - later kunnen we de data laden en invullen
    window.showNotification('Edit functionaliteit TODO - edit ID: ' + eventId);
}

async function deleteTimeline(eventId) {
    if (!confirm('Weet je zeker dat je dit timeline event wilt verwijderen?')) return;
    
    const result = await window.apiCall(`delete_timeline&id=${eventId}`);
    
    if (result && result.success) {
        window.showNotification('Timeline event verwijderd');
        loadTimelineList();
    }
}

async function editTimelineGroup(groupId, naam, kleur) {
    // Simpele prompt edit - later mooiere UI
    const newNaam = prompt('Groep naam:', naam);
    if (!newNaam) return;
    
    const newKleur = prompt('Kleur (hex):', kleur);
    if (!newKleur) return;
    
    const result = await window.apiCall('update_timeline_group', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            group_id: groupId,
            naam: newNaam,
            kleur: newKleur
        })
    });
    
    if (result && result.success) {
        window.showNotification('Groep bijgewerkt');
        loadTimelineGroups();
    }
}

async function deleteTimelineGroup(groupId) {
    if (!confirm('Weet je zeker dat je deze groep wilt verwijderen?')) return;
    
    const result = await window.apiCall(`delete_timeline_group&id=${groupId}`);
    
    if (result && result.success) {
        window.showNotification('Groep verwijderd');
        loadTimelineGroups();
        loadTimelineList(); // Reload events too
    }
}

// ============= LOCATIONS CRUD FUNCTIES =============

async function saveLocation() {
    const locationId = document.getElementById('locationId')?.value;
    const naam = document.getElementById('locationName')?.value;
    const lat = document.getElementById('locationLat')?.value;
    const lng = document.getElementById('locationLng')?.value;
    
    if (!naam || !lat || !lng) {
        window.showNotification('Vul alle velden in', true);
        return;
    }
    
    const data = {
        naam,
        latitude: parseFloat(lat),
        longitude: parseFloat(lng),
        type: 'overig',
        beschrijving: ''
    };
    
    if (locationId) {
        data.locatie_id = locationId;
    }
    
    const result = await window.apiCall('save_location', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    
    if (result && result.success) {
        window.showNotification('Locatie opgeslagen!');
        // Clear form
        document.getElementById('locationId').value = '';
        document.getElementById('locationName').value = '';
        document.getElementById('locationLat').value = '';
        document.getElementById('locationLng').value = '';
        // Reload list
        loadLocationList();
    }
}

async function editLocation(locationId) {
    window.showNotification('Edit functionaliteit TODO - edit ID: ' + locationId);
}

async function deleteLocation(locationId) {
    if (!confirm('Weet je zeker dat je deze locatie wilt verwijderen?')) return;
    
    const result = await window.apiCall(`delete_location&id=${locationId}`);
    
    if (result && result.success) {
        window.showNotification('Locatie verwijderd');
        loadLocationList();
    }
}

// ============= IMAGES CRUD FUNCTIES =============

async function uploadImage() {
    const fileInput = document.getElementById('imageFile');
    const caption = document.getElementById('imageCaption')?.value;
    
    if (!fileInput || !fileInput.files || !fileInput.files[0]) {
        window.showNotification('Selecteer een afbeelding', true);
        return;
    }
    
    const formData = new FormData();
    formData.append('image', fileInput.files[0]);
    if (caption) formData.append('caption', caption);
    
    try {
        const response = await fetch('?api=upload_image', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result && result.success) {
            window.showNotification('Afbeelding geüpload!');
            fileInput.value = '';
            document.getElementById('imageCaption').value = '';
            loadImageList();
        } else {
            window.showNotification('Upload mislukt: ' + (result.error || 'Onbekende fout'), true);
        }
    } catch (error) {
        window.showNotification('Upload fout: ' + error.message, true);
    }
}

async function editImage(imageId) {
    window.showNotification('Edit functionaliteit TODO - edit ID: ' + imageId);
}

async function deleteImage(imageId) {
    if (!confirm('Weet je zeker dat je deze afbeelding wilt verwijderen?')) return;
    
    const result = await window.apiCall(`delete_image&id=${imageId}`);
    
    if (result && result.success) {
        window.showNotification('Afbeelding verwijderd');
        loadImageList();
    }
}

// Maak nieuwe functies globally beschikbaar 
window.saveTimeline = saveTimeline;
window.editTimeline = editTimeline;
window.deleteTimeline = deleteTimeline;
window.editTimelineGroup = editTimelineGroup;
window.deleteTimelineGroup = deleteTimelineGroup;
window.saveLocation = saveLocation;
window.editLocation = editLocation;
window.deleteLocation = deleteLocation;
window.uploadImage = uploadImage;
window.editImage = editImage;
window.deleteImage = deleteImage;

console.log('✅ Data loading functions registered');

/**
 * ADMIN.JS - FIXED VERSION with Images Support
 * Add this to the END of your existing admin.js
 */

// ============================================
// IMAGES FIX - Permanent Solution
// ============================================

console.log('🖼️ Loading images module...');

// Silent API call (doesn't spam console with errors)
async function silentApiCall(endpoint) {
    try {
        const response = await fetch('?api=' + endpoint);
        if (!response.ok) return null;
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            return null;
        }
        
        return await response.json();
    } catch (error) {
        return null;
    }
}

// Load image list (tries multiple endpoints)
window.loadImageList = async function() {
    console.log('🖼️ Loading images...');
    
    const imgList = document.getElementById('imageList');
    if (!imgList) {
        console.warn('⚠️ imageList element not found');
        return;
    }
    
    // Show loading
    imgList.innerHTML = '<div class="col-12 text-center py-3"><div class="spinner-border spinner-border-sm"></div><p class="mt-2 text-muted">Laden...</p></div>';
    
    // Try multiple endpoints silently
    let imgs = await silentApiCall('images');
    if (!imgs) imgs = await silentApiCall('afbeeldingen');
    if (!imgs) imgs = await silentApiCall('get_images');
    
    // No images or no API
    if (!imgs || !Array.isArray(imgs) || imgs.length === 0) {
        console.log('ℹ️ No images available (API not implemented)');
        imgList.innerHTML = `
            <div class="col-12">
                <div class="alert alert-info">
                    <div class="text-center mb-3">
                        <i class="bi bi-image" style="font-size: 4rem; opacity: 0.3;"></i>
                    </div>
                    <h5 class="alert-heading">
                        <i class="bi bi-info-circle"></i> Afbeeldingen Functionaliteit
                    </h5>
                    <p class="mb-2">De afbeeldingen API is nog niet geïmplementeerd in de backend.</p>
                    <hr>
                    <h6 class="mb-2">Vereiste Backend Implementatie:</h6>
                    <ul class="small mb-2">
                        <li>Database tabellen: <code>Afbeeldingen</code> en <code>Vers_Afbeeldingen</code></li>
                        <li>API endpoint: <code>?api=images</code></li>
                        <li>Upload endpoint: <code>?api=upload_image</code></li>
                        <li>Upload directory: <code>/images/</code> (chmod 755)</li>
                    </ul>
                    <p class="small text-muted mb-0">
                        📖 Zie <strong>IMAGES-COMPLETE-GUIDE.md</strong> voor volledige implementatie instructies
                    </p>
                </div>
            </div>
        `;
        return;
    }
    
    // Display images
    console.log(`✅ Loaded ${imgs.length} images`);
    imgList.innerHTML = '';
    
    imgs.forEach(img => {
        const col = document.createElement('div');
        col.className = 'col-md-4 col-lg-3 mb-3';
        
        const imgPath = img.Bestandspad || img.path || img.url || '';
        const caption = img.Bijschrift || img.caption || img.beschrijving || 'Geen bijschrift';
        const imgId = img.Afbeelding_ID || img.id || img.image_id || 0;
        
        col.innerHTML = `
            <div class="card h-100 shadow-sm">
                <img src="${imgPath}" 
                     class="card-img-top" 
                     alt="${caption}"
                     style="height: 200px; object-fit: cover; cursor: pointer;"
                     onclick="window.open('${imgPath}', '_blank')"
                     onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\\'p-3 text-center text-muted\\'><i class=\\'bi bi-image\\' style=\\'font-size:3rem;\\'></i><p class=\\'small\\'>Afbeelding niet gevonden</p></div>';">
                <div class="card-body">
                    <p class="card-text small mb-2">${caption}</p>
                    ${imgId ? `
                        <button class="btn btn-sm btn-danger w-100" onclick="deleteImage(${imgId})">
                            <i class="bi bi-trash"></i> Verwijderen
                        </button>
                    ` : ''}
                </div>
            </div>
        `;
        
        imgList.appendChild(col);
    });
};

// Upload image function
window.uploadImage = async function() {
    const fileInput = document.getElementById('imageFile');
    const captionInput = document.getElementById('imageCaption');
    
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        showNotification('Selecteer eerst een afbeelding', true);
        return;
    }
    
    const caption = captionInput ? captionInput.value : '';
    
    try {
        const formData = new FormData();
        formData.append('image', fileInput.files[0]);
        formData.append('bijschrift', caption);
        
        showNotification('Uploaden...');
        
        const response = await fetch('?api=upload_image', {
            method: 'POST',
            body: formData
        });
        
        if (!response.ok) {
            throw new Error('Upload failed: ' + response.status);
        }
        
        const result = await response.json();
        
        if (result && result.success) {
            showNotification('Afbeelding geüpload!');
            fileInput.value = '';
            if (captionInput) captionInput.value = '';
            loadImageList();
        } else {
            showNotification('Upload mislukt: ' + (result.error || 'Unknown error'), true);
        }
        
    } catch (error) {
        console.error('Upload error:', error);
        showNotification('Upload mislukt: API endpoint niet beschikbaar', true);
    }
};

// Delete image function
window.deleteImage = async function(id) {
    if (!id) {
        showNotification('Geen image ID', true);
        return;
    }
    
    if (!confirm('Weet je zeker dat je deze afbeelding wilt verwijderen?')) {
        return;
    }
    
    try {
        const result = await silentApiCall(`delete_image&id=${id}`);
        
        if (result && result.success) {
            showNotification('Afbeelding verwijderd');
            loadImageList();
        } else {
            showNotification('Verwijderen mislukt: API endpoint niet beschikbaar', true);
        }
        
    } catch (error) {
        console.error('Delete error:', error);
        showNotification('Verwijderen mislukt', true);
    }
};

console.log('✅ Images module loaded');

// Auto-load images when section is opened
const originalShowAdminSection = window.showAdminSection;
window.showAdminSection = function(section) {
    // Call original function
    if (originalShowAdminSection) {
        originalShowAdminSection(section);
    }
    
    // Auto-load images
    if (section === 'images') {
        setTimeout(() => {
            loadImageList();
        }, 100);
    }
};
/**
 * ADMIN.JS - TIMELINE PROFESSIONAL EDITOR
 * Add these functions to your admin.js
 */

// ============= TIMELINE QUILL EDITOR =============

// let timelineDescQuill = null;

/**
 * Initialize Timeline Beschrijving Quill Editor
 * Call this in your initAdminMode() or when timeline section is shown
 */
function initTimelineEditor() {
    const container = document.getElementById('timelineBeschrijvingEditor');
    if (!container || timelineDescQuill) return;
    
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

/**
 * ADMIN-IMAGES.JS - COMPLETE IMAGE MANAGEMENT
 * Add these functions to your admin.js
 */

// ============= IMAGE VERSE SELECTOR SETUP =============

/**
 * Load books into image verse selector
 */
async function loadImageVerseBooks() {
    const books = await window.apiCall('books');
    if (!books) return;
    
    const boekSelect = document.getElementById('imageBoek');
    if (!boekSelect) return;
    
    boekSelect.innerHTML = '<option value="">Kies boek...</option>';
    books.forEach(book => {
        const option = document.createElement('option');
        option.value = book.Bijbelboeknaam;
        option.textContent = book.Bijbelboeknaam;
        boekSelect.appendChild(option);
    });
    
    console.log(`✅ Loaded ${books.length} books into image selector`);
}

/**
 * Setup image verse selector event listeners
 */
function setupImageVerseSelectors() {
    // Boek change → Load chapters
    const boekSelect = document.getElementById('imageBoek');
    if (boekSelect) {
        boekSelect.addEventListener('change', async (e) => {
            const boek = e.target.value;
            const hoofdstukSelect = document.getElementById('imageHoofdstuk');
            const versSelect = document.getElementById('imageVers');
            
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
    
    // Hoofdstuk change → Load verses
    const hoofdstukSelect = document.getElementById('imageHoofdstuk');
    if (hoofdstukSelect) {
        hoofdstukSelect.addEventListener('change', async (e) => {
            const hoofdstuk = e.target.value;
            const boek = document.getElementById('imageBoek').value;
            const versSelect = document.getElementById('imageVers');
            
            versSelect.innerHTML = '<option value="">Vers</option>';
            
            if (!boek || !hoofdstuk) return;
            
            const verses = await window.apiCall(`verses&boek=${encodeURIComponent(boek)}&hoofdstuk=${hoofdstuk}&limit=999`);
            if (verses) {
                versSelect.innerHTML = '<option value="">Vers</option>' +
                    verses.map(v => `<option value="${v.Vers_ID}">${v.Versnummer}</option>`).join('');
            }
        });
    }
    
    console.log('✅ Image verse selectors ready');
}

// ============= IMAGE CRUD FUNCTIONS =============

/**
 * Save/Upload Image
 */
async function saveImage() {
    const imageId = document.getElementById('imageId')?.value;
    const file = document.getElementById('imageFile')?.files[0];
    const caption = document.getElementById('imageCaption')?.value;
    const versId = document.getElementById('imageVers')?.value || null;
    
    // NEW: Get layout & dimensions
    const uitlijning = document.getElementById('imageUitlijning')?.value || 'center';
    const breedte = document.getElementById('imageBreedte')?.value || 400;
    const hoogte = document.getElementById('imageHoogte')?.value || '';
    
    // For new uploads, file is required
    if (!imageId && !file) {
        window.showNotification('Selecteer een afbeelding', true);
        return;
    }
    
    // For edits, file is optional (only updating caption/verse)
    if (imageId && !file && !caption && !versId) {
        window.showNotification('Vul minimaal caption of vers in', true);
        return;
    }
    
    const formData = new FormData();
    if (file) formData.append('image', file);
    if (caption) formData.append('caption', caption);
    if (versId) formData.append('vers_id', versId);
    if (imageId) formData.append('image_id', imageId);
    
    // NEW: Append layout & dimensions
    formData.append('uitlijning', uitlijning);
    formData.append('breedte', breedte);
    formData.append('hoogte', hoogte);
    
    console.log('📤 Uploading image with layout:', { uitlijning, breedte, hoogte });
    
    try {
        const response = await fetch('?api=save_image', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result && result.success) {
            window.showNotification(imageId ? 'Afbeelding bijgewerkt!' : 'Afbeelding geüpload!');
            clearImageForm();
            
            // Reload image list
            if (typeof loadImageList === 'function') {
                loadImageList();
            }
        } else {
            window.showNotification(result?.error || 'Er is een fout opgetreden', true);
        }
    } catch (error) {
        console.error('❌ Upload error:', error);
        window.showNotification('Upload mislukt', true);
    }
}

/**
 * Edit Image - Load data into form
 */
async function editImage(imageId) {
    console.log('📝 Editing image:', imageId);
    
    // Get image data
    const result = await window.apiCall(`get_image&id=${imageId}`);
    
    if (!result) {
        window.showNotification('Afbeelding niet gevonden', true);
        return;
    }
    
    // Fill form
    document.getElementById('imageId').value = result.Afbeelding_ID;
    document.getElementById('imageCaption').value = result.Caption || '';
    
    // NEW: Fill layout & dimensions
    document.getElementById('imageUitlijning').value = result.Uitlijning || 'center';
    document.getElementById('imageBreedte').value = result.Breedte || 400;
    document.getElementById('imageHoogte').value = result.Hoogte || '';
    
    // Update button text
    const saveBtn = document.getElementById('imageSaveButtonText');
    if (saveBtn) {
        saveBtn.textContent = 'Bijwerken';
    }
    
    // Fill verse selector if verse is linked
    if (result.Vers_ID) {
        await fillImageVerseSelector(result.Vers_ID);
    }
    
    // Scroll to form
    document.getElementById('imageCaption').scrollIntoView({ behavior: 'smooth', block: 'center' });
    document.getElementById('imageCaption').focus();
    
    window.showNotification('Afbeelding geladen - pas aan en klik Bijwerken');
}

/**
 * Fill image verse selector with data from vers ID
 */
async function fillImageVerseSelector(versId) {
    const verse = await window.apiCall(`verse_detail&vers_id=${versId}`);
    if (!verse) return;
    
    // Set boek
    const boekSelect = document.getElementById('imageBoek');
    if (boekSelect) {
        boekSelect.value = verse.Bijbelboeknaam;
        
        // Load chapters
        const chapters = await window.apiCall(`chapters&boek=${encodeURIComponent(verse.Bijbelboeknaam)}`);
        const hoofdstukSelect = document.getElementById('imageHoofdstuk');
        
        if (chapters && hoofdstukSelect) {
            hoofdstukSelect.innerHTML = '<option value="">Hoofdstuk</option>' +
                chapters.map(ch => `<option value="${ch.Hoofdstuknummer}">${ch.Hoofdstuknummer}</option>`).join('');
            
            hoofdstukSelect.value = verse.Hoofdstuknummer;
            
            // Load verses
            const verses = await window.apiCall(`verses&boek=${encodeURIComponent(verse.Bijbelboeknaam)}&hoofdstuk=${verse.Hoofdstuknummer}&limit=999`);
            const versSelect = document.getElementById('imageVers');
            
            if (verses && versSelect) {
                versSelect.innerHTML = '<option value="">Vers</option>' +
                    verses.map(v => `<option value="${v.Vers_ID}">${v.Versnummer}</option>`).join('');
                
                versSelect.value = versId;
            }
        }
    }
}

/**
 * Delete Image
 */
async function deleteImage(imageId) {
    if (!confirm('Weet je zeker dat je deze afbeelding wilt verwijderen?')) return;
    
    const result = await window.apiCall(`delete_image&id=${imageId}`);
    
    if (result && result.success) {
        window.showNotification('Afbeelding verwijderd');
        
        // Reload image list
        if (typeof loadImageList === 'function') {
            loadImageList();
        }
    }
}

/**
 * Clear Image Form
 */
function clearImageForm() {
    document.getElementById('imageId').value = '';
    document.getElementById('imageFile').value = '';
    document.getElementById('imageCaption').value = '';
    document.getElementById('imageBoek').value = '';
    document.getElementById('imageHoofdstuk').innerHTML = '<option value="">Hoofdstuk</option>';
    document.getElementById('imageVers').innerHTML = '<option value="">Vers</option>';
    
    // NEW: Reset layout & dimensions to defaults
    document.getElementById('imageUitlijning').value = 'center';
    document.getElementById('imageBreedte').value = 400;
    document.getElementById('imageHoogte').value = '';
    
    // Reset button text
    const saveBtn = document.getElementById('imageSaveButtonText');
    if (saveBtn) {
        saveBtn.textContent = 'Uploaden';
    }
    
    window.showNotification('Formulier geleegd');
}

// ============= INITIALIZATION =============

/**
 * Initialize image section when opened
 */
async function initImageSection() {
    await loadImageVerseBooks();
    setupImageVerseSelectors();
    
    if (typeof loadImageList === 'function') {
        await loadImageList();
    }
}

// ============= MAKE GLOBAL =============

window.loadImageVerseBooks = loadImageVerseBooks;
window.setupImageVerseSelectors = setupImageVerseSelectors;
window.saveImage = saveImage;
window.editImage = editImage;
window.fillImageVerseSelector = fillImageVerseSelector;
window.deleteImage = deleteImage;
window.clearImageForm = clearImageForm;
window.initImageSection = initImageSection;

console.log('✅ Image admin functions loaded (with verse selector)');