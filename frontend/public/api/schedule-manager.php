<?php
/**
 * RadioGrab Schedule Manager API
 * Interface between web frontend and Python schedule manager
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// CSRF protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

$action = $_REQUEST['action'] ?? '';
$show_id = isset($_REQUEST['show_id']) ? (int)$_REQUEST['show_id'] : null;

/**
 * Execute Python schedule manager command
 */
function executeScheduleManager($command) {
    $python_script = dirname(dirname(dirname(__DIR__))) . '/backend/services/schedule_manager.py';
    $full_command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python $python_script $command 2>&1";
    
    $output = shell_exec($full_command);
    
    // Try to parse as JSON, fallback to plain text
    $result = json_decode($output, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'error' => 'Invalid JSON response', 'raw_output' => $output];
    }
    
    return $result;
}

try {
    switch ($action) {
        case 'add_show':
            if (!$show_id) {
                throw new Exception('Show ID is required');
            }
            $result = executeScheduleManager("--add-show $show_id");
            break;
            
        case 'update_show':
            if (!$show_id) {
                throw new Exception('Show ID is required');
            }
            $result = executeScheduleManager("--update-show $show_id");
            break;
            
        case 'remove_show':
            if (!$show_id) {
                throw new Exception('Show ID is required');
            }
            $result = executeScheduleManager("--remove-show $show_id");
            break;
            
        case 'refresh_all':
            $result = executeScheduleManager("--refresh-all");
            break;
            
        case 'status':
            $result = executeScheduleManager("--status");
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
    echo json_encode($result);
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>