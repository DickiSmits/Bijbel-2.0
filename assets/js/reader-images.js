/**
 * READER IMAGES MODULE - BULLETPROOF VERSION
 * Met event listeners en MutationObserver voor 100% betrouwbaarheid
 */

console.log('🖼️ [IMAGES] Loading bulletproof reader images module...');

// Track loaded verses to prevent duplicate loading
const loadedVerses = new Set();

/**
 * Load images for all visible verses
 */
async function loadVerseImages() {
    console.log('📸 [IMAGES] Starting loadVerseImages...');
    
    const verses = document.querySelectorAll('.verse[data-vers-id]');
    
    if (verses.length === 0) {
        console.log('⚠️ [IMAGES] No verses found');
        return;
    }
    
    console.log(`📊 [IMAGES] Found ${verses.length} verses`);
    
    let newlyLoaded = 0;
    
    for (const verseElement of verses) {
        const versId = verseElement.dataset.versId;
        
        // Skip if already loaded
        if (loadedVerses.has(versId)) {
            continue;
        }
        
        try {
            const images = await apiCall(`verse_images&vers_id=${versId}`);
            
            if (images && images.length > 0) {
                console.log(`✅ [IMAGES] Found ${images.length} image(s) for verse ${versId}`);
                displayVerseImages(versId, images, verseElement);
                newlyLoaded++;
            }
            
            // Mark as loaded
            loadedVerses.add(versId);
            
        } catch (error) {
            console.warn(`❌ [IMAGES] Error for verse ${versId}:`, error);
            loadedVerses.add(versId); // Mark anyway to prevent retry
        }
    }
    
    if (newlyLoaded > 0) {
        console.log(`✅ [IMAGES] Loaded ${newlyLoaded} new images`);
    }
}

/**
 * Display images for a verse
 */
function displayVerseImages(versId, images, verseElement) {
    images.forEach(img => {
        const imgContainer = document.createElement('div');
        imgContainer.className = 'verse-image my-3';
        imgContainer.dataset.imageId = img.Afbeelding_ID;
        imgContainer.dataset.versId = versId;
        
        // Apply alignment
        let alignmentClass = 'text-center';
        if (img.Uitlijning === 'left') alignmentClass = 'text-start';
        if (img.Uitlijning === 'right') alignmentClass = 'text-end';
        imgContainer.className += ` ${alignmentClass}`;
        
        // Build image element
        const width = img.Breedte || 400;
        const height = img.Hoogte ? `height: ${img.Hoogte}px;` : '';
        const caption = img.Caption ? `<p class="text-muted small mt-2 mb-0"><i class="bi bi-image"></i> ${escapeHtml(img.Caption)}</p>` : '';
        
        imgContainer.innerHTML = `
            <div class="verse-image-wrapper d-inline-block" style="max-width: 100%;">
                <img src="${img.Bestandspad}" 
                     alt="${img.Caption || ''}" 
                     class="img-fluid rounded shadow-sm verse-image-img"
                     style="max-width: ${width}px; ${height} cursor: pointer;"
                     onclick="openImageFullscreen('${img.Bestandspad}', '${escapeForJs(img.Caption || '')}')"
                     onerror="console.error('[IMAGES] Failed to load:', '${img.Bestandspad}'); this.style.display='none';">
                ${caption}
            </div>
        `;
        
        // Insert after verse
        verseElement.after(imgContainer);
    });
}

/**
 * Clear images for verses that are no longer in DOM
 */
function clearRemovedImages() {
    console.log('🧹 [IMAGES] Clearing removed images...');
    
    const currentVerses = new Set(
        Array.from(document.querySelectorAll('.verse[data-vers-id]'))
            .map(v => v.dataset.versId)
    );
    
    // Remove from loaded set if verse is gone
    for (const versId of loadedVerses) {
        if (!currentVerses.has(versId)) {
            loadedVerses.delete(versId);
        }
    }
    
    console.log(`ℹ️ [IMAGES] Tracking ${loadedVerses.size} loaded verses`);
}

/**
 * Open image in fullscreen modal
 */
