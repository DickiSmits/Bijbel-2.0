/**
 * Bijbelreader - Map Module
 * Leaflet kaart functionaliteit voor locaties
 */

// Global map variabelen
let map;
let locationsByName = {};
let allLocations = [];
let mapMarkers = [];

/**
 * Initialiseer de Leaflet kaart
 */
function initMap() {
    console.log('Initializing map...');
    
    // Initialize Map centered on Israel/Palestine
    map = L.map('map').setView([31.7683, 35.2137], 7);
    
    // Add OpenStreetMap tiles
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '© OpenStreetMap contributors',
        maxZoom: 19
    }).addTo(map);
    
    // Fix map size after container is ready
    setTimeout(() => {
        if (map) {
            map.invalidateSize();
        }
    }, 500);
    
    console.log('Map initialized');
}

/**
 * Laad alle locaties op de kaart
 */
async function loadAllLocationsOnMap() {
    console.log('Loading locations on map...');
    
    const locations = await apiCall('locations');
    
    if (locations && locations.length > 0) {
        // Build location name mapping
        locationsByName = {};
        allLocations = locations;
        mapMarkers = [];
        
        locations.forEach(loc => {
            // Store by exact name (case-insensitive key)
            locationsByName[loc.Naam.toLowerCase()] = loc;
            
            // Create marker
            const marker = L.marker([loc.Latitude, loc.Longitude])
                .bindPopup(`<b>${loc.Naam}</b><br>${loc.Beschrijving || ''}<br><small>${loc.Type}</small>`)
                .addTo(map);
            
            marker.locatieId = loc.Locatie_ID;
            marker.locatieNaam = loc.Naam;
            mapMarkers.push(marker);
        });
        
        console.log(`Loaded ${locations.length} locations on map`);
    } else {
        console.log('No locations found in database');
    }
}

/**
 * Zoek locaties genoemd in tekst
 */
function findLocationsInText(text) {
    const foundLocations = [];
    
    if (!text || !allLocations || allLocations.length === 0) {
        return foundLocations;
    }
    
    // Remove HTML tags and get clean text
    const cleanText = text.replace(/<[^>]*>/g, ' ').toLowerCase();
    
    // Check each location name
    allLocations.forEach(location => {
        const locationName = location.Naam.toLowerCase();
        
        // Check if location name appears as whole word in text
        const regex = new RegExp('\\b' + locationName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'i');
        
        if (regex.test(cleanText)) {
            foundLocations.push(location);
        }
    });
    
    return foundLocations;
}

/**
 * Highlight locaties voor een vers
 */
function highlightLocationsForVerse(versId, verseText, fromClick = false) {
    // Reset all markers to default style
    if (map) {
        map.eachLayer(layer => {
            if (layer instanceof L.Marker) {
                layer.setOpacity(0.5);
                layer.setZIndexOffset(0);
            }
        });
    }
    
    // Find locations in verse text
    let locationsToShow = findLocationsInText(verseText);
    
    // If no locations found in text, try verse_id mapping
    if (locationsToShow.length === 0 && versId) {
        apiCall(`locations&vers_id=${versId}`).then(verseLocations => {
            if (verseLocations && verseLocations.length > 0) {
                locationsToShow = verseLocations;
                highlightAndFocusLocations(locationsToShow, fromClick);
            }
        });
    } else if (locationsToShow.length > 0) {
        highlightAndFocusLocations(locationsToShow, fromClick);
    }
}

/**
 * Highlight en focus op locaties
 */
function highlightAndFocusLocations(locations, fromClick = false) {
    if (!map || locations.length === 0) return;
    
    let firstLocation = null;
    
    locations.forEach((loc, index) => {
        if (index === 0) firstLocation = loc;
        
        // Find marker for this location
        let marker = null;
        map.eachLayer(layer => {
            if (layer instanceof L.Marker) {
                const latLng = layer.getLatLng();
                if (Math.abs(latLng.lat - loc.Latitude) < 0.0001 && 
                    Math.abs(latLng.lng - loc.Longitude) < 0.0001) {
                    marker = layer;
                }
            }
        });
        
        if (!marker) {
            // Create new marker if not exists
            marker = L.marker([loc.Latitude, loc.Longitude]).addTo(map);
        }
        
        // Highlight this marker
        marker.setOpacity(1.0);
        marker.setZIndexOffset(1000);
        marker.bindPopup(`<b>${loc.Naam}</b><br>${loc.Beschrijving || ''}<br><small>${loc.Type}</small>`);
        
        // Open popup only on click
        if (fromClick && index === 0) {
            marker.openPopup();
        }
    });
    
    // Center map on locations
    if (locations.length === 1) {
        // Single location - center and zoom in
        map.setView([firstLocation.Latitude, firstLocation.Longitude], 9, {
            animate: !fromClick
        });
    } else {
        // Multiple locations - fit all in view
        const bounds = L.latLngBounds(
            locations.map(loc => [loc.Latitude, loc.Longitude])
        );
        map.fitBounds(bounds, {
            padding: [50, 50],
            animate: !fromClick
        });
    }
}

// Export functies voor gebruik in reader.js
if (typeof window !== 'undefined') {
    window.initMap = initMap;
    window.loadAllLocationsOnMap = loadAllLocationsOnMap;
    window.findLocationsInText = findLocationsInText;
    window.highlightLocationsForVerse = highlightLocationsForVerse;
}

console.log('Map module loaded');
