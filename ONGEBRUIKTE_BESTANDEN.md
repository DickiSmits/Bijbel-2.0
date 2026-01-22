# 📋 Ongebruikte Bestanden - Analyse

## ❌ ZEKER ONGEBRUIKT (Kunnen verwijderd worden)

### API Map - Oude versies met streepje vooraan
Deze bestanden zijn vervangen door de nieuwe routing in `index.php`:

1. **`api/- create_profile.php`** ❌
   - Vervangen door: `api/profiles.php` (via routing)
   - Reden: Oude versie, nu via `profiles` endpoint

2. **`api/- delete_profile.php`** ❌
   - Vervangen door: `api/profiles.php` (via routing)
   - Reden: Oude versie, nu via `profiles` endpoint

3. **`api/- delete_timeline_group.php`** ❌
   - Vervangen door: `api/timeline.php` (via routing)
   - Reden: Oude versie, nu via `timeline` endpoint

4. **`api/- delete_timeline.php`** ❌
   - Vervangen door: `api/timeline.php` (via routing)
   - Reden: Oude versie, nu via `timeline` endpoint

5. **`api/- get_timeline_group.php`** ❌
   - Vervangen door: `api/timeline.php` (via routing)
   - Reden: Oude versie, nu via `timeline` endpoint

6. **`api/- get_timeline.php`** ❌
   - Vervangen door: `api/timeline.php` (via routing)
   - Reden: Oude versie, nu via `timeline` endpoint

7. **`api/- save_timeline_group.php`** ❌
   - Vervangen door: `api/timeline.php` (via routing)
   - Reden: Oude versie, nu via `timeline` endpoint

8. **`api/- save_timeline.php`** ❌
   - Vervangen door: `api/timeline.php` (via routing)
   - Reden: Oude versie, nu via `timeline` endpoint

9. **`api/- timeline_groups.php`** ❌
   - Vervangen door: `api/timeline.php` (via routing)
   - Reden: Oude versie, nu via `timeline` endpoint

10. **`api/- timeline kopie.php`** ❌
    - Reden: Duidelijk een kopie/backup bestand

### Root Map - Oude versies
11. **`get_location.php`** ❌ (in root)
    - Vervangen door: `api/get_location.php`
    - Reden: Oude versie, nu in api map

12. **`get_timeline.php`** ❌ (in root)
    - Vervangen door: `api/timeline.php`
    - Reden: Oude versie, nu in api map

### JavaScript - Kopieën
13. **`assets/js/admin kopie.js`** ❌
    - Reden: Duidelijk een kopie/backup van `admin.js`

### Archief en Documentatie
14. **`api/Archief.zip`** ❌
    - Reden: Archief bestand, niet nodig in productie

15. **`BijbelUpdate.png`** ⚠️ (in root)
    - Reden: Mogelijk alleen voor documentatie
    - Check: Wordt dit gebruikt in README of documentatie?

## ⚠️ MOGELIJK ONGEBRUIKT (Eerst controleren)

### API Map
16. **`api/app.js`** ⚠️
    - Reden: JavaScript bestand in API map (ongebruikelijk)
    - Check: Wordt dit ergens geladen? Waarschijnlijk niet.

17. **`api/update_timeline_group.php`** ⚠️
    - Check: Wordt dit gebruikt? Mogelijk via `timeline.php` routing
    - Gebruikt endpoint: `update_timeline_group` (via timeline.php routing)

18. **`api/all_formatting.php`** ⚠️
    - Check: Wordt dit gebruikt? Niet gevonden in code
    - Mogelijk oude versie van formatting functionaliteit

### HTML
19. **`timeline-fullscreen.html`** ⚠️
    - Reden: Apart HTML bestand, niet geladen via index.php
    - Check: Wordt dit direct geopend? Mogelijk voor testing/demo

## ✅ GEBRUIKTE BESTANDEN (Niet verwijderen!)

### API Bestanden (via routing)
- `api/books.php` ✓
- `api/chapters.php` ✓
- `api/verses.php` ✓
- `api/verse_detail.php` ✓
- `api/profiles.php` ✓
- `api/chapter_profiles.php` ✓
- `api/timeline.php` ✓
- `api/locations.php` ✓
- `api/get_location.php` ✓
- `api/delete_location.php` ✓
- `api/save_location.php` ✓
- `api/images.php` ✓
- `api/delete_image.php` ✓
- `api/save_formatting.php` ✓
- `api/delete_formatting.php` ✓
- `api/notes.php` ✓
- `api/verse_profiles.php` ✓

### JavaScript Bestanden
- `assets/js/app.js` ✓
- `assets/js/reader.js` ✓
- `assets/js/map.js` ✓
- `assets/js/timeline.js` ✓
- `assets/js/timeline-admin.js` ✓
- `assets/js/admin.js` ✓
- `assets/js/admin-extensions.js` ✓
- `assets/js/admin-datatable-loaders.js` ✓
- `assets/js/admin-timeline-groups.js` ✓
- `assets/js/reader-images.js` ✓
- `assets/js/multi-profile-indicator.js` ✓
- `assets/js/reader-resize.js` ✓

### CSS Bestanden
- `assets/css/style.css` ✓
- `assets/css/admin-datatable.css` ✓
- `assets/css/multi-profile-indicator.css` ✓

## 📊 Samenvatting

| Categorie | Aantal | Actie |
|-----------|--------|-------|
| **Zeker ongebruikt** | 15 | ✅ Verwijderen |
| **Mogelijk ongebruikt** | 3 | ⚠️ Eerst controleren |
| **Totaal te verwijderen** | **18** | |

## 🗑️ Verwijder Commando's

```bash
# Zeker ongebruikte bestanden verwijderen
rm api/-create_profile.php
rm api/-delete_profile.php
rm api/-delete_timeline_group.php
rm api/-delete_timeline.php
rm api/-get_timeline_group.php
rm api/-get_timeline.php
rm api/-save_timeline_group.php
rm api/-save_timeline.php
rm api/-timeline_groups.php
rm "api/- timeline kopie.php"
rm get_location.php
rm get_timeline.php
rm "assets/js/admin kopie.js"
rm api/Archief.zip

# Mogelijk ongebruikte bestanden (eerst controleren!)
# rm api/app.js
# rm api/all_formatting.php
# rm timeline-fullscreen.html
```

## ⚠️ Let Op!

1. **Maak eerst een backup** voordat je bestanden verwijdert
2. **Test de applicatie** na verwijdering
3. **Controleer `timeline-fullscreen.html`** - mogelijk gebruikt voor demo/testing
4. **Check `api/app.js`** - waarom staat dit in de api map?

## 🔍 Verificatie

Na verwijdering, test deze functionaliteiten:
- ✅ Profile management (create, update, delete)
- ✅ Timeline management (alle CRUD operaties)
- ✅ Location management
- ✅ Image management
- ✅ Formatting management
- ✅ Notes management

---

**Datum**: 2025-01-22  
**Status**: Analyse compleet - Klaar voor cleanup
