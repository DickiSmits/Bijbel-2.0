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

// Continuous scrolling support
let continuousScrolling = false;  // Start in chapter mode
let lastLoadedVersId = null;      // Track last verse for continuous loading
let selectedChapter = null;        // Remember originally selected chapter

// Initialize reader
async function initReader() {
    console.log('🚀 Initializing reader...');
    
    // Load books
    console.log('📚 Loading books...');
    try {
        const books = await apiCall('books');
        console.log('📚 Books received:', books);
        
        if (!books) {
            console.error('❌ No books data received!');
            return;
        }
        
        if (!Array.isArray(books)) {
            console.error('❌ Books is not an array:', typeof books);
            return;
        }
        
        const bookSelect = document.getElementById('bookSelect');
        if (!bookSelect) {
            console.error('❌ bookSelect element not found!');
            return;
        }
        
        console.log(`📚 Populating dropdown with ${books.length} books...`);
        
        books.forEach((book, index) => {
            const option = document.createElement('option');
            option.value = book.Bijbelboeknaam;
            option.textContent = book.Bijbelboeknaam;
            bookSelect.appendChild(option);
            
            if (index < 3) {
                console.log(`  ✅ Added: ${book.Bijbelboeknaam}`);
            }
        });
        
        console.log(`✅ Books loaded: ${books.length} books in dropdown`);
        
    } catch (error) {
        console.error('❌ Error loading books:', error);
    }
    
    // Load profiles
    console.log('📋 Loading profiles...');
    try {
        const profiles = await apiCall('profiles');
        console.log('📋 Profiles received:', profiles);
        
        if (profiles && Array.isArray(profiles)) {
            const profileSelect = document.getElementById('profileSelect');
            if (profileSelect) {
                console.log(`📋 Populating dropdown with ${profiles.length} profiles...`);
                
                profiles.forEach((profile, index) => {
                    const option = document.createElement('option');
                    option.value = profile.Profiel_ID;
                    option.textContent = profile.Profiel_Naam;
                    profileSelect.appendChild(option);
                    
                    if (index < 3) {
                        console.log(`  ✅ Added: ${profile.Profiel_Naam} (ID: ${profile.Profiel_ID})`);
                    }
                });
                
                console.log(`✅ Profiles loaded: ${profiles.length} profiles in dropdown`);
            } else {
                console.error('❌ profileSelect element not found!');
            }
        } else {
            console.error('❌ No profiles data or not an array');
        }
        
    } catch (error) {
        console.error('❌ Error loading profiles:', error);
    }
    
    // Setup event listeners
    console.log('🔧 Setting up event listeners...');
    setupEventListeners();
    
    // Restore saved values from localStorage
    const savedProfile = localStorage.getItem('reader_profile');
    const savedBook = localStorage.getItem('reader_book');
    const savedChapter = localStorage.getItem('reader_chapter');
    
    console.log('💾 Restoring saved values:', { savedProfile, savedBook, savedChapter });
    
    // Restore profile
    if (savedProfile) {
        const profileSelect = document.getElementById('profileSelect');
        if (profileSelect) {
            profileSelect.value = savedProfile;
            currentProfile = savedProfile;
            console.log('✅ Restored profile:', savedProfile);
        }
    }
    
    // Restore book and load its chapters
    if (savedBook) {
        const bookSelect = document.getElementById('bookSelect');
        if (bookSelect) {
            bookSelect.value = savedBook;
            currentBook = savedBook;
            console.log('✅ Restored book:', savedBook);
            
            // Load chapters for this book
            const chapters = await apiCall(`chapters&boek=${encodeURIComponent(savedBook)}`);
            const chapterSelect = document.getElementById('chapterSelect');
            chapterSelect.innerHTML = '<option value="">Alle hoofdstukken</option>';
            
            if (chapters) {
                chapters.forEach(ch => {
                    const option = document.createElement('option');
                    option.value = ch.Hoofdstuknummer;
                    option.textContent = `Hoofdstuk ${ch.Hoofdstuknummer}`;
                    chapterSelect.appendChild(option);
                });
                
                // Restore chapter
                if (savedChapter) {
                    chapterSelect.value = savedChapter;
                    currentChapter = savedChapter;
                    console.log('✅ Restored chapter:', savedChapter);
                }
            }
        }
    }
    
    // Load initial content
    console.log('📖 Loading initial verses...');
    await loadVerses();
    
    // Setup infinite scroll
    console.log('♾️ Setting up infinite scroll...');
    const bibleTextContainer = document.getElementById('bibleText');
    if (bibleTextContainer) {
        bibleTextContainer.addEventListener('scroll', (e) => {
            const element = e.target;
            
            // Check if scrolled near bottom (100px threshold)
            const nearBottom = element.scrollHeight - element.scrollTop <= element.clientHeight + 100;
            
            if (nearBottom && !loading && !allLoaded) {
                console.log('📜 Near bottom - loading more verses...');
                loadVerses(true); // Append mode
            }
        });
        console.log('✅ Infinite scroll activated!');
    } else {
        console.error('❌ bibleText container not found for scroll listener!');
    }
    
    console.log('✅ Reader initialized successfully!');
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
            
            // Save to localStorage
            if (currentBook) {
                localStorage.setItem('reader_book', currentBook);
                localStorage.removeItem('reader_chapter');
            } else {
                localStorage.removeItem('reader_book');
                localStorage.removeItem('reader_chapter');
            }
            
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
            
            // Save to localStorage
            if (currentChapter) {
                localStorage.setItem('reader_chapter', currentChapter);
            } else {
                localStorage.removeItem('reader_chapter');
            }
            
            loadVerses();
        });
    }
    
    if (profileSelect) {
        profileSelect.addEventListener('change', (e) => {
            currentProfile = e.target.value || null;
            
            // Save to localStorage
            if (currentProfile) {
                localStorage.setItem('reader_profile', currentProfile);
            } else {
                localStorage.removeItem('reader_profile');
            }
            
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
        continuousScrolling = false;
        lastLoadedVersId = null;
        selectedChapter = currentChapter; // Remember what user selected
    }
    
    const params = new URLSearchParams({
        limit: 50
    });
    
    if (currentBook) params.append('boek', currentBook);
    if (currentProfile) params.append('profiel_id', currentProfile);
    
    // CONTINUOUS SCROLLING MODE: Use after_vers_id to load next verses
    if (continuousScrolling && lastLoadedVersId) {
        params.append('after_vers_id', lastLoadedVersId);
        params.append('offset', 0);
        console.log('🔄 Continuous mode: Loading after vers_id', lastLoadedVersId);
    } else {
        params.append('offset', currentOffset);
        // Only filter by chapter if NOT in continuous mode
        if (currentChapter) {
            params.append('hoofdstuk', currentChapter);
        }
    }
    
    const url = 'verses&' + params.toString();
    console.log('📖 Loading verses:', url);
    console.log('   Current profile:', currentProfile);
    console.log('   Continuous mode:', continuousScrolling);
    
    const verses = await apiCall(url);
    
    if (!verses || verses.length === 0) {
        if (!append) {
            container.innerHTML = '<div class="text-center py-5 text-muted">Geen verzen gevonden</div>';
            allLoaded = true;
        } else if (selectedChapter && !continuousScrolling && lastLoadedVersId) {
            // Selected chapter is complete → Switch to continuous scrolling!
            console.log('✅ Chapter complete! Enabling continuous scrolling from vers_id:', lastLoadedVersId);
            continuousScrolling = true;
            loading = false;
            loadVerses(true); // Load more (now without chapter filter)
            return;
        } else {
            allLoaded = true;
        }
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
    
    // Track last loaded verse for continuous scrolling
    if (verses.length > 0) {
        lastLoadedVersId = verses[verses.length - 1].Vers_ID;
        console.log('   Last loaded Vers_ID:', lastLoadedVersId);
    }
    
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
    
    // Smart allLoaded detection
    if (verses.length < 50) {
        if (continuousScrolling || !currentChapter) {
            // In continuous mode OR no chapter selected → truly done
            allLoaded = true;
            console.log('🏁 All verses loaded (end of content)');
        }
        // If we have a chapter filter and got < 50, we'll switch to continuous on next scroll
    }
    
    // Laad profiel mappings voor nieuwe verzen
    if (!append && typeof window.loadChapterProfiles === 'function') {
        await window.loadChapterProfiles();
    } else if (append && typeof window.updateVerseNumberIndicators === 'function') {
        window.updateVerseNumberIndicators();
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
