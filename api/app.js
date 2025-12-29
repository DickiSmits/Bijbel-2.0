/**
 * APP.JS - Basis functionaliteit voor hele app
 */

// API helper functie
async function apiCall(endpoint, options = {}) {
    // Fix URL to work with existing query parameters
    // If we're on ?mode=admin, we need ?mode=admin&api=... not ?api=...
    const currentUrl = new URL(window.location.href);
    const hasParams = currentUrl.search.length > 0;
    const separator = hasParams ? '&' : '?';
    const url = separator + 'api=' + endpoint;
    
    console.log('API Call: ' + url);
    
    try {
        const response = await fetch(url, options);
        
        if (!response.ok) {
            console.error('API Error: ' + response.status + ' ' + response.statusText);
            showNotification('API fout: ' + response.status, true);
            return null;
        }
        
        const contentType = response.headers.get('content-type');
        if (!contentType || !contentType.includes('application/json')) {
            console.error('API returned non-JSON:', contentType);
            const text = await response.text();
            console.error('Response:', text.substring(0, 200));
            showNotification('API retourneert geen JSON', true);
            return null;
        }
        
        const data = await response.json();
        console.log('API Success: ' + endpoint, data);
        return data;
        
    } catch (error) {
        console.error('API Exception:', error);
        showNotification('Er is een fout opgetreden', true);
        return null;
    }
}

// Notification functie (Bootstrap Toast)
function showNotification(message, isError = false) {
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
    
    const bsToast = new bootstrap.Toast(toast);
    bsToast.show();
}

// Maak functies global beschikbaar
window.apiCall = apiCall;
window.showNotification = showNotification;

console.log('App.js loaded - API helper ready');
