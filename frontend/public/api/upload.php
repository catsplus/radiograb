<?php
/**
 * RadioGrab Upload API
 * Handle audio file uploads for playlists and shows
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Verify CSRF token
if (empty($_POST['csrf_token']) || !verifyCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'upload_file':
            handleFileUpload();
            break;
            
        case 'upload_url':
            handleUrlUpload();
            break;
            
        case 'create_playlist':
            handleCreatePlaylist();
            break;
            
        case 'upload_playlist_image':
            handlePlaylistImageUpload();
            break;
            
        case 'delete_upload':
            handleDeleteUpload();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Upload API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

function handleFileUpload() {
    $show_id = intval($_POST['show_id'] ?? 0);
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $source_type = trim($_POST['source_type'] ?? 'uploaded');
    
    if (!$show_id) {
        echo json_encode(['success' => false, 'error' => 'Show ID required']);
        return;
    }
    
    if (!isset($_FILES['audio_file']) || $_FILES['audio_file']['error'] !== UPLOAD_ERR_OK) {
        $error_message = getUploadErrorMessage($_FILES['audio_file']['error'] ?? UPLOAD_ERR_NO_FILE);
        echo json_encode(['success' => false, 'error' => $error_message]);
        return;
    }
    
    $uploaded_file = $_FILES['audio_file'];
    $temp_path = $uploaded_file['tmp_name'];
    $original_filename = $uploaded_file['name'];
    
    // Validate file type
    $allowed_types = [
        'audio/mpeg', 'audio/mp3',
        'audio/wav', 'audio/wave',
        'audio/mp4', 'audio/m4a',
        'audio/aac',
        'audio/ogg',
        'audio/flac',
        'audio/webm'  // For voice clips recorded in browser
    ];
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $temp_path);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        echo json_encode(['success' => false, 'error' => 'Unsupported audio format']);
        return;
    }
    
    // Move to permanent temp location for processing
    $temp_dir = '/var/radiograb/temp';
    if (!is_dir($temp_dir)) {
        mkdir($temp_dir, 0755, true);
    }
    
    $temp_filename = uniqid('upload_') . '_' . $original_filename;
    $temp_filepath = $temp_dir . '/' . $temp_filename;
    
    if (!move_uploaded_file($temp_path, $temp_filepath)) {
        echo json_encode(['success' => false, 'error' => 'Failed to process uploaded file']);
        return;
    }
    
    // Use Python upload service to process the file
    $python_script = '/opt/radiograb/backend/services/upload_service.py';
    $escaped_file = escapeshellarg($temp_filepath);
    $escaped_title = escapeshellarg($title);
    $escaped_description = escapeshellarg($description);
    $escaped_original = escapeshellarg($original_filename);
    $escaped_source_type = escapeshellarg($source_type);
    
    $command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python {$python_script} " .
               "--upload {$escaped_file} --show-id {$show_id} --source-type {$escaped_source_type}";
    
    if ($title) {
        $command .= " --title {$escaped_title}";
    }
    if ($description) {
        $command .= " --description {$escaped_description}";
    }
    
    $command .= " 2>&1";
    
    $output = shell_exec($command);
    
    // Clean up temp file
    if (file_exists($temp_filepath)) {
        unlink($temp_filepath);
    }
    
    if ($output === null) {
        error_log("Upload command failed: $command");
        echo json_encode(['success' => false, 'error' => 'Failed to process upload']);
        return;
    }
    
    // Log output for debugging
    error_log("Upload command output: " . $output);
    
    // Check if upload was successful
    if (strpos($output, '✅ Upload successful') !== false) {
        // Extract recording info from output
        preg_match('/Recording ID: (\d+)/', $output, $matches);
        $recording_id = $matches[1] ?? null;
        
        preg_match('/File size: (\d+) bytes/', $output, $matches);
        $file_size = intval($matches[1] ?? 0);
        
        preg_match('/Duration: (\d+) seconds/', $output, $matches);
        $duration = intval($matches[1] ?? 0);
        
        echo json_encode([
            'success' => true,
            'message' => 'File uploaded successfully',
            'recording_id' => $recording_id,
            'file_size' => $file_size,
            'duration' => $duration
        ]);
    } else {
        // Extract error message
        $error = 'Upload failed';
        if (strpos($output, '❌ Upload failed:') !== false) {
            $error = trim(substr($output, strpos($output, '❌ Upload failed:') + 18));
        }
        
        echo json_encode(['success' => false, 'error' => $error]);
    }
}

function handleUrlUpload() {
    $show_id = intval($_POST['show_id'] ?? 0);
    $url = trim($_POST['url'] ?? '');
    $title = trim($_POST['title'] ?? '');
    $description = trim($_POST['description'] ?? '');
    
    if (!$show_id) {
        echo json_encode(['success' => false, 'error' => 'Show ID required']);
        return;
    }
    
    if (!$url) {
        echo json_encode(['success' => false, 'error' => 'URL required']);
        return;
    }
    
    // Validate URL
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        echo json_encode(['success' => false, 'error' => 'Invalid URL format']);
        return;
    }
    
    // Use Python upload service to process the URL
    $python_script = '/opt/radiograb/backend/services/upload_service.py';
    $escaped_url = escapeshellarg($url);
    $escaped_title = escapeshellarg($title);
    $escaped_description = escapeshellarg($description);
    
    $command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python {$python_script} " .
               "--upload-url {$escaped_url} --show-id {$show_id}";
    
    if ($title) {
        $command .= " --title {$escaped_title}";
    }
    if ($description) {
        $command .= " --description {$escaped_description}";
    }
    
    $command .= " 2>&1";
    
    $output = shell_exec($command);
    
    if ($output === null) {
        error_log("URL upload command failed: $command");
        echo json_encode(['success' => false, 'error' => 'Failed to process URL upload']);
        return;
    }
    
    // Log output for debugging
    error_log("URL upload command output: " . $output);
    
    // Check if upload was successful
    if (strpos($output, '✅ Upload successful') !== false) {
        // Extract recording info from output
        preg_match('/Recording ID: (\d+)/', $output, $matches);
        $recording_id = $matches[1] ?? null;
        
        preg_match('/File size: (\d+) bytes/', $output, $matches);
        $file_size = intval($matches[1] ?? 0);
        
        preg_match('/Duration: (\d+) seconds/', $output, $matches);
        $duration = intval($matches[1] ?? 0);
        
        echo json_encode([
            'success' => true,
            'message' => 'URL uploaded successfully',
            'recording_id' => $recording_id,
            'file_size' => $file_size,
            'duration' => $duration
        ]);
    } else {
        // Extract error message
        $error = 'URL upload failed';
        if (strpos($output, '❌ Upload failed:') !== false) {
            $error = trim(substr($output, strpos($output, '❌ Upload failed:') + 18));
        }
        
        echo json_encode(['success' => false, 'error' => $error]);
    }
}

function handlePlaylistImageUpload() {
    $show_id = intval($_POST['show_id'] ?? 0);
    
    if (!$show_id) {
        echo json_encode(['success' => false, 'error' => 'Show ID required']);
        return;
    }
    
    if (!isset($_FILES['image_file']) || $_FILES['image_file']['error'] !== UPLOAD_ERR_OK) {
        $error_message = getUploadErrorMessage($_FILES['image_file']['error'] ?? UPLOAD_ERR_NO_FILE);
        echo json_encode(['success' => false, 'error' => $error_message]);
        return;
    }
    
    $uploaded_file = $_FILES['image_file'];
    $temp_path = $uploaded_file['tmp_name'];
    $original_filename = $uploaded_file['name'];
    
    // Validate file type
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mime_type = finfo_file($finfo, $temp_path);
    finfo_close($finfo);
    
    if (!in_array($mime_type, $allowed_types)) {
        echo json_encode(['success' => false, 'error' => 'Unsupported image format. Use JPEG, PNG, GIF, or WebP.']);
        return;
    }
    
    // Create unique filename
    $extension = strtolower(pathinfo($original_filename, PATHINFO_EXTENSION));
    $filename = 'playlist_' . $show_id . '_' . time() . '.' . $extension;
    
    // Save to playlist images directory
    $upload_dir = '/var/radiograb/playlist_images';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $file_path = $upload_dir . '/' . $filename;
    
    if (!move_uploaded_file($temp_path, $file_path)) {
        echo json_encode(['success' => false, 'error' => 'Failed to save image file']);
        return;
    }
    
    // Update database with image path
    try {
        global $db;
        $db->update('shows', ['image_url' => '/playlist_images/' . $filename], 'id = ?', [$show_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Playlist image uploaded successfully',
            'image_url' => '/playlist_images/' . $filename
        ]);
        
    } catch (Exception $e) {
        // Clean up file if database update fails
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        echo json_encode(['success' => false, 'error' => 'Failed to update playlist image']);
    }
}

function handleCreatePlaylist() {
    $station_id = intval($_POST['station_id'] ?? 0);
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $host = trim($_POST['host'] ?? '');
    $max_size = intval($_POST['max_file_size'] ?? 100);
    
    if (!$station_id || !$name) {
        echo json_encode(['success' => false, 'error' => 'Station ID and name required']);
        return;
    }
    
    // Use Python service to create playlist
    $python_script = '/opt/radiograb/backend/services/upload_service.py';
    $escaped_name = escapeshellarg($name);
    
    $command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python {$python_script} " .
               "--create-playlist {$escaped_name} --station-id {$station_id} 2>&1";
    
    $output = shell_exec($command);
    
    if ($output === null) {
        echo json_encode(['success' => false, 'error' => 'Failed to create playlist']);
        return;
    }
    
    if (strpos($output, '✅ Playlist created') !== false) {
        // Extract show ID
        preg_match('/\(ID: (\d+)\)/', $output, $matches);
        $show_id = $matches[1] ?? null;
        
        echo json_encode([
            'success' => true,
            'message' => 'Playlist created successfully',
            'show_id' => $show_id
        ]);
    } else {
        $error = 'Failed to create playlist';
        if (strpos($output, '❌ Failed to create playlist:') !== false) {
            $error = trim(substr($output, strpos($output, '❌ Failed to create playlist:') + 32));
        }
        
        echo json_encode(['success' => false, 'error' => $error]);
    }
}

function handleDeleteUpload() {
    $recording_id = intval($_POST['recording_id'] ?? 0);
    
    if (!$recording_id) {
        echo json_encode(['success' => false, 'error' => 'Recording ID required']);
        return;
    }
    
    // Verify the recording is an upload and user has permission
    try {
        global $db;
        $recording = $db->fetchOne(
            "SELECT r.*, s.name as show_name FROM recordings r 
             JOIN shows s ON r.show_id = s.id 
             WHERE r.id = ? AND r.source_type = 'uploaded'",
            [$recording_id]
        );
        
        if (!$recording) {
            echo json_encode(['success' => false, 'error' => 'Upload not found']);
            return;
        }
        
        // Delete the file (handle both old and new folder structure)
        $file_path = "/var/radiograb/recordings/" . $recording['filename'];
        if (!file_exists($file_path)) {
            // Try old structure (filename only in root)
            $filename_only = basename($recording['filename']);
            $file_path = "/var/radiograb/recordings/" . $filename_only;
        }
        
        if (file_exists($file_path)) {
            unlink($file_path);
        }
        
        // Delete database record
        $db->delete('recordings', 'id = ?', [$recording_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Upload deleted successfully'
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => 'Failed to delete upload']);
    }
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