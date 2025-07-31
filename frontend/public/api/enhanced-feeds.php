<?php
/**
 * Enhanced RSS Feed API Endpoint
 * Serves all types of RSS feeds: show, station, custom, playlist, and universal feeds
 */

header('Content-Type: application/rss+xml; charset=utf-8');

require_once '../../includes/database.php';
require_once '../../includes/functions.php';

// Get feed parameters
$feed_type = $_GET['type'] ?? 'show';  // show, station, custom, playlist, universal
$feed_id = isset($_GET['id']) ? (int)$_GET['id'] : null;
$feed_slug = $_GET['slug'] ?? null;

try {
    switch ($feed_type) {
        case 'show':
            if (!$feed_id) {
                throwFeedError(400, 'Show ID required');
            }
            generateShowFeed($feed_id);
            break;
            
        case 'station':
            if (!$feed_id) {
                throwFeedError(400, 'Station ID required');
            }
            generateStationFeed($feed_id);
            break;
            
        case 'custom':
            if (!$feed_slug && !$feed_id) {
                throwFeedError(400, 'Custom feed slug or ID required');
            }
            generateCustomFeed($feed_slug, $feed_id);
            break;
            
        case 'playlist':
            if (!$feed_id) {
                throwFeedError(400, 'Playlist show ID required');
            }
            generatePlaylistFeed($feed_id);
            break;
            
        case 'universal':
            if (!$feed_slug) {
                throwFeedError(400, 'Universal feed slug required (all-shows or all-playlists)');
            }
            generateUniversalFeed($feed_slug);
            break;
            
        default:
            throwFeedError(400, 'Invalid feed type');
    }
    
} catch (Exception $e) {
    error_log("Enhanced feeds error: " . $e->getMessage());
    throwFeedError(500, 'Server error');
}

function throwFeedError($code, $message) {
    http_response_code($code);
    echo '<?xml version="1.0" encoding="UTF-8"?><error>' . h($message) . '</error>';
    exit;
}

