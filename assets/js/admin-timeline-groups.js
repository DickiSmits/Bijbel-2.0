// ============= TIMELINE GROUP FUNCTIES =============

/**
 * Save Timeline Group (Create or Update)
 */
async function saveTimelineGroup() {
    const groupId = document.getElementById('timelineGroupId')?.value;
    const naam = document.getElementById('timelineGroupNaam')?.value;
    const kleur = document.getElementById('timelineGroupKleur')?.value || '#3498db';
    const volgorde = document.getElementById('timelineGroupVolgorde')?.value || 1;
    const zichtbaar = document.getElementById('timelineGroupZichtbaar')?.value || 1;
    const beschrijving = document.getElementById('timelineGroupBeschrijving')?.value || '';
    
    if (!naam) {
        window.showNotification('Vul een groep naam in', true);
        return;
    }
    
    const data = {
        naam: naam,
        kleur: kleur,
        volgorde: parseInt(volgorde),
        zichtbaar: parseInt(zichtbaar),
        beschrijving: beschrijving
    };
    
    if (groupId) {
        data.group_id = parseInt(groupId);
    }
    
    const result = await window.apiCall('save_timeline_group', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    });
    
    if (result && result.success) {
        window.showNotification(groupId ? 'Timeline groep geüpdatet!' : 'Timeline groep aangemaakt!');
        clearTimelineGroupForm();
        loadTimelineGroups();
    }
}

/**
 * Edit Timeline Group - Load into form
 */
async function editTimelineGroup(groupId) {
    console.log('📝 Editing timeline group:', groupId);
    
    const result = await window.apiCall(`get_timeline_group&id=${groupId}`);
    
    if (!result) {
        window.showNotification('Groep niet gevonden', true);
        return;
    }
    
    // Fill form
    document.getElementById('timelineGroupId').value = result.Group_ID;
    document.getElementById('timelineGroupNaam').value = result.Groep_Naam || '';
    document.getElementById('timelineGroupKleur').value = result.Kleur || '#3498db';
    document.getElementById('timelineGroupVolgorde').value = result.Volgorde || 1;
    document.getElementById('timelineGroupZichtbaar').value = result.Zichtbaar || 1;
    document.getElementById('timelineGroupBeschrijving').value = result.Beschrijving || '';
    
    // Scroll to form
    document.getElementById('timelineGroupNaam').scrollIntoView({ behavior: 'smooth', block: 'center' });
    document.getElementById('timelineGroupNaam').focus();
    
    window.showNotification('Groep geladen - pas aan en klik Opslaan');
}

/**
 * Delete Timeline Group
 */
async function deleteTimelineGroup(groupId) {
    if (!confirm('Weet je zeker dat je deze timeline groep wilt verwijderen?\n\nEvents in deze groep blijven bestaan maar verliezen hun groep.')) {
        return;
    }
    
    const result = await window.apiCall(`delete_timeline_group&id=${groupId}`);
    
    if (result && result.success) {
        window.showNotification('Timeline groep verwijderd');
        loadTimelineGroups();
    }
}

/**
 * Clear Timeline Group Form
 */
function clearTimelineGroupForm() {
    document.getElementById('timelineGroupId').value = '';
    document.getElementById('timelineGroupNaam').value = '';
    document.getElementById('timelineGroupKleur').value = '#3498db';
    document.getElementById('timelineGroupVolgorde').value = '1';
    document.getElementById('timelineGroupZichtbaar').value = '1';
    document.getElementById('timelineGroupBeschrijving').value = '';
}

/**
 * Clear Timeline Event Form
 */
function clearTimelineForm() {
    document.getElementById('timelineEventId').value = '';
    document.getElementById('timelineTitel').value = '';
    document.getElementById('timelineGroup').value = '';
    document.getElementById('timelineStartDatum').value = '';
    document.getElementById('timelineEndDatum').value = '';
    document.getElementById('timelineKleur').value = '#3498db';
}

// Make functions global
window.saveTimelineGroup = saveTimelineGroup;
window.editTimelineGroup = editTimelineGroup;
window.deleteTimelineGroup = deleteTimelineGroup;
window.clearTimelineGroupForm = clearTimelineGroupForm;
window.clearTimelineForm = clearTimelineForm;

console.log('✅ Timeline group functions loaded');
