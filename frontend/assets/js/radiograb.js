/**
 * RadioGrab JavaScript
 * Frontend functionality for the Radio TiVo application
 */

class RadioGrab {
    constructor() {
        this.currentAudio = null;
        this.init();
    }
    
    init() {
        this.bindEvents();
        this.initAudioPlayers();
        this.autoRefresh();
    }
    
    bindEvents() {
        // Add station form
        const addStationForm = document.getElementById('add-station-form');
        if (addStationForm) {
            addStationForm.addEventListener('submit', this.handleAddStation.bind(this));
        }
        
        // Add show form
        const addShowForm = document.getElementById('add-show-form');
        if (addShowForm) {
            addShowForm.addEventListener('submit', this.handleAddShow.bind(this));
        }
        
        // Station discovery
        const discoverBtn = document.getElementById('discover-station');
        if (discoverBtn) {
            discoverBtn.addEventListener('click', this.discoverStation.bind(this));
        }
        
        // Test recording buttons
        document.querySelectorAll('.test-recording').forEach(btn => {
            btn.addEventListener('click', this.testRecording.bind(this));
        });
        
        // Delete confirmations
        document.querySelectorAll('.delete-confirm').forEach(btn => {
            btn.addEventListener('click', this.confirmDelete.bind(this));
        });
    }
    
    initAudioPlayers() {
        document.querySelectorAll('.audio-player').forEach(player => {
            this.initAudioPlayer(player);
        });
    }
    
    initAudioPlayer(playerElement) {
        const audio = playerElement.querySelector('audio');
        const playBtn = playerElement.querySelector('.play-btn');
        const progressBar = playerElement.querySelector('.progress-bar');
        const timeDisplay = playerElement.querySelector('.time');
        const skipBackBtn = playerElement.querySelector('.skip-back');
        const skipForwardBtn = playerElement.querySelector('.skip-forward');
        
        if (!audio) return;
        
        // Play/pause button
        playBtn?.addEventListener('click', () => {
            if (audio.paused) {
                this.pauseAllAudio();
                audio.play();
                playBtn.innerHTML = '<i class="fas fa-pause"></i>';
                this.currentAudio = audio;
            } else {
                audio.pause();
                playBtn.innerHTML = '<i class="fas fa-play"></i>';
                this.currentAudio = null;
            }
        });
        
        // Progress bar
        audio.addEventListener('timeupdate', () => {
            if (audio.duration) {
                const progress = (audio.currentTime / audio.duration) * 100;
                if (progressBar) {
                    progressBar.style.width = progress + '%';
                }
                
                if (timeDisplay) {
                    const current = this.formatTime(audio.currentTime);
                    const total = this.formatTime(audio.duration);
                    timeDisplay.textContent = `${current} / ${total}`;
                }
            }
        });
        
        // Audio ended
        audio.addEventListener('ended', () => {
            playBtn.innerHTML = '<i class="fas fa-play"></i>';
            if (progressBar) progressBar.style.width = '0%';
            this.currentAudio = null;
        });
        
        // Skip buttons
        skipBackBtn?.addEventListener('click', () => {
            audio.currentTime = Math.max(0, audio.currentTime - 15);
        });
        
        skipForwardBtn?.addEventListener('click', () => {
            audio.currentTime = Math.min(audio.duration, audio.currentTime + 15);
        });
        
        // Click on progress bar to seek
        const progressContainer = playerElement.querySelector('.progress');
        progressContainer?.addEventListener('click', (e) => {
            const rect = progressContainer.getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const width = rect.width;
            const seekTime = (clickX / width) * audio.duration;
            audio.currentTime = seekTime;
        });
    }
    
    pauseAllAudio() {
        document.querySelectorAll('audio').forEach(audio => {
            if (!audio.paused) {
                audio.pause();
                const player = audio.closest('.audio-player');
                const playBtn = player?.querySelector('.play-btn');
                if (playBtn) {
                    playBtn.innerHTML = '<i class="fas fa-play"></i>';
                }
            }
        });
        this.currentAudio = null;
    }
    
    formatTime(seconds) {
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = Math.floor(seconds % 60);
        
        if (hours > 0) {
            return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        } else {
            return `${minutes}:${secs.toString().padStart(2, '0')}`;
        }
    }
    
