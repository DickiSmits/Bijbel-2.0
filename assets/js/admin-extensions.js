/**
 * ADMIN EXTENSIONS
 * Edit functies + Search/Sort DataTable component
 */

// ============= EDIT FUNCTIES =============

/**
 * Edit Timeline Event - Load data into form
 */
async function editTimeline(eventId) {
    console.log('📝 Editing timeline event:', eventId);
    
    // Get event data
    const result = await window.apiCall(`get_timeline&id=${eventId}`);
    
    if (!result) {
        window.showNotification('Event niet gevonden', true);
        return;
    }
    
    // Fill form
    document.getElementById('timelineEventId').value = result.Event_ID;
    document.getElementById('timelineTitel').value = result.Titel || '';
    document.getElementById('timelineGroup').value = result.Group_ID || '';
    document.getElementById('timelineStartDatum').value = result.Start_Datum || '';
    document.getElementById('timelineEndDatum').value = result.End_Datum || '';
    document.getElementById('timelineKleur').value = result.Kleur || '#3498db';
    
    // Scroll to form
    document.getElementById('timelineTitel').scrollIntoView({ behavior: 'smooth', block: 'center' });
    document.getElementById('timelineTitel').focus();
    
    window.showNotification('Event geladen - pas aan en klik Opslaan');
}

/**
 * Edit Location - Load data into form
 */
async function editLocation(locationId) {
    console.log('📝 Editing location:', locationId);
    
    // Get location data
    const result = await window.apiCall(`get_location&id=${locationId}`);
    
    if (!result) {
        window.showNotification('Locatie niet gevonden', true);
        return;
    }
    
    // Fill form
    document.getElementById('locationId').value = result.Locatie_ID;
    document.getElementById('locationName').value = result.Naam || '';
    document.getElementById('locationLat').value = result.Latitude || '';
    document.getElementById('locationLng').value = result.Longitude || '';
    
    // Scroll to form
    document.getElementById('locationName').scrollIntoView({ behavior: 'smooth', block: 'center' });
    document.getElementById('locationName').focus();
    
    window.showNotification('Locatie geladen - pas aan en klik Opslaan');
}

/**
 * Edit Image - Load data into form
 */
async function editImage(imageId) {
    console.log('📝 Editing image:', imageId);
    
    // Get image data
    const result = await window.apiCall(`get_image&id=${imageId}`);
    
    if (!result) {
        window.showNotification('Afbeelding niet gevonden', true);
        return;
    }
    
    // Fill form
    document.getElementById('imageId').value = result.Afbeelding_ID;
    document.getElementById('imageCaption').value = result.Caption || '';
    
    // Update button text
    const saveBtn = document.getElementById('imageSaveButtonText');
    if (saveBtn) {
        saveBtn.textContent = 'Bijwerken';
    }
    
    // Fill verse selector if verse is linked
    if (result.Vers_ID && typeof window.fillImageVerseSelector === 'function') {
        await window.fillImageVerseSelector(result.Vers_ID);
    }
    
    // Scroll to form
    document.getElementById('imageCaption').scrollIntoView({ behavior: 'smooth', block: 'center' });
    document.getElementById('imageCaption').focus();
    
    window.showNotification('Afbeelding geladen - pas aan en klik Bijwerken');
}

/**
 * Clear Timeline Form
 */
function clearTimelineForm() {
    document.getElementById('timelineEventId').value = '';
    document.getElementById('timelineTitel').value = '';
    document.getElementById('timelineGroup').value = '';
    document.getElementById('timelineStartDatum').value = '';
    document.getElementById('timelineEndDatum').value = '';
    document.getElementById('timelineKleur').value = '#3498db';
}

/**
 * Clear Location Form
 */
function clearLocationForm() {
    document.getElementById('locationId').value = '';
    document.getElementById('locationName').value = '';
    document.getElementById('locationLat').value = '';
    document.getElementById('locationLng').value = '';
}

// Make functions global
window.editTimeline = editTimeline;
window.editLocation = editLocation;
window.editImage = editImage;
window.clearTimelineForm = clearTimelineForm;
window.clearLocationForm = clearLocationForm;

// ============= DATATABLE COMPONENT =============

/**
 * DataTable - Searchable, Sortable Table Component
 * 
 * @param {string} containerId - ID of container element
 * @param {Array} data - Array of objects
 * @param {Object} config - Configuration
 * @param {Array} config.columns - Column definitions
 * @param {string} config.actionColumnWidth - Width for action column (default: '80px')
 * @param {Function} config.onEdit - Edit callback
 * @param {Function} config.onDelete - Delete callback
 */
class DataTable {
    constructor(containerId, data, config) {
        this.container = document.getElementById(containerId);
        this.data = data;
        this.config = config;
        this.filteredData = [...data];
        this.sortColumn = null;
        this.sortDirection = 'asc';
        this.searchQuery = '';
        
        this.render();
    }
    
    render() {
        if (!this.container) return;
        
        // Build HTML
        const tableId = 'dt-' + Math.random().toString(36).substr(2, 9);
        
        this.container.innerHTML = `
            <div class="datatable-wrapper">
                <div class="datatable-header">
                    <div class="datatable-search">
                        <i class="bi bi-search"></i>
                        <input type="text" 
                               class="form-control form-control-sm" 
                               placeholder="Zoeken..."
                               id="${tableId}-search">
                    </div>
                    <div class="datatable-info">
                        <span id="${tableId}-count">${this.filteredData.length}</span> 
                        van ${this.data.length} items
                    </div>
                </div>
                
                <div class="table-responsive">
                    <table class="table table-hover datatable" id="${tableId}">
                        ${this.renderHeader()}
                        <tbody id="${tableId}-body">
                            ${this.renderRows()}
                        </tbody>
                    </table>
                </div>
            </div>
        `;
        
        // Attach event listeners
        this.attachListeners(tableId);
    }
    
