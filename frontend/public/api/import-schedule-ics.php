<?php
/**
 * RadioGrab - Import Schedule from ICS File API
 * Handles manual ICS file upload and parsing for schedule discovery
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$station_id = (int)($_POST['station_id'] ?? 0);

if (!$station_id) {
    echo json_encode(['success' => false, 'error' => 'Station ID is required']);
    exit;
}

// Check if file was uploaded
if (!isset($_FILES['ics_file']) || $_FILES['ics_file']['error'] !== UPLOAD_ERR_OK) {
    $error_message = getUploadErrorMessage($_FILES['ics_file']['error'] ?? UPLOAD_ERR_NO_FILE);
    echo json_encode(['success' => false, 'error' => 'File upload error: ' . $error_message]);
    exit;
}

try {
    // Get station information
    $station = $db->fetchOne("SELECT id, name, call_letters FROM stations WHERE id = ?", [$station_id]);
    
    if (!$station) {
        echo json_encode(['success' => false, 'error' => 'Station not found']);
        exit;
    }
    
    $uploaded_file = $_FILES['ics_file'];
    $temp_path = $uploaded_file['tmp_name'];
    $original_filename = $uploaded_file['name'];
    
    // Validate file type
    $allowed_extensions = ['ics', 'ical'];
    $file_extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
    
    if (!in_array($file_extension, $allowed_extensions)) {
        echo json_encode(['success' => false, 'error' => 'Invalid file type. Please upload an .ics or .ical file.']);
        exit;
    }
    
    // Validate file size (max 10MB)
    if ($uploaded_file['size'] > 10 * 1024 * 1024) {
        echo json_encode(['success' => false, 'error' => 'File too large. Maximum size is 10MB.']);
        exit;
    }
    
    // Validate file content (basic ICS format check)
    $file_content = file_get_contents($temp_path);
    if (!$file_content || !str_contains($file_content, 'BEGIN:VCALENDAR')) {
        echo json_encode(['success' => false, 'error' => 'Invalid ICS file format. File must contain calendar data.']);
        exit;
    }
    
    // Move to temporary processing location
    $temp_dir = '/var/radiograb/temp';
    if (!is_dir($temp_dir)) {
        mkdir($temp_dir, 0755, true);
    }
    
    $temp_filename = 'import_' . uniqid() . '_' . $original_filename;
    $temp_filepath = $temp_dir . '/' . $temp_filename;
    
    if (!move_uploaded_file($temp_path, $temp_filepath)) {
        echo json_encode(['success' => false, 'error' => 'Failed to process uploaded file']);
        exit;
    }
    
    // Call the Python ICS parser
    $python_script = dirname(dirname(dirname(__DIR__))) . '/backend/services/ics_parser.py';
    $command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python -c \"
import sys
sys.path.append('/opt/radiograb')
from backend.services.ics_parser import ICSParser
import json
import logging

# Suppress debug logging for API call
logging.getLogger().setLevel(logging.WARNING)

parser = ICSParser()
result = parser.parse_ics_file('" . escapeshellarg($temp_filepath) . "', " . $station_id . ")

if result.success:
    # Convert shows to JSON
    show_data = []
    for show in result.shows:
        show_dict = {
            'name': show.name,
            'start_time': show.start_time.strftime('%H:%M'),
            'end_time': show.end_time.strftime('%H:%M') if show.end_time else None,
            'days': show.days,
            'description': show.description,
            'host': show.host,
            'genre': show.genre,
            'duration_minutes': show.duration_minutes
        }
        show_data.append(show_dict)
    
    print(json.dumps({
        'success': True, 
        'shows': show_data,
        'method_info': result.method_info,
        'stats': result.stats
    }))
else:
    print(json.dumps({'success': False, 'error': result.error}))
\" 2>&1";
    
    $output = shell_exec($command);
    
    // Clean up temp file
    if (file_exists($temp_filepath)) {
        unlink($temp_filepath);
    }
    
    // Parse output
    $lines = explode("\n", trim($output));
    $json_line = end($lines); // Get the last line which should be our JSON
    
    // Try to parse the JSON output
    $result = json_decode($json_line, true);
    
    if (!$result || !isset($result['success'])) {
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to parse ICS file',
            'debug_output' => $output
        ]);
        exit;
    }
    
    if (!$result['success']) {
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Unknown parsing error'
        ]);
        exit;
    }
    
    // Group shows by name to handle multiple airings
    $grouped_shows = [];
    foreach ($result['shows'] as $show) {
        $show_name = $show['name'];
        
        if (!isset($grouped_shows[$show_name])) {
            $grouped_shows[$show_name] = [
                'name' => $show_name,
                'description' => $show['description'],
                'host' => $show['host'],
                'genre' => $show['genre'],
                'airings' => []
            ];
        }
        
        // Add this airing
        $airing = [
            'start_time' => $show['start_time'],
            'end_time' => $show['end_time'],
            'days' => $show['days'],
            'duration_minutes' => $show['duration_minutes']
        ];
        
        $grouped_shows[$show_name]['airings'][] = $airing;
    }
    
    // Sort shows alphabetically
    ksort($grouped_shows);
    $shows_list = array_values($grouped_shows);
    
    echo json_encode([
        'success' => true,
        'source' => 'ics_upload',
        'filename' => $original_filename,
        'station' => [
            'id' => $station['id'],
            'name' => $station['name'],
            'call_letters' => $station['call_letters']
        ],
        'shows' => $shows_list,
        'count' => count($shows_list),
        'method_info' => $result['method_info'] ?? null,
        'stats' => $result['stats'] ?? null
    ]);
    
} catch (Exception $e) {
    // Clean up temp file on error
    if (isset($temp_filepath) && file_exists($temp_filepath)) {
        unlink($temp_filepath);
    }
    
    echo json_encode(['success' => false, 'error' => 'Processing error: ' . $e->getMessage()]);
}

function getUploadErrorMessage($error_code) {
    switch ($error_code) {
        case UPLOAD_ERR_OK:
            return 'No error';
        case UPLOAD_ERR_INI_SIZE:
        case UPLOAD_ERR_FORM_SIZE:
            return 'File too large';
        case UPLOAD_ERR_PARTIAL:
            return 'File upload incomplete';
        case UPLOAD_ERR_NO_FILE:
            return 'No file uploaded';
        case UPLOAD_ERR_NO_TMP_DIR:
            return 'No temporary directory';
        case UPLOAD_ERR_CANT_WRITE:
            return 'Cannot write to disk';
        case UPLOAD_ERR_EXTENSION:
            return 'Upload blocked by extension';
        default:
            return 'Upload error';
    }
}
?>