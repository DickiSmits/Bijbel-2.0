<!-- Admin View - Modern Tabs Version -->
<div class="admin-container">
    <!-- Admin Header with Tabs -->
    <div class="admin-header">
        <h4 class="mb-0"><i class="bi bi-gear-fill"></i> Admin Panel</h4>
    </div>
    
    <!-- Admin Tabs Navigation -->
    <div class="admin-tabs">
        <button class="admin-tab active" data-section="editor" onclick="switchAdminTab('editor')">
            <i class="bi bi-pencil"></i>
            <span>Tekst Bewerken</span>
        </button>
        <button class="admin-tab" data-section="profiles" onclick="switchAdminTab('profiles')">
            <i class="bi bi-person-badge"></i>
            <span>Profielen</span>
        </button>
        <button class="admin-tab" data-section="timeline" onclick="switchAdminTab('timeline')">
            <i class="bi bi-calendar-event"></i>
            <span>Timeline</span>
        </button>
        <button class="admin-tab" data-section="locations" onclick="switchAdminTab('locations')">
            <i class="bi bi-geo-alt"></i>
            <span>Locaties</span>
        </button>
        <button class="admin-tab" data-section="images" onclick="switchAdminTab('images')">
            <i class="bi bi-image"></i>
            <span>Afbeeldingen</span>
        </button>
        <button class="admin-tab" data-section="notes" onclick="switchAdminTab('notes')">
            <i class="bi bi-journal-text"></i>
            <span>Notities</span>
        </button>
    </div>
    
    <!-- Admin Content Area -->
    <div class="admin-content-area">
        <!-- Editor Section -->
        <div id="section-editor" class="admin-section active">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span>Tekst Bewerken</span>
                    <div class="btn-group btn-group-sm" role="group">
                        <input type="radio" class="btn-check" name="editMode" id="editModeSingle" checked onchange="setEditMode('single')">
                        <label class="btn btn-outline-primary" for="editModeSingle">Enkel vers</label>
                        <input type="radio" class="btn-check" name="editMode" id="editModeChapter" onchange="setEditMode('chapter')">
                        <label class="btn btn-outline-primary" for="editModeChapter">Heel hoofdstuk</label>
                    </div>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-3 align-items-end">
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">Boek</label>
                            <select id="adminBookSelect" class="form-select form-select-sm"></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold text-muted">Hoofdstuk</label>
                            <select id="adminChapterSelect" class="form-select form-select-sm"></select>
                        </div>
                        <div class="col-md-3" id="verseSelectContainer">
                            <label class="form-label small fw-semibold text-muted">Vers</label>
                            <select id="adminVerseSelect" class="form-select form-select-sm"></select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label small fw-semibold" style="color: #2c5282;">
                                <i class="bi bi-person-badge"></i> Profiel
                            </label>
                            <select id="editorProfileSelect" class="form-select form-select-sm" 
                                    style="border: 2px solid #2c5282; background-color: #f0f5ff;">
                            </select>
                        </div>
                    </div>
                    <hr>
                    
                    <!-- Single verse editor -->
                    <div id="singleVerseEditor">
                        <div class="mb-3">
                            <label class="form-label">Originele tekst</label>
                            <div id="originalText" class="p-2 bg-light border rounded" style="min-height: 80px; max-height: 150px; overflow-y: auto;"></div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Opgemaakte tekst</label>
                            <div id="editor-container"></div>
                        </div>
                        <div class="d-flex gap-2">
                            <button class="btn btn-primary btn-sm" onclick="saveFormatting()">
                                <i class="bi bi-save"></i> Opslaan
                            </button>
                            <button class="btn btn-outline-secondary btn-sm" onclick="resetFormatting()">
                                <i class="bi bi-arrow-counterclockwise"></i> Reset
                            </button>
                            <button class="btn btn-outline-danger btn-sm" onclick="deleteFormatting()">
                                <i class="bi bi-trash"></i> Verwijderen
                            </button>
                        </div>
                    </div>
                    
                    <!-- Chapter editor -->
                    <div id="chapterEditor" class="d-none">
                        <div class="mb-3 d-flex justify-content-between align-items-center">
                            <label class="form-label mb-0">Verzen bewerken</label>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary" onclick="resetAllChapterVerses()" title="Reset alle wijzigingen">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset Alles
                                </button>
                                <button class="btn btn-primary" onclick="saveAllChapterFormatting()">
                                    <i class="bi bi-save-fill"></i> Alles Opslaan
                                </button>
                            </div>
                        </div>
                        <div id="chapterVersesContainer" style="max-height: 500px; overflow-y: auto;">
                            <div class="text-muted text-center py-4">Selecteer een boek en hoofdstuk om te beginnen</div>
                        </div>
                        <div class="mt-3 d-flex justify-content-between align-items-center">
                            <small class="text-muted"><span id="chapterVerseCount">0</span> verzen</small>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-secondary" onclick="resetAllChapterVerses()" title="Reset alle wijzigingen">
                                    <i class="bi bi-arrow-counterclockwise"></i> Reset Alles
                                </button>
                                <button class="btn btn-primary" onclick="saveAllChapterFormatting()">
                                    <i class="bi bi-save-fill"></i> Alles Opslaan
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <!-- Profiles Section -->
        <div id="section-profiles" class="admin-section">
            <div class="card mb-4">
                <div class="card-header">Nieuw Profiel</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-5">
                            <label class="form-label">Naam</label>
                            <input type="text" id="newProfileName" class="form-control" placeholder="Profiel naam">
                        </div>
                        <div class="col-md-5">
                            <label class="form-label">Beschrijving</label>
                            <input type="text" id="newProfileDesc" class="form-control" placeholder="Optionele beschrijving">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button class="btn btn-primary w-100" onclick="createProfile()">
                                <i class="bi bi-plus"></i> Aanmaken
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">Bestaande Profielen</div>
                <div class="card-body">
                    <div id="profilesList"></div>
                </div>
            </div>
        </div>
        
        <!-- Timeline Section -->
        <div id="section-timeline" class="admin-section">
            <h5 class="mb-4">Timeline Beheren</h5>
            <div class="card mb-4">
                <div class="card-header">Timeline Event</div>
                <div class="card-body">
                    <input type="hidden" id="timelineEventId">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Titel</label>
                            <input type="text" id="timelineTitel" class="form-control">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Groep</label>
                            <select id="timelineGroup" class="form-select">
                                <option value="">Geen groep</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Beschrijving</label>
                            <textarea id="timelineBeschrijving" class="form-control" rows="3" placeholder="Optionele beschrijving van het event"></textarea>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Start Datum</label>
                            <input type="text" id="timelineStartDatum" class="form-control" placeholder="-1000 of 2024-01-15">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Eind Datum</label>
                            <input type="text" id="timelineEndDatum" class="form-control" placeholder="Optioneel">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Kleur</label>
                            <input type="color" id="timelineKleur" class="form-control form-control-color w-100" value="#3498db">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="bi bi-book"></i> Start Bijbelvers (optioneel)
                            </label>
                            <input type="text" id="timelineVersStart" class="form-control" placeholder="Bijv: Genesis 1:1 of ID nummer">
                            <small class="text-muted">Geef vers referentie of ID nummer</small>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">
                                <i class="bi bi-book"></i> Eind Bijbelvers (optioneel)
                            </label>
                            <input type="text" id="timelineVersEnd" class="form-control" placeholder="Bijv: Genesis 1:31 of ID nummer">
                            <small class="text-muted">Geef vers referentie of ID nummer</small>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" onclick="saveTimeline()">
                                <i class="bi bi-save"></i> Opslaan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">Timeline Events</div>
                <div class="card-body">
                    <div id="timelineList"></div>
                </div>
            </div>
        </div>
        
        <!-- Locations Section -->
        <div id="section-locations" class="admin-section">
            <h5 class="mb-4">Locaties Beheren</h5>
            <div class="card mb-4">
                <div class="card-header">Nieuwe Locatie</div>
                <div class="card-body">
                    <input type="hidden" id="locationId">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Naam</label>
                            <input type="text" id="locationName" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Latitude</label>
                            <input type="number" step="any" id="locationLat" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Longitude</label>
                            <input type="number" step="any" id="locationLng" class="form-control">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" onclick="saveLocation()">
                                <i class="bi bi-save"></i> Opslaan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">Bestaande Locaties</div>
                <div class="card-body">
                    <div id="locationList"></div>
                </div>
            </div>
        </div>
        
        <!-- Images Section -->
        <div id="section-images" class="admin-section">
            <h5 class="mb-4">Afbeeldingen Beheren</h5>
            <div class="card mb-4">
                <div class="card-header">Afbeelding Uploaden</div>
                <div class="card-body">
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Afbeelding</label>
                            <input type="file" id="imageFile" class="form-control" accept="image/*">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Bijschrift</label>
                            <input type="text" id="imageCaption" class="form-control">
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" onclick="uploadImage()">
                                <i class="bi bi-upload"></i> Uploaden
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card">
                <div class="card-header">Geüploade Afbeeldingen</div>
                <div class="card-body">
                    <div id="imageList" class="row g-3"></div>
                </div>
            </div>
        </div>
        
        <!-- Notes Section -->
        <div id="section-notes" class="admin-section">
            <h5 class="mb-4">Notities</h5>
            <div class="row" style="height: calc(100vh - 250px);">
                <div class="col-md-4 col-lg-3">
                    <div class="card h-100">
                        <div class="card-header">
                            <button class="btn btn-primary btn-sm w-100" onclick="createNewNote()">
                                <i class="bi bi-plus"></i> Nieuwe Notitie
                            </button>
                        </div>
                        <div class="card-body p-2 overflow-auto" id="notesList">
                            <p class="text-muted text-center py-4">Nog geen notities</p>
                        </div>
                    </div>
                </div>
                <div class="col-md-8 col-lg-9">
                    <div class="card h-100">
                        <div id="emptyNotesState" class="card-body d-flex flex-column align-items-center justify-content-center text-muted">
                            <i class="bi bi-journal-text" style="font-size: 4rem;"></i>
                            <h5 class="mt-3">Geen notitie geselecteerd</h5>
                            <p>Selecteer een notitie of maak een nieuwe aan.</p>
                        </div>
                        <div id="noteEditorContent" class="card-body p-0 d-none d-flex flex-column h-100">
                            <input type="text" id="noteTitleInput" class="form-control border-0 border-bottom rounded-0 fs-5 fw-bold" placeholder="Titel...">
                            <div id="notesEditorContainer" class="flex-grow-1"></div>
                            <div class="p-2 border-top d-flex gap-2 align-items-center">
                                <button class="btn btn-primary btn-sm" onclick="saveCurrentNote()">
                                    <i class="bi bi-save"></i> Opslaan
                                </button>
                                <button class="btn btn-outline-danger btn-sm" onclick="deleteCurrentNote()">
                                    <i class="bi bi-trash"></i> Verwijderen
                                </button>
                                <span id="noteSaveStatus" class="ms-auto text-muted small"></span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification -->
