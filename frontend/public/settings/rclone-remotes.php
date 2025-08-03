<?php
session_start();
require_once '../../includes/auth.php';
require_once '../../includes/header.php';

// Check authentication
checkAuth();
$user_id = $_SESSION['user_id'];

// Get database connection
require_once '../../includes/database.php';
$db = Database::getInstance();

// Get user's rclone remotes
$remotes = $db->fetchAll("
    SELECT urr.*, rbt.display_name, rbt.icon_class 
    FROM user_rclone_remotes urr
    LEFT JOIN rclone_backend_templates rbt ON urr.backend_type = rbt.backend_type
    WHERE urr.user_id = ?
    ORDER BY urr.created_at DESC
", [$user_id]);

// Get available backend templates
$backend_templates = $db->fetchAll("
    SELECT * FROM rclone_backend_templates 
    WHERE is_active = 1 
    ORDER BY sort_order, display_name
");

// Get statistics
$stats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_remotes,
        SUM(CASE WHEN is_active = 1 THEN 1 ELSE 0 END) as active_remotes,
        SUM(total_uploaded_files) as total_files,
        SUM(total_uploaded_bytes) as total_bytes
    FROM user_rclone_remotes 
    WHERE user_id = ?
", [$user_id]);
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-12">
            <div class="card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <div>
                        <h5 class="mb-0">
                            <i class="fas fa-cloud text-primary"></i>
                            Rclone Remote Storage
                        </h5>
                        <small class="text-muted">Manage Google Drive, SFTP, Dropbox and other remote storage backends</small>
                    </div>
                    <div>
                        <button class="btn btn-primary btn-sm" data-toggle="modal" data-target="#addRemoteModal">
                            <i class="fas fa-plus"></i> Add Remote
                        </button>
                        <a href="/settings/api-keys.php" class="btn btn-outline-secondary btn-sm">
                            <i class="fas fa-key"></i> API Keys
                        </a>
                    </div>
                </div>
                
                <div class="card-body">
                    <!-- Statistics -->
                    <div class="row mb-4">
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3 class="text-primary"><?= $stats['total_remotes'] ?? 0 ?></h3>
                                    <p class="mb-0">Total Remotes</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3 class="text-success"><?= $stats['active_remotes'] ?? 0 ?></h3>
                                    <p class="mb-0">Active Remotes</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3 class="text-info"><?= number_format($stats['total_files'] ?? 0) ?></h3>
                                    <p class="mb-0">Files Uploaded</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-light">
                                <div class="card-body text-center">
                                    <h3 class="text-warning"><?= formatBytes($stats['total_bytes'] ?? 0) ?></h3>
                                    <p class="mb-0">Data Uploaded</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <?php if (empty($remotes)): ?>
                    <!-- Empty State -->
                    <div class="text-center py-5">
                        <i class="fas fa-cloud fa-3x text-muted mb-3"></i>
                        <h4>No Remote Storage Configured</h4>
                        <p class="text-muted">Add your first remote storage backend to automatically backup your recordings.</p>
                        <button class="btn btn-primary" data-toggle="modal" data-target="#addRemoteModal">
                            <i class="fas fa-plus"></i> Add Your First Remote
                        </button>
                    </div>
                    <?php else: ?>
                    
                    <!-- Remotes List -->
                    <div class="table-responsive">
                        <table class="table table-hover">
                            <thead>
                                <tr>
                                    <th>Remote</th>
                                    <th>Backend</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Statistics</th>
                                    <th>Last Activity</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($remotes as $remote): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <i class="<?= h($remote['icon_class'] ?? 'fas fa-cloud') ?> fa-lg text-primary me-2"></i>
                                            <div>
                                                <strong><?= h($remote['remote_name']) ?></strong>
                                                <br><small class="text-muted"><?= h($remote['display_name'] ?? ucfirst($remote['backend_type'])) ?></small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-secondary"><?= h(strtoupper($remote['backend_type'])) ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $role_colors = ['primary' => 'success', 'backup' => 'info', 'off' => 'secondary'];
                                        $role_color = $role_colors[$remote['role']] ?? 'secondary';
                                        ?>
                                        <span class="badge badge-<?= $role_color ?>"><?= h(ucfirst($remote['role'])) ?></span>
                                    </td>
                                    <td>
                                        <?php if ($remote['is_active']): ?>
                                            <span class="badge badge-success">
                                                <i class="fas fa-check-circle"></i> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="badge badge-danger">
                                                <i class="fas fa-times-circle"></i> Inactive
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <small>
                                            <?= number_format($remote['total_uploaded_files']) ?> files<br>
                                            <?= formatBytes($remote['total_uploaded_bytes']) ?>
                                        </small>
                                    </td>
                                    <td>
                                        <?php if ($remote['last_upload_at']): ?>
                                            <small><?= timeAgo($remote['last_upload_at']) ?></small>
                                        <?php else: ?>
                                            <small class="text-muted">Never</small>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary" onclick="testRemote(<?= $remote['id'] ?>)" title="Test Connection">
                                                <i class="fas fa-plug"></i>
                                            </button>
                                            <button class="btn btn-outline-secondary" onclick="editRemote(<?= $remote['id'] ?>)" title="Edit">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            <button class="btn btn-outline-danger" onclick="deleteRemote(<?= $remote['id'] ?>, '<?= h($remote['remote_name']) ?>')" title="Delete">
                                                <i class="fas fa-trash"></i>
                                            </button>
                                        </div>
                                    </td>
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

<!-- Add Remote Modal -->
<div class="modal fade" id="addRemoteModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Add Remote Storage</h5>
                <button type="button" class="close" data-dismiss="modal">
                    <span>&times;</span>
                </button>
            </div>
            <div class="modal-body">
                <!-- Backend Selection -->
                <div id="backendSelection">
                    <h6>Select Storage Backend</h6>
                    <div class="row">
                        <?php foreach ($backend_templates as $template): ?>
                        <div class="col-md-6 mb-3">
                            <div class="card backend-card h-100" onclick="selectBackend('<?= h($template['backend_type']) ?>')">
                                <div class="card-body text-center">
                                    <i class="<?= h($template['icon_class']) ?> fa-3x text-primary mb-2"></i>
                                    <h6><?= h($template['display_name']) ?></h6>
                                    <p class="small text-muted"><?= h($template['description']) ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                
                <!-- Configuration Form -->
                <div id="configurationForm" style="display: none;">
                    <form id="addRemoteForm">
                        <input type="hidden" name="csrf_token" value="<?= h($_SESSION['csrf_token']) ?>">
                        <input type="hidden" name="backend_type" id="selectedBackendType">
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="remoteName">Remote Name *</label>
                                    <input type="text" class="form-control" id="remoteName" name="remote_name" required 
                                           placeholder="my-drive" pattern="[a-zA-Z0-9_-]+" 
                                           title="Only letters, numbers, hyphens, and underscores allowed">
                                    <small class="text-muted">Used to identify this remote in rclone commands</small>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="form-group">
                                    <label for="remoteRole">Role *</label>
                                    <select class="form-control" id="remoteRole" name="role" required>
                                        <option value="backup">Backup - Copy recordings after local recording</option>
                                        <option value="primary">Primary - Upload recordings publicly (replaces local files)</option>
                                        <option value="off">Off - Don't use this remote for automatic uploads</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div id="backendFields">
                            <!-- Dynamic fields will be inserted here -->
                        </div>
                        
                        <div id="setupInstructions" class="alert alert-info" style="display: none;">
                            <!-- Setup instructions will be shown here -->
                        </div>
                    </form>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-outline-secondary" id="backButton" onclick="backToSelection()" style="display: none;">
                    <i class="fas fa-arrow-left"></i> Back
                </button>
                <button type="button" class="btn btn-primary" id="testRemoteBtn" onclick="testRemoteConfig()" style="display: none;">
                    <i class="fas fa-plug"></i> Test Connection
                </button>
                <button type="button" class="btn btn-success" id="saveRemoteBtn" onclick="saveRemote()" style="display: none;">
                    <i class="fas fa-save"></i> Save Remote
                </button>
            </div>
        </div>
    </div>
</div>

<style>
.backend-card {
    cursor: pointer;
    transition: all 0.2s;
    border: 2px solid transparent;
}

.backend-card:hover {
    border-color: #007bff;
    transform: translateY(-2px);
    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
}

.table td {
    vertical-align: middle;
}

.me-2 {
    margin-right: 0.5rem;
}
</style>

<script>
// Backend templates data
const backendTemplates = <?= json_encode(array_column($backend_templates, null, 'backend_type')) ?>;

function selectBackend(backendType) {
    const template = backendTemplates[backendType];
    if (!template) return;
    
    // Hide selection, show configuration
    document.getElementById('backendSelection').style.display = 'none';
    document.getElementById('configurationForm').style.display = 'block';
    document.getElementById('backButton').style.display = 'inline-block';
    document.getElementById('testRemoteBtn').style.display = 'inline-block';
    
    // Set backend type
    document.getElementById('selectedBackendType').value = backendType;
    
    // Generate form fields
    generateBackendFields(template);
    
    // Show setup instructions
    showSetupInstructions(template);
}

function generateBackendFields(template) {
    const fieldsContainer = document.getElementById('backendFields');
    const configFields = JSON.parse(template.config_fields);
    
    let html = '<h6><i class="' + template.icon_class + '"></i> ' + template.display_name + ' Configuration</h6>';
    
    for (const [fieldName, fieldConfig] of Object.entries(configFields)) {
        html += '<div class="form-group">';
        html += '<label for="field_' + fieldName + '">' + fieldConfig.label;
        if (fieldConfig.required) html += ' *';
        html += '</label>';
        
        if (fieldConfig.type === 'select') {
            html += '<select class="form-control" id="field_' + fieldName + '" name="' + fieldName + '"';
            if (fieldConfig.required) html += ' required';
            html += '>';
            
            for (const option of fieldConfig.options) {
                html += '<option value="' + option + '"';
                if (fieldConfig.default === option) html += ' selected';
                html += '>' + option + '</option>';
            }
            html += '</select>';
        } else {
            const inputType = fieldConfig.type === 'password' ? 'password' : 
                             fieldConfig.type === 'number' ? 'number' : 'text';
            
            html += '<input type="' + inputType + '" class="form-control" id="field_' + fieldName + '" name="' + fieldName + '"';
            if (fieldConfig.required) html += ' required';
            if (fieldConfig.default) html += ' value="' + fieldConfig.default + '"';
            if (fieldConfig.placeholder) html += ' placeholder="' + fieldConfig.placeholder + '"';
            html += '>';
        }
        
        if (fieldConfig.help) {
            html += '<small class="text-muted">' + fieldConfig.help + '</small>';
        }
        
        html += '</div>';
    }
    
    fieldsContainer.innerHTML = html;
}

function showSetupInstructions(template) {
    const instructionsDiv = document.getElementById('setupInstructions');
    
    if (template.setup_instructions) {
        const instructions = template.setup_instructions.split('\n').map(line => {
            if (line.match(/^\d+\./)) {
                return '<li>' + line.substring(3) + '</li>';
            }
            return line;
        }).join('');
        
        instructionsDiv.innerHTML = '<h6>Setup Instructions</h6><ol>' + instructions + '</ol>';
        if (template.documentation_url) {
            instructionsDiv.innerHTML += '<p><a href="' + template.documentation_url + '" target="_blank" class="btn btn-sm btn-outline-info">View Full Documentation <i class="fas fa-external-link-alt"></i></a></p>';
        }
        instructionsDiv.style.display = 'block';
    } else {
        instructionsDiv.style.display = 'none';
    }
}

function backToSelection() {
    document.getElementById('backendSelection').style.display = 'block';
    document.getElementById('configurationForm').style.display = 'none';
    document.getElementById('backButton').style.display = 'none';
    document.getElementById('testRemoteBtn').style.display = 'none';
    document.getElementById('saveRemoteBtn').style.display = 'none';
}

function testRemoteConfig() {
    const form = document.getElementById('addRemoteForm');
    const formData = new FormData(form);
    
    // Show loading state
    const testBtn = document.getElementById('testRemoteBtn');
    const originalText = testBtn.innerHTML;
    testBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Testing...';
    testBtn.disabled = true;
    
    fetch('/api/rclone-test.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Remote connection successful!\n\n' + data.message);
            document.getElementById('saveRemoteBtn').style.display = 'inline-block';
        } else {
            alert('❌ Remote connection failed:\n\n' + data.error);
        }
    })
    .catch(error => {
        alert('❌ Test failed: ' + error.message);
    })
    .finally(() => {
        testBtn.innerHTML = originalText;
        testBtn.disabled = false;
    });
}

