-- Add Rclone Remote Storage System (Issue #42)
-- Support for Google Drive, SFTP, Dropbox, and other rclone backends

-- Create table for user rclone remote configurations
CREATE TABLE IF NOT EXISTS user_rclone_remotes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    remote_name VARCHAR(100) NOT NULL,
    backend_type ENUM('drive', 'sftp', 'dropbox', 'onedrive', 'mega', 'box', 'pcloud', 'webdav', 'ftp') NOT NULL,
    config_data JSON NOT NULL,
    role ENUM('primary', 'backup', 'off') DEFAULT 'backup',
    config_file_path VARCHAR(500) NOT NULL,
    is_active TINYINT(1) DEFAULT 1,
    last_test_at DATETIME NULL,
    last_test_result TEXT NULL,
    total_uploaded_files INT DEFAULT 0,
    total_uploaded_bytes BIGINT DEFAULT 0,
    last_upload_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    UNIQUE KEY unique_user_remote (user_id, remote_name),
    INDEX idx_user_active (user_id, is_active),
    INDEX idx_role (role),
    INDEX idx_backend_type (backend_type)
);

-- Create table for rclone usage logging
CREATE TABLE IF NOT EXISTS rclone_usage_log (
    id INT AUTO_INCREMENT PRIMARY KEY,
    user_id INT NOT NULL,
    remote_id INT NOT NULL,
    operation_type ENUM('upload', 'download', 'test', 'list', 'delete') NOT NULL,
    file_path VARCHAR(500) NULL,
    file_size_bytes BIGINT NULL,
    operation_duration_seconds DECIMAL(10,3) NULL,
    success TINYINT(1) NOT NULL,
    error_message TEXT NULL,
    metrics JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
    FOREIGN KEY (remote_id) REFERENCES user_rclone_remotes(id) ON DELETE CASCADE,
    INDEX idx_user_date (user_id, created_at),
    INDEX idx_remote_date (remote_id, created_at),
    INDEX idx_operation (operation_type),
    INDEX idx_success (success)
);

-- Create table for rclone backend templates and documentation
CREATE TABLE IF NOT EXISTS rclone_backend_templates (
    id INT AUTO_INCREMENT PRIMARY KEY,
    backend_type VARCHAR(50) NOT NULL UNIQUE,
    display_name VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    icon_class VARCHAR(50) NULL,
    config_fields JSON NOT NULL,
    documentation_url VARCHAR(500) NULL,
    setup_instructions TEXT NULL,
    is_active TINYINT(1) DEFAULT 1,
    sort_order INT DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_active_sort (is_active, sort_order)
);

-- Insert default backend templates
INSERT INTO rclone_backend_templates (backend_type, display_name, description, icon_class, config_fields, documentation_url, setup_instructions, sort_order) VALUES 

('drive', 'Google Drive', 'Store recordings in Google Drive with unlimited storage for Google Workspace accounts', 'fab fa-google-drive', 
'{"client_id": {"label": "Client ID", "type": "text", "required": true, "help": "OAuth 2.0 Client ID from Google Cloud Console"}, "client_secret": {"label": "Client Secret", "type": "password", "required": true, "help": "OAuth 2.0 Client Secret"}, "scope": {"label": "Scope", "type": "select", "options": ["drive", "drive.readonly", "drive.file"], "default": "drive", "help": "Access scope for Google Drive"}, "root_folder_id": {"label": "Root Folder ID", "type": "text", "required": false, "help": "Optional: ID of folder to use as root"}}',
'https://rclone.org/drive/', 
'1. Go to Google Cloud Console\n2. Create new project or select existing\n3. Enable Google Drive API\n4. Create OAuth 2.0 credentials\n5. Add your domain to authorized origins\n6. Copy Client ID and Secret', 
1),