function generateShowFeed($show_id) {
    global $db;
    
    // Get show details with enhanced feed metadata
    $show = $db->fetchOne("
        SELECT s.*, st.name as station_name, st.logo_url as station_logo, st.call_letters
        FROM shows s 
        JOIN stations st ON s.station_id = st.id 
        WHERE s.id = ?
    ", [$show_id]);
    
    if (!$show) {
        throwFeedError(404, 'Show not found');
    }
    
    // Get recordings with proper ordering
    if ($show['show_type'] === 'playlist') {
        $recordings = $db->fetchAll("
            SELECT * FROM recordings 
            WHERE show_id = ? 
            ORDER BY track_number ASC, recorded_at ASC 
            LIMIT 200
        ", [$show_id]);
    } else {
        $recordings = $db->fetchAll("
            SELECT * FROM recordings 
            WHERE show_id = ? 
            ORDER BY recorded_at DESC 
            LIMIT 50
        ", [$show_id]);
    }
    
    $feed_title = $show['feed_title'] ?: $show['name'];
    $feed_description = $show['feed_description'] ?: $show['description'] ?: "Recordings of {$show['name']} from {$show['station_name']}";
    $feed_image = getFeedImage($show['feed_image_url'], $show['image_url'], $show['station_logo']);
    
    generateRSSXML($feed_title, $feed_description, $feed_image, $recordings, $show);
}

function generateStationFeed($station_id) {
    global $db;
    
    // Get station details
    $station = $db->fetchOne("
        SELECT st.*, sf.custom_title, sf.custom_description, sf.custom_image_url
        FROM stations st
        LEFT JOIN station_feeds sf ON st.id = sf.station_id
        WHERE st.id = ? AND st.status = 'active'
    ", [$station_id]);
    
    if (!$station) {
        throwFeedError(404, 'Station not found');
    }
    
    // Get all recordings from all shows for this station
    $recordings = $db->fetchAll("
        SELECT r.*, s.name as show_name 
        FROM recordings r
        JOIN shows s ON r.show_id = s.id
        WHERE s.station_id = ? AND s.active = 1 
        AND s.show_type != 'playlist'
        ORDER BY r.recorded_at DESC 
        LIMIT 100
    ", [$station_id]);
    
    $feed_title = $station['custom_title'] ?: "{$station['name']} - All Shows";
    $feed_description = $station['custom_description'] ?: "All radio shows and recordings from {$station['name']}";
    $feed_image = getFeedImage($station['custom_image_url'], null, $station['logo_url']);
    
    generateRSSXML($feed_title, $feed_description, $feed_image, $recordings, $station);
}

function generateCustomFeed($slug, $feed_id = null) {
    global $db;
    
    // Get custom feed details
    $where_clause = $slug ? "slug = ?" : "id = ?";
    $param = $slug ?: $feed_id;
    
    $custom_feed = $db->fetchOne("
        SELECT * FROM custom_feeds 
        WHERE $where_clause AND is_public = 1
    ", [$param]);
    
    if (!$custom_feed) {
        throwFeedError(404, 'Custom feed not found');
    }
    
    // Get recordings from selected shows
    $recordings = $db->fetchAll("
        SELECT r.*, s.name as show_name, st.name as station_name
        FROM recordings r
        JOIN shows s ON r.show_id = s.id
        JOIN stations st ON s.station_id = st.id
        JOIN custom_feed_shows cfs ON s.id = cfs.show_id
        WHERE cfs.custom_feed_id = ? AND s.active = 1
        ORDER BY r.recorded_at DESC 
        LIMIT 100
    ", [$custom_feed['id']]);
    
    $feed_title = $custom_feed['custom_title'] ?: $custom_feed['name'];
    $feed_description = $custom_feed['custom_description'] ?: $custom_feed['description'];
    $feed_image = getFeedImage($custom_feed['custom_image_url'], null, null);
    
    generateRSSXML($feed_title, $feed_description, $feed_image, $recordings, $custom_feed);
}

function generatePlaylistFeed($show_id) {
    global $db;
    
    // Get playlist show details
    $show = $db->fetchOne("
        SELECT s.*, st.name as station_name, st.logo_url as station_logo
        FROM shows s 
        JOIN stations st ON s.station_id = st.id 
        WHERE s.id = ? AND s.show_type = 'playlist'
    ", [$show_id]);
    
    if (!$show) {
        throwFeedError(404, 'Playlist not found');
    }
    
    // Get playlist tracks in manual order
    $recordings = $db->fetchAll("
        SELECT * FROM recordings 
        WHERE show_id = ? 
        ORDER BY track_number ASC, recorded_at ASC
    ", [$show_id]);
    
    $feed_title = $show['feed_title'] ?: $show['name'];
    $feed_description = $show['feed_description'] ?: "User-created playlist: {$show['name']}";
    $feed_image = getFeedImage($show['feed_image_url'], $show['image_url'], null);
    
    generateRSSXML($feed_title, $feed_description, $feed_image, $recordings, $show);
}

function generateUniversalFeed($slug) {
    global $db;
    
    if ($slug === 'all-shows') {
        // All radio show recordings (exclude playlists)
        $recordings = $db->fetchAll("
            SELECT r.*, s.name as show_name, st.name as station_name
            FROM recordings r
            JOIN shows s ON r.show_id = s.id
            JOIN stations st ON s.station_id = st.id
            WHERE s.active = 1 AND s.show_type != 'playlist' AND r.source_type != 'uploaded'
            ORDER BY r.recorded_at DESC 
            LIMIT 200
        ");
        
        $feed_title = "RadioGrab - All Shows";
        $feed_description = "Complete collection of all radio show recordings from all stations";
        
    } elseif ($slug === 'all-playlists') {
        // All playlist tracks
        $recordings = $db->fetchAll("
            SELECT r.*, s.name as show_name
            FROM recordings r
            JOIN shows s ON r.show_id = s.id
            WHERE s.show_type = 'playlist' AND s.active = 1
            ORDER BY s.name ASC, r.track_number ASC, r.recorded_at ASC
            LIMIT 500
        ");
        
        $feed_title = "RadioGrab - All Playlists";
        $feed_description = "Complete collection of all user-created playlist tracks";
        
    } else {
        throwFeedError(404, 'Universal feed not found');
    }
    
    $feed_image = getFeedImage(null, null, '/assets/images/radiograb-logo.png');
    
    generateRSSXML($feed_title, $feed_description, $feed_image, $recordings, ['name' => 'RadioGrab']);
}

function getFeedImage($custom_image, $show_image, $station_image) {
    // Implement fallback logic: Custom → Show → Station → Default
    if ($custom_image) return $custom_image;
    if ($show_image) return $show_image;
    if ($station_image) return $station_image;
    return getBaseUrl() . '/assets/images/default-podcast-artwork.png';
}

function generateRSSXML($title, $description, $image_url, $recordings, $source_data) {
    $base_url = getBaseUrl();
    
    echo '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
    echo '<rss version="2.0" xmlns:itunes="http://www.itunes.com/dtds/podcast-1.0.dtd" xmlns:content="http://purl.org/rss/1.0/modules/content/" xmlns:atom="http://www.w3.org/2005/Atom">' . "\n";
    echo '<channel>' . "\n";
    
    // Basic channel info
    echo '<title>' . h($title) . '</title>' . "\n";
    echo '<description>' . h($description) . '</description>' . "\n";
    echo '<link>' . $base_url . '</link>' . "\n";
    echo '<language>en-us</language>' . "\n";
    echo '<copyright>© RadioGrab</copyright>' . "\n";
    echo '<generator>RadioGrab - Enhanced RSS Feed System</generator>' . "\n";
    echo '<lastBuildDate>' . date('r') . '</lastBuildDate>' . "\n";
    
    // iTunes specific tags
    $author = $source_data['feed_author'] ?? $source_data['host'] ?? $source_data['station_name'] ?? $source_data['name'] ?? 'RadioGrab';
    echo '<itunes:author>' . h($author) . '</itunes:author>' . "\n";
    echo '<itunes:summary>' . h($description) . '</itunes:summary>' . "\n";
    
    $explicit = $source_data['feed_explicit'] ?? 'no';
    echo '<itunes:explicit>' . h($explicit) . '</itunes:explicit>' . "\n";
    
    $category = $source_data['feed_category'] ?? 'Arts';
    echo '<itunes:category text="' . h($category) . '" />' . "\n";
    
    // Feed artwork
    if ($image_url) {
        echo '<image>' . "\n";
        echo '<url>' . h($image_url) . '</url>' . "\n";
        echo '<title>' . h($title) . '</title>' . "\n";
        echo '<link>' . $base_url . '</link>' . "\n";
        echo '</image>' . "\n";
        
        echo '<itunes:image href="' . h($image_url) . '" />' . "\n";
    }
    
    // Self-referencing link (will be updated by specific feed functions)
    echo '<atom:link href="' . h($_SERVER['REQUEST_URI']) . '" rel="self" type="application/rss+xml" />' . "\n";
    
    // Recording items
    foreach ($recordings as $recording) {
        generateRecordingItem($recording, $source_data, $base_url);
    }
    
    echo '</channel>' . "\n";
    echo '</rss>' . "\n";
}

function generateRecordingItem($recording, $source_data, $base_url) {
    $title = $recording['title'] ?: $recording['show_name'] ?: 'Recording';
    $description = $recording['description'] ?: "Recorded on " . date('F j, Y', strtotime($recording['recorded_at']));
    
    echo '<item>' . "\n";
    echo '<title>' . h($title) . '</title>' . "\n";
    echo '<description>' . h($description) . '</description>' . "\n";
    echo '<pubDate>' . date('r', strtotime($recording['recorded_at'])) . '</pubDate>' . "\n";
    echo '<guid isPermaLink="false">recording-' . $recording['id'] . '</guid>' . "\n";
    
    // Audio enclosure
    if ($recording['filename'] && recordingFileExists($recording['filename'])) {
        $audio_url = getRecordingUrl($recording['filename']);
        $file_size = $recording['file_size_bytes'] ?: 0;
        
        echo '<enclosure url="' . h($audio_url) . '" length="' . $file_size . '" type="audio/mpeg" />' . "\n";
    }
    
    // iTunes specific tags
    echo '<itunes:duration>' . formatDurationForRSS($recording['duration_seconds']) . '</itunes:duration>' . "\n";
    echo '<itunes:explicit>no</itunes:explicit>' . "\n";
    
    echo '</item>' . "\n";
}

function formatDurationForRSS($seconds) {
    if (!$seconds) return '00:00:00';
    
    $hours = floor($seconds / 3600);
    $minutes = floor(($seconds % 3600) / 60);
    $seconds = $seconds % 60;
    
    return sprintf('%02d:%02d:%02d', $hours, $minutes, $seconds);
}
?>