<?php
/**
 * RadioGrab - Recordings Management and Player
 *
 * This file provides the web interface for browsing, playing, and managing recorded
 * radio shows. It includes functionalities for filtering, sorting, downloading,
 * and deleting recordings.
 *
 * Key Variables:
 * - `$show_id`: Filter recordings by a specific show ID.
 * - `$station_id`: Filter recordings by a specific station ID.
 * - `$search`: Search term for recording titles, show names, or station names.
 * - `$sort`: The column to sort recordings by (e.g., `recorded_at`, `title`).
 * - `$order`: The sort order (`asc` or `desc`).
 * - `$recordings`: An array of recording data retrieved from the database.
 * - `$total_count`: The total number of recordings matching the filters.
 * - `$total_pages`: The total number of pages for pagination.
 *
 * Inter-script Communication:
 * - This script interacts with the database to fetch recording, show, and station data.
 * - It uses `includes/database.php` for database connection and `includes/functions.php` for helper functions.
 * - JavaScript functions in `assets/js/radiograb.js` handle audio playback and deletion confirmation.
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Handle recording deletion
if ($_POST['action'] ?? '' === 'delete' && isset($_POST['recording_id'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        try {
            $recording_id = (int)$_POST['recording_id'];
            
            // Get recording file path before deletion
            $recording = $db->fetchOne("SELECT filename FROM recordings WHERE id = ?", [$recording_id]);
            
            // Delete from database
            $db->delete('recordings', 'id = ?', [$recording_id]);
            
            // Delete file if it exists
            if ($recording && $recording['filename']) {
                $file_path = getRecordingPath($recording['filename']);
                if (file_exists($file_path)) {
                    unlink($file_path);
                }
            }
            
            setFlashMessage('success', 'Recording deleted successfully');
        } catch (Exception $e) {
            setFlashMessage('danger', 'Failed to delete recording');
        }
    }
    header('Location: /recordings.php');
    exit;
}

// Get filter parameters
$show_id = isset($_GET['show_id']) ? (int)$_GET['show_id'] : null;
$station_id = isset($_GET['station_id']) ? (int)$_GET['station_id'] : null;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$sort = isset($_GET['sort']) ? $_GET['sort'] : 'recorded_at';
$order = isset($_GET['order']) && $_GET['order'] === 'asc' ? 'asc' : 'desc';

// Build query
$where_conditions = [];
$params = [];

if ($show_id) {
    $where_conditions[] = "r.show_id = ?";
    $params[] = $show_id;
}

if ($station_id) {
    $where_conditions[] = "s.station_id = ?";
    $params[] = $station_id;
}

if ($search) {
    $where_conditions[] = "(r.title LIKE ? OR s.name LIKE ? OR st.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

// Filter out playlist uploads - only show regular recordings
$where_conditions[] = "(s.show_type != 'playlist' OR s.show_type IS NULL OR r.source_type != 'uploaded')";

$where_clause = $where_conditions ? 'WHERE ' . implode(' AND ', $where_conditions) : '';

// Validate sort column
$valid_sorts = ['recorded_at', 'title', 'show_name', 'station_name', 'duration_seconds', 'file_size_bytes'];
if (!in_array($sort, $valid_sorts)) {
    $sort = 'recorded_at';
}

try {
    // Get recordings with pagination
    $page = max(1, (int)($_GET['page'] ?? 1));
    $per_page = 20;
    $offset = ($page - 1) * $per_page;
    
    $recordings = $db->fetchAll("
        SELECT r.*, s.name as show_name, st.name as station_name, st.logo_url
        FROM recordings r 
        JOIN shows s ON r.show_id = s.id 
        JOIN stations st ON s.station_id = st.id 
        $where_clause
        ORDER BY $sort $order
        LIMIT $per_page OFFSET $offset
    ", $params);
    
    // Get total count for pagination
    $total_count = $db->fetchOne("
        SELECT COUNT(*) as count
        FROM recordings r 
        JOIN shows s ON r.show_id = s.id 
        JOIN stations st ON s.station_id = st.id 
        $where_clause
    ", $params)['count'];
    
    $total_pages = ceil($total_count / $per_page);
    
    // Get filter options
    $shows = $db->fetchAll("
        SELECT s.id, s.name, st.name as station_name 
        FROM shows s 
        JOIN stations st ON s.station_id = st.id 
        ORDER BY st.name, s.name
    ");
    
    $stations = $db->fetchAll("SELECT id, name FROM stations ORDER BY name");
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $recordings = [];
    $total_count = 0;
    $total_pages = 0;
    $shows = [];
    $stations = [];
}

// Helper function to build query string for pagination
function buildQueryString($page = null) {
    $params = $_GET;
    if ($page !== null) {
        $params['page'] = $page;
    }
    return http_build_query($params);
}
?>
<?php
// Set page variables for shared template
$page_title = 'Recordings';
$active_nav = 'recordings';

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
                <h1><i class="fas fa-file-audio"></i> Recordings</h1>
                <p class="text-muted">Browse and play your recorded radio shows</p>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" 
                               value="<?= h($search) ?>" placeholder="Search recordings...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Show</label>
                        <select class="form-select" name="show_id">
                            <option value="">All Shows</option>
                            <?php foreach ($shows as $show): ?>
                                <option value="<?= $show['id'] ?>" <?= $show_id == $show['id'] ? 'selected' : '' ?>>
                                    <?= h($show['name']) ?> (<?= h($show['station_name']) ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-2">
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
                    <div class="col-md-2">
                        <label class="form-label">Sort By</label>
                        <select class="form-select" name="sort">
                            <option value="recorded_at" <?= $sort === 'recorded_at' ? 'selected' : '' ?>>Date</option>
                            <option value="title" <?= $sort === 'title' ? 'selected' : '' ?>>Title</option>
                            <option value="show_name" <?= $sort === 'show_name' ? 'selected' : '' ?>>Show</option>
                            <option value="duration_seconds" <?= $sort === 'duration_seconds' ? 'selected' : '' ?>>Duration</option>
                            <option value="file_size_bytes" <?= $sort === 'file_size_bytes' ? 'selected' : '' ?>>Size</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">Order</label>
                        <select class="form-select" name="order">
                            <option value="desc" <?= $order === 'desc' ? 'selected' : '' ?>>Newest First</option>
                            <option value="asc" <?= $order === 'asc' ? 'selected' : '' ?>>Oldest First</option>
                        </select>
                    </div>
                    <div class="col-12">
                        <button type="submit" class="btn btn-primary">
                            <i class="fas fa-search"></i> Filter
                        </button>
                        <a href="/recordings.php" class="btn btn-outline-secondary">
                            <i class="fas fa-times"></i> Clear
                        </a>
                    </div>
                </form>
            </div>
        </div>

        <!-- Results Summary -->
        <?php if ($total_count > 0): ?>
            <div class="row mb-3">
                <div class="col">
                    <p class="text-muted">
                        Showing <?= number_format($total_count) ?> recording<?= $total_count != 1 ? 's' : '' ?>
                        <?php if ($search): ?>
                            matching "<?= h($search) ?>"
                        <?php endif; ?>
                    </p>
                </div>
            </div>
        <?php endif; ?>

        <!-- Recordings List -->
        <?php if (empty($recordings)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-file-audio fa-3x text-muted mb-3"></i>
                    <h3>No recordings found</h3>
                    <?php if ($search || $show_id || $station_id): ?>
                        <p class="text-muted mb-4">Try adjusting your filters to see more results.</p>
                        <a href="/recordings.php" class="btn btn-primary">Clear Filters</a>
                    <?php else: ?>
                        <p class="text-muted mb-4">Start recording shows to see them here.</p>
                        <a href="/stations.php" class="btn btn-primary">Add Stations</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($recordings as $recording): ?>
                    <div class="col-12 mb-4">
                        <div class="card recording-card">
                            <div class="card-body">
                                <div class="row">
                                    <!-- Recording Info -->
                                    <div class="col-md-4">
                                        <div class="d-flex align-items-start">
                                            <img src="<?= h(getStationLogo(['logo_url' => $recording['logo_url']])) ?>" 
                                                 alt="<?= h($recording['station_name']) ?>" 
                                                 class="station-logo me-3"
                                                 onerror="this.src='/assets/images/default-station-logo.png'">
                                            <div>
                                                <h5 class="card-title mb-1">
                                                    <?= h($recording['title'] ?: $recording['show_name']) ?>
                                                </h5>
                                                <div class="recording-meta">
                                                    <div><strong><?= h($recording['show_name']) ?></strong></div>
                                                    <div><?= h($recording['station_name']) ?></div>
                                                    <div class="text-muted">
                                                        <i class="fas fa-calendar"></i> 
                                                        <?= date('M j, Y', strtotime($recording['recorded_at'])) ?>
                                                    </div>
                                                    <div class="text-muted">
                                                        <i class="fas fa-clock"></i> 
                                                        <?= formatDuration($recording['duration_seconds']) ?>
                                                        <span class="ms-2">
                                                            <i class="fas fa-hdd"></i> 
                                                            <?= formatFileSize($recording['file_size_bytes']) ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <!-- Audio Player -->
                                    <div class="col-md-6">
                                        <?php if (recordingFileExists($recording['filename'])): ?>
                                            <div class="audio-player">
                                                <audio class="d-none" preload="metadata">
                                                    <source src="<?= getRecordingUrl($recording['filename']) ?>" type="audio/mpeg">
                                                    Your browser does not support the audio element.
                                                </audio>
                                                
                                                <div class="d-flex align-items-center mb-2">
                                                    <button class="btn btn-primary btn-sm play-btn me-2">
                                                        <i class="fas fa-play"></i>
                                                    </button>
                                                    <button class="btn btn-outline-secondary btn-sm skip-back me-1" title="Skip back 15s">
                                                        <i class="fas fa-backward"></i> 15s
                                                    </button>
                                                    <button class="btn btn-outline-secondary btn-sm skip-forward me-2" title="Skip forward 15s">
                                                        15s <i class="fas fa-forward"></i>
                                                    </button>
                                                    <div class="duration-display ms-auto">
                                                        <span class="current-time">00:00</span> / 
                                                        <span class="total-time">--:--</span>
                                                    </div>
                                                </div>
                                                
                                                <div class="waveform-container mb-2" role="progressbar">
                                                    <div class="waveform-progress" style="width: 0%"></div>
                                                </div>
                                                
                                                <div class="d-flex align-items-center">
                                                    <small class="text-muted">
                                                        <i class="fas fa-volume-up"></i> Volume
                                                    </small>
                                                    <input type="range" class="form-range ms-2 volume-control" 
                                                           min="0" max="100" value="80" style="width: 100px;">
                                                </div>
                                            </div>
                                        <?php else: ?>
                                            <div class="audio-player text-center">
                                                <div class="text-muted">
                                                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i>
                                                    <p>Recording file not found</p>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Actions -->
                                    <div class="col-md-2">
                                        <div class="d-grid gap-2">
                                            <?php if (recordingFileExists($recording['filename'])): ?>
                                                <a href="<?= getRecordingUrl($recording['filename']) ?>" 
                                                   class="btn btn-outline-primary btn-sm" 
                                                   download="<?= h($recording['filename']) ?>">
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                            <?php endif; ?>
                                            
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-sm delete-recording"
                                                    data-recording-id="<?= $recording['id'] ?>"
                                                    data-recording-title="<?= h($recording['title'] ?: $recording['show_name']) ?>">
                                                <i class="fas fa-trash"></i> Delete
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <!-- Pagination -->
            <?php if ($total_pages > 1): ?>
                <nav aria-label="Recordings pagination">
                    <ul class="pagination justify-content-center">
                        <!-- Previous Page -->
                        <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= buildQueryString($page - 1) ?>">
                                <i class="fas fa-chevron-left"></i> Previous
                            </a>
                        </li>
                        
                        <!-- Page Numbers -->
                        <?php
                        $start_page = max(1, $page - 2);
                        $end_page = min($total_pages, $page + 2);
                        
                        if ($start_page > 1): ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= buildQueryString(1) ?>">1</a>
                            </li>
                            <?php if ($start_page > 2): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                        <?php endif; ?>
                        
                        <?php for ($i = $start_page; $i <= $end_page; $i++): ?>
                            <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                <a class="page-link" href="?<?= buildQueryString($i) ?>"><?= $i ?></a>
                            </li>
                        <?php endfor; ?>
                        
                        <?php if ($end_page < $total_pages): ?>
                            <?php if ($end_page < $total_pages - 1): ?>
                                <li class="page-item disabled">
                                    <span class="page-link">...</span>
                                </li>
                            <?php endif; ?>
                            <li class="page-item">
                                <a class="page-link" href="?<?= buildQueryString($total_pages) ?>"><?= $total_pages ?></a>
                            </li>
                        <?php endif; ?>
                        
                        <!-- Next Page -->
                        <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                            <a class="page-link" href="?<?= buildQueryString($page + 1) ?>">
                                Next <i class="fas fa-chevron-right"></i>
                            </a>
                        </li>
                    </ul>
                </nav>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Recording</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the recording <strong id="recordingTitle"></strong>?</p>
                    <p class="text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        This will permanently delete the audio file. This action cannot be undone.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="recording_id" id="deleteRecordingId">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Recording
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php
// RadioGrab.js handles the delete functionality
$additional_js = '<script src="/assets/js/radiograb.js"></script>';
require_once '../includes/footer.php';
?>