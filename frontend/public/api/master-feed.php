<?php
/**
 * Master RSS Feed API Endpoint
 * Serves the combined RSS feed for all shows
 */

header('Content-Type: application/rss+xml; charset=utf-8');

require_once '../../includes/database.php';
require_once '../../includes/functions.php';

try {
    // Check if master feed file exists
    $feeds_dir = '/var/radiograb/feeds';
    $master_feed_path = $feeds_dir . '/master.xml';
    
    if (!file_exists($master_feed_path)) {
        // Try to generate master feed
        $python_script = dirname(dirname(dirname(__DIR__))) . '/backend/services/rss_manager.py';
        $command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python $python_script --action update-master 2>&1";
        $output = shell_exec($command);
        
        // Check if generation was successful
        if (!file_exists($master_feed_path)) {
            http_response_code(500);
            echo '<?xml version="1.0" encoding="UTF-8"?><error>Master feed not available</error>';
            exit;
        }
    }
    
    // Serve the master feed
    $feed_content = file_get_contents($master_feed_path);
    
    // Set appropriate headers
    header('Cache-Control: public, max-age=300'); // 5 minutes cache
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', filemtime($master_feed_path)) . ' GMT');
    
    echo $feed_content;
    
} catch (Exception $e) {
    http_response_code(500);
    echo '<?xml version="1.0" encoding="UTF-8"?><error>Server error</error>';
    exit;
}
?>