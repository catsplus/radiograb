/**
 * RadioGrab Playlists JavaScript
 * Handles drag-and-drop uploads, URL uploads, and playlist management
 */

// Global variables
let currentPlaylistId = null;
let currentPlaylistName = '';
let dropZoneActive = false;

document.addEventListener('DOMContentLoaded', function() {
    initializeDropZones();
    initializeModals();
    initializeUploadHandlers();
});

/**
 * Initialize drag-and-drop zones
 */
function initializeDropZones() {
    // Create drop zone overlay
    const dropOverlay = document.createElement('div');
    dropOverlay.id = 'drop-overlay';
    dropOverlay.innerHTML = `
        <div class="drop-message">
            <i class="fas fa-cloud-upload-alt fa-3x mb-3"></i>
            <h3>Drop audio files here to upload</h3>
            <p>Supports MP3, WAV, M4A, AAC, OGG, FLAC</p>
        </div>
    `;
    dropOverlay.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 123, 255, 0.9);
        color: white;
        display: none;
        align-items: center;
        justify-content: center;
        z-index: 9999;
        text-align: center;
    `;
    document.body.appendChild(dropOverlay);

    // Prevent default drag behaviors
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        document.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    // Highlight drop zone when item is dragged over it
    ['dragenter', 'dragover'].forEach(eventName => {
        document.addEventListener(eventName, handleDragEnter, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        document.addEventListener(eventName, handleDragLeave, false);
    });

    function handleDragEnter(e) {
        if (e.dataTransfer.items && e.dataTransfer.items.length > 0) {
            dropZoneActive = true;
            dropOverlay.style.display = 'flex';
        }
    }

    function handleDragLeave(e) {
        // Check if we're really leaving the document
        if (e.clientX === 0 && e.clientY === 0) {
            dropZoneActive = false;
            dropOverlay.style.display = 'none';
        }
    }

    // Handle dropped files
    document.addEventListener('drop', handleDrop, false);

    function handleDrop(e) {
        dropZoneActive = false;
        dropOverlay.style.display = 'none';

        const files = Array.from(e.dataTransfer.files);
        const audioFiles = files.filter(file => file.type.startsWith('audio/'));

        if (audioFiles.length === 0) {
            showAlert('No audio files found in drop', 'warning');
            return;
        }

        if (audioFiles.length === 1) {
            // Single file - show upload modal
            const file = audioFiles[0];
            showUploadModalWithFile(null, 'Select Playlist', file);
        } else {
            // Multiple files - show batch upload modal
            showBatchUploadModal(audioFiles);
        }
    }
}

/**
 * Initialize modal event handlers
 */
function initializeModals() {
    // Create playlist form handler
    const createForm = document.querySelector('#createPlaylistModal form');
    if (createForm) {
        createForm.addEventListener('submit', handleCreatePlaylist);
    }

    // Delete modal handler
    const deleteModal = document.getElementById('deleteModal');
    if (deleteModal) {
        deleteModal.addEventListener('show.bs.modal', function(event) {
            const button = event.relatedTarget;
            const showId = button.getAttribute('data-show-id');
            const showName = button.getAttribute('data-show-name');
            
            document.getElementById('deletePlaylistId').value = showId;
            document.getElementById('deletePlaylistName').textContent = showName;
        });
    }
}

/**
 * Initialize upload handlers
 */
function initializeUploadHandlers() {
    // File input change handler
    const fileInput = document.getElementById('upload_file');
    if (fileInput) {
        fileInput.addEventListener('change', function() {
            if (this.files.length > 0) {
                updateUploadPreview(this.files[0]);
            }
        });
    }
    
    // Upload method radio button handlers
    const methodRadios = document.querySelectorAll('input[name="upload_method"]');
    methodRadios.forEach(radio => {
        radio.addEventListener('change', function() {
            toggleUploadMethod(this.value);
        });
    });
}

/**
 * Toggle between file and URL upload methods
 */
function toggleUploadMethod(method) {
    const fileSection = document.getElementById('file_upload_section');
    const urlSection = document.getElementById('url_upload_section');
    const fileInput = document.getElementById('upload_file');
    const urlInput = document.getElementById('upload_url');
    
    if (method === 'file') {
        fileSection.style.display = 'block';
        urlSection.style.display = 'none';
        fileInput.required = true;
        urlInput.required = false;
        urlInput.value = '';
    } else {
        fileSection.style.display = 'none';
        urlSection.style.display = 'block';
        fileInput.required = false;
        urlInput.required = true;
        fileInput.value = '';
        // Clear upload preview
        document.querySelector('.upload-status').innerHTML = '';
    }
}

/**
 * Show upload modal for specific playlist
 */
function showUploadModal(playlistId, playlistName) {
    currentPlaylistId = playlistId;
    currentPlaylistName = playlistName;
    
    document.getElementById('upload_show_id').value = playlistId;
    document.getElementById('upload_show_name').textContent = playlistName;
    
    // Reset form
    document.getElementById('upload_file').value = '';
    document.getElementById('upload_title').value = '';
    document.getElementById('upload_description').value = '';
    document.querySelector('.upload-progress').style.display = 'none';
    document.querySelector('.upload-status').innerHTML = '';
    
    const modal = new bootstrap.Modal(document.getElementById('uploadModal'));
    modal.show();
}

/**
 * Show upload modal with pre-selected file
 */
function showUploadModalWithFile(playlistId, playlistName, file) {
    // If no specific playlist, show playlist selection
    if (!playlistId) {
        showPlaylistSelectionModal(file);
        return;
    }
    
    showUploadModal(playlistId, playlistName);
    
    // Set the file (create a new FileList)
    const dataTransfer = new DataTransfer();
    dataTransfer.items.add(file);
    document.getElementById('upload_file').files = dataTransfer.files;
    
    updateUploadPreview(file);
}

/**
 * Show playlist selection modal for dropped files
 */
function showPlaylistSelectionModal(file) {
    // Create playlist selection modal if it doesn't exist
    let selectionModal = document.getElementById('playlistSelectionModal');
    if (!selectionModal) {
        selectionModal = createPlaylistSelectionModal();
        document.body.appendChild(selectionModal);
    }
    
    // Populate playlist options
    const playlistSelect = selectionModal.querySelector('#playlist_select');
    playlistSelect.innerHTML = '<option value="">Select a playlist...</option>';
    
    // Get playlists from the page
    document.querySelectorAll('.playlist-card').forEach(card => {
        const title = card.querySelector('.card-title').textContent;
        const playlistId = card.querySelector('[onclick*="showUploadModal"]')
            ?.getAttribute('onclick')?.match(/showUploadModal\((\d+)/)?.[1];
        
        if (playlistId) {
            const option = document.createElement('option');
            option.value = playlistId;
            option.textContent = title;
            playlistSelect.appendChild(option);
        }
    });
    
    // Store the file for later use
    selectionModal.uploadFile = file;
    
    const modal = new bootstrap.Modal(selectionModal);
    modal.show();
}

/**
 * Create playlist selection modal
 */
function createPlaylistSelectionModal() {
    const modal = document.createElement('div');
    modal.className = 'modal fade';
    modal.id = 'playlistSelectionModal';
    modal.innerHTML = `
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title">Select Playlist</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="playlist_select" class="form-label">Choose playlist for upload:</label>
                        <select class="form-select" id="playlist_select" required>
                            <option value="">Select a playlist...</option>
                        </select>
                    </div>
                    <div class="alert alert-info">
                        <i class="fas fa-info-circle"></i>
                        File: <span id="selected_filename"></span>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-primary" onclick="selectPlaylistForUpload()">Continue</button>
                </div>
            </div>
        </div>
    `;
    
    return modal;
}

/**
 * Handle playlist selection for dropped file
 */
function selectPlaylistForUpload() {
    const modal = document.getElementById('playlistSelectionModal');
    const select = modal.querySelector('#playlist_select');
    const playlistId = select.value;
    
    if (!playlistId) {
        showAlert('Please select a playlist', 'warning');
        return;
    }
    
    const playlistName = select.options[select.selectedIndex].text;
    const file = modal.uploadFile;
    
    // Close selection modal
    bootstrap.Modal.getInstance(modal).hide();
    
    // Show upload modal with selected playlist
    showUploadModalWithFile(playlistId, playlistName, file);
}

/**
 * Update upload preview with file info
 */
function updateUploadPreview(file) {
    const preview = document.querySelector('.upload-status');
    preview.innerHTML = `
        <div class="alert alert-info">
            <i class="fas fa-file-audio"></i>
            <strong>Selected:</strong> ${file.name} (${formatFileSize(file.size)})
        </div>
    `;
    
    // Auto-fill title if empty
    const titleInput = document.getElementById('upload_title');
    if (!titleInput.value) {
        const filename = file.name.replace(/\.[^/.]+$/, ""); // Remove extension
        titleInput.value = filename;
    }
}

/**
 * Handle file upload
 */
function handleUpload() {
    const fileInput = document.getElementById('upload_file');
    const urlInput = document.getElementById('upload_url');
    const showId = document.getElementById('upload_show_id').value;
    const title = document.getElementById('upload_title').value;
    const description = document.getElementById('upload_description').value;
    
    if (!showId) {
        showAlert('No playlist selected', 'danger');
        return;
    }
    
    // Check if we have a file or URL
    const hasFile = fileInput.files && fileInput.files.length > 0;
    const hasUrl = urlInput && urlInput.value.trim();
    
    if (!hasFile && !hasUrl) {
        showAlert('Please select a file or enter a URL', 'warning');
        return;
    }
    
    if (hasFile) {
        uploadFile(fileInput.files[0], showId, title, description);
    } else if (hasUrl) {
        uploadUrl(urlInput.value.trim(), showId, title, description);
    }
}

/**
 * Upload file
 */
function uploadFile(file, showId, title, description) {
    const formData = new FormData();
    formData.append('action', 'upload_file');
    formData.append('show_id', showId);
    formData.append('audio_file', file);
    formData.append('title', title);
    formData.append('description', description);
    formData.append('csrf_token', getCSRFToken());
    
    showUploadProgress(true);
    updateUploadStatus('Uploading file...', 'info');
    
    fetch('/api/upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showUploadProgress(false);
        
        if (data.success) {
            updateUploadStatus('Upload successful!', 'success');
            setTimeout(() => {
                location.reload(); // Refresh to show new track
            }, 1500);
        } else {
            updateUploadStatus(`Upload failed: ${data.error}`, 'danger');
        }
    })
    .catch(error => {
        showUploadProgress(false);
        updateUploadStatus(`Upload error: ${error.message}`, 'danger');
    });
}

/**
 * Upload from URL
 */
function uploadUrl(url, showId, title, description) {
    const formData = new FormData();
    formData.append('action', 'upload_url');
    formData.append('show_id', showId);
    formData.append('url', url);
    formData.append('title', title);
    formData.append('description', description);
    formData.append('csrf_token', getCSRFToken());
    
    showUploadProgress(true);
    updateUploadStatus('Downloading from URL...', 'info');
    
    fetch('/api/upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        showUploadProgress(false);
        
        if (data.success) {
            updateUploadStatus('URL upload successful!', 'success');
            setTimeout(() => {
                location.reload(); // Refresh to show new track
            }, 1500);
        } else {
            updateUploadStatus(`URL upload failed: ${data.error}`, 'danger');
        }
    })
    .catch(error => {
        showUploadProgress(false);
        updateUploadStatus(`URL upload error: ${error.message}`, 'danger');
    });
}

/**
 * Show/hide upload progress
 */
function showUploadProgress(show) {
    const progressContainer = document.querySelector('.upload-progress');
    const uploadButton = document.querySelector('#uploadModal .btn-primary');
    
    if (show) {
        progressContainer.style.display = 'block';
        progressContainer.querySelector('.progress-bar').style.width = '100%';
        progressContainer.querySelector('.progress-bar').classList.add('progress-bar-animated');
        uploadButton.disabled = true;
        uploadButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Uploading...';
    } else {
        progressContainer.style.display = 'none';
        uploadButton.disabled = false;
        uploadButton.innerHTML = '<i class="fas fa-upload"></i> Upload';
    }
}

/**
 * Update upload status message
 */
function updateUploadStatus(message, type) {
    const statusDiv = document.querySelector('.upload-status');
    const alertClass = `alert-${type}`;
    
    statusDiv.innerHTML = `<div class="alert ${alertClass}">${message}</div>`;
}

/**
 * Handle create playlist form submission
 */
function handleCreatePlaylist(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    
    fetch('/api/upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showAlert('Playlist created successfully!', 'success');
            setTimeout(() => location.reload(), 1000);
        } else {
            showAlert(`Failed to create playlist: ${data.error}`, 'danger');
        }
    })
    .catch(error => {
        showAlert(`Error: ${error.message}`, 'danger');
    });
}

/**
 * Show playlist management modal
 */
function showPlaylistModal(playlistId) {
    const modal = new bootstrap.Modal(document.getElementById('playlistModal'));
    modal.show();
    
    // Load playlist tracks
    loadPlaylistTracks(playlistId);
}

/**
 * Load playlist tracks for management
 */
function loadPlaylistTracks(playlistId) {
    const contentDiv = document.getElementById('playlistContent');
    contentDiv.innerHTML = '<p class="text-center"><i class="fas fa-spinner fa-spin"></i> Loading tracks...</p>';
    
    fetch(`/api/playlist-tracks.php?show_id=${playlistId}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderPlaylistTracks(data.tracks, playlistId);
            } else {
                contentDiv.innerHTML = `<div class="alert alert-danger">Failed to load tracks: ${data.error}</div>`;
            }
        })
        .catch(error => {
            contentDiv.innerHTML = `<div class="alert alert-danger">Error loading tracks: ${error.message}</div>`;
        });
}

