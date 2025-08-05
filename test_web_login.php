<?php
// Test web login process exactly like login.php
session_start();
require_once 'frontend/includes/database.php';
require_once 'frontend/includes/functions.php';
require_once 'frontend/includes/auth.php';

echo "Testing web login process...\n";

try {
    $auth = new UserAuth($db);
    
    // Simulate POST data
    $email_or_username = 'testuser123';
    $password = 'TestPassword123\!';
    
    echo "Attempting login with: $email_or_username\n";
    echo "Password: " . str_repeat('*', strlen($password)) . "\n";
    
    if (empty($email_or_username) || empty($password)) {
        echo "❌ Empty credentials check failed\n";
    } else {
        echo "✅ Credentials provided\n";
        
        $result = $auth->login($email_or_username, $password);
        echo "Login result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
        
        if ($result['success']) {
            echo "✅ Login successful\!\n";
            echo "Session data:\n";
            print_r($_SESSION);
        } else {
            echo "❌ Login failed: " . $result['error'] . "\n";
        }
    }
    
} catch (Exception $e) {
    echo "❌ Exception: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
EOF < /dev/null