/**
 * ON-AIR Status Management
 * Handles real-time recording status updates and UI indicators
 */

class OnAirStatusManager {
    constructor() {
        this.currentRecordings = [];
        this.updateInterval = 30000; // Update every 30 seconds
        this.intervalId = null;
        this.initialized = false;
    }

    /**
     * Initialize the ON-AIR status system
     */
    async init() {
        if (this.initialized) return;
        
        console.log('Initializing ON-AIR Status Manager');
        
        // Load initial status
        await this.updateRecordingStatus();
        
        // Start periodic updates
        this.startPeriodicUpdates();
        
        this.initialized = true;
    }

    /**
     * Start periodic status updates
     */
    startPeriodicUpdates() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
        }
        
        this.intervalId = setInterval(() => {
            this.updateRecordingStatus();
        }, this.updateInterval);
    }

    /**
     * Stop periodic updates
     */
    stopPeriodicUpdates() {
        if (this.intervalId) {
            clearInterval(this.intervalId);
            this.intervalId = null;
        }
    }

    /**
     * Fetch current recording status from API
     */
    async updateRecordingStatus() {
        try {
            const response = await fetch('/api/recording-status.php?action=current_recordings');
            const data = await response.json();
            
            if (data.success) {
                this.currentRecordings = data.current_recordings || [];
                this.updateUI();
                this.triggerStatusEvent();
            } else {
                console.error('Failed to fetch recording status:', data.error);
            }
        } catch (error) {
            console.error('Error fetching recording status:', error);
        }
    }

    /**
     * Update UI elements based on current recording status
     */
    updateUI() {
        this.updateShowCards();
        this.updateStationCards();
        this.updateRecordingBanners();
        this.updatePageTitle();
    }

    /**
     * Update show cards with ON-AIR status
     */
    updateShowCards() {
        // Find all show cards and update their status
        const showCards = document.querySelectorAll('[data-show-id]');
        
        showCards.forEach(card => {
            const showId = parseInt(card.dataset.showId);
            const recording = this.currentRecordings.find(r => r.show_id === showId);
            
            if (recording) {
                this.addOnAirIndicator(card, recording);
            } else {
                this.removeOnAirIndicator(card);
            }
        });
    }

    /**
     * Update station cards with recording status
     */
    updateStationCards() {
        const stationCards = document.querySelectorAll('[data-station-call]');
        
        stationCards.forEach(card => {
            const callLetters = card.dataset.stationCall;
            const hasRecording = this.currentRecordings.some(r => r.call_letters === callLetters);
            
            if (hasRecording) {
                card.classList.add('recording');
                const recording = this.currentRecordings.find(r => r.call_letters === callLetters);
                this.addStationRecordingIndicator(card, recording);
            } else {
                card.classList.remove('recording');
                this.removeStationRecordingIndicator(card);
            }
        });
    }

    /**
     * Add ON-AIR indicator to a show card
     */
    addOnAirIndicator(card, recording) {
        // Remove existing indicator
        this.removeOnAirIndicator(card);
        
        // Add recording class
        card.classList.add('recording');
        
        // Create ON-AIR badge
        const onAirBadge = document.createElement('span');
        onAirBadge.className = 'on-air-badge on-air-indicator-element';
        onAirBadge.innerHTML = 'ON-AIR';
        
        // Create progress info
        const progressInfo = document.createElement('div');
        progressInfo.className = 'recording-progress-info on-air-indicator-element';
        progressInfo.innerHTML = `
            <div class="recording-progress">
                <div class="recording-progress-bar" style="width: ${recording.progress_percent}%"></div>
            </div>
            <div class="d-flex justify-content-between mt-1">
                <small class="recording-time elapsed">
                    ${this.formatDuration(recording.elapsed_seconds)} elapsed
                </small>
                <small class="recording-time remaining">
                    ${this.formatDuration(recording.remaining_seconds)} remaining
                </small>
            </div>
        `;
        
        // Insert indicators into card
        const cardHeader = card.querySelector('.card-header, .card-title, h5, h6');
        if (cardHeader) {
            cardHeader.appendChild(onAirBadge);
        } else {
            card.insertBefore(onAirBadge, card.firstChild);
        }
        
        const cardBody = card.querySelector('.card-body');
        if (cardBody) {
            cardBody.appendChild(progressInfo);
        } else {
            card.appendChild(progressInfo);
        }
    }

    /**
     * Remove ON-AIR indicator from a show card
     */
    removeOnAirIndicator(card) {
        card.classList.remove('recording');
        
        // Remove all ON-AIR indicator elements
        const indicators = card.querySelectorAll('.on-air-indicator-element');
        indicators.forEach(indicator => indicator.remove());
    }

    /**
     * Add recording indicator to station card
     */
    addStationRecordingIndicator(card, recording) {
        // Remove existing indicator
        this.removeStationRecordingIndicator(card);
        
        // Create station recording indicator
        const indicator = document.createElement('div');
        indicator.className = 'station-recording-indicator on-air-indicator-element';
        indicator.innerHTML = `
            <div class="on-air-badge">
                <i class="fas fa-record-vinyl"></i> Recording "${recording.show_name}"
            </div>
        `;
        
        const cardBody = card.querySelector('.card-body');
        if (cardBody) {
            cardBody.insertBefore(indicator, cardBody.firstChild);
        }
    }

    /**
     * Remove station recording indicator
     */
    removeStationRecordingIndicator(card) {
        const indicators = card.querySelectorAll('.station-recording-indicator');
        indicators.forEach(indicator => indicator.remove());
    }

    /**
     * Update recording banners on dashboard/main pages
     */
    updateRecordingBanners() {
        // Remove existing banners
        const existingBanners = document.querySelectorAll('.on-air-banner-container');
        existingBanners.forEach(banner => banner.remove());
        
        if (this.currentRecordings.length > 0) {
            this.createRecordingBanner();
        }
    }

    /**
     * Create a recording banner for the page
     */
    createRecordingBanner() {
        const container = document.querySelector('.container');
        if (!container) return;
        
        const bannerContainer = document.createElement('div');
        bannerContainer.className = 'on-air-banner-container';
        
        const banner = document.createElement('div');
        banner.className = 'on-air-banner';
        
        if (this.currentRecordings.length === 1) {
            const recording = this.currentRecordings[0];
            banner.innerHTML = `
                <span class="recording-icon">🔴</span>
                <strong>LIVE RECORDING:</strong> ${recording.show_name} on ${recording.station_name}
                <span class="ms-3">${this.formatDuration(recording.remaining_seconds)} remaining</span>
            `;
        } else {
            banner.innerHTML = `
                <span class="recording-icon">🔴</span>
                <strong>LIVE RECORDINGS:</strong> ${this.currentRecordings.length} shows currently being captured
            `;
        }
        
        bannerContainer.appendChild(banner);
        
        // Insert banner at the top of the main container
        const firstChild = container.querySelector('.row, .card, h1, h2');
        if (firstChild) {
            container.insertBefore(bannerContainer, firstChild);
        } else {
            container.insertBefore(bannerContainer, container.firstChild);
        }
    }

    /**
     * Update page title with recording indicator
     */
    updatePageTitle() {
        const originalTitle = document.title.replace(/^🔴 /, '');
        
        if (this.currentRecordings.length > 0) {
            document.title = `🔴 ${originalTitle}`;
        } else {
            document.title = originalTitle;
        }
    }

    /**
     * Trigger custom event for other components
     */
    triggerStatusEvent() {
        const event = new CustomEvent('recordingStatusUpdate', {
            detail: {
                recordings: this.currentRecordings,
                count: this.currentRecordings.length
            }
        });
        
        document.dispatchEvent(event);
    }

    /**
     * Format duration in seconds to human readable format
     */
    formatDuration(seconds) {
        if (seconds < 0) return '0:00';
        
        const hours = Math.floor(seconds / 3600);
        const minutes = Math.floor((seconds % 3600) / 60);
        const secs = seconds % 60;
        
        if (hours > 0) {
            return `${hours}:${minutes.toString().padStart(2, '0')}:${secs.toString().padStart(2, '0')}`;
        } else {
            return `${minutes}:${secs.toString().padStart(2, '0')}`;
        }
    }

    /**
     * Get current recordings
     */
    getCurrentRecordings() {
        return this.currentRecordings;
    }

    /**
     * Check if a specific show is currently recording
     */
    isShowRecording(showId) {
        return this.currentRecordings.some(r => r.show_id === parseInt(showId));
    }

    /**
     * Check if any show from a station is recording
     */
    isStationRecording(callLetters) {
        return this.currentRecordings.some(r => r.call_letters === callLetters);
    }

    /**
     * Clean up resources
     */
    destroy() {
        this.stopPeriodicUpdates();
        this.removeOnAirIndicator(document.body);
        this.updateRecordingBanners(); // This will remove banners
        this.updatePageTitle(); // This will remove title indicator
        this.initialized = false;
    }
}

// Global instance
window.onAirManager = new OnAirStatusManager();

// Auto-initialize on DOM ready
document.addEventListener('DOMContentLoaded', function() {
    if (window.onAirManager) {
        window.onAirManager.init();
    }
});

// Clean up on page unload
window.addEventListener('beforeunload', function() {
    if (window.onAirManager) {
        window.onAirManager.destroy();
    }
});