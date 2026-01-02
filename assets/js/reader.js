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
let searchQuery = '';  // Search query for highlighting

// Continuous scrolling support
let continuousScrolling = false;  // Start in chapter mode
let lastLoadedVersId = null;      // Track last verse for continuous loading
let selectedChapter = null;        // Remember originally selected chapter

// Initialize reader
async function initReader() {
    console.log('ðŸš€ Initializing reader...');
    
    // Load books
    console.log('ðŸ“š Loading books...');
    try {
        const books = await apiCall('books');
        console.log('ðŸ“š Books received:', books);
        
        if (!books) {
            console.error('âŒ No books data received!');
            return;
        }
        
        if (!Array.isArray(books)) {
            console.error('âŒ Books is not an array:', typeof books);
            return;
        }
        
        const bookSelect = document.getElementById('bookSelect');
        if (!bookSelect) {
            console.error('âŒ bookSelect element not found!');
            return;
        }
        
        console.log(`ðŸ“š Populating dropdown with ${books.length} books...`);
        
        books.forEach((book, index) => {
            const option = document.createElement('option');
            option.value = book.Bijbelboeknaam;
            option.textContent = book.Bijbelboeknaam;
            bookSelect.appendChild(option);
            
            if (index < 3) {
                console.log(`  âœ… Added: ${book.Bijbelboeknaam}`);
            }
        });
        
        console.log(`âœ… Books loaded: ${books.length} books in dropdown`);
        
    } catch (error) {
        console.error('âŒ Error loading books:', error);
    }
    
    // Load profiles
    console.log('ðŸ“‹ Loading profiles...');
    try {
        const profiles = await apiCall('profiles');
        console.log('ðŸ“‹ Profiles received:', profiles);
        
        if (profiles && Array.isArray(profiles)) {
            const profileSelect = document.getElementById('profileSelect');
            if (profileSelect) {
                console.log(`ðŸ“‹ Populating dropdown with ${profiles.length} profiles...`);
                
                profiles.forEach((profile, index) => {
                    const option = document.createElement('option');
                    option.value = profile.Profiel_ID;
                    option.textContent = profile.Profiel_Naam;
                    profileSelect.appendChild(option);
                    
                    if (index < 3) {
                        console.log(`  âœ… Added: ${profile.Profiel_Naam} (ID: ${profile.Profiel_ID})`);
                    }
                });
                
                console.log(`âœ… Profiles loaded: ${profiles.length} profiles in dropdown`);
            } else {
                console.error('âŒ profileSelect element not found!');
            }
        } else {
            console.error('âŒ No profiles data or not an array');
        }
        
    } catch (error) {
        console.error('âŒ Error loading profiles:', error);
    }
    
    // Setup event listeners
    console.log('ðŸ”§ Setting up event listeners...');
    setupEventListeners();
    
    // Restore saved values from localStorage
    const savedProfile = localStorage.getItem('reader_profile');
    const savedBook = localStorage.getItem('reader_book');
    const savedChapter = localStorage.getItem('reader_chapter');
    
    console.log('ðŸ’¾ Restoring saved values:', { savedProfile, savedBook, savedChapter });
    
    // Restore profile
    if (savedProfile) {
        const profileSelect = document.getElementById('profileSelect');
        if (profileSelect) {
            profileSelect.value = savedProfile;
            currentProfile = savedProfile;
            console.log('âœ… Restored profile:', savedProfile);
        }
    }
    
    // Restore book and load its chapters
    if (savedBook) {
        const bookSelect = document.getElementById('bookSelect');
        if (bookSelect) {
            bookSelect.value = savedBook;
            currentBook = savedBook;
            console.log('âœ… Restored book:', savedBook);
            
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
                    console.log('âœ… Restored chapter:', savedChapter);
                }
            }
        }
    }
    
    // Load initial content
    console.log('ðŸ“– Loading initial verses...');
    await loadVerses();
    
    // Setup infinite scroll
    console.log('â™¾ï¸ Setting up infinite scroll...');
    const bibleTextContainer = document.getElementById('bibleText');
    if (bibleTextContainer) {
        bibleTextContainer.addEventListener('scroll', (e) => {
            const element = e.target;
            
            // Check if scrolled near bottom (100px threshold)
            const nearBottom = element.scrollHeight - element.scrollTop <= element.clientHeight + 100;
            
            if (nearBottom && !loading && !allLoaded) {
                console.log('ðŸ“œ Near bottom - loading more verses...');
                loadVerses(true); // Append mode
            }
        });
        console.log('âœ… Infinite scroll activated!');
    } else {
        console.error('âŒ bibleText container not found for scroll listener!');
    }
    
    console.log('âœ… Reader initialized successfully!');
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
            
            console.log('ðŸ“‹ Profile changed to:', currentProfile);
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
    
    // Clean duplicate chapter headers BEFORE loading new verses
    if (append) {
        const seen = new Set();
        const duplicates = [];
        
        const container = document.getElementById('bibleText');
        if (container) {
            container.querySelectorAll('.chapter-header').forEach(header => {
                const text = header.textContent.trim();
                if (seen.has(text)) {
                    duplicates.push(header);
                } else {
                    seen.add(text);
                }
            });
            
            if (duplicates.length > 0) {
                console.log(`🧹 Removing ${duplicates.length} duplicate headers`);
                duplicates.forEach(dup => dup.remove());
            }
        }
    }
    
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
    
    // Add search parameter for filtering
    if (searchQuery && searchQuery.trim().length > 0) {
        params.append('search', searchQuery.trim());
        console.log('🔍 Search query:', searchQuery.trim());
    }
    
    // CONTINUOUS SCROLLING MODE: Use after_vers_id to load next verses
    if (continuousScrolling && lastLoadedVersId) {
        params.append('after_vers_id', lastLoadedVersId);
        params.append('offset', 0);
        console.log('ðŸ”„ Continuous mode: Loading after vers_id', lastLoadedVersId);
    } else {
        params.append('offset', currentOffset);
        // Only filter by chapter if NOT in continuous mode
        if (currentChapter) {
            params.append('hoofdstuk', currentChapter);
        }
    }
    
    const url = 'verses&' + params.toString();
    console.log('ðŸ“– Loading verses:', url);
    console.log('   Current profile:', currentProfile);
    console.log('   Continuous mode:', continuousScrolling);
    
    const verses = await apiCall(url);
    
    if (!verses || verses.length === 0) {
        if (!append) {
            container.innerHTML = '<div class="text-center py-5 text-muted">Geen verzen gevonden</div>';
            allLoaded = true;
        } else if (selectedChapter && !continuousScrolling && lastLoadedVersId) {
            // Selected chapter is complete â†’ Switch to continuous scrolling!
            console.log('âœ… Chapter complete! Enabling continuous scrolling from vers_id:', lastLoadedVersId);
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
    
    console.log(`âœ… Loaded ${verses.length} verses: ${withOpmaak} with opmaak, ${withoutOpmaak} without`);
    
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
        // Add chapter header (check if not already exists in DOM)
        const chapterKey = `${verse.Bijbelboeknaam}_${verse.Hoofdstuknummer}`;
        if (lastChapter !== chapterKey) {
            // Check if this chapter header already exists in container
            const chapterText = `${verse.Bijbelboeknaam} ${verse.Hoofdstuknummer}`;
            const existingHeaders = Array.from(container.querySelectorAll('.chapter-header'));
            const headerExists = existingHeaders.some(h => h.textContent.trim() === chapterText);
            
            if (!headerExists) {
                const header = document.createElement('div');
                header.className = 'chapter-header';
                header.textContent = chapterText;
                container.appendChild(header);
            }
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
        
        // Apply search highlighting if query exists
        let textContent = verse.Opgemaakte_Tekst || verse.Tekst;
        if (searchQuery && searchQuery.trim().length > 0) {
            textContent = highlightSearchTerms(textContent, searchQuery.trim());
        }
        text.innerHTML = textContent;
        
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
            // In continuous mode OR no chapter selected â†’ truly done
            allLoaded = true;
            console.log('ðŸ All verses loaded (end of content)');
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
// Select verse
function selectVerse(versId) {
    document.querySelectorAll('.verse').forEach(v => v.classList.remove('active'));
    const verseElement = document.querySelector(`[data-vers-id="${versId}"]`);
    if (verseElement) {
        verseElement.classList.add('active');
        
        // Get verse text for location matching
        const verseTextElement = verseElement.querySelector('.verse-text');
        if (verseTextElement) {
            // Get plain text (strip HTML if any)
            const verseText = verseTextElement.textContent || verseTextElement.innerText;
            
            // Check for locations in verse text and highlight on map
            if (typeof window.highlightLocationFromVerse === 'function') {
                window.highlightLocationFromVerse(verseText);
            }
        }
        
        // Sync timeline to this verse
        if (typeof window.syncTimelineToVerse === 'function') {
            window.syncTimelineToVerse(versId);
        }
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

// ============= SEARCH HIGHLIGHTING =============

/**
 * Highlight search terms in text
 * @param {string} text - HTML text to search in
 * @param {string} query - Search query
 * @returns {string} Text with highlighted search terms
 */
function highlightSearchTerms(text, query) {
    if (!query || query.trim().length === 0) {
        return text;
    }
    
    // Escape special regex characters in query
    const escapedQuery = query.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
    
    // Create regex for case-insensitive matching
    // Use word boundaries for whole word matching, or partial for flexibility
    const regex = new RegExp(`(${escapedQuery})`, 'gi');
    
    // Temporarily replace HTML tags with placeholders to avoid breaking them
    const tagPattern = /<[^>]+>/g;
    const tags = [];
    let tagIndex = 0;
    
    // Store tags and replace with placeholders
    let processedText = text.replace(tagPattern, (match) => {
        tags.push(match);
        return `___TAG_${tagIndex++}___`;
    });
    
    // Apply highlighting to text between tags
    processedText = processedText.replace(regex, '<mark class="search-highlight">$1</mark>');
    
    // Restore original tags
    processedText = processedText.replace(/___TAG_(\d+)___/g, (match, index) => {
        return tags[parseInt(index)];
    });
    
    return processedText;
}

// Make functions global
window.initReader = initReader;
window.toggleTimelineFilter = toggleTimelineFilter;
window.navigateTimelinePrev = navigateTimelinePrev;
window.navigateTimelineNext = navigateTimelineNext;
window.highlightSearchTerms = highlightSearchTerms;

console.log('Reader.js loaded');