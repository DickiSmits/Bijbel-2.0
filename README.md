# Bijbelreader - Geherstructureerde Versie

## ✅ Wat is al klaar

### Backend (PHP)
- ✅ `config.php` - Alle configuratie instellingen
- ✅ `index.php` - Hoofd router
- ✅ `includes/Database.php` - Database klasse met singleton pattern
- ✅ `includes/Auth.php` - Authenticatie en sessie management
- ✅ `includes/helpers.php` - Helper functies (JSON, validatie, etc.)
- ✅ `api/verses.php` - Bijbelverzen API
- ✅ `api/profiles.php` - Profielen en opmaak API  
- ✅ `api/timeline.php` - Timeline events en groepen API
- ✅ `api/locations.php` - Locaties API
- ✅ `api/images.php` - Afbeeldingen upload/beheer API
- ✅ `api/notes.php` - Notities API

### Wat nog moet worden aangemaakt

#### Backend
- `api/import-export.php` - Import/Export functionaliteit

#### Views (HTML Templates)
- `views/layout.php` - Basis layout met header/footer
- `views/reader.php` - Reader modus view
- `views/admin.php` - Admin modus view  
- `views/login.php` - Login pagina

#### Frontend Assets
- `assets/css/styles.css` - Alle CSS styling
- `assets/js/app.js` - Algemene JavaScript functies
- `assets/js/reader.js` - Reader modus JavaScript
- `assets/js/admin.js` - Admin modus JavaScript
- `assets/js/timeline.js` - Timeline functionaliteit
- `assets/js/map.js` - Kaart functionaliteit
- `assets/js/editor.js` - Quill editor functies

#### Configuratie
- `.htaccess` - URL rewriting (optioneel)

## 🎯 Voordelen van Nieuwe Structuur

### 1. **Modulariteit**
- Elke functionaliteit in eigen bestand
- Makkelijk om specifieke delen aan te passen
- Betere code hergebruik

### 2. **Onderhoudbaarheid**
- Overzichtelijke mappenstructuur
- Logische scheiding van concerns (MVC-achtig)
- Makkelijker bugs te vinden en op te lossen

### 3. **Uitbreidbaarheid**
- Nieuwe API endpoints toevoegen = nieuw bestand in `api/`
- Nieuwe views toevoegen = nieuw bestand in `views/`
- Nieuwe JavaScript modules = nieuw bestand in `assets/js/`

### 4. **Professionaliteit**
- Singleton pattern voor database
- Proper error handling
- Security best practices (CSRF tokens, input validation)
- PSR-4 autoloading stijl

## 📝 Hoe Verder

### Stap 1: Maak de ontbrekende API endpoint

```php
// api/import-export.php
// Kopieer de export en import logica uit origineel bestand
// Gebruik $db = Database::getInstance()
// Gebruik helper functies zoals jsonResponse()
```

### Stap 2: Maak de Views

De views moeten de HTML bevatten die nu in je originele index.php staat.

**views/layout.php** - Basis template:
```php
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title><?= APP_NAME ?></title>
    <!-- Laad CSS -->
    <link href="<?= BOOTSTRAP_CSS ?>" rel="stylesheet">
    <link href="<?= BOOTSTRAP_ICONS ?>" rel="stylesheet">
    <link href="assets/css/styles.css" rel="stylesheet">
</head>
<body>
    <nav class="navbar">
        <!-- Navbar HTML -->
    </nav>
    
    <?php echo $content; ?>
    
    <!-- Laad JavaScript -->
    <script src="<?= BOOTSTRAP_JS ?>"></script>
    <script src="assets/js/app.js"></script>
</body>
</html>
```

**views/reader.php** - Reader interface
**views/admin.php** - Admin interface  
**views/login.php** - Login form

### Stap 3: Splits JavaScript

Maak de volgende JavaScript modules:

