<?php
header('Content-Type: application/json');
echo json_encode([
    'DB_HOST' => $_ENV['DB_HOST'] ?? 'NOT_SET',
    'DB_PORT' => $_ENV['DB_PORT'] ?? 'NOT_SET', 
    'DB_USER' => $_ENV['DB_USER'] ?? 'NOT_SET',
    'DB_NAME' => $_ENV['DB_NAME'] ?? 'NOT_SET',
    'SERVER_DB_HOST' => $_SERVER['DB_HOST'] ?? 'NOT_SET',
    'php_version' => phpversion(),
    'all_env_count' => count($_ENV),
    'all_server_count' => count($_SERVER)
]);
?>
EOF < /dev/null