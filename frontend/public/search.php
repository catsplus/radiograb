<?php
/**
 * RadioGrab - Global Search
 * Search across stations, shows, recordings, and playlists
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

$search_query = trim($_GET['q'] ?? '');
$search_type = $_GET['type'] ?? 'all';

$results = [
    'stations' => [],
    'shows' => [],
    'recordings' => [],
    'playlists' => []
];

$total_results = 0;

if (!empty($search_query) && strlen($search_query) >= 2) {
    $search_param = "%$search_query%";
    
    try {
        // Search stations
        if ($search_type === 'all' || $search_type === 'stations') {
            $results['stations'] = $db->fetchAll("
                SELECT id, name, website_url, call_letters, logo_url
                FROM stations 
                WHERE (name LIKE ? OR call_letters LIKE ? OR website_url LIKE ?)
                AND status = 'active'
                ORDER BY name
                LIMIT 20
            ", [$search_param, $search_param, $search_param]);
        }
        
        // Search shows
        if ($search_type === 'all' || $search_type === 'shows') {
            $results['shows'] = $db->fetchAll("
                SELECT s.id, s.name, s.description, s.show_type, s.active,
                       st.name as station_name, st.call_letters, st.logo_url,
                       COUNT(r.id) as recording_count
                FROM shows s
                JOIN stations st ON s.station_id = st.id
                LEFT JOIN recordings r ON s.id = r.show_id
                WHERE (s.name LIKE ? OR s.description LIKE ? OR s.host LIKE ? OR s.genre LIKE ?)
                GROUP BY s.id
                ORDER BY s.name
                LIMIT 20
            ", [$search_param, $search_param, $search_param, $search_param]);
        }
        
        // Search recordings
        if ($search_type === 'all' || $search_type === 'recordings') {
            $results['recordings'] = $db->fetchAll("
                SELECT r.id, r.title, r.filename, r.recorded_at, r.duration_seconds, r.file_size_bytes,
                       s.name as show_name, st.name as station_name, st.call_letters
                FROM recordings r
                JOIN shows s ON r.show_id = s.id
                JOIN stations st ON s.station_id = st.id
                WHERE (r.title LIKE ? OR s.name LIKE ? OR st.name LIKE ?)
                ORDER BY r.recorded_at DESC
                LIMIT 20
            ", [$search_param, $search_param, $search_param]);
        }
        
        // Search playlists
        if ($search_type === 'all' || $search_type === 'playlists') {
            $results['playlists'] = $db->fetchAll("
                SELECT s.id, s.name, s.description, st.name as station_name, st.call_letters,
                       COUNT(r.id) as track_count
                FROM shows s
                JOIN stations st ON s.station_id = st.id
                LEFT JOIN recordings r ON s.id = r.show_id
                WHERE s.show_type = 'playlist' 
                AND (s.name LIKE ? OR s.description LIKE ?)
                GROUP BY s.id
                ORDER BY s.name
                LIMIT 20
            ", [$search_param, $search_param]);
        }
        
        $total_results = count($results['stations']) + count($results['shows']) + 
                        count($results['recordings']) + count($results['playlists']);
        
    } catch (Exception $e) {
        $error = "Search error: " . $e->getMessage();
    }
}

// Set page variables
$page_title = 'Search';
$active_nav = 'search';

require_once '../includes/header.php';
?>

<!-- Main Content -->
<div class="container mt-4">
    <!-- Page Header -->
    <div class="row mb-4">
        <div class="col">
            <h1><i class="fas fa-search"></i> Search RadioGrab</h1>
            <p class="text-muted">Find stations, shows, recordings, and playlists</p>
        </div>
    </div>

    <!-- Search Form -->
    <div class="row mb-4">
        <div class="col">
            <div class="card">
                <div class="card-body">
                    <form method="GET" class="row g-3">
                        <div class="col-md-6">
                            <label for="search_query" class="form-label">Search</label>
                            <div class="input-group">
                                <span class="input-group-text"><i class="fas fa-search"></i></span>
                                <input type="text" 
                                       class="form-control" 
                                       id="search_query" 
                                       name="q" 
                                       value="<?= h($search_query) ?>" 
                                       placeholder="Enter search terms..."
                                       autofocus>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <label for="search_type" class="form-label">Search In</label>
                            <select class="form-select" name="type" id="search_type">
                                <option value="all" <?= $search_type === 'all' ? 'selected' : '' ?>>Everything</option>
                                <option value="stations" <?= $search_type === 'stations' ? 'selected' : '' ?>>Stations</option>
                                <option value="shows" <?= $search_type === 'shows' ? 'selected' : '' ?>>Shows</option>
                                <option value="recordings" <?= $search_type === 'recordings' ? 'selected' : '' ?>>Recordings</option>
                                <option value="playlists" <?= $search_type === 'playlists' ? 'selected' : '' ?>>Playlists</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">&nbsp;</label>
                            <div class="d-grid">
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-search"></i> Search
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <?php if (isset($error)): ?>
        <div class="alert alert-danger">
            <i class="fas fa-exclamation-triangle"></i> <?= h($error) ?>
        </div>
    <?php endif; ?>

    <!-- Search Results -->
    <?php if (!empty($search_query)): ?>
        <?php if ($total_results > 0): ?>
            <div class="row mb-3">
                <div class="col">
                    <h4>Search Results for "<?= h($search_query) ?>"</h4>
                    <p class="text-muted">Found <?= $total_results ?> result<?= $total_results != 1 ? 's' : '' ?></p>
                </div>
            </div>

            <!-- Stations Results -->
            <?php if (!empty($results['stations'])): ?>
                <div class="row mb-4">
                    <div class="col">
                        <h5><i class="fas fa-broadcast-tower"></i> Stations (<?= count($results['stations']) ?>)</h5>
                        <div class="row">
                            <?php foreach ($results['stations'] as $station): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <div class="d-flex align-items-center">
                                                <img src="<?= h(getStationLogo(['logo_url' => $station['logo_url']])) ?>" 
                                                     alt="<?= h($station['name']) ?>" 
                                                     class="station-logo station-logo-sm me-3"
                                                     onerror="this.src='/assets/images/default-station-logo.png'"
                                                     loading="lazy">
                                                <div>
                                                    <h6 class="mb-0"><?= h($station['name']) ?></h6>
                                                    <small class="text-muted"><?= h($station['call_letters']) ?></small>
                                                </div>
                                            </div>
                                            <div class="mt-2">
                                                <a href="/stations.php?station_id=<?= $station['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                    View Station
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Shows Results -->
            <?php if (!empty($results['shows'])): ?>
                <div class="row mb-4">
                    <div class="col">
                        <h5><i class="fas fa-microphone"></i> Shows (<?= count($results['shows']) ?>)</h5>
                        <div class="row">
                            <?php foreach ($results['shows'] as $show): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title">
                                                <?= h($show['name']) ?>
                                                <span class="badge bg-<?= $show['show_type'] === 'playlist' ? 'warning' : 'primary' ?> ms-1">
                                                    <?= $show['show_type'] === 'playlist' ? 'Playlist' : 'Show' ?>
                                                </span>
                                            </h6>
                                            <p class="card-text">
                                                <small class="text-muted"><?= h($show['station_name']) ?></small><br>
                                                <?= h(substr($show['description'], 0, 100)) ?><?= strlen($show['description']) > 100 ? '...' : '' ?>
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><?= $show['recording_count'] ?> recordings</small>
                                                <a href="/shows.php?show_id=<?= $show['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                    View
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Recordings Results -->
            <?php if (!empty($results['recordings'])): ?>
                <div class="row mb-4">
                    <div class="col">
                        <h5><i class="fas fa-file-audio"></i> Recordings (<?= count($results['recordings']) ?>)</h5>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Title</th>
                                        <th>Show</th>
                                        <th>Station</th>
                                        <th>Recorded</th>
                                        <th>Duration</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($results['recordings'] as $recording): ?>
                                        <tr>
                                            <td><?= h($recording['title']) ?></td>
                                            <td><?= h($recording['show_name']) ?></td>
                                            <td><?= h($recording['station_name']) ?></td>
                                            <td><?= timeAgo($recording['recorded_at']) ?></td>
                                            <td><?= formatDuration($recording['duration_seconds']) ?></td>
                                            <td>
                                                <a href="/recordings.php?recording_id=<?= $recording['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                    <i class="fas fa-play"></i>
                                                </a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

            <!-- Playlists Results -->
            <?php if (!empty($results['playlists'])): ?>
                <div class="row mb-4">
                    <div class="col">
                        <h5><i class="fas fa-list"></i> Playlists (<?= count($results['playlists']) ?>)</h5>
                        <div class="row">
                            <?php foreach ($results['playlists'] as $playlist): ?>
                                <div class="col-md-6 col-lg-4 mb-3">
                                    <div class="card">
                                        <div class="card-body">
                                            <h6 class="card-title"><?= h($playlist['name']) ?></h6>
                                            <p class="card-text">
                                                <small class="text-muted"><?= h($playlist['station_name']) ?></small><br>
                                                <?= h(substr($playlist['description'], 0, 100)) ?><?= strlen($playlist['description']) > 100 ? '...' : '' ?>
                                            </p>
                                            <div class="d-flex justify-content-between align-items-center">
                                                <small class="text-muted"><?= $playlist['track_count'] ?> tracks</small>
                                                <a href="/playlists.php?playlist_id=<?= $playlist['id'] ?>" class="btn btn-outline-primary btn-sm">
                                                    View
                                                </a>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>

        <?php else: ?>
            <div class="row">
                <div class="col">
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-search fa-3x text-muted mb-3"></i>
                            <h4>No results found</h4>
                            <p class="text-muted">Try different search terms or check your spelling</p>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    <?php elseif ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['q'])): ?>
        <div class="row">
            <div class="col">
                <div class="alert alert-warning">
                    <i class="fas fa-info-circle"></i> Please enter at least 2 characters to search
                </div>
            </div>
        </div>
    <?php else: ?>
        <div class="row">
            <div class="col">
                <div class="card">
                    <div class="card-body text-center py-5">
                        <i class="fas fa-search fa-3x text-muted mb-3"></i>
                        <h4>Start Searching</h4>
                        <p class="text-muted">Enter your search terms above to find stations, shows, recordings, and playlists</p>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php
require_once '../includes/footer.php';
?>