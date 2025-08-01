<?php
/**
 * RadioGrab - Edit Playlist
 *
 * This file provides the web interface for editing an existing playlist's
 * details, including its name, description, and image. For playlists,
 * scheduling is not applicable as they are manually managed collections
 * of uploaded audio files.
 *
 * Key Variables:
 * - `$show_id`: The ID of the playlist being edited.
 * - `$show`: An array containing the current data of the playlist.
 * - `$errors`: An array to store any validation or database errors.
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

// Get show ID from URL parameter
$show_id = isset($_GET['id']) ? (int)$_GET['id'] : null;

if (!$show_id) {
    redirectWithMessage('/playlists.php', 'danger', 'Playlist ID is required');
}

// Get existing playlist data
try {
    $show = $db->fetchOne("
        SELECT s.*, st.name as station_name 
        FROM shows s 
        JOIN stations st ON s.station_id = st.id 
        WHERE s.id = ? AND s.show_type = 'playlist'
    ", [$show_id]);
    
    if (!$show) {
        redirectWithMessage('/playlists.php', 'danger', 'Playlist not found');
    }
} catch (Exception $e) {
    redirectWithMessage('/playlists.php', 'danger', 'Database error: ' . $e->getMessage());
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        setFlashMessage('danger', 'Invalid security token');
        header('Location: /edit-playlist.php?id=' . $show_id);
        exit;
    }
    
    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $image_url = trim($_POST['image_url'] ?? '');
    $active = isset($_POST['active']) ? 1 : 0;
    $max_file_size_mb = (int)($_POST['max_file_size_mb'] ?? 100);
    
    $errors = [];
    
    // Validation
    if (empty($name)) {
        $errors[] = 'Playlist name is required';
    }
    
    if ($max_file_size_mb < 1 || $max_file_size_mb > 500) {
        $errors[] = 'Max file size must be between 1 and 500 MB';
    }
    
    if ($image_url && !filter_var($image_url, FILTER_VALIDATE_URL)) {
        $errors[] = 'Image URL must be a valid URL';
    }
    
    if (empty($errors)) {
        try {
            // Check if playlist with this name already exists for this station (excluding current playlist)
            $existing = $db->fetchOne("SELECT id FROM shows WHERE station_id = ? AND name = ? AND id != ? AND show_type = 'playlist'", [$show['station_id'], $name, $show_id]);
            if ($existing) {
                $errors[] = 'A playlist with this name already exists for this station';
            } else {
                // Update playlist
                $db->update('shows', [
                    'name' => $name,
                    'description' => $description ?: null,
                    'image_url' => $image_url ?: null,
                    'active' => $active,
                    'max_file_size_mb' => $max_file_size_mb,
                    'updated_at' => date('Y-m-d H:i:s')
                ], 'id = ?', [$show_id]);
                
                redirectWithMessage('/playlists.php', 'success', 'Playlist updated successfully!');
            }
        } catch (Exception $e) {
            $errors[] = 'Database error: ' . $e->getMessage();
        }
    }
}
?>
<?php
// Set page variables for shared template
$page_title = 'Edit Playlist';
$active_nav = 'playlists';

require_once '../includes/header.php';
?>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb">
                        <li class="breadcrumb-item"><a href="/playlists.php">Playlists</a></li>
                        <li class="breadcrumb-item active">Edit Playlist</li>
                    </ol>
                </nav>
                <h1><i class="fas fa-edit"></i> Edit Playlist</h1>
                <p class="text-muted">Update the details for "<?= h($show['name']) ?>"</p>
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
                <!-- Edit Playlist Form -->
                <div class="card">
                    <div class="card-header">
                        <h5><i class="fas fa-list-music"></i> Playlist Information</h5>
                    </div>
                    <div class="card-body">
                        <form id="edit-playlist-form" method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <div class="mb-3">
                                <label for="station_display" class="form-label">Station</label>
                                <input type="text" class="form-control" id="station_display" 
                                       value="<?= h($show['station_name']) ?>" readonly>
                                <div class="form-text">Playlists belong to a specific station and cannot be moved</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="name" class="form-label">Playlist Name *</label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= h($show['name']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label for="description" class="form-label">Description</label>
                                <textarea class="form-control" id="description" name="description" rows="3"><?= h($show['description']) ?></textarea>
                                <div class="form-text">Brief description of your playlist's theme or content</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="image_url" class="form-label">Playlist Image/Logo</label>
                                <input type="url" class="form-control" id="image_url" name="image_url" 
                                       value="<?= h($show['image_url'] ?? '') ?>" 
                                       placeholder="https://example.com/playlist-cover.png">
                                <div class="form-text">URL to the playlist's cover art or logo image</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="max_file_size_mb" class="form-label">Maximum Upload File Size (MB)</label>
                                <input type="number" class="form-control" id="max_file_size_mb" name="max_file_size_mb" 
                                       value="<?= $show['max_file_size_mb'] ?? 100 ?>" min="1" max="500">
                                <div class="form-text">Maximum allowed file size for uploads to this playlist</div>
                            </div>
                            
                            <div class="mb-3">
                                <label for="active" class="form-label">Status</label>
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="active" name="active" 
                                           <?= $show['active'] ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="active">
                                        Active (enable uploads and public access)
                                    </label>
                                </div>
                            </div>
                            
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="/playlists.php" class="btn btn-outline-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-save"></i> Update Playlist
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
            
            <div class="col-lg-4">
                <!-- Playlist Status -->
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
                        <div class="mb-2">
                            <strong>Type:</strong> <span class="badge bg-info">Playlist</span>
                        </div>
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
                
                <!-- Playlist Management -->
                <div class="card">
                    <div class="card-header">
                        <h6><i class="fas fa-cog"></i> Playlist Management</h6>
                    </div>
                    <div class="card-body">
                        <div class="d-grid gap-2">
                            <button class="btn btn-outline-primary" onclick="showUploadModal(<?= $show['id'] ?>, '<?= h($show['name']) ?>')">
                                <i class="fas fa-upload"></i> Upload Track
                            </button>
                            <button class="btn btn-outline-secondary" onclick="showPlaylistModal(<?= $show['id'] ?>)">
                                <i class="fas fa-list"></i> Manage Tracks
                            </button>
                            <?php 
                            // Get track count for this playlist
                            $track_count = $db->fetchOne("SELECT COUNT(*) as count FROM recordings WHERE show_id = ? AND source_type = 'uploaded'", [$show['id']])['count'] ?? 0;
                            ?>
                            <?php if ($track_count > 0): ?>
                                <a href="/playlist-player.php?id=<?= $show['id'] ?>" class="btn btn-outline-success">
                                    <i class="fas fa-play"></i> Play Playlist (<?= $track_count ?> tracks)
                                </a>
                            <?php else: ?>
                                <button class="btn btn-outline-secondary" disabled>
                                    <i class="fas fa-music"></i> No tracks yet
                                </button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Upload Modal (referenced by JavaScript functions) -->
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
                        <div class="form-text">Supported formats: MP3, WAV, M4A, AAC, OGG, FLAC (Max: <?= $show['max_file_size_mb'] ?? 100 ?>MB)</div>
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
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                    <button type="button" class="btn btn-primary" id="savePlaylistOrder">
                        <i class="fas fa-save"></i> Save Order
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Hidden CSRF Token for JavaScript -->
    <form style="display: none;">
        <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
    </form>

    <!-- Load playlists JavaScript -->
    <script src="/assets/js/playlists.js"></script>

    <?php
require_once '../includes/footer.php';
?>