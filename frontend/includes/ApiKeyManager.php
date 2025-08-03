<?php
/**
 * API Key Management Service
 * Issues #13, #25, #26 - Secure API key storage and management
 */

class ApiKeyManager {
    private $db;
    private $encryption_key;
    
    public function __construct($database) {
        $this->db = $database;
        $this->encryption_key = $this->getEncryptionKey();
    }
    
    /**
     * Get encryption key from environment or generate
     */
    private function getEncryptionKey() {
        $key = $_ENV['API_ENCRYPTION_KEY'] ?? null;
        if (!$key) {
            throw new Exception('API_ENCRYPTION_KEY environment variable not set');
        }
        return base64_decode($key);
    }
    
    /**
     * Encrypt API credentials for secure storage
     */
    private function encryptCredentials($credentials) {
        $json = json_encode($credentials);
        $iv = random_bytes(16);
        $encrypted = openssl_encrypt($json, 'AES-256-GCM', $this->encryption_key, OPENSSL_RAW_DATA, $iv, $tag);
        
        if ($encrypted === false) {
            throw new Exception('Encryption failed');
        }
        
        return base64_encode($iv . $tag . $encrypted);
    }
    
    /**
     * Decrypt API credentials for use
     */
    private function decryptCredentials($encrypted_data) {
        $data = base64_decode($encrypted_data);
        $iv = substr($data, 0, 16);
        $tag = substr($data, 16, 16);
        $encrypted = substr($data, 32);
        
        $decrypted = openssl_decrypt($encrypted, 'AES-256-GCM', $this->encryption_key, OPENSSL_RAW_DATA, $iv, $tag);
        
        if ($decrypted === false) {
            throw new Exception('Decryption failed');
        }
        
        return json_decode($decrypted, true);
    }
    
    /**
     * Store API key for user
     */
    public function storeApiKey($user_id, $service_type, $service_name, $credentials, $config = []) {
        try {
            $encrypted_credentials = $this->encryptCredentials($credentials);
            
            $data = [
                'user_id' => $user_id,
                'service_type' => $service_type,
                'service_name' => $service_name,
                'encrypted_credentials' => $encrypted_credentials,
                'is_active' => 1,
                'is_validated' => 0
            ];
            
            // Check if API key already exists for this user/service
            $existing = $this->db->fetchOne(
                "SELECT id FROM user_api_keys WHERE user_id = ? AND service_type = ? AND service_name = ?",
                [$user_id, $service_type, $service_name]
            );
            
            if ($existing) {
                // Update existing
                $this->db->update('user_api_keys', $data, 'id = :id', ['id' => $existing['id']]);
                $api_key_id = $existing['id'];
            } else {
                // Insert new
                $api_key_id = $this->db->insert('user_api_keys', $data);
            }
            
            // Store service-specific configuration
            $this->storeServiceConfig($user_id, $api_key_id, $service_type, $config);
            
            return [
                'success' => true,
                'api_key_id' => $api_key_id,
                'message' => 'API key stored successfully'
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => 'Failed to store API key: ' . $e->getMessage()
            ];
        }
    }
    
    /**
     * Store service-specific configuration
     */
    private function storeServiceConfig($user_id, $api_key_id, $service_type, $config) {
        switch ($service_type) {
            case 's3_storage':
                $this->storeS3Config($user_id, $api_key_id, $config);
                break;
            case 'transcription':
                $this->storeTranscriptionConfig($user_id, $api_key_id, $config);
                break;
            case 'llm_openai':
            case 'llm_anthropic':
            case 'llm_google':
            case 'llm_other':
                $this->storeLLMConfig($user_id, $api_key_id, $service_type, $config);
                break;
        }
    }
    
