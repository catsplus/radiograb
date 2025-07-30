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
            return 'v2.13.0'; // Fallback if no database connection
        }
        
        $stmt = $db->prepare("SELECT version FROM system_info WHERE key_name = 'current_version' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result && !empty($result['version'])) {
            return $result['version'];
        } else {
            return 'v2.13.0'; // Fallback version
        }
        
    } catch (Exception $e) {
        error_log("Error getting version from database: " . $e->getMessage());
        return 'v2.13.0'; // Fallback version
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
                'version' => 'v2.13.0',
                'description' => 'Enhanced calendar discovery system',
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }
        
        $stmt = $db->prepare("SELECT version, description, updated_at FROM system_info WHERE key_name = 'current_version' LIMIT 1");
        $stmt->execute();
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($result) {
            return $result;
        } else {
            return [
                'version' => 'v2.13.0',
                'description' => 'Enhanced calendar discovery system',
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }
        
    } catch (Exception $e) {
        error_log("Error getting version info from database: " . $e->getMessage());
        return [
            'version' => 'v2.13.0',
            'description' => 'Enhanced calendar discovery system',
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
        $stmt = $db->prepare("SELECT id FROM system_info WHERE key_name = 'current_version'");
        $stmt->execute();
        $exists = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($exists) {
            // Update existing
            $stmt = $db->prepare("UPDATE system_info SET version = ?, description = ?, updated_at = NOW() WHERE key_name = 'current_version'");
            $result = $stmt->execute([$version, $description]);
        } else {
            // Insert new
            $stmt = $db->prepare("INSERT INTO system_info (key_name, version, description, created_at, updated_at) VALUES ('current_version', ?, ?, NOW(), NOW())");
            $result = $stmt->execute([$version, $description]);
        }
        
        return $result;
        
    } catch (Exception $e) {
        error_log("Error updating version in database: " . $e->getMessage());
        return false;
    }
}
?>