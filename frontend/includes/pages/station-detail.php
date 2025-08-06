<?php
/**
 * Station Detail Page Template
 * Displays comprehensive information about a single station
 */

// Get station shows with recording counts
try {
    $shows = $db->fetchAll("
        SELECT s.*, 
               COUNT(r.id) as recording_count,
               MAX(r.recorded_at) as latest_recording,
               SUM(CASE WHEN r.recorded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as recent_recordings
        FROM shows s 
        LEFT JOIN recordings r ON s.id = r.show_id
        WHERE s.station_id = ? AND s.active = 1 AND (s.show_type != 'playlist' OR s.show_type IS NULL)
        GROUP BY s.id 
        ORDER BY s.name ASC
    ", [$station['id']]);
    
    // Get station statistics
    $stats = $db->fetchOne("
        SELECT 
            COUNT(DISTINCT s.id) as total_shows,
            COUNT(DISTINCT CASE WHEN s.active = 1 THEN s.id END) as active_shows,
            COUNT(r.id) as total_recordings,
            SUM(r.file_size_bytes) as total_size_bytes,
            MAX(r.recorded_at) as latest_recording
        FROM shows s 
        LEFT JOIN recordings r ON s.id = r.show_id
        WHERE s.station_id = ? AND (s.show_type != 'playlist' OR s.show_type IS NULL)
    ", [$station['id']]);
    
    // Get recent recordings
    $recent_recordings = $db->fetchAll("
        SELECT r.*, s.name as show_name, s.slug as show_slug
        FROM recordings r 
        JOIN shows s ON r.show_id = s.id 
        WHERE s.station_id = ? AND (s.show_type != 'playlist' OR s.show_type IS NULL)
        ORDER BY r.recorded_at DESC 
        LIMIT 10
    ", [$station['id']]);
    
} catch (Exception $e) {
    $shows = [];
    $stats = ['total_shows' => 0, 'active_shows' => 0, 'total_recordings' => 0, 'total_size_bytes' => 0];
    $recent_recordings = [];
    error_log("Station detail error: " . $e->getMessage());
}

// Set page variables
$page_title = $station['name'] . ' - RadioGrab';
$active_nav = 'stations';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?></title>
    <meta name="description" content="<?= h($station['description'] ?: $station['name'] . ' radio station recordings and shows') ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?= h($station['name']) ?>">
    <meta property="og:description" content="<?= h($station['description'] ?: 'Radio station recordings and shows') ?>">
    <meta property="og:image" content="<?= h($station['logo_url'] ?: '/assets/images/default-station-logo.png') ?>">
    <meta property="og:url" content="<?= h($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">
    
    <link rel="icon" href="/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/radiograb.css" rel="stylesheet">
    <link href="/assets/css/on-air.css" rel="stylesheet">
</head>
<body>
    <?php require_once '../includes/navbar.php'; ?>

    <!-- Station Header -->
    <div class="container-fluid bg-light py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-2">
                    <img src="<?= h($station['logo_url'] ?: '/assets/images/default-station-logo.png') ?>" 
                         alt="<?= h($station['name']) ?>" 
                         class="station-logo station-logo-xl img-fluid rounded shadow"
                         onerror="this.src='/assets/images/default-station-logo.png'"
                         loading="lazy">
                </div>
                <div class="col-md-8">
                    <h1 class="display-5 mb-2"><?= h($station['name']) ?></h1>
                    <?php if ($station['call_letters']): ?>
                        <h2 class="h4 text-muted mb-2"><?= h($station['call_letters']) ?></h2>
                    <?php endif; ?>
                    <?php if ($station['frequency']): ?>
                        <p class="h5 text-primary mb-2"><?= h($station['frequency']) ?></p>
                    <?php endif; ?>
                    <?php if ($station['location']): ?>
                        <p class="text-muted mb-2">
                            <i class="fas fa-map-marker-alt"></i> <?= h($station['location']) ?>
                        </p>
                    <?php endif; ?>
                    <?php if ($station['description']): ?>
                        <p class="lead"><?= h($station['description']) ?></p>
                    <?php endif; ?>
                </div>
                <div class="col-md-2 text-end">
                    <?php if ($station['website_url']): ?>
                        <a href="<?= h($station['website_url']) ?>" target="_blank" class="btn btn-outline-primary mb-2">
                            <i class="fas fa-globe"></i> Website
                        </a>
                    <?php endif; ?>
                    <a href="/edit-station.php?id=<?= $station['id'] ?>" class="btn btn-outline-secondary mb-2">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <a href="/add-show.php?station_id=<?= $station['id'] ?>" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Show
                    </a>
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
                        <h3 class="text-primary"><?= number_format($stats['total_shows']) ?></h3>
                        <p class="card-text">Total Shows</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><?= number_format($stats['active_shows']) ?></h3>
                        <p class="card-text">Active Shows</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-info"><?= number_format($stats['total_recordings']) ?></h3>
                        <p class="card-text">Recordings</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-warning"><?= formatFileSize($stats['total_size_bytes'] ?: 0) ?></h3>
                        <p class="card-text">Total Size</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Shows List -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="fas fa-microphone"></i> Shows</h5>
                        <a href="/add-show.php?station_id=<?= $station['id'] ?>" class="btn btn-sm btn-primary">
                            <i class="fas fa-plus"></i> Add Show
                        </a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($shows)): ?>
                            <div class="text-center py-4">
                                <i class="fas fa-microphone fa-3x text-muted mb-3"></i>
                                <h4>No shows yet</h4>
                                <p class="text-muted">Start by adding a show to this station.</p>
                                <a href="/add-show.php?station_id=<?= $station['id'] ?>" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Show
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($shows as $show): ?>
                                    <div class="list-group-item">
                                        <div class="row align-items-center">
                                            <div class="col-md-6">
                                                <h6 class="mb-1">
                                                    <a href="/<?= strtolower($station['call_letters']) ?>/<?= h($show['slug']) ?>" 
                                                       class="text-decoration-none">
                                                        <?= h($show['name']) ?>
                                                    </a>
                                                    <?php if (!$show['active']): ?>
                                                        <span class="badge bg-secondary ms-2">Inactive</span>
                                                    <?php endif; ?>
                                                </h6>
                                                <?php if ($show['description']): ?>
                                                    <p class="mb-1 text-muted small"><?= h(substr($show['description'], 0, 100)) ?><?= strlen($show['description']) > 100 ? '...' : '' ?></p>
                                                <?php endif; ?>
                                                <?php if ($show['schedule_description']): ?>
                                                    <small class="text-info">
                                                        <i class="fas fa-clock"></i> <?= h($show['schedule_description']) ?>
                                                    </small>
                                                <?php endif; ?>
                                            </div>
                                            <div class="col-md-3 text-center">
                                                <div class="small">
                                                    <div><strong><?= number_format($show['recording_count']) ?></strong> recordings</div>
                                                    <?php if ($show['recent_recordings'] > 0): ?>
                                                        <div class="text-success"><?= $show['recent_recordings'] ?> this month</div>
                                                    <?php endif; ?>
                                                    <?php if ($show['latest_recording']): ?>
                                                        <div class="text-muted">Last: <?= timeAgo($show['latest_recording']) ?></div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            <div class="col-md-3 text-end">
                                                <a href="/<?= strtolower($station['call_letters']) ?>/<?= h($show['slug']) ?>" 
                                                   class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-eye"></i> View
                                                </a>
                                                <a href="/edit-show.php?id=<?= $show['id'] ?>" 
                                                   class="btn btn-sm btn-outline-secondary">
                                                    <i class="fas fa-edit"></i> Edit
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Recent Recordings -->
            <div class="col-md-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-file-audio"></i> Recent Recordings</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_recordings)): ?>
                            <div class="text-center py-3">
                                <i class="fas fa-file-audio fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">No recordings yet</p>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach ($recent_recordings as $recording): ?>
                                    <div class="list-group-item px-0 py-2">
                                        <h6 class="mb-1">
                                            <a href="/<?= strtolower($station['call_letters']) ?>/<?= h($recording['show_slug']) ?>" 
                                               class="text-decoration-none">
                                                <?= h($recording['title'] ?: $recording['show_name']) ?>
                                            </a>
                                        </h6>
                                        <small class="text-muted">
                                            <?= date('M j, Y', strtotime($recording['recorded_at'])) ?>
                                            • <?= formatDuration($recording['duration_seconds']) ?>
                                            • <?= formatFileSize($recording['file_size_bytes']) ?>
                                        </small>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="text-center mt-3">
                                <a href="/recordings.php?station_id=<?= $station['id'] ?>" class="btn btn-sm btn-outline-primary">
                                    View All Recordings
                                </a>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/radiograb.js"></script>
</body>
</html>