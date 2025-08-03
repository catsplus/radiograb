<?php
session_start();
header('Content-Type: application/json');

require_once '../../includes/auth.php';
require_once '../../includes/database.php';

// Check authentication
checkAuth();
$user_id = $_SESSION['user_id'];

// Verify CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

// Validate required fields
if (empty($_POST['remote_name']) || empty($_POST['backend_type']) || empty($_POST['role'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Missing required fields']);
    exit;
}

$remote_name = trim($_POST['remote_name']);
$backend_type = trim($_POST['backend_type']);
$role = trim($_POST['role']);

// Validate remote name format
if (!preg_match('/^[a-zA-Z0-9_-]+$/', $remote_name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Remote name can only contain letters, numbers, hyphens, and underscores']);
    exit;
}

// Validate role
if (!in_array($role, ['primary', 'backup', 'off'])) {
    http_response_code(400);
    echo json_encode(['success' => false, 'error' => 'Invalid role']);
    exit;
}

try {
    $db = Database::getInstance();
    
    // Check if remote name already exists for this user
    $existing = $db->fetchOne("
        SELECT id FROM user_rclone_remotes 
        WHERE user_id = ? AND remote_name = ?
    ", [$user_id, $remote_name]);
    
    if ($existing) {
        http_response_code(409);
        echo json_encode(['success' => false, 'error' => 'A remote with this name already exists']);
        exit;
    }
    
    // Get backend template for validation
    $template = $db->fetchOne("
        SELECT * FROM rclone_backend_templates 
        WHERE backend_type = ? AND is_active = 1
    ", [$backend_type]);
    
    if (!$template) {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid backend type']);
        exit;
    }
    
    // Extract and validate configuration fields
    $config_fields = json_decode($template['config_fields'], true);
    $config_data = [];
    
    foreach ($config_fields as $field_name => $field_config) {
        $value = $_POST[$field_name] ?? '';
        
        // Check required fields
        if ($field_config['required'] && empty($value)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Field '{$field_config['label']}' is required"]);
            exit;
        }
        
        // Store non-empty values
        if (!empty($value)) {
            $config_data[$field_name] = $value;
        }
    }
    
    // Create rclone config directory if it doesn't exist
    $rclone_dir = '/var/radiograb/rclone';
    if (!is_dir($rclone_dir)) {
        mkdir($rclone_dir, 0755, true);
    }
    
    // Generate config file path
    $config_file = "$rclone_dir/user_$user_id.conf";
    
    // Create rclone configuration section
    $rclone_config = ['type' => $backend_type] + $config_data;
    
    // Read existing config file if it exists
    $existing_config = [];
    if (file_exists($config_file)) {
        $current_section = null;
        $lines = file($config_file, FILE_IGNORE_NEW_LINES);
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (preg_match('/^\[(.+)\]$/', $line, $matches)) {
                $current_section = $matches[1];
                $existing_config[$current_section] = [];
            } elseif (strpos($line, '=') !== false && $current_section) {
                list($key, $value) = explode('=', $line, 2);
                $existing_config[$current_section][trim($key)] = trim($value);
            }
        }
    }
    
    // Add new remote to config
    $existing_config[$remote_name] = $rclone_config;
    
    // Write updated config file
    $config_content = '';
    foreach ($existing_config as $section_name => $section_config) {
        $config_content .= "[$section_name]\n";
        foreach ($section_config as $key => $value) {
            $config_content .= "$key = $value\n";
        }
        $config_content .= "\n";
    }
    
    if (file_put_contents($config_file, $config_content) === false) {
        throw new Exception('Failed to write rclone configuration file');
    }
    
    // Test the remote configuration
    $test_command = "rclone lsd $remote_name: --config $config_file --timeout 30s --retries 1 2>&1";
    $test_output = shell_exec($test_command);
    $test_success = (strpos($test_output, 'ERROR') === false && strpos($test_output, 'NOTICE') === false);
    
    // Store in database
    $db->execute("
        INSERT INTO user_rclone_remotes 
        (user_id, remote_name, backend_type, config_data, role, config_file_path, 
         is_active, last_test_at, last_test_result, created_at)
        VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), ?, NOW())
    ", [
        $user_id, 
        $remote_name, 
        $backend_type, 
        json_encode($config_data), 
        $role, 
        $config_file,
        $test_success ? 1 : 0,
        $test_success ? 'Connection successful' : "Test failed: $test_output"
    ]);
    
    echo json_encode([
        'success' => true,
        'message' => 'Remote saved successfully',
        'remote_name' => $remote_name,
        'test_result' => $test_success ? 'passed' : 'failed',
        'test_output' => $test_output
    ]);
    
} catch (Exception $e) {
    error_log("Rclone save error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Failed to save remote: ' . $e->getMessage()]);
}
?>