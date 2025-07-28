<?php
/**
 * RadioGrab - Recording Status API
 * Check current recording status and active sessions
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

$action = $_GET['action'] ?? 'current_recordings';

try {
    switch ($action) {
        case 'current_recordings':
            getCurrentRecordings();
            break;
        case 'recording_history':
            getRecordingHistory();
            break;
        default:
            http_response_code(400);
            echo json_encode(['error' => 'Invalid action']);
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => $e->getMessage()]);
}

function getCurrentRecordings() {
    global $db;
    
    $currentRecordings = [];
    
    // Get all active shows with their next recording times
    $shows = $db->fetchAll("
        SELECT s.id, s.name, s.schedule_pattern, s.duration_minutes,
               st.name as station_name, st.call_letters,
               s.schedule_description
        FROM shows s
        JOIN stations st ON s.station_id = st.id
        WHERE s.active = 1
        ORDER BY s.name
    ");
    
    $currentTime = new DateTime();
    $currentTime->setTimezone(new DateTimeZone('America/New_York'));
    
    foreach ($shows as $show) {
        // Check if this show should be recording right now
        $isRecording = isShowCurrentlyRecording($show, $currentTime);
        
        if ($isRecording) {
            $startTime = $isRecording['start_time'];
            $endTime = $isRecording['end_time'];
            $elapsed = $currentTime->getTimestamp() - $startTime->getTimestamp();
            $remaining = $endTime->getTimestamp() - $currentTime->getTimestamp();
            
            $currentRecordings[] = [
                'show_id' => $show['id'],
                'show_name' => $show['name'],
                'station_name' => $show['station_name'],
                'call_letters' => $show['call_letters'],
                'start_time' => $startTime->format('Y-m-d H:i:s'),
                'end_time' => $endTime->format('Y-m-d H:i:s'),
                'duration_minutes' => $show['duration_minutes'],
                'elapsed_seconds' => $elapsed,
                'remaining_seconds' => max(0, $remaining),
                'progress_percent' => min(100, ($elapsed / ($show['duration_minutes'] * 60)) * 100)
            ];
        }
    }
    
    echo json_encode([
        'success' => true,
        'current_recordings' => $currentRecordings,
        'count' => count($currentRecordings),
        'timestamp' => $currentTime->format('Y-m-d H:i:s')
    ]);
}

function isShowCurrentlyRecording($show, $currentTime) {
    // Parse the cron pattern to determine if show should be recording now
    $cronParts = explode(' ', $show['schedule_pattern']);
    if (count($cronParts) !== 5) {
        return false;
    }
    
    list($minute, $hour, $day, $month, $dayOfWeek) = $cronParts;
    
    // Convert cron day of week to PHP day of week
    $currentDayOfWeek = $currentTime->format('w'); // 0 = Sunday, 6 = Saturday
    $currentHour = (int)$currentTime->format('H');
    $currentMinute = (int)$currentTime->format('i');
    
    // Check if current day matches cron day of week
    $dayMatches = false;
    if ($dayOfWeek === '*') {
        $dayMatches = true;
    } elseif (strpos($dayOfWeek, ',') !== false) {
        $days = explode(',', $dayOfWeek);
        $dayMatches = in_array($currentDayOfWeek, $days);
    } elseif (strpos($dayOfWeek, '-') !== false) {
        list($startDay, $endDay) = explode('-', $dayOfWeek);
        if ($startDay <= $endDay) {
            $dayMatches = $currentDayOfWeek >= $startDay && $currentDayOfWeek <= $endDay;
        } else {
            // Range crosses week boundary (like 6-1 for Sat-Mon)
            $dayMatches = $currentDayOfWeek >= $startDay || $currentDayOfWeek <= $endDay;
        }
    } else {
        $dayMatches = $currentDayOfWeek == $dayOfWeek;
    }
    
    if (!$dayMatches) {
        return false;
    }
    
    // Check if current time matches scheduled time
    $scheduleHour = (int)$hour;
    $scheduleMinute = (int)$minute;
    
    // Calculate start and end times for today's recording
    $startTime = clone $currentTime;
    $startTime->setTime($scheduleHour, $scheduleMinute, 0);
    
    $endTime = clone $startTime;
    $endTime->add(new DateInterval('PT' . $show['duration_minutes'] . 'M'));
    
    // Check if current time is within the recording window
    if ($currentTime >= $startTime && $currentTime <= $endTime) {
        return [
            'start_time' => $startTime,
            'end_time' => $endTime
        ];
    }
    
    return false;
}

function getRecordingHistory() {
    global $db;
    
    $limit = (int)($_GET['limit'] ?? 20);
    
    $recentRecordings = $db->fetchAll("
        SELECT r.id, r.filename, r.title, r.recorded_at, r.duration_seconds,
               s.name as show_name, st.name as station_name, st.call_letters
        FROM recordings r
        JOIN shows s ON r.show_id = s.id
        JOIN stations st ON s.station_id = st.id
        WHERE r.recorded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
        ORDER BY r.recorded_at DESC
        LIMIT ?
    ", [$limit]);
    
    echo json_encode([
        'success' => true,
        'recent_recordings' => $recentRecordings,
        'count' => count($recentRecordings)
    ]);
}
?>