/**
 * Render playlist tracks with drag-and-drop ordering
 */
function renderPlaylistTracks(tracks, playlistId) {
    const contentDiv = document.getElementById('playlistContent');
    
    if (tracks.length === 0) {
        contentDiv.innerHTML = `
            <div class="text-center py-4">
                <i class="fas fa-music fa-2x text-muted mb-3"></i>
                <p class="text-muted">No tracks in this playlist yet.</p>
            </div>
        `;
        return;
    }
    
    const tracksList = tracks.map((track, index) => `
        <div class="playlist-track p-3 border rounded mb-2" data-track-id="${track.id}" data-track-number="${track.track_number || index + 1}">
            <div class="d-flex align-items-center">
                <div class="me-3">
                    <i class="fas fa-grip-vertical text-muted"></i>
                </div>
                <div class="me-3">
                    <span class="badge bg-secondary">${String(track.track_number || index + 1).padStart(2, '0')}</span>
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-1">${escapeHtml(track.title)}</h6>
                    ${track.description ? `<small class="text-muted">${escapeHtml(track.description)}</small>` : ''}
                    <div class="small text-muted">
                        <i class="fas fa-clock"></i> ${formatDuration(track.duration_seconds)}
                        <span class="mx-2">•</span>
                        <i class="fas fa-hdd"></i> ${formatFileSize(track.file_size_bytes)}
                    </div>
                </div>
                <div class="text-end">
                    <button class="btn btn-sm btn-outline-danger" onclick="deleteTrack(${track.id})">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            </div>
        </div>
    `).join('');
    
    contentDiv.innerHTML = `
        <div class="mb-3">
            <h6><i class="fas fa-list"></i> Playlist Tracks (${tracks.length})</h6>
            <small class="text-muted">Drag tracks to reorder them</small>
        </div>
        <div id="sortable-tracks">
            ${tracksList}
        </div>
    `;
    
    // Initialize sortable if available
    if (typeof Sortable !== 'undefined') {
        new Sortable(document.getElementById('sortable-tracks'), {
            animation: 150,
            handle: '.fa-grip-vertical',
            onEnd: function(evt) {
                updateTrackOrder();
            }
        });
    }
}

