<?php
require_once 'database.php';

function getSiteSetting($name, $default = '') {
    global $pdo;
    $stmt = $pdo->prepare("SELECT setting_value FROM site_settings WHERE setting_name = ?");
    $stmt->execute([$name]);
    $result = $stmt->fetchColumn();
    return $result !== false ? $result : $default;
}

function getAllSiteSettings() {
    global $pdo;
    $stmt = $pdo->query("SELECT setting_name, setting_value FROM site_settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
    return $settings;
}

// Load all settings into a global variable to reduce database queries
$site_settings = getAllSiteSettings();

function get_setting($name, $default = '') {
    global $site_settings;
    return isset($site_settings[$name]) ? $site_settings[$name] : $default;
}
?>