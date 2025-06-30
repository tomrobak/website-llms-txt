/**
 * LLMS Progress Tracker - Modern REST API Implementation
 * 
 * @since 2.1.0
 */

class LLMSProgressTracker {
    constructor() {
        this.progressId = null;
        this.lastLogId = 0;
        this.pollInterval = null;
        this.logPollInterval = null;
        this.apiBase = `${wpApiSettings.root}wp-llms-txt/v1`;
        this.nonce = wpApiSettings.nonce;
        
        this.initElements();
        this.bindEvents();
    }
    
    initElements() {
        // Progress elements
        this.progressBar = document.querySelector('.llms-progress-bar');
        this.progressText = document.querySelector('.llms-progress-text');
        this.progressDetails = document.querySelector('.llms-progress-details');
        this.progressStatus = document.querySelector('.llms-progress-status');
        
        // Log viewer elements
        this.logContainer = document.querySelector('.llms-log-container');
        this.logFilter = document.querySelector('.llms-log-filter');
        this.logSearch = document.querySelector('.llms-log-search');
        
        // Control buttons
        this.pauseBtn = document.querySelector('.llms-pause-btn');
        this.resumeBtn = document.querySelector('.llms-resume-btn');
        this.cancelBtn = document.querySelector('.llms-cancel-btn');
        this.clearLogsBtn = document.querySelector('.llms-clear-logs-btn');
    }
    
    bindEvents() {
        if (this.pauseBtn) {
            this.pauseBtn.addEventListener('click', () => this.pauseGeneration());
        }
        
        if (this.cancelBtn) {
            this.cancelBtn.addEventListener('click', () => this.cancelGeneration());
        }
        
        if (this.clearLogsBtn) {
            this.clearLogsBtn.addEventListener('click', () => this.clearLogs());
        }
        
        if (this.logFilter) {
            this.logFilter.addEventListener('change', () => this.filterLogs());
        }
        
        // Auto-start if progress ID is present
        const progressId = this.getProgressIdFromDOM();
        if (progressId) {
            // Ensure progress section is visible
            this.showProgressUI();
            
            // Check if generation needs to be started
            this.checkAndStartGeneration(progressId);
            
            // Start tracking
            this.startTracking(progressId);
        }
    }
    
    getProgressIdFromDOM() {
        const elem = document.querySelector('[data-progress-id]');
        return elem ? elem.dataset.progressId : null;
    }
    
    async checkAndStartGeneration(progressId) {
        try {
            // Check current progress status
            const data = await this.apiRequest(`/progress/${progressId}`);
            
            // If status is pending, start the generation
            if (data.status === 'pending') {
                console.log('Starting generation process...');
                await this.apiRequest('/generate/start', {
                    method: 'POST',
                    body: JSON.stringify({ progress_id: progressId })
                });
            }
        } catch (error) {
            console.error('Failed to start generation:', error);
        }
    }
    
    async apiRequest(endpoint, options = {}) {
        const defaults = {
            headers: {
                'Content-Type': 'application/json',
                'X-WP-Nonce': this.nonce
            }
        };
        
        const response = await fetch(`${this.apiBase}${endpoint}`, {
            ...defaults,
            ...options
        });
        
        if (!response.ok) {
            throw new Error(`API Error: ${response.statusText}`);
        }
        
        return response.json();
    }
    
    startTracking(progressId) {
        this.progressId = progressId;
        this.showProgressUI();
        
        // Set initial state
        if (this.progressBar) {
            this.progressBar.style.width = '0%';
        }
        if (this.progressText) {
            this.progressText.textContent = 'Initializing...';
        }
        
        // Start polling for progress with slight delay for smooth animation
        setTimeout(() => {
            this.pollInterval = setInterval(() => this.updateProgress(), 1000);
            this.updateProgress();
        }, 100);
        
        // Start polling for logs
        this.logPollInterval = setInterval(() => this.updateLogs(), 2000);
        
        // Initial log update
        this.updateLogs();
    }
    
