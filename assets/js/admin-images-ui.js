/**
 * ADMIN IMAGES UI
 * Complete upload/edit interface
 */

console.log('🖼️ Loading admin images UI...');

// Global state
let currentEditingImage = null;
let allBooks = [];
let allChapters = [];
let allVerses = [];

/**
 * Initialize images section
 */
async function initImagesSection() {
    console.log('Initializing images section...');
    
    // Load books for dropdown
    allBooks = await apiCall('books');
    populateBookDropdown();
    
    // Setup event listeners
    setupImageEventListeners();
    
    // Load and display images
    loadImageGallery();
}

/**
 * Populate book dropdown
 */
function populateBookDropdown() {
    const bookSelect = document.getElementById('imageBookSelect');
    if (!bookSelect) return;
    
    bookSelect.innerHTML = '<option value="">Kies boek...</option>';
    
    if (allBooks && allBooks.length > 0) {
        allBooks.forEach(book => {
            const option = document.createElement('option');
            option.value = book.Bijbelboeknaam;
            option.textContent = book.Bijbelboeknaam;
            bookSelect.appendChild(option);
        });
    }
}

/**
 * Setup event listeners
 */
function setupImageEventListeners() {
    // Book change
    const bookSelect = document.getElementById('imageBookSelect');
    if (bookSelect) {
        bookSelect.addEventListener('change', async (e) => {
            const book = e.target.value;
            if (book) {
                await loadChaptersForImage(book);
            } else {
                document.getElementById('imageChapterSelect').innerHTML = '<option value="">Hoofdstuk</option>';
                document.getElementById('imageVerseSelect').innerHTML = '<option value="">Vers</option>';
            }
        });
    }
    
    // Chapter change
    const chapterSelect = document.getElementById('imageChapterSelect');
    if (chapterSelect) {
        chapterSelect.addEventListener('change', async (e) => {
            const chapter = e.target.value;
            const book = document.getElementById('imageBookSelect').value;
            if (book && chapter) {
                await loadVersesForImage(book, chapter);
            } else {
                document.getElementById('imageVerseSelect').innerHTML = '<option value="">Vers</option>';
            }
        });
    }
    
    // File input change
    const fileInput = document.getElementById('imageFileInput');
    if (fileInput) {
        fileInput.addEventListener('change', (e) => {
            const file = e.target.files[0];
            if (file) {
                const fileName = document.getElementById('imageFileName');
                if (fileName) {
                    fileName.textContent = file.name;
                    fileName.classList.remove('d-none');
                }
            }
        });
    }
}

/**
 * Load chapters for selected book
 */
async function loadChaptersForImage(book) {
    const chapterSelect = document.getElementById('imageChapterSelect');
    if (!chapterSelect) return;
    
    chapterSelect.innerHTML = '<option value="">Laden...</option>';
    
    const chapters = await apiCall(`chapters&boek=${encodeURIComponent(book)}`);
    allChapters = chapters || [];
    
    chapterSelect.innerHTML = '<option value="">Hoofdstuk</option>';
    
    if (chapters && chapters.length > 0) {
        chapters.forEach(ch => {
            const option = document.createElement('option');
            option.value = ch.Hoofdstuknummer;
            option.textContent = ch.Hoofdstuknummer;
            chapterSelect.appendChild(option);
        });
    }
}

/**
 * Load verses for selected chapter
 */
async function loadVersesForImage(book, chapter) {
    const verseSelect = document.getElementById('imageVerseSelect');
    if (!verseSelect) return;
    
    verseSelect.innerHTML = '<option value="">Laden...</option>';
    
    const verses = await apiCall(`verses&boek=${encodeURIComponent(book)}&hoofdstuk=${chapter}&limit=999`);
    allVerses = verses || [];
    
    verseSelect.innerHTML = '<option value="">Vers</option>';
    
    if (verses && verses.length > 0) {
        verses.forEach(v => {
            const option = document.createElement('option');
            option.value = v.Vers_ID;
            option.textContent = v.Versnummer;
            verseSelect.appendChild(option);
        });
    }
}

/**
 * Upload image
 */
