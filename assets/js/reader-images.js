/**
 * READER IMAGES MODULE
 * Load and display images for verses
 */

console.log('🖼️ Loading reader images module...');

/**
 * Load images for all visible verses
 */
async function loadVerseImages() {
    console.log('📸 Loading verse images...');
    
    const verses = document.querySelectorAll('.verse[data-vers-id]');
    
    if (verses.length === 0) {
        console.log('No verses found to load images for');
        return;
    }
    
    console.log(`Found ${verses.length} verses to check for images`);
    
    let imagesLoaded = 0;
    
    for (const verseElement of verses) {
        const versId = verseElement.dataset.versId;
        
        // Check if images already loaded for this verse
        if (verseElement.dataset.imagesLoaded === 'true') {
            continue;
        }
        
        try {
            // Get images for this verse
            const images = await apiCall(`verse_images&vers_id=${versId}`);
            
            if (images && images.length > 0) {
                console.log(`✅ Found ${images.length} image(s) for verse ${versId}`);
                
                // Display images
                displayVerseImages(versId, images, verseElement);
                imagesLoaded += images.length;
            }
            
            // Mark as loaded
            verseElement.dataset.imagesLoaded = 'true';
            
        } catch (error) {
            console.warn(`Could not load images for verse ${versId}:`, error);
            verseElement.dataset.imagesLoaded = 'true'; // Mark anyway to prevent retry
        }
    }
    
    if (imagesLoaded > 0) {
        console.log(`✅ Loaded ${imagesLoaded} total images`);
    } else {
        console.log('ℹ️ No images found for current verses');
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
                     onerror="this.style.display='none'; this.parentElement.innerHTML='<div class=\\'alert alert-warning\\'><i class=\\'bi bi-exclamation-triangle\\'></i> Afbeelding niet gevonden</div>';">
                ${caption}
            </div>
        `;
        
        // Insert after verse (you can change this to 'before' if needed)
        verseElement.after(imgContainer);
    });
}

/**
 * Open image in fullscreen modal
 */
function openImageFullscreen(imagePath, caption) {
    // Create modal if doesn't exist
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
    
    // Set image and caption
    const img = document.getElementById('readerImageModalImg');
    const title = document.getElementById('readerImageModalTitle');
    
    if (img) img.src = imagePath;
    if (title) title.textContent = caption || 'Afbeelding';
    
    // Show modal
    const bsModal = new bootstrap.Modal(modal);
    bsModal.show();
}

/**
 * Escape HTML for display
 */
function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Escape for JavaScript string
 */
function escapeForJs(text) {
    return text.replace(/'/g, "\\'").replace(/"/g, '\\"').replace(/\n/g, '\\n');
}

/**
 * Load images when verses are loaded
 * Hook into existing loadVerses function
 */
if (typeof window.loadVerses === 'function') {
    const originalLoadVerses = window.loadVerses;
    
    window.loadVerses = async function(...args) {
        // Call original function
        await originalLoadVerses.apply(this, args);
        
        // Load images after verses are loaded
        setTimeout(() => {
            loadVerseImages();
        }, 500);
    };
    
    console.log('✅ Hooked into loadVerses');
}

// Make functions globally available
window.loadVerseImages = loadVerseImages;
window.openImageFullscreen = openImageFullscreen;

console.log('✅ Reader images module loaded');

// Auto-load images on page load
document.addEventListener('DOMContentLoaded', () => {
    // Wait a bit for verses to load
    setTimeout(() => {
        loadVerseImages();
    }, 2000);
});
