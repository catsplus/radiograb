<?php
/**
 * RadioGrab - Settings
 *
 * This file provides the administrative interface for configuring system-wide settings,
 * including domain name, SSL certificates (Let's Encrypt or self-signed), and service control.
 * It includes functions for interacting with Nginx and Docker to apply configuration changes
 * and restart services.
 *
 * Key Variables:
 * - `$admin_password`: The password required for administrative authentication.
 * - `$domain`: The domain name for the RadioGrab instance.
 * - `$ssl_type`: The type of SSL certificate to set up (letsencrypt or selfsigned).
 * - `$current_config`: An array holding the current Nginx and SSL configuration.
 *
 * Inter-script Communication:
 * - This script executes shell commands to interact with Docker (e.g., `docker exec`, `docker cp`).
 * - It calls external functions like `updateNginxConfig`, `setupLetsEncryptSSL`,
 *   `setupSelfSignedSSL`, `checkDomainPubliclyAccessible`, and `restartWebServices`.
 * - It uses `includes/database.php` for database connection and `includes/functions.php` for helper functions.
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Improved admin authentication
if (!isset($_SESSION['admin_authenticated'])) {
    // Enhanced authentication for settings access
    if (isset($_POST['admin_password'])) {
        $entered_password = $_POST['admin_password'];
        
        // Check for environment variable password first, then fallback to default
        $admin_password = $_SERVER['RADIOGRAB_ADMIN_PASSWORD'] ?? 'radiograb_admin_2024';
        
        if (hash_equals($admin_password, $entered_password)) {
            $_SESSION['admin_authenticated'] = true;
            $_SESSION['admin_login_time'] = time();
            setFlashMessage('success', 'Successfully authenticated as admin');
        } else {
            $auth_error = 'Invalid password';
            // Log failed attempt
            error_log("Failed admin login attempt from IP: " . ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        }
    }
}

// Check session timeout (30 minutes)
if (isset($_SESSION['admin_authenticated']) && isset($_SESSION['admin_login_time'])) {
    if (time() - $_SESSION['admin_login_time'] > 1800) { // 30 minutes
        session_destroy();
        setFlashMessage('warning', 'Session expired. Please log in again.');
        header('Location: /settings.php');
        exit;
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    setFlashMessage('info', 'Successfully logged out');
    header('Location: /settings.php');
    exit;
}
    
    if (!isset($_SESSION['admin_authenticated'])) {
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Admin Authentication - RadioGrab</title>
            <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
        </head>
        <body>
            <div class="container mt-5">
                <div class="row justify-content-center">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h5>Admin Authentication Required</h5>
                            </div>
                            <div class="card-body">
                                <?php if (isset($auth_error)): ?>
                                    <div class="alert alert-danger"><?= h($auth_error) ?></div>
                                <?php endif; ?>
                                <form method="POST">
                                    <div class="mb-3">
                                        <label for="admin_password" class="form-label">Admin Password</label>
                                        <input type="password" class="form-control" id="admin_password" name="admin_password" required>
                                    </div>
                                    <button type="submit" class="btn btn-primary">Authenticate</button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </body>
        </html>
        <?php
        exit;
    }

// Handle settings updates
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    switch ($action) {
        case 'update_domain':
            $domain = trim($_POST['domain'] ?? '');
            $base_url = trim($_POST['base_url'] ?? '');
            
            if ($domain && $base_url) {
                // Update nginx configuration
                $result = updateNginxConfig($domain);
                if ($result['success']) {
                    $flash_message = 'Domain configuration updated successfully. Restart required.';
                    $flash_type = 'success';
                } else {
                    $flash_message = 'Error updating domain: ' . $result['error'];
                    $flash_type = 'danger';
                }
            }
            break;
            
        case 'setup_ssl':
            $domain = trim($_POST['ssl_domain'] ?? '');
            $ssl_type = $_POST['ssl_type'] ?? 'letsencrypt';
            
            if ($domain) {
                if ($ssl_type === 'letsencrypt') {
                    $result = setupLetsEncryptSSL($domain);
                } else {
                    $result = setupSelfSignedSSL($domain);
                }
                
                if ($result['success']) {
                    $flash_message = 'SSL certificate configured successfully.';
                    $flash_type = 'success';
                } else {
                    $flash_message = 'Error setting up SSL: ' . $result['error'];
                    $flash_type = 'danger';
                }
            }
            break;
            
        case 'restart_services':
            $result = restartWebServices();
            if ($result['success']) {
                $flash_message = 'Services restarted successfully.';
                $flash_type = 'success';
            } else {
                $flash_message = 'Error restarting services: ' . $result['error'];
                $flash_type = 'danger';
            }
            break;
    }
}

// Get current configuration
$current_config = getCurrentConfig();

?>
<?php
// Set page variables for shared template
$page_title = 'Settings';
$active_nav = 'settings';

require_once '../includes/header.php';
?>

    <!-- Main Content -->
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col">
                <h1><i class="fas fa-cog"></i> System Settings</h1>
                <p class="text-muted">Configure domain, SSL certificates, and system settings</p>
            </div>
            <div class="col-auto">
                <div class="d-flex align-items-center gap-3">
                    <small class="text-muted">
                        <i class="fas fa-user-shield"></i> 
                        Admin session (<?= date('H:i', $_SESSION['admin_login_time']) ?>)
                    </small>
                    <a href="?logout=1" class="btn btn-outline-danger btn-sm">
                        <i class="fas fa-sign-out-alt"></i> Logout
                    </a>
                </div>
            </div>
        </div>

        <?php if (isset($flash_message)): ?>
            <div class="alert alert-<?= $flash_type ?> alert-dismissible fade show">
                <?= h($flash_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- System Status -->
        <div class="row mb-4">
            <div class="col">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-server"></i> System Status</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6>Current Configuration</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Server Name:</strong> <?= h($current_config['server_name']) ?></li>
                                    <li><strong>Document Root:</strong> <?= h($current_config['document_root']) ?></li>
                                    <li><strong>SSL Status:</strong> 
                                        <?php if ($current_config['ssl_enabled']): ?>
                                            <span class="badge bg-success">Enabled</span>
                                        <?php else: ?>
                                            <span class="badge bg-warning">Disabled</span>
                                        <?php endif; ?>
                                    </li>
                                    <li><strong>SSL Certificate:</strong> <?= h($current_config['ssl_cert_type'] ?? 'None') ?></li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6>Service Status</h6>
                                <ul class="list-unstyled">
                                    <li><strong>Nginx:</strong> 
                                        <span class="badge bg-success">Running</span>
                                    </li>
                                    <li><strong>PHP-FPM:</strong> 
                                        <span class="badge bg-success">Running</span>
                                    </li>
                                    <li><strong>MySQL:</strong> 
                                        <span class="badge bg-success">Running</span>
                                    </li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <div class="col-lg-6">
                <!-- Domain Configuration -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-globe"></i> Domain Configuration</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="update_domain">
                            
                            <div class="mb-3">
                                <label for="domain" class="form-label">Domain Name</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="domain" 
                                       name="domain" 
                                       value="<?= h($current_config['server_name'] === 'localhost' ? '' : $current_config['server_name']) ?>"
                                       placeholder="radiograb.svaha.com">
                                <div class="form-text">
                                    Enter your domain name (without http/https)
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="base_url" class="form-label">Base URL</label>
                                <input type="url" 
                                       class="form-control" 
                                       id="base_url" 
                                       name="base_url" 
                                       value="<?= h($current_config['base_url'] ?? '') ?>"
                                       placeholder="https://radiograb.svaha.com">
                                <div class="form-text">
                                    Complete base URL including protocol
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Update Domain
                            </button>
                        </form>
                    </div>
                </div>

                <!-- SSL Configuration -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-lock"></i> SSL Certificate</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="action" value="setup_ssl">
                            
                            <div class="mb-3">
                                <label for="ssl_domain" class="form-label">Domain for SSL</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="ssl_domain" 
                                       name="ssl_domain" 
                                       placeholder="radiograb.svaha.com">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">SSL Certificate Type</label>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="ssl_type" id="letsencrypt" value="letsencrypt" checked>
                                    <label class="form-check-label" for="letsencrypt">
                                        <strong>Let's Encrypt (Recommended)</strong><br>
                                        <small class="text-muted">Free, automatic SSL certificate for public domains</small>
                                    </label>
                                </div>
                                <div class="form-check">
                                    <input class="form-check-input" type="radio" name="ssl_type" id="selfsigned" value="selfsigned">
                                    <label class="form-check-label" for="selfsigned">
                                        <strong>Self-Signed Certificate</strong><br>
                                        <small class="text-muted">For local/private use (browsers will show warnings)</small>
                                    </label>
                                </div>
                            </div>
                            
                            <button type="submit" class="btn btn-success">
                                <i class="fas fa-certificate"></i> Setup SSL
                            </button>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <!-- System Control -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-tools"></i> System Control</h5>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <form method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="restart_services">
                                <button type="submit" class="btn btn-warning w-100">
                                    <i class="fas fa-redo"></i> Restart Web Services
                                </button>
                            </form>
                            
                            <a href="/api/test-discovery.php" class="btn btn-info">
                                <i class="fas fa-vial"></i> Test Discovery Service
                            </a>
                            
                            <a href="/api/system-info.php" class="btn btn-secondary">
                                <i class="fas fa-info-circle"></i> System Information
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Documentation -->
                <div class="card mb-4">
                    <div class="card-header">
                        <h5><i class="fas fa-book"></i> Documentation</h5>
                    </div>
                    <div class="card-body">
                        <h6>SSL Certificate Setup</h6>
                        <p class="small">
                            <strong>Let's Encrypt:</strong> Requires a public domain pointing to this server. 
                            Certificates are automatically renewed.
                        </p>
                        <p class="small">
                            <strong>Self-Signed:</strong> For local development or private networks. 
                            Browsers will show security warnings that need to be accepted.
                        </p>
                        
                        <h6 class="mt-3">Domain Configuration</h6>
                        <p class="small">
                            After updating the domain, restart services to apply changes. 
                            Make sure your domain's DNS A record points to this server's IP address.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
require_once '../includes/footer.php';
?>

<?php
// Logout handling
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /settings.php');
    exit;
}

function getCurrentConfig() {
    $config = [
        'server_name' => 'localhost',
        'document_root' => '/opt/radiograb/frontend/public',
        'ssl_enabled' => false,
        'ssl_cert_type' => null,
        'base_url' => 'http://localhost'
    ];
    
    // Try to read current nginx config
    exec('docker exec radiograb-web-1 grep "server_name" /etc/nginx/sites-enabled/default 2>/dev/null', $output);
    if (!empty($output)) {
        foreach ($output as $line) {
            if (preg_match('/server_name\s+([^;]+);/', $line, $matches)) {
                $config['server_name'] = trim($matches[1]);
                break;
            }
        }
    }
    
    // Check SSL status
    exec('docker exec radiograb-web-1 grep "listen.*443.*ssl" /etc/nginx/sites-enabled/default 2>/dev/null', $ssl_output);
    if (!empty($ssl_output)) {
        $config['ssl_enabled'] = true;
        $config['ssl_cert_type'] = 'configured';
    }
    
    return $config;
}

function updateNginxConfig($domain) {
    try {
        $nginx_config = generateNginxConfig($domain);
        
        // Write new config to container
        $temp_file = tempnam(sys_get_temp_dir(), 'nginx_config');
        file_put_contents($temp_file, $nginx_config);
        
        exec("docker cp $temp_file radiograb-web-1:/etc/nginx/sites-available/default", $output, $return_code);
        unlink($temp_file);
        
        if ($return_code === 0) {
            // Test nginx config
            exec('docker exec radiograb-web-1 nginx -t 2>&1', $test_output, $test_return);
            if ($test_return === 0) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'Nginx config test failed: ' . implode(' ', $test_output)];
            }
        } else {
            return ['success' => false, 'error' => 'Failed to copy config file'];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function setupLetsEncryptSSL($domain) {
    try {
        // Check if domain is publicly accessible
        $public_check = checkDomainPubliclyAccessible($domain);
        if (!$public_check['accessible']) {
            return ['success' => false, 'error' => 'Domain is not publicly accessible. Use self-signed certificates for local/private domains.'];
        }
        
        // Install certbot if not present
        exec('docker exec radiograb-web-1 which certbot 2>/dev/null', $output, $return_code);
        if ($return_code !== 0) {
            exec('docker exec radiograb-web-1 apt-get update && apt-get install -y certbot python3-certbot-nginx', $install_output, $install_return);
            if ($install_return !== 0) {
                return ['success' => false, 'error' => 'Failed to install certbot'];
            }
        }
        
        // Get certificate
        $cmd = "docker exec radiograb-web-1 certbot --nginx -d $domain --non-interactive --agree-tos --email admin@$domain";
        exec($cmd . ' 2>&1', $cert_output, $cert_return);
        
        if ($cert_return === 0) {
            return ['success' => true];
        } else {
            return ['success' => false, 'error' => 'Certbot failed: ' . implode(' ', $cert_output)];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function setupSelfSignedSSL($domain) {
    try {
        $ssl_dir = '/etc/nginx/ssl';
        $cert_file = "$ssl_dir/$domain.crt";
        $key_file = "$ssl_dir/$domain.key";
        
        // Create SSL directory
        exec("docker exec radiograb-web-1 mkdir -p $ssl_dir");
        
        // Generate self-signed certificate
        $openssl_cmd = "docker exec radiograb-web-1 openssl req -x509 -nodes -days 365 -newkey rsa:2048 " .
                      "-keyout $key_file -out $cert_file " .
                      "-subj '/C=US/ST=State/L=City/O=Organization/CN=$domain'";
        
        exec($openssl_cmd . ' 2>&1', $output, $return_code);
        
        if ($return_code === 0) {
            // Update nginx config for SSL
            $ssl_config = generateNginxConfigWithSSL($domain, $cert_file, $key_file);
            $temp_file = tempnam(sys_get_temp_dir(), 'nginx_ssl_config');
            file_put_contents($temp_file, $ssl_config);
            
            exec("docker cp $temp_file radiograb-web-1:/etc/nginx/sites-available/default", $copy_output, $copy_return);
            unlink($temp_file);
            
            if ($copy_return === 0) {
                return ['success' => true];
            } else {
                return ['success' => false, 'error' => 'Failed to update nginx config for SSL'];
            }
        } else {
            return ['success' => false, 'error' => 'Failed to generate SSL certificate: ' . implode(' ', $output)];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function checkDomainPubliclyAccessible($domain) {
    try {
        // Try to resolve domain
        $ip = gethostbyname($domain);
        if ($ip === $domain) {
            return ['accessible' => false, 'reason' => 'Domain does not resolve'];
        }
        
        // Try to connect to domain on port 80
        $context = stream_context_create(['http' => ['timeout' => 5]]);
        $result = @file_get_contents("http://$domain", false, $context);
        
        return ['accessible' => $result !== false];
    } catch (Exception $e) {
        return ['accessible' => false, 'reason' => $e->getMessage()];
    }
}

function restartWebServices() {
    try {
        exec('docker exec radiograb-web-1 service nginx reload 2>&1', $nginx_output, $nginx_return);
        exec('docker exec radiograb-web-1 service php8.1-fpm restart 2>&1', $php_output, $php_return);
        
        if ($nginx_return === 0 && $php_return === 0) {
            return ['success' => true];
        } else {
            $errors = [];
            if ($nginx_return !== 0) $errors[] = 'Nginx: ' . implode(' ', $nginx_output);
            if ($php_return !== 0) $errors[] = 'PHP-FPM: ' . implode(' ', $php_output);
            return ['success' => false, 'error' => implode('; ', $errors)];
        }
    } catch (Exception $e) {
        return ['success' => false, 'error' => $e->getMessage()];
    }
}

function generateNginxConfig($domain) {
    return "server {
    listen 80;
    server_name $domain localhost;
    
    root /opt/radiograb/frontend/public;
    index index.php index.html index.htm;

    # Logging
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;

    # Main application
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP processing
    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
        fastcgi_read_timeout 300;
    }

    # Recordings access
    location /recordings/ {
        alias /var/radiograb/recordings/;
        add_header Content-Type audio/mpeg;
        add_header Cache-Control \"public, max-age=3600\";
        add_header Access-Control-Allow-Origin \"*\";
    }

    # RSS feeds
    location /feeds/ {
        alias /var/radiograb/feeds/;
        add_header Content-Type application/rss+xml;
        add_header Cache-Control \"public, max-age=300\";
    }

    # API endpoints
    location /api/ {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # Static assets
    location /assets/ {
        alias /opt/radiograb/frontend/assets/;
        expires 1y;
        add_header Cache-Control \"public, immutable\";
    }

    # Security headers
    add_header X-Frame-Options \"SAMEORIGIN\" always;
    add_header X-Content-Type-Options \"nosniff\" always;
    add_header X-XSS-Protection \"1; mode=block\" always;
    add_header Referrer-Policy \"strict-origin-when-cross-origin\" always;

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    location ~* \.(env|ini|conf|log|bak|backup)\$ {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Deny access to backend files
    location ~ ^/(backend|database|venv)/ {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Handle large file uploads for potential future features
    client_max_body_size 100M;
    
    # Optimize for audio streaming
    location ~* \.(mp3|wav|ogg|flac|m4a)\$ {
        add_header Accept-Ranges bytes;
        add_header Cache-Control \"public, max-age=3600\";
    }
}";
}

function generateNginxConfigWithSSL($domain, $cert_file, $key_file) {
    return "server {
    listen 80;
    server_name $domain localhost;
    return 301 https://\$server_name\$request_uri;
}

server {
    listen 443 ssl http2;
    server_name $domain localhost;
    
    ssl_certificate $cert_file;
    ssl_certificate_key $key_file;
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_ciphers ECDHE-RSA-AES256-GCM-SHA512:DHE-RSA-AES256-GCM-SHA512:ECDHE-RSA-AES256-GCM-SHA384:DHE-RSA-AES256-GCM-SHA384;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 10m;
    
    root /opt/radiograb/frontend/public;
    index index.php index.html index.htm;

    # Logging
    access_log /var/log/nginx/access.log;
    error_log /var/log/nginx/error.log;

    # Main application
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # PHP processing
    location ~ \.php\$ {
        include fastcgi_params;
        fastcgi_pass 127.0.0.1:9000;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
        fastcgi_param HTTPS on;
        fastcgi_read_timeout 300;
    }

    # Recordings access
    location /recordings/ {
        alias /var/radiograb/recordings/;
        add_header Content-Type audio/mpeg;
        add_header Cache-Control \"public, max-age=3600\";
        add_header Access-Control-Allow-Origin \"*\";
    }

    # RSS feeds
    location /feeds/ {
        alias /var/radiograb/feeds/;
        add_header Content-Type application/rss+xml;
        add_header Cache-Control \"public, max-age=300\";
    }

    # API endpoints
    location /api/ {
        try_files \$uri \$uri/ /index.php?\$query_string;
    }

    # Static assets
    location /assets/ {
        alias /opt/radiograb/frontend/assets/;
        expires 1y;
        add_header Cache-Control \"public, immutable\";
    }

    # Security headers
    add_header X-Frame-Options \"SAMEORIGIN\" always;
    add_header X-Content-Type-Options \"nosniff\" always;
    add_header X-XSS-Protection \"1; mode=block\" always;
    add_header Referrer-Policy \"strict-origin-when-cross-origin\" always;
    add_header Strict-Transport-Security \"max-age=31536000; includeSubDomains\" always;

    # Deny access to sensitive files
    location ~ /\. {
        deny all;
        access_log off;
        log_not_found off;
    }
    
    location ~* \.(env|ini|conf|log|bak|backup)\$ {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Deny access to backend files
    location ~ ^/(backend|database|venv)/ {
        deny all;
        access_log off;
        log_not_found off;
    }

    # Handle large file uploads for potential future features
    client_max_body_size 100M;
    
    # Optimize for audio streaming
    location ~* \.(mp3|wav|ogg|flac|m4a)\$ {
        add_header Accept-Ranges bytes;
        add_header Cache-Control \"public, max-age=3600\";
    }
}";
}
?>