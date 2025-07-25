<?php
/**
 * RadioGrab - CSRF Debug Tool
 * Helps debug CSRF token issues in browser
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

$method = $_SERVER['REQUEST_METHOD'];
$submitted_token = '';
$action = '';

if ($method === 'POST') {
    $submitted_token = $_POST['csrf_token'] ?? '';
    $action = $_POST['action'] ?? '';
} else {
    $submitted_token = $_GET['csrf_token'] ?? '';
    $action = $_GET['action'] ?? '';
}

$session_token = $_SESSION['csrf_token'] ?? '';
$session_id = session_id();

$debug_info = [
    'method' => $method,
    'action' => $action,
    'submitted_token' => $submitted_token,
    'session_token' => $session_token,
    'session_id' => $session_id,
    'tokens_match' => hash_equals($session_token, $submitted_token),
    'session_active' => !empty($session_token),
    'token_lengths' => [
        'submitted' => strlen($submitted_token),
        'session' => strlen($session_token)
    ],
    'headers' => [
        'Content-Type' => $_SERVER['CONTENT_TYPE'] ?? 'not set',
        'User-Agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'not set'
    ],
    'cookies' => $_COOKIE,
    'timestamp' => date('Y-m-d H:i:s')
];

echo json_encode($debug_info, JSON_PRETTY_PRINT);
?>