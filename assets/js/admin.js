/**
 * ADMIN.JS - Complete Admin Functionality
 *  Handles text editing, profiles, timeline, locations, images, notes
 */

console.log('Admin.js loaded - initializing admin features');

// Global variables
let quill = null; // Main single verse editor
let timelineDescQuill = null; // Timeline description editor
let notesQuill = null; // Notes editor
let currentEditMode = localStorage.getItem('adminEditMode') || 'single';
let chapterEditors = {}; // Store Quill instances for chapter mode
let chapterVersesData = []; // Store verse data for chapter mode

// ============= QUILL INITIALIZATION =============

function initQuillEditors() {
    console.log('🖊️ Initializing Quill editors...');
    
    // Check if Quill is loaded
    if (typeof Quill === 'undefined') {
        console.error('❌ Quill not loaded! Check if CDN is included.');
        return;
    }
    
    // Register custom fonts
    const Font = Quill.import('formats/font');
    Font.whitelist = ['serif', 'monospace', 'arial', 'times', 'courier', 'georgia', 'verdana', 'tahoma', 'trebuchet'];
    Quill.register(Font, true);
    
    // Initialize main editor (single verse mode)
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
    }
    
    // Initialize timeline description editor
    const timelineDescContainer = document.getElementById('timelineBeschrijvingEditor');
    if (timelineDescContainer) {
        timelineDescQuill = new Quill('#timelineBeschrijvingEditor', {
            theme: 'snow',
            placeholder: 'Beschrijving van het event...',
            modules: {
                toolbar: [
                    [{ 'header': [1, 2, 3, false] }],
                    [{ 'font': Font.whitelist }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                    ['link'],
                    ['clean']
                ]
            }
        });
        console.log('✅ Timeline description editor initialized');
        window.timelineDescQuill = timelineDescQuill;
    }
    
    console.log('✅ Quill editors initialization complete');
}

// Initialize on DOMContentLoaded
document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const mode = urlParams.get('mode');
    
    if (mode === 'admin') {
        console.log('Admin mode detected, initializing Quill...');
        setTimeout(() => {
            initQuillEditors();
        }, 100);
    }
});

// ============= ADMIN INITIALIZATION =============

async function initAdmin() {
    console.log('Admin mode - loading initial data...');
    
    // Load books for editor
    const books = await window.apiCall('books');
    const bookSelect = document.getElementById('adminBookSelect');
    if (bookSelect && books) {
        books.forEach(book => {
            const option = document.createElement('option');
            option.value = book.Bijbelboeknaam;
            option.textContent = book.Bijbelboeknaam;
            bookSelect.appendChild(option);
        });
        console.log(`Loaded ${books.length} books in admin editor`);
    }
    
    // Load books for all verse selectors
    await populateBookSelectors();
    
    // Load profiles
    loadProfiles();
    loadFormattingList();
    
    // Restore editor settings
    await restoreEditorSettings();
}

// ============= RESTORE EDITOR SETTINGS =============

async function restoreEditorSettings() {
    const savedBook = localStorage.getItem('adminBook');
    const savedChapter = localStorage.getItem('adminChapter');
    const savedVerse = localStorage.getItem('adminVerse');
    const savedProfile = localStorage.getItem('adminProfile');
    const savedEditMode = localStorage.getItem('adminEditMode');
    
    // Restore edit mode first
    if (savedEditMode) {
        currentEditMode = savedEditMode;
        if (savedEditMode === 'chapter') {
            document.getElementById('editModeChapter').checked = true;
            document.getElementById('singleVerseEditor').classList.add('d-none');
            document.getElementById('chapterEditor').classList.remove('d-none');
            document.getElementById('verseSelectContainer').classList.add('d-none');
        } else {
            document.getElementById('editModeSingle').checked = true;
        }
    }
    
    // Restore profile
    if (savedProfile) {
        setTimeout(() => {
            const profileSelect = document.getElementById('editorProfileSelect');
            if (profileSelect) {
                profileSelect.value = savedProfile;
            }
        }, 300);
    }
    
    // Restore book
    if (savedBook) {
        const bookSelect = document.getElementById('adminBookSelect');
        bookSelect.value = savedBook;
        
        await loadAdminChapters();
        
        if (savedChapter) {
            const chapterSelect = document.getElementById('adminChapterSelect');
            chapterSelect.value = savedChapter;
            
            await loadAdminVerses();
            
            if (savedVerse && currentEditMode === 'single') {
                const verseSelect = document.getElementById('adminVerseSelect');
                verseSelect.value = savedVerse;
                
                setTimeout(() => {
                    loadVerse();
                }, 400);
            }
            
            if (currentEditMode === 'chapter') {
                setTimeout(() => {
                    loadChapterForEditing();
                }, 400);
            }
        }
    }
    
    console.log('Editor settings restored:', { savedBook, savedChapter, savedVerse, savedProfile, savedEditMode });
}

