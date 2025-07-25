<?php
/**
 * RSS Feed API Endpoint
 * Serves RSS feeds for shows and handles feed generation
 */

header('Content-Type: application/rss+xml; charset=utf-8');

require_once '../../includes/database.php';
require_once '../../includes/functions.php';

// Get show ID from URL parameter
$show_id = isset($_GET['show_id']) ? (int)$_GET['show_id'] : null;

if (!$show_id) {
    http_response_code(404);
    echo '<?xml version="1.0" encoding="UTF-8"?><error>Show ID required</error>';
    exit;
}

try {
    // Get show details
    $show = $db->fetchOne("
        SELECT s.*, st.name as station_name, st.logo_url as station_logo 
        FROM shows s 
        JOIN stations st ON s.station_id = st.id 
        WHERE s.id = ?
    ", [$show_id]);
    
    if (!$show) {
        http_response_code(404);
        echo '<?xml version="1.0" encoding="UTF-8"?><error>Show not found</error>';
        exit;
    }
    
    // Get recordings for this show
    $recordings = $db->fetchAll("
        SELECT * FROM recordings 
        WHERE show_id = ? 
        ORDER BY recorded_at DESC 
        LIMIT 50
    ", [$show_id]);
    
    // Generate RSS feed
    generateRSSFeed($show, $recordings);
    
} catch (Exception $e) {
    http_response_code(500);
    echo '<?xml version="1.0" encoding="UTF-8"?><error>Server error</error>';
    exit;
}

function generateRSSFeed($show, $recordings) {
    $base_url = getBaseUrl();
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
    echo '<channel>' . "\n";
    
    // Basic channel info
    echo '<title>' . h($show['name'] . ' - ' . $show['station_name']) . '</title>' . "\n";
    echo '<description>' . h($show['description'] ?: "Recordings of {$show['name']} from {$show['station_name']}") . '</description>' . "\n";
    echo '<link>' . $base_url . '/shows/' . $show['id'] . '</link>' . "\n";
    echo '<language>en-us</language>' . "\n";
    echo '<copyright>© ' . h($show['station_name']) . '</copyright>' . "\n";
    echo '<generator>RadioGrab - Radio TiVo</generator>' . "\n";
    echo '<lastBuildDate>' . date('r') . '</lastBuildDate>' . "\n";
    
    // iTunes specific tags
    echo '<itunes:author>' . h($show['station_name']) . '</itunes:author>' . "\n";
    echo '<itunes:summary>' . h($show['description'] ?: "Recordings of {$show['name']}") . '</itunes:summary>' . "\n";
    echo '<itunes:explicit>no</itunes:explicit>' . "\n";
    echo '<itunes:category text="Arts" />' . "\n";
    
    // Station logo as podcast artwork
    if ($show['station_logo']) {
        echo '<image>' . "\n";
        echo '<url>' . h($show['station_logo']) . '</url>' . "\n";
        echo '<title>' . h($show['name'] . ' - ' . $show['station_name']) . '</title>' . "\n";
        echo '<link>' . $base_url . '/shows/' . $show['id'] . '</link>' . "\n";
        echo '</image>' . "\n";
        
        echo '<itunes:image href="' . h($show['station_logo']) . '" />' . "\n";
    }
    
    // Atom self link
    echo '<atom:link href="' . $base_url . '/api/feeds.php?show_id=' . $show['id'] . '" rel="self" type="application/rss+xml" />' . "\n";
    
    // Recording items
    foreach ($recordings as $recording) {
        generateRecordingItem($recording, $show, $base_url);
    }
    
    echo '</channel>' . "\n";
    echo '</rss>' . "\n";
}

function generateRecordingItem($recording, $show, $base_url) {
    echo '<item>' . "\n";
    
    // Basic item info
    $title = $recording['title'] ?: ($show['name'] . ' - ' . date('Y-m-d', strtotime($recording['recorded_at'])));
    echo '<title>' . h($title) . '</title>' . "\n";
    
    $description = $recording['description'] ?: 
                  "Recording of {$show['name']} from {$show['station_name']} on " . 
                  date('F d, Y', strtotime($recording['recorded_at']));
    echo '<description>' . h($description) . '</description>' . "\n";
    
    // Unique identifier
    echo '<guid isPermaLink="false">radiograb-recording-' . $recording['id'] . '</guid>' . "\n";
    
    // Publication date
    echo '<pubDate>' . date('r', strtotime($recording['recorded_at'])) . '</pubDate>' . "\n";
    
    // Audio enclosure
    if ($recording['filename'] && recordingFileExists($recording['filename'])) {
        $file_url = $base_url . '/recordings/' . $recording['filename'];
        $file_size = $recording['file_size_bytes'] ?: 0;
        $mime_type = getMimeType($recording['filename']);
        
        echo '<enclosure url="' . h($file_url) . '" length="' . $file_size . '" type="' . $mime_type . '" />' . "\n";
        
        // iTunes duration
        if ($recording['duration_seconds']) {
            $duration = formatDurationForRSS($recording['duration_seconds']);
            echo '<itunes:duration>' . $duration . '</itunes:duration>' . "\n";
        }
    }
    
    // iTunes specific tags
    echo '<itunes:author>' . h($show['station_name']) . '</itunes:author>' . "\n";
    echo '<itunes:summary>' . h($description) . '</itunes:summary>' . "\n";
    echo '<itunes:explicit>no</itunes:explicit>' . "\n";
    
    echo '</item>' . "\n";
}

function getMimeType($filename) {
    $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
    $mime_types = [
        'mp3' => 'audio/mpeg',
        'm4a' => 'audio/mp4',
        'wav' => 'audio/wav',
        'ogg' => 'audio/ogg',
        'flac' => 'audio/flac'
    ];
    return $mime_types[$ext] ?? 'audio/mpeg';
}

function formatDurationForRSS($seconds) {
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $secs = $seconds % 60;
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $secs);
}

function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https://' : 'http://';
    $host = $_SERVER['HTTP_HOST'];
    $script = dirname($_SERVER['SCRIPT_NAME']);
    return $protocol . $host . rtrim($script, '/api');
}
?>