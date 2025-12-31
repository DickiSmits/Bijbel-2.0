<!-- Admin View -->
<div class="admin-layout">
    <!-- Admin Sidebar -->
    <div class="admin-sidebar">
        <h6 class="text-muted mb-3">ADMIN MENU</h6>
        <div class="list-group list-group-flush">
            <button class="list-group-item list-group-item-action active" onclick="showAdminSection('editor')">
                <i class="bi bi-pencil"></i> Tekst Bewerken
            </button>
            <button class="list-group-item list-group-item-action" onclick="showAdminSection('profiles')">
                <i class="bi bi-person-badge"></i> Profielen
            </button>
            <button class="list-group-item list-group-item-action" onclick="showAdminSection('timeline')">
                <i class="bi bi-calendar-event"></i> Timeline
            </button>
            <button class="list-group-item list-group-item-action" onclick="showAdminSection('locations')">
                <i class="bi bi-geo-alt"></i> Locaties
            </button>
            <button class="list-group-item list-group-item-action" onclick="showAdminSection('images')">
                <i class="bi bi-image"></i> Afbeeldingen
            </button>
            <button class="list-group-item list-group-item-action" onclick="showAdminSection('notes')">
                <i class="bi bi-journal-text"></i> Notities
            </button>
        </div>
    </div>
    
    <!-- Admin Content -->
    <div class="admin-content">
        <!-- Editor Section -->
        <div id="section-editor" class="admin-section">
            <h4 class="mb-4"><i class="bi bi-pencil"></i> Tekst Bewerken</h4>
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
                            <!-- Boek/Hoofdstuk/Vers op Ã©Ã©n rij -->
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
                            
                            <!-- Profiel DAARNA met accent kleur -->
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
                    
                    <!-- Chapter editor (multiple verses) -->
 <!-- Chapter editor (multiple verses) -->
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
        
        <!-- Other sections remain the same -->
        <div id="section-profiles" class="admin-section d-none">
            <h4 class="mb-4"><i class="bi bi-person-badge"></i> Profielen Beheren</h4>
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
        
        <div id="section-timeline" class="admin-section d-none">
            <h4 class="mb-4"><i class="bi bi-calendar-event"></i> Timeline Beheren</h4>
            
            <!-- Timeline Events -->
            <div class="card mb-4">
                <div class="card-header">
                    Timeline Event
                    <button class="btn btn-sm btn-outline-secondary form-clear-btn" 
                            onclick="clearTimelineForm()" 
                            title="Form leegmaken">
                        <i class="bi bi-x-circle"></i>
                    </button>
                </div>
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
                        <div class="col-12">
                            <button class="btn btn-primary" onclick="saveTimeline()">
                                <i class="bi bi-save"></i> Opslaan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Timeline Events List -->
            <div class="card mb-4">
                <div class="card-header">Timeline Events</div>
                <div class="card-body">
                    <div id="timelineList"></div>
                </div>
            </div>
            
            <!-- Timeline Groups -->
            <div class="card mb-4">
                <div class="card-header">
                    Timeline Groep
                    <button class="btn btn-sm btn-outline-secondary form-clear-btn" 
                            onclick="clearTimelineGroupForm()" 
                            title="Form leegmaken">
                        <i class="bi bi-x-circle"></i>
                    </button>
                </div>
                <div class="card-body">
                    <input type="hidden" id="timelineGroupId">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Groep Naam</label>
                            <input type="text" id="timelineGroupNaam" class="form-control" placeholder="Bijv. Leven van Jezus">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Kleur</label>
                            <input type="color" id="timelineGroupKleur" class="form-control form-control-color w-100" value="#3498db">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Volgorde</label>
                            <input type="number" id="timelineGroupVolgorde" class="form-control" placeholder="1" value="1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Zichtbaar</label>
                            <select id="timelineGroupZichtbaar" class="form-select">
                                <option value="1" selected>Ja</option>
                                <option value="0">Nee</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Beschrijving</label>
                            <textarea id="timelineGroupBeschrijving" class="form-control" rows="2" placeholder="Optionele beschrijving..."></textarea>
                        </div>
                        <div class="col-12">
                            <button class="btn btn-primary" onclick="saveTimelineGroup()">
                                <i class="bi bi-save"></i> Opslaan
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Timeline Groups List -->
            <div class="card">
                <div class="card-header">Timeline Groepen</div>
                <div class="card-body">
                    <div id="groupsList"></div>
                </div>
            </div>
        </div>

        
        <div id="section-locations" class="admin-section d-none">
            <h4 class="mb-4"><i class="bi bi-geo-alt"></i> Locaties Beheren</h4>
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
        
