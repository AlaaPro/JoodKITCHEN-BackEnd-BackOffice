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
            const profileTypeEl = document.getElementById('userProfileType') || document.getElementById('activityProfileType');
            const actionEl = document.getElementById('userActivityAction') || document.getElementById('activityAction');
            const entityTypeEl = document.getElementById('userEntityType') || document.getElementById('activityEntityType');
            const dateStartEl = document.getElementById('activityDateStart');
            const dateEndEl = document.getElementById('activityDateEnd');
            
            // Debug - log found elements
            console.log('🔍 Filter elements found:', {
                profileType: profileTypeEl?.id,
                action: actionEl?.id,
                entityType: entityTypeEl?.id,
                dateStart: dateStartEl?.id,
                dateEnd: dateEndEl?.id
            });
            
            this.currentFilters = {
                profileType: profileTypeEl?.value || '',
                action: actionEl?.value || '',
                entityType: entityTypeEl?.value || '',
                dateStart: dateStartEl?.value || '',
                dateEnd: dateEndEl?.value || ''
            };

            // Debug - log raw filter values
            console.log('📋 Raw filter values:', this.currentFilters);

            // Remove empty filters
            Object.keys(this.currentFilters).forEach(key => {
                if (!this.currentFilters[key]) {
                    delete this.currentFilters[key];
                }
            });

            console.log('✅ Final filters to send:', this.currentFilters);
            await this.loadActivities();
            
        } catch (error) {
            console.error('❌ Error applying filters:', error);
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
     * Update activities list - Enhanced display with better formatting
     */
    updateActivitiesList(activities) {
        // Try different container elements depending on page structure
        let container = document.getElementById('activitiesTableBody'); // Dedicated activities page
        if (!container) {
            container = document.getElementById('activitiesContent'); // Logs page tab
        }
        
        if (!container) {
            console.warn('Activities container not found');
            return;
        }

        if (!activities || activities.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted py-5">
                    <i class="fas fa-inbox fa-3x mb-3 opacity-50"></i>
                    <div class="h5">Aucune activité trouvée</div>
                    <small class="text-muted">Essayez d'ajuster les filtres ou de générer des données de test</small>
                </div>
            `;
            return;
        }

        // Enhanced activity display with better UX
        const activitiesHtml = `
            <div class="activities-header d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                <span class="small text-muted">
                    <i class="fas fa-list me-1"></i>
                    ${activities.length} activité(s) trouvée(s)
                </span>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary" onclick="window.activitiesManager?.loadActivities()">
                        <i class="fas fa-sync-alt"></i> Actualiser
                    </button>
                    <button class="btn btn-outline-secondary" onclick="window.activitiesManager?.exportActivities()">
                        <i class="fas fa-download"></i> Exporter
                    </button>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-hover mb-0">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 140px;">Horodatage</th>
                            <th style="width: 100px;">Action</th>
                            <th style="width: 150px;">Utilisateur</th>
                            <th style="width: 120px;">Entité</th>
                            <th style="width: 60px;">ID</th>
                            <th>Détails</th>
                        </tr>
                    </thead>
                    <tbody>
                        ${activities.map(activity => `
                            <tr class="activity-row">
                                <td>
                                    <small class="text-muted">
                                        <i class="fas fa-clock me-1"></i>
                                        ${activity.logged_at_formatted || 'N/A'}
                                    </small>
                                </td>
                                <td>
                                    <span class="badge bg-${this.getActionBadgeColor(activity.action)} d-inline-flex align-items-center">
                                        <i class="fas fa-${this.getActionIcon(activity.action)} me-1"></i>
                                        ${activity.action || 'N/A'}
                                    </span>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-user-circle me-2 text-${this.getProfileColor(activity.user_name)}"></i>
                                        <span class="small">${activity.user_name || 'Système'}</span>
                                    </div>
                                </td>
                                <td>
                                    <div class="d-flex align-items-center">
                                        <i class="fas fa-${this.getEntityIcon(activity.entity_type)} me-2 text-primary"></i>
                                        <span class="small">${activity.entity_type || 'N/A'}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="badge badge-outline-secondary small">
                                        #${activity.entity_id || 'N/A'}
                                    </span>
                                </td>
                                <td>
                                    <div class="changes-preview">
                                        ${this.renderChangesPreview(activity.changes)}
                                    </div>
                                </td>
                            </tr>
                        `).join('')}
                    </tbody>
                </table>
            </div>
            <div class="mt-3 text-center">
                <small class="text-muted">
                    Dernière mise à jour: ${new Date().toLocaleTimeString('fr-FR')}
                </small>
            </div>
        `;

        container.innerHTML = activitiesHtml;
        console.log(`✅ Activities list updated with ${activities.length} items`);
    }

    /**
     * Get icon for action type
     */
    getActionIcon(action) {
        const icons = {
            'insert': 'plus',
            'create': 'plus',
            'update': 'edit',
            'remove': 'trash',
            'delete': 'trash-alt'
        };
        return icons[action] || 'circle';
    }

    /**
     * Render changes preview with better formatting
     */
    renderChangesPreview(changes) {
        if (!changes || typeof changes !== 'object' || Object.keys(changes).length === 0) {
            return '<small class="text-muted">Aucun changement</small>';
        }

        const changeCount = Object.keys(changes).length;
        const entries = Object.entries(changes).slice(0, 2);
        
        let preview = entries.map(([field, change]) => {
            if (typeof change === 'object' && change.old !== undefined && change.new !== undefined) {
                const oldValue = String(change.old).length > 15 ? String(change.old).substring(0, 15) + '...' : change.old;
                const newValue = String(change.new).length > 15 ? String(change.new).substring(0, 15) + '...' : change.new;
                return `<div class="small mb-1"><strong>${field}:</strong> <span class="text-danger">${oldValue}</span> → <span class="text-success">${newValue}</span></div>`;
            }
            return `<div class="small mb-1"><strong>${field}:</strong> modifié</div>`;
        }).join('');

        if (changeCount > 2) {
            preview += `<div class="small text-muted">... et ${changeCount - 2} autre(s) modification(s)</div>`;
        }

        return `
            <div class="changes-summary">
                ${preview}
                ${changeCount > 0 ? `
                    <details class="mt-1">
                        <summary class="cursor-pointer small text-primary">Voir tous les détails</summary>
                        <pre class="mt-2 p-2 bg-light rounded small" style="max-height: 150px; overflow-y: auto;">${JSON.stringify(changes, null, 2)}</pre>
                    </details>
                ` : ''}
            </div>
        `;
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
        if (changeCount === 0) return 'Aucun changement';
        
        // Show first few changes in summary
        const entries = Object.entries(changes).slice(0, 2);
        const summary = entries.map(([field, change]) => {
            if (typeof change === 'object' && change.old !== undefined && change.new !== undefined) {
                return `${field}: ${String(change.old).substring(0, 20)}... → ${String(change.new).substring(0, 20)}...`;
            }
            return `${field}: modifié`;
        }).join(', ');
        
        return changeCount > 2 ? `${summary} +${changeCount - 2} autres` : summary;
    }

    /**
     * Get profile-based color for user identification
     */
    getProfileColor(userName) {
        if (!userName || userName === 'Système') return 'secondary';
        if (userName.toLowerCase().includes('admin')) return 'primary';
        if (userName.toLowerCase().includes('kitchen')) return 'warning';
        if (userName.toLowerCase().includes('client')) return 'info';
        return 'secondary';
    }

    /**
     * Get icon for entity type
     */
    getEntityIcon(entityType) {
        const icons = {
            'Utilisateur': 'user',
            'User': 'user',
            'Menu': 'utensils',
            'Plat': 'hamburger',
            'Commande': 'shopping-cart',
            'Permission': 'key',
            'Profil Admin': 'user-shield',
            'AdminProfile': 'user-shield',
            'Profil Client': 'user-circle',
            'ClientProfile': 'user-circle'
        };
        return icons[entityType] || 'cube';
    }

    /**
     * Export activities with current filters
     */
    async exportActivities(format = 'csv') {
        try {
            console.log('🔽 Exporting activities...');
            const result = await this.api.exportActivities(this.currentFilters, format);
            
            if (result && result.success) {
                // Create and trigger download
                const blob = new Blob([result.data], { 
                    type: format === 'csv' ? 'text/csv' : 'application/json' 
                });
                const url = window.URL.createObjectURL(blob);
                const a = document.createElement('a');
                a.href = url;
                a.download = `activities_export_${new Date().toISOString().split('T')[0]}.${format}`;
                document.body.appendChild(a);
                a.click();
                document.body.removeChild(a);
                window.URL.revokeObjectURL(url);
                
                console.log('✅ Export completed');
                if (window.showSuccess) {
                    window.showSuccess('Export terminé avec succès');
                }
            }
        } catch (error) {
            console.error('Export failed:', error);
            if (window.showError) {
                window.showError('Erreur lors de l\'export');
            }
        }
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
        
        // Also use global error handler if available
        if (window.showError) {
            window.showError(message);
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