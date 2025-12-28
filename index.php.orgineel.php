<?php
/*
 * BIJBELREADER APPLICATIE
 * 
 * BELANGRIJK: De tabel "De_Bijbel" wordt NOOIT bewerkt!
 * Alle wijzigingen en opmaak worden opgeslagen in de "Opmaak" tabel.
 * De originele bijbeltekst blijft altijd intact en ongewijzigd.
 */

session_start();

// Load config if exists, otherwise use defaults
if (file_exists('config.php')) {
    require_once 'config.php';
} else {
    // Default admin wachtwoord - VERANDER DIT!
    $ADMIN_PASSWORD = 'bijbel2024';
    $db_file = 'NWT-Bijbel.db';
    $images_dir = 'images';
}

// Login/Logout handling
if (isset($_POST['login'])) {
    if ($_POST['password'] === $ADMIN_PASSWORD) {
        $_SESSION['admin_logged_in'] = true;
        header('Location: ?mode=admin');
        exit;
    } else {
        $login_error = 'Onjuist wachtwoord';
    }
}

if (isset($_GET['logout'])) {
    unset($_SESSION['admin_logged_in']);
    header('Location: ?mode=reader');
    exit;
}

// Check admin access
$is_admin = isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true;

// Database configuratie
$db_file = 'NWT-Bijbel.db';
$db = new PDO("sqlite:$db_file");
$db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

// Zorg dat de Notities tabel bestaat
$db->exec("CREATE TABLE IF NOT EXISTS Notities (
    Notitie_ID INTEGER PRIMARY KEY AUTOINCREMENT,
    Titel TEXT,
    Inhoud TEXT,
    Aangemaakt DATETIME DEFAULT CURRENT_TIMESTAMP,
    Gewijzigd DATETIME DEFAULT CURRENT_TIMESTAMP
)");

// Migratie: Voeg Tekst_Kleur kolom toe aan Timeline_Events als deze nog niet bestaat
try {
    $columns = $db->query("PRAGMA table_info(Timeline_Events)")->fetchAll(PDO::FETCH_ASSOC);
    $hasTextColor = false;
    foreach ($columns as $col) {
        if ($col['name'] === 'Tekst_Kleur') {
            $hasTextColor = true;
            break;
        }
    }
    if (!$hasTextColor) {
        $db->exec("ALTER TABLE Timeline_Events ADD COLUMN Tekst_Kleur TEXT");
    }
} catch (Exception $e) {
    // Table might not exist yet, that's okay
}

// Zorg dat de images directory bestaat
if (!file_exists($images_dir)) {
    mkdir($images_dir, 0755, true);
}

// API Endpoints voor AJAX requests
if (isset($_GET['api'])) {
    header('Content-Type: application/json');
    
    try {
        switch ($_GET['api']) {
            case 'verses':
                // Lazy loading van bijbelverzen
                $offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
                $limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 50;
                $boek = isset($_GET['boek']) ? $_GET['boek'] : null;
                $hoofdstuk = isset($_GET['hoofdstuk']) ? $_GET['hoofdstuk'] : null;
                $profiel_id = isset($_GET['profiel_id']) ? (int)$_GET['profiel_id'] : null;
                $vers_id = isset($_GET['vers_id']) ? (int)$_GET['vers_id'] : null;
                $after_vers_id = isset($_GET['after_vers_id']) ? (int)$_GET['after_vers_id'] : null;
                $before_vers_id = isset($_GET['before_vers_id']) ? (int)$_GET['before_vers_id'] : null;
                
                $query = "SELECT v.Vers_ID, v.Boeknummer, v.Bijbelboeknaam, v.Hoofdstuknummer, 
                         v.Versnummer, v.Tekst";
                
                if ($profiel_id) {
                    $query .= ", o.Opgemaakte_Tekst";
                }
                
                $query .= " FROM De_Bijbel v";
                
                if ($profiel_id) {
                    $query .= " LEFT JOIN Opmaak o ON v.Vers_ID = o.Vers_ID AND o.Profiel_ID = ?";
                }
                
                $where = [];
                $params = [];
                
                if ($profiel_id) {
                    $params[] = $profiel_id;
                }
                
                if ($vers_id) {
                    $where[] = "v.Vers_ID = ?";
                    $params[] = $vers_id;
                }
                
                // If after_vers_id is set, get verses after that ID (for forward scrolling)
                if ($after_vers_id) {
                    $where[] = "v.Vers_ID > ?";
                    $params[] = $after_vers_id;
                }
                
                // If before_vers_id is set, get verses before that ID (for backward scrolling)
                if ($before_vers_id) {
                    $where[] = "v.Vers_ID < ?";
                    $params[] = $before_vers_id;
                }
                
                if ($boek) {
                    $where[] = "v.Bijbelboeknaam = ?";
                    $params[] = $boek;
                }
                
                if ($hoofdstuk) {
                    $where[] = "v.Hoofdstuknummer = ?";
                    $params[] = $hoofdstuk;
                }
                
                if ($where) {
                    $query .= " WHERE " . implode(" AND ", $where);
                }
                
                // For backward loading, order DESC and then reverse results
                if ($before_vers_id) {
                    $query .= " ORDER BY v.Vers_ID DESC LIMIT ? OFFSET ?";
                } else {
                    $query .= " ORDER BY v.Vers_ID LIMIT ? OFFSET ?";
                }
                $params[] = $limit;
                $params[] = $offset;
                
                $stmt = $db->prepare($query);
                $stmt->execute($params);
                $verses = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Reverse results for backward loading so they're in correct order
                if ($before_vers_id) {
                    $verses = array_reverse($verses);
                }
                
                echo json_encode($verses);
                break;
                
            case 'books':
                // Lijst van alle bijbelboeken
                $stmt = $db->query("SELECT DISTINCT Bijbelboeknaam, MIN(Vers_ID) as First_ID 
                                   FROM De_Bijbel GROUP BY Bijbelboeknaam ORDER BY First_ID");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
                
            case 'chapters':
                // Hoofdstukken van een boek
                $boek = $_GET['boek'];
                $stmt = $db->prepare("SELECT DISTINCT Hoofdstuknummer 
                                     FROM De_Bijbel WHERE Bijbelboeknaam = ? 
                                     ORDER BY CAST(Hoofdstuknummer AS INTEGER)");
                $stmt->execute([$boek]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
                
            case 'search':
                // Zoeken in bijbeltekst
                $query = $_GET['query'];
                $stmt = $db->prepare("SELECT Vers_ID, Bijbelboeknaam, Hoofdstuknummer, 
                                     Versnummer, Tekst FROM De_Bijbel 
                                     WHERE Tekst LIKE ? LIMIT 100");
                $stmt->execute(['%' . $query . '%']);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
                
            case 'profiles':
                // Ophalen van alle profielen
                $stmt = $db->query("SELECT * FROM Profielen ORDER BY Profiel_Naam");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
                
            case 'timeline':
                // Timeline events ophalen met groep info en vers referenties
                $vers_id = isset($_GET['vers_id']) ? (int)$_GET['vers_id'] : null;
                
                if ($vers_id) {
                    $stmt = $db->prepare("SELECT e.*, g.Groep_Naam, g.Kleur as Groep_Kleur,
                                         v1.Bijbelboeknaam as Start_Boek, v1.Hoofdstuknummer as Start_Hoofdstuk, 
                                         v1.Versnummer as Start_Vers,
                                         v2.Bijbelboeknaam as End_Boek, v2.Hoofdstuknummer as End_Hoofdstuk, 
                                         v2.Versnummer as End_Vers
                                         FROM Timeline_Events e 
                                         LEFT JOIN Timeline_Groups g ON e.Group_ID = g.Group_ID
                                         LEFT JOIN De_Bijbel v1 ON e.Vers_ID_Start = v1.Vers_ID
                                         LEFT JOIN De_Bijbel v2 ON e.Vers_ID_End = v2.Vers_ID
                                         WHERE (e.Vers_ID_Start <= ? AND e.Vers_ID_End >= ?) 
                                         OR (e.Vers_ID_Start = ? AND e.Vers_ID_End IS NULL)
                                         ORDER BY e.Start_Datum");
                    $stmt->execute([$vers_id, $vers_id, $vers_id]);
                } else {
                    $stmt = $db->query("SELECT e.*, g.Groep_Naam, g.Kleur as Groep_Kleur,
                                       v1.Bijbelboeknaam as Start_Boek, v1.Hoofdstuknummer as Start_Hoofdstuk, 
                                       v1.Versnummer as Start_Vers,
                                       v2.Bijbelboeknaam as End_Boek, v2.Hoofdstuknummer as End_Hoofdstuk, 
                                       v2.Versnummer as End_Vers
                                       FROM Timeline_Events e 
                                       LEFT JOIN Timeline_Groups g ON e.Group_ID = g.Group_ID
                                       LEFT JOIN De_Bijbel v1 ON e.Vers_ID_Start = v1.Vers_ID
                                       LEFT JOIN De_Bijbel v2 ON e.Vers_ID_End = v2.Vers_ID
                                       ORDER BY e.Start_Datum");
                }
                
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
                
            case 'locations':
                // Locaties ophalen, met of zonder vers filter
                $vers_id = isset($_GET['vers_id']) ? (int)$_GET['vers_id'] : null;
                
                if ($vers_id) {
                    $stmt = $db->prepare("SELECT l.*, vl.Context 
                                         FROM Locaties l 
                                         JOIN Vers_Locaties vl ON l.Locatie_ID = vl.Locatie_ID 
                                         WHERE vl.Vers_ID = ?");
                    $stmt->execute([$vers_id]);
                } else {
                    // Get all locations with their linked verses
                    $stmt = $db->query("SELECT l.*, 
                                       GROUP_CONCAT(v.Bijbelboeknaam || ' ' || v.Hoofdstuknummer || ':' || v.Versnummer, ', ') as Gekoppelde_Verzen
                                       FROM Locaties l 
                                       LEFT JOIN Vers_Locaties vl ON l.Locatie_ID = vl.Locatie_ID
                                       LEFT JOIN De_Bijbel v ON vl.Vers_ID = v.Vers_ID
                                       GROUP BY l.Locatie_ID");
                }
                
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
                
            case 'verse_detail':
                // Details van een specifiek vers ophalen
                $vers_id = (int)$_GET['vers_id'];
                $profiel_id = isset($_GET['profiel_id']) ? (int)$_GET['profiel_id'] : null;
                
                if ($profiel_id) {
                    // Met profiel - haal opgemaakte tekst op
                    $query = "SELECT v.*, o.Opgemaakte_Tekst 
                             FROM De_Bijbel v 
                             LEFT JOIN Opmaak o ON v.Vers_ID = o.Vers_ID AND o.Profiel_ID = ?
                             WHERE v.Vers_ID = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$profiel_id, $vers_id]);
                } else {
                    // Zonder profiel - alleen originele tekst
                    $query = "SELECT v.*, NULL as Opgemaakte_Tekst 
                             FROM De_Bijbel v 
                             WHERE v.Vers_ID = ?";
                    $stmt = $db->prepare($query);
                    $stmt->execute([$vers_id]);
                }
                
                echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
                break;
                
            case 'verse_images':
                // Afbeeldingen voor een vers ophalen
                $vers_id = (int)$_GET['vers_id'];
                $stmt = $db->prepare("SELECT * FROM Afbeeldingen WHERE Vers_ID = ? ORDER BY Afbeelding_ID");
                $stmt->execute([$vers_id]);
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
                
            case 'get_vers_id':
                // Haal vers_id op basis van boek, hoofdstuk, vers
                $boek = $_GET['boek'];
                $hoofdstuk = $_GET['hoofdstuk'];
                $vers = $_GET['vers'];
                
                $stmt = $db->prepare("SELECT Vers_ID FROM De_Bijbel 
                                     WHERE Bijbelboeknaam = ? AND Hoofdstuknummer = ? AND Versnummer = ?");
                $stmt->execute([$boek, $hoofdstuk, $vers]);
                $result = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($result) {
                    echo json_encode(['success' => true, 'vers_id' => $result['Vers_ID']]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Vers niet gevonden']);
                }
                break;
                
            case 'get_vers_info':
                // Haal boek/hoofdstuk/vers op basis van vers_id
                $vers_id = (int)$_GET['vers_id'];
                $stmt = $db->prepare("SELECT Bijbelboeknaam, Hoofdstuknummer, Versnummer 
                                     FROM De_Bijbel WHERE Vers_ID = ?");
                $stmt->execute([$vers_id]);
                echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
                break;
                
            case 'save_formatting':
                // Opmaak opslaan
                $data = json_decode(file_get_contents('php://input'), true);
                $profiel_id = (int)$data['profiel_id'];
                $vers_id = (int)$data['vers_id'];
                $tekst = $data['tekst'];
                
                $stmt = $db->prepare("INSERT OR REPLACE INTO Opmaak 
                                     (Profiel_ID, Vers_ID, Opgemaakte_Tekst, Laatst_Gewijzigd) 
                                     VALUES (?, ?, ?, CURRENT_TIMESTAMP)");
                $stmt->execute([$profiel_id, $vers_id, $tekst]);
                
                echo json_encode(['success' => true]);
                break;
                
            case 'all_formatting':
                // Alle bewerkte verzen ophalen met vers info
                $stmt = $db->query("SELECT o.*, p.Profiel_Naam, v.Bijbelboeknaam, v.Hoofdstuknummer, v.Versnummer, v.Tekst as Originele_Tekst
                                   FROM Opmaak o
                                   JOIN Profielen p ON o.Profiel_ID = p.Profiel_ID
                                   JOIN De_Bijbel v ON o.Vers_ID = v.Vers_ID
                                   ORDER BY v.Bijbelboeknaam, v.Hoofdstuknummer, v.Versnummer");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
                
            case 'delete_formatting':
                // Opmaak verwijderen
                $profiel_id = (int)$_GET['profiel_id'];
                $vers_id = (int)$_GET['vers_id'];
                
                $stmt = $db->prepare("DELETE FROM Opmaak WHERE Profiel_ID = ? AND Vers_ID = ?");
                $stmt->execute([$profiel_id, $vers_id]);
                
                echo json_encode(['success' => true]);
                break;
                
            case 'create_profile':
                $data = json_decode(file_get_contents('php://input'), true);
                $stmt = $db->prepare("INSERT INTO Profielen (Profiel_Naam, Beschrijving) VALUES (?, ?)");
                $stmt->execute([$data['naam'], $data['beschrijving']]);
                echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
                break;
                
            case 'delete_profile':
                $profiel_id = (int)$_GET['id'];
                $stmt = $db->prepare("DELETE FROM Profielen WHERE Profiel_ID = ?");
                $stmt->execute([$profiel_id]);
                echo json_encode(['success' => true]);
                break;
                
            case 'save_timeline':
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (isset($data['event_id']) && $data['event_id']) {
                    $stmt = $db->prepare("UPDATE Timeline_Events SET Titel=?, Beschrijving=?, 
                                         Start_Datum=?, End_Datum=?, Vers_ID_Start=?, Vers_ID_End=?, 
                                         Type=?, Kleur=?, Tekst_Kleur=?, Group_ID=? WHERE Event_ID=?");
                    $stmt->execute([
                        $data['titel'], $data['beschrijving'], $data['start_datum'],
                        $data['end_datum'], $data['vers_id_start'], $data['vers_id_end'],
                        $data['type'], $data['kleur'], $data['tekst_kleur'], $data['group_id'], $data['event_id']
                    ]);
                } else {
                    $stmt = $db->prepare("INSERT INTO Timeline_Events 
                                         (Titel, Beschrijving, Start_Datum, End_Datum, Vers_ID_Start, 
                                         Vers_ID_End, Type, Kleur, Tekst_Kleur, Group_ID) 
                                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $data['titel'], $data['beschrijving'], $data['start_datum'],
                        $data['end_datum'], $data['vers_id_start'], $data['vers_id_end'],
                        $data['type'], $data['kleur'], $data['tekst_kleur'], $data['group_id']
                    ]);
                }
                
                echo json_encode(['success' => true]);
                break;
                
            case 'delete_timeline':
                $event_id = (int)$_GET['id'];
                $stmt = $db->prepare("DELETE FROM Timeline_Events WHERE Event_ID = ?");
                $stmt->execute([$event_id]);
                echo json_encode(['success' => true]);
                break;
                
            case 'get_timeline':
                $event_id = (int)$_GET['id'];
                $stmt = $db->prepare("SELECT * FROM Timeline_Events WHERE Event_ID = ?");
                $stmt->execute([$event_id]);
                echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
                break;
                
            case 'get_location':
                $locatie_id = (int)$_GET['id'];
                $stmt = $db->prepare("SELECT * FROM Locaties WHERE Locatie_ID = ?");
                $stmt->execute([$locatie_id]);
                echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
                break;
                
            case 'get_image':
                $afbeelding_id = (int)$_GET['id'];
                $stmt = $db->prepare("SELECT a.*, v.Bijbelboeknaam, v.Hoofdstuknummer, v.Versnummer 
                                     FROM Afbeeldingen a 
                                     LEFT JOIN De_Bijbel v ON a.Vers_ID = v.Vers_ID 
                                     WHERE a.Afbeelding_ID = ?");
                $stmt->execute([$afbeelding_id]);
                echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
                break;
                
            case 'all_images':
                $stmt = $db->query("SELECT a.*, v.Bijbelboeknaam, v.Hoofdstuknummer, v.Versnummer 
                                   FROM Afbeeldingen a 
                                   LEFT JOIN De_Bijbel v ON a.Vers_ID = v.Vers_ID 
                                   ORDER BY a.Geupload_Op DESC");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
                
            case 'update_image':
                $data = json_decode(file_get_contents('php://input'), true);
                $stmt = $db->prepare("UPDATE Afbeeldingen SET Vers_ID=?, Caption=?, 
                                     Uitlijning=?, Breedte=?, Hoogte=? WHERE Afbeelding_ID=?");
                $stmt->execute([
                    $data['vers_id'], $data['caption'], $data['uitlijning'],
                    $data['breedte'], $data['hoogte'], $data['afbeelding_id']
                ]);
                echo json_encode(['success' => true]);
                break;
                
            case 'delete_image':
                $afbeelding_id = (int)$_GET['id'];
                $stmt = $db->prepare("SELECT Bestandspad FROM Afbeeldingen WHERE Afbeelding_ID = ?");
                $stmt->execute([$afbeelding_id]);
                $image = $stmt->fetch(PDO::FETCH_ASSOC);
                
                if ($image && file_exists($image['Bestandspad'])) {
                    unlink($image['Bestandspad']);
                }
                
                $stmt = $db->prepare("DELETE FROM Afbeeldingen WHERE Afbeelding_ID = ?");
                $stmt->execute([$afbeelding_id]);
                echo json_encode(['success' => true]);
                break;
                
            case 'delete_location':
                $locatie_id = (int)$_GET['id'];
                $stmt = $db->prepare("DELETE FROM Locaties WHERE Locatie_ID = ?");
                $stmt->execute([$locatie_id]);
                echo json_encode(['success' => true]);
                break;
                
            case 'timeline_groups':
                $stmt = $db->query("SELECT * FROM Timeline_Groups ORDER BY Volgorde, Groep_Naam");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
                
            case 'create_timeline_group':
                $data = json_decode(file_get_contents('php://input'), true);
                $stmt = $db->prepare("INSERT INTO Timeline_Groups (Groep_Naam, Beschrijving, Kleur, Zichtbaar, Volgorde) 
                                     VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([
                    $data['naam'], 
                    $data['beschrijving'] ?? '', 
                    $data['kleur'] ?? '#4A90E2',
                    $data['zichtbaar'] ?? 1,
                    $data['volgorde'] ?? 0
                ]);
                echo json_encode(['success' => true, 'id' => $db->lastInsertId()]);
                break;
                
            case 'update_timeline_group':
                $data = json_decode(file_get_contents('php://input'), true);
                $stmt = $db->prepare("UPDATE Timeline_Groups SET Groep_Naam=?, Beschrijving=?, Kleur=?, 
                                     Zichtbaar=?, Volgorde=? WHERE Group_ID=?");
                $stmt->execute([
                    $data['naam'], 
                    $data['beschrijving'], 
                    $data['kleur'],
                    $data['zichtbaar'],
                    $data['volgorde'],
                    $data['group_id']
                ]);
                echo json_encode(['success' => true]);
                break;
                
            case 'delete_timeline_group':
                $group_id = (int)$_GET['id'];
                $stmt = $db->prepare("DELETE FROM Timeline_Groups WHERE Group_ID = ?");
                $stmt->execute([$group_id]);
                echo json_encode(['success' => true]);
                break;
                
            case 'toggle_timeline_group':
                $group_id = (int)$_GET['id'];
                $stmt = $db->prepare("UPDATE Timeline_Groups SET Zichtbaar = 1 - Zichtbaar WHERE Group_ID = ?");
                $stmt->execute([$group_id]);
                echo json_encode(['success' => true]);
                break;
                
            case 'save_location':
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (isset($data['locatie_id']) && $data['locatie_id']) {
                    $stmt = $db->prepare("UPDATE Locaties SET Naam=?, Latitude=?, Longitude=?, 
                                         Beschrijving=?, Type=?, Icon=? WHERE Locatie_ID=?");
                    $stmt->execute([
                        $data['naam'], $data['latitude'], $data['longitude'],
                        $data['beschrijving'], $data['type'], $data['icon'], $data['locatie_id']
                    ]);
                } else {
                    $stmt = $db->prepare("INSERT INTO Locaties 
                                         (Naam, Latitude, Longitude, Beschrijving, Type, Icon) 
                                         VALUES (?, ?, ?, ?, ?, ?)");
                    $stmt->execute([
                        $data['naam'], $data['latitude'], $data['longitude'],
                        $data['beschrijving'], $data['type'], $data['icon']
                    ]);
                }
                
                echo json_encode(['success' => true]);
                break;
                
            case 'link_verse_location':
                $data = json_decode(file_get_contents('php://input'), true);
                $stmt = $db->prepare("INSERT OR IGNORE INTO Vers_Locaties 
                                     (Vers_ID, Locatie_ID, Context) VALUES (?, ?, ?)");
                $stmt->execute([$data['vers_id'], $data['locatie_id'], $data['context']]);
                echo json_encode(['success' => true]);
                break;
                
            case 'upload_image':
                if (isset($_FILES['image'])) {
                    $file = $_FILES['image'];
                    $filename = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file['name']);
                    $filepath = 'images/' . $filename;
                    
                    if (move_uploaded_file($file['tmp_name'], $filepath)) {
                        $vers_id = isset($_POST['vers_id']) ? (int)$_POST['vers_id'] : null;
                        $caption = isset($_POST['caption']) ? $_POST['caption'] : '';
                        $uitlijning = isset($_POST['uitlijning']) ? $_POST['uitlijning'] : 'center';
                        $breedte = isset($_POST['breedte']) ? (int)$_POST['breedte'] : 400;
                        $hoogte = isset($_POST['hoogte']) && $_POST['hoogte'] ? (int)$_POST['hoogte'] : null;
                        
                        $stmt = $db->prepare("INSERT INTO Afbeeldingen 
                                             (Bestandsnaam, Originele_Naam, Bestandspad, Vers_ID, Caption, 
                                              Uitlijning, Breedte, Hoogte) 
                                             VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                        $stmt->execute([$filename, $file['name'], $filepath, $vers_id, $caption, 
                                       $uitlijning, $breedte, $hoogte]);
                        
                        echo json_encode([
                            'success' => true,
                            'id' => $db->lastInsertId(),
                            'url' => $filepath
                        ]);
                    } else {
                        echo json_encode(['success' => false, 'error' => 'Upload mislukt']);
                    }
                } else {
                    echo json_encode(['success' => false, 'error' => 'Geen bestand ontvangen']);
                }
                break;
                
            case 'export':
                // Export tabel naar CSV
                $table = $_GET['table'] ?? '';
                
                $queries = [
                    'profiles' => "SELECT * FROM Profielen",
                    'formatting' => "SELECT o.*, p.Profiel_Naam, v.Bijbelboeknaam, v.Hoofdstuknummer, v.Versnummer 
                                   FROM Opmaak o 
                                   JOIN Profielen p ON o.Profiel_ID = p.Profiel_ID
                                   JOIN De_Bijbel v ON o.Vers_ID = v.Vers_ID",
                    'timeline_events' => "SELECT * FROM Timeline_Events",
                    'timeline_groups' => "SELECT * FROM Timeline_Groups",
                    'locations' => "SELECT * FROM Locaties",
                    'verse_locations' => "SELECT vl.*, l.Naam as Locatie_Naam, v.Bijbelboeknaam, v.Hoofdstuknummer, v.Versnummer
                                        FROM Vers_Locaties vl
                                        JOIN Locaties l ON vl.Locatie_ID = l.Locatie_ID
                                        JOIN De_Bijbel v ON vl.Vers_ID = v.Vers_ID",
                    'images' => "SELECT * FROM Afbeeldingen"
                ];
                
                if (!isset($queries[$table])) {
                    echo json_encode(['error' => 'Onbekende tabel']);
                    break;
                }
                
                $stmt = $db->query($queries[$table]);
                $data = $stmt->fetchAll(PDO::FETCH_ASSOC);
                
                // Set headers for CSV download
                header('Content-Type: text/csv; charset=utf-8');
                header('Content-Disposition: attachment; filename="' . $table . '_' . date('Y-m-d') . '.csv"');
                
                // Output CSV
                $output = fopen('php://output', 'w');
                
                // UTF-8 BOM for Excel compatibility
                fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
                
                if (count($data) > 0) {
                    // Headers
                    fputcsv($output, array_keys($data[0]));
                    
                    // Data
                    foreach ($data as $row) {
                        fputcsv($output, $row);
                    }
                }
                
                fclose($output);
                exit;
                
            case 'import':
                // Import CSV data
                if (!isset($_FILES['file'])) {
                    echo json_encode(['error' => 'Geen bestand ontvangen']);
                    break;
                }
                
                $table = $_POST['table'] ?? '';
                $overwrite = isset($_POST['overwrite']) && $_POST['overwrite'] === 'true';
                
                $tableMap = [
                    'profiles' => 'Profielen',
                    'formatting' => 'Opmaak',
                    'timeline_events' => 'Timeline_Events',
                    'timeline_groups' => 'Timeline_Groups',
                    'locations' => 'Locaties',
                    'verse_locations' => 'Vers_Locaties',
                    'images' => 'Afbeeldingen'
                ];
                
                if (!isset($tableMap[$table])) {
                    echo json_encode(['error' => 'Onbekende tabel']);
                    break;
                }
                
                $file = $_FILES['file']['tmp_name'];
                $handle = fopen($file, 'r');
                
                if (!$handle) {
                    echo json_encode(['error' => 'Kan bestand niet lezen']);
                    break;
                }
                
                // Read headers
                $headers = fgetcsv($handle);
                if (!$headers) {
                    echo json_encode(['error' => 'Geen headers gevonden']);
                    break;
                }
                
                $tableName = $tableMap[$table];
                $imported = 0;
                $errors = [];
                
                $db->beginTransaction();
                
                try {
                    while (($data = fgetcsv($handle)) !== false) {
                        if (count($data) !== count($headers)) {
                            $errors[] = "Rij overgeslagen: verkeerd aantal kolommen";
                            continue;
                        }
                        
                        $row = array_combine($headers, $data);
                        
                        // Build INSERT query
                        $columns = array_keys($row);
                        $placeholders = array_fill(0, count($columns), '?');
                        
                        $sql = $overwrite ? "INSERT OR REPLACE" : "INSERT";
                        $sql .= " INTO $tableName (" . implode(', ', $columns) . ") VALUES (" . implode(', ', $placeholders) . ")";
                        
                        $stmt = $db->prepare($sql);
                        
                        // Convert empty strings to NULL
                        $values = array_map(function($v) {
                            return $v === '' ? null : $v;
                        }, array_values($row));
                        
                        $stmt->execute($values);
                        $imported++;
                    }
                    
                    $db->commit();
                    echo json_encode([
                        'success' => true,
                        'imported' => $imported,
                        'errors' => $errors
                    ]);
                    
                } catch (Exception $e) {
                    $db->rollBack();
                    echo json_encode(['error' => 'Import mislukt: ' . $e->getMessage()]);
                }
                
                fclose($handle);
                break;
            
            // ============= NOTITIES API =============
            case 'notes':
                // Alle notities ophalen
                $stmt = $db->query("SELECT * FROM Notities ORDER BY Gewijzigd DESC");
                echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
                break;
                
            case 'get_note':
                // Enkele notitie ophalen
                $id = (int)$_GET['id'];
                $stmt = $db->prepare("SELECT * FROM Notities WHERE Notitie_ID = ?");
                $stmt->execute([$id]);
                echo json_encode($stmt->fetch(PDO::FETCH_ASSOC));
                break;
                
            case 'save_note':
                // Notitie opslaan of updaten
                $data = json_decode(file_get_contents('php://input'), true);
                
                if (!empty($data['notitie_id'])) {
                    // Update bestaande notitie
                    $stmt = $db->prepare("UPDATE Notities SET Titel = ?, Inhoud = ?, Gewijzigd = CURRENT_TIMESTAMP WHERE Notitie_ID = ?");
                    $stmt->execute([$data['titel'], $data['inhoud'], $data['notitie_id']]);
                    echo json_encode(['success' => true, 'notitie_id' => $data['notitie_id']]);
                } else {
                    // Nieuwe notitie
                    $stmt = $db->prepare("INSERT INTO Notities (Titel, Inhoud) VALUES (?, ?)");
                    $stmt->execute([$data['titel'] ?? 'Nieuwe notitie', $data['inhoud'] ?? '']);
                    echo json_encode(['success' => true, 'notitie_id' => $db->lastInsertId()]);
                }
                break;
                
            case 'delete_note':
                // Notitie verwijderen
                $id = (int)$_GET['id'];
                $stmt = $db->prepare("DELETE FROM Notities WHERE Notitie_ID = ?");
                $stmt->execute([$id]);
                echo json_encode(['success' => true]);
                break;
                
            default:
                echo json_encode(['error' => 'Onbekende API call']);
        }
    } catch (Exception $e) {
        echo json_encode(['error' => $e->getMessage()]);
    }
    
    exit;
}

// Check of we in admin mode zijn
$requested_mode = isset($_GET['mode']) ? $_GET['mode'] : 'reader';

// Protect admin mode with password
if ($requested_mode === 'admin' && !$is_admin) {
    // Show login form instead of admin mode
    $mode = 'login';
} else {
    $mode = $requested_mode;
}
?>
<!DOCTYPE html>
<html lang="nl" data-bs-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bijbelreader <?php echo $mode === 'admin' ? '- Admin' : ''; ?></title>
    
    <!-- Bootstrap 5 -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css" rel="stylesheet">
    
    <!-- Externe bibliotheken -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/vis-timeline@7.7.3/styles/vis-timeline-graph2d.min.css" />
    <link href="https://cdn.quilljs.com/1.3.6/quill.snow.css" rel="stylesheet">
    
    <style>
        /* Minimal custom CSS - alleen wat Bootstrap niet dekt */
        :root {
            --primary-color: #2c5282;
            --bs-primary: #2c5282;
        }
        
        body {
            font-family: 'Georgia', 'Times New Roman', serif;
        }
        
        /* Reader Layout */
        .reader-layout {
            display: grid;
            grid-template-columns: 2fr 4px 1fr;
            grid-template-rows: 1fr 4px 250px;
            height: calc(100vh - 120px);
            gap: 0;
        }
        
        .bible-panel {
            grid-column: 1;
            grid-row: 1;
            overflow-y: auto;
            padding: 1rem;
            background: #fff;
        }
        
        .resize-handle-v {
            grid-column: 2;
            grid-row: 1;
            background: #dee2e6;
            cursor: col-resize;
        }
        
        .resize-handle-v:hover {
            background: var(--primary-color);
        }
        
        .map-panel {
            grid-column: 3;
            grid-row: 1;
        }
        
        .resize-handle-h {
            grid-column: 1 / -1;
            grid-row: 2;
            background: #dee2e6;
            cursor: row-resize;
        }
        
        .resize-handle-h:hover {
            background: var(--primary-color);
        }
        
        .timeline-panel {
            grid-column: 1 / -1;
            grid-row: 3;
            position: relative;
            background: #fff;
        }
        
        #map {
            height: 100%;
            width: 100%;
        }
        
        #timeline {
            height: 100%;
            width: 100%;
        }
        
        /* Timeline search results */
        .timeline-search-result {
            transition: background-color 0.15s;
        }
        
        .timeline-search-result:hover {
            background-color: #f8f9fa;
        }
        
        .timeline-search-result mark {
            background-color: #fff3cd;
            padding: 0 2px;
            border-radius: 2px;
        }
        
        /* Timeline popup */
        .timeline-popup {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 2000;
            align-items: center;
            justify-content: center;
        }
        
        .timeline-popup.show {
            display: flex;
        }
        
        .timeline-popup-content {
            background: white;
            border-radius: 12px;
            max-width: 500px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            box-shadow: 0 10px 40px rgba(0,0,0,0.3);
            position: relative;
        }
        
        .timeline-popup-close {
            position: absolute;
            top: 10px;
            right: 10px;
            background: none;
            border: none;
            font-size: 1.2rem;
            color: #666;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 4px;
        }
        
        .timeline-popup-close:hover {
            background: #f0f0f0;
            color: #333;
        }
        
        .timeline-popup-header {
            padding: 20px 20px 10px;
            border-bottom: 1px solid #eee;
        }
        
        .timeline-popup-header h5 {
            margin: 0 0 10px;
            padding-right: 30px;
            color: #333;
        }
        
        .timeline-popup-meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        
        .timeline-popup-body {
            padding: 20px;
        }
        
        .timeline-popup-body #timelinePopupDescription {
            line-height: 1.6;
            color: #555;
        }
        
        .timeline-popup-body #timelinePopupDescription p {
            margin: 0 0 0.5rem 0;
        }
        
        .timeline-popup-body #timelinePopupDescription p:last-child {
            margin-bottom: 0;
        }
        
        .timeline-popup-body #timelinePopupDescription ul,
        .timeline-popup-body #timelinePopupDescription ol {
            margin: 0.5rem 0;
            padding-left: 1.5rem;
        }
        
        .timeline-popup-body #timelinePopupDescription a {
            color: var(--primary-color);
        }
        
        .timeline-popup-footer {
            padding: 15px 20px;
            border-top: 1px solid #eee;
            background: #f8f9fa;
            border-radius: 0 0 12px 12px;
        }
        
        /* Smaller/thinner timeline items */
        .vis-item {
            font-size: 11px !important;
            height: auto !important;
            min-height: 18px !important;
            line-height: 1.2 !important;
            padding: 2px 6px !important;
            border-radius: 3px !important;
        }
        
        .vis-item .vis-item-content {
            padding: 0 !important;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .vis-item.vis-range {
            min-height: 16px !important;
        }
        
        .vis-item.vis-box {
            min-height: 18px !important;
        }
        
        .vis-label {
            font-size: 11px !important;
        }
        
        .vis-group {
            min-height: 28px !important;
            padding-top: 2px !important;
        }
        
        .vis-labelset .vis-label {
            padding: 2px 5px !important;
            pointer-events: none !important; /* Don't block clicks on timeline items */
        }
        
        .vis-labelset {
            pointer-events: none !important; /* Labels don't block clicks */
        }
        
        /* Make sure timeline items are always clickable */
        .vis-item {
            pointer-events: auto !important;
            z-index: 10 !important;
            cursor: pointer !important;
        }
        
        /* Ensure the timeline content area allows clicks through to items */
        .vis-panel.vis-center,
        .vis-content {
            pointer-events: none !important;
        }
        
        /* But allow the actual item content to be clickable */
        .vis-item-content {
            pointer-events: auto !important;
        }
        
        /* Make sure group backgrounds don't block clicks */
        .vis-panel.vis-background {
            pointer-events: none !important;
        }
        
        /* Verse styling */
        .verse {
            padding: 0.5rem;
            border-radius: 4px;
            cursor: pointer;
            transition: background-color 0.2s;
        }
        
        .verse:hover {
            background-color: #f8f9fa;
        }
        
        .verse.active {
            background-color: #e3f2fd;
            border-left: 3px solid var(--primary-color);
        }
        
        .verse-number {
            font-weight: bold;
            color: var(--primary-color);
            margin-right: 0.5rem;
            font-size: 0.85em;
            vertical-align: super;
        }
        
        /* Fix for Quill paragraph tags in verse text */
        .verse-text p {
            display: inline;
            margin: 0;
            padding: 0;
        }
        
        .verse-text p + p::before {
            content: ' ';
        }
        
        .chapter-header {
            font-size: 1.5rem;
            font-weight: bold;
            color: var(--primary-color);
            padding: 1rem;
            margin: 0 -1rem 1rem -1rem;
            border-bottom: 2px solid #dee2e6;
            position: sticky;
            top: 0;
            background: #fff;
            z-index: 100;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }
        
        /* Verse images */
        .verse-image-container {
            margin: 1rem 0;
            padding: 0.5rem;
            background: #f8f9fa;
            border-radius: 8px;
        }
        
        .verse-image-container.align-left {
            float: left;
            margin-right: 1rem;
            max-width: 40%;
        }
        
        .verse-image-container.align-right {
            float: right;
            margin-left: 1rem;
            max-width: 40%;
        }
        
        .verse-image-container.align-center {
            text-align: center;
            clear: both;
        }
        
        .verse-image {
            max-width: 100%;
            border-radius: 4px;
            cursor: pointer;
        }
        
        .verse-image-caption {
            font-size: 0.85rem;
            color: #6c757d;
            text-align: center;
            margin-top: 0.5rem;
            font-style: italic;
        }
        
        /* Timeline navigation */
        .timeline-nav-btn {
            position: absolute;
            top: 50%;
            transform: translateY(-50%);
            z-index: 100;
            width: 36px;
            height: 36px;
            border-radius: 50%;
            border: none;
            background: var(--primary-color);
            color: white;
            font-size: 1.2rem;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 8px rgba(0,0,0,0.2);
        }
        
        .timeline-nav-btn:hover {
            background: #1a365d;
        }
        
        .timeline-nav-prev { left: 10px; }
        .timeline-nav-next { right: 10px; }
        
        /* Admin layout */
        .admin-layout {
            display: flex;
            height: calc(100vh - 70px);
        }
        
        .admin-sidebar {
            width: 250px;
            background: #f8f9fa;
            border-right: 1px solid #dee2e6;
            padding: 1rem;
            overflow-y: auto;
        }
        
        .admin-content {
            flex: 1;
            padding: 1.5rem;
            overflow-y: auto;
        }
        
        /* Quill editor styling */
        .ql-container {
            font-family: 'Georgia', serif;
            font-size: 1rem;
        }
        
        .ql-editor {
            min-height: 150px;
        }
        
        #editor-container .ql-editor {
            min-height: 150px;
            max-height: 150px;
            overflow-y: auto;
        }
        
        /* Chapter editor verse items */
        .chapter-verse-item {
            border: 1px solid #dee2e6;
            border-radius: 0.375rem;
            margin-bottom: 0.5rem;
            background: #fff;
        }
        
        .chapter-verse-item:hover {
            border-color: #86b7fe;
        }
        
        .chapter-verse-item.modified {
            border-color: #ffc107;
            background: #fffbeb;
        }
        
        .chapter-verse-header {
            display: flex;
            align-items: center;
            padding: 0.5rem;
            background: #f8f9fa;
            border-bottom: 1px solid #dee2e6;
            border-radius: 0.375rem 0.375rem 0 0;
            gap: 0.5rem;
        }
        
        .chapter-verse-number {
            font-weight: bold;
            color: #6c757d;
            min-width: 30px;
        }
        
        .chapter-verse-original {
            flex: 1;
            font-size: 0.85rem;
            color: #6c757d;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        
        .chapter-verse-status {
            font-size: 0.75rem;
        }
        
        .chapter-verse-editor {
            padding: 0.5rem;
        }
        
        .chapter-verse-editor .ql-toolbar {
            padding: 4px;
            border: none;
            border-bottom: 1px solid #dee2e6;
            flex-wrap: wrap;
        }
        
        .chapter-verse-editor .ql-toolbar .ql-formats {
            margin-right: 8px;
            margin-bottom: 4px;
        }
        
        .chapter-verse-editor .ql-container {
            border: none;
            font-size: 0.9rem;
        }
        
        .chapter-verse-editor .ql-editor {
            min-height: 60px;
            max-height: 120px;
            padding: 8px;
        }
        
        /* Smaller font picker for chapter editor */
        .chapter-verse-editor .ql-font .ql-picker-label,
        .chapter-verse-editor .ql-size .ql-picker-label {
            font-size: 0.8rem;
        }
        
        #notesEditorContainer .ql-toolbar {
            border-radius: 0.375rem 0.375rem 0 0;
        }
        
        #notesEditorContainer .ql-container {
            border-radius: 0 0 0.375rem 0.375rem;
            min-height: 300px;
        }
        
        /* Timeline description editor */
        #timelineBeschrijvingEditor {
            width: 100% !important;
            overflow: hidden;
        }
        
        #timelineBeschrijvingEditor .ql-toolbar.ql-snow {
            border-radius: 0.375rem 0.375rem 0 0;
            padding: 5px;
        }
        
        #timelineBeschrijvingEditor .ql-toolbar.ql-snow .ql-picker-options {
            z-index: 1000 !important;
        }
        
        #timelineBeschrijvingEditor .ql-container.ql-snow {
            border-radius: 0 0 0.375rem 0.375rem;
            height: 100px;
        }
        
        #timelineBeschrijvingEditor .ql-editor {
            min-height: 70px;
            max-height: 100px;
            overflow-y: auto;
        }
        
        /* Fix Quill dropdown z-index globally */
        .ql-toolbar .ql-picker-options {
            z-index: 1000 !important;
        }
        
        /* Originele tekst meer ruimte */
        #originalText {
            min-height: 80px !important;
            max-height: 150px;
            overflow-y: auto;
        }
        
        /* Quill Font Families */
        .ql-font-serif { font-family: Georgia, 'Times New Roman', serif; }
        .ql-font-monospace { font-family: Monaco, 'Courier New', monospace; }
        .ql-font-arial { font-family: Arial, Helvetica, sans-serif; }
        .ql-font-times { font-family: 'Times New Roman', Times, serif; }
        .ql-font-courier { font-family: 'Courier New', Courier, monospace; }
        .ql-font-courier { font-family: Courier, monospace; }
        .ql-font-georgia { font-family: Georgia, serif; }
        .ql-font-verdana { font-family: Verdana, Geneva, sans-serif; }
        .ql-font-tahoma { font-family: Tahoma, Geneva, sans-serif; }
        .ql-font-trebuchet { font-family: 'Trebuchet MS', sans-serif; }
        .ql-font-impact { font-family: Impact, sans-serif; }
        
        /* Quill font picker labels */
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="serif"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="serif"]::before { content: "Serif"; font-family: Georgia, serif; }
        
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="monospace"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="monospace"]::before { content: "Monospace"; font-family: Monaco, monospace; }
        
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="arial"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="arial"]::before { content: "Arial"; font-family: Arial, sans-serif; }
        
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="times"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="times"]::before { content: "Times New Roman"; font-family: 'Times New Roman', serif; }
        
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="courier"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="courier"]::before { content: "Courier"; font-family: Courier, monospace; }
        
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="georgia"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="georgia"]::before { content: "Georgia"; font-family: Georgia, serif; }
        
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="verdana"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="verdana"]::before { content: "Verdana"; font-family: Verdana, sans-serif; }
        
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="tahoma"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="tahoma"]::before { content: "Tahoma"; font-family: Tahoma, sans-serif; }
        
        .ql-snow .ql-picker.ql-font .ql-picker-label[data-value="trebuchet"]::before,
        .ql-snow .ql-picker.ql-font .ql-picker-item[data-value="trebuchet"]::before { content: "Trebuchet MS"; font-family: 'Trebuchet MS', sans-serif; }
        
        /* Lightbox */
        .lightbox {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0,0,0,0.9);
            z-index: 9999;
            justify-content: center;
            align-items: center;
            cursor: pointer;
        }
        
        .lightbox img {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
        }
        
        /* Toast notification positioning */
        .toast-container {
            z-index: 9999;
        }
        
        /* Responsive */
        @media (max-width: 992px) {
            .reader-layout {
                grid-template-columns: 1fr;
                grid-template-rows: 400px 300px 250px;
            }
            
            .bible-panel {
                grid-column: 1;
                grid-row: 1;
            }
            
            .resize-handle-v {
                display: none;
            }
            
            .map-panel {
                grid-column: 1;
                grid-row: 2;
            }
            
            .resize-handle-h {
                display: none;
            }
            
            .timeline-panel {
                grid-column: 1;
                grid-row: 3;
            }
            
            .admin-layout {
                flex-direction: column;
                height: auto;
            }
            
            .admin-sidebar {
                width: 100%;
                border-right: none;
                border-bottom: 1px solid #dee2e6;
            }
        }
    </style>
</head>
<body>
    <!-- Navbar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark sticky-top">
        <div class="container-fluid">
            <a class="navbar-brand" href="?mode=reader">
                <i class="bi bi-book"></i> Bijbelreader
            </a>
            
            <?php if ($mode === 'reader'): ?>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarContent">
                <span class="navbar-toggler-icon"></span>
            </button>
            
            <div class="collapse navbar-collapse" id="navbarContent">
                <div class="d-flex flex-wrap align-items-center gap-2 me-auto ms-3">
                    <select id="bookSelect" class="form-select form-select-sm" style="width: 180px;">
                        <option value="">Kies een boek...</option>
                    </select>
                    
                    <select id="chapterSelect" class="form-select form-select-sm" style="width: 140px;">
                        <option value="">Hoofdstuk</option>
                    </select>
                    
                    <input type="search" id="searchInput" class="form-control form-control-sm" placeholder="Zoeken..." style="width: 160px;">
                    
                    <select id="profileSelect" class="form-select form-select-sm" style="width: 160px;">
                        <option value="">Geen opmaak</option>
                    </select>
                    
                    <button class="btn btn-outline-light btn-sm" onclick="toggleTimelineFilter()" title="Timeline filter">
                        <i class="bi bi-funnel"></i> <span id="groupFilterCount">0</span>
                    </button>
                </div>
            </div>
            <?php endif; ?>
            
            <div class="d-flex align-items-center gap-2">
                <?php if ($is_admin): ?>
                <a href="?logout" class="btn btn-outline-light btn-sm">
                    <i class="bi bi-box-arrow-right"></i> Uitloggen
                </a>
                <?php endif; ?>
                
                <a href="?mode=<?php echo $mode === 'admin' ? 'reader' : 'admin'; ?>" class="btn btn-outline-light btn-sm">
                    <?php if ($mode === 'admin'): ?>
                        <i class="bi bi-eye"></i> Reader
                    <?php else: ?>
                        <i class="bi bi-gear"></i> Admin
                    <?php endif; ?>
                </a>
            </div>
        </div>
    </nav>

    <?php if ($mode === 'login'): ?>
    <!-- Login Form -->
    <div class="container mt-5">
        <div class="row justify-content-center">
            <div class="col-md-4">
                <div class="card shadow">
                    <div class="card-body p-4">
                        <h3 class="card-title text-center mb-4">
                            <i class="bi bi-lock"></i> Admin Login
                        </h3>
                        
                        <?php if (isset($login_error)): ?>
                        <div class="alert alert-danger">
                            <i class="bi bi-exclamation-triangle"></i> <?php echo htmlspecialchars($login_error); ?>
                        </div>
                        <?php endif; ?>
                        
                        <form method="POST">
                            <div class="mb-3">
                                <label class="form-label">Wachtwoord</label>
                                <input type="password" name="password" class="form-control" required autofocus placeholder="Voer wachtwoord in">
                            </div>
                            <button type="submit" name="login" value="1" class="btn btn-primary w-100">
                                <i class="bi bi-unlock"></i> Inloggen
                            </button>
                        </form>
                        
                        <div class="text-center mt-3">
                            <a href="?mode=reader" class="text-decoration-none">
                                <i class="bi bi-arrow-left"></i> Terug naar Reader
                            </a>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php elseif ($mode === 'reader'): ?>
    <!-- Timeline Filter Panel (collapsible) -->
    <div class="collapse bg-light border-bottom" id="timelineFilterPanel">
        <div class="container-fluid py-2">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0"><i class="bi bi-tags"></i> Timeline Filter & Zoeken</h6>
                <button class="btn btn-sm btn-outline-secondary" onclick="toggleTimelineFilter()">
                    <i class="bi bi-x"></i> Sluiten
                </button>
            </div>
            
            <!-- Timeline Search -->
            <div class="row mb-3">
                <div class="col-md-6">
                    <div class="input-group input-group-sm">
                        <span class="input-group-text"><i class="bi bi-search"></i></span>
                        <input type="text" id="readerTimelineSearch" class="form-control" placeholder="Zoek in timeline events..." onkeyup="searchTimelineEvents(this.value)">
                        <button class="btn btn-outline-secondary" type="button" onclick="clearTimelineSearch()">
                            <i class="bi bi-x-lg"></i>
                        </button>
                    </div>
                </div>
            </div>
            
            <!-- Search Results -->
            <div id="timelineSearchResults" class="mb-3 d-none">
                <div class="card">
                    <div class="card-header py-1 px-2 d-flex justify-content-between align-items-center">
                        <small class="fw-bold"><span id="timelineSearchCount">0</span> resultaten</small>
                        <button class="btn btn-sm btn-link p-0" onclick="clearTimelineSearch()">Sluiten</button>
                    </div>
                    <div class="card-body p-0" style="max-height: 200px; overflow-y: auto;">
                        <div id="timelineSearchResultsList"></div>
                    </div>
                </div>
            </div>
            
            <!-- Group Toggles -->
            <div class="mb-2">
                <small class="text-muted fw-bold">Groepen tonen/verbergen:</small>
            </div>
            <div id="timelineGroupToggles" class="d-flex flex-wrap gap-2"></div>
        </div>
    </div>
    
    <!-- Reader Layout -->
    <div class="reader-layout" id="readerContainer">
        <div class="bible-panel" id="bibleText">
            <div class="text-center py-5 text-muted">
                <div class="spinner-border" role="status"></div>
                <p class="mt-2">Bijbeltekst wordt geladen...</p>
            </div>
        </div>
        
        <div class="resize-handle-v" id="verticalHandle"></div>
        
        <div class="map-panel">
            <div id="map"></div>
        </div>
        
        <div class="resize-handle-h" id="horizontalHandle"></div>
        
        <div class="timeline-panel">
            <button class="timeline-nav-btn timeline-nav-prev" onclick="navigateTimelinePrev()" title="Vorig event">
                <i class="bi bi-chevron-left"></i>
            </button>
            <div id="timeline"></div>
            <button class="timeline-nav-btn timeline-nav-next" onclick="navigateTimelineNext()" title="Volgend event">
                <i class="bi bi-chevron-right"></i>
            </button>
        </div>
    </div>

    <?php else: ?>
    <!-- Admin Mode -->
    <div class="admin-layout">
        <!-- Admin Sidebar -->
        <div class="admin-sidebar">
            <h6 class="text-muted mb-3">ADMIN MENU</h6>
            <div class="list-group list-group-flush">
                <button class="list-group-item list-group-item-action active" onclick="showAdminSection('editor')">
                    <i class="bi bi-pencil"></i> Tekst Bewerken
                </button>
                <button class="list-group-item list-group-item-action" onclick="showAdminSection('profiles')">
                    <i class="bi bi-person-badge"></i> Profielen
                </button>
                <button class="list-group-item list-group-item-action" onclick="showAdminSection('timeline')">
                    <i class="bi bi-calendar-event"></i> Timeline
                </button>
                <button class="list-group-item list-group-item-action" onclick="showAdminSection('locations')">
                    <i class="bi bi-geo-alt"></i> Locaties
                </button>
                <button class="list-group-item list-group-item-action" onclick="showAdminSection('images')">
                    <i class="bi bi-image"></i> Afbeeldingen
                </button>
                <button class="list-group-item list-group-item-action" onclick="showAdminSection('notes')">
                    <i class="bi bi-journal-text"></i> Notities
                </button>
                <button class="list-group-item list-group-item-action" onclick="showAdminSection('import-export')">
                    <i class="bi bi-arrow-left-right"></i> Import/Export
                </button>
            </div>
        </div>
        
        <!-- Admin Content -->
        <div class="admin-content">
            <!-- TEXT EDITOR SECTION -->
            <div id="section-editor" class="admin-section">
                <h4 class="mb-4"><i class="bi bi-pencil"></i> Tekst Bewerken</h4>
                
                <div class="row">
                    <!-- Linker kolom: Editor -->
                    <div class="col-lg-6 mb-4">
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center">
                                <span>Tekst Bewerken</span>
                                <div class="btn-group btn-group-sm" role="group">
                                    <input type="radio" class="btn-check" name="editMode" id="editModeSingle" checked onchange="setEditMode('single')">
                                    <label class="btn btn-outline-primary" for="editModeSingle">Enkel vers</label>
                                    <input type="radio" class="btn-check" name="editMode" id="editModeChapter" onchange="setEditMode('chapter')">
                                    <label class="btn btn-outline-primary" for="editModeChapter">Heel hoofdstuk</label>
                                </div>
                            </div>
                            <div class="card-body">
                                <div class="row g-3 mb-3">
                                    <div class="col-md-4">
                                        <label class="form-label">Boek</label>
                                        <select id="adminBookSelect" class="form-select form-select-sm"></select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Hoofdstuk</label>
                                        <select id="adminChapterSelect" class="form-select form-select-sm"></select>
                                    </div>
                                    <div class="col-md-4" id="verseSelectContainer">
                                        <label class="form-label">Vers</label>
                                        <select id="adminVerseSelect" class="form-select form-select-sm"></select>
                                    </div>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Profiel</label>
                                    <select id="editorProfileSelect" class="form-select form-select-sm"></select>
                                </div>
                                
                                <!-- Single verse editor -->
                                <div id="singleVerseEditor">
                                    <div class="mb-3">
                                        <label class="form-label">Originele tekst</label>
                                        <div id="originalText" class="p-2 bg-light border rounded" style="min-height: 80px; max-height: 150px; overflow-y: auto; font-size: 0.9rem;"></div>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <label class="form-label">Opgemaakte tekst</label>
                                        <div id="editor-container"></div>
                                    </div>
                                    
                                    <div class="d-flex gap-2">
                                        <button class="btn btn-primary btn-sm" onclick="saveFormatting()">
                                            <i class="bi bi-save"></i> Opslaan
                                        </button>
                                        <button class="btn btn-outline-secondary btn-sm" onclick="resetFormatting()">
                                            <i class="bi bi-arrow-counterclockwise"></i> Reset
                                        </button>
                                        <button class="btn btn-outline-danger btn-sm" onclick="deleteFormatting()">
                                            <i class="bi bi-trash"></i> Verwijderen
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Chapter editor (multiple verses) -->
                                <div id="chapterEditor" class="d-none">
                                    <div class="mb-3 d-flex justify-content-between align-items-center">
                                        <label class="form-label mb-0">Verzen bewerken</label>
                                        <button class="btn btn-primary btn-sm" onclick="saveAllChapterFormatting()">
                                            <i class="bi bi-save-fill"></i> Alles Opslaan
                                        </button>
                                    </div>
                                    <div id="chapterVersesContainer" style="max-height: 500px; overflow-y: auto;">
                                        <div class="text-muted text-center py-4">Selecteer een boek en hoofdstuk om te beginnen</div>
                                    </div>
                                    <div class="mt-3 d-flex justify-content-between">
                                        <small class="text-muted"><span id="chapterVerseCount">0</span> verzen</small>
                                        <button class="btn btn-primary btn-sm" onclick="saveAllChapterFormatting()">
                                            <i class="bi bi-save-fill"></i> Alles Opslaan
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Rechter kolom: Overzicht bewerkte teksten -->
                    <div class="col-lg-6 mb-4">
                        <div class="card h-100">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <span>Bewerkte Verzen</span>
                                <div class="d-flex gap-2 flex-wrap">
                                    <select id="formattingFilterProfile" class="form-select form-select-sm" style="width: 120px;" onchange="filterFormattingList()">
                                        <option value="">Alle profielen</option>
                                    </select>
                                    <select id="formattingFilterBook" class="form-select form-select-sm" style="width: 120px;" onchange="filterFormattingList()">
                                        <option value="">Alle boeken</option>
                                    </select>
                                </div>
                            </div>
                            <div class="card-body p-2" style="max-height: 500px; overflow-y: auto;">
                                <div id="formattingList"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- PROFILES SECTION -->
            <div id="section-profiles" class="admin-section d-none">
                <h4 class="mb-4"><i class="bi bi-person-badge"></i> Profielen Beheren</h4>
                
                <div class="card mb-4">
                    <div class="card-header">Nieuw Profiel</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-5">
                                <label class="form-label">Naam</label>
                                <input type="text" id="newProfileName" class="form-control" placeholder="Profiel naam">
                            </div>
                            <div class="col-md-5">
                                <label class="form-label">Beschrijving</label>
                                <input type="text" id="newProfileDesc" class="form-control" placeholder="Optionele beschrijving">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button class="btn btn-primary w-100" onclick="createProfile()">
                                    <i class="bi bi-plus"></i> Aanmaken
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Bestaande Profielen</span>
                        <input type="text" id="profileSearchInput" class="form-control form-control-sm" placeholder="Zoeken..." style="width: 200px;" onkeyup="filterProfiles()">
                    </div>
                    <div class="card-body">
                        <div id="profilesList"></div>
                    </div>
                </div>
            </div>

            <!-- TIMELINE SECTION -->
            <div id="section-timeline" class="admin-section d-none">
                <h4 class="mb-4"><i class="bi bi-calendar-event"></i> Timeline Beheren</h4>
                
                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" role="tablist">
                    <li class="nav-item">
                        <button class="nav-link active" data-bs-toggle="tab" data-bs-target="#tab-events">Events</button>
                    </li>
                    <li class="nav-item">
                        <button class="nav-link" data-bs-toggle="tab" data-bs-target="#tab-groups">Groepen</button>
                    </li>
                </ul>
                
                <div class="tab-content">
                    <!-- Events Tab -->
                    <div class="tab-pane fade show active" id="tab-events">
                        <div class="card mb-4">
                            <div class="card-header">Nieuw Event</div>
                            <div class="card-body">
                                <input type="hidden" id="timelineEventId">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Titel</label>
                                        <input type="text" id="timelineTitel" class="form-control" placeholder="Event titel">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label">Groep</label>
                                        <select id="timelineGroup" class="form-select">
                                            <option value="">Geen groep</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="row g-3 mt-2 mb-3">
                                    <div class="col-12" style="overflow: hidden;">
                                        <label class="form-label">Beschrijving</label>
                                        <div id="timelineBeschrijvingEditor"></div>
                                    </div>
                                </div>
                                <div class="row g-3" style="margin-top: 40px;">
                                    <div class="col-md-4">
                                        <label class="form-label">Start Datum</label>
                                        <input type="text" id="timelineStartDatum" class="form-control" placeholder="-1000 of 2024-01-15">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Eind Datum</label>
                                        <input type="text" id="timelineEndDatum" class="form-control" placeholder="Optioneel">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Achtergrond Kleur</label>
                                        <input type="color" id="timelineKleur" class="form-control form-control-color w-100" value="#3498db">
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Tekst Kleur <small class="text-muted">(optioneel)</small></label>
                                        <div class="input-group">
                                            <input type="color" id="timelineTekstKleur" class="form-control form-control-color" value="#ffffff">
                                            <button type="button" class="btn btn-outline-secondary btn-sm" onclick="clearTextColor()" title="Automatisch contrast">
                                                <i class="bi bi-arrow-repeat"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                <div class="row g-3 mt-1">
                                    <!-- Start Vers Selectors -->
                                    <div class="col-md-4">
                                        <label class="form-label">Start Vers - Boek</label>
                                        <select id="timelineStartBoek" class="form-select">
                                            <option value="">Kies boek...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Hoofdstuk</label>
                                        <select id="timelineStartHoofdstuk" class="form-select">
                                            <option value="">Hoofdstuk</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Vers</label>
                                        <select id="timelineStartVers" class="form-select">
                                            <option value="">Vers</option>
                                        </select>
                                    </div>
                                    
                                    <!-- End Vers Selectors -->
                                    <div class="col-md-4">
                                        <label class="form-label">Eind Vers - Boek</label>
                                        <select id="timelineEndBoek" class="form-select">
                                            <option value="">Kies boek...</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Hoofdstuk</label>
                                        <select id="timelineEndHoofdstuk" class="form-select">
                                            <option value="">Hoofdstuk</option>
                                        </select>
                                    </div>
                                    <div class="col-md-4">
                                        <label class="form-label">Vers</label>
                                        <select id="timelineEndVers" class="form-select">
                                            <option value="">Vers</option>
                                        </select>
                                    </div>
                                    
                                    <div class="col-12">
                                        <button class="btn btn-primary" onclick="saveTimeline()">
                                            <i class="bi bi-plus"></i> Event Opslaan
                                        </button>
                                        <button class="btn btn-outline-secondary ms-2" onclick="clearTimelineForm()">
                                            <i class="bi bi-x"></i> Formulier Legen
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header d-flex justify-content-between align-items-center flex-wrap gap-2">
                                <span>Timeline Events</span>
                                <div class="d-flex gap-2">
                                    <input type="text" id="timelineSearchInput" class="form-control form-control-sm" placeholder="Zoeken..." style="width: 150px;" onkeyup="filterTimeline()">
                                    <select id="timelineFilterGroup" class="form-select form-select-sm" style="width: 150px;" onchange="filterTimeline()">
                                        <option value="">Alle groepen</option>
                                    </select>
                                </div>
                            </div>
                            <div class="card-body">
                                <div id="timelineList" class="table-responsive"></div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Groups Tab -->
                    <div class="tab-pane fade" id="tab-groups">
                        <div class="card mb-4">
                            <div class="card-header">Nieuwe Groep</div>
                            <div class="card-body">
                                <div class="row g-3">
                                    <div class="col-md-6">
                                        <label class="form-label">Groep Naam</label>
                                        <input type="text" id="groupName" class="form-control" placeholder="Groep naam">
                                    </div>
                                    <div class="col-md-3">
                                        <label class="form-label">Kleur</label>
                                        <input type="color" id="groupColor" class="form-control form-control-color w-100" value="#9b59b6">
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button class="btn btn-primary w-100" onclick="createTimelineGroup()">
                                            <i class="bi bi-plus"></i> Toevoegen
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <div class="card">
                            <div class="card-header">Bestaande Groepen</div>
                            <div class="card-body">
                                <div id="groupsList"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- LOCATIONS SECTION -->
            <div id="section-locations" class="admin-section d-none">
                <h4 class="mb-4"><i class="bi bi-geo-alt"></i> Locaties Beheren</h4>
                
                <div class="card mb-4">
                    <div class="card-header">Nieuwe Locatie</div>
                    <div class="card-body">
                        <input type="hidden" id="locationId">
                        <div class="row g-3">
                            <div class="col-md-4">
                                <label class="form-label">Naam</label>
                                <input type="text" id="locationName" class="form-control" placeholder="Locatie naam">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Latitude</label>
                                <input type="number" step="any" id="locationLat" class="form-control" placeholder="31.7683">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Longitude</label>
                                <input type="number" step="any" id="locationLng" class="form-control" placeholder="35.2137">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Type</label>
                                <select id="locationType" class="form-select">
                                    <option value="stad">Stad</option>
                                    <option value="berg">Berg</option>
                                    <option value="rivier">Rivier</option>
                                    <option value="zee">Zee/Meer</option>
                                    <option value="regio">Regio</option>
                                    <option value="overig">Overig</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Icon</label>
                                <select id="locationIcon" class="form-select">
                                    <option value="marker">Marker</option>
                                    <option value="city">Stad</option>
                                    <option value="mountain">Berg</option>
                                    <option value="water">Water</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Beschrijving</label>
                                <input type="text" id="locationDesc" class="form-control" placeholder="Optionele beschrijving">
                            </div>
                            <div class="col-12">
                                <button class="btn btn-primary" onclick="saveLocation()">
                                    <i class="bi bi-plus"></i> Locatie Toevoegen
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>Bestaande Locaties</span>
                        <input type="text" id="locationSearchInput" class="form-control form-control-sm" placeholder="Zoeken..." style="width: 200px;" onkeyup="filterLocations()">
                    </div>
                    <div class="card-body">
                        <div id="locationList" class="table-responsive"></div>
                    </div>
                </div>
            </div>

            <!-- IMAGES SECTION -->
            <div id="section-images" class="admin-section d-none">
                <h4 class="mb-4"><i class="bi bi-image"></i> Afbeeldingen Beheren</h4>
                
                <div class="card mb-4">
                    <div class="card-header">Afbeelding Uploaden</div>
                    <div class="card-body">
                        <div class="row g-3">
                            <div class="col-md-6">
                                <label class="form-label">Afbeelding</label>
                                <input type="file" id="imageFile" class="form-control" accept="image/*">
                            </div>
                            <div class="col-md-6">
                                <label class="form-label">Bijschrift</label>
                                <input type="text" id="imageCaption" class="form-control" placeholder="Optioneel bijschrift">
                            </div>
                            
                            <!-- Vers Selectors voor Image -->
                            <div class="col-md-4">
                                <label class="form-label">Boek (optioneel)</label>
                                <select id="imageBoek" class="form-select">
                                    <option value="">Kies boek...</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Hoofdstuk</label>
                                <select id="imageHoofdstuk" class="form-select">
                                    <option value="">Hoofdstuk</option>
                                </select>
                            </div>
                            <div class="col-md-4">
                                <label class="form-label">Vers</label>
                                <select id="imageVers" class="form-select">
                                    <option value="">Vers</option>
                                </select>
                            </div>
                            
                            <div class="col-md-4">
                                <label class="form-label">Uitlijning</label>
                                <select id="imageUitlijning" class="form-select">
                                    <option value="center">Gecentreerd</option>
                                    <option value="left">Links</option>
                                    <option value="right">Rechts</option>
                                </select>
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Breedte (px)</label>
                                <input type="number" id="imageBreedte" class="form-control" value="400">
                            </div>
                            <div class="col-md-3">
                                <label class="form-label">Hoogte (px)</label>
                                <input type="number" id="imageHoogte" class="form-control" placeholder="Auto">
                            </div>
                            <div class="col-md-2 d-flex align-items-end">
                                <button class="btn btn-primary w-100" onclick="uploadImage()">
                                    <i class="bi bi-upload"></i> Uploaden
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span>GeÃƒÆ’Ã‚Â¼ploade Afbeeldingen</span>
                        <input type="text" id="imageSearchInput" class="form-control form-control-sm" placeholder="Zoeken..." style="width: 200px;" onkeyup="filterImages()">
                    </div>
                    <div class="card-body">
                        <div id="imageList" class="row g-3"></div>
                    </div>
                </div>
            </div>
            
            <!-- NOTES SECTION -->
            <div id="section-notes" class="admin-section d-none">
                <h4 class="mb-4"><i class="bi bi-journal-text"></i> Notities</h4>
                
                <div class="row" style="height: calc(100vh - 200px);">
                    <div class="col-md-4 col-lg-3">
                        <div class="card h-100">
                            <div class="card-header">
                                <button class="btn btn-primary btn-sm w-100" onclick="createNewNote()">
                                    <i class="bi bi-plus"></i> Nieuwe Notitie
                                </button>
                            </div>
                            <div class="card-body p-2 overflow-auto" id="notesList">
                                <p class="text-muted text-center py-4">Nog geen notities</p>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-8 col-lg-9">
                        <div class="card h-100">
                            <div id="emptyNotesState" class="card-body d-flex flex-column align-items-center justify-content-center text-muted">
                                <i class="bi bi-journal-text" style="font-size: 4rem;"></i>
                                <h5 class="mt-3">Geen notitie geselecteerd</h5>
                                <p>Selecteer een notitie of maak een nieuwe aan.</p>
                            </div>
                            <div id="noteEditorContent" class="card-body p-0 d-none d-flex flex-column h-100">
                                <input type="text" id="noteTitleInput" class="form-control border-0 border-bottom rounded-0 fs-5 fw-bold" placeholder="Titel...">
                                <div id="notesEditorContainer" class="flex-grow-1"></div>
                                <div class="p-2 border-top d-flex gap-2 align-items-center">
                                    <button class="btn btn-primary btn-sm" onclick="saveCurrentNote()">
                                        <i class="bi bi-save"></i> Opslaan
                                    </button>
                                    <button class="btn btn-outline-danger btn-sm" onclick="deleteCurrentNote()">
                                        <i class="bi bi-trash"></i> Verwijderen
                                    </button>
                                    <span id="noteSaveStatus" class="ms-auto text-muted small"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- IMPORT/EXPORT SECTION -->
            <div id="section-import-export" class="admin-section d-none">
                <h4 class="mb-4"><i class="bi bi-arrow-left-right"></i> Import & Export</h4>
                
                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <i class="bi bi-download"></i> Exporteren naar CSV
                            </div>
                            <div class="card-body">
                                <p class="text-muted">Download je data als CSV bestanden voor backup.</p>
                                <div class="d-flex flex-wrap gap-2">
                                    <button class="btn btn-outline-primary" onclick="exportTable('profiles')">
                                        <i class="bi bi-person-badge"></i> Profielen
                                    </button>
                                    <button class="btn btn-outline-primary" onclick="exportTable('formatting')">
                                        <i class="bi bi-pencil"></i> Opmaak
                                    </button>
                                    <button class="btn btn-outline-primary" onclick="exportTable('timeline_events')">
                                        <i class="bi bi-calendar"></i> Events
                                    </button>
                                    <button class="btn btn-outline-primary" onclick="exportTable('timeline_groups')">
                                        <i class="bi bi-tags"></i> Groepen
                                    </button>
                                    <button class="btn btn-outline-primary" onclick="exportTable('locations')">
                                        <i class="bi bi-geo"></i> Locaties
                                    </button>
                                    <button class="btn btn-outline-primary" onclick="exportTable('images')">
                                        <i class="bi bi-image"></i> Afbeeldingen
                                    </button>
                                </div>
                                <hr>
                                <button class="btn btn-primary" onclick="exportAll()">
                                    <i class="bi bi-download"></i> Alles Exporteren
                                </button>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-lg-6">
                        <div class="card h-100">
                            <div class="card-header">
                                <i class="bi bi-upload"></i> Importeren van CSV
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <label class="form-label">Selecteer tabel</label>
                                    <select id="importTableSelect" class="form-select">
                                        <option value="">-- Kies tabel --</option>
                                        <option value="profiles">Profielen</option>
                                        <option value="formatting">Opgemaakte Teksten</option>
                                        <option value="timeline_events">Timeline Events</option>
                                        <option value="timeline_groups">Timeline Groepen</option>
                                        <option value="locations">Locaties</option>
                                        <option value="verse_locations">Vers-Locatie Koppelingen</option>
                                        <option value="images">Afbeeldingen</option>
                                    </select>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">CSV Bestand</label>
                                    <input type="file" id="importFile" class="form-control" accept=".csv">
                                </div>
                                <div class="form-check mb-3">
                                    <input type="checkbox" id="importOverwrite" class="form-check-input">
                                    <label class="form-check-label">Bestaande data overschrijven</label>
                                </div>
                                <button class="btn btn-primary" onclick="importCSV()">
                                    <i class="bi bi-upload"></i> Importeren
                                </button>
                                <div id="importStatus" class="mt-3"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
        </div>
    </div>
    
    <!-- Image Edit Modal -->
    <div class="modal fade" id="imageEditModal" tabindex="-1" aria-labelledby="imageEditModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="imageEditModalLabel">Afbeelding Bewerken</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Sluiten"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editImageId">
                    <div class="row g-3">
                        <div class="col-md-4">
                            <label class="form-label">Boek</label>
                            <select id="editImageBoek" class="form-select">
                                <option value="">Kies boek...</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Hoofdstuk</label>
                            <select id="editImageHoofdstuk" class="form-select">
                                <option value="">Hoofdstuk</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Vers</label>
                            <select id="editImageVers" class="form-select">
                                <option value="">Vers</option>
                            </select>
                        </div>
                        <div class="col-12">
                            <label class="form-label">Bijschrift</label>
                            <input type="text" id="editImageCaption" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Uitlijning</label>
                            <select id="editImageUitlijning" class="form-select">
                                <option value="center">Gecentreerd</option>
                                <option value="left">Links</option>
                                <option value="right">Rechts</option>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Breedte (px)</label>
                            <input type="number" id="editImageBreedte" class="form-control">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Hoogte (px)</label>
                            <input type="number" id="editImageHoogte" class="form-control" placeholder="Auto">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <button type="button" class="btn btn-primary" onclick="updateImage()">Opslaan</button>
                </div>
            </div>
        </div>
    </div>
    <?php endif; ?>

    <!-- Group Edit Modal -->
    <div class="modal fade" id="groupEditModal" tabindex="-1" aria-labelledby="groupEditModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="groupEditModalLabel">Groep Bewerken</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Sluiten"></button>
                </div>
                <div class="modal-body">
                    <input type="hidden" id="editGroupId">
                    <div class="mb-3">
                        <label class="form-label">Groep Naam</label>
                        <input type="text" id="editGroupName" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Kleur</label>
                        <input type="color" id="editGroupColor" class="form-control form-control-color w-100" value="#9b59b6">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Annuleren</button>
                    <button type="button" class="btn btn-primary" onclick="updateTimelineGroup()">Opslaan</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Lightbox -->
    <div class="lightbox" id="lightbox" onclick="closeLightbox()">
        <img id="lightboxImage" src="" alt="">
    </div>
    
    <!-- Timeline Event Popup -->
    <div class="timeline-popup" id="timelinePopup">
        <div class="timeline-popup-content">
            <button class="timeline-popup-close" onclick="closeTimelinePopup()">
                <i class="bi bi-x-lg"></i>
            </button>
            <div class="timeline-popup-header">
                <h5 id="timelinePopupTitle">Event Titel</h5>
                <div class="timeline-popup-meta">
                    <span id="timelinePopupDate" class="badge bg-secondary"></span>
                    <span id="timelinePopupGroup" class="badge"></span>
                </div>
            </div>
            <div class="timeline-popup-body">
                <div id="timelinePopupDescription"></div>
            </div>
            <div class="timeline-popup-footer">
                <button class="btn btn-sm btn-primary" id="timelinePopupGoToVerse" onclick="goToTimelineVerse()">
                    <i class="bi bi-book"></i> Ga naar vers
                </button>
            </div>
        </div>
    </div>
    
    <!-- Toast Container -->
    <div class="toast-container position-fixed top-0 end-0 p-3">
        <div id="notificationToast" class="toast" role="alert">
            <div class="toast-header">
                <i class="bi bi-info-circle me-2"></i>
                <strong class="me-auto">Melding</strong>
                <button type="button" class="btn-close" data-bs-dismiss="toast"></button>
            </div>
            <div class="toast-body"></div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.2/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/vis-timeline@7.7.3/standalone/umd/vis-timeline-graph2d.min.js"></script>
    <script src="https://cdn.quilljs.com/1.3.6/quill.min.js"></script>
    
    <script>
        const mode = '<?php echo $mode; ?>';
        let map, timeline, quill;
        let currentBook = null, currentChapter = null, currentVerse = null, currentProfile = null;
        let currentOffset = 0, loading = false, allLoaded = false;
        let searchQuery = '';
        let lastChapter = null;
        let allLocations = [];
        let allPersonen = [];
        let timelineGroupsVisible = {};
        let firstLoadedVersId = null; // Track first loaded verse for backward scrolling
        let loadingBackward = false; // Prevent multiple backward loads
        let allLoadedBackward = false; // Track if we've reached the beginning
        
        // Notification using Bootstrap Toast
        function showNotification(message, isError = false) {
            const toast = document.getElementById('notificationToast');
            const toastBody = toast.querySelector('.toast-body');
            const toastHeader = toast.querySelector('.toast-header i');
            
            toastBody.textContent = message;
            toast.classList.remove('text-bg-success', 'text-bg-danger');
            toast.classList.add(isError ? 'text-bg-danger' : 'text-bg-success');
            toastHeader.className = isError ? 'bi bi-exclamation-circle me-2' : 'bi bi-check-circle me-2';
            
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
        }
        
        // Lightbox functions
        window.openLightbox = function(imageSrc) {
            document.getElementById('lightboxImage').src = imageSrc;
            document.getElementById('lightbox').style.display = 'flex';
        };
        
        window.closeLightbox = function() {
            document.getElementById('lightbox').style.display = 'none';
        };
        
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                closeLightbox();
                // Also close timeline popup if open
                const timelinePopup = document.getElementById('timelinePopup');
                if (timelinePopup && timelinePopup.classList.contains('show')) {
                    timelinePopup.classList.remove('show');
                }
            }
        });
        
        // API helper
        async function apiCall(endpoint, options = {}) {
            try {
                const response = await fetch('?api=' + endpoint, options);
                return await response.json();
            } catch (error) {
                console.error('API Error:', error);
                showNotification('Er is een fout opgetreden', true);
                return null;
            }
        }
        
        // Toggle timeline filter panel
        function toggleTimelineFilter() {
            const panel = document.getElementById('timelineFilterPanel');
            if (panel) {
                const collapse = new bootstrap.Collapse(panel, { toggle: true });
            }
        }
        window.toggleTimelineFilter = toggleTimelineFilter;
        // Load timeline groups - works in both admin and reader mode
        async function loadTimelineGroups() {
            const groups = await apiCall('timeline_groups');
            
            // Update dropdown in event form (admin mode)
            const groupSelect = document.getElementById('timelineGroup');
            if (groupSelect) {
                groupSelect.innerHTML = '<option value="">Geen groep</option>';
            }
            
            // Update filter dropdown (admin mode)
            const filterGroupSelect = document.getElementById('timelineFilterGroup');
            if (filterGroupSelect) {
                filterGroupSelect.innerHTML = '<option value="">Alle groepen</option>';
            }
            
            // Update groups list with toggle buttons (admin mode)
            const groupsList = document.getElementById('groupsList');
            if (groupsList) {
                groupsList.innerHTML = '';
            }
            
            // Update reader mode filter toggles
            const readerToggles = document.getElementById('timelineGroupToggles');
            if (readerToggles) {
                readerToggles.innerHTML = '';
            }
            
            // Update group count
            const countElement = document.getElementById('groupFilterCount');
            if (countElement) {
                const visibleCount = groups ? groups.filter(g => g.Zichtbaar).length : 0;
                countElement.textContent = visibleCount;
            }
            
            if (groups && groups.length > 0) {
                groups.forEach(group => {
                    // Add to dropdown (admin mode)
                    if (groupSelect) {
                        const option = document.createElement('option');
                        option.value = group.Group_ID;
                        option.textContent = group.Groep_Naam;
                        groupSelect.appendChild(option);
                    }
                    
                    // Add to filter dropdown (admin mode)
                    if (filterGroupSelect) {
                        const option = document.createElement('option');
                        option.value = group.Group_ID;
                        option.textContent = group.Groep_Naam;
                        filterGroupSelect.appendChild(option);
                    }
                    
                    // Add as toggle chip (admin mode)
                    if (groupsList) {
                        const chip = document.createElement('div');
                        chip.style.cssText = `
                            display: inline-flex;
                            align-items: center;
                            gap: 0.5rem;
                            padding: 0.5rem 0.8rem;
                            border-radius: 20px;
                            background: ${group.Zichtbaar ? group.Kleur + '20' : '#e0e0e0'};
                            border: 2px solid ${group.Kleur};
                            cursor: pointer;
                            transition: var(--transition);
                            opacity: ${group.Zichtbaar ? '1' : '0.5'};
                        `;
                        
                        chip.innerHTML = `
                            <span style="font-weight: 500; color: var(--text-color);">${group.Groep_Naam}</span>
                            <span style="font-size: 0.85rem; color: #666;">${group.Zichtbaar ? 'Zichtbaar' : 'Verborgen'}</span>
                            <button onclick="editTimelineGroup(${group.Group_ID}, '${group.Groep_Naam.replace(/'/g, "\\'")}', '${group.Kleur}')" 
                                    style="background: none; border: none; cursor: pointer; font-size: 1rem; padding: 0; margin-left: 0.3rem; color: var(--primary-color);"
                                    title="Bewerk groep"><i class="bi bi-pencil"></i></button>
                            <button onclick="deleteTimelineGroup(${group.Group_ID})" 
                                    style="background: none; border: none; cursor: pointer; font-size: 1.2rem; padding: 0; margin-left: 0.3rem;"
                                    title="Verwijder groep"><i class="bi bi-trash"></i></button>
                        `;
                        
                        chip.onclick = (e) => {
                            if (e.target.tagName !== 'BUTTON') {
                                toggleTimelineGroup(group.Group_ID);
                            }
                        };
                        
                        groupsList.appendChild(chip);
                    }
                    
                    // Add as toggle button (reader mode)
                    if (readerToggles) {
                        const toggle = document.createElement('button');
                        toggle.className = 'timeline-group-toggle';
                        toggle.style.cssText = `
                            padding: 0.5rem 1rem;
                            border-radius: 20px;
                            background: ${group.Zichtbaar ? group.Kleur + '20' : '#f0f0f0'};
                            border: 2px solid ${group.Kleur};
                            color: var(--text-color);
                            font-weight: 500;
                            cursor: pointer;
                            transition: var(--transition);
                            opacity: ${group.Zichtbaar ? '1' : '0.5'};
                            font-size: 0.9rem;
                        `;
                        
                        // Hover effect
                        toggle.onmouseenter = () => {
                            toggle.style.transform = 'scale(1.05)';
                            toggle.style.boxShadow = '0 2px 8px rgba(0,0,0,0.15)';
                        };
                        toggle.onmouseleave = () => {
                            toggle.style.transform = 'scale(1)';
                            toggle.style.boxShadow = 'none';
                        };
                        
                        toggle.innerHTML = `
                            <i class="bi bi-${group.Zichtbaar ? 'check-circle-fill' : 'x-circle'}"></i> ${group.Groep_Naam}
                        `;
                        
                        toggle.onclick = () => toggleTimelineGroupReader(group.Group_ID);
                        
                        readerToggles.appendChild(toggle);
                    }
                });
            }
        }
        
        // Toggle timeline group visibility
        async function toggleTimelineGroup(groupId) {
            const result = await apiCall(`toggle_timeline_group&id=${groupId}`);
            if (result && result.success) {
                await loadTimelineGroups();
                // Reload timeline in reader mode
                if (mode === 'reader' && window.loadTimelineEvents) {
                    await window.loadTimelineEvents();
                    // Update navigation buttons after group toggle
                    setTimeout(() => updateNavButtons(), 100);
                }
            }
        }
        window.toggleTimelineGroup = toggleTimelineGroup;
        
        // Reader mode specific toggle
        async function toggleTimelineGroupReader(groupId) {
            await toggleTimelineGroup(groupId);
        }
        window.toggleTimelineGroupReader = toggleTimelineGroupReader;
        
        // Toggle the filter panel visibility
        function toggleTimelineFilter() {
            const panel = document.getElementById('timelineFilterPanel');
            if (panel) {
                if (panel.style.display === 'none') {
                    panel.style.display = 'block';
                } else {
                    panel.style.display = 'none';
                    panel.style.display = 'none';
                }
            }
        }
        window.toggleTimelineFilter = toggleTimelineFilter;
        
        // ============= MODE SPECIFIC CODE =============
        
        if (mode === 'reader') {
            // ============= READER MODE =============
            
            // Resize functionality
            let isResizingVertical = false;
            let isResizingHorizontal = false;
            
            const verticalHandle = document.getElementById('verticalHandle');
            const horizontalHandle = document.getElementById('horizontalHandle');
            const readerContainer = document.getElementById('readerContainer');
            
            // Vertical resize (between text and map)
            verticalHandle.addEventListener('mousedown', (e) => {
                isResizingVertical = true;
                document.body.style.cursor = 'col-resize';
                e.preventDefault();
            });
            
            // Horizontal resize (timeline height)
            horizontalHandle.addEventListener('mousedown', (e) => {
                isResizingHorizontal = true;
                document.body.style.cursor = 'row-resize';
                e.preventDefault();
            });
            
            document.addEventListener('mousemove', (e) => {
                if (isResizingVertical) {
                    const containerRect = readerContainer.getBoundingClientRect();
                    const offsetX = e.clientX - containerRect.left;
                    const totalWidth = containerRect.width;
                    const leftPercent = (offsetX / totalWidth) * 100;
                    
                    // Limit between 30% and 70%
                    if (leftPercent > 30 && leftPercent < 70) {
                        const rightPercent = 100 - leftPercent;
                        readerContainer.style.gridTemplateColumns = `${leftPercent}% 4px ${rightPercent}%`;
                    }
                } else if (isResizingHorizontal) {
                    const containerRect = readerContainer.getBoundingClientRect();
                    const offsetY = e.clientY - containerRect.top;
                    const totalHeight = containerRect.height;
                    const timelineHeight = totalHeight - offsetY;
                    
                    // Limit between 150px and 600px
                    if (timelineHeight > 150 && timelineHeight < 600) {
                        const topHeight = totalHeight - timelineHeight;
                        readerContainer.style.gridTemplateRows = `${topHeight}px 4px ${timelineHeight}px`;
                        
                        // Update timeline height dynamically during resize
                        if (timeline) {
                            const timelineContainer = document.querySelector('.timeline-panel');
                            if (timelineContainer) {
                                const newHeight = timelineHeight - 40; // Subtract padding
                                timeline.setOptions({
                                    height: newHeight + 'px'
                                });
                            }
                        }
                    }
                }
            });
            
            document.addEventListener('mouseup', () => {
                if (isResizingVertical || isResizingHorizontal) {
                    isResizingVertical = false;
                    isResizingHorizontal = false;
                    document.body.style.cursor = 'default';
                    
                    // Trigger map resize event
                    if (map) {
                        setTimeout(() => map.invalidateSize(), 100);
                    }
                    
                    // Trigger timeline redraw and update height
                    if (timeline && isResizingHorizontal) {
                        const timelineContainer = document.querySelector('.timeline-panel');
                        if (timelineContainer) {
                            const newHeight = timelineContainer.offsetHeight - 40; // Subtract padding
                            timeline.setOptions({
                                height: newHeight + 'px'
                            });
                        }
                        timeline.redraw();
                    } else if (timeline) {
                        timeline.redraw();
                    }
                }
            });
            
            // Initialize Map
            map = L.map('map').setView([31.7683, 35.2137], 7);
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: 'Ãƒâ€šÃ‚Â© OpenStreetMap contributors',
                maxZoom: 19
            }).addTo(map);
            
            // Fix map size after container is ready
            setTimeout(() => {
                map.invalidateSize();
            }, 500);
            
            // Load all locations initially on map
            let locationsByName = {}; // Global mapping: location name -> location data
            let allLocations = []; // Store all locations for text scanning
            let allPersonen = []; // Store all person timeline events for text scanning
            let personenByName = {}; // Global mapping: person name -> timeline event data
            let originalTimelineItems = {}; // Cache of original timeline item styles
            let visibleGroupIds = new Set(); // Track which groups are currently visible
            let allTimelineEvents = []; // Store all timeline events for search
            
            async function loadAllLocationsOnMap() {
                const locations = await apiCall('locations');
                if (locations && locations.length > 0) {
                    // Build location name mapping
                    locationsByName = {};
                    allLocations = locations;
                    
                    locations.forEach(loc => {
                        // Store by exact name (case-insensitive key)
                        locationsByName[loc.Naam.toLowerCase()] = loc;
                        
                        const marker = L.marker([loc.Latitude, loc.Longitude])
                            .bindPopup(`<b>${loc.Naam}</b><br>${loc.Beschrijving || ''}<br><small>${loc.Type}</small>`)
                            .addTo(map);
                        marker.locatieId = loc.Locatie_ID; // Store ID for filtering later
                        marker.locatieNaam = loc.Naam; // Store name for text matching
                    });
                    console.log(`Loaded ${locations.length} locations on map`);
                } else {
                    console.log('No locations found in database');
                }
            }
            
            // Find locations mentioned in text
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
                    // Use word boundaries to avoid partial matches
                    const regex = new RegExp('\\b' + locationName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'i');
                    
                    if (regex.test(cleanText)) {
                        foundLocations.push(location);
                    }
                });
                
                return foundLocations;
            }
            
            // Load all locations on startup
            loadAllLocationsOnMap();
            
            // Initialize Timeline
            const container = document.getElementById('timeline');
            const timelineItems = new vis.DataSet();
            const timelineGroups = new vis.DataSet();
            const options = {
                height: '300px',
                orientation: 'top',
                zoomMin: 1000 * 60 * 60 * 24 * 365,
                zoomMax: 1000 * 60 * 60 * 24 * 365 * 100,
                zoomable: true,
                moveable: true,
                horizontalScroll: true,
                groupOrder: 'order',
                stack: true, // Stack items within groups to prevent overlap
                selectable: true, // Make items selectable
                multiselect: false, // Only select one item at a time
                margin: {
                    item: {
                        horizontal: 5,
                        vertical: 3
                    },
                    axis: 5
                },
                showTooltips: false // We handle tooltips ourselves with popup
            };
            timeline = new vis.Timeline(container, timelineItems, timelineGroups, options);
            
            // Load initial data
            async function initReader() {
                // Load books
                const books = await apiCall('books');
                const bookSelect = document.getElementById('bookSelect');
                books.forEach(book => {
                    const option = document.createElement('option');
                    option.value = book.Bijbelboeknaam;
                    option.textContent = book.Bijbelboeknaam;
                    bookSelect.appendChild(option);
                });
                
                // Load profiles
                const profiles = await apiCall('profiles');
                const profileSelect = document.getElementById('profileSelect');
                
                // Don't add "Geen opmaak" - it's already in HTML
                
                profiles.forEach(profile => {
                    const option = document.createElement('option');
                    option.value = profile.Profiel_ID;
                    option.textContent = profile.Profiel_Naam;
                    profileSelect.appendChild(option);
                });
                
                // Restore profile from localStorage, or use first as default
                const lastProfile = localStorage.getItem('lastProfile');
                if (lastProfile && profiles.some(p => p.Profiel_ID == lastProfile)) {
                    currentProfile = lastProfile;
                    profileSelect.value = currentProfile;
                    console.log(`Restored profile from localStorage: ${currentProfile}`);
                } else if (profiles.length > 0) {
                    currentProfile = profiles[0].Profiel_ID;
                    profileSelect.value = currentProfile;
                    console.log(`Default profile selected: ${currentProfile}`);
                }
                
                // Restore last position from localStorage BEFORE loading verses
                const lastBook = localStorage.getItem('lastBook');
                const lastChapter = localStorage.getItem('lastChapter');
                const lastVerse = localStorage.getItem('lastVerse');
                
                if (lastBook && books.some(b => b.Bijbelboeknaam === lastBook)) {
                    bookSelect.value = lastBook;
                    currentBook = lastBook;
                    console.log(`Restored book from localStorage: ${lastBook}`);
                    
                    // Load chapters for the book
                    const chapters = await apiCall(`chapters&boek=${encodeURIComponent(lastBook)}`);
                    const chapterSelect = document.getElementById('chapterSelect');
                    chapterSelect.innerHTML = '<option value="">Alle hoofdstukken</option>';
                    
                    chapters.forEach(ch => {
                        const option = document.createElement('option');
                        option.value = ch.Hoofdstuknummer;
                        option.textContent = `Hoofdstuk ${ch.Hoofdstuknummer}`;
                        chapterSelect.appendChild(option);
                    });
                    
                    // Restore chapter if available
                    if (lastChapter) {
                        chapterSelect.value = lastChapter;
                        currentChapter = lastChapter;
                        console.log(`Restored chapter from localStorage: ${lastChapter}`);
                    }
                }
                
                // Load initial verses (now with restored position)
                await loadVerses();
                
                // After verses are loaded, scroll to last verse and select it
                if (lastVerse) {
                    setTimeout(() => {
                        const verseElement = document.querySelector(`[data-vers-id="${lastVerse}"]`);
                        if (verseElement) {
                            verseElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            selectVerse(parseInt(lastVerse), false);
                            console.log(`Restored verse from localStorage: ${lastVerse}`);
                        }
                    }, 300);
                }
                
                // Load timeline events
                loadTimelineEvents();
                
                // Load timeline groups for filter
                loadTimelineGroups();
            }
            
            // Load verses with lazy loading
            let selectedChapter = null; // Track initially selected chapter
            let continuousScrolling = false; // Track if we're in continuous scrolling mode
            let lastLoadedVersId = null; // Track last loaded verse ID for continuous scrolling
            
            async function loadVerses(append = false) {
                if (loading || (allLoaded && append)) return;
                
                loading = true;
                const container = document.getElementById('bibleText');
                
                if (!append) {
                    container.innerHTML = '<div class="loading">Laden...</div>';
                    currentOffset = 0;
                    allLoaded = false;
                    lastChapter = null; // Reset chapter tracking
                    selectedChapter = currentChapter; // Remember what user selected
                    continuousScrolling = false;
                    lastLoadedVersId = null;
                    firstLoadedVersId = null;
                    allLoadedBackward = false;
                }
                
                const params = new URLSearchParams({
                    limit: 50
                });
                
                if (currentBook) params.append('boek', currentBook);
                if (currentProfile) params.append('profiel_id', currentProfile);
                
                // If in continuous scrolling mode, use after_vers_id instead of chapter filter
                if (continuousScrolling && lastLoadedVersId) {
                    params.append('after_vers_id', lastLoadedVersId);
                    params.append('offset', 0);
                } else {
                    params.append('offset', currentOffset);
                    // Only filter by chapter if not in continuous mode
                    if (currentChapter) {
                        params.append('hoofdstuk', currentChapter);
                    }
                }
                
                const verses = await apiCall('verses&' + params.toString());
                
                if (!verses || verses.length === 0) {
                    if (!append) {
                        container.innerHTML = '<div class="loading">Geen verzen gevonden</div>';
                        allLoaded = true;
                    } else if (currentChapter && !continuousScrolling && lastLoadedVersId) {
                        // Selected chapter is fully loaded, enable continuous scrolling
                        console.log('Chapter complete, enabling continuous scrolling from vers_id:', lastLoadedVersId);
                        continuousScrolling = true;
                        loading = false;
                        loadVerses(true); // Load more (now without chapter filter, using after_vers_id)
                        return;
                    } else {
                        allLoaded = true;
                    }
                    loading = false;
                    return;
                }
                
                if (!append) {
                    container.innerHTML = '';
                }
                
                // Track the first and last verse IDs for bidirectional scrolling
                if (verses.length > 0) {
                    lastLoadedVersId = verses[verses.length - 1].Vers_ID;
                    if (!firstLoadedVersId) {
                        firstLoadedVersId = verses[0].Vers_ID;
                    }
                }
                
                try {
                    for (const verse of verses) {
                    // Check if we need a chapter header
                    const chapterKey = `${verse.Bijbelboeknaam}_${verse.Hoofdstuknummer}`;
                    if (lastChapter !== chapterKey) {
                        const chapterHeader = document.createElement('div');
                        chapterHeader.className = 'chapter-header';
                        chapterHeader.textContent = `${verse.Bijbelboeknaam} ${verse.Hoofdstuknummer}`;
                        container.appendChild(chapterHeader);
                        lastChapter = chapterKey;
                    }
                    
                    // Check for images for this verse
                    const images = await apiCall(`verse_images&vers_id=${verse.Vers_ID}`);
                    
                    // Add images before the verse if any
                    if (images && images.length > 0) {
                        images.forEach(img => {
                            const imgContainer = document.createElement('div');
                            imgContainer.className = `verse-image-container align-${img.Uitlijning}`;
                            
                            const imgElement = document.createElement('img');
                            imgElement.src = img.Bestandspad;
                            imgElement.className = 'verse-image';
                            imgElement.style.width = img.Breedte + 'px';
                            if (img.Hoogte) {
                                imgElement.style.height = img.Hoogte + 'px';
                            }
                            imgElement.alt = img.Caption || '';
                            imgElement.title = 'Klik om te vergroten';
                            
                            // Add click handler for lightbox
                            imgElement.addEventListener('click', (e) => {
                                e.stopPropagation();
                                openLightbox(img.Bestandspad);
                            });
                            
                            imgContainer.appendChild(imgElement);
                            
                            if (img.Caption) {
                                const caption = document.createElement('div');
                                caption.className = 'image-caption';
                                caption.textContent = img.Caption;
                                imgContainer.appendChild(caption);
                            }
                            
                            container.appendChild(imgContainer);
                        });
                    }
                    
                    const verseSpan = document.createElement('span');
                    verseSpan.className = 'verse';
                    verseSpan.dataset.versId = verse.Vers_ID;
                    
                    const reference = document.createElement('sup');
                    reference.className = 'verse-reference';
                    reference.textContent = verse.Versnummer;
                    
                    const text = document.createElement('span');
                    text.className = 'verse-text';
                    
                    let displayText = verse.Opgemaakte_Tekst || verse.Tekst;
                    
                    // Highlight search terms
                    if (searchQuery) {
                        const regex = new RegExp(`(${searchQuery})`, 'gi');
                        displayText = displayText.replace(regex, '<mark>$1</mark>');
                    }
                    
                    text.innerHTML = displayText;
                    
                    verseSpan.appendChild(reference);
                    verseSpan.appendChild(text);
                    verseSpan.appendChild(document.createTextNode(' '));
                    
                    verseSpan.addEventListener('click', () => {
                        isAutoScrolling = true;
                        selectVerse(verse.Vers_ID, true);
                        setTimeout(() => isAutoScrolling = false, 1000);
                    });
                    
                    container.appendChild(verseSpan);
                }
                
                currentOffset += verses.length;
                loading = false;
                
                // Only mark as fully loaded if:
                // - We're in continuous scrolling mode and got less than 50
                // - OR we have no chapter filter and got less than 50
                if (verses.length < 50) {
                    if (continuousScrolling || !currentChapter) {
                        allLoaded = true;
                    }
                    // If we have a chapter filter and got less than 50, we'll switch to continuous scrolling on next scroll
                }
                
                } catch (error) {
                    console.error('Error loading verses:', error);
                    loading = false;
                    container.innerHTML = '<div class="loading" style="color: red;">Fout bij laden: ' + error.message + '</div>';
                }
            }
            
            // Select a verse (show on map and timeline)
            async function selectVerse(versId, fromClick = false) {
                if (currentVerse === versId && !fromClick) return; // Already selected
                
                document.querySelectorAll('.verse').forEach(v => v.classList.remove('active'));
                const verseElement = document.querySelector(`[data-vers-id="${versId}"]`);
                if (verseElement) {
                    verseElement.classList.add('active');
                }
                
                currentVerse = versId;
                
                // Save to localStorage
                localStorage.setItem('lastVerse', versId);
                
                // Update timeline focus - always update, except when click comes FROM timeline
                const fromTimeline = window.timelineClickInProgress || false;
                if (!fromTimeline) {
                    updateTimelineFocus(versId);
                }
                
                // First: Check for location names in the verse text itself
                let locationsToShow = [];
                
                if (verseElement) {
                    const verseText = verseElement.querySelector('.verse-text')?.textContent || '';
                    locationsToShow = findLocationsInText(verseText);
                    console.log(`Found ${locationsToShow.length} locations in verse text: ${locationsToShow.map(l => l.Naam).join(', ')}`);
                }
                
                // Second: If no locations found in text, fall back to verse_id mappings
                if (locationsToShow.length === 0) {
                    const verseLocations = await apiCall(`locations&vers_id=${versId}`);
                    if (verseLocations && verseLocations.length > 0) {
                        locationsToShow = verseLocations;
                        console.log(`Using ${locationsToShow.length} verse_id mapped locations`);
                    }
                }
                
                // Reset all markers to default style
                map.eachLayer(layer => {
                    if (layer instanceof L.Marker) {
                        // Reset to default marker
                        layer.setOpacity(0.5);
                        layer.setZIndexOffset(0);
                    }
                });
                
                if (locationsToShow.length > 0) {
                    // Highlight found locations
                    let firstLocation = null;
                    
                    locationsToShow.forEach((loc, index) => {
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
                        
                        // Open popup only on click, not on auto-scroll
                        if (fromClick && index === 0) {
                            marker.openPopup();
                        }
                    });
                    
                    // Always center map on locations
                    if (locationsToShow.length === 1) {
                        // Single location - center and zoom in
                        map.setView([firstLocation.Latitude, firstLocation.Longitude], 9, {
                            animate: !fromClick
                        });
                    } else {
                        // Multiple locations - fit all in view
                        const bounds = L.latLngBounds(
                            locationsToShow.map(loc => [loc.Latitude, loc.Longitude])
                        );
                        map.fitBounds(bounds, {
                            padding: [50, 50],
                            animate: !fromClick
                        });
                    }
                } else {
                    // No locations found, show all at default opacity
                    map.eachLayer(layer => {
                        if (layer instanceof L.Marker) {
                            layer.setOpacity(0.7);
                        }
                    });
                }
                
                // Check for persons mentioned in the verse text
                // Only highlight if persons system is active (allPersonen loaded)
                if (verseElement && allPersonen.length > 0) {
                    const verseText = verseElement.querySelector('.verse-text')?.textContent || '';
                    const personenInVerse = findPersonenInText(verseText);
                    
                    if (personenInVerse.length > 0) {
                        console.log(`Found ${personenInVerse.length} persons in verse text: ${personenInVerse.map(p => p.Titel).join(', ')}`);
                        
                        // Smart filtering for duplicate names based on book context
                        let filteredPersonen = personenInVerse;
                        
                        // Try to get timeline context from verse_id (even if group is filtered out)
                        const allItems = timeline.itemsData.get();
                        const verseContext = allItems.find(item => {
                            if (item.vers_id_start && item.vers_id_end) {
                                return versId >= item.vers_id_start && versId <= item.vers_id_end;
                            } else if (item.vers_id_start) {
                                return versId === item.vers_id_start;
                            }
                            return false;
                        });
                        
                        if (verseContext && verseContext.start) {
                            // We have a time context - filter persons to those closest in time
                            const contextYear = verseContext.start.getFullYear();
                            console.log(`Verse context year: ${contextYear}`);
                            
                            // Group persons by name
                            const personsByName = {};
                            personenInVerse.forEach(p => {
                                const name = p.Titel.toLowerCase();
                                if (!personsByName[name]) {
                                    personsByName[name] = [];
                                }
                                personsByName[name].push(p);
                            });
                            
                            // For each name with duplicates, pick the closest in time
                            filteredPersonen = [];
                            Object.values(personsByName).forEach(persons => {
                                if (persons.length === 1) {
                                    // Single person with this name - keep it
                                    filteredPersonen.push(persons[0]);
                                } else {
                                    // Multiple persons with same name - pick closest to context
                                    console.log(`Multiple ${persons[0].Titel} found, filtering by time context`);
                                    
                                    let closestPerson = persons[0];
                                    let closestDistance = Infinity;
                                    
                                    persons.forEach(person => {
                                        // Parse person dates
                                        let personYear = null;
                                        if (person.Start_Datum) {
                                            if (person.Start_Datum.startsWith('-')) {
                                                personYear = parseInt(person.Start_Datum);
                                            } else {
                                                personYear = new Date(person.Start_Datum).getFullYear();
                                            }
                                        }
                                        
                                        if (personYear !== null) {
                                            const distance = Math.abs(contextYear - personYear);
                                            console.log(`  - ${person.Titel} (${personYear}): distance ${distance} years`);
                                            
                                            if (distance < closestDistance) {
                                                closestDistance = distance;
                                                closestPerson = person;
                                            }
                                        }
                                    });
                                    
                                    console.log(`  ÃƒÂ¢Ã¢â‚¬Â Ã¢â‚¬â„¢ Selected ${closestPerson.Titel} (${closestPerson.Start_Datum}) as closest match`);
                                    filteredPersonen.push(closestPerson);
                                }
                            });
                        }
                        
                        console.log(`After smart filtering: ${filteredPersonen.length} persons`);
                        
                        // Highlight found persons in timeline
                        highlightPersonenInTimeline(filteredPersonen);
                    } else {
                        // Reset timeline items to default (no fading when no persons found)
                        resetTimelineHighlight();
                    }
                }
            }
            
            // Reset timeline highlight
            function resetTimelineHighlight() {
                if (!timeline) return;
                
                // Don't reset if no items cached yet
                if (!originalTimelineItems || Object.keys(originalTimelineItems).length === 0) {
                    console.log('Reset skipped - no original items cached yet');
                    return;
                }
                
                const allItems = timeline.itemsData.get();
                allItems.forEach(item => {
                    // Restore original style from cache
                    const original = originalTimelineItems[item.id];
                    if (original) {
                        timeline.itemsData.update({
                            id: item.id,
                            style: original.style,
                            className: original.className
                        });
                    }
                });
                console.log('Timeline reset to original styles');
            }
            
            // Highlight persons in timeline
            function highlightPersonenInTimeline(personen) {
                if (!timeline) return;
                
                // Don't highlight if no items cached yet
                if (!originalTimelineItems || Object.keys(originalTimelineItems).length === 0) {
                    console.log('Highlight skipped - no original items cached yet');
                    return;
                }
                
                // Filter personen to only include those in visible groups
                const visiblePersonen = personen.filter(persoon => {
                    return persoon.Group_ID && visibleGroupIds.has(persoon.Group_ID);
                });
                
                if (visiblePersonen.length === 0) {
                    console.log(`Found ${personen.length} persons but none are in visible groups - no highlighting`);
                    return;
                }
                
                console.log(`Highlighting ${visiblePersonen.length} persons in timeline (${personen.length} total found, ${personen.length - visiblePersonen.length} filtered out)`);
                
                // Get all timeline items
                const allItems = timeline.itemsData.get();
                
                // Fade all items to 0.3 opacity
                allItems.forEach(item => {
                    const original = originalTimelineItems[item.id];
                    if (original) {
                        timeline.itemsData.update({
                            id: item.id,
                            style: original.style + ' opacity: 0.3;'
                        });
                    }
                });
                
                // Highlight found persons (full opacity + glow) - only visible ones
                visiblePersonen.forEach(persoon => {
                    const item = timeline.itemsData.get(persoon.Event_ID);
                    if (item) {
                        const original = originalTimelineItems[item.id];
                        if (original) {
                            timeline.itemsData.update({
                                id: item.id,
                                style: original.style + ' opacity: 1.0; box-shadow: 0 0 10px rgba(52, 152, 219, 0.5);'
                            });
                        }
                    }
                });
                
                // Scroll timeline to show found persons - only visible ones
                if (visiblePersonen.length === 1) {
                    // Single person - focus on that person
                    timeline.focus(visiblePersonen[0].Event_ID, {
                        animation: {
                            duration: 500,
                            easingFunction: 'easeInOutQuad'
                        }
                    });
                } else if (visiblePersonen.length > 1) {
                    // Multiple persons - calculate time window to show all
                    const items = visiblePersonen.map(p => timeline.itemsData.get(p.Event_ID)).filter(item => item);
                    
                    if (items.length > 0) {
                        // Find earliest start and latest end
                        let minStart = items[0].start;
                        let maxEnd = items[0].end || items[0].start;
                        
                        items.forEach(item => {
                            if (item.start < minStart) minStart = item.start;
                            const itemEnd = item.end || item.start;
                            if (itemEnd > maxEnd) maxEnd = itemEnd;
                        });
                        
                        // Add 10% padding on each side
                        const range = maxEnd - minStart;
                        const padding = range * 0.1;
                        
                        timeline.setWindow(
                            new Date(minStart.getTime() - padding),
                            new Date(maxEnd.getTime() + padding),
                            {
                                animation: {
                                    duration: 500,
                                    easingFunction: 'easeInOutQuad'
                                }
                            }
                        );
                    }
                }
            }
            
            // Auto-select verse on scroll - verbeterd
            let scrollTimeout;
            let isAutoScrolling = false;
            let scrollSyncEnabled = true; // Enable scroll sync by default
            
            document.getElementById('bibleText').addEventListener('scroll', () => {
                if (isAutoScrolling || !scrollSyncEnabled) return;
                
                clearTimeout(scrollTimeout);
                scrollTimeout = setTimeout(() => {
                    const container = document.getElementById('bibleText');
                    const verses = container.querySelectorAll('.verse');
                    const containerRect = container.getBoundingClientRect();
                    
                    // Find the first verse below the sticky header
                    // Account for sticky chapter header (~60px)
                    const headerOffset = 140;
                    
                    for (let verse of verses) {
                        const rect = verse.getBoundingClientRect();
                        
                        // Check if verse top is below header and in visible area
                        if (rect.top >= containerRect.top + headerOffset && 
                            rect.top <= containerRect.top + (containerRect.height * 0.4)) {
                            const versId = parseInt(verse.dataset.versId);
                            if (versId && currentVerse !== versId) {
                                selectVerse(versId);
                            }
                            break;
                        }
                    }
                }, 500); // Increased debounce to 500ms
            });
            
            // Helper function: Calculate contrasting text color for a background color
            function getContrastColor(hexColor) {
                // Remove # if present
                const hex = hexColor.replace('#', '');
                
                // Convert to RGB
                const r = parseInt(hex.substr(0, 2), 16);
                const g = parseInt(hex.substr(2, 2), 16);
                const b = parseInt(hex.substr(4, 2), 16);
                
                // Calculate luminance using the relative luminance formula
                // https://www.w3.org/TR/WCAG20/#relativeluminancedef
                const luminance = (0.299 * r + 0.587 * g + 0.114 * b) / 255;
                
                // Return black for light backgrounds, white for dark backgrounds
                return luminance > 0.5 ? '#000000' : '#ffffff';
            }
            
            // Load timeline events and groups
            async function loadTimelineEvents() {
                // Load groups first
                const groups = await apiCall('timeline_groups');
                if (groups) {
                    const groupData = groups
                        .filter(g => g.Zichtbaar === 1) // Only visible groups
                        .map(group => ({
                            id: group.Group_ID,
                            content: group.Groep_Naam,
                            order: group.Volgorde,
                            style: `background-color: ${group.Kleur}20; border-color: ${group.Kleur};`
                        }));
                    
                    // Track which groups are visible
                    visibleGroupIds = new Set(groupData.map(g => g.id));
                    console.log('Visible groups:', Array.from(visibleGroupIds));
                    
                    timeline.setGroups(groupData);
                }
                
                // Load events
                const events = await apiCall('timeline');
                if (events) {
                    // Store all events for search functionality
                    allTimelineEvents = events;
                    
                    const items = events.map(event => {
                        // Parse dates - handle various formats
                        let startDate = event.Start_Datum;
                        let endDate = event.End_Datum;
                        
                        // Try to create valid dates for vis.js
                        try {
                            // If it's a negative year (BC), use a simple year format
                            if (startDate && startDate.startsWith('-')) {
                                const year = parseInt(startDate);
                                startDate = new Date(year, 0, 1);
                            } else if (startDate) {
                                startDate = new Date(startDate);
                            } else {
                                startDate = null;
                            }
                            
                            // Check if date is valid
                            if (startDate && isNaN(startDate.getTime())) {
                                console.warn('Invalid start date for event:', event.Titel, event.Start_Datum);
                                startDate = null;
                            }
                            
                            if (endDate && endDate.startsWith('-')) {
                                const year = parseInt(endDate);
                                endDate = new Date(year, 11, 31);
                            } else if (endDate) {
                                endDate = new Date(endDate);
                            } else {
                                endDate = null;
                            }
                            
                            // Check if end date is valid
                            if (endDate && isNaN(endDate.getTime())) {
                                console.warn('Invalid end date for event:', event.Titel, event.End_Datum);
                                endDate = null;
                            }
                        } catch (e) {
                            console.warn('Date parse error for event:', event.Titel, e);
                            startDate = null;
                            endDate = null;
                        }
                        
                        // Return null for events without valid start date - these will be filtered out
                        if (!startDate) {
                            console.warn('Skipping timeline event without valid start date:', event.Titel);
                            return null;
                        }
                        
                        // Determine type based on whether we have an end date
                        // Range items REQUIRE an end date in vis.js
                        let itemType = event.Type || 'box';
                        if (itemType === 'range' && !endDate) {
                            console.warn(`Event "${event.Titel}" is type "range" but has no end date - changing to "box"`);
                            itemType = 'box';
                        }
                        
                        const item = {
                            id: event.Event_ID,
                            content: event.Titel,
                            start: startDate,
                            type: itemType,
                            style: `background-color: ${event.Kleur}; color: ${event.Tekst_Kleur || getContrastColor(event.Kleur)};`,
                            className: 'timeline-event',
                            title: event.Beschrijving || '', // Keep for accessibility
                            vers_id_start: event.Vers_ID_Start,
                            vers_id_end: event.Vers_ID_End,
                            // Extra data for popup
                            description: event.Beschrijving || '',
                            color: event.Kleur,
                            textColor: event.Tekst_Kleur,
                            groupName: event.Groep_Naam || '',
                            startDatum: event.Start_Datum,
                            endDatum: event.End_Datum
                        };
                        
                        // Only add end date if it exists (required for range items)
                        if (endDate) {
                            item.end = endDate;
                        }
                        
                        // Add group if exists
                        if (event.Group_ID) {
                            item.group = event.Group_ID;
                        }
                        
                        return item;
                    }).filter(item => item !== null); // Filter out null items
                    
                    console.log(`Loading ${items.length} timeline events (filtered from ${events.length} total)`);
                    timeline.setItems(items);
                    console.log('Timeline items set successfully');
                    
                    // Cache original styles for reset functionality
                    originalTimelineItems = {};
                    items.forEach(item => {
                        originalTimelineItems[item.id] = {
                            style: item.style,
                            className: item.className
                        };
                    });
                    console.log(`Cached ${Object.keys(originalTimelineItems).length} original timeline item styles`);
                    
                    // Load persons from timeline events with "Personen" group
                    loadPersonenFromTimeline(events);
                    
                    // Update navigation buttons after loading items
                    setTimeout(() => updateNavButtons(), 100);
                    
                    // Add click handler to timeline items - use both click and select for better compatibility
                    timeline.on('click', function (properties) {
                        if (properties.item) {
                            const item = timeline.itemsData.get(properties.item);
                            if (item) {
                                // Select the item
                                timeline.setSelection([properties.item]);
                                // Show popup with event details
                                showTimelinePopup(item);
                                // Update navigation buttons
                                updateNavButtons();
                            }
                        }
                    });
                    
                    timeline.on('select', function (properties) {
                        if (properties.items.length > 0) {
                            const item = timeline.itemsData.get(properties.items[0]);
                            
                            // Show popup with event details
                            showTimelinePopup(item);
                        }
                        
                        // Update navigation buttons
                        updateNavButtons();
                    });
                }
            }
            window.loadTimelineEvents = loadTimelineEvents; // Export for toggle functions
            
            // Current selected timeline item (for "go to verse" button)
            let currentTimelineItem = null;
            
            // Show timeline event popup
            function showTimelinePopup(item) {
                currentTimelineItem = item;
                
                const popup = document.getElementById('timelinePopup');
                const titleEl = document.getElementById('timelinePopupTitle');
                const dateEl = document.getElementById('timelinePopupDate');
                const groupEl = document.getElementById('timelinePopupGroup');
                const descEl = document.getElementById('timelinePopupDescription');
                const goToVerseBtn = document.getElementById('timelinePopupGoToVerse');
                
                // Set title
                titleEl.textContent = item.content;
                
                // Format date
                let dateStr = '';
                if (item.startDatum) {
                    const year = parseInt(item.startDatum);
                    if (year < 0) {
                        dateStr = Math.abs(year) + ' v.Chr.';
                    } else {
                        dateStr = year + ' n.Chr.';
                    }
                    
                    if (item.endDatum && item.endDatum !== item.startDatum) {
                        const endYear = parseInt(item.endDatum);
                        if (endYear < 0) {
                            dateStr += ' - ' + Math.abs(endYear) + ' v.Chr.';
                        } else {
                            dateStr += ' - ' + endYear + ' n.Chr.';
                        }
                    }
                }
                dateEl.textContent = dateStr || 'Geen datum';
                
                // Set group badge
                if (item.groupName) {
                    groupEl.textContent = item.groupName;
                    groupEl.style.backgroundColor = item.color || '#666';
                    groupEl.style.display = '';
                } else {
                    groupEl.style.display = 'none';
                }
                
                // Set description (use innerHTML for HTML content from Quill)
                if (item.description && item.description.trim() !== '' && item.description !== '<p><br></p>') {
                    descEl.innerHTML = item.description;
                } else {
                    descEl.innerHTML = '<em class="text-muted">Geen beschrijving beschikbaar.</em>';
                }
                
                // Show/hide "go to verse" button
                if (item.vers_id_start) {
                    goToVerseBtn.style.display = '';
                } else {
                    goToVerseBtn.style.display = 'none';
                }
                
                // Show popup
                popup.classList.add('show');
            }
            window.showTimelinePopup = showTimelinePopup;
            
            // Close timeline popup
            function closeTimelinePopup() {
                document.getElementById('timelinePopup').classList.remove('show');
                currentTimelineItem = null;
            }
            window.closeTimelinePopup = closeTimelinePopup;
            
            // Helper: Get boek/hoofdstuk/vers from vers_id (for reader mode)
            async function getVersInfo(versId) {
                if (!versId) return null;
                return await apiCall(`get_vers_info&vers_id=${versId}`);
            }
            
            // Go to verse from timeline popup
            async function goToTimelineVerse() {
                if (!currentTimelineItem || !currentTimelineItem.vers_id_start) return;
                
                const versId = currentTimelineItem.vers_id_start;
                console.log('goToTimelineVerse called for vers_id:', versId);
                
                // Close popup first
                closeTimelinePopup();
                
                // Set flag to prevent timeline refocus
                window.timelineClickInProgress = true;
                
                // Check if verse is already in the DOM
                let verseElement = document.querySelector(`[data-vers-id="${versId}"]`);
                
                if (verseElement) {
                    console.log('Verse already in DOM, scrolling to it');
                    // Verse is already loaded - just scroll to it
                    isAutoScrolling = true;
                    verseElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    selectVerse(versId, true);
                    setTimeout(() => {
                        isAutoScrolling = false;
                        window.timelineClickInProgress = false;
                    }, 1000);
                } else {
                    console.log('Verse not in DOM, loading chapter...');
                    // Verse not in DOM - need to load it
                    const versInfo = await getVersInfo(versId);
                    console.log('Verse info:', versInfo);
                    
                    if (!versInfo) {
                        showNotification('Vers niet gevonden', true);
                        window.timelineClickInProgress = false;
                        return;
                    }
                    
                    // Update book and chapter selectors
                    const bookSelect = document.getElementById('bookSelect');
                    const chapterSelect = document.getElementById('chapterSelect');
                    
                    bookSelect.value = versInfo.Bijbelboeknaam;
                    currentBook = versInfo.Bijbelboeknaam;
                    
                    // Load chapters for this book
                    const chapters = await apiCall(`chapters&boek=${encodeURIComponent(versInfo.Bijbelboeknaam)}`);
                    chapterSelect.innerHTML = '<option value="">Hoofdstuk</option>';
                    chapters.forEach(ch => {
                        const option = document.createElement('option');
                        option.value = ch.Hoofdstuknummer;
                        option.textContent = ch.Hoofdstuknummer;
                        chapterSelect.appendChild(option);
                    });
                    
                    chapterSelect.value = versInfo.Hoofdstuknummer;
                    currentChapter = versInfo.Hoofdstuknummer;
                    
                    console.log('Loading chapter:', currentBook, currentChapter);
                    
                    // Reset scroll state
                    currentOffset = 0;
                    allLoaded = false;
                    allLoadedBackward = false;
                    continuousScrolling = false;
                    firstLoadedVersId = null;
                    lastLoadedVersId = null;
                    
                    // Clear container and load verses
                    const container = document.getElementById('bibleText');
                    container.innerHTML = '<div class="loading">Laden...</div>';
                    
                    // Load verses
                    loading = false; // Reset loading flag
                    await loadVerses(false);
                    
                    console.log('Verses loaded, waiting for render...');
                    
                    // Wait for verses to render, then scroll to target verse
                    // Try multiple times with increasing delay
                    let attempts = 0;
                    const maxAttempts = 10;
                    
                    const tryScrollToVerse = () => {
                        attempts++;
                        verseElement = document.querySelector(`[data-vers-id="${versId}"]`);
                        console.log(`Attempt ${attempts}: Looking for verse element...`, verseElement ? 'FOUND' : 'not found');
                        
                        if (verseElement) {
                            isAutoScrolling = true;
                            verseElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            selectVerse(versId, true);
                            setTimeout(() => {
                                isAutoScrolling = false;
                                window.timelineClickInProgress = false;
                            }, 1000);
                        } else if (attempts < maxAttempts) {
                            setTimeout(tryScrollToVerse, 200);
                        } else {
                            console.log('Could not find verse after', maxAttempts, 'attempts');
                            window.timelineClickInProgress = false;
                        }
                    };
                    
                    setTimeout(tryScrollToVerse, 300);
                }
            }
            window.goToTimelineVerse = goToTimelineVerse;
            
            // Close popup on background click
            document.getElementById('timelinePopup').addEventListener('click', (e) => {
                if (e.target.id === 'timelinePopup') {
                    closeTimelinePopup();
                }
            });
            
            // Search timeline events
            function searchTimelineEvents(query) {
                const resultsContainer = document.getElementById('timelineSearchResults');
                const resultsList = document.getElementById('timelineSearchResultsList');
                const countEl = document.getElementById('timelineSearchCount');
                
                if (!query || query.trim().length < 2) {
                    resultsContainer.classList.add('d-none');
                    return;
                }
                
                const searchTerm = query.toLowerCase().trim();
                
                // Search in all timeline events
                const results = allTimelineEvents.filter(event => {
                    const title = (event.Titel || '').toLowerCase();
                    const desc = (event.Beschrijving || '').toLowerCase();
                    return title.includes(searchTerm) || desc.includes(searchTerm);
                });
                
                // Show results
                resultsContainer.classList.remove('d-none');
                countEl.textContent = results.length;
                
                if (results.length === 0) {
                    resultsList.innerHTML = '<div class="p-2 text-muted text-center">Geen resultaten gevonden</div>';
                    return;
                }
                
                // Build results list
                resultsList.innerHTML = '';
                results.slice(0, 20).forEach(event => { // Limit to 20 results
                    const div = document.createElement('div');
                    div.className = 'timeline-search-result p-2 border-bottom';
                    div.style.cursor = 'pointer';
                    
                    // Highlight match in title
                    let titleHtml = event.Titel;
                    const titleLower = event.Titel.toLowerCase();
                    const matchIndex = titleLower.indexOf(searchTerm);
                    if (matchIndex >= 0) {
                        titleHtml = event.Titel.substring(0, matchIndex) + 
                            '<mark>' + event.Titel.substring(matchIndex, matchIndex + searchTerm.length) + '</mark>' +
                            event.Titel.substring(matchIndex + searchTerm.length);
                    }
                    
                    // Format date
                    let dateStr = '';
                    if (event.Start_Datum) {
                        const year = parseInt(event.Start_Datum);
                        if (year < 0) {
                            dateStr = Math.abs(year) + ' v.Chr.';
                        } else {
                            dateStr = year + ' n.Chr.';
                        }
                    }
                    
                    div.innerHTML = `
                        <div class="d-flex justify-content-between align-items-start">
                            <div>
                                <div class="fw-bold small">${titleHtml}</div>
                                <small class="text-muted">${dateStr}</small>
                            </div>
                            <span class="badge" style="background-color: ${event.Kleur || '#666'};">
                                ${event.Groep_Naam || 'Geen groep'}
                            </span>
                        </div>
                    `;
                    
                    // Click to navigate to event
                    div.addEventListener('click', () => {
                        navigateToTimelineEvent(event.Event_ID);
                    });
                    
                    // Hover effect
                    div.addEventListener('mouseenter', () => div.classList.add('bg-light'));
                    div.addEventListener('mouseleave', () => div.classList.remove('bg-light'));
                    
                    resultsList.appendChild(div);
                });
                
                if (results.length > 20) {
                    const moreDiv = document.createElement('div');
                    moreDiv.className = 'p-2 text-muted text-center small';
                    moreDiv.textContent = `En ${results.length - 20} meer resultaten...`;
                    resultsList.appendChild(moreDiv);
                }
            }
            window.searchTimelineEvents = searchTimelineEvents;
            
            // Navigate to a specific timeline event
            function navigateToTimelineEvent(eventId) {
                if (!timeline) return;
                
                // Focus and select the event
                timeline.focus(eventId, {
                    animation: {
                        duration: 500,
                        easingFunction: 'easeInOutQuad'
                    }
                });
                timeline.setSelection([eventId]);
                
                // Get the event item to scroll to associated verse
                const item = timeline.itemsData.get(eventId);
                if (item && item.vers_id_start) {
                    // Try to scroll to the verse
                    const verseElement = document.querySelector(`[data-vers-id="${item.vers_id_start}"]`);
                    if (verseElement) {
                        isAutoScrolling = true;
                        verseElement.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        selectVerse(item.vers_id_start, true);
                        setTimeout(() => isAutoScrolling = false, 1000);
                    }
                }
                
                // Close search results after navigation
                clearTimelineSearch();
            }
            window.navigateToTimelineEvent = navigateToTimelineEvent;
            
            // Clear timeline search
            function clearTimelineSearch() {
                const searchInput = document.getElementById('readerTimelineSearch');
                const resultsContainer = document.getElementById('timelineSearchResults');
                
                if (searchInput) searchInput.value = '';
                if (resultsContainer) resultsContainer.classList.add('d-none');
            }
            window.clearTimelineSearch = clearTimelineSearch;
            
            // Load persons from timeline events (only "Personen" group)
            function loadPersonenFromTimeline(events) {
                if (!events || events.length === 0) return;
                
                // Reset arrays
                allPersonen = [];
                personenByName = {};
                
                // Filter events that are in a group containing "Personen"
                const personEvents = events.filter(event => 
                    event.Groep_Naam && event.Groep_Naam.toLowerCase().includes('personen')
                );
                
                console.log(`Found ${personEvents.length} person events in timeline`);
                
                // Build name mapping
                personEvents.forEach(event => {
                    allPersonen.push(event);
                    
                    // Store by exact name (case-insensitive key)
                    const nameLower = event.Titel.toLowerCase();
                    
                    // If multiple persons with same name, store as array
                    if (!personenByName[nameLower]) {
                        personenByName[nameLower] = [];
                    }
                    personenByName[nameLower].push(event);
                });
                
                console.log(`Loaded ${allPersonen.length} persons from timeline, ${Object.keys(personenByName).length} unique names`);
            }
            
            // Find persons mentioned in text
            function findPersonenInText(text) {
                const foundPersonen = [];
                
                if (!text || !allPersonen || allPersonen.length === 0) {
                    return foundPersonen;
                }
                
                // Remove HTML tags and get clean text
                const cleanText = text.replace(/<[^>]*>/g, ' ').toLowerCase();
                
                // Check each person name
                allPersonen.forEach(persoon => {
                    const personName = persoon.Titel.toLowerCase();
                    
                    // Check if person name appears as whole word in text
                    // Use word boundaries to avoid partial matches
                    const regex = new RegExp('\\b' + personName.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\b', 'i');
                    
                    if (regex.test(cleanText)) {
                        foundPersonen.push(persoon);
                    }
                });
                
                return foundPersonen;
            }
            
            // Update timeline focus based on current verse
            function updateTimelineFocus(versId) {
                if (!timeline || !versId) return;
                
                try {
                    // Find events that match this verse
                    const items = timeline.itemsData.get();
                    
                    const matchingItems = items.filter(item => {
                        // First check if item is in a visible group
                        if (item.group && !visibleGroupIds.has(item.group)) {
                            return false; // Skip items in filtered-out groups
                        }
                        
                        if (item.vers_id_start && item.vers_id_end) {
                            // Range: check if versId is within range
                            return versId >= item.vers_id_start && versId <= item.vers_id_end;
                        } else if (item.vers_id_start) {
                            // Point: exact match
                            return versId === item.vers_id_start;
                        }
                        return false;
                    });
                    
                    if (matchingItems.length > 0) {
                        console.log(`Timeline focus: ${matchingItems.length} matching items in visible groups`);
                        
                        // Select all matching events
                        const ids = matchingItems.map(item => item.id);
                        timeline.setSelection(ids);
                        
                        // Simple focus without animation - more reliable
                        try {
                            // Get the time of the first item to center on
                            const firstItem = matchingItems[0];
                            timeline.moveTo(firstItem.start, {
                                animation: false  // Disable animation to prevent errors
                            });
                        } catch (focusError) {
                            // If that fails too, just keep the selection
                            console.warn('Timeline moveTo failed, keeping selection only');
                        }
                        
                        // Update navigation buttons
                        updateNavButtons();
                    } else {
                        // No matches - clear selection
                        timeline.setSelection([]);
                        updateNavButtons();
                    }
                } catch (error) {
                    console.error('Error updating timeline focus:', error);
                }
            }
            
            // Timeline navigation functions
            function navigateTimelinePrev() {
                if (!timeline) return;
                
                const allItems = timeline.itemsData.get();
                const selection = timeline.getSelection();
                
                // Filter to only visible groups
                const items = allItems.filter(item => {
                    // Items without group are always visible
                    if (!item.group) return true;
                    // Check if item's group is in visible set
                    return visibleGroupIds.has(item.group);
                });
                
                if (items.length === 0) return;
                
                // Sort items by date
                const sortedItems = items.sort((a, b) => {
                    const aDate = new Date(a.start);
                    const bDate = new Date(b.start);
                    return aDate - bDate;
                });
                
                if (selection.length === 0) {
                    // No selection - select last visible item
                    const lastItem = sortedItems[sortedItems.length - 1];
                    timeline.setSelection(lastItem.id);
                    timeline.moveTo(lastItem.start, { animation: true });
                    
                    // Scroll to verse if available
                    if (lastItem.vers_id_start) {
                        window.timelineClickInProgress = true;
                        selectVerse(lastItem.vers_id_start, true);
                        setTimeout(() => { window.timelineClickInProgress = false; }, 1000);
                    }
                } else {
                    // Find currently selected item in visible items
                    const currentId = selection[0];
                    const currentIndex = sortedItems.findIndex(item => item.id === currentId);
                    
                    if (currentIndex > 0) {
                        // Go to previous visible item
                        const prevItem = sortedItems[currentIndex - 1];
                        timeline.setSelection(prevItem.id);
                        timeline.moveTo(prevItem.start, { animation: true });
                        
                        // Scroll to verse if available
                        if (prevItem.vers_id_start) {
                            window.timelineClickInProgress = true;
                            selectVerse(prevItem.vers_id_start, true);
                            setTimeout(() => { window.timelineClickInProgress = false; }, 1000);
                        }
                    } else if (currentIndex === -1) {
                        // Current item is not visible, select last visible item
                        const lastItem = sortedItems[sortedItems.length - 1];
                        timeline.setSelection(lastItem.id);
                        timeline.moveTo(lastItem.start, { animation: true });
                        
                        if (lastItem.vers_id_start) {
                            window.timelineClickInProgress = true;
                            selectVerse(lastItem.vers_id_start, true);
                            setTimeout(() => { window.timelineClickInProgress = false; }, 1000);
                        }
                    }
                }
                
                updateNavButtons();
            }
            window.navigateTimelinePrev = navigateTimelinePrev;
            
            function navigateTimelineNext() {
                if (!timeline) return;
                
                const allItems = timeline.itemsData.get();
                const selection = timeline.getSelection();
                
                // Filter to only visible groups
                const items = allItems.filter(item => {
                    // Items without group are always visible
                    if (!item.group) return true;
                    // Check if item's group is in visible set
                    return visibleGroupIds.has(item.group);
                });
                
                if (items.length === 0) return;
                
                // Sort items by date
                const sortedItems = items.sort((a, b) => {
                    const aDate = new Date(a.start);
                    const bDate = new Date(b.start);
                    return aDate - bDate;
                });
                
                if (selection.length === 0) {
                    // No selection - select first visible item
                    const firstItem = sortedItems[0];
                    timeline.setSelection(firstItem.id);
                    timeline.moveTo(firstItem.start, { animation: true });
                    
                    // Scroll to verse if available
                    if (firstItem.vers_id_start) {
                        window.timelineClickInProgress = true;
                        selectVerse(firstItem.vers_id_start, true);
                        setTimeout(() => { window.timelineClickInProgress = false; }, 1000);
                    }
                } else {
                    // Find currently selected item in visible items
                    const currentId = selection[0];
                    const currentIndex = sortedItems.findIndex(item => item.id === currentId);
                    
                    if (currentIndex < sortedItems.length - 1 && currentIndex !== -1) {
                        // Go to next visible item
                        const nextItem = sortedItems[currentIndex + 1];
                        timeline.setSelection(nextItem.id);
                        timeline.moveTo(nextItem.start, { animation: true });
                        
                        // Scroll to verse if available
                        if (nextItem.vers_id_start) {
                            window.timelineClickInProgress = true;
                            selectVerse(nextItem.vers_id_start, true);
                            setTimeout(() => { window.timelineClickInProgress = false; }, 1000);
                        }
                    } else if (currentIndex === -1) {
                        // Current item is not visible, select first visible item
                        const firstItem = sortedItems[0];
                        timeline.setSelection(firstItem.id);
                        timeline.moveTo(firstItem.start, { animation: true });
                        
                        if (firstItem.vers_id_start) {
                            window.timelineClickInProgress = true;
                            selectVerse(firstItem.vers_id_start, true);
                            setTimeout(() => { window.timelineClickInProgress = false; }, 1000);
                        }
                    }
                }
                
                updateNavButtons();
            }
            window.navigateTimelineNext = navigateTimelineNext;
            
            // Update navigation button states
            function updateNavButtons() {
                if (!timeline) return;
                
                const allItems = timeline.itemsData.get();
                const selection = timeline.getSelection();
                
                // Filter to only visible groups
                const items = allItems.filter(item => {
                    if (!item.group) return true;
                    return visibleGroupIds.has(item.group);
                });
                
                const prevBtn = document.querySelector('.timeline-nav-prev');
                const nextBtn = document.querySelector('.timeline-nav-next');
                
                if (!prevBtn || !nextBtn) return;
                
                if (items.length === 0) {
                    prevBtn.classList.add('disabled');
                    nextBtn.classList.add('disabled');
                    return;
                }
                
                if (selection.length === 0) {
                    prevBtn.classList.remove('disabled');
                    nextBtn.classList.remove('disabled');
                    return;
                }
                
                // Sort items by date
                const sortedItems = items.sort((a, b) => {
                    const aDate = new Date(a.start);
                    const bDate = new Date(b.start);
                    return aDate - bDate;
                });
                
                const currentId = selection[0];
                const currentIndex = sortedItems.findIndex(item => item.id === currentId);
                
                // If current item is not in visible items, enable both buttons
                if (currentIndex === -1) {
                    prevBtn.classList.remove('disabled');
                    nextBtn.classList.remove('disabled');
                    return;
                }
                
                // Update button states based on position in visible items
                if (currentIndex === 0) {
                    prevBtn.classList.add('disabled');
                } else {
                    prevBtn.classList.remove('disabled');
                }
                
                if (currentIndex === sortedItems.length - 1) {
                    nextBtn.classList.add('disabled');
                } else {
                    nextBtn.classList.remove('disabled');
                }
            }
            
            // Event listeners
            document.getElementById('bookSelect').addEventListener('change', async (e) => {
                currentBook = e.target.value;
                currentChapter = null;
                
                // Save to localStorage
                localStorage.setItem('lastBook', currentBook);
                localStorage.removeItem('lastChapter'); // Clear chapter when book changes
                localStorage.removeItem('lastVerse'); // Clear verse when book changes
                
                // Load chapters
                const chapters = await apiCall(`chapters&boek=${encodeURIComponent(currentBook)}`);
                const chapterSelect = document.getElementById('chapterSelect');
                chapterSelect.innerHTML = '<option value="">Alle hoofdstukken</option>';
                
                chapters.forEach(ch => {
                    const option = document.createElement('option');
                    option.value = ch.Hoofdstuknummer;
                    option.textContent = `Hoofdstuk ${ch.Hoofdstuknummer}`;
                    chapterSelect.appendChild(option);
                });
                
                loadVerses();
            });
            
            document.getElementById('chapterSelect').addEventListener('change', (e) => {
                currentChapter = e.target.value;
                
                // Save to localStorage
                if (currentChapter) {
                    localStorage.setItem('lastChapter', currentChapter);
                } else {
                    localStorage.removeItem('lastChapter');
                }
                localStorage.removeItem('lastVerse'); // Clear verse when chapter changes
                
                loadVerses();
            });
            
            document.getElementById('profileSelect').addEventListener('change', (e) => {
                currentProfile = e.target.value || null;
                
                // Save to localStorage
                if (currentProfile) {
                    localStorage.setItem('lastProfile', currentProfile);
                } else {
                    localStorage.removeItem('lastProfile');
                }
                
                loadVerses();
            });
            
            document.getElementById('searchInput').addEventListener('input', (e) => {
                searchQuery = e.target.value;
                if (searchQuery.length > 2) {
                    loadVerses();
                } else if (searchQuery.length === 0) {
                    loadVerses();
                }
            });
            
            // Bidirectional infinite scroll
            document.getElementById('bibleText').addEventListener('scroll', (e) => {
                const element = e.target;
                
                // Scroll down - load more verses ahead
                if (element.scrollHeight - element.scrollTop <= element.clientHeight + 100) {
                    loadVerses(true);
                }
                
                // Scroll up - load previous verses
                if (element.scrollTop < 100 && firstLoadedVersId && !allLoadedBackward && !loadingBackward) {
                    loadVersesBefore();
                }
            });
            
            // Load verses before the first loaded verse (for backward scrolling)
            async function loadVersesBefore() {
                if (loadingBackward || allLoadedBackward || !firstLoadedVersId) return;
                
                loadingBackward = true;
                const container = document.getElementById('bibleText');
                const scrollHeightBefore = container.scrollHeight;
                const scrollTopBefore = container.scrollTop;
                
                const params = new URLSearchParams({
                    limit: 50,
                    offset: 0,
                    before_vers_id: firstLoadedVersId
                });
                
                if (currentBook) params.append('boek', currentBook);
                if (currentProfile) params.append('profiel_id', currentProfile);
                // Don't filter by chapter for backward scrolling - allow going to previous chapters
                
                const verses = await apiCall('verses&' + params.toString());
                
                if (!verses || verses.length === 0) {
                    allLoadedBackward = true;
                    loadingBackward = false;
                    console.log('Reached beginning of content');
                    return;
                }
                
                // Update first loaded verse ID
                firstLoadedVersId = verses[0].Vers_ID;
                
                // Create a document fragment to build content
                const fragment = document.createDocumentFragment();
                let prevChapterKey = null;
                
                for (const verse of verses) {
                    const chapterKey = `${verse.Bijbelboeknaam}_${verse.Hoofdstuknummer}`;
                    
                    // Add chapter header if needed
                    if (prevChapterKey !== chapterKey) {
                        const chapterHeader = document.createElement('div');
                        chapterHeader.className = 'chapter-header';
                        chapterHeader.textContent = `${verse.Bijbelboeknaam} ${verse.Hoofdstuknummer}`;
                        fragment.appendChild(chapterHeader);
                        prevChapterKey = chapterKey;
                    }
                    
                    // Create verse element
                    const verseSpan = document.createElement('span');
                    verseSpan.className = 'verse';
                    verseSpan.dataset.versId = verse.Vers_ID;
                    
                    const reference = document.createElement('span');
                    reference.className = 'verse-number';
                    reference.textContent = verse.Versnummer;
                    
                    const text = document.createElement('span');
                    text.className = 'verse-text';
                    text.innerHTML = verse.Opgemaakte_Tekst || verse.Tekst;
                    
                    verseSpan.appendChild(reference);
                    verseSpan.appendChild(text);
                    verseSpan.appendChild(document.createTextNode(' '));
                    
                    verseSpan.addEventListener('click', () => {
                        isAutoScrolling = true;
                        selectVerse(verse.Vers_ID, true);
                        setTimeout(() => isAutoScrolling = false, 1000);
                    });
                    
                    fragment.appendChild(verseSpan);
                }
                
                // Check if we need to remove the first chapter header in the existing content
                // to avoid duplicates
                const firstExistingHeader = container.querySelector('.chapter-header');
                if (firstExistingHeader && prevChapterKey) {
                    const existingHeaderText = firstExistingHeader.textContent;
                    const lastLoadedChapter = `${verses[verses.length - 1].Bijbelboeknaam} ${verses[verses.length - 1].Hoofdstuknummer}`;
                    if (existingHeaderText === lastLoadedChapter) {
                        firstExistingHeader.remove();
                    }
                }
                
                // Insert at the beginning
                container.insertBefore(fragment, container.firstChild);
                
                // Maintain scroll position
                const scrollHeightAfter = container.scrollHeight;
                container.scrollTop = scrollTopBefore + (scrollHeightAfter - scrollHeightBefore);
                
                loadingBackward = false;
                console.log('Loaded', verses.length, 'previous verses, new firstLoadedVersId:', firstLoadedVersId);
            }
            
            // Initialize
            initReader();
            
        } else {
            // ============= ADMIN MODE =============
            
            console.log('Admin mode initializing...');
            
            // Initialize Quill - alleen als editor container bestaat
            const editorContainer = document.getElementById('editor-container');
            if (editorContainer) {
                // Register custom fonts
                const Font = Quill.import('formats/font');
                Font.whitelist = ['serif', 'monospace', 'arial', 'times', 'courier', 'georgia', 'verdana', 'tahoma', 'trebuchet'];
                Quill.register(Font, true);
                
                quill = new Quill('#editor-container', {
                    theme: 'snow',
                    modules: {
                        toolbar: [
                            [{ 'header': [1, 2, 3, 4, 5, 6, false] }],
                            [{ 'size': ['small', false, 'large', 'huge'] }],
                            [{ 'font': Font.whitelist }],
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ 'color': [] }, { 'background': [] }],
                            [{ 'script': 'sub'}, { 'script': 'super' }],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            [{ 'indent': '-1'}, { 'indent': '+1' }],
                            [{ 'align': [] }],
                            ['link', 'blockquote'],
                            ['clean']
                        ]
                    }
                });
                console.log('Quill editor initialized with custom fonts:', Font.whitelist);
            } else {
                console.log('Editor container not found, Quill not initialized');
            }
            
            // Initialize Timeline Description Quill Editor
            let timelineDescQuill = null;
            const timelineDescContainer = document.getElementById('timelineBeschrijvingEditor');
            if (timelineDescContainer) {
                const Font = Quill.import('formats/font');
                timelineDescQuill = new Quill('#timelineBeschrijvingEditor', {
                    theme: 'snow',
                    placeholder: 'Beschrijving van het event...',
                    modules: {
                        toolbar: [
                            [{ 'header': [1, 2, 3, false] }],
                            [{ 'font': Font.whitelist }],
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ 'color': [] }, { 'background': [] }],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }],
                            ['link'],
                            ['clean']
                        ]
                    }
                });
                console.log('Timeline description Quill editor initialized');
            }
            
            // ============= NOTES SYSTEM (SQLite) =============
            let notesQuill = null;
            let currentNoteId = null;
            let notes = [];
            
            // Initialize notes editor
            function initNotesEditor() {
                const container = document.getElementById('notesEditorContainer');
                if (!container || notesQuill) return;
                
                notesQuill = new Quill('#notesEditorContainer', {
                    theme: 'snow',
                    placeholder: 'Begin hier met schrijven...',
                    modules: {
                        toolbar: [
                            [{ 'header': [1, 2, 3, false] }],
                            ['bold', 'italic', 'underline', 'strike'],
                            [{ 'color': [] }, { 'background': [] }],
                            [{ 'list': 'ordered'}, { 'list': 'bullet' }, { 'list': 'check' }],
                            [{ 'indent': '-1'}, { 'indent': '+1' }],
                            ['link'],
                            ['clean']
                        ]
                    }
                });
                
                // Load notes from database
                loadNotes();
                
                // Setup auto-save after editor is ready
                setupAutoSave();
            }
            
            // Load notes from SQLite database
            async function loadNotes() {
                const result = await apiCall('notes');
                if (result) {
                    notes = result.map(n => ({
                        id: n.Notitie_ID,
                        title: n.Titel || '',
                        content: n.Inhoud || '',
                        created: n.Aangemaakt,
                        updated: n.Gewijzigd
                    }));
                } else {
                    notes = [];
                }
                renderNotesList();
            }
            
            // Render notes list
            function renderNotesList() {
                const listContainer = document.getElementById('notesList');
                if (!listContainer) return;
                
                if (notes.length === 0) {
                    listContainer.innerHTML = `
                        <div style="text-align: center; padding: 2rem; color: var(--secondary-color);">
                            <p>Nog geen notities</p>
                        </div>
                    `;
                    return;
                }
                
                // Sort notes by updated date (newest first)
                const sortedNotes = [...notes].sort((a, b) => new Date(b.updated) - new Date(a.updated));
                
                listContainer.innerHTML = sortedNotes.map(note => {
                    const date = new Date(note.updated);
                    const dateStr = date.toLocaleDateString('nl-NL', { 
                        day: 'numeric', 
                        month: 'short',
                        hour: '2-digit',
                        minute: '2-digit'
                    });
                    
                    // Strip HTML for preview
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = note.content || '';
                    const plainText = tempDiv.textContent || '';
                    const preview = plainText.substring(0, 50) + (plainText.length > 50 ? '...' : '');
                    
                    return `
                        <div style="padding: 0.75rem; border-radius: 6px; cursor: pointer; margin-bottom: 0.5rem; background: ${note.id === currentNoteId ? 'var(--primary-color)' : 'var(--light-bg)'}; color: ${note.id === currentNoteId ? 'white' : 'inherit'}; border: 1px solid var(--border-color);" onclick="selectNote(${note.id})">
                            <div style="font-weight: 600; margin-bottom: 0.25rem; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">${note.title || 'Naamloos'}</div>
                            <div style="font-size: 0.75rem; opacity: 0.7;">${dateStr}</div>
                            ${preview ? `<div style="font-size: 0.8rem; opacity: 0.8; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; margin-top: 0.25rem;">${preview}</div>` : ''}
                        </div>
                    `;
                }).join('');
            }
            
            // Create new note
            async function createNewNote() {
                // Initialize editor if not done yet
                if (!notesQuill) {
                    initNotesEditor();
                }
                
                // Create new note in database
                const result = await apiCall('save_note', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        titel: 'Nieuwe notitie',
                        inhoud: ''
                    })
                });
                
                if (result && result.success) {
                    // Reload notes and select the new one
                    await loadNotes();
                    selectNote(result.notitie_id);
                    
                    // Focus on title input
                    setTimeout(() => {
                        const titleInput = document.getElementById('noteTitleInput');
                        if (titleInput) {
                            titleInput.value = '';
                            titleInput.focus();
                        }
                    }, 100);
                }
            }
            window.createNewNote = createNewNote;
            
            // Select a note
            function selectNote(noteId) {
                // Initialize editor if not done yet
                if (!notesQuill) {
                    initNotesEditor();
                }
                
                currentNoteId = noteId;
                const note = notes.find(n => n.id === noteId);
                
                if (!note) return;
                
                // Show editor, hide empty state (Bootstrap classes)
                document.getElementById('emptyNotesState').classList.add('d-none');
                const editorContent = document.getElementById('noteEditorContent');
                editorContent.classList.remove('d-none');
                editorContent.classList.add('d-flex');
                
                // Load note content
                document.getElementById('noteTitleInput').value = note.title || '';
                
                if (notesQuill) {
                    if (note.content) {
                        notesQuill.root.innerHTML = note.content;
                    } else {
                        notesQuill.setText('');
                    }
                }
                
                // Update list selection
                renderNotesList();
                
                // Clear save status
                document.getElementById('noteSaveStatus').textContent = '';
            }
            window.selectNote = selectNote;
            
            // Save current note to database
            async function saveCurrentNote() {
                if (!currentNoteId || !notesQuill) return;
                
                const noteIndex = notes.findIndex(n => n.id === currentNoteId);
                if (noteIndex === -1) return;
                
                const titel = document.getElementById('noteTitleInput').value || 'Naamloos';
                const inhoud = notesQuill.root.innerHTML;
                
                const result = await apiCall('save_note', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({
                        notitie_id: currentNoteId,
                        titel: titel,
                        inhoud: inhoud
                    })
                });
                
                if (result && result.success) {
                    // Update local cache
                    notes[noteIndex].title = titel;
                    notes[noteIndex].content = inhoud;
                    notes[noteIndex].updated = new Date().toISOString();
                    
                    renderNotesList();
                    
                    // Show save status
                    const statusEl = document.getElementById('noteSaveStatus');
                    statusEl.textContent = 'Opgeslagen';
                    setTimeout(() => {
                        statusEl.textContent = '';
                    }, 2000);
                }
            }
            window.saveCurrentNote = saveCurrentNote;
            
            // Delete current note from database
            async function deleteCurrentNote() {
                if (!currentNoteId) return;
                
                if (!confirm('Weet je zeker dat je deze notitie wilt verwijderen?')) return;
                
                const result = await apiCall(`delete_note&id=${currentNoteId}`);
                
                if (result && result.success) {
                    notes = notes.filter(n => n.id !== currentNoteId);
                    currentNoteId = null;
                    
                    // Show empty state, hide editor (Bootstrap classes)
                    document.getElementById('emptyNotesState').classList.remove('d-none');
                    const editorContent = document.getElementById('noteEditorContent');
                    editorContent.classList.add('d-none');
                    editorContent.classList.remove('d-flex');
                    
                    renderNotesList();
                    showNotification('Notitie verwijderd');
                }
            }
            window.deleteCurrentNote = deleteCurrentNote;
            
            // Auto-save on typing (debounced)
            let autoSaveTimeout = null;
            function setupAutoSave() {
                if (!notesQuill) return;
                
                notesQuill.on('text-change', () => {
                    if (!currentNoteId) return;
                    
                    // Clear previous timeout
                    if (autoSaveTimeout) {
                        clearTimeout(autoSaveTimeout);
                    }
                    
                    // Show "saving..." status
                    const statusEl = document.getElementById('noteSaveStatus');
                    if (statusEl) statusEl.textContent = 'Opslaan...';
                    
                    // Auto-save after 2 seconds of inactivity
                    autoSaveTimeout = setTimeout(() => {
                        saveCurrentNote();
                    }, 2000);
                });
                
                // Also auto-save on title change
                const titleInput = document.getElementById('noteTitleInput');
                if (titleInput) {
                    titleInput.addEventListener('input', () => {
                        if (!currentNoteId) return;
                        
                        if (autoSaveTimeout) {
                            clearTimeout(autoSaveTimeout);
                        }
                        
                        const statusEl = document.getElementById('noteSaveStatus');
                        if (statusEl) statusEl.textContent = 'Opslaan...';
                        
                        autoSaveTimeout = setTimeout(() => {
                            saveCurrentNote();
                        }, 2000);
                    });
                }
            }
            // ============= END NOTES SYSTEM =============
            
            // Initialize admin
            async function initAdmin() {
                // Load books for editor
                const books = await apiCall('books');
                const bookSelect = document.getElementById('adminBookSelect');
                books.forEach(book => {
                    const option = document.createElement('option');
                    option.value = book.Bijbelboeknaam;
                    option.textContent = book.Bijbelboeknaam;
                    bookSelect.appendChild(option);
                });
                
                // Load books for all verse selectors
                await populateBookSelectors();
                
                // Load profiles
                loadProfiles();
                loadTimelineList();
                loadTimelineGroups(); // Load timeline groups
                loadLocationList();
                loadFormattingList(); // Load formatted verses
                
                // Restore last used group color
                const lastColor = localStorage.getItem('lastGroupColor');
                if (lastColor) {
                    const groupColorEl = document.getElementById('groupColor');
                    if (groupColorEl) groupColorEl.value = lastColor;
                }
                
                // Restore last used timeline event color and group
                const lastEventColor = localStorage.getItem('lastEventColor');
                if (lastEventColor) {
                    const timelineColorEl = document.getElementById('timelineKleur');
                    if (timelineColorEl) timelineColorEl.value = lastEventColor;
                }
                
                const lastEventTextColor = localStorage.getItem('lastEventTextColor');
                if (lastEventTextColor) {
                    const timelineTextColorEl = document.getElementById('timelineTekstKleur');
                    if (timelineTextColorEl) timelineTextColorEl.value = lastEventTextColor;
                }
                
                const lastEventGroup = localStorage.getItem('lastEventGroup');
                if (lastEventGroup) {
                    // Wait a bit for groups to load first
                    setTimeout(() => {
                        const timelineGroupEl = document.getElementById('timelineGroup');
                        if (timelineGroupEl) timelineGroupEl.value = lastEventGroup;
                    }, 200);
                }
                
                // Restore editor settings
                await restoreEditorSettings();
            }
            
            // Restore editor settings from localStorage
            async function restoreEditorSettings() {
                const savedBook = localStorage.getItem('adminBook');
                const savedChapter = localStorage.getItem('adminChapter');
                const savedVerse = localStorage.getItem('adminVerse');
                const savedProfile = localStorage.getItem('adminProfile');
                const savedEditMode = localStorage.getItem('adminEditMode');
                
                // Restore edit mode first
                if (savedEditMode) {
                    currentEditMode = savedEditMode;
                    if (savedEditMode === 'chapter') {
                        document.getElementById('editModeChapter').checked = true;
                        document.getElementById('singleVerseEditor').classList.add('d-none');
                        document.getElementById('chapterEditor').classList.remove('d-none');
                        document.getElementById('verseSelectContainer').classList.add('d-none');
                    } else {
                        document.getElementById('editModeSingle').checked = true;
                    }
                }
                
                // Restore profile
                if (savedProfile) {
                    // Wait for profiles to load
                    setTimeout(() => {
                        const profileSelect = document.getElementById('editorProfileSelect');
                        if (profileSelect) {
                            profileSelect.value = savedProfile;
                        }
                    }, 300);
                }
                
                // Restore book
                if (savedBook) {
                    const bookSelect = document.getElementById('adminBookSelect');
                    bookSelect.value = savedBook;
                    
                    // Load chapters for this book
                    await loadAdminChapters();
                    
                    // Restore chapter
                    if (savedChapter) {
                        const chapterSelect = document.getElementById('adminChapterSelect');
                        chapterSelect.value = savedChapter;
                        
                        // Load verses for this chapter
                        await loadAdminVerses();
                        
                        // Restore verse (only in single mode)
                        if (savedVerse && currentEditMode === 'single') {
                            const verseSelect = document.getElementById('adminVerseSelect');
                            verseSelect.value = savedVerse;
                            
                            // Load the verse after profile is set
                            setTimeout(() => {
                                loadVerse();
                            }, 400);
                        }
                        
                        // Load chapter editor if in chapter mode
                        if (currentEditMode === 'chapter') {
                            setTimeout(() => {
                                loadChapterForEditing();
                            }, 400);
                        }
                    }
                }
                
                console.log('Editor settings restored:', { savedBook, savedChapter, savedVerse, savedProfile, savedEditMode });
            }
            
            // Helper: Populate all book selectors
            async function populateBookSelectors() {
                const books = await apiCall('books');
                const selectors = [
                    'imageBoek', 'editImageBoek',
                    'timelineStartBoek', 'timelineEndBoek'
                ];
                
                selectors.forEach(selectorId => {
                    const select = document.getElementById(selectorId);
                    if (select) {
                        // Keep first option (placeholder)
                        const firstOption = select.options[0];
                        select.innerHTML = '';
                        select.appendChild(firstOption);
                        
                        books.forEach(book => {
                            const option = document.createElement('option');
                            option.value = book.Bijbelboeknaam;
                            option.textContent = book.Bijbelboeknaam;
                            select.appendChild(option);
                        });
                    }
                });
            }
            
            // Helper: Setup verse selector trio
            function setupVerseSelector(bookId, hoofdstukId, versId) {
                const bookSelect = document.getElementById(bookId);
                const hoofdstukSelect = document.getElementById(hoofdstukId);
                const versSelect = document.getElementById(versId);
                
                // Skip if elements don't exist
                if (!bookSelect || !hoofdstukSelect || !versSelect) {
                    console.log(`Verse selector elements not found: ${bookId}, ${hoofdstukId}, ${versId}`);
                    return;
                }
                
                bookSelect.addEventListener('change', async () => {
                    const boek = bookSelect.value;
                    hoofdstukSelect.innerHTML = '<option value="">Hoofdstuk</option>';
                    versSelect.innerHTML = '<option value="">Vers</option>';
                    
                    if (boek) {
                        const chapters = await apiCall(`chapters&boek=${encodeURIComponent(boek)}`);
                        chapters.forEach(ch => {
                            const option = document.createElement('option');
                            option.value = ch.Hoofdstuknummer;
                            option.textContent = ch.Hoofdstuknummer;
                            hoofdstukSelect.appendChild(option);
                        });
                    }
                });
                
                hoofdstukSelect.addEventListener('change', async () => {
                    const boek = bookSelect.value;
                    const hoofdstuk = hoofdstukSelect.value;
                    versSelect.innerHTML = '<option value="">Vers</option>';
                    
                    if (boek && hoofdstuk) {
                        const verses = await apiCall(`verses&boek=${encodeURIComponent(boek)}&hoofdstuk=${hoofdstuk}&limit=999`);
                        verses.forEach(v => {
                            const option = document.createElement('option');
                            option.value = v.Versnummer;
                            option.textContent = v.Versnummer;
                            versSelect.appendChild(option);
                        });
                    }
                });
            }
            
            // Helper: Get vers_id from boek/hoofdstuk/vers
            async function getVersId(boek, hoofdstuk, vers) {
                if (!boek || !hoofdstuk || !vers) return null;
                const result = await apiCall(`get_vers_id&boek=${encodeURIComponent(boek)}&hoofdstuk=${hoofdstuk}&vers=${vers}`);
                return result && result.success ? result.vers_id : null;
            }
            
            // Helper: Get boek/hoofdstuk/vers from vers_id
            async function getVersInfo(versId) {
                if (!versId) return null;
                return await apiCall(`get_vers_info&vers_id=${versId}`);
            }
            
            // Helper: Set verse selector values from vers_id
            async function setVerseSelectorFromId(bookId, hoofdstukId, versId, vers_id) {
                if (!vers_id) return;
                
                const info = await getVersInfo(vers_id);
                if (info) {
                    const bookSelect = document.getElementById(bookId);
                    const hoofdstukSelect = document.getElementById(hoofdstukId);
                    const versSelect = document.getElementById(versId);
                    
                    // Set book
                    bookSelect.value = info.Bijbelboeknaam;
                    
                    // Load and set hoofdstuk
                    const chapters = await apiCall(`chapters&boek=${encodeURIComponent(info.Bijbelboeknaam)}`);
                    hoofdstukSelect.innerHTML = '<option value="">Hoofdstuk</option>';
                    chapters.forEach(ch => {
                        const option = document.createElement('option');
                        option.value = ch.Hoofdstuknummer;
                        option.textContent = ch.Hoofdstuknummer;
                        hoofdstukSelect.appendChild(option);
                    });
                    hoofdstukSelect.value = info.Hoofdstuknummer;
                    
                    // Load and set vers
                    const verses = await apiCall(`verses&boek=${encodeURIComponent(info.Bijbelboeknaam)}&hoofdstuk=${info.Hoofdstuknummer}&limit=999`);
                    versSelect.innerHTML = '<option value="">Vers</option>';
                    verses.forEach(v => {
                        const option = document.createElement('option');
                        option.value = v.Versnummer;
                        option.textContent = v.Versnummer;
                        versSelect.appendChild(option);
                    });
                    versSelect.value = info.Versnummer;
                }
            }
            
            // Setup all verse selectors on page load
            setTimeout(() => {
                setupVerseSelector('imageBoek', 'imageHoofdstuk', 'imageVers');
                setupVerseSelector('editImageBoek', 'editImageHoofdstuk', 'editImageVers');
                setupVerseSelector('timelineStartBoek', 'timelineStartHoofdstuk', 'timelineStartVers');
                setupVerseSelector('timelineEndBoek', 'timelineEndHoofdstuk', 'timelineEndVers');
            }, 500);
            
            // Show admin section
            function showAdminSection(section) {
                // Hide all sections
                document.querySelectorAll('.admin-section').forEach(s => {
                    s.classList.add('d-none');
                    s.classList.remove('d-block');
                });
                
                // Show selected section
                const selectedSection = document.getElementById('section-' + section);
                if (selectedSection) {
                    selectedSection.classList.remove('d-none');
                    selectedSection.classList.add('d-block');
                }
                
                // Update sidebar active state
                document.querySelectorAll('.admin-sidebar .list-group-item').forEach(btn => {
                    btn.classList.remove('active');
                });
                event.target.classList.add('active');
                
                // Load relevant data when section is opened
                if (section === 'images') {
                    loadImageList();
                } else if (section === 'timeline') {
                    loadTimelineList();
                } else if (section === 'locations') {
                    loadLocationList();
                } else if (section === 'profiles') {
                    loadProfiles();
                } else if (section === 'notes') {
                    // Initialize notes editor if not done yet
                    if (!notesQuill) {
                        initNotesEditor();
                    } else {
                        loadNotes();
                    }
                }
            }
            window.showAdminSection = showAdminSection;
            
            // Load profiles
            async function loadProfiles() {
                const profiles = await apiCall('profiles');
                
                // For editor
                const editorSelect = document.getElementById('editorProfileSelect');
                if (editorSelect) {
                    editorSelect.innerHTML = '';
                    profiles.forEach(profile => {
                        const option = document.createElement('option');
                        option.value = profile.Profiel_ID;
                        option.textContent = profile.Profiel_Naam;
                        editorSelect.appendChild(option);
                    });
                }
                
                // For profile list (let op: profilesList met 's')
                const profileList = document.getElementById('profilesList');
                if (profileList) {
                    if (!profiles || profiles.length === 0) {
                        profileList.innerHTML = '<p class="text-muted fst-italic">Nog geen profielen aangemaakt</p>';
                        return;
                    }
                    
                    profileList.innerHTML = '';
                    profiles.forEach(profile => {
                        const item = document.createElement('div');
                        item.className = 'profile-item d-flex justify-content-between align-items-center p-3 mb-2 bg-light rounded';
                        item.dataset.name = profile.Profiel_Naam.toLowerCase();
                        item.dataset.desc = (profile.Beschrijving || '').toLowerCase();
                        item.innerHTML = `
                            <div>
                                <div class="fw-semibold">${profile.Profiel_Naam}</div>
                                <small class="text-muted">${profile.Beschrijving || 'Geen beschrijving'}</small>
                            </div>
                            <button class="btn btn-outline-danger btn-sm" onclick="deleteProfile(${profile.Profiel_ID})">
                                <i class="bi bi-trash"></i> Verwijder
                            </button>
                        `;
                        profileList.appendChild(item);
                    });
                }
            }
            
            // Create profile
            async function createProfile() {
                const naam = document.getElementById('newProfileName').value;
                const beschrijving = document.getElementById('newProfileDesc').value;
                
                if (!naam) {
                    showNotification('Vul een naam in', true);
                    return;
                }
                
                const result = await apiCall('create_profile', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ naam, beschrijving })
                });
                
                if (result && result.success) {
                    showNotification('Profiel aangemaakt!');
                    document.getElementById('newProfileName').value = '';
                    document.getElementById('newProfileDesc').value = '';
                    loadProfiles();
                }
            }
            window.createProfile = createProfile;
            
            // Delete profile
            async function deleteProfile(id) {
                if (!confirm('Weet je zeker dat je dit profiel wilt verwijderen?')) return;
                
                const result = await apiCall(`delete_profile&id=${id}`, { method: 'GET' });
                
                if (result && result.success) {
                    showNotification('Profiel verwijderd');
                    loadProfiles();
                }
            }
            window.deleteProfile = deleteProfile;
            
            // ============= FILTER FUNCTIONS =============
            
            // Filter profiles
            function filterProfiles() {
                const searchTerm = document.getElementById('profileSearchInput').value.toLowerCase();
                const items = document.querySelectorAll('#profilesList .profile-item');
                
                items.forEach(item => {
                    const name = item.dataset.name || '';
                    const desc = item.dataset.desc || '';
                    const matches = name.includes(searchTerm) || desc.includes(searchTerm);
                    item.style.display = matches ? '' : 'none';
                });
            }
            window.filterProfiles = filterProfiles;
            
            // Filter timeline events
            function filterTimeline() {
                const searchTerm = document.getElementById('timelineSearchInput').value.toLowerCase();
                const groupFilter = document.getElementById('timelineFilterGroup').value;
                const items = document.querySelectorAll('#timelineTableBody .timeline-item');
                
                items.forEach(item => {
                    const title = item.dataset.title || '';
                    const desc = item.dataset.desc || '';
                    const group = item.dataset.group || '';
                    
                    const matchesSearch = title.includes(searchTerm) || desc.includes(searchTerm);
                    const matchesGroup = !groupFilter || group === groupFilter;
                    
                    item.style.display = (matchesSearch && matchesGroup) ? '' : 'none';
                });
            }
            window.filterTimeline = filterTimeline;
            
            // Filter locations
            function filterLocations() {
                const searchTerm = document.getElementById('locationSearchInput').value.toLowerCase();
                const items = document.querySelectorAll('#locationTableBody .location-item');
                
                items.forEach(item => {
                    const name = item.dataset.name || '';
                    const type = item.dataset.type || '';
                    const desc = item.dataset.desc || '';
                    
                    const matches = name.includes(searchTerm) || type.includes(searchTerm) || desc.includes(searchTerm);
                    item.style.display = matches ? '' : 'none';
                });
            }
            window.filterLocations = filterLocations;
            
            // Filter images
            function filterImages() {
                const searchTerm = document.getElementById('imageSearchInput').value.toLowerCase();
                const items = document.querySelectorAll('#imageList > div');
                
                items.forEach(item => {
                    const text = item.textContent.toLowerCase();
                    const matches = text.includes(searchTerm);
                    item.style.display = matches ? '' : 'none';
                });
            }
            window.filterImages = filterImages;
            
            // Helper: Update counter badge on input
            function updateCounter(inputId, visible, total) {
                const input = document.getElementById(inputId);
                if (!input) return;
                
                // Remove existing counter
                const existingCounter = input.parentElement.querySelector('.filter-counter');
                if (existingCounter) existingCounter.remove();
                
                // Add new counter if filtered
                if (visible < total) {
                    const counter = document.createElement('span');
                    counter.className = 'filter-counter';
                    counter.textContent = `${visible} van ${total}`;
                    counter.style.cssText = `
                        position: absolute;
                        right: 0.75rem;
                        top: 50%;
                        transform: translateY(-50%);
                        background: var(--accent-color);
                        color: white;
                        padding: 0.2rem 0.5rem;
                        border-radius: 12px;
                        font-size: 0.75rem;
                        font-weight: 600;
                        pointer-events: none;
                    `;
                    input.parentElement.style.position = 'relative';
                    input.parentElement.appendChild(counter);
                }
            }
            
            // Load verse for editing
            document.getElementById('adminBookSelect').addEventListener('change', async (e) => {
                localStorage.setItem('adminBook', e.target.value);
                localStorage.removeItem('adminChapter');
                localStorage.removeItem('adminVerse');
                await loadAdminChapters();
            });
            
            // Helper function to load chapters for admin
            async function loadAdminChapters() {
                const boek = document.getElementById('adminBookSelect').value;
                const chapters = await apiCall(`chapters&boek=${encodeURIComponent(boek)}`);
                const chapterSelect = document.getElementById('adminChapterSelect');
                chapterSelect.innerHTML = '<option value="">Kies hoofdstuk</option>';
                
                chapters.forEach(ch => {
                    const option = document.createElement('option');
                    option.value = ch.Hoofdstuknummer;
                    option.textContent = ch.Hoofdstuknummer;
                    chapterSelect.appendChild(option);
                });
            }
            window.loadAdminChapters = loadAdminChapters;
            
            // Track current edit mode
            let currentEditMode = localStorage.getItem('adminEditMode') || 'single';
            let chapterEditors = {}; // Store Quill instances for chapter mode
            let chapterVersesData = []; // Store verse data for chapter mode
            
            // Set edit mode (single verse or chapter)
            function setEditMode(mode) {
                currentEditMode = mode;
                localStorage.setItem('adminEditMode', mode);
                const singleEditor = document.getElementById('singleVerseEditor');
                const chapterEditor = document.getElementById('chapterEditor');
                const verseSelectContainer = document.getElementById('verseSelectContainer');
                
                if (mode === 'single') {
                    singleEditor.classList.remove('d-none');
                    chapterEditor.classList.add('d-none');
                    verseSelectContainer.classList.remove('d-none');
                } else {
                    singleEditor.classList.add('d-none');
                    chapterEditor.classList.remove('d-none');
                    verseSelectContainer.classList.add('d-none');
                    // Load chapter if book and chapter are selected
                    const boek = document.getElementById('adminBookSelect').value;
                    const hoofdstuk = document.getElementById('adminChapterSelect').value;
                    if (boek && hoofdstuk) {
                        loadChapterForEditing();
                    }
                }
            }
            window.setEditMode = setEditMode;
            
            document.getElementById('adminChapterSelect').addEventListener('change', async (e) => {
                localStorage.setItem('adminChapter', e.target.value);
                localStorage.removeItem('adminVerse');
                await loadAdminVerses();
                // If in chapter mode, also load the chapter editor
                if (currentEditMode === 'chapter') {
                    loadChapterForEditing();
                }
            });
            
            // Helper function to load verses for admin
            async function loadAdminVerses() {
                const boek = document.getElementById('adminBookSelect').value;
                const hoofdstuk = document.getElementById('adminChapterSelect').value;
                
                if (!boek || !hoofdstuk) return;
                
                const verses = await apiCall(`verses&boek=${encodeURIComponent(boek)}&hoofdstuk=${hoofdstuk}&limit=999`);
                const verseSelect = document.getElementById('adminVerseSelect');
                verseSelect.innerHTML = '<option value="">Kies vers</option>';
                
                verses.forEach(v => {
                    const option = document.createElement('option');
                    option.value = v.Vers_ID;
                    option.textContent = v.Versnummer;
                    verseSelect.appendChild(option);
                });
            }
            window.loadAdminVerses = loadAdminVerses;
            
            document.getElementById('adminVerseSelect').addEventListener('change', (e) => {
                localStorage.setItem('adminVerse', e.target.value);
                loadVerse();
            });
            
            async function loadVerse() {
                // Don't load single verse if in chapter mode
                if (currentEditMode === 'chapter') return;
                
                const versId = document.getElementById('adminVerseSelect').value;
                const profielId = document.getElementById('editorProfileSelect').value;
                
                if (!versId) {
                    return; // Just return, don't show error
                }
                
                console.log(`Loading verse ${versId} with profile ${profielId}`);
                
                const params = `vers_id=${versId}` + (profielId ? `&profiel_id=${profielId}` : '');
                const verse = await apiCall('verse_detail&' + params);
                
                if (verse) {
                    console.log('Verse data:', verse);
                    
                    // Set original text
                    document.getElementById('originalText').textContent = verse.Tekst;
                    
                    // Clear editor first
                    quill.setText('');
                    
                    // Load into Quill editor
                    if (verse.Opgemaakte_Tekst && verse.Opgemaakte_Tekst.trim() !== '') {
                        // Use Quill's dangerouslyPasteHTML for better HTML handling
                        quill.clipboard.dangerouslyPasteHTML(verse.Opgemaakte_Tekst);
                        console.log('Loaded formatted text:', verse.Opgemaakte_Tekst.substring(0, 100) + '...');
                    } else {
                        // Load plain text
                        quill.setText(verse.Tekst);
                        console.log('Loaded plain text');
                    }
                } else {
                    showNotification('Vers niet gevonden', true);
                }
            }
            window.loadVerse = loadVerse;
            
            // Save formatting
            async function saveFormatting() {
                const versId = document.getElementById('adminVerseSelect').value;
                const profielId = document.getElementById('editorProfileSelect').value;
                
                if (!versId || !profielId) {
                    showNotification('Selecteer een vers en profiel', true);
                    return;
                }
                
                const tekst = quill.root.innerHTML;
                
                const result = await apiCall('save_formatting', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ vers_id: versId, profiel_id: profielId, tekst })
                });
                
                if (result && result.success) {
                    showNotification('Opmaak opgeslagen!');
                    loadFormattingList(); // Reload list
                }
            }
            window.saveFormatting = saveFormatting;
            
            // ============= CHAPTER EDITOR FUNCTIONS =============
            
            // Load all verses of a chapter for editing
            async function loadChapterForEditing() {
                const boek = document.getElementById('adminBookSelect').value;
                const hoofdstuk = document.getElementById('adminChapterSelect').value;
                const profielId = document.getElementById('editorProfileSelect').value;
                
                if (!boek || !hoofdstuk) {
                    document.getElementById('chapterVersesContainer').innerHTML = 
                        '<div class="text-muted text-center py-4">Selecteer een boek en hoofdstuk om te beginnen</div>';
                    return;
                }
                
                // Show loading
                document.getElementById('chapterVersesContainer').innerHTML = 
                    '<div class="text-center py-4"><div class="spinner-border spinner-border-sm" role="status"></div> Laden...</div>';
                
                // Destroy existing Quill instances
                Object.values(chapterEditors).forEach(editor => {
                    if (editor && editor.container) {
                        editor.container.innerHTML = '';
                    }
                });
                chapterEditors = {};
                
                // Fetch verses with formatting if profile selected
                const params = profielId ? 
                    `verses&boek=${encodeURIComponent(boek)}&hoofdstuk=${hoofdstuk}&profiel_id=${profielId}&limit=999` :
                    `verses&boek=${encodeURIComponent(boek)}&hoofdstuk=${hoofdstuk}&limit=999`;
                    
                const verses = await apiCall(params);
                chapterVersesData = verses || [];
                
                if (!verses || verses.length === 0) {
                    document.getElementById('chapterVersesContainer').innerHTML = 
                        '<div class="text-muted text-center py-4">Geen verzen gevonden</div>';
                    return;
                }
                
                // Update verse count
                document.getElementById('chapterVerseCount').textContent = verses.length;
                
                // Build HTML for all verses
                const container = document.getElementById('chapterVersesContainer');
                container.innerHTML = '';
                
                for (const verse of verses) {
                    const hasFormatting = verse.Opgemaakte_Tekst && verse.Opgemaakte_Tekst.trim() !== '';
                    
                    const verseItem = document.createElement('div');
                    verseItem.className = 'chapter-verse-item' + (hasFormatting ? ' has-formatting' : '');
                    verseItem.dataset.versId = verse.Vers_ID;
                    verseItem.innerHTML = `
                        <div class="chapter-verse-header">
                            <span class="chapter-verse-number">${verse.Versnummer}</span>
                            <span class="chapter-verse-original" title="${verse.Tekst}">${verse.Tekst}</span>
                            <span class="chapter-verse-status badge ${hasFormatting ? 'bg-success' : 'bg-secondary'}">${hasFormatting ? 'Bewerkt' : 'Origineel'}</span>
                        </div>
                        <div class="chapter-verse-editor">
                            <div id="chapter-editor-${verse.Vers_ID}"></div>
                        </div>
                    `;
                    container.appendChild(verseItem);
                    
                    // Initialize Quill for this verse
                    const editorId = `chapter-editor-${verse.Vers_ID}`;
                    const Font = Quill.import('formats/font');
                    const quillInstance = new Quill(`#${editorId}`, {
                        theme: 'snow',
                        modules: {
                            toolbar: [
                                [{ 'font': Font.whitelist }],
                                [{ 'size': ['small', false, 'large', 'huge'] }],
                                ['bold', 'italic', 'underline', 'strike'],
                                [{ 'color': [] }, { 'background': [] }],
                                [{ 'script': 'sub'}, { 'script': 'super' }],
                                ['clean']
                            ]
                        }
                    });
                    
                    // Set content
                    if (hasFormatting) {
                        quillInstance.clipboard.dangerouslyPasteHTML(verse.Opgemaakte_Tekst);
                    } else {
                        quillInstance.setText(verse.Tekst);
                    }
                    
                    // Store original content for change detection
                    quillInstance.originalHtml = quillInstance.root.innerHTML;
                    
                    // Track changes
                    quillInstance.on('text-change', () => {
                        const currentHtml = quillInstance.root.innerHTML;
                        const isModified = currentHtml !== quillInstance.originalHtml;
                        verseItem.classList.toggle('modified', isModified);
                    });
                    
                    chapterEditors[verse.Vers_ID] = quillInstance;
                }
            }
            window.loadChapterForEditing = loadChapterForEditing;
            
            // Also reload chapter when profile changes (if in chapter mode)
            document.getElementById('editorProfileSelect').addEventListener('change', (e) => {
                localStorage.setItem('adminProfile', e.target.value);
                if (currentEditMode === 'chapter') {
                    loadChapterForEditing();
                } else {
                    loadVerse();
                }
            });
            
            // Save all modified verses in chapter
            async function saveAllChapterFormatting() {
                const profielId = document.getElementById('editorProfileSelect').value;
                
                if (!profielId) {
                    showNotification('Selecteer eerst een profiel', true);
                    return;
                }
                
                let savedCount = 0;
                let errorCount = 0;
                
                // Find all modified verses
                for (const [versId, editor] of Object.entries(chapterEditors)) {
                    const currentHtml = editor.root.innerHTML;
                    const isModified = currentHtml !== editor.originalHtml;
                    
                    if (isModified) {
                        const result = await apiCall('save_formatting', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ 
                                vers_id: versId, 
                                profiel_id: profielId, 
                                tekst: currentHtml 
                            })
                        });
                        
                        if (result && result.success) {
                            savedCount++;
                            // Update original to mark as saved
                            editor.originalHtml = currentHtml;
                            // Remove modified class
                            const verseItem = document.querySelector(`.chapter-verse-item[data-vers-id="${versId}"]`);
                            if (verseItem) {
                                verseItem.classList.remove('modified');
                                verseItem.classList.add('has-formatting');
                                const badge = verseItem.querySelector('.chapter-verse-status');
                                if (badge) {
                                    badge.className = 'chapter-verse-status badge bg-success';
                                    badge.textContent = 'Bewerkt';
                                }
                            }
                        } else {
                            errorCount++;
                        }
                    }
                }
                
                if (savedCount > 0) {
                    showNotification(`${savedCount} vers(en) opgeslagen!`);
                    loadFormattingList(); // Refresh the list
                } else if (errorCount > 0) {
                    showNotification(`Fout bij opslaan van ${errorCount} vers(en)`, true);
                } else {
                    showNotification('Geen wijzigingen om op te slaan');
                }
            }
            window.saveAllChapterFormatting = saveAllChapterFormatting;
            
            // Edit a formatted verse - DEFINED BEFORE loadFormattingList so it's available for event listeners
            async function editFormatting(versId, profielId) {
                console.log(`Editing formatted verse: ${versId}, profile: ${profielId}`);
                
                // We need to load the verse first to get book/chapter info
                const verse = await apiCall(`verses&offset=0&limit=1&vers_id=${versId}`);
                if (!verse || verse.length === 0) {
                    showNotification('Vers niet gevonden', true);
                    return;
                }
                
                const v = verse[0];
                
                // Set book
                document.getElementById('adminBookSelect').value = v.Bijbelboeknaam;
                await loadAdminChapters();
                
                // Set chapter
                document.getElementById('adminChapterSelect').value = v.Hoofdstuknummer;
                await loadAdminVerses();
                
                // Set verse
                document.getElementById('adminVerseSelect').value = versId;
                
                // Set profile AFTER all selectors are set
                document.getElementById('editorProfileSelect').value = profielId;
                
                // Load the verse into editor with the profile
                await loadVerse();
                
                // Scroll to editor
                document.querySelector('#section-editor').scrollIntoView({ behavior: 'smooth', block: 'start' });
                
                showNotification('Vers geladen in editor');
            }
            window.editFormatting = editFormatting;
            
            // Delete formatted verse - DEFINED BEFORE loadFormattingList
            async function deleteFormattingItem(versId, profielId) {
                if (!confirm('Weet je zeker dat je deze opmaak wilt verwijderen?')) return;
                
                const result = await apiCall(`delete_formatting&vers_id=${versId}&profiel_id=${profielId}`);
                if (result && result.success) {
                    showNotification('Opmaak verwijderd');
                    loadFormattingList();
                }
            }
            window.deleteFormattingItem = deleteFormattingItem;
            
            // Load all formatted verses
            async function loadFormattingList() {
                const formattedVerses = await apiCall('all_formatting');
                const list = document.getElementById('formattingList');
                const filterProfile = document.getElementById('formattingFilterProfile');
                const filterBook = document.getElementById('formattingFilterBook');
                
                // Skip if elements don't exist (not on this page)
                if (!list) return;
                if (!formattedVerses) return;
                
                // Update filter dropdowns (if they exist)
                const profiles = new Set();
                const books = new Set();
                formattedVerses.forEach(item => {
                    profiles.add(item.Profiel_Naam);
                    books.add(item.Bijbelboeknaam);
                });
                
                if (filterProfile) {
                    filterProfile.innerHTML = '<option value="">Alle profielen</option>';
                    [...profiles].sort().forEach(profile => {
                        const option = document.createElement('option');
                        option.value = profile;
                        option.textContent = profile;
                        filterProfile.appendChild(option);
                    });
                }
                
                if (filterBook) {
                    filterBook.innerHTML = '<option value="">Alle boeken</option>';
                    [...books].sort().forEach(book => {
                        const option = document.createElement('option');
                        option.value = book;
                        option.textContent = book;
                        filterBook.appendChild(option);
                    });
                }
                
                // Restore saved filter values
                const savedProfileFilter = localStorage.getItem('formattingFilterProfile');
                const savedBookFilter = localStorage.getItem('formattingFilterBook');
                
                if (savedProfileFilter && filterProfile) {
                    filterProfile.value = savedProfileFilter;
                }
                if (savedBookFilter && filterBook) {
                    filterBook.value = savedBookFilter;
                }
                
                // Build list
                list.innerHTML = '';
                if (formattedVerses.length === 0) {
                    list.innerHTML = '<div class="text-center text-muted py-4">Nog geen bewerkte verzen</div>';
                    return;
                }
                
                formattedVerses.forEach(item => {
                    const div = document.createElement('div');
                    div.className = 'formatting-item p-2 mb-2 border rounded';
                    div.style.cursor = 'pointer';
                    div.setAttribute('data-profile', item.Profiel_Naam);
                    div.setAttribute('data-book', item.Bijbelboeknaam);
                    
                    // Strip HTML for preview
                    const tempDiv = document.createElement('div');
                    tempDiv.innerHTML = item.Opgemaakte_Tekst;
                    const preview = tempDiv.textContent.substring(0, 60) + (tempDiv.textContent.length > 60 ? '...' : '');
                    
                    div.innerHTML = `
                        <div class="d-flex justify-content-between align-items-start">
                            <div class="flex-grow-1">
                                <div class="fw-bold small">
                                    ${item.Bijbelboeknaam} ${item.Hoofdstuknummer}:${item.Versnummer}
                                    <span class="badge bg-primary ms-1">${item.Profiel_Naam}</span>
                                </div>
                                <div class="text-muted small text-truncate" style="max-width: 250px;">${preview}</div>
                            </div>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary btn-sm edit-btn" title="Bewerken">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <button class="btn btn-outline-danger btn-sm delete-btn" title="Verwijderen">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    
                    // Hover effect
                    div.addEventListener('mouseenter', () => div.classList.add('bg-light'));
                    div.addEventListener('mouseleave', () => div.classList.remove('bg-light'));
                    
                    // Add event listeners programmatically
                    const editBtn = div.querySelector('.edit-btn');
                    const deleteBtn = div.querySelector('.delete-btn');
                    
                    // Click on content area to edit
                    div.addEventListener('click', (e) => {
                        if (!e.target.closest('button')) {
                            editFormatting(item.Vers_ID, item.Profiel_ID);
                        }
                    });
                    
                    // Edit button
                    editBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        editFormatting(item.Vers_ID, item.Profiel_ID);
                    });
                    
                    // Delete button
                    deleteBtn.addEventListener('click', (e) => {
                        e.stopPropagation();
                        deleteFormattingItem(item.Vers_ID, item.Profiel_ID);
                    });
                    
                    list.appendChild(div);
                });
                
                // Apply saved filters
                if (savedProfileFilter || savedBookFilter) {
                    // Apply filter without saving (just hide/show items)
                    const items = document.querySelectorAll('.formatting-item');
                    items.forEach(item => {
                        const profile = item.getAttribute('data-profile');
                        const book = item.getAttribute('data-book');
                        
                        const matchesProfile = !savedProfileFilter || profile === savedProfileFilter;
                        const matchesBook = !savedBookFilter || book === savedBookFilter;
                        
                        item.style.display = (matchesProfile && matchesBook) ? '' : 'none';
                    });
                }
            }
            window.loadFormattingList = loadFormattingList;
            
            // Delete current formatting
            async function deleteFormatting() {
                const versId = document.getElementById('adminVerseSelect').value;
                const profielId = document.getElementById('editorProfileSelect').value;
                
                if (!versId || !profielId) {
                    showNotification('Selecteer een vers en profiel', true);
                    return;
                }
                
                if (!confirm('Weet je zeker dat je deze opmaak wilt verwijderen?')) return;
                
                const result = await apiCall(`delete_formatting&vers_id=${versId}&profiel_id=${profielId}`);
                if (result && result.success) {
                    showNotification('Opmaak verwijderd');
                    quill.setContents([]);
                    loadFormattingList();
                }
            }
            window.deleteFormatting = deleteFormatting;
            
            // Reset formatting to original text
            function resetFormatting() {
                const originalText = document.getElementById('originalText').textContent;
                if (originalText) {
                    quill.setText(originalText);
                    showNotification('Tekst gereset naar origineel');
                } else {
                    showNotification('Geen originele tekst beschikbaar', true);
                }
            }
            window.resetFormatting = resetFormatting;
            
            // Filter formatting list
            function filterFormattingList() {
                const profileFilter = document.getElementById('formattingFilterProfile').value;
                const bookFilter = document.getElementById('formattingFilterBook').value;
                
                // Save filter settings
                localStorage.setItem('formattingFilterProfile', profileFilter);
                localStorage.setItem('formattingFilterBook', bookFilter);
                
                const items = document.querySelectorAll('.formatting-item');
                
                items.forEach(item => {
                    const profile = item.getAttribute('data-profile');
                    const book = item.getAttribute('data-book');
                    
                    const matchesProfile = !profileFilter || profile === profileFilter;
                    const matchesBook = !bookFilter || book === bookFilter;
                    
                    item.style.display = (matchesProfile && matchesBook) ? '' : 'none';
                });
            }
            window.filterFormattingList = filterFormattingList;
            
            // Timeline functions
            async function loadTimelineList() {
                const events = await apiCall('timeline');
                const list = document.getElementById('timelineList');
                list.innerHTML = '';
                
                if (!events || events.length === 0) {
                    list.innerHTML = '<p class="text-muted fst-italic">Nog geen timeline events</p>';
                    return;
                }
                
                const table = document.createElement('table');
                table.className = 'table table-hover table-sm';
                table.innerHTML = `
                    <thead>
                        <tr>
                            <th>Titel</th>
                            <th>Groep</th>
                            <th>Datum</th>
                            <th>Vers</th>
                            <th style="width: 120px;">Acties</th>
                        </tr>
                    </thead>
                    <tbody id="timelineTableBody"></tbody>
                `;
                list.appendChild(table);
                
                const tbody = document.getElementById('timelineTableBody');
                
                events.forEach(event => {
                    const tr = document.createElement('tr');
                    tr.className = 'timeline-item';
                    tr.dataset.title = (event.Titel || '').toLowerCase();
                    tr.dataset.desc = (event.Beschrijving || '').toLowerCase();
                    tr.dataset.group = event.Group_ID || '';
                    
                    const groupBadge = event.Groep_Naam ? 
                        `<span class="badge" style="background: ${event.Groep_Kleur};">${event.Groep_Naam}</span>` : 
                        '<span class="text-muted">-</span>';
                    
                    let verseRef = '-';
                    if (event.Start_Boek) {
                        verseRef = `${event.Start_Boek} ${event.Start_Hoofdstuk}:${event.Start_Vers}`;
                        if (event.End_Boek) {
                            verseRef += ` - ${event.End_Boek} ${event.End_Hoofdstuk}:${event.End_Vers}`;
                        }
                    }
                    
                    let dateDisplay = '-';
                    if (event.Start_Datum) {
                        dateDisplay = event.Start_Datum;
                        if (event.End_Datum && event.End_Datum !== event.Start_Datum) {
                            dateDisplay += ' tot ' + event.End_Datum;
                        }
                    }
                    
                    const dateWarning = !event.Start_Datum ? 
                        '<i class="bi bi-exclamation-triangle text-warning" title="Geen datum"></i> ' : '';
                    
                    tr.innerHTML = `
                        <td>${dateWarning}<strong>${event.Titel}</strong><br><small class="text-muted">${event.Beschrijving || ''}</small></td>
                        <td>${groupBadge}</td>
                        <td><small>${dateDisplay}</small></td>
                        <td><small>${verseRef}</small></td>
                        <td>
                            <button class="btn btn-outline-primary btn-sm" onclick="editTimeline(${event.Event_ID})" title="Bewerk">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-sm" onclick="deleteTimeline(${event.Event_ID})" title="Verwijder">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }
            
            async function saveTimeline() {
                // Get start vers_id
                const startBoek = document.getElementById('timelineStartBoek').value;
                const startHoofdstuk = document.getElementById('timelineStartHoofdstuk').value;
                const startVers = document.getElementById('timelineStartVers').value;
                let vers_id_start = null;
                if (startBoek && startHoofdstuk && startVers) {
                    vers_id_start = await getVersId(startBoek, startHoofdstuk, startVers);
                }
                
                // Get end vers_id
                const endBoek = document.getElementById('timelineEndBoek').value;
                const endHoofdstuk = document.getElementById('timelineEndHoofdstuk').value;
                const endVers = document.getElementById('timelineEndVers').value;
                let vers_id_end = null;
                if (endBoek && endHoofdstuk && endVers) {
                    vers_id_end = await getVersId(endBoek, endHoofdstuk, endVers);
                }
                
                const group_id = document.getElementById('timelineGroup').value || null;
                const start_datum = document.getElementById('timelineStartDatum').value;
                const end_datum = document.getElementById('timelineEndDatum').value;
                const kleur = document.getElementById('timelineKleur').value;
                const tekst_kleur = document.getElementById('timelineTekstKleur').value || null;
                
                // Save color, text color and group to localStorage for next event
                localStorage.setItem('lastEventColor', kleur);
                if (tekst_kleur) {
                    localStorage.setItem('lastEventTextColor', tekst_kleur);
                }
                if (group_id) {
                    localStorage.setItem('lastEventGroup', group_id);
                }
                
                // Validatie
                if (!document.getElementById('timelineTitel').value) {
                    showNotification('Vul een titel in', true);
                    return;
                }
                
                if (!start_datum) {
                    showNotification('Start datum is verplicht voor zichtbaarheid in timeline', true);
                    return;
                }
                
                // Automatically determine type based on end date
                const type = end_datum ? 'range' : 'point';
                
                const data = {
                    event_id: document.getElementById('timelineEventId').value || null,
                    titel: document.getElementById('timelineTitel').value,
                    beschrijving: timelineDescQuill ? timelineDescQuill.root.innerHTML : '',
                    start_datum: start_datum,
                    end_datum: end_datum,
                    vers_id_start: vers_id_start,
                    vers_id_end: vers_id_end,
                    type: type,
                    kleur: kleur,
                    tekst_kleur: tekst_kleur,
                    group_id: group_id
                };
                
                const result = await apiCall('save_timeline', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                if (result && result.success) {
                    showNotification('Timeline event opgeslagen!');
                    clearTimelineForm();
                    loadTimelineList();
                }
            }
            window.saveTimeline = saveTimeline;
            
            // Clear text color (for auto-contrast button)
            function clearTextColor() {
                const bgColor = document.getElementById('timelineKleur').value;
                const autoColor = getContrastColor(bgColor);
                document.getElementById('timelineTekstKleur').value = autoColor;
                showNotification('Tekstkleur aangepast naar automatisch contrast');
            }
            window.clearTextColor = clearTextColor;
            
            function clearTimelineForm() {
                document.getElementById('timelineEventId').value = '';
                document.getElementById('timelineTitel').value = '';
                if (timelineDescQuill) {
                    timelineDescQuill.setContents([]);
                }
                document.getElementById('timelineStartDatum').value = '';
                document.getElementById('timelineEndDatum').value = '';
                document.getElementById('timelineTekstKleur').value = '#ffffff';
                
                // Don't reset group and color - keep last used values
                // document.getElementById('timelineGroup').value = '';
                // document.getElementById('timelineKleur').value = '#4A90E2';
                
                document.getElementById('timelineStartBoek').value = '';
                document.getElementById('timelineStartHoofdstuk').innerHTML = '<option value="">Hoofdstuk</option>';
                document.getElementById('timelineStartVers').innerHTML = '<option value="">Vers</option>';
                
                document.getElementById('timelineEndBoek').value = '';
                document.getElementById('timelineEndHoofdstuk').innerHTML = '<option value="">Hoofdstuk</option>';
                document.getElementById('timelineEndVers').innerHTML = '<option value="">Vers</option>';
            }
            window.clearTimelineForm = clearTimelineForm;
            
            async function deleteTimeline(id) {
                if (!confirm('Weet je zeker dat je dit event wilt verwijderen?')) return;
                
                const result = await apiCall(`delete_timeline&id=${id}`);
                if (result && result.success) {
                    showNotification('Timeline event verwijderd');
                    loadTimelineList();
                }
            }
            window.deleteTimeline = deleteTimeline;
            
            // Timeline Groups functies
            
            async function createTimelineGroup() {
                const naam = document.getElementById('groupName').value;
                const kleur = document.getElementById('groupColor').value;
                
                if (!naam) {
                    showNotification('Vul een groep naam in', true);
                    return;
                }
                
                // Save color to localStorage
                localStorage.setItem('lastGroupColor', kleur);
                
                const result = await apiCall('create_timeline_group', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        naam, 
                        kleur,
                        zichtbaar: 1,
                        volgorde: 0
                    })
                });
                
                if (result && result.success) {
                    showNotification('Groep aangemaakt!');
                    document.getElementById('groupName').value = '';
                    // Keep the same color for next group (don't reset)
                    await loadTimelineGroups();
                    // Reload timeline to show new group
                    if (mode === 'reader') {
                        loadTimelineEvents();
                    }
                }
            }
            window.createTimelineGroup = createTimelineGroup;
            
            // Edit timeline group
            function editTimelineGroup(groupId, naam, kleur) {
                document.getElementById('editGroupId').value = groupId;
                document.getElementById('editGroupName').value = naam;
                document.getElementById('editGroupColor').value = kleur;
                
                // Open Bootstrap modal
                const modal = new bootstrap.Modal(document.getElementById('groupEditModal'));
                modal.show();
            }
            window.editTimelineGroup = editTimelineGroup;
            
            // Update timeline group
            async function updateTimelineGroup() {
                const groupId = document.getElementById('editGroupId').value;
                const naam = document.getElementById('editGroupName').value;
                const kleur = document.getElementById('editGroupColor').value;
                
                if (!naam) {
                    showNotification('Vul een groep naam in', true);
                    return;
                }
                
                const result = await apiCall('update_timeline_group', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ 
                        group_id: groupId,
                        naam, 
                        kleur,
                        beschrijving: '',
                        zichtbaar: 1,
                        volgorde: 0
                    })
                });
                
                if (result && result.success) {
                    showNotification('Groep bijgewerkt!');
                    
                    // Close Bootstrap modal
                    const modal = bootstrap.Modal.getInstance(document.getElementById('groupEditModal'));
                    if (modal) modal.hide();
                    
                    await loadTimelineGroups();
                    // Reload timeline to show updated group
                    if (mode === 'reader') {
                        loadTimelineEvents();
                    }
                }
            }
            window.updateTimelineGroup = updateTimelineGroup;
            
            // Close edit modal - deprecated, keeping for compatibility
            function closeGroupEditModal() {
                const modal = bootstrap.Modal.getInstance(document.getElementById('groupEditModal'));
                if (modal) modal.hide();
            }
            window.closeGroupEditModal = closeGroupEditModal;
            
            async function deleteTimelineGroup(groupId) {
                if (!confirm('Weet je zeker dat je deze groep wilt verwijderen? Events in deze groep blijven bestaan.')) return;
                
                const result = await apiCall(`delete_timeline_group&id=${groupId}`);
                if (result && result.success) {
                    showNotification('Groep verwijderd');
                    await loadTimelineGroups();
                    if (mode === 'reader') {
                        loadTimelineEvents();
                    }
                }
            }
            window.deleteTimelineGroup = deleteTimelineGroup;
            
            async function editTimeline(id) {
                const event = await apiCall(`get_timeline&id=${id}`);
                if (event) {
                    document.getElementById('timelineEventId').value = event.Event_ID;
                    document.getElementById('timelineTitel').value = event.Titel;
                    
                    // Load description into Quill editor
                    if (timelineDescQuill) {
                        if (event.Beschrijving && event.Beschrijving.trim() !== '') {
                            timelineDescQuill.clipboard.dangerouslyPasteHTML(event.Beschrijving);
                        } else {
                            timelineDescQuill.setContents([]);
                        }
                    }
                    
                    document.getElementById('timelineStartDatum').value = event.Start_Datum || '';
                    document.getElementById('timelineEndDatum').value = event.End_Datum || '';
                    document.getElementById('timelineKleur').value = event.Kleur || '#4A90E2';
                    document.getElementById('timelineTekstKleur').value = event.Tekst_Kleur || getContrastColor(event.Kleur || '#4A90E2');
                    document.getElementById('timelineGroup').value = event.Group_ID || '';
                    
                    // Set verse selectors
                    if (event.Vers_ID_Start) {
                        await setVerseSelectorFromId('timelineStartBoek', 'timelineStartHoofdstuk', 'timelineStartVers', event.Vers_ID_Start);
                    }
                    if (event.Vers_ID_End) {
                        await setVerseSelectorFromId('timelineEndBoek', 'timelineEndHoofdstuk', 'timelineEndVers', event.Vers_ID_End);
                    }
                    
                    showNotification('Event geladen voor bewerking');
                    
                    // Scroll naar het formulier
                    document.getElementById('section-timeline').scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
            window.editTimeline = editTimeline;
            
            // Location functions
            async function loadLocationList() {
                const locations = await apiCall('locations');
                const list = document.getElementById('locationList');
                list.innerHTML = '';
                
                if (!locations || locations.length === 0) {
                    list.innerHTML = '<p class="text-muted fst-italic">Nog geen locaties</p>';
                    return;
                }
                
                const table = document.createElement('table');
                table.className = 'table table-hover table-sm';
                table.innerHTML = `
                    <thead>
                        <tr>
                            <th>Naam</th>
                            <th>Type</th>
                            <th>CoÃƒÆ’Ã‚Â¶rdinaten</th>
                            <th>Beschrijving</th>
                            <th style="width: 120px;">Acties</th>
                        </tr>
                    </thead>
                    <tbody id="locationTableBody"></tbody>
                `;
                list.appendChild(table);
                
                const tbody = document.getElementById('locationTableBody');
                
                locations.forEach(loc => {
                    const tr = document.createElement('tr');
                    tr.className = 'location-item';
                    tr.dataset.name = (loc.Naam || '').toLowerCase();
                    tr.dataset.type = (loc.Type || '').toLowerCase();
                    tr.dataset.desc = (loc.Beschrijving || '').toLowerCase();
                    
                    const typeIcons = {
                        'stad': 'bi-building',
                        'berg': 'bi-triangle',
                        'rivier': 'bi-water',
                        'zee': 'bi-tsunami',
                        'regio': 'bi-map',
                        'overig': 'bi-geo-alt'
                    };
                    const icon = typeIcons[loc.Type] || 'bi-geo-alt';
                    
                    tr.innerHTML = `
                        <td><strong>${loc.Naam}</strong></td>
                        <td><i class="bi ${icon}"></i> ${loc.Type}</td>
                        <td><small>${loc.Latitude}, ${loc.Longitude}</small></td>
                        <td><small class="text-muted">${loc.Beschrijving || '-'}</small></td>
                        <td>
                            <button class="btn btn-outline-primary btn-sm" onclick="editLocation(${loc.Locatie_ID})" title="Bewerk">
                                <i class="bi bi-pencil"></i>
                            </button>
                            <button class="btn btn-outline-danger btn-sm" onclick="deleteLocation(${loc.Locatie_ID})" title="Verwijder">
                                <i class="bi bi-trash"></i>
                            </button>
                        </td>
                    `;
                    tbody.appendChild(tr);
                });
            }
            
            async function deleteLocation(id) {
                if (!confirm('Weet je zeker dat je deze locatie wilt verwijderen?')) return;
                
                const result = await apiCall(`delete_location&id=${id}`);
                if (result && result.success) {
                    showNotification('Locatie verwijderd');
                    loadLocationList();
                }
            }
            window.deleteLocation = deleteLocation;
            
            async function saveLocation() {
                const data = {
                    locatie_id: document.getElementById('locationId').value || null,
                    naam: document.getElementById('locationName').value,
                    latitude: document.getElementById('locationLat').value,
                    longitude: document.getElementById('locationLng').value,
                    beschrijving: document.getElementById('locationDesc').value,
                    type: document.getElementById('locationType').value,
                    icon: document.getElementById('locationIcon').value
                };
                
                if (!data.naam || !data.latitude || !data.longitude) {
                    showNotification('Vul naam en coÃƒÆ’Ã‚Â¶rdinaten in', true);
                    return;
                }
                
                const result = await apiCall('save_location', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                if (result && result.success) {
                    showNotification('Locatie opgeslagen!');
                    
                    // Als vers is ingevuld, koppel de locatie
                    const boek = document.getElementById('locationBoek').value;
                    const hoofdstuk = document.getElementById('locationHoofdstuk').value;
                    const vers = document.getElementById('locationVers').value;
                    const context = document.getElementById('locationContext').value;
                    
                    if (boek && hoofdstuk && vers) {
                        const vers_id = await getVersId(boek, hoofdstuk, vers);
                        if (vers_id) {
                            await apiCall('link_verse_location', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify({
                                    vers_id: vers_id,
                                    locatie_id: data.locatie_id || result.id,
                                    context: context
                                })
                            });
                        }
                    }
                    
                    clearLocationForm();
                    loadLocationList();
                }
            }
            window.saveLocation = saveLocation;
            
            function clearLocationForm() {
                document.getElementById('locationId').value = '';
                document.getElementById('locationName').value = '';
                document.getElementById('locationLat').value = '';
                document.getElementById('locationLng').value = '';
                document.getElementById('locationDesc').value = '';
                document.getElementById('locationType').value = 'city';
                document.getElementById('locationIcon').value = 'marker';
                
                document.getElementById('locationBoek').value = '';
                document.getElementById('locationHoofdstuk').innerHTML = '<option value="">Hoofdstuk</option>';
                document.getElementById('locationVers').innerHTML = '<option value="">Vers</option>';
                document.getElementById('locationContext').value = '';
            }
            window.clearLocationForm = clearLocationForm;
            
            async function editLocation(id) {
                const locatie = await apiCall(`get_location&id=${id}`);
                if (locatie) {
                    document.getElementById('locationId').value = locatie.Locatie_ID;
                    document.getElementById('locationName').value = locatie.Naam;
                    document.getElementById('locationLat').value = locatie.Latitude;
                    document.getElementById('locationLng').value = locatie.Longitude;
                    document.getElementById('locationDesc').value = locatie.Beschrijving || '';
                    document.getElementById('locationType').value = locatie.Type || 'city';
                    document.getElementById('locationIcon').value = locatie.Icon || 'marker';
                    
                    showNotification('Locatie geladen voor bewerking');
                    
                    // Scroll naar het formulier
                    document.getElementById('section-locations').scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            }
            window.editLocation = editLocation;
            
            // Image upload
            async function uploadImage() {
                const fileInput = document.getElementById('imageFile');
                const file = fileInput.files[0];
                
                if (!file) {
                    showNotification('Selecteer een afbeelding', true);
                    return;
                }
                
                const boek = document.getElementById('imageBoek').value;
                const hoofdstuk = document.getElementById('imageHoofdstuk').value;
                const vers = document.getElementById('imageVers').value;
                
                if (!boek || !hoofdstuk || !vers) {
                    showNotification('Selecteer een vers voor de afbeelding', true);
                    return;
                }
                
                const vers_id = await getVersId(boek, hoofdstuk, vers);
                if (!vers_id) {
                    showNotification('Vers niet gevonden', true);
                    return;
                }
                
                const formData = new FormData();
                formData.append('image', file);
                formData.append('vers_id', vers_id);
                formData.append('caption', document.getElementById('imageCaption').value || '');
                formData.append('uitlijning', document.getElementById('imageUitlijning').value);
                formData.append('breedte', document.getElementById('imageBreedte').value);
                formData.append('hoogte', document.getElementById('imageHoogte').value || '');
                
                const response = await fetch('?api=upload_image', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification('Afbeelding geÃƒÆ’Ã‚Â¼pload!');
                    
                    // Reset form
                    fileInput.value = '';
                    document.getElementById('imageBoek').value = '';
                    document.getElementById('imageHoofdstuk').innerHTML = '<option value="">Hoofdstuk</option>';
                    document.getElementById('imageVers').innerHTML = '<option value="">Vers</option>';
                    document.getElementById('imageCaption').value = '';
                    document.getElementById('imageUitlijning').value = 'center';
                    document.getElementById('imageBreedte').value = '400';
                    document.getElementById('imageHoogte').value = '';
                    
                    // Reload image list
                    loadImageList();
                } else {
                    showNotification('Upload mislukt: ' + result.error, true);
                }
            }
            window.uploadImage = uploadImage;
            
            // Load image list
            async function loadImageList() {
                const images = await apiCall('all_images');
                const list = document.getElementById('imageList');
                list.innerHTML = '';
                
                if (!images || images.length === 0) {
                    list.innerHTML = '<div class="col-12"><p class="text-muted fst-italic">Nog geen afbeeldingen geÃƒÆ’Ã‚Â¼pload</p></div>';
                    return;
                }
                
                images.forEach(img => {
                    const versInfo = img.Bijbelboeknaam ? 
                        `<span class="text-primary fw-semibold"><i class="bi bi-book"></i> ${img.Bijbelboeknaam} ${img.Hoofdstuknummer}:${img.Versnummer}</span>` : 
                        '<span class="text-muted"><i class="bi bi-exclamation-triangle"></i> Geen vers gekoppeld</span>';
                    
                    const col = document.createElement('div');
                    col.className = 'col-md-6 col-lg-4';
                    col.innerHTML = `
                        <div class="card h-100">
                            <img src="${img.Bestandspad}" class="card-img-top" style="height: 150px; object-fit: cover;">
                            <div class="card-body">
                                <h6 class="card-title text-truncate">${img.Originele_Naam}</h6>
                                <p class="card-text small text-muted mb-2">
                                    ${versInfo}<br>
                                    <i class="bi bi-arrows-angle-expand"></i> ${img.Breedte}px ${img.Hoogte ? 'ÃƒÆ’Ã¢â‚¬â€ ' + img.Hoogte + 'px' : '(auto)'} ÃƒÂ¢Ã¢â€šÂ¬Ã‚Â¢ 
                                    <i class="bi bi-text-${img.Uitlijning === 'left' ? 'left' : img.Uitlijning === 'right' ? 'right' : 'center'}"></i>
                                    ${img.Caption ? '<br><i class="bi bi-chat-quote"></i> ' + img.Caption : ''}
                                </p>
                            </div>
                            <div class="card-footer bg-transparent">
                                <button class="btn btn-sm btn-outline-primary" onclick="editImage(${img.Afbeelding_ID})">
                                    <i class="bi bi-pencil"></i> Bewerk
                                </button>
                                <button class="btn btn-sm btn-outline-danger" onclick="deleteImage(${img.Afbeelding_ID})">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </div>
                    `;
                    
                    list.appendChild(col);
                });
            }
            
            // Edit image
            async function editImage(id) {
                const img = await apiCall(`get_image&id=${id}`);
                if (img) {
                    document.getElementById('editImageId').value = img.Afbeelding_ID;
                    document.getElementById('editImageCaption').value = img.Caption || '';
                    document.getElementById('editImageUitlijning').value = img.Uitlijning || 'center';
                    document.getElementById('editImageBreedte').value = img.Breedte || 400;
                    document.getElementById('editImageHoogte').value = img.Hoogte || '';
                    
                    // Reset vers selectors first
                    document.getElementById('editImageBoek').value = '';
                    document.getElementById('editImageHoofdstuk').innerHTML = '<option value="">Hoofdstuk</option>';
                    document.getElementById('editImageVers').innerHTML = '<option value="">Vers</option>';
                    
                    // Load vers info if available
                    if (img.Vers_ID && img.Bijbelboeknaam) {
                        await setVerseSelectorFromId('editImageBoek', 'editImageHoofdstuk', 'editImageVers', img.Vers_ID);
                    }
                    
                    // Open Bootstrap modal
                    const modal = new bootstrap.Modal(document.getElementById('imageEditModal'));
                    modal.show();
                }
            }
            window.editImage = editImage;
            
            // Update image
            async function updateImage() {
                const boek = document.getElementById('editImageBoek').value;
                const hoofdstuk = document.getElementById('editImageHoofdstuk').value;
                const vers = document.getElementById('editImageVers').value;
                
                let vers_id = null;
                if (boek && hoofdstuk && vers) {
                    vers_id = await getVersId(boek, hoofdstuk, vers);
                    if (!vers_id) {
                        showNotification('Vers niet gevonden', true);
                        return;
                    }
                }
                
                const data = {
                    afbeelding_id: document.getElementById('editImageId').value,
                    vers_id: vers_id,
                    caption: document.getElementById('editImageCaption').value,
                    uitlijning: document.getElementById('editImageUitlijning').value,
                    breedte: document.getElementById('editImageBreedte').value,
                    hoogte: document.getElementById('editImageHoogte').value || null
                };
                
                const result = await apiCall('update_image', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                if (result && result.success) {
                    showNotification('Afbeelding bijgewerkt!');
                    closeImageEditModal();
                    loadImageList();
                }
            }
            window.updateImage = updateImage;
            
            // Close edit modal
            function closeImageEditModal() {
                // Blur any focused element inside modal first
                const modal = document.getElementById('imageEditModal');
                const focusedElement = modal.querySelector(':focus');
                if (focusedElement) focusedElement.blur();
                
                const modalInstance = bootstrap.Modal.getInstance(modal);
                if (modalInstance) modalInstance.hide();
            }
            window.closeImageEditModal = closeImageEditModal;
            
            // Fix aria-hidden issue on page load - ensure modal is properly reset
            document.getElementById('imageEditModal').addEventListener('hidden.bs.modal', function () {
                // Remove aria-hidden when modal is fully hidden
                this.removeAttribute('aria-hidden');
            });
            
            // Delete image
            async function deleteImage(id) {
                if (!confirm('Weet je zeker dat je deze afbeelding wilt verwijderen?')) return;
                
                const result = await apiCall(`delete_image&id=${id}`);
                if (result && result.success) {
                    showNotification('Afbeelding verwijderd');
                    loadImageList();
                }
            }
            window.deleteImage = deleteImage;
            
            // ============= IMPORT/EXPORT FUNCTIONS =============
            
            // Export single table
            async function exportTable(table) {
                window.location.href = `?api=export&table=${table}`;
                showNotification(`${table} wordt geÃƒÆ’Ã‚Â«xporteerd...`);
            }
            window.exportTable = exportTable;
            
            // Export all tables as separate files
            async function exportAll() {
                const tables = ['profiles', 'formatting', 'timeline_events', 'timeline_groups', 'locations', 'verse_locations', 'images'];
                
                showNotification('Alle tabellen worden geÃƒÆ’Ã‚Â«xporteerd...');
                
                // Export each table with a small delay
                for (let i = 0; i < tables.length; i++) {
                    setTimeout(() => {
                        exportTable(tables[i]);
                    }, i * 500); // 500ms delay between downloads
                }
            }
            window.exportAll = exportAll;
            
            // Import CSV
            async function importCSV() {
                const table = document.getElementById('importTableSelect').value;
                const fileInput = document.getElementById('importFile');
                const overwrite = document.getElementById('importOverwrite').checked;
                const statusDiv = document.getElementById('importStatus');
                
                if (!table) {
                    showNotification('Selecteer een tabel om te importeren', true);
                    return;
                }
                
                if (!fileInput.files || fileInput.files.length === 0) {
                    showNotification('Selecteer een CSV bestand', true);
                    return;
                }
                
                const file = fileInput.files[0];
                
                if (!file.name.endsWith('.csv')) {
                    showNotification('Alleen CSV bestanden zijn toegestaan', true);
                    return;
                }
                
                if (!confirm(`Weet je zeker dat je wilt importeren naar "${table}"?\n${overwrite ? 'Bestaande data wordt overschreven!' : 'Duplicaten worden overgeslagen.'}`)) {
                    return;
                }
                
                statusDiv.innerHTML = '<div style="color: #2196F3; padding: 1rem; background: #e3f2fd; border-radius: 6px;"><i class="bi bi-upload"></i> Bezig met importeren...</div>';
                
                const formData = new FormData();
                formData.append('file', file);
                formData.append('table', table);
                formData.append('overwrite', overwrite);
                
                try {
                    const response = await fetch('?api=import', {
                        method: 'POST',
                        body: formData
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        let message = `Succesvol geimporteerd: ${result.imported} rijen`;
                        if (result.errors && result.errors.length > 0) {
                            message += `\nWaarschuwingen: ${result.errors.length}`;
                        }
                        
                        statusDiv.innerHTML = `
                            <div style="color: #4CAF50; padding: 1rem; background: #e8f5e9; border-radius: 6px;">
                                <strong><i class="bi bi-check-circle"></i> Import Succesvol!</strong><br>
                                ${result.imported} rijen geimporteerd
                                ${result.errors.length > 0 ? `<br><small style="color: #ff9800;"><i class="bi bi-exclamation-triangle"></i> ${result.errors.length} waarschuwingen</small>` : ''}
                            </div>
                        `;
                        
                        showNotification(`${result.imported} rijen geimporteerd!`);
                        
                        // Reload relevant lists
                        switch(table) {
                            case 'profiles':
                                loadProfiles();
                                break;
                            case 'formatting':
                                loadFormattingList();
                                break;
                            case 'timeline_events':
                                loadTimelineList();
                                break;
                            case 'timeline_groups':
                                loadTimelineGroups();
                                break;
                            case 'locations':
                                loadLocationList();
                                break;
                            case 'images':
                                loadImageList();
                                break;
                        }
                        
                        // Clear form
                        fileInput.value = '';
                        document.getElementById('importTableSelect').value = '';
                        
                    } else {
                        statusDiv.innerHTML = `
                            <div style="color: #f44336; padding: 1rem; background: #ffebee; border-radius: 6px;">
                                <strong>ÃƒÂ¢Ã‚ÂÃ…â€™ Import Mislukt</strong><br>
                                ${result.error}
                            </div>
                        `;
                        showNotification('Import mislukt!', true);
                    }
                } catch (error) {
                    statusDiv.innerHTML = `
                        <div style="color: #f44336; padding: 1rem; background: #ffebee; border-radius: 6px;">
                            <strong>ÃƒÂ¢Ã‚ÂÃ…â€™ Fout</strong><br>
                            ${error.message}
                        </div>
                    `;
                    showNotification('Er is een fout opgetreden', true);
                }
            }
            window.importCSV = importCSV;
            
            // Initialize admin
            initAdmin();
        }
    </script>
</body>
</html>