function saveRemote() {
    const form = document.getElementById('addRemoteForm');
    const formData = new FormData(form);
    
    // Show loading state
    const saveBtn = document.getElementById('saveRemoteBtn');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;
    
    fetch('/api/rclone-save.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Remote saved successfully!');
            location.reload();
        } else {
            alert('❌ Failed to save remote:\n\n' + data.error);
        }
    })
    .catch(error => {
        alert('❌ Save failed: ' + error.message);
    })
    .finally(() => {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    });
}

function testRemote(remoteId) {
    if (!confirm('Test connection to this remote?')) return;
    
    fetch('/api/rclone-test.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            remote_id: remoteId,
            csrf_token: '<?= h($_SESSION['csrf_token']) ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Remote connection successful!');
        } else {
            alert('❌ Remote connection failed:\n\n' + data.error);
        }
    })
    .catch(error => {
        alert('❌ Test failed: ' + error.message);
    });
}

function deleteRemote(remoteId, remoteName) {
    if (!confirm(`Are you sure you want to delete the remote "${remoteName}"?\n\nThis will not delete files already uploaded, but will stop future automatic uploads.`)) return;
    
    fetch('/api/rclone-delete.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
        },
        body: JSON.stringify({
            remote_id: remoteId,
            csrf_token: '<?= h($_SESSION['csrf_token']) ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('✅ Remote deleted successfully!');
            location.reload();
        } else {
            alert('❌ Failed to delete remote:\n\n' + data.error);
        }
    })
    .catch(error => {
        alert('❌ Delete failed: ' + error.message);
    });
}

// Reset modal when closed
$('#addRemoteModal').on('hidden.bs.modal', function () {
    backToSelection();
    document.getElementById('addRemoteForm').reset();
});
</script>

<?php
function formatBytes($size, $precision = 2) {
    if ($size == 0) return '0 B';
    $base = log($size, 1024);
    $suffixes = array('B', 'KB', 'MB', 'GB', 'TB');
    return round(pow(1024, $base - floor($base)), $precision) . ' ' . $suffixes[floor($base)];
}

require_once '../../includes/footer.php';
?>