// ============= HELPER FUNCTIONS =============

async function populateBookSelectors() {
    const books = await window.apiCall('books');
    const selectors = [
        'imageBoek', 'editImageBoek',
        'timelineStartBoek', 'timelineEndBoek'
    ];
    
    selectors.forEach(selectorId => {
        const select = document.getElementById(selectorId);
        if (select && books) {
            const firstOption = select.options[0];
            select.innerHTML = '';
            select.appendChild(firstOption);
            
            books.forEach(book => {
                const option = document.createElement('option');
                option.value = book.Bijbelboeknaam;
                option.textContent = book.Bijbelboeknaam;
                select.appendChild(option);
            });
        }
    });
}

// ============= SET EDIT MODE =============

function setEditMode(mode) {
    currentEditMode = mode;
    localStorage.setItem('adminEditMode', mode);
    const singleEditor = document.getElementById('singleVerseEditor');
    const chapterEditor = document.getElementById('chapterEditor');
    const verseSelectContainer = document.getElementById('verseSelectContainer');
    
    if (mode === 'single') {
        singleEditor.classList.remove('d-none');
        chapterEditor.classList.add('d-none');
        verseSelectContainer.classList.remove('d-none');
    } else {
        singleEditor.classList.add('d-none');
        chapterEditor.classList.remove('d-none');
        verseSelectContainer.classList.add('d-none');
        const boek = document.getElementById('adminBookSelect').value;
        const hoofdstuk = document.getElementById('adminChapterSelect').value;
        if (boek && hoofdstuk) {
            loadChapterForEditing();
        }
    }
}
window.setEditMode = setEditMode;

// ============= ADMIN DROPDOWNS EVENT LISTENERS =============

document.getElementById('adminBookSelect').addEventListener('change', async (e) => {
    localStorage.setItem('adminBook', e.target.value);
    localStorage.removeItem('adminChapter');
    localStorage.removeItem('adminVerse');
    await loadAdminChapters();
});

async function loadAdminChapters() {
    const boek = document.getElementById('adminBookSelect').value;
    if (!boek) return;
    
    const chapters = await window.apiCall(`chapters&boek=${encodeURIComponent(boek)}`);
    const chapterSelect = document.getElementById('adminChapterSelect');
    chapterSelect.innerHTML = '<option value="">Kies hoofdstuk</option>';
    
    if (chapters) {
        chapters.forEach(ch => {
            const option = document.createElement('option');
            option.value = ch.Hoofdstuknummer;
            option.textContent = ch.Hoofdstuknummer;
            chapterSelect.appendChild(option);
        });
    }
}
window.loadAdminChapters = loadAdminChapters;

document.getElementById('adminChapterSelect').addEventListener('change', async (e) => {
    localStorage.setItem('adminChapter', e.target.value);
    localStorage.removeItem('adminVerse');
    await loadAdminVerses();
    if (currentEditMode === 'chapter') {
        loadChapterForEditing();
    }
});

async function loadAdminVerses() {
    const boek = document.getElementById('adminBookSelect').value;
    const hoofdstuk = document.getElementById('adminChapterSelect').value;
    
    if (!boek || !hoofdstuk) return;
    
    const verses = await window.apiCall(`verses&boek=${encodeURIComponent(boek)}&hoofdstuk=${hoofdstuk}&limit=999`);
    const verseSelect = document.getElementById('adminVerseSelect');
    verseSelect.innerHTML = '<option value="">Kies vers</option>';
    
    if (verses) {
        verses.forEach(v => {
            const option = document.createElement('option');
            option.value = v.Vers_ID;
            option.textContent = v.Versnummer;
            verseSelect.appendChild(option);
        });
    }
}
window.loadAdminVerses = loadAdminVerses;

document.getElementById('adminVerseSelect').addEventListener('change', (e) => {
    localStorage.setItem('adminVerse', e.target.value);
    loadVerse();
});

document.getElementById('editorProfileSelect').addEventListener('change', (e) => {
    localStorage.setItem('adminProfile', e.target.value);
    if (currentEditMode === 'chapter') {
        loadChapterForEditing();
    } else {
        loadVerse();
    }
});

// ============= LOAD SINGLE VERSE =============

