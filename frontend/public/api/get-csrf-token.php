<?php
session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Generate and return a fresh CSRF token
$token = generateCSRFToken();
error_log("CSRF Token Generated: '$token', Session ID: " . session_id());
echo json_encode(['csrf_token' => $token]);
?>