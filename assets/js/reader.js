/**
 * READER.JS - Reader mode functionaliteit
 */

let currentBook = null;
let currentChapter = null;
let currentVerse = null;
let currentProfile = null;
let currentOffset = 0;
let loading = false;
let allLoaded = false;

// Initialize reader
async function initReader() {
    console.log('Initializing reader...');
    
    // Load books
    const books = await apiCall('books');
    if (books) {
        const bookSelect = document.getElementById('bookSelect');
        books.forEach(book => {
            const option = document.createElement('option');
            option.value = book.Bijbelboeknaam;
            option.textContent = book.Bijbelboeknaam;
            bookSelect.appendChild(option);
        });
    }
    
    // Load profiles
    const profiles = await apiCall('profiles');
    if (profiles) {
        const profileSelect = document.getElementById('profileSelect');
        profiles.forEach(profile => {
            const option = document.createElement('option');
            option.value = profile.Profiel_ID;
            option.textContent = profile.Profiel_Naam;
            profileSelect.appendChild(option);
        });
    }
    
    // Setup event listeners
    setupEventListeners();
    
    // Load initial content (Genesis 1 as example)
    await loadVerses();
    
    console.log('Reader initialized');
}

// Setup event listeners
function setupEventListeners() {
    const bookSelect = document.getElementById('bookSelect');
    const chapterSelect = document.getElementById('chapterSelect');
    const profileSelect = document.getElementById('profileSelect');
    const searchInput = document.getElementById('searchInput');
    
    if (bookSelect) {
        bookSelect.addEventListener('change', async (e) => {
            currentBook = e.target.value;
            currentChapter = null;
            
            // Load chapters
            const chapters = await apiCall(`chapters&boek=${encodeURIComponent(currentBook)}`);
            chapterSelect.innerHTML = '<option value="">Alle hoofdstukken</option>';
            
            if (chapters) {
                chapters.forEach(ch => {
                    const option = document.createElement('option');
                    option.value = ch.Hoofdstuknummer;
                    option.textContent = `Hoofdstuk ${ch.Hoofdstuknummer}`;
                    chapterSelect.appendChild(option);
                });
            }
            
            loadVerses();
        });
    }
    
    if (chapterSelect) {
        chapterSelect.addEventListener('change', (e) => {
            currentChapter = e.target.value;
            loadVerses();
        });
    }
    
    if (profileSelect) {
        profileSelect.addEventListener('change', (e) => {
            currentProfile = e.target.value || null;
            console.log('📋 Profile changed to:', currentProfile);
            loadVerses();
        });
    }
    
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                searchQuery = e.target.value;
                if (searchQuery.length > 2 || searchQuery.length === 0) {
                    loadVerses();
                }
            }, 300);
        });
    }
}

// Load verses
async function loadVerses(append = false) {
    if (loading || (allLoaded && append)) return;
    
    loading = true;
    const container = document.getElementById('bibleText');
    
    if (!append) {
        container.innerHTML = '<div class="text-center py-5"><div class="spinner-border"></div><p class="mt-2">Laden...</p></div>';
        currentOffset = 0;
        allLoaded = false;
    }
    
    const params = new URLSearchParams({
        limit: 50,
        offset: currentOffset
    });
    
    if (currentBook) params.append('boek', currentBook);
    if (currentChapter) params.append('hoofdstuk', currentChapter);
    if (currentProfile) params.append('profiel_id', currentProfile);
    
    const url = 'verses&' + params.toString();
    console.log('📖 Loading verses:', url);
    console.log('   Current profile:', currentProfile);
    
    const verses = await apiCall(url);
    
    if (!verses || verses.length === 0) {
        if (!append) {
            container.innerHTML = '<div class="text-center py-5 text-muted">Geen verzen gevonden</div>';
        }
        allLoaded = true;
        loading = false;
        return;
    }
    
    // Count opmaak for debugging
    let withOpmaak = 0;
    let withoutOpmaak = 0;
    verses.forEach(v => {
        if (v.Opgemaakte_Tekst && v.Opgemaakte_Tekst.trim() !== '') {
            withOpmaak++;
        } else {
            withoutOpmaak++;
        }
    });
    
    console.log(`✅ Loaded ${verses.length} verses: ${withOpmaak} with opmaak, ${withoutOpmaak} without`);
    
    if (!append) {
        container.innerHTML = '';
    }
    
    let lastChapter = null;
    
    verses.forEach(verse => {
        // Add chapter header
        const chapterKey = `${verse.Bijbelboeknaam}_${verse.Hoofdstuknummer}`;
        if (lastChapter !== chapterKey) {
            const header = document.createElement('div');
            header.className = 'chapter-header';
            header.textContent = `${verse.Bijbelboeknaam} ${verse.Hoofdstuknummer}`;
            container.appendChild(header);
            lastChapter = chapterKey;
        }
        
        // Add verse
        const verseSpan = document.createElement('span');
        verseSpan.className = 'verse';
        verseSpan.dataset.versId = verse.Vers_ID;
        
        const number = document.createElement('span');
        number.className = 'verse-number';
        number.textContent = verse.Versnummer;
        
        const text = document.createElement('span');
        text.className = 'verse-text';
        text.innerHTML = verse.Opgemaakte_Tekst || verse.Tekst;
        
        verseSpan.appendChild(number);
        verseSpan.appendChild(text);
        verseSpan.appendChild(document.createTextNode(' '));
        
        verseSpan.addEventListener('click', () => {
            selectVerse(verse.Vers_ID);
        });
        
        container.appendChild(verseSpan);
    });
    
    currentOffset += verses.length;
    loading = false;
    
    if (verses.length < 50) {
        allLoaded = true;
    }
}

// Select verse
function selectVerse(versId) {
    document.querySelectorAll('.verse').forEach(v => v.classList.remove('active'));
    const verseElement = document.querySelector(`[data-vers-id="${versId}"]`);
    if (verseElement) {
        verseElement.classList.add('active');
    }
    currentVerse = versId;
}

// Timeline filter toggle
function toggleTimelineFilter() {
    const panel = document.getElementById('timelineFilterPanel');
    if (panel) {
        const collapse = new bootstrap.Collapse(panel, { toggle: true });
    }
}

// Timeline navigation (placeholders)
function navigateTimelinePrev() {
    console.log('Navigate timeline prev - requires timeline.js');
}

function navigateTimelineNext() {
    console.log('Navigate timeline next - requires timeline.js');
}

// Make functions global
window.initReader = initReader;
window.toggleTimelineFilter = toggleTimelineFilter;
window.navigateTimelinePrev = navigateTimelinePrev;
window.navigateTimelineNext = navigateTimelineNext;

console.log('Reader.js loaded');
