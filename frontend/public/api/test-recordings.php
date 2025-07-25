<?php
/**
 * RadioGrab - Test Recordings API
 * Lists and manages test recordings
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Verify CSRF token for non-GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['error' => 'Invalid security token']);
        exit;
    }
}

$action = $_GET['action'] ?? $_POST['action'] ?? 'list';

try {
    switch ($action) {
        case 'list':
            listTestRecordings();
            break;
        case 'delete':
            deleteTestRecording();
            break;
        case 'download':
            downloadTestRecording();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function listTestRecordings() {
    $tempDir = '/var/radiograb/temp';
    $recordings = [];
    
    if (is_dir($tempDir)) {
        $files = glob($tempDir . '/*.mp3');
        
        foreach ($files as $file) {
            if (is_file($file)) {
                $filename = basename($file);
                $size = filesize($file);
                $created = filemtime($file);
                
                // Parse filename to extract info (support both formats)
                $callLetters = null;
                $stationId = null;
                $timestamp = null;
                
                // New format: {call_letters}_test_{timestamp}.mp3
                if (preg_match('/^([A-Z]{4})_test_(\d{4}-\d{2}-\d{2}-\d{6})\.mp3$/', $filename, $matches)) {
                    $callLetters = $matches[1];
                    $timestamp = $matches[2];
                    
                    // Get station ID from call letters
                    global $db;
                    $station = $db->fetchOne("SELECT id FROM stations WHERE call_letters = ?", [$callLetters]);
                    $stationId = $station ? (int)$station['id'] : null;
                }
                // Old format: {station_id}_test_{timestamp}.mp3  
                else if (preg_match('/^(\d+)_test_(\d{4}-\d{2}-\d{2}-\d{6})\.mp3$/', $filename, $matches)) {
                    $stationId = (int)$matches[1];
                    $timestamp = $matches[2];
                    
                    // Get call letters from station ID
                    global $db;
                    $station = $db->fetchOne("SELECT call_letters FROM stations WHERE id = ?", [$stationId]);
                    $callLetters = $station['call_letters'] ?? null;
                }
                
                if ($timestamp && $stationId) {
                    
                    // Convert timestamp to readable format
                    $dateTime = DateTime::createFromFormat('Y-m-d-His', $timestamp);
                    $readableDate = $dateTime ? $dateTime->format('M j, Y g:i A') : $timestamp;
                    
                    $recordings[] = [
                        'filename' => $filename,
                        'station_id' => $stationId,
                        'call_letters' => $callLetters,
                        'timestamp' => $timestamp,
                        'readable_date' => $readableDate,
                        'size' => $size,
                        'size_human' => formatBytes($size),
                        'created' => $created,
                        'url' => '/temp/' . $filename,
                        'download_url' => '/api/test-recordings.php?action=download&file=' . urlencode($filename)
                    ];
                }
            }
        }
        
        // Sort by creation time, newest first
        usort($recordings, function($a, $b) {
            return $b['created'] - $a['created'];
        });
    }
    
    echo json_encode([
        'success' => true,
        'recordings' => $recordings,
        'count' => count($recordings)
    ]);
}

function deleteTestRecording() {
    $filename = $_POST['filename'] ?? '';
    
    if (!$filename) {
        http_response_code(400);
        echo json_encode(['error' => 'Filename required']);
        return;
    }
    
    // Validate filename format for security (support both old and new formats)
    if (!preg_match('/^([A-Z]{4}|\d+)_test_\d{4}-\d{2}-\d{2}-\d{6}\.mp3$/', $filename)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid filename format']);
        return;
    }
    
    $filePath = '/var/radiograb/temp/' . $filename;
    
    if (file_exists($filePath)) {
        if (unlink($filePath)) {
            echo json_encode(['success' => true, 'message' => 'Test recording deleted']);
        } else {
            http_response_code(500);
            echo json_encode(['error' => 'Failed to delete file']);
        }
    } else {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
    }
}

function downloadTestRecording() {
    $filename = $_GET['file'] ?? '';
    
    if (!$filename) {
        http_response_code(400);
        echo json_encode(['error' => 'Filename required']);
        return;
    }
    
    // Validate filename format for security (support both old and new formats)
    if (!preg_match('/^([A-Z]{4}|\d+)_test_\d{4}-\d{2}-\d{2}-\d{6}\.mp3$/', $filename)) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid filename format']);
        return;
    }
    
    $filePath = '/var/radiograb/temp/' . $filename;
    
    if (!file_exists($filePath)) {
        http_response_code(404);
        echo json_encode(['error' => 'File not found']);
        return;
    }
    
    // Set headers for MP3 download
    header('Content-Type: audio/mpeg');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Content-Length: ' . filesize($filePath));
    header('Accept-Ranges: bytes');
    header('Cache-Control: no-cache');
    
    // Output file contents
    readfile($filePath);
    exit;
}

function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('bytes', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}
?>