    /**
     * Store S3 configuration
     */
    private function storeS3Config($user_id, $api_key_id, $config) {
        $s3_config = [
            'user_id' => $user_id,
            'api_key_id' => $api_key_id,
            'config_name' => $config['config_name'] ?? 'Default S3',
            'bucket_name' => $config['bucket_name'],
            'region' => $config['region'] ?? 'us-east-1',
            'endpoint_url' => $config['endpoint_url'] ?? null,
            'path_prefix' => $config['path_prefix'] ?? 'radiograb/',
            'auto_upload_recordings' => $config['auto_upload_recordings'] ?? 1,
            'auto_upload_playlists' => $config['auto_upload_playlists'] ?? 1,
            'storage_class' => $config['storage_class'] ?? 'STANDARD',
            'is_active' => 1
        ];
        
        // Check if config already exists
        $existing = $this->db->fetchOne(
            "SELECT id FROM user_s3_configs WHERE user_id = ? AND api_key_id = ?",
            [$user_id, $api_key_id]
        );
        
        if ($existing) {
            $this->db->update('user_s3_configs', $s3_config, 'id = :id', ['id' => $existing['id']]);
        } else {
            $this->db->insert('user_s3_configs', $s3_config);
        }
    }
    
    /**
     * Store transcription configuration
     */
    private function storeTranscriptionConfig($user_id, $api_key_id, $config) {
        $transcription_config = [
            'user_id' => $user_id,
            'api_key_id' => $api_key_id,
            'service_provider' => $config['service_provider'] ?? 'whisper_local',
            'model_name' => $config['model_name'] ?? 'base',
            'language_code' => $config['language_code'] ?? 'en',
            'quality_level' => $config['quality_level'] ?? 'standard',
            'auto_transcribe_recordings' => $config['auto_transcribe_recordings'] ?? 0,
            'monthly_minutes_limit' => $config['monthly_minutes_limit'] ?? 1000,
            'is_active' => 1
        ];
        
        $existing = $this->db->fetchOne(
            "SELECT id FROM user_transcription_configs WHERE user_id = ? AND api_key_id = ?",
            [$user_id, $api_key_id]
        );
        
        if ($existing) {
            $this->db->update('user_transcription_configs', $transcription_config, 'id = :id', ['id' => $existing['id']]);
        } else {
            $this->db->insert('user_transcription_configs', $transcription_config);
        }
    }
    
    /**
     * Store LLM configuration
     */
    private function storeLLMConfig($user_id, $api_key_id, $service_type, $config) {
        $provider = str_replace('llm_', '', $service_type);
        
        $llm_config = [
            'user_id' => $user_id,
            'api_key_id' => $api_key_id,
            'provider' => $provider,
            'model_name' => $config['model_name'] ?? $this->getDefaultModel($provider),
            'max_tokens' => $config['max_tokens'] ?? 1000,
            'temperature' => $config['temperature'] ?? 0.7,
            'enable_summarization' => $config['enable_summarization'] ?? 1,
            'enable_playlist_generation' => $config['enable_playlist_generation'] ?? 1,
            'monthly_tokens_limit' => $config['monthly_tokens_limit'] ?? 100000,
            'priority_order' => $config['priority_order'] ?? 1,
            'is_active' => 1
        ];
        
        $existing = $this->db->fetchOne(
            "SELECT id FROM user_llm_configs WHERE user_id = ? AND api_key_id = ?",
            [$user_id, $api_key_id]
        );
        
        if ($existing) {
            $this->db->update('user_llm_configs', $llm_config, 'id = :id', ['id' => $existing['id']]);
        } else {
            $this->db->insert('user_llm_configs', $llm_config);
        }
    }
    
    /**
     * Get default model for LLM provider
     */
    private function getDefaultModel($provider) {
        $defaults = [
            'openai' => 'gpt-3.5-turbo',
            'anthropic' => 'claude-3-haiku-20240307',
            'google' => 'gemini-pro',
            'other' => 'default'
        ];
        
        return $defaults[$provider] ?? 'default';
    }
    