    async updateProgress() {
        try {
            const data = await this.apiRequest(`/progress/${this.progressId}`);
            
            // Update progress bar
            if (this.progressBar) {
                this.progressBar.style.width = `${data.percentage}%`;
                this.progressBar.setAttribute('aria-valuenow', data.percentage);
            }
            
            // Update text
            if (this.progressText) {
                this.progressText.textContent = `${data.percentage}% (${data.current_item}/${data.total_items})`;
            }
            
            // Update details
            if (this.progressDetails) {
                let details = `
                    <div class="llms-progress-stat">
                        <span class="label">Status:</span>
                        <span class="value ${data.status}">${this.formatStatus(data.status)}</span>
                    </div>
                    <div class="llms-progress-stat">
                        <span class="label">Current:</span>
                        <span class="value" title="${data.current_post_title || 'Initializing...'}">${this.truncateText(data.current_post_title || 'Initializing...', 40)}</span>
                    </div>
                    <div class="llms-progress-stat">
                        <span class="label">Runtime:</span>
                        <span class="value">${data.elapsed_time}</span>
                    </div>
                `;
                
                // Show rate instead of estimated time
                if (data.current_item > 0 && data.elapsed_seconds > 0) {
                    const rate = (data.current_item / data.elapsed_seconds).toFixed(1);
                    details += `
                        <div class="llms-progress-stat">
                            <span class="label">Speed:</span>
                            <span class="value">${rate} posts/sec</span>
                        </div>
                    `;
                }
                
                details += `
                    <div class="llms-progress-stat">
                        <span class="label">Memory:</span>
                        <span class="value">${data.memory_peak_formatted}</span>
                    </div>
                `;
                
                if (data.errors > 0 || data.warnings > 0) {
                    details += `
                        <div class="llms-progress-stat">
                            <span class="label">Issues:</span>
                            <span class="value">
                                ${data.errors > 0 ? `<span class="error">${data.errors} errors</span>` : ''}
                                ${data.warnings > 0 ? `<span class="warning">${data.warnings} warnings</span>` : ''}
                            </span>
                        </div>
                    `;
                }
                
                this.progressDetails.innerHTML = details;
            }
            
            // Check if completed
            if (data.status !== 'running') {
                this.onComplete(data.status);
            }
            
        } catch (error) {
            console.error('Progress update error:', error);
        }
    }
    
    async updateLogs() {
        try {
            const level = this.logFilter ? this.logFilter.value : '';
            const data = await this.apiRequest(`/logs?last_id=${this.lastLogId}&level=${level}&limit=20`);
            
            if (data.logs && data.logs.length > 0) {
                this.renderLogs(data.logs);
                this.lastLogId = Math.max(...data.logs.map(log => log.id));
            }
            
        } catch (error) {
            console.error('Logs update error:', error);
        }
    }
    
    renderLogs(logs) {
        if (!this.logContainer) return;
        
        const logHtml = logs.map(log => `
            <div class="llms-log-entry llms-log-${log.level.toLowerCase()}" data-log-id="${log.id}">
                <div class="llms-log-header">
                    <span class="llms-log-time">${log.timestamp}</span>
                    <span class="llms-log-level">${log.level}</span>
                    <span class="llms-log-memory">${log.memory_formatted}</span>
                    <span class="llms-log-execution">${log.time_formatted}</span>
                </div>
                <div class="llms-log-message">${this.escapeHtml(log.message)}</div>
                ${log.context ? `<div class="llms-log-context">${this.formatContext(log.context)}</div>` : ''}
            </div>
        `).join('');
        
        // Prepend new logs
        this.logContainer.insertAdjacentHTML('afterbegin', logHtml);
        
        // Limit displayed logs
        const maxLogs = 100;
        const entries = this.logContainer.querySelectorAll('.llms-log-entry');
        if (entries.length > maxLogs) {
            for (let i = maxLogs; i < entries.length; i++) {
                entries[i].remove();
            }
        }
    }
    
    formatContext(context) {
        if (typeof context === 'object') {
            return `<pre>${JSON.stringify(context, null, 2)}</pre>`;
        }
        return this.escapeHtml(context);
    }
    
    formatStatus(status) {
        const statusMap = {
            'running': 'Running',
            'completed': 'Completed',
            'cancelled': 'Cancelled',
            'error': 'Error',
            'paused': 'Paused'
        };
        return statusMap[status] || status;
    }
    
