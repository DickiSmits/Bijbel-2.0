# Bijbelreader - Installatiegids

## 📁 Bestandsstructuur Overzicht

```
bijbelreader/
│
├── 📄 index.php                    ✅ KLAAR - Hoofd router
├── 📄 config.php                   ✅ KLAAR - Configuratie
├── 📄 .htaccess                    ⏳ TODO - URL rewriting (optioneel)
├── 📄 README.md                    ✅ KLAAR - Deze documentatie
│
├── 📁 api/                         API Endpoints
│   ├── 📄 verses.php              ✅ KLAAR
│   ├── 📄 profiles.php            ✅ KLAAR
│   ├── 📄 timeline.php            ✅ KLAAR
│   ├── 📄 locations.php           ✅ KLAAR
│   ├── 📄 images.php              ✅ KLAAR
│   ├── 📄 notes.php               ✅ KLAAR
│   └── 📄 import-export.php       ⏳ TODO
│
├── 📁 includes/                    PHP Classes
│   ├── 📄 Database.php            ✅ KLAAR
│   ├── 📄 Auth.php                ✅ KLAAR
│   └── 📄 helpers.php             ✅ KLAAR
│
├── 📁 views/                       HTML Templates
│   ├── 📄 layout.php              ⏳ TODO
│   ├── 📄 reader.php              ✅ KLAAR (voorbeeld)
│   ├── 📄 admin.php               ⏳ TODO
│   └── 📄 login.php               ⏳ TODO
│
├── 📁 assets/                      Frontend Bestanden
│   ├── 📁 css/
│   │   └── 📄 styles.css          ✅ KLAAR
│   │
│   └── 📁 js/
│       ├── 📄 app.js              ✅ KLAAR
│       ├── 📄 reader.js           ⏳ TODO
│       ├── 📄 admin.js            ⏳ TODO
│       ├── 📄 timeline.js         ⏳ TODO
│       ├── 📄 map.js              ⏳ TODO
│       └── 📄 editor.js           ⏳ TODO
│
├── 📁 images/                      Uploaded Afbeeldingen
│
└── 📄 NWT-Bijbel.db               Jouw bestaande database
```

## 🚀 Installatie Stappen

### Stap 1: Bestanden Kopiëren

1. **Maak een backup** van je huidige `index.php`
2. **Download alle nieuwe bestanden** die ik heb gemaakt
3. **Kopieer ze** naar je webserver directory
4. **Behoud** je bestaande `NWT-Bijbel.db` database

### Stap 2: Configuratie Aanpassen

Edit `config.php` en pas aan:

```php
// Admin wachtwoord AANPASSEN!
define('ADMIN_PASSWORD', 'JOUW_STERKE_WACHTWOORD_HIER');

// Database pad controleren
define('DB_FILE', BASE_PATH . '/NWT-Bijbel.db');

// Images directory controleren  
define('IMAGES_DIR', BASE_PATH . '/images');
```

### Stap 3: Directories Aanmaken

Maak de volgende directories aan (als ze niet bestaan):

```bash
mkdir -p api
mkdir -p includes
mkdir -p views
mkdir -p assets/css
mkdir -p assets/js
mkdir -p images
```

Zorg dat `images/` schrijfbaar is:

```bash
chmod 755 images/
```

### Stap 4: Ontbrekende Bestanden Maken

Je moet nog de volgende bestanden maken door code te kopiëren uit je originele `index.php`:

#### A) `api/import-export.php`

Kopieer de export/import logica uit je originele bestand:

```php
<?php
$db = Database::getInstance();
$endpoint = $_GET['api'];

switch ($endpoint) {
    case 'export':
        // Kopieer de export code uit je originele bestand
        exportTable($db);
        break;
        
    case 'import':
        // Kopieer de import code uit je originele bestand
        importCSV($db);
        break;
}

function exportTable($db) {
    // ... je bestaande export code
}

function importCSV($db) {
    // ... je bestaande import code
}
```

#### B) Views Maken

**views/login.php** - Kopieer de login HTML uit je origineel:

```php
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title><?= APP_NAME ?> - Login</title>
    <link href="<?= BOOTSTRAP_CSS ?>" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <div class="container mt-5">
        <!-- Kopieer je login form HTML hier -->
    </div>
    
    <script src="<?= BOOTSTRAP_JS ?>"></script>
</body>
</html>
```

**views/admin.php** - Kopieer de admin HTML:

```php
<!DOCTYPE html>
<html lang="nl">
<head>
    <!-- Header zoals in reader.php -->
</head>
<body>
    <!-- Navbar -->
    <!-- Kopieer je admin interface HTML hier -->
    
    <!-- Scripts -->
    <script src="assets/js/app.js"></script>
    <script src="assets/js/admin.js"></script>
</body>
</html>
```

#### C) JavaScript Bestanden

**assets/js/reader.js** - Kopieer alle reader JavaScript:

```javascript
// Reader mode variabelen
let map, timeline, quill;
let currentBook = null, currentChapter = null;
// ... etc

// Initialize reader
async function initReader() {
    // ... je bestaande code
}

// ... alle andere reader functies
```

**assets/js/admin.js** - Kopieer alle admin JavaScript:

```javascript
// Admin mode functies
async function loadProfiles() {
    // ... je bestaande code
}

// ... etc
```

**assets/js/timeline.js** - Timeline specifieke functies:

```javascript
// Timeline initialisatie en functies
function initTimeline() {
    // ...
}
```

**assets/js/map.js** - Kaart functies:

```javascript
// Map initialisatie
function initMap() {
    // ...
}
```

**assets/js/editor.js** - Quill editor functies:

