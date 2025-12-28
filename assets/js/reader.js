/**
 * Bijbelreader - Reader Module
 * Core functionaliteit voor het lezen van bijbelteksten
 */

console.log('📦 Loading reader.js...');

// Global reader variabelen
window.currentBook = null;
window.currentChapter = null;
window.currentVerse = null;
window.currentProfile = null;
window.currentOffset = 0;
window.loading = false;
window.allLoaded = false;
window.searchQuery = '';
window.lastChapter = null;
window.isAutoScrolling = false;

/**
 * Initialiseer reader mode
 */
window.initReader = async function() {
    console.log('🚀 Initializing reader...');
    
    try {
        // Load books
        const books = await window.apiCall('books');
        const bookSelect = document.getElementById('bookSelect');
        if (books && bookSelect) {
            books.forEach(book => {
                const option = document.createElement('option');
                option.value = book.Bijbelboeknaam;
                option.textContent = book.Bijbelboeknaam;
                bookSelect.appendChild(option);
            });
            console.log('✓ Loaded', books.length, 'books');
        } else {
            console.error('❌ Failed to load books');
        }
        
        // Load profiles
        const profiles = await window.apiCall('profiles');
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
                window.currentProfile = lastProfile;
                profileSelect.value = window.currentProfile;
            } else if (profiles.length > 0) {
                window.currentProfile = profiles[0].Profiel_ID;
                profileSelect.value = window.currentProfile;
            }
            console.log('✓ Loaded', profiles.length, 'profiles');
        }
        
        // Restore last position
        const lastBook = localStorage.getItem('lastBook');
        const lastChapter = localStorage.getItem('lastChapter');
        
        if (lastBook && books && books.some(b => b.Bijbelboeknaam === lastBook)) {
            bookSelect.value = lastBook;
            window.currentBook = lastBook;
            
            const chapters = await window.apiCall(`chapters&boek=${encodeURIComponent(lastBook)}`);
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
                    window.currentChapter = lastChapter;
                }
            }
        }
        
        // Setup event listeners
        window.setupEventListeners();
        
        // Initialize components
        if (typeof window.initMap === 'function') {
            console.log('Starting map...');
            window.initMap();
        }
        
        if (typeof window.initTimeline === 'function') {
            console.log('Starting timeline...');
            window.initTimeline();
        }
        
        // Load data
        console.log('Loading verses...');
        await window.loadVerses();
        
        if (typeof window.loadAllLocationsOnMap === 'function') {
            console.log('Loading locations...');
            await window.loadAllLocationsOnMap();
        }
        
        if (typeof window.loadTimelineEvents === 'function') {
            console.log('Loading timeline events...');
            await window.loadTimelineEvents();
        }
        
        // Scroll to last verse if available
        const lastVerse = localStorage.getItem('lastVerse');
        if (lastVerse) {
            setTimeout(() => {
                const verseElement = document.querySelector(`[data-vers-id="${lastVerse}"]`);
                if (verseElement) {
                    verseElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    window.selectVerse(parseInt(lastVerse), false);
                }
            }, 500);
        }
        
        console.log('✅ Reader initialized successfully!');
        
    } catch (error) {
        console.error('❌ Error initializing reader:', error);
    }
};

/**
 * Setup event listeners
 */
window.setupEventListeners = function() {
    // Book select
    const bookSelect = document.getElementById('bookSelect');
    if (bookSelect) {
        bookSelect.addEventListener('change', async (e) => {
            window.currentBook = e.target.value;
            window.currentChapter = null;
            
            localStorage.setItem('lastBook', window.currentBook);
            localStorage.removeItem('lastChapter');
            localStorage.removeItem('lastVerse');
            
            const chapters = await window.apiCall(`chapters&boek=${encodeURIComponent(window.currentBook)}`);
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
            
            window.loadVerses();
        });
    }
    
    // Chapter select
    const chapterSelect = document.getElementById('chapterSelect');
    if (chapterSelect) {
        chapterSelect.addEventListener('change', (e) => {
            window.currentChapter = e.target.value;
            
            if (window.currentChapter) {
                localStorage.setItem('lastChapter', window.currentChapter);
            } else {
                localStorage.removeItem('lastChapter');
            }
            localStorage.removeItem('lastVerse');
            
            window.loadVerses();
        });
    }
    
    // Profile select
    const profileSelect = document.getElementById('profileSelect');
    if (profileSelect) {
        profileSelect.addEventListener('change', (e) => {
            window.currentProfile = e.target.value || null;
            
            if (window.currentProfile) {
                localStorage.setItem('lastProfile', window.currentProfile);
            } else {
                localStorage.removeItem('lastProfile');
            }
            
            window.loadVerses();
        });
    }
    
    // Search input
    const searchInput = document.getElementById('searchInput');
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            window.searchQuery = e.target.value;
            if (window.searchQuery.length > 2 || window.searchQuery.length === 0) {
                window.loadVerses();
            }
        });
    }
    
    // Infinite scroll
    const bibleText = document.getElementById('bibleText');
    if (bibleText) {
        bibleText.addEventListener('scroll', (e) => {
            const element = e.target;
            if (element.scrollHeight - element.scrollTop <= element.clientHeight + 100) {
                window.loadVerses(true);
            }
        });
    }
};

