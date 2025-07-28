<?php
/**
 * RadioGrab - Shows Management
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Handle show actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid security token');
        header('Location: /shows.php');
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete' && isset($_POST['show_id'])) {
        try {
            $show_id = (int)$_POST['show_id'];
            $db->delete('shows', 'id = ?', [$show_id]);
            setFlashMessage('success', 'Show deleted successfully');
        } catch (Exception $e) {
            setFlashMessage('danger', 'Failed to delete show');
        }
    }
    elseif ($action === 'toggle_status' && isset($_POST['show_id'])) {
        try {
            $show_id = (int)$_POST['show_id'];
            $current_status = $db->fetchOne("SELECT active FROM shows WHERE id = ?", [$show_id])['active'];
            $new_status = $current_status ? 0 : 1;
            
            $db->update('shows', ['active' => $new_status], 'id = ?', [$show_id]);
            
            $status_text = $new_status ? 'activated' : 'deactivated';
            setFlashMessage('success', "Show {$status_text} successfully");
        } catch (Exception $e) {
            setFlashMessage('danger', 'Failed to update show status');
        }
    }
    
    header('Location: /shows.php' . ($_GET ? '?' . http_build_query($_GET) : ''));
    exit;
}

// Get filter parameters
$station_id = isset($_GET['station_id']) ? (int)$_GET['station_id'] : null;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query
$where_conditions = [];
$params = [];

if ($station_id) {
    $where_conditions[] = "s.station_id = ?";
    $params[] = $station_id;
}

if ($status === 'active') {
    $where_conditions[] = "s.active = 1";
} elseif ($status === 'inactive') {
    $where_conditions[] = "s.active = 0";
}

if ($search) {
    $where_conditions[] = "(s.name LIKE ? OR s.description LIKE ? OR st.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

try {
    // Get shows with station and recording info
    $shows = $db->fetchAll("
        SELECT s.*, st.name as station_name, st.logo_url, st.timezone as station_timezone,
               COUNT(r.id) as recording_count,
               MAX(r.recorded_at) as latest_recording
        FROM shows s 
        JOIN stations st ON s.station_id = st.id 
        LEFT JOIN recordings r ON s.id = r.show_id
        $where_clause
        GROUP BY s.id 
        ORDER BY s.name
    ", $params);
    
    // Get stations for filter
    $stations = $db->fetchAll("SELECT id, name FROM stations ORDER BY name");
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $shows = [];
    $stations = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shows - RadioGrab</title>
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
                        <a class="nav-link active" href="/shows.php">Shows</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/recordings.php">Recordings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/feeds.php">RSS Feeds</a>
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
                <h1><i class="fas fa-microphone"></i> Radio Shows</h1>
                <p class="text-muted">Manage your recorded radio shows and schedules</p>
            </div>
            <div class="col-auto">
                <a href="/add-show.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Show
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" 
                               value="<?= h($search) ?>" placeholder="Search shows...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Station</label>
                        <select class="form-select" name="station_id">
                            <option value="">All Stations</option>
                            <?php foreach ($stations as $station): ?>
                                <option value="<?= $station['id'] ?>" <?= $station_id == $station['id'] ? 'selected' : '' ?>>
                                    <?= h($station['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Shows</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active Only</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Shows List -->
        <?php if (empty($shows)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-microphone fa-3x text-muted mb-3"></i>
                    <h3>No shows found</h3>
                    <?php if ($search || $station_id || $status): ?>
                        <p class="text-muted mb-4">Try adjusting your filters to see more results.</p>
                        <a href="/shows.php" class="btn btn-primary">Clear Filters</a>
                    <?php else: ?>
                        <p class="text-muted mb-4">Add stations and import their schedules to get started.</p>
                        <a href="/stations.php" class="btn btn-primary">Manage Stations</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($shows as $show): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start mb-3">
                                    <img src="<?= h(getStationLogo(['logo_url' => $show['logo_url']])) ?>" 
                                         alt="<?= h($show['station_name']) ?>" 
                                         class="station-logo me-3"
                                         onerror="this.src='/assets/images/default-station-logo.png'">
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-1"><?= h($show['name']) ?></h5>
                                        <small class="text-muted"><?= h($show['station_name']) ?></small>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" 
                                               id="toggle<?= $show['id'] ?>"
                                               <?= $show['active'] ? 'checked' : '' ?>
                                               onchange="toggleShowStatus(<?= $show['id'] ?>)">
                                        <label class="form-check-label" for="toggle<?= $show['id'] ?>">
                                            <span class="badge <?= $show['active'] ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= $show['active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                
                                <?php if ($show['description']): ?>
                                    <p class="card-text text-muted small mb-2">
                                        <?= h(substr($show['description'], 0, 100)) ?>
                                        <?= strlen($show['description']) > 100 ? '...' : '' ?>
                                    </p>
                                <?php endif; ?>
                                
                                <div class="mb-3">
                                    <div class="row text-center">
                                        <div class="col">
                                            <div class="fw-bold"><?= $show['recording_count'] ?></div>
                                            <small class="text-muted">Recordings</small>
                                        </div>
                                        <div class="col">
                                            <div class="fw-bold">
                                                <?= $show['latest_recording'] ? timeAgo($show['latest_recording']) : 'Never' ?>
                                            </div>
                                            <small class="text-muted">Latest</small>
                                        </div>
                                    </div>
                                    
                                    <?php if ($show['schedule_description']): ?>
                                        <div class="mt-2">
                                            <small class="text-muted">
                                                <i class="fas fa-calendar"></i> 
                                                <?= h($show['schedule_description']) ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Schedule Status -->
                                    <?php if ($show['schedule_pattern']): ?>
                                        <div class="mt-1">
                                            <small class="text-success">
                                                <i class="fas fa-check-circle"></i> 
                                                Scheduled for automatic recording
                                            </small>
                                        </div>
                                    <?php else: ?>
                                        <div class="mt-1">
                                            <small class="text-warning">
                                                <i class="fas fa-exclamation-triangle"></i> 
                                                No schedule configured
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($show['timezone'] || $show['station_timezone']): ?>
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                <i class="fas fa-clock"></i> 
                                                <?= h($show['timezone'] ?: $show['station_timezone']) ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($show['duration_minutes']): ?>
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                <i class="fas fa-clock"></i> 
                                                <?= $show['duration_minutes'] ?> minutes
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($show['host']): ?>
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                <i class="fas fa-user"></i> 
                                                <?= h($show['host']) ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <div class="card-footer bg-transparent">
                                <div class="btn-group w-100" role="group">
                                    <a href="/edit-show.php?id=<?= $show['id'] ?>" 
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="/recordings.php?show_id=<?= $show['id'] ?>" 
                                       class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-file-audio"></i> Recordings
                                    </a>
                                    <button type="button" 
                                            class="btn btn-outline-secondary btn-sm schedule-manager"
                                            data-show-id="<?= $show['id'] ?>"
                                            data-show-name="<?= h($show['name']) ?>"
                                            title="Manage Schedule">
                                        <i class="fas fa-clock"></i>
                                    </button>
                                    <?php if ($show['recording_count'] > 0): ?>
                                        <a href="/feeds.php#show-<?= $show['id'] ?>" 
                                           class="btn btn-outline-success btn-sm"
                                           title="RSS Feed">
                                            <i class="fas fa-rss"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" 
                                            class="btn btn-outline-danger btn-sm delete-confirm"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteModal"
                                            data-show-id="<?= $show['id'] ?>"
                                            data-show-name="<?= h($show['name']) ?>"
                                            data-item="show">
                                        <i class="fas fa-trash"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Show</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the show <strong id="showName"></strong>?</p>
                    <p class="text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        This will also delete all associated recordings. This action cannot be undone.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="show_id" id="deleteShowId">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Show
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden forms for AJAX actions -->
    <form id="toggleStatusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="show_id" id="toggleShowId">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
    </form>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/radiograb.js"></script>
    <script>
        // Handle delete modal
        document.addEventListener('DOMContentLoaded', function() {
            const deleteModal = document.getElementById('deleteModal');
            deleteModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const showId = button.getAttribute('data-show-id');
                const showName = button.getAttribute('data-show-name');
                
                document.getElementById('deleteShowId').value = showId;
                document.getElementById('showName').textContent = showName;
            });
        });
        
        // Toggle show status
        function toggleShowStatus(showId) {
            document.getElementById('toggleShowId').value = showId;
            document.getElementById('toggleStatusForm').submit();
        }
    </script>

    <!-- Footer -->
    <footer class="bg-light mt-5 py-3">
        <div class="container">
            <div class="row">
                <div class="col text-center text-muted">
                    <small>
                        RadioGrab - TiVo for Radio | 
                        Version: <?= getVersionNumber() ?>
                    </small>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>