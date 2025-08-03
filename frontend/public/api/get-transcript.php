<?php
/**
 * API endpoint for retrieving transcript content
 * Issue #25 - Transcription system integration
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

$auth = new UserAuth($db);
requireAuth($auth);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$user_id = $auth->getCurrentUserId();
$recording_id = (int)($_GET['recording_id'] ?? 0);

if (!$recording_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Recording ID is required']);
    exit;
}

// Verify user owns this recording and get transcript info
$stmt = $db->prepare("
    SELECT r.*, s.name as show_name, st.name as station_name 
    FROM recordings r 
    JOIN shows s ON r.show_id = s.id 
    JOIN stations st ON s.station_id = st.id 
    WHERE r.id = ? AND st.user_id = ?
");
$stmt->execute([$recording_id, $user_id]);
$recording = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$recording) {
    http_response_code(404);
    echo json_encode(['error' => 'Recording not found or access denied']);
    exit;
}

// Check if transcript exists
if (!$recording['transcript_file'] || !file_exists($recording['transcript_file'])) {
    echo json_encode([
        'success' => false,
        'error' => 'No transcript available for this recording',
        'has_transcript' => false
    ]);
    exit;
}

// Read transcript content
$transcript_content = file_get_contents($recording['transcript_file']);

if ($transcript_content === false) {
    http_response_code(500);
    echo json_encode(['error' => 'Failed to read transcript file']);
    exit;
}

echo json_encode([
    'success' => true,
    'recording_id' => $recording_id,
    'show_name' => $recording['show_name'],
    'station_name' => $recording['station_name'],
    'transcript' => $transcript_content,
    'provider' => $recording['transcript_provider'],
    'generated_at' => $recording['transcript_generated_at'],
    'cost' => $recording['transcript_cost'],
    'word_count' => str_word_count($transcript_content),
    'character_count' => mb_strlen($transcript_content)
]);
?>