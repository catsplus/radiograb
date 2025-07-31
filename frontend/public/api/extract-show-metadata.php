<?php
/**
 * Extract Show Metadata API
 * Automatically extracts comprehensive show metadata from calendars and websites
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

$show_name = trim($_POST['show_name'] ?? '');
$station_id = (int)($_POST['station_id'] ?? 0);
$calendar_data = $_POST['calendar_data'] ?? null;

if (!$show_name) {
    echo json_encode(['success' => false, 'error' => 'Show name is required']);
    exit;
}

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
    
    // Prepare Python script execution
    $python_script = dirname(dirname(dirname(__DIR__))) . '/backend/services/show_metadata_extractor.py';
    
    if (!file_exists($python_script)) {
        echo json_encode(['success' => false, 'error' => 'Metadata extraction service not found']);
        exit;
    }
    
    // Build command with proper escaping
    $command_parts = [
        'cd /opt/radiograb',
        'PYTHONPATH=/opt/radiograb',
        '/opt/radiograb/venv/bin/python',
        escapeshellarg($python_script),
        escapeshellarg($show_name),
        escapeshellarg($station['website_url'])
    ];
    
    if ($station_id) {
        $command_parts[] = '--station-id';
        $command_parts[] = escapeshellarg($station_id);
    }
    
    $command_parts[] = '--verbose';
    $command_parts[] = '2>&1';
    
    $command = implode(' ', $command_parts);
    
    // Execute the Python script
    $output = shell_exec($command);
    
    if ($output === null) {
        echo json_encode([
            'success' => false, 
            'error' => 'Failed to execute metadata extraction service'
        ]);
        exit;
    }
    
    // Try to parse JSON output
    $metadata_json = null;
    $lines = explode("\n", trim($output));
    
    // Look for JSON output (usually the last few lines)
    for ($i = count($lines) - 1; $i >= 0; $i--) {
        $line = trim($lines[$i]);
        if ($line && ($line[0] === '{' || $line[0] === '[')) {
            $metadata_json = json_decode($line, true);
            if ($metadata_json !== null) {
                break;
            }
        }
    }
    
    if ($metadata_json === null) {
        // If no JSON found, try to extract useful information from output
        $filtered_output = [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line && !preg_match('/^(INFO|WARNING|ERROR|DEBUG)/', $line)) {
                $filtered_output[] = $line;
            }
        }
        
        echo json_encode([
            'success' => false,
            'error' => 'Could not parse metadata extraction results',
            'debug_output' => implode("\n", $filtered_output),
            'raw_output' => $output
        ]);
        exit;
    }
    
    // Process and enhance the metadata
    $processed_metadata = processExtractedMetadata($metadata_json, $station);
    
    echo json_encode([
        'success' => true,
        'metadata' => $processed_metadata,
        'confidence' => $metadata_json['confidence'] ?? 0.0,
        'source' => $metadata_json['source'] ?? 'unknown'
    ]);
    
} catch (Exception $e) {
    error_log("Show metadata extraction error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'error' => 'Error extracting show metadata: ' . $e->getMessage()
    ]);
}

function processExtractedMetadata($metadata, $station) {
    // Process and validate extracted metadata
    $processed = [
        'title' => sanitizeText($metadata['title'] ?? ''),
        'description' => sanitizeText($metadata['description'] ?? ''),
        'long_description' => sanitizeText($metadata['long_description'] ?? ''),
        'host' => sanitizeText($metadata['host'] ?? ''),
        'genre' => sanitizeText($metadata['genre'] ?? ''),
        'image_url' => validateImageUrl($metadata['image_url'] ?? ''),
        'website_url' => validateUrl($metadata['website_url'] ?? ''),
        'source' => $metadata['source'] ?? 'extracted',
        'confidence' => (float)($metadata['confidence'] ?? 0.0)
    ];
    
    // Apply image fallback logic
    if (!$processed['image_url']) {
        // Fallback to station logo
        $processed['image_url'] = getStationLogo($station);
    }
    
    // Enhance description if missing
    if (!$processed['description'] && $processed['title']) {
        $processed['description'] = "Radio show '{$processed['title']}' from {$station['name']}";
        $processed['source'] = 'enhanced';
    }
    
    return $processed;
}

function sanitizeText($text) {
    if (!$text) return null;
    
    // Remove excessive whitespace and normalize
    $text = preg_replace('/\s+/', ' ', trim($text));
    
    // Remove HTML tags if present
    $text = strip_tags($text);
    
    // Limit length for reasonable storage
    if (strlen($text) > 1000) {
        $text = substr($text, 0, 997) . '...';
    }
    
    return $text;
}

function validateImageUrl($url) {
    if (!$url) return null;
    
    // Basic URL validation
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
        return null;
    }
    
    // Check for reasonable image extensions
    $parsed = parse_url($url);
    $path = $parsed['path'] ?? '';
    
    if (preg_match('/\.(jpg|jpeg|png|gif|webp)$/i', $path)) {
        return $url;
    }
    
    // Allow URLs without extensions (might be dynamic)
    if (!preg_match('/\.[a-z]{2,4}$/i', $path)) {
        return $url;
    }
    
    return null;
}

function validateUrl($url) {
    if (!$url) return null;
    
    return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
}

function getStationLogo($station) {
    // Implement station logo fallback logic
    if (!empty($station['logo_url'])) {
        return $station['logo_url'];
    }
    
    // Check for local logo file
    $call_letters = strtolower($station['call_letters'] ?? '');
    if ($call_letters) {
        $local_logo = "/var/radiograb/logos/{$call_letters}.jpg";
        if (file_exists($local_logo)) {
            return "/logos/{$call_letters}.jpg";
        }
    }
    
    // System default
    return '/assets/images/default-station-logo.png';
}
?>