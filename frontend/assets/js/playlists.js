/**
 * RadioGrab Playlist Management JavaScript
 * Handles playlist functionality including uploads, drag-and-drop, and track management
 */

// Handle delete modal
document.addEventListener("DOMContentLoaded", function() {
    const deleteModal = document.getElementById("deleteModal");
    if (deleteModal) {
        deleteModal.addEventListener("show.bs.modal", function(event) {
            const button = event.relatedTarget;
            const playlistId = button.getAttribute("data-show-id");
            const playlistName = button.getAttribute("data-show-name");

            document.getElementById("deletePlaylistId").value = playlistId;
            document.getElementById("deletePlaylistName").textContent = playlistName;
        });
    }
});

// Upload functionality with progress tracking
let currentUploadFormData = null;
let currentUploadModal = null;

function showUploadModal(showId, showName) {
    currentUploadFormData = new FormData();
    currentUploadFormData.append("show_id", showId);
    currentUploadFormData.append("action", "upload_file");
    
    document.getElementById("upload_show_id").value = showId;
    document.getElementById("upload_show_name").textContent = showName;
    document.getElementById("upload_title").value = "";
    document.getElementById("upload_description").value = "";
    document.getElementById("upload_file").value = "";
    
    // Reset progress and status
    const progressDiv = document.querySelector(".upload-progress");
    const statusDiv = document.querySelector(".upload-status");
    const progressBar = document.querySelector(".progress-bar");
    
    progressDiv.style.display = "none";
    statusDiv.innerHTML = "";
    progressBar.style.width = "0%";
    progressBar.textContent = "0%";
    
    currentUploadModal = new bootstrap.Modal(document.getElementById("uploadModal"));
    currentUploadModal.show();
}

function handleUpload() {
    const fileInput = document.getElementById("upload_file");
    const title = document.getElementById("upload_title").value;
    const description = document.getElementById("upload_description").value;
    const csrfToken = document.querySelector("input[name=csrf_token]").value;
    
    if (!fileInput.files[0]) {
        alert("Please select a file to upload");
        return;
    }
    
    // Add form data
    currentUploadFormData.append("audio_file", fileInput.files[0]);
    currentUploadFormData.append("title", title);
    currentUploadFormData.append("description", description);
    currentUploadFormData.append("csrf_token", csrfToken);
    
    // Show progress
    const progressDiv = document.querySelector(".upload-progress");
    const statusDiv = document.querySelector(".upload-status");
    const progressBar = document.querySelector(".progress-bar");
    
    progressDiv.style.display = "block";
    statusDiv.innerHTML = "Uploading...";
    
    const xhr = new XMLHttpRequest();
    
    xhr.upload.addEventListener("progress", function(e) {
        if (e.lengthComputable) {
            const percentComplete = (e.loaded / e.total) * 100;
            progressBar.style.width = percentComplete + "%";
            progressBar.textContent = Math.round(percentComplete) + "%";
        }
    });
    
    xhr.addEventListener("load", function() {
        if (xhr.status === 200) {
            try {
                const data = JSON.parse(xhr.responseText);
                if (data.success) {
                    statusDiv.innerHTML = '<i class="fas fa-check-circle text-success"></i> Upload successful!';
                    setTimeout(() => {
                        currentUploadModal.hide();
                        location.reload(); // Refresh to show new upload
                    }, 2000);
                } else {
                    statusDiv.innerHTML = '<i class="fas fa-times-circle text-danger"></i> ' + data.error;
                }
            } catch (e) {
                statusDiv.innerHTML = '<i class="fas fa-times-circle text-danger"></i> Upload failed: ' + error.message;
            }
        } else {
            statusDiv.innerHTML = '<i class="fas fa-times-circle text-danger"></i> Upload failed (' + xhr.status + ')';
        }
    });
    
    xhr.addEventListener("error", function() {
        statusDiv.innerHTML = '<i class="fas fa-times-circle text-danger"></i> Upload failed - network error';
    });
    
    xhr.open("POST", "/api/upload.php");
    xhr.send(currentUploadFormData);
}

// Playlist management functionality
function showPlaylistModal(showId) {
    const modal = new bootstrap.Modal(document.getElementById("playlistModal"));
    const content = document.getElementById("playlistContent");
    
    // Show loading
    content.innerHTML = "<p>Loading playlist tracks...</p>";
    modal.show();
    
    // Load tracks
    fetch("/api/playlist-tracks.php?show_id=" + showId)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                renderPlaylistTracks(data.tracks, data.show);
            } else {
                content.innerHTML = '<div class="alert alert-danger">' + data.error + '</div>';
            }
        })
        .catch(error => {
            content.innerHTML = '<div class="alert alert-danger">Failed to load tracks: ' + error.message + '</div>';
        });
}

