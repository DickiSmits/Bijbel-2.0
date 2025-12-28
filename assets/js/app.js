/**
 * APP.JS - Basis functionaliteit voor hele app
 */

// API helper functie
async function apiCall(endpoint, options = {}) {
    try {
        const response = await fetch('?api=' + endpoint, options);
        const data = await response.json();
        return data;
    } catch (error) {
        console.error('API Error:', error);
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