function openImageFullscreen(imagePath, caption) {
    let modal = document.getElementById('readerImageModal');
    
    if (!modal) {
        const modalHtml = `
            <div class="modal fade" id="readerImageModal" tabindex="-1">
                <div class="modal-dialog modal-xl modal-dialog-centered">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="readerImageModalTitle">Afbeelding</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body text-center p-0">
                            <img id="readerImageModalImg" 
                                 class="img-fluid" 
                                 style="max-height: 80vh; width: auto;">
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        modal = document.getElementById('readerImageModal');
    }
    
    const img = document.getElementById('readerImageModalImg');
    const title = document.getElementById('readerImageModalTitle');
    
    if (img) img.src = imagePath;
    if (title) title.textContent = caption || 'Afbeelding';
    
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

/**
 * Escape HTML
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Escape for JS
 */
function escapeForJs(text) {
    return text.replace(/'/g, "\\'").replace(/"/g, '\\"').replace(/\n/g, '\\n');
}

/**
 * Setup event listeners for book/chapter changes
 */
function setupEventListeners() {
    console.log('🎧 [IMAGES] Setting up event listeners...');
    
    const bookSelect = document.getElementById('bookSelect');
    const chapterSelect = document.getElementById('chapterSelect');
    
    if (bookSelect) {
        bookSelect.addEventListener('change', () => {
            console.log('📖 [IMAGES] Book changed, will reload images...');
            clearRemovedImages();
            // Wait for verses to load, then load images
            setTimeout(() => {
                console.log('⏰ [IMAGES] Loading images after book change...');
                loadVerseImages();
            }, 1500);
        });
        console.log('✅ [IMAGES] Book select listener added');
    }
    
    if (chapterSelect) {
        chapterSelect.addEventListener('change', () => {
            console.log('📑 [IMAGES] Chapter changed, will reload images...');
            clearRemovedImages();
            // Wait for verses to load, then load images
            setTimeout(() => {
                console.log('⏰ [IMAGES] Loading images after chapter change...');
                loadVerseImages();
            }, 1500);
        });
        console.log('✅ [IMAGES] Chapter select listener added');
    }
}

/**
 * Setup MutationObserver to detect new verses
 */
function setupMutationObserver() {
    console.log('👁️ [IMAGES] Setting up MutationObserver...');
    
    const bibleText = document.getElementById('bibleText');
    if (!bibleText) {
        console.warn('⚠️ [IMAGES] bibleText container not found');
        return;
    }
    
    const observer = new MutationObserver((mutations) => {
        let hasNewVerses = false;
        
        for (const mutation of mutations) {
            if (mutation.addedNodes.length > 0) {
                // Check if any added nodes are verses
                for (const node of mutation.addedNodes) {
                    if (node.nodeType === 1) { // Element node
                        if (node.classList && node.classList.contains('verse')) {
                            hasNewVerses = true;
                            break;
                        }
                        // Check children
                        if (node.querySelectorAll && node.querySelectorAll('.verse').length > 0) {
                            hasNewVerses = true;
                            break;
                        }
                    }
                }
            }
            if (hasNewVerses) break;
        }
        
        if (hasNewVerses) {
            console.log('🆕 [IMAGES] New verses detected by MutationObserver');
            // Small delay to ensure verses are fully rendered
            setTimeout(() => {
                loadVerseImages();
            }, 300);
        }
    });
    
    observer.observe(bibleText, {
        childList: true,
        subtree: true
    });
    
    console.log('✅ [IMAGES] MutationObserver active');
}

/**
 * Hook into loadVerses if it exists
 */
function hookLoadVerses() {
    if (typeof window.loadVerses === 'function') {
        console.log('🔗 [IMAGES] Hooking into loadVerses...');
        
        const originalLoadVerses = window.loadVerses;
        
        window.loadVerses = async function(...args) {
            console.log('📖 [IMAGES] loadVerses called');
            
            // Call original
            const result = await originalLoadVerses.apply(this, args);
            
            // Clear and reload images
            const append = args[0]; // First arg is append mode
            if (!append) {
                console.log('🔄 [IMAGES] Fresh load, clearing cache...');
                clearRemovedImages();
            }
            
            setTimeout(() => {
                console.log('⏰ [IMAGES] Loading images after loadVerses...');
                loadVerseImages();
            }, 500);
            
            return result;
        };
        
        console.log('✅ [IMAGES] Successfully hooked into loadVerses');
    } else {
        console.log('ℹ️ [IMAGES] loadVerses not found yet, will rely on event listeners');
    }
}

/**
 * Initialize everything
 */
function initReaderImages() {
    console.log('🚀 [IMAGES] Initializing reader images...');
    
    // Setup event listeners
    setupEventListeners();
    
    // Setup MutationObserver
    setupMutationObserver();
    
    // Hook loadVerses if available
    hookLoadVerses();
    
    // Initial load
    setTimeout(() => {
        console.log('⏰ [IMAGES] Initial image load...');
        loadVerseImages();
    }, 1000);
    
    console.log('✅ [IMAGES] Reader images initialized');
}

// Make functions globally available
window.loadVerseImages = loadVerseImages;
window.openImageFullscreen = openImageFullscreen;

// Initialize when DOM is ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initReaderImages);
} else {
    initReaderImages();
}

console.log('✅ [IMAGES] Reader images module loaded (bulletproof)');