<!-- REPLACE section-images in admin.php with this -->

<div id="section-images" class="admin-section d-none">
    <h4 class="mb-4"><i class="bi bi-image"></i> Afbeeldingen Beheren</h4>
    
    <!-- Upload Card -->
    <div class="card mb-4">
        <div class="card-header bg-primary text-white">
            <i class="bi bi-upload"></i> Afbeelding Uploaden
        </div>
        <div class="card-body">
            <div class="row g-3">
                <!-- File Input -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Afbeelding</label>
                    <div class="input-group">
                        <input type="file" 
                               id="imageFileInput" 
                               class="form-control" 
                               accept="image/*">
                        <label class="input-group-text" for="imageFileInput">
                            <i class="bi bi-folder2-open"></i> Bestand kiezen
                        </label>
                    </div>
                    <small id="imageFileName" class="text-muted d-none"></small>
                </div>
                
                <!-- Caption -->
                <div class="col-md-6">
                    <label class="form-label fw-semibold">Bijschrift</label>
                    <input type="text" 
                           id="imageCaptionInput" 
                           class="form-control" 
                           placeholder="Optioneel bijschrift">
                </div>
                
                <!-- Book -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Boek (optioneel)</label>
                    <select id="imageBookSelect" class="form-select">
                        <option value="">Kies boek...</option>
                    </select>
                </div>
                
                <!-- Chapter -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Hoofdstuk</label>
                    <select id="imageChapterSelect" class="form-select" disabled>
                        <option value="">Hoofdstuk</option>
                    </select>
                </div>
                
                <!-- Verse -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Vers</label>
                    <select id="imageVerseSelect" class="form-select" disabled>
                        <option value="">Vers</option>
                    </select>
                </div>
                
                <!-- Alignment -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Uitlijning</label>
                    <select id="imageAlignmentSelect" class="form-select">
                        <option value="left">Links</option>
                        <option value="center" selected>Gecentreerd</option>
                        <option value="right">Rechts</option>
                    </select>
                </div>
                
                <!-- Width -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Breedte (px)</label>
                    <input type="number" 
                           id="imageWidthInput" 
                           class="form-control" 
                           value="400" 
                           min="100" 
                           max="1200">
                </div>
                
                <!-- Height -->
                <div class="col-md-4">
                    <label class="form-label fw-semibold">Hoogte (px)</label>
                    <input type="number" 
                           id="imageHeightInput" 
                           class="form-control" 
                           placeholder="Auto">
                </div>
                
                <!-- Upload Button -->
                <div class="col-12">
                    <button class="btn btn-primary btn-lg w-100" onclick="uploadImage()">
                        <i class="bi bi-cloud-upload"></i> Uploaden
                    </button>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Gallery Card -->
    <div class="card">
        <div class="card-header d-flex justify-content-between align-items-center">
            <span><i class="bi bi-images"></i> Afbeeldingen Gallery</span>
            <button class="btn btn-sm btn-outline-primary" onclick="loadImageGallery()">
                <i class="bi bi-arrow-clockwise"></i> Vernieuwen
            </button>
        </div>
        <div class="card-body">
            <div id="imageGallery" class="row">
                <div class="col-12 text-center py-3">
                    <div class="spinner-border spinner-border-sm"></div> Laden...
                </div>
            </div>
        </div>
    </div>
</div>

<!-- EDIT MODAL - EXTENDED VERSION -->
<!-- Replace the existing imageEditModal in admin.php with this -->

