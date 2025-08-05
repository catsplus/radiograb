<?php
// Test authentication system
require_once 'frontend/includes/database.php';
require_once 'frontend/includes/auth.php';

echo "Testing authentication system...\n";

try {
    $auth = new UserAuth($db);
    
    // Test direct password verification
    $user = $db->fetchOne(
        "SELECT id, username, password_hash, email_verified, is_active FROM users WHERE username = ?",
        ['testuser123']
    );
    
    if ($user) {
        echo "User found: " . $user['username'] . "\n";
        echo "Password hash: " . $user['password_hash'] . "\n";
        echo "Email verified: " . ($user['email_verified'] ? 'Yes' : 'No') . "\n";
        echo "Is active: " . ($user['is_active'] ? 'Yes' : 'No') . "\n";
        
        $password = 'TestPassword123\!';
        $verify_result = password_verify($password, $user['password_hash']);
        echo "Password verification: " . ($verify_result ? 'SUCCESS' : 'FAILED') . "\n";
        
        // Test the auth class login method
        echo "\nTesting UserAuth->login()...\n";
        $login_result = $auth->login('testuser123', $password);
        echo "Login result: " . json_encode($login_result, JSON_PRETTY_PRINT) . "\n";
        
    } else {
        echo "User not found\!\n";
    }
    
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
    echo "Stack trace: " . $e->getTraceAsString() . "\n";
}
?>
EOF < /dev/null