/**
 * APP.JS - Basis functionaliteit voor hele app
 */

// API helper functie
async function apiCall(endpoint, options = {}) {
    // Build proper URL with existing query params
    const currentUrl = new URL(window.location.href);
    
    // Parse endpoint to extract API call and parameters
    // e.g. "delete_timeline&id=174" → api=delete_timeline, id=174
    const parts = endpoint.split('&');
    const apiName = parts[0];
    
    // Set api parameter
    currentUrl.searchParams.set('api', apiName);
    
    // Add other parameters from endpoint
    for (let i = 1; i < parts.length; i++) {
        const [key, value] = parts[i].split('=');
        if (key && value) {
            currentUrl.searchParams.set(key, value);
        }
    }
    
    const url = currentUrl.toString();
    
    console.log('API Call:', url);
    
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
