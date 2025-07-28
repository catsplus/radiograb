<?php
/**
 * RadioGrab Show Management API
 * Handle show management operations including active/inactive toggle and tags
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Verify CSRF token for all POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        parse_str(file_get_contents('php://input'), $input);
    }
    
    if (empty($input['csrf_token']) || !verifyCSRFToken($input['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'toggle_active':
            handleToggleActive();
            break;
            
        case 'update_tags':
            handleUpdateTags();
            break;
            
        case 'get_next_recordings':
            handleGetNextRecordings();
            break;
            
        case 'cleanup_tests':
            handleCleanupTests();
            break;
            
        case 'get_stats':
            handleGetStats();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Show Management API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

function handleToggleActive() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        parse_str(file_get_contents('php://input'), $input);
    }
    
    $show_id = intval($input['show_id'] ?? 0);
    $active = filter_var($input['active'] ?? false, FILTER_VALIDATE_BOOLEAN);
    
    if (!$show_id) {
        echo json_encode(['success' => false, 'error' => 'Show ID required']);
        return;
    }
    
    $result = executeShowManagement("--toggle-show {$show_id} " . ($active ? '--activate' : '--deactivate'));
    echo json_encode($result);
}

function handleUpdateTags() {
    $input = json_decode(file_get_contents('php://input'), true);
    if (!$input) {
        parse_str(file_get_contents('php://input'), $input);
    }
    
    $show_id = intval($input['show_id'] ?? 0);
    $tags = trim($input['tags'] ?? '');
    
    if (!$show_id) {
        echo json_encode(['success' => false, 'error' => 'Show ID required']);
        return;
    }
    
    $escaped_tags = escapeshellarg($tags);
    $result = executeShowManagement("--update-tags {$show_id} --tags {$escaped_tags}");
    echo json_encode($result);
}

function handleGetNextRecordings() {
    $limit = intval($_GET['limit'] ?? 10);
    $result = executeShowManagement("--next-recordings {$limit}");
    
    if ($result['success']) {
        // Parse the command output into structured data
        $lines = explode("\n", $result['output']);
        $recordings = [];
        $current_recording = null;
        
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '===') !== false) continue;
            
            if (strpos($line, '- ') === 0) {
                // New recording entry
                if ($current_recording) {
                    $recordings[] = $current_recording;
                }
                $current_recording = ['title' => substr($line, 2)];
            } elseif (strpos($line, 'Next: ') === 0) {
                if ($current_recording) {
                    $current_recording['next_run'] = substr($line, 6);
                }
            } elseif (strpos($line, 'Tags: ') === 0) {
                if ($current_recording) {
                    $current_recording['tags'] = substr($line, 6);
                }
            }
        }
        
        if ($current_recording) {
            $recordings[] = $current_recording;
        }
        
        echo json_encode(['success' => true, 'recordings' => $recordings]);
    } else {
        echo json_encode($result);
    }
}

function handleCleanupTests() {
    $max_age = intval($_GET['max_age'] ?? 4);
    $result = executeShowManagement("--cleanup-tests {$max_age}");
    echo json_encode($result);
}

function handleGetStats() {
    $result = executeShowManagement("--stats");
    
    if ($result['success']) {
        // Parse stats output
        $lines = explode("\n", $result['output']);
        $stats = [];
        
        foreach ($lines as $line) {
            if (strpos($line, ':') !== false) {
                list($key, $value) = explode(':', $line, 2);
                $key = strtolower(str_replace(' ', '_', trim($key)));
                $stats[$key] = intval(trim($value));
            }
        }
        
        echo json_encode(['success' => true, 'stats' => $stats]);
    } else {
        echo json_encode($result);
    }
}

function executeShowManagement($command) {
    $python_script = dirname(dirname(dirname(__DIR__))) . '/backend/services/show_management.py';
    $full_command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python {$python_script} {$command} 2>&1";
    
    $output = shell_exec($full_command);
    $exit_code = 0;
    
    if ($output === null) {
        return [
            'success' => false,
            'error' => 'Failed to execute command',
            'command' => $full_command
        ];
    }
    
    // Check if output contains error indicators
    $success = !preg_match('/error|exception|failed/i', $output);
    
    return [
        'success' => $success,
        'output' => trim($output),
        'command' => $full_command
    ];
}
?>