('sftp', 'SFTP Server', 'Upload recordings to any SFTP server including your own VPS or dedicated server', 'fas fa-server',
'{"host": {"label": "Host", "type": "text", "required": true, "help": "SFTP server hostname or IP"}, "user": {"label": "Username", "type": "text", "required": true, "help": "SSH username"}, "port": {"label": "Port", "type": "number", "default": "22", "help": "SSH port (usually 22)"}, "password": {"label": "Password", "type": "password", "required": false, "help": "SSH password (if not using key)"}, "key_file": {"label": "Private Key File", "type": "text", "required": false, "help": "Path to SSH private key file"}}',
'https://rclone.org/sftp/',
'1. Ensure SSH access to your server\n2. Create dedicated user for RadioGrab\n3. Generate SSH key pair (recommended)\n4. Test connection: ssh user@hostname\n5. Create upload directory with write permissions',
2),

('dropbox', 'Dropbox', 'Store recordings in Dropbox with up to 2TB storage on paid plans', 'fab fa-dropbox',
'{"client_id": {"label": "App Key", "type": "text", "required": true, "help": "Dropbox App Key from App Console"}, "client_secret": {"label": "App Secret", "type": "password", "required": true, "help": "Dropbox App Secret"}}',
'https://rclone.org/dropbox/',
'1. Go to Dropbox App Console\n2. Create new app\n3. Choose "Scoped access"\n4. Choose "Full Dropbox" access\n5. Copy App Key and App Secret\n6. Complete OAuth flow during setup',
3),

('onedrive', 'Microsoft OneDrive', 'Store recordings in OneDrive with up to 1TB on Microsoft 365 plans', 'fab fa-microsoft',
'{"client_id": {"label": "Application ID", "type": "text", "required": true, "help": "Application ID from Azure portal"}, "client_secret": {"label": "Client Secret", "type": "password", "required": true, "help": "Client secret value"}, "drive_type": {"label": "Drive Type", "type": "select", "options": ["personal", "business", "documentLibrary"], "default": "personal", "help": "Type of OneDrive account"}}',
'https://rclone.org/onedrive/',
'1. Go to Azure Portal\n2. Register new application\n3. Add redirect URI for rclone\n4. Generate client secret\n5. Grant Files.ReadWrite permissions\n6. Copy Application ID and secret',
4);

-- Add indexes for performance
ALTER TABLE recordings ADD INDEX idx_filename (filename);
ALTER TABLE recordings ADD INDEX idx_recorded_at (recorded_at);

-- Add rclone URL column to recordings for tracking remote locations
ALTER TABLE recordings ADD COLUMN rclone_urls JSON NULL COMMENT 'JSON object mapping remote names to their URLs';

-- Create view for rclone remote statistics
CREATE VIEW rclone_remote_stats AS
SELECT 
    urr.id,
    urr.user_id,
    urr.remote_name,
    urr.backend_type,
    urr.role,
    urr.is_active,
    urr.total_uploaded_files,
    urr.total_uploaded_bytes,
    urr.last_upload_at,
    COALESCE(recent_uploads.upload_count_30d, 0) as uploads_last_30_days,
    COALESCE(recent_uploads.bytes_uploaded_30d, 0) as bytes_uploaded_30d,
    COALESCE(recent_failures.failure_count_7d, 0) as failures_last_7_days
FROM user_rclone_remotes urr
LEFT JOIN (
    SELECT 
        remote_id,
        COUNT(*) as upload_count_30d,
        SUM(file_size_bytes) as bytes_uploaded_30d
    FROM rclone_usage_log 
    WHERE operation_type = 'upload' 
    AND success = 1 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
    GROUP BY remote_id
) recent_uploads ON urr.id = recent_uploads.remote_id
LEFT JOIN (
    SELECT 
        remote_id,
        COUNT(*) as failure_count_7d
    FROM rclone_usage_log 
    WHERE operation_type = 'upload' 
    AND success = 0 
    AND created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
    GROUP BY remote_id
) recent_failures ON urr.id = recent_failures.remote_id;