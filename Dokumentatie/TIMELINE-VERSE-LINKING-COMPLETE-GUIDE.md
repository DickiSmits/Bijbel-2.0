# 📅 TIMELINE ↔ VERSE LINKING FEATURE

## ✨ WAT KRIJG JE?

### **IN ADMIN MODE:**
✅ **Kolom "Bijbelvers"** in timeline tabel  
✅ **Verse koppeling velden** in timeline editor  
✅ **Beschrijving veld** voor timeline events  
✅ **Edit functie** laadt verse koppelingen  

### **IN READER MODE:**
✅ **Klik vers** → Timeline pant naar dat event  
✅ **Automatische selectie** van timeline event  
✅ **Smooth animatie** (1 seconde)  
✅ **Werkt samen met location sync**  

---

## 📦 INSTALLATIE (6 bestanden)

### **STAP 1: Admin Interface (3 files)**

```
1. admin-TIMELINE-VERSE-LINKING.php
   → Upload naar: /views/admin.php (VERVANG!)
   
   Toevoegingen:
   - Beschrijving textarea
   - Vers Start input veld
   - Vers Eind input veld

2. admin-datatable-loaders-TIMELINE-FIX.js
   → Upload naar: /assets/js/admin-datatable-loaders.js (VERVANG!)
   
   Toevoegingen:
   - Bijbelvers kolom in tabel
   - Shows vers IDs met badges

3. admin-js-TIMELINE-PATCH.js
   → COPY/PASTE de functies naar je /assets/js/admin.js
   
   Zoek in admin.js:
   - async function saveTimeline() { ... }
   - async function editTimeline() { ... }
   
   Vervang met de nieuwe versies uit de patch file!
```

### **STAP 2: Reader Interface (2 files)**

```
4. timeline-VERSE-SYNC.js
   → Upload naar: /assets/js/timeline.js (VERVANG!)
   
   Nieuwe functies:
   - syncTimelineToVerse(versId)
   - findEventsForVerse(versId)
   - resetTimelineSelection()

5. reader-COMPLETE-SYNC.js
   → Upload naar: /assets/js/reader.js (VERVANG!)
   
   Updated selectVerse():
   - Triggert location highlight
   - Triggert timeline sync
```

### **STAP 3: API Endpoint Check**

```
Check of je API deze endpoints heeft:

✅ ?api=save_timeline (POST)
   Moet accepteren: vers_id_start, vers_id_end, beschrijving

✅ ?api=get_timeline&id=X
   Moet returnen: Vers_ID_Start, Vers_ID_End, Beschrijving

Als deze ontbreken, laat het me weten!
```

---

## 🧪 TESTEN

### **TEST 1: Admin - Verse Koppeling Velden**

```
1. Ga naar Admin mode
2. Open Timeline tab
3. Check timeline editor form

✅ Je ziet nu:
   - Titel veld
   - Groep dropdown
   - Beschrijving textarea (NIEUW!)
   - Start Datum
   - Eind Datum
   - Kleur
   - Start Bijbelvers (NIEUW!)
   - Eind Bijbelvers (NIEUW!)
```

### **TEST 2: Admin - Tabel met Verse Kolom**

```
1. Scroll naar "Timeline Events" tabel
2. Check kolommen

✅ Je ziet nu:
   - Titel
   - Start
   - Eind
   - Bijbelvers (NIEUW! - shows IDs met badges)
   - Type
   - Kleur
```

### **TEST 3: Admin - Event Opslaan met Verses**

```
1. Vul een nieuw event in:
   Titel: "Jezus' geboorte"
   Start Datum: -4
   Start Bijbelvers: (vers ID van Mattheüs 1:18)
   Eind Bijbelvers: (vers ID van Mattheüs 2:12)
   
2. Klik "Opslaan"

✅ Event verschijnt in tabel met verse IDs
```

### **TEST 4: Admin - Event Bewerken**

```
1. Klik "Edit" op een event in de tabel
2. Check of form wordt gevuld

✅ Alle velden gevuld inclusief:
   - Beschrijving
   - Start Bijbelvers
   - Eind Bijbelvers
```

