<?php
/**
 * RadioGrab Playlist Reorder API
 * Handle track reordering for playlists
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
        case 'reorder_tracks':
            handleReorderTracks();
            break;
            
        case 'set_track_number':
            handleSetTrackNumber();
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
} catch (Exception $e) {
    error_log("Playlist reorder API error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Internal server error']);
}

function handleReorderTracks() {
    global $db;
    
    $show_id = intval($_POST['show_id'] ?? 0);
    $track_ids = $_POST['track_ids'] ?? [];
    
    if (!$show_id || !is_array($track_ids)) {
        echo json_encode(['success' => false, 'error' => 'Show ID and track IDs required']);
        return;
    }
    
    // Verify show is a playlist
    $show = $db->fetchOne("SELECT show_type FROM shows WHERE id = ?", [$show_id]);
    if (!$show || $show['show_type'] !== 'playlist') {
        echo json_encode(['success' => false, 'error' => 'Show is not a playlist']);
        return;
    }
    
    try {
        $db->beginTransaction();
        
        // Update track numbers based on new order
        foreach ($track_ids as $index => $recording_id) {
            $new_track_number = $index + 1;
            $db->update(
                'recordings',
                ['track_number' => $new_track_number],
                'id = ? AND show_id = ? AND source_type = ?',
                [$recording_id, $show_id, 'uploaded']
            );
        }
        
        $db->commit();
        
        // Update MP3 metadata for all reordered tracks
        updateMetadataForTracks($track_ids);
        
        echo json_encode([
            'success' => true,
            'message' => 'Track order updated successfully'
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        throw $e;
    }
}

function handleSetTrackNumber() {
    global $db;
    
    $recording_id = intval($_POST['recording_id'] ?? 0);
    $track_number = intval($_POST['track_number'] ?? 0);
    
    if (!$recording_id || $track_number < 1) {
        echo json_encode(['success' => false, 'error' => 'Invalid recording ID or track number']);
        return;
    }
    
    // Get recording and verify it's an upload
    $recording = $db->fetchOne(
        "SELECT r.*, s.show_type FROM recordings r 
         JOIN shows s ON r.show_id = s.id 
         WHERE r.id = ? AND r.source_type = 'uploaded'",
        [$recording_id]
    );
    
    if (!$recording) {
        echo json_encode(['success' => false, 'error' => 'Upload not found']);
        return;
    }
    
    if ($recording['show_type'] !== 'playlist') {
        echo json_encode(['success' => false, 'error' => 'Recording is not in a playlist']);
        return;
    }
    
    try {
        // Check if track number is already taken
        $existing = $db->fetchOne(
            "SELECT id FROM recordings 
             WHERE show_id = ? AND track_number = ? AND id != ? AND source_type = 'uploaded'",
            [$recording['show_id'], $track_number, $recording_id]
        );
        
        if ($existing) {
            // Swap track numbers
            $db->beginTransaction();
            
            // Temporarily set existing track to 0
            $db->update(
                'recordings',
                ['track_number' => 0],
                'id = ?',
                [$existing['id']]
            );
            
            // Set our track to the desired number
            $db->update(
                'recordings',
                ['track_number' => $track_number],
                'id = ?',
                [$recording_id]
            );
            
            // Set the existing track to our old number
            $db->update(
                'recordings',
                ['track_number' => $recording['track_number']],
                'id = ?',
                [$existing['id']]
            );
            
            $db->commit();
            
            // Update metadata for both tracks
            updateMetadataForTracks([$recording_id, $existing['id']]);
            
        } else {
            // Simple update
            $db->update(
                'recordings',
                ['track_number' => $track_number],
                'id = ?',
                [$recording_id]
            );
            
            // Update metadata
            updateMetadataForTracks([$recording_id]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Track number updated successfully'
        ]);
        
    } catch (Exception $e) {
        if ($db->inTransaction()) {
            $db->rollback();
        }
        throw $e;
    }
}

function updateMetadataForTracks($track_ids) {
    // Update MP3 metadata in background using Python service
    foreach ($track_ids as $recording_id) {
        $python_script = dirname(dirname(dirname(__DIR__))) . '/backend/services/mp3_metadata_service.py';
        $command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python {$python_script} --recording-id {$recording_id} > /dev/null 2>&1 &";
        shell_exec($command);
    }
}
?>