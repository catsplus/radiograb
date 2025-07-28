<?php
/**
 * RadioGrab - TTL Management API
 * Handles Time-to-Live settings for recordings
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

$action = $_GET['action'] ?? $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'get_recording_ttl':
            getRecordingTTL();
            break;
        case 'update_recording_ttl':
            updateRecordingTTL();
            break;
        case 'update_show_ttl':
            updateShowTTL();
            break;
        case 'get_expired_recordings':
            getExpiredRecordings();
            break;
        case 'get_expiring_soon':
            getExpiringSoon();
            break;
        case 'extend_recording':
            extendRecording();
            break;
        case 'cleanup_expired':
            cleanupExpired();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getRecordingTTL() {
    global $db;
    
    $recording_id = (int)($_GET['recording_id'] ?? 0);
    if (!$recording_id) {
        http_response_code(400);
        echo json_encode(['error' => 'Recording ID required']);
        return;
    }
    
    $recording = $db->fetchOne("
        SELECT r.*, r.ttl_override_days, r.ttl_type, r.expires_at,
               s.retention_days, s.default_ttl_type, s.name as show_name
        FROM recordings r
        JOIN shows s ON r.show_id = s.id
        WHERE r.id = ?
    ", [$recording_id]);
    
    if (!$recording) {
        http_response_code(404);
        echo json_encode(['error' => 'Recording not found']);
        return;
    }
    
    // Calculate effective TTL
    $effective_ttl = $recording['ttl_override_days'] ?? $recording['retention_days'];
    $effective_type = $recording['ttl_type'] ?? $recording['default_ttl_type'] ?? 'days';
    
    echo json_encode([
        'success' => true,
        'recording' => [
            'id' => $recording['id'],
            'filename' => $recording['filename'],
            'recorded_at' => $recording['recorded_at'],
            'expires_at' => $recording['expires_at'],
            'show_name' => $recording['show_name'],
            'ttl_override_days' => $recording['ttl_override_days'],
            'ttl_type' => $effective_type,
            'effective_ttl' => $effective_ttl,
            'show_default_ttl' => $recording['retention_days'],
            'show_default_type' => $recording['default_ttl_type']
        ]
    ]);
}

function updateRecordingTTL() {
    global $db;
    
    $recording_id = (int)($_POST['recording_id'] ?? 0);
    $ttl_value = $_POST['ttl_value'] ?? null;
    $ttl_type = $_POST['ttl_type'] ?? 'days';
    
    if (!$recording_id) {
        echo json_encode(['error' => 'Recording ID required']);
        return;
    }
    
    // Validate TTL type
    $valid_types = ['days', 'weeks', 'months', 'indefinite'];
    if (!in_array($ttl_type, $valid_types)) {
        echo json_encode(['error' => 'Invalid TTL type']);
        return;
    }
    
    // Convert ttl_value based on type
    if ($ttl_type === 'indefinite') {
        $ttl_days = null;
        $expires_at = null;
    } else {
        $ttl_days = (int)$ttl_value;
        if ($ttl_days <= 0) {
            echo json_encode(['error' => 'TTL value must be positive']);
            return;
        }
        
        // Get recording date to calculate expiry
        $recording = $db->fetchOne("SELECT recorded_at FROM recordings WHERE id = ?", [$recording_id]);
        if (!$recording) {
            echo json_encode(['error' => 'Recording not found']);
            return;
        }
        
        $recorded_at = new DateTime($recording['recorded_at']);
        
        // Calculate expiry date
        switch ($ttl_type) {
            case 'days':
                $expires_at = $recorded_at->modify("+{$ttl_days} days")->format('Y-m-d H:i:s');
                break;
            case 'weeks':
                $weeks = $ttl_days;
                $expires_at = $recorded_at->modify("+{$weeks} weeks")->format('Y-m-d H:i:s');
                break;
            case 'months':
                $months = $ttl_days;
                $expires_at = $recorded_at->modify("+{$months} months")->format('Y-m-d H:i:s');
                break;
        }
    }
    
    try {
        $db->update('recordings', [
            'ttl_override_days' => $ttl_days,
            'ttl_type' => $ttl_type,
            'expires_at' => $expires_at
        ], 'id = ?', [$recording_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Recording TTL updated successfully',
            'expires_at' => $expires_at
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to update TTL: ' . $e->getMessage()]);
    }
}

function updateShowTTL() {
    global $db;
    
    $show_id = (int)($_POST['show_id'] ?? 0);
    $retention_days = (int)($_POST['retention_days'] ?? 0);
    $ttl_type = $_POST['ttl_type'] ?? 'days';
    
    if (!$show_id || $retention_days <= 0) {
        echo json_encode(['error' => 'Valid show ID and retention days required']);
        return;
    }
    
    // Validate TTL type
    $valid_types = ['days', 'weeks', 'months', 'indefinite'];
    if (!in_array($ttl_type, $valid_types)) {
        echo json_encode(['error' => 'Invalid TTL type']);
        return;
    }
    
    try {
        // Update show TTL settings
        $db->update('shows', [
            'retention_days' => $retention_days,
            'default_ttl_type' => $ttl_type
        ], 'id = ?', [$show_id]);
        
        // Update recordings that don't have overrides
        $recordings = $db->fetchAll("SELECT id, recorded_at FROM recordings WHERE show_id = ? AND ttl_override_days IS NULL", [$show_id]);
        
        foreach ($recordings as $recording) {
            $recorded_at = new DateTime($recording['recorded_at']);
            
            switch ($ttl_type) {
                case 'days':
                    $expires_at = $recorded_at->modify("+{$retention_days} days")->format('Y-m-d H:i:s');
                    break;
                case 'weeks':
                    $expires_at = $recorded_at->modify("+{$retention_days} weeks")->format('Y-m-d H:i:s');
                    break;
                case 'months':
                    $expires_at = $recorded_at->modify("+{$retention_days} months")->format('Y-m-d H:i:s');
                    break;
                case 'indefinite':
                    $expires_at = null;
                    break;
            }
            
            $db->update('recordings', [
                'expires_at' => $expires_at,
                'ttl_type' => $ttl_type
            ], 'id = ?', [$recording['id']]);
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Show TTL updated successfully',
            'updated_recordings' => count($recordings)
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to update show TTL: ' . $e->getMessage()]);
    }
}

function getExpiredRecordings() {
    global $db;
    
    $expired = $db->fetchAll("
        SELECT r.id, r.filename, r.recorded_at, r.expires_at,
               s.name as show_name, st.name as station_name
        FROM recordings r
        JOIN shows s ON r.show_id = s.id
        JOIN stations st ON s.station_id = st.id
        WHERE r.expires_at IS NOT NULL 
        AND r.expires_at <= NOW()
        ORDER BY r.expires_at ASC
    ");
    
    echo json_encode([
        'success' => true,
        'expired_recordings' => $expired,
        'count' => count($expired)
    ]);
}

function getExpiringSoon() {
    global $db;
    
    $days_ahead = (int)($_GET['days'] ?? 7);
    
    $expiring = $db->fetchAll("
        SELECT r.id, r.filename, r.recorded_at, r.expires_at,
               s.name as show_name, st.name as station_name,
               DATEDIFF(r.expires_at, NOW()) as days_until_expiry
        FROM recordings r
        JOIN shows s ON r.show_id = s.id
        JOIN stations st ON s.station_id = st.id
        WHERE r.expires_at IS NOT NULL 
        AND r.expires_at > NOW()
        AND r.expires_at <= DATE_ADD(NOW(), INTERVAL ? DAY)
        ORDER BY r.expires_at ASC
    ", [$days_ahead]);
    
    echo json_encode([
        'success' => true,
        'expiring_recordings' => $expiring,
        'count' => count($expiring),
        'days_ahead' => $days_ahead
    ]);
}

function extendRecording() {
    global $db;
    
    $recording_id = (int)($_POST['recording_id'] ?? 0);
    $additional_days = (int)($_POST['additional_days'] ?? 0);
    
    if (!$recording_id || $additional_days <= 0) {
        echo json_encode(['error' => 'Valid recording ID and additional days required']);
        return;
    }
    
    try {
        $recording = $db->fetchOne("SELECT expires_at FROM recordings WHERE id = ?", [$recording_id]);
        
        if (!$recording) {
            echo json_encode(['error' => 'Recording not found']);
            return;
        }
        
        if ($recording['expires_at'] === null) {
            echo json_encode(['error' => 'Recording has indefinite TTL, no extension needed']);
            return;
        }
        
        $current_expiry = new DateTime($recording['expires_at']);
        $new_expiry = $current_expiry->modify("+{$additional_days} days")->format('Y-m-d H:i:s');
        
        $db->update('recordings', [
            'expires_at' => $new_expiry
        ], 'id = ?', [$recording_id]);
        
        echo json_encode([
            'success' => true,
            'message' => 'Recording TTL extended successfully',
            'new_expires_at' => $new_expiry
        ]);
        
    } catch (Exception $e) {
        echo json_encode(['error' => 'Failed to extend recording: ' . $e->getMessage()]);
    }
}

function cleanupExpired() {
    // Call Python TTL manager to perform cleanup
    $command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python backend/services/ttl_manager.py --cleanup 2>&1";
    $output = shell_exec($command);
    
    // Parse output for statistics
    $lines = explode("\n", trim($output));
    $stats = [
        'deleted_files' => 0,
        'deleted_records' => 0,
        'errors' => 0
    ];
    
    foreach ($lines as $line) {
        if (strpos($line, 'Deleted expired recording:') !== false) {
            $stats['deleted_files']++;
        }
        if (strpos($line, 'Error') !== false) {
            $stats['errors']++;
        }
    }
    
    echo json_encode([
        'success' => true,
        'message' => 'Cleanup completed',
        'stats' => $stats,
        'output' => $output
    ]);
}
?>