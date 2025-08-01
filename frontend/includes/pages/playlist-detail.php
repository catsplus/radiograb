<?php
/**
 * Playlist Detail Page Template
 * Displays playlist information and tracks with audio player
 */

// Get playlist tracks ordered by track_number, then by recorded_at
try {
    $tracks = $db->fetchAll("
        SELECT * FROM recordings 
        WHERE show_id = ? 
        ORDER BY 
            CASE WHEN track_number IS NOT NULL THEN track_number ELSE 9999 END ASC,
            recorded_at ASC
    ", [$playlist['id']]);
    
    // Get playlist statistics
    $stats = $db->fetchOne("
        SELECT 
            COUNT(*) as total_tracks,
            SUM(file_size_bytes) as total_size_bytes,
            SUM(duration_seconds) as total_duration,
            MIN(recorded_at) as first_track,
            MAX(recorded_at) as latest_track
        FROM recordings 
        WHERE show_id = ?
    ", [$playlist['id']]);
    
} catch (Exception $e) {
    $tracks = [];
    $stats = [
        'total_tracks' => 0, 
        'total_size_bytes' => 0, 
        'total_duration' => 0
    ];
    error_log("Playlist detail error: " . $e->getMessage());
}

// Set page variables
$playlist_title = $playlist['name'];
if (isset($playlist['username'])) {
    $page_title = $playlist_title . ' by ' . $playlist['username'] . ' - RadioGrab';
} else {
    $page_title = $playlist_title . ' - RadioGrab';
}
$active_nav = 'playlists';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= h($page_title) ?></title>
    <meta name="description" content="<?= h($playlist['description'] ?: $playlist_title . ' playlist') ?>">
    
    <!-- Open Graph -->
    <meta property="og:title" content="<?= h($playlist_title) ?>">
    <meta property="og:description" content="<?= h($playlist['description'] ?: 'Audio playlist') ?>">
    <meta property="og:image" content="<?= h($playlist['image_url'] ?: '/assets/images/default-station-logo.png') ?>">
    <meta property="og:url" content="<?= h($_SERVER['REQUEST_SCHEME'] . '://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI']) ?>">
    
    <!-- RSS Feed -->
    <link rel="alternate" type="application/rss+xml" title="<?= h($playlist_title) ?> RSS Feed" 
          href="/api/enhanced-feeds.php?type=playlist&id=<?= $playlist['id'] ?>">
    
    <link rel="icon" href="/favicon.png" type="image/png">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="/assets/css/radiograb.css" rel="stylesheet">
    <link href="/assets/css/on-air.css" rel="stylesheet">
</head>
<body>
    <?php require_once '../includes/navbar.php'; ?>

    <!-- Playlist Header -->
    <div class="container-fluid bg-light py-4">
        <div class="container">
            <div class="row align-items-center">
                <div class="col-md-2">
                    <img src="<?= h($playlist['image_url'] ?: '/assets/images/default-station-logo.png') ?>" 
                         alt="<?= h($playlist_title) ?>" 
                         class="img-fluid rounded shadow"
                         style="max-height: 120px; width: auto;"
                         onerror="this.src='/assets/images/default-station-logo.png'">
                </div>
                <div class="col-md-8">
                    <?php if (isset($playlist['username'])): ?>
                        <nav aria-label="breadcrumb">
                            <ol class="breadcrumb">
                                <li class="breadcrumb-item">
                                    <a href="/user/<?= h(strtolower($playlist['username'])) ?>">
                                        <?= h($playlist['username']) ?>
                                    </a>
                                </li>
                                <li class="breadcrumb-item active"><?= h($playlist_title) ?></li>
                            </ol>
                        </nav>
                    <?php endif; ?>
                    
                    <h1 class="display-5 mb-2"><?= h($playlist_title) ?></h1>
                    
                    <?php if (isset($playlist['username'])): ?>
                        <h2 class="h5 text-muted mb-2">by <?= h($playlist['username']) ?></h2>
                    <?php endif; ?>
                    
                    <?php if ($playlist['description']): ?>
                        <p class="lead"><?= h($playlist['description']) ?></p>
                    <?php endif; ?>
                    
                    <div class="mt-3">
                        <span class="badge bg-info">Playlist</span>
                        <span class="badge <?= $playlist['active'] ? 'bg-success' : 'bg-secondary' ?>">
                            <?= $playlist['active'] ? 'Active' : 'Inactive' ?>
                        </span>
                        <?php if ($stats['total_tracks'] > 0): ?>
                            <span class="badge bg-primary"><?= number_format($stats['total_tracks']) ?> tracks</span>
                            <span class="badge bg-secondary"><?= formatDuration($stats['total_duration']) ?> total</span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="col-md-2 text-end">
                    <a href="/api/enhanced-feeds.php?type=playlist&id=<?= $playlist['id'] ?>" 
                       class="btn btn-outline-warning mb-2" title="RSS Feed">
                        <i class="fas fa-rss"></i> RSS
                    </a>
                    <a href="/edit-show.php?id=<?= $playlist['id'] ?>" class="btn btn-outline-secondary mb-2">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                    <?php if (!empty($tracks)): ?>
                        <button class="btn btn-primary" id="playAllBtn">
                            <i class="fas fa-play"></i> Play All
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>

    <div class="container mt-4">
        <!-- Now Playing -->
        <div id="nowPlaying" class="card mb-4" style="display: none;">
            <div class="card-body">
                <div class="row align-items-center">
                    <div class="col-md-8">
                        <h5 class="mb-1" id="currentTrackTitle">Now Playing</h5>
                        <p class="mb-0 text-muted" id="currentTrackInfo"></p>
                    </div>
                    <div class="col-md-4">
                        <div id="mainPlayerControls" class="d-flex align-items-center justify-content-end">
                            <button class="btn btn-outline-secondary btn-sm me-2" id="prevTrackBtn">
                                <i class="fas fa-step-backward"></i>
                            </button>
                            <button class="btn btn-primary me-2" id="mainPlayPauseBtn">
                                <i class="fas fa-play"></i>
                            </button>
                            <button class="btn btn-outline-secondary btn-sm me-3" id="nextTrackBtn">
                                <i class="fas fa-step-forward"></i>
                            </button>
                            <div class="progress flex-grow-1 me-2" style="height: 8px;">
                                <div class="progress-bar" id="mainProgressBar" style="width: 0%"></div>
                            </div>
                            <small class="text-muted" id="mainTimeDisplay">--:--</small>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Tracks List -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="mb-0">
                    <i class="fas fa-music"></i> Tracks 
                    <span class="badge bg-secondary"><?= number_format($stats['total_tracks']) ?></span>
                </h5>
                <div>
                    <?php if ($stats['total_tracks'] > 0): ?>
                        <span class="text-muted me-3">
                            <?= formatDuration($stats['total_duration']) ?> total • 
                            <?= formatFileSize($stats['total_size_bytes'] ?: 0) ?>
                        </span>
                    <?php endif; ?>
                    <a href="/api/enhanced-feeds.php?type=playlist&id=<?= $playlist['id'] ?>" 
                       class="btn btn-sm btn-outline-warning">
                        <i class="fas fa-rss"></i> RSS Feed
                    </a>
                </div>
            </div>
            <div class="card-body">
                <?php if (empty($tracks)): ?>
                    <div class="text-center py-5">
                        <i class="fas fa-music fa-3x text-muted mb-3"></i>
                        <h4>No tracks yet</h4>
                        <p class="text-muted">Upload audio files to add tracks to this playlist.</p>
                        <a href="/playlists.php" class="btn btn-primary">
                            <i class="fas fa-upload"></i> Upload Tracks
                        </a>
                    </div>
                <?php else: ?>
                    <div class="playlist-tracks" id="playlistTracks">
                        <?php foreach ($tracks as $index => $track): ?>
                            <div class="track-item card mb-2" data-track-index="<?= $index ?>" data-track-id="<?= $track['id'] ?>">
                                <div class="card-body py-2">
                                    <div class="row align-items-center">
                                        <div class="col-md-1 text-center">
                                            <div class="track-number text-muted"><?= str_pad($track['track_number'] ?: ($index + 1), 2, '0', STR_PAD_LEFT) ?></div>
                                        </div>
                                        <div class="col-md-5">
                                            <h6 class="mb-1"><?= h($track['title'] ?: 'Track ' . ($index + 1)) ?></h6>
                                            <?php if ($track['description']): ?>
                                                <small class="text-muted"><?= h($track['description']) ?></small>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <small class="text-muted">
                                                <?= formatDuration($track['duration_seconds']) ?>
                                            </small>
                                        </div>
                                        <div class="col-md-2 text-center">
                                            <small class="text-muted">
                                                <?= formatFileSize($track['file_size_bytes']) ?>
                                            </small>
                                        </div>
                                        <div class="col-md-2 text-end">
                                            <?php if (recordingFileExists($track['filename'])): ?>
                                                <button class="btn btn-sm btn-outline-primary track-play-btn me-1" 
                                                        data-track-url="<?= getRecordingUrl($track['filename']) ?>"
                                                        data-track-title="<?= h($track['title'] ?: 'Track ' . ($index + 1)) ?>"
                                                        data-track-duration="<?= $track['duration_seconds'] ?>">
                                                    <i class="fas fa-play"></i>
                                                </button>
                                                <a href="<?= getRecordingUrl($track['filename']) ?>" 
                                                   class="btn btn-sm btn-outline-secondary" 
                                                   download="<?= h($track['filename']) ?>">
                                                    <i class="fas fa-download"></i>
                                                </a>
                                            <?php else: ?>
                                                <span class="text-muted small">
                                                    <i class="fas fa-exclamation-triangle"></i> Missing
                                                </span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Hidden audio element -->
    <audio id="playlistAudio" preload="metadata"></audio>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="/assets/js/radiograb.js"></script>
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Playlist player functionality
        const audio = document.getElementById('playlistAudio');
        const nowPlaying = document.getElementById('nowPlaying');
        const currentTrackTitle = document.getElementById('currentTrackTitle');
        const currentTrackInfo = document.getElementById('currentTrackInfo');
        const mainPlayPauseBtn = document.getElementById('mainPlayPauseBtn');
        const mainProgressBar = document.getElementById('mainProgressBar');
        const mainTimeDisplay = document.getElementById('mainTimeDisplay');
        const prevTrackBtn = document.getElementById('prevTrackBtn');
        const nextTrackBtn = document.getElementById('nextTrackBtn');
        const playAllBtn = document.getElementById('playAllBtn');
        
        let currentTrackIndex = -1;
        let tracks = [];
        
        // Initialize tracks array
        document.querySelectorAll('.track-play-btn').forEach((btn, index) => {
            tracks.push({
                url: btn.dataset.trackUrl,
                title: btn.dataset.trackTitle,
                duration: parseInt(btn.dataset.trackDuration),
                element: btn.closest('.track-item')
            });
        });
        
        // Play specific track
        function playTrack(index) {
            if (index < 0 || index >= tracks.length) return;
            
            currentTrackIndex = index;
            const track = tracks[index];
            
            audio.src = track.url;
            audio.load();
            audio.play();
            
            // Update UI
            currentTrackTitle.textContent = track.title;
            currentTrackInfo.textContent = `Track ${index + 1} of ${tracks.length}`;
            nowPlaying.style.display = 'block';
            mainPlayPauseBtn.innerHTML = '<i class="fas fa-pause"></i>';
            
            // Highlight current track
            document.querySelectorAll('.track-item').forEach(item => {
                item.classList.remove('bg-light');
            });
            track.element.classList.add('bg-light');
            
            // Update individual play buttons
            document.querySelectorAll('.track-play-btn').forEach((btn, i) => {
                btn.innerHTML = i === index ? '<i class="fas fa-pause"></i>' : '<i class="fas fa-play"></i>';
            });
        }
        
        // Play/pause main control
        mainPlayPauseBtn?.addEventListener('click', function() {
            if (audio.paused) {
                audio.play();
                this.innerHTML = '<i class="fas fa-pause"></i>';
            } else {
                audio.pause();
                this.innerHTML = '<i class="fas fa-play"></i>';
            }
        });
        
        // Previous track
        prevTrackBtn?.addEventListener('click', function() {
            if (currentTrackIndex > 0) {
                playTrack(currentTrackIndex - 1);
            }
        });
        
        // Next track
        nextTrackBtn?.addEventListener('click', function() {
            if (currentTrackIndex < tracks.length - 1) {
                playTrack(currentTrackIndex + 1);
            }
        });
        
        // Play all button
        playAllBtn?.addEventListener('click', function() {
            playTrack(0);
        });
        
        // Individual track play buttons
        document.querySelectorAll('.track-play-btn').forEach((btn, index) => {
            btn.addEventListener('click', function() {
                if (currentTrackIndex === index && !audio.paused) {
                    audio.pause();
                } else {
                    playTrack(index);
                }
            });
        });
        
        // Audio events
        audio.addEventListener('timeupdate', function() {
            if (audio.duration) {
                const progress = (audio.currentTime / audio.duration) * 100;
                mainProgressBar.style.width = progress + '%';
                
                const currentTime = Math.floor(audio.currentTime);
                const totalTime = Math.floor(audio.duration);
                mainTimeDisplay.textContent = `${formatTime(currentTime)} / ${formatTime(totalTime)}`;
            }
        });
        
        audio.addEventListener('ended', function() {
            // Auto-play next track
            if (currentTrackIndex < tracks.length - 1) {
                playTrack(currentTrackIndex + 1);
            } else {
                // Playlist ended
                mainPlayPauseBtn.innerHTML = '<i class="fas fa-play"></i>';
                document.querySelectorAll('.track-item').forEach(item => {
                    item.classList.remove('bg-light');
                });
                document.querySelectorAll('.track-play-btn').forEach(btn => {
                    btn.innerHTML = '<i class="fas fa-play"></i>';
                });
            }
        });
        
        // Helper function to format time
        function formatTime(seconds) {
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            return `${minutes}:${secs.toString().padStart(2, '0')}`;
        }
    });
    </script>
</body>
</html>