    renderHeader() {
        let html = '<thead><tr>';
        
        // 🔴 UPDATED: Add width support for columns
        this.config.columns.forEach((col, index) => {
            const widthStyle = col.width ? ` style="width: ${col.width}"` : '';
            html += `
                <th class="sortable" data-column="${index}"${widthStyle}>
                    ${col.label}
                    <i class="bi bi-chevron-expand sort-icon"></i>
                </th>
            `;
        });
        
        // 🔴 UPDATED: Add width support for action column
        if (this.config.onEdit || this.config.onDelete) {
            const actionWidth = this.config.actionColumnWidth || '80px';
            html += `<th class="text-end" style="width: ${actionWidth}">Acties</th>`;
        }
        
        html += '</tr></thead>';
        return html;
    }
    
    renderRows() {
        if (this.filteredData.length === 0) {
            const colSpan = this.config.columns.length + (this.config.onEdit || this.config.onDelete ? 1 : 0);
            return `
                <tr>
                    <td colspan="${colSpan}" class="text-center text-muted py-4">
                        <i class="bi bi-inbox" style="font-size: 2rem;"></i>
                        <p class="mb-0 mt-2">Geen items gevonden</p>
                    </td>
                </tr>
            `;
        }
        
        return this.filteredData.map(row => {
            const cells = this.config.columns.map(col => {
                const value = this.getNestedValue(row, col.field);
                const formatted = col.format ? col.format(value, row) : value;
                return `<td>${formatted || '-'}</td>`;
            }).join('');
            
            let actions = '';
            if (this.config.onEdit || this.config.onDelete) {
                const idField = this.config.idField || 'id';
                const id = row[idField];
                
                // 🔴 UPDATED: Horizontal button group (not vertical!)
                actions = '<td class="text-end"><div class="btn-group btn-group-sm" role="group">';
                
                if (this.config.onEdit) {
                    actions += `<button class="btn btn-outline-primary" 
                                       onclick="${this.config.onEdit}(${id})" 
                                       title="Bewerken">
                                   <i class="bi bi-pencil"></i>
                               </button>`;
                }
                if (this.config.onDelete) {
                    actions += `<button class="btn btn-outline-danger" 
                                       onclick="${this.config.onDelete}(${id})" 
                                       title="Verwijderen">
                                   <i class="bi bi-trash"></i>
                               </button>`;
                }
                actions += '</div></td>';
            }
            
            return `<tr>${cells}${actions}</tr>`;
        }).join('');
    }
    
    getNestedValue(obj, path) {
        return path.split('.').reduce((value, key) => value?.[key], obj);
    }
    
    attachListeners(tableId) {
        // Search
        const searchInput = document.getElementById(`${tableId}-search`);
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.searchQuery = e.target.value.toLowerCase();
                this.filterData();
                this.updateTable(tableId);
            });
        }
        
        // Sort
        const headers = document.querySelectorAll(`#${tableId} th.sortable`);
        headers.forEach(header => {
            header.addEventListener('click', () => {
                const columnIndex = parseInt(header.dataset.column);
                this.sortData(columnIndex);
                this.updateTable(tableId);
                this.updateSortIcons(headers, header);
            });
        });
    }
    
    filterData() {
        if (!this.searchQuery) {
            this.filteredData = [...this.data];
            return;
        }
        
        this.filteredData = this.data.filter(row => {
            return this.config.columns.some(col => {
                const value = this.getNestedValue(row, col.field);
                return String(value || '').toLowerCase().includes(this.searchQuery);
            });
        });
    }
    
    sortData(columnIndex) {
        const column = this.config.columns[columnIndex];
        
        // Toggle direction if same column
        if (this.sortColumn === columnIndex) {
            this.sortDirection = this.sortDirection === 'asc' ? 'desc' : 'asc';
        } else {
            this.sortColumn = columnIndex;
            this.sortDirection = 'asc';
        }
        
        this.filteredData.sort((a, b) => {
            const aVal = this.getNestedValue(a, column.field);
            const bVal = this.getNestedValue(b, column.field);
            
            // Handle nulls
            if (aVal === null || aVal === undefined) return 1;
            if (bVal === null || bVal === undefined) return -1;
            
            // Compare
            let result = 0;
            if (typeof aVal === 'number' && typeof bVal === 'number') {
                result = aVal - bVal;
            } else {
                result = String(aVal).localeCompare(String(bVal));
            }
            
            return this.sortDirection === 'asc' ? result : -result;
        });
    }
    
    updateTable(tableId) {
        const tbody = document.getElementById(`${tableId}-body`);
        const countSpan = document.getElementById(`${tableId}-count`);
        
        if (tbody) tbody.innerHTML = this.renderRows();
        if (countSpan) countSpan.textContent = this.filteredData.length;
    }
    
    updateSortIcons(headers, activeHeader) {
        headers.forEach(header => {
            const icon = header.querySelector('.sort-icon');
            if (header === activeHeader) {
                icon.className = this.sortDirection === 'asc' ? 
                    'bi bi-chevron-up sort-icon' : 
                    'bi bi-chevron-down sort-icon';
            } else {
                icon.className = 'bi bi-chevron-expand sort-icon';
            }
        });
    }
    
    // Public method to refresh data
    refresh(newData) {
        this.data = newData;
        this.filterData();
        this.render();
    }
}

// Make DataTable global
window.DataTable = DataTable;

console.log('✅ Admin extensions loaded: Edit + DataTable (with horizontal buttons + width support)');
