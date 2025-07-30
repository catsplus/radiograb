<?php
try {
    session_start();
    require_once '../../includes/database.php';
    require_once '../../includes/functions.php';

    header('Content-Type: application/json');

    // Generate and return a fresh CSRF token
    $token = generateCSRFToken();
    
    if (!$token) {
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to generate CSRF token'
        ]);
        exit;
    }
    
    error_log("CSRF Token Generated: '$token', Session ID: " . session_id());
    echo json_encode([
        'success' => true,
        'csrf_token' => $token
    ]);
    
} catch (Exception $e) {
    error_log("CSRF Token API Error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Internal server error: ' . $e->getMessage()
    ]);
}
?>