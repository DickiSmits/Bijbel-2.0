/**
 * ADMIN.JS - Admin mode functionaliteit
 */

// Initialize admin when loaded
document.addEventListener('DOMContentLoaded', function() {
    if (mode === 'admin') {
        console.log('Admin mode - loading initial data...');
        loadProfiles();
    }
});

// Load profiles list
async function loadProfiles() {
    const profiles = await apiCall('profiles');
    const profileList = document.getElementById('profilesList');
    
    if (!profileList) {
        console.warn('profilesList element not found');
        return;
    }
    
    if (!profiles || profiles.length === 0) {
        profileList.innerHTML = '<p class="text-muted fst-italic">Nog geen profielen aangemaakt</p>';
        return;
    }
    
    profileList.innerHTML = '';
    profiles.forEach(profile => {
        const item = document.createElement('div');
        item.className = 'profile-item d-flex justify-content-between align-items-center p-3 mb-2 bg-light rounded';
        item.innerHTML = `
            <div>
                <div class="fw-semibold">${profile.Profiel_Naam}</div>
                <small class="text-muted">${profile.Beschrijving || 'Geen beschrijving'}</small>
            </div>
            <button class="btn btn-outline-danger btn-sm" onclick="deleteProfile(${profile.Profiel_ID})">
                <i class="bi bi-trash"></i> Verwijder
            </button>
        `;
        profileList.appendChild(item);
    });
    
    console.log(`Loaded ${profiles.length} profiles in admin`);
}

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
    const naam = document.getElementById('newProfileName');
    const beschrijving = document.getElementById('newProfileDesc');
    
    if (!naam || !naam.value.trim()) {
        showNotification('Vul een naam in', true);
        return;
    }
    
    apiCall('create_profile', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
            naam: naam.value.trim(),
            beschrijving: beschrijving ? beschrijving.value.trim() : ''
        })
    }).then(result => {
        if (result && result.success) {
            showNotification('Profiel aangemaakt!');
            naam.value = '';
            if (beschrijving) beschrijving.value = '';
            loadProfiles(); // Reload list
        } else {
            showNotification('Fout bij aanmaken profiel', true);
        }
    });
}

function deleteProfile(id) {
    if (!confirm('Weet je zeker dat je dit profiel wilt verwijderen?')) return;
    
    apiCall(`delete_profile&id=${id}`, { method: 'GET' })
        .then(result => {
            if (result && result.success) {
                showNotification('Profiel verwijderd');
                loadProfiles(); // Reload list
            } else {
                showNotification('Fout bij verwijderen profiel', true);
            }
        });
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
