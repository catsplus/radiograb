<?php
/**
 * Admin Template Management
 * Issue #38 Phase 2 - Station Template Sharing System
 * Admin interface for managing submitted templates
 */

session_start();
require_once '../../includes/database.php';
require_once '../../includes/functions.php';
require_once '../../includes/auth.php';
require_once '../../includes/StationTemplateService.php';

$auth = new UserAuth($db);

// Require admin authentication
requireAuth($auth);
if (!$auth->getCurrentUser()['is_admin']) {
    header('HTTP/1.1 403 Forbidden');
    echo "Access denied. Admin privileges required.";
    exit;
}

$templateService = new StationTemplateService($db, $auth->getCurrentUserId());

// Handle admin actions
if ($_POST['action'] ?? '' === 'verify_template' && isset($_POST['template_id'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $template_id = (int)$_POST['template_id'];
        $verified = $_POST['verified'] === '1';
        
        $result = $templateService->verifyTemplate($template_id, $verified);
        
        if ($result['success']) {
            setFlashMessage('success', $result['message']);
        } else {
            setFlashMessage('danger', $result['error']);
        }
    }
    header('Location: /admin/template-management.php');
    exit;
}

if ($_POST['action'] ?? '' === 'update_template' && isset($_POST['template_id'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $template_id = (int)$_POST['template_id'];
        
        // Update template information
        $update_data = [
            'name' => trim($_POST['name']),
            'description' => trim($_POST['description']),
            'genre' => trim($_POST['genre']),
            'country' => trim($_POST['country']),
            'language' => trim($_POST['language']),
            'is_active' => isset($_POST['is_active']) ? 1 : 0
        ];
        
        try {
            $db->update('stations_master', $update_data, 'id = :id', ['id' => $template_id]);
            setFlashMessage('success', 'Template updated successfully');
        } catch (Exception $e) {
            setFlashMessage('danger', 'Failed to update template: ' . $e->getMessage());
        }
    }
    header('Location: /admin/template-management.php');
    exit;
}

// Get filter parameters
$filters = [
    'status' => $_GET['status'] ?? 'all',
    'search' => trim($_GET['search'] ?? ''),
    'page' => max(1, (int)($_GET['page'] ?? 1)),
    'per_page' => 15
];

// Build query conditions
$where_conditions = ['1=1'];
$params = [];

if ($filters['status'] === 'pending') {
    $where_conditions[] = 'sm.is_verified = 0';
} elseif ($filters['status'] === 'verified') {
    $where_conditions[] = 'sm.is_verified = 1';
} elseif ($filters['status'] === 'inactive') {
    $where_conditions[] = 'sm.is_active = 0';
}

if (!empty($filters['search'])) {
    $where_conditions[] = "(sm.name LIKE ? OR sm.call_letters LIKE ? OR sm.description LIKE ?)";
    $search_param = "%{$filters['search']}%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = implode(' AND ', $where_conditions);

// Get templates
$offset = ($filters['page'] - 1) * $filters['per_page'];
$templates = $db->fetchAll("
    SELECT sm.*,
           u.username as created_by_username,
           u.email as created_by_email,
           AVG(str.rating) as avg_rating,
           COUNT(str.id) as review_count,
           COUNT(ust.id) as copy_count
    FROM stations_master sm
    LEFT JOIN users u ON sm.created_by_user_id = u.id
    LEFT JOIN station_template_reviews str ON sm.id = str.template_id
    LEFT JOIN user_station_templates ust ON sm.id = ust.template_id
    WHERE $where_clause
    GROUP BY sm.id
    ORDER BY sm.created_at DESC
    LIMIT {$filters['per_page']} OFFSET $offset
", $params);

// Get total count
$total_count = $db->fetchOne("
    SELECT COUNT(DISTINCT sm.id) as count
    FROM stations_master sm
    LEFT JOIN users u ON sm.created_by_user_id = u.id
    WHERE $where_clause
", $params)['count'];

$total_pages = ceil($total_count / $filters['per_page']);

// Get statistics
$stats = $db->fetchOne("
    SELECT 
        COUNT(*) as total_templates,
        SUM(CASE WHEN is_verified = 1 THEN 1 ELSE 0 END) as verified_templates,
        SUM(CASE WHEN is_verified = 0 THEN 1 ELSE 0 END) as pending_templates,
        SUM(CASE WHEN is_active = 0 THEN 1 ELSE 0 END) as inactive_templates,
        SUM(usage_count) as total_copies
    FROM stations_master
");

$page_title = 'Template Management';

require_once '../../includes/header.php';
?>

<!-- Template Management Content -->
<div class="container-fluid mt-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-cogs"></i> Template Management</h1>
                    <p class="text-muted">Manage community-submitted station templates</p>
                </div>
                <div>
                    <a href="/admin/" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Admin Dashboard
                    </a>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3">
            <div class="card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= number_format($stats['total_templates']) ?></h4>
                            <small>Total Templates</small>
                        </div>
                        <i class="fas fa-clone fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-warning text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= number_format($stats['pending_templates']) ?></h4>
                            <small>Pending Review</small>
                        </div>
                        <i class="fas fa-clock fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= number_format($stats['verified_templates']) ?></h4>
                            <small>Verified</small>
                        </div>
                        <i class="fas fa-shield-alt fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-md-3">
            <div class="card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex justify-content-between">
                        <div>
                            <h4><?= number_format($stats['total_copies']) ?></h4>
                            <small>Total Copies</small>
                        </div>
                        <i class="fas fa-copy fa-2x opacity-75"></i>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Filters -->
    <div class="card mb-4">
        <div class="card-header">
            <h5><i class="fas fa-filter"></i> Filter Templates</h5>
        </div>
        <div class="card-body">
            <form method="GET" class="row g-3">
                <div class="col-md-3">
                    <label class="form-label">Status</label>
                    <select name="status" class="form-select">
                        <option value="all" <?= $filters['status'] === 'all' ? 'selected' : '' ?>>All Templates</option>
                        <option value="pending" <?= $filters['status'] === 'pending' ? 'selected' : '' ?>>Pending Review</option>
                        <option value="verified" <?= $filters['status'] === 'verified' ? 'selected' : '' ?>>Verified</option>
                        <option value="inactive" <?= $filters['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                    </select>
                </div>
                
                <div class="col-md-6">
                    <label class="form-label">Search</label>
                    <input type="text" name="search" class="form-control" 
                           placeholder="Search templates..." value="<?= h($filters['search']) ?>">
                </div>
                
                <div class="col-md-3 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary me-2">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    <a href="/admin/template-management.php" class="btn btn-outline-secondary">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>
    
    <!-- Templates Table -->
    <div class="card">
        <div class="card-header">
            <h5><i class="fas fa-list"></i> Templates (<?= number_format($total_count) ?>)</h5>
        </div>
        <div class="card-body p-0">
            <?php if (empty($templates)): ?>
                <div class="text-center py-5">
                    <i class="fas fa-clone fa-3x text-muted mb-3"></i>
                    <h5>No templates found</h5>
                    <p class="text-muted">No templates match your current filters</p>
                </div>
            <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead class="table-light">
                            <tr>
                                <th>Template</th>
                                <th>Contributor</th>
                                <th>Status</th>
                                <th>Stats</th>
                                <th>Created</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($templates as $template): ?>
                                <tr>
                                    <td>
                                        <div class="d-flex align-items-center">
                                            <?php if ($template['logo_url']): ?>
                                                <img src="<?= h($template['logo_url']) ?>" 
                                                     alt="<?= h($template['name']) ?>"
                                                     class="me-2" 
                                                     style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;">
                                            <?php endif; ?>
                                            <div>
                                                <strong><?= h($template['name']) ?></strong><br>
                                                <small class="text-muted">
                                                    <strong><?= h($template['call_letters']) ?></strong>
                                                    <?= $template['genre'] ? ' • ' . h($template['genre']) : '' ?>
                                                </small>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <?php if ($template['created_by_username']): ?>
                                            <strong><?= h($template['created_by_username']) ?></strong><br>
                                            <small class="text-muted"><?= h($template['created_by_email']) ?></small>
                                        <?php else: ?>
                                            <span class="text-muted">Unknown</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="d-flex flex-column gap-1">
                                            <?php if ($template['is_verified']): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-shield-alt"></i> Verified
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-warning">
                                                    <i class="fas fa-clock"></i> Pending
                                                </span>
                                            <?php endif; ?>
                                            
                                            <?php if ($template['is_active']): ?>
                                                <span class="badge bg-primary">Active</span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Inactive</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <small>
                                            <div><i class="fas fa-copy"></i> <?= number_format($template['copy_count']) ?> copies</div>
                                            <?php if ($template['avg_rating']): ?>
                                                <div><i class="fas fa-star text-warning"></i> <?= round($template['avg_rating'], 1) ?> (<?= $template['review_count'] ?>)</div>
                                            <?php endif; ?>
                                        </small>
                                    </td>
                                    <td>
                                        <small><?= date('M j, Y', strtotime($template['created_at'])) ?></small>
                                    </td>
                                    <td>
                                        <div class="btn-group" role="group">
                                            <button type="button" class="btn btn-sm btn-outline-primary" 
                                                    data-bs-toggle="modal" data-bs-target="#editModal"
                                                    onclick="editTemplate(<?= htmlspecialchars(json_encode($template)) ?>)">
                                                <i class="fas fa-edit"></i>
                                            </button>
                                            
                                            <?php if (!$template['is_verified']): ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="verify_template">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                                    <input type="hidden" name="verified" value="1">
                                                    <button type="submit" class="btn btn-sm btn-success" 
                                                            onclick="return confirm('Verify this template?')">
                                                        <i class="fas fa-check"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <form method="POST" class="d-inline">
                                                    <input type="hidden" name="action" value="verify_template">
                                                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                    <input type="hidden" name="template_id" value="<?= $template['id'] ?>">
                                                    <input type="hidden" name="verified" value="0">
                                                    <button type="submit" class="btn btn-sm btn-warning" 
                                                            onclick="return confirm('Remove verification from this template?')">
                                                        <i class="fas fa-times"></i>
                                                    </button>
                                                </form>
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
        
        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
            <div class="card-footer">
                <nav aria-label="Templates pagination">
                    <ul class="pagination justify-content-center mb-0">
                        <?php for ($i = 1; $i <= $total_pages; $i++): ?>
                            <li class="page-item <?= $i === $filters['page'] ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                                    <?= $i ?>
                                </a>
                            </li>
                        <?php endfor; ?>
                    </ul>
                </nav>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Edit Template Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Edit Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="editForm">
                <div class="modal-body">
                    <input type="hidden" name="action" value="update_template">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="template_id" id="editTemplateId">
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Name</label>
                            <input type="text" name="name" id="editName" class="form-control" required>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Genre</label>
                            <select name="genre" id="editGenre" class="form-select">
                                <option value="">Select Genre</option>
                                <option value="Public Radio">Public Radio</option>
                                <option value="News/Talk">News/Talk</option>
                                <option value="Music">Music</option>
                                <option value="Community">Community</option>
                                <option value="College Radio">College Radio</option>
                                <option value="Religious">Religious</option>
                                <option value="International">International</option>
                                <option value="Specialty">Specialty</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <textarea name="description" id="editDescription" class="form-control" rows="3"></textarea>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" id="editCountry" class="form-control">
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Language</label>
                            <input type="text" name="language" id="editLanguage" class="form-control">
                        </div>
                    </div>
                    
                    <div class="form-check">
                        <input class="form-check-input" type="checkbox" name="is_active" id="editIsActive">
                        <label class="form-check-label" for="editIsActive">
                            Active (visible in browse templates)
                        </label>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
function editTemplate(template) {
    document.getElementById('editTemplateId').value = template.id;
    document.getElementById('editName').value = template.name;
    document.getElementById('editGenre').value = template.genre || '';
    document.getElementById('editDescription').value = template.description || '';
    document.getElementById('editCountry').value = template.country || '';
    document.getElementById('editLanguage').value = template.language || '';
    document.getElementById('editIsActive').checked = template.is_active == 1;
}
</script>

<?php
require_once '../../includes/footer.php';
?>