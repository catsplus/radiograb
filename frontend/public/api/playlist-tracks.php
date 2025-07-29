<?php
/**
 * RadioGrab Playlist Tracks API
 * Get tracks for playlist management
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// Only allow GET requests
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$show_id = intval($_GET['show_id'] ?? 0);

if (!$show_id) {
    echo json_encode(['success' => false, 'error' => 'Show ID required']);
    exit;
}

try {
    // Verify show is a playlist
    $show = $db->fetchOne("SELECT id, name, show_type FROM shows WHERE id = ?", [$show_id]);
    if (!$show) {
        echo json_encode(['success' => false, 'error' => 'Show not found']);
        exit;
    }
    
    if ($show['show_type'] !== 'playlist') {
        echo json_encode(['success' => false, 'error' => 'Show is not a playlist']);
        exit;
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
?>