async function loadVerse() {
    if (currentEditMode === 'chapter') return;
    
    const versId = document.getElementById('adminVerseSelect').value;
    const profielId = document.getElementById('editorProfileSelect').value;
    
    if (!versId) return;
    
    console.log(`Loading verse ${versId} with profile ${profielId}`);
    
    const params = `vers_id=${versId}` + (profielId ? `&profiel_id=${profielId}` : '');
    const verse = await window.apiCall('verse_detail&' + params);
    
    if (verse) {
        document.getElementById('originalText').textContent = verse.Tekst;
        
        if (quill) {
            quill.setText('');
            
            if (verse.Opgemaakte_Tekst && verse.Opgemaakte_Tekst.trim() !== '') {
                quill.clipboard.dangerouslyPasteHTML(verse.Opgemaakte_Tekst);
            } else {
                quill.setText(verse.Tekst);
            }
        }
    }
}
window.loadVerse = loadVerse;

// ============= LOAD CHAPTER FOR EDITING =============

async function loadChapterForEditing() {
    const boek = document.getElementById('adminBookSelect').value;
    const hoofdstuk = document.getElementById('adminChapterSelect').value;
    const profielId = document.getElementById('editorProfileSelect').value;
    
    if (!boek || !hoofdstuk) {
        document.getElementById('chapterVersesContainer').innerHTML = 
            '<div class="text-muted text-center py-4">Selecteer een boek en hoofdstuk om te beginnen</div>';
        return;
    }
    
    document.getElementById('chapterVersesContainer').innerHTML = 
        '<div class="text-center py-4"><div class="spinner-border spinner-border-sm" role="status"></div> Laden...</div>';
    
    Object.values(chapterEditors).forEach(editor => {
        if (editor && editor.container) {
            editor.container.innerHTML = '';
        }
    });
    chapterEditors = {};
    
    const params = profielId ? 
        `verses&boek=${encodeURIComponent(boek)}&hoofdstuk=${hoofdstuk}&profiel_id=${profielId}&limit=999` :
        `verses&boek=${encodeURIComponent(boek)}&hoofdstuk=${hoofdstuk}&limit=999`;
        
    const verses = await window.apiCall(params);
    chapterVersesData = verses || [];
    
    if (!verses || verses.length === 0) {
        document.getElementById('chapterVersesContainer').innerHTML = 
            '<div class="text-muted text-center py-4">Geen verzen gevonden</div>';
        return;
    }
    
    document.getElementById('chapterVerseCount').textContent = verses.length;
    
    const container = document.getElementById('chapterVersesContainer');
    container.innerHTML = '';
    
    for (const verse of verses) {
        const hasFormatting = verse.Opgemaakte_Tekst && verse.Opgemaakte_Tekst.trim() !== '';
        
        const verseItem = document.createElement('div');
        verseItem.className = 'chapter-verse-item' + (hasFormatting ? ' has-formatting' : '');
        verseItem.dataset.versId = verse.Vers_ID;
        verseItem.innerHTML = `
            <div class="chapter-verse-header">
                <span class="chapter-verse-number">${verse.Versnummer}</span>
                <span class="chapter-verse-original" title="${verse.Tekst}">${verse.Tekst}</span>
                <span class="chapter-verse-status badge ${hasFormatting ? 'bg-success' : 'bg-secondary'}">${hasFormatting ? 'Bewerkt' : 'Origineel'}</span>
            </div>
            <div class="chapter-verse-editor">
                <div id="chapter-editor-${verse.Vers_ID}"></div>
            </div>
        `;
        container.appendChild(verseItem);
        
        const editorId = `chapter-editor-${verse.Vers_ID}`;
        const Font = Quill.import('formats/font');
        const quillInstance = new Quill(`#${editorId}`, {
            theme: 'snow',
            modules: {
                toolbar: [
                    [{ 'font': Font.whitelist }],
                    [{ 'size': ['small', false, 'large', 'huge'] }],
                    ['bold', 'italic', 'underline', 'strike'],
                    [{ 'color': [] }, { 'background': [] }],
                    [{ 'script': 'sub'}, { 'script': 'super' }],
                    ['clean']
                ]
            }
        });
        
        if (hasFormatting) {
            quillInstance.clipboard.dangerouslyPasteHTML(verse.Opgemaakte_Tekst);
        } else {
            quillInstance.setText(verse.Tekst);
        }
        
        quillInstance.originalHtml = quillInstance.root.innerHTML;
        
        quillInstance.on('text-change', () => {
            const currentHtml = quillInstance.root.innerHTML;
            const isModified = currentHtml !== quillInstance.originalHtml;
            verseItem.classList.toggle('modified', isModified);
        });
        
        chapterEditors[verse.Vers_ID] = quillInstance;
    }
}
window.loadChapterForEditing = loadChapterForEditing;

