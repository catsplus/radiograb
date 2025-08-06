<?php
/**
 * RadioGrab - Stations Management
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Set page variables for shared template
$page_title = 'Stations';
$active_nav = 'stations';

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

// Set additional CSS for stations page
$additional_css = '<link href="/assets/css/on-air.css" rel="stylesheet">';

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
                <h1><i class="fas fa-broadcast-tower"></i> Radio Stations</h1>
                <p class="text-muted">Manage your radio stations and their streaming information</p>
            </div>
            <div class="col-auto">
                <a href="/add-station.php" class="btn btn-primary">
                    <i class="fas fa-plus"></i> Add Station
                </a>
            </div>
        </div>

        <!-- Stations Needing Attention Alert (Less Intrusive) -->
        <div id="stationsNeedingAttention" style="display: none;" class="mb-4">
            <div class="alert alert-info alert-dismissible">
                <div class="d-flex align-items-center justify-content-between">
                    <div>
                        <i class="fas fa-info-circle me-2"></i>
                        <strong>Station Status Update Available</strong>
                        <span id="stationAlertSummary"></span>
                    </div>
                    <div>
                        <button class="btn btn-sm btn-outline-info me-2" onclick="toggleStationDetails()">
                            <i class="fas fa-eye"></i> <span id="toggleDetailsText">Show Details</span>
                        </button>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                </div>
                <div id="stationAlertDetails" class="mt-3" style="display: none;">
                    <hr>
                    <p class="mb-2"><small class="text-muted"><i class="fas fa-lightbulb"></i> <strong>What does "Verify Station" do?</strong><br>
                    Checks the station's website for updated show schedules and verifies the stream is working properly.</small></p>
                </div>
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
                        <div class="card h-100 station-card" data-station-call="<?= h($station['call_letters']) ?>">
                            <div class="card-body">
                                <div class="d-flex align-items-start mb-3">
                                    <img src="<?= h(getStationLogo($station)) ?>" 
                                         alt="<?= h($station['name']) ?>" 
                                         class="station-logo station-logo-md me-3"
                                         onerror="this.src='/assets/images/default-station-logo.png'"
                                         loading="lazy">
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-1"><?= h($station['name']) ?></h5>
                                        <small class="text-muted"><?= h($station['website_url']) ?></small>
                                        
                                        <!-- Social Media Icons -->
                                        <div class="social-media-icons mt-2">
                                            <?= generateSocialMediaIcons($station) ?>
                                        </div>
                                    </div>
                                    <div class="d-flex align-items-center">
                                        <span class="badge <?= $station['status'] === 'active' ? 'status-active' : 'status-inactive' ?> me-2">
                                            <?= h($station['status']) ?>
                                        </span>
                                        <button type="button" 
                                                class="btn btn-sm btn-outline-danger delete-confirm"
                                                data-bs-toggle="modal" 
                                                data-bs-target="#deleteModal"
                                                data-station-id="<?= $station['id'] ?>"
                                                data-station-name="<?= h($station['name']) ?>"
                                                data-item="station"
                                                title="Delete station">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
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
                                                <button class="btn btn-sm btn-link p-0 ms-2 play-test-recording"
                                                        data-station-id="<?= $station['id'] ?>"
                                                        data-station-call="<?= h($station['call_letters']) ?>"
                                                        title="Play latest test recording"
                                                        style="display: none;">
                                                    <i class="fas fa-play text-primary"></i>
                                                </button>
                                                <?php if ($station['stream_url']): ?>
                                                    <button class="btn btn-sm btn-outline-success ms-2 test-stream"
                                                            data-station-id="<?= $station['id'] ?>"
                                                            data-station-name="<?= h($station['name']) ?>"
                                                            data-stream-url="<?= h($station['stream_url']) ?>"
                                                            title="Test stream connection (30-sec sample)">
                                                        <i class="fas fa-play"></i> Test
                                                    </button>
                                                <?php endif; ?>
                                            </small>
                                        <?php else: ?>
                                            <i class="fas fa-question-circle text-muted"></i>
                                            <small class="text-muted">
                                                Never tested
                                                <?php if ($station['stream_url']): ?>
                                                    <button class="btn btn-sm btn-outline-success ms-2 test-stream"
                                                            data-station-id="<?= $station['id'] ?>"
                                                            data-station-name="<?= h($station['name']) ?>"
                                                            data-stream-url="<?= h($station['stream_url']) ?>"
                                                            title="Test stream now">
                                                        <i class="fas fa-sync"></i> Re-check
                                                    </button>
                                                <?php endif; ?>
                                            </small>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Calendar Verification Status -->
                                    <div class="mb-2">
                                        <?php if (!$station['last_tested']): ?>
                                            <i class="fas fa-calendar-times text-muted"></i>
                                            <small class="text-muted">
                                                Last Verified: Never
                                        <?php elseif ($station['last_test_result'] === 'success' && $station['show_count'] > 0): ?>
                                            <i class="fas fa-calendar-check text-success"></i>
                                            <small class="text-success">
                                                Calendar found: <?= $station['show_count'] ?> shows imported (<?= timeAgo($station['last_tested']) ?>)
                                        <?php elseif ($station['last_test_result'] === 'failed'): ?>
                                            <i class="fas fa-calendar-times text-danger"></i>
                                            <small class="text-danger">
                                                Calendar verification failed: <?= timeAgo($station['last_tested']) ?>
                                                <?php if ($station['last_test_error']): ?>
                                                    <span title="<?= h($station['last_test_error']) ?>">
                                                        <i class="fas fa-info-circle"></i>
                                                    </span>
                                                <?php endif; ?>
                                        <?php else: ?>
                                            <i class="fas fa-calendar-exclamation text-warning"></i>
                                            <small class="text-warning">
                                                Calendar verified but no shows found (<?= timeAgo($station['last_tested']) ?>)
                                        <?php endif; ?>
                                            <button class="btn btn-sm btn-outline-primary ms-2 verify-calendar"
                                                    data-station-id="<?= $station['id'] ?>"
                                                    data-station-name="<?= h($station['name']) ?>"
                                                    title="Check for updated show schedules">
                                                <i class="fas fa-calendar-check"></i> Update
                                            </button>
                                        </small>
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
                                                class="btn btn-outline-primary btn-sm verify-station"
                                                data-station-id="<?= $station['id'] ?>"
                                                data-station-name="<?= h($station['name']) ?>"
                                                title="Complete station verification: test stream + check schedule">
                                            <i class="fas fa-shield-check"></i> Verify
                                        </button>
                                    </div>
                                    
                                    <!-- Recording Actions -->
                                    <?php if ($station['stream_url']): ?>
                                    <div class="d-grid">
                                        <button type="button" 
                                                class="btn btn-danger btn-sm record-now"
                                                data-station-id="<?= $station['id'] ?>"
                                                data-station-name="<?= h($station['name']) ?>"
                                                data-stream-url="<?= h($station['stream_url']) ?>">
                                            <i class="fas fa-record-vinyl"></i> Record Now (1h)
                                        </button>
                                    </div>
                                    <?php endif; ?>
                                    
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
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

<?php
// Set additional JavaScript for stations page functionality
$additional_js = '
<script src="/assets/js/on-air-status.js"></script>
<script>
        let currentStationId = null;
        
        // Handle delete modal
        document.addEventListener("DOMContentLoaded", function() {
            const deleteModal = document.getElementById("deleteModal");
            deleteModal.addEventListener("show.bs.modal", function(event) {
                const button = event.relatedTarget;
                const stationId = button.getAttribute("data-station-id");
                const stationName = button.getAttribute("data-station-name");
                
                document.getElementById("deleteStationId").value = stationId;
                document.getElementById("stationName").textContent = stationName;
            });
            
            // Handle consolidated verify station buttons
            document.querySelectorAll(".verify-station").forEach(btn => {
                btn.addEventListener("click", async function() {
                    const stationId = this.dataset.stationId;
                    const stationName = this.dataset.stationName;
                    
                    if (!confirm(`Verify station "${stationName}" now?\n\nThis will test the stream and check for updated show schedules.`)) {
                        return;
                    }
                    
                    await performStationVerification(stationId, stationName, this);
                });
            });
            
            // Handle import schedule buttons (legacy - to be removed after testing)
            document.querySelectorAll(".import-schedule").forEach(btn => {
                btn.addEventListener("click", function() {
                    currentStationId = this.dataset.stationId;
                    document.getElementById("importStationName").textContent = this.dataset.stationName;
                    
                    // Reset modal state
                    document.getElementById("importStep1").style.display = "block";
                    document.getElementById("importStep2").style.display = "none";
                    document.getElementById("importStep3").style.display = "none";
                    document.getElementById("importStep4").style.display = "none";
                    document.getElementById("previewBtn").style.display = "inline-block";
                    document.getElementById("importBtn").style.display = "none";
                    
                    // Show modal
                    new bootstrap.Modal(document.getElementById("importModal")).show();
                });
            });
            
            // Handle preview button
            document.getElementById("previewBtn").addEventListener("click", function() {
                previewSchedule();
            });
            
            // Handle import button
            document.getElementById("importBtn").addEventListener("click", function() {
                importSchedule();
            });
        });
        
        async function previewSchedule() {
            document.getElementById("importStep1").style.display = "none";
            document.getElementById("importStep2").style.display = "block";
            
            try {
                const response = await fetch("/api/import-schedule.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        action: "preview",
                        station_id: currentStationId,
                        csrf_token: "<?= generateCSRFToken() ?>"
                    })
                });
                
                const result = await response.json();
                
                document.getElementById("importStep2").style.display = "none";
                
                if (result.success) {
                    displayPreview(result.shows);
                    document.getElementById("importStep3").style.display = "block";
                    document.getElementById("previewBtn").style.display = "none";
                    document.getElementById("importBtn").style.display = "inline-block";
                } else {
                    alert("Error: " + result.error);
                    document.getElementById("importStep1").style.display = "block";
                }
            } catch (error) {
                document.getElementById("importStep2").style.display = "none";
                document.getElementById("importStep1").style.display = "block";
                alert("Network error occurred");
            }
        }
        
        function toggleAllShows(select) {
            const checkboxes = document.querySelectorAll(".show-checkbox");
            checkboxes.forEach(checkbox => {
                checkbox.checked = select;
            });
        }
        
        function getSelectedShows() {
            const selectedShows = [];
            const checkboxes = document.querySelectorAll(".show-checkbox:checked");
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
                alert("Please select at least one show to import.");
                return;
            }
            
            document.getElementById("importStep3").style.display = "none";
            document.getElementById("importStep2").style.display = "block";
            document.querySelector("#importStep2 p").textContent = `Importing ${selectedShows.length} selected shows...`;
            
            try {
                const response = await fetch("/api/import-schedule.php", {
                    method: "POST",
                    headers: {
                        "Content-Type": "application/json",
                    },
                    body: JSON.stringify({
                        action: "import",
                        station_id: currentStationId,
                        auto_create: document.getElementById("autoCreate").checked,
                        update_existing: document.getElementById("updateExisting").checked,
                        selected_shows: selectedShows,
                        csrf_token: "<?= generateCSRFToken() ?>"
                    })
                });
                
                const result = await response.json();
                
                document.getElementById("importStep2").style.display = "none";
                
                if (result.success) {
                    displayResults(result.results);
                    document.getElementById("importStep4").style.display = "block";
                    document.getElementById("importBtn").style.display = "none";
                    
                    // Refresh page after successful import
                    setTimeout(() => {
                        window.location.reload();
                    }, 3000);
                } else {
                    alert("Error: " + result.error);
                    document.getElementById("importStep3").style.display = "block";
                }
            } catch (error) {
                document.getElementById("importStep2").style.display = "none";
                document.getElementById("importStep3").style.display = "block";
                alert("Network error occurred");
            }
        }
        
        function displayPreview(shows) {
            const container = document.getElementById("previewShows");
            
            if (shows.length === 0) {
                container.innerHTML = "<p class=\"text-muted\">No shows found in the station schedule.</p>";
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
                const badgeClass = show.exists ? "bg-warning" : "bg-success";
                const badgeText = show.exists ? "EXISTS" : "NEW";
                const showId = `show_${index}`;
                
                html += `
                    <div class="list-group-item">
                        <div class="d-flex align-items-start">
                            <div class="form-check me-3">
                                <input class="form-check-input show-checkbox" type="checkbox" 
                                       id="${showId}" value="${index}" ${!show.exists ? "checked" : ""}>
                                <label class="form-check-label" for="${showId}"></label>
                            </div>
                            <div class="flex-grow-1">
                                <h6 class="mb-1">${show.name}</h6>
                                <p class="mb-1">${show.schedule}</p>
                                ${show.host ? `<small class="text-muted">Host: ${show.host}</small>` : ""}
                            </div>
                            <span class="badge ${badgeClass}">${badgeText}</span>
                        </div>
                    </div>
                `;
            });
            
            html += "</div>";
            container.innerHTML = html;
            
            // Store shows data for later use
            window.previewShows = shows;
        }
        
        function displayResults(results) {
            const container = document.getElementById("importResults");
            
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
                ${results.errors > 0 ? `<div class="alert alert-warning mt-3">Some errors occurred during import.</div>` : ""}
                <div class="alert alert-success mt-3">Import completed! The page will refresh shortly.</div>
            `;
        }
        
        // Function to get fresh CSRF token from API (maintains session)
        async function getCSRFToken() {
            try {
                const response = await fetch("/api/get-csrf-token.php", {
                    credentials: "same-origin"
                });
                const data = await response.json();
                console.log("CSRF token response:", data);
                return data.csrf_token;
            } catch (error) {
                console.error("Failed to get CSRF token:", error);
                return null;
            }
        }
        
        // Handle test stream buttons (Re-check buttons)
        document.querySelectorAll(".test-stream").forEach(btn => {
            btn.addEventListener("click", async function() {
                const stationId = this.dataset.stationId;
                const stationName = this.dataset.stationName;
                const streamUrl = this.dataset.streamUrl;
                
                if (!confirm(`Test stream for ${stationName}?`)) {
                    return;
                }
                
                // Disable button and show loading
                this.disabled = true;
                this.innerHTML = "<i class=\"fas fa-spinner fa-spin\"></i> Testing...";
                
                try {
                    // Get fresh CSRF token from API
                    const csrfToken = await getCSRFToken();
                    console.log("CSRF Token fetched:", csrfToken);
                    if (!csrfToken) {
                        throw new Error("Failed to get CSRF token");
                    }
                    
                    const requestBody = new URLSearchParams({
                        action: "test_recording",
                        station_id: stationId,
                        csrf_token: csrfToken
                    });
                    console.log("Request body:", requestBody.toString());
                    
                    const response = await fetch("/api/test-recording.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                        },
                        credentials: "same-origin",
                        body: requestBody
                    });
                    
                    console.log("Response status:", response.status);
                    const responseText = await response.text();
                    console.log("Response text:", responseText);
                    
                    let result;
                    try {
                        result = JSON.parse(responseText);
                    } catch (e) {
                        console.error("Failed to parse JSON response:", responseText);
                        throw new Error("Invalid JSON response from server");
                    }
                    
                    if (result.success) {
                        // Show progress indication with countdown
                        showRecordingProgress(result.filename, result.duration, stationName);
                        
                    } else {
                        alert(`Test recording failed: ${result.error}`);
                    }
                    
                } catch (error) {
                    console.error("Test recording error:", error);
                    alert("Network error occurred during test recording");
                } finally {
                    // Re-enable button
                    this.disabled = false;
                    this.innerHTML = "<i class=\"fas fa-play\"></i> Test";
                }
            });
        });
        
        // Handle record now buttons
        document.querySelectorAll(".record-now").forEach(btn => {
            btn.addEventListener("click", async function() {
                const stationId = this.dataset.stationId;
                const stationName = this.dataset.stationName;
                const streamUrl = this.dataset.streamUrl;
                
                if (!confirm(`Start 1-hour on-demand recording for ${stationName}?\\n\\nThis will create a new recording that will run for 1 hour.`)) {
                    return;
                }
                
                // Disable button and show loading
                this.disabled = true;
                this.innerHTML = "<i class=\"fas fa-spinner fa-spin\"></i> Starting...";
                
                try {
                    // Get fresh CSRF token from API
                    const csrfToken = await getCSRFToken();
                    if (!csrfToken) {
                        throw new Error("Failed to get CSRF token");
                    }
                    
                    const response = await fetch("/api/test-recording.php", {
                        method: "POST",
                        headers: {
                            "Content-Type": "application/x-www-form-urlencoded",
                        },
                        body: new URLSearchParams({
                            action: "record_now",
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
                    console.error("On-demand recording error:", error);
                    alert("Network error occurred during on-demand recording");
                } finally {
                    // Re-enable button
                    this.disabled = false;
                    this.innerHTML = "<i class=\"fas fa-record-vinyl\"></i> Record Now (1h)";
                }
            });
        });
        
        
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
                                        Test recording will be saved to temporary storage
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove any existing modal
            const existingModal = document.getElementById("recordingProgressModal");
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to page
            document.body.insertAdjacentHTML("beforeend", modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById("recordingProgressModal"));
            modal.show();
            
            // Start countdown
            let remaining = duration;
            const countdownElement = document.getElementById("recordingCountdown");
            const progressBar = document.getElementById("recordingProgressBar");
            
            const interval = setInterval(() => {
                remaining--;
                const progress = ((duration - remaining) / duration) * 100;
                
                countdownElement.textContent = remaining;
                progressBar.style.width = progress + "%";
                progressBar.setAttribute("aria-valuenow", progress);
                
                if (remaining <= 0) {
                    clearInterval(interval);
                    
                    // Update to completion state
                    countdownElement.textContent = "0";
                    progressBar.style.width = "100%";
                    progressBar.className = "progress-bar bg-success";
                    progressBar.textContent = "Complete!";
                    
                    // Hide modal after 2 seconds
                    setTimeout(() => {
                        modal.hide();
                        // Remove modal from DOM after hiding
                        setTimeout(() => {
                            document.getElementById("recordingProgressModal")?.remove();
                        }, 300);
                    }, 2000);
                }
            }, 1000);
        }
        
        // Handle calendar verification buttons
        document.querySelectorAll(".verify-calendar").forEach(btn => {
            btn.addEventListener("click", async function() {
                const stationId = this.dataset.stationId;
                const stationName = this.dataset.stationName;
                
                if (!confirm(`Re-check calendar schedule for ${stationName}?`)) {
                    return;
                }
                
                // Disable button and show loading
                this.disabled = true;
                this.innerHTML = "<i class=\"fas fa-spinner fa-spin\"></i> Checking...";
                
                try {
                    const csrfToken = await getCSRFToken();
                    if (!csrfToken) {
                        throw new Error("Failed to get CSRF token");
                    }
                    
                    const response = await fetch(`/api/schedule-verification.php?action=verify_station&station_id=${stationId}`, {
                        method: "GET",
                        credentials: "same-origin"
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        alert(`Calendar verification completed for ${stationName}!\n\nResult: ${result.message || "Verification completed successfully"}`);
                        // Refresh page to show updated verification status
                        setTimeout(() => {
                            window.location.reload();
                        }, 1000);
                    } else {
                        alert(`Calendar verification failed: ${result.error}`);
                    }
                    
                } catch (error) {
                    console.error("Calendar verification error:", error);
                    alert("Network error occurred during calendar verification");
                } finally {
                    // Re-enable button
                    this.disabled = false;
                    this.innerHTML = "<i class=\"fas fa-calendar-check\"></i> Update";
                }
            });
        });
        
        // Check for test recordings and show play icons
        async function checkForTestRecordings() {
            try {
                const response = await fetch("/api/test-recordings.php?action=list");
                const result = await response.json();
                
                if (result.success && result.recordings.length > 0) {
                    // Group recordings by station ID
                    const recordingsByStation = {};
                    result.recordings.forEach(recording => {
                        if (!recordingsByStation[recording.station_id]) {
                            recordingsByStation[recording.station_id] = [];
                        }
                        recordingsByStation[recording.station_id].push(recording);
                    });
                    
                    // Show play icons for stations with recordings
                    Object.keys(recordingsByStation).forEach(stationId => {
                        const playButton = document.querySelector(`.play-test-recording[data-station-id="${stationId}"]`);
                        if (playButton) {
                            // Get the latest recording for this station
                            const latestRecording = recordingsByStation[stationId][0]; // Already sorted newest first
                            playButton.dataset.filename = latestRecording.filename;
                            playButton.dataset.url = latestRecording.url;
                            playButton.style.display = "inline-block";
                        }
                    });
                }
            } catch (error) {
                console.error("Failed to check for test recordings:", error);
            }
        }
        
        // Handle play test recording buttons
        document.querySelectorAll(".play-test-recording").forEach(btn => {
            btn.addEventListener("click", function() {
                const filename = this.dataset.filename;
                const url = this.dataset.url;
                const stationCall = this.dataset.stationCall;
                
                if (!filename || !url) {
                    alert("No test recording available");
                    return;
                }
                
                // Create and show audio player modal
                showAudioPlayerModal(filename, url, stationCall);
            });
        });
        
        // Show audio player modal
        function showAudioPlayerModal(filename, url, stationCall) {
            const modalHtml = `
                <div class="modal fade" id="audioPlayerModal" tabindex="-1">
                    <div class="modal-dialog modal-dialog-centered">
                        <div class="modal-content">
                            <div class="modal-header">
                                <h5 class="modal-title">
                                    <i class="fas fa-play text-primary"></i> Test Recording - ${stationCall}
                                </h5>
                                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                            </div>
                            <div class="modal-body text-center">
                                <h6 class="mb-3">${filename}</h6>
                                
                                <audio controls class="w-100 mb-3" preload="metadata">
                                    <source src="${url}" type="audio/mpeg">
                                    Your browser does not support the audio element.
                                </audio>
                                
                                <div class="d-flex justify-content-center gap-2">
                                    <a href="/api/test-recordings.php?action=download&file=${encodeURIComponent(filename.split("/").pop())}" 
                                       class="btn btn-sm btn-outline-primary">
                                        <i class="fas fa-download"></i> Download
                                    </a>
                                    <button type="button" class="btn btn-sm btn-outline-secondary" data-bs-dismiss="modal">
                                        <i class="fas fa-times"></i> Close
                                    </button>
                                </div>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i> 
                                        This is a 30-second test recording
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove any existing modal
            const existingModal = document.getElementById("audioPlayerModal");
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to page
            document.body.insertAdjacentHTML("beforeend", modalHtml);
            
            // Show modal
            const modal = new bootstrap.Modal(document.getElementById("audioPlayerModal"));
            modal.show();
            
            // Clean up modal when hidden
            document.getElementById("audioPlayerModal").addEventListener("hidden.bs.modal", function() {
                this.remove();
            });
        }
        
        // Initialize test recording checks when page loads
        document.addEventListener("DOMContentLoaded", function() {
            checkForTestRecordings();
            loadStationVerificationStatus();
        });
        
        // Load station verification status
        async function loadStationVerificationStatus() {
            try {
                const response = await fetch("/api/schedule-verification.php?action=get_verification_status");
                const result = await response.json();
                
                if (result.success) {
                    displayStationVerificationStatus(result.stations, result.summary);
                }
            } catch (error) {
                console.error("Failed to load station verification status:", error);
            }
        }
        
        // Display station verification status (less intrusive)
        function displayStationVerificationStatus(stations, summary) {
            // Only show alert for truly problematic stations (never checked or overdue)
            // Remove "due_soon" to be less intrusive
            const needAttention = stations.filter(station => 
                station.verification_status === "never" || 
                station.verification_status === "overdue"
            );
            
            if (needAttention.length === 0) {
                document.getElementById("stationsNeedingAttention").style.display = "none";
                return;
            }
            
            // Show the alert
            document.getElementById("stationsNeedingAttention").style.display = "block";
            
            // Update summary text (more user-friendly)
            const summaryElement = document.getElementById("stationAlertSummary");
            let summaryText = "";
            if (summary.never > 0) summaryText += ` - ${summary.never} station${summary.never > 1 ? 's' : ''} need initial setup`;
            if (summary.overdue > 0) summaryText += ` - ${summary.overdue} station${summary.overdue > 1 ? 's' : ''} need updates`;
            summaryElement.textContent = summaryText;
            
            // Build details HTML
            let detailsHtml = "<div class=\"row\">";
            needAttention.forEach(station => {
                const statusIcon = station.verification_status === "never" ? "fas fa-question-circle text-muted" :
                                 station.verification_status === "overdue" ? "fas fa-exclamation-triangle text-danger" :
                                 "fas fa-clock text-warning";
                
                const statusText = station.verification_status === "never" ? "Never Checked" :
                                 station.verification_status === "overdue" ? `Overdue (${station.days_since_check} days ago)` :
                                 `Due Soon (${station.days_since_check} days ago)`;
                
                detailsHtml += `
                    <div class="col-md-6 col-lg-4 mb-2">
                        <div class="d-flex align-items-center justify-content-between">
                            <div>
                                <i class="${statusIcon}"></i>
                                <span class="fw-bold">${station.name}</span>
                                <br>
                                <small class="text-muted">${statusText}</small>
                            </div>
                            <button class="btn btn-sm btn-outline-primary verify-station-now"
                                    data-station-id="${station.id}"
                                    data-station-name="${station.name}"
                                    title="Verify this station now">
                                <i class="fas fa-shield-check"></i> Verify
                            </button>
                        </div>
                    </div>
                `;
            });
            detailsHtml += "</div>";
            
            document.getElementById("stationAlertDetails").innerHTML = detailsHtml;
            document.getElementById("stationAlertDetails").style.display = "block";
            
            // Add event listeners to "Verify" buttons in alert
            document.querySelectorAll(".verify-station-now").forEach(btn => {
                btn.addEventListener("click", async function() {
                    const stationId = this.dataset.stationId;
                    const stationName = this.dataset.stationName;
                    
                    if (!confirm(`Verify station "${stationName}" now?\n\nThis will test the stream and check for updated show schedules.`)) {
                        return;
                    }
                    
                    await performStationVerification(stationId, stationName, this);
                });
            });
        }
        
        // Toggle station details visibility
        function toggleStationDetails() {
            const detailsDiv = document.getElementById("stationAlertDetails");
            const toggleBtn = document.getElementById("toggleDetailsText");
            
            if (detailsDiv.style.display === "none") {
                detailsDiv.style.display = "block";
                toggleBtn.textContent = "Hide Details";
            } else {
                detailsDiv.style.display = "none";
                toggleBtn.textContent = "Show Details";
            }
        }
        
        // Consolidated station verification function
        async function performStationVerification(stationId, stationName, buttonElement) {
            // Disable button and show loading
            buttonElement.disabled = true;
            buttonElement.innerHTML = "<i class=\"fas fa-spinner fa-spin\"></i> Verifying...";
            
            try {
                // Step 1: Test stream
                const csrfToken = await getCSRFToken();
                if (!csrfToken) {
                    throw new Error("Failed to get CSRF token");
                }
                
                let streamResult = null;
                try {
                    const streamResponse = await fetch("/api/test-recording.php", {
                        method: "POST",
                        headers: { "Content-Type": "application/x-www-form-urlencoded" },
                        credentials: "same-origin",
                        body: new URLSearchParams({
                            action: "test_recording",
                            station_id: stationId,
                            csrf_token: csrfToken
                        })
                    });
                    streamResult = await streamResponse.json();
                } catch (e) {
                    console.log("Stream test skipped:", e.message);
                }
                
                // Step 2: Verify schedule
                const scheduleResponse = await fetch(`/api/schedule-verification.php?action=verify_station&station_id=${stationId}`, {
                    method: "GET",
                    credentials: "same-origin"
                });
                const scheduleResult = await scheduleResponse.json();
                
                // Show results
                let message = `Station verification completed for ${stationName}!\n\n`;
                if (streamResult && streamResult.success) {
                    message += `✅ Stream test: Success (${streamResult.filename})\n`;
                } else if (streamResult) {
                    message += `⚠️ Stream test: ${streamResult.error || "Failed"}\n`;
                }
                
                if (scheduleResult.success) {
                    message += `✅ Schedule check: ${scheduleResult.output || "Success"}`;
                } else {
                    message += `⚠️ Schedule check: ${scheduleResult.error}`;
                }
                
                alert(message);
                
                // Refresh page to show updated status
                setTimeout(() => {
                    window.location.reload();
                }, 1000);
                
            } catch (error) {
                console.error("Station verification error:", error);
                alert(`Station verification failed: ${error.message}`);
            } finally {
                // Re-enable button
                buttonElement.disabled = false;
                buttonElement.innerHTML = "<i class=\"fas fa-shield-check\"></i> Verify";
            }
        }
        
        // Refresh station verification status
        async function refreshStationVerification() {
            const refreshBtn = document.querySelector("button[onclick=\"refreshStationVerification()\"]");
            if (refreshBtn) {
                refreshBtn.disabled = true;
                refreshBtn.innerHTML = "<i class=\"fas fa-spinner fa-spin\"></i> Refreshing...";
            }
            
            try {
                await loadStationVerificationStatus();
            } finally {
                if (refreshBtn) {
                    refreshBtn.disabled = false;
                    refreshBtn.innerHTML = "<i class=\"fas fa-sync\"></i> Refresh Status";
                }
            }
        }
        
    </script>';

// Include shared footer
require_once '../includes/footer.php';
?>