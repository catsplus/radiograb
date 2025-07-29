<?php
/**
 * RadioGrab - TTL Management Interface
 * Manage recording expiration settings
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Get recordings with TTL information
try {
    $recordings = $db->fetchAll("
        SELECT r.id, r.filename, r.title, r.recorded_at, r.expires_at,
               r.ttl_override_days, r.ttl_type, r.file_size_bytes,
               s.name as show_name, s.retention_days, s.default_ttl_type,
               st.name as station_name,
               CASE 
                   WHEN r.expires_at IS NULL THEN 'Never'
                   WHEN r.expires_at <= NOW() THEN 'Expired'
                   ELSE CONCAT(DATEDIFF(r.expires_at, NOW()), ' days')
               END as expires_in
        FROM recordings r
        JOIN shows s ON r.show_id = s.id
        JOIN stations st ON s.station_id = st.id
        ORDER BY r.recorded_at DESC
        LIMIT 100
    ");
    
    // Get expired recordings count
    $expired_count = $db->fetchOne("
        SELECT COUNT(*) as count FROM recordings 
        WHERE expires_at IS NOT NULL AND expires_at <= NOW()
    ")['count'];
    
    // Get expiring soon count (next 7 days)
    $expiring_soon_count = $db->fetchOne("
        SELECT COUNT(*) as count FROM recordings 
        WHERE expires_at IS NOT NULL 
        AND expires_at > NOW() 
        AND expires_at <= DATE_ADD(NOW(), INTERVAL 7 DAY)
    ")['count'];
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $recordings = [];
    $expired_count = 0;
    $expiring_soon_count = 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>TTL Management - RadioGrab</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/radiograb.css" rel="stylesheet">
</head>
<body>
    <!-- Navigation -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-primary">
        <div class="container">
            <a class="navbar-brand" href="/">
                <i class="fas fa-radio"></i> RadioGrab
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav me-auto">
                    <li class="nav-item">
                        <a class="nav-link" href="/">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/stations.php">Stations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/shows.php">Shows</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/recordings.php">Recordings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/manage-ttl.php">TTL Management</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Flash Messages -->
    <?php foreach (getFlashMessages() as $flash): ?>
        <div class="alert alert-<?= $flash['type'] ?> alert-dismissible fade show" role="alert">
            <?= h($flash['message']) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    <?php endforeach; ?>

    <!-- Main Content -->
    <div class="container mt-4">
        <?php if (isset($error)): ?>
            <div class="alert alert-danger">
                <i class="fas fa-exclamation-triangle"></i> <?= h($error) ?>
            </div>
        <?php endif; ?>

        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <h1><i class="fas fa-clock"></i> Recording TTL Management</h1>
                <p class="text-muted">Manage recording expiration and cleanup settings</p>
            </div>
        </div>

        <!-- TTL Statistics -->
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h2 class="text-danger"><?= $expired_count ?></h2>
                        <p class="card-text">Expired Recordings</p>
                        <button class="btn btn-danger btn-sm" onclick="cleanupExpired()">
                            <i class="fas fa-trash"></i> Cleanup Now
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h2 class="text-warning"><?= $expiring_soon_count ?></h2>
                        <p class="card-text">Expiring Soon (7 days)</p>
                        <button class="btn btn-warning btn-sm" onclick="showExpiringSoon()">
                            <i class="fas fa-list"></i> View List
                        </button>
                    </div>
                </div>
            </div>
            <div class="col-md-4">
                <div class="card text-center">
                    <div class="card-body">
                        <h2 class="text-info"><?= count($recordings) ?></h2>
                        <p class="card-text">Recent Recordings</p>
                        <small class="text-muted">Showing last 100</small>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recordings Table -->
        <div class="card">
            <div class="card-header">
                <h5><i class="fas fa-list"></i> Recent Recordings & TTL Settings</h5>
            </div>
            <div class="card-body">
                <?php if (empty($recordings)): ?>
                    <div class="text-center py-4">
                        <i class="fas fa-microphone fa-3x text-muted mb-3"></i>
                        <h4>No recordings found</h4>
                        <p class="text-muted">Start recording shows to see TTL management options here.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Recording</th>
                                    <th>Show</th>
                                    <th>Recorded</th>
                                    <th>Size</th>
                                    <th>TTL Setting</th>
                                    <th>Expires</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($recordings as $recording): ?>
                                    <tr class="<?= $recording['expires_in'] === 'Expired' ? 'table-danger' : '' ?>">
                                        <td>
                                            <div class="fw-bold"><?= h($recording['title'] ?: $recording['filename']) ?></div>
                                            <small class="text-muted"><?= h($recording['filename']) ?></small>
                                        </td>
                                        <td>
                                            <div><?= h($recording['show_name']) ?></div>
                                            <small class="text-muted"><?= h($recording['station_name']) ?></small>
                                        </td>
                                        <td>
                                            <div><?= date('M j, Y', strtotime($recording['recorded_at'])) ?></div>
                                            <small class="text-muted"><?= date('g:i A', strtotime($recording['recorded_at'])) ?></small>
                                        </td>
                                        <td>
                                            <?= $recording['file_size_bytes'] ? formatBytes($recording['file_size_bytes']) : '-' ?>
                                        </td>
                                        <td>
                                            <?php if ($recording['ttl_override_days']): ?>
                                                <span class="badge bg-primary">
                                                    Custom: <?= $recording['ttl_override_days'] ?> <?= $recording['ttl_type'] ?>
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">
                                                    Default: <?= $recording['retention_days'] ?> <?= $recording['default_ttl_type'] ?: 'days' ?>
                                                </span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <?php if ($recording['expires_in'] === 'Never'): ?>
                                                <span class="badge bg-success">Never</span>
                                            <?php elseif ($recording['expires_in'] === 'Expired'): ?>
                                                <span class="badge bg-danger">Expired</span>
                                            <?php else: ?>
                                                <span class="badge bg-info"><?= $recording['expires_in'] ?></span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <div class="btn-group btn-group-sm">
                                                <button class="btn btn-outline-primary edit-ttl" 
                                                        data-recording-id="<?= $recording['id'] ?>"
                                                        data-recording-name="<?= h($recording['title'] ?: $recording['filename']) ?>"
                                                        data-current-ttl="<?= $recording['ttl_override_days'] ?: $recording['retention_days'] ?>"
                                                        data-current-type="<?= $recording['ttl_type'] ?: $recording['default_ttl_type'] ?: 'days' ?>"
                                                        title="Edit TTL">
                                                    <i class="fas fa-edit"></i>
                                                </button>
                                                <?php if ($recording['expires_in'] !== 'Never' && $recording['expires_in'] !== 'Expired'): ?>
                                                    <button class="btn btn-outline-warning extend-ttl" 
                                                            data-recording-id="<?= $recording['id'] ?>"
                                                            data-recording-name="<?= h($recording['title'] ?: $recording['filename']) ?>"
                                                            title="Extend TTL">
                                                        <i class="fas fa-plus-circle"></i>
                                                    </button>
                                                <?php endif; ?>
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

    <!-- Edit TTL Modal -->
    <div class="modal fade" id="editTTLModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Edit Recording TTL</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="editTTLForm">
                        <input type="hidden" id="editRecordingId">
                        
                        <div class="mb-3">
                            <label class="form-label"><strong id="editRecordingName"></strong></label>
                            <p class="text-muted">Set custom TTL for this recording</p>
                        </div>
                        
                        <div class="row">
                            <div class="col-8">
                                <div class="mb-3">
                                    <label for="editTTLValue" class="form-label">TTL Value</label>
                                    <input type="number" class="form-control" id="editTTLValue" min="1" max="3650" required>
                                </div>
                            </div>
                            <div class="col-4">
                                <div class="mb-3">
                                    <label for="editTTLType" class="form-label">Unit</label>
                                    <select class="form-select" id="editTTLType">
                                        <option value="days">Days</option>
                                        <option value="weeks">Weeks</option>
                                        <option value="months">Months</option>
                                        <option value="indefinite">Forever</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="revertToDefault">
                            <label class="form-check-label" for="revertToDefault">
                                Revert to show default TTL
                            </label>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="saveTTL()">Save Changes</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Extend TTL Modal -->
    <div class="modal fade" id="extendTTLModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Extend Recording TTL</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="extendTTLForm">
                        <input type="hidden" id="extendRecordingId">
                        
                        <div class="mb-3">
                            <label class="form-label"><strong id="extendRecordingName"></strong></label>
                            <p class="text-muted">Add additional time before this recording expires</p>
                        </div>
                        
                        <div class="mb-3">
                            <label for="extendDays" class="form-label">Additional Days</label>
                            <input type="number" class="form-control" id="extendDays" min="1" max="365" value="7" required>
                            <div class="form-text">Number of days to add to current expiry date</div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-warning" onclick="extendTTL()">Extend TTL</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Edit TTL functionality
        document.querySelectorAll('.edit-ttl').forEach(btn => {
            btn.addEventListener('click', function() {
                const recordingId = this.dataset.recordingId;
                const recordingName = this.dataset.recordingName;
                const currentTtl = this.dataset.currentTtl;
                const currentType = this.dataset.currentType;
                
                document.getElementById('editRecordingId').value = recordingId;
                document.getElementById('editRecordingName').textContent = recordingName;
                document.getElementById('editTTLValue').value = currentTtl;
                document.getElementById('editTTLType').value = currentType;
                
                new bootstrap.Modal(document.getElementById('editTTLModal')).show();
            });
        });
        
        // Extend TTL functionality
        document.querySelectorAll('.extend-ttl').forEach(btn => {
            btn.addEventListener('click', function() {
                const recordingId = this.dataset.recordingId;
                const recordingName = this.dataset.recordingName;
                
                document.getElementById('extendRecordingId').value = recordingId;
                document.getElementById('extendRecordingName').textContent = recordingName;
                
                new bootstrap.Modal(document.getElementById('extendTTLModal')).show();
            });
        });
        
        // Handle revert to default checkbox
        document.getElementById('revertToDefault').addEventListener('change', function() {
            const inputs = ['editTTLValue', 'editTTLType'];
            inputs.forEach(id => {
                document.getElementById(id).disabled = this.checked;
            });
        });
        
        async function saveTTL() {
            const recordingId = document.getElementById('editRecordingId').value;
            const revertToDefault = document.getElementById('revertToDefault').checked;
            
            let ttlValue, ttlType;
            if (revertToDefault) {
                ttlValue = null;
                ttlType = 'days';
            } else {
                ttlValue = document.getElementById('editTTLValue').value;
                ttlType = document.getElementById('editTTLType').value;
            }
            
            try {
                const csrfToken = await getCSRFToken();
                const response = await fetch('/api/ttl-management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'update_recording_ttl',
                        recording_id: recordingId,
                        ttl_value: ttlValue,
                        ttl_type: ttlType,
                        csrf_token: csrfToken
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('editTTLModal')).hide();
                    showAlert('success', 'TTL updated successfully!');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showAlert('danger', result.error || 'Failed to update TTL');
                }
                
            } catch (error) {
                showAlert('danger', 'Network error occurred');
            }
        }
        
        async function extendTTL() {
            const recordingId = document.getElementById('extendRecordingId').value;
            const additionalDays = document.getElementById('extendDays').value;
            
            try {
                const csrfToken = await getCSRFToken();
                const response = await fetch('/api/ttl-management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'extend_recording',
                        recording_id: recordingId,
                        additional_days: additionalDays,
                        csrf_token: csrfToken
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    bootstrap.Modal.getInstance(document.getElementById('extendTTLModal')).hide();
                    showAlert('success', 'TTL extended successfully!');
                    setTimeout(() => window.location.reload(), 1000);
                } else {
                    showAlert('danger', result.error || 'Failed to extend TTL');
                }
                
            } catch (error) {
                showAlert('danger', 'Network error occurred');
            }
        }
        
        async function cleanupExpired() {
            if (!confirm('Are you sure you want to delete all expired recordings? This action cannot be undone.')) {
                return;
            }
            
            try {
                const csrfToken = await getCSRFToken();
                const response = await fetch('/api/ttl-management.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    body: new URLSearchParams({
                        action: 'cleanup_expired',
                        csrf_token: csrfToken
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showAlert('success', 'Cleanup completed successfully!');
                    setTimeout(() => window.location.reload(), 2000);
                } else {
                    showAlert('danger', result.error || 'Cleanup failed');
                }
                
            } catch (error) {
                showAlert('danger', 'Network error occurred');
            }
        }
        
        async function showExpiringSoon() {
            try {
                const response = await fetch('/api/ttl-management.php?action=get_expiring_soon&days=7');
                const result = await response.json();
                
                if (result.success) {
                    let content = '<h6>Recordings Expiring in Next 7 Days:</h6>';
                    if (result.expiring_recordings.length === 0) {
                        content += '<p class="text-muted">No recordings expiring soon.</p>';
                    } else {
                        content += '<ul class="list-group">';
                        result.expiring_recordings.forEach(rec => {
                            content += `<li class="list-group-item d-flex justify-content-between">
                                <span>${rec.filename}</span>
                                <span class="badge bg-warning">${rec.days_until_expiry} days</span>
                            </li>`;
                        });
                        content += '</ul>';
                    }
                    
                    showAlert('info', content);
                } else {
                    showAlert('danger', result.error || 'Failed to load expiring recordings');
                }
                
            } catch (error) {
                showAlert('danger', 'Network error occurred');
            }
        }
        
        // Helper functions
        async function getCSRFToken() {
            try {
                const response = await fetch('/api/get-csrf-token.php');
                const data = await response.json();
                return data.csrf_token;
            } catch (error) {
                console.error('Failed to get CSRF token:', error);
                return null;
            }
        }
        
        function showAlert(type, message) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            
            document.querySelector('.container').insertBefore(alertDiv, document.querySelector('.container').firstChild);
            
            setTimeout(() => {
                alertDiv.remove();
            }, 5000);
        }
    </script>

    <!-- Footer -->
    <footer class="bg-light mt-5 py-3">
        <div class="container">
            <div class="row">
                <div class="col text-center text-muted">
                    <small>
                        RadioGrab - Radio Recorder | 
                        Version: <?= getVersionNumber() ?>
                    </small>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>

<?php
function formatBytes($bytes, $precision = 2) {
    $units = array('B', 'KB', 'MB', 'GB', 'TB');
    
    for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
        $bytes /= 1024;
    }
    
    return round($bytes, $precision) . ' ' . $units[$i];
}
?>