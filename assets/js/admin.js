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
                    <button class="btn btn-outline-danger btn-sm" onclick="deleteProfile(${profile.Profiel_ID})" title="Verwijder">
                        <i class="bi bi-trash"></i>
                    </button>
                </td>
            `;
            
            tbody.appendChild(tr);
        });
        
        console.log(`✅ Filled list with ${profiles.length} profiles`);
    }
}

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
        loadProfiles();
    }
}
window.createProfile = createProfile;

async function deleteProfile(id) {
    if (!confirm('Weet je zeker dat je dit profiel wilt verwijderen?')) return;
    
    const result = await window.apiCall(`delete_profile&id=${id}`);
    if (result?.success) {
        window.showNotification('Profiel verwijderd');
        loadProfiles();
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
            if (typeof loadTimelineList === 'function') {
                loadTimelineList();
            }
            if (typeof loadTimelineGroups === 'function') {
                loadTimelineGroups();
            }
            break;
            
        case 'locations':
            if (typeof loadLocationList === 'function') {
                loadLocationList();
            }
            break;
            
        case 'images':
            if (typeof loadImageList === 'function') {
                loadImageList();
            }
            break;
            
        case 'notes':
            if (typeof initNotesEditor === 'function') {
                initNotesEditor();
            }
            break;
    }
}

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
    
    list.innerHTML = '';
    
    images.forEach(img => {
        const versInfo = img.Bijbelboeknaam ? 
            `<span class="text-primary fw-semibold"><i class="bi bi-book"></i> ${img.Bijbelboeknaam} ${img.Hoofdstuknummer}:${img.Versnummer}</span>` : 
            '<span class="text-muted"><i class="bi bi-exclamation-triangle"></i> Geen vers gekoppeld</span>';
        
        const col = document.createElement('div');
        col.className = 'col-md-6 col-lg-4';
        col.innerHTML = `
            <div class="card h-100">
                <img src="${img.Bestandspad}" class="card-img-top" style="height: 150px; object-fit: cover;">
                <div class="card-body">
                    <h6 class="card-title text-truncate">${img.Originele_Naam}</h6>
                    <p class="card-text small text-muted mb-2">
                        ${versInfo}<br>
                        <i class="bi bi-arrows-angle-expand"></i> ${img.Breedte}px ${img.Hoogte ? '× ' + img.Hoogte + 'px' : '(auto)'}
                        ${img.Caption ? '<br><i class="bi bi-chat-quote"></i> ' + img.Caption : ''}
                    </p>
                </div>
                <div class="card-footer bg-transparent">
                    <button class="btn btn-sm btn-outline-primary" onclick="editImage(${img.Afbeelding_ID})">
                        <i class="bi bi-pencil"></i> Bewerk
                    </button>
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteImage(${img.Afbeelding_ID})">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        list.appendChild(col);
    });
    
    console.log(`✅ Loaded ${images.length} images`);
}

// ============= NOTES DATA LOADING =============

let notesQuill = null;
let currentNoteId = null;
// Make notes globally accessible to avoid scope issues
window.notesArray = [];

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
        
        // Make Quill globally accessible
        window.notesQuill = notesQuill;
        
        console.log('✅ Notes Quill editor initialized');
        
        // Setup auto-save
        setupNotesAutoSave();
    } else if (container) {
        // Quill might be in container already
        if (container.__quill) {
            notesQuill = container.__quill;
            window.notesQuill = notesQuill;
        }
    }
    
    // Load notes from database
    await loadNotes();
}

async function loadNotes() {
    console.log('📋 Loading notes from database...');
    
    const result = await window.apiCall('notes');
    
    if (result) {
        window.notesArray = result.map(n => ({
            id: n.Notitie_ID,
            title: n.Titel || '',
            content: n.Inhoud || '',
            created: n.Aangemaakt,
            updated: n.Gewijzigd
        }));
        console.log(`✅ Loaded ${window.notesArray.length} notes`);
    } else {
        window.notesArray = [];
        console.log('No notes found');
    }
    
    renderNotesList();
}

function renderNotesList() {
    const listContainer = document.getElementById('notesList');
    if (!listContainer) {
        console.warn('⚠️ notesList element not found');
        return;
    }
    
    if (!window.notesArray || window.notesArray.length === 0) {
        listContainer.innerHTML = `
            <div class="text-center p-4 text-muted">
                <p>Nog geen notities</p>
            </div>
        `;
        return;
    }
    
    // Sort by updated date
    const sortedNotes = [...window.notesArray].sort((a, b) => new Date(b.updated) - new Date(a.updated));
    
    listContainer.innerHTML = sortedNotes.map(note => {
        const date = new Date(note.updated);
        const dateStr = date.toLocaleDateString('nl-NL', { 
            day: 'numeric', 
            month: 'short',
            hour: '2-digit',
            minute: '2-digit'
        });
        
        // Strip HTML for preview
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = note.content || '';
        const plainText = tempDiv.textContent || '';
        const preview = plainText.substring(0, 50) + (plainText.length > 50 ? '...' : '');
        
        const isActive = note.id === currentNoteId;
        
        return `
            <div class="p-3 mb-2 rounded cursor-pointer ${isActive ? 'bg-primary text-white' : 'bg-light'}" 
                 onclick="selectNote(${note.id})" style="cursor: pointer;">
                <div class="fw-bold text-truncate">${note.title || 'Naamloos'}</div>
                <div class="small opacity-75">${dateStr}</div>
                ${preview ? `<div class="small opacity-75 text-truncate mt-1">${preview}</div>` : ''}
            </div>
        `;
    }).join('');
}