/**
 * Update track order after drag-and-drop
 */
function updateTrackOrder() {
    const tracks = document.querySelectorAll('#sortable-tracks .playlist-track');
    const trackOrder = Array.from(tracks).map((track, index) => ({
        id: parseInt(track.dataset.trackId),
        track_number: index + 1
    }));
    
    // Enable save button
    document.getElementById('savePlaylistOrder').disabled = false;
    document.getElementById('savePlaylistOrder').classList.add('btn-warning');
    document.getElementById('savePlaylistOrder').innerHTML = '<i class="fas fa-save"></i> Save Changes';
}

/**
 * Delete a track from playlist
 */
function deleteTrack(trackId) {
    if (!confirm('Are you sure you want to delete this track?')) {
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'delete_upload');
    formData.append('recording_id', trackId);
    formData.append('csrf_token', getCSRFToken());
    
    fetch('/api/upload.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove track from display
            document.querySelector(`[data-track-id="${trackId}"]`).remove();
            showAlert('Track deleted successfully', 'success');
        } else {
            showAlert(`Failed to delete track: ${data.error}`, 'danger');
        }
    })
    .catch(error => {
        showAlert(`Error: ${error.message}`, 'danger');
    });
}

/**
 * Utility functions
 */
function getCSRFToken() {
    const tokenInput = document.querySelector('input[name="csrf_token"]');
    return tokenInput ? tokenInput.value : '';
}

function formatFileSize(bytes) {
    if (bytes === 0) return '0 Bytes';
    const k = 1024;
    const sizes = ['Bytes', 'KB', 'MB', 'GB'];
    const i = Math.floor(Math.log(bytes) / Math.log(k));
    return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
}

function formatDuration(seconds) {
    if (!seconds) return '0:00';
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return mins + ':' + String(secs).padStart(2, '0');
}

function escapeHtml(text) {
    const div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

function showAlert(message, type) {
    // Create or update alert
    let alertDiv = document.getElementById('dynamic-alert');
    if (!alertDiv) {
        alertDiv = document.createElement('div');
        alertDiv.id = 'dynamic-alert';
        alertDiv.style.cssText = 'position: fixed; top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        document.body.appendChild(alertDiv);
    }
    
    alertDiv.innerHTML = `
        <div class="alert alert-${type} alert-dismissible fade show">
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        </div>
    `;
    
    // Auto-dismiss after 5 seconds
    setTimeout(() => {
        const alert = alertDiv.querySelector('.alert');
        if (alert) {
            bootstrap.Alert.getOrCreateInstance(alert).close();
        }
    }, 5000);
}