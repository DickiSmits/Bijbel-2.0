/**
 * Bijbelreader - Reader Module
 * Core functionaliteit voor het lezen van bijbelteksten
 */

// Global reader variabelen
let currentBook = null;
let currentChapter = null;
let currentVerse = null;
let currentProfile = null;
let currentOffset = 0;
let loading = false;
let allLoaded = false;
let searchQuery = '';
let lastChapter = null;
let firstLoadedVersId = null;
let loadingBackward = false;
let allLoadedBackward = false;
let selectedChapter = null;
let continuousScrolling = false;
let lastLoadedVersId = null;
let isAutoScrolling = false;
let scrollSyncEnabled = true;

/**
 * Initialiseer reader mode
 */
async function initReader() {
    console.log('Initializing reader...');
    
    // Load books
    const books = await apiCall('books');
    const bookSelect = document.getElementById('bookSelect');
    if (books && bookSelect) {
        books.forEach(book => {
            const option = document.createElement('option');
            option.value = book.Bijbelboeknaam;
            option.textContent = book.Bijbelboeknaam;
            bookSelect.appendChild(option);
        });
    }
    
    // Load profiles
    const profiles = await apiCall('profiles');
    const profileSelect = document.getElementById('profileSelect');
    if (profiles && profileSelect) {
        profiles.forEach(profile => {
            const option = document.createElement('option');
            option.value = profile.Profiel_ID;
            option.textContent = profile.Profiel_Naam;
            profileSelect.appendChild(option);
        });
        
        // Restore profile from localStorage
        const lastProfile = localStorage.getItem('lastProfile');
        if (lastProfile && profiles.some(p => p.Profiel_ID == lastProfile)) {
            currentProfile = lastProfile;
            profileSelect.value = currentProfile;
        } else if (profiles.length > 0) {
            currentProfile = profiles[0].Profiel_ID;
            profileSelect.value = currentProfile;
        }
    }
    
    // Restore last position from localStorage
    const lastBook = localStorage.getItem('lastBook');
    const lastChapter = localStorage.getItem('lastChapter');
    const lastVerse = localStorage.getItem('lastVerse');
    
    if (lastBook && books && books.some(b => b.Bijbelboeknaam === lastBook)) {
        bookSelect.value = lastBook;
        currentBook = lastBook;
        
        // Load chapters for the book
        const chapters = await apiCall(`chapters&boek=${encodeURIComponent(lastBook)}`);
        const chapterSelect = document.getElementById('chapterSelect');
        if (chapters && chapterSelect) {
            chapterSelect.innerHTML = '<option value="">Alle hoofdstukken</option>';
            
            chapters.forEach(ch => {
                const option = document.createElement('option');
                option.value = ch.Hoofdstuknummer;
                option.textContent = `Hoofdstuk ${ch.Hoofdstuknummer}`;
                chapterSelect.appendChild(option);
            });
            
            if (lastChapter) {
                chapterSelect.value = lastChapter;
                currentChapter = lastChapter;
            }
        }
    }
    
    // Setup event listeners
    setupEventListeners();
    
    // Initialize map and timeline
    if (typeof initMap === 'function') {
        initMap();
    }
    
    if (typeof initTimeline === 'function') {
        initTimeline();
    }
    
    // Load data
    await loadVerses();
    
    if (typeof loadAllLocationsOnMap === 'function') {
        await loadAllLocationsOnMap();
    }
    
    if (typeof loadTimelineEvents === 'function') {
        await loadTimelineEvents();
    }
    
    // Scroll to last verse if available
    if (lastVerse) {
        setTimeout(() => {
            const verseElement = document.querySelector(`[data-vers-id="${lastVerse}"]`);
            if (verseElement) {
                verseElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                selectVerse(parseInt(lastVerse), false);
            }
        }, 300);
    }
    
    console.log('Reader initialized');
}

/**
 * Setup event listeners
 */
