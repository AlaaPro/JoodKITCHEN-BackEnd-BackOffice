/**
 * User Activities API - JoodKitchen Admin Interface
 * Simple API manager for user activities without auto-refresh
 */

class UserActivitiesAPI {
    constructor() {
        this.baseUrl = '/api/admin/activities';
    }

    /**
     * Get JWT token from localStorage
     */
    getToken() {
        return localStorage.getItem('admin_token');
    }

    /**
     * Get headers with JWT token
     */
    getHeaders() {
        const token = this.getToken();
        return {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
        };
    }

    /**
     * Handle API response
     */
    async handleResponse(response) {
        if (response.status === 401) {
            localStorage.removeItem('admin_token');
            window.location.href = '/admin/login';
            return null;
        }

        if (!response.ok) {
            throw new Error(`HTTP ${response.status}: ${response.statusText}`);
        }

        return await response.json();
    }

    /**
     * Get activity statistics
     */
    async getStats() {
        const response = await fetch(`${this.baseUrl}/stats`, {
            method: 'GET',
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    /**
     * Get activities with filters
     */
    async getActivities(filters = {}, limit = 50) {
        const params = new URLSearchParams();
        Object.keys(filters).forEach(key => {
            if (filters[key] !== null && filters[key] !== '') {
                params.append(key, filters[key]);
            }
        });
        if (limit) params.append('limit', limit.toString());

        const response = await fetch(`${this.baseUrl}?${params.toString()}`, {
            method: 'GET',
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    /**
     * Get activity distribution
     */
    async getDistribution() {
        const response = await fetch(`${this.baseUrl}/distribution`, {
            method: 'GET',
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    /**
     * Get profile distribution
     */
    async getProfiles() {
        const response = await fetch(`${this.baseUrl}/profiles`, {
            method: 'GET',
            headers: this.getHeaders()
        });
        return await this.handleResponse(response);
    }

    /**
     * Export activities
     */
    async exportActivities(filters = {}, format = 'csv') {
        try {
            const response = await fetch(`${this.baseUrl}/export`, {
                method: 'POST',
                headers: this.getHeaders(),
                body: JSON.stringify({ filters, format })
            });

            return await this.handleResponse(response);
        } catch (error) {
            console.error('Error exporting activities:', error);
            throw error;
        }
    }
}

class ActivitiesManager {
    constructor() {
        this.api = new UserActivitiesAPI();
        this.initialized = false;
        this.currentFilters = {};
    }

    /**
     * Initialize the activities manager
     */
    async init() {
        if (this.initialized) {
            return;
        }

        try {
            console.log('Initializing Activities Manager...');
            
            // Setup refresh button
            this.setupRefreshButton();
            
            // Setup filters
            this.setupFilters();
            
            // Load initial data
            await this.loadAllData();
            
            this.initialized = true;
            console.log('Activities Manager initialized successfully');
            
        } catch (error) {
            console.error('Failed to initialize Activities Manager:', error);
            this.showError('Erreur lors de l\'initialisation');
        }
    }

    /**
     * Setup manual refresh button
     */
    setupRefreshButton() {
        const refreshBtn = document.getElementById('refreshActivities');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => {
                this.loadAllData();
            });
        }
    }

    /**
     * Setup filter controls
     */
    setupFilters() {
        const filterBtn = document.getElementById('applyActivityFilters');
        if (filterBtn) {
            filterBtn.addEventListener('click', () => {
                this.applyFilters();
            });
        }
    }

    /**
     * Apply current filters and reload data
     */
    async applyFilters() {
        try {
            // Get filter values - handle both dedicated page and logs tab element IDs
            this.currentFilters = {
                profileType: document.getElementById('userProfileType')?.value || 
                            document.getElementById('activityProfileType')?.value || '',
                action: document.getElementById('userActivityAction')?.value || 
                       document.getElementById('activityAction')?.value || '',
                entityType: document.getElementById('userEntityType')?.value || 
                           document.getElementById('activityEntityType')?.value || '',
                dateStart: document.getElementById('activityDateStart')?.value || '',
                dateEnd: document.getElementById('activityDateEnd')?.value || ''
            };

            // Remove empty filters
            Object.keys(this.currentFilters).forEach(key => {
                if (!this.currentFilters[key]) {
                    delete this.currentFilters[key];
                }
            });

            console.log('Applying filters:', this.currentFilters);
            await this.loadActivities();
            
        } catch (error) {
            console.error('Error applying filters:', error);
            this.showError('Erreur lors de l\'application des filtres');
        }
    }

    /**
     * Load all data (stats, activities, distributions)
     */
    async loadAllData() {
        console.log('Loading all activities data...');
        
        try {
            await Promise.all([
                this.loadStats(),
                this.loadActivities(),
                this.loadDistribution(),
                this.loadProfiles()
            ]);
            
            console.log('All activities data loaded successfully');
            
        } catch (error) {
            console.error('Error loading activities data:', error);
            this.showError('Erreur lors du chargement des données');
        }
    }

    /**
     * Load activity statistics
     */
    async loadStats() {
        try {
            const result = await this.api.getStats();
            if (result && result.success) {
                this.updateStats(result.data);
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    /**
     * Load activities list
     */
    async loadActivities() {
        try {
            const result = await this.api.getActivities(this.currentFilters, 50);
            if (result && result.success) {
                this.updateActivitiesList(result.data);
            }
        } catch (error) {
            console.error('Error loading activities:', error);
        }
    }

    /**
     * Load activity distribution
     */
    async loadDistribution() {
        try {
            const result = await this.api.getDistribution();
            if (result && result.success) {
                this.updateDistributionStats(result.data);
            }
        } catch (error) {
            console.error('Error loading distribution:', error);
        }
    }

    /**
     * Load profile distribution
     */
    async loadProfiles() {
        try {
            const result = await this.api.getProfiles();
            if (result && result.success) {
                this.updateProfilesStats(result.data);
            }
        } catch (error) {
            console.error('Error loading profiles:', error);
        }
    }

    /**
     * Update statistics display
     */
    updateStats(stats) {
        // Update stat cards
        const totalElement = document.querySelector('[data-stat="total-activities"]');
        if (totalElement) totalElement.textContent = stats.total_activities || '0';

        const todayElement = document.querySelector('[data-stat="today-activities"]');
        if (todayElement) todayElement.textContent = stats.today_activities || '0';

        const weekElement = document.querySelector('[data-stat="week-activities"]');
        if (weekElement) weekElement.textContent = stats.week_activities || '0';

        console.log('Stats updated:', stats);
    }

    /**
     * Update activities list
     */
    updateActivitiesList(activities) {
        // Try different container elements depending on page structure
        let container = document.getElementById('activitiesTableBody'); // Dedicated activities page
        if (!container) {
            container = document.getElementById('activitiesContent'); // Logs page tab
        }
        
        if (!container) return;

        if (!activities || activities.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted py-5">
                    <i class="fas fa-info-circle me-2"></i>Aucune activité trouvée
                </div>
            `;
            return;
        }

        // Create table format for activities
        const activitiesHtml = `
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead class="table-light">
                        <tr>
                            <th>Horodatage</th>
                            <th>Action</th>
                            <th>Utilisateur</th>
                            <th>Entité</th>
                            <th>ID</th>
                            <th>Détails</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${activities.map(activity => `
                            <tr>
                                <td><small class="text-muted">${activity.logged_at_formatted || 'N/A'}</small></td>
                                <td><span class="badge bg-${this.getActionBadgeColor(activity.action)}">${activity.action || 'N/A'}</span></td>
                                <td>${activity.user_name || 'Système'}</td>
                                <td>${activity.entity_type || 'N/A'}</td>
                                <td>#${activity.entity_id || 'N/A'}</td>
                                <td><small class="text-muted">${this.formatChanges(activity.changes)}</small></td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
        `;

        container.innerHTML = activitiesHtml;
        console.log(`Activities list updated with ${activities.length} items`);
    }

    /**
     * Update distribution stats
     */
    updateDistributionStats(distribution) {
        Object.keys(distribution).forEach(action => {
            const element = document.querySelector(`[data-action="${action}"]`);
            if (element) {
                element.textContent = `${distribution[action]}%`;
            }
        });
        console.log('Distribution updated:', distribution);
    }

    /**
     * Update profiles stats
     */
    updateProfilesStats(profiles) {
        Object.keys(profiles).forEach(profile => {
            const element = document.querySelector(`[data-profile="${profile}"]`);
            if (element) {
                element.textContent = `${profiles[profile]}%`;
            }
        });
        console.log('Profiles updated:', profiles);
    }

    /**
     * Get badge color for action type
     */
    getActionBadgeColor(action) {
        const colors = {
            'insert': 'success',
            'update': 'warning', 
            'remove': 'danger',
            'create': 'success',
            'delete': 'danger'
        };
        return colors[action] || 'secondary';
    }

    /**
     * Format changes for display
     */
    formatChanges(changes) {
        if (!changes || typeof changes !== 'object') {
            return 'Aucun détail';
        }
        
        const changeCount = Object.keys(changes).length;
        return changeCount > 0 ? `${changeCount} champ(s) modifié(s)` : 'Aucun changement';
    }

    /**
     * Show error message
     */
    showError(message) {
        console.error('Activities Manager Error:', message);
        
        // Show error in UI if there's an error container
        const errorContainer = document.getElementById('activitiesError');
        if (errorContainer) {
            errorContainer.innerHTML = `
                <div class="alert alert-danger">
                    <i class="fas fa-exclamation-triangle me-2"></i>${message}
                </div>
            `;
        }
    }
}

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded, initializing Activities Manager...');
    
    // Only initialize if we're on the activities page
    if (document.getElementById('refreshActivities') || document.querySelector('.activities-container')) {
        window.activitiesManager = new ActivitiesManager();
        window.activitiesManager.init();
    }
}); 