    /**
     * Get API keys for user and service type
     */
    public function getUserApiKeys($user_id, $service_type = null) {
        $query = "
            SELECT id, service_type, service_name, is_active, is_validated, 
                   last_validated_at, usage_count, last_used_at, created_at
            FROM user_api_keys 
            WHERE user_id = ?
        ";
        $params = [$user_id];
        
        if ($service_type) {
            $query .= " AND service_type = ?";
            $params[] = $service_type;
        }
        
        $query .= " ORDER BY service_type, service_name";
        
        return $this->db->fetchAll($query, $params);
    }
    
    /**
     * Get decrypted API credentials for use
     */
    public function getApiCredentials($user_id, $service_type, $service_name = null) {
        $query = "
            SELECT id, encrypted_credentials 
            FROM user_api_keys 
            WHERE user_id = ? AND service_type = ? AND is_active = 1
        ";
        $params = [$user_id, $service_type];
        
        if ($service_name) {
            $query .= " AND service_name = ?";
            $params[] = $service_name;
        }
        
        $query .= " ORDER BY last_used_at DESC LIMIT 1";
        
        $api_key = $this->db->fetchOne($query, $params);
        
        if (!$api_key) {
            return null;
        }
        
        try {
            $credentials = $this->decryptCredentials($api_key['encrypted_credentials']);
            return [
                'api_key_id' => $api_key['id'],
                'credentials' => $credentials
            ];
        } catch (Exception $e) {
            error_log("Failed to decrypt API credentials: " . $e->getMessage());
            return null;
        }
    }
    
