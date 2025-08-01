<?php
/**
 * RadioGrab - Obfuscated Recording Access API
 * Provides access to recording files through obfuscated paths for DMCA compliance
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    exit('Method not allowed');
}

$token = $_GET['token'] ?? '';

if (!$token) {
    http_response_code(400);
    exit('Token required');
}

// Decode the obfuscated path
function deobfuscatePath($obfuscated) {
    $cleaned = str_replace(['-', '_'], ['+', '/'], $obfuscated);
    // Add padding if needed
    while (strlen($cleaned) % 4) {
        $cleaned .= '=';
    }
    try {
        return base64_decode($cleaned);
    } catch (Exception $e) {
        return null;
    }
}

$filepath = deobfuscatePath($token);

if (!$filepath) {
    http_response_code(400);
    exit('Invalid token');
}

// Security: Ensure the file path is within the recordings directory
$recordings_dir = '/var/radiograb/recordings/';
$full_path = $recordings_dir . basename($filepath);

// Additional security: verify the recording exists in database
try {
    $filename = basename($filepath);
    $recording = $db->fetchOne("
        SELECT r.*, s.stream_only, s.name as show_name 
        FROM recordings r 
        JOIN shows s ON r.show_id = s.id 
        WHERE r.filename = ?
    ", [$filename]);
    
    if (!$recording) {
        http_response_code(404);
        exit('Recording not found');
    }
    
    // Check if the show is stream-only
    if ($recording['stream_only']) {
        http_response_code(403);
        exit('Download not allowed for stream-only content');
    }
    
    // Check if file exists
    if (!file_exists($full_path)) {
        http_response_code(404);
        exit('File not found');
    }
    
    // Log access for monitoring
    error_log("Recording access: {$recording['show_name']} - {$filename} - IP: {$_SERVER['REMOTE_ADDR']}");
    
    // Serve the file
    header('Content-Type: audio/mpeg');
    header('Content-Length: ' . filesize($full_path));
    header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
    header('Accept-Ranges: bytes');
    
    // Use readfile for efficiency
    readfile($full_path);
    
} catch (Exception $e) {
    error_log("Recording access error: " . $e->getMessage());
    http_response_code(500);
    exit('Server error');
}
?>