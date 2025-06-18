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

    async getLogs() {
        return this.request('/logs?limit=50');
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
                this.api.getLogs(),
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

        logs.forEach(log => {
            const entry = document.createElement('div');
            entry.className = 'log-entry p-2 border-bottom';
            entry.innerHTML = `
                <div class="d-flex">
                    <span class="text-muted me-3" style="min-width: 120px;">${log.timestamp || 'N/A'}</span>
                    <span class="badge bg-primary me-3">${(log.level || 'info').toUpperCase()}</span>
                    <span class="text-muted me-3">[${log.component || 'system'}]</span>
                    <span>${this.escapeHtml(log.message || 'No message')}</span>
                </div>
            `;
            container.appendChild(entry);
        });
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