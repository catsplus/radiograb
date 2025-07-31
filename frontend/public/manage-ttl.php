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
<?php
// Set page variables for shared template
$page_title = 'TTL Management';
$active_nav = 'manage-ttl';

require_once '../includes/header.php';
?>

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

    <?php
require_once '../includes/footer.php';
?>