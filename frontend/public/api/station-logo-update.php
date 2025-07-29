<?php
/**
 * Station Logo and Social Media Update API
 * Updates station logos and social media links using discovery services
 */

require_once '../../includes/database.php';
require_once '../../includes/functions.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// CSRF protection for POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    session_start();
    
    $input = json_decode(file_get_contents('php://input'), true);
    $csrf_token = $input['csrf_token'] ?? $_POST['csrf_token'] ?? '';
    
    if (!verifyCSRFToken($csrf_token)) {
        http_response_code(403);
        echo json_encode(['success' => false, 'error' => 'Invalid CSRF token']);
        exit;
    }
}

try {
    $action = $_GET['action'] ?? $input['action'] ?? '';
    
    switch ($action) {
        case 'update_station_logos':
            updateStationLogos();
            break;
            
        case 'update_single_station':
            $station_id = $input['station_id'] ?? 0;
            updateSingleStation($station_id);
            break;
            
        case 'get_logo_update_status':
            getLogoUpdateStatus();
            break;
            
        default:
            throw new Exception('Invalid action');
    }
    
} catch (Exception $e) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => $e->getMessage()
    ]);
}

function updateStationLogos() {
    global $db;
    
    try {
        // Get all active stations that need logo updates
        $stations = $db->fetchAll("
            SELECT id, name, website_url, logo_url, local_logo_path, logo_source, 
                   social_media_links, social_media_updated_at
            FROM stations 
            WHERE status = 'active'
            ORDER BY id
        ");
        
        $results = [];
        $updated_count = 0;
        
        foreach ($stations as $station) {
            $station_id = $station['id'];
            $website_url = $station['website_url'];
            
            // Skip if no website URL
            if (empty($website_url)) {
                $results[] = [
                    'station_id' => $station_id,
                    'name' => $station['name'],
                    'status' => 'skipped',
                    'reason' => 'No website URL'
                ];
                continue;
            }
            
            try {
                // Call Python station discovery service
                $update_result = callStationDiscoveryService($station_id, $website_url);
                
                if ($update_result['success']) {
                    $updated_count++;
                    $results[] = [
                        'station_id' => $station_id,
                        'name' => $station['name'],
                        'status' => 'updated',
                        'logo_source' => $update_result['logo_source'] ?? 'none',
                        'social_links_count' => count($update_result['social_links'] ?? [])
                    ];
                } else {
                    $results[] = [
                        'station_id' => $station_id,
                        'name' => $station['name'],
                        'status' => 'failed',
                        'error' => $update_result['error'] ?? 'Unknown error'
                    ];
                }
                
                // Add delay to avoid overwhelming the server
                usleep(500000); // 0.5 second delay
                
            } catch (Exception $e) {
                $results[] = [
                    'station_id' => $station_id,
                    'name' => $station['name'],
                    'status' => 'error',
                    'error' => $e->getMessage()
                ];
            }
        }
        
        echo json_encode([
            'success' => true,
            'message' => "Updated {$updated_count} out of " . count($stations) . " stations",
            'updated_count' => $updated_count,
            'total_count' => count($stations),
            'results' => $results
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Failed to update station logos: " . $e->getMessage());
    }
}

function updateSingleStation($station_id) {
    global $db;
    
    if (!$station_id) {
        throw new Exception('Station ID is required');
    }
    
    try {
        // Get station information
        $station = $db->fetchOne("
            SELECT id, name, website_url, logo_url, local_logo_path, logo_source
            FROM stations 
            WHERE id = ?
        ", [$station_id]);
        
        if (!$station) {
            throw new Exception('Station not found');
        }
        
        if (empty($station['website_url'])) {
            throw new Exception('Station has no website URL');
        }
        
        // Call Python station discovery service
        $result = callStationDiscoveryService($station_id, $station['website_url']);
        
        if (!$result['success']) {
            throw new Exception($result['error'] ?? 'Discovery service failed');
        }
        
        echo json_encode([
            'success' => true,
            'message' => 'Station updated successfully',
            'station_id' => $station_id,
            'station_name' => $station['name'],
            'logo_source' => $result['logo_source'] ?? 'none',
            'social_links_count' => count($result['social_links'] ?? []),
            'data' => $result
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Failed to update station: " . $e->getMessage());
    }
}

function getLogoUpdateStatus() {
    global $db;
    
    try {
        // Get statistics about logo and social media status
        $stats = $db->fetchOne("
            SELECT 
                COUNT(*) as total_stations,
                COUNT(CASE WHEN local_logo_path IS NOT NULL THEN 1 END) as local_logos,
                COUNT(CASE WHEN logo_url IS NOT NULL THEN 1 END) as remote_logos,
                COUNT(CASE WHEN social_media_links IS NOT NULL THEN 1 END) as social_media,
                COUNT(CASE WHEN logo_source = 'facebook' THEN 1 END) as facebook_logos,
                COUNT(CASE WHEN logo_source = 'website' THEN 1 END) as website_logos,
                COUNT(CASE WHEN logo_source IS NULL THEN 1 END) as no_logos
            FROM stations 
            WHERE status = 'active'
        ");
        
        // Get recent updates
        $recent_updates = $db->fetchAll("
            SELECT id, name, logo_source, logo_updated_at, social_media_updated_at
            FROM stations 
            WHERE status = 'active' 
              AND (logo_updated_at IS NOT NULL OR social_media_updated_at IS NOT NULL)
            ORDER BY GREATEST(COALESCE(logo_updated_at, '1970-01-01'), 
                            COALESCE(social_media_updated_at, '1970-01-01')) DESC
            LIMIT 10
        ");
        
        echo json_encode([
            'success' => true,
            'stats' => $stats,
            'recent_updates' => $recent_updates
        ]);
        
    } catch (Exception $e) {
        throw new Exception("Failed to get update status: " . $e->getMessage());
    }
}

function callStationDiscoveryService($station_id, $website_url) {
    // Use the virtual environment Python path
    $python_path = '/opt/radiograb/venv/bin/python';
    $script_path = '/opt/radiograb/backend/services/station_discovery.py';
    
    // Prepare the command
    $command = sprintf(
        'cd /opt/radiograb && PYTHONPATH=/opt/radiograb %s %s --station-id %d --website-url %s --update-database 2>&1',
        escapeshellarg($python_path),
        escapeshellarg($script_path),
        intval($station_id),
        escapeshellarg($website_url)
    );
    
    // Execute the command
    $output = shell_exec($command);
    
    // Try to parse JSON output
    $result = json_decode($output, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        // If not valid JSON, return error with the raw output
        return [
            'success' => false,
            'error' => 'Invalid response from discovery service',
            'raw_output' => $output
        ];
    }
    
    return $result;
}
?>