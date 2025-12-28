/**
 * ADMIN.JS - Admin mode functionaliteit
 */

// Show admin section
function showAdminSection(section) {
    // Hide all sections
    document.querySelectorAll('.admin-section').forEach(s => {
        s.classList.add('d-none');
    });
    
    // Show selected section
    const selectedSection = document.getElementById('section-' + section);
    if (selectedSection) {
        selectedSection.classList.remove('d-none');
    }
    
    // Update sidebar active state
    document.querySelectorAll('.admin-sidebar .list-group-item').forEach(btn => {
        btn.classList.remove('active');
    });
    event.target.classList.add('active');
}

// Placeholder functions voor admin
function saveFormatting() {
    showNotification('Deze functie wordt binnenkort toegevoegd!');
}

function resetFormatting() {
    showNotification('Deze functie wordt binnenkort toegevoegd!');
}

function createProfile() {
    showNotification('Deze functie wordt binnenkort toegevoegd!');
}

function deleteProfile(id) {
    if (confirm('Weet je zeker dat je dit profiel wilt verwijderen?')) {
        showNotification('Deze functie wordt binnenkort toegevoegd!');
    }
}

function saveTimeline() {
    showNotification('Deze functie wordt binnenkort toegevoegd!');
}

function deleteTimeline(id) {
    if (confirm('Weet je zeker dat je dit event wilt verwijderen?')) {
        showNotification('Deze functie wordt binnenkort toegevoegd!');
    }
}

function saveLocation() {
    showNotification('Deze functie wordt binnenkort toegevoegd!');
}

function deleteLocation(id) {
    if (confirm('Weet je zeker dat je deze locatie wilt verwijderen?')) {
        showNotification('Deze functie wordt binnenkort toegevoegd!');
    }
}

function uploadImage() {
    showNotification('Deze functie wordt binnenkort toegevoegd!');
}

function deleteImage(id) {
    if (confirm('Weet je zeker dat je deze afbeelding wilt verwijderen?')) {
        showNotification('Deze functie wordt binnenkort toegevoegd!');
    }
}

function createNewNote() {
    showNotification('Deze functie wordt binnenkort toegevoegd!');
}

function saveCurrentNote() {
    showNotification('Deze functie wordt binnenkort toegevoegd!');
}

function deleteCurrentNote() {
    if (confirm('Weet je zeker dat je deze notitie wilt verwijderen?')) {
        showNotification('Deze functie wordt binnenkort toegevoegd!');
    }
}

// Make functions global
window.showAdminSection = showAdminSection;
window.saveFormatting = saveFormatting;
window.resetFormatting = resetFormatting;
window.createProfile = createProfile;
window.deleteProfile = deleteProfile;
window.saveTimeline = saveTimeline;
window.deleteTimeline = deleteTimeline;
window.saveLocation = saveLocation;
window.deleteLocation = deleteLocation;
window.uploadImage = uploadImage;
window.deleteImage = deleteImage;
window.createNewNote = createNewNote;
window.saveCurrentNote = saveCurrentNote;
window.deleteCurrentNote = deleteCurrentNote;

console.log('Admin.js loaded - basic placeholder functions');
