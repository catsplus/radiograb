<?php
/**
 * RadioGrab - Add Playlist
 * Create new user playlist for audio uploads
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

$success_message = '';
$error_message = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCSRFToken($_POST['csrf_token'] ?? '')) {
        $error_message = 'Invalid security token';
    } else {
        $name = trim($_POST['name'] ?? '');
        $description = trim($_POST['description'] ?? '');
        $host = trim($_POST['host'] ?? '');
        $genre = trim($_POST['genre'] ?? '');
        $max_file_size_mb = (int)($_POST['max_file_size_mb'] ?? 100);
        $active = isset($_POST['active']) && $_POST['active'] === '1';
        
        // Validation
        $errors = [];
        
        if (empty($name)) {
            $errors[] = 'Playlist name is required';
        }
        
        if ($max_file_size_mb < 1 || $max_file_size_mb > 500) {
            $errors[] = 'Max file size must be between 1 and 500 MB';
        }
        
        if (empty($errors)) {
            try {
                // Create a default "user uploads" station if it doesn't exist
                $upload_station = $db->fetchOne("SELECT id FROM stations WHERE name = 'User Uploads' AND call_letters = 'USER'");
                
                if (!$upload_station) {
                    $station_data = [
                        'name' => 'User Uploads',
                        'call_letters' => 'USER',
                        'website_url' => '',
                        'stream_url' => '',
                        'logo_url' => '/assets/images/default-station-logo.png',
                        'status' => 'active',
                        'timezone' => 'America/New_York',
                        'created_at' => date('Y-m-d H:i:s'),
                        'updated_at' => date('Y-m-d H:i:s')
                    ];
                    
                    $station_id = $db->insert('stations', $station_data);
                } else {
                    $station_id = $upload_station['id'];
                }
                
                // Create the playlist
                $playlist_data = [
                    'station_id' => $station_id,
                    'name' => $name,
                    'description' => $description,
                    'host' => $host ?: null,
                    'genre' => $genre ?: null,
                    'show_type' => 'playlist',
                    'schedule_pattern' => null,
                    'schedule_description' => "User playlist: $name",
                    'retention_days' => 0, // Never expire
                    'active' => $active,
                    'allow_uploads' => 1,
                    'max_file_size_mb' => $max_file_size_mb,
                    'auto_imported' => 0,
                    'created_at' => date('Y-m-d H:i:s'),
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                $playlist_id = $db->insert('shows', $playlist_data);
                
                if ($playlist_id) {
                    $success_message = 'Playlist created successfully! You can now upload audio files to it.';
                    
                    // Clear form on success
                    $_POST = [];
                } else {
                    $error_message = 'Failed to create playlist. Please try again.';
                }
                
            } catch (Exception $e) {
                error_log("Error creating playlist: " . $e->getMessage());
                $error_message = 'Database error occurred. Please try again.';
            }
        } else {
            $error_message = implode('<br>', $errors);
        }
    }
}
?>
<?php
// Set page variables for shared template
$page_title = 'Add Playlist';
$active_nav = 'playlists';

require_once '../includes/header.php';
?>

    <!-- Main Content -->
    <div class="container mt-4">
        <!-- Page Header -->
        <div class="row mb-4">
            <div class="col">
                <div class="d-flex align-items-center mb-2">
                    <a href="/playlists.php" class="btn btn-outline-secondary btn-sm me-2">
                        <i class="fas fa-arrow-left"></i> Back to Playlists
                    </a>
                    <div>
                        <h1><i class="fas fa-plus-circle text-success"></i> Create New Playlist</h1>
                        <p class="text-muted mb-0">Create a new playlist for organizing your uploaded audio files</p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Flash Messages -->
        <?php if ($success_message): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <i class="fas fa-check-circle"></i> <?= h($success_message) ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                <div class="mt-3">
                    <a href="/playlists.php" class="btn btn-primary">
                        <i class="fas fa-list-music"></i> View All Playlists
                    </a>
                    <button type="button" class="btn btn-success" onclick="location.reload()">
                        <i class="fas fa-plus"></i> Create Another Playlist
                    </button>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($error_message): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?= $error_message ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>

        <!-- Add Playlist Form -->
        <div class="row justify-content-center">
            <div class="col-lg-8">
                <div class="card">
                    <div class="card-header bg-success text-white">
                        <h5 class="mb-0"><i class="fas fa-list-music"></i> Playlist Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST">
                            <input type="hidden" name="csrf_token" value="<?= generateCSRFToken() ?>">
                            
                            <!-- Playlist Name -->
                            <div class="mb-3">
                                <label for="name" class="form-label">
                                    <i class="fas fa-list-music text-success"></i> Playlist Name *
                                </label>
                                <input type="text" class="form-control" id="name" name="name" 
                                       value="<?= h($_POST['name'] ?? '') ?>" required maxlength="255"
                                       placeholder="e.g., My Favorite Songs, Study Music, Road Trip Mix">
                                <div class="form-text">Choose a descriptive name for your playlist</div>
                            </div>

                            <!-- Description -->
                            <div class="mb-3">
                                <label for="description" class="form-label">
                                    <i class="fas fa-align-left text-success"></i> Description
                                </label>
                                <textarea class="form-control" id="description" name="description" 
                                          rows="3" maxlength="1000"
                                          placeholder="Describe what this playlist is about, the mood, or when you'd listen to it..."><?= h($_POST['description'] ?? '') ?></textarea>
                                <div class="form-text">Optional description to help you remember what this playlist is for</div>
                            </div>

                            <!-- Row for Host and Genre -->
                            <div class="row">
                                <div class="col-md-6">
                                    <!-- Curator/Host -->
                                    <div class="mb-3">
                                        <label for="host" class="form-label">
                                            <i class="fas fa-user text-success"></i> Curator/Creator
                                        </label>
                                        <input type="text" class="form-control" id="host" name="host" 
                                               value="<?= h($_POST['host'] ?? '') ?>" maxlength="255"
                                               placeholder="Your name or username">
                                        <div class="form-text">Who created or curated this playlist?</div>
                                    </div>
                                </div>
                                
                                <div class="col-md-6">
                                    <!-- Genre -->
                                    <div class="mb-3">
                                        <label for="genre" class="form-label">
                                            <i class="fas fa-music text-success"></i> Genre/Category
                                        </label>
                                        <input type="text" class="form-control" id="genre" name="genre" 
                                               value="<?= h($_POST['genre'] ?? '') ?>" maxlength="100"
                                               placeholder="e.g., Rock, Jazz, Ambient, Podcast, Mixed">
                                        <div class="form-text">What genre or category best describes this playlist?</div>
                                    </div>
                                </div>
                            </div>

                            <!-- Upload Settings -->
                            <div class="card border-info mb-3">
                                <div class="card-header bg-info text-white">
                                    <h6 class="mb-0"><i class="fas fa-upload"></i> Upload Settings</h6>
                                </div>
                                <div class="card-body">
                                    <!-- Max File Size -->
                                    <div class="mb-3">
                                        <label for="max_file_size_mb" class="form-label">
                                            <i class="fas fa-weight-hanging text-info"></i> Maximum File Size (MB)
                                        </label>
                                        <select class="form-select" id="max_file_size_mb" name="max_file_size_mb">
                                            <option value="50" <?= ($_POST['max_file_size_mb'] ?? 100) == 50 ? 'selected' : '' ?>>50 MB</option>
                                            <option value="100" <?= ($_POST['max_file_size_mb'] ?? 100) == 100 ? 'selected' : '' ?>>100 MB (Recommended)</option>
                                            <option value="200" <?= ($_POST['max_file_size_mb'] ?? 100) == 200 ? 'selected' : '' ?>>200 MB</option>
                                            <option value="300" <?= ($_POST['max_file_size_mb'] ?? 100) == 300 ? 'selected' : '' ?>>300 MB</option>
                                            <option value="500" <?= ($_POST['max_file_size_mb'] ?? 100) == 500 ? 'selected' : '' ?>>500 MB (Maximum)</option>
                                        </select>
                                        <div class="form-text">
                                            <i class="fas fa-info-circle"></i> 
                                            Larger files allow longer songs but take more storage space. 
                                            100MB is sufficient for most audio files.
                                        </div>
                                    </div>

                                    <!-- Supported Formats Info -->
                                    <div class="alert alert-light">
                                        <h6><i class="fas fa-file-audio text-success"></i> Supported Audio Formats</h6>
                                        <p class="mb-2">Your playlist supports these audio formats:</p>
                                        <div class="row">
                                            <div class="col-md-6">
                                                <ul class="list-unstyled mb-0">
                                                    <li><i class="fas fa-check text-success"></i> MP3 (recommended)</li>
                                                    <li><i class="fas fa-check text-success"></i> WAV (high quality)</li>
                                                    <li><i class="fas fa-check text-success"></i> M4A (iTunes format)</li>
                                                </ul>
                                            </div>
                                            <div class="col-md-6">
                                                <ul class="list-unstyled mb-0">
                                                    <li><i class="fas fa-check text-success"></i> AAC (advanced codec)</li>
                                                    <li><i class="fas fa-check text-success"></i> OGG (open source)</li>
                                                    <li><i class="fas fa-check text-success"></i> FLAC (lossless)</li>
                                                </ul>
                                            </div>
                                        </div>
                                        <small class="text-muted">All formats are automatically converted to MP3 for compatibility</small>
                                    </div>
                                </div>
                            </div>

                            <!-- Playlist Status -->
                            <div class="mb-3">
                                <div class="form-check form-switch">
                                    <input class="form-check-input" type="checkbox" id="active" name="active" 
                                           value="1" <?= ($_POST['active'] ?? '1') === '1' ? 'checked' : '' ?>>
                                    <label class="form-check-label" for="active">
                                        <i class="fas fa-toggle-on text-success"></i> <strong>Active Playlist</strong>
                                    </label>
                                </div>
                                <div class="form-text">
                                    Active playlists appear in your playlist list and RSS feeds. 
                                    You can always change this later.
                                </div>
                            </div>

                            <!-- Submit Buttons -->
                            <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                                <a href="/playlists.php" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </a>
                                <button type="submit" class="btn btn-success">
                                    <i class="fas fa-plus-circle"></i> Create Playlist
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>

        <!-- Usage Tips -->
        <div class="row justify-content-center mt-4">
            <div class="col-lg-8">
                <div class="card border-light">
                    <div class="card-header bg-light">
                        <h6 class="mb-0"><i class="fas fa-lightbulb text-warning"></i> Tips for Creating Great Playlists</h6>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6">
                                <h6><i class="fas fa-tags text-primary"></i> Naming</h6>
                                <ul class="small">
                                    <li>Use descriptive names that reflect the mood or purpose</li>
                                    <li>Include the genre or style if it's specific</li>
                                    <li>Consider when or where you'd listen to it</li>
                                </ul>
                            </div>
                            <div class="col-md-6">
                                <h6><i class="fas fa-upload text-success"></i> Organization</h6>
                                <ul class="small">
                                    <li>You can reorder tracks after uploading</li>
                                    <li>Add descriptions to individual tracks</li>
                                    <li>Playlists automatically generate RSS feeds</li>
                                </ul>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <?php
require_once '../includes/footer.php';
?>