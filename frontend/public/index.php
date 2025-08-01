<?php
/**
 * RadioGrab - Main Dashboard
 * Radio Recorder Application Frontend
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';
require_once '../includes/router.php';

// Initialize router and check for friendly URLs
$router = new RadioGrabRouter($db);
$route = $router->route($_SERVER['REQUEST_URI']);

// Handle routing
switch ($route['type']) {
    case 'station':
        $station = $route['station'];
        require_once '../includes/pages/station-detail.php';
        exit;
        
    case 'show':
        $show = $route['show'];
        require_once '../includes/pages/show-detail.php';
        exit;
        
    case 'user':
        $user = $route['user'];
        require_once '../includes/pages/user-profile.php';
        exit;
        
    case 'playlist':
    case 'user_playlist':
        $playlist = $route['playlist'];
        require_once '../includes/pages/playlist-detail.php';
        exit;
        
    case 'system_page':
        // Redirect to actual system page files
        $page = $route['page'];
        if (file_exists($page . '.php')) {
            header('Location: /' . $page . '.php');
            exit;
        } else {
            http_response_code(404);
            require_once '../includes/pages/404.php';
            exit;
        }
        
    case 'not_found':
        http_response_code(404);
        require_once '../includes/pages/404.php';
        exit;
        
    case 'dashboard':
    default:
        // Continue with dashboard rendering below
        break;
}

// Set page variables for shared template (dashboard)
$page_title = 'RadioGrab Dashboard';
$active_nav = 'dashboard';

// Add custom CSS for dashboard
$additional_css = '
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

.dashboard-stat-card .opacity-75 {
    opacity: 0.8;
}

.recording-card {
    transition: all 0.2s ease-in-out;
}

.recording-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.list-group-item {
    border-left: 0;
    border-right: 0;
    transition: background-color 0.2s ease-in-out;
}

.list-group-item:hover {
    background-color: rgba(0,123,255,0.05);
}

#next-recordings-content .card {
    transition: all 0.2s ease-in-out;
}

#next-recordings-content .card:hover {
    transform: translateY(-2px);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.pulse-animation {
    animation: pulse 2s infinite;
}

@keyframes pulse {
    0% { transform: scale(1); }
    50% { transform: scale(1.05); }
    100% { transform: scale(1); }
}
</style>
';

// Get dashboard statistics
try {
    $stats = [
        'stations' => $db->fetchOne("SELECT COUNT(*) as count FROM stations WHERE status = 'active'")['count'],
        'shows' => $db->fetchOne("SELECT COUNT(*) as count FROM shows WHERE active = 1 AND (show_type != 'playlist' OR show_type IS NULL)")['count'],
        'recordings' => $db->fetchOne("
            SELECT COUNT(*) as count 
            FROM recordings r 
            JOIN shows s ON r.show_id = s.id 
            WHERE s.show_type != 'playlist' AND r.source_type != 'uploaded'
        ")['count'],
        'playlists' => $db->fetchOne("SELECT COUNT(*) as count FROM shows WHERE show_type = 'playlist' AND active = 1")['count']
    ];
    
    // Recent recordings (exclude playlist uploads)
    $recent_recordings = $db->fetchAll("
        SELECT r.*, s.name as show_name, st.name as station_name 
        FROM recordings r 
        JOIN shows s ON r.show_id = s.id 
        JOIN stations st ON s.station_id = st.id 
        WHERE s.show_type != 'playlist' AND r.source_type != 'uploaded'
        ORDER BY r.recorded_at DESC 
        LIMIT 10
    ");
    
    // Active shows
    $active_shows = $db->fetchAll("
        SELECT s.*, st.name as station_name, st.logo_url,
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
    $stats = ['stations' => 0, 'shows' => 0, 'recordings' => 0, 'playlists' => 0];
    $recent_recordings = [];
    $active_shows = [];
}

// Include shared header
require_once '../includes/header.php';

// Show error if present
if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> <?= h($error) ?>
    </div>
<?php endif; ?>

        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <h1><i class="fas fa-tachometer-alt"></i> <?= h(get_setting('site_title', 'RadioGrab')) ?></h1>
                <p class="text-muted"><?= h(get_setting('site_tagline', 'Your Personal Radio Recorder')) ?></p>
            </div>
            <div class="col-auto">
                <a href="/add-station.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Station
                </a>
            </div>
        </div>

        <!-- Enhanced Statistics Cards -->
        <div class="row mb-4">
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-primary h-100 dashboard-stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Stations</h5>
                                <h2 class="mb-0"><?= $stats['stations'] ?></h2>
                                <small class="opacity-75">Radio stations configured</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-broadcast-tower fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0">
                        <div class="d-grid gap-2">
                            <a href="/stations.php" class="btn btn-light btn-sm">
                                <i class="fas fa-cog"></i> Manage Stations
                            </a>
                            <a href="/add-station.php" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-plus"></i> Add New
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-success h-100 dashboard-stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Active Shows</h5>
                                <h2 class="mb-0"><?= $stats['shows'] ?></h2>
                                <small class="opacity-75">Recording schedules</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-microphone fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0">
                        <div class="d-grid gap-2">
                            <a href="/shows.php" class="btn btn-light btn-sm">
                                <i class="fas fa-list"></i> View Shows
                            </a>
                            <a href="/add-show.php" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-plus"></i> Add Show
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-info h-100 dashboard-stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Recordings</h5>
                                <h2 class="mb-0"><?= $stats['recordings'] ?></h2>
                                <small class="opacity-75">Audio files captured</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-file-audio fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0">
                        <div class="d-grid gap-2">
                            <a href="/recordings.php" class="btn btn-light btn-sm">
                                <i class="fas fa-play"></i> Browse Library
                            </a>
                            <a href="/feeds.php" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-rss"></i> RSS Feeds
                            </a>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-md-3 mb-3">
                <div class="card text-white bg-warning h-100 dashboard-stat-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between">
                            <div>
                                <h5 class="card-title">Playlists</h5>
                                <h2 class="mb-0"><?= $stats['playlists'] ?></h2>
                                <small class="opacity-75">Custom collections</small>
                            </div>
                            <div class="align-self-center">
                                <i class="fas fa-list fa-2x opacity-75"></i>
                            </div>
                        </div>
                    </div>
                    <div class="card-footer bg-transparent border-0 pt-0">
                        <div class="d-grid gap-2">
                            <a href="/playlists.php" class="btn btn-light btn-sm">
                                <i class="fas fa-eye"></i> View Playlists
                            </a>
                            <a href="/add-playlist.php" class="btn btn-outline-light btn-sm">
                                <i class="fas fa-plus"></i> Create New
                            </a>
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
                                        <div class="d-flex align-items-start">
                                            <div class="me-3">
                                                <img src="<?= h(getStationLogo(['logo_url' => $show['logo_url']])) ?>" 
                                                     alt="<?= h($show['station_name']) ?>" 
                                                     class="station-logo-small"
                                                     style="width: 40px; height: 40px; object-fit: cover; border-radius: 4px;"
                                                     onerror="this.src='/assets/images/default-station-logo.png'">
                                            </div>
                                            <div>
                                                <h6 class="mb-1">
                                                    <a href="/shows.php?show_id=<?= $show['id'] ?>" class="text-decoration-none">
                                                        <?= h($show['name']) ?>
                                                    </a>
                                                </h6>
                                                <p class="mb-1 text-muted small"><?= h($show['station_name']) ?></p>
                                                <small class="text-muted"><?= h($show['schedule_description']) ?></small>
                                            </div>
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

<?php
// Set additional JavaScript for dashboard functionality
$additional_js = '
<script>
    document.addEventListener("DOMContentLoaded", function() {
        // Load next recordings on page load
        loadNextRecordings();
    });

    function loadNextRecordings() {
        const loading = document.getElementById("next-recordings-loading");
        const content = document.getElementById("next-recordings-content");
        
        loading.style.display = "block";
        content.style.display = "none";
        
        fetch("/api/show-management.php?action=get_next_recordings&limit=3")
            .then(response => response.json())
            .then(data => {
                loading.style.display = "none";
                content.style.display = "block";
                
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
                loading.style.display = "none";
                content.style.display = "block";
                content.innerHTML = `
                    <div class="alert alert-warning">
                        <i class="fas fa-exclamation-triangle"></i> 
                        Unable to load next recordings: ${error.message}
                    </div>
                `;
            });
    }

    function displayNextRecordings(recordings) {
        const content = document.getElementById("next-recordings-content");
        
        let html = "<div class=\"row\">";
        
        recordings.forEach((recording, index) => {
            const colClass = recordings.length === 1 ? "col-12" : recordings.length === 2 ? "col-md-6" : "col-md-4";
            
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
                            ` : ""}
                            <div class="badge bg-primary">
                                ${index === 0 ? "Next" : index === 1 ? "2nd" : "3rd"}
                            </div>
                        </div>
                    </div>
                </div>
            `;
        });
        
        html += "</div>";
        content.innerHTML = html;
    }

    function refreshNextRecordings() {
        loadNextRecordings();
    }
</script>';

// Include shared footer
require_once '../includes/footer.php';
?>