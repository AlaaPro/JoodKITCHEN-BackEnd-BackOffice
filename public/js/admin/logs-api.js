/**
 * Simple Logs System for JoodKitchen Admin
 * Clean, minimal implementation without auto-refresh chaos
 */

class LogsAPI {
    constructor() {
        this.baseUrl = '/api/admin';
        this.token = localStorage.getItem('admin_token');
    }

    getHeaders() {
        const headers = {
            'Content-Type': 'application/json',
            'Accept': 'application/json'
        };
        
        if (this.token) {
            headers['Authorization'] = `Bearer ${this.token}`;
        }
        
        return headers;
    }

    async request(endpoint) {
        const response = await fetch(this.baseUrl + endpoint, {
            method: 'GET',
            headers: this.getHeaders()
        });
        
        if (response.status === 401) {
            window.location.href = '/admin/login';
            throw new Error('Authentication required');
        }
        
        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }
        
        return await response.json();
    }

    async getStats() {
        return this.request('/logs/stats');
    }

    async getLogs(filters = {}) {
        const params = new URLSearchParams();
        
        // Add filters as query parameters
        if (filters.level) params.append('level', filters.level);
        if (filters.component) params.append('component', filters.component);
        if (filters.dateStart) params.append('dateStart', filters.dateStart);
        if (filters.dateEnd) params.append('dateEnd', filters.dateEnd);
        if (filters.limit) params.append('limit', filters.limit);
        
        const queryString = params.toString();
        const endpoint = queryString ? `/logs?${queryString}` : '/logs?limit=50';
        
        console.log('🔍 Fetching logs with filters:', filters, 'endpoint:', endpoint);
        return this.request(endpoint);
    }

    async getErrors() {
        return this.request('/logs/errors');
    }

    async getDistribution() {
        return this.request('/logs/distribution');
    }
}

class LogsManager {
    constructor() {
        this.api = new LogsAPI();
        this.initialized = false;
        this.currentFilters = {};
    }

    async init() {
        if (this.initialized) {
            console.log('⚠️ LogsManager already initialized');
            return;
        }

        console.log('🚀 Initializing LogsManager...');
        
        // Check authentication
        const token = localStorage.getItem('admin_token');
        if (!token) {
            console.error('❌ No admin token found');
            window.location.href = '/admin/login';
            return;
        }

        // Setup event handlers
        this.setupEventHandlers();
        
        // Load data once
        await this.loadData();
        
        this.initialized = true;
        console.log('✅ LogsManager initialized');
    }

    async loadData() {
        try {
            console.log('📊 Loading logs data...');
            
            // Load basic data
            const [stats, logs, errors, distribution] = await Promise.all([
                this.api.getStats(),
                this.api.getLogs(this.currentFilters),
                this.api.getErrors(),
                this.api.getDistribution()
            ]);

            // Update UI
            this.updateStats(stats.data || {});
            this.updateLogs(logs.data || []);
            this.updateErrors(errors.data || []);
            this.updateDistribution(distribution.data || {});
            
            console.log('✅ Data loaded successfully');
            
        } catch (error) {
            console.error('❌ Error loading data:', error);
            this.showError('Erreur lors du chargement des données');
        }
    }

    updateStats(stats) {
        const elements = {
            'logs-today': stats.logs_today || 0,
            'errors': stats.errors || 0,
            'warnings': stats.warnings || 0,
            'info': stats.info || 0
        };

        Object.entries(elements).forEach(([key, value]) => {
            const element = document.querySelector(`[data-stat="${key}"]`);
            if (element) {
                element.textContent = value.toLocaleString();
            }
        });
    }

    updateLogs(logs) {
        const container = document.getElementById('logsContent');
        if (!container) return;

        container.innerHTML = '';
        
        if (logs.length === 0) {
            container.innerHTML = '<div class="text-center py-4">Aucun log trouvé</div>';
            return;
        }

        console.log('📋 Displaying logs:', logs.length, 'entries');

        logs.forEach(log => {
            const entry = document.createElement('div');
            entry.className = 'log-entry p-2 border-bottom';
            
            // Get appropriate badge color based on level
            const badgeColor = this.getLevelBadgeColor(log.level);
            
            entry.innerHTML = `
                <div class="d-flex">
                    <span class="text-muted me-3" style="min-width: 120px;">${log.timestamp || 'N/A'}</span>
                    <span class="badge bg-${badgeColor} me-3">${(log.level || 'info').toUpperCase()}</span>
                    <span class="text-muted me-3">[${log.component || 'system'}]</span>
                    <span>${this.escapeHtml(log.message || 'No message')}</span>
                </div>
            `;
            container.appendChild(entry);
        });
        
        console.log('✅ Logs displayed successfully');
    }

    getLevelBadgeColor(level) {
        const colors = {
            'error': 'danger',
            'warning': 'warning', 
            'info': 'primary',
            'debug': 'secondary'
        };
        return colors[level] || 'primary';
    }

