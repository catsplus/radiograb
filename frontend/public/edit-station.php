<?php
/**
 * RadioGrab - Edit Station
 * Edit station details including name, description, logo, stream URL, etc.
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Get station ID from URL parameter
$station_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$station_id) {
    redirectWithMessage('/stations.php', 'danger', 'Station ID is required');
}

// Get existing station data
try {
    $station = $db->fetchOne("SELECT * FROM stations WHERE id = ?", [$station_id]);
    
    if (!$station) {
        redirectWithMessage('/stations.php', 'danger', 'Station not found');
    }
} catch (Exception $e) {
    redirectWithMessage('/stations.php', 'danger', 'Database error: ' . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid security token');
        header("Location: /edit-station.php?id=$station_id");
        exit;
    }
    
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $logo_url = trim($_POST['logo_url'] ?? '');
    $stream_url = trim($_POST['stream_url'] ?? '');
    $calendar_url = trim($_POST['calendar_url'] ?? '');
    $timezone = trim($_POST['timezone'] ?? '');
    $call_letters = trim($_POST['call_letters'] ?? '');
    $website_url = trim($_POST['website_url'] ?? '');
    $frequency = trim($_POST['frequency'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $default_stream_mode = trim($_POST['default_stream_mode'] ?? 'inherit');
    
    $errors = [];
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Station name is required';
    }
    
    if (empty($call_letters)) {
        $errors[] = 'Call letters are required';
    }
    
    if ($stream_url && !filter_var($stream_url, FILTER_VALIDATE_URL)) {
        $errors[] = 'Stream URL must be a valid URL';
    }
    
    if ($calendar_url && !filter_var($calendar_url, FILTER_VALIDATE_URL)) {
        $errors[] = 'Calendar URL must be a valid URL';
    }
    
    if ($logo_url && !filter_var($logo_url, FILTER_VALIDATE_URL)) {
        $errors[] = 'Logo URL must be a valid URL';
    }
    
    if ($website_url && !filter_var($website_url, FILTER_VALIDATE_URL)) {
        $errors[] = 'Website URL must be a valid URL';
    }
    
    // Check for duplicate call letters (excluding current station)
    if (empty($errors)) {
        $existing = $db->fetchOne(
            "SELECT id FROM stations WHERE call_letters = ? AND id != ?", 
            [$call_letters, $station_id]
        );
        if ($existing) {
            $errors[] = 'Call letters already exist for another station';
        }
    }
    
    if (empty($errors)) {
        try {
            $db->update('stations', [
                'name' => $name,
                'description' => $description,
                'logo_url' => $logo_url ?: null,
                'stream_url' => $stream_url ?: null,
                'calendar_url' => $calendar_url ?: null,
                'timezone' => $timezone ?: 'America/New_York',
                'call_letters' => $call_letters,
                'website_url' => $website_url ?: null,
                'frequency' => $frequency ?: null,
                'location' => $location ?: null,
                'default_stream_mode' => $default_stream_mode,
                'updated_at' => date('Y-m-d H:i:s')
            ], 'id = ?', [$station_id]);
            
            // Clear any cached test results since station info changed
            $db->update('stations', [
                'last_tested' => null,
                'last_test_result' => null,
                'last_test_error' => null
            ], 'id = ?', [$station_id]);
            
            setFlashMessage('success', 'Station updated successfully');
            header("Location: /stations.php");
            exit;
            
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
    
    // If we have errors, update the station array with submitted values for form repopulation
    if (!empty($errors)) {
        $station = array_merge($station, $_POST);
    }
}

// Set page variables
$page_title = 'Edit Station: ' . $station['name'];
$active_nav = 'stations';

require_once '../includes/header.php';
?>

<div class="container-fluid">
    <div class="row">
        <div class="col-lg-8">
            <div class="card">
                <div class="card-header">
                    <h5><i class="fas fa-edit"></i> Edit Station</h5>
                </div>
                <div class="card-body">
                    <?php if (!empty($errors)): ?>
                        <div class="alert alert-danger">
                            <ul class="mb-0">
                                <?php foreach ($errors as $error): ?>
                                    <li><?= h($error) ?></li>
                                <?php endforeach; ?>
                            </ul>
                        </div>
                    <?php endif; ?>

                    <form method="POST" id="edit-station-form">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="name" class="form-label">Station Name *</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="name" 
                                           name="name" 
                                           value="<?= h($station['name']) ?>"
                                           placeholder="WYSO Public Radio"
                                           required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="call_letters" class="form-label">Call Letters *</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="call_letters" 
                                           name="call_letters" 
                                           value="<?= h($station['call_letters']) ?>"
                                           placeholder="WYSO"
                                           required>
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="description" class="form-label">Description</label>
                            <textarea class="form-control" 
                                      id="description" 
                                      name="description" 
                                      rows="3"
                                      placeholder="Brief description of the station..."><?= h($station['description']) ?></textarea>
                        </div>

                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="frequency" class="form-label">Frequency</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="frequency" 
                                           name="frequency" 
                                           value="<?= h($station['frequency']) ?>"
                                           placeholder="91.3 FM">
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label for="location" class="form-label">Location</label>
                                    <input type="text" 
                                           class="form-control" 
                                           id="location" 
                                           name="location" 
                                           value="<?= h($station['location']) ?>"
                                           placeholder="Yellow Springs, OH">
                                </div>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label for="website_url" class="form-label">Website URL</label>
                            <input type="url" 
                                   class="form-control" 
                                   id="website_url" 
                                   name="website_url" 
                                   value="<?= h($station['website_url']) ?>"
                                   placeholder="https://www.wyso.org">
                        </div>

                        <div class="mb-3">
                            <label for="logo_url" class="form-label">Logo URL</label>
                            <input type="url" 
                                   class="form-control" 
                                   id="logo_url" 
                                   name="logo_url" 
                                   value="<?= h($station['logo_url']) ?>"
                                   placeholder="https://example.com/logo.png">
                            <div class="form-text">URL to the station's logo image</div>
                        </div>

                        <div class="mb-3">
                            <label for="stream_url" class="form-label">Stream URL <small class="text-muted">(optional)</small></label>
                            <input type="url" 
                                   class="form-control" 
                                   id="stream_url" 
                                   name="stream_url" 
                                   value="<?= h($station['stream_url']) ?>"
                                   placeholder="https://stream.example.com/live">
                            <div class="form-text">Direct URL to the audio stream for recording</div>
                        </div>

                        <div class="mb-3">
                            <label for="calendar_url" class="form-label">Calendar URL <small class="text-muted">(optional)</small></label>
                            <input type="url" 
                                   class="form-control" 
                                   id="calendar_url" 
                                   name="calendar_url" 
                                   value="<?= h($station['calendar_url']) ?>"
                                   placeholder="https://www.example.com/schedule">
                            <div class="form-text">URL to the station's schedule/calendar page for automatic show discovery</div>
                        </div>

                        <div class="mb-3">
                            <label for="timezone" class="form-label">Time Zone</label>
                            <select class="form-select" id="timezone" name="timezone">
                                <?php
                                $timezones = [
                                    'America/New_York' => 'Eastern Time (ET)',
                                    'America/Chicago' => 'Central Time (CT)', 
                                    'America/Denver' => 'Mountain Time (MT)',
                                    'America/Los_Angeles' => 'Pacific Time (PT)',
                                    'America/Anchorage' => 'Alaska Time (AT)',
                                    'Pacific/Honolulu' => 'Hawaii Time (HT)',
                                    'America/Phoenix' => 'Arizona Time (MST)',
                                    'America/Indiana/Indianapolis' => 'Indiana Time (ET)',
                                ];
                                
                                $selected_timezone = $station['timezone'] ?: 'America/New_York';
                                
                                foreach ($timezones as $tz => $label) {
                                    $selected = ($tz === $selected_timezone) ? 'selected' : '';
                                    echo "<option value=\"$tz\" $selected>$label</option>";
                                }
                                ?>
                            </select>
                            <div class="form-text">Time zone for show schedules and recordings</div>
                        </div>

                        <!-- Streaming Controls Section -->
                        <div class="mb-4">
                            <h6 class="text-muted mb-3">
                                <i class="fas fa-stream"></i> Streaming & Download Controls
                            </h6>
                            <div class="mb-3">
                                <label for="default_stream_mode" class="form-label">Default Stream Mode</label>
                                <select class="form-select" id="default_stream_mode" name="default_stream_mode">
                                    <?php
                                    $stream_modes = [
                                        'inherit' => 'Inherit (Use system default)',
                                        'allow_downloads' => 'Allow Downloads - Users can download and stream',
                                        'stream_only' => 'Stream Only - No downloads (DMCA compliant)'
                                    ];
                                    
                                    $selected_mode = $station['default_stream_mode'] ?: 'inherit';
                                    
                                    foreach ($stream_modes as $mode => $label) {
                                        $selected = ($mode === $selected_mode) ? 'selected' : '';
                                        echo "<option value=\"$mode\" $selected>$label</option>";
                                    }
                                    ?>
                                </select>
                                <div class="form-text">
                                    <small>
                                        <strong>Allow Downloads:</strong> Users can download recordings (suitable for talk/news/educational content)<br>
                                        <strong>Stream Only:</strong> Users can only stream recordings (recommended for music content and DMCA compliance)<br>
                                        <strong>Inherit:</strong> Use system-wide default setting
                                    </small>
                                </div>
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <a href="/stations.php" class="btn btn-secondary">
                                <i class="fas fa-arrow-left"></i> Cancel
                            </a>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i> Save Changes
                            </button>
                        </div>

                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                    </form>
                </div>
            </div>
        </div>

        <div class="col-lg-4">
            <!-- Station Preview -->
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-eye"></i> Station Preview</h6>
                </div>
                <div class="card-body">
                    <div class="d-flex align-items-center mb-3" id="station-preview">
                        <img src="<?= h($station['logo_url'] ?: '/assets/images/default-station-logo.png') ?>" 
                             alt="Station Logo" 
                             class="station-logo me-3"
                             style="width: 60px; height: 60px; object-fit: cover; border-radius: 8px;"
                             onerror="this.src='/assets/images/default-station-logo.png'">
                        <div>
                            <h6 class="mb-1"><?= h($station['name']) ?></h6>
                            <small class="text-muted"><?= h($station['call_letters']) ?></small>
                            <?php if ($station['frequency']): ?>
                                <br><small class="text-muted"><?= h($station['frequency']) ?></small>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <?php if ($station['description']): ?>
                        <p class="small text-muted"><?= h($station['description']) ?></p>
                    <?php endif; ?>
                    
                    <div class="small">
                        <?php if ($station['location']): ?>
                            <div><i class="fas fa-map-marker-alt text-muted"></i> <?= h($station['location']) ?></div>
                        <?php endif; ?>
                        
                        <?php if ($station['website_url']): ?>
                            <div class="mt-1">
                                <i class="fas fa-globe text-muted"></i> 
                                <a href="<?= h($station['website_url']) ?>" target="_blank" class="text-decoration-none">
                                    Website <i class="fas fa-external-link-alt fa-xs"></i>
                                </a>
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($station['stream_url']): ?>
                            <div class="mt-1">
                                <i class="fas fa-broadcast-tower text-muted"></i> Stream URL configured
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($station['calendar_url']): ?>
                            <div class="mt-1">
                                <i class="fas fa-calendar text-muted"></i> Schedule URL configured
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Station Statistics -->
            <div class="card mt-3">
                <div class="card-header">
                    <h6><i class="fas fa-chart-bar"></i> Statistics</h6>
                </div>
                <div class="card-body">
                    <?php
                    try {
                        $stats = $db->fetchOne("
                            SELECT 
                                COUNT(DISTINCT sh.id) as show_count,
                                COUNT(DISTINCT CASE WHEN sh.active = 1 THEN sh.id END) as active_shows,
                                COUNT(r.id) as total_recordings,
                                SUM(r.file_size_bytes) as total_size_bytes
                            FROM shows sh
                            LEFT JOIN recordings r ON sh.id = r.show_id
                            WHERE sh.station_id = ?
                        ", [$station_id]);
                    ?>
                        <div class="row text-center">
                            <div class="col-6">
                                <div class="h5 mb-0"><?= $stats['show_count'] ?></div>
                                <small class="text-muted">Total Shows</small>
                            </div>
                            <div class="col-6">
                                <div class="h5 mb-0"><?= $stats['active_shows'] ?></div>
                                <small class="text-muted">Active Shows</small>
                            </div>
                            <div class="col-6 mt-3">
                                <div class="h5 mb-0"><?= $stats['total_recordings'] ?></div>
                                <small class="text-muted">Recordings</small>
                            </div>
                            <div class="col-6 mt-3">
                                <div class="h5 mb-0"><?= formatBytes($stats['total_size_bytes'] ?: 0) ?></div>
                                <small class="text-muted">Total Size</small>
                            </div>
                        </div>
                    <?php
                    } catch (Exception $e) {
                        echo '<p class="text-muted">Statistics unavailable</p>';
                    }
                    ?>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Live preview updates
    const nameInput = document.getElementById('name');
    const callLettersInput = document.getElementById('call_letters');
    const logoInput = document.getElementById('logo_url');
    const frequencyInput = document.getElementById('frequency');
    
    const previewName = document.querySelector('#station-preview h6');
    const previewCall = document.querySelector('#station-preview .text-muted');
    const previewLogo = document.querySelector('#station-preview img');
    
    function updatePreview() {
        if (nameInput.value) {
            previewName.textContent = nameInput.value;
        }
        
        let callText = callLettersInput.value || '<?= h($station['call_letters']) ?>';
        if (frequencyInput.value) {
            callText += ' - ' + frequencyInput.value;
        }
        previewCall.innerHTML = callText;
        
        if (logoInput.value) {
            previewLogo.src = logoInput.value;
        }
    }
    
    nameInput.addEventListener('input', updatePreview);
    callLettersInput.addEventListener('input', updatePreview);
    logoInput.addEventListener('input', updatePreview);
    frequencyInput.addEventListener('input', updatePreview);
});
</script>

<?php
require_once '../includes/footer.php';
?>