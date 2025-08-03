<?php
/**
 * API endpoint for transcribing recordings
 * Issue #25 - Transcription system integration
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';

$auth = new UserAuth($db);
requireAuth($auth);

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
    http_response_code(403);
    echo json_encode(['error' => 'Invalid CSRF token']);
    exit;
}

$user_id = $auth->getCurrentUserId();
$recording_id = (int)($_POST['recording_id'] ?? 0);
$provider = $_POST['provider'] ?? null;

if (!$recording_id) {
    http_response_code(400);
    echo json_encode(['error' => 'Recording ID is required']);
    exit;
}

// Verify user owns this recording
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

// Check if already transcribed
if ($recording['transcript_file'] && file_exists($recording['transcript_file'])) {
    echo json_encode([
        'success' => true,
        'message' => 'Recording already transcribed',
        'provider' => $recording['transcript_provider'],
        'generated_at' => $recording['transcript_generated_at'],
        'cost' => $recording['transcript_cost']
    ]);
    exit;
}

// Call Python transcription service
$python_path = '/opt/radiograb/venv/bin/python';
$script_path = '/opt/radiograb/backend/services/transcription_service.py';

$cmd = sprintf(
    'cd /opt/radiograb && PYTHONPATH=/opt/radiograb %s %s --recording-id %d --user-id %d',
    escapeshellarg($python_path),
    escapeshellarg($script_path),
    $recording_id,
    $user_id
);

if ($provider) {
    $cmd .= ' --provider ' . escapeshellarg($provider);
}

$cmd .= ' 2>&1';

// Execute transcription
$output = shell_exec($cmd);
$lines = explode("\n", trim($output));

// Parse output for result
$success = false;
$provider_used = null;
$cost_estimate = 0;
$duration_minutes = 0;
$error_message = null;

foreach ($lines as $line) {
    if (strpos($line, '✅ Transcription completed') !== false) {
        $success = true;
        // Extract provider from line
        if (preg_match('/using (\w+)/', $line, $matches)) {
            $provider_used = $matches[1];
        }
    } elseif (strpos($line, 'Duration:') !== false) {
        if (preg_match('/Duration: ([\d.]+) minutes/', $line, $matches)) {
            $duration_minutes = (float)$matches[1];
        }
    } elseif (strpos($line, 'Estimated cost:') !== false) {
        if (preg_match('/Estimated cost: \$([\d.]+)/', $line, $matches)) {
            $cost_estimate = (float)$matches[1];
        }
    } elseif (strpos($line, '❌ Transcription failed') !== false) {
        $error_message = substr($line, strpos($line, ':') + 2);
    }
}

if ($success) {
    // Refresh recording data to get transcript info
    $stmt->execute([$recording_id, $user_id]);
    $updated_recording = $stmt->fetch(PDO::FETCH_ASSOC);
    
    echo json_encode([
        'success' => true,
        'message' => 'Transcription completed successfully',
        'provider' => $provider_used,
        'duration_minutes' => $duration_minutes,
        'cost_estimate' => $cost_estimate,
        'transcript_file' => $updated_recording['transcript_file'],
        'generated_at' => $updated_recording['transcript_generated_at']
    ]);
} else {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => $error_message ?: 'Transcription failed',
        'output' => $output // Include for debugging
    ]);
}
?>