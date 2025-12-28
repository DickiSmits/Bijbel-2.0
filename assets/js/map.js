/**
 * MAP.JS - Leaflet kaart functionaliteit
 */

let map = null;

// Initialize map
function initMap() {
    console.log('Initializing map...');
    
    const mapElement = document.getElementById('map');
    if (!mapElement) {
        console.warn('Map element not found');
        return;
    }
    
    // Create map centered on Jerusalem
    map = L.map('map').setView([31.7683, 35.2137], 7);
    
    // Add tile layer
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    // Fix map size after container is ready
    setTimeout(() => {
        if (map) map.invalidateSize();
    }, 300);
    
    // Load locations
    loadLocations();
    
    console.log('Map initialized');
}

// Load locations from API
async function loadLocations() {
    const locations = await apiCall('locations');
    
    if (!locations || locations.length === 0) {
        console.log('No locations found');
        return;
    }
    
    console.log(`Loading ${locations.length} locations on map`);
    
    locations.forEach(loc => {
        const marker = L.marker([loc.Latitude, loc.Longitude])
            .bindPopup(`<b>${loc.Naam}</b><br>${loc.Beschrijving || ''}<br><small>${loc.Type}</small>`)
            .addTo(map);
        
        marker.locatieId = loc.Locatie_ID;
        marker.locatieNaam = loc.Naam;
    });
}

// Make global
window.initMap = initMap;
window.map = map;

console.log('Map.js loaded');