function renderPlaylistTracks(tracks, show) {
    const content = document.getElementById("playlistContent");
    const modalTitle = document.getElementById("playlistModalLabel");
    
    modalTitle.textContent = "Manage Playlist: " + show.name;
    
    if (tracks.length === 0) {
        content.innerHTML = "<p>No tracks uploaded yet. Use the Upload button to add tracks.</p>";
        return;
    }
    
    let html = '<div class="table-responsive"><table class="table table-hover"><thead><tr><th style="width: 80px;">Order</th><th>Track</th><th style="width: 100px;">Duration</th><th style="width: 120px;">Uploaded</th><th style="width: 100px;">Actions</th></tr></thead><tbody id="playlist-tracks" class="sortable">';
    
    tracks.forEach((track, index) => {
        html += '<tr data-track-id="' + track.id + '" class="playlist-track">';
        html += '<td><input type="number" class="form-control form-control-sm track-number" value="' + track.track_number + '" min="1" style="width: 60px;"></td>';
        html += '<td><strong>' + escapeHtml(track.title) + '</strong>';
        if (track.description) {
            html += '<br><small class="text-muted">' + escapeHtml(track.description) + '</small>';
        }
        html += '</td>';
        html += '<td>' + formatDuration(track.duration_seconds) + '</td>';
        html += '<td>' + timeAgo(track.recorded_at) + '</td>';
        html += '<td><button class="btn btn-sm btn-outline-danger delete-track" data-track-id="' + track.id + '" data-track-title="' + escapeHtml(track.title) + '"><i class="fas fa-trash"></i></button></td>';
        html += '</tr>';
    });
    
    html += '</tbody></table></div>';
    
    content.innerHTML = html;
    
    // Initialize sortable
    initializeSortable();
    
    // Add delete handlers
    document.querySelectorAll(".delete-track").forEach(btn => {
        btn.addEventListener("click", function() {
            const trackId = this.getAttribute("data-track-id");
            const trackTitle = this.getAttribute("data-track-title");
            
            if (confirm("Delete track \"" + trackTitle + "\"?")) {
                deleteTrack(trackId);
            }
        });
    });
}

function deleteTrack(trackId) {
    const csrfToken = document.querySelector("input[name=csrf_token]").value;
    
    fetch("/api/upload.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/x-www-form-urlencoded",
        },
        body: "action=delete_upload&recording_id=" + trackId + "&csrf_token=" + encodeURIComponent(csrfToken)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Remove the track row from the table
            const trackRow = document.querySelector('[data-track-id="' + trackId + '"]');
            if (trackRow) {
                trackRow.remove();
                updateTrackOrder(); // Renumber remaining tracks
            }
        } else {
            alert("Failed to delete track: " + data.error);
        }
    })
    .catch(error => {
        alert("Network error: " + error.message);
    });
}

function escapeHtml(text) {
    const div = document.createElement("div");
    div.textContent = text;
    return div.innerHTML;
}

function formatDuration(seconds) {
    if (!seconds) return "0:00";
    const mins = Math.floor(seconds / 60);
    const secs = seconds % 60;
    return mins + ":" + secs.toString().padStart(2, "0");
}

function timeAgo(dateString) {
    const now = new Date();
    const date = new Date(dateString);
    const diffInSeconds = Math.floor((now - date) / 1000);
    
    if (diffInSeconds < 60) return "Just now";
    if (diffInSeconds < 3600) return Math.floor(diffInSeconds / 60) + " minutes ago";
    if (diffInSeconds < 86400) return Math.floor(diffInSeconds / 3600) + " hours ago";
    return Math.floor(diffInSeconds / 86400) + " days ago";
}

// Description toggle functions
function toggleDescription(playlistId) {
    const shortDiv = document.getElementById("desc-short-" + playlistId);
    const fullDiv = document.getElementById("desc-full-" + playlistId);
    const toggleBtn = document.getElementById("desc-toggle-" + playlistId);
    
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
    document.getElementById("tags-display-" + playlistId).style.display = "none";
    document.getElementById("tags-edit-" + playlistId).style.display = "block";
    document.getElementById("edit-tags-btn-" + playlistId).style.display = "none";
    document.getElementById("tags-input-" + playlistId).focus();
}

function cancelEditTags(playlistId) {
    document.getElementById("tags-display-" + playlistId).style.display = "block";
    document.getElementById("tags-edit-" + playlistId).style.display = "none";
    document.getElementById("edit-tags-btn-" + playlistId).style.display = "inline-block";
}

function saveTags(playlistId) {
    const input = document.getElementById("tags-input-" + playlistId);
    const tags = input.value.trim();
    
    // Here you would typically send an AJAX request to save the tags
    // For now, just update the display
    document.getElementById("tags-display-" + playlistId).textContent = tags || "No tags";
    cancelEditTags(playlistId);
}

// Drag and drop functionality
function initializeSortable() {
    const tbody = document.getElementById("playlist-tracks");
    if (!tbody) return;
    
    // Check if Sortable is available
    if (typeof Sortable === 'undefined') {
        console.warn('Sortable.js not loaded - drag and drop functionality disabled');
        return;
    }
    
    new Sortable(tbody, {
        animation: 150,
        ghostClass: "sortable-ghost",
        handle: ".playlist-track",
        onEnd: function(evt) {
            updateTrackOrder();
        }
    });
}

function updateTrackOrder() {
    const tracks = document.querySelectorAll(".playlist-track");
    const updates = [];
    
    tracks.forEach((track, index) => {
        const trackId = track.getAttribute("data-track-id");
        const newOrder = index + 1;
        
        // Update the input field
        const numberInput = track.querySelector(".track-number");
        numberInput.value = newOrder;
        
        updates.push({
            id: parseInt(trackId),
            track_number: newOrder
        });
    });
    
    // Save to database
    saveTrackOrder(updates);
}

function saveTrackOrder(updates) {
    const csrfToken = document.querySelector("input[name=csrf_token]").value;
    const saveButton = document.getElementById("savePlaylistOrder");
    
    if (!saveButton) return; // Button might not exist if modal is closed
    
    const originalContent = saveButton.innerHTML;
    
    saveButton.disabled = true;
    saveButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    
    fetch("/api/playlist-tracks.php", {
        method: "POST",
        headers: {
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            action: "update_order",
            updates: updates,
            csrf_token: csrfToken
        })
    })
    .then(response => response.json())
    .then(data => {
        saveButton.disabled = false;
        saveButton.innerHTML = originalContent;
        
        if (data.success) {
            // Success feedback could be added here
        } else {
            alert("Failed to save order: " + data.error);
        }
    })
    .catch(error => {
        saveButton.disabled = false;
        saveButton.innerHTML = originalContent;
        alert("Network error: " + error.message);
    });
}