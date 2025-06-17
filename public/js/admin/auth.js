/**
 * AdminAuth - Authentication management for JoodKitchen Admin
 * Handles JWT tokens, user sessions, login/logout, and token refresh
 */
(function(global) {
    'use strict';
    
    // Prevent redeclaration
    if (global.AdminAuth) {
        return;
    }

    class AdminAuth {
        constructor() {
            this.tokenKey = 'admin_token';
            this.userKey = 'admin_user';
            this.refreshInterval = null;
            this.currentUser = null;
            
            // Initialize authentication state
            this.init();
        }

        init() {
            // Load user from localStorage
            this.loadUserFromStorage();
            
            // Set up token refresh
            this.setupTokenRefresh();
            
            // Set up storage event listener for multi-tab support
            this.setupStorageListener();
        }

        loadUserFromStorage() {
            const token = localStorage.getItem(this.tokenKey);
            const userData = localStorage.getItem(this.userKey);
            
            console.log('🔍 AdminAuth.loadUserFromStorage():', {
                hasToken: !!token,
                hasUserData: !!userData,
                token: token ? token.substring(0, 20) + '...' : null
            });
            
            if (token && userData) {
                try {
                    this.currentUser = JSON.parse(userData);
                    console.log('✅ AdminAuth loaded user from storage:', this.currentUser);
                    
                    // Verify token hasn't expired
                    if (this.isTokenExpired(token)) {
                        console.log('⏰ Token expired during loading, logging out');
                        this.logout();
                        return;
                    }
                    
                    // Set cookie for Symfony authentication
                    this.setTokenCookie(token);
                    
                    // Update API token
                    if (window.AdminAPI) {
                        AdminAPI.updateToken(token);
                        console.log('✅ Updated API token');
                    }
                } catch (error) {
                    console.error('❌ Error loading user from storage:', error);
                    this.logout();
                }
            } else {
                console.log('ℹ️ No auth data in storage');
            }
        }

        setupTokenRefresh() {
            // Refresh token every 20 minutes (tokens expire in 24 hours)
            this.refreshInterval = setInterval(() => {
                if (this.isAuthenticated()) {
                    this.refreshToken();
                }
            }, 20 * 60 * 1000); // 20 minutes
        }

        setupStorageListener() {
            // Listen for changes in other tabs
            window.addEventListener('storage', (e) => {
                if (e.key === this.tokenKey) {
                    if (!e.newValue) {
                        // Token was removed in another tab
                        this.handleLogout();
                    } else {
                        // Token was updated in another tab
                        this.loadUserFromStorage();
                    }
                }
            });
        }

        // ==================== AUTHENTICATION METHODS ====================

        async login(email, password) {
            try {
                const credentials = { email, password };
                const response = await AdminAPI.login(credentials);
                
                if (response.token && response.user) {
                    this.setUserSession(response.token, response.user);
                    AdminUtils.showAlert('Connexion réussie !', 'success');
                    return response;
                } else {
                    throw new Error('Réponse de connexion invalide');
                }
            } catch (error) {
                console.error('Login error:', error);
                AdminUtils.showAlert(error.message || 'Erreur de connexion', 'error');
                throw error;
            }
        }

        async logout() {
            try {
                // Call logout API
                await AdminAPI.logout();
            } catch (error) {
                console.error('Logout API error:', error);
            } finally {
                this.handleLogout();
            }
        }

        handleLogout() {
            // Clear session data
            this.clearUserSession();
            
            // Clear refresh interval
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
                this.refreshInterval = null;
            }
            
            // Redirect to login page
            if (!window.location.pathname.includes('/admin/login')) {
                window.location.href = '/admin/login';
            }
        }

        async refreshToken() {
            try {
                const response = await AdminAPI.refreshToken();
                if (response.token) {
                    // Update token while keeping user data
                    const token = localStorage.getItem(this.tokenKey);
                    if (token) {
                        localStorage.setItem(this.tokenKey, response.token);
                        AdminAPI.updateToken(response.token);
                    }
                }
            } catch (error) {
                console.error('Token refresh failed:', error);
                this.logout();
            }
        }

        // ==================== SESSION MANAGEMENT ====================

        setUserSession(token, user) {
            // Store in localStorage
            localStorage.setItem(this.tokenKey, token);
            localStorage.setItem(this.userKey, JSON.stringify(user));
            
            // Store in cookie for Symfony authentication
            this.setTokenCookie(token);
            
            // Update current user
            this.currentUser = user;
            
            // Update API token
            AdminAPI.updateToken(token);
            
            // Dispatch custom event for other components
            this.dispatchAuthEvent('login', user);
        }

        clearUserSession() {
            // Clear localStorage
            localStorage.removeItem(this.tokenKey);
            localStorage.removeItem(this.userKey);
            
            // Clear cookie
            this.clearTokenCookie();
            
            // Clear current user
            this.currentUser = null;
            
            // Clear API token
            AdminAPI.updateToken(null);
            
            // Dispatch logout event
            this.dispatchAuthEvent('logout');
        }

        dispatchAuthEvent(type, data = null) {
            const event = new CustomEvent(`admin-auth-${type}`, {
                detail: data
            });
            window.dispatchEvent(event);
        }

        // ==================== TOKEN UTILITIES ====================

        isTokenExpired(token) {
            try {
                // Check if it's a JWT token (format: header.payload.signature)
                if (token.includes('.')) {
                    const parts = token.split('.');
                    if (parts.length !== 3) {
                        return true;
                    }
                    
                    try {
                        // Decode JWT payload (second part)
                        const payload = JSON.parse(atob(parts[1]));
                        
                        // Check expiration (exp is in seconds, Date.now() is in milliseconds)
                        if (payload.exp) {
                            const expirationTime = payload.exp * 1000; // Convert to milliseconds
                            return Date.now() > expirationTime;
                        } else {
                            return false; // Assume valid if no expiration
                        }
                    } catch (error) {
                        console.error('Error decoding JWT payload:', error);
                        return true;
                    }
                } else {
                    // Legacy API token format: user_id:email:timestamp:hash
                    const parts = token.split(':');
                    if (parts.length !== 4) {
                        return true;
                    }
                    
                    const timestamp = parseInt(parts[2]);
                    if (isNaN(timestamp)) {
                        return true;
                    }
                    
                    const expirationTime = timestamp + (24 * 60 * 60 * 1000); // 24 hours
                    
                    return Date.now() > expirationTime;
                }
            } catch (error) {
                console.error('Token validation error:', error);
                return true;
            }
        }

        getTokenExpiration() {
            const token = localStorage.getItem(this.tokenKey);
            if (!token) return null;
            
            try {
                // Check if it's a JWT token
                if (token.includes('.')) {
                    const parts = token.split('.');
                    if (parts.length !== 3) return null;
                    
                    const payload = JSON.parse(atob(parts[1]));
                    return payload.exp ? new Date(payload.exp * 1000) : null;
                } else {
                    // Legacy token format
                    const parts = token.split(':');
                    if (parts.length !== 4) return null;
                    
                    const timestamp = parseInt(parts[2]);
                    if (isNaN(timestamp)) return null;
                    
                    return new Date(timestamp + (24 * 60 * 60 * 1000));
                }
            } catch (error) {
                console.error('Error getting token expiration:', error);
                return null;
            }
        }

        getTimeUntilExpiration() {
            const expiration = this.getTokenExpiration();
            if (!expiration) return 0;
            
            return Math.max(0, expiration.getTime() - Date.now());
        }

        // ==================== COOKIE MANAGEMENT ====================

        setTokenCookie(token) {
            // Set HTTP-only cookie for Symfony authentication
            // Use secure cookie if HTTPS is available
            const isSecure = window.location.protocol === 'https:';
            const expires = new Date();
            expires.setTime(expires.getTime() + (24 * 60 * 60 * 1000)); // 24 hours
            
            document.cookie = `admin_token=${token}; expires=${expires.toUTCString()}; path=/; ${isSecure ? 'secure; ' : ''}samesite=lax`;
            
            console.log('🍪 Set admin_token cookie for Symfony authentication');
        }

        clearTokenCookie() {
            // Clear the cookie by setting it to expire in the past
            document.cookie = 'admin_token=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
            console.log('🍪 Cleared admin_token cookie');
        }

        // ==================== USER INFORMATION ====================

        isAuthenticated() {
            const token = localStorage.getItem(this.tokenKey);
            const isExpired = token ? this.isTokenExpired(token) : true;
            const hasUser = !!this.currentUser;
            
            console.log('🔍 AdminAuth.isAuthenticated() check:', {
                hasToken: !!token,
                isExpired: isExpired,
                hasCurrentUser: hasUser,
                token: token ? token.substring(0, 20) + '...' : null,
                currentUser: this.currentUser
            });
            
            const result = token && !isExpired && hasUser;
            console.log('🔍 AdminAuth.isAuthenticated() result:', result);
            
            return result;
        }

        getToken() {
            return localStorage.getItem(this.tokenKey);
        }

        getCurrentUser() {
            return this.currentUser;
        }

        getUserRole() {
            if (!this.currentUser || !this.currentUser.roles) {
                return null;
            }
            
            // Return highest role
            const roleHierarchy = [
                'ROLE_SUPER_ADMIN',
                'ROLE_ADMIN', 
                'ROLE_KITCHEN',
                'ROLE_CLIENT'
            ];
            
            for (const role of roleHierarchy) {
                if (this.currentUser.roles.includes(role)) {
                    return role;
                }
            }
            
            return 'ROLE_CLIENT';
        }

        hasRole(role) {
            if (!this.currentUser || !this.currentUser.roles) {
                return false;
            }
            return this.currentUser.roles.includes(role);
        }

        hasAnyRole(roles) {
            if (!this.currentUser || !this.currentUser.roles) {
                return false;
            }
            return roles.some(role => this.currentUser.roles.includes(role));
        }

        canAccess(requiredRoles) {
            if (!requiredRoles || requiredRoles.length === 0) {
                return this.isAuthenticated();
            }
            
            return this.hasAnyRole(requiredRoles);
        }

        // ==================== PROFILE MANAGEMENT ====================

        async updateProfile(profileData) {
            try {
                const response = await AdminAPI.updateUser(this.currentUser.id, profileData);
                
                if (response.user) {
                    // Update stored user data
                    this.currentUser = { ...this.currentUser, ...response.user };
                    localStorage.setItem(this.userKey, JSON.stringify(this.currentUser));
                    
                    // Dispatch update event
                    this.dispatchAuthEvent('profile-updated', this.currentUser);
                    
                    AdminUtils.showAlert('Profil mis à jour avec succès', 'success');
                    return response;
                }
            } catch (error) {
                console.error('Profile update error:', error);
                AdminUtils.showAlert(error.message || 'Erreur lors de la mise à jour du profil', 'error');
                throw error;
            }
        }

        async changePassword(currentPassword, newPassword) {
            try {
                const response = await AdminAPI.changePassword({
                    current_password: currentPassword,
                    new_password: newPassword
                });
                
                AdminUtils.showAlert('Mot de passe modifié avec succès', 'success');
                return response;
            } catch (error) {
                console.error('Password change error:', error);
                AdminUtils.showAlert(error.message || 'Erreur lors du changement de mot de passe', 'error');
                throw error;
            }
        }

        // ==================== ROUTE PROTECTION ====================

        requireAuth() {
            if (!this.isAuthenticated()) {
                this.logout();
                return false;
            }
            return true;
        }

        requireRole(roles) {
            if (!this.requireAuth()) {
                return false;
            }
            
            if (!this.canAccess(roles)) {
                AdminUtils.showAlert('Accès non autorisé', 'error');
                return false;
            }
            
            return true;
        }

        // ==================== UTILITY METHODS ====================

        getRoleDisplayName(role) {
            const roleNames = {
                'ROLE_SUPER_ADMIN': 'Super Administrateur',
                'ROLE_ADMIN': 'Administrateur',
                'ROLE_KITCHEN': 'Personnel Cuisine',
                'ROLE_CLIENT': 'Client'
            };
            
            return roleNames[role] || role;
        }

        getUserDisplayName() {
            if (!this.currentUser) return 'Utilisateur';
            
            const prenom = this.currentUser.prenom || '';
            const nom = this.currentUser.nom || '';
            
            return `${prenom} ${nom}`.trim() || this.currentUser.email || 'Utilisateur';
        }

        getAvatar() {
            if (!this.currentUser) return '/admin/img/avatars/default.svg';
            
            return this.currentUser.photoProfil || '/admin/img/avatars/default.svg';
        }

        // ==================== DEVELOPMENT HELPERS ====================

        getDebugInfo() {
            return {
                isAuthenticated: this.isAuthenticated(),
                user: this.currentUser,
                token: localStorage.getItem(this.tokenKey),
                expiration: this.getTokenExpiration(),
                timeUntilExpiration: this.getTimeUntilExpiration(),
                role: this.getUserRole()
            };
        }

        // Cleanup method
        destroy() {
            if (this.refreshInterval) {
                clearInterval(this.refreshInterval);
            }
            
            window.removeEventListener('storage', this.setupStorageListener);
        }

        // ==================== SINGLETON ====================

        static getInstance() {
            if (!AdminAuth.instance) {
                AdminAuth.instance = new AdminAuth();
            }
            return AdminAuth.instance;
        }
    }

    // Create global instance
    global.AdminAuth = AdminAuth.getInstance();

    // Listen for authentication events
    window.addEventListener('admin-auth-login', (e) => {
        console.log('User logged in:', e.detail);
    });

    window.addEventListener('admin-auth-logout', () => {
        console.log('User logged out');
    });

    window.addEventListener('admin-auth-profile-updated', (e) => {
        console.log('Profile updated:', e.detail);
    });

})(window); 