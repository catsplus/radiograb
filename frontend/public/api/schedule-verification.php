<?php
/**
 * RadioGrab Schedule Verification API
 * Manages automated schedule verification and updates
 */

header('Content-Type: application/json');
session_start();

require_once '../includes/database.php';
require_once '../includes/functions.php';

// Enable CORS for API requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// CSRF protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true) ?: $_POST;
    
    if (!verifyCSRFToken($input['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? '';

/**
 * Execute schedule verification command
 */
function executeScheduleVerification(string $command): array {
    $fullCommand = "cd /opt/radiograb && docker exec radiograb-web-1 /opt/radiograb/venv/bin/python backend/services/schedule_verification_service.py $command 2>&1";
    
    $output = [];
    $returnCode = 0;
    exec($fullCommand, $output, $returnCode);
    
    return [
        'success' => $returnCode === 0,
        'output' => implode("\n", $output),
        'return_code' => $returnCode,
        'command' => $command
    ];
}

try {
    switch ($action) {
        case 'verify_all':
            $force = filter_var($_GET['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $command = '--verify-all' . ($force ? ' --force' : '');
            $result = executeScheduleVerification($command);
            echo json_encode($result);
            break;
            
        case 'verify_station':
            $station_id = intval($_GET['station_id'] ?? 0);
            if ($station_id <= 0) {
                throw new Exception('Valid station ID required');
            }
            
            $command = "--station-id $station_id";
            $result = executeScheduleVerification($command);
            echo json_encode($result);
            break;
            
        case 'get_history':
            $days = min(intval($_GET['days'] ?? 30), 90); // Max 90 days
            $command = "--history $days";
            $result = executeScheduleVerification($command);
            echo json_encode($result);
            break;
            
        case 'get_verification_status':
            // Get verification status for all stations
            try {
                $stations = $db->fetchAll("
                    SELECT id, name, call_letters, last_tested, last_test_result, last_test_error,
                           CASE 
                               WHEN last_tested IS NULL THEN 'never'
                               WHEN last_tested < DATE_SUB(NOW(), INTERVAL 7 DAY) THEN 'overdue'
                               WHEN last_tested < DATE_SUB(NOW(), INTERVAL 5 DAY) THEN 'due_soon'
                               ELSE 'current'
                           END as verification_status,
                           TIMESTAMPDIFF(DAY, last_tested, NOW()) as days_since_check
                    FROM stations 
                    WHERE status = 'active'
                    ORDER BY verification_status DESC, last_tested ASC
                ");
                
                $status_summary = [
                    'never' => 0,
                    'overdue' => 0,
                    'due_soon' => 0,
                    'current' => 0
                ];
                
                foreach ($stations as $station) {
                    $status_summary[$station['verification_status']]++;
                }
                
                echo json_encode([
                    'success' => true,
                    'stations' => $stations,
                    'summary' => $status_summary
                ]);
                
            } catch (Exception $e) {
                throw new Exception('Failed to get verification status: ' . $e->getMessage());
            }
            break;
            
        case 'manual_verify':
            // Trigger manual verification via AJAX
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('POST method required');
            }
            
            $input = json_decode(file_get_contents('php://input'), true);
            $station_id = intval($input['station_id'] ?? 0);
            
            if ($station_id > 0) {
                $command = "--station-id $station_id";
            } else {
                $force = filter_var($input['force'] ?? false, FILTER_VALIDATE_BOOLEAN);
                $command = '--verify-all' . ($force ? ' --force' : '');
            }
            
            $result = executeScheduleVerification($command);
            
            // Parse the output to extract meaningful information
            $parsed_result = [
                'success' => $result['success'],
                'command' => $result['command'],
                'raw_output' => $result['output']
            ];
            
            // Try to extract structured information from output
            if (preg_match('/Stations checked: (\d+)/', $result['output'], $matches)) {
                $parsed_result['stations_checked'] = intval($matches[1]);
            }
            
            if (preg_match('/Total changes: (\d+)/', $result['output'], $matches)) {
                $parsed_result['total_changes'] = intval($matches[1]);
            }
            
            echo json_encode($parsed_result);
            break;
            
        case 'get_recent_changes':
            // Get recent schedule changes from the JSON log
            $log_file = '/var/radiograb/logs/schedule_changes.json';
            $days = min(intval($_GET['days'] ?? 7), 30);
            
            $changes = [];
            $total_changes = 0;
            
            if (file_exists($log_file)) {
                $cutoff_date = new DateTime();
                $cutoff_date->sub(new DateInterval("P{$days}D"));
                
                $lines = file($log_file, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                
                foreach ($lines as $line) {
                    try {
                        $log_entry = json_decode($line, true);
                        $entry_date = new DateTime($log_entry['timestamp']);
                        
                        if ($entry_date >= $cutoff_date) {
                            $changes[] = $log_entry;
                            $total_changes += $log_entry['summary']['total_changes'];
                        }
                    } catch (Exception $e) {
                        // Skip invalid JSON lines
                        continue;
                    }
                }
            }
            
            // Sort by timestamp descending (newest first)
            usort($changes, function($a, $b) {
                return strcmp($b['timestamp'], $a['timestamp']);
            });
            
            echo json_encode([
                'success' => true,
                'changes' => $changes,
                'total_changes' => $total_changes,
                'days' => $days
            ]);
            break;
            
        default:
            throw new Exception('Invalid action specified');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}
?>