/**
 * Laad verses
 */
window.loadVerses = async function(append = false) {
    if (window.loading || (window.allLoaded && append)) return;
    
    window.loading = true;
    const container = document.getElementById('bibleText');
    if (!container) {
        window.loading = false;
        return;
    }
    
    if (!append) {
        container.innerHTML = '<div class="text-center py-5"><div class="spinner-border" role="status"></div><p class="mt-2">Laden...</p></div>';
        window.currentOffset = 0;
        window.allLoaded = false;
        window.lastChapter = null;
    }
    
    const params = new URLSearchParams({
        limit: 50,
        offset: window.currentOffset
    });
    
    if (window.currentBook) params.append('boek', window.currentBook);
    if (window.currentChapter) params.append('hoofdstuk', window.currentChapter);
    if (window.currentProfile) params.append('profiel_id', window.currentProfile);
    
    const verses = await window.apiCall('verses&' + params.toString());
    
    if (!verses || verses.length === 0) {
        if (!append) {
            container.innerHTML = '<div class="text-center py-5 text-muted">Geen verzen gevonden</div>';
        }
        window.allLoaded = true;
        window.loading = false;
        return;
    }
    
    if (!append) {
        container.innerHTML = '';
    }
    
    for (const verse of verses) {
        const chapterKey = `${verse.Bijbelboeknaam}_${verse.Hoofdstuknummer}`;
        if (window.lastChapter !== chapterKey) {
            const chapterHeader = document.createElement('div');
            chapterHeader.className = 'chapter-header';
            chapterHeader.textContent = `${verse.Bijbelboeknaam} ${verse.Hoofdstuknummer}`;
            container.appendChild(chapterHeader);
            window.lastChapter = chapterKey;
        }
        
        const verseSpan = document.createElement('span');
        verseSpan.className = 'verse';
        verseSpan.dataset.versId = verse.Vers_ID;
        
        const reference = document.createElement('sup');
        reference.className = 'verse-reference';
        reference.textContent = verse.Versnummer;
        
        const text = document.createElement('span');
        text.className = 'verse-text';
        text.innerHTML = verse.Opgemaakte_Tekst || verse.Tekst;
        
        verseSpan.appendChild(reference);
        verseSpan.appendChild(text);
        verseSpan.appendChild(document.createTextNode(' '));
        
        verseSpan.addEventListener('click', () => {
            window.isAutoScrolling = true;
            window.selectVerse(verse.Vers_ID, true);
            setTimeout(() => window.isAutoScrolling = false, 1000);
        });
        
        container.appendChild(verseSpan);
    }
    
    window.currentOffset += verses.length;
    window.loading = false;
    
    if (verses.length < 50) {
        window.allLoaded = true;
    }
};

/**
 * Selecteer een vers
 */
window.selectVerse = function(versId, fromClick = false) {
    document.querySelectorAll('.verse').forEach(v => v.classList.remove('active'));
    const verseElement = document.querySelector(`[data-vers-id="${versId}"]`);
    if (verseElement) {
        verseElement.classList.add('active');
    }
    
    window.currentVerse = versId;
    localStorage.setItem('lastVerse', versId);
    
    if (typeof window.updateTimelineFocus === 'function') {
        window.updateTimelineFocus(versId);
    }
    
    if (verseElement && typeof window.highlightLocationsForVerse === 'function') {
        const verseText = verseElement.querySelector('.verse-text')?.textContent || '';
        window.highlightLocationsForVerse(versId, verseText, fromClick);
    }
};

console.log('✓ Reader module loaded');
