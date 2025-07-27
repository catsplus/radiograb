<?php
/**
 * RadioGrab - Stations Management
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Handle station deletion
if ($_POST['action'] ?? '' === 'delete' && isset($_POST['station_id'])) {
    if (verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        try {
            $station_id = (int)$_POST['station_id'];
            $db->delete('stations', 'id = ?', [$station_id]);
            setFlashMessage('success', 'Station deleted successfully');
        } catch (Exception $e) {
            setFlashMessage('danger', 'Failed to delete station');
        }
    }
    header('Location: /stations.php');
    exit;
}

// Get stations with show counts and last test information
try {
    $stations = $db->fetchAll("
        SELECT s.*, 
               COUNT(sh.id) as show_count,
               COUNT(r.id) as recording_count,
               s.last_tested,
               s.last_test_result,
               s.last_test_error
        FROM stations s 
        LEFT JOIN shows sh ON s.id = sh.station_id AND sh.active = 1
        LEFT JOIN recordings r ON sh.id = r.show_id
        GROUP BY s.id 
        ORDER BY s.name
    ");
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $stations = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Stations - RadioGrab</title>
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
                        <a class="nav-link active" href="/stations.php">Stations</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/shows.php">Shows</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link" href="/recordings.php">Recordings</a>
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
                <h1><i class="fas fa-broadcast-tower"></i> Radio Stations</h1>
                <p class="text-muted">Manage your radio stations and their streaming information</p>
            </div>
            <div class="col-auto">
                <a href="/add-station.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Station
                </a>
            </div>
        </div>

        <!-- Stations List -->
        <?php if (empty($stations)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-broadcast-tower fa-3x text-muted mb-3"></i>
                    <h3>No stations yet</h3>
                    <p class="text-muted mb-4">Get started by adding your first radio station.</p>
                    <a href="/add-station.php" class="btn btn-primary">
                        <i class="fas fa-plus"></i> Add Your First Station
                    </a>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($stations as $station): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card h-100">
                            <div class="card-body">
                                <div class="d-flex align-items-start mb-3">
                                    <img src="<?= h(getStationLogo($station)) ?>" 
                                         alt="<?= h($station['name']) ?>" 
                                         class="station-logo me-3"
                                         onerror="this.src='/assets/images/default-station-logo.png'">
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-1"><?= h($station['name']) ?></h5>
                                        <small class="text-muted"><?= h($station['website_url']) ?></small>
                                    </div>
                                    <span class="badge <?= $station['status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                                        <?= h($station['status']) ?>
                                    </span>
                                </div>
                                
                                <div class="mb-3">
                                    <?php if ($station['stream_url']): ?>
                                        <div class="mb-2">
                                            <i class="fas fa-stream text-success"></i>
                                            <small class="text-muted">Stream URL configured</small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <?php if ($station['timezone']): ?>
                                        <div class="mb-2">
                                            <i class="fas fa-clock text-info"></i>
                                            <small class="text-muted">Timezone: <?= h($station['timezone']) ?></small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Last Tested Information -->
                                    <div class="mb-2">
                                        <?php if ($station['last_tested']): ?>
                                            <?php 
                                            $test_icon = 'fas fa-check-circle text-success';
                                            $test_text = 'success';
                                            if ($station['last_test_result'] === 'failed') {
                                                $test_icon = 'fas fa-times-circle text-danger';
                                                $test_text = 'failed';
                                            } elseif ($station['last_test_result'] === 'error') {
                                                $test_icon = 'fas fa-exclamation-triangle text-warning';
                                                $test_text = 'error';
                                            }
                                            ?>
                                            <i class="<?= $test_icon ?>"></i>
                                            <small class="text-muted">
                                                Last tested: <?= timeAgo($station['last_tested']) ?> (<?= $test_text ?>)
                                                <?php if ($station['last_test_error']): ?>
                                                    <span class="text-danger" title="<?= h($station['last_test_error']) ?>">
                                                        <i class="fas fa-info-circle"></i>
                                                    </span>
                                                <?php endif; ?>
                                            </small>
                                        <?php else: ?>
                                            <i class="fas fa-question-circle text-muted"></i>
                                            <small class="text-muted">Never tested</small>
                                        <?php endif; ?>
                                    </div>
                                    
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
                                </div>
                            </div>
                            
                            <div class="card-footer bg-transparent">
                                <div class="d-grid gap-2">
                                    <!-- Primary Actions -->
                                    <div class="btn-group" role="group">
                                        <a href="/edit-station.php?id=<?= $station['id'] ?>" 
                                           class="btn btn-outline-primary btn-sm">
                                            <i class="fas fa-edit"></i> Edit
                                        </a>
                                        <a href="/shows.php?station_id=<?= $station['id'] ?>" 
                                           class="btn btn-outline-info btn-sm">
                                            <i class="fas fa-microphone"></i> Shows
                                        </a>
                                        <button type="button" 
                                                class="btn btn-outline-success btn-sm import-schedule"
                                                data-station-id="<?= $station['id'] ?>"
                                                data-station-name="<?= h($station['name']) ?>">
                                            <i class="fas fa-calendar-import"></i> Import
                                        </button>
                                    </div>
                                    
                                    <!-- Recording Actions -->
                                    <?php if ($station['stream_url']): ?>
                                    <div class="btn-group" role="group">
                                        <button type="button" 
                                                class="btn btn-warning btn-sm test-recording"
                                                data-station-id="<?= $station['id'] ?>"
                                                data-station-name="<?= h($station['name']) ?>"
                                                data-stream-url="<?= h($station['stream_url']) ?>">
                                            <i class="fas fa-play-circle"></i> Test (10s)
                                        </button>
                                        <button type="button" 
                                                class="btn btn-danger btn-sm record-now"
                                                data-station-id="<?= $station['id'] ?>"
                                                data-station-name="<?= h($station['name']) ?>"
                                                data-stream-url="<?= h($station['stream_url']) ?>">
                                            <i class="fas fa-record-vinyl"></i> Record Now (1h)
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                    
                                    <!-- Delete Action -->
                                    <button type="button" 
                                            class="btn btn-outline-danger btn-sm delete-confirm"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteModal"
                                            data-station-id="<?= $station['id'] ?>"
                                            data-station-name="<?= h($station['name']) ?>"
                                            data-item="station">
                                        <i class="fas fa-trash"></i> Delete
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>

    <!-- Test Recordings Section -->
    <div class="container mt-5">
        <div class="row">
            <div class="col">
                <h2><i class="fas fa-play-circle"></i> Test Recordings</h2>
                <p class="text-muted">Recent 10-second test recordings from your stations</p>
                
                <div id="testRecordingsContainer">
                    <div class="text-center">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Loading...</span>
                        </div>
                        <p>Loading test recordings...</p>
                    </div>
                </div>
                
                <button class="btn btn-outline-primary btn-sm mt-3" onclick="loadTestRecordings()">
                    <i class="fas fa-sync"></i> Refresh
                </button>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Delete Station</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the station <strong id="stationName"></strong>?</p>
                    <p class="text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        This will also delete all associated shows and recordings. This action cannot be undone.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="station_id" id="deleteStationId">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Station
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- Import Schedule Modal -->
    <div class="modal fade" id="importModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Import Schedule for <span id="importStationName"></span></h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div id="importStep1">
                        <p>This will scan the station's website for show schedules and import them automatically.</p>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="autoCreate" checked>
                            <label class="form-check-label" for="autoCreate">
                                Automatically create new shows
                            </label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" id="updateExisting">
                            <label class="form-check-label" for="updateExisting">
                                Update existing shows with new information
                            </label>
                        </div>
                    </div>
                    
                    <div id="importStep2" style="display: none;">
                        <div class="text-center mb-3">
                            <div class="spinner-border" role="status">
                                <span class="visually-hidden">Loading...</span>
                            </div>
                            <p>Scanning station website for schedule...</p>
                        </div>
                    </div>
                    
                    <div id="importStep3" style="display: none;">
                        <h6>Found Shows:</h6>
                        <div id="previewShows"></div>
                    </div>
                    
                    <div id="importStep4" style="display: none;">
                        <h6>Import Results:</h6>
                        <div id="importResults"></div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" id="previewBtn" class="btn btn-primary">Preview Shows</button>
                    <button type="button" id="importBtn" class="btn btn-success" style="display: none;">Import Shows</button>
                </div>
            </div>
        </div>
    </div>

    <!-- Scripts -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/radiograb.js"></script>
    <script>
        let currentStationId = null;
        
        // Handle delete modal
        document.addEventListener('DOMContentLoaded', function() {
            const deleteModal = document.getElementById('deleteModal');
            deleteModal.addEventListener('show.bs.modal', function(event) {
                const button = event.relatedTarget;
                const stationId = button.getAttribute('data-station-id');
                const stationName = button.getAttribute('data-station-name');
                
                document.getElementById('deleteStationId').value = stationId;
                document.getElementById('stationName').textContent = stationName;
            });
            
            // Handle import schedule buttons
            document.querySelectorAll('.import-schedule').forEach(btn => {
                btn.addEventListener('click', function() {
                    currentStationId = this.dataset.stationId;
                    document.getElementById('importStationName').textContent = this.dataset.stationName;
                    
                    // Reset modal state
                    document.getElementById('importStep1').style.display = 'block';
                    document.getElementById('importStep2').style.display = 'none';
                    document.getElementById('importStep3').style.display = 'none';
                    document.getElementById('importStep4').style.display = 'none';
                    document.getElementById('previewBtn').style.display = 'inline-block';
                    document.getElementById('importBtn').style.display = 'none';
                    
                    // Show modal
                    new bootstrap.Modal(document.getElementById('importModal')).show();
                });
            });
            
            // Handle preview button
            document.getElementById('previewBtn').addEventListener('click', function() {
                previewSchedule();
            });
            
            // Handle import button
            document.getElementById('importBtn').addEventListener('click', function() {
                importSchedule();
            });
        });
        
        async function previewSchedule() {
            document.getElementById('importStep1').style.display = 'none';
            document.getElementById('importStep2').style.display = 'block';
            
            try {
                const response = await fetch('/api/import-schedule.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'preview',
                        station_id: currentStationId,
                        csrf_token: '<?= generateCSRFToken() ?>'
                    })
                });
                
                const result = await response.json();
                
                document.getElementById('importStep2').style.display = 'none';
                
                if (result.success) {
                    displayPreview(result.shows);
                    document.getElementById('importStep3').style.display = 'block';
                    document.getElementById('previewBtn').style.display = 'none';
                    document.getElementById('importBtn').style.display = 'inline-block';
                } else {
                    alert('Error: ' + result.error);
                    document.getElementById('importStep1').style.display = 'block';
                }
            } catch (error) {
                document.getElementById('importStep2').style.display = 'none';
                document.getElementById('importStep1').style.display = 'block';
                alert('Network error occurred');
            }
        }
        
        function toggleAllShows(select) {
            const checkboxes = document.querySelectorAll('.show-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = select;
            });
        }
        
        function getSelectedShows() {
            const selectedShows = [];
            const checkboxes = document.querySelectorAll('.show-checkbox:checked');
            checkboxes.forEach(checkbox => {
                const index = parseInt(checkbox.value);
                if (window.previewShows && window.previewShows[index]) {
                    selectedShows.push(window.previewShows[index]);
                }
            });
            return selectedShows;
        }
        
        async function importSchedule() {
            // Get selected shows
            const selectedShows = getSelectedShows();
            
            if (selectedShows.length === 0) {
                alert('Please select at least one show to import.');
                return;
            }
            
            document.getElementById('importStep3').style.display = 'none';
            document.getElementById('importStep2').style.display = 'block';
            document.querySelector('#importStep2 p').textContent = `Importing ${selectedShows.length} selected shows...`;
            
            try {
                const response = await fetch('/api/import-schedule.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: JSON.stringify({
                        action: 'import',
                        station_id: currentStationId,
                        auto_create: document.getElementById('autoCreate').checked,
                        update_existing: document.getElementById('updateExisting').checked,
                        selected_shows: selectedShows,
                        csrf_token: '<?= generateCSRFToken() ?>'
                    })
                });
                
                const result = await response.json();
                
                document.getElementById('importStep2').style.display = 'none';
                
                if (result.success) {
                    displayResults(result.results);
                    document.getElementById('importStep4').style.display = 'block';
                    document.getElementById('importBtn').style.display = 'none';
                    
                    // Refresh page after successful import
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                } else {
                    alert('Error: ' + result.error);
                    document.getElementById('importStep3').style.display = 'block';
                }
            } catch (error) {
                document.getElementById('importStep2').style.display = 'none';
                document.getElementById('importStep3').style.display = 'block';
                alert('Network error occurred');
            }
        }
        
        function displayPreview(shows) {
            const container = document.getElementById('previewShows');
            
            if (shows.length === 0) {
                container.innerHTML = '<p class="text-muted">No shows found in the station schedule.</p>';
                return;
            }
            
            let html = `
                <div class="mb-3">
                    <button type="button" class="btn btn-sm btn-outline-primary" onclick="toggleAllShows(true)">Select All</button>
                    <button type="button" class="btn btn-sm btn-outline-secondary" onclick="toggleAllShows(false)">Select None</button>
                    <span class="ms-3 text-muted">Select shows to import:</span>
                </div>
                <div class="list-group">
            `;
            
            shows.forEach((show, index) => {
                const badgeClass = show.exists ? 'bg-warning' : 'bg-success';
                const badgeText = show.exists ? 'EXISTS' : 'NEW';
                const showId = `show_${index}`;
                
                html += `
                    <div class="list-group-item">
                        <div class="d-flex align-items-start">
                            <div class="form-check me-3">
                                <input class="form-check-input show-checkbox" type="checkbox" 
                                       id="${showId}" value="${index}" ${!show.exists ? 'checked' : ''}>
                                <label class="form-check-label" for="${showId}"></label>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${show.name}</h6>
                                <p class="mb-1">${show.schedule}</p>
                                ${show.host ? `<small class="text-muted">Host: ${show.host}</small>` : ''}
                            </div>
                            <span class="badge ${badgeClass}">${badgeText}</span>
                        </div>
                    </div>
                `;
            });
            
            html += '</div>';
            container.innerHTML = html;
            
            // Store shows data for later use
            window.previewShows = shows;
        }
        
        function displayResults(results) {
            const container = document.getElementById('importResults');
            
            container.innerHTML = `
                <div class="row text-center">
                    <div class="col-3">
                        <div class="fw-bold text-primary">${results.shows_found}</div>
                        <small class="text-muted">Found</small>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold text-success">${results.shows_created}</div>
                        <small class="text-muted">Created</small>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold text-info">${results.shows_updated}</div>
                        <small class="text-muted">Updated</small>
                    </div>
                    <div class="col-3">
                        <div class="fw-bold text-secondary">${results.shows_skipped}</div>
                        <small class="text-muted">Skipped</small>
                    </div>
                </div>
                ${results.errors > 0 ? `<div class="alert alert-warning mt-3">Some errors occurred during import.</div>` : ''}
                <div class="alert alert-success mt-3">Import completed! The page will refresh shortly.</div>
            `;
        }
        
        // Function to get fresh CSRF token from API (maintains session)
        async function getCSRFToken() {
            try {
                const response = await fetch('/api/get-csrf-token.php', {
                    credentials: 'same-origin'
                });
                const data = await response.json();
                console.log('CSRF token response:', data);
                return data.csrf_token;
            } catch (error) {
                console.error('Failed to get CSRF token:', error);
                return null;
            }
        }
        
        // Handle test recording buttons
        document.querySelectorAll('.test-recording').forEach(btn => {
            btn.addEventListener('click', async function() {
                const stationId = this.dataset.stationId;
                const stationName = this.dataset.stationName;
                const streamUrl = this.dataset.streamUrl;
                
                if (!confirm(`Start 10-second test recording for ${stationName}?`)) {
                    return;
                }
                
                // Disable button and show loading
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Recording...';
                
                try {
                    // Get fresh CSRF token from API
                    const csrfToken = await getCSRFToken();
                    console.log('CSRF Token fetched:', csrfToken);
                    if (!csrfToken) {
                        throw new Error('Failed to get CSRF token');
                    }
                    
                    const requestBody = new URLSearchParams({
                        action: 'test_recording',
                        station_id: stationId,
                        csrf_token: csrfToken
                    });
                    console.log('Request body:', requestBody.toString());
                    
                    const response = await fetch('/api/test-recording.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        credentials: 'same-origin',
                        body: requestBody
                    });
                    
                    console.log('Response status:', response.status);
                    const responseText = await response.text();
                    console.log('Response text:', responseText);
                    
                    let result;
                    try {
                        result = JSON.parse(responseText);
                    } catch (e) {
                        console.error('Failed to parse JSON response:', responseText);
                        throw new Error('Invalid JSON response from server');
                    }
                    
                    if (result.success) {
                        // Show progress indication with countdown
                        showRecordingProgress(result.filename, result.duration, stationName);
                        
                        // Immediate refresh to show the recording started
                        setTimeout(() => {
                            loadTestRecordings();
                        }, 2000); // Quick refresh after 2 seconds
                        
                        // Auto-refresh test recordings after completion
                        setTimeout(() => {
                            loadTestRecordings();
                        }, (result.duration + 5) * 1000); // Add 5 seconds buffer
                    } else {
                        alert(`Test recording failed: ${result.error}`);
                    }
                    
                } catch (error) {
                    console.error('Test recording error:', error);
                    alert('Network error occurred during test recording');
                } finally {
                    // Re-enable button
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-play-circle"></i> Test (10s)';
                }
            });
        });
        
        // Handle record now buttons
        document.querySelectorAll('.record-now').forEach(btn => {
            btn.addEventListener('click', async function() {
                const stationId = this.dataset.stationId;
                const stationName = this.dataset.stationName;
                const streamUrl = this.dataset.streamUrl;
                
                if (!confirm(`Start 1-hour on-demand recording for ${stationName}?\\n\\nThis will create a new recording that will run for 1 hour.`)) {
                    return;
                }
                
                // Disable button and show loading
                this.disabled = true;
                this.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Starting...';
                
                try {
                    // Get fresh CSRF token from API
                    const csrfToken = await getCSRFToken();
                    if (!csrfToken) {
                        throw new Error('Failed to get CSRF token');
                    }
                    
                    const response = await fetch('/api/test-recording.php', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/x-www-form-urlencoded',
                        },
                        body: new URLSearchParams({
                            action: 'record_now',
                            station_id: stationId,
                            csrf_token: csrfToken
                        })
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        alert(`On-demand recording started!\\n\\nFile: ${result.filename}\\nDuration: 1 hour\\nEstimated completion: ${result.estimated_completion}\\n\\nYou can find this recording in the Shows section under "On-Demand Recordings".`);
                    } else {
                        alert(`On-demand recording failed: ${result.error}`);
                    }
                    
                } catch (error) {
                    console.error('On-demand recording error:', error);
                    alert('Network error occurred during on-demand recording');
                } finally {
                    // Re-enable button
                    this.disabled = false;
                    this.innerHTML = '<i class="fas fa-record-vinyl"></i> Record Now (1h)';
                }
            });
        });
        
        // Load and display test recordings
        async function loadTestRecordings() {
            const container = document.getElementById('testRecordingsContainer');
            console.log('Loading test recordings...', new Date().toLocaleTimeString());
            container.innerHTML = `
                <div class="text-center">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Loading...</span>
                    </div>
                    <p>Loading test recordings...</p>
                </div>
            `;
            
            try {
                // Add cache-busting parameter to avoid stale responses
                const response = await fetch('/api/test-recordings.php?_t=' + Date.now(), {
                    credentials: 'same-origin',
                    cache: 'no-cache'
                });
                
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }
                
                const result = await response.json();
                console.log('Test recordings result:', result, 'at', new Date().toLocaleTimeString());
                
                if (result.success && result.recordings.length > 0) {
                    let html = '<div class="row">';
                    
                    result.recordings.forEach(recording => {
                        html += `
                            <div class="col-md-6 col-lg-4 mb-3">
                                <div class="card">
                                    <div class="card-body">
                                        <h6 class="card-title">
                                            <i class="fas fa-microphone"></i> ${recording.call_letters || `Station ${recording.station_id}`} Test
                                        </h6>
                                        <p class="card-text">
                                            <small class="text-muted">
                                                ${recording.readable_date}<br>
                                                Size: ${recording.size_human}
                                            </small>
                                        </p>
                                        <div class="audio-controls">
                                            <audio controls class="w-100 mb-2">
                                                <source src="${recording.download_url}" type="audio/mpeg">
                                                Your browser does not support the audio element.
                                            </audio>
                                            <div class="btn-group w-100">
                                                <a href="${recording.download_url}" class="btn btn-outline-primary btn-sm" download>
                                                    <i class="fas fa-download"></i> Download
                                                </a>
                                                <button class="btn btn-outline-danger btn-sm delete-test-recording" 
                                                        data-filename="${recording.filename}">
                                                    <i class="fas fa-trash"></i> Delete
                                                </button>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        `;
                    });
                    
                    html += '</div>';
                    container.innerHTML = html;
                    
                    // Add delete event listeners
                    document.querySelectorAll('.delete-test-recording').forEach(btn => {
                        btn.addEventListener('click', function() {
                            deleteTestRecording(this.dataset.filename);
                        });
                    });
                    
                } else {
                    container.innerHTML = `
                        <div class="alert alert-info">
                            <i class="fas fa-info-circle"></i> No test recordings found. 
                            Use the "Test (10s)" button next to any station to create one.
                        </div>
                    `;
                }
            } catch (error) {
                console.error('Error loading test recordings:', error);
                container.innerHTML = `
                    <div class="alert alert-danger">
                        <i class="fas fa-exclamation-triangle"></i> Failed to load test recordings.
                    </div>
                `;
            }
        }
        
        // Delete test recording
        async function deleteTestRecording(filename) {
            if (!confirm(`Delete test recording "${filename}"?`)) {
                return;
            }
            
            try {
                const csrfToken = await getCSRFToken();
                const response = await fetch('/api/test-recordings.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/x-www-form-urlencoded',
                    },
                    credentials: 'same-origin',
                    body: new URLSearchParams({
                        action: 'delete',
                        filename: filename,
                        csrf_token: csrfToken
                    })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    loadTestRecordings(); // Refresh the list
                } else {
                    alert('Failed to delete recording: ' + result.error);
                }
            } catch (error) {
                console.error('Error deleting test recording:', error);
                alert('Failed to delete recording');
            }
        }
        
        // Show recording progress with countdown
        function showRecordingProgress(filename, duration, stationName) {
            // Create progress modal
            const modalHtml = `
                <div class="modal fade" id="recordingProgressModal" tabindex="-1" data-bs-backdrop="static" data-bs-keyboard="false">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-record-vinyl text-danger"></i> Recording in Progress
                                </h5>
                            </div>
                            <div class="modal-body text-center">
                                <h6>${stationName}</h6>
                                <p class="text-muted mb-3">Recording test sample...</p>
                                
                                <div class="progress mb-3" style="height: 20px;">
                                    <div class="progress-bar progress-bar-striped progress-bar-animated bg-danger" 
                                         id="recordingProgressBar" 
                                         role="progressbar" 
                                         style="width: 0%" 
                                         aria-valuenow="0" 
                                         aria-valuemin="0" 
                                         aria-valuemax="100">
                                    </div>
                                </div>
                                
                                <div class="d-flex justify-content-between">
                                    <span class="badge bg-secondary">
                                        <i class="fas fa-clock"></i> 
                                        <span id="recordingCountdown">${duration}</span>s remaining
                                    </span>
                                    <span class="badge bg-info">
                                        <i class="fas fa-file-audio"></i> ${filename}
                                    </span>
                                </div>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i> 
                                        Recording will appear in the Test Recordings section when complete
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove any existing modal
            const existingModal = document.getElementById('recordingProgressModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById('recordingProgressModal'));
            modal.show();
            
            // Start countdown
            let remaining = duration;
            const countdownElement = document.getElementById('recordingCountdown');
            const progressBar = document.getElementById('recordingProgressBar');
            
            const interval = setInterval(() => {
                remaining--;
                const progress = ((duration - remaining) / duration) * 100;
                
                countdownElement.textContent = remaining;
                progressBar.style.width = progress + '%';
                progressBar.setAttribute('aria-valuenow', progress);
                
                if (remaining <= 0) {
                    clearInterval(interval);
                    
                    // Update to completion state
                    countdownElement.textContent = '0';
                    progressBar.style.width = '100%';
                    progressBar.className = 'progress-bar bg-success';
                    progressBar.textContent = 'Complete!';
                    
                    // Hide modal after 2 seconds
                    setTimeout(() => {
                        modal.hide();
                        // Remove modal from DOM after hiding
                        setTimeout(() => {
                            document.getElementById('recordingProgressModal')?.remove();
                        }, 300);
                    }, 2000);
                }
            }, 1000);
        }
        
        // Load test recordings when page loads
        document.addEventListener('DOMContentLoaded', function() {
            loadTestRecordings();
        });
    </script>

    <!-- Footer -->
    <footer class="bg-light mt-5 py-3">
        <div class="container">
            <div class="row">
                <div class="col text-center text-muted">
                    <small>
                        RadioGrab - TiVo for Radio | 
                        Version: <?php 
                            $version_file = dirname(dirname(__DIR__)) . '/VERSION';
                            if (file_exists($version_file)) {
                                echo trim(file_get_contents($version_file));
                            } else {
                                echo 'Unknown';
                            }
                        ?>
                    </small>
                </div>
            </div>
        </div>
    </footer>
</body>
</html>