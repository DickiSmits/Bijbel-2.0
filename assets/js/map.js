/**
 * Bijbelreader - Map Module
 * Leaflet map voor locaties
 */

console.log('📦 Loading map.js...');

// Global map variabelen
window.map = null;
window.locationsByName = {};
window.allLocations = [];

/**
 * Initialiseer de kaart
 */
window.initMap = function() {
    console.log('🗺️ Initializing map...');
    
    const mapElement = document.getElementById('map');
    if (!mapElement) {
        console.error('❌ Map element not found');
        return;
    }
    
    try {
        // Create Leaflet map centered on Israel/Palestine
        window.map = L.map('map').setView([31.7683, 35.2137], 7);
        
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '© OpenStreetMap contributors',
            maxZoom: 19
        }).addTo(window.map);
        
        // Fix map size after container is ready
        setTimeout(() => {
            window.map.invalidateSize();
        }, 500);
        
        console.log('✓ Map initialized');
    } catch (error) {
        console.error('❌ Error initializing map:', error);
    }
};

/**
 * Laad alle locaties op de kaart
 */
window.loadAllLocationsOnMap = async function() {
    console.log('📍 Loading locations...');
    
    if (!window.map) {
        console.error('❌ Map not initialized');
        return;
    }
    
    try {
        const locations = await window.apiCall('locations');
        
        if (locations && locations.length > 0) {
            window.allLocations = locations;
            window.locationsByName = {};
            
            locations.forEach(loc => {
                // Store by name (case-insensitive)
                window.locationsByName[loc.Naam.toLowerCase()] = loc;
                
                // Create marker
                const marker = L.marker([loc.Latitude, loc.Longitude])
                    .bindPopup(`<b>${loc.Naam}</b><br>${loc.Beschrijving || ''}<br><small>${loc.Type}</small>`)
                    .addTo(window.map);
                
                marker.locatieId = loc.Locatie_ID;
                marker.locatieNaam = loc.Naam;
            });
            
            console.log('✓ Loaded', locations.length, 'locations on map');
        } else {
            console.log('No locations found');
        }
    } catch (error) {
        console.error('❌ Error loading locations:', error);
    }
};

/**
 * Vind locaties in tekst
 */
window.findLocationsInText = function(text) {
    const foundLocations = [];
    
    if (!text || !window.allLocations || window.allLocations.length === 0) {
        return foundLocations;
    }
    
    const cleanText = text.replace(/<[^>]*>/g, ' ').toLowerCase();
    
    window.allLocations.forEach(location => {
        const locationName = location.Naam.toLowerCase();
        const regex = new RegExp('\\b' + locationName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'i');
        
        if (regex.test(cleanText)) {
            foundLocations.push(location);
        }
    });
    
    return foundLocations;
};

/**
 * Highlight locaties voor vers
 */
window.highlightLocationsForVerse = function(versId, verseText, fromClick = false) {
    if (!window.map) return;
    
    // Reset all markers
    window.map.eachLayer(layer => {
        if (layer instanceof L.Marker) {
            layer.setOpacity(0.5);
            layer.setZIndexOffset(0);
        }
    });
    
    // Find locations in text
    const locationsToShow = window.findLocationsInText(verseText);
    
    if (locationsToShow.length > 0) {
        console.log('Found', locationsToShow.length, 'locations:', locationsToShow.map(l => l.Naam).join(', '));
        
        let firstLocation = null;
        
        locationsToShow.forEach((loc, index) => {
            if (index === 0) firstLocation = loc;
            
            // Find marker
            let marker = null;
            window.map.eachLayer(layer => {
                if (layer instanceof L.Marker) {
                    const latLng = layer.getLatLng();
                    if (Math.abs(latLng.lat - loc.Latitude) < 0.0001 && 
                        Math.abs(latLng.lng - loc.Longitude) < 0.0001) {
                        marker = layer;
                    }
                }
            });
            
            if (marker) {
                marker.setOpacity(1.0);
                marker.setZIndexOffset(1000);
                
                if (fromClick && index === 0) {
                    marker.openPopup();
                }
            }
        });
        
        // Center map on locations
        if (locationsToShow.length === 1) {
            window.map.setView([firstLocation.Latitude, firstLocation.Longitude], 9, {
                animate: !fromClick
            });
        } else {
            const bounds = L.latLngBounds(
                locationsToShow.map(loc => [loc.Latitude, loc.Longitude])
            );
            window.map.fitBounds(bounds, {
                padding: [50, 50],
                animate: !fromClick
            });
        }
    } else {
        // No locations - reset opacity
        window.map.eachLayer(layer => {
            if (layer instanceof L.Marker) {
                layer.setOpacity(0.7);
            }
        });
    }
};

console.log('✓ Map module loaded');