async function uploadImage() {
    const fileInput = document.getElementById('imageFileInput');
    const captionInput = document.getElementById('imageCaptionInput');
    const bookSelect = document.getElementById('imageBookSelect');
    const chapterSelect = document.getElementById('imageChapterSelect');
    const verseSelect = document.getElementById('imageVerseSelect');
    const alignmentSelect = document.getElementById('imageAlignmentSelect');
    const widthInput = document.getElementById('imageWidthInput');
    const heightInput = document.getElementById('imageHeightInput');
    
    if (!fileInput || !fileInput.files || fileInput.files.length === 0) {
        showNotification('Selecteer eerst een afbeelding', true);
        return;
    }
    
    // Get selected verse ID
    const versId = verseSelect ? verseSelect.value : '';
    
    const formData = new FormData();
    formData.append('image', fileInput.files[0]);
    formData.append('caption', captionInput ? captionInput.value : '');
    formData.append('vers_id', versId);
    formData.append('uitlijning', alignmentSelect ? alignmentSelect.value : 'center');
    formData.append('breedte', widthInput ? widthInput.value : '400');
    formData.append('hoogte', heightInput ? heightInput.value : '');
    
    try {
        showNotification('Uploaden...');
        
        const response = await fetch('?api=upload_image', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result && result.success) {
            showNotification('Afbeelding geüpload!');
            
            // Reset form
            fileInput.value = '';
            if (captionInput) captionInput.value = '';
            if (bookSelect) bookSelect.value = '';
            if (chapterSelect) chapterSelect.innerHTML = '<option value="">Hoofdstuk</option>';
            if (verseSelect) verseSelect.innerHTML = '<option value="">Vers</option>';
            if (widthInput) widthInput.value = '400';
            if (heightInput) heightInput.value = '';
            document.getElementById('imageFileName').classList.add('d-none');
            
            // Reload gallery
            loadImageGallery();
        } else {
            showNotification('Upload mislukt: ' + (result.error || 'Unknown error'), true);
        }
        
    } catch (error) {
        console.error('Upload error:', error);
        showNotification('Upload mislukt: ' + error.message, true);
    }
}

/**
 * Load image gallery
 */
async function loadImageGallery() {
    const gallery = document.getElementById('imageGallery');
    if (!gallery) return;
    
    gallery.innerHTML = '<div class="col-12 text-center py-3"><div class="spinner-border spinner-border-sm"></div> Laden...</div>';
    
    try {
        const images = await apiCall('all_images');
        
        if (!images || images.length === 0) {
            gallery.innerHTML = '<div class="col-12 text-center text-muted py-5"><i class="bi bi-image" style="font-size:3rem;"></i><p class="mt-3">Nog geen afbeeldingen geüpload</p></div>';
            return;
        }
        
        gallery.innerHTML = '';
        
        images.forEach(img => {
            const col = document.createElement('div');
            col.className = 'col-md-3 mb-3';
            
            const verseInfo = img.Bijbelboeknaam ? 
                `<small class="text-muted">${img.Bijbelboeknaam} ${img.Hoofdstuknummer}:${img.Versnummer}</small>` : 
                '<small class="text-muted">Niet gekoppeld</small>';
            
            col.innerHTML = `
                <div class="card h-100 shadow-sm image-card">
                    <img src="${img.Bestandspad}" 
                         class="card-img-top" 
                         style="height: 200px; object-fit: cover; cursor: pointer;"
                         onclick="viewImage('${img.Bestandspad}')"
                         onerror="this.src='data:image/svg+xml,%3Csvg xmlns=%22http://www.w3.org/2000/svg%22 width=%22200%22 height=%22200%22%3E%3Crect fill=%22%23ddd%22 width=%22200%22 height=%22200%22/%3E%3Ctext x=%2250%25%22 y=%2250%25%22 text-anchor=%22middle%22 dy=%22.3em%22 fill=%22%23999%22%3EImage Error%3C/text%3E%3C/svg%3E'">
                    <div class="card-body p-2">
                        ${verseInfo}
                        ${img.Caption ? `<p class="small mb-2">${img.Caption}</p>` : ''}
                        <div class="btn-group btn-group-sm w-100">
                            <button class="btn btn-outline-primary" onclick="editImage(${img.Afbeelding_ID})" title="Bewerken">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="deleteImageConfirm(${img.Afbeelding_ID})" title="Verwijderen">
                                <i class="bi bi-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            `;
            
            gallery.appendChild(col);
        });
        
        console.log(`✅ Loaded ${images.length} images in gallery`);
        
    } catch (error) {
        console.error('Error loading gallery:', error);
        gallery.innerHTML = '<div class="col-12 alert alert-danger">Fout bij laden afbeeldingen</div>';
    }
}

