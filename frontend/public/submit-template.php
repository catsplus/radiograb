<?php
/**
 * Submit Station Template
 * Issue #38 Phase 2 - Station Template Sharing System
 * Allow users to submit their stations as public templates
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

// Handle template submission
if ($_POST['action'] ?? '' === 'submit_template' && isset($_POST['station_id'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $station_id = (int)$_POST['station_id'];
        
        // Prepare submission data
        $submission_data = [
            'name' => trim($_POST['template_name'] ?? ''),
            'description' => trim($_POST['template_description'] ?? ''),
            'genre' => trim($_POST['genre'] ?? ''),
            'language' => trim($_POST['language'] ?? 'English'),
            'country' => trim($_POST['country'] ?? 'United States'),
            'bitrate' => trim($_POST['bitrate'] ?? ''),
            'format' => trim($_POST['format'] ?? ''),
            'category_ids' => $_POST['category_ids'] ?? []
        ];
        
        $result = $templateService->submitAsTemplate($station_id, $submission_data);
        
        if ($result['success']) {
            setFlashMessage('success', $result['message']);
            header('Location: /browse-templates.php');
            exit;
        } else {
            setFlashMessage('danger', $result['error']);
        }
    } else {
        setFlashMessage('danger', 'Invalid form submission. Please try again.');
    }
}

// Get user's stations that haven't been submitted as templates
$user_stations = $db->fetchAll("
    SELECT s.*, 
           COALESCE(COUNT(r.id), 0) as recording_count,
           s.submitted_as_template
    FROM stations s
    LEFT JOIN shows sh ON s.id = sh.station_id 
    LEFT JOIN recordings r ON sh.id = r.show_id
    WHERE s.user_id = ? 
    GROUP BY s.id
    ORDER BY s.name
", [$user_id]);

// Filter out already submitted stations
$available_stations = array_filter($user_stations, function($station) {
    return !$station['submitted_as_template'];
});

// Get categories for selection
$categories = $templateService->getCategories();

$page_title = 'Submit Station Template';
$active_nav = 'browse-templates';

require_once '../includes/header.php';
?>

<!-- Submit Template Content -->
<div class="container mt-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-upload"></i> Submit Station Template</h1>
                    <p class="text-muted">Share your station configuration with the community</p>
                </div>
                <div>
                    <a href="/browse-templates.php" class="btn btn-outline-secondary">
                        <i class="fas fa-arrow-left"></i> Back to Browse
                    </a>
                </div>
            </div>
        </div>
    </div>

    <?php if (empty($available_stations)): ?>
        <!-- No Stations Available -->
        <div class="text-center py-5">
            <i class="fas fa-radio fa-3x text-muted mb-3"></i>
            <h4>No Stations Available for Submission</h4>
            <p class="text-muted">You need to have stations that haven't been submitted as templates yet.</p>
            <div class="mt-4">
                <a href="/stations.php" class="btn btn-primary me-2">
                    <i class="fas fa-plus"></i> Add New Station
                </a>
                <a href="/browse-templates.php" class="btn btn-outline-primary">
                    <i class="fas fa-clone"></i> Browse Templates
                </a>
            </div>
        </div>
    <?php else: ?>
        <!-- Station Selection -->
        <div class="row">
            <div class="col-lg-8 mx-auto">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-radio"></i> Select Station to Submit</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <?php foreach ($available_stations as $station): ?>
                                <div class="col-md-6 mb-3">
                                    <div class="card station-selection-card h-100" style="cursor: pointer;" onclick="selectStation(<?= $station['id'] ?>, '<?= h($station['name']) ?>')">
                                        <div class="card-body text-center">
                                            <?php if ($station['logo_url'] || $station['local_logo_path']): ?>
                                                <img src="<?= h($station['logo_url'] ?: $station['local_logo_path']) ?>" 
                                                     alt="<?= h($station['name']) ?>" 
                                                     class="img-fluid mb-3" 
                                                     style="max-width: 80px; max-height: 80px; object-fit: cover;"
                                                     onerror="this.src='/assets/images/default-station-logo.png'">
                                            <?php else: ?>
                                                <div class="bg-light rounded mb-3 d-flex align-items-center justify-content-center" style="width: 80px; height: 80px; margin: 0 auto;">
                                                    <i class="fas fa-radio fa-2x text-muted"></i>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <h6><?= h($station['name']) ?></h6>
                                            <p class="text-muted small mb-2">
                                                <strong><?= h($station['call_letters']) ?></strong>
                                            </p>
                                            
                                            <div class="small text-muted">
                                                <div><?= $station['recording_count'] ?> recordings</div>
                                                <?php if ($station['stream_url']): ?>
                                                    <div><i class="fas fa-check text-success"></i> Stream URL</div>
                                                <?php endif; ?>
                                                <?php if ($station['website_url']): ?>
                                                    <div><i class="fas fa-check text-success"></i> Website</div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<!-- Template Submission Modal -->
<div class="modal fade" id="submissionModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Submit Template: <span id="selectedStationName"></span></h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="submit_template">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    <input type="hidden" name="station_id" id="selectedStationId">
                    
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Community Contribution:</strong> Your station will be reviewed by administrators before being made public. You can customize the template information below.
                    </div>
                    
                    <!-- Template Information -->
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Template Name *</label>
                            <input type="text" name="template_name" id="templateName" class="form-control" required
                                   placeholder="Friendly name for the template">
                            <div class="form-text">This will be the display name in the template library</div>
                        </div>
                        
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Genre</label>
                            <select name="genre" class="form-select">
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
                        <textarea name="template_description" class="form-control" rows="3"
                                  placeholder="Describe this station and what makes it special..."></textarea>
                        <div class="form-text">Help other users understand what this station offers</div>
                    </div>
                    
                    <!-- Categories -->
                    <div class="mb-3">
                        <label class="form-label">Categories</label>
                        <div class="row">
                            <?php foreach ($categories as $category): ?>
                                <div class="col-md-6 col-lg-4 mb-2">
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" 
                                               name="category_ids[]" value="<?= $category['id'] ?>" 
                                               id="category_<?= $category['id'] ?>">
                                        <label class="form-check-label" for="category_<?= $category['id'] ?>">
                                            <i class="<?= h($category['icon']) ?>"></i> <?= h($category['name']) ?>
                                        </label>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                    
                    <!-- Technical Details -->
                    <h6>Technical Details <small class="text-muted">(Optional)</small></h6>
                    <div class="row">
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Language</label>
                            <input type="text" name="language" class="form-control" value="English">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Country</label>
                            <input type="text" name="country" class="form-control" value="United States">
                        </div>
                        
                        <div class="col-md-4 mb-3">
                            <label class="form-label">Stream Bitrate</label>
                            <input type="text" name="bitrate" class="form-control" placeholder="e.g., 128kbps">
                        </div>
                    </div>
                    
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Audio Format</label>
                            <select name="format" class="form-select">
                                <option value="">Unknown</option>
                                <option value="MP3">MP3</option>
                                <option value="AAC">AAC</option>
                                <option value="OGG">OGG</option>
                                <option value="FLAC">FLAC</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i>
                        <strong>Submission Guidelines:</strong>
                        <ul class="mb-0 mt-2">
                            <li>Ensure your station has a working stream URL</li>
                            <li>Verify the station information is accurate</li>
                            <li>Only submit stations you have permission to share</li>
                            <li>Templates will be reviewed before becoming public</li>
                        </ul>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-upload"></i> Submit Template
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<style>
.station-selection-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border: 2px solid transparent;
}

.station-selection-card:hover {
    transform: translateY(-3px);
    box-shadow: 0 6px 20px rgba(0,0,0,0.15);
    border-color: var(--bs-primary);
}

.station-selection-card.selected {
    border-color: var(--bs-primary);
    background-color: rgba(var(--bs-primary-rgb), 0.05);
}
</style>

<script>
function selectStation(stationId, stationName) {
    // Highlight selected station
    document.querySelectorAll('.station-selection-card').forEach(card => {
        card.classList.remove('selected');
    });
    event.currentTarget.classList.add('selected');
    
    // Update modal
    document.getElementById('selectedStationId').value = stationId;
    document.getElementById('selectedStationName').textContent = stationName;
    document.getElementById('templateName').value = stationName;
    
    // Show modal
    const modal = new bootstrap.Modal(document.getElementById('submissionModal'));
    modal.show();
}
</script>

<?php
require_once '../includes/footer.php';
?>