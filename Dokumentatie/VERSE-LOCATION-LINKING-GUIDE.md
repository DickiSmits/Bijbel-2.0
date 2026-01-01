# 🗺️ VERSE → LOCATION LINKING FEATURE

## ✨ WAT DOET HET?

Wanneer je een vers aanklikt in reader mode:
1. ✅ Vers wordt actief (zoals altijd)
2. ✅ Systeem checkt of er plaatsnamen in het vers staan
3. ✅ Als match gevonden → Kaart centreert op die locatie
4. ✅ Marker wordt rood/highlighted
5. ✅ Popup opent automatisch

**VOORBEELD:**
```
Klik vers: "En Jezus kwam in Jeruzalem..."
→ Kaart vliegt naar Jeruzalem ✈️
→ Marker wordt rood 🔴
→ Popup toont info 💬
```

---

## 📦 INSTALLATIE (5 minuten)

### **STAP 1: Upload JavaScript files**

```
1. Download: map-VERSE-LOCATION-LINKING.js
   Upload naar: /assets/js/map.js (VERVANG!)

2. Download: reader-VERSE-LOCATION-LINKING.js
   Upload naar: /assets/js/reader.js (VERVANG!)
```

### **STAP 2: Upload CSS**

**OPTIE A: Toevoegen aan bestaand CSS bestand**
```
1. Download: map-highlighting.css
2. Open je main CSS file (bijv. /assets/css/style.css)
3. Plak de inhoud van map-highlighting.css onderaan
4. Save
```

**OPTIE B: Nieuw CSS bestand**
```
1. Download: map-highlighting.css
2. Upload naar: /assets/css/map-highlighting.css
3. Voeg toe aan je HTML <head>:
   <link rel="stylesheet" href="/assets/css/map-highlighting.css">
```

### **STAP 3: Test!**

```
1. Ga naar reader mode
2. Zoek een vers met een plaatsnaam (bijv. "Jeruzalem", "Bethlehem", "Nazareth")
3. Klik op het vers
4. Zie de kaart vliegen! ✈️
```

---

## 🔍 HOE HET WERKT (Technisch)

### **map.js - Nieuwe functies:**

**1. Location storage:**
```javascript
let allLocations = []; // Alle locaties uit database
let markers = {};      // Markers per location ID
let activeMarker = null; // Currently highlighted marker
```

**2. Icon system:**
```javascript
const defaultIcon = L.icon({...});      // Normale blauwe marker
const highlightIcon = L.icon({...});    // Grotere rode marker
```

**3. Location matching:**
```javascript
function findLocationInVerse(verseText) {
    // Case-insensitive search
    // Word boundary matching (geen partial matches)
    // Longest names first (voorkomt conflicts)
}
```

**4. Highlighting:**
```javascript
function highlightLocationFromVerse(verseText) {
    // Reset previous highlight
    // Find location in verse
    // Set red icon
    // Fly to location (animated!)
    // Open popup
}
```

### **reader.js - Updated selectVerse:**

```javascript
function selectVerse(versId) {
    // ... normale verse selection ...
    
    // NIEUW: Get verse text
    const verseText = verseTextElement.textContent;
    
    // NIEUW: Highlight location on map
    window.highlightLocationFromVerse(verseText);
}
```

---

## 🧪 TEST VOORBEELDEN

### **Test 1: Jeruzalem**
```
Zoek vers met "Jeruzalem"
Bijv: Johannes 2:13 - "Het pascha der Joden was nabij en Jezus ging op naar Jeruzalem"

Klik vers → Kaart vliegt naar Jeruzalem ✅
```

### **Test 2: Bethlehem**
```
Zoek vers met "Bethlehem"
Bijv: Mattheüs 2:1 - "Toen Jezus geboren was te Bethlehem"

Klik vers → Kaart vliegt naar Bethlehem ✅
```

### **Test 3: Nazareth**
```
Zoek vers met "Nazareth"
Bijv: Lukas 2:39 - "Zij keerden terug naar Galilea, naar hun stad Nazareth"

Klik vers → Kaart vliegt naar Nazareth ✅
```

### **Test 4: Geen locatie**
```
Klik vers zonder plaatsnaam
Bijv: Johannes 3:16 - "Want alzo lief heeft God de wereld gehad..."

Niets gebeurt op kaart (correct!) ✅
```

### **Test 5: Multiple clicks**
```
1. Klik vers met "Jeruzalem" → Kaart naar Jeruzalem
2. Klik vers met "Bethlehem" → Kaart naar Bethlehem (eerste marker weer normaal!)
3. Klik vers zonder locatie → Marker reset naar normaal

Switcht correct! ✅
```

---

## 🎨 CUSTOMIZATION

### **Marker kleuren aanpassen:**

**In map.js - highlightIcon:**
```javascript
// Wijzig icon size voor grotere/kleinere highlight
iconSize: [35, 57], // Default: groter dan normaal
// Maak kleiner: [30, 49]
// Maak groter: [40, 65]
```

**In map-highlighting.css:**
```css
/* Wijzig kleur van highlighted marker */
.highlighted-marker {
    /* Rood (current): */
    filter: invert(27%) sepia(99%) saturate(7462%) hue-rotate(354deg) brightness(96%) contrast(118%);
    
    /* Groen: */
    /* filter: invert(48%) sepia(79%) saturate(2476%) hue-rotate(86deg) brightness(118%) contrast(119%); */
    
    /* Oranje: */
    /* filter: invert(58%) sepia(88%) saturate(5844%) hue-rotate(360deg) brightness(104%) contrast(104%); */
    
    /* Paars: */
    /* filter: invert(27%) sepia(51%) saturate(2878%) hue-rotate(265deg) brightness(104%) contrast(97%); */
}
```