### **TEST 5: Reader - Timeline Sync**

```
1. Ga naar Reader mode
2. Zoek een vers dat gekoppeld is aan timeline event
3. Klik op het vers

✅ Wat gebeurt er:
   - Vers wordt actief (blauw)
   - Kaart vliegt naar locatie (als aanwezig)
   - Timeline pant naar event datum (NIEUW!)
   - Timeline event wordt geselecteerd (oranje)
   - Smooth 1-sec animatie
```

### **TEST 6: Console Check**

```
F12 → Console

Bij verse click zie je:
📅 Found 1 timeline event(s) for verse 12345
✅ Timeline moved to event: Jezus' geboorte at -0004-12-25
```

---

## 🎯 HOE HET WERKT (Technisch)

### **Admin Side - Verse Linking:**

**HTML (admin.php):**
```html
<input id="timelineVersStart" placeholder="Bijv: Genesis 1:1 of ID nummer">
<input id="timelineVersEnd" placeholder="Bijv: Genesis 1:31 of ID nummer">
```

**JavaScript (admin.js):**
```javascript
const data = {
    vers_id_start: document.getElementById('timelineVersStart').value,
    vers_id_end: document.getElementById('timelineVersEnd').value
};
```

**DataTable (admin-datatable-loaders.js):**
```javascript
{
    label: 'Bijbelvers',
    format: (val, row) => {
        if (val) return `<span class="badge bg-info">${val}</span>`;
        return '-';
    }
}
```

---

### **Reader Side - Timeline Sync:**

**timeline.js - Find Events:**
```javascript
function findEventsForVerse(versId) {
    return allTimelineEvents.filter(event => {
        // Exact match
        if (event.Vers_ID_Start == versId) return true;
        
        // Range match (tussen start en eind)
        if (event.Vers_ID_Start && event.Vers_ID_End) {
            return versId >= event.Vers_ID_Start && 
                   versId <= event.Vers_ID_End;
        }
    });
}
```

**timeline.js - Sync Timeline:**
```javascript
function syncTimelineToVerse(versId) {
    const events = findEventsForVerse(versId);
    if (events.length === 0) return;
    
    const event = events[0];
    const item = timelineItems.get(event.Event_ID);
    
    // Select event
    timeline.setSelection([event.Event_ID]);
    
    // Pan to event
    timeline.moveTo(item.start, {
        animation: { duration: 1000 }
    });
}
```

**reader.js - Trigger Sync:**
```javascript
function selectVerse(versId) {
    // ... normale verse selection ...
    
    // NIEUW: Sync timeline
    if (window.syncTimelineToVerse) {
        window.syncTimelineToVerse(versId);
    }
}
```

---

## 📊 DATABASE VELDEN CHECK

**Timeline Events tabel moet hebben:**

```sql
SHOW COLUMNS FROM Timeline_Events;
```

**Expected:**
```
Event_ID          INT
Titel             VARCHAR
Beschrijving      TEXT      ← Check deze!
Start_Datum       VARCHAR
End_Datum         VARCHAR
Kleur             VARCHAR
Group_ID          INT
Vers_ID_Start     INT       ← Check deze!
Vers_ID_End       INT       ← Check deze!
```

**Als Vers_ID_Start/End ontbreken:**

```sql
ALTER TABLE Timeline_Events 
ADD COLUMN Vers_ID_Start INT NULL,
ADD COLUMN Vers_ID_End INT NULL;

ALTER TABLE Timeline_Events 
ADD COLUMN Beschrijving TEXT NULL;
```

---

## 🎨 VERSE ID'S VINDEN

### **Optie 1: Via Database**
```sql
SELECT Vers_ID, Bijbelboeknaam, Hoofdstuknummer, Versnummer, Tekst
FROM Verzen
WHERE Bijbelboeknaam = 'Mattheüs' 
  AND Hoofdstuknummer = 1 
  AND Versnummer = 18;
```