// ============= SAVE FUNCTIONS =============

async function saveFormatting() {
    const versId = document.getElementById('adminVerseSelect').value;
    const profielId = document.getElementById('editorProfileSelect').value;
    
    if (!versId || !profielId) {
        window.showNotification('Selecteer een vers en profiel', true);
        return;
    }
    
    const tekst = quill.root.innerHTML;
    
    const result = await window.apiCall('save_formatting', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ vers_id: versId, profiel_id: profielId, tekst })
    });
    
    if (result && result.success) {
        window.showNotification('Opmaak opgeslagen!');
        loadFormattingList();
    }
}
window.saveFormatting = saveFormatting;

async function saveAllChapterFormatting() {
    const profielId = document.getElementById('editorProfileSelect').value;
    
    if (!profielId) {
        window.showNotification('Selecteer eerst een profiel', true);
        return;
    }
    
    let savedCount = 0;
    
    for (const [versId, editor] of Object.entries(chapterEditors)) {
        const currentHtml = editor.root.innerHTML;
        const isModified = currentHtml !== editor.originalHtml;
        
        if (isModified) {
            const result = await window.apiCall('save_formatting', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ 
                    vers_id: versId, 
                    profiel_id: profielId, 
                    tekst: currentHtml 
                })
            });
            
            if (result && result.success) {
                savedCount++;
                editor.originalHtml = currentHtml;
                const verseItem = document.querySelector(`.chapter-verse-item[data-vers-id="${versId}"]`);
                if (verseItem) {
                    verseItem.classList.remove('modified');
                    verseItem.classList.add('has-formatting');
                    const badge = verseItem.querySelector('.chapter-verse-status');
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
        loadFormattingList();
    } else {
        window.showNotification('Geen wijzigingen om op te slaan');
    }
}
window.saveAllChapterFormatting = saveAllChapterFormatting;

function resetFormatting() {
    const originalText = document.getElementById('originalText').textContent;
    if (originalText && quill) {
        quill.setText(originalText);
        window.showNotification('Tekst gereset naar origineel');
    }
}
window.resetFormatting = resetFormatting;

async function deleteFormatting() {
    const versId = document.getElementById('adminVerseSelect').value;
    const profielId = document.getElementById('editorProfileSelect').value;
    
    if (!versId || !profielId) {
        window.showNotification('Selecteer een vers en profiel', true);
        return;
    }
    
    if (!confirm('Weet je zeker dat je deze opmaak wilt verwijderen?')) return;
    
    const result = await window.apiCall(`delete_formatting&vers_id=${versId}&profiel_id=${profielId}`);
    if (result && result.success) {
        window.showNotification('Opmaak verwijderd');
        if (quill) {
            quill.setContents([]);
        }
        loadFormattingList();
    }
}
window.deleteFormatting = deleteFormatting;

// ============= LOAD PROFILES =============

async function loadProfiles() {
    const profiles = await window.apiCall('profiles');
    
    const editorSelect = document.getElementById('editorProfileSelect');
    if (editorSelect && profiles) {
        editorSelect.innerHTML = '';
        profiles.forEach(profile => {
            const option = document.createElement('option');
            option.value = profile.Profiel_ID;
            option.textContent = profile.Profiel_Naam;
            editorSelect.appendChild(option);
        });
        console.log(`Loaded ${profiles.length} profiles in admin`);
    }
    
    const profileList = document.getElementById('profilesList');
    if (profileList && profiles) {
        if (profiles.length === 0) {
            profileList.innerHTML = '<p class="text-muted fst-italic">Nog geen profielen aangemaakt</p>';
            return;
        }
        
        profileList.innerHTML = '';
        profiles.forEach(profile => {
            const item = document.createElement('div');
            item.className = 'profile-item d-flex justify-content-between align-items-center p-3 mb-2 bg-light rounded';
            item.innerHTML = `
                <div>
                    <div class="fw-semibold">${profile.Profiel_Naam}</div>
                    <small class="text-muted">${profile.Beschrijving || 'Geen beschrijving'}</small>
                </div>
                <button class="btn btn-outline-danger btn-sm" onclick="deleteProfile(${profile.Profiel_ID})">
                    <i class="bi bi-trash"></i> Verwijder
                </button>
            `;
            profileList.appendChild(item);
        });
    }
}