<div class="modal fade" id="imageEditModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header bg-primary text-white">
                <h5 class="modal-title">
                    <i class="bi bi-pencil"></i> Afbeelding Bewerken
                </h5>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <input type="hidden" id="editImageId">
                
                <!-- Preview -->
                <div class="text-center mb-4">
                    <img id="editImagePreview" 
                         class="img-fluid rounded shadow" 
                         style="max-height: 200px;">
                </div>
                
                <div class="row g-3">
                    <!-- Caption -->
                    <div class="col-12">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-chat-square-text"></i> Bijschrift
                        </label>
                        <input type="text" 
                               id="editImageCaption" 
                               class="form-control" 
                               placeholder="Optioneel bijschrift">
                    </div>
                    
                    <!-- Verse Linking Section -->
                    <div class="col-12">
                        <div class="card bg-light">
                            <div class="card-header">
                                <i class="bi bi-link-45deg"></i> Vers Koppeling
                                <small class="text-muted">(Optioneel - koppel aan specifiek vers)</small>
                            </div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <!-- Book -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Boek</label>
                                        <select id="editImageBook" class="form-select">
                                            <option value="">Geen koppeling</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Chapter -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Hoofdstuk</label>
                                        <select id="editImageChapter" class="form-select" disabled>
                                            <option value="">Hoofdstuk</option>
                                        </select>
                                    </div>
                                    
                                    <!-- Verse -->
                                    <div class="col-md-4">
                                        <label class="form-label fw-semibold">Vers</label>
                                        <select id="editImageVerse" class="form-select" disabled>
                                            <option value="">Vers</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Alignment -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-align-center"></i> Uitlijning
                        </label>
                        <select id="editImageAlignment" class="form-select">
                            <option value="left">Links</option>
                            <option value="center">Gecentreerd</option>
                            <option value="right">Rechts</option>
                        </select>
                    </div>
                    
                    <!-- Width -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-arrows-expand"></i> Breedte (px)
                        </label>
                        <input type="number" 
                               id="editImageWidth" 
                               class="form-control" 
                               min="100" 
                               max="1200" 
                               value="400">
                    </div>
                    
                    <!-- Height -->
                    <div class="col-md-4">
                        <label class="form-label fw-semibold">
                            <i class="bi bi-arrows-vertical"></i> Hoogte (px)
                        </label>
                        <input type="number" 
                               id="editImageHeight" 
                               class="form-control" 
                               placeholder="Auto">
                        <small class="text-muted">Leeg = auto</small>
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
                    <i class="bi bi-x-circle"></i> Annuleren
                </button>
                <button type="button" class="btn btn-primary" onclick="saveImageEdit()">
                    <i class="bi bi-save"></i> Opslaan
                </button>
            </div>
        </div>
    </div>
</div>


<!-- View Modal -->
<div class="modal fade" id="imageViewModal" tabindex="-1">
    <div class="modal-dialog modal-lg modal-dialog-centered">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Afbeelding</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body text-center">
                <img id="imageViewImg" class="img-fluid" style="max-height: 70vh;">
            </div>
        </div>
    </div>
</div>

<style>
/* Image Card Hover Effect */
.image-card {
    transition: transform 0.2s, box-shadow 0.2s;
}

.image-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.15) !important;
}

/* Enable chapter/verse selects when book is selected */
#imageBookSelect:not([value=""]) ~ * #imageChapterSelect {
    pointer-events: all;
    opacity: 1;
}
</style>

<script>
// Enable/disable chapter and verse selects based on selections
document.addEventListener('DOMContentLoaded', function() {
    const bookSelect = document.getElementById('imageBookSelect');
    const chapterSelect = document.getElementById('imageChapterSelect');
    const verseSelect = document.getElementById('imageVerseSelect');
    
    if (bookSelect) {
        bookSelect.addEventListener('change', function() {
            chapterSelect.disabled = !this.value;
            verseSelect.disabled = true;
            verseSelect.innerHTML = '<option value="">Vers</option>';
        });
    }
    
    if (chapterSelect) {
        chapterSelect.addEventListener('change', function() {
            verseSelect.disabled = !this.value;
        });
    }
});
</script>

        
        <div id="section-notes" class="admin-section d-none">
            <h4 class="mb-4"><i class="bi bi-journal-text"></i> Notities</h4>
            <div class="row" style="height: calc(100vh - 200px);">
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