function selectNote(noteId) {
    // Convert to number (onclick passes string)
    noteId = parseInt(noteId);
    currentNoteId = noteId;
    
    const note = window.notesArray?.find(n => n.id === noteId);
    
    if (!note) {
        console.error('Note not found:', noteId);
        return;
    }
    
    console.log('📝 Loading note:', note.title);
    
    // Ensure Quill is available
    if (!window.notesQuill && notesQuill) {
        window.notesQuill = notesQuill;
    }
    
    // Show editor, hide empty state
    const emptyState = document.getElementById('emptyNotesState');
    const editorContent = document.getElementById('noteEditorContent');
    
    if (emptyState) {
        emptyState.classList.add('d-none');
    }
    
    if (editorContent) {
        editorContent.classList.remove('d-none');
        editorContent.classList.add('d-flex');
    }
    
    // Load note content
    const titleInput = document.getElementById('noteTitleInput');
    if (titleInput) {
        titleInput.value = note.title || '';
    }
    
    if (window.notesQuill) {
        // Clear editor first
        window.notesQuill.setText('');
        
        // Load HTML content if available
        if (note.content && note.content.trim()) {
            window.notesQuill.clipboard.dangerouslyPasteHTML(note.content);
        }
        
        console.log('✅ Note loaded in editor');
    } else {
        console.error('❌ notesQuill not available');
    }
    
    // Update active state in list
    renderNotesList();
}
        if (note.content) {
            notesQuill.root.innerHTML = note.content;
        } else {
            notesQuill.setText('');
        }
    }
    
    // Update list selection
    renderNotesList();
    
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
    const quillInstance = window.notesQuill || notesQuill;
    
    if (!quillInstance) {
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
        await loadNotes();
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
    } else {
        console.error('❌ Failed to create note:', result);
        window.showNotification('Nieuwe notitie maken mislukt', true);
    }
}

async function saveCurrentNote() {
    if (!currentNoteId) {
        console.log('⚠️ No note selected');
        return;
    }
    
    // Ensure Quill is available
    const quillInstance = window.notesQuill || notesQuill;
    if (!quillInstance) {
        console.error('❌ Quill editor not available');
        return;
    }
    
    const titleInput = document.getElementById('noteTitleInput');
    const titel = titleInput ? titleInput.value || 'Naamloos' : 'Naamloos';
    const inhoud = quillInstance.root.innerHTML;
    
    console.log('💾 Saving note:', currentNoteId, titel);
    
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
        // Update local cache
        const noteIndex = window.notesArray.findIndex(n => n.id === currentNoteId);
        if (noteIndex !== -1) {
            window.notesArray[noteIndex].title = titel;
            window.notesArray[noteIndex].content = inhoud;
            window.notesArray[noteIndex].updated = new Date().toISOString();
        }
        
        renderNotesList();
        
        const statusEl = document.getElementById('noteSaveStatus');
        if (statusEl) {
            statusEl.textContent = 'Opgeslagen';
            setTimeout(() => {
                statusEl.textContent = '';
            }, 2000);
        }
        
        console.log('✅ Note saved successfully');
        window.showNotification('Notitie opgeslagen');
    } else {
        console.error('❌ Save failed:', result);
        window.showNotification('Opslaan mislukt', true);
    }
}

async function deleteCurrentNote() {
    if (!currentNoteId) return;
    
    if (!confirm('Weet je zeker dat je deze notitie wilt verwijderen?')) return;
    
    console.log('🗑️ Deleting note:', currentNoteId);
    
    const result = await window.apiCall(`delete_note&id=${currentNoteId}`);
    
    if (result && result.success) {
        // Remove from local array
        window.notesArray = window.notesArray.filter(n => n.id !== currentNoteId);
        currentNoteId = null;
        
        // Show empty state
        const emptyState = document.getElementById('emptyNotesState');
        const editorContent = document.getElementById('noteEditorContent');
        
        if (emptyState) emptyState.classList.remove('d-none');
        if (editorContent) {
            editorContent.classList.add('d-none');
            editorContent.classList.remove('d-flex');
        }
        
        renderNotesList();
        window.showNotification('Notitie verwijderd');
        console.log('✅ Note deleted successfully');
    } else {
        console.error('❌ Delete failed:', result);
        window.showNotification('Verwijderen mislukt', true);
    }
}
    }
}

// ============= EXPORT FUNCTIES =============

// Maak alle functies globally beschikbaar
window.loadTimelineList = loadTimelineList;
window.loadTimelineGroups = loadTimelineGroups;
window.loadLocationList = loadLocationList;
window.loadImageList = loadImageList;
window.initNotesEditor = initNotesEditor;
window.loadNotes = loadNotes;
window.selectNote = selectNote;
window.createNewNote = createNewNote;
window.saveCurrentNote = saveCurrentNote;
window.deleteCurrentNote = deleteCurrentNote;

// ============= TIMELINE CRUD FUNCTIES =============

async function saveTimeline() {
    const eventId = document.getElementById('timelineEventId')?.value;
    const titel = document.getElementById('timelineTitel')?.value;
    const groupId = document.getElementById('timelineGroup')?.value || null;
    const startDatum = document.getElementById('timelineStartDatum')?.value;
    const endDatum = document.getElementById('timelineEndDatum')?.value || null;
    const kleur = document.getElementById('timelineKleur')?.value || '#3498db';
    
    if (!titel || !startDatum) {
        window.showNotification('Vul titel en start datum in', true);
        return;
    }
    
    const data = {
        titel,
        group_id: groupId,
        start_datum: startDatum,
        end_datum: endDatum,
        kleur
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
        document.getElementById('timelineStartDatum').value = '';
        document.getElementById('timelineEndDatum').value = '';
        // Reload list
        loadTimelineList();
    }
}

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