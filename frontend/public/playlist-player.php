<?php
/**
 * RadioGrab Playlist Player
 * Dedicated audio player for playlist tracks
 */

session_start();
require_once '../includes/database.php';
require_once '../includes/functions.php';

$playlist_id = (int)($_GET['id'] ?? 0);

if (!$playlist_id) {
    header('Location: /playlists.php');
    exit;
}

try {
    // Get playlist information
    $playlist = $db->fetchOne("
        SELECT s.*, st.name as station_name, st.call_letters 
        FROM shows s 
        JOIN stations st ON s.station_id = st.id 
        WHERE s.id = ? AND s.show_type = 'playlist'
    ", [$playlist_id]);
    
    if (!$playlist) {
        setFlashMessage('danger', 'Playlist not found');
        header('Location: /playlists.php');
        exit;
    }
    
    // Get playlist tracks
    $tracks = $db->fetchAll("
        SELECT r.*, s.name as show_name, st.name as station_name 
        FROM recordings r 
        JOIN shows s ON r.show_id = s.id 
        JOIN stations st ON s.station_id = st.id 
        WHERE r.show_id = ? AND r.source_type = 'uploaded'
        ORDER BY r.track_number ASC, r.recorded_at ASC
    ", [$playlist_id]);
    
} catch (Exception $e) {
    setFlashMessage('danger', 'Error loading playlist: ' . $e->getMessage());
    header('Location: /playlists.php');
    exit;
}

$page_title = 'Play Playlist: ' . $playlist['name'];

// Custom CSS for playlist player
$additional_css = '
<style>
    .playlist-player {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 2rem 0;
        margin-bottom: 2rem;
    }
    .now-playing-card {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border: 1px solid rgba(255, 255, 255, 0.2);
        border-radius: 15px;
    }
    .track-list {
        max-height: 400px;
        overflow-y: auto;
    }
    .track-item {
        cursor: pointer;
        transition: background-color 0.2s;
    }
    .track-item:hover {
        background-color: rgba(0, 123, 255, 0.1);
    }
    .track-item.active {
        background-color: rgba(0, 123, 255, 0.2);
        border-left: 4px solid #007bff;
    }
    .audio-controls {
        background: rgba(255, 255, 255, 0.1);
        backdrop-filter: blur(10px);
        border-radius: 10px;
        padding: 1rem;
    }
    .progress-container {
        height: 6px;
        background: rgba(255, 255, 255, 0.3);
        border-radius: 3px;
        overflow: hidden;
        cursor: pointer;
    }
    .progress-bar {
        height: 100%;
        background: #007bff;
        border-radius: 3px;
        transition: width 0.1s;
    }
    .control-btn {
        background: rgba(255, 255, 255, 0.2);
        border: 1px solid rgba(255, 255, 255, 0.3);
        color: white;
        border-radius: 50%;
        width: 50px;
        height: 50px;
        display: flex;
        align-items: center;
        justify-content: center;
        transition: all 0.2s;
    }
    .control-btn:hover {
        background: rgba(255, 255, 255, 0.3);
        color: white;
        transform: scale(1.05);
    }
    .control-btn.play-btn {
        width: 60px;
        height: 60px;
        font-size: 1.2rem;
    }
</style>';

require_once '../includes/header.php';
?>

<div class="playlist-player">
    <div class="container">
        <div class="row align-items-center">
            <div class="col-md-8">
                <h1><i class="fas fa-list-music"></i> <?= h($playlist['name']) ?></h1>
                <p class="mb-0">
                    <i class="fas fa-radio"></i> <?= h($playlist['station_name']) ?> 
                    <span class="mx-2">•</span>
                    <i class="fas fa-music"></i> <?= count($tracks) ?> tracks
                </p>
            </div>
            <div class="col-md-4 text-md-end">
                <a href="/playlists.php" class="btn btn-outline-light">
                    <i class="fas fa-arrow-left"></i> Back to Playlists
                </a>
            </div>
        </div>
    </div>
</div>

<div class="container">
    <?php if (empty($tracks)): ?>
        <div class="text-center py-5">
            <i class="fas fa-music fa-3x text-muted mb-3"></i>
            <h3>No tracks in this playlist</h3>
            <p class="text-muted mb-4">Upload some tracks to start listening!</p>
            <a href="/playlists.php" class="btn btn-primary">
                <i class="fas fa-upload"></i> Upload Tracks
            </a>
        </div>
    <?php else: ?>
        <div class="row">
            <!-- Now Playing -->
            <div class="col-lg-8">
                <div class="card now-playing-card mb-4">
                    <div class="card-body text-center">
                        <h5 class="card-title mb-3">Now Playing</h5>
                        <h3 id="current-track-title">Select a track to play</h3>
                        <p id="current-track-info" class="text-muted"></p>
                        
                        <!-- Audio Element (hidden) -->
                        <audio id="playlist-audio" preload="metadata">
                            Your browser does not support the audio element.
                        </audio>
                        
                        <!-- Audio Controls -->
                        <div class="audio-controls mt-4">
                            <div class="d-flex justify-content-center align-items-center mb-3">
                                <button class="btn control-btn me-3" id="prev-btn">
                                    <i class="fas fa-step-backward"></i>
                                </button>
                                <button class="btn control-btn play-btn me-3" id="play-btn">
                                    <i class="fas fa-play"></i>
                                </button>
                                <button class="btn control-btn" id="next-btn">
                                    <i class="fas fa-step-forward"></i>
                                </button>
                            </div>
                            
                            <!-- Progress Bar -->
                            <div class="progress-container mb-3" id="progress-container">
                                <div class="progress-bar" id="progress-bar"></div>
                            </div>
                            
                            <!-- Time Display -->
                            <div class="d-flex justify-content-between small">
                                <span id="current-time">0:00</span>
                                <span id="total-time">0:00</span>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Track List -->
            <div class="col-lg-4">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="fas fa-list"></i> Playlist Tracks</h5>
                    </div>
                    <div class="card-body p-0">
                        <div class="track-list">
                            <?php foreach ($tracks as $index => $track): ?>
                                <div class="track-item p-3 border-bottom" 
                                     data-track-index="<?= $index ?>"
                                     data-track-url="<?= getRecordingUrl($track['filename']) ?>"
                                     data-track-title="<?= h($track['title']) ?>"
                                     data-track-duration="<?= $track['duration_seconds'] ?>">
                                    <div class="d-flex align-items-center">
                                        <div class="me-3">
                                            <span class="badge bg-secondary"><?= str_pad($track['track_number'] ?: $index + 1, 2, '0', STR_PAD_LEFT) ?></span>
                                        </div>
                                        <div class="flex-grow-1">
                                            <h6 class="mb-1"><?= h($track['title']) ?></h6>
                                            <?php if ($track['description']): ?>
                                                <small class="text-muted"><?= h($track['description']) ?></small>
                                            <?php endif; ?>
                                            <div class="small text-muted">
                                                <i class="fas fa-clock"></i> <?= formatDuration($track['duration_seconds']) ?>
                                            </div>
                                        </div>
                                        <div class="text-end">
                                            <i class="fas fa-play-circle text-primary play-icon"></i>
                                        </div>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    <?php endif; ?>
</div>

<?php if (!empty($tracks)): ?>
<script>
class PlaylistPlayer {
    constructor() {
        this.audio = document.getElementById('playlist-audio');
        this.tracks = <?= json_encode(array_values($tracks)) ?>;
        this.currentTrackIndex = 0;
        this.isPlaying = false;
        
        this.initElements();
        this.bindEvents();
        this.loadTrack(0);
    }
    
    initElements() {
        this.playBtn = document.getElementById('play-btn');
        this.prevBtn = document.getElementById('prev-btn');
        this.nextBtn = document.getElementById('next-btn');
        this.progressContainer = document.getElementById('progress-container');
        this.progressBar = document.getElementById('progress-bar');
        this.currentTimeEl = document.getElementById('current-time');
        this.totalTimeEl = document.getElementById('total-time');
        this.trackTitleEl = document.getElementById('current-track-title');
        this.trackInfoEl = document.getElementById('current-track-info');
        this.trackItems = document.querySelectorAll('.track-item');
    }
    
    bindEvents() {
        // Control buttons
        this.playBtn.addEventListener('click', () => this.togglePlay());
        this.prevBtn.addEventListener('click', () => this.previousTrack());
        this.nextBtn.addEventListener('click', () => this.nextTrack());
        
        // Progress bar
        this.progressContainer.addEventListener('click', (e) => this.seek(e));
        
        // Audio events
        this.audio.addEventListener('timeupdate', () => this.updateProgress());
        this.audio.addEventListener('ended', () => this.nextTrack());
        this.audio.addEventListener('loadedmetadata', () => this.updateDuration());
        
        // Track selection
        this.trackItems.forEach((item, index) => {
            item.addEventListener('click', () => this.loadTrack(index));
        });
        
        // Keyboard shortcuts
        document.addEventListener('keydown', (e) => {
            if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') return;
            
            switch (e.key) {
                case ' ':
                    e.preventDefault();
                    this.togglePlay();
                    break;
                case 'ArrowLeft':
                    e.preventDefault();
                    this.audio.currentTime -= 15;
                    break;
                case 'ArrowRight':
                    e.preventDefault();
                    this.audio.currentTime += 15;
                    break;
            }
        });
    }
    
    loadTrack(index) {
        if (index < 0 || index >= this.tracks.length) return;
        
        this.currentTrackIndex = index;
        const track = this.tracks[index];
        
        // Update audio source
        this.audio.src = `<?= getRecordingUrl('') ?>${track.filename}`;
        
        // Update UI
        this.trackTitleEl.textContent = track.title;
        this.trackInfoEl.textContent = track.description || 'Track ' + (index + 1);
        
        // Update track list visual state
        this.trackItems.forEach((item, i) => {
            item.classList.toggle('active', i === index);
        });
        
        // Reset progress
        this.progressBar.style.width = '0%';
        this.currentTimeEl.textContent = '0:00';
        
        // Auto-play if currently playing
        if (this.isPlaying) {
            this.audio.play().catch(e => console.log('Play failed:', e));
        }
    }
    
    togglePlay() {
        if (this.audio.paused) {
            this.audio.play().then(() => {
                this.isPlaying = true;
                this.playBtn.innerHTML = '<i class="fas fa-pause"></i>';
            }).catch(e => console.log('Play failed:', e));
        } else {
            this.audio.pause();
            this.isPlaying = false;
            this.playBtn.innerHTML = '<i class="fas fa-play"></i>';
        }
    }
    
    previousTrack() {
        this.loadTrack(this.currentTrackIndex - 1);
    }
    
    nextTrack() {
        this.loadTrack(this.currentTrackIndex + 1);
    }
    
    seek(e) {
        const rect = this.progressContainer.getBoundingClientRect();
        const clickX = e.clientX - rect.left;
        const width = rect.width;
        const seekTime = (clickX / width) * this.audio.duration;
        this.audio.currentTime = seekTime;
    }
    
    updateProgress() {
        if (this.audio.duration) {
            const progress = (this.audio.currentTime / this.audio.duration) * 100;
            this.progressBar.style.width = progress + '%';
            this.currentTimeEl.textContent = this.formatTime(this.audio.currentTime);
        }
    }
    
    updateDuration() {
        if (this.audio.duration) {
            this.totalTimeEl.textContent = this.formatTime(this.audio.duration);
        }
    }
    
    formatTime(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = Math.floor(seconds % 60);
        return mins + ':' + secs.toString().padStart(2, '0');
    }
}

// Initialize player when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    new PlaylistPlayer();
});
</script>
<?php endif; ?>

<?php
require_once '../includes/footer.php';
?>