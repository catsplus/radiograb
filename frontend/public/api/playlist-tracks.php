<?php
/**
 * RadioGrab Playlist Tracks API
 * Get tracks for playlist management
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Handle both GET and POST requests
$method = $_SERVER['REQUEST_METHOD'];

if ($method === 'GET') {
    handleGetTracks();
} elseif ($method === 'POST') {
    handlePostRequest();
} else {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

function handleGetTracks() {
    global $db;
    
    $show_id = intval($_GET['show_id'] ?? 0);

    if (!$show_id) {
        echo json_encode(['success' => false, 'error' => 'Show ID required']);
        return;
    }

    try {
        // Verify show is a playlist
        $show = $db->fetchOne("SELECT id, name, show_type FROM shows WHERE id = ?", [$show_id]);
        if (!$show) {
            echo json_encode(['success' => false, 'error' => 'Show not found']);
            return;
        }
        
        if ($show['show_type'] !== 'playlist') {
            echo json_encode(['success' => false, 'error' => 'Show is not a playlist']);
            return;
        }
        
        // Get uploaded tracks ordered by track_number
        $tracks = $db->fetchAll("
            SELECT r.id, r.title, r.description, r.filename, r.duration_seconds, 
                   r.recorded_at, r.track_number, r.original_filename,
                   r.file_size_bytes
            FROM recordings r
            WHERE r.show_id = ? AND r.source_type = 'uploaded'
            ORDER BY COALESCE(r.track_number, 999999), r.recorded_at
        ", [$show_id]);
        
        // Format tracks for frontend
        $formatted_tracks = array_map(function($track) {
            return [
                'id' => intval($track['id']),
                'title' => $track['title'] ?: $track['original_filename'] ?: 'Untitled',
                'description' => $track['description'],
                'filename' => $track['filename'],
                'duration_seconds' => intval($track['duration_seconds'] ?? 0),
                'recorded_at' => $track['recorded_at'],
                'track_number' => intval($track['track_number'] ?? 1),
                'file_size_bytes' => intval($track['file_size_bytes'] ?? 0)
            ];
        }, $tracks);
        
        echo json_encode([
            'success' => true,
            'tracks' => $formatted_tracks,
            'show' => [
                'id' => intval($show['id']),
                'name' => $show['name']
            ]
        ]);
        
    } catch (Exception $e) {
        error_log("Playlist tracks API error: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['success' => false, 'error' => 'Internal server error']);
    }
}

function handlePostRequest() {
    global $db;
    
    // Get JSON input
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!$input) {
        echo json_encode(['success' => false, 'error' => 'Invalid JSON input']);
        return;
    }
    
    // Verify CSRF token
    if (empty($input['csrf_token']) || !verifyCSRFToken($input['csrf_token'])) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        return;
    }
    
    $action = $input['action'] ?? '';
    
    switch ($action) {
        case 'update_order':
            handleUpdateOrder($input);
            break;
            
        default:
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid action']);
    }
}

function handleUpdateOrder($input) {
    global $db;
    
    $updates = $input['updates'] ?? [];
    
    if (empty($updates) || !is_array($updates)) {
        echo json_encode(['success' => false, 'error' => 'Updates required']);
        return;
    }
    
    try {
        $db->beginTransaction();
        
        $updated_count = 0;
        foreach ($updates as $update) {
            $recording_id = intval($update['id'] ?? 0);
            $track_number = intval($update['track_number'] ?? 0);
            
            if ($recording_id && $track_number > 0) {
                $affected = $db->update('recordings', 
                    ['track_number' => $track_number], 
                    'id = ? AND source_type = ?', 
                    [$recording_id, 'uploaded']
                );
                if ($affected) {
                    $updated_count++;
                }
            }
        }
        
        $db->commit();
        
        echo json_encode([
            'success' => true,
            'message' => 'Track order updated successfully',
            'updated_count' => $updated_count
        ]);
        
    } catch (Exception $e) {
        $db->rollback();
        error_log("Error updating track order: " . $e->getMessage());
        echo json_encode(['success' => false, 'error' => 'Failed to update track order']);
    }
}
?>