function setupEventListeners() {
    // Book select
    const bookSelect = document.getElementById('bookSelect');
    if (bookSelect) {
        bookSelect.addEventListener('change', async (e) => {
            currentBook = e.target.value;
            currentChapter = null;
            
            localStorage.setItem('lastBook', currentBook);
            localStorage.removeItem('lastChapter');
            localStorage.removeItem('lastVerse');
            
            // Load chapters
            const chapters = await apiCall(`chapters&boek=${encodeURIComponent(currentBook)}`);
            const chapterSelect = document.getElementById('chapterSelect');
            if (chapters && chapterSelect) {
                chapterSelect.innerHTML = '<option value="">Alle hoofdstukken</option>';
                
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
    
    // Chapter select
    const chapterSelect = document.getElementById('chapterSelect');
    if (chapterSelect) {
        chapterSelect.addEventListener('change', (e) => {
            currentChapter = e.target.value;
            
            if (currentChapter) {
                localStorage.setItem('lastChapter', currentChapter);
            } else {
                localStorage.removeItem('lastChapter');
            }
            localStorage.removeItem('lastVerse');
            
            loadVerses();
        });
    }
    
    // Profile select
    const profileSelect = document.getElementById('profileSelect');
    if (profileSelect) {
        profileSelect.addEventListener('change', (e) => {
            currentProfile = e.target.value || null;
            
            if (currentProfile) {
                localStorage.setItem('lastProfile', currentProfile);
            } else {
                localStorage.removeItem('lastProfile');
            }
            
            loadVerses();
        });
    }
    
    // Search input
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            searchQuery = e.target.value;
            if (searchQuery.length > 2) {
                loadVerses();
            } else if (searchQuery.length === 0) {
                loadVerses();
            }
        });
    }
    
    // Scroll events
    const bibleText = document.getElementById('bibleText');
    if (bibleText) {
        // Auto-select verse on scroll
        let scrollTimeout;
        bibleText.addEventListener('scroll', () => {
            if (isAutoScrolling || !scrollSyncEnabled) return;
            
            clearTimeout(scrollTimeout);
            scrollTimeout = setTimeout(() => {
                const container = document.getElementById('bibleText');
                const verses = container.querySelectorAll('.verse');
                const containerRect = container.getBoundingClientRect();
                const headerOffset = 140;
                
                for (let verse of verses) {
                    const rect = verse.getBoundingClientRect();
                    
                    if (rect.top >= containerRect.top + headerOffset && 
                        rect.top <= containerRect.top + (containerRect.height * 0.4)) {
                        const versId = parseInt(verse.dataset.versId);
                        if (versId && currentVerse !== versId) {
                            selectVerse(versId);
                        }
                        break;
                    }
                }
            }, 500);
        });
        
        // Infinite scroll
        bibleText.addEventListener('scroll', (e) => {
            const element = e.target;
            
            // Scroll down - load more verses
            if (element.scrollHeight - element.scrollTop <= element.clientHeight + 100) {
                loadVerses(true);
            }
        });
    }
}

/**
 * Laad verses
 */
async function loadVerses(append = false) {
    if (loading || (allLoaded && append)) return;
    
    loading = true;
    const container = document.getElementById('bibleText');
    if (!container) {
        loading = false;
        return;
    }
    
    if (!append) {
        container.innerHTML = '<div class="text-center py-5"><div class="spinner-border" role="status"></div><p class="mt-2">Laden...</p></div>';
        currentOffset = 0;
        allLoaded = false;
        lastChapter = null;
        selectedChapter = currentChapter;
        continuousScrolling = false;
        lastLoadedVersId = null;
        firstLoadedVersId = null;
        allLoadedBackward = false;
    }
    
    const params = new URLSearchParams({
        limit: 50
    });
    
    if (currentBook) params.append('boek', currentBook);
    if (currentProfile) params.append('profiel_id', currentProfile);
    
    if (continuousScrolling && lastLoadedVersId) {
        params.append('after_vers_id', lastLoadedVersId);
        params.append('offset', 0);
    } else {
        params.append('offset', currentOffset);
        if (currentChapter) {
            params.append('hoofdstuk', currentChapter);
        }
    }
    
    const verses = await apiCall('verses&' + params.toString());
    
    if (!verses || verses.length === 0) {
        if (!append) {
            container.innerHTML = '<div class="text-center py-5 text-muted">Geen verzen gevonden</div>';
            allLoaded = true;
        } else if (currentChapter && !continuousScrolling && lastLoadedVersId) {
            continuousScrolling = true;
            loading = false;
            loadVerses(true);
            return;
        } else {
            allLoaded = true;
        }
        loading = false;
        return;
    }
    
    if (!append) {
        container.innerHTML = '';
    }
    
    if (verses.length > 0) {
        lastLoadedVersId = verses[verses.length - 1].Vers_ID;
        if (!firstLoadedVersId) {
            firstLoadedVersId = verses[0].Vers_ID;
        }
    }
    
    for (const verse of verses) {
        // Check if we need a chapter header
        const chapterKey = `${verse.Bijbelboeknaam}_${verse.Hoofdstuknummer}`;
        if (lastChapter !== chapterKey) {
            const chapterHeader = document.createElement('div');
            chapterHeader.className = 'chapter-header';
            chapterHeader.textContent = `${verse.Bijbelboeknaam} ${verse.Hoofdstuknummer}`;
            container.appendChild(chapterHeader);
            lastChapter = chapterKey;
        }
        
        // Add verse
        const verseSpan = document.createElement('span');
        verseSpan.className = 'verse';
        verseSpan.dataset.versId = verse.Vers_ID;
        
        const reference = document.createElement('sup');
        reference.className = 'verse-reference';
        reference.textContent = verse.Versnummer;
        
        const text = document.createElement('span');
        text.className = 'verse-text';
        
        let displayText = verse.Opgemaakte_Tekst || verse.Tekst;
        
        // Highlight search terms
        if (searchQuery) {
            const regex = new RegExp(`(${searchQuery})`, 'gi');
            displayText = displayText.replace(regex, '<mark>$1</mark>');
        }
        
        text.innerHTML = displayText;
        
        verseSpan.appendChild(reference);
        verseSpan.appendChild(text);
        verseSpan.appendChild(document.createTextNode(' '));
        
        verseSpan.addEventListener('click', () => {
            isAutoScrolling = true;
            selectVerse(verse.Vers_ID, true);
            setTimeout(() => isAutoScrolling = false, 1000);
        });
        
        container.appendChild(verseSpan);
    }
    
    currentOffset += verses.length;
    loading = false;
    
    if (verses.length < 50) {
        if (continuousScrolling || !currentChapter) {
            allLoaded = true;
        }
    }
}

/**
 * Selecteer een vers
 */
async function selectVerse(versId, fromClick = false) {
    if (currentVerse === versId && !fromClick) return;
    
    // Update active state
    document.querySelectorAll('.verse').forEach(v => v.classList.remove('active'));
    const verseElement = document.querySelector(`[data-vers-id="${versId}"]`);
    if (verseElement) {
        verseElement.classList.add('active');
    }
    
    currentVerse = versId;
    localStorage.setItem('lastVerse', versId);
    
    // Update timeline focus
    if (typeof updateTimelineFocus === 'function') {
        const fromTimeline = window.timelineClickInProgress || false;
        if (!fromTimeline) {
            updateTimelineFocus(versId);
        }
    }
    
    // Update map
    if (verseElement && typeof highlightLocationsForVerse === 'function') {
        const verseText = verseElement.querySelector('.verse-text')?.textContent || '';
        highlightLocationsForVerse(versId, verseText, fromClick);
    }
}

// Export functies
if (typeof window !== 'undefined') {
    window.initReader = initReader;
    window.loadVerses = loadVerses;
    window.selectVerse = selectVerse;
    window.isAutoScrolling = false;
}

// Start reader when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        if (typeof mode !== 'undefined' && mode === 'reader') {
            initReader();
        }
    });
} else {
    if (typeof mode !== 'undefined' && mode === 'reader') {
        initReader();
    }
}

console.log('Reader module loaded');
