<?php
/**
 * Utility functions for RadioGrab frontend
 */

/**
 * Format file size in human readable format
 */
function formatFileSize($bytes) {
    if ($bytes == 0) return '0 B';
    
    $units = ['B', 'KB', 'MB', 'GB'];
    $unitIndex = 0;
    
    while ($bytes >= 1024 && $unitIndex < count($units) - 1) {
        $bytes /= 1024;
        $unitIndex++;
    }
    
    return round($bytes, 2) . ' ' . $units[$unitIndex];
}

/**
 * Format duration in seconds to human readable format
 */
function formatDuration($seconds) {
    if ($seconds < 60) {
        return $seconds . 's';
    } elseif ($seconds < 3600) {
        return floor($seconds / 60) . 'm ' . ($seconds % 60) . 's';
    } else {
        $hours = floor($seconds / 3600);
        $minutes = floor(($seconds % 3600) / 60);
        return $hours . 'h ' . $minutes . 'm';
    }
}

/**
 * Sanitize output for HTML display
 */
function h($string) {
    return htmlspecialchars($string, ENT_QUOTES, 'UTF-8');
}

/**
 * Get relative time (e.g., "2 hours ago")
 */
function timeAgo($datetime) {
    $time = time() - strtotime($datetime);
    
    if ($time < 60) return 'just now';
    if ($time < 3600) return floor($time/60) . ' minutes ago';
    if ($time < 86400) return floor($time/3600) . ' hours ago';
    if ($time < 2592000) return floor($time/86400) . ' days ago';
    if ($time < 31536000) return floor($time/2592000) . ' months ago';
    
    return floor($time/31536000) . ' years ago';
}

/**
 * Call Python backend service
 */
function callPythonService($service, $method, $data = []) {
    $python_script = __DIR__ . "/../../backend/api/{$service}.py";
    
    if (!file_exists($python_script)) {
        return ['success' => false, 'error' => 'Service not found'];
    }
    
    $input = json_encode(['method' => $method, 'data' => $data]);
    $command = "python3 " . escapeshellarg($python_script) . " " . escapeshellarg($input);
    
    $output = shell_exec($command);
    $result = json_decode($output, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        return ['success' => false, 'error' => 'Invalid response from service'];
    }
    
    return $result;
}

/**
 * Get station logo URL with fallback (prioritizes local storage)
 */
function getStationLogo($station) {
    // Prioritize local logo if available
    if (!empty($station['local_logo_path'])) {
        // Convert local path to web path
        $filename = basename($station['local_logo_path']);
        return "/logos/{$filename}";
    }
    
    // Fallback to original logo URL
    if (!empty($station['logo_url'])) {
        return $station['logo_url'];
    }
    
    // Return default logo
    return '/assets/images/default-station-logo.png';
}

/**
 * Generate social media icons HTML for a station
 */
function generateSocialMediaIcons($station) {
    $html = '';
    
    // Add main website icon
    if (!empty($station['website_url'])) {
        $html .= sprintf(
            '<a href="%s" target="_blank" rel="noopener noreferrer" class="social-icon me-2" title="Website">
                <i class="fas fa-globe" style="color: #6c757d;"></i>
            </a>',
            h($station['website_url'])
        );
    }
    
    // Parse social media links from JSON if available
    $social_links = [];
    if (!empty($station['social_media_links'])) {
        $social_links = json_decode($station['social_media_links'], true) ?: [];
    }
    
    // Define display order for social media platforms
    $platform_order = ['facebook', 'twitter', 'instagram', 'youtube', 'soundcloud', 'spotify', 'linkedin', 'tiktok'];
    
    // Generate icons in preferred order
    foreach ($platform_order as $platform) {
        if (isset($social_links[$platform])) {
            $link_info = $social_links[$platform];
            $html .= sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" class="social-icon me-2" title="%s">
                    <i class="%s" style="color: %s;"></i>
                </a>',
                h($link_info['url']),
                h($link_info['name']),
                h($link_info['icon']),
                h($link_info['color'])
            );
        }
    }
    
    // Add any remaining platforms not in the preferred order
    foreach ($social_links as $platform => $link_info) {
        if (!in_array($platform, $platform_order)) {
            $html .= sprintf(
                '<a href="%s" target="_blank" rel="noopener noreferrer" class="social-icon me-2" title="%s">
                    <i class="%s" style="color: %s;"></i>
                </a>',
                h($link_info['url']),
                h($link_info['name']),
                h($link_info['icon']),
                h($link_info['color'])
            );
        }
    }
    
    return $html;
}

/**
 * Validate URL format
 */
function isValidUrl($url) {
    return filter_var($url, FILTER_VALIDATE_URL) !== false;
}

/**
 * Generate CSRF token
 */
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

/**
 * Verify CSRF token
 */
function verifyCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Extract call letters from station name
 * Gets first 4 alphabetic characters, uppercase
 */
function getStationCallLetters($stationName) {
    return strtoupper(substr(preg_replace('/[^a-zA-Z]/', '', $stationName), 0, 4));
}

/**
 * Flash message system
 */
function setFlashMessage($type, $message) {
    if (!isset($_SESSION['flash_messages'])) {
        $_SESSION['flash_messages'] = [];
    }
    $_SESSION['flash_messages'][] = ['type' => $type, 'message' => $message];
}

function getFlashMessages() {
    $messages = $_SESSION['flash_messages'] ?? [];
    unset($_SESSION['flash_messages']);
    return $messages;
}

/**
 * Redirect with flash message
 */
function redirectWithMessage($url, $type, $message) {
    setFlashMessage($type, $message);
    header("Location: $url");
    exit;
}

/**
 * Check if file exists in recordings directory
 */
function recordingFileExists($filename) {
    if (!$filename) return false;
    $recordings_dir = '/var/radiograb/recordings/';
    return file_exists($recordings_dir . $filename);
}

/**
 * Get recording file path
 */
function getRecordingPath($filename) {
    $recordings_dir = '/var/radiograb/recordings/';
    return $recordings_dir . $filename;
}

/**
 * Get recording file URL
 */
function getRecordingUrl($filename) {
    return '/recordings/' . urlencode($filename);
}

/**
 * Pagination helper
 */
function paginate($total, $perPage = 20, $currentPage = 1) {
    $totalPages = ceil($total / $perPage);
    $currentPage = max(1, min($totalPages, $currentPage));
    $offset = ($currentPage - 1) * $perPage;
    
    return [
        'total' => $total,
        'perPage' => $perPage,
        'currentPage' => $currentPage,
        'totalPages' => $totalPages,
        'offset' => $offset,
        'hasNext' => $currentPage < $totalPages,
        'hasPrev' => $currentPage > 1
    ];
}

/**
 * Get RadioGrab version number from database (with file fallback)
 */
function getVersionNumber() {
    // First try to get from database
    require_once __DIR__ . '/version.php';
    
    $version = getCurrentVersion();
    if ($version && $version !== 'v2.13.0') {
        return $version;
    }
    
    // Fallback to VERSION file if database fails
    $version_file = dirname(dirname(__DIR__)) . '/VERSION';
    if (file_exists($version_file)) {
        $version_content = trim(file_get_contents($version_file));
        // Extract version number (e.g., "v2.5.0") from the content
        if (preg_match('/v\d+\.\d+\.\d+/', $version_content, $matches)) {
            return $matches[0];
        }
    }
    
    return 'v2.13.0'; // Final fallback
}
?>