    async pauseGeneration() {
        // TODO: Implement pause functionality
        console.log('Pause not yet implemented');
    }
    
    async cancelGeneration() {
        if (!confirm('Are you sure you want to cancel the current generation?')) {
            return;
        }
        
        try {
            await this.apiRequest(`/progress/${this.progressId}/cancel`, {
                method: 'POST'
            });
            
            this.onComplete('cancelled');
        } catch (error) {
            console.error('Cancel error:', error);
            alert('Failed to cancel generation');
        }
    }
    
    async clearLogs() {
        if (!confirm('This will clear logs older than 24 hours. Continue?')) {
            return;
        }
        
        try {
            const result = await this.apiRequest('/logs', {
                method: 'DELETE'
            });
            
            alert(`Cleared ${result.deleted} log entries`);
            
            // Clear displayed logs
            if (this.logContainer) {
                this.logContainer.innerHTML = '';
                this.lastLogId = 0;
            }
            
        } catch (error) {
            console.error('Clear logs error:', error);
            alert('Failed to clear logs');
        }
    }
    
    filterLogs() {
        this.lastLogId = 0;
        if (this.logContainer) {
            this.logContainer.innerHTML = '';
        }
        this.updateLogs();
    }
    
    onComplete(status) {
        // Stop polling
        if (this.pollInterval) {
            clearInterval(this.pollInterval);
            this.pollInterval = null;
        }
        
        if (this.logPollInterval) {
            clearInterval(this.logPollInterval);
            this.logPollInterval = null;
        }
        
        // Update UI
        if (this.progressStatus) {
            this.progressStatus.textContent = this.formatStatus(status);
            this.progressStatus.className = `llms-progress-status ${status}`;
        }
        
        // Show completion message
        this.showCompletionMessage(status);
    }
    
    showProgressUI() {
        const progressSection = document.querySelector('.llms-progress-section');
        if (progressSection) {
            progressSection.style.display = 'block';
        }
    }
    
    showCompletionMessage(status) {
        const messages = {
            'completed': {
                title: 'Generation Completed Successfully',
                message: 'Your LLMS files have been generated and are ready for AI crawlers.',
                actions: true
            },
            'cancelled': {
                title: 'Generation Cancelled',
                message: 'The file generation process was cancelled by user request.',
                actions: false
            },
            'error': {
                title: 'Generation Error',
                message: 'An error occurred during generation. Please check the logs for details.',
                actions: false
            }
        };
        
        const config = messages[status] || { title: `Generation ${status}`, message: '', actions: false };
        
        // Create detailed completion card
        const completionCard = document.createElement('div');
        completionCard.className = 'llms-card llms-completion-card llms-completion-' + status;
        completionCard.innerHTML = `
            <div class="llms-card-header">
                <h3 class="llms-card-title">${config.title}</h3>
            </div>
            <div class="llms-card-content">
                <p>${config.message}</p>
                ${config.actions ? `
                    <div class="llms-completion-actions">
                        <a href="${window.location.origin}/llms.txt" target="_blank" class="llms-button primary">
                            View Standard File
                        </a>
                        <a href="${window.location.origin}/llms-full.txt" target="_blank" class="llms-button primary">
                            View Full File
                        </a>
                        <button type="button" class="llms-button secondary" onclick="location.reload()">
                            Back to Dashboard
                        </button>
                    </div>
                ` : `
                    <button type="button" class="llms-button primary" onclick="location.reload()">
                        Back to Dashboard
                    </button>
                `}
            </div>
        `;
        
        // Replace progress section with completion card
        const progressSection = document.querySelector('.llms-progress-section');
        if (progressSection) {
            progressSection.innerHTML = '';
            progressSection.appendChild(completionCard);
        }
        
        // Also show a temporary notification
        const notification = document.createElement('div');
        notification.className = 'llms-notification llms-notification-' + status;
        notification.textContent = config.title;
        document.body.appendChild(notification);
        
        setTimeout(() => notification.remove(), 5000);
    }
    
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    truncateText(text, maxLength) {
        if (text.length <= maxLength) return text;
        return text.substring(0, maxLength - 3) + '...';
    }
}

// Initialize on DOM ready
document.addEventListener('DOMContentLoaded', () => {
    new LLMSProgressTracker();
});