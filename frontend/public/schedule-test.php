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
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Test - RadioGrab</title>
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
                        <a class="nav-link" href="/">Dashboard</a>
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
                    <li class="nav-item">
                        <a class="nav-link" href="/feeds.php">RSS Feeds</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link active" href="/schedule-test.php">Schedule Test</a>
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

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/radiograb.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Load initial status
            loadScheduleStatus();
            
            // Get Status button
            document.getElementById('get-status').addEventListener('click', function() {
                loadScheduleStatus();
            });
            
            // Test show schedule buttons
            document.querySelectorAll('.test-show-schedule').forEach(button => {
                button.addEventListener('click', function() {
                    const showId = this.dataset.showId;
                    const showName = this.dataset.showName;
                    
                    if (confirm(`Test recording for "${showName}"?`)) {
                        testShowRecording(showId, showName);
                    }
                });
            });
            
            // Refresh show schedule buttons
            document.querySelectorAll('.refresh-show-schedule').forEach(button => {
                button.addEventListener('click', function() {
                    const showId = this.dataset.showId;
                    const showName = this.dataset.showName;
                    
                    refreshShowSchedule(showId, showName);
                });
            });
        });
        
        function loadScheduleStatus() {
            const loading = document.getElementById('schedule-status-loading');
            const content = document.getElementById('schedule-status-content');
            
            loading.style.display = 'block';
            content.style.display = 'none';
            
            fetch('/api/schedule-manager.php?action=status')
                .then(response => response.json())
                .then(data => {
                    loading.style.display = 'none';
                    content.style.display = 'block';
                    
                    if (data.success) {
                        displayScheduleStatus(data);
                    } else {
                        content.innerHTML = `
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i> 
                                Error loading status: ${data.error}
                            </div>
                        `;
                    }
                })
                .catch(error => {
                    loading.style.display = 'none';
                    content.style.display = 'block';
                    content.innerHTML = `
                        <div class="alert alert-danger">
                            <i class="fas fa-exclamation-triangle"></i> 
                            Network error: ${error.message}
                        </div>
                    `;
                });
        }
        
        function displayScheduleStatus(data) {
            const content = document.getElementById('schedule-status-content');
            
            let html = `
                <div class="row text-center mb-3">
                    <div class="col-md-3">
                        <div class="h4 text-primary">${data.total_active_shows}</div>
                        <small class="text-muted">Active Shows</small>
                    </div>
                    <div class="col-md-3">
                        <div class="h4 text-success">${data.shows_with_schedule}</div>
                        <small class="text-muted">With Schedule</small>
                    </div>
                    <div class="col-md-3">
                        <div class="h4 text-warning">${data.shows_without_schedule}</div>
                        <small class="text-muted">Without Schedule</small>
                    </div>
                    <div class="col-md-3">
                        <div class="h4 text-info">${data.total_scheduled_jobs}</div>
                        <small class="text-muted">Scheduled Jobs</small>
                    </div>
                </div>
            `;
            
            if (data.scheduled_jobs && data.scheduled_jobs.length > 0) {
                html += `
                    <h6>Scheduled Jobs:</h6>
                    <div class="table-responsive">
                        <table class="table table-sm">
                            <thead>
                                <tr>
                                    <th>Show ID</th>
                                    <th>Name</th>
                                    <th>Next Run</th>
                                    <th>Trigger</th>
                                </tr>
                            </thead>
                            <tbody>
                `;
                
                data.scheduled_jobs.forEach(job => {
                    const nextRun = job.next_run ? new Date(job.next_run).toLocaleString() : 'Not scheduled';
                    html += `
                        <tr>
                            <td>${job.show_id}</td>
                            <td>${job.name}</td>
                            <td>${nextRun}</td>
                            <td><code>${job.trigger}</code></td>
                        </tr>
                    `;
                });
                
                html += '</tbody></table></div>';
            }
            
            if (data.unscheduled_shows && data.unscheduled_shows.length > 0) {
                html += `
                    <div class="alert alert-warning mt-3">
                        <h6><i class="fas fa-exclamation-triangle"></i> Unscheduled Shows (${data.unscheduled_shows.length}):</h6>
                        <ul class="mb-0">
                `;
                
                data.unscheduled_shows.forEach(show => {
                    html += `<li>${show.name} (ID: ${show.id}) - Pattern: <code>${show.schedule_pattern}</code></li>`;
                });
                
                html += '</ul></div>';
            }
            
            content.innerHTML = html;
        }
        
        function testShowRecording(showId, showName) {
            // Use existing test recording API
            fetch('/api/test-recording.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=test_recording&station_id=${showId}&csrf_token=${getCsrfToken()}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Test recording started for "${showName}"`);
                } else {
                    alert(`Test recording failed: ${data.error}`);
                }
            })
            .catch(error => {
                alert(`Network error: ${error.message}`);
            });
        }
        
        function refreshShowSchedule(showId, showName) {
            fetch('/api/schedule-manager.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=update_show&show_id=${showId}&csrf_token=${getCsrfToken()}`
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    alert(`Schedule refreshed for "${showName}"`);
                    loadScheduleStatus(); // Refresh status display
                } else {
                    alert(`Schedule refresh failed: ${data.error}`);
                }
            })
            .catch(error => {
                alert(`Network error: ${error.message}`);
            });
        }
        
        function getCsrfToken() {
            // This should match your existing CSRF token implementation
            return '<?= generateCSRFToken() ?>';
        }
    </script>

    <!-- Footer -->
    <footer class="bg-light mt-5 py-3">
        <div class="container">
            <div class="row">
                <div class="col text-center text-muted">
                    <small>
                        RadioGrab - Radio Recorder | 
                        Version: <?= getVersionNumber() ?>
                    </small>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>