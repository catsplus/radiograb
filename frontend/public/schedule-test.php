<?php
/**
 * RadioGrab Schedule Test Interface
 * Test and monitor automatic recording scheduler
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Handle actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid security token');
        header('Location: /schedule-test.php');
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    switch ($action) {
        case 'refresh_schedules':
            $python_script = dirname(dirname(__DIR__)) . '/backend/services/schedule_manager.py';
            $command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python $python_script --refresh-all 2>&1";
            $output = shell_exec($command);
            
            setFlashMessage('info', 'Schedule refresh completed. Check logs for details.');
            error_log("Schedule refresh output: $output");
            break;
            
        case 'get_status': 
            // Status will be fetched via JavaScript
            break;
    }
    
    header('Location: /schedule-test.php');
    exit;
}

// Get shows for testing
try {
    $shows = $db->fetchAll("
        SELECT s.id, s.name, s.schedule_pattern, s.schedule_description, s.active,
               st.name as station_name
        FROM shows s 
        JOIN stations st ON s.station_id = st.id 
        ORDER BY s.name
    ");
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $shows = [];
}
?>
<?php
// Set page variables for shared template
$page_title = 'Schedule Test';
$active_nav = 'schedule-test';

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
                <h1><i class="fas fa-clock"></i> Schedule Test & Monitoring</h1>
                <p class="text-muted">Test and monitor the automatic recording scheduler</p>
            </div>
        </div>

        <!-- Control Panel -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-cogs"></i> Scheduler Controls</h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-8">
                                <p>Use these controls to manage and test the automatic recording scheduler.</p>
                            </div>
                            <div class="col-md-4">
                                <div class="d-grid gap-2">
                                    <form method="POST" class="d-inline">
                                        <input type="hidden" name="action" value="refresh_schedules">
                                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                                        <button type="submit" class="btn btn-primary w-100">
                                            <i class="fas fa-sync"></i> Refresh All Schedules
                                        </button>
                                    </form>
                                    <button type="button" id="get-status" class="btn btn-info w-100">
                                        <i class="fas fa-info-circle"></i> Get Status
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Schedule Status -->
        <div class="row mb-4">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list"></i> Schedule Status</h5>
                    </div>
                    <div class="card-body">
                        <div id="schedule-status-loading" class="text-center py-3">
                            <i class="fas fa-spinner fa-spin"></i> Loading schedule status...
                        </div>
                        <div id="schedule-status-content" style="display: none;"></div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Shows List -->
        <div class="row">
            <div class="col-12">
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-tv"></i> Shows & Schedules</h5>
                    </div>
                    <div class="card-body">
                        <?php if (empty($shows)): ?>
                            <div class="text-center py-5">
                                <i class="fas fa-tv fa-3x text-muted mb-3"></i>
                                <h3>No shows found</h3>
                                <p class="text-muted mb-4">Add some shows to see scheduling information.</p>
                                <a href="/add-show.php" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Show
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-striped">
                                    <thead>
                                        <tr>
                                            <th>Show</th>
                                            <th>Station</th>
                                            <th>Schedule</th>
                                            <th>Pattern</th>
                                            <th>Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($shows as $show): ?>
                                            <tr>
                                                <td>
                                                    <strong><?= h($show['name']) ?></strong>
                                                </td>
                                                <td><?= h($show['station_name']) ?></td>
                                                <td>
                                                    <?php if ($show['schedule_description']): ?>
                                                        <?= h($show['schedule_description']) ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">No description</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($show['schedule_pattern']): ?>
                                                        <code><?= h($show['schedule_pattern']) ?></code>
                                                    <?php else: ?>
                                                        <span class="text-warning">No pattern</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if ($show['active'] && $show['schedule_pattern']): ?>
                                                        <span class="badge bg-success">
                                                            <i class="fas fa-check"></i> Scheduled
                                                        </span>
                                                    <?php elseif (!$show['active']): ?>
                                                        <span class="badge bg-secondary">
                                                            <i class="fas fa-pause"></i> Inactive
                                                        </span>
                                                    <?php else: ?>
                                                        <span class="badge bg-warning">
                                                            <i class="fas fa-exclamation"></i> No Schedule
                                                        </span>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div class="btn-group btn-group-sm" role="group">
                                                        <button type="button" 
                                                                class="btn btn-outline-primary test-show-schedule"
                                                                data-show-id="<?= $show['id'] ?>"
                                                                data-show-name="<?= h($show['name']) ?>">
                                                            <i class="fas fa-play"></i>
                                                        </button>
                                                        <button type="button" 
                                                                class="btn btn-outline-secondary refresh-show-schedule"
                                                                data-show-id="<?= $show['id'] ?>"
                                                                data-show-name="<?= h($show['name']) ?>">
                                                            <i class="fas fa-sync"></i>
                                                        </button>
                                                    </div>
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
        </div>
    </div>

    <?php
$additional_js = '<script src="/assets/js/radiograb.js"></script>';
require_once '../includes/footer.php';
?>