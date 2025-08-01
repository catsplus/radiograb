<?php
/**
 * Show Detail Page Template
 * Displays comprehensive information about a single show and its recordings
 */

// Get show recordings with pagination
$page = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset = ($page - 1) * $per_page;

try {
    // Get recordings for this show
    $recordings = $db->fetchAll("
        SELECT * FROM recordings 
        WHERE show_id = ? 
        ORDER BY recorded_at DESC 
        LIMIT ? OFFSET ?
    ", [$show['id'], $per_page, $offset]);
    
    // Get total recording count for pagination
    $total_recordings = $db->fetchOne("
        SELECT COUNT(*) as count FROM recordings WHERE show_id = ?
    ", [$show['id']])['count'];
    
    $total_pages = ceil($total_recordings / $per_page);
    
    // Get show statistics
    $stats = $db->fetchOne("
        SELECT 
            COUNT(*) as total_recordings,
            SUM(file_size_bytes) as total_size_bytes,
            AVG(duration_seconds) as avg_duration,
            MIN(recorded_at) as first_recording,
            MAX(recorded_at) as latest_recording,
            SUM(CASE WHEN recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as recent_recordings
        FROM recordings 
        WHERE show_id = ?
    ", [$show['id']]);
    
} catch (Exception $e) {
    $recordings = [];
    $total_recordings = 0;
    $total_pages = 0;
    $stats = [
        'total_recordings' => 0, 
        'total_size_bytes' => 0, 
        'avg_duration' => 0,
        'recent_recordings' => 0
    ];
    error_log("Show detail error: " . $e->getMessage());
}

// Set page variables
$page_title = $show['name'] . ' - ' . $show['station_name'] . ' - RadioGrab';
$active_nav = 'shows';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?></title>
    <meta name="description" content="<?= h($show['description'] ?: $show['name'] . ' recordings from ' . $show['station_name']) ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?= h($show['name']) ?>">
    <meta property="og:description" content="<?= h($show['description'] ?: 'Radio show recordings from ' . $show['station_name']) ?>">
    <meta property="og:image" content="<?= h($show['image_url'] ?: $show['logo_url'] ?: '/assets/images/default-station-logo.png') ?>">
    <meta property="og:url" content="<?= h($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">
    
    <!-- RSS Feed -->
    <link rel="alternate" type="application/rss+xml" title="<?= h($show['name']) ?> RSS Feed" 
          href="/api/enhanced-feeds.php?type=show&id=<?= $show['id'] ?>">
    
    <link rel="icon" href="/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/radiograb.css" rel="stylesheet">
    <link href="/assets/css/on-air.css" rel="stylesheet">
</head>
<body>
    <?php require_once '../includes/navbar.php'; ?>

    <!-- Show Header -->
    <div class="container-fluid bg-light py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-2">
                    <img src="<?= h($show['image_url'] ?: $show['logo_url'] ?: '/assets/images/default-station-logo.png') ?>" 
                         alt="<?= h($show['name']) ?>" 
                         class="img-fluid rounded shadow"
                         style="max-height: 120px; width: auto;"
                         onerror="this.src='/assets/images/default-station-logo.png'">
                </div>
                <div class="col-md-8">
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb">
                            <li class="breadcrumb-item">
                                <a href="/<?= strtolower($show['call_letters']) ?>"><?= h($show['station_name']) ?></a>
                            </li>
                            <li class="breadcrumb-item active"><?= h($show['name']) ?></li>
                        </ol>
                    </nav>
                    <h1 class="display-5 mb-2"><?= h($show['name']) ?></h1>
                    <h2 class="h5 text-muted mb-2"><?= h($show['station_name']) ?></h2>
                    
                    <?php if ($show['host']): ?>
                        <p class="text-primary mb-2">
                            <i class="fas fa-user"></i> Hosted by <?= h($show['host']) ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($show['genre']): ?>
                        <p class="mb-2">
                            <span class="badge bg-secondary"><?= h($show['genre']) ?></span>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($show['schedule_description']): ?>
                        <p class="text-info mb-2">
                            <i class="fas fa-clock"></i> <?= h($show['schedule_description']) ?>
                        </p>
                    <?php endif; ?>
                    
                    <?php if ($show['description']): ?>
                        <p class="lead"><?= h($show['description']) ?></p>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <span class="badge <?= $show['active'] ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $show['active'] ? 'Active' : 'Inactive' ?>
                        </span>
                        <?php if ($show['show_type'] === 'playlist'): ?>
                            <span class="badge bg-info">Playlist</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-2 text-end">
                    <a href="/api/enhanced-feeds.php?type=show&id=<?= $show['id'] ?>" 
                       class="btn btn-outline-warning mb-2" title="RSS Feed">
                        <i class="fas fa-rss"></i> RSS
                    </a>
                    <a href="/edit-show.php?id=<?= $show['id'] ?>" class="btn btn-outline-secondary mb-2">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <?php if ($show['show_type'] !== 'playlist'): ?>
                        <button class="btn btn-primary test-recording" 
                                data-show-id="<?= $show['id'] ?>"
                                data-station-name="<?= h($show['station_name']) ?>">
                            <i class="fas fa-play"></i> Test Recording
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <!-- Statistics Row -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-primary"><?= number_format($stats['total_recordings']) ?></h3>
                        <p class="card-text">Total Recordings</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><?= number_format($stats['recent_recordings']) ?></h3>
                        <p class="card-text">This Month</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-info"><?= formatFileSize($stats['total_size_bytes'] ?: 0) ?></h3>
                        <p class="card-text">Total Size</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning"><?= formatDuration($stats['avg_duration'] ?: 0) ?></h3>
                        <p class="card-text">Avg Duration</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Recordings List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-file-audio"></i> Recordings 
                    <span class="badge bg-secondary"><?= number_format($total_recordings) ?></span>
                </h5>
                <div>
                    <a href="/api/enhanced-feeds.php?type=show&id=<?= $show['id'] ?>" 
                       class="btn btn-sm btn-outline-warning">
                        <i class="fas fa-rss"></i> RSS Feed
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($recordings)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-file-audio fa-3x text-muted mb-3"></i>
                        <h4>No recordings yet</h4>
                        <p class="text-muted">Recordings will appear here when the show is recorded.</p>
                        <?php if ($show['show_type'] !== 'playlist' && $show['active']): ?>
                            <button class="btn btn-primary test-recording" 
                                    data-show-id="<?= $show['id'] ?>"
                                    data-station-name="<?= h($show['station_name']) ?>">
                                <i class="fas fa-play"></i> Test Recording
                            </button>
                        <?php endif; ?>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($recordings as $recording): ?>
                            <div class="col-12 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <h6 class="mb-1"><?= h($recording['title'] ?: $show['name']) ?></h6>
                                                <small class="text-muted">
                                                    <i class="fas fa-calendar"></i> <?= date('M j, Y \a\t g:i A', strtotime($recording['recorded_at'])) ?>
                                                    <span class="ms-3">
                                                        <i class="fas fa-clock"></i> <?= formatDuration($recording['duration_seconds']) ?>
                                                    </span>
                                                    <span class="ms-3">
                                                        <i class="fas fa-hdd"></i> <?= formatFileSize($recording['file_size_bytes']) ?>
                                                    </span>
                                                </small>
                                                <?php if ($recording['description']): ?>
                                                    <p class="mt-2 mb-0 small text-muted"><?= h($recording['description']) ?></p>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-4">
                                                <?php if (recordingFileExists($recording['filename'])): ?>
                                                    <div class="audio-player">
                                                        <audio class="d-none" preload="metadata">
                                                            <source src="<?= getRecordingUrl($recording['filename']) ?>" type="audio/mpeg">
                                                        </audio>
                                                        <div class="d-flex align-items-center">
                                                            <button class="btn btn-primary btn-sm play-btn me-2">
                                                                <i class="fas fa-play"></i>
                                                            </button>
                                                            <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                                                <div class="progress-bar" style="width: 0%"></div>
                                                            </div>
                                                            <small class="time text-muted">--:--</small>
                                                        </div>
                                                    </div>
                                                <?php else: ?>
                                                    <div class="text-muted small">
                                                        <i class="fas fa-exclamation-triangle"></i> File not found
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-2 text-end">
                                                <?php if (recordingFileExists($recording['filename'])): ?>
                                                    <a href="<?= getRecordingUrl($recording['filename']) ?>" 
                                                       class="btn btn-sm btn-outline-primary mb-1" 
                                                       download="<?= h($recording['filename']) ?>">
                                                        <i class="fas fa-download"></i>
                                                    </a>
                                                <?php endif; ?>
                                                <button type="button" 
                                                        class="btn btn-sm btn-outline-danger delete-recording"
                                                        data-recording-id="<?= $recording['id'] ?>"
                                                        data-recording-title="<?= h($recording['title'] ?: $show['name']) ?>"
                                                        data-file-exists="<?= recordingFileExists($recording['filename']) ? 'true' : 'false' ?>">
                                                    <i class="fas fa-trash"></i>
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <!-- Pagination -->
                    <?php if ($total_pages > 1): ?>
                        <nav aria-label="Recordings pagination" class="mt-4">
                            <ul class="pagination justify-content-center">
                                <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page - 1 ?>">Previous</a>
                                </li>
                                
                                <?php
                                $start_page = max(1, $page - 2);
                                $end_page = min($total_pages, $page + 2);
                                
                                for ($i = $start_page; $i <= $end_page; $i++): ?>
                                    <li class="page-item <?= $i === $page ? 'active' : '' ?>">
                                        <a class="page-link" href="?page=<?= $i ?>"><?= $i ?></a>
                                    </li>
                                <?php endfor; ?>
                                
                                <li class="page-item <?= $page >= $total_pages ? 'disabled' : '' ?>">
                                    <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                                </li>
                            </ul>
                        </nav>
                    <?php endif; ?>
                <?php endif; ?>
            </div>
        </div>
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
                    <form method="POST" action="/recordings.php" class="d-inline">
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

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/radiograb.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Handle delete recording modal (same as recordings.php)
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
</body>
</html>