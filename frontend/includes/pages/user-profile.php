<?php
/**
 * User Profile Page Template
 * Displays user information and their playlists
 */

// Get user's playlists
try {
    $playlists = $db->fetchAll("
        SELECT s.*, st.name as station_name, st.call_letters,
               COUNT(r.id) as track_count,
               SUM(r.file_size_bytes) as total_size_bytes,
               MAX(r.recorded_at) as latest_track
        FROM shows s 
        JOIN stations st ON s.station_id = st.id 
        LEFT JOIN recordings r ON s.id = r.show_id
        WHERE s.show_type = 'playlist' AND s.created_by = ?
        GROUP BY s.id 
        ORDER BY s.created_at DESC
    ", [$user['id']]);
    
    // Get user statistics
    $stats = $db->fetchOne("
        SELECT 
            COUNT(DISTINCT s.id) as total_playlists,
            COUNT(DISTINCT CASE WHEN s.active = 1 THEN s.id END) as active_playlists,
            COUNT(r.id) as total_tracks,
            SUM(r.file_size_bytes) as total_size_bytes
        FROM shows s 
        LEFT JOIN recordings r ON s.id = r.show_id
        WHERE s.show_type = 'playlist' AND s.created_by = ?
    ", [$user['id']]);
    
} catch (Exception $e) {
    $playlists = [];
    $stats = ['total_playlists' => 0, 'active_playlists' => 0, 'total_tracks' => 0, 'total_size_bytes' => 0];
    error_log("User profile error: " . $e->getMessage());
}

// Set page variables
$page_title = $user['username'] . ' - User Profile - RadioGrab';
$active_nav = 'users';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?></title>
    <meta name="description" content="<?= h($user['username'] . "'s playlists and audio collections") ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?= h($user['username']) ?> - User Profile">
    <meta property="og:description" content="<?= h($user['username'] . "'s playlists and audio collections") ?>">
    <meta property="og:url" content="<?= h($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">
    
    <link rel="icon" href="/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/radiograb.css" rel="stylesheet">
</head>
<body>
    <?php require_once '../includes/navbar.php'; ?>

    <!-- User Header -->
    <div class="container-fluid bg-light py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-2">
                    <div class="text-center">
                        <i class="fas fa-user-circle fa-5x text-muted"></i>
                    </div>
                </div>
                <div class="col-md-8">
                    <h1 class="display-5 mb-2"><?= h($user['username']) ?></h1>
                    <p class="text-muted mb-2">
                        <i class="fas fa-calendar"></i> Member since <?= date('M j, Y', strtotime($user['created_at'])) ?>
                    </p>
                    <p class="lead">User profile and playlist collections</p>
                </div>
                <div class="col-md-2 text-end">
                    <a href="/add-playlist.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Create Playlist
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
                        <h3 class="text-primary"><?= number_format($stats['total_playlists']) ?></h3>
                        <p class="card-text">Total Playlists</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-success"><?= number_format($stats['active_playlists']) ?></h3>
                        <p class="card-text">Active Playlists</p>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-center">
                    <div class="card-body">
                        <h3 class="text-info"><?= number_format($stats['total_tracks']) ?></h3>
                        <p class="card-text">Total Tracks</p>
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

        <!-- Playlists -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-list"></i> Playlists 
                    <span class="badge bg-secondary"><?= number_format(count($playlists)) ?></span>
                </h5>
                <a href="/add-playlist.php" class="btn btn-sm btn-primary">
                    <i class="fas fa-plus"></i> Create Playlist
                </a>
            </div>
            <div class="card-body">
                <?php if (empty($playlists)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-list fa-3x text-muted mb-3"></i>
                        <h4>No playlists yet</h4>
                        <p class="text-muted">Create your first playlist to get started.</p>
                        <a href="/add-playlist.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i> Create Playlist
                        </a>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($playlists as $playlist): ?>
                            <div class="col-md-6 col-lg-4 mb-4">
                                <div class="card h-100">
                                    <?php if ($playlist['image_url']): ?>
                                        <img src="<?= h($playlist['image_url']) ?>" 
                                             class="card-img-top" 
                                             style="height: 200px; object-fit: cover;"
                                             alt="<?= h($playlist['name']) ?>">
                                    <?php endif; ?>
                                    <div class="card-body d-flex flex-column">
                                        <h5 class="card-title">
                                            <a href="/user/<?= h($user['slug'] ?: strtolower($user['username'])) ?>/<?= h($playlist['slug']) ?>" 
                                               class="text-decoration-none">
                                                <?= h($playlist['name']) ?>
                                            </a>
                                        </h5>
                                        <?php if ($playlist['description']): ?>
                                            <p class="card-text text-muted"><?= h(substr($playlist['description'], 0, 100)) ?><?= strlen($playlist['description']) > 100 ? '...' : '' ?></p>
                                        <?php endif; ?>
                                        
                                        <div class="mt-auto">
                                            <div class="d-flex justify-content-between text-muted small mb-3">
                                                <span><i class="fas fa-music"></i> <?= number_format($playlist['track_count']) ?> tracks</span>
                                                <span><i class="fas fa-hdd"></i> <?= formatFileSize($playlist['total_size_bytes'] ?: 0) ?></span>
                                            </div>
                                            
                                            <?php if ($playlist['latest_track']): ?>
                                                <div class="text-muted small mb-3">
                                                    <i class="fas fa-clock"></i> Updated <?= timeAgo($playlist['latest_track']) ?>
                                                </div>
                                            <?php endif; ?>
                                            
                                            <div class="d-flex justify-content-between align-items-center">
                                                <div>
                                                    <?php if (!$playlist['active']): ?>
                                                        <span class="badge bg-secondary">Inactive</span>
                                                    <?php endif; ?>
                                                </div>
                                                <div class="btn-group" role="group">
                                                    <a href="/user/<?= h($user['slug'] ?: strtolower($user['username'])) ?>/<?= h($playlist['slug']) ?>" 
                                                       class="btn btn-sm btn-outline-primary">
                                                        <i class="fas fa-play"></i> Play
                                                    </a>
                                                    <a href="/edit-show.php?id=<?= $playlist['id'] ?>" 
                                                       class="btn btn-sm btn-outline-secondary">
                                                        <i class="fas fa-edit"></i> Edit
                                                    </a>
                                                    <a href="/api/enhanced-feeds.php?type=playlist&id=<?= $playlist['id'] ?>" 
                                                       class="btn btn-sm btn-outline-warning" 
                                                       title="RSS Feed">
                                                        <i class="fas fa-rss"></i>
                                                    </a>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/radiograb.js"></script>
</body>
</html>