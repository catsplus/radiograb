<?php
/**
 * RadioGrab Playlists Page
 * Manage user-created playlists and audio uploads
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

$page_title = "Playlists";

// Handle search and filtering
$search = $_GET['search'] ?? '';
$status = $_GET['status'] ?? 'all';
$filter_station = $_GET['station'] ?? '';

$where_conditions = [];
$params = [];

// Only show playlist-type shows
$where_conditions[] = "s.show_type = 'playlist'";

// Status filter
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

if ($filter_station) {
    $where_conditions[] = "s.station_id = ?";
    $params[] = $filter_station;
}

$where_clause = $where_conditions ? "WHERE " . implode(" AND ", $where_conditions) : "";

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
        GROUP BY s.id, st.id
        ORDER BY s.name ASC
    ", $params);

    // Get stations for filter dropdown
    $stations = $db->fetchAll("
        SELECT id, name, call_letters 
        FROM stations 
        WHERE id IN (SELECT DISTINCT station_id FROM shows WHERE show_type = 'playlist')
        ORDER BY name
    ");

} catch (Exception $e) {
    $error = "Error loading playlists: " . $e->getMessage();
    $playlists = [];
    $stations = [];
}

// Set playlist-specific CSS
$additional_css = '
<style>
    .playlist-card {
        transition: all 0.3s ease;
        border: 1px solid #dee2e6;
        margin-bottom: 1.5rem;
    }
    .playlist-card:hover {
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    .playlist-card .card-body {
        padding: 1.5rem;
    }
    .track-count-badge {
        font-size: 0.875rem;
        padding: 0.25rem 0.75rem;
    }
    .upload-progress {
        margin-top: 1rem;
    }
    .upload-status {
        margin-top: 0.5rem;
        font-weight: 500;
    }
    .sortable-ghost {
        opacity: 0.4;
    }
    .playlist-track {
        cursor: move;
    }
    .playlist-track:hover {
        background-color: #f8f9fa;
    }
    .modal-header .btn-close {
        filter: invert(1);
    }
</style>';

// Set playlist-specific JavaScript - Load external file to avoid PHP parsing issues
$additional_js = '<script src="/assets/js/playlists.js"></script><script src="/assets/js/audio-recorder.js"></script>';

// Include shared header
require_once '../includes/header.php';

// Show error if present
if (isset($error)): ?>
    <div class="alert alert-danger">
        <i class="fas fa-exclamation-triangle"></i> <?= h($error) ?>
    </div>
<?php endif; ?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <h1><i class="fas fa-list-music"></i> Playlists</h1>
    <div>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPlaylistModal">
            <i class="fas fa-plus"></i> Create Playlist
        </button>
    </div>
</div>

<!-- Search and Filter Section -->
<div class="card mb-4">
    <div class="card-body">
        <form method="GET" class="row g-3">
            <div class="col-md-4">
                <label for="search" class="form-label">Search</label>
                <input type="text" class="form-control" id="search" name="search" 
                       value="<?= h($search) ?>" placeholder="Search playlists...">
            </div>
            <div class="col-md-3">
                <label for="status" class="form-label">Status</label>
                <select class="form-select" id="status" name="status">
                    <option value="all" <?= $status === 'all' ? 'selected' : '' ?>>All</option>
                    <option value="active" <?= $status === 'active' ? 'selected' : '' ?>>Active</option>
                    <option value="inactive" <?= $status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                </select>
            </div>
            <div class="col-md-3">
                <label for="station" class="form-label">Station</label>
                <select class="form-select" id="station" name="station">
                    <option value="">All Stations</option>
                    <?php foreach ($stations as $station): ?>
                        <option value="<?= $station['id'] ?>" <?= $filter_station == $station['id'] ? 'selected' : '' ?>>
                            <?= h($station['call_letters']) ?> - <?= h($station['name']) ?>
                        </option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex align-items-end">
                <button type="submit" class="btn btn-primary me-2">
                    <i class="fas fa-search"></i> Search
                </button>
                <a href="playlists.php" class="btn btn-outline-secondary">
                    <i class="fas fa-times"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Playlists Grid -->
<?php if (empty($playlists)): ?>
    <div class="text-center py-5">
        <i class="fas fa-list-music fa-3x text-muted mb-3"></i>
        <h3 class="text-muted">No playlists found</h3>
        <p class="text-muted">Create your first playlist to get started!</p>
        <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createPlaylistModal">
            <i class="fas fa-plus"></i> Create First Playlist
        </button>
    </div>
<?php else: ?>
    <div class="row">
        <?php foreach ($playlists as $playlist): ?>
            <div class="col-lg-4 col-md-6">
                <div class="card playlist-card">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h5 class="card-title mb-0"><?= h($playlist['name']) ?></h5>
                            <div class="dropdown">
                                <button class="btn btn-sm btn-outline-secondary dropdown-toggle" type="button" 
                                        data-bs-toggle="dropdown">
                                    <i class="fas fa-ellipsis-v"></i>
                                </button>
                                <ul class="dropdown-menu">
                                    <li>
                                        <button class="dropdown-item" onclick="showUploadModal(<?= $playlist['id'] ?>, '<?= h($playlist['name']) ?>')">
                                            <i class="fas fa-upload"></i> Upload Track
                                        </button>
                                    </li>
                                    <li>
                                        <button class="dropdown-item" onclick="showPlaylistModal(<?= $playlist['id'] ?>)">
                                            <i class="fas fa-list"></i> Manage Tracks
                                        </button>
                                    </li>
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <a class="dropdown-item" href="edit-playlist.php?id=<?= $playlist['id'] ?>">
                                            <i class="fas fa-edit"></i> Edit Playlist
                                        </a>
                                    </li>
                                    <li>
                                        <button class="dropdown-item text-danger" 
                                                data-bs-toggle="modal" data-bs-target="#deleteModal"
                                                data-show-id="<?= $playlist['id'] ?>" 
                                                data-show-name="<?= h($playlist['name']) ?>">
                                            <i class="fas fa-trash"></i> Delete
                                        </button>
                                    </li>
                                </ul>
                            </div>
                        </div>

                        <div class="mb-3">
                            <small class="text-muted">
                                <i class="fas fa-radio"></i> <?= h($playlist['station_name']) ?>
                            </small>
                        </div>

                        <?php if ($playlist['description']): ?>
                            <p class="card-text text-muted small mb-3"><?= h($playlist['description']) ?></p>
                        <?php endif; ?>

                        <div class="d-flex justify-content-between align-items-center mb-3">
                            <span class="badge bg-primary track-count-badge">
                                <i class="fas fa-music"></i> <?= $playlist['track_count'] ?> tracks
                            </span>
                            
                            <?php if ($playlist['active']): ?>
                                <span class="badge bg-success">Active</span>
                            <?php else: ?>
                                <span class="badge bg-secondary">Inactive</span>
                            <?php endif; ?>
                        </div>
                        
                        <?php if ($playlist['track_count'] > 0): ?>
                            <div class="d-grid">
                                <a href="/playlist-player.php?id=<?= $playlist['id'] ?>" class="btn btn-outline-primary">
                                    <i class="fas fa-play"></i> Play Playlist
                                </a>
                            </div>
                        <?php endif; ?>

                        <?php if ($playlist['latest_upload']): ?>
                            <div class="mt-2">
                                <small class="text-muted">
                                    <i class="fas fa-clock"></i> 
                                    Last upload: <?= date('M j, Y', strtotime($playlist['latest_upload'])) ?>
                                </small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        <?php endforeach; ?>
    </div>
<?php endif; ?>

<!-- Create Playlist Modal -->
<div class="modal fade" id="createPlaylistModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Create New Playlist</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form action="/api/upload.php" method="POST">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="playlist_name" class="form-label">Playlist Name</label>
                        <input type="text" class="form-control" id="playlist_name" name="name" required>
                    </div>
                    <div class="mb-3">
                        <label for="playlist_station" class="form-label">Station</label>
                        <select class="form-select" id="playlist_station" name="station_id" required>
                            <option value="">Select Station</option>
                            <?php foreach ($stations as $station): ?>
                                <option value="<?= $station['id'] ?>">
                                    <?= h($station['call_letters']) ?> - <?= h($station['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="playlist_description" class="form-label">Description</label>
                        <textarea class="form-control" id="playlist_description" name="description" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Playlist</button>
                </div>
                <input type="hidden" name="action" value="create_playlist">
                <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
            </form>
        </div>
    </div>
</div>

<!-- Upload Modal -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Upload Audio Track</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="mb-3">
                    <strong>Uploading to:</strong> 
                    <span id="upload_show_name"></span>
                </div>
                
                <!-- Upload Type Selection -->
                <div class="mb-3">
                    <label class="form-label">Upload Method</label>
                    <div class="btn-group w-100" role="group">
                        <input type="radio" class="btn-check" name="upload_method" id="method_file" value="file" checked>
                        <label class="btn btn-outline-primary" for="method_file">
                            <i class="fas fa-file"></i> Upload File
                        </label>
                        <input type="radio" class="btn-check" name="upload_method" id="method_url" value="url">
                        <label class="btn btn-outline-primary" for="method_url">
                            <i class="fas fa-link"></i> From URL
                        </label>
                    </div>
                </div>
                
                <!-- File Upload Section -->
                <div class="mb-3" id="file_upload_section">
                    <label for="upload_file" class="form-label">Audio File</label>
                    <input type="file" class="form-control" id="upload_file" name="audio_file" 
                           accept=".mp3,.wav,.m4a,.aac,.ogg,.flac">
                    <div class="form-text">Supported formats: MP3, WAV, M4A, AAC, OGG, FLAC (Max: 100MB)</div>
                </div>
                
                <!-- URL Upload Section -->
                <div class="mb-3" id="url_upload_section" style="display: none;">
                    <label for="upload_url" class="form-label">Audio URL</label>
                    <input type="url" class="form-control" id="upload_url" name="url" 
                           placeholder="https://example.com/audio.mp3 or YouTube URL">
                    <div class="form-text">
                        <i class="fas fa-info-circle"></i> 
                        Supports direct MP3/audio links and YouTube videos (auto-converted to MP3)
                    </div>
                </div>
                
                <div class="mb-3">
                    <label for="upload_title" class="form-label">Track Title</label>
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
                </div>
                
                <div class="upload-status"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn btn-primary" onclick="handleUpload()">
                    <i class="fas fa-upload"></i> Upload
                </button>
            </div>
            <input type="hidden" id="upload_show_id" name="show_id">
        </div>
    </div>
</div>

<!-- Playlist Management Modal -->
<div class="modal fade" id="playlistModal" tabindex="-1">
    <div class="modal-dialog modal-xl">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="playlistModalLabel">Manage Playlist</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div id="playlistContent">
                    <p class="mt-2">Loading playlist tracks...</p>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-success" onclick="showVoiceRecordingModal(window.currentPlaylistId)">
                    <i class="fas fa-microphone"></i> Record Voice Clip
                </button>
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-primary" id="savePlaylistOrder">
                    <i class="fas fa-save"></i> Save Order
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Confirm Delete</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <p>Are you sure you want to delete the playlist "<span id="deletePlaylistName"></span>"?</p>
                <p class="text-warning"><strong>Warning:</strong> This will also delete all uploaded tracks in this playlist. This action cannot be undone.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                <form action="delete-show.php" method="POST" style="display: inline;">
                    <button type="submit" class="btn btn-danger">Delete Playlist</button>
                    <input type="hidden" id="deletePlaylistId" name="show_id">
                    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                </form>
            </div>
        </div>
    </div>
</div>

<!-- Voice Recording Modal -->
<div class="modal fade" id="voiceRecordingModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="fas fa-microphone"></i> Record DJ Voice Clip
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <div class="row">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6><i class="fas fa-circle text-danger"></i> Recording Controls</h6>
                            </div>
                            <div class="card-body text-center">
                                <div id="recordingStatus" class="alert alert-secondary mb-3">
                                    <i class="fas fa-microphone"></i> Ready to record
                                </div>
                                
                                <div class="mb-3">
                                    <div class="display-4 font-monospace" id="recordingTime">00:00</div>
                                    <small class="text-muted">Recording time (max 5:00)</small>
                                </div>
                                
                                <div class="d-grid gap-2">
                                    <button type="button" class="btn btn-danger btn-lg" id="recordVoiceBtn" onclick="startVoiceRecording()">
                                        <i class="fas fa-microphone"></i> Start Recording
                                    </button>
                                    <button type="button" class="btn btn-outline-secondary btn-lg" id="stopRecordingBtn" onclick="stopVoiceRecording()" style="display: none;">
                                        <i class="fas fa-stop"></i> Stop Recording
                                    </button>
                                </div>
                                
                                <div class="mt-3">
                                    <small class="text-muted">
                                        <i class="fas fa-info-circle"></i> 
                                        Perfect for intros, outros, station IDs, and DJ drops
                                    </small>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-6">
                        <div id="recordingPreview" style="display: none;">
                            <!-- Recording preview will be inserted here -->
                        </div>
                        
                        <div class="card" id="recordingTips">
                            <div class="card-header">
                                <h6><i class="fas fa-lightbulb"></i> Recording Tips</h6>
                            </div>
                            <div class="card-body">
                                <ul class="list-unstyled small">
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i> 
                                        Speak clearly and close to microphone
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i> 
                                        Keep background noise minimal
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i> 
                                        Record in a quiet environment
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i> 
                                        Keep clips under 2 minutes for best results
                                    </li>
                                    <li class="mb-2">
                                        <i class="fas fa-check text-success"></i> 
                                        Test your microphone first
                                    </li>
                                </ul>
                                
                                <div class="alert alert-info small mt-3">
                                    <i class="fas fa-browser"></i> 
                                    <strong>Browser Compatibility:</strong> Works on Chrome, Firefox, Safari, and Edge. 
                                    Mobile browsers supported.
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <div class="progress mt-3" id="voiceClipUploadProgress" style="display: none;">
                    <div class="progress-bar progress-bar-striped progress-bar-animated" style="width: 100%">
                        Uploading voice clip...
                    </div>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                <button type="button" class="btn btn-outline-info" onclick="window.open('https://support.google.com/chrome/answer/2693767', '_blank')">
                    <i class="fas fa-question-circle"></i> Microphone Help
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Hidden CSRF Token for JavaScript -->
<form style="display: none;">
    <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
</form>

<?php
// Include shared footer
require_once '../includes/footer.php';
?>