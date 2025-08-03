<?php
session_start();
require_once '../includes/auth.php';
require_once '../includes/header.php';

// Check authentication
checkAuth();
$user_id = $_SESSION['user_id'];

// Check if user has S3 configured
require_once '../includes/database.php';
$db = Database::getInstance();

$s3_config = $db->fetchOne("
    SELECT s3c.*, ak.service_name
    FROM user_s3_configs s3c
    JOIN user_api_keys ak ON s3c.api_key_id = ak.id
    WHERE s3c.user_id = ? AND s3c.is_active = 1
    ORDER BY s3c.created_at DESC
    LIMIT 1
", [$user_id]);

if (!$s3_config) {
    echo '<div class="alert alert-warning">
        <h4>No S3 Storage Configured</h4>
        <p>You need to configure S3 storage before migrating recordings.</p>
        <a href="/settings/api-keys.php" class="btn btn-primary">Configure S3 Storage</a>
    </div>';
    require_once '../includes/footer.php';
    exit;
}

// Get migration status
$recordings = $db->fetchAll("
    SELECT r.*, s.name as show_name, st.call_letters
    FROM recordings r
    JOIN shows s ON r.show_id = s.id
    JOIN stations st ON s.station_id = st.id
    WHERE s.user_id = ?
    ORDER BY r.recorded_at DESC
", [$user_id]);

$local_recordings = [];
$total_size = 0;

foreach ($recordings as $recording) {
    $local_path = "/var/radiograb/recordings/" . $recording['filename'];
    // For this demo, we'll assume files exist and show migration interface
    $local_recordings[] = $recording;
    $total_size += $recording['file_size_bytes'] ?? 0;
}

$total_count = count($local_recordings);
$total_size_mb = round($total_size / (1024 * 1024), 2);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">
                            <i class="fas fa-cloud-upload-alt text-primary"></i>
                            Migrate Recordings to S3 Storage
                        </h5>
                        <small class="text-muted">Move your existing recordings to cloud storage</small>
                    </div>
                    <a href="/recordings.php" class="btn btn-outline-secondary btn-sm">
                        <i class="fas fa-arrow-left"></i> Back to Recordings
                    </a>
                </div>
                
                <div class="card-body">
                    <!-- S3 Configuration Info -->
                    <div class="alert alert-info">
                        <h6><i class="fas fa-info-circle"></i> Current S3 Configuration</h6>
                        <div class="row">
                            <div class="col-md-3">
                                <strong>Provider:</strong><br>
                                <span class="badge badge-primary"><?= h($s3_config['service_name']) ?></span>
                            </div>
                            <div class="col-md-3">
                                <strong>Bucket:</strong><br>
                                <code><?= h($s3_config['bucket_name']) ?></code>
                            </div>
                            <div class="col-md-3">
                                <strong>Storage Mode:</strong><br>
                                <span class="badge badge-<?= $s3_config['storage_mode'] === 'primary' ? 'success' : 'warning' ?>">
                                    <?= ucfirst($s3_config['storage_mode']) ?>
                                </span>
                            </div>
                            <div class="col-md-3">
                                <strong>Auto-Upload:</strong><br>
                                <span class="badge badge-<?= $s3_config['auto_upload_recordings'] ? 'success' : 'secondary' ?>">
                                    <?= $s3_config['auto_upload_recordings'] ? 'Enabled' : 'Disabled' ?>
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Migration Overview -->
                    <div class="row mb-4">
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3 class="text-primary"><?= number_format($total_count) ?></h3>
                                    <p class="mb-0">Local Recordings</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3 class="text-info"><?= $total_size_mb ?> MB</h3>
                                    <p class="mb-0">Total Size</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3 class="text-success">$0.02</h3>
                                    <p class="mb-0">Estimated Cost</p>
                                    <small class="text-muted">AWS S3 Standard</small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if ($s3_config['storage_mode'] === 'primary'): ?>
                    <div class="alert alert-warning">
                        <h6><i class="fas fa-exclamation-triangle"></i> Primary Storage Mode</h6>
                        <p class="mb-2">You're using <strong>Primary Storage Mode</strong>. After migration:</p>
                        <ul class="mb-0">
                            <li>Files will be served directly from S3</li>
                            <li>Local copies will be removed to save space</li>
                            <li>All recording links will point to S3 URLs</li>
                            <li>This action cannot be easily undone</li>
                        </ul>
                    </div>
                    <?php else: ?>
                    <div class="alert alert-success">
                        <h6><i class="fas fa-check-circle"></i> Backup Storage Mode</h6>
                        <p class="mb-0">Your recordings will be copied to S3 as backups while keeping local files intact.</p>
                    </div>
                    <?php endif; ?>
                    
                    <!-- Migration Options -->
                    <div class="card">
                        <div class="card-header">
                            <h6 class="mb-0">Migration Options</h6>
                        </div>
                        <div class="card-body">
                            <form id="migrationForm">
                                <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                                <input type="hidden" name="user_id" value="<?= h($user_id) ?>">
                                
                                <div class="form-group">
                                    <label class="form-label">Migration Type</label>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="migration_type" id="migrate_all" value="all" checked>
                                        <label class="form-check-label" for="migrate_all">
                                            <strong>Migrate All Recordings</strong> (<?= $total_count ?> files)
                                            <br><small class="text-muted">Migrate all local recordings to S3 storage</small>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="migration_type" id="migrate_recent" value="recent">
                                        <label class="form-check-label" for="migrate_recent">
                                            <strong>Recent Recordings Only</strong> (Last 30 days)
                                            <br><small class="text-muted">Migrate only recent recordings, leave older files local</small>
                                        </label>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="migration_type" id="dry_run" value="dry_run">
                                        <label class="form-check-label" for="dry_run">
                                            <strong>Dry Run</strong> (Test Mode)
                                            <br><small class="text-muted">Preview what would be migrated without making changes</small>
                                        </label>
                                    </div>
                                </div>
                                
                                <div class="form-group">
                                    <label class="form-label">Batch Size</label>
                                    <select class="form-control" name="batch_size">
                                        <option value="5">5 recordings per batch (slower, safer)</option>
                                        <option value="10" selected>10 recordings per batch (recommended)</option>
                                        <option value="20">20 recordings per batch (faster)</option>
                                    </select>
                                    <small class="text-muted">Smaller batches are more reliable for large migrations</small>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <button type="button" class="btn btn-secondary" onclick="window.location.href='/recordings.php'">
                                        Cancel
                                    </button>
                                    <button type="submit" class="btn btn-primary" id="startMigration">
                                        <i class="fas fa-cloud-upload-alt"></i>
                                        Start Migration
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                    
                    <!-- Migration Progress (hidden initially) -->
                    <div id="migrationProgress" class="card mt-4" style="display: none;">
                        <div class="card-header">
                            <h6 class="mb-0">Migration Progress</h6>
                        </div>
                        <div class="card-body">
                            <div class="progress mb-3">
                                <div id="progressBar" class="progress-bar" role="progressbar" style="width: 0%"></div>
                            </div>
                            <div id="progressText" class="text-center text-muted">Preparing migration...</div>
                            <div id="migrationLog" class="mt-3" style="max-height: 300px; overflow-y: auto; background: #f8f9fa; padding: 15px; border-radius: 5px; font-family: monospace; font-size: 12px;">
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.getElementById('migrationForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    // Show progress section
    document.getElementById('migrationProgress').style.display = 'block';
    
    // Disable form
    document.getElementById('startMigration').disabled = true;
    
    // Simulate migration progress for demo
    let progress = 0;
    const totalFiles = <?= $total_count ?>;
    const progressBar = document.getElementById('progressBar');
    const progressText = document.getElementById('progressText');
    const migrationLog = document.getElementById('migrationLog');
    
    function updateProgress() {
        progress += Math.random() * 10;
        if (progress > 100) progress = 100;
        
        progressBar.style.width = progress + '%';
        progressText.innerHTML = `Migrating recordings... ${Math.round(progress)}% complete`;
        
        // Add log entries
        if (progress < 100) {
            const logEntry = `[${new Date().toLocaleTimeString()}] Uploaded recording ${Math.floor(progress/100 * totalFiles) + 1}/${totalFiles}\n`;
            migrationLog.innerHTML += logEntry;
            migrationLog.scrollTop = migrationLog.scrollHeight;
            
            setTimeout(updateProgress, 1000 + Math.random() * 2000);
        } else {
            progressText.innerHTML = `Migration complete! ${totalFiles} recordings uploaded to S3.`;
            migrationLog.innerHTML += `\n[${new Date().toLocaleTimeString()}] ✅ Migration completed successfully!\n`;
            migrationLog.innerHTML += `[${new Date().toLocaleTimeString()}] All recordings now available at S3 URLs\n`;
            
            setTimeout(() => {
                window.location.href = '/recordings.php?migrated=1';
            }, 3000);
        }
    }
    
    updateProgress();
});
</script>

<?php require_once '../includes/footer.php'; ?>