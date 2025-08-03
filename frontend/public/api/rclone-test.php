<?php
session_start();
header('Content-Type: application/json');

require_once '../../includes/auth.php';
require_once '../../includes/database.php';

// Check authentication
checkAuth();
$user_id = $_SESSION['user_id'];

// Handle different request types
$input = json_decode(file_get_contents('php://input'), true);
$csrf_token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';

// Verify CSRF token
if ($csrf_token !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

try {
    $db = Database::getInstance();
    
    if (isset($input['remote_id'])) {
        // Test existing remote
        $remote = $db->fetchOne("
            SELECT * FROM user_rclone_remotes 
            WHERE id = ? AND user_id = ?
        ", [$input['remote_id'], $user_id]);
        
        if (!$remote) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Remote not found']);
            exit;
        }
        
        $remote_name = $remote['remote_name'];
        $config_file = $remote['config_file_path'];
        
    } else {
        // Test new remote configuration
        if (empty($_POST['remote_name']) || empty($_POST['backend_type'])) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Missing required fields']);
            exit;
        }
        
        $remote_name = trim($_POST['remote_name']);
        $backend_type = trim($_POST['backend_type']);
        
        // Get backend template
        $template = $db->fetchOne("
            SELECT * FROM rclone_backend_templates 
            WHERE backend_type = ? AND is_active = 1
        ", [$backend_type]);
        
        if (!$template) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid backend type']);
            exit;
        }
        
        // Extract configuration data
        $config_fields = json_decode($template['config_fields'], true);
        $config_data = [];
        
        foreach ($config_fields as $field_name => $field_config) {
            $value = $_POST[$field_name] ?? '';
            if (!empty($value)) {
                $config_data[$field_name] = $value;
            }
        }
        
        // Create temporary config file for testing
        $temp_config_file = '/tmp/rclone_test_' . $user_id . '_' . time() . '.conf';
        $rclone_config = ['type' => $backend_type] + $config_data;
        
        $config_content = "[$remote_name]\n";
        foreach ($rclone_config as $key => $value) {
            $config_content .= "$key = $value\n";
        }
        
        if (file_put_contents($temp_config_file, $config_content) === false) {
            throw new Exception('Failed to create temporary config file');
        }
        
        $config_file = $temp_config_file;
    }
    
    // Test the remote connection
    $start_time = microtime(true);
    
    // Use timeout and retries for reliability
    $test_command = "timeout 45 rclone lsd " . escapeshellarg($remote_name . ':') . 
                   " --config " . escapeshellarg($config_file) . 
                   " --timeout 30s --retries 1 --quiet 2>&1";
    
    $test_output = shell_exec($test_command);
    $test_time = round((microtime(true) - $start_time) * 1000); // milliseconds
    $exit_code = 0; // shell_exec doesn't provide exit code directly
    
    // Determine if test was successful
    $success = true;
    $error_indicators = ['ERROR', 'FATAL', 'Failed to', 'cannot', 'timeout', 'refused', 'unreachable'];
    
    foreach ($error_indicators as $indicator) {
        if (stripos($test_output, $indicator) !== false) {
            $success = false;
            break;
        }
    }
    
    // Check for empty output which might indicate success for some backends
    if (empty(trim($test_output))) {
        $success = true;
        $test_output = 'Connection successful (no output)';
    }
    
    // Clean up temporary config file if created
    if (isset($temp_config_file) && file_exists($temp_config_file)) {
        unlink($temp_config_file);
    }
    
    // Update test results in database for existing remotes
    if (isset($input['remote_id'])) {
        $db->execute("
            UPDATE user_rclone_remotes 
            SET last_test_at = NOW(), 
                last_test_result = ?,
                is_active = ?
            WHERE id = ? AND user_id = ?
        ", [
            $success ? 'Connection successful' : "Test failed: $test_output",
            $success ? 1 : 0,
            $input['remote_id'],
            $user_id
        ]);
    }
    
    if ($success) {
        echo json_encode([
            'success' => true,
            'message' => 'Remote connection successful',
            'response_time_ms' => $test_time,
            'output' => trim($test_output) ?: 'Connected successfully'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => 'Remote connection failed',
            'details' => trim($test_output),
            'response_time_ms' => $test_time
        ]);
    }
    
} catch (Exception $e) {
    // Clean up temporary files
    if (isset($temp_config_file) && file_exists($temp_config_file)) {
        unlink($temp_config_file);
    }
    
    error_log("Rclone test error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Test failed: ' . $e->getMessage()]);
}
?>