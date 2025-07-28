<?php
/**
 * RadioGrab - Main Dashboard
 * Radio TiVo Application Frontend
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Get dashboard statistics
try {
    $stats = [
        'stations' => $db->fetchOne("SELECT COUNT(*) as count FROM stations WHERE status = 'active'")['count'],
        'shows' => $db->fetchOne("SELECT COUNT(*) as count FROM shows WHERE active = 1")['count'],
        'recordings' => $db->fetchOne("SELECT COUNT(*) as count FROM recordings")['count'],
        'total_size' => $db->fetchOne("SELECT COALESCE(SUM(file_size_bytes), 0) as size FROM recordings")['size']
    ];
    
    // Recent recordings
    $recent_recordings = $db->fetchAll("
        SELECT r.*, s.name as show_name, st.name as station_name 
        FROM recordings r 
        JOIN shows s ON r.show_id = s.id 
        JOIN stations st ON s.station_id = st.id 
        ORDER BY r.recorded_at DESC 
        LIMIT 10
    ");
    
    // Active shows
    $active_shows = $db->fetchAll("
        SELECT s.*, st.name as station_name, 
               COUNT(r.id) as recording_count
        FROM shows s 
        JOIN stations st ON s.station_id = st.id 
        LEFT JOIN recordings r ON s.id = r.show_id
        WHERE s.active = 1 
        GROUP BY s.id 
        ORDER BY s.name
    ");
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $stats = ['stations' => 0, 'shows' => 0, 'recordings' => 0, 'total_size' => 0];
    $recent_recordings = [];
    $active_shows = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>RadioGrab - Radio TiVo Dashboard</title>
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
                        <a class="nav-link active" href="/">Dashboard</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/stations.php">Stations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/shows.php">Shows</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/recordings.php">Recordings</a>
                    </li>
                </ul>
                <ul class="navbar-nav">
                    <li class="nav-item dropdown">
                        <a class="nav-link dropdown-toggle" href="#" id="navbarDropdown" role="button" data-bs-toggle="dropdown">
                            <i class="fas fa-cog"></i> Settings
                        </a>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="/settings.php">Configuration</a></li>
                            <li><a class="dropdown-item" href="/logs.php">Logs</a></li>
                            <li><hr class="dropdown-divider"></li>
                            <li><a class="dropdown-item" href="/about.php">About</a></li>
                        </ul>
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
                <h1><i class="fas fa-tachometer-alt"></i> Dashboard</h1>
                <p class="text-muted">Welcome to RadioGrab, your personal radio TiVo system</p>
            </div>
            <div class="col-auto">
                <a href="/add-station.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Station
                </a>
            </div>
        </div>

        <!-- Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3">
                <div class="card text-white bg-primary">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Stations</h5>
                                <h2><?= $stats['stations'] ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-broadcast-tower fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-success">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Active Shows</h5>
                                <h2><?= $stats['shows'] ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-microphone fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-info">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Recordings</h5>
                                <h2><?= $stats['recordings'] ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-file-audio fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3">
                <div class="card text-white bg-warning">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Storage Used</h5>
                                <h2><?= formatFileSize($stats['total_size']) ?></h2>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-hdd fa-2x"></i>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Next Recordings -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-calendar-alt"></i> Next Recordings</h5>
                        <button class="btn btn-sm btn-outline-primary" onclick="refreshNextRecordings()">
                            <i class="fas fa-sync"></i> Refresh
                        </button>
                    </div>
                    <div class="card-body">
                        <div id="next-recordings-loading" class="text-center py-3">
                            <i class="fas fa-spinner fa-spin"></i> Loading next recordings...
                        </div>
                        <div id="next-recordings-content" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row">
            <!-- Recent Recordings -->
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-clock"></i> Recent Recordings</h5>
                        <a href="/recordings.php" class="btn btn-sm btn-outline-primary">View All</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($recent_recordings)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-file-audio fa-3x mb-3"></i>
                                <p>No recordings yet. <a href="/add-station.php">Add a station</a> to get started!</p>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Show</th>
                                            <th>Station</th>
                                            <th>Recorded</th>
                                            <th>Duration</th>
                                            <th>Size</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($recent_recordings as $recording): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= h($recording['title']) ?></strong><br>
                                                    <small class="text-muted"><?= h($recording['show_name']) ?></small>
                                                </td>
                                                <td><?= h($recording['station_name']) ?></td>
                                                <td>
                                                    <span title="<?= h($recording['recorded_at']) ?>">
                                                        <?= timeAgo($recording['recorded_at']) ?>
                                                    </span>
                                                </td>
                                                <td><?= formatDuration($recording['duration_seconds']) ?></td>
                                                <td><?= formatFileSize($recording['file_size_bytes']) ?></td>
                                                <td>
                                                    <?php if (recordingFileExists($recording['filename'])): ?>
                                                        <a href="<?= getRecordingUrl($recording['filename']) ?>" 
                                                           class="btn btn-sm btn-outline-primary" 
                                                           target="_blank">
                                                            <i class="fas fa-play"></i>
                                                        </a>
                                                    <?php else: ?>
                                                        <span class="text-muted">
                                                            <i class="fas fa-exclamation-triangle"></i>
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Active Shows -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <h5><i class="fas fa-microphone"></i> Active Shows</h5>
                        <a href="/shows.php" class="btn btn-sm btn-outline-primary">Manage</a>
                    </div>
                    <div class="card-body">
                        <?php if (empty($active_shows)): ?>
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-microphone fa-2x mb-3"></i>
                                <p>No active shows.</p>
                                <a href="/add-show.php" class="btn btn-sm btn-primary">Add Show</a>
                            </div>
                        <?php else: ?>
                            <div class="list-group list-group-flush">
                                <?php foreach (array_slice($active_shows, 0, 8) as $show): ?>
                                    <div class="list-group-item d-flex justify-content-between align-items-start">
                                        <div>
                                            <h6 class="mb-1"><?= h($show['name']) ?></h6>
                                            <p class="mb-1 text-muted small"><?= h($show['station_name']) ?></p>
                                            <small class="text-muted"><?= h($show['schedule_description']) ?></small>
                                        </div>
                                        <span class="badge bg-secondary rounded-pill">
                                            <?= $show['recording_count'] ?>
                                        </span>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/radiograb.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Load next recordings on page load
            loadNextRecordings();
        });

        function loadNextRecordings() {
            const loading = document.getElementById('next-recordings-loading');
            const content = document.getElementById('next-recordings-content');
            
            loading.style.display = 'block';
            content.style.display = 'none';
            
            fetch('/api/show-management.php?action=get_next_recordings&limit=3')
                .then(response => response.json())
                .then(data => {
                    loading.style.display = 'none';
                    content.style.display = 'block';
                    
                    if (data.success && data.recordings && data.recordings.length > 0) {
                        displayNextRecordings(data.recordings);
                    } else {
                        content.innerHTML = `
                            <div class="text-center py-3">
                                <i class="fas fa-calendar-times fa-2x text-muted mb-2"></i>
                                <p class="text-muted mb-0">No upcoming recordings scheduled</p>
                                <small class="text-muted">Add shows with schedules to see upcoming recordings</small>
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    loading.style.display = 'none';
                    content.style.display = 'block';
                    content.innerHTML = `
                        <div class="alert alert-warning">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Unable to load next recordings: ${error.message}
                        </div>
                    `;
                });
        }

        function displayNextRecordings(recordings) {
            const content = document.getElementById('next-recordings-content');
            
            let html = '<div class="row">';
            
            recordings.forEach((recording, index) => {
                const colClass = recordings.length === 1 ? 'col-12' : recordings.length === 2 ? 'col-md-6' : 'col-md-4';
                
                html += `
                    <div class="${colClass} mb-3">
                        <div class="card border-primary">
                            <div class="card-body">
                                <h6 class="card-title">${recording.title}</h6>
                                <p class="card-text">
                                    <i class="fas fa-clock text-primary"></i> 
                                    <strong>${recording.next_run}</strong>
                                </p>
                                ${recording.tags ? `
                                    <div class="mb-2">
                                        <small class="text-muted">
                                            <i class="fas fa-tags"></i> ${recording.tags}
                                        </small>
                                    </div>
                                ` : ''}
                                <div class="badge bg-primary">
                                    ${index === 0 ? 'Next' : index === 1 ? '2nd' : '3rd'}
                                </div>
                            </div>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            content.innerHTML = html;
        }

        function refreshNextRecordings() {
            loadNextRecordings();
        }
    </script>
</body>
</html>