### **Optie 2: Via API**
```javascript
// In console (F12):
const verses = await apiCall('verses&boek=Mattheüs&hoofdstuk=1&limit=999');
const vers18 = verses.find(v => v.Versnummer === 18);
console.log('Vers ID:', vers18.Vers_ID);
```

### **Optie 3: Via Reader**
```
1. Ga naar Reader mode
2. Open Mattheüs 1
3. F12 → Console
4. Klik op vers 18
5. In console zie je:
   > currentVerse
   12345  ← Dit is het Vers_ID!
```

---

## 🔗 VERSE LINKING VOORBEELDEN

### **Voorbeeld 1: Single Verse Event**
```
Titel: "Jezus' doop"
Start Datum: 26
Start Bijbelvers: (ID van Mattheüs 3:13)
Eind Bijbelvers: (leeg)

Effect:
- Alleen Mattheüs 3:13 triggert dit event
```

### **Voorbeeld 2: Verse Range Event**
```
Titel: "Bergrede"
Start Datum: 28
Start Bijbelvers: (ID van Mattheüs 5:1)
Eind Bijbelvers: (ID van Mattheüs 7:29)

Effect:
- ALLE verzen van Mattheüs 5-7 triggeren dit event
- Timeline pant naar event bij elk vers in deze range
```

### **Voorbeeld 3: Multi-Gospel Event**
```
Je kunt 1 event maken per evangelie:

Event 1:
Titel: "Geboorte (Mattheüs)"
Start Bijbelvers: Mattheüs 1:18 ID
Eind Bijbelvers: Mattheüs 2:23 ID

Event 2:
Titel: "Geboorte (Lukas)"
Start Bijbelvers: Lukas 2:1 ID
Eind Bijbelvers: Lukas 2:40 ID

Both events op zelfde datum, maar triggeren op verschillende verzen!
```

---

## 🐛 TROUBLESHOOTING

### **Probleem: Verse velden niet zichtbaar in admin**

**Check:**
```
1. Is admin-TIMELINE-VERSE-LINKING.php correct geupload?
2. Hard refresh (Cmd+Shift+R)
3. F12 → Elements → Check of inputs bestaan:
   <input id="timelineVersStart">
   <input id="timelineVersEnd">
```

---

### **Probleem: Bijbelvers kolom niet in tabel**

**Check:**
```
1. Is admin-datatable-loaders-TIMELINE-FIX.js correct geupload?
2. In console:
   window.loadTimelineList()
3. Check tabel headers
```

---

### **Probleem: Timeline pant niet in reader**

**Check 1: Is timeline.js geladen?**
```javascript
// In console:
typeof window.syncTimelineToVerse
// Expected: "function"
// If "undefined" → timeline.js niet correct geladen
```

**Check 2: Zijn events geladen?**
```javascript
// In console:
window.apiCall('timeline').then(e => console.log(e.length + ' events'));
// Expected: aantal > 0
```

**Check 3: Is vers gekoppeld aan event?**
```javascript
// In console na verse click:
window.findEventsForVerse(currentVerse)
// Expected: [{Event_ID: ..., Titel: ...}]
// If [] → Geen event gekoppeld aan dit vers
```

**Check 4: Console errors?**
```
F12 → Console
Klik vers
Zie je errors? Share ze!
```

---

### **Probleem: Verse IDs niet opgeslagen**

**Check API:**
```javascript
// Test save:
const testData = {
    titel: 'Test Event',
    start_datum: '2024-01-01',
    vers_id_start: 123,
    vers_id_end: 456
};

fetch('?api=save_timeline', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify(testData)
}).then(r => r.json()).then(console.log);
```

**Expected response:**
```json
{
    "success": true,
    "message": "Timeline event opgeslagen",
    "event_id": 789
}
```

**Dan check database:**
```sql
SELECT * FROM Timeline_Events WHERE Event_ID = 789;
```

**Als Vers_ID_Start/End nog NULL zijn:**
→ API accept de velden niet! Check je save_timeline.php!

---

## 🎯 ENHANCED FEATURES (Optioneel)

### **Feature 1: Verse Badge in Timeline Tabel**

