/**
 * RadioGrab Audio Recorder
 * Handles DJ voice snippet recording using WebRTC MediaRecorder API
 */

class AudioRecorder {
    constructor() {
        this.mediaRecorder = null;
        this.audioStream = null;
        this.audioChunks = [];
        this.isRecording = false;
        this.recordingStartTime = null;
        this.playlistId = null;
        this.maxRecordingTime = 300; // 5 minutes max
        this.recordingTimer = null;
        
        // Check browser compatibility
        this.isSupported = this.checkBrowserSupport();
    }
    
    /**
     * Check if browser supports audio recording
     */
    checkBrowserSupport() {
        return !!(navigator.mediaDevices && 
                 navigator.mediaDevices.getUserMedia && 
                 window.MediaRecorder);
    }
    
    /**
     * Initialize audio recording for a playlist
     */
    async initRecording(playlistId) {
        if (!this.isSupported) {
            throw new Error('Audio recording is not supported in this browser');
        }
        
        this.playlistId = playlistId;
        
        try {
            // Request microphone access
            this.audioStream = await navigator.mediaDevices.getUserMedia({
                audio: {
                    echoCancellation: true,
                    noiseSuppression: true,
                    autoGainControl: true,
                    sampleRate: 44100
                }
            });
            
            // Initialize MediaRecorder
            const options = {
                mimeType: this.getSupportedMimeType()
            };
            
            this.mediaRecorder = new MediaRecorder(this.audioStream, options);
            this.setupMediaRecorderEvents();
            
            return true;
        } catch (error) {
            console.error('Failed to initialize recording:', error);
            throw new Error('Could not access microphone. Please check permissions.');
        }
    }
    
    /**
     * Get the best supported MIME type for recording
     */
    getSupportedMimeType() {
        const types = [
            'audio/webm;codecs=opus',
            'audio/webm',
            'audio/mp4',
            'audio/ogg;codecs=opus'
        ];
        
        for (const type of types) {
            if (MediaRecorder.isTypeSupported(type)) {
                return type;
            }
        }
        
        return 'audio/webm'; // Fallback
    }
    
    /**
     * Setup MediaRecorder event handlers
     */
    setupMediaRecorderEvents() {
        this.mediaRecorder.ondataavailable = (event) => {
            if (event.data.size > 0) {
                this.audioChunks.push(event.data);
            }
        };
        
        this.mediaRecorder.onstop = () => {
            this.processRecording();
        };
        
        this.mediaRecorder.onerror = (event) => {
            console.error('MediaRecorder error:', event.error);
            this.stopRecording();
            this.showError('Recording error occurred');
        };
    }
    
    /**
     * Start recording
     */
    startRecording() {
        if (!this.mediaRecorder || this.isRecording) {
            return false;
        }
        
        try {
            this.audioChunks = [];
            this.recordingStartTime = Date.now();
            this.isRecording = true;
            
            this.mediaRecorder.start(100); // Collect data every 100ms
            this.startRecordingTimer();
            this.updateRecordingUI();
            
            return true;
        } catch (error) {
            console.error('Failed to start recording:', error);
            this.isRecording = false;
            return false;
        }
    }
    
    /**
     * Stop recording
     */
    stopRecording() {
        if (!this.isRecording) {
            return false;
        }
        
        try {
            this.isRecording = false;
            this.mediaRecorder.stop();
            this.stopRecordingTimer();
            this.updateRecordingUI();
            
            return true;
        } catch (error) {
            console.error('Failed to stop recording:', error);
            return false;
        }
    }
    
    /**
     * Process the recorded audio
     */
    processRecording() {
        if (this.audioChunks.length === 0) {
            this.showError('No audio data recorded');
            return;
        }
        
        const audioBlob = new Blob(this.audioChunks, {
            type: this.mediaRecorder.mimeType
        });
        
        const duration = this.getRecordingDuration();
        
        // Show recording preview
        this.showRecordingPreview(audioBlob, duration);
    }
    
    /**
     * Get recording duration in seconds
     */
    getRecordingDuration() {
        if (!this.recordingStartTime) return 0;
        return Math.round((Date.now() - this.recordingStartTime) / 1000);
    }
    
    /**
     * Start recording timer
     */
    startRecordingTimer() {
        this.recordingTimer = setInterval(() => {
            const elapsed = this.getRecordingDuration();
            this.updateRecordingTime(elapsed);
            
            // Auto-stop at max time
            if (elapsed >= this.maxRecordingTime) {
                this.stopRecording();
                this.showWarning(`Recording stopped at ${this.maxRecordingTime} second limit`);
            }
        }, 100);
    }
    