<div class="toast-container position-fixed top-0 end-0 p-3">
    <div id="notificationToast" class="toast" role="alert">
        <div class="toast-header">
            <i class="bi bi-info-circle me-2"></i>
            <strong class="me-auto">Melding</strong>
            <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
        </div>
        <div class="toast-body"></div>
    </div>
</div>

<style>
/* ============================================
   MODERN ADMIN TABS LAYOUT
   ============================================ */

.admin-container {
    display: flex;
    flex-direction: column;
    height: calc(100vh - 56px);
    background: #f8f9fa;
}

/* Admin Header */
.admin-header {
    background: linear-gradient(135deg, #2c5282 0%, #1a365d 100%);
    color: white;
    padding: 1rem 1.5rem;
    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
}

.admin-header h4 {
    margin: 0;
    font-weight: 600;
}

/* Tabs Navigation */
.admin-tabs {
    display: flex;
    background: white;
    border-bottom: 2px solid #dee2e6;
    overflow-x: auto;
    flex-shrink: 0;
}

.admin-tab {
    flex: 0 0 auto;
    padding: 1rem 1.5rem;
    background: none;
    border: none;
    border-bottom: 3px solid transparent;
    color: #6c757d;
    font-weight: 500;
    cursor: pointer;
    transition: all 0.2s ease;
    display: flex;
    align-items: center;
    gap: 0.5rem;
    white-space: nowrap;
}

.admin-tab i {
    font-size: 1.1rem;
}

.admin-tab:hover {
    background: #f8f9fa;
    color: #2c5282;
}

.admin-tab.active {
    color: #2c5282;
    border-bottom-color: #2c5282;
    background: white;
}

/* Content Area */
.admin-content-area {
    flex: 1;
    overflow-y: auto;
    padding: 1.5rem;
}

/* Sections */
.admin-section {
    display: none;
}

.admin-section.active {
    display: block;
}

/* Responsive */
@media (max-width: 768px) {
    .admin-tab span {
        display: none;
    }
    
    .admin-tab {
        padding: 1rem;
        justify-content: center;
    }
    
    .admin-tab i {
        font-size: 1.3rem;
    }
    
    .admin-content-area {
        padding: 1rem;
    }
}

/* Smooth transitions */
.admin-section {
    animation: fadeIn 0.2s ease-in;
}

@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>

<script>
/**
 * Switch between admin tabs
 */
function switchAdminTab(section) {
    console.log('🔄 Switching to tab:', section);
    
    // Update tabs
    document.querySelectorAll('.admin-tab').forEach(tab => {
        tab.classList.remove('active');
    });
    
    const activeTab = document.querySelector(`.admin-tab[data-section="${section}"]`);
    if (activeTab) {
        activeTab.classList.add('active');
    }
    
    // Update sections
    document.querySelectorAll('.admin-section').forEach(sec => {
        sec.classList.remove('active');
    });
    
    const activeSection = document.getElementById('section-' + section);
    if (activeSection) {
        activeSection.classList.add('active');
    }
    
    // Call existing showAdminSection if it exists (for data loading)
    if (typeof showAdminSection === 'function') {
        showAdminSection(section);
    }
    
    // Save preference
    localStorage.setItem('adminActiveTab', section);
    
    console.log('✅ Tab switched:', section);
}

// Restore last active tab on load
document.addEventListener('DOMContentLoaded', () => {
    const lastTab = localStorage.getItem('adminActiveTab') || 'editor';
    
    // Small delay to ensure admin.js has loaded
    setTimeout(() => {
        switchAdminTab(lastTab);
    }, 100);
});

// Make globally available
window.switchAdminTab = switchAdminTab;
</script>