-- Create system_info table for version management and system settings
CREATE TABLE IF NOT EXISTS system_info (
    id INT PRIMARY KEY AUTO_INCREMENT,
    key_name VARCHAR(100) NOT NULL UNIQUE,
    version VARCHAR(50) DEFAULT NULL,
    description TEXT DEFAULT NULL,
    value_text TEXT DEFAULT NULL,
    value_json JSON DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_key_name (key_name),
    INDEX idx_updated_at (updated_at)
);

-- Insert initial version
INSERT INTO system_info (key_name, version, description) 
VALUES ('current_version', 'v2.13.0', 'Enhanced calendar discovery with intelligent filtering and user-controlled activation') 
ON DUPLICATE KEY UPDATE 
    version = 'v2.13.0',
    description = 'Enhanced calendar discovery with intelligent filtering and user-controlled activation',
    updated_at = CURRENT_TIMESTAMP;