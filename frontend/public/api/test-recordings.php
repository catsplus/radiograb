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
                
                // Parse filename to extract info (format: {station_id}_test_{timestamp}.mp3)
                if (preg_match('/^(\d+)_test_(\d{4}-\d{2}-\d{2}-\d{6})\.mp3$/', $filename, $matches)) {
                    $stationId = (int)$matches[1];
                    $timestamp = $matches[2];
                    
                    // Convert timestamp to readable format
                    $dateTime = DateTime::createFromFormat('Y-m-d-His', $timestamp);
                    $readableDate = $dateTime ? $dateTime->format('M j, Y g:i A') : $timestamp;
                    
                    $recordings[] = [
                        'filename' => $filename,
                        'station_id' => $stationId,
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
    
    // Validate filename format for security
    if (!preg_match('/^\d+_test_\d{4}-\d{2}-\d{2}-\d{6}\.mp3$/', $filename)) {
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

function formatBytes($size, $precision = 2) {
    $base = log($size, 1024);
    $suffixes = array('bytes', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}
?>