### **Animatie speed aanpassen:**

**In map.js - highlightLocationFromVerse:**
```javascript
map.flyTo([lat, lng], 10, {
    duration: 1.5  // Seconden
    // Sneller: 1.0
    // Langzamer: 2.0
});
```

### **Zoom level aanpassen:**

```javascript
map.flyTo([lat, lng], 10, {...});
//                     ^^
// Huidige zoom: 10
// Dichterbij: 12-15
// Verder weg: 7-9
```

---

## 🐛 TROUBLESHOOTING

### **Probleem: Kaart beweegt niet**

**Check 1: Is map.js geladen?**
```javascript
// In console:
console.log(typeof window.highlightLocationFromVerse);
// Expected: "function"
// Als "undefined" → map.js niet correct geladen
```

**Check 2: Zijn locations geladen?**
```javascript
// In console:
window.apiCall('locations').then(locs => console.log(locs.length + ' locations'));
// Expected: aantal > 0
```

**Check 3: Console errors?**
```
F12 → Console tab
Zie je rode errors? Share ze!
```

---

### **Probleem: Marker wordt niet rood**

**Check 1: CSS geladen?**
```
F12 → Elements tab → <head>
Zie je de CSS file?
```

**Check 2: CSS werkend?**
```javascript
// In console na verse click:
document.querySelector('.highlighted-marker');
// Expected: <img class="highlighted-marker ...">
// Als null → CSS class wordt niet toegepast
```

**Fix:** Upload map-highlighting.css opnieuw en check link in HTML

---

### **Probleem: Verkeerde locatie wordt gevonden**

**Oorzaak:** Meerdere locaties met vergelijkbare namen

**Voorbeeld:**
```
Vers: "Jezus ging naar Bethlehem Judea"
Locaties in DB:
- Bethlehem (Judea) ✅
- Bethlehem (Galilea) ❌

Systeem matched eerst gevonden "Bethlehem"
```

**Oplossing 1:** Specifiekere namen in database
```
Wijzig in database:
"Bethlehem" → "Bethlehem (Judea)"
"Bethlehem" → "Bethlehem (Galilea)"

Dan matched "Bethlehem (Judea)" correct!
```

**Oplossing 2:** Priority system (advanced)
```javascript
// In map.js, findLocationInVerse, add priority logic:
const sortedLocations = [...allLocations].sort((a, b) => {
    // Primary: Name length (longest first)
    if (b.Naam.length !== a.Naam.length) {
        return b.Naam.length - a.Naam.length;
    }
    // Secondary: Type priority
    const typePriority = { 'Stad': 3, 'Dorp': 2, 'Gebied': 1 };
    return (typePriority[b.Type] || 0) - (typePriority[a.Type] || 0);
});
```

---

## 📊 LOCATIONS DATABASE

**Voor beste resultaten, zorg dat je database bevat:**

### **Must-have locations:**
```
✅ Jeruzalem
✅ Bethlehem
✅ Nazareth
✅ Kapernaüm
✅ Jericho
✅ Samaria
✅ Galilea (gebied)
✅ Judea (gebied)
✅ Egypte
✅ Babylon
```

### **Nice-to-have:**
```
✅ Gethsemane
✅ Golgota
✅ Olivet (Olijfberg)
✅ Jordaan (rivier)
✅ Gennesaret (meer)
✅ Rode Zee
✅ Sinaï
✅ Damascus
```

### **Database velden check:**
```sql
SELECT Naam, Latitude, Longitude, Type 
FROM Locaties 
LIMIT 10;
```

**Expected output:**
```
Naam          | Latitude  | Longitude | Type
--------------|-----------|-----------|------
Jeruzalem     | 31.7683   | 35.2137   | Stad
Bethlehem     | 31.7054   | 35.2024   | Stad
Nazareth      | 32.7009   | 35.2976   | Stad
```

**Als coordinates ontbreken:** Voeg ze toe! Zonder coordinates geen map positioning.

---

## 🎯 ADVANCED FEATURES (Toekomstig)

**Mogelijke uitbreidingen:**

### **1. Multiple locations in 1 vers**
```javascript
// Currently: Matches eerste gevonden locatie
// Future: Show alle locaties, highlight dichtstbijzijnde
```

### **2. Location hints in verse**
```javascript
// Show subtle icon naast vers als het locatie bevat
<span class="verse">
    <i class="bi bi-geo-alt-fill text-primary"></i> Vers met locatie
</span>
```

### **3. Location filter**
```javascript
// Filter verzen op basis van locatie
"Toon alle verzen over Jeruzalem"
```

### **4. Route visualization**
```javascript
// Toon reis van Jezus op kaart
// Connect locations in chronologische volgorde
```

---

## ✅ CHECKLIST

```
□ Download map-VERSE-LOCATION-LINKING.js
□ Download reader-VERSE-LOCATION-LINKING.js
□ Download map-highlighting.css
□ Upload map.js naar server
□ Upload reader.js naar server
□ Upload/add CSS
□ Hard refresh (Cmd+Shift+R)
□ Test: Click vers met "Jeruzalem"
□ Kaart vliegt naar Jeruzalem! ✅
□ Marker is rood! ✅
□ Popup opent! ✅
□ Test andere locaties ✅
□ PERFECT! 🎉
```

---

## 🎉 RESULTAAT

**VOOR:**
```
Click vers → Vers wordt actief
Dat is alles
```

**NA:**
```
Click vers → Vers wordt actief ✅
           → Kaart vliegt naar locatie ✈️
           → Marker wordt rood 🔴
           → Popup toont info 💬
           → VEEL COOLER! 🎉
```

---

**Upload de 3 files en test!** 

**Dit wordt echt gaaf!** 🗺️🚀