    /**
     * Stop recording timer
     */
    stopRecordingTimer() {
        if (this.recordingTimer) {
            clearInterval(this.recordingTimer);
            this.recordingTimer = null;
        }
    }
    
    /**
     * Update recording UI elements
     */
    updateRecordingUI() {
        const recordBtn = document.getElementById('recordVoiceBtn');
        const stopBtn = document.getElementById('stopRecordingBtn');
        const statusDiv = document.getElementById('recordingStatus');
        
        if (this.isRecording) {
            if (recordBtn) recordBtn.style.display = 'none';
            if (stopBtn) stopBtn.style.display = 'inline-block';
            if (statusDiv) {
                statusDiv.innerHTML = '<i class="fas fa-circle text-danger"></i> Recording...';
                statusDiv.className = 'alert alert-info';
            }
        } else {
            if (recordBtn) recordBtn.style.display = 'inline-block';
            if (stopBtn) stopBtn.style.display = 'none';
            if (statusDiv) {
                statusDiv.innerHTML = '<i class="fas fa-microphone"></i> Ready to record';
                statusDiv.className = 'alert alert-secondary';
            }
        }
    }
    
    /**
     * Update recording time display
     */
    updateRecordingTime(seconds) {
        const timeDiv = document.getElementById('recordingTime');
        if (timeDiv) {
            const minutes = Math.floor(seconds / 60);
            const secs = seconds % 60;
            timeDiv.textContent = `${String(minutes).padStart(2, '0')}:${String(secs).padStart(2, '0')}`;
        }
    }
    
    /**
     * Show recording preview with playback and save options
     */
    showRecordingPreview(audioBlob, duration) {
        const previewDiv = document.getElementById('recordingPreview');
        if (!previewDiv) return;
        
        // Create audio URL for playback
        const audioUrl = URL.createObjectURL(audioBlob);
        
        previewDiv.innerHTML = `
            <div class="card">
                <div class="card-header">
                    <h6><i class="fas fa-headphones"></i> Voice Clip Preview</h6>
                </div>
                <div class="card-body">
                    <div class="mb-3">
                        <audio controls class="w-100" preload="metadata">
                            <source src="${audioUrl}" type="${audioBlob.type}">
                            Your browser does not support audio playback.
                        </audio>
                    </div>
                    <div class="mb-3">
                        <strong>Duration:</strong> ${this.formatDuration(duration)}<br>
                        <strong>Size:</strong> ${this.formatFileSize(audioBlob.size)}<br>
                        <strong>Format:</strong> ${audioBlob.type}
                    </div>
                    <div class="mb-3">
                        <label for="voiceClipTitle" class="form-label">Voice Clip Title</label>
                        <input type="text" class="form-control" id="voiceClipTitle" 
                               placeholder="e.g., Intro, Outro, Station ID" value="Voice Clip ${new Date().toLocaleTimeString()}">
                    </div>
                    <div class="mb-3">
                        <label for="voiceClipDescription" class="form-label">Description (Optional)</label>
                        <textarea class="form-control" id="voiceClipDescription" rows="2" 
                                  placeholder="Brief description of this voice clip"></textarea>
                    </div>
                    <div class="d-grid gap-2 d-md-flex justify-content-md-end">
                        <button type="button" class="btn btn-outline-secondary" onclick="audioRecorder.discardRecording()">
                            <i class="fas fa-times"></i> Discard
                        </button>
                        <button type="button" class="btn btn-success" onclick="audioRecorder.saveVoiceClip()">
                            <i class="fas fa-save"></i> Save Voice Clip
                        </button>
                    </div>
                </div>
            </div>
        `;
        
        previewDiv.style.display = 'block';
        
        // Store the blob for saving
        this.currentRecording = {
            blob: audioBlob,
            duration: duration,
            url: audioUrl
        };
    }
    
