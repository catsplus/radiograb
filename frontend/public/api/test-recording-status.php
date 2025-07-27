<?php
/**
 * RadioGrab - Test Recording Status API  
 * Returns detailed status and results of test recordings with diagnostics
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

// Verify CSRF token
$submitted_token = $_POST['csrf_token'] ?? '';
if (!verifyCSRFToken($submitted_token)) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid security token']);
    exit;
}

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
    
    // Perform synchronous test recording and get detailed results
    $python_cmd = "/opt/radiograb/venv/bin/python";
    $script_path = "/opt/radiograb/backend/services/test_recording_service.py";
    
    $timestamp = date('Y-m-d-His');
    $call_letters = strtoupper($station['call_letters']);
    $filename = "{$call_letters}_test_{$timestamp}.mp3";
    $output_dir = '/var/radiograb/temp';
    
    // Build command for synchronous execution
    $cmd = escapeshellcmd($python_cmd) . ' ' . 
           escapeshellarg($script_path) . ' ' .
           '--station-id ' . escapeshellarg($station_id) . ' ' .
           '--duration 10 ' .
           '--output-dir ' . escapeshellarg($output_dir) . ' ' .
           '--filename ' . escapeshellarg($filename) . ' ' .
           '--stream-url ' . escapeshellarg($station['stream_url']);
    
    // Execute and capture output
    $output = [];
    $return_code = 0;
    exec($cmd . ' 2>&1', $output, $return_code);
    
    $output_text = implode("\n", $output);
    
    // Check if test recording was successful
    $success = ($return_code === 0);
    
    // Build detailed response
    $response = [
        'success' => $success,
        'station_name' => $station['name'],
        'filename' => $filename,
        'return_code' => $return_code,
        'debug' => $output_text
    ];
    
    if ($success) {
        $response['message'] = 'Test recording completed successfully';
        
        // Check if file exists and get size
        $file_path = $output_dir . '/' . $filename;
        if (file_exists($file_path)) {
            $response['file_size'] = filesize($file_path);
            $response['download_url'] = '/temp/' . $filename;
        }
        
        // Parse output for additional details
        if (strpos($output_text, 'AAC converted to MP3') !== false) {
            $response['format_conversion'] = 'AAC automatically converted to MP3';
        }
        
        if (strpos($output_text, 'saved User-Agent') !== false) {
            $response['user_agent_saved'] = 'New User-Agent saved for future use';
        }
        
    } else {
        // Parse detailed error information from output
        $response['error'] = 'Test recording failed';
        
        // Extract primary error message
        $lines = explode("\n", $output_text);
        foreach ($lines as $line) {
            if (strpos($line, 'Recording failed:') !== false) {
                $response['error'] = trim(str_replace('Recording failed:', '', $line));
                break;
            }
        }
        
        // Check for stream discovery attempts
        if (strpos($output_text, 'stream rediscovery') !== false || 
            strpos($output_text, 'Radio Browser') !== false) {
            $response['stream_discovery_attempted'] = true;
            $response['stream_discovery_result'] = 'Stream rediscovery was attempted';
            
            if (strpos($output_text, 'Found new stream') !== false) {
                $response['stream_discovery_result'] = 'New stream URL was found and tested';
            } elseif (strpos($output_text, 'No better stream found') !== false) {
                $response['stream_discovery_result'] = 'No better stream URL could be found';
            }
        }
        
        // Check for User-Agent testing
        if (strpos($output_text, 'HTTP 403 detected') !== false || 
            strpos($output_text, 'User-Agent') !== false) {
            $response['user_agent_tested'] = true;
            $response['user_agent_result'] = 'Multiple User-Agents were tested';
            
            if (strpos($output_text, 'Success with') !== false && strpos($output_text, 'User-Agent') !== false) {
                $response['user_agent_result'] = 'Working User-Agent was found and saved';
            } else {
                $response['user_agent_result'] = 'No working User-Agent could be found';
            }
        }
        
        // Check for specific error types
        if (strpos($output_text, 'HTTP:403') !== false || 
            strpos($output_text, 'Access Forbidden') !== false) {
            $response['error_type'] = 'HTTP 403 Access Forbidden';
            $response['suggested_action'] = 'Stream requires specific User-Agent or authentication';
        } elseif (strpos($output_text, 'timeout') !== false) {
            $response['error_type'] = 'Connection Timeout';
            $response['suggested_action'] = 'Stream may be temporarily unavailable or slow';
        } elseif (strpos($output_text, 'not found') !== false || strpos($output_text, '404') !== false) {
            $response['error_type'] = 'Stream Not Found';
            $response['suggested_action'] = 'Stream URL may have changed or is invalid';
        }
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    error_log("Test recording status error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Test recording failed: ' . $e->getMessage(),
        'debug' => 'Exception: ' . $e->getMessage()
    ]);
}
?>