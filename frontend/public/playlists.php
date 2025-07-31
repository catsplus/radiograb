<?php
/**
 * RadioGrab - Playlists Management
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Set page variables for shared template
$page_title = 'Playlists';
$active_nav = 'playlists';

// Handle playlist actions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid security token');
        header('Location: /playlists.php');
        exit;
    }
    
    $action = $_POST['action'] ?? '';
    
    if ($action === 'delete' && isset($_POST['show_id'])) {
        try {
            $show_id = (int)$_POST['show_id'];
            // Verify this is a playlist before deleting
            $show = $db->fetchOne("SELECT show_type FROM shows WHERE id = ?", [$show_id]);
            if ($show && $show['show_type'] === 'playlist') {
                $db->delete('shows', 'id = ?', [$show_id]);
                setFlashMessage('success', 'Playlist deleted successfully');
            } else {
                setFlashMessage('danger', 'Invalid playlist');
            }
        } catch (Exception $e) {
            setFlashMessage('danger', 'Failed to delete playlist');
        }
    }
    elseif ($action === 'toggle_status' && isset($_POST['show_id'])) {
        try {
            $show_id = (int)$_POST['show_id'];
            // Verify this is a playlist and get current status
            $show = $db->fetchOne("SELECT active, show_type FROM shows WHERE id = ?", [$show_id]);
            if ($show && $show['show_type'] === 'playlist') {
                $new_status = $show['active'] ? 0 : 1;
                $db->update('shows', ['active' => $new_status], 'id = ?', [$show_id]);
                $status_text = $new_status ? 'activated' : 'deactivated';
                setFlashMessage('success', "Playlist {$status_text} successfully");
            } else {
                setFlashMessage('danger', 'Invalid playlist');
            }
        } catch (Exception $e) {
            setFlashMessage('danger', 'Failed to update playlist status');
        }
    }
    
    header('Location: /playlists.php' . ($_GET ? '?' . http_build_query($_GET) : ''));
    exit;
}

// Get filter parameters
$station_id = isset($_GET['station_id']) ? (int)$_GET['station_id'] : null;
$status = isset($_GET['status']) ? $_GET['status'] : '';
$search = isset($_GET['search']) ? trim($_GET['search']) : '';

// Build query - only show playlists
$where_conditions = ["s.show_type = 'playlist'"];
$params = [];

if ($station_id) {
    $where_conditions[] = "s.station_id = ?";
    $params[] = $station_id;
}

if ($status === 'active') {
    $where_conditions[] = "s.active = 1";
} elseif ($status === 'inactive') {
    $where_conditions[] = "s.active = 0";
}

if ($search) {
    $where_conditions[] = "(s.name LIKE ? OR s.description LIKE ? OR st.name LIKE ?)";
    $search_param = "%$search%";
    $params[] = $search_param;
    $params[] = $search_param;
    $params[] = $search_param;
}

$where_clause = 'WHERE ' . implode(' AND ', $where_conditions);

try {
    // Get playlists with station and track info
    $playlists = $db->fetchAll("
        SELECT s.*, st.name as station_name, st.logo_url, st.call_letters, st.timezone as station_timezone,
               COUNT(r.id) as track_count,
               MAX(r.recorded_at) as latest_upload,
               s.long_description, s.genre, s.image_url, s.website_url,
               s.description_source, s.image_source, s.metadata_updated,
               s.show_type, s.allow_uploads, s.max_file_size_mb
        FROM shows s 
        JOIN stations st ON s.station_id = st.id 
        LEFT JOIN recordings r ON s.id = r.show_id AND r.source_type = 'uploaded'
        $where_clause
        GROUP BY s.id 
        ORDER BY s.name
    ", $params);
    
    // Get stations for filter
    $stations = $db->fetchAll("SELECT id, name FROM stations ORDER BY name");
    
    // Get station info if filtering by station
    $station_info = null;
    if ($station_id) {
        $station_info = $db->fetchOne("SELECT id, name, call_letters FROM stations WHERE id = ?", [$station_id]);
        // Update page title if filtering by station
        if ($station_info) {
            $page_title = $station_info['call_letters'] . ' Playlists';
        }
    }
    
} catch (Exception $e) {
    $error = "Database error: " . $e->getMessage();
    $playlists = [];
    $stations = [];
    $station_info = null;
}

// Set playlist-specific CSS
$additional_css = '
<style>
    .playlist-hero {
        background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
        color: white;
        padding: 3rem 0 2rem;
        margin-bottom: 2rem;
    }
    .playlist-card {
        transition: all 0.3s ease;
        border: none;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    .playlist-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
    }
    .playlist-stats {
        background: rgba(40, 167, 69, 0.1);
        border-radius: 10px;
        padding: 1rem;
    }
    .upload-area {
        border: 2px dashed #28a745;
        border-radius: 10px;
        padding: 2rem;
        text-align: center;
        background: rgba(40, 167, 69, 0.05);
        transition: all 0.3s ease;
    }
    .upload-area.dragover {
        border-color: #20c997;
        background: rgba(32, 201, 151, 0.1);
    }
    .status-badge.active {
        background: linear-gradient(45deg, #28a745, #20c997);
        color: white;
    }
    .status-badge.inactive {
        background: #6c757d;
        color: white;
    }
    .track-list {
        max-height: 400px;
        overflow-y: auto;
    }
    .track-item {
        cursor: move;
        transition: background-color 0.2s;
    }
    .track-item:hover {
        background-color: rgba(40, 167, 69, 0.05);
    }
    .track-item.ui-sortable-helper {
        background-color: rgba(40, 167, 69, 0.1);
        box-shadow: 0 2px 10px rgba(0,0,0,0.2);
    }
    .playlist-actions .btn {
        margin: 0.25rem;
    }
    .progress-upload {
        transition: width 0.3s ease;
    }
    
    /* Green theme for playlists */
    .text-primary { color: #28a745 !important; }
    .btn-primary { 
        background: linear-gradient(45deg, #28a745, #20c997);
        border: none;
        box-shadow: 0 2px 8px rgba(40, 167, 69, 0.3);
    }
    .btn-primary:hover {
        background: linear-gradient(45deg, #218838, #1ea085);
        transform: translateY(-1px);
        box-shadow: 0 4px 15px rgba(40, 167, 69, 0.4);
    }
    .btn-outline-primary {
        color: #28a745;
        border-color: #28a745;
    }
    .btn-outline-primary:hover {
        background: #28a745;
        border-color: #28a745;
    }
    .navbar-brand { color: white !important; }
    .nav-link.active { background: rgba(40, 167, 69, 0.3) !important; }
    
    /* Upload modal styling */
    .modal-header {
        background: linear-gradient(135deg, #28a745, #20c997);
        color: white;
    }
    .modal-header .btn-close {
        filter: invert(1);
    }
</style>';

// Set playlist-specific JavaScript
$additional_js = <<<'JS'
<script>
    // Handle delete modal
    document.addEventListener("DOMContentLoaded", function() {
        const deleteModal = document.getElementById("deleteModal");
        deleteModal.addEventListener("show.bs.modal", function(event) {
            const button = event.relatedTarget;
            const playlistId = button.getAttribute("data-show-id");
            const playlistName = button.getAttribute("data-show-name");
            
            document.getElementById("deletePlaylistId").value = playlistId;
            document.getElementById("playlistName").textContent = playlistName;
        });

        setupUploadHandlers();
        setupPlaylistHandlers();
    });
    
    // Toggle playlist status
    function togglePlaylistStatus(playlistId) {
        const toggle = document.getElementById(`toggle${playlistId}`);
        const active = toggle.checked;
        
        fetch("/api/show-management.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                action: "toggle_active",
                show_id: playlistId,
                active: active,
                csrf_token: "<?= generateCSRFToken() ?>"
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const badge = toggle.parentElement.querySelector(".badge");
                if (active) {
                    badge.className = "badge bg-success";
                    badge.textContent = "Active";
                } else {
                    badge.className = "badge bg-secondary";
                    badge.textContent = "Inactive";
                }
            } else {
                toggle.checked = !active;
                alert("Failed to update playlist status: " + (data.error || "Unknown error"));
            }
        })
        .catch(error => {
            toggle.checked = !active;
            alert("Network error: " + error.message);
        });
    }
    
    // Upload functionality and other JavaScript functions...
    function setupUploadHandlers() {
        document.querySelectorAll(".upload-file-btn").forEach(btn => {
            btn.addEventListener("click", function() {
                const showId = this.dataset.showId;
                const showName = this.dataset.showName;
                const maxSize = this.dataset.maxSize;
                
                document.getElementById("upload_show_id").value = showId;
                document.getElementById("upload_max_size").textContent = maxSize;
                document.querySelector("#uploadModal .modal-title").textContent = `Upload Audio - ${showName}`;
                
                document.getElementById("uploadForm").reset();
                document.querySelector(".upload-progress").style.display = "none";
                
                new bootstrap.Modal(document.getElementById("uploadModal")).show();
            });
        });
        
        document.getElementById("uploadButton").addEventListener("click", function() {
            const form = document.getElementById("uploadForm");
            const formData = new FormData(form);
            const progressBar = document.querySelector(".upload-progress");
            const statusDiv = document.querySelector(".upload-status");
            
            progressBar.style.display = "block";
            statusDiv.textContent = "Uploading...";
            document.querySelector(".progress-bar").style.width = "0%";
            
            fetch("/api/upload.php", {
                method: "POST",
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    statusDiv.innerHTML = "<i class=\"fas fa-check-circle text-success\"></i> Upload successful!";
                    document.querySelector(".progress-bar").style.width = "100%";
                    
                    setTimeout(() => {
                        bootstrap.Modal.getInstance(document.getElementById("uploadModal")).hide();
                        location.reload();
                    }, 1500);
                } else {
                    statusDiv.innerHTML = `<i class=\"fas fa-times-circle text-danger\"></i> ${data.error}`;
                }
            })
            .catch(error => {
                statusDiv.innerHTML = `<i class=\"fas fa-times-circle text-danger\"></i> Upload failed: ${error.message}`;
            });
        });
    }
    
    function setupPlaylistHandlers() {
        document.querySelectorAll(".manage-playlist-btn").forEach(btn => {
            btn.addEventListener("click", function() {
                const showId = this.dataset.showId;
                const showName = this.dataset.showName;
                
                document.querySelector("#playlistModal .modal-title").textContent = `Manage Track Order - ${showName}`;
                loadPlaylistTracks(showId);
                
                new bootstrap.Modal(document.getElementById("playlistModal")).show();
            });
        });
    }
    
    // Additional playlist management functions...
    function loadPlaylistTracks(showId) {
        const loading = document.getElementById("playlist-loading");
        const content = document.getElementById("playlist-content");
        
        loading.style.display = "block";
        content.style.display = "none";
        
        fetch(`/api/playlist-tracks.php?show_id=${showId}`)
        .then(response => response.json())
        .then(data => {
            loading.style.display = "none";
            
            if (data.success) {
                const tbody = document.getElementById("playlist-tracks");
                tbody.innerHTML = "";
                
                data.tracks.forEach(track => {
                    const row = createTrackRow(track);
                    tbody.appendChild(row);
                });
                
                initializeDragDrop();
                setupDeleteHandlers();
                content.style.display = "block";
            } else {
                content.innerHTML = `<div class=\"alert alert-danger\">${data.error}</div>`;
                content.style.display = "block";
            }
        })
        .catch(error => {
            loading.style.display = "none";
            content.innerHTML = `<div class=\"alert alert-danger\">Failed to load tracks: ${error.message}</div>`;
            content.style.display = "block";
        });
    }
    
    function createTrackRow(track) {
        const row = document.createElement("tr");
        row.dataset.recordingId = track.id;
        row.innerHTML = `
            <td class=\"drag-handle\" style=\"cursor: move;\">
                <i class=\"fas fa-grip-vertical text-muted\"></i>
            </td>
            <td>
                <input type=\"number\" class=\"form-control form-control-sm track-number\" 
                       value=\"${track.track_number}\" min=\"1\" style=\"width: 60px;\">
            </td>
            <td>
                <strong>${escapeHtml(track.title)}</strong>
                ${track.description ? `<br><small class=\"text-muted\">${escapeHtml(track.description)}</small>` : ""}
            </td>
            <td>${formatDuration(track.duration_seconds)}</td>
            <td>${timeAgo(track.recorded_at)}</td>
            <td>
                <button class=\"btn btn-sm btn-outline-danger delete-track\" 
                        data-recording-id=\"${track.id}\" 
                        data-track-title=\"${escapeHtml(track.title)}\" 
                        title=\"Delete\">
                    <i class=\"fas fa-trash\"></i>
                </button>
            </td>
        `;
        return row;
    }
    
    function escapeHtml(text) {
        const div = document.createElement("div");
        div.textContent = text;
        return div.innerHTML;
    }
    
    function formatDuration(seconds) {
        if (!seconds) return "--";
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${secs.toString().padStart(2, "0")}`;
    }
    
    function timeAgo(dateString) {
        const date = new Date(dateString);
        const now = new Date();
        const diffInSeconds = (now - date) / 1000;
        
        if (diffInSeconds < 60) return "just now";
        if (diffInSeconds < 3600) return `${Math.floor(diffInSeconds / 60)} minutes ago`;
        if (diffInSeconds < 86400) return `${Math.floor(diffInSeconds / 3600)} hours ago`;
        return `${Math.floor(diffInSeconds / 86400)} days ago`;
    }
    
    // Description toggle functions
    function toggleDescription(playlistId) {
        const shortDiv = document.getElementById(`desc-short-${playlistId}`);
        const fullDiv = document.getElementById(`desc-full-${playlistId}`);
        const toggleBtn = document.getElementById(`desc-toggle-${playlistId}`);
        
        if (fullDiv.style.display === "none") {
            shortDiv.style.display = "none";
            fullDiv.style.display = "block";
            toggleBtn.innerHTML = '<i class="fas fa-chevron-up"></i> Show less';
        } else {
            shortDiv.style.display = "block";
            fullDiv.style.display = "none";
            toggleBtn.innerHTML = '<i class="fas fa-chevron-down"></i> Show more';
        }
    }
    
    // Tags editing functions  
    function editTags(playlistId) {
        document.getElementById(`tags-display-${playlistId}`).style.display = "none";
        document.getElementById(`tags-edit-${playlistId}`).style.display = "block";
        document.getElementById(`edit-tags-btn-${playlistId}`).style.display = "none";
        document.getElementById(`tags-input-${playlistId}`).focus();
    }
    
    function cancelEditTags(playlistId) {
        document.getElementById(`tags-display-${playlistId}`).style.display = "block";
        document.getElementById(`tags-edit-${playlistId}`).style.display = "none";
        document.getElementById(`edit-tags-btn-${playlistId}`).style.display = "inline-block";
    }
    
    function saveTags(playlistId) {
        const input = document.getElementById(`tags-input-${playlistId}`);
        const tags = input.value.trim();
        
        fetch("/api/show-management.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                action: "update_tags",
                show_id: playlistId,
                tags: tags,
                csrf_token: "<?= generateCSRFToken() ?>"
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const tagsDisplay = document.getElementById(`tags-display-${playlistId}`);
                if (tags) {
                    const tagList = tags.split(",").map(tag => 
                        `<span class="badge bg-light text-dark me-1">${tag.trim()}</span>`
                    ).join("");
                    tagsDisplay.innerHTML = tagList;
                } else {
                    tagsDisplay.innerHTML = '<small class="text-muted">No tags</small>';
                }
                
                cancelEditTags(playlistId);
            } else {
                alert("Failed to update tags: " + (data.error || "Unknown error"));
            }
        })
        .catch(error => {
            alert("Network error: " + error.message);
        });
    }
    
    // Enhanced drag and drop functionality
    function initializeDragDrop() {
        const tbody = document.getElementById("playlist-tracks");
        if (!tbody) return;
        
        let draggedElement = null;
        
        tbody.querySelectorAll("tr").forEach(row => {
            row.draggable = true;
            
            const dragHandle = row.querySelector(".drag-handle");
            if (dragHandle) {
                dragHandle.style.cursor = "move";
            }
            
            row.addEventListener("dragstart", function(e) {
                draggedElement = this;
                this.style.opacity = "0.5";
                this.classList.add("ui-sortable-helper");
                e.dataTransfer.effectAllowed = "move";
            });
            
            row.addEventListener("dragend", function(e) {
                this.style.opacity = "";
                this.classList.remove("ui-sortable-helper");
                draggedElement = null;
            });
            
            row.addEventListener("dragover", function(e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = "move";
                
                // Visual feedback
                this.style.borderTop = "2px solid #28a745";
            });
            
            row.addEventListener("dragleave", function(e) {
                this.style.borderTop = "";
            });
            
            row.addEventListener("drop", function(e) {
                e.preventDefault();
                this.style.borderTop = "";
                
                if (draggedElement && draggedElement !== this) {
                    const rect = this.getBoundingClientRect();
                    const middle = rect.top + rect.height / 2;
                    
                    if (e.clientY < middle) {
                        this.parentNode.insertBefore(draggedElement, this);
                    } else {
                        this.parentNode.insertBefore(draggedElement, this.nextSibling);
                    }
                    
                    updateTrackNumbers();
                }
            });
        });
    }
    
    function updateTrackNumbers() {
        const rows = document.querySelectorAll("#playlist-tracks tr");
        rows.forEach((row, index) => {
            const input = row.querySelector(".track-number");
            if (input) {
                input.value = index + 1;
            }
        });
    }
    
    function setupDeleteHandlers() {
        document.querySelectorAll(".delete-track").forEach(btn => {
            btn.addEventListener("click", function() {
                const recordingId = this.dataset.recordingId;
                const trackTitle = this.dataset.trackTitle || "this track";
                
                if (confirm(`Are you sure you want to delete "${trackTitle}"? This action cannot be undone.`)) {
                    deleteTrack(recordingId, this);
                }
            });
        });
    }
    
    function deleteTrack(recordingId, buttonElement) {
        const row = buttonElement.closest("tr");
        const originalContent = buttonElement.innerHTML;
        
        // Show loading state
        buttonElement.disabled = true;
        buttonElement.innerHTML = "<i class=\"fas fa-spinner fa-spin\"></i>";
        
        fetch("/api/upload.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/x-www-form-urlencoded",
            },
            body: new URLSearchParams({
                action: "delete_upload",
                recording_id: recordingId,
                csrf_token: "<?= generateCSRFToken() ?>"
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Remove row with animation
                row.style.transition = "opacity 0.3s ease";
                row.style.opacity = "0";
                setTimeout(() => {
                    row.remove();
                    updateTrackNumbers();
                }, 300);
            } else {
                buttonElement.disabled = false;
                buttonElement.innerHTML = originalContent;
                alert("Failed to delete track: " + (data.error || "Unknown error"));
            }
        })
        .catch(error => {
            buttonElement.disabled = false;
            buttonElement.innerHTML = originalContent;
            alert("Network error: " + error.message);
        });
    }
    
    // Save playlist order function - setup event listener
    document.addEventListener("DOMContentLoaded", function() {
        const modal = document.getElementById("playlistModal");
        if (modal) {
            modal.addEventListener("shown.bs.modal", function() {
                const saveButton = document.getElementById("savePlaylistOrder");
                if (saveButton && !saveButton.hasAttribute("data-listener-added")) {
                    saveButton.setAttribute("data-listener-added", "true");
                    saveButton.addEventListener("click", function() {
                        const rows = document.querySelectorAll("#playlist-tracks tr");
                        const updates = [];
                        
                        rows.forEach((row, index) => {
                            const recordingId = row.dataset.recordingId;
                            if (recordingId) {
                                updates.push({
                                    id: recordingId,
                                    track_number: index + 1
                                });
                            }
                        });
                        
                        if (updates.length > 0) {
                            savePlaylistOrderToServer(updates);
                        }
                    });
                }
            });
        }
    });
    
    function savePlaylistOrderToServer(updates) {
        const saveButton = document.getElementById("savePlaylistOrder");
        const originalContent = saveButton.innerHTML;
        
        saveButton.disabled = true;
        saveButton.innerHTML = "<i class=\"fas fa-spinner fa-spin\"></i> Saving...";
        
        fetch("/api/playlist-tracks.php", {
            method: "POST",
            headers: {
                "Content-Type": "application/json",
            },
            body: JSON.stringify({
                action: "update_order",
                updates: updates,
                csrf_token: "<?= generateCSRFToken() ?>"
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                saveButton.innerHTML = "<i class=\"fas fa-check\"></i> Saved!";
                saveButton.className = "btn btn-success";
                
                setTimeout(() => {
                    saveButton.disabled = false;
                    saveButton.innerHTML = originalContent;
                    saveButton.className = "btn btn-primary";
                }, 2000);
            } else {
                saveButton.disabled = false;
                saveButton.innerHTML = originalContent;
                alert("Failed to save order: " + (data.error || "Unknown error"));
            }
        })
        .catch(error => {
            saveButton.disabled = false;
            saveButton.innerHTML = originalContent;
            alert("Network error: " + error.message);
        });
    }
</script>
JS;

<?php
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
                <?php if ($station_info): ?>
                    <div class="d-flex align-items-center mb-2">
                        <a href="/stations.php" class="btn btn-outline-secondary btn-sm me-2">
                            <i class="fas fa-arrow-left"></i> Back to Stations
                        </a>
                        <div>
                            <h1><i class="fas fa-list-music"></i> <?= h($station_info['call_letters']) ?> Playlists</h1>
                            <p class="text-muted mb-0">User-uploaded audio collections for <?= h($station_info['name']) ?></p>
                        </div>
                    </div>
                <?php else: ?>
                    <h1><i class="fas fa-list-music"></i> Playlists</h1>
                    <p class="text-muted">Manage your user-uploaded audio collections and track ordering</p>
                <?php endif; ?>
            </div>
            <div class="col-auto">
                <a href="/add-playlist.php" class="btn btn-success">
                    <i class="fas fa-plus-circle"></i> Add Playlist
                </a>
            </div>
        </div>

        <!-- Filters -->
        <div class="card mb-4">
            <div class="card-body">
                <form method="GET" class="row g-3">
                    <div class="col-md-4">
                        <label class="form-label">Search</label>
                        <input type="text" class="form-control" name="search" 
                               value="<?= h($search) ?>" placeholder="Search playlists...">
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Station</label>
                        <select class="form-select" name="station_id">
                            <option value="">All Stations</option>
                            <?php foreach ($stations as $station): ?>
                                <option value="<?= $station['id'] ?>" <?= $station_id == $station['id'] ? 'selected' : '' ?>>
                                    <?= h($station['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <label class="form-label">Status</label>
                        <select class="form-select" name="status">
                            <option value="">All Playlists</option>
                            <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active Only</option>
                            <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive Only</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <label class="form-label">&nbsp;</label>
                        <div class="d-grid">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-search"></i> Filter
                            </button>
                        </div>
                    </div>
                </form>
            </div>
        </div>

        <!-- Playlists List -->
        <?php if (empty($playlists)): ?>
            <div class="card">
                <div class="card-body text-center py-5">
                    <i class="fas fa-list-music fa-3x text-muted mb-3"></i>
                    <h3>No playlists found</h3>
                    <?php if ($search || $station_id || $status): ?>
                        <p class="text-muted mb-4">Try adjusting your filters to see more results.</p>
                        <a href="/playlists.php" class="btn btn-primary">Clear Filters</a>
                    <?php else: ?>
                        <p class="text-muted mb-4">Create playlists to organize your uploaded audio collections.</p>
                        <a href="/add-playlist.php" class="btn btn-success">Create Your First Playlist</a>
                    <?php endif; ?>
                </div>
            </div>
        <?php else: ?>
            <div class="row">
                <?php foreach ($playlists as $playlist): ?>
                    <div class="col-lg-6 col-xl-4 mb-4">
                        <div class="card h-100 playlist-card" data-playlist-id="<?= $playlist['id'] ?>" data-station-call="<?= h($playlist['call_letters']) ?>">
                            <!-- Playlist Image Header -->
                            <?php if ($playlist['image_url']): ?>
                                <div class="card-img-top-container" style="height: 150px; overflow: hidden; position: relative;">
                                    <img src="<?= h($playlist['image_url']) ?>" 
                                         alt="<?= h($playlist['name']) ?>" 
                                         class="card-img-top" 
                                         style="width: 100%; height: 100%; object-fit: cover;"
                                         onerror="this.style.display='none'; this.nextElementSibling.style.display='block';">
                                    <div class="fallback-header bg-gradient text-white d-flex align-items-center justify-content-center" 
                                         style="height: 100%; position: absolute; top: 0; left: 0; width: 100%; background: linear-gradient(135deg, #28a745, #20c997); display: none;">
                                        <div class="text-center">
                                            <i class="fas fa-list-music fa-2x mb-2"></i>
                                            <h6 class="mb-0"><?= h($playlist['name']) ?></h6>
                                        </div>
                                    </div>
                                    <!-- Image source badge -->
                                    <?php if ($playlist['image_source']): ?>
                                        <span class="position-absolute top-0 end-0 m-2 badge bg-dark bg-opacity-75">
                                            <?php
                                            $source_icons = [
                                                'calendar' => 'fa-calendar',
                                                'website' => 'fa-globe',
                                                'station' => 'fa-building',
                                                'default' => 'fa-image'
                                            ];
                                            $icon = $source_icons[$playlist['image_source']] ?? 'fa-image';
                                            ?>
                                            <i class="fas <?= $icon ?>"></i> <?= ucfirst($playlist['image_source']) ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                            <?php endif; ?>
                            
                            <div class="card-body">
                                <div class="d-flex align-items-start mb-3">
                                    <?php if (!$playlist['image_url']): ?>
                                        <img src="<?= h(getStationLogo(['logo_url' => $playlist['logo_url']])) ?>" 
                                             alt="<?= h($playlist['station_name']) ?>" 
                                             class="station-logo me-3"
                                             onerror="this.src='/assets/images/default-station-logo.png'">
                                    <?php endif; ?>
                                    <div class="flex-grow-1">
                                        <h5 class="card-title mb-1">
                                            <?= h($playlist['name']) ?>
                                            <?php if ($playlist['genre']): ?>
                                                <small class="badge bg-success text-white ms-2"><?= h($playlist['genre']) ?></small>
                                            <?php endif; ?>
                                            <small class="badge bg-primary text-white ms-1">
                                                <i class="fas fa-list-music"></i> Playlist
                                            </small>
                                        </h5>
                                        <small class="text-muted"><?= h($playlist['station_name']) ?></small>
                                        <?php if ($playlist['website_url']): ?>
                                            <div class="mt-1">
                                                <a href="<?= h($playlist['website_url']) ?>" target="_blank" class="btn btn-sm btn-outline-primary">
                                                    <i class="fas fa-external-link-alt"></i> Playlist Page
                                                </a>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="form-check form-switch">
                                        <input class="form-check-input" type="checkbox" 
                                               id="toggle<?= $playlist['id'] ?>"
                                               <?= $playlist['active'] ? 'checked' : '' ?>
                                               onchange="togglePlaylistStatus(<?= $playlist['id'] ?>)">
                                        <label class="form-check-label" for="toggle<?= $playlist['id'] ?>">
                                            <span class="badge <?= $playlist['active'] ? 'bg-success' : 'bg-secondary' ?>">
                                                <?= $playlist['active'] ? 'Active' : 'Inactive' ?>
                                            </span>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Enhanced Description Section -->
                                <?php if ($playlist['description'] || $playlist['long_description']): ?>
                                    <div class="description-section mb-3">
                                        <?php 
                                        $display_description = $playlist['long_description'] ?: $playlist['description'];
                                        $is_truncated = strlen($display_description) > 150;
                                        $short_description = $is_truncated ? substr($display_description, 0, 150) . '...' : $display_description;
                                        ?>
                                        
                                        <div class="description-content">
                                            <div class="description-text" id="desc-short-<?= $playlist['id'] ?>">
                                                <p class="card-text text-muted small mb-2"><?= h($short_description) ?></p>
                                            </div>
                                            
                                            <?php if ($is_truncated): ?>
                                                <div class="description-text" id="desc-full-<?= $playlist['id'] ?>" style="display: none;">
                                                    <p class="card-text text-muted small mb-2"><?= h($display_description) ?></p>
                                                </div>
                                                <button class="btn btn-sm btn-link p-0 text-primary" id="desc-toggle-<?= $playlist['id'] ?>" onclick="toggleDescription(<?= $playlist['id'] ?>)">
                                                    <i class="fas fa-chevron-down"></i> Show more
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                        
                                        <!-- Description Source Badge -->
                                        <?php if ($playlist['description_source']): ?>
                                            <div class="mt-1">
                                                <small class="badge bg-light text-dark">
                                                    <?php
                                                    $source_icons = [
                                                        'calendar' => 'fa-calendar',
                                                        'website' => 'fa-globe',
                                                        'manual' => 'fa-user-edit',
                                                        'generated' => 'fa-robot'
                                                    ];
                                                    $icon = $source_icons[$playlist['description_source']] ?? 'fa-question';
                                                    ?>
                                                    <i class="fas <?= $icon ?>"></i> <?= ucfirst($playlist['description_source']) ?> description
                                                </small>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                
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
                                            <small class="text-muted">Latest Upload</small>
                                        </div>
                                    </div>
                                    
                                    <!-- Upload Settings -->
                                    <div class="mt-2">
                                        <small class="text-muted">
                                            <i class="fas fa-upload"></i> 
                                            Max upload: 200MB | 
                                            Uploads: <?= $playlist['allow_uploads'] ? 'Enabled' : 'Disabled' ?>
                                        </small>
                                    </div>
                                    
                                    <?php if ($playlist['host']): ?>
                                        <div class="mt-1">
                                            <small class="text-muted">
                                                <i class="fas fa-user"></i> 
                                                <?= h($playlist['host']) ?>
                                            </small>
                                        </div>
                                    <?php endif; ?>
                                    
                                    <!-- Tags Display -->
                                    <div class="mt-2">
                                        <div id="tags-display-<?= $playlist['id'] ?>">
                                            <?php if ($playlist['tags']): ?>
                                                <?php foreach (explode(',', $playlist['tags']) as $tag): ?>
                                                    <span class="badge bg-light text-dark me-1"><?= h(trim($tag)) ?></span>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <small class="text-muted">No tags</small>
                                            <?php endif; ?>
                                        </div>
                                        <div id="tags-edit-<?= $playlist['id'] ?>" style="display: none;">
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control" 
                                                       id="tags-input-<?= $playlist['id'] ?>" 
                                                       value="<?= h($playlist['tags'] ?? '') ?>"
                                                       placeholder="Enter tags separated by commas"
                                                       maxlength="255">
                                                <button class="btn btn-success" type="button" 
                                                        onclick="saveTags(<?= $playlist['id'] ?>)">
                                                    <i class="fas fa-check"></i>
                                                </button>
                                                <button class="btn btn-secondary" type="button" 
                                                        onclick="cancelEditTags(<?= $playlist['id'] ?>)">
                                                    <i class="fas fa-times"></i>
                                                </button>
                                            </div>
                                            <small class="text-muted">Use commas to separate tags</small>
                                        </div>
                                        <button class="btn btn-sm btn-link p-0 mt-1" 
                                                onclick="editTags(<?= $playlist['id'] ?>)"
                                                id="edit-tags-btn-<?= $playlist['id'] ?>">
                                            <i class="fas fa-edit"></i> Edit tags
                                        </button>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="card-footer bg-transparent">
                                <!-- Playlist Upload Actions -->
                                <div class="mb-2">
                                    <div class="d-flex gap-2">
                                        <button type="button" 
                                                class="btn btn-success btn-sm flex-fill upload-file-btn"
                                                data-show-id="<?= $playlist['id'] ?>"
                                                data-show-name="<?= h($playlist['name']) ?>"
                                                data-max-size="200">
                                            <i class="fas fa-upload"></i> Upload Audio
                                        </button>
                                        <button type="button" 
                                                class="btn btn-outline-secondary btn-sm manage-playlist-btn"
                                                data-show-id="<?= $playlist['id'] ?>"
                                                data-show-name="<?= h($playlist['name']) ?>"
                                                title="Manage Track Order">
                                            <i class="fas fa-list-ol"></i> Order
                                        </button>
                                    </div>
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i> 
                                        Max size: 200MB | 
                                        Supports: MP3, WAV, M4A, AAC, OGG, FLAC
                                    </small>
                                </div>
                                
                                <div class="btn-group w-100" role="group">
                                    <a href="/edit-show.php?id=<?= $playlist['id'] ?>" 
                                       class="btn btn-outline-primary btn-sm">
                                        <i class="fas fa-edit"></i> Edit
                                    </a>
                                    <a href="/recordings.php?show_id=<?= $playlist['id'] ?>" 
                                       class="btn btn-outline-info btn-sm">
                                        <i class="fas fa-file-audio"></i> Tracks
                                    </a>
                                    <?php if ($playlist['track_count'] > 0): ?>
                                        <a href="/feeds.php#show-<?= $playlist['id'] ?>" 
                                           class="btn btn-outline-success btn-sm"
                                           title="RSS Feed">
                                            <i class="fas fa-rss"></i>
                                        </a>
                                    <?php endif; ?>
                                    <button type="button" 
                                            class="btn btn-outline-danger btn-sm delete-confirm"
                                            data-bs-toggle="modal" 
                                            data-bs-target="#deleteModal"
                                            data-show-id="<?= $playlist['id'] ?>"
                                            data-show-name="<?= h($playlist['name']) ?>"
                                            data-item="playlist">
                                        <i class="fas fa-trash"></i>
                                    </button>
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
                    <h5 class="modal-title">Delete Playlist</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete the playlist <strong id="playlistName"></strong>?</p>
                    <p class="text-danger">
                        <i class="fas fa-exclamation-triangle"></i>
                        This will also delete all uploaded tracks. This action cannot be undone.
                    </p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <form method="POST" class="d-inline">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="show_id" id="deletePlaylistId">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <button type="submit" class="btn btn-danger">
                            <i class="fas fa-trash"></i> Delete Playlist
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <!-- File Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Upload Audio File</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <form id="uploadForm" enctype="multipart/form-data">
                        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                        <input type="hidden" name="action" value="upload_file">
                        <input type="hidden" name="show_id" id="upload_show_id">
                        
                        <div class="mb-3">
                            <label class="form-label">Audio File *</label>
                            <input type="file" class="form-control" name="audio_file" id="audio_file" 
                                   accept=".mp3,.wav,.m4a,.aac,.ogg,.flac,audio/*" required>
                            <div class="form-text">
                                Supported formats: MP3, WAV, M4A, AAC, OGG, FLAC | 
                                Max size: <span id="upload_max_size">100</span>MB
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="upload_title" class="form-label">Title</label>
                            <input type="text" class="form-control" name="title" id="upload_title" 
                                   placeholder="Leave blank to use file metadata or filename">
                        </div>
                        
                        <div class="mb-3">
                            <label for="upload_description" class="form-label">Description</label>
                            <textarea class="form-control" name="description" id="upload_description" 
                                      rows="3" placeholder="Optional description"></textarea>
                        </div>
                        
                        <div class="upload-progress" style="display: none;">
                            <div class="progress mb-2">
                                <div class="progress-bar" role="progressbar"></div>
                            </div>
                            <div class="upload-status"></div>
                        </div>
                    </form>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-success" id="uploadButton">
                        <i class="fas fa-upload"></i> Upload File
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Playlist Management Modal -->
    <div class="modal fade" id="playlistModal" tabindex="-1">
        <div class="modal-dialog modal-xl">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Manage Track Order</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        <strong>Drag & Drop:</strong> Drag tracks by their left edge to reorder them in the playlist.
                        You can also manually edit track numbers.
                    </div>
                    
                    <div id="playlist-loading" class="text-center py-4">
                        <i class="fas fa-spinner fa-spin fa-2x"></i>
                        <p class="mt-2">Loading playlist tracks...</p>
                    </div>
                    
                    <div id="playlist-content" style="display: none;">
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th width="40">Order</th>
                                        <th width="60">Track #</th>
                                        <th>Title</th>
                                        <th width="100">Duration</th>
                                        <th width="100">Uploaded</th>
                                        <th width="80">Actions</th>
                                    </tr>
                                </thead>
                                <tbody id="playlist-tracks">
                                    <!-- Tracks loaded via AJAX -->
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="savePlaylistOrder">
                        <i class="fas fa-save"></i> Save Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden forms for AJAX actions -->
    <form id="toggleStatusForm" method="POST" style="display: none;">
        <input type="hidden" name="action" value="toggle_status">
        <input type="hidden" name="show_id" id="togglePlaylistId">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
    </form>

<?php
// Include shared footer
require_once '../includes/footer.php';
?>