**assets/js/app.js**:
```javascript
// Algemene functies zoals showNotification, apiCall
const apiCall = async (endpoint, options = {}) => {
    const response = await fetch('?api=' + endpoint, options);
    return await response.json();
};

const showNotification = (message, isError = false) => {
    // Toast notification code
};
```

**assets/js/reader.js**:
```javascript
// Reader-specifieke code
// - Verse loading
// - Map initialization  
// - Timeline initialization
// - Scroll handling
```

**assets/js/admin.js**:
```javascript
// Admin-specifieke code
// - Form handling
// - List management
// - CRUD operaties
```

### Stap 4: CSS File

Verplaats alle `<style>` CSS naar `assets/css/styles.css`

### Stap 5: Test en Migreer

1. Test alle functionaliteit in nieuwe structuur
2. Fix eventuele bugs
3. Backup oude bestand
4. Deploy nieuwe versie

## 🚀 Nieuwe Functies Toevoegen

### Voorbeeld: Nieuwe "Favorieten" Functie

**1. Database** (gebruik bestaande Database klasse):
```php
$db = Database::getInstance();
$db->execute("CREATE TABLE IF NOT EXISTS Favorieten (...)");
```

**2. API Endpoint** (`api/favorites.php`):
```php
<?php
$db = Database::getInstance();
$endpoint = $_GET['api'];

switch ($endpoint) {
    case 'add_favorite':
        addFavorite($db);
        break;
    // etc...
}

function addFavorite($db) {
    $data = getJsonInput();
    // Implementatie
    jsonSuccess([], 'Favoriet toegevoegd');
}
```

**3. Update Router** (in `index.php`):
```php
function getApiFile($endpoint) {
    $mapping = [
        // ... bestaande mappings
        'add_favorite' => 'favorites.php',
        'get_favorites' => 'favorites.php',
    ];
    return $mapping[$endpoint] ?? 'unknown.php';
}
```

**4. JavaScript** (`assets/js/favorites.js`):
```javascript
async function addFavorite(versId) {
    const result = await apiCall('add_favorite', {
        method: 'POST',
        body: JSON.stringify({ vers_id: versId })
    });
    if (result.success) {
        showNotification('Favoriet toegevoegd!');
    }
}
```

## 📚 Code Voorbeelden

### Database Query
```php
$db = Database::getInstance();
$verses = $db->query("SELECT * FROM De_Bijbel WHERE Vers_ID = ?", [$versId]);
```

### API Response
```php
jsonSuccess(['data' => $result], 'Operatie geslaagd');
jsonError('Fout opgetreden', 400);
```

### Validatie
```php
$data = getJsonInput();
validateRequired($data, ['naam', 'email']);
```

### Authentication
```php
if (!Auth::isAdmin()) {
    jsonError('Niet geautoriseerd', 403);
}
```

## 🔒 Security Verbeteringen

De nieuwe structuur bevat:
- Input sanitization via helper functies
- CSRF token support in Auth klasse
- Prepared statements in Database klasse
- File upload validatie
- Admin rechten controles

## 📖 Documentatie

Elke functie heeft docblocks:
```php
/**
 * Haal verzen op met filtering
 * 
 * @param Database $db Database instantie
 * @return void JSON response
 */
function getVerses($db) {
    // ...
}
```

## 🎨 Volgende Stappen

1. Maak de resterende bestanden aan (zie lijstje bovenaan)
2. Kopieer de relevante HTML/CSS/JS uit je originele bestand
3. Test grondig alle functionaliteit
4. Voeg extra features toe zoals favorieten, bookmarks, etc.

De basis infrastructuur staat nu. Het is nu veel makkelijker om:
- Bugs te fixen (je weet waar te zoeken)
- Features toe te voegen (nieuwe files maken)
- Code te onderhouden (geen 2000+ regels meer in 1 bestand)
- Met Claude samen te werken (Claude kan specifieke modules begrijpen)

Succes! 🚀