    updateErrors(errors) {
        const container = document.querySelector('.recent-errors-list');
        if (!container) return;

        container.innerHTML = '';
        
        if (errors.length === 0) {
            container.innerHTML = '<div class="text-center py-3">Aucune erreur récente</div>';
            return;
        }

        errors.forEach(error => {
            const item = document.createElement('div');
            item.className = 'list-group-item px-0';
            item.innerHTML = `
                <div class="fw-semibold">${this.escapeHtml(error.title || 'Erreur')}</div>
                <small class="text-muted">${error.time || 'N/A'}</small>
            `;
            container.appendChild(item);
        });
    }

    updateDistribution(distribution) {
        const elements = {
            'info': distribution.info || 0,
            'warning': distribution.warning || 0,
            'error': distribution.error || 0,
            'debug': distribution.debug || 0
        };

        Object.entries(elements).forEach(([level, percentage]) => {
            const element = document.querySelector(`[data-distribution="${level}"]`);
            if (element) {
                element.textContent = `${percentage}%`;
            }
        });
    }

    showError(message) {
        console.error(message);
        // Simple alert for now
        const container = document.getElementById('logsContent');
        if (container) {
            container.innerHTML = `<div class="alert alert-danger">${message}</div>`;
        }
    }

    setupEventHandlers() {
        console.log('🎛️ Setting up filter event handlers...');
        
        // Apply filters button
        const applyFiltersBtn = document.getElementById('applyFilters');
        if (applyFiltersBtn) {
            applyFiltersBtn.addEventListener('click', () => {
                this.applyFilters();
            });
        }
        
        // Clear filters button
        const clearFiltersBtn = document.getElementById('clearFilters');
        if (clearFiltersBtn) {
            clearFiltersBtn.addEventListener('click', () => {
                this.clearFilters();
            });
        }
        
        // Refresh logs button
        const refreshLogsBtn = document.getElementById('refreshLogs');
        if (refreshLogsBtn) {
            refreshLogsBtn.addEventListener('click', () => {
                this.refreshLogs();
            });
        }
        
        console.log('✅ Event handlers setup complete');
    }

    async applyFilters() {
        try {
            console.log('🔍 Applying log filters...');
            
            // Get filter values
            const level = document.getElementById('logLevel')?.value || '';
            const component = document.getElementById('logComponent')?.value || '';
            const dateStart = document.getElementById('dateStart')?.value || '';
            const dateEnd = document.getElementById('dateEnd')?.value || '';
            
            // Build filters object
            this.currentFilters = {};
            if (level) this.currentFilters.level = level;
            if (component) this.currentFilters.component = component;
            if (dateStart) this.currentFilters.dateStart = dateStart;
            if (dateEnd) this.currentFilters.dateEnd = dateEnd;
            
            console.log('📋 Applied filters:', this.currentFilters);
            
            // Show loading
            const container = document.getElementById('logsContent');
            if (container) {
                container.innerHTML = '<div class="text-center py-4"><div class="spinner-border" role="status"></div><div class="mt-2">Application des filtres...</div></div>';
            }
            
            // Reload logs with filters
            const result = await this.api.getLogs(this.currentFilters);
            this.updateLogs(result.data || []);
            
            console.log('✅ Filters applied successfully');
            
        } catch (error) {
            console.error('❌ Error applying filters:', error);
            this.showError('Erreur lors de l\'application des filtres');
        }
    }

    clearFilters() {
        console.log('🧹 Clearing filters...');
        
        // Clear filter inputs
        document.getElementById('logLevel').value = '';
        document.getElementById('logComponent').value = '';
        document.getElementById('dateStart').value = '';
        document.getElementById('dateEnd').value = '';
        
        // Clear current filters
        this.currentFilters = {};
        
        // Reload data
        this.loadData();
        
        console.log('✅ Filters cleared');
    }

    async refreshLogs() {
        console.log('🔄 Manual refresh triggered...');
        
        const refreshBtn = document.getElementById('refreshLogs');
        if (refreshBtn) {
            const originalHtml = refreshBtn.innerHTML;
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualisation...';
            refreshBtn.disabled = true;
            
            try {
                await this.loadData();
                console.log('✅ Refresh completed');
            } finally {
                setTimeout(() => {
                    refreshBtn.innerHTML = originalHtml;
                    refreshBtn.disabled = false;
                }, 1000);
            }
        }
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}

// Simple initialization - NO auto-refresh, NO complexity
document.addEventListener('DOMContentLoaded', function() {
    // Only initialize once
    if (window.logsManager) {
        console.log('⚠️ LogsManager already exists');
        return;
    }

    // Check if we're on the logs page
    if (!document.getElementById('logsContent')) {
        console.log('ℹ️ Not on logs page, skipping initialization');
        return;
    }

    console.log('🔐 Auth check:', {
        hasToken: !!localStorage.getItem('admin_token'),
        hasUser: !!localStorage.getItem('admin_user')
    });

    // Initialize
    window.logsManager = new LogsManager();
    window.logsManager.init();
}); 