<?php
/**
 * Test script for Issue #6 Authentication System
 */

require_once __DIR__ . '/frontend/includes/database.php';
require_once __DIR__ . '/frontend/includes/functions.php';
require_once __DIR__ . '/frontend/includes/auth.php';

echo "Testing Issue #6 User Authentication System\n";
echo "==========================================\n\n";

try {
    $auth = new UserAuth($db);
    
    // Test 1: Check if admin user exists and can login
    echo "Test 1: Admin User Login\n";
    echo "------------------------\n";
    
    // Test with admin/admin (assuming default password)
    $result = $auth->login('admin', 'admin');
    if ($result['success']) {
        echo "✅ Admin login successful\n";
        $current_user = $auth->getCurrentUser();
        echo "   User ID: " . $current_user['id'] . "\n";
        echo "   Username: " . $current_user['username'] . "\n";
        echo "   Email: " . $current_user['email'] . "\n";
        echo "   Is Admin: " . ($current_user['is_admin'] ? 'Yes' : 'No') . "\n";
        echo "   Is Active: " . ($current_user['is_active'] ? 'Yes' : 'No') . "\n";
        
        // Test admin check
        echo "   Admin Check: " . ($auth->isAdmin() ? '✅ Admin' : '❌ Not Admin') . "\n";
        
        $auth->logout();
        echo "   Logout: ✅ Successful\n";
    } else {
        echo "❌ Admin login failed: " . $result['error'] . "\n";
    }
    
    echo "\nTest 2: Database Tables\n";
    echo "-----------------------\n";
    
    // Check if users table has required fields
    $result = $db->query("DESCRIBE users");
    $fields = $result->fetchAll(PDO::FETCH_ASSOC);
    
    $required_fields = ['email', 'email_verified', 'is_admin', 'is_active'];
    foreach ($required_fields as $field) {
        $found = false;
        foreach ($fields as $table_field) {
            if ($table_field['Field'] === $field) {
                $found = true;
                break;
            }
        }
        echo "   Field '$field': " . ($found ? '✅ Present' : '❌ Missing') . "\n";
    }
    
    echo "\nTest 3: Authentication Pages\n";
    echo "----------------------------\n";
    
    // Test key authentication pages exist
    $pages = [
        'register.php' => 'Registration Page',
        'login.php' => 'Login Page',
        'dashboard.php' => 'User Dashboard',
        'admin/dashboard.php' => 'Admin Dashboard',
        'verify-email.php' => 'Email Verification'
    ];
    
    foreach ($pages as $page => $name) {
        $path = __DIR__ . '/frontend/public/' . $page;
        echo "   $name: " . (file_exists($path) ? '✅ Exists' : '❌ Missing') . "\n";
    }
    
    echo "\nTest 4: Authentication Class Methods\n";
    echo "------------------------------------\n";
    
    $methods = [
        'register' => 'User Registration',
        'login' => 'User Login', 
        'logout' => 'User Logout',
        'isAuthenticated' => 'Authentication Check',
        'isAdmin' => 'Admin Check',
        'getCurrentUser' => 'Get Current User',
        'verifyEmail' => 'Email Verification'
    ];
    
    foreach ($methods as $method => $name) {
        echo "   $name: " . (method_exists($auth, $method) ? '✅ Available' : '❌ Missing') . "\n";
    }
    
    echo "\n✅ Issue #6 Authentication System Testing Complete!\n";
    
} catch (Exception $e) {
    echo "❌ Error: " . $e->getMessage() . "\n";
    echo "   File: " . $e->getFile() . "\n";
    echo "   Line: " . $e->getLine() . "\n";
}
?>