/**
 * View image in modal
 */
function viewImage(path) {
    const modal = document.getElementById('imageViewModal');
    const img = document.getElementById('imageViewImg');
    
    if (modal && img) {
        img.src = path;
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
    } else {
        // Fallback: open in new tab
        window.open(path, '_blank');
    }
}

/**
 * Edit image
 */
async function editImage(imageId) {
    try {
        const image = await apiCall(`get_image&id=${imageId}`);
        
        if (!image) {
            showNotification('Afbeelding niet gevonden', true);
            return;
        }
        
        currentEditingImage = image;
        
        // Fill edit form
        document.getElementById('editImageId').value = image.Afbeelding_ID;
        document.getElementById('editImageCaption').value = image.Caption || '';
        document.getElementById('editImageAlignment').value = image.Uitlijning || 'center';
        document.getElementById('editImageWidth').value = image.Breedte || '400';
        document.getElementById('editImageHeight').value = image.Hoogte || '';
        
        // Show image preview
        document.getElementById('editImagePreview').src = image.Bestandspad;
        
        // Show verse info if linked
        if (image.Bijbelboeknaam) {
            document.getElementById('editImageVerseInfo').textContent = 
                `Gekoppeld aan: ${image.Bijbelboeknaam} ${image.Hoofdstuknummer}:${image.Versnummer}`;
            document.getElementById('editImageVerseInfo').classList.remove('d-none');
        } else {
            document.getElementById('editImageVerseInfo').classList.add('d-none');
        }
        
        // Show modal
        const modal = new bootstrap.Modal(document.getElementById('imageEditModal'));
        modal.show();
        
    } catch (error) {
        console.error('Error loading image for edit:', error);
        showNotification('Fout bij laden afbeelding', true);
    }
}

/**
 * Save edited image
 */
async function saveImageEdit() {
    const imageId = document.getElementById('editImageId').value;
    const caption = document.getElementById('editImageCaption').value;
    const alignment = document.getElementById('editImageAlignment').value;
    const width = document.getElementById('editImageWidth').value;
    const height = document.getElementById('editImageHeight').value;
    
    try {
        const result = await apiCall('update_image', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                afbeelding_id: parseInt(imageId),
                caption: caption,
                uitlijning: alignment,
                breedte: parseInt(width),
                hoogte: height ? parseInt(height) : null,
                vers_id: currentEditingImage ? currentEditingImage.Vers_ID : null
            })
        });
        
        if (result && result.success) {
            showNotification('Afbeelding bijgewerkt!');
            
            // Close modal
            const modal = bootstrap.Modal.getInstance(document.getElementById('imageEditModal'));
            modal.hide();
            
            // Reload gallery
            loadImageGallery();
        } else {
            showNotification('Bijwerken mislukt', true);
        }
        
    } catch (error) {
        console.error('Error updating image:', error);
        showNotification('Fout bij bijwerken', true);
    }
}

/**
 * Delete image with confirmation
 */
async function deleteImageConfirm(imageId) {
    if (!confirm('Weet je zeker dat je deze afbeelding wilt verwijderen?\n\nDit kan niet ongedaan worden gemaakt.')) {
        return;
    }
    
    try {
        const result = await apiCall(`delete_image&id=${imageId}`);
        
        if (result && result.success) {
            showNotification('Afbeelding verwijderd');
            loadImageGallery();
        } else {
            showNotification('Verwijderen mislukt', true);
        }
        
    } catch (error) {
        console.error('Error deleting image:', error);
        showNotification('Fout bij verwijderen', true);
    }
}

// Make functions globally available
window.initImagesSection = initImagesSection;
window.uploadImage = uploadImage;
window.editImage = editImage;
window.saveImageEdit = saveImageEdit;
window.deleteImageConfirm = deleteImageConfirm;
window.viewImage = viewImage;

console.log('✅ Admin images UI loaded');
