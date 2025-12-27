/**
 * Bijbelreader - Core JavaScript
 * 
 * Algemene functies die door de hele applicatie gebruikt worden
 */

// ============= API HELPER =============

/**
 * Maak API call naar backend
 * @param {string} endpoint - API endpoint naam
 * @param {object} options - Fetch options (method, body, etc.)
 * @returns {Promise<object>} JSON response
 */
async function apiCall(endpoint, options = {}) {
    try {
        const response = await fetch('?api=' + endpoint, options);
        const data = await response.json();
        
        if (data.error) {
            console.error('API Error:', data.error);
            showNotification(data.error, true);
            return null;
        }
        
        return data;
    } catch (error) {
        console.error('API Error:', error);
        showNotification('Er is een fout opgetreden', true);
        return null;
    }
}

// ============= NOTIFICATIONS =============

/**
 * Toon notificatie met Bootstrap Toast
 * @param {string} message - Bericht om te tonen
 * @param {boolean} isError - Of het een error is
 */
function showNotification(message, isError = false) {
    const toast = document.getElementById('notificationToast');
    const toastBody = toast.querySelector('.toast-body');
    const toastHeader = toast.querySelector('.toast-header i');
    
    toastBody.textContent = message;
    toast.classList.remove('text-bg-success', 'text-bg-danger');
    toast.classList.add(isError ? 'text-bg-danger' : 'text-bg-success');
    toastHeader.className = isError ? 'bi bi-exclamation-circle me-2' : 'bi bi-check-circle me-2';
    
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
}

// ============= LIGHTBOX =============

/**
 * Open lightbox met afbeelding
 * @param {string} imageSrc - URL van afbeelding
 */
function openLightbox(imageSrc) {
    document.getElementById('lightboxImage').src = imageSrc;
    document.getElementById('lightbox').style.display = 'flex';
}

/**
 * Sluit lightbox
 */
function closeLightbox() {
    document.getElementById('lightbox').style.display = 'none';
}

// ============= HELPERS =============

/**
 * Sanitize HTML string
 * @param {string} str - String om te sanitizen
 * @returns {string} Gesanitized string
 */
function sanitizeHtml(str) {
    const div = document.createElement('div');
    div.textContent = str;
    return div.innerHTML;
}

/**
 * Format datum voor display
 * @param {string} dateStr - Datum string
 * @param {string} format - Format (kort/lang)
 * @returns {string} Geformatteerde datum
 */
function formatDate(dateStr, format = 'kort') {
    if (!dateStr) return '-';
    
    try {
        const date = new Date(dateStr);
        if (format === 'kort') {
            return date.toLocaleDateString('nl-NL');
        } else {
            return date.toLocaleString('nl-NL');
        }
    } catch (e) {
        return dateStr;
    }
}

/**
 * Bereken contrast kleur voor tekst op achtergrond
 * @param {string} hexColor - Hex kleurcode (bijv. #ffffff)
 * @returns {string} Contrast kleur (#000000 of #ffffff)
 */
function getContrastColor(hexColor) {
    // Verwijder #
    const hex = hexColor.replace('#', '');
    
    // Converteer naar RGB
    const r = parseInt(hex.substr(0, 2), 16);
    const g = parseInt(hex.substr(2, 2), 16);
    const b = parseInt(hex.substr(4, 2), 16);
    
    // Bereken luminantie
    const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
    
    // Return zwart voor lichte achtergronden, wit voor donkere
    return luminance > 0.5 ? '#000000' : '#ffffff';
}

/**
 * Debounce functie - voorkomt te veel functie calls
 * @param {Function} func - Functie om te debounce
 * @param {number} wait - Wacht tijd in ms
 * @returns {Function} Debounced functie
 */
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

/**
 * Escape HTML maar behoud formatting
 * @param {string} text - Tekst om te escapen
 * @returns {string} Escaped tekst
 */
function escapeHtml(text) {
    const map = {
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#039;'
    };
    return text.replace(/[&<>"']/g, m => map[m]);
}

// ============= STORAGE HELPERS =============

/**
 * Sla waarde op in localStorage
 * @param {string} key - Storage key
 * @param {any} value - Waarde om op te slaan
 */
function setStorage(key, value) {
    try {
        localStorage.setItem(key, JSON.stringify(value));
    } catch (e) {
        console.error('Storage error:', e);
    }
}

/**
 * Haal waarde op uit localStorage
 * @param {string} key - Storage key
 * @param {any} defaultValue - Default waarde als key niet bestaat
 * @returns {any} Opgeslagen waarde of default
 */
function getStorage(key, defaultValue = null) {
    try {
        const item = localStorage.getItem(key);
        return item ? JSON.parse(item) : defaultValue;
    } catch (e) {
        console.error('Storage error:', e);
        return defaultValue;
    }
}

/**
 * Verwijder waarde uit localStorage
 * @param {string} key - Storage key
 */
function removeStorage(key) {
    try {
        localStorage.removeItem(key);
    } catch (e) {
        console.error('Storage error:', e);
    }
}

// ============= EVENT LISTENERS =============

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    // ESC - Sluit lightbox en popups
    if (e.key === 'Escape') {
        closeLightbox();
        
        // Sluit timeline popup
        const timelinePopup = document.getElementById('timelinePopup');
        if (timelinePopup && timelinePopup.classList.contains('show')) {
            timelinePopup.classList.remove('show');
        }
    }
});

// ============= INITIALIZATION =============

// Log dat app.js geladen is
console.log('Bijbelreader app.js loaded');