**Maak verse badges klikbaar:**

In admin-datatable-loaders.js:
```javascript
format: (val, row) => {
    if (!val) return '-';
    // Maak badge klikbaar
    return `<a href="?mode=reader&vers_id=${val}" target="_blank" 
               class="badge bg-info text-decoration-none">
               Vers ${val}
           </a>`;
}
```

Nu kun je verse ID direct openen in reader!

---

### **Feature 2: Verse Selector in Admin**

**Voeg verse lookup toe:**

In admin.php na timelineVersStart:
```html
<button class="btn btn-sm btn-outline-primary" 
        onclick="showVerseSelector('timelineVersStart')">
    <i class="bi bi-search"></i> Zoek vers
</button>
```

In admin.js:
```javascript
function showVerseSelector(targetInputId) {
    // Open modal met verse search/selection
    // Bij selectie: fill input met Vers_ID
    // TODO: Implement modal
}
```

---

### **Feature 3: Multiple Events per Verse**

**Timeline kan meerdere events tonen:**

In timeline.js - syncTimelineToVerse:
```javascript
// Currently: shows first event
const event = matchingEvents[0];

// Enhanced: show all events
matchingEvents.forEach(event => {
    timeline.setSelection([...timeline.getSelection(), event.Event_ID]);
});
```

---

## ✅ INSTALLATIE CHECKLIST

```
Admin Interface:
□ Download admin-TIMELINE-VERSE-LINKING.php
□ Upload naar /views/admin.php
□ Download admin-datatable-loaders-TIMELINE-FIX.js
□ Upload naar /assets/js/admin-datatable-loaders.js
□ Download admin-js-TIMELINE-PATCH.js
□ Copy functies naar /assets/js/admin.js
□ Hard refresh (Cmd+Shift+R)
□ Test: Timeline tab → Verse velden zichtbaar ✅
□ Test: Timeline tabel → Bijbelvers kolom zichtbaar ✅

Reader Interface:
□ Download timeline-VERSE-SYNC.js
□ Upload naar /assets/js/timeline.js
□ Download reader-COMPLETE-SYNC.js
□ Upload naar /assets/js/reader.js
□ Hard refresh (Cmd+Shift+R)
□ Test: Klik vers → Timeline pant ✅

Database:
□ Check Vers_ID_Start kolom bestaat
□ Check Vers_ID_End kolom bestaat
□ Check Beschrijving kolom bestaat
□ Als ontbreekt → Run ALTER TABLE queries

API:
□ Check save_timeline accepteert vers_id_start
□ Check save_timeline accepteert vers_id_end
□ Check get_timeline returnt Vers_ID_Start
□ Check get_timeline returnt Vers_ID_End

Final Test:
□ Admin: Create event met verse linking ✅
□ Reader: Click gekoppeld vers ✅
□ Timeline pant naar event ✅
□ Event wordt geselecteerd ✅
□ PERFECT! 🎉
```

---

## 🎉 RESULTAAT

**VOOR (Admin):**
```
Timeline editor:
- Titel
- Groep
- Start/Eind datum
- Kleur

Tabel:
- Titel, Start, Eind, Type, Kleur
```

**NA (Admin):**
```
Timeline editor:
- Titel
- Groep
- Beschrijving ✨
- Start/Eind datum
- Kleur
- Start Bijbelvers ✨
- Eind Bijbelvers ✨

Tabel:
- Titel, Start, Eind, Bijbelvers ✨, Type, Kleur
```

---

**VOOR (Reader):**
```
Klik vers → Vers actief
          → Kaart sync (locations)
```

**NA (Reader):**
```
Klik vers → Vers actief ✅
          → Kaart sync ✅
          → Timeline sync! ✨
          → Event selected ✨
          → Smooth pan ✨
          → WOW! 🎉
```

---

**Upload de 5 files en test!** 

Je krijgt een super interactieve Bible reader met:
- 🗺️ Location highlighting
- 📅 Timeline synchronization
- 🎯 Verse-centric navigation

**Dit wordt echt gaaf!** 🚀
