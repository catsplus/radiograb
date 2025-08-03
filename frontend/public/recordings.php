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
require_once '../includes/auth.php';

$auth = new UserAuth($db);

// Require authentication
requireAuth($auth);

$current_user = $auth->getCurrentUser();
$user_id = $auth->getCurrentUserId();

// Handle recording deletion
if ($_POST['action'] ?? '' === 'delete' && isset($_POST['recording_id'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        try {
            $recording_id = (int)$_POST['recording_id'];
            
            // Get recording file path before deletion (ensure user owns this recording)
            $recording = $db->fetchOne("
                SELECT r.filename 
                FROM recordings r 
                JOIN shows s ON r.show_id = s.id 
                WHERE r.id = ? AND s.user_id = ?
            ", [$recording_id, $user_id]);
            
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

// Add user_id scoping to only show user's recordings
$where_conditions[] = "s.user_id = ?";
$params[] = $user_id;

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
        SELECT r.*, s.name as show_name, s.stream_only, s.content_type, s.is_syndicated,
               st.name as station_name, st.logo_url
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
    
    // Get filter options (user-scoped)
    $shows = $db->fetchAll("
        SELECT s.id, s.name, st.name as station_name 
        FROM shows s 
        JOIN stations st ON s.station_id = st.id 
        WHERE s.user_id = ?
        ORDER BY st.name, s.name
    ", [$user_id]);
    
    $stations = $db->fetchAll("
        SELECT id, name 
        FROM stations 
        WHERE user_id = ? 
        ORDER BY name
    ", [$user_id]);
    
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
                                                <?php if (!$recording['stream_only']): ?>
                                                    <?php
                                                    // Obfuscate the file path for DMCA compliance
                                                    $obfuscated_token = base64_encode($recording['filename']);
                                                    $obfuscated_token = str_replace(['+', '/', '='], ['-', '_', ''], $obfuscated_token);
                                                    ?>
                                                    <a href="/api/get-recording.php?token=<?= $obfuscated_token ?>" 
                                                       class="btn btn-outline-primary btn-sm" 
                                                       download="<?= h($recording['filename']) ?>">
                                                        <i class="fas fa-download"></i> Download
                                                    </a>
                                                <?php else: ?>
                                                    <button type="button" class="btn btn-outline-secondary btn-sm" disabled title="Stream-only show - downloads disabled for DMCA compliance">
                                                        <i class="fas fa-ban"></i> Stream Only
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <!-- Transcription Button -->
                                            <?php if (recordingFileExists($recording['filename'])): ?>
                                                <?php if ($recording['transcript_file'] && file_exists($recording['transcript_file'])): ?>
                                                    <button type="button" 
                                                            class="btn btn-outline-success btn-sm view-transcript"
                                                            data-recording-id="<?= $recording['id'] ?>"
                                                            data-show-name="<?= h($recording['show_name']) ?>"
                                                            title="View transcript">
                                                        <i class="fas fa-file-text"></i> Transcript
                                                    </button>
                                                <?php else: ?>
                                                    <button type="button" 
                                                            class="btn btn-outline-info btn-sm transcribe-recording"
                                                            data-recording-id="<?= $recording['id'] ?>"
                                                            data-show-name="<?= h($recording['show_name']) ?>"
                                                            data-duration="<?= $recording['duration_seconds'] ?>"
                                                            title="Generate transcript">
                                                        <i class="fas fa-microphone"></i> Transcribe
                                                    </button>
                                                <?php endif; ?>
                                            <?php endif; ?>
                                            
                                            <button type="button" 
                                                    class="btn btn-outline-danger btn-sm delete-recording"
                                                    data-recording-id="<?= $recording['id'] ?>"
                                                    data-recording-title="<?= h($recording['title'] ?: $recording['show_name']) ?>"
                                                    data-file-exists="<?= recordingFileExists($recording['filename']) ? 'true' : 'false' ?>">
                                                <i class="fas fa-trash"></i> 
                                                <?= recordingFileExists($recording['filename']) ? 'Delete' : 'Remove Entry' ?>
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
                    <p class="text-danger" id="deleteWarning">
                        <i class="fas fa-exclamation-triangle"></i>
                        This will permanently delete the audio file. This action cannot be undone.
                    </p>
                    <p class="text-info d-none" id="orphanedWarning">
                        <i class="fas fa-info-circle"></i>
                        This will remove the database entry for a missing recording file.
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

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle delete recording modal
        const deleteButtons = document.querySelectorAll('.delete-recording');
        const deleteModal = document.getElementById('deleteModal');
        const recordingTitle = document.getElementById('recordingTitle');
        const deleteRecordingId = document.getElementById('deleteRecordingId');
        const deleteWarning = document.getElementById('deleteWarning');
        const orphanedWarning = document.getElementById('orphanedWarning');
        
        deleteButtons.forEach(button => {
            button.addEventListener('click', function() {
                const recordingId = this.dataset.recordingId;
                const title = this.dataset.recordingTitle;
                const fileExists = this.dataset.fileExists === 'true';
                
                recordingTitle.textContent = title;
                deleteRecordingId.value = recordingId;
                
                // Show appropriate warning based on file existence
                if (fileExists) {
                    deleteWarning.classList.remove('d-none');
                    orphanedWarning.classList.add('d-none');
                } else {
                    deleteWarning.classList.add('d-none');
                    orphanedWarning.classList.remove('d-none');
                }
                
                const modal = new bootstrap.Modal(deleteModal);
                modal.show();
            });
        });
    });
    </script>

    <!-- Transcription Modal -->
    <div class="modal fade" id="transcriptionModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-microphone"></i> Generate Transcript
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="transcriptionProviderSelection">
                        <p>Select a transcription provider to generate a transcript for <strong id="transcribeShowName"></strong>:</p>
                        
                        <div id="providerCards" class="row">
                            <!-- Provider cards will be loaded here -->
                        </div>
                        
                        <div class="alert alert-warning mt-3" id="noProvidersWarning" style="display: none;">
                            <i class="fas fa-exclamation-triangle"></i>
                            No transcription services configured. Please configure your API keys in 
                            <a href="/settings/api-keys.php" class="alert-link">Settings → API Keys</a>.
                        </div>
                    </div>
                    
                    <div id="transcriptionProgress" style="display: none;">
                        <div class="text-center">
                            <div class="spinner-border text-primary mb-3" role="status">
                                <span class="visually-hidden">Transcribing...</span>
                            </div>
                            <h5>Generating Transcript</h5>
                            <p class="text-muted">This may take a few minutes depending on the recording length and provider...</p>
                            <div class="progress mb-3">
                                <div class="progress-bar progress-bar-striped progress-bar-animated" 
                                     role="progressbar" style="width: 100%"></div>
                            </div>
                            <p><strong>Provider:</strong> <span id="selectedProvider"></span></p>
                            <p><strong>Estimated Cost:</strong> $<span id="estimatedCost">0.00</span></p>
                        </div>
                    </div>
                    
                    <div id="transcriptionComplete" style="display: none;">
                        <div class="alert alert-success">
                            <i class="fas fa-check-circle"></i>
                            Transcription completed successfully!
                        </div>
                        <div class="transcription-details">
                            <p><strong>Provider:</strong> <span id="completedProvider"></span></p>
                            <p><strong>Cost:</strong> $<span id="actualCost">0.00</span></p>
                            <p><strong>Duration:</strong> <span id="transcribedDuration">0</span> minutes</p>
                        </div>
                    </div>
                    
                    <div id="transcriptionError" style="display: none;">
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            <strong>Transcription Failed:</strong> <span id="errorMessage"></span>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Transcript Viewer Modal -->
    <div class="modal fade" id="transcriptModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">
                        <i class="fas fa-file-text"></i> Transcript - <span id="transcriptShowName"></span>
                    </h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="transcriptContent">
                        <div class="text-center">
                            <div class="spinner-border text-primary mb-3" role="status">
                                <span class="visually-hidden">Loading transcript...</span>
                            </div>
                            <p>Loading transcript...</p>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-primary" id="copyTranscript">
                        <i class="fas fa-copy"></i> Copy to Clipboard
                    </button>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                </div>
            </div>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Transcription functionality
        const transcriptionModal = document.getElementById('transcriptionModal');
        const transcriptModal = document.getElementById('transcriptModal');
        
        // Handle transcribe recording buttons
        document.querySelectorAll('.transcribe-recording').forEach(button => {
            button.addEventListener('click', function() {
                const recordingId = this.dataset.recordingId;
                const showName = this.dataset.showName;
                const duration = parseInt(this.dataset.duration || 3600);
                
                document.getElementById('transcribeShowName').textContent = showName;
                
                // Load available providers
                loadTranscriptionProviders(recordingId, duration);
                
                const modal = new bootstrap.Modal(transcriptionModal);
                modal.show();
            });
        });
        
        // Handle view transcript buttons
        document.querySelectorAll('.view-transcript').forEach(button => {
            button.addEventListener('click', function() {
                const recordingId = this.dataset.recordingId;
                const showName = this.dataset.showName;
                
                document.getElementById('transcriptShowName').textContent = showName;
                
                // Load transcript content
                loadTranscript(recordingId);
                
                const modal = new bootstrap.Modal(transcriptModal);
                modal.show();
            });
        });
        
        // Copy transcript to clipboard
        document.getElementById('copyTranscript').addEventListener('click', function() {
            const content = document.getElementById('transcriptContent').textContent;
            navigator.clipboard.writeText(content).then(() => {
                showToast('Transcript copied to clipboard', 'success');
            });
        });
        
        function loadTranscriptionProviders(recordingId, duration) {
            // Reset modal state
            document.getElementById('transcriptionProviderSelection').style.display = 'block';
            document.getElementById('transcriptionProgress').style.display = 'none';
            document.getElementById('transcriptionComplete').style.display = 'none';
            document.getElementById('transcriptionError').style.display = 'none';
            
            // Mock provider data - in real implementation, this would come from an API
            const providers = [
                {
                    id: 'openai_whisper',
                    name: 'OpenAI Whisper API',
                    cost_per_minute: 0.006,
                    description: 'High accuracy, official OpenAI service'
                },
                {
                    id: 'deepinfra_whisper',
                    name: 'DeepInfra Whisper',
                    cost_per_minute: 0.0006,
                    description: 'Cost-effective, same quality as OpenAI'
                }
            ];
            
            const providerCards = document.getElementById('providerCards');
            const durationMinutes = duration / 60;
            
            if (providers.length === 0) {
                document.getElementById('noProvidersWarning').style.display = 'block';
                providerCards.innerHTML = '';
                return;
            }
            
            providerCards.innerHTML = providers.map(provider => {
                const estimatedCost = (durationMinutes * provider.cost_per_minute).toFixed(4);
                return `
                    <div class="col-md-6 mb-3">
                        <div class="card provider-card" data-provider="${provider.id}" data-cost="${estimatedCost}">
                            <div class="card-body">
                                <h6 class="card-title">${provider.name}</h6>
                                <p class="card-text text-muted small">${provider.description}</p>
                                <div class="d-flex justify-content-between align-items-center">
                                    <span class="text-success fw-bold">$${estimatedCost}</span>
                                    <button class="btn btn-primary btn-sm transcribe-with-provider" 
                                            data-provider="${provider.id}" 
                                            data-recording-id="${recordingId}"
                                            data-cost="${estimatedCost}">
                                        Select
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
            
            // Add click handlers for provider selection
            document.querySelectorAll('.transcribe-with-provider').forEach(btn => {
                btn.addEventListener('click', function() {
                    const provider = this.dataset.provider;
                    const cost = this.dataset.cost;
                    const recordingId = this.dataset.recordingId;
                    
                    startTranscription(recordingId, provider, cost);
                });
            });
        }
        
        function startTranscription(recordingId, provider, estimatedCost) {
            // Show progress
            document.getElementById('transcriptionProviderSelection').style.display = 'none';
            document.getElementById('transcriptionProgress').style.display = 'block';
            document.getElementById('selectedProvider').textContent = provider;
            document.getElementById('estimatedCost').textContent = estimatedCost;
            
            // Make API call
            const formData = new FormData();
            formData.append('recording_id', recordingId);
            formData.append('provider', provider);
            formData.append('csrf_token', '<?= generateCSRFToken() ?>');
            
            fetch('/api/transcribe-recording.php', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    // Show completion
                    document.getElementById('transcriptionProgress').style.display = 'none';
                    document.getElementById('transcriptionComplete').style.display = 'block';
                    document.getElementById('completedProvider').textContent = data.provider;
                    document.getElementById('actualCost').textContent = data.cost_estimate || estimatedCost;
                    document.getElementById('transcribedDuration').textContent = (data.duration_minutes || 0).toFixed(1);
                    
                    // Refresh page after a delay to show new transcript button
                    setTimeout(() => {
                        location.reload();
                    }, 3000);
                } else {
                    // Show error
                    document.getElementById('transcriptionProgress').style.display = 'none';
                    document.getElementById('transcriptionError').style.display = 'block';
                    document.getElementById('errorMessage').textContent = data.error || 'Unknown error occurred';
                }
            })
            .catch(error => {
                document.getElementById('transcriptionProgress').style.display = 'none';
                document.getElementById('transcriptionError').style.display = 'block';
                document.getElementById('errorMessage').textContent = 'Network error: ' + error.message;
            });
        }
        
        function loadTranscript(recordingId) {
            document.getElementById('transcriptContent').innerHTML = `
                <div class="text-center">
                    <div class="spinner-border text-primary mb-3" role="status">
                        <span class="visually-hidden">Loading transcript...</span>
                    </div>
                    <p>Loading transcript...</p>
                </div>
            `;
            
            fetch(`/api/get-transcript.php?recording_id=${recordingId}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        document.getElementById('transcriptContent').innerHTML = `
                            <div class="transcript-info mb-3">
                                <div class="row">
                                    <div class="col-md-3">
                                        <strong>Provider:</strong> ${data.provider}
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Generated:</strong> ${new Date(data.generated_at).toLocaleDateString()}
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Words:</strong> ${data.word_count.toLocaleString()}
                                    </div>
                                    <div class="col-md-3">
                                        <strong>Cost:</strong> $${(data.cost || 0).toFixed(4)}
                                    </div>
                                </div>
                            </div>
                            <div class="transcript-text">
                                <div class="border p-3 bg-light" style="max-height: 400px; overflow-y: auto;">
                                    <pre style="white-space: pre-wrap; margin: 0;">${data.transcript}</pre>
                                </div>
                            </div>
                        `;
                    } else {
                        document.getElementById('transcriptContent').innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-circle"></i>
                                ${data.error}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    document.getElementById('transcriptContent').innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-circle"></i>
                            Error loading transcript: ${error.message}
                        </div>
                    `;
                });
        }
        
        function showToast(message, type = 'info') {
            // Simple toast notification
            const toast = document.createElement('div');
            toast.className = `alert alert-${type} position-fixed`;
            toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
            toast.innerHTML = `
                ${message}
                <button type="button" class="btn-close" onclick="this.parentElement.remove()"></button>
            `;
            document.body.appendChild(toast);
            
            setTimeout(() => {
                if (toast.parentElement) {
                    toast.remove();
                }
            }, 5000);
        }
    });
    </script>

    <?php
// RadioGrab.js handles the delete functionality
$additional_js = '<script src="/assets/js/radiograb.js"></script>';
require_once '../includes/footer.php';
?>