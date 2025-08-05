<?php
// Simple login debug accessible via web
session_start();
require_once 'frontend/includes/database.php';
require_once 'frontend/includes/functions.php';
require_once 'frontend/includes/auth.php';

header('Content-Type: text/plain');

echo "=== WEB LOGIN DEBUG ===\n";
echo "Timestamp: " . date('Y-m-d H:i:s') . "\n\n";

try {
    $auth = new UserAuth($db);
    
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        echo "POST Request received\n";
        echo "email_or_username: " . ($_POST['email_or_username'] ?? 'NOT SET') . "\n";
        echo "password length: " . strlen($_POST['password'] ?? '') . "\n\n";
        
        $email_or_username = trim($_POST['email_or_username'] ?? '');
        $password = $_POST['password'] ?? '';
        
        if (empty($email_or_username) || empty($password)) {
            echo "❌ Empty credentials\n";
        } else {
            echo "Testing login...\n";
            $result = $auth->login($email_or_username, $password);
            echo "Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
            
            if ($result['success']) {
                echo "✅ LOGIN SUCCESS!\n";
                echo "Session: " . print_r($_SESSION, true);
            } else {
                echo "❌ LOGIN FAILED: " . $result['error'] . "\n";
            }
        }
    } else {
        echo "GET Request - showing test form\n";
        echo "\n<form method='POST'>\n";
        echo "<input type='text' name='email_or_username' value='testuser123' placeholder='Username'><br>\n";
        echo "<input type='password' name='password' value='TestPassword123!' placeholder='Password'><br>\n";
        echo "<input type='submit' value='Test Login'>\n";
        echo "</form>\n";
    }
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
?>