async function createProfile() {
    const naam = document.getElementById('newProfileName').value;
    const beschrijving = document.getElementById('newProfileDesc').value;
    
    if (!naam) {
        window.showNotification('Vul een naam in', true);
        return;
    }
    
    const result = await window.apiCall('create_profile', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ naam, beschrijving })
    });
    
    if (result && result.success) {
        window.showNotification('Profiel aangemaakt!');
        document.getElementById('newProfileName').value = '';
        document.getElementById('newProfileDesc').value = '';
        loadProfiles();
    }
}
window.createProfile = createProfile;

async function deleteProfile(id) {
    if (!confirm('Weet je zeker dat je dit profiel wilt verwijderen?')) return;
    
    const result = await window.apiCall(`delete_profile&id=${id}`, { method: 'GET' });
    
    if (result && result.success) {
        window.showNotification('Profiel verwijderd');
        loadProfiles();
    }
}
window.deleteProfile = deleteProfile;

// ============= LOAD FORMATTING LIST =============

async function loadFormattingList() {
    const formattedVerses = await window.apiCall('all_formatting');
    const list = document.getElementById('formattingList');
    
    if (!list || !formattedVerses) return;
    
    if (formattedVerses.length === 0) {
        list.innerHTML = '<div class="text-center text-muted py-4">Nog geen bewerkte verzen</div>';
        return;
    }
    
    list.innerHTML = '';
    formattedVerses.forEach(item => {
        const div = document.createElement('div');
        div.className = 'formatting-item p-2 mb-2 border rounded';
        div.style.cursor = 'pointer';
        
        const tempDiv = document.createElement('div');
        tempDiv.innerHTML = item.Opgemaakte_Tekst;
        const preview = tempDiv.textContent.substring(0, 60) + (tempDiv.textContent.length > 60 ? '...' : '');
        
        div.innerHTML = `
            <div class="d-flex justify-content-between align-items-start">
                <div class="flex-grow-1">
                    <div class="fw-bold small">
                        ${item.Bijbelboeknaam} ${item.Hoofdstuknummer}:${item.Versnummer}
                        <span class="badge bg-primary ms-1">${item.Profiel_Naam}</span>
                    </div>
                    <div class="text-muted small">${preview}</div>
                </div>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary btn-sm edit-btn">
                        <i class="bi bi-pencil"></i>
                    </button>
                    <button class="btn btn-outline-danger btn-sm delete-btn">
                        <i class="bi bi-trash"></i>
                    </button>
                </div>
            </div>
        `;
        
        div.querySelector('.edit-btn').onclick = (e) => {
            e.stopPropagation();
            editFormatting(item.Vers_ID, item.Profiel_ID);
        };
        
        div.querySelector('.delete-btn').onclick = (e) => {
            e.stopPropagation();
            deleteFormattingItem(item.Vers_ID, item.Profiel_ID);
        };
        
        list.appendChild(div);
    });
}

async function editFormatting(versId, profielId) {
    const verse = await window.apiCall(`verses&offset=0&limit=1&vers_id=${versId}`);
    if (!verse || verse.length === 0) return;
    
    const v = verse[0];
    
    document.getElementById('adminBookSelect').value = v.Bijbelboeknaam;
    await loadAdminChapters();
    
    document.getElementById('adminChapterSelect').value = v.Hoofdstuknummer;
    await loadAdminVerses();
    
    document.getElementById('adminVerseSelect').value = versId;
    document.getElementById('editorProfileSelect').value = profielId;
    
    await loadVerse();
    
    document.querySelector('#section-editor').scrollIntoView({ behavior: 'smooth' });
}
window.editFormatting = editFormatting;

async function deleteFormattingItem(versId, profielId) {
    if (!confirm('Weet je zeker dat je deze opmaak wilt verwijderen?')) return;
    
    const result = await window.apiCall(`delete_formatting&vers_id=${versId}&profiel_id=${profielId}`);
    if (result && result.success) {
        window.showNotification('Opmaak verwijderd');
        loadFormattingList();
    }
}
window.deleteFormattingItem = deleteFormattingItem;

// ============= INITIALIZE ON PAGE LOAD =============

document.addEventListener('DOMContentLoaded', () => {
    const urlParams = new URLSearchParams(window.location.search);
    const mode = urlParams.get('mode');
    
    if (mode === 'admin') {
        console.log('Initializing admin mode...');
        setTimeout(() => {
            initAdmin();
        }, 200);
    }
});

console.log('✅ Admin.js fully loaded');
