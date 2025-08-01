<?php
/**
 * RadioGrab - RSS Feeds Management
 *
 * This file provides the web interface for managing RSS podcast feeds for recorded
 * shows. It allows users to view individual show feeds, a master feed combining
 * all shows, and regenerate feeds. It also provides QR codes for easy subscription.
 *
 * Key Variables:
 * - `$shows`: An array of show data with RSS feed information.
 * - `$error`: A string to store any database errors.
 *
 * Inter-script Communication:
 * - This script executes shell commands to call `backend/services/rss_service.py`
 *   and `backend/services/rss_manager.py` for feed generation.
 * - It uses `includes/database.php` for database connection and `includes/functions.php` for helper functions.
 * - JavaScript handles copying feed URLs and generating QR codes.
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';


// Handle feed regeneration
if (($_POST['action'] ?? '') === 'regenerate' && isset($_POST['show_id']) && !empty($_POST['show_id']) && is_numeric($_POST['show_id'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        try {
            // Call Python RSS service to regenerate feed
            $show_id = (int)$_POST['show_id'];
            
            // Validate show_id
            if ($show_id <= 0) {
                setFlashMessage('warning', 'Invalid show ID provided for RSS regeneration');
                header('Location: /feeds.php');
                exit;
            }
            
            $python_script = dirname(dirname(__DIR__)) . '/backend/services/rss_service.py';
            $command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python $python_script $show_id 2>&1";
            $output = shell_exec($command);
            
            if (strpos($output, 'Success') !== false) {
                setFlashMessage('success', 'RSS feed regenerated successfully');
            } else {
                setFlashMessage('warning', 'Feed regeneration completed with warnings: ' . trim($output));
            }
        } catch (Exception $e) {
            setFlashMessage('danger', 'Failed to regenerate RSS feed: ' . $e->getMessage());
        }
    }
    header('Location: /feeds.php');
    exit;
}

// Handle regenerate all feeds
if ($_POST['action'] ?? '' === 'regenerate_all') {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        try {
            // Call RSS manager service to regenerate all feeds
            $python_script = dirname(dirname(__DIR__)) . '/backend/services/rss_manager.py';
            $command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python $python_script --action update-all 2>&1";
            $output = shell_exec($command);
            
            // Parse output for success/warning status
            if (strpos($output, 'RSS Update Results:') !== false) {
                // Extract numbers from output like "RSS Update Results: 17 updated, 0 errors"
                preg_match('/RSS Update Results:\s*(\d+)\s+updated,\s*(\d+)\s+errors/', $output, $matches);
                if ($matches && $matches[2] == '0') {
                    setFlashMessage('success', "RSS feeds regenerated successfully: {$matches[1]} feeds updated, {$matches[2]} errors");
                } else {
                    setFlashMessage('warning', "Feed regeneration completed with warnings: {$matches[1]} updated, {$matches[2]} errors");
                }
            } else {
                setFlashMessage('warning', 'Feed regeneration completed with warnings: ' . trim($output));
            }
        } catch (Exception $e) {
            setFlashMessage('danger', 'Failed to regenerate all RSS feeds: ' . $e->getMessage());
        }
    }
    header('Location: /feeds.php');
    exit;
}

// Get shows with RSS feed information
try {
    $shows = $db->fetchAll("
        SELECT s.*, st.name as station_name, st.logo_url,
               COUNT(r.id) as recording_count,
               MAX(r.recorded_at) as latest_recording
        FROM shows s 
        JOIN stations st ON s.station_id = st.id 
        LEFT JOIN recordings r ON s.id = r.show_id
        WHERE s.active = 1
        GROUP BY s.id 
        ORDER BY s.name
    ");
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $shows = [];
}

// Helper function to check if RSS feed exists
function feedExists($show_id) {
    $feeds_dir = '/var/radiograb/feeds'; // Adjust path as needed
    return file_exists("$feeds_dir/$show_id.xml");
}

function getFeedUrl($show_id) {
    return "/api/feeds.php?show_id=$show_id";
}
?>
<?php
// Set page variables for shared template
$page_title = 'RSS Feeds';
$active_nav = 'feeds';

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
                <h1><i class="fas fa-rss"></i> RSS Podcast Feeds</h1>
                <p class="text-muted">Manage RSS feeds for your recorded shows - compatible with iTunes and podcast apps</p>
            </div>
            <div class="col-auto">
                <a href="/custom-feeds.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Create Custom Feed
                </a>
            </div>
        </div>

        <!-- Enhanced Feed Types Navigation -->
        <div class="row mb-4">
            <div class="col-12">
                <nav class="nav nav-pills nav-fill">
                    <a class="nav-link active" href="#universal-feeds" data-bs-toggle="tab">
                        <i class="fas fa-globe"></i> Universal Feeds
                    </a>
                    <a class="nav-link" href="#station-feeds" data-bs-toggle="tab">
                        <i class="fas fa-broadcast-tower"></i> Station Feeds
                    </a>
                    <a class="nav-link" href="#show-feeds" data-bs-toggle="tab">
                        <i class="fas fa-microphone"></i> Show Feeds
                    </a>
                    <a class="nav-link" href="#playlist-feeds" data-bs-toggle="tab">
                        <i class="fas fa-list-music"></i> Playlist Feeds
                    </a>
                    <a class="nav-link" href="/custom-feeds.php">
                        <i class="fas fa-cog"></i> Custom Feeds
                    </a>
                </nav>
            </div>
        </div>

        <!-- Feed Content Tabs -->
        <div class="tab-content">
            <!-- Universal Feeds Tab -->
            <div class="tab-pane fade show active" id="universal-feeds">
                <div class="row mb-4">
                    <div class="col-md-6 mb-4">
                        <div class="card border-primary h-100">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="fas fa-globe"></i> All Shows Feed</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-2"><strong>Subscribe to all your radio recordings in one feed!</strong></p>
                                <p class="small text-muted mb-3">This universal feed combines recordings from all your radio shows (excluding playlists) into a single RSS feed, ordered by recording date.</p>
                                
                                <div class="mb-3">
                                    <label class="form-label">Feed URL:</label>
                                    <div class="input-group">
                                        <input type="text" 
                                               class="form-control" 
                                               value="<?= getBaseUrl() ?>/api/enhanced-feeds.php?type=universal&slug=all-shows"
                                               readonly>
                                        <button class="btn btn-outline-secondary copy-feed-url" 
                                                type="button"
                                                data-feed-url="<?= getBaseUrl() ?>/api/enhanced-feeds.php?type=universal&slug=all-shows">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <a href="/api/enhanced-feeds.php?type=universal&slug=all-shows" 
                                       class="btn btn-primary" target="_blank">
                                        <i class="fas fa-rss"></i> View Feed
                                    </a>
                                    <button type="button" class="btn btn-outline-info copy-feed-url"
                                            data-feed-url="<?= getBaseUrl() ?>/api/enhanced-feeds.php?type=universal&slug=all-shows">
                                        <i class="fas fa-qrcode"></i> QR Code
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6 mb-4">
                        <div class="card border-success h-100">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="fas fa-list-music"></i> All Playlists Feed</h5>
                            </div>
                            <div class="card-body">
                                <p class="mb-2"><strong>Subscribe to all your playlists in one feed!</strong></p>
                                <p class="small text-muted mb-3">This universal feed combines all tracks from your user-created playlists, maintaining their custom order and metadata.</p>
                                
                                <div class="mb-3">
                                    <label class="form-label">Feed URL:</label>
                                    <div class="input-group">
                                        <input type="text" 
                                               class="form-control" 
                                               value="<?= getBaseUrl() ?>/api/enhanced-feeds.php?type=universal&slug=all-playlists"
                                               readonly>
                                        <button class="btn btn-outline-secondary copy-feed-url" 
                                                type="button"
                                                data-feed-url="<?= getBaseUrl() ?>/api/enhanced-feeds.php?type=universal&slug=all-playlists">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <a href="/api/enhanced-feeds.php?type=universal&slug=all-playlists" 
                                       class="btn btn-success" target="_blank">
                                        <i class="fas fa-rss"></i> View Feed
                                    </a>
                                    <button type="button" class="btn btn-outline-info copy-feed-url"
                                            data-feed-url="<?= getBaseUrl() ?>/api/enhanced-feeds.php?type=universal&slug=all-playlists">
                                        <i class="fas fa-qrcode"></i> QR Code
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Station Feeds Tab -->
            <div class="tab-pane fade" id="station-feeds">
                <?php
                try {
                    $stations = $db->fetchAll("
                        SELECT st.*, 
                               COUNT(DISTINCT s.id) as show_count,
                               COUNT(DISTINCT r.id) as recording_count
                        FROM stations st
                        LEFT JOIN shows s ON st.id = s.station_id AND s.active = 1 AND s.show_type != 'playlist'
                        LEFT JOIN recordings r ON s.id = r.show_id
                        WHERE st.status = 'active'
                        GROUP BY st.id
                        ORDER BY st.name
                    ");
                } catch (Exception $e) {
                    $stations = [];
                }
                ?>
                
                <div class="row">
                    <?php foreach ($stations as $station): ?>
                        <div class="col-lg-6 col-xl-4 mb-4">
                            <div class="card h-100">
                                <div class="card-body">
                                    <div class="d-flex align-items-start mb-3">
                                        <img src="<?= h(getStationLogo(['logo_url' => $station['logo_url']])) ?>" 
                                             alt="<?= h($station['name']) ?>" 
                                             class="station-logo me-3"
                                             onerror="this.src='/assets/images/default-station-logo.png'">
                                        <div class="flex-grow-1">
                                            <h5 class="card-title mb-1"><?= h($station['name']) ?></h5>
                                            <small class="text-muted"><?= h($station['call_letters']) ?></small>
                                        </div>
                                        <?php if ($station['recording_count'] > 0): ?>
                                            <span class="badge bg-success">
                                                <i class="fas fa-rss"></i> Active
                                            </span>
                                        <?php else: ?>
                                            <span class="badge bg-secondary">No Recordings</span>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <div class="mb-3">
                                        <div class="row text-center">
                                            <div class="col">
                                                <div class="fw-bold"><?= $station['show_count'] ?></div>
                                                <small class="text-muted">Shows</small>
                                            </div>
                                            <div class="col">
                                                <div class="fw-bold"><?= $station['recording_count'] ?></div>
                                                <small class="text-muted">Recordings</small>
                                            </div>
                                        </div>
                                        
                                        <?php if ($station['recording_count'] > 0): ?>
                                            <div class="mt-3">
                                                <label class="form-label">Station Feed URL:</label>
                                                <div class="input-group">
                                                    <input type="text" 
                                                           class="form-control form-control-sm" 
                                                           value="<?= getBaseUrl() ?>/api/enhanced-feeds.php?type=station&id=<?= $station['id'] ?>"
                                                           readonly>
                                                    <button class="btn btn-outline-secondary btn-sm copy-feed-url" 
                                                            type="button"
                                                            data-feed-url="<?= getBaseUrl() ?>/api/enhanced-feeds.php?type=station&id=<?= $station['id'] ?>">
                                                        <i class="fas fa-copy"></i>
                                                    </button>
                                                </div>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                                
                                <div class="card-footer bg-transparent">
                                    <?php if ($station['recording_count'] > 0): ?>
                                        <div class="d-grid">
                                            <a href="/api/enhanced-feeds.php?type=station&id=<?= $station['id'] ?>" 
                                               class="btn btn-outline-primary btn-sm" target="_blank">
                                                <i class="fas fa-rss"></i> View Station Feed
                                            </a>
                                        </div>
                                    <?php else: ?>
                                        <div class="text-center text-muted">
                                            <small>No recordings available</small>
                                        </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <!-- Show Feeds Tab -->
            <div class="tab-pane fade" id="show-feeds">
                <!-- RSS Information Card -->
                <div class="row mb-4">
                    <div class="col-12">  
                        <div class="card">
                            <div class="card-header">
                                <h5><i class="fas fa-info-circle"></i> Individual Show Feeds</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-8">
                                        <p>Each show gets its own RSS feed containing only recordings from that specific show.</p>
                                        <p class="mb-0">Use individual feeds if you want to subscribe to specific shows separately.</p>
                                    </div>
                                    <div class="col-md-4">
                                        <div class="d-grid">
                                            <form method="POST" class="d-inline">
                                                <input type="hidden" name="action" value="regenerate_all">
                                                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                <button type="submit" class="btn btn-outline-primary">
                                                    <i class="fas fa-sync"></i> Regenerate All Feeds
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Shows List -->
                <?php if (empty($shows)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-rss fa-3x text-muted mb-3"></i>
                            <h3>No shows with recordings</h3>
                            <p class="text-muted mb-4">RSS feeds are generated automatically for shows that have recordings.</p>
                            <a href="/add-station.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> Add Your First Station
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($shows as $show): ?>
                            <?php if ($show['show_type'] !== 'playlist'): // Exclude playlists from show feeds ?>
                                <div class="col-lg-6 col-xl-4 mb-4">
                                    <div class="card h-100">
                                        <div class="card-body">
                                            <div class="d-flex align-items-start mb-3">
                                                <img src="<?= h(getStationLogo($show)) ?>" 
                                                     alt="<?= h($show['station_name']) ?>" 
                                                     class="station-logo me-3"
                                                     onerror="this.src='/assets/images/default-station-logo.png'">
                                                <div class="flex-grow-1">
                                                    <h5 class="card-title mb-1"><?= h($show['name']) ?></h5>
                                                    <small class="text-muted"><?= h($show['station_name']) ?></small>
                                                </div>
                                                <?php if ($show['recording_count'] > 0): ?>
                                                    <span class="badge bg-success">
                                                        <i class="fas fa-rss"></i> Active
                                                    </span>
                                                <?php else: ?>
                                                    <span class="badge bg-secondary">No Recordings</span>
                                                <?php endif; ?>
                                            </div>
                                            
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
                                                
                                                <?php if ($show['recording_count'] > 0): ?>
                                                    <div class="mt-3">
                                                        <label class="form-label">RSS Feed URL:</label>
                                                        <div class="input-group">
                                                            <input type="text" 
                                                                   class="form-control form-control-sm" 
                                                                   value="<?= getBaseUrl() ?>/api/enhanced-feeds.php?type=show&id=<?= $show['id'] ?>"
                                                                   readonly>
                                                            <button class="btn btn-outline-secondary btn-sm copy-feed-url" 
                                                                    type="button"
                                                                    data-feed-url="<?= getBaseUrl() ?>/api/enhanced-feeds.php?type=show&id=<?= $show['id'] ?>">
                                                                <i class="fas fa-copy"></i>
                                                            </button>
                                                        </div>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                        
                                        <div class="card-footer bg-transparent">
                                            <?php if ($show['recording_count'] > 0): ?>
                                                <div class="btn-group w-100" role="group">
                                                    <a href="/api/enhanced-feeds.php?type=show&id=<?= $show['id'] ?>" 
                                                       class="btn btn-outline-primary btn-sm"
                                                       target="_blank">
                                                        <i class="fas fa-rss"></i> View Feed
                                                    </a>
                                                    <form method="POST" class="d-inline flex-fill">
                                                        <input type="hidden" name="action" value="regenerate">
                                                        <input type="hidden" name="show_id" value="<?= $show['id'] ?>">
                                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                                        <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                            <i class="fas fa-sync"></i> Regenerate
                                                        </button>
                                                    </form>
                                                </div>
                                            <?php else: ?>
                                                <div class="text-center text-muted">
                                                    <small>No recordings available</small>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
            
            <!-- Playlist Feeds Tab -->  
            <div class="tab-pane fade" id="playlist-feeds">
                <?php
                try {
                    $playlists = $db->fetchAll("
                        SELECT s.*, st.name as station_name, st.logo_url,
                               COUNT(r.id) as track_count,
                               MAX(r.recorded_at) as latest_upload
                        FROM shows s 
                        JOIN stations st ON s.station_id = st.id 
                        LEFT JOIN recordings r ON s.id = r.show_id
                        WHERE s.active = 1 AND s.show_type = 'playlist'
                        GROUP BY s.id 
                        ORDER BY s.name
                    ");
                } catch (Exception $e) {
                    $playlists = [];
                }
                ?>
                
                <?php if (empty($playlists)): ?>
                    <div class="card">
                        <div class="card-body text-center py-5">
                            <i class="fas fa-list-music fa-3x text-muted mb-3"></i>
                            <h3>No playlists yet</h3>
                            <p class="text-muted mb-4">Create playlists by uploading tracks to user playlist shows.</p>
                            <a href="/shows.php" class="btn btn-primary">
                                <i class="fas fa-plus"></i> View Shows
                            </a>
                        </div>
                    </div>
                <?php else: ?>
                    <div class="row">
                        <?php foreach ($playlists as $playlist): ?>
                            <div class="col-lg-6 col-xl-4 mb-4">
                                <div class="card h-100">
                                    <div class="card-body">
                                        <div class="d-flex align-items-start mb-3">
                                            <div class="flex-grow-1">
                                                <h5 class="card-title mb-1"><?= h($playlist['name']) ?></h5>
                                                <small class="text-muted">
                                                    <i class="fas fa-list-music"></i> Playlist
                                                </small>
                                            </div>
                                            <?php if ($playlist['track_count'] > 0): ?>
                                                <span class="badge bg-success">
                                                    <i class="fas fa-rss"></i> Active
                                                </span>
                                            <?php else: ?>
                                                <span class="badge bg-secondary">Empty</span>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <div class="mb-3">
                                            <div class="row text-center">
                                                <div class="col">
                                                    <div class="fw-bold"><?= $playlist['track_count'] ?></div>
                                                    <small class="text-muted">Tracks</small>
                                                </div>
                                                <div class="col">
                                                    <div class="fw-bold">
                                                        <?= $playlist['latest_upload'] ? timeAgo($playlist['latest_upload']) : 'Never' ?>
                                                    </div>
                                                    <small class="text-muted">Latest</small>
                                                </div>
                                            </div>
                                            
                                            <?php if ($playlist['track_count'] > 0): ?>
                                                <div class="mt-3">
                                                    <label class="form-label">Playlist Feed URL:</label>
                                                    <div class="input-group">
                                                        <input type="text" 
                                                               class="form-control form-control-sm" 
                                                               value="<?= getBaseUrl() ?>/api/enhanced-feeds.php?type=playlist&id=<?= $playlist['id'] ?>"
                                                               readonly>
                                                        <button class="btn btn-outline-secondary btn-sm copy-feed-url" 
                                                                type="button"
                                                                data-feed-url="<?= getBaseUrl() ?>/api/enhanced-feeds.php?type=playlist&id=<?= $playlist['id'] ?>">
                                                            <i class="fas fa-copy"></i>
                                                        </button>
                                                    </div>
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    
                                    <div class="card-footer bg-transparent">
                                        <?php if ($playlist['track_count'] > 0): ?>
                                            <div class="d-grid gap-2">
                                                <a href="/api/enhanced-feeds.php?type=playlist&id=<?= $playlist['id'] ?>" 
                                                   class="btn btn-outline-primary btn-sm" target="_blank">
                                                    <i class="fas fa-rss"></i> View Playlist Feed
                                                </a>
                                                <a href="/playlists.php?show_id=<?= $playlist['id'] ?>" 
                                                   class="btn btn-outline-info btn-sm">
                                                    <i class="fas fa-edit"></i> Manage Playlist
                                                </a>
                                            </div>
                                        <?php else: ?>
                                            <div class="text-center text-muted">
                                                <small>No tracks uploaded</small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- QR Code Modal -->
    <div class="modal fade" id="qrModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">QR Code for <span id="qrShowName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body text-center">
                    <div id="qrcode"></div>
                    <p class="mt-3 small text-muted">Scan with your phone to add this podcast feed</p>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Enhanced Feeds Page Functionality
        document.addEventListener('DOMContentLoaded', function() {
            // Copy to clipboard functionality
            document.querySelectorAll('.copy-feed-url').forEach(button => {
                button.addEventListener('click', function() {
                    const feedUrl = this.dataset.feedUrl;
                    if (navigator.clipboard) {
                        navigator.clipboard.writeText(feedUrl).then(() => {
                            showToast('Feed URL copied to clipboard!', 'success');
                        }).catch(() => {
                            fallbackCopyTextToClipboard(feedUrl);
                        });
                    } else {
                        fallbackCopyTextToClipboard(feedUrl);
                    }
                });
            });
            
            // QR Code generation for buttons
            document.querySelectorAll('button[data-feed-url]').forEach(button => {
                if (button.innerHTML.includes('qrcode')) {
                    button.addEventListener('click', function() {
                        const feedUrl = this.dataset.feedUrl;
                        generateQRCode(feedUrl, 'RSS Feed');
                    });
                }
            });
            
            // Test feed functionality
            document.querySelectorAll('.btn').forEach(button => {
                if (button.textContent.includes('View Feed')) {
                    const feedLink = button.getAttribute('href');
                    if (feedLink && feedLink.includes('/api/enhanced-feeds.php')) {
                        // Add test functionality
                        button.addEventListener('contextmenu', function(e) {
                            e.preventDefault();
                            testFeedValidity(feedLink);
                        });
                        button.title = 'Left click to view, right click to test feed validity';
                    }
                }
            });
            
            // Add feed statistics loading
            loadFeedStatistics();
        });
        
        function fallbackCopyTextToClipboard(text) {
            const textArea = document.createElement('textarea');
            textArea.value = text;
            textArea.style.position = 'fixed';
            textArea.style.left = '-999999px';
            textArea.style.top = '-999999px';
            document.body.appendChild(textArea);
            textArea.focus();
            textArea.select();
            
            try {
                document.execCommand('copy');
                showToast('Feed URL copied to clipboard!', 'success');
            } catch (err) {
                showToast('Failed to copy URL', 'danger');
            }
            
            document.body.removeChild(textArea);
        }
        
        function generateQRCode(feedUrl, feedName) {
            const qrUrl = `https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=${encodeURIComponent(feedUrl)}`;
            
            const modal = document.getElementById('qrModal');
            if (!modal) {
                // Create QR modal if it doesn't exist
                createQRModal();
            }
            
            const modalTitle = modal.querySelector('.modal-title');
            const qrContainer = modal.querySelector('#qrcode');
            
            modalTitle.textContent = `QR Code for ${feedName}`;
            qrContainer.innerHTML = `
                <img src="${qrUrl}" alt="QR Code for ${feedName}" class="img-fluid mb-3">
                <div class="text-center">
                    <small class="text-muted d-block mb-2">Scan with your podcast app to subscribe</small>
                    <button class="btn btn-sm btn-outline-primary" onclick="copyToClipboard('${feedUrl}')">
                        <i class="fas fa-copy"></i> Copy Feed URL
                    </button>
                </div>
            `;
            
            new bootstrap.Modal(modal).show();
        }
        
        function createQRModal() {
            const modalHTML = `
                <div class="modal fade" id="qrModal" tabindex="-1">
                    <div class="modal-dialog">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">QR Code</h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-center">
                                <div id="qrcode"></div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            document.body.insertAdjacentHTML('beforeend', modalHTML);
        }
        
        function testFeedValidity(feedUrl) {
            showToast('Testing feed validity...', 'info');
            
            fetch(feedUrl)
                .then(response => {
                    if (response.ok) {
                        return response.text();
                    }
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                })
                .then(xmlText => {
                    const parser = new DOMParser();
                    const xmlDoc = parser.parseFromString(xmlText, 'text/xml');
                    
                    if (xmlDoc.querySelector('parsererror')) {
                        throw new Error('Invalid XML format');
                    }
                    
                    const channel = xmlDoc.querySelector('channel');
                    if (!channel) {
                        throw new Error('Not a valid RSS feed');
                    }
                    
                    const items = xmlDoc.querySelectorAll('item');
                    const title = channel.querySelector('title')?.textContent || 'Unknown';
                    showToast(`✅ "${title}" feed is valid! Contains ${items.length} episodes.`, 'success');
                })
                .catch(error => {
                    showToast(`❌ Feed test failed: ${error.message}`, 'danger');
                });
        }
        
        function loadFeedStatistics() {
            // Add feed statistics to universal feeds
            const universalCards = document.querySelectorAll('.card.border-primary, .card.border-success');
            universalCards.forEach(card => {
                const feedUrl = card.querySelector('input[readonly]')?.value;
                if (feedUrl) {
                    loadFeedItemCount(feedUrl, card);
                }
            });
        }
        
        function loadFeedItemCount(feedUrl, card) {
            fetch(feedUrl)
                .then(response => response.text())
                .then(xmlText => {
                    const parser = new DOMParser();
                    const xmlDoc = parser.parseFromString(xmlText, 'text/xml');
                    const items = xmlDoc.querySelectorAll('item');
                    
                    // Add count badge to card header
                    const cardHeader = card.querySelector('.card-header h5');
                    if (cardHeader && !cardHeader.querySelector('.badge')) {
                        const badge = document.createElement('span');
                        badge.className = 'badge bg-light text-dark ms-2';
                        badge.textContent = `${items.length} items`;
                        cardHeader.appendChild(badge);
                    }
                })
                .catch(error => {
                    console.log('Could not load feed statistics:', error);
                });
        }
        
        function showToast(message, type) {
            const toastContainer = document.getElementById('toast-container') || createToastContainer();
            const toast = document.createElement('div');
            toast.className = `toast align-items-center text-white bg-${type} border-0`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="d-flex">
                    <div class="toast-body">${message}</div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast"></button>
                </div>
            `;
            
            toastContainer.appendChild(toast);
            const bsToast = new bootstrap.Toast(toast);
            bsToast.show();
            
            toast.addEventListener('hidden.bs.toast', () => {
                toast.remove();
            });
        }
        
        function createToastContainer() {
            const container = document.createElement('div');
            container.id = 'toast-container';
            container.className = 'toast-container position-fixed bottom-0 end-0 p-3';
            container.style.zIndex = '1080';
            document.body.appendChild(container);
            return container;
        }
        
        function copyToClipboard(text) {
            if (navigator.clipboard) {
                navigator.clipboard.writeText(text).then(() => {
                    showToast('Copied to clipboard!', 'success');
                });
            } else {
                fallbackCopyTextToClipboard(text);
            }
        }
    </script>

    <?php
$additional_js = '<script src="/assets/js/radiograb.js"></script>';
require_once '../includes/footer.php';
?>