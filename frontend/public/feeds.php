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
        </div>

        <!-- Master Feed Card -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card border-primary">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0"><i class="fas fa-rss"></i> Master Feed - All Shows Combined</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <p class="mb-2"><strong>Subscribe to all your recorded shows in one feed!</strong></p>
                                <p class="small text-muted mb-3">This master feed combines recordings from all your shows into a single RSS feed, ordered by recording date (most recent first). Perfect for getting all your content in one podcast subscription.</p>
                                
                                <div class="mb-3">
                                    <label class="form-label">Master Feed URL:</label>
                                    <div class="input-group">
                                        <input type="text" 
                                               class="form-control" 
                                               value="<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/api/master-feed.php') ?>"
                                               readonly>
                                        <button class="btn btn-outline-secondary copy-feed-url" 
                                                type="button"
                                                data-feed-url="<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/api/master-feed.php') ?>">
                                            <i class="fas fa-copy"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="d-grid gap-2">
                                    <a href="/api/master-feed.php" 
                                       class="btn btn-primary"
                                       target="_blank">
                                        <i class="fas fa-rss"></i> View Master Feed
                                    </a>
                                    <button type="button" 
                                            class="btn btn-outline-info" 
                                            data-bs-toggle="modal" 
                                            data-bs-target="#qrModal"
                                            data-feed-url="<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . '/api/master-feed.php') ?>"
                                            data-show-name="Master Feed - All Shows">
                                        <i class="fas fa-qrcode"></i> QR Code
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

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
                                <p>Each show also gets its own RSS feed containing only recordings from that specific show.</p>
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
                                                       value="<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . getFeedUrl($show['id'])) ?>"
                                                       readonly>
                                                <button class="btn btn-outline-secondary btn-sm copy-feed-url" 
                                                        type="button"
                                                        data-feed-url="<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . getFeedUrl($show['id'])) ?>">
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
                                        <a href="<?= getFeedUrl($show['id']) ?>" 
                                           class="btn btn-outline-primary btn-sm"
                                           target="_blank">
                                            <i class="fas fa-rss"></i> View Feed
                                        </a>
                                        <form method="POST" class="d-inline">
                                            <input type="hidden" name="action" value="regenerate">
                                            <input type="hidden" name="show_id" value="<?= $show['id'] ?>">
                                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                            <button type="submit" class="btn btn-outline-secondary btn-sm">
                                                <i class="fas fa-sync"></i> Regenerate
                                            </button>
                                        </form>
                                        <button type="button" 
                                                class="btn btn-outline-info btn-sm" 
                                                data-bs-toggle="modal" 
                                                data-bs-target="#qrModal"
                                                data-feed-url="<?= htmlspecialchars($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . getFeedUrl($show['id'])) ?>"
                                                data-show-name="<?= h($show['name']) ?>">
                                            <i class="fas fa-qrcode"></i>
                                        </button>
                                    </div>
                                <?php else: ?>
                                    <div class="text-center text-muted">
                                        <small>No recordings available for RSS feed</small>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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

    <?php
$additional_js = '<script src="/assets/js/radiograb.js"></script>';
require_once '../includes/footer.php';
?>