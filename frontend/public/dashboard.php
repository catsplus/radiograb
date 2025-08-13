<?php
/**
 * RadioGrab - User Dashboard
 * Issue #6 - User Authentication & Admin Access
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

// Get user's statistics - adapt to existing database schema
try {
    $stats = [
        'stations' => $db->fetchOne("SELECT COUNT(*) as count FROM stations WHERE user_id = ?", [$user_id])['count'],
        'shows' => $db->fetchOne("SELECT COUNT(*) as count FROM shows")['count'], // No user_id in shows table yet
        'recordings' => $db->fetchOne("SELECT COUNT(*) as count FROM recordings")['count'], // No user filtering yet
        'storage_used' => $db->fetchOne("SELECT COALESCE(SUM(file_size_bytes), 0) as size FROM recordings")['size'] // No user filtering yet
    ];
    
    // Recent recordings - no user filtering until shows table has user_id
    $recent_recordings = $db->fetchAll("
        SELECT r.*, s.name as show_name, st.name as station_name 
        FROM recordings r 
        JOIN shows s ON r.show_id = s.id 
        JOIN stations st ON s.station_id = st.id 
        ORDER BY r.recorded_at DESC 
        LIMIT 5
    ");
    
    // Active shows - no user filtering until shows table has user_id
    $active_shows = $db->fetchAll("
        SELECT s.*, st.name as station_name, st.logo_url,
               COUNT(r.id) as recording_count
        FROM shows s 
        JOIN stations st ON s.station_id = st.id 
        LEFT JOIN recordings r ON s.id = r.show_id
        WHERE s.active = 1
        GROUP BY s.id 
        ORDER BY s.name
        LIMIT 10
    ");
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $stats = ['stations' => 0, 'shows' => 0, 'recordings' => 0, 'storage_used' => 0];
    $recent_recordings = [];
    $active_shows = [];
}

$page_title = 'Dashboard';
$active_nav = 'dashboard';

require_once '../includes/header.php';
?>

<!-- User Dashboard Content -->
<div class="container mt-4">
    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> <?= h($error) ?>
        </div>
    <?php endif; ?>

    <!-- Welcome Header -->
    <div class="row mb-4">
        <div class="col">
            <div class="d-flex justify-content-between align-items-center">
                <div>
                    <h1><i class="fas fa-tachometer-alt"></i> Welcome back, <?= h($current_user['first_name'] ?: $current_user['username']) ?>!</h1>
                    <p class="text-muted">Here's what's happening with your radio recordings</p>
                </div>
                <div>
                    <?php if ($auth->isAdmin()): ?>
                        <a href="/admin/dashboard.php" class="btn btn-warning me-2">
                            <i class="fas fa-shield-alt"></i> Admin Panel
                        </a>
                    <?php endif; ?>
                    <a href="/add-station.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Station
                    </a>
                </div>
            </div>
        </div>
    </div>

    <!-- Statistics Cards -->
    <div class="row mb-4">
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stat-card bg-primary text-white">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-broadcast-tower fa-2x"></i>
                        </div>
                        <div>
                            <h2><?= $stats['stations'] ?></h2>
                            <p class="mb-0">Stations</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="/stations.php" class="text-white text-decoration-none">
                        <i class="fas fa-arrow-right"></i> View All
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stat-card bg-success text-white">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-microphone fa-2x"></i>
                        </div>
                        <div>
                            <h2><?= $stats['shows'] ?></h2>
                            <p class="mb-0">Shows</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="/shows.php" class="text-white text-decoration-none">
                        <i class="fas fa-arrow-right"></i> View All
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stat-card bg-info text-white">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-file-audio fa-2x"></i>
                        </div>
                        <div>
                            <h2><?= $stats['recordings'] ?></h2>
                            <p class="mb-0">Recordings</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <a href="/recordings.php" class="text-white text-decoration-none">
                        <i class="fas fa-arrow-right"></i> View All
                    </a>
                </div>
            </div>
        </div>
        
        <div class="col-md-3 mb-3">
            <div class="card dashboard-stat-card bg-warning text-dark">
                <div class="card-body">
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <i class="fas fa-hdd fa-2x"></i>
                        </div>
                        <div>
                            <h2><?= formatFileSize($stats['storage_used']) ?></h2>
                            <p class="mb-0">Storage Used</p>
                        </div>
                    </div>
                </div>
                <div class="card-footer bg-transparent">
                    <span class="text-muted">
                        <i class="fas fa-info-circle"></i> Total file size
                    </span>
                </div>
            </div>
        </div>
    </div>

    <div class="row">
        <!-- Recent Recordings -->
        <div class="col-lg-8 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-history"></i> Recent Recordings</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($recent_recordings)): ?>
                        <div class="text-center py-4">
                            <i class="fas fa-file-audio fa-3x text-muted mb-3"></i>
                            <h4>No recordings yet</h4>
                            <p class="text-muted">Start by adding a station and setting up shows</p>
                            <a href="/add-station.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Your First Station
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($recent_recordings as $recording): ?>
                                <div class="list-group-item">
                                    <div class="d-flex justify-content-between align-items-center">
                                        <div>
                                            <h6 class="mb-1"><?= h($recording['title'] ?: $recording['filename']) ?></h6>
                                            <p class="mb-1 text-muted">
                                                <?= h($recording['show_name']) ?> • <?= h($recording['station_name']) ?>
                                            </p>
                                            <small class="text-muted">
                                                <i class="fas fa-clock"></i> <?= timeAgo($recording['recorded_at']) ?>
                                                <?php if ($recording['duration_seconds']): ?>
                                                    • <i class="fas fa-stopwatch"></i> <?= formatDuration($recording['duration_seconds']) ?>
                                                <?php endif; ?>
                                            </small>
                                        </div>
                                        <div>
                                            <a href="/recordings.php?recording_id=<?= $recording['id'] ?>" class="btn btn-sm btn-outline-primary">
                                                <i class="fas fa-play"></i>
                                            </a>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="card-footer">
                            <a href="/recordings.php" class="btn btn-outline-primary btn-sm">
                                <i class="fas fa-arrow-right"></i> View All Recordings
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Active Shows -->
        <div class="col-lg-4 mb-4">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-microphone"></i> Active Shows</h5>
                </div>
                <div class="card-body">
                    <?php if (empty($active_shows)): ?>
                        <div class="text-center py-3">
                            <i class="fas fa-microphone fa-2x text-muted mb-2"></i>
                            <p class="text-muted mb-2">No active shows</p>
                            <a href="/add-show.php" class="btn btn-sm btn-outline-primary">
                                <i class="fas fa-plus"></i> Add Show
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="list-group list-group-flush">
                            <?php foreach ($active_shows as $show): ?>
                                <div class="list-group-item px-0">
                                    <div class="d-flex align-items-center">
                                        <img src="<?= h(getStationLogo($show)) ?>" 
                                             alt="<?= h($show['station_name']) ?>" 
                                             class="station-logo station-logo-xs me-2"
                                             onerror="this.src='/assets/images/default-station-logo.png'"
                                             loading="lazy">
                                        <div class="flex-grow-1">
                                            <h6 class="mb-0"><?= h($show['name']) ?></h6>
                                            <small class="text-muted">
                                                <?= h($show['station_name']) ?> • <?= $show['recording_count'] ?> recordings
                                            </small>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="card-footer p-2">
                            <a href="/shows.php" class="btn btn-outline-primary btn-sm w-100">
                                <i class="fas fa-arrow-right"></i> View All Shows
                            </a>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.dashboard-stat-card {
    transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out;
    border: none;
}

.dashboard-stat-card:hover {
    transform: translateY(-5px);
    box-shadow: 0 8px 25px rgba(0,0,0,0.15);
}

.dashboard-stat-card .card-body {
    padding-bottom: 1rem;
}

.dashboard-stat-card .card-footer {
    padding-top: 0.5rem;
}

.dashboard-stat-card h2 {
    font-weight: 700;
    font-size: 2.5rem;
}
</style>

<?php
require_once '../includes/footer.php';
?>