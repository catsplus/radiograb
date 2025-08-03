<?php
/**
 * API Key Testing Endpoint
 * Issues #13, #25, #26 - Test API key connectivity
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/ApiKeyManager.php';

header('Content-Type: application/json');

$auth = new UserAuth($db);

// Require authentication
if (!$auth->isAuthenticated()) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Authentication required']);
    exit;
}

// Only handle POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$user_id = $auth->getCurrentUserId();
$api_key_id = (int)($_POST['api_key_id'] ?? 0);

if (!$api_key_id) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'API key ID is required']);
    exit;
}

try {
    $apiKeyManager = new ApiKeyManager($db);
    $result = $apiKeyManager->validateApiKey($user_id, $api_key_id);
    
    echo json_encode($result);
    
} catch (Exception $e) {
    error_log("API key test error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to test API key']);
}
?>