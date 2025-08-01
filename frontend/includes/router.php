<?php
/**
 * RadioGrab URL Router
 * Handles friendly URL routing for stations, shows, users, and playlists
 */

class RadioGrabRouter {
    private $db;
    
    public function __construct($database) {
        $this->db = $database;
    }
    
    /**
     * Parse the current request URI and route to appropriate content
     */
    public function route($uri) {
        // Remove query string and leading/trailing slashes
        $path = trim(parse_url($uri, PHP_URL_PATH), '/');
        
        // Handle empty path (homepage)
        if (empty($path)) {
            return ['type' => 'dashboard'];
        }
        
        // Split path into segments
        $segments = explode('/', $path);
        
        // Route based on URL patterns
        switch (count($segments)) {
            case 1:
                return $this->routeSingleSegment($segments[0]);
                
            case 2:
                return $this->routeTwoSegments($segments[0], $segments[1]);
                
            case 3:
                return $this->routeThreeSegments($segments[0], $segments[1], $segments[2]);
                
            default:
                return ['type' => 'not_found'];
        }
    }
    
    /**
     * Route single segment URLs like /weru or /user
     */
    private function routeSingleSegment($segment) {
        // Check if it's a station call letters
        try {
            $station = $this->db->fetchOne(
                "SELECT * FROM stations WHERE LOWER(call_letters) = ? AND status = 'active'", 
                [strtolower($segment)]
            );
            
            if ($station) {
                return [
                    'type' => 'station',
                    'station' => $station
                ];
            }
        } catch (Exception $e) {
            error_log("Router error checking station: " . $e->getMessage());
        }
        
        // Check if it's a system page (preserved existing functionality)
        $systemPages = [
            'shows', 'stations', 'recordings', 'playlists', 'feeds',
            'add-show', 'add-station', 'add-playlist', 'settings'
        ];
        
        if (in_array($segment, $systemPages)) {
            return ['type' => 'system_page', 'page' => $segment];
        }
        
        return ['type' => 'not_found'];
    }
    
    /**
     * Route two segment URLs like /weru/fresh_air or /user/mattbaya
     */
    private function routeTwoSegments($first, $second) {
        // Handle user profiles: /user/username
        if ($first === 'user') {
            try {
                $user = $this->db->fetchOne(
                    "SELECT * FROM users WHERE LOWER(slug) = ? OR LOWER(username) = ?", 
                    [strtolower($second), strtolower($second)]
                );
                
                if ($user) {
                    return [
                        'type' => 'user',
                        'user' => $user
                    ];
                }
            } catch (Exception $e) {
                error_log("Router error checking user: " . $e->getMessage());
            }
        }
        
        // Handle playlist shortcut: /playlist/slug
        if ($first === 'playlist') {
            try {
                $playlist = $this->db->fetchOne(
                    "SELECT s.*, st.name as station_name, st.call_letters 
                     FROM shows s 
                     JOIN stations st ON s.station_id = st.id 
                     WHERE s.show_type = 'playlist' AND LOWER(s.slug) = ?", 
                    [strtolower($second)]
                );
                
                if ($playlist) {
                    return [
                        'type' => 'playlist',
                        'playlist' => $playlist
                    ];
                }
            } catch (Exception $e) {
                error_log("Router error checking playlist: " . $e->getMessage());
            }
        }
        
        // Handle station shows: /call_letters/show_slug
        try {
            $show = $this->db->fetchOne(
                "SELECT s.*, st.name as station_name, st.call_letters, st.logo_url, st.website_url
                 FROM shows s 
                 JOIN stations st ON s.station_id = st.id 
                 WHERE LOWER(st.call_letters) = ? AND LOWER(s.slug) = ? AND st.status = 'active'", 
                [strtolower($first), strtolower($second)]
            );
            
            if ($show) {
                return [
                    'type' => 'show',
                    'show' => $show
                ];
            }
        } catch (Exception $e) {
            error_log("Router error checking show: " . $e->getMessage());
        }
        
        return ['type' => 'not_found'];
    }
    
    /**
     * Route three segment URLs like /user/mattbaya/playlist_name
     */
    private function routeThreeSegments($first, $second, $third) {
        // Handle user playlists: /user/username/playlist_slug
        if ($first === 'user') {
            try {
                $playlist = $this->db->fetchOne(
                    "SELECT s.*, st.name as station_name, st.call_letters, u.username
                     FROM shows s 
                     JOIN stations st ON s.station_id = st.id 
                     JOIN users u ON s.created_by = u.id 
                     WHERE s.show_type = 'playlist' 
                     AND (LOWER(u.slug) = ? OR LOWER(u.username) = ?) 
                     AND LOWER(s.slug) = ?", 
                    [strtolower($second), strtolower($second), strtolower($third)]
                );
                
                if ($playlist) {
                    return [
                        'type' => 'user_playlist',
                        'playlist' => $playlist
                    ];
                }
            } catch (Exception $e) {
                error_log("Router error checking user playlist: " . $e->getMessage());
            }
        }
        
        return ['type' => 'not_found'];
    }
    
    /**
     * Generate a URL-friendly slug from a string
     */
    public static function generateSlug($string) {
        // Convert to lowercase
        $slug = strtolower($string);
        
        // Replace common characters
        $replacements = [
            ' ' => '_',
            '&' => 'and',
            '+' => 'plus',
            '@' => 'at',
            '#' => 'hash',
            '%' => 'percent'
        ];
        
        $slug = str_replace(array_keys($replacements), array_values($replacements), $slug);
        
        // Remove special characters
        $slug = preg_replace('/[^a-z0-9_\-]/', '', $slug);
        
        // Remove multiple underscores/dashes
        $slug = preg_replace('/[_\-]+/', '_', $slug);
        
        // Trim underscores/dashes from ends
        $slug = trim($slug, '_-');
        
        return $slug;
    }
    
    /**
     * Generate canonical URLs for entities
     */
    public static function generateUrl($type, $data) {
        switch ($type) {
            case 'station':
                return '/' . strtolower($data['call_letters']);
                
            case 'show':
                return '/' . strtolower($data['call_letters']) . '/' . $data['slug'];
                
            case 'user':
                return '/user/' . ($data['slug'] ?: strtolower($data['username']));
                
            case 'playlist':
                if (isset($data['username'])) {
                    return '/user/' . strtolower($data['username']) . '/' . $data['slug'];
                } else {
                    return '/playlist/' . $data['slug'];
                }
                
            default:
                return '/';
        }
    }
}
?>