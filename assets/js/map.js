/**
 * MAP.JS - Leaflet kaart functionaliteit met verse-location koppeling
 */

let map = null;
let markers = {}; // Store markers by location ID
let allLocations = []; // Store all location data
let activeMarker = null; // Track currently highlighted marker

// Custom icons
const defaultIcon = L.icon({
    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    iconSize: [25, 41],
    iconAnchor: [12, 41],
    popupAnchor: [1, -34],
    shadowSize: [41, 41]
});

const highlightIcon = L.icon({
    iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
    shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
    iconSize: [35, 57], // Larger
    iconAnchor: [17, 57],
    popupAnchor: [1, -47],
    shadowSize: [57, 57],
    className: 'highlighted-marker' // For red color via CSS
});

// Initialize map
function initMap() {
    console.log('🗺️ Initializing map...');
    
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
    
    console.log('✅ Map initialized');
}

// Load locations from API
async function loadLocations() {
    const locations = await window.apiCall('locations');
    
    if (!locations || locations.length === 0) {
        console.log('No locations found');
        return;
    }
    
    console.log(`🗺️ Loading ${locations.length} locations on map`);
    
    // Store all locations globally
    allLocations = locations;
    
    locations.forEach(loc => {
        const marker = L.marker([loc.Latitude, loc.Longitude], { icon: defaultIcon })
            .bindPopup(`<b>${loc.Naam}</b><br>${loc.Beschrijving || ''}<br><small>${loc.Type}</small>`)
            .addTo(map);
        
        // Store marker with location data
        markers[loc.Locatie_ID] = {
            marker: marker,
            data: loc
        };
    });
    
    console.log(`✅ Loaded ${locations.length} locations`);
}

/**
 * Escape special regex characters in a string
 * @param {string} str - String to escape
 * @returns {string} - Escaped string safe for regex
 */
function escapeRegex(str) {
    return str.replace(/[.*+?^${}()|[\]\\]/g, '\\$&');
}

/**
 * Find location name in verse text
 * @param {string} verseText - The verse text to search
 * @returns {object|null} - Location object if found, null otherwise
 */
function findLocationInVerse(verseText) {
    if (!verseText || allLocations.length === 0) {
        return null;
    }
    
    // Normalize verse text (lowercase for case-insensitive matching)
    const normalizedVerse = verseText.toLowerCase();
    
    // Sort locations by name length (longest first) to match specific names before generic ones
    const sortedLocations = [...allLocations].sort((a, b) => b.Naam.length - a.Naam.length);
    
    // Check each location name
    for (const location of sortedLocations) {
        const locationName = location.Naam.toLowerCase();
        
        // Escape special regex characters (like [ ] in "Filadelfia [Klein-Azië]")
        const escapedName = escapeRegex(locationName);
        
        // Check if location name appears in verse
        // Use word boundaries to avoid partial matches
        const regex = new RegExp(`\\b${escapedName}\\b`, 'i');
        
        if (regex.test(normalizedVerse)) {
            console.log(`📍 Found location in verse: ${location.Naam}`);
            return location;
        }
    }
    
    return null;
}

/**
 * Highlight location on map based on verse content
 * @param {string} verseText - The verse text
 */
function highlightLocationFromVerse(verseText) {
    // Reset previous highlight
    if (activeMarker) {
        activeMarker.marker.setIcon(defaultIcon);
        activeMarker = null;
    }
    
    // Find location in verse
    const location = findLocationInVerse(verseText);
    
    if (!location) {
        console.log('📍 No location found in verse');
        return;
    }
    
    // Get marker for this location
    const markerObj = markers[location.Locatie_ID];
    
    if (!markerObj) {
        console.warn('⚠️ Marker not found for location:', location.Naam);
        return;
    }
    
    // Highlight marker
    markerObj.marker.setIcon(highlightIcon);
    activeMarker = markerObj;
    
    // Center map on location with animation
    map.flyTo([location.Latitude, location.Longitude], 10, {
        duration: 1.5
    });
    
    // Open popup
    markerObj.marker.openPopup();
    
    console.log(`✅ Highlighted location: ${location.Naam} at [${location.Latitude}, ${location.Longitude}]`);
}

/**
 * Reset all markers to default state
 */
function resetMapHighlights() {
    if (activeMarker) {
        activeMarker.marker.setIcon(defaultIcon);
        activeMarker = null;
    }
}

// Make functions global
window.initMap = initMap;
window.map = map;
window.highlightLocationFromVerse = highlightLocationFromVerse;
window.resetMapHighlights = resetMapHighlights;
window.findLocationInVerse = findLocationInVerse;

console.log('✅ Map.js loaded with verse-location linking');