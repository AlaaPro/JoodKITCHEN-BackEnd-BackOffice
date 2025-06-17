/**
 * AdminAPI - Complete API communication layer for JoodKitchen Admin
 * Handles all API requests with JWT authentication, error handling, and caching
 */
(function(global) {
    'use strict';
    
    // Prevent redeclaration
    if (global.AdminAPI) {
        return;
    }

    class AdminAPI {
        constructor() {
            this.baseURL = '/api';
            this.token = null;
            this.cache = new Map();
            this.cacheTimeout = 5 * 60 * 1000; // 5 minutes
            
            this.init();
        }

        init() {
            // Get token from localStorage
            this.token = localStorage.getItem('admin_token');
            
            // Set up request interceptor
            this.setupRequestInterceptor();
        }

        setupRequestInterceptor() {
            // Add token to all requests
            this.defaultHeaders = {
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            };
            
            if (this.token) {
                this.defaultHeaders['Authorization'] = `Bearer ${this.token}`;
            }
        }

        updateToken(token) {
            this.token = token;
            if (token) {
                localStorage.setItem('admin_token', token);
                this.defaultHeaders['Authorization'] = `Bearer ${token}`;
            } else {
                localStorage.removeItem('admin_token');
                delete this.defaultHeaders['Authorization'];
            }
        }

        // ==================== CORE REQUEST METHODS ====================

        async request(method, endpoint, data = null, options = {}) {
            const url = `${this.baseURL}${endpoint}`;
            const config = {
                method: method.toUpperCase(),
                headers: { ...this.defaultHeaders, ...options.headers },
                ...options
            };

            if (data && method.toUpperCase() !== 'GET') {
                if (data instanceof FormData) {
                    delete config.headers['Content-Type']; // Let browser set multipart boundary
                    config.body = data;
                } else {
                    config.body = JSON.stringify(data);
                }
            }

            try {
                AdminUtils.showLoading();
                
                const response = await fetch(url, config);
                const responseData = await this.handleResponse(response);
                
                AdminUtils.hideLoading();
                return responseData;
            } catch (error) {
                AdminUtils.hideLoading();
                await this.handleError(error);
                throw error;
            }
        }

        async handleResponse(response) {
            if (response.status === 401) {
                // Token expired or invalid
                AdminAuth.logout();
                throw new Error('Session expirée. Veuillez vous reconnecter.');
            }

            const responseText = await response.text();
            let responseData;

            try {
                responseData = responseText ? JSON.parse(responseText) : {};
            } catch (e) {
                responseData = { message: responseText };
            }

            if (!response.ok) {
                const error = new Error(responseData.message || `HTTP ${response.status}`);
                error.status = response.status;
                error.data = responseData;
                throw error;
            }

            return responseData;
        }

        async handleError(error) {
            console.error('API Error:', error);
            
            if (error.status === 401) {
                AdminUtils.showAlert('Session expirée. Redirection vers la connexion...', 'warning');
                setTimeout(() => {
                    window.location.href = '/admin/login';
                }, 2000);
            } else if (error.status >= 500) {
                AdminUtils.showAlert('Erreur serveur. Veuillez réessayer plus tard.', 'error');
            } else {
                AdminUtils.showAlert(error.message || 'Une erreur est survenue', 'error');
            }
        }

        // ==================== AUTHENTICATION ====================

        async login(credentials) {
            const response = await this.request('POST', '/auth/login', credentials);
            if (response.token) {
                this.updateToken(response.token);
            }
            return response;
        }

        async logout() {
            try {
                await this.request('POST', '/auth/logout');
            } catch (error) {
                // Ignore logout errors
            } finally {
                this.updateToken(null);
            }
        }

        async getProfile() {
            return this.request('GET', '/auth/profile');
        }

        async refreshToken() {
            const response = await this.request('POST', '/auth/refresh');
            if (response.token) {
                this.updateToken(response.token);
            }
            return response;
        }

        // ==================== USERS MANAGEMENT ====================

        async getUsers(params = {}) {
            const queryString = new URLSearchParams(params).toString();
            return this.request('GET', `/users${queryString ? '?' + queryString : ''}`);
        }

        async getAdminUsers(params = {}) {
            const queryString = new URLSearchParams(params).toString();
            return this.request('GET', `/admin/users${queryString ? '?' + queryString : ''}`);
        }

        async getUser(id) {
            return this.request('GET', `/users/${id}`);
        }

        async createUser(userData) {
            return this.request('POST', '/admin/create-user', userData);
        }

        async updateUser(id, userData) {
            return this.request('PUT', `/users/${id}`, userData);
        }

        async updateAdminUser(id, userData) {
            return this.request('PUT', `/admin/update-user/${id}`, userData);
        }

        async deleteUser(id) {
            return this.request('DELETE', `/users/${id}`);
        }

        async getUserOrders(userId, params = {}) {
            const queryString = new URLSearchParams(params).toString();
            return this.request('GET', `/users/${userId}/orders${queryString ? '?' + queryString : ''}`);
        }

        // ==================== ADMIN PROFILES ====================

        // ==================== ADMIN PROFILES (DEPRECATED - USE EMBEDDED DATA) ====================

        async getAdminProfiles(params = {}) {
            // Deprecated: Use getAdminUsers() which includes embedded profile data
            console.warn('getAdminProfiles is deprecated. Use getAdminUsers() which includes embedded profile data.');
            const response = await this.getAdminUsers(params);
            return response.data || [];
        }

        async getAdminProfile(userId) {
            // Deprecated: Use getAdminUsers() and find the specific user
            console.warn('getAdminProfile is deprecated. Use getAdminUsers() which includes embedded profile data.');
            const response = await this.getAdminUsers();
            if (response && response.success && response.data) {
                const user = response.data.find(u => u.id == userId);
                return user ? user.admin_profile : null;
            }
            throw new Error('Admin profile not found');
        }

        async createAdminProfile(profileData) {
            // Deprecated: Admin profiles are now created automatically with users
            console.warn('createAdminProfile is deprecated. Admin profiles are created automatically with users.');
            throw new Error('Use createUser() instead - profiles are created automatically');
        }

        async updateAdminProfile(userId, profileData) {
            // For now, we'll continue to support this method until we create a unified update endpoint
            // TODO: Create unified update endpoint like we did for creation
            return this.request('PUT', `/admin_profiles/${userId}`, profileData);
        }

        async deleteAdminProfile(userId) {
            // Deprecated: Admin profiles are deleted automatically when users are deleted
            console.warn('deleteAdminProfile is deprecated. Admin profiles are deleted automatically with users.');
            throw new Error('Use deleteUser() instead - profiles are deleted automatically');
        }

        async getInternalRoles() {
            const cacheKey = 'internal_roles';
            
            if (this.isValidCache(cacheKey)) {
                return this.cache.get(cacheKey);
            }

            const response = await this.request('GET', '/admin/roles/internal');
            this.cache.set(cacheKey, response);
            return response;
        }

        async getAvailablePermissions() {
            const cacheKey = 'available_permissions';
            
            if (this.isValidCache(cacheKey)) {
                return this.cache.get(cacheKey);
            }

            const response = await this.request('GET', '/admin/permissions');
            this.cache.set(cacheKey, response);
            return response;
        }

        // ==================== NEW PERMISSION MANAGEMENT ====================

        /**
         * Get permission matrix for visualization
         */
        async getPermissionMatrix() {
            const cacheKey = 'permission_matrix';
            
            if (this.isValidCache(cacheKey)) {
                return this.cache.get(cacheKey);
            }

            const response = await this.request('GET', '/admin/permission-management/matrix');
            this.cache.set(cacheKey, response, 300); // 5 minute cache
            return response;
        }

        /**
         * Get all permissions grouped by category
         */
        async getPermissionsGrouped() {
            const cacheKey = 'permissions_grouped';
            
            if (this.isValidCache(cacheKey)) {
                return this.cache.get(cacheKey);
            }

            const response = await this.request('GET', '/admin/permission-management/permissions');
            this.cache.set(cacheKey, response);
            return response;
        }

        /**
         * Create a new permission
         */
        async createPermission(permissionData) {
            const response = await this.request('POST', '/admin/permission-management/permissions', permissionData);
            this.clearPermissionCache();
            return response;
        }

        /**
         * Get all roles with their permissions
         */
        async getRolesWithPermissions() {
            const cacheKey = 'roles_with_permissions';
            
            if (this.isValidCache(cacheKey)) {
                return this.cache.get(cacheKey);
            }

            const response = await this.request('GET', '/admin/permission-management/roles');
            this.cache.set(cacheKey, response);
            return response;
        }

        /**
         * Create a new role
         */
        async createRole(roleData) {
            const response = await this.request('POST', '/admin/permission-management/roles', roleData);
            this.clearPermissionCache();
            return response;
        }

        /**
         * Assign permissions to a user
         */
        async assignUserPermissions(userId, permissionData) {
            const response = await this.request('POST', `/admin/permission-management/users/${userId}/permissions`, permissionData);
            this.clearPermissionCache();
            this.clearUsersCache();
            return response;
        }

        /**
         * Get detailed user permissions
         */
        async getUserPermissions(userId) {
            const cacheKey = `user_permissions_${userId}`;
            
            if (this.isValidCache(cacheKey)) {
                return this.cache.get(cacheKey);
            }

            const response = await this.request('GET', `/admin/user/${userId}/permissions`);
            this.cache.set(cacheKey, response, 300); // 5 minute cache
            return response;
        }

        /**
         * Bulk update permissions for multiple users
         */
        async bulkUpdatePermissions(operations) {
            const response = await this.request('POST', '/admin/permission-management/bulk-update', {
                operations: operations
            });
            this.clearPermissionCache();
            this.clearUsersCache();
            return response;
        }

        /**
         * Get permission system health check
         */
        async getPermissionSystemHealth() {
            return this.request('GET', '/admin/permissions/health');
        }

        /**
         * Clear all permission-related caches
         */
        clearPermissionCache() {
            const permissionKeys = [
                'permission_matrix',
                'permissions_grouped',
                'roles_with_permissions',
                'available_permissions',
                'internal_roles'
            ];

            permissionKeys.forEach(key => {
                this.cache.delete(key);
            });

            // Clear user permission caches (pattern-based)
            this.cache.forEach((value, key) => {
                if (key.startsWith('user_permissions_')) {
                    this.cache.delete(key);
                }
            });

            console.log('Permission cache cleared');
        }

        /**
         * Clear user-related caches
         */
        clearUsersCache() {
            const userKeys = [
                'admin_users',
                'users'
            ];

            userKeys.forEach(key => {
                this.cache.delete(key);
            });

            // Clear user-specific caches
            this.cache.forEach((value, key) => {
                if (key.startsWith('user_') || key.startsWith('admin_profile_')) {
                    this.cache.delete(key);
                }
            });

            console.log('Users cache cleared');
        }

        // ==================== DISHES & MENUS ====================

        async getDishes(params = {}) {
            const cacheKey = `dishes_${JSON.stringify(params)}`;
            
            if (this.isValidCache(cacheKey)) {
                return this.cache.get(cacheKey);
            }

            const queryString = new URLSearchParams(params).toString();
            const response = await this.request('GET', `/plats${queryString ? '?' + queryString : ''}`);
            
            this.cache.set(cacheKey, response);
            setTimeout(() => this.cache.delete(cacheKey), this.cacheTimeout);
            
            return response;
        }

        async getDish(id) {
            return this.request('GET', `/plats/${id}`);
        }

        async createDish(dishData) {
            const response = await this.request('POST', '/plats', dishData);
            this.clearDishesCache();
            return response;
        }

        async updateDish(id, dishData) {
            const response = await this.request('PUT', `/plats/${id}`, dishData);
            this.clearDishesCache();
            return response;
        }

        async deleteDish(id) {
            const response = await this.request('DELETE', `/plats/${id}`);
            this.clearDishesCache();
            return response;
        }

        async getMenus(params = {}) {
            const queryString = new URLSearchParams(params).toString();
            return this.request('GET', `/menus${queryString ? '?' + queryString : ''}`);
        }

        async getMenu(id) {
            return this.request('GET', `/menus/${id}`);
        }

        async createMenu(menuData) {
            return this.request('POST', '/menus', menuData);
        }

        async updateMenu(id, menuData) {
            return this.request('PUT', `/menus/${id}`, menuData);
        }

        async deleteMenu(id) {
            return this.request('DELETE', `/menus/${id}`);
        }

        // ==================== ORDERS MANAGEMENT ====================

        async getOrders(params = {}) {
            const queryString = new URLSearchParams(params).toString();
            return this.request('GET', `/commandes${queryString ? '?' + queryString : ''}`);
        }

        async getOrder(id) {
            return this.request('GET', `/commandes/${id}`);
        }

        async updateOrderStatus(id, status, comment = null) {
            return this.request('PATCH', `/orders/${id}/status`, {
                status: status,
                comment: comment
            });
        }

        async getOrdersCount(status = null) {
            const cacheKey = `orders_count_${status || 'all'}`;
            
            if (this.isValidCache(cacheKey)) {
                return this.cache.get(cacheKey);
            }

            try {
                let endpoint = '/orders/count';
                if (status) {
                    // Handle both string status and object params
                    if (typeof status === 'string') {
                        endpoint += `?status=${status}`;
                    } else {
                        const queryString = new URLSearchParams(status).toString();
                        endpoint += queryString ? `?${queryString}` : '';
                    }
                }
                
                const response = await this.request('GET', endpoint);
                const count = response.count || 0;
                this.cache.set(cacheKey, count);
                return count;
            } catch (error) {
                console.log('Could not fetch orders count:', error);
                return 0;
            }
        }

        async getKitchenDashboard() {
            return this.request('GET', '/mobile/kitchen/mobile');
        }

        async updateOrderEstimate(id, estimate) {
            return this.request('PATCH', `/orders/${id}/estimate`, {
                estimated_delivery: estimate
            });
        }

        // ==================== ANALYTICS ====================

        async getAnalyticsDashboard() {
            return this.request('GET', '/analytics/dashboard');
        }

        async getDailyAnalytics(date = null) {
            const params = date ? { date } : {};
            const queryString = new URLSearchParams(params).toString();
            return this.request('GET', `/analytics/daily${queryString ? '?' + queryString : ''}`);
        }

        async getWeeklyAnalytics(startDate = null) {
            const params = startDate ? { startDate } : {};
            const queryString = new URLSearchParams(params).toString();
            return this.request('GET', `/analytics/weekly${queryString ? '?' + queryString : ''}`);
        }

        async getCustomerAnalytics() {
            return this.request('GET', '/analytics/customers');
        }

        async getFinancialAnalytics(startDate = null, endDate = null) {
            const params = {};
            if (startDate) params.startDate = startDate;
            if (endDate) params.endDate = endDate;
            const queryString = new URLSearchParams(params).toString();
            return this.request('GET', `/analytics/financial${queryString ? '?' + queryString : ''}`);
        }

        async getOperationalAnalytics() {
            return this.request('GET', '/analytics/operational');
        }

        async exportAnalytics(format = 'csv', params = {}) {
            const queryString = new URLSearchParams({ format, ...params }).toString();
            return this.request('GET', `/analytics/export${queryString ? '?' + queryString : ''}`);
        }

        async clearAnalyticsCache() {
            return this.request('POST', '/analytics/cache/clear');
        }

        // ==================== NOTIFICATIONS ====================

        async getNotifications(params = {}) {
            const queryString = new URLSearchParams(params).toString();
            return this.request('GET', `/notifications${queryString ? '?' + queryString : ''}`);
        }

        async getUnreadNotifications(limit = 10) {
            return this.request('GET', `/notifications?read=false&limit=${limit}`);
        }

        async getUnreadNotificationsCount() {
            const cacheKey = 'unread_notifications_count';
            
            if (this.isValidCache(cacheKey)) {
                return this.cache.get(cacheKey);
            }

            try {
                const response = await this.request('GET', '/notifications/unread/count');
                this.cache.set(cacheKey, response.count || 0);
                return response.count || 0;
            } catch (error) {
                console.log('Could not fetch unread notifications count:', error);
                return 0;
            }
        }

        async getNotificationsCount(status = 'unread') {
            const cacheKey = `notifications_count_${status}`;
            
            if (this.isValidCache(cacheKey)) {
                return this.cache.get(cacheKey);
            }

            try {
                const response = await this.request('GET', `/notifications/count?status=${status}`);
                this.cache.set(cacheKey, response.count || 0);
                return response.count || 0;
            } catch (error) {
                console.log('Could not fetch notifications count:', error);
                return 0;
            }
        }

        async markNotificationAsRead(id) {
            const response = await this.request('PATCH', `/notifications/${id}/read`);
            
            // Clear notifications cache
            this.clearNotificationsCache();
            
            return response;
        }

        async markAllNotificationsAsRead() {
            const response = await this.request('PATCH', '/notifications/read-all');
            
            // Clear notifications cache
            this.clearNotificationsCache();
            
            return response;
        }
        
        // Clear notifications cache
        clearNotificationsCache() {
            const keys = Array.from(this.cache.keys()).filter(key => 
                key.startsWith('notifications_') || key.startsWith('unread_notifications_')
            );
            keys.forEach(key => this.cache.delete(key));
        }

        // ==================== SUBSCRIPTIONS ====================

        async getSubscriptions(params = {}) {
            const queryString = new URLSearchParams(params).toString();
            return this.request('GET', `/abonnements${queryString ? '?' + queryString : ''}`);
        }

        async getSubscription(id) {
            return this.request('GET', `/abonnements/${id}`);
        }

        async createSubscription(subscriptionData) {
            return this.request('POST', '/abonnements', subscriptionData);
        }

        async updateSubscription(id, subscriptionData) {
            return this.request('PUT', `/abonnements/${id}`, subscriptionData);
        }

        async deleteSubscription(id) {
            return this.request('DELETE', `/abonnements/${id}`);
        }

        // ==================== MOBILE API ENDPOINTS ====================

        async getMobileDashboard() {
            return this.request('GET', '/mobile/dashboard');
        }

        async getMobileSync() {
            return this.request('GET', '/mobile/sync');
        }

        async getHealthCheck() {
            return this.request('GET', '/mobile/health');
        }

        // ==================== CACHE MANAGEMENT ====================

        isValidCache(key) {
            return this.cache.has(key);
        }

        clearCache() {
            this.cache.clear();
        }

        clearDishesCache() {
            for (const key of this.cache.keys()) {
                if (key.startsWith('dishes_')) {
                    this.cache.delete(key);
                }
            }
        }

        // ==================== UTILITY METHODS ====================

        async uploadFile(file, endpoint = '/upload') {
            const formData = new FormData();
            formData.append('file', file);
            
            return this.request('POST', endpoint, formData);
        }

        async search(query, type = 'all') {
            const params = { q: query, type };
            const queryString = new URLSearchParams(params).toString();
            return this.request('GET', `/search?${queryString}`);
        }

        // File download helper
        async downloadFile(endpoint, filename = null) {
            const response = await fetch(`${this.baseURL}${endpoint}`, {
                headers: this.defaultHeaders
            });

            if (!response.ok) {
                throw new Error(`Download failed: ${response.statusText}`);
            }

            const blob = await response.blob();
            const url = window.URL.createObjectURL(blob);
            const a = document.createElement('a');
            a.href = url;
            a.download = filename || 'download';
            document.body.appendChild(a);
            a.click();
            window.URL.revokeObjectURL(url);
            document.body.removeChild(a);
        }

        // ==================== SINGLETON ====================

        static getInstance() {
            if (!AdminAPI.instance) {
                AdminAPI.instance = new AdminAPI();
            }
            return AdminAPI.instance;
        }

        /**
         * Check detailed permissions for a specific user
         */
        async checkUserPermissions(targetUserId) {
            return this.request('GET', `/admin/check-permissions/${targetUserId}`);
        }
    }

    // Create global instance
    global.AdminAPI = AdminAPI.getInstance();

})(window); 