<?php
/**
 * System Information API
 * Provides system status and configuration information
 */

header('Content-Type: application/json');

// Get system information
$system_info = [
    'server' => [
        'hostname' => gethostname(),
        'php_version' => PHP_VERSION,
        'operating_system' => php_uname(),
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown',
        'server_admin' => $_SERVER['SERVER_ADMIN'] ?? 'Not set'
    ],
    'database' => [
        'status' => 'unknown',
        'version' => 'unknown',
        'host' => $_ENV['DB_HOST'] ?? 'localhost'
    ],
    'directories' => [
        'recordings' => $_ENV['RECORDINGS_DIR'] ?? '/var/radiograb/recordings',
        'feeds' => $_ENV['FEEDS_DIR'] ?? '/var/radiograb/feeds',
        'logs' => $_ENV['LOGS_DIR'] ?? '/var/radiograb/logs',
        'temp' => $_ENV['TEMP_DIR'] ?? '/var/radiograb/temp'
    ],
    'disk_space' => [],
    'services' => [],
    'environment' => [
        'base_url' => $_ENV['RADIOGRAB_BASE_URL'] ?? 'http://localhost',
        'streamripper_path' => $_ENV['STREAMRIPPER_PATH'] ?? '/usr/bin/streamripper'
    ]
];

// Check database connection
try {
    require_once '../../includes/database.php';
    $db_check = $db->fetchOne("SELECT VERSION() as version");
    if ($db_check) {
        $system_info['database']['status'] = 'connected';
        $system_info['database']['version'] = $db_check['version'];
    }
} catch (Exception $e) {
    $system_info['database']['status'] = 'error';
    $system_info['database']['error'] = $e->getMessage();
}

// Check disk space for key directories
foreach ($system_info['directories'] as $name => $path) {
    if (is_dir($path)) {
        $bytes = disk_free_space($path);
        $total_bytes = disk_total_space($path);
        
        if ($bytes !== false && $total_bytes !== false) {
            $system_info['disk_space'][$name] = [
                'free_bytes' => $bytes,
                'total_bytes' => $total_bytes,
                'free_gb' => round($bytes / 1024 / 1024 / 1024, 2),
                'total_gb' => round($total_bytes / 1024 / 1024 / 1024, 2),
                'used_percent' => round((($total_bytes - $bytes) / $total_bytes) * 100, 1)
            ];
        }
    }
}

// Check if key binaries exist
$binaries = [
    'streamripper' => $system_info['environment']['streamripper_path'],
    'python3' => '/usr/bin/python3',
    'nginx' => '/usr/sbin/nginx',
    'php-fpm' => '/usr/sbin/php-fpm8.1'
];

foreach ($binaries as $name => $path) {
    $system_info['services'][$name] = [
        'path' => $path,
        'exists' => file_exists($path),
        'executable' => is_executable($path)
    ];
    
    if ($system_info['services'][$name]['exists'] && $system_info['services'][$name]['executable']) {
        // Try to get version info
        switch ($name) {
            case 'streamripper':
                exec("$path 2>&1 | head -1", $version_output);
                if (!empty($version_output)) {
                    $system_info['services'][$name]['version'] = $version_output[0];
                }
                break;
            case 'python3':
                exec("$path --version 2>&1", $version_output);
                if (!empty($version_output)) {
                    $system_info['services'][$name]['version'] = $version_output[0];
                }
                break;
            case 'nginx':
                exec("$path -v 2>&1", $version_output);
                if (!empty($version_output)) {
                    $system_info['services'][$name]['version'] = $version_output[0];
                }
                break;
        }
    }
}

// Memory information
$system_info['memory'] = [
    'php_memory_limit' => ini_get('memory_limit'),
    'php_memory_usage' => round(memory_get_usage(true) / 1024 / 1024, 2) . ' MB',
    'php_peak_memory' => round(memory_get_peak_usage(true) / 1024 / 1024, 2) . ' MB'
];

// PHP configuration
$system_info['php_config'] = [
    'max_execution_time' => ini_get('max_execution_time'),
    'max_input_time' => ini_get('max_input_time'),
    'upload_max_filesize' => ini_get('upload_max_filesize'),
    'post_max_size' => ini_get('post_max_size'),
    'max_file_uploads' => ini_get('max_file_uploads'),
    'error_reporting' => ini_get('error_reporting'),
    'display_errors' => ini_get('display_errors')
];

// Network information
$system_info['network'] = [
    'server_addr' => $_SERVER['SERVER_ADDR'] ?? 'Unknown',
    'server_port' => $_SERVER['SERVER_PORT'] ?? 'Unknown',
    'https' => isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
    'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
];

// Check if running in Docker
$system_info['docker'] = [
    'in_container' => file_exists('/.dockerenv'),
    'cgroup_info' => is_readable('/proc/1/cgroup') ? file_get_contents('/proc/1/cgroup') : null
];

echo json_encode($system_info, JSON_PRETTY_PRINT);
?>