    async handleAddStation(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Adding...';
        
        try {
            const formData = new FormData(form);
            const response = await fetch('/api/add-station.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert('success', 'Station added successfully!');
                form.reset();
                setTimeout(() => {
                    window.location.href = '/stations.php';
                }, 1000);
            } else {
                this.showAlert('danger', result.error || 'Failed to add station');
            }
        } catch (error) {
            this.showAlert('danger', 'Network error occurred');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }
    
    async handleAddShow(e) {
        e.preventDefault();
        const form = e.target;
        const submitBtn = form.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        
        // Show loading state
        submitBtn.disabled = true;
        submitBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Adding...';
        
        try {
            const formData = new FormData(form);
            const response = await fetch('/api/add-show.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert('success', 'Show added and scheduled successfully!');
                form.reset();
                setTimeout(() => {
                    window.location.href = '/shows.php';
                }, 1000);
            } else {
                this.showAlert('danger', result.error || 'Failed to add show');
            }
        } catch (error) {
            this.showAlert('danger', 'Network error occurred');
        } finally {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        }
    }
    
    async discoverStation() {
        const urlInput = document.getElementById('website_url');
        const discoverBtn = document.getElementById('discover-station');
        const url = urlInput.value.trim();
        
        if (!url) {
            this.showAlert('warning', 'Please enter a website URL first');
            return;
        }
        
        // Show loading state
        const originalText = discoverBtn.textContent;
        discoverBtn.disabled = true;
        discoverBtn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Discovering...';
        
        try {
            const response = await fetch('/api/discover-station.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ url: url })
            });
            
            const result = await response.json();
            
            if (result.success) {
                // Fill in discovered information
                const data = result.data;
                if (data.station_name) {
                    document.getElementById('name').value = data.station_name;
                }
                if (data.stream_url) {
                    document.getElementById('stream_url').value = data.stream_url;
                }
                if (data.logo_url) {
                    document.getElementById('logo_url').value = data.logo_url;
                }
                
                this.showAlert('success', 'Station information discovered successfully!');
            } else {
                this.showAlert('warning', result.error || 'Could not discover station information');
            }
        } catch (error) {
            this.showAlert('danger', 'Discovery failed. Please check the URL and try again.');
        } finally {
            discoverBtn.disabled = false;
            discoverBtn.textContent = originalText;
        }
    }
    
    async testRecording(e) {
        const btn = e.target.closest('.test-recording');
        const showId = btn.dataset.showId;
        const originalText = btn.textContent;
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Testing...';
        
        try {
            const response = await fetch('/api/test-recording.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ show_id: showId })
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert('success', 'Test recording completed successfully!');
            } else {
                this.showAlert('danger', result.error || 'Test recording failed');
            }
        } catch (error) {
            this.showAlert('danger', 'Test recording failed');
        } finally {
            btn.disabled = false;
            btn.textContent = originalText;
        }
    }
    
    confirmDelete(e) {
        const btn = e.target.closest('.delete-confirm');
        const item = btn.dataset.item || 'item';
        
        // Check for various name attributes used across different pages
        const name = btn.dataset.name || 
                    btn.dataset.stationName || 
                    btn.dataset.showName || 
                    btn.dataset.recordingTitle ||
                    'this item';
        
        if (confirm(`Are you sure you want to delete ${item} "${name}"? This action cannot be undone.`)) {
            return true;
        } else {
            e.preventDefault();
            return false;
        }
    }
    
    showAlert(type, message) {
        // Remove existing alerts
        document.querySelectorAll('.alert-auto-dismiss').forEach(alert => {
            alert.remove();
        });
        
        // Create new alert
        const alertDiv = document.createElement('div');
        alertDiv.className = `alert alert-${type} alert-dismissible fade show alert-auto-dismiss`;
        alertDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        // Insert after navbar
        const navbar = document.querySelector('.navbar');
        navbar.insertAdjacentElement('afterend', alertDiv);
        
        // Auto dismiss after 5 seconds
        setTimeout(() => {
            if (alertDiv.parentNode) {
                alertDiv.remove();
            }
        }, 5000);
    }
    
    autoRefresh() {
        // Auto-refresh dashboard every 30 seconds
        if (window.location.pathname === '/' || window.location.pathname === '/index.php') {
            setInterval(() => {
                this.refreshDashboardStats();
            }, 30000);
        }
    }
    
    async refreshDashboardStats() {
        try {
            const response = await fetch('/api/dashboard-stats.php');
            const stats = await response.json();
            
            if (stats.success) {
                // Update statistics cards
                document.querySelectorAll('[data-stat]').forEach(element => {
                    const stat = element.dataset.stat;
                    if (stats.data[stat] !== undefined) {
                        element.textContent = stats.data[stat];
                    }
                });
            }
        } catch (error) {
            console.log('Failed to refresh dashboard stats');
        }
    }
}

// Initialize RadioGrab when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    window.radioGrab = new RadioGrab();
});

// Keyboard shortcuts
document.addEventListener('keydown', (e) => {
    if (e.target.tagName === 'INPUT' || e.target.tagName === 'TEXTAREA') {
        return; // Don't trigger shortcuts when typing
    }
    
    switch (e.key) {
        case ' ': // Spacebar - play/pause
            e.preventDefault();
            if (window.radioGrab.currentAudio) {
                const player = window.radioGrab.currentAudio.closest('.audio-player');
                const playBtn = player?.querySelector('.play-btn');
                if (playBtn) playBtn.click();
            }
            break;
        case 'ArrowLeft': // Left arrow - skip back 15s
            e.preventDefault();
            if (window.radioGrab.currentAudio) {
                window.radioGrab.currentAudio.currentTime -= 15;
            }
            break;
        case 'ArrowRight': // Right arrow - skip forward 15s
            e.preventDefault();
            if (window.radioGrab.currentAudio) {
                window.radioGrab.currentAudio.currentTime += 15;
            }
            break;
    }
});