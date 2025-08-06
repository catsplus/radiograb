<?php
/**
 * RadioGrab Version Management
 * Dynamic version retrieval from database
 */

/**
 * Get current system version from database
 * @return string Current version or fallback version
 */
function getCurrentVersion() {
    global $db;
    
    try {
        if (!$db) {
            return 'v3.19.0'; // Fallback if no database connection
        }
        
        $result = $db->fetchOne("SELECT version FROM system_info WHERE key_name = 'current_version' LIMIT 1");
        
        if ($result && !empty($result['version'])) {
            return $result['version'];
        } else {
            return 'v3.19.0'; // Fallback version
        }
        
    } catch (Exception $e) {
        error_log("Error getting version from database: " . $e->getMessage());
        return 'v3.19.0'; // Fallback version
    }
}

/**
 * Get version with description
 * @return array Version info with description
 */
function getVersionInfo() {
    global $db;
    
    try {
        if (!$db) {
            return [
                'version' => 'v3.19.0',
                'description' => 'Enhanced Station Stream Discovery with Radio Browser API Integration',
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }
        
        $result = $db->fetchOne("SELECT version, description, updated_at FROM system_info WHERE key_name = 'current_version' LIMIT 1");
        
        if ($result) {
            return $result;
        } else {
            return [
                'version' => 'v3.19.0',
                'description' => 'Enhanced Station Stream Discovery with Radio Browser API Integration',
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }
        
    } catch (Exception $e) {
        error_log("Error getting version info from database: " . $e->getMessage());
        return [
            'version' => 'v3.8.0',
            'description' => 'Playlist upload system and dedicated forms',
            'updated_at' => date('Y-m-d H:i:s')
        ];
    }
}

/**
 * Update system version
 * @param string $version New version
 * @param string $description Version description
 * @return bool Success status
 */
function updateVersion($version, $description = '') {
    global $db;
    
    try {
        if (!$db) {
            return false;
        }
        
        // Check if version entry exists
        $exists = $db->fetchOne("SELECT id FROM system_info WHERE key_name = 'current_version'");
        
        if ($exists) {
            // Update existing
            $result = $db->update('system_info', [
                'version' => $version,
                'description' => $description,
                'updated_at' => date('Y-m-d H:i:s')
            ], "key_name = ?", ['current_version']);
        } else {
            // Insert new
            $result = $db->insert('system_info', [
                'key_name' => 'current_version',
                'version' => $version,
                'description' => $description,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ]);
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error updating version in database: " . $e->getMessage());
        return false;
    }
}
?>