<?php
// Debug the exact login form process
session_start();
require_once 'frontend/includes/database.php';
require_once 'frontend/includes/functions.php';
require_once 'frontend/includes/auth.php';

echo "=== LOGIN FORM DEBUG ===\n";

$auth = new UserAuth($db);

// Simulate exact POST conditions from the web form
$_SERVER['REQUEST_METHOD'] = 'POST';
$_POST['email_or_username'] = 'testuser123';
$_POST['password'] = 'TestPassword123\!';

$email_or_username = trim($_POST['email_or_username'] ?? '');
$password = $_POST['password'] ?? '';

echo "POST Method: " . $_SERVER['REQUEST_METHOD'] . "\n";
echo "Email/Username: '$email_or_username'\n";
echo "Password length: " . strlen($password) . "\n";

if (empty($email_or_username) || empty($password)) {
    echo "❌ Empty credentials check failed\n";
} else {
    echo "✅ Credentials not empty\n";
    
    echo "\nCalling auth->login()...\n";
    $result = $auth->login($email_or_username, $password);
    
    echo "Login result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
    
    if ($result['success']) {
        echo "✅ AUTH SUCCESS - Should redirect to /dashboard.php\n";
        echo "Session after login:\n";
        print_r($_SESSION);
    } else {
        echo "❌ AUTH FAILED: " . $result['error'] . "\n";
    }
}

echo "\nSession ID: " . session_id() . "\n";
echo "Session status: " . session_status() . "\n";
?>
EOF < /dev/null