<?php
/**
 * RadioGrab - Test Recording API
 * Handles 10-second test recordings and on-demand recordings
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

// Debug CSRF token validation
$submitted_token = $_POST['csrf_token'] ?? '';
$session_token = $_SESSION['csrf_token'] ?? '';
error_log("CSRF Debug - Submitted: '$submitted_token', Session: '$session_token', Session ID: " . session_id());

// Verify CSRF token
if (!verifyCSRFToken($submitted_token)) {
    http_response_code(403);
    echo json_encode([
        'error' => 'Invalid security token',
        'debug' => [
            'submitted_token' => $submitted_token,
            'session_id' => session_id(),
            'has_session_token' => !empty($session_token)
        ]
    ]);
    exit;
}

$action = $_POST['action'] ?? '';
$station_id = (int)($_POST['station_id'] ?? 0);

if (!$station_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Station ID required']);
    exit;
}

try {
    // Get station details
    $station = $db->fetchOne("SELECT * FROM stations WHERE id = ?", [$station_id]);
    if (!$station) {
        http_response_code(404);
        echo json_encode(['error' => 'Station not found']);
        exit;
    }
    
    if (!$station['call_letters']) {
        http_response_code(400);
        echo json_encode(['error' => 'Station has no call letters configured']);
        exit;
    }
    
    if (!$station['stream_url']) {
        http_response_code(400);
        echo json_encode(['error' => 'Station has no stream URL configured']);
        exit;
    }
    
    // Determine recording type and duration
    $duration = 10; // Default 10 seconds for test
    $output_dir = '/var/radiograb/temp'; // Test recordings go to temp directory
    $show_name = $station['name'] . ' Test Recording';
    
    if ($action === 'record_now') {
        $duration = 3600; // 1 hour for on-demand
        $output_dir = '/var/radiograb/recordings'; // On-demand recordings go to main directory
        
        // Get station call letters (first 4 letters of name, uppercase)
        $call_letters = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $station['name']), 0, 4));
        $show_name = $call_letters . ' On-Demand Recording';
    }
    
    // Generate filename with timestamp using call letters
    $timestamp = date('Y-m-d-His');
    $call_letters = strtoupper($station['call_letters']);
    if ($action === 'test_recording') {
        $filename = "{$call_letters}_test_{$timestamp}.mp3";
    } else {
        $filename = "{$call_letters}_on-demand_{$timestamp}.mp3";
    }
    
    // Build Python command to start recording
    $python_cmd = "/opt/radiograb/venv/bin/python";
    $script_path = "/opt/radiograb/backend/services/test_recording_service.py";
    
    $cmd = [
        $python_cmd,
        $script_path,
        '--station-id', $station_id,
        '--duration', $duration,
        '--output-dir', $output_dir,
        '--filename', $filename,
        '--stream-url', $station['stream_url'],
        '--show-name', $show_name
    ];
    
    // Start recording in background with proper error logging
    $log_file = "/var/radiograb/logs/test_recording_" . $station_id . "_" . date('Y-m-d-His') . ".log";
    $cmd_string = implode(' ', array_map('escapeshellarg', $cmd)) . " > $log_file 2>&1 &";
    exec($cmd_string, $output, $return_code);
    
    // Log the command for debugging
    error_log("Test recording command: $cmd_string");
    
    // Create recording entry for on-demand recordings
    if ($action === 'record_now') {
        // Create or get the on-demand show
        $call_letters = strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $station['name']), 0, 4));
        $on_demand_show_name = $call_letters . ' On-Demand Recordings';
        
        $show = $db->fetchOne("SELECT id FROM shows WHERE name = ? AND station_id = ?", 
                             [$on_demand_show_name, $station_id]);
        
        if (!$show) {
            // Create the on-demand show
            $show_id = $db->insert('shows', [
                'station_id' => $station_id,
                'name' => $on_demand_show_name,
                'description' => 'On-demand recordings from ' . $station['name'],
                'schedule_pattern' => '', // No scheduled pattern
                'schedule_description' => 'Manual on-demand recordings',
                'retention_days' => 30,
                'active' => 1,
                'timezone' => $station['timezone'] ?? 'America/New_York'
            ]);
        } else {
            $show_id = $show['id'];
        }
        
        // Create recording entry
        $db->insert('recordings', [
            'show_id' => $show_id,
            'filename' => $filename,
            'title' => $show_name . ' - ' . date('Y-m-d H:i'),
            'description' => 'On-demand recording started at ' . date('Y-m-d H:i:s'),
            'recorded_at' => date('Y-m-d H:i:s'),
            'duration_seconds' => $duration,
            'file_size_bytes' => 0 // Will be updated when recording completes
        ]);
    }
    
    $response = [
        'success' => true,
        'message' => ($action === 'test_recording') 
            ? 'Test recording started (10 seconds)'
            : 'On-demand recording started (1 hour)',
        'filename' => $filename,
        'duration' => $duration,
        'output_dir' => $output_dir,
        'estimated_completion' => date('Y-m-d H:i:s', time() + $duration)
    ];
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Test recording error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Recording failed: ' . $e->getMessage()]);
}
?>