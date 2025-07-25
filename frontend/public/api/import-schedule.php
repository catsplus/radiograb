<?php
/**
 * Import Schedule API Endpoint
 * Handles schedule import requests from the web interface
 */

header('Content-Type: application/json');

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
$input = json_decode(file_get_contents('php://input'), true);
if (!verifyCSRFToken($input['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$station_id = (int)($input['station_id'] ?? 0);
$action = $input['action'] ?? 'preview';
$auto_create = $input['auto_create'] ?? true;
$update_existing = $input['update_existing'] ?? false;
$selected_shows = $input['selected_shows'] ?? null;

if (!$station_id) {
    echo json_encode(['success' => false, 'error' => 'Station ID required']);
    exit;
}

try {
    // Verify station exists
    $station = $db->fetchOne("SELECT * FROM stations WHERE id = ?", [$station_id]);
    if (!$station) {
        echo json_encode(['success' => false, 'error' => 'Station not found']);
        exit;
    }
    
    // Get the Python backend path
    $backend_path = dirname(dirname(dirname(__DIR__))) . '/backend';
    $script_path = $backend_path . '/services/schedule_importer.py';
    
    if (!file_exists($script_path)) {
        echo json_encode(['success' => false, 'error' => 'Schedule importer not found']);
        exit;
    }
    
    // Build command with proper environment
    $command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python " . escapeshellarg($script_path);
    $command .= " --station-id " . (int)$station_id;
    
    if ($action === 'preview') {
        $command .= " --preview";
    } else {
        if ($auto_create) {
            $command .= " --auto-create";
        }
        if ($update_existing) {
            $command .= " --update-existing";
        }
        
        // Handle selective import
        if ($selected_shows && is_array($selected_shows)) {
            // Create temporary file with selected shows data
            $temp_file = tempnam(sys_get_temp_dir(), 'radiograb_selected_shows_');
            file_put_contents($temp_file, json_encode($selected_shows));
            $command .= " --selected-shows " . escapeshellarg($temp_file);
            
            // Clean up temp file after use (register shutdown function)
            register_shutdown_function(function() use ($temp_file) {
                if (file_exists($temp_file)) {
                    unlink($temp_file);
                }
            });
        }
    }
    
    $command .= " 2>&1";
    
    // Execute the command
    $output = shell_exec($command);
    $exit_code = 0; // shell_exec doesn't provide exit code
    
    if ($action === 'preview') {
        // Parse preview output to extract show information
        $shows = parsePreviewOutput($output);
        
        echo json_encode([
            'success' => true,
            'action' => 'preview',
            'shows' => $shows,
            'station_name' => $station['name'],
            'raw_output' => $output
        ]);
    } else {
        // Parse import results
        $results = parseImportOutput($output);
        
        echo json_encode([
            'success' => true,
            'action' => 'import',
            'results' => $results,
            'station_name' => $station['name'],
            'raw_output' => $output
        ]);
    }
    
} catch (Exception $e) {
    error_log("Schedule import error: " . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Import failed: ' . $e->getMessage()
    ]);
}

function parsePreviewOutput($output) {
    $shows = [];
    $lines = explode("\n", $output);
    $current_show = null;
    
    foreach ($lines as $line) {
        $line = trim($line);
        
        // Look for show lines like "[NEW] Show Name" or "[EXISTS] Show Name"
        if (preg_match('/^\[(NEW|EXISTS)\]\s+(.+)$/', $line, $matches)) {
            if ($current_show) {
                $shows[] = $current_show;
            }
            
            $current_show = [
                'name' => $matches[2],
                'status' => $matches[1],
                'exists' => $matches[1] === 'EXISTS',
                'schedule' => '',
                'host' => '',
                'description' => ''
            ];
        }
        // Look for schedule lines
        elseif ($current_show && preg_match('/^\s*Schedule:\s*(.+)$/', $line, $matches)) {
            $current_show['schedule'] = $matches[1];
        }
        // Look for host lines
        elseif ($current_show && preg_match('/^\s*Host:\s*(.+)$/', $line, $matches)) {
            $current_show['host'] = $matches[1];
        }
        // Look for description lines
        elseif ($current_show && preg_match('/^\s*Description:\s*(.+)$/', $line, $matches)) {
            $current_show['description'] = $matches[1];
        }
    }
    
    // Add the last show
    if ($current_show) {
        $shows[] = $current_show;
    }
    
    return $shows;
}

function parseImportOutput($output) {
    $results = [
        'shows_found' => 0,
        'shows_created' => 0,
        'shows_updated' => 0,
        'shows_skipped' => 0,
        'errors' => 0
    ];
    
    $lines = explode("\n", $output);
    
    foreach ($lines as $line) {
        // Look for result summary lines
        if (preg_match('/Shows found:\s*(\d+)/', $line, $matches)) {
            $results['shows_found'] = (int)$matches[1];
        }
        elseif (preg_match('/Shows created:\s*(\d+)/', $line, $matches)) {
            $results['shows_created'] = (int)$matches[1];
        }
        elseif (preg_match('/Shows updated:\s*(\d+)/', $line, $matches)) {
            $results['shows_updated'] = (int)$matches[1];
        }
        elseif (preg_match('/Shows skipped:\s*(\d+)/', $line, $matches)) {
            $results['shows_skipped'] = (int)$matches[1];
        }
        elseif (preg_match('/Errors:\s*(\d+)/', $line, $matches)) {
            $results['errors'] = (int)$matches[1];
        }
    }
    
    return $results;
}
?>