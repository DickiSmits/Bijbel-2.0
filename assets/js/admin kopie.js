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
        
    const verses = await apiCall(params);
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
    const profiles = await window.apiCall('profiles');
    
    const select = document.getElementById('editorProfileSelect');
    if (select && profiles) {
        select.innerHTML = '<option value="">Kies profiel...</option>';
        profiles.forEach(p => {
            const option = document.createElement('option');
            option.value = p.Profiel_ID;
            option.textContent = p.Profiel_Naam;
            select.appendChild(option);
        });
        console.log(`✅ Loaded ${profiles.length} profiles`);
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
            const result = await apiCall(`delete_formatting&vers_id=${versId}&profiel_id=${profielId}`);
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
                const result = await apiCall(`delete_formatting&vers_id=${versId}&profiel_id=${profielId}`);
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
            const result = await apiCall(`delete_formatting&vers_id=${versId}&profiel_id=${profielId}`);
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
                const result = await apiCall(`delete_formatting&vers_id=${versId}&profiel_id=${profielId}`);
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


console.log('✅ Admin.js ready');
