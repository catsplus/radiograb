<?php
/**
 * RadioGrab - Edit Show
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Get show ID from URL parameter
$show_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$show_id) {
    redirectWithMessage('/shows.php', 'danger', 'Show ID is required');
}

// Get existing show data
try {
    $show = $db->fetchOne("
        SELECT s.*, st.name as station_name 
        FROM shows s 
        JOIN stations st ON s.station_id = st.id 
        WHERE s.id = ?
    ", [$show_id]);
    
    if (!$show) {
        redirectWithMessage('/shows.php', 'danger', 'Show not found');
    }
} catch (Exception $e) {
    redirectWithMessage('/shows.php', 'danger', 'Database error: ' . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid security token');
        header('Location: /edit-show.php?id=' . $show_id);
        exit;
    }
    
    $name = trim($_POST['name'] ?? '');
    $station_id = (int)($_POST['station_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $schedule_text = trim($_POST['schedule_text'] ?? '');
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 60);
    $host = trim($_POST['host'] ?? '');
    $genre = trim($_POST['genre'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;
    $retention_days = (int)($_POST['retention_days'] ?? 30);
    $default_ttl_type = $_POST['default_ttl_type'] ?? 'days';
    
    $errors = [];
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Show name is required';
    }
    
    if (!$station_id) {
        $errors[] = 'Station selection is required';
    } else {
        // Verify station exists
        $station = $db->fetchOne("SELECT id FROM stations WHERE id = ?", [$station_id]);
        if (!$station) {
            $errors[] = 'Selected station does not exist';
        }
    }
    
    if (empty($schedule_text)) {
        $errors[] = 'Schedule is required';
    }
    
    if ($duration_minutes < 1 || $duration_minutes > 1440) {
        $errors[] = 'Duration must be between 1 and 1440 minutes';
    }
    
    if ($retention_days < 1 || $retention_days > 3650) {
        $errors[] = 'Retention period must be between 1 and 3650 days';
    }
    
    $valid_ttl_types = ['days', 'weeks', 'months', 'indefinite'];
    if (!in_array($default_ttl_type, $valid_ttl_types)) {
        $errors[] = 'Invalid TTL type';
    }
    
    if (empty($errors)) {
        try {
            // Parse schedule text using Python schedule parser
            $python_script = dirname(dirname(__DIR__)) . '/backend/services/parse_schedule.py';
            $command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python " . escapeshellarg($python_script) . " " . escapeshellarg($schedule_text) . " 2>&1";
            $output = shell_exec($command);
            
            // Parse the output to get cron expression
            $schedule_data = json_decode($output, true);
            
            if (!$schedule_data || !isset($schedule_data['cron'])) {
                $errors[] = 'Could not parse schedule: ' . ($schedule_data['error'] ?? 'Invalid schedule format');
            } else {
                // Check if show with this name already exists for this station (excluding current show)
                $existing = $db->fetchOne("SELECT id FROM shows WHERE station_id = ? AND name = ? AND id != ?", [$station_id, $name, $show_id]);
                if ($existing) {
                    $errors[] = 'A show with this name already exists for this station';
                } else {
                    // Update show
                    $db->update('shows', [
                        'station_id' => $station_id,
                        'name' => $name,
                        'description' => $description ?: null,
                        'schedule_pattern' => $schedule_data['cron'],
                        'schedule_description' => $schedule_data['description'] ?? $schedule_text,
                        'duration_minutes' => $duration_minutes,
                        'host' => $host ?: null,
                        'genre' => $genre ?: null,
                        'active' => $active,
                        'retention_days' => $retention_days,
                        'default_ttl_type' => $default_ttl_type,
                        'updated_at' => date('Y-m-d H:i:s')
                    ], 'id = ?', [$show_id]);
                    
                    // Update existing recordings TTL that don't have overrides
                    try {
                        $python_script = dirname(dirname(__DIR__)) . '/backend/services/ttl_manager.py';
                        $ttl_command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python $python_script --update-show-ttl $show_id --ttl-days $retention_days --ttl-type $default_ttl_type 2>&1";
                        $ttl_output = shell_exec($ttl_command);
                        error_log("TTL update result for show $show_id: $ttl_output");
                    } catch (Exception $e) {
                        error_log("Failed to update TTL for show $show_id: " . $e->getMessage());
                    }
                    
                    // Update the show in the recording scheduler
                    try {
                        $python_script = dirname(dirname(__DIR__)) . '/backend/services/schedule_manager.py';
                        $schedule_command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python $python_script --update-show $show_id 2>&1";
                        $schedule_output = shell_exec($schedule_command);
                        
                        // Log the scheduling result but don't fail if it doesn't work
                        error_log("Show scheduling update result for show $show_id: $schedule_output");
                    } catch (Exception $e) {
                        // Log but don't fail the show update
                        error_log("Failed to update schedule for show $show_id: " . $e->getMessage());
                    }
                    
                    redirectWithMessage('/shows.php', 'success', 'Show updated and rescheduled successfully!');
                }
            }
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}

// Get stations for dropdown
try {
    $stations = $db->fetchAll("SELECT id, name FROM stations WHERE status = 'active' ORDER BY name");
} catch (Exception $e) {
    $stations = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Show - RadioGrab</title>
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
                        <a class="nav-link active" href="/shows.php">Shows</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/recordings.php">Recordings</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/feeds.php">RSS Feeds</a>
                    </li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/shows.php">Shows</a></li>
                        <li class="breadcrumb-item active">Edit Show</li>
                    </ol>
                </nav>
                <h1><i class="fas fa-edit"></i> Edit Radio Show</h1>
                <p class="text-muted">Update the recording schedule for "<?= h($show['name']) ?>"</p>
            </div>
        </div>

        <!-- Error Messages -->
        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?= h($error) ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row">
            <div class="col-lg-8">
                <!-- Edit Show Form -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-microphone"></i> Show Information</h5>
                    </div>
                    <div class="card-body">
                        <form id="edit-show-form" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="mb-3">
                                <label for="station_id" class="form-label">Station *</label>
                                <select class="form-select" id="station_id" name="station_id" required>
                                    <option value="">Select a station...</option>
                                    <?php foreach ($stations as $station): ?>
                                        <option value="<?= $station['id'] ?>" <?= $station['id'] == $show['station_id'] ? 'selected' : '' ?>>
                                            <?= h($station['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Show Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= h($show['name']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?= h($show['description']) ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label for="schedule_text" class="form-label">Schedule *</label>
                                <input type="text" class="form-control" id="schedule_text" name="schedule_text" 
                                       value="<?= h($show['schedule_description']) ?>" required 
                                       placeholder="e.g., Monday 9:00 AM, Weekdays 6:00 PM">
                                <div class="form-text">
                                    Examples: "Monday 9:00 AM", "Weekdays 6:00 PM", "Saturday 2:30 PM"
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="duration_minutes" class="form-label">Duration (minutes) *</label>
                                        <input type="number" class="form-control" id="duration_minutes" name="duration_minutes" 
                                               value="<?= $show['duration_minutes'] ?>" min="1" max="1440" required>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="active" class="form-label">Status</label>
                                        <div class="form-check form-switch">
                                            <input class="form-check-input" type="checkbox" id="active" name="active" 
                                                   <?= $show['active'] ? 'checked' : '' ?>>
                                            <label class="form-check-label" for="active">
                                                Active (enable recording)
                                            </label>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="host" class="form-label">Host</label>
                                        <input type="text" class="form-control" id="host" name="host" 
                                               value="<?= h($show['host']) ?>">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="genre" class="form-label">Genre</label>
                                        <input type="text" class="form-control" id="genre" name="genre" 
                                               value="<?= h($show['genre']) ?>">
                                    </div>
                                </div>
                            </div>
                            
                            <!-- TTL Settings -->
                            <div class="row">
                                <div class="col-md-8">
                                    <div class="mb-3">
                                        <label for="retention_days" class="form-label">Recording Retention</label>
                                        <input type="number" class="form-control" id="retention_days" name="retention_days" 
                                               value="<?= $show['retention_days'] ?>" min="1" max="3650">
                                        <div class="form-text">How long to keep recordings before automatic deletion</div>
                                    </div>
                                </div>
                                <div class="col-md-4">
                                    <div class="mb-3">
                                        <label for="default_ttl_type" class="form-label">Time Unit</label>
                                        <select class="form-select" id="default_ttl_type" name="default_ttl_type">
                                            <option value="days" <?= ($show['default_ttl_type'] ?? 'days') === 'days' ? 'selected' : '' ?>>Days</option>
                                            <option value="weeks" <?= ($show['default_ttl_type'] ?? 'days') === 'weeks' ? 'selected' : '' ?>>Weeks</option>
                                            <option value="months" <?= ($show['default_ttl_type'] ?? 'days') === 'months' ? 'selected' : '' ?>>Months</option>
                                            <option value="indefinite" <?= ($show['default_ttl_type'] ?? 'days') === 'indefinite' ? 'selected' : '' ?>>Keep Forever</option>
                                        </select>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="/shows.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Show
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Show Status -->
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-info-circle"></i> Current Status</h6>
                    </div>
                    <div class="card-body">
                        <div class="mb-2">
                            <strong>Station:</strong> <?= h($show['station_name']) ?>
                        </div>
                        <div class="mb-2">
                            <strong>Status:</strong> 
                            <span class="badge <?= $show['active'] ? 'bg-success' : 'bg-secondary' ?>">
                                <?= $show['active'] ? 'Active' : 'Inactive' ?>
                            </span>
                        </div>
                        <?php if ($show['schedule_pattern']): ?>
                            <div class="mb-2">
                                <strong>Current Schedule:</strong><br>
                                <code><?= h($show['schedule_pattern']) ?></code>
                            </div>
                        <?php endif; ?>
                        <div class="mb-2">
                            <strong>Created:</strong> <?= timeAgo($show['created_at']) ?>
                        </div>
                        <?php if ($show['updated_at']): ?>
                            <div class="mb-2">
                                <strong>Last Updated:</strong> <?= timeAgo($show['updated_at']) ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
                
                <!-- Schedule Help -->
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-question-circle"></i> Schedule Help</h6>
                    </div>
                    <div class="card-body">
                        <p class="mb-2"><strong>Format examples:</strong></p>
                        <ul class="list-unstyled">
                            <li><code>Monday 9:00 AM</code></li>
                            <li><code>Weekdays 6:00 PM</code></li>
                            <li><code>Saturday 2:30 PM</code></li>
                            <li><code>Sunday 8:00 AM</code></li>
                        </ul>
                        <p class="mb-0 text-muted">
                            <small>Use natural language to describe when the show airs.</small>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
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
</body>
</html>