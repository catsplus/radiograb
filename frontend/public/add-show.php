<?php
/**
 * RadioGrab - Add Show
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Get station ID from URL parameter
$station_id = isset($_GET['station_id']) ? (int)$_GET['station_id'] : null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid security token');
        header('Location: /add-show.php' . ($station_id ? "?station_id=$station_id" : ''));
        exit;
    }
    
    $name = trim($_POST['name'] ?? '');
    $station_id = (int)($_POST['station_id'] ?? 0);
    $description = trim($_POST['description'] ?? '');
    $show_type = $_POST['show_type'] ?? 'scheduled';
    $schedule_text = trim($_POST['schedule_text'] ?? '');
    $duration_minutes = (int)($_POST['duration_minutes'] ?? 60);
    $host = trim($_POST['host'] ?? '');
    $genre = trim($_POST['genre'] ?? '');
    $max_file_size = (int)($_POST['max_file_size'] ?? 100);
    $active = isset($_POST['active']) ? 1 : 0;
    
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
    
    if ($show_type === 'scheduled' && empty($schedule_text)) {
        $errors[] = 'Schedule is required for scheduled shows';
    }
    
    if ($duration_minutes < 1 || $duration_minutes > 1440) {
        $errors[] = 'Duration must be between 1 and 1440 minutes';
    }
    
    if (empty($errors)) {
        try {
            $schedule_data = null;
            
            // Parse schedule for scheduled shows
            if ($show_type === 'scheduled') {
                $python_script = dirname(dirname(__DIR__)) . '/backend/services/parse_schedule.py';
                $command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python " . escapeshellarg($python_script) . " " . escapeshellarg($schedule_text) . " 2>&1";
                $output = shell_exec($command);
                
                // Parse the output to get cron expression
                $schedule_data = json_decode($output, true);
                
                if (!$schedule_data || !isset($schedule_data['cron'])) {
                    $errors[] = 'Could not parse schedule: ' . ($schedule_data['error'] ?? 'Invalid schedule format');
                }
            }
            
            if (empty($errors)) {
                // Check if show with this name already exists for this station
                $existing = $db->fetchOne("SELECT id FROM shows WHERE station_id = ? AND name = ?", [$station_id, $name]);
                if ($existing) {
                    $errors[] = 'A show with this name already exists for this station';
                } else {
                    // Prepare show data
                    $show_data = [
                        'station_id' => $station_id,
                        'name' => $name,
                        'description' => $description ?: null,
                        'show_type' => $show_type,
                        'host' => $host ?: null,
                        'genre' => $genre ?: null,
                        'active' => $active,
                        'allow_uploads' => ($show_type === 'playlist') ? 1 : 0,
                        'retention_days' => ($show_type === 'playlist') ? 0 : 30,  // Never expire for playlists
                        'auto_imported' => 0
                    ];
                    
                    if ($show_type === 'scheduled') {
                        $show_data['schedule_pattern'] = $schedule_data['cron'];
                        $show_data['schedule_description'] = $schedule_data['description'] ?? $schedule_text;
                        $show_data['duration_minutes'] = $duration_minutes;
                    } else {
                        // Playlist type
                        $show_data['schedule_pattern'] = null;
                        $show_data['schedule_description'] = "User upload playlist: {$name}";
                        $show_data['max_file_size_mb'] = $max_file_size;
                    }
                    
                    // Insert new show
                    $show_id = $db->insert('shows', $show_data);
                    
                    // Add the show to the recording scheduler (only for scheduled shows)
                    if ($show_type === 'scheduled') {
                        try {
                            $python_script = dirname(dirname(__DIR__)) . '/backend/services/schedule_manager.py';
                            $schedule_command = "cd /opt/radiograb && PYTHONPATH=/opt/radiograb /opt/radiograb/venv/bin/python $python_script --add-show $show_id 2>&1";
                            $schedule_output = shell_exec($schedule_command);
                            
                            // Log the scheduling result but don't fail if it doesn't work
                            error_log("Show scheduling result for show $show_id: $schedule_output");
                        } catch (Exception $e) {
                            // Log but don't fail the show creation
                            error_log("Failed to schedule show $show_id: " . $e->getMessage());
                        }
                    }
                    
                    $success_message = ($show_type === 'playlist') ? 
                        'Playlist created successfully! You can now upload audio files to it.' : 
                        'Show added and scheduled successfully!';
                    
                    redirectWithMessage('/shows.php', 'success', $success_message);
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
    <title>Add Show - RadioGrab</title>
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
                        <li class="breadcrumb-item active">Add Show</li>
                    </ol>
                </nav>
                <h1><i class="fas fa-plus"></i> Add Radio Show</h1>
                <p class="text-muted">Create a new recording schedule for a radio show</p>
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
                <!-- Add Show Form -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-microphone"></i> Show Information</h5>
                    </div>
                    <div class="card-body">
                        <form id="add-show-form" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="mb-3">
                                <label for="station_id" class="form-label">Station *</label>
                                <select class="form-select" id="station_id" name="station_id" required>
                                    <option value="">Select a station...</option>
                                    <?php foreach ($stations as $station): ?>
                                        <option value="<?= $station['id'] ?>" 
                                                <?= (($_POST['station_id'] ?? $station_id) == $station['id']) ? 'selected' : '' ?>>
                                            <?= h($station['name']) ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <!-- Show Type Selection -->
                            <div class="mb-3">
                                <label class="form-label">Type *</label>
                                <div class="d-flex gap-4">
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="show_type" id="show_type_scheduled" 
                                               value="scheduled" <?= ($_POST['show_type'] ?? 'scheduled') === 'scheduled' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="show_type_scheduled">
                                            <i class="fas fa-clock"></i> Scheduled Show
                                        </label>
                                        <div class="form-text">Automatically record at scheduled times</div>
                                    </div>
                                    <div class="form-check">
                                        <input class="form-check-input" type="radio" name="show_type" id="show_type_playlist" 
                                               value="playlist" <?= ($_POST['show_type'] ?? '') === 'playlist' ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="show_type_playlist">
                                            <i class="fas fa-upload"></i> Playlist/Upload
                                        </label>
                                        <div class="form-text">User-uploaded audio files collection</div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-3">
                                <label for="name" class="form-label">Name *</label>
                                <input type="text" 
                                       class="form-control" 
                                       id="name" 
                                       name="name" 
                                       value="<?= h($_POST['name'] ?? '') ?>"
                                       placeholder="The Morning Show"
                                       required>
                                <div class="form-text" id="name-help-scheduled">Name of the radio show to record</div>
                                <div class="form-text" id="name-help-playlist" style="display: none;">Name of the playlist/collection</div>
                            </div>

                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" 
                                          id="description" 
                                          name="description" 
                                          rows="3"
                                          placeholder="Brief description..."><?= h($_POST['description'] ?? '') ?></textarea>
                            </div>

                            <div class="row">
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="host" class="form-label">Host</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="host" 
                                               name="host" 
                                               value="<?= h($_POST['host'] ?? '') ?>"
                                               placeholder="John Doe">
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="mb-3">
                                        <label for="genre" class="form-label">Genre</label>
                                        <input type="text" 
                                               class="form-control" 
                                               id="genre" 
                                               name="genre" 
                                               value="<?= h($_POST['genre'] ?? '') ?>"
                                               placeholder="Talk, Music, News, etc.">
                                    </div>
                                </div>
                            </div>

                            <!-- Scheduled Show Fields -->
                            <div id="scheduled-fields">
                                <div class="mb-3">
                                    <label for="schedule_text" class="form-label">Schedule *</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="schedule_text" 
                                           name="schedule_text" 
                                           value="<?= h($_POST['schedule_text'] ?? '') ?>"
                                           placeholder="Record every weekday at 8:00 AM">
                                    <div class="form-text">
                                        Use plain English like "every Tuesday at 7 PM" or "weekdays at 9:00 AM"
                                    </div>
                                </div>

                                <div class="mb-3">
                                    <label for="duration_minutes" class="form-label">Duration (minutes) *</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="duration_minutes" 
                                           name="duration_minutes" 
                                           value="<?= h($_POST['duration_minutes'] ?? '60') ?>"
                                           min="1" 
                                           max="1440">
                                    <div class="form-text">
                                        How long to record in minutes (1-1440)
                                    </div>
                                </div>
                            </div>

                            <!-- Playlist Fields -->
                            <div id="playlist-fields" style="display: none;">
                                <div class="mb-3">
                                    <label for="max_file_size" class="form-label">Max Upload Size (MB)</label>
                                    <input type="number" 
                                           class="form-control" 
                                           id="max_file_size" 
                                           name="max_file_size" 
                                           value="<?= h($_POST['max_file_size'] ?? '100') ?>"
                                           min="1" 
                                           max="500">
                                    <div class="form-text">
                                        Maximum file size for uploads (1-500 MB)
                                    </div>
                                </div>
                                
                                <div class="alert alert-info">
                                    <i class="fas fa-info-circle"></i>
                                    <strong>Playlist Features:</strong>
                                    <ul class="mb-0 mt-2">
                                        <li>Users can upload audio files to this playlist</li>
                                        <li>Files never expire and can be reordered</li>
                                        <li>Supports MP3, WAV, M4A, AAC, OGG, FLAC formats</li>
                                        <li>Automatic MP3 conversion and metadata extraction</li>
                                    </ul>
                                </div>
                            </div>

                            <div class="mb-3">
                                <div class="form-check">
                                    <input class="form-check-input" 
                                           type="checkbox" 
                                           id="active" 
                                           name="active"
                                           <?= isset($_POST['active']) || !$_POST ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="active">
                                        Activate show immediately
                                    </label>
                                    <div class="form-text">
                                        If checked, recording will start according to the schedule
                                    </div>
                                </div>
                            </div>

                            <div class="d-flex justify-content-between">
                                <a href="/shows.php" class="btn btn-secondary">
                                    <i class="fas fa-arrow-left"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-plus"></i> Add Show
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>

            <div class="col-lg-4">
                <!-- Schedule Examples -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-clock"></i> Schedule Examples</h5>
                    </div>
                    <div class="card-body">
                        <p class="mb-3">Here are some example schedules you can use:</p>
                        
                        <div class="list-group list-group-flush">
                            <div class="list-group-item border-0 px-0">
                                <code class="schedule-example" role="button">every weekday at 8:00 AM</code>
                                <small class="d-block text-muted">Monday through Friday</small>
                            </div>
                            <div class="list-group-item border-0 px-0">
                                <code class="schedule-example" role="button">every Tuesday at 7 PM</code>
                                <small class="d-block text-muted">Weekly on Tuesdays</small>
                            </div>
                            <div class="list-group-item border-0 px-0">
                                <code class="schedule-example" role="button">weekends at 10:00 AM</code>
                                <small class="d-block text-muted">Saturday and Sunday</small>
                            </div>
                            <div class="list-group-item border-0 px-0">
                                <code class="schedule-example" role="button">every day at 6:00 PM</code>
                                <small class="d-block text-muted">Daily</small>
                            </div>
                            <div class="list-group-item border-0 px-0">
                                <code class="schedule-example" role="button">Monday, Wednesday, Friday at 2:30 PM</code>
                                <small class="d-block text-muted">Specific days</small>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Tips Card -->
                <div class="card mt-4">
                    <div class="card-header">
                        <h5><i class="fas fa-lightbulb"></i> Tips</h5>
                    </div>
                    <div class="card-body">
                        <ul class="list-unstyled mb-0">
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i>
                                Use 12-hour format (8:00 AM, 7:30 PM)
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i>
                                Be specific about days and times
                            </li>
                            <li class="mb-2">
                                <i class="fas fa-check text-success"></i>
                                Set duration slightly longer than the actual show
                            </li>
                            <li class="mb-0">
                                <i class="fas fa-check text-success"></i>
                                You can edit the schedule later if needed
                            </li>
                        </ul>
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
            // Handle schedule example clicks
            document.querySelectorAll('.schedule-example').forEach(example => {
                example.addEventListener('click', function() {
                    document.getElementById('schedule_text').value = this.textContent;
                });
            });
            
            // Handle show type changes
            const showTypeRadios = document.querySelectorAll('input[name="show_type"]');
            const scheduledFields = document.getElementById('scheduled-fields');
            const playlistFields = document.getElementById('playlist-fields');
            const nameHelpScheduled = document.getElementById('name-help-scheduled');
            const nameHelpPlaylist = document.getElementById('name-help-playlist');
            const scheduleInput = document.getElementById('schedule_text');
            const durationInput = document.getElementById('duration_minutes');
            
            function updateFormFields() {
                const selectedType = document.querySelector('input[name="show_type"]:checked').value;
                
                if (selectedType === 'scheduled') {
                    scheduledFields.style.display = 'block';
                    playlistFields.style.display = 'none';
                    nameHelpScheduled.style.display = 'block';
                    nameHelpPlaylist.style.display = 'none';
                    scheduleInput.required = true;
                    durationInput.required = true;
                } else {
                    scheduledFields.style.display = 'none';
                    playlistFields.style.display = 'block';
                    nameHelpScheduled.style.display = 'none';
                    nameHelpPlaylist.style.display = 'block';
                    scheduleInput.required = false;
                    durationInput.required = false;
                }
            }
            
            showTypeRadios.forEach(radio => {
                radio.addEventListener('change', updateFormFields);
            });
            
            // Initialize form state
            updateFormFields();
            
            // Form validation
            const form = document.getElementById('add-show-form');
            form.addEventListener('submit', function(e) {
                const stationId = document.getElementById('station_id').value;
                const name = document.getElementById('name').value.trim();
                const showType = document.querySelector('input[name="show_type"]:checked').value;
                const scheduleText = document.getElementById('schedule_text').value.trim();
                
                if (!stationId) {
                    e.preventDefault();
                    alert('Please select a station');
                    return;
                }
                
                if (!name) {
                    e.preventDefault();
                    alert('Please enter a show name');
                    return;
                }
                
                if (showType === 'scheduled' && !scheduleText) {
                    e.preventDefault();
                    alert('Please enter a schedule for scheduled shows');
                    return;
                }
            });
        });
    </script>

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