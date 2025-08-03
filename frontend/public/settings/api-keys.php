<?php
/**
 * API Keys Management Page
 * Issues #13, #25, #26 - User API Keys Settings
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/ApiKeyManager.php';

$auth = new UserAuth($db);
requireAuth($auth);

$current_user = $auth->getCurrentUser();
$user_id = $auth->getCurrentUserId();
$apiKeyManager = new ApiKeyManager($db);

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid security token. Please try again.');
        header('Location: /settings/api-keys.php');
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'save_s3_key':
            $result = $apiKeyManager->storeApiKey($user_id, 's3_storage', $_POST['service_name'], [
                'access_key_id' => $_POST['access_key_id'],
                'secret_access_key' => $_POST['secret_access_key']
            ], [
                'config_name' => $_POST['config_name'],
                'bucket_name' => $_POST['bucket_name'],
                'region' => $_POST['region'],
                'endpoint_url' => $_POST['endpoint_url'],
                'path_prefix' => $_POST['path_prefix'],
                'auto_upload_recordings' => isset($_POST['auto_upload_recordings']),
                'auto_upload_playlists' => isset($_POST['auto_upload_playlists']),
                'storage_class' => $_POST['storage_class']
            ]);
            break;
            
        case 'save_transcription_key':
            $result = $apiKeyManager->storeApiKey($user_id, 'transcription', $_POST['service_name'], [
                'api_key' => $_POST['api_key']
            ], [
                'service_provider' => $_POST['service_provider'],
                'model_name' => $_POST['model_name'],
                'language_code' => $_POST['language_code'],
                'quality_level' => $_POST['quality_level'],
                'auto_transcribe_recordings' => isset($_POST['auto_transcribe_recordings']),
                'monthly_minutes_limit' => (int)$_POST['monthly_minutes_limit']
            ]);
            break;
            
        case 'save_llm_key':
            $service_type = 'llm_' . $_POST['provider'];
            $result = $apiKeyManager->storeApiKey($user_id, $service_type, $_POST['service_name'], [
                'api_key' => $_POST['api_key']
            ], [
                'model_name' => $_POST['model_name'],
                'max_tokens' => (int)$_POST['max_tokens'],
                'temperature' => (float)$_POST['temperature'],
                'enable_summarization' => isset($_POST['enable_summarization']),
                'enable_playlist_generation' => isset($_POST['enable_playlist_generation']),
                'monthly_tokens_limit' => (int)$_POST['monthly_tokens_limit'],
                'priority_order' => (int)$_POST['priority_order']
            ]);
            break;
            
        case 'validate_key':
            $api_key_id = (int)$_POST['api_key_id'];
            $result = $apiKeyManager->validateApiKey($user_id, $api_key_id);
            break;
            
        case 'delete_key':
            $api_key_id = (int)$_POST['api_key_id'];
            $result = $apiKeyManager->deleteApiKey($user_id, $api_key_id);
            break;
    }
    
    if (isset($result)) {
        if ($result['success']) {
            setFlashMessage('success', $result['message']);
        } else {
            setFlashMessage('danger', $result['error']);
        }
    }
    
    header('Location: /settings/api-keys.php');
    exit;
}

// Get user's API keys
$api_keys = $apiKeyManager->getUserApiKeys($user_id);
$usage_stats = $apiKeyManager->getUsageStats($user_id, 30);

// Group API keys by service type
$keys_by_service = [];
foreach ($api_keys as $key) {
    $keys_by_service[$key['service_type']][] = $key;
}

$page_title = 'API Keys Management';
$active_nav = 'api-keys';

require_once '../../includes/header.php';
?>

<div class="container mt-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col">
            <nav aria-label="breadcrumb">
                <ol class="breadcrumb">
                    <li class="breadcrumb-item"><a href="/dashboard.php">Dashboard</a></li>
                    <li class="breadcrumb-item active">API Keys</li>
                </ol>
            </nav>
            <h1><i class="fas fa-key"></i> API Keys Management</h1>
            <p class="text-muted">Configure your API keys for S3 storage, transcription, and LLM features</p>
        </div>
    </div>

    <!-- Security Notice -->
    <div class="alert alert-info">
        <i class="fas fa-shield-alt"></i>
        <strong>Security:</strong> Your API keys are encrypted and stored securely. They are only decrypted when needed for operations.
    </div>

    <!-- API Keys Tabs -->
    <ul class="nav nav-tabs" id="apiKeyTabs" role="tablist">
        <li class="nav-item" role="presentation">
            <button class="nav-link active" id="s3-tab" data-bs-toggle="tab" data-bs-target="#s3" type="button" role="tab">
                <i class="fas fa-cloud"></i> S3 Storage
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="transcription-tab" data-bs-toggle="tab" data-bs-target="#transcription" type="button" role="tab">
                <i class="fas fa-microphone"></i> Transcription
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="llm-tab" data-bs-toggle="tab" data-bs-target="#llm" type="button" role="tab">
                <i class="fas fa-robot"></i> LLM Services
            </button>
        </li>
        <li class="nav-item" role="presentation">
            <button class="nav-link" id="usage-tab" data-bs-toggle="tab" data-bs-target="#usage" type="button" role="tab">
                <i class="fas fa-chart-line"></i> Usage Statistics
            </button>
        </li>
    </ul>

    <div class="tab-content" id="apiKeyTabContent">
        <!-- S3 Storage Tab -->
        <div class="tab-pane fade show active" id="s3" role="tabpanel">
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-cloud"></i> S3 Storage Configuration</h5>
                    <small class="text-muted">Issue #13 - Automatic cloud backup of recordings and playlists</small>
                </div>
                <div class="card-body">
                    <!-- Existing S3 Keys -->
                    <?php if (isset($keys_by_service['s3_storage'])): ?>
                        <div class="mb-4">
                            <h6>Configured S3 Services</h6>
                            <?php foreach ($keys_by_service['s3_storage'] as $key): ?>
                                <div class="card border-start border-info border-3 mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="card-title"><?= h($key['service_name']) ?></h6>
                                                <div class="row">
                                                    <div class="col-auto">
                                                        <span class="badge bg-<?= $key['is_active'] ? 'success' : 'secondary' ?>">
                                                            <?= $key['is_active'] ? 'Active' : 'Inactive' ?>
                                                        </span>
                                                    </div>
                                                    <div class="col-auto">
                                                        <span class="badge bg-<?= $key['is_validated'] ? 'success' : 'warning' ?>">
                                                            <?= $key['is_validated'] ? 'Validated' : 'Not Validated' ?>
                                                        </span>
                                                    </div>
                                                </div>
                                                <small class="text-muted">
                                                    Created: <?= date('M j, Y', strtotime($key['created_at'])) ?>
                                                    <?php if ($key['last_used_at']): ?>
                                                        • Last used: <?= timeAgo($key['last_used_at']) ?>
                                                    <?php endif; ?>
                                                </small>
                                            </div>
                                            <div class="btn-group">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="action" value="validate_key">
                                                    <input type="hidden" name="api_key_id" value="<?= $key['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary" title="Test Connection">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="action" value="delete_key">
                                                    <input type="hidden" name="api_key_id" value="<?= $key['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                            onclick="return confirm('Delete this API key?')" title="Delete">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Add New S3 Key Form -->
                    <button class="btn btn-primary mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#s3Form">
                        <i class="fas fa-plus"></i> Add S3 Storage
                    </button>
                    
                    <div class="collapse" id="s3Form">
                        <form method="POST" class="border p-3 rounded">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="save_s3_key">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Service Name *</label>
                                    <input type="text" name="service_name" class="form-control" 
                                           placeholder="e.g., AWS S3, Wasabi, DigitalOcean Spaces" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Configuration Name *</label>
                                    <input type="text" name="config_name" class="form-control" 
                                           placeholder="e.g., Production Backup" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Access Key ID *</label>
                                    <input type="text" name="access_key_id" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Secret Access Key *</label>
                                    <input type="password" name="secret_access_key" class="form-control" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Bucket Name *</label>
                                    <input type="text" name="bucket_name" class="form-control" required>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Region</label>
                                    <input type="text" name="region" class="form-control" value="us-east-1">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Endpoint URL</label>
                                    <input type="url" name="endpoint_url" class="form-control" 
                                           placeholder="Leave blank for AWS S3">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Path Prefix</label>
                                    <input type="text" name="path_prefix" class="form-control" 
                                           value="radiograb/" placeholder="radiograb/">
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Storage Class</label>
                                    <select name="storage_class" class="form-select">
                                        <option value="STANDARD">Standard</option>
                                        <option value="REDUCED_REDUNDANCY">Reduced Redundancy</option>
                                        <option value="STANDARD_IA">Standard IA</option>
                                        <option value="GLACIER">Glacier</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="auto_upload_recordings" checked>
                                        <label class="form-check-label">Auto-upload recordings</label>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="auto_upload_playlists" checked>
                                        <label class="form-check-label">Auto-upload playlists</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save S3 Configuration
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Transcription Tab -->
        <div class="tab-pane fade" id="transcription" role="tabpanel">
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-microphone"></i> Transcription Services</h5>
                    <small class="text-muted">Issue #25 - Automatic speech-to-text for recordings</small>
                </div>
                <div class="card-body">
                    <!-- Existing Transcription Keys -->
                    <?php if (isset($keys_by_service['transcription'])): ?>
                        <div class="mb-4">
                            <h6>Configured Transcription Services</h6>
                            <?php foreach ($keys_by_service['transcription'] as $key): ?>
                                <div class="card border-start border-warning border-3 mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between align-items-start">
                                            <div>
                                                <h6 class="card-title"><?= h($key['service_name']) ?></h6>
                                                <div class="d-flex gap-2">
                                                    <span class="badge bg-<?= $key['is_active'] ? 'success' : 'secondary' ?>">
                                                        <?= $key['is_active'] ? 'Active' : 'Inactive' ?>
                                                    </span>
                                                    <span class="badge bg-<?= $key['is_validated'] ? 'success' : 'warning' ?>">
                                                        <?= $key['is_validated'] ? 'Validated' : 'Not Validated' ?>
                                                    </span>
                                                </div>
                                                <small class="text-muted">
                                                    Usage: <?= $key['usage_count'] ?> transcriptions
                                                </small>
                                            </div>
                                            <div class="btn-group">
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="action" value="validate_key">
                                                    <input type="hidden" name="api_key_id" value="<?= $key['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="action" value="delete_key">
                                                    <input type="hidden" name="api_key_id" value="<?= $key['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                            onclick="return confirm('Delete this API key?')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                </form>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Add Transcription Service -->
                    <button class="btn btn-primary mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#transcriptionForm">
                        <i class="fas fa-plus"></i> Add Transcription Service
                    </button>
                    
                    <div class="collapse" id="transcriptionForm">
                        <form method="POST" class="border p-3 rounded">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="save_transcription_key">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Service Provider *</label>
                                    <select name="service_provider" class="form-select" required>
                                        <option value="">Select Provider</option>
                                        <option value="whisper_local">Whisper (Local)</option>
                                        <option value="whisper_api">Whisper API</option>
                                        <option value="google_stt">Google Speech-to-Text</option>
                                        <option value="azure_stt">Azure Speech</option>
                                        <option value="aws_transcribe">AWS Transcribe</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Service Name *</label>
                                    <input type="text" name="service_name" class="form-control" 
                                           placeholder="e.g., OpenAI Whisper API" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">API Key *</label>
                                    <input type="password" name="api_key" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Model</label>
                                    <select name="model_name" class="form-select">
                                        <option value="base">Base</option>
                                        <option value="small">Small</option>
                                        <option value="medium">Medium</option>
                                        <option value="large">Large</option>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Language</label>
                                    <select name="language_code" class="form-select">
                                        <option value="en">English</option>
                                        <option value="es">Spanish</option>
                                        <option value="fr">French</option>
                                        <option value="de">German</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Quality Level</label>
                                    <select name="quality_level" class="form-select">
                                        <option value="draft">Draft</option>
                                        <option value="standard" selected>Standard</option>
                                        <option value="high">High</option>
                                        <option value="premium">Premium</option>
                                    </select>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Monthly Limit (minutes)</label>
                                    <input type="number" name="monthly_minutes_limit" class="form-control" 
                                           value="1000" min="0">
                                </div>
                            </div>
                            
                            <div class="form-check mb-3">
                                <input class="form-check-input" type="checkbox" name="auto_transcribe_recordings">
                                <label class="form-check-label">
                                    Automatically transcribe new recordings
                                </label>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Transcription Service
                            </button>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- LLM Services Tab -->
        <div class="tab-pane fade" id="llm" role="tabpanel">
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-robot"></i> LLM Services</h5>
                    <small class="text-muted">Issue #26 - AI features for summarization and content generation</small>
                </div>
                <div class="card-body">
                    <!-- Existing LLM Keys -->
                    <?php 
                    $llm_services = ['llm_openai', 'llm_anthropic', 'llm_google', 'llm_other'];
                    $has_llm_keys = false;
                    foreach ($llm_services as $service) {
                        if (isset($keys_by_service[$service])) {
                            $has_llm_keys = true;
                            break;
                        }
                    }
                    ?>
                    
                    <?php if ($has_llm_keys): ?>
                        <div class="mb-4">
                            <h6>Configured LLM Services</h6>
                            <?php foreach ($llm_services as $service): ?>
                                <?php if (isset($keys_by_service[$service])): ?>
                                    <?php foreach ($keys_by_service[$service] as $key): ?>
                                        <div class="card border-start border-success border-3 mb-3">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="card-title">
                                                            <?= h($key['service_name']) ?>
                                                            <span class="badge bg-primary ms-2"><?= ucfirst(str_replace('llm_', '', $key['service_type'])) ?></span>
                                                        </h6>
                                                        <div class="d-flex gap-2 mb-2">
                                                            <span class="badge bg-<?= $key['is_active'] ? 'success' : 'secondary' ?>">
                                                                <?= $key['is_active'] ? 'Active' : 'Inactive' ?>
                                                            </span>
                                                            <span class="badge bg-<?= $key['is_validated'] ? 'success' : 'warning' ?>">
                                                                <?= $key['is_validated'] ? 'Validated' : 'Not Validated' ?>
                                                            </span>
                                                        </div>
                                                        <small class="text-muted">
                                                            Usage: <?= number_format($key['usage_count']) ?> requests
                                                        </small>
                                                    </div>
                                                    <div class="btn-group">
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                            <input type="hidden" name="action" value="validate_key">
                                                            <input type="hidden" name="api_key_id" value="<?= $key['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-primary">
                                                                <i class="fas fa-check"></i>
                                                            </button>
                                                        </form>
                                                        <form method="POST" class="d-inline">
                                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                            <input type="hidden" name="action" value="delete_key">
                                                            <input type="hidden" name="api_key_id" value="<?= $key['id'] ?>">
                                                            <button type="submit" class="btn btn-sm btn-outline-danger" 
                                                                    onclick="return confirm('Delete this API key?')">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <!-- Add LLM Service -->
                    <button class="btn btn-primary mb-3" type="button" data-bs-toggle="collapse" data-bs-target="#llmForm">
                        <i class="fas fa-plus"></i> Add LLM Service
                    </button>
                    
                    <div class="collapse" id="llmForm">
                        <form method="POST" class="border p-3 rounded">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            <input type="hidden" name="action" value="save_llm_key">
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Provider *</label>
                                    <select name="provider" class="form-select" required>
                                        <option value="">Select Provider</option>
                                        <option value="openai">OpenAI</option>
                                        <option value="anthropic">Anthropic</option>
                                        <option value="google">Google AI</option>
                                        <option value="other">Other</option>
                                    </select>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Service Name *</label>
                                    <input type="text" name="service_name" class="form-control" 
                                           placeholder="e.g., OpenAI GPT-4" required>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">API Key *</label>
                                    <input type="password" name="api_key" class="form-control" required>
                                </div>
                                <div class="col-md-6 mb-3">
                                    <label class="form-label">Model Name</label>
                                    <input type="text" name="model_name" class="form-control" 
                                           placeholder="e.g., gpt-3.5-turbo, claude-3-haiku">
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Max Tokens</label>
                                    <input type="number" name="max_tokens" class="form-control" 
                                           value="1000" min="1" max="32000">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Temperature</label>
                                    <input type="number" name="temperature" class="form-control" 
                                           value="0.7" min="0" max="1" step="0.1">
                                </div>
                                <div class="col-md-4 mb-3">
                                    <label class="form-label">Priority Order</label>
                                    <input type="number" name="priority_order" class="form-control" 
                                           value="1" min="1">
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Monthly Token Limit</label>
                                <input type="number" name="monthly_tokens_limit" class="form-control" 
                                       value="100000" min="0">
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="enable_summarization" checked>
                                        <label class="form-check-label">Enable summarization</label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="enable_playlist_generation" checked>
                                        <label class="form-check-label">Enable playlist generation</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-3">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Save LLM Service
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usage Statistics Tab -->
        <div class="tab-pane fade" id="usage" role="tabpanel">
            <div class="card mt-3">
                <div class="card-header">
                    <h5><i class="fas fa-chart-line"></i> Usage Statistics (Last 30 Days)</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($usage_stats)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-chart-line fa-3x text-muted mb-3"></i>
                            <h5>No usage data yet</h5>
                            <p class="text-muted">Configure your API keys and start using features to see statistics here.</p>
                        </div>
                    <?php else: ?>
                        <div class="table-responsive">
                            <table class="table">
                                <thead>
                                    <tr>
                                        <th>Service</th>
                                        <th>Operation</th>
                                        <th>Requests</th>
                                        <th>Tokens/Bytes</th>
                                        <th>Est. Cost</th>
                                        <th>Avg Response</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($usage_stats as $stat): ?>
                                        <tr>
                                            <td>
                                                <span class="badge bg-secondary"><?= ucfirst($stat['service_type']) ?></span>
                                            </td>
                                            <td><?= h($stat['operation_type']) ?></td>
                                            <td><?= number_format($stat['request_count']) ?></td>
                                            <td>
                                                <?php if ($stat['total_tokens'] > 0): ?>
                                                    <?= number_format($stat['total_tokens']) ?> tokens
                                                <?php elseif ($stat['total_bytes'] > 0): ?>
                                                    <?= formatBytes($stat['total_bytes']) ?>
                                                <?php else: ?>
                                                    -
                                                <?php endif; ?>
                                            </td>
                                            <td>$<?= number_format($stat['total_cost'], 2) ?></td>
                                            <td><?= number_format($stat['avg_response_time']) ?>ms</td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.nav-tabs .nav-link {
    color: #6c757d;
}

.nav-tabs .nav-link.active {
    color: #495057;
    background-color: #fff;
    border-color: #dee2e6 #dee2e6 #fff;
}

.card {
    box-shadow: 0 0.125rem 0.25rem rgba(0, 0, 0, 0.075);
}

.border-start {
    border-left-width: 3px !important;
}

.form-check-input:checked {
    background-color: #0d6efd;
    border-color: #0d6efd;
}
</style>

<?php require_once '../../includes/footer.php'; ?>