```javascript
// Quill editor functies
function initQuill() {
    // ...
}
```

### Stap 5: Testen

1. **Open** je website in browser
2. **Test** reader mode
3. **Test** login functionaliteit
4. **Test** admin functies
5. **Controleer** console op errors

### Stap 6: Debugging

Als er errors zijn:

1. **Check** browser console (F12)
2. **Check** PHP error log
3. **Controleer** bestandspaden in `config.php`
4. **Controleer** database connectie

## 🎯 Voordelen Checklist

Na migratie heb je:

- ✅ **Modulaire code** - Makkelijk te onderhouden
- ✅ **Betere organisatie** - Alles heeft z'n plek
- ✅ **API structuur** - Schaalbaar en uitbreidbaar
- ✅ **Security** - Input validatie, prepared statements
- ✅ **Herbruikbaar** - Helper functies en classes
- ✅ **Professional** - Best practices en patterns

## 📝 Nieuwe Functie Toevoegen - Voorbeeld

Stel je wilt een **Favorieten** functie toevoegen:

### 1. Database Tabel (gebruik Database klasse)

```php
// In includes/Database.php, voeg toe aan initializeTables():
$this->pdo->exec("CREATE TABLE IF NOT EXISTS Favorieten (
    Favoriet_ID INTEGER PRIMARY KEY AUTOINCREMENT,
    Vers_ID INTEGER NOT NULL,
    Toegevoegd DATETIME DEFAULT CURRENT_TIMESTAMP
)");
```

### 2. API Endpoint Maken

Maak `api/favorites.php`:

```php
<?php
$db = Database::getInstance();
$endpoint = $_GET['api'];

switch ($endpoint) {
    case 'add_favorite':
        addFavorite($db);
        break;
    case 'get_favorites':
        getFavorites($db);
        break;
    case 'remove_favorite':
        removeFavorite($db);
        break;
}

function addFavorite($db) {
    $data = getJsonInput();
    validateRequired($data, ['vers_id']);
    
    $sql = "INSERT INTO Favorieten (Vers_ID) VALUES (?)";
    $db->execute($sql, [$data['vers_id']]);
    
    jsonSuccess([], 'Favoriet toegevoegd');
}

function getFavorites($db) {
    $sql = "SELECT f.*, v.Bijbelboeknaam, v.Hoofdstuknummer, v.Versnummer, v.Tekst
            FROM Favorieten f
            JOIN De_Bijbel v ON f.Vers_ID = v.Vers_ID
            ORDER BY f.Toegevoegd DESC";
    
    $favorites = $db->query($sql);
    jsonResponse($favorites);
}

function removeFavorite($db) {
    $favorietId = (int)$_GET['id'];
    $db->execute("DELETE FROM Favorieten WHERE Favoriet_ID = ?", [$favorietId]);
    jsonSuccess([], 'Favoriet verwijderd');
}
```

### 3. Router Updaten

In `index.php`, voeg toe aan `getApiFile()`:

```php
$mapping = [
    // ... bestaande mappings
    'add_favorite' => 'favorites.php',
    'get_favorites' => 'favorites.php',
    'remove_favorite' => 'favorites.php',
];
```

### 4. JavaScript Functie

In `assets/js/reader.js` of een nieuw `assets/js/favorites.js`:

```javascript
async function addFavorite(versId) {
    const result = await apiCall('add_favorite', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ vers_id: versId })
    });
    
    if (result && result.success) {
        showNotification('Favoriet toegevoegd!');
        loadFavorites(); // Reload lijst
    }
}

async function loadFavorites() {
    const favorites = await apiCall('get_favorites');
    if (favorites) {
        displayFavorites(favorites);
    }
}

function displayFavorites(favorites) {
    // Display logica
    const container = document.getElementById('favoritesList');
    container.innerHTML = favorites.map(fav => `
        <div class="favorite-item">
            ${fav.Bijbelboeknaam} ${fav.Hoofdstuknummer}:${fav.Versnummer}
            <button onclick="removeFavorite(${fav.Favoriet_ID})">
                <i class="bi bi-trash"></i>
            </button>
        </div>
    `).join('');
}
```

### 5. UI Toevoegen

In je view (bijv. `views/reader.php`):

```html
<!-- Voeg favoriet knop toe bij elk vers -->
<button class="btn btn-sm btn-outline-primary" onclick="addFavorite(<?= $vers['Vers_ID'] ?>)">
    <i class="bi bi-star"></i> Favoriet
</button>

<!-- Voeg favorieten lijst toe in sidebar -->
<div id="favoritesList"></div>
```

## 🔧 Troubleshooting

### Probleem: "Class 'Database' not found"

**Oplossing:** Controleer of `config.php` correct wordt geladen en de autoloader werkt.

### Probleem: "API endpoint not found"

**Oplossing:** Controleer de mapping in `index.php` `getApiFile()` functie.

### Probleem: JavaScript errors

**Oplossing:** Controleer of alle `.js` bestanden correct worden geladen in de view.

### Probleem: Database errors

**Oplossing:** Controleer `DB_FILE` path in `config.php`.

## 📞 Support

Bij vragen of problemen:

1. Check de browser console voor JavaScript errors
2. Check PHP error log voor backend errors  
3. Gebruik `debugLog()` helper functie voor debugging
4. Vraag Claude om specifieke hulp bij problemen

## 🎉 Succes!

Je hebt nu een professionele, modulaire Bijbelreader applicatie die:

- ✅ Makkelijk uit te breiden is
- ✅ Goed onderhoudbaar is
- ✅ Volgt best practices
- ✅ Schaalbaar is voor de toekomst

Veel plezier met verder bouwen! 🚀
