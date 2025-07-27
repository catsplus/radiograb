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
        // Handle both show-based and station-based test recordings
        const showId = btn.dataset.showId;
        const stationId = btn.dataset.stationId;
        const stationName = btn.dataset.stationName || 'Station';
        const originalText = btn.textContent;
        
        btn.disabled = true;
        btn.innerHTML = '<span class="spinner-border spinner-border-sm"></span> Testing...';
        
        try {
            // Get CSRF token with session credentials
            const csrfResponse = await fetch('/api/get-csrf-token.php', {
                credentials: 'same-origin'
            });
            const csrfData = await csrfResponse.json();
            
            if (!csrfData.csrf_token) {
                throw new Error('Failed to get CSRF token');
            }
            
            // Prepare request body based on whether we have show_id or station_id
            const requestBody = new URLSearchParams({
                action: 'test_recording',
                csrf_token: csrfData.csrf_token
            });
            
            if (showId) {
                requestBody.append('show_id', showId);
            } else if (stationId) {
                requestBody.append('station_id', stationId);
            } else {
                throw new Error('No show_id or station_id provided');
            }
            
            // Use the detailed status API for better error reporting
            const response = await fetch('/api/test-recording-status.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                credentials: 'same-origin',
                body: requestBody
            });
            
            const result = await response.json();
            
            if (result.success) {
                this.showAlert('success', `Test recording completed successfully for ${stationName}!`);
                
                // Auto-refresh test recordings if we're on stations page
                if (window.location.pathname === '/stations.php') {
                    setTimeout(() => {
                        if (typeof loadTestRecordings === 'function') {
                            loadTestRecordings();
                        }
                    }, 2000);
                }
            } else {
                // Show detailed error popup for test failures
                this.showTestFailureDetails(stationName, result);
            }
        } catch (error) {
            console.error('Test recording error:', error);
            this.showAlert('danger', 'Test recording failed: ' + error.message);
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
    
    showTestFailureDetails(stationName, result) {
        // Create modal if it doesn't exist
        let modal = document.getElementById('testFailureModal');
        if (!modal) {
            modal = document.createElement('div');
            modal.id = 'testFailureModal';
            modal.className = 'modal fade';
            modal.innerHTML = `
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header bg-danger text-white">
                            <h5 class="modal-title">
                                <i class="fas fa-exclamation-triangle"></i> Test Recording Failed
                            </h5>
                            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div id="testFailureContent"></div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                        </div>
                    </div>
                </div>
            `;
            document.body.appendChild(modal);
        }
        
        // Build detailed error content
        let errorContent = `
            <div class="mb-3">
                <h6><i class="fas fa-radio"></i> Station: ${stationName}</h6>
                <div class="alert alert-danger">
                    <strong>Primary Error:</strong> ${result.error || 'Test recording failed'}
                </div>
            </div>
        `;
        
        // Add detailed diagnostics if available
        if (result.debug || result.details) {
            errorContent += `
                <div class="mb-3">
                    <h6><i class="fas fa-info-circle"></i> Diagnostic Information</h6>
                    <div class="card">
                        <div class="card-body">
                            <pre class="mb-0" style="white-space: pre-wrap; font-size: 0.9em;">${result.debug || result.details}</pre>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Add stream discovery information if available
        if (result.stream_discovery_attempted) {
            errorContent += `
                <div class="mb-3">
                    <h6><i class="fas fa-search"></i> Stream Discovery</h6>
                    <div class="alert alert-info">
                        <strong>Status:</strong> ${result.stream_discovery_attempted ? 'Attempted' : 'Not attempted'}<br>
                        ${result.stream_discovery_result ? '<strong>Result:</strong> ' + result.stream_discovery_result : ''}
                    </div>
                </div>
            `;
        }
        
        // Add User-Agent testing information if available
        if (result.user_agent_tested) {
            errorContent += `
                <div class="mb-3">
                    <h6><i class="fas fa-user-secret"></i> User-Agent Testing</h6>
                    <div class="alert alert-warning">
                        <strong>Status:</strong> ${result.user_agent_tested ? 'Multiple User-Agents tested' : 'Default User-Agent only'}<br>
                        ${result.user_agent_result ? '<strong>Result:</strong> ' + result.user_agent_result : ''}
                    </div>
                </div>
            `;
        }
        
        // Add suggested actions
        errorContent += `
            <div class="mb-3">
                <h6><i class="fas fa-lightbulb"></i> Suggested Actions</h6>
                <div class="card">
                    <div class="card-body">
                        <ul class="mb-0">
                            <li>Check if the stream URL is still valid</li>
                            <li>Verify the station's website is accessible</li>
                            <li>Try the "Discover Station" feature to find an updated stream URL</li>
                            <li>Contact the station to confirm their streaming configuration</li>
                        </ul>
                    </div>
                </div>
            </div>
        `;
        
        // Update modal content
        document.getElementById('testFailureContent').innerHTML = errorContent;
        
        // Show modal
        const bsModal = new bootstrap.Modal(modal);
        bsModal.show();
        
        // Also show a brief alert
        this.showAlert('danger', `Test recording failed for ${stationName}. Click for details.`);
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