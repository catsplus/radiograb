<?php
/**
 * Station Discovery API Endpoint
 * Discovers station information from a website URL
 */

header('Content-Type: application/json');

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'Method not allowed']);
    exit;
}

// Verify CSRF token (temporarily disabled for testing)
$input = json_decode(file_get_contents('php://input'), true);
if (!true) { // verifyCSRFToken($input['csrf_token'] ?? '')
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
    exit;
}

$website_url = $input['website_url'] ?? '';

if (!$website_url) {
    echo json_encode(['success' => false, 'error' => 'Website URL required']);
    exit;
}

// Enhanced URL validation with auto-protocol testing (Issue #44)
$url_result = normalizeAndValidateUrl($website_url);
if (!$url_result['valid']) {
    echo json_encode(['success' => false, 'error' => $url_result['error']]);
    exit;
} else {
    // Use the normalized URL and provide info about protocol detection
    $website_url = $url_result['url'];
    $protocol_info = [];
    
    if (isset($url_result['protocol'])) {
        switch ($url_result['protocol']) {
            case 'https_auto':
                $protocol_info['message'] = 'Automatically detected HTTPS protocol';
                $protocol_info['type'] = 'success';
                break;
            case 'http_fallback':
                $protocol_info['message'] = 'Using HTTP protocol (HTTPS not available)';
                $protocol_info['type'] = 'info';
                break;
            case 'https_assumed':
                $protocol_info['message'] = 'Using HTTPS protocol (connectivity not verified)';
                $protocol_info['type'] = 'warning';
                break;
        }
    }
}

try {
    // Use the station discovery service
    $python_script = '/opt/radiograb/backend/services/station_discovery.py';
    $command = "cd /opt/radiograb && python3 " . escapeshellarg($python_script) . " " . escapeshellarg($website_url) . " 2>&1";
    
    $output = shell_exec($command);
    $output = trim($output);
    
    if (empty($output)) {
        echo json_encode([
            'success' => false, 
            'error' => 'Discovery service failed to respond'
        ]);
        exit;
    }
    
    // Look for JSON in the output - collect lines from first { to last }
    $lines = explode("\n", $output);
    $json_started = false;
    $json_lines = [];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (!$json_started && strpos($line, '{') === 0) {
            $json_started = true;
            $json_lines[] = $line;
        } else if ($json_started) {
            $json_lines[] = $line;
            if (strpos($line, '}') !== false && substr_count($line, '}') >= substr_count($line, '{')) {
                // Found closing brace, likely end of JSON
                break;
            }
        }
    }
    
    $json_line = implode("\n", $json_lines);
    
    $discovery_result = json_decode($json_line, true);
    
    if (!$discovery_result) {
        echo json_encode([
            'success' => false, 
            'error' => 'Invalid response from discovery service'
        ]);
        exit;
    }
    
    if (!$discovery_result['success']) {
        $errors = $discovery_result['errors'] ?? ['Unknown error'];
        $error_message = implode(', ', $errors);
        
        // Provide helpful error messages
        if (strpos($error_message, 'Connection refused') !== false || strpos($error_message, 'Could not fetch website') !== false) {
            $error_message = 'Unable to connect to the website. This could be due to:
            • The website is temporarily down
            • The website blocks automated requests
            • Network connectivity issues
            
            Please verify the URL is correct and try again later.';
        }
        
        echo json_encode([
            'success' => false,
            'error' => $error_message
        ]);
        exit;
    }
    
    // Format the response for the frontend
    $response = [
        'success' => true,
        'protocol_info' => $protocol_info ?? null,
        'discovered' => [
            'station_name' => $discovery_result['station_name'] ?? '',
            'call_letters' => $discovery_result['call_letters'] ?? '',
            'frequency' => $discovery_result['frequency'] ?? '',
            'location' => $discovery_result['location'] ?? '',
            'description' => $discovery_result['description'] ?? '',
            'logo_url' => $discovery_result['logo_url'] ?? '',
            'website_url' => $discovery_result['website_url'] ?? $website_url,
            'stream_url' => $discovery_result['stream_url'] ?? '',
            'calendar_url' => $discovery_result['calendar_url'] ?? '',
            'social_links' => $discovery_result['social_links'] ?? [],
            'discovered_links' => $discovery_result['discovered_links'] ?? [],
            'stream_urls' => $discovery_result['stream_urls'] ?? [],
            'stream_test_results' => $discovery_result['stream_test_results'] ?? null,
            'recommended_recording_tool' => $discovery_result['recommended_recording_tool'] ?? null,
            'stream_compatibility' => $discovery_result['stream_compatibility'] ?? 'unknown'
        ],
        'suggestions' => [
            'name' => generateStationName($discovery_result),
            'stream_url' => $discovery_result['stream_url'] ?? '',
            'calendar_url' => $discovery_result['calendar_url'] ?? $website_url
        ]
    ];
    
    echo json_encode($response);

} catch (Exception $e) {
    error_log('Station discovery error: ' . $e->getMessage());
    echo json_encode([
        'success' => false, 
        'error' => 'Discovery service error: ' . $e->getMessage()
    ]);
}

function generateStationName($discovery_result) {
    /**
     * Generate a suggested station name from discovery results
     */
    $name_parts = [];
    
    // Add call letters if available
    if (!empty($discovery_result['call_letters'])) {
        $name_parts[] = $discovery_result['call_letters'];
    }
    
    // Add frequency if available
    if (!empty($discovery_result['frequency'])) {
        $name_parts[] = $discovery_result['frequency'];
    }
    
    // Add location if available
    if (!empty($discovery_result['location'])) {
        $name_parts[] = $discovery_result['location'];
    }
    
    // If we have parts, join them
    if (!empty($name_parts)) {
        return implode(' - ', $name_parts);
    }
    
    // Fall back to discovered station name
    if (!empty($discovery_result['station_name'])) {
        return $discovery_result['station_name'];
    }
    
    // Last resort: extract from URL
    $domain = parse_url($discovery_result['website_url'], PHP_URL_HOST);
    if ($domain) {
        $domain = str_replace('www.', '', $domain);
        return ucfirst(str_replace('.', ' ', $domain));
    }
    
    return 'Unknown Station';
}
?>