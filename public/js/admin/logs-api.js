/**
 * LogsAPI - JoodKitchen Logs System API Client
 * 
 * This class handles all API communication for the logs system,
 * integrating with DataDogAuditBundle backend endpoints
 */
class LogsAPI {
    constructor() {
        this.baseUrl = '/api/admin';
        this.token = localStorage.getItem('admin_token');
        this.lastCheckTimestamp = Math.floor(Date.now() / 1000);
    }

    /**
     * Get authentication headers
     */
    getHeaders() {
        return {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${this.token}`
        };
    }

    /**
     * Handle API response
     */
    async handleResponse(response) {
        if (!response.ok) {
            const errorData = await response.json().catch(() => ({}));
            throw new Error(errorData.message || `HTTP ${response.status}: ${response.statusText}`);
        }
        return await response.json();
    }

    /**
     * Get log statistics for dashboard widgets
     */
    async getLogStatistics() {
        try {
            const response = await fetch(`${this.baseUrl}/logs/stats`, {
                method: 'GET',
                headers: this.getHeaders()
            });
            
            const data = await this.handleResponse(response);
            return data.data;
        } catch (error) {
            console.error('Error fetching log statistics:', error);
            throw error;
        }
    }

    /**
     * Get logs with filtering options
     */
    async getLogs(filters = {}) {
        try {
            const params = new URLSearchParams();
            
            if (filters.level) params.append('level', filters.level);
            if (filters.component) params.append('component', filters.component);
            if (filters.dateStart) params.append('dateStart', filters.dateStart);
            if (filters.dateEnd) params.append('dateEnd', filters.dateEnd);
            if (filters.limit) params.append('limit', filters.limit);
            
            const response = await fetch(`${this.baseUrl}/logs?${params}`, {
                method: 'GET',
                headers: this.getHeaders()
            });
            
            const data = await this.handleResponse(response);
            return data.data;
        } catch (error) {
            console.error('Error fetching logs:', error);
            throw error;
        }
    }

    /**
     * Get recent logs for real-time updates
     */
    async getRecentLogs(limit = 20, since = null) {
        try {
            const params = new URLSearchParams();
            params.append('limit', limit);
            
            if (since) {
                params.append('since', since);
            }
            
            const response = await fetch(`${this.baseUrl}/logs/recent?${params}`, {
                method: 'GET',
                headers: this.getHeaders()
            });
            
            const data = await this.handleResponse(response);
            this.lastCheckTimestamp = data.last_check;
            return data;
        } catch (error) {
            console.error('Error fetching recent logs:', error);
            throw error;
        }
    }

    /**
     * Get recent errors for sidebar
     */
    async getRecentErrors() {
        try {
            const response = await fetch(`${this.baseUrl}/logs/errors`, {
                method: 'GET',
                headers: this.getHeaders()
            });
            
            const data = await this.handleResponse(response);
            return data.data;
        } catch (error) {
            console.error('Error fetching recent errors:', error);
            throw error;
        }
    }

    /**
     * Get log distribution for charts
     */
    async getLogDistribution() {
        try {
            const response = await fetch(`${this.baseUrl}/logs/distribution`, {
                method: 'GET',
                headers: this.getHeaders()
            });
            
            const data = await this.handleResponse(response);
            return data.data;
        } catch (error) {
            console.error('Error fetching log distribution:', error);
            throw error;
        }
    }

    /**
     * Get system health metrics
     */
    async getSystemHealth() {
        try {
            const response = await fetch(`${this.baseUrl}/system/health`, {
                method: 'GET',
                headers: this.getHeaders()
            });
            
            const data = await this.handleResponse(response);
            return data.data;
        } catch (error) {
            console.error('Error fetching system health:', error);
            throw error;
        }
    }

    /**
     * Export logs
     */
    async exportLogs(filters = {}, format = 'csv') {
        try {
            const response = await fetch(`${this.baseUrl}/logs/export`, {
                method: 'POST',
                headers: this.getHeaders(),
                body: JSON.stringify({
                    filters: filters,
                    format: format
                })
            });
            
            const data = await this.handleResponse(response);
            return data;
        } catch (error) {
            console.error('Error exporting logs:', error);
            throw error;
        }
    }

    /**
     * Check for updates since last check
     */
    async checkForUpdates() {
        try {
            return await this.getRecentLogs(50, this.lastCheckTimestamp);
        } catch (error) {
            console.error('Error checking for updates:', error);
            return { has_updates: false, data: [] };
        }
    }
}

/**
 * LogsManager - Manages the logs interface
 */
class LogsManager {
    constructor() {
        this.api = new LogsAPI();
        this.autoRefreshEnabled = true;
        this.autoRefreshInterval = null;
        this.currentFilters = {};
        this.isLoading = false;
    }

    /**
     * Initialize the logs manager
     */
    async init() {
        console.log('🔄 Initializing LogsManager...');
        
        // Load initial data
        await this.loadInitialData();
        
        // Setup event listeners
        this.setupEventListeners();
        
        // Start auto-refresh
        this.startAutoRefresh();
        
        console.log('✅ LogsManager initialized successfully');
    }

    /**
     * Load initial data for all components
     */
    async loadInitialData() {
        try {
            this.showLoading(true);
            
            // Load in parallel for better performance
            const [stats, logs, errors, distribution, health] = await Promise.all([
                this.api.getLogStatistics(),
                this.api.getLogs({ limit: 50 }),
                this.api.getRecentErrors(),
                this.api.getLogDistribution(),
                this.api.getSystemHealth()
            ]);
            
            // Update UI components
            this.updateStatistics(stats);
            this.updateLogsDisplay(logs);
            this.updateRecentErrors(errors);
            this.updateLogDistribution(distribution);
            this.updateSystemHealth(health);
            
        } catch (error) {
            console.error('Error loading initial data:', error);
            this.showError('Erreur lors du chargement des données');
        } finally {
            this.showLoading(false);
        }
    }

    /**
     * Update statistics widgets
     */
    updateStatistics(stats) {
        const widgets = {
            'logs-today': stats.logs_today || 0,
            'errors': stats.errors || 0,
            'warnings': stats.warnings || 0,
            'info': stats.info || 0
        };

        Object.entries(widgets).forEach(([key, value]) => {
            const element = document.querySelector(`[data-stat="${key}"]`);
            if (element) {
                element.textContent = value.toLocaleString();
            }
        });
    }

    /**
     * Update logs display
     */
    updateLogsDisplay(logs) {
        const logsContainer = document.getElementById('logsContent');
        if (!logsContainer) return;

        logsContainer.innerHTML = '';

        logs.forEach(log => {
            const logEntry = this.createLogEntry(log);
            logsContainer.appendChild(logEntry);
        });
    }

    /**
     * Create log entry element
     */
    createLogEntry(log) {
        const entry = document.createElement('div');
        entry.className = 'log-entry p-2 border-bottom';
        entry.dataset.level = log.level;
        entry.dataset.component = log.component;

        const badgeClass = this.getLevelBadgeClass(log.level);
        const levelText = log.level.toUpperCase();

        entry.innerHTML = `
            <div class="d-flex">
                <span class="log-time text-muted me-3" style="min-width: 120px;">${log.timestamp}</span>
                <span class="badge ${badgeClass} me-3">${levelText}</span>
                <span class="log-component text-muted me-3">[${log.component}]</span>
                <span class="log-message">${this.escapeHtml(log.message)}</span>
            </div>
        `;

        return entry;
    }

    /**
     * Get CSS class for log level badge
     */
    getLevelBadgeClass(level) {
        const classes = {
            'error': 'jood-secondary-bg',
            'warning': 'jood-warning-bg',
            'info': 'bg-primary',
            'debug': 'bg-secondary'
        };
        return classes[level] || 'bg-secondary';
    }

    /**
     * Update recent errors sidebar
     */
    updateRecentErrors(errors) {
        const errorsContainer = document.querySelector('.recent-errors-list');
        if (!errorsContainer) return;

        errorsContainer.innerHTML = '';

        errors.forEach(error => {
            const errorItem = document.createElement('div');
            errorItem.className = 'list-group-item d-flex justify-content-between align-items-start px-0';
            errorItem.innerHTML = `
                <div>
                    <div class="fw-semibold">${this.escapeHtml(error.title)}</div>
                    <small class="text-muted">${error.time} - ${error.component}</small>
                </div>
                <span class="badge jood-secondary-bg">${error.count}x</span>
            `;
            errorsContainer.appendChild(errorItem);
        });
    }

    /**
     * Update log distribution chart
     */
    updateLogDistribution(distribution) {
        const distributionElements = {
            'info': document.querySelector('[data-distribution="info"]'),
            'warning': document.querySelector('[data-distribution="warning"]'),
            'error': document.querySelector('[data-distribution="error"]'),
            'debug': document.querySelector('[data-distribution="debug"]')
        };

        Object.entries(distribution).forEach(([level, percentage]) => {
            const element = distributionElements[level];
            if (element) {
                element.textContent = `${percentage}%`;
            }
        });
    }

    /**
     * Update system health metrics
     */
    updateSystemHealth(health) {
        const healthElements = {
            'cpu': document.querySelector('[data-health="cpu"]'),
            'memory': document.querySelector('[data-health="memory"]'),
            'disk': document.querySelector('[data-health="disk"]'),
            'network': document.querySelector('[data-health="network"]')
        };

        Object.entries(health).forEach(([metric, value]) => {
            const element = healthElements[metric];
            if (element) {
                element.textContent = `${value}%`;
                
                // Update progress bar
                const progressBar = element.nextElementSibling?.querySelector('.progress-bar');
                if (progressBar) {
                    progressBar.style.width = `${value}%`;
                }
            }
        });
    }

    /**
     * Setup event listeners
     */
    setupEventListeners() {
        // Auto-refresh toggle
        const autoRefreshToggle = document.getElementById('autoRefresh');
        if (autoRefreshToggle) {
            autoRefreshToggle.addEventListener('change', (e) => {
                this.autoRefreshEnabled = e.target.checked;
                if (this.autoRefreshEnabled) {
                    this.startAutoRefresh();
                } else {
                    this.stopAutoRefresh();
                }
            });
        }

        // Manual refresh button
        const refreshButton = document.getElementById('refreshLogs');
        if (refreshButton) {
            refreshButton.addEventListener('click', () => this.refreshLogs());
        }

        // Filter application
        const applyFiltersButton = document.getElementById('applyFilters');
        if (applyFiltersButton) {
            applyFiltersButton.addEventListener('click', () => this.applyFilters());
        }

        // Clear filters
        const clearFiltersButton = document.getElementById('clearFilters');
        if (clearFiltersButton) {
            clearFiltersButton.addEventListener('click', () => this.clearFilters());
        }

        // Toggle filters panel
        const toggleFiltersButton = document.getElementById('toggleFilters');
        if (toggleFiltersButton) {
            toggleFiltersButton.addEventListener('click', () => this.toggleFiltersPanel());
        }
    }

    /**
     * Start auto-refresh
     */
    startAutoRefresh() {
        this.stopAutoRefresh(); // Clear existing interval
        
        if (this.autoRefreshEnabled) {
            this.autoRefreshInterval = setInterval(async () => {
                try {
                    const updates = await this.api.checkForUpdates();
                    if (updates.has_updates && updates.data.length > 0) {
                        this.addNewLogs(updates.data);
                    }
                } catch (error) {
                    console.error('Auto-refresh error:', error);
                }
            }, 10000); // Check every 10 seconds
        }
    }

    /**
     * Stop auto-refresh
     */
    stopAutoRefresh() {
        if (this.autoRefreshInterval) {
            clearInterval(this.autoRefreshInterval);
            this.autoRefreshInterval = null;
        }
    }

    /**
     * Add new logs to the display
     */
    addNewLogs(newLogs) {
        const logsContainer = document.getElementById('logsContent');
        if (!logsContainer) return;

        // Add new logs at the beginning
        newLogs.reverse().forEach(log => {
            const logEntry = this.createLogEntry(log);
            logsContainer.insertBefore(logEntry, logsContainer.firstChild);
        });

        // Keep only last 50 entries
        while (logsContainer.children.length > 50) {
            logsContainer.removeChild(logsContainer.lastChild);
        }

        // Show notification
        this.showNotification(`${newLogs.length} nouveau${newLogs.length > 1 ? 'x' : ''} log${newLogs.length > 1 ? 's' : ''}`);
    }

    /**
     * Apply current filters
     */
    async applyFilters() {
        try {
            this.currentFilters = {
                level: document.getElementById('logLevel')?.value || '',
                component: document.getElementById('logComponent')?.value || '',
                dateStart: document.getElementById('dateStart')?.value || '',
                dateEnd: document.getElementById('dateEnd')?.value || '',
                limit: 50
            };

            // Remove empty filters
            Object.keys(this.currentFilters).forEach(key => {
                if (!this.currentFilters[key]) {
                    delete this.currentFilters[key];
                }
            });

            const logs = await this.api.getLogs(this.currentFilters);
            this.updateLogsDisplay(logs);
            
        } catch (error) {
            console.error('Error applying filters:', error);
            this.showError('Erreur lors de l\'application des filtres');
        }
    }

    /**
     * Clear all filters
     */
    async clearFilters() {
        // Reset form elements
        const filterElements = ['logLevel', 'logComponent', 'dateStart', 'dateEnd'];
        filterElements.forEach(id => {
            const element = document.getElementById(id);
            if (element) element.value = '';
        });

        this.currentFilters = {};
        
        try {
            const logs = await this.api.getLogs({ limit: 50 });
            this.updateLogsDisplay(logs);
        } catch (error) {
            console.error('Error clearing filters:', error);
        }
    }

    /**
     * Refresh logs manually
     */
    async refreshLogs() {
        const refreshButton = document.getElementById('refreshLogs');
        if (refreshButton) {
            refreshButton.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualisation...';
        }

        try {
            await this.loadInitialData();
        } catch (error) {
            console.error('Error refreshing logs:', error);
        } finally {
            if (refreshButton) {
                setTimeout(() => {
                    refreshButton.innerHTML = '<i class="fas fa-sync-alt"></i> Actualiser';
                }, 1000);
            }
        }
    }

    /**
     * Toggle filters panel
     */
    toggleFiltersPanel() {
        const panel = document.getElementById('filtersPanel');
        if (panel) {
            panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
        }
    }

    /**
     * Show loading state
     */
    showLoading(show) {
        this.isLoading = show;
        // You can add loading spinner logic here
    }

    /**
     * Show error message
     */
    showError(message) {
        console.error(message);
        // You can add error display logic here
    }

    /**
     * Show notification
     */
    showNotification(message) {
        // You can add notification display logic here
        console.log('📢', message);
    }

    /**
     * Escape HTML to prevent XSS
     */
    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize on logs page
    if (document.getElementById('logsContent')) {
        window.logsManager = new LogsManager();
        window.logsManager.init();
    }
}); 