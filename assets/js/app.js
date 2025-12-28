/**
 * Bijbelreader - Main Application
 * Core functionaliteit en utilities
 */

console.log('📦 Loading app.js...');

/**
 * API helper - DIRECT OP WINDOW
 */
window.apiCall = async function(endpoint, options = {}) {
    try {
        const url = '?api=' + endpoint;
        console.log('🌐 API Call:', url);
        
        const response = await fetch(url, options);
        const data = await response.json();
        
        console.log('✓ API Response:', endpoint, data);
        return data;
    } catch (error) {
        console.error('❌ API Error:', endpoint, error);
        if (typeof window.showNotification === 'function') {
            window.showNotification('API fout: ' + error.message, true);
        }
        return null;
    }
};

/**
 * Notification system
 */
window.showNotification = function(message, isError = false) {
    const toast = document.getElementById('notificationToast');
    if (!toast) return;
    
    const toastBody = toast.querySelector('.toast-body');
    const toastHeader = toast.querySelector('.toast-header i');
    
    toastBody.textContent = message;
    toast.classList.remove('text-bg-success', 'text-bg-danger');
    toast.classList.add(isError ? 'text-bg-danger' : 'text-bg-success');
    
    if (toastHeader) {
        toastHeader.className = isError ? 'bi bi-exclamation-circle me-2' : 'bi bi-check-circle me-2';
    }
    
    // Use Bootstrap Toast if available
    if (typeof bootstrap !== 'undefined' && bootstrap.Toast) {
        const bsToast = new bootstrap.Toast(toast);
        bsToast.show();
    } else {
        // Fallback: simple show/hide
        toast.style.display = 'block';
        setTimeout(() => {
            toast.style.display = 'none';
        }, 3000);
    }
};

/**
 * Lightbox functions
 */
window.openLightbox = function(imageSrc) {
    const lightboxImage = document.getElementById('lightboxImage');
    const lightbox = document.getElementById('lightbox');
    
    if (lightboxImage && lightbox) {
        lightboxImage.src = imageSrc;
        lightbox.style.display = 'flex';
    }
};

window.closeLightbox = function() {
    const lightbox = document.getElementById('lightbox');
    if (lightbox) {
        lightbox.style.display = 'none';
    }
};

/**
 * Escape key handler
 */
document.addEventListener('keydown', (e) => {
    if (e.key === 'Escape') {
        window.closeLightbox();
        
        const timelinePopup = document.getElementById('timelinePopup');
        if (timelinePopup && timelinePopup.classList.contains('show')) {
            timelinePopup.classList.remove('show');
        }
    }
});

/**
 * Helper: Get contrast color for background
 */
window.getContrastColor = function(hexColor) {
    const hex = hexColor.replace('#', '');
    const r = parseInt(hex.substr(0, 2), 16);
    const g = parseInt(hex.substr(2, 2), 16);
    const b = parseInt(hex.substr(4, 2), 16);
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    return luminance > 0.5 ? '#000000' : '#ffffff';
};

console.log('✓ App.js loaded - apiCall, showNotification, lightbox ready');