    /**
     * Save the voice clip to the playlist
     */
    async saveVoiceClip() {
        if (!this.currentRecording) {
            this.showError('No recording to save');
            return;
        }
        
        const title = document.getElementById('voiceClipTitle')?.value || 'Voice Clip';
        const description = document.getElementById('voiceClipDescription')?.value || '';
        
        try {
            // Convert blob to file
            const file = new File([this.currentRecording.blob], `voice_clip_${Date.now()}.webm`, {
                type: this.currentRecording.blob.type
            });
            
            // Create form data
            const formData = new FormData();
            formData.append('action', 'upload_file');
            formData.append('show_id', this.playlistId);
            formData.append('audio_file', file);
            formData.append('title', title);
            formData.append('description', description);
            formData.append('source_type', 'voice_clip');
            formData.append('csrf_token', getCSRFToken());
            
            // Show upload progress
            this.showUploadProgress(true);
            
            // Upload the voice clip
            const response = await fetch('/api/upload.php', {
                method: 'POST',
                body: formData
            });
            
            const result = await response.json();
            
            this.showUploadProgress(false);
            
            if (result.success) {
                this.showSuccess('Voice clip saved successfully!');
                this.discardRecording();
                
                // Refresh playlist tracks to show the new voice clip
                if (typeof loadPlaylistTracks === 'function') {
                    loadPlaylistTracks(this.playlistId);
                }
            } else {
                this.showError(`Failed to save voice clip: ${result.error}`);
            }
            
        } catch (error) {
            this.showUploadProgress(false);
            this.showError(`Upload error: ${error.message}`);
        }
    }
    
    /**
     * Discard the current recording
     */
    discardRecording() {
        if (this.currentRecording) {
            URL.revokeObjectURL(this.currentRecording.url);
            this.currentRecording = null;
        }
        
        const previewDiv = document.getElementById('recordingPreview');
        if (previewDiv) {
            previewDiv.style.display = 'none';
            previewDiv.innerHTML = '';
        }
        
        // Reset UI
        this.updateRecordingUI();
        this.updateRecordingTime(0);
    }
    
    /**
     * Show upload progress
     */
    showUploadProgress(show) {
        const progressDiv = document.getElementById('voiceClipUploadProgress');
        const saveBtn = document.querySelector('#recordingPreview .btn-success');
        
        if (show) {
            if (progressDiv) progressDiv.style.display = 'block';
            if (saveBtn) {
                saveBtn.disabled = true;
                saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
            }
        } else {
            if (progressDiv) progressDiv.style.display = 'none';
            if (saveBtn) {
                saveBtn.disabled = false;
                saveBtn.innerHTML = '<i class="fas fa-save"></i> Save Voice Clip';
            }
        }
    }
    
    /**
     * Clean up resources
     */
    cleanup() {
        this.stopRecording();
        this.stopRecordingTimer();
        
        if (this.audioStream) {
            this.audioStream.getTracks().forEach(track => track.stop());
            this.audioStream = null;
        }
        
        if (this.currentRecording) {
            URL.revokeObjectURL(this.currentRecording.url);
            this.currentRecording = null;
        }
        
        this.mediaRecorder = null;
    }
    
    /**
     * Utility functions
     */
    formatDuration(seconds) {
        const mins = Math.floor(seconds / 60);
        const secs = seconds % 60;
        return `${mins}:${String(secs).padStart(2, '0')}`;
    }
    
    formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
    
    showSuccess(message) {
        this.showAlert(message, 'success');
    }
    
    showError(message) {
        this.showAlert(message, 'danger');
    }
    
    showWarning(message) {
        this.showAlert(message, 'warning');
    }
    
    showAlert(message, type) {
        // Reuse existing alert system from playlists.js
        if (typeof showAlert === 'function') {
            showAlert(message, type);
        } else {
            alert(message); // Fallback
        }
    }
}

// Global instance
let audioRecorder = null;

/**
 * Initialize audio recorder for a playlist
 */
async function initAudioRecorder(playlistId) {
    try {
        audioRecorder = new AudioRecorder();
        
        if (!audioRecorder.isSupported) {
            throw new Error('Audio recording is not supported in this browser');
        }
        
        await audioRecorder.initRecording(playlistId);
        return true;
    } catch (error) {
        console.error('Failed to initialize audio recorder:', error);
        if (typeof showAlert === 'function') {
            showAlert(error.message, 'danger');
        }
        return false;
    }
}

/**
 * Show the voice recording modal
 */
function showVoiceRecordingModal(playlistId) {
    const modal = new bootstrap.Modal(document.getElementById('voiceRecordingModal'));
    modal.show();
    
    // Initialize recorder when modal opens
    modal._element.addEventListener('shown.bs.modal', async () => {
        await initAudioRecorder(playlistId);
    });
    
    // Cleanup when modal closes
    modal._element.addEventListener('hidden.bs.modal', () => {
        if (audioRecorder) {
            audioRecorder.cleanup();
            audioRecorder = null;
        }
    });
}

/**
 * Start recording voice clip
 */
function startVoiceRecording() {
    if (audioRecorder) {
        audioRecorder.startRecording();
    }
}

/**
 * Stop recording voice clip
 */
function stopVoiceRecording() {
    if (audioRecorder) {
        audioRecorder.stopRecording();
    }
}