    /**
     * Validate API key by testing connection
     */
    public function validateApiKey($user_id, $api_key_id) {
        $api_key = $this->db->fetchOne(
            "SELECT service_type, service_name, encrypted_credentials FROM user_api_keys WHERE id = ? AND user_id = ?",
            [$api_key_id, $user_id]
        );
        
        if (!$api_key) {
            return ['success' => false, 'error' => 'API key not found'];
        }
        
        try {
            $credentials = $this->decryptCredentials($api_key['encrypted_credentials']);
            $validation_result = $this->testApiConnection($api_key['service_type'], $credentials);
            
            // Update validation status
            $update_data = [
                'is_validated' => $validation_result['success'] ? 1 : 0,
                'last_validated_at' => $validation_result['success'] ? date('Y-m-d H:i:s') : null,
                'validation_error' => $validation_result['success'] ? null : $validation_result['error']
            ];
            
            $this->db->update('user_api_keys', $update_data, 'id = :id', ['id' => $api_key_id]);
            
            return $validation_result;
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Validation failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Test API connection based on service type
     */
    private function testApiConnection($service_type, $credentials) {
        switch ($service_type) {
            case 's3_storage':
                return $this->testS3Connection($credentials);
            case 'transcription':
                return $this->testTranscriptionService($credentials);
            case 'llm_openai':
                return $this->testOpenAIConnection($credentials);
            case 'llm_anthropic':
                return $this->testAnthropicConnection($credentials);
            default:
                return ['success' => false, 'error' => 'Unknown service type'];
        }
    }
    
    /**
     * Test S3 connection
     */
    private function testS3Connection($credentials) {
        try {
            // Simple S3 list buckets test
            $endpoint = $credentials['endpoint_url'] ?? 'https://s3.amazonaws.com';
            $region = $credentials['region'] ?? 'us-east-1';
            
            // Create AWS signature for test request
            $headers = $this->createS3AuthHeaders($credentials, 'GET', '/', $region);
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $endpoint);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                return ['success' => true, 'message' => 'S3 connection successful'];
            } else {
                return ['success' => false, 'error' => 'S3 connection failed: HTTP ' . $http_code];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'S3 test failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Create S3 authentication headers
     */
    private function createS3AuthHeaders($credentials, $method, $path, $region) {
        // Simplified S3 signature - in production, use AWS SDK
        $access_key = $credentials['access_key_id'];
        $secret_key = $credentials['secret_access_key'];
        $date = gmdate('D, d M Y H:i:s T');
        
        $string_to_sign = $method . "\n\n\n" . $date . "\n" . $path;
        $signature = base64_encode(hash_hmac('sha1', $string_to_sign, $secret_key, true));
        
        return [
            'Date: ' . $date,
            'Authorization: AWS ' . $access_key . ':' . $signature
        ];
    }
    
    /**
     * Test OpenAI API connection
     */
    private function testOpenAIConnection($credentials) {
        try {
            $api_key = $credentials['api_key'];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, 'https://api.openai.com/v1/models');
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $api_key,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 10);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            if ($http_code === 200) {
                return ['success' => true, 'message' => 'OpenAI API connection successful'];
            } else {
                return ['success' => false, 'error' => 'OpenAI API connection failed: HTTP ' . $http_code];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'OpenAI test failed: ' . $e->getMessage()];
        }
    }
    
    /**
     * Test transcription service (placeholder)
     */
    private function testTranscriptionService($credentials) {
        // Placeholder - implement based on specific transcription service
        return ['success' => true, 'message' => 'Transcription service validation not yet implemented'];
    }
    
    /**
     * Test Anthropic API connection (placeholder)
     */
    private function testAnthropicConnection($credentials) {
        // Placeholder - implement when Anthropic API is integrated
        return ['success' => true, 'message' => 'Anthropic API validation not yet implemented'];
    }
    
    /**
     * Delete API key
     */
    public function deleteApiKey($user_id, $api_key_id) {
        try {
            $deleted = $this->db->delete('user_api_keys', 'id = :id AND user_id = :user_id', [
                'id' => $api_key_id,
                'user_id' => $user_id
            ]);
            
            if ($deleted) {
                return ['success' => true, 'message' => 'API key deleted successfully'];
            } else {
                return ['success' => false, 'error' => 'API key not found'];
            }
            
        } catch (Exception $e) {
            return ['success' => false, 'error' => 'Failed to delete API key: ' . $e->getMessage()];
        }
    }
    
    /**
     * Log API usage
     */
    public function logApiUsage($user_id, $api_key_id, $service_type, $operation_type, $metrics = []) {
        $log_data = [
            'user_id' => $user_id,
            'api_key_id' => $api_key_id,
            'service_type' => $service_type,
            'operation_type' => $operation_type,
            'tokens_used' => $metrics['tokens_used'] ?? 0,
            'bytes_processed' => $metrics['bytes_processed'] ?? 0,
            'duration_seconds' => $metrics['duration_seconds'] ?? 0,
            'estimated_cost' => $metrics['estimated_cost'] ?? 0.00,
            'success' => $metrics['success'] ?? 1,
            'error_message' => $metrics['error_message'] ?? null,
            'response_time_ms' => $metrics['response_time_ms'] ?? 0
        ];
        
        $this->db->insert('user_api_usage_log', $log_data);
        
        // Update usage count in main table
        $this->db->execute(
            "UPDATE user_api_keys SET usage_count = usage_count + 1, last_used_at = NOW() WHERE id = ?",
            [$api_key_id]
        );
    }
    
    /**
     * Get usage statistics for user
     */
    public function getUsageStats($user_id, $days = 30) {
        $since_date = date('Y-m-d H:i:s', strtotime("-{$days} days"));
        
        return $this->db->fetchAll("
            SELECT 
                service_type,
                operation_type,
                COUNT(*) as request_count,
                SUM(tokens_used) as total_tokens,
                SUM(bytes_processed) as total_bytes,
                SUM(estimated_cost) as total_cost,
                AVG(response_time_ms) as avg_response_time
            FROM user_api_usage_log 
            WHERE user_id = ? AND created_at >= ?
            GROUP BY service_type, operation_type
            ORDER BY service_type, operation_type
        ", [$user_id, $since_date]);
    }
}
?>