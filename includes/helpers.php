<?php
/**
 * Helper Functions
 * 
 * Algemene helper functies voor de applicatie
 */

/**
 * Return JSON response
 */
function jsonResponse($data, $statusCode = 200) {
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Return error JSON response
 */
function jsonError($message, $statusCode = 400) {
    jsonResponse(['error' => $message], $statusCode);
}

/**
 * Return success JSON response
 */
function jsonSuccess($data = [], $message = null) {
    $response = ['success' => true];
    if ($message) {
        $response['message'] = $message;
    }
    if (!empty($data)) {
        $response = array_merge($response, $data);
    }
    jsonResponse($response);
}

/**
 * Sanitize input
 */
function sanitize($input) {
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return htmlspecialchars(strip_tags($input), ENT_QUOTES, 'UTF-8');
}

/**
 * Get POST data as JSON
 */
function getJsonInput() {
    $input = file_get_contents('php://input');
    return json_decode($input, true);
}

/**
 * Validate required fields
 */
function validateRequired($data, $fields) {
    $missing = [];
    foreach ($fields as $field) {
        if (!isset($data[$field]) || empty($data[$field])) {
            $missing[] = $field;
        }
    }
    
    if (!empty($missing)) {
        jsonError('Verplichte velden ontbreken: ' . implode(', ', $missing));
    }
}

/**
 * Escape HTML maar behoud formatting voor display
 */
function escapeHtml($text) {
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

/**
 * Format date voor display
 */
function formatDate($date, $format = 'd-m-Y H:i') {
    if (empty($date)) {
        return '-';
    }
    
    try {
        $dt = new DateTime($date);
        return $dt->format($format);
    } catch (Exception $e) {
        return $date;
    }
}

/**
 * Debug log (alleen in development)
 */
function debugLog($message, $data = null) {
    if (defined('DEBUG') && DEBUG) {
        error_log('[DEBUG] ' . $message);
        if ($data !== null) {
            error_log(print_r($data, true));
        }
    }
}

/**
 * Controleer of bestand een toegestane afbeelding is
 */
function isValidImage($file) {
    if (!isset($file['type']) || !in_array($file['type'], ALLOWED_IMAGE_TYPES)) {
        return false;
    }
    
    if ($file['size'] > MAX_UPLOAD_SIZE) {
        return false;
    }
    
    // Extra check met getimagesize
    $imageInfo = @getimagesize($file['tmp_name']);
    if ($imageInfo === false) {
        return false;
    }
    
    return true;
}

/**
 * Genereer veilige bestandsnaam
 */
function generateSafeFilename($originalName) {
    $extension = pathinfo($originalName, PATHINFO_EXTENSION);
    $basename = pathinfo($originalName, PATHINFO_FILENAME);
    
    // Verwijder onveilige karakters
    $basename = preg_replace('/[^a-zA-Z0-9._-]/', '', $basename);
    
    // Voeg timestamp toe voor uniciteit
    return time() . '_' . substr($basename, 0, 50) . '.' . $extension;
}

/**
 * Include view template
 */
function view($template, $data = []) {
    extract($data);
    $templatePath = VIEWS_PATH . '/' . $template . '.php';
    
    if (!file_exists($templatePath)) {
        die("Template niet gevonden: $template");
    }
    
    require $templatePath;
}

/**
 * Bereken contrast kleur voor tekst op achtergrond
 */
function getContrastColor($hexColor) {
    // Verwijder #
    $hex = str_replace('#', '', $hexColor);
    
    // Converteer naar RGB
    $r = hexdec(substr($hex, 0, 2));
    $g = hexdec(substr($hex, 2, 2));
    $b = hexdec(substr($hex, 4, 2));
    
    // Bereken luminantie
    $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
    
    // Return zwart voor lichte achtergronden, wit voor donkere
    return $luminance > 0.5 ? '#000000' : '#ffffff';
}

/**
 * Paginate results
 */
function paginate($total, $page = 1, $perPage = DEFAULT_LIMIT) {
    $totalPages = ceil($total / $perPage);
    $page = max(1, min($page, $totalPages));
    $offset = ($page - 1) * $perPage;
    
    return [
        'total' => $total,
        'per_page' => $perPage,
        'current_page' => $page,
        'total_pages' => $totalPages,
        'offset' => $offset,
        'has_more' => $page < $totalPages
    ];
}
