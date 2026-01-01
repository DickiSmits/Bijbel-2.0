<?php
/**
 * ULTRA SIMPLE TEST - Just return OK
 */

echo json_encode([
    'test' => 'OK',
    'message' => 'File loaded successfully!',
    'endpoint' => isset($_GET['api']) ? $_GET['api'] : 'unknown'
]);
exit;