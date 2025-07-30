<?php
/**
 * RadioGrab - Discover Station Schedule API
 * Fetches and parses a station's program schedule
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');

// CSRF protection
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

$station_id = (int)($_POST['station_id'] ?? 0);

if (!$station_id) {
    echo json_encode(['success' => false, 'error' => 'Station ID is required']);
    exit;
}

try {
    // Get station information
    $station = $db->fetchOne("SELECT id, name, website_url, call_letters FROM stations WHERE id = ?", [$station_id]);
    
    if (!$station) {
        echo json_encode(['success' => false, 'error' => 'Station not found']);
        exit;
    }
    
    if (!$station['website_url']) {
        echo json_encode(['success' => false, 'error' => 'Station has no website URL configured']);
        exit;
    }
    
    // Call the Python calendar parser to discover the schedule
    $python_script = dirname(dirname(dirname(__DIR__))) . '/backend/services/calendar_parser.py';
    $command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python -c \"
import sys
sys.path.append('/opt/radiograb')
from backend.services.calendar_parser import CalendarParser
import json
import logging

# Suppress debug logging for API call
logging.getLogger().setLevel(logging.WARNING)

parser = CalendarParser()
shows = parser.parse_station_schedule('" . escapeshellarg($station['website_url']) . "', " . $station_id . ")

# Convert shows to JSON
show_data = []
for show in shows:
    # Group shows by name to handle multiple airings
    show_dict = {
        'name': show.name,
        'start_time': show.start_time.strftime('%H:%M'),
        'end_time': show.end_time.strftime('%H:%M') if show.end_time else None,
        'days': show.days,
        'description': show.description,
        'host': show.host,
        'genre': show.genre,
        'duration_minutes': show.duration_minutes
    }
    show_data.append(show_dict)

print(json.dumps({'success': True, 'shows': show_data}))
\" 2>&1";
    
    $output = shell_exec($command);
    $lines = explode("\n", trim($output));
    $json_line = end($lines); // Get the last line which should be our JSON
    
    // Try to parse the JSON output
    $result = json_decode($json_line, true);
    
    if (!$result || !isset($result['success']) || !$result['success']) {
        // If parsing failed, return the raw output for debugging
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to parse station schedule',
            'debug_output' => $output
        ]);
        exit;
    }
    
    // Group shows by name to handle multiple airings
    $grouped_shows = [];
    foreach ($result['shows'] as $show) {
        $show_name = $show['name'];
        
        if (!isset($grouped_shows[$show_name])) {
            $grouped_shows[$show_name] = [
                'name' => $show_name,
                'description' => $show['description'],
                'host' => $show['host'],
                'genre' => $show['genre'],
                'airings' => []
            ];
        }
        
        // Add this airing
        $airing = [
            'start_time' => $show['start_time'],
            'end_time' => $show['end_time'],
            'days' => $show['days'],
            'duration_minutes' => $show['duration_minutes']
        ];
        
        $grouped_shows[$show_name]['airings'][] = $airing;
    }
    
    // Sort shows alphabetically
    ksort($grouped_shows);
    $shows_list = array_values($grouped_shows);
    
    echo json_encode([
        'success' => true,
        'station' => [
            'id' => $station['id'],
            'name' => $station['name'],
            'call_letters' => $station['call_letters'],
            'website_url' => $station['website_url']
        ],
        'shows' => $shows_list,
        'count' => count($shows_list)
    ]);
    
} catch (Exception $e) {
    echo json_encode(['success' => false, 'error' => 'Database error: ' . $e->getMessage()]);
}
?>