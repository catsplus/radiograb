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
     * Add ON-AIR indicator to a show card (small badge style)
     */
    addOnAirIndicator(card, recording) {
        // Remove existing indicator
        this.removeOnAirIndicator(card);
        
        // Add recording class
        card.classList.add('recording');
        
        // Create small recording badge
        const recordingBadge = document.createElement('span');
        recordingBadge.className = 'recording-badge on-air-indicator-element';
        recordingBadge.innerHTML = '🔴 Recording';
        
        // Create progress info (smaller, less intrusive)
        const progressInfo = document.createElement('div');
        progressInfo.className = 'recording-progress-info on-air-indicator-element mt-2';
        progressInfo.innerHTML = `
            <div class="recording-progress mb-1">
                <div class="recording-progress-bar" style="width: ${recording.progress_percent}%"></div>
            </div>
            <div class="d-flex justify-content-between">
                <small class="recording-time remaining">
                    ${this.formatDuration(recording.remaining_seconds)} remaining
                </small>
            </div>
        `;
        
        // Insert recording badge next to the show title
        const cardTitle = card.querySelector('.card-title');
        if (cardTitle) {
            cardTitle.appendChild(recordingBadge);
        } else {
            // Fallback: add to card header
            const cardHeader = card.querySelector('.card-header, h5, h6');
            if (cardHeader) {
                cardHeader.appendChild(recordingBadge);
            } else {
                card.insertBefore(recordingBadge, card.firstChild);
            }
        }
        
        // Add compact progress info after the schedule information
        const cardBody = card.querySelector('.card-body');
        if (cardBody) {
            // Find a good spot - after the statistics but before action buttons
            const statsRow = cardBody.querySelector('.row.text-center');
            if (statsRow) {
                statsRow.parentNode.insertBefore(progressInfo, statsRow.nextSibling);
            } else {
                // Find action buttons and insert before them
                const actionButtonsRow = cardBody.querySelector('.btn-group, .text-center:has(button)');
                if (actionButtonsRow) {
                    cardBody.insertBefore(progressInfo, actionButtonsRow);
                } else {
                    cardBody.appendChild(progressInfo);
                }
            }
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
     * Update recording banners on dashboard/main pages (only for actively recording shows)
     */
    updateRecordingBanners() {
        // Remove existing banners
        const existingBanners = document.querySelectorAll('.on-air-banner-container');
        existingBanners.forEach(banner => banner.remove());
        
        // Only show banner if there are active recordings
        if (this.currentRecordings.length > 0) {
            this.createRecordingBanner();
        }
    }

    /**
     * Create a recording banner for the page
     */
    createRecordingBanner() {
        // Try to find the "Next Recordings" section first
        const nextRecordingsSection = document.querySelector('.next-recordings-section, [data-section="next-recordings"]');
        
        if (nextRecordingsSection) {
            // Insert banner as first item in Next Recordings section
            this.insertBannerInNextRecordings(nextRecordingsSection);
        } else {
            // Fallback: look for a card container or main content area
            const cardContainer = document.querySelector('.row .col-md-6, .card-container, .main-content');
            if (cardContainer) {
                this.insertBannerInContainer(cardContainer);
            } else {
                // Last resort: use the original method but with better positioning
                this.insertBannerInMainContainer();
            }
        }
    }
    
    /**
     * Insert banner in the Next Recordings section
     */
    insertBannerInNextRecordings(section) {
        const bannerContainer = this.createBannerElement();
        
        // Insert as the first child of the Next Recordings section
        const firstChild = section.querySelector('.card, .row, .next-recording-item');
        if (firstChild) {
            section.insertBefore(bannerContainer, firstChild);
        } else {
            section.insertBefore(bannerContainer, section.firstChild);
        }
    }
    
    /**
     * Insert banner in a card container
     */
    insertBannerInContainer(container) {
        const bannerContainer = this.createBannerElement();
        
        // Insert at the beginning of the container
        const firstChild = container.firstChild;
        if (firstChild) {
            container.insertBefore(bannerContainer, firstChild);
        } else {
            container.appendChild(bannerContainer);
        }
    }
    
    /**
     * Fallback method - insert in main container but positioned better
     */
    insertBannerInMainContainer() {
        const container = document.querySelector('.container');
        if (!container) return;
        
        const bannerContainer = this.createBannerElement();
        
        // Try to find a good insertion point after the header
        const headerElements = container.querySelectorAll('h1, h2, .page-header, .site-header');
        let insertionPoint = null;
        
        if (headerElements.length > 0) {
            // Insert after the last header element
            insertionPoint = headerElements[headerElements.length - 1].nextSibling;
        }
        
        if (insertionPoint) {
            container.insertBefore(bannerContainer, insertionPoint);
        } else {
            // Find first content element
            const firstContent = container.querySelector('.row, .card, .content');
            if (firstContent) {
                container.insertBefore(bannerContainer, firstContent);
            } else {
                container.insertBefore(bannerContainer, container.firstChild);
            }
        }
    }
    
    /**
     * Create the banner element
     */
    createBannerElement() {
        const bannerContainer = document.createElement('div');
        bannerContainer.className = 'on-air-banner-container mb-3';
        
        const banner = document.createElement('div');
        banner.className = 'on-air-banner';
        
        if (this.currentRecordings.length === 1) {
            const recording = this.currentRecordings[0];
            banner.innerHTML = `
                <span class="recording-icon">🔴</span>
                <strong>Recording:</strong> ${recording.show_name} on ${recording.station_name}
                <span class="ms-3">${this.formatDuration(recording.remaining_seconds)} remaining</span>
            `;
        } else {
            // Show details of multiple recordings
            const recordingList = this.currentRecordings.map(r => 
                `${r.show_name} (${r.station_name})`
            ).join(', ');
            banner.innerHTML = `
                <span class="recording-icon">🔴</span>
                <strong>Recording ${this.currentRecordings.length} shows:</strong> ${recordingList}
            `;
        }
        
        bannerContainer.appendChild(banner);
        return bannerContainer;
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