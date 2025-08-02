<?php
/**
 * Browse Station Templates
 * Issue #38 - Station Template Sharing System
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/auth.php';
require_once '../includes/StationTemplateService.php';

$auth = new UserAuth($db);

// Require authentication
requireAuth($auth);

$current_user = $auth->getCurrentUser();
$user_id = $auth->getCurrentUserId();

$templateService = new StationTemplateService($db, $user_id);

// Handle template copying
if ($_POST['action'] ?? '' === 'copy_template' && isset($_POST['template_id'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $template_id = (int)$_POST['template_id'];
        $custom_name = trim($_POST['custom_name'] ?? '');
        
        $result = $templateService->copyTemplate($template_id, $custom_name);
        
        if ($result['success']) {
            setFlashMessage('success', $result['message']);
        } else {
            setFlashMessage('danger', $result['error']);
        }
    }
    header('Location: /browse-templates.php?' . http_build_query($_GET));
    exit;
}

// Get filter parameters
$filters = [
    'search' => trim($_GET['search'] ?? ''),
    'genre' => trim($_GET['genre'] ?? ''),
    'country' => trim($_GET['country'] ?? ''),
    'category_id' => (int)($_GET['category_id'] ?? 0) ?: null,
    'verified_only' => isset($_GET['verified_only']),
    'exclude_copied' => isset($_GET['exclude_copied']),
    'sort' => $_GET['sort'] ?? 'usage_count',
    'order' => $_GET['order'] ?? 'desc',
    'page' => max(1, (int)($_GET['page'] ?? 1)),
    'per_page' => 20
];

// Browse templates
$browse_result = $templateService->browseTemplates($filters);
$templates = $browse_result['templates'] ?? [];
$pagination = $browse_result['pagination'] ?? [];

// Get categories for filter dropdown
$categories = $templateService->getCategories();

// Get unique genres and countries for filters
try {
    $genres = $db->fetchAll("
        SELECT DISTINCT genre 
        FROM stations_master 
        WHERE genre IS NOT NULL AND genre != '' AND is_active = 1
        ORDER BY genre
    ");
    
    $countries = $db->fetchAll("
        SELECT DISTINCT country 
        FROM stations_master 
        WHERE country IS NOT NULL AND country != '' AND is_active = 1
        ORDER BY country
    ");
} catch (Exception $e) {
    $genres = [];
    $countries = [];
}

$page_title = 'Browse Station Templates';
$active_nav = 'browse-templates';

require_once '../includes/header.php';
?>

<!-- Browse Templates Content -->
<div class="container mt-4">
    <?php if (!$browse_result['success']): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> <?= h($browse_result['error']) ?>
        </div>
    <?php endif; ?>

    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-clone"></i> Browse Station Templates</h1>
                    <p class="text-muted">Discover and copy community-contributed station configurations</p>
                </div>
                <div>
                    <a href="/stations.php" class="btn btn-outline-secondary me-2">
                        <i class="fas fa-arrow-left"></i> My Stations
                    </a>
                    <a href="/submit-template.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Submit Template
                    </a>
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
                    <label class="form-label">Search</label>
                    <input type="text" 
                           name="search" 
                           class="form-control" 
                           placeholder="Station name or call letters..."
                           value="<?= h($filters['search']) ?>">
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Category</label>
                    <select name="category_id" class="form-select">
                        <option value="">All Categories</option>
                        <?php foreach ($categories as $category): ?>
                            <option value="<?= $category['id'] ?>" 
                                    <?= $filters['category_id'] == $category['id'] ? 'selected' : '' ?>>
                                <?= h($category['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Genre</label>
                    <select name="genre" class="form-select">
                        <option value="">All Genres</option>
                        <?php foreach ($genres as $genre): ?>
                            <option value="<?= h($genre['genre']) ?>" 
                                    <?= $filters['genre'] === $genre['genre'] ? 'selected' : '' ?>>
                                <?= h($genre['genre']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Country</label>
                    <select name="country" class="form-select">
                        <option value="">All Countries</option>
                        <?php foreach ($countries as $country): ?>
                            <option value="<?= h($country['country']) ?>" 
                                    <?= $filters['country'] === $country['country'] ? 'selected' : '' ?>>
                                <?= h($country['country']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                
                <div class="col-md-2">
                    <label class="form-label">Sort By</label>
                    <select name="sort" class="form-select">
                        <option value="usage_count" <?= $filters['sort'] === 'usage_count' ? 'selected' : '' ?>>Popularity</option>
                        <option value="name" <?= $filters['sort'] === 'name' ? 'selected' : '' ?>>Name</option>
                        <option value="created_at" <?= $filters['sort'] === 'created_at' ? 'selected' : '' ?>>Newest</option>
                        <option value="last_tested" <?= $filters['sort'] === 'last_tested' ? 'selected' : '' ?>>Recently Tested</option>
                    </select>
                </div>
                
                <div class="col-md-1">
                    <label class="form-label">Order</label>
                    <select name="order" class="form-select">
                        <option value="desc" <?= $filters['order'] === 'desc' ? 'selected' : '' ?>>↓</option>
                        <option value="asc" <?= $filters['order'] === 'asc' ? 'selected' : '' ?>>↑</option>
                    </select>
                </div>
                
                <div class="col-12">
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" 
                               type="checkbox" 
                               name="verified_only" 
                               id="verified_only"
                               <?= $filters['verified_only'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="verified_only">
                            <i class="fas fa-shield-alt text-success"></i> Verified Only
                        </label>
                    </div>
                    
                    <div class="form-check form-check-inline">
                        <input class="form-check-input" 
                               type="checkbox" 
                               name="exclude_copied" 
                               id="exclude_copied"
                               <?= $filters['exclude_copied'] ? 'checked' : '' ?>>
                        <label class="form-check-label" for="exclude_copied">
                            Hide Already Copied
                        </label>
                    </div>
                    
                    <button type="submit" class="btn btn-primary ms-3">
                        <i class="fas fa-search"></i> Filter
                    </button>
                    
                    <a href="/browse-templates.php" class="btn btn-outline-secondary ms-2">
                        <i class="fas fa-times"></i> Clear
                    </a>
                </div>
            </form>
        </div>
    </div>

    <!-- Results Summary -->
    <?php if ($pagination): ?>
        <div class="d-flex justify-content-between align-items-center mb-3">
            <p class="text-muted mb-0">
                Showing <?= number_format(($pagination['page'] - 1) * $pagination['per_page'] + 1) ?>-<?= number_format(min($pagination['page'] * $pagination['per_page'], $pagination['total_count'])) ?> 
                of <?= number_format($pagination['total_count']) ?> templates
            </p>
        </div>
    <?php endif; ?>

    <!-- Templates Grid -->
    <div class="row">
        <?php if (empty($templates)): ?>
            <div class="col-12">
                <div class="text-center py-5">
                    <i class="fas fa-clone fa-3x text-muted mb-3"></i>
                    <h4>No templates found</h4>
                    <p class="text-muted">Try adjusting your filters or search terms</p>
                    <a href="/browse-templates.php" class="btn btn-outline-primary">
                        <i class="fas fa-refresh"></i> Show All Templates
                    </a>
                </div>
            </div>
        <?php else: ?>
            <?php foreach ($templates as $template): ?>
                <div class="col-lg-6 col-xl-4 mb-4">
                    <div class="card h-100 template-card">
                        <div class="card-body">
                            <!-- Header with logo and basic info -->
                            <div class="d-flex align-items-start mb-3">
                                <?php if ($template['logo_url']): ?>
                                    <img src="<?= h($template['logo_url']) ?>" 
                                         alt="<?= h($template['name']) ?>"
                                         class="station-logo me-3"
                                         style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px;"
                                         onerror="this.src='/assets/images/default-station-logo.png'">
                                <?php endif; ?>
                                
                                <div class="flex-grow-1">
                                    <h5 class="card-title mb-1">
                                        <?= h($template['name']) ?>
                                        
                                        <?php if ($template['is_verified']): ?>
                                            <span class="badge bg-success ms-1" title="Verified by administrators">
                                                <i class="fas fa-shield-alt"></i> Verified
                                            </span>
                                        <?php endif; ?>
                                        
                                        <?php if ($template['already_copied']): ?>
                                            <span class="badge bg-info ms-1" title="You've already copied this template">
                                                <i class="fas fa-check"></i> Copied
                                            </span>
                                        <?php endif; ?>
                                    </h5>
                                    
                                    <p class="text-muted mb-0">
                                        <strong><?= h($template['call_letters']) ?></strong>
                                        <?php if ($template['genre']): ?>
                                            • <?= h($template['genre']) ?>
                                        <?php endif; ?>
                                    </p>
                                    
                                    <?php if ($template['country']): ?>
                                        <small class="text-muted">
                                            <i class="fas fa-globe"></i> <?= h($template['country']) ?>
                                        </small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Description -->
                            <?php if ($template['description']): ?>
                                <p class="card-text text-muted small mb-3">
                                    <?= h(substr($template['description'], 0, 150)) ?><?= strlen($template['description']) > 150 ? '...' : '' ?>
                                </p>
                            <?php endif; ?>
                            
                            <!-- Categories -->
                            <?php if ($template['categories']): ?>
                                <div class="mb-3">
                                    <?php foreach (explode(', ', $template['categories']) as $category): ?>
                                        <span class="badge bg-light text-dark me-1"><?= h($category) ?></span>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                            
                            <!-- Stats -->
                            <div class="row text-center mb-3">
                                <div class="col-4">
                                    <div class="fw-bold"><?= number_format($template['usage_count']) ?></div>
                                    <small class="text-muted">Copies</small>
                                </div>
                                <div class="col-4">
                                    <?php if ($template['avg_rating']): ?>
                                        <div class="fw-bold">
                                            <?= $template['avg_rating'] ?> 
                                            <i class="fas fa-star text-warning"></i>
                                        </div>
                                        <small class="text-muted"><?= $template['review_count'] ?> reviews</small>
                                    <?php else: ?>
                                        <div class="text-muted">-</div>
                                        <small class="text-muted">No ratings</small>
                                    <?php endif; ?>
                                </div>
                                <div class="col-4">
                                    <?php if ($template['last_tested']): ?>
                                        <div class="fw-bold text-<?= $template['last_test_result'] === 'success' ? 'success' : 'warning' ?>">
                                            <i class="fas fa-<?= $template['last_test_result'] === 'success' ? 'check' : 'exclamation-triangle' ?>"></i>
                                        </div>
                                        <small class="text-muted"><?= timeAgo($template['last_tested']) ?></small>
                                    <?php else: ?>
                                        <div class="text-muted">-</div>
                                        <small class="text-muted">Not tested</small>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Attribution -->
                            <?php if ($template['created_by_username']): ?>
                                <p class="text-muted small mb-3">
                                    <i class="fas fa-user"></i> Contributed by <?= h($template['created_by_username']) ?>
                                    • <?= timeAgo($template['created_at']) ?>
                                </p>
                            <?php endif; ?>
                        </div>
                        
                        <!-- Actions -->
                        <div class="card-footer">
                            <div class="d-flex gap-2">
                                <button type="button" 
                                        class="btn btn-outline-primary btn-sm flex-grow-1"
                                        data-bs-toggle="modal" 
                                        data-bs-target="#templateDetailsModal"
                                        onclick="loadTemplateDetails(<?= $template['id'] ?>)">
                                    <i class="fas fa-eye"></i> Details
                                </button>
                                
                                <?php if (!$template['already_copied']): ?>
                                    <button type="button" 
                                            class="btn btn-primary btn-sm flex-grow-1"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#copyTemplateModal"
                                            onclick="setupCopyModal(<?= $template['id'] ?>, '<?= h($template['name']) ?>')">
                                        <i class="fas fa-copy"></i> Copy
                                    </button>
                                <?php else: ?>
                                    <button type="button" class="btn btn-success btn-sm flex-grow-1" disabled>
                                        <i class="fas fa-check"></i> Copied
                                    </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>

    <!-- Pagination -->
    <?php if ($pagination && $pagination['total_pages'] > 1): ?>
        <nav aria-label="Templates pagination">
            <ul class="pagination justify-content-center">
                <?php for ($i = 1; $i <= $pagination['total_pages']; $i++): ?>
                    <li class="page-item <?= $i === $pagination['page'] ? 'active' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $i])) ?>">
                            <?= $i ?>
                        </a>
                    </li>
                <?php endfor; ?>
            </ul>
        </nav>
    <?php endif; ?>
</div>

<!-- Copy Template Modal -->
<div class="modal fade" id="copyTemplateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Copy Station Template</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="copy_template">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="template_id" id="copy_template_id">
                    
                    <div class="mb-3">
                        <label class="form-label">Station Name</label>
                        <input type="text" 
                               name="custom_name" 
                               id="copy_custom_name"
                               class="form-control" 
                               placeholder="Leave blank to use template name">
                        <div class="form-text">You can customize the name for your copy</div>
                    </div>
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        This will create a copy of the template in your station collection. You can then customize it as needed.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-copy"></i> Copy Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Template Details Modal -->
<div class="modal fade" id="templateDetailsModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Template Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="templateDetailsContent">
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.template-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
}

.template-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 15px rgba(0,0,0,0.1);
}

.station-logo {
    border: 1px solid #dee2e6;
}
</style>

<script>
function setupCopyModal(templateId, templateName) {
    document.getElementById('copy_template_id').value = templateId;
    document.getElementById('copy_custom_name').placeholder = `Copy of ${templateName}`;
}

function loadTemplateDetails(templateId) {
    const content = document.getElementById('templateDetailsContent');
    content.innerHTML = '<div class="text-center"><div class="spinner-border" role="status"><span class="visually-hidden">Loading...</span></div></div>';
    
    fetch(`/api/template-details.php?id=${templateId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                content.innerHTML = renderTemplateDetails(data.template);
            } else {
                content.innerHTML = `<div class="alert alert-danger">${data.error}</div>`;
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Failed to load template details</div>';
        });
}

function renderTemplateDetails(template) {
    const verifiedBadge = template.is_verified ? 
        '<span class="badge bg-success ms-2"><i class="fas fa-shield-alt"></i> Verified</span>' : '';
    
    const logoHtml = template.logo_url ? 
        `<img src="${template.logo_url}" alt="${template.name}" class="img-fluid mb-3" style="max-width: 120px; border-radius: 8px;" onerror="this.src='/assets/images/default-station-logo.png'">` : '';
    
    const ratingHtml = template.avg_rating ? 
        `<div class="mb-2">
            <strong>Rating:</strong> ${template.avg_rating} <i class="fas fa-star text-warning"></i> 
            (${template.review_count} ${template.review_count === 1 ? 'review' : 'reviews'})
         </div>` : '';
    
    const testStatusIcon = template.last_test_result === 'success' ? 
        '<i class="fas fa-check text-success"></i>' : 
        template.last_test_result === 'failed' ? '<i class="fas fa-times text-danger"></i>' : 
        '<i class="fas fa-question text-muted"></i>';
    
    const testStatusText = template.last_tested ? 
        `Last tested: ${new Date(template.last_tested).toLocaleDateString()} ${testStatusIcon}` : 
        'Not yet tested';
    
    const categoriesHtml = template.categories && template.categories.length > 0 ? 
        `<div class="mb-2">
            <strong>Categories:</strong><br>
            ${template.categories.map(cat => `<span class="badge bg-light text-dark me-1">${cat.name}</span>`).join('')}
         </div>` : '';
    
    const reviewsHtml = template.reviews && template.reviews.length > 0 ? 
        `<div class="mt-4">
            <h6>Recent Reviews</h6>
            ${template.reviews.map(review => `
                <div class="border-bottom pb-2 mb-2">
                    <div class="d-flex justify-content-between align-items-start">
                        <strong>${review.username}</strong>
                        <div>
                            ${review.rating} <i class="fas fa-star text-warning"></i>
                            <span class="badge bg-${review.working_status === 'working' ? 'success' : review.working_status === 'not_working' ? 'danger' : 'warning'} ms-1">
                                ${review.working_status.replace('_', ' ')}
                            </span>
                        </div>
                    </div>
                    ${review.review_text ? `<p class="mb-1 text-muted">${review.review_text}</p>` : ''}
                    <small class="text-muted">${new Date(review.created_at).toLocaleDateString()}</small>
                </div>
            `).join('')}
         </div>` : '';
    
    return `
        <div class="row">
            <div class="col-md-4 text-center">
                ${logoHtml}
                <h5>${template.name} ${verifiedBadge}</h5>
                <p class="text-muted"><strong>${template.call_letters}</strong></p>
            </div>
            <div class="col-md-8">
                <div class="mb-2"><strong>Genre:</strong> ${template.genre || 'Not specified'}</div>
                <div class="mb-2"><strong>Country:</strong> ${template.country || 'Not specified'}</div>
                <div class="mb-2"><strong>Language:</strong> ${template.language || 'Not specified'}</div>
                ${template.description ? `<div class="mb-2"><strong>Description:</strong><br>${template.description}</div>` : ''}
                ${ratingHtml}
                ${categoriesHtml}
                <div class="mb-2"><strong>Usage:</strong> Copied ${template.usage_count} times</div>
                <div class="mb-2"><strong>Contributor:</strong> ${template.created_by_username || 'Unknown'}</div>
                <div class="mb-2"><strong>Added:</strong> ${new Date(template.created_at).toLocaleDateString()}</div>
                <div class="mb-3"><strong>Status:</strong> ${testStatusText}</div>
                
                ${template.website_url ? `<div class="mb-2"><a href="${template.website_url}" target="_blank" class="btn btn-outline-primary btn-sm"><i class="fas fa-external-link-alt"></i> Visit Website</a></div>` : ''}
                
                <div class="mt-3">
                    <strong>Technical Details:</strong><br>
                    <small class="text-muted">
                        Stream URL: <code>${template.stream_url || 'Not specified'}</code><br>
                        ${template.bitrate ? `Bitrate: ${template.bitrate}<br>` : ''}
                        ${template.format ? `Format: ${template.format}<br>` : ''}
                        ${template.timezone ? `Timezone: ${template.timezone}<br>` : ''}
                    </small>
                </div>
                
                ${reviewsHtml}
            </div>
        </div>
    `;
}
</script>

<?php
require_once '../includes/footer.php';
?>