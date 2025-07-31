<?php
require_once 'database.php';

function getSiteSetting($name, $default = '') {
    global $pdo;
    
    // Handle case where database connection is not available
    if (!$pdo) {
        error_log("PDO connection not available in getSiteSetting()");
        return $default;
    }
    
    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_name = ?");
        $stmt->execute([$name]);
        $result = $stmt->fetchColumn();
        return $result !== false ? $result : $default;
    } catch (PDOException $e) {
        error_log("Error fetching site setting '$name': " . $e->getMessage());
        return $default;
    }
}

function getAllSiteSettings() {
    global $pdo;
    
    // Handle case where database connection is not available
    if (!$pdo) {
        error_log("PDO connection not available in getAllSiteSettings()");
        return [];
    }
    
    try {
        $stmt = $pdo->query("SELECT setting_name, setting_value FROM site_settings");
        $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        return $settings;
    } catch (PDOException $e) {
        error_log("Error fetching site settings: " . $e->getMessage());
        return [];
    }
}

// Load all settings into a global variable to reduce database queries
$site_settings = getAllSiteSettings();

function get_setting($name, $default = '') {
    global $site_settings;
    return isset($site_settings[$name]) ? $site_settings[$name] : $default;
}
?>