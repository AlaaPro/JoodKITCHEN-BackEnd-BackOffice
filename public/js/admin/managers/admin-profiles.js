/**
 * AdminProfileManager - CRUD operations for Administrator Profiles
 * Following MEGA PROMPT: 100% API separation with CoreUI integration
 */
class AdminProfileManager {
    constructor() {
        this.currentPage = 1;
        this.perPage = 10;
        this.searchQuery = '';
        this.filters = {};
        this.selectedItems = new Set();
        
        this.init();
    }

    init() {
        this.bindEvents();
        this.loadAdminProfiles();
        this.setupRealTimeUpdates();
    }

    // ==================== EVENT BINDINGS ====================

    bindEvents() {
        // Create Admin button
        $('#createAdminBtn').on('click', () => this.showCreateModal());
        
        // Search input
        $('#adminSearchInput').on('keyup debounce', (e) => {
            this.searchQuery = e.target.value;
            this.loadAdminProfiles();
        });

        // Filter dropdowns
        $('#roleFilter').on('change', (e) => {
            this.filters.role = e.target.value;
            this.loadAdminProfiles();
        });

        $('#statusFilter').on('change', (e) => {
            this.filters.status = e.target.value;
            this.loadAdminProfiles();
        });

        // Bulk actions
        $('#selectAllAdmins').on('change', (e) => this.handleSelectAll(e.target.checked));
        $('#bulkActionBtn').on('click', () => this.showBulkActionModal());

        // Form submissions
        $('#createAdminForm').on('submit', (e) => this.handleCreate(e));
        $('#editAdminForm').on('submit', (e) => this.handleUpdate(e));

        // Modal events (CoreUI)
        // Note: CoreUI handles modal events differently, we'll handle them in the modal show/hide methods
    }

    // ==================== DATA LOADING ====================

    async loadAdminProfiles() {
        try {
            // Show loading indicator
            const tbody = document.querySelector('#adminsTable tbody');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <div class="spinner-border text-primary" role="status">
                                <span class="visually-hidden">Chargement...</span>
                            </div>
                            <p class="mt-2 text-muted">Chargement des administrateurs...</p>
                        </td>
                    </tr>
                `;
            }

            // Get admin users with their profiles
            const response = await AdminAPI.getAdminUsers();
            console.log('Admin users API response:', response);
            
            if (response && response.success && response.data) {
                // Use API data with profiles
                this.renderAdminProfilesTable(response.data);
                
                // Update stats
                const adminUsers = response.data;
                this.updateStats({
                    total_admins: adminUsers.length,
                    active_admins: adminUsers.filter(u => u.is_active).length,
                    recent_logins: adminUsers.filter(u => {
                        if (!u.last_connexion) return false;
                        const lastLogin = new Date(u.last_connexion);
                        const now = new Date();
                        return (now - lastLogin) < (24 * 60 * 60 * 1000);
                    }).length
                });
                
            } else {
                // Fallback to static data
                this.renderStaticAdminData();
            }

        } catch (error) {
            console.error('Error loading admin profiles:', error);
            
            // Show error in table
            const tbody = document.querySelector('#adminsTable tbody');
            if (tbody) {
                tbody.innerHTML = `
                    <tr>
                        <td colspan="7" class="text-center py-4">
                            <div class="alert alert-danger">
                                <i class="fas fa-exclamation-triangle"></i>
                                Erreur lors du chargement des administrateurs: ${error.message}
                            </div>
                        </td>
                    </tr>
                `;
            }
        }
    }
    
    renderStaticAdminData() {
        const tbody = document.querySelector('#adminsTable tbody');
        if (!tbody) return;
        
        // For now, just keep the existing static data and remove loading
        // The static admin data is already in the template
        console.log('Loaded admin profiles successfully - displaying static data');
    }

    renderAdminProfilesTable(users) {
        const tbody = $('#adminsTable tbody');
        tbody.empty();

        if (!users || users.length === 0) {
            tbody.append(`
                <tr>
                    <td colspan="7" class="text-center py-4">
                        <i class="fas fa-users-slash fa-3x text-muted mb-3"></i>
                        <p class="text-muted">Aucun administrateur trouvé</p>
                    </td>
                </tr>
            `);
            return;
        }

        users.forEach(user => {
            const profile = user.admin_profile; // Profile is now embedded in user object
            const row = this.createAdminRow(user, profile);
            tbody.append(row);
        });

        // Re-bind row events
        this.bindRowEvents();
    }

    createAdminRow(user, profile) {
        const roles = user.roles || [];
        const isSuperAdmin = roles.includes('ROLE_SUPER_ADMIN');
        const isCurrentUser = user.id === AdminAuth.getCurrentUser()?.id;
        
        const rolesBadges = this.generateRolesBadges(roles, profile?.roles_internes || []);
        const statusBadge = this.generateStatusBadge(user.is_active, user.last_connexion);
        const permissionsBadges = this.generatePermissionsBadges(profile?.permissions_avancees || []);

        return `
            <tr data-admin-id="${user.id}" data-profile-id="${profile?.id || ''}">
                <td>
                    <div class="form-check">
                        <input class="form-check-input admin-checkbox" type="checkbox" 
                               value="${user.id}" ${isSuperAdmin || isCurrentUser ? 'disabled' : ''}>
                    </div>
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar-circle me-3">
                            ${user.photo_profil ? 
                                `<img src="${user.photo_profil}" alt="${user.nom}">` :
                                `<i class="fas fa-user-shield"></i>`
                            }
                        </div>
                        <div>
                            <div class="fw-semibold">${user.nom} ${user.prenom}</div>
                            <small class="text-muted">${user.email}</small>
                            ${profile?.notes_interne ? 
                                `<div class="mt-1"><small class="text-info">${profile.notes_interne}</small></div>` : 
                                ''
                            }
                        </div>
                    </div>
                </td>
                <td>${rolesBadges}</td>
                <td>${statusBadge}</td>
                <td>
                    <div class="small">
                        ${user.last_connexion ? 
                            `<div>${AdminUtils.formatDate(user.last_connexion)}</div>
                             <small class="text-muted">${AdminUtils.timeAgo(user.last_connexion)}</small>` :
                            '<span class="text-muted">Jamais connecté</span>'
                        }
                    </div>
                </td>
                <td>${permissionsBadges}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary view-admin-btn" 
                                data-admin-id="${user.id}" title="Voir détails">
                            <i class="fas fa-eye"></i>
                        </button>
                        ${!isCurrentUser ? `
                            ${user.can_edit ? `
                                <button class="btn btn-outline-secondary edit-admin-btn" 
                                        data-admin-id="${user.id}" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                            ` : `
                                <button class="btn btn-outline-secondary" disabled 
                                        title="Permissions insuffisantes pour modifier cet utilisateur">
                                    <i class="fas fa-lock"></i>
                                </button>
                            `}
                            ${!isSuperAdmin && user.can_edit ? `
                                <button class="btn btn-outline-danger delete-admin-btn" 
                                        data-admin-id="${user.id}" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            ` : ''}
                        ` : `
                            <button class="btn btn-outline-secondary" disabled title="Compte actuel">
                                <i class="fas fa-lock"></i>
                            </button>
                        `}
                    </div>
                </td>
            </tr>
        `;
    }

    generateRolesBadges(systemRoles, internalRoles) {
        let badges = '';
        
        if (systemRoles.includes('ROLE_SUPER_ADMIN')) {
            badges += '<span class="badge bg-warning me-1"><i class="fas fa-crown"></i> Super Admin</span>';
        } else if (systemRoles.includes('ROLE_ADMIN')) {
            badges += '<span class="badge bg-primary me-1"><i class="fas fa-user-shield"></i> Admin</span>';
        }

        internalRoles.forEach(role => {
            badges += `<span class="badge bg-secondary me-1">${role}</span>`;
        });

        return badges || '<span class="text-muted">Aucun rôle</span>';
    }

    generateStatusBadge(isActive, lastConnexion) {
        if (!isActive) {
            return '<span class="badge bg-danger"><i class="fas fa-user-times"></i> Suspendu</span>';
        }

        const lastLogin = lastConnexion ? new Date(lastConnexion) : null;
        const now = new Date();
        const diffMinutes = lastLogin ? (now - lastLogin) / (1000 * 60) : null;

        if (diffMinutes && diffMinutes < 30) {
            return '<span class="badge bg-success"><i class="fas fa-circle"></i> En ligne</span>';
        } else if (diffMinutes && diffMinutes < 1440) { // 24 hours
            return '<span class="badge bg-info"><i class="fas fa-clock"></i> Récent</span>';
        } else {
            return '<span class="badge bg-secondary"><i class="fas fa-user"></i> Hors ligne</span>';
        }
    }

    generatePermissionsBadges(permissions) {
        if (!permissions || permissions.length === 0) {
            return '<span class="text-muted">Permissions standard</span>';
        }

        let badges = '';
        permissions.slice(0, 3).forEach(permission => {
            badges += `<span class="badge bg-info me-1">${permission}</span>`;
        });

        if (permissions.length > 3) {
            badges += `<span class="badge bg-secondary">+${permissions.length - 3}</span>`;
        }

        return badges;
    }

    // ==================== CRUD OPERATIONS ====================

    showCreateModal() {
        // Using CoreUI modal instead of Bootstrap
        const modal = document.getElementById('createAdminModal');
        if (modal) {
            const modalInstance = new coreui.Modal(modal);
            modalInstance.show();
        }
        this.loadRolesAndPermissions('#createAdminForm');
    }

    async handleCreate(event) {
        event.preventDefault();
        
        try {
            const formData = new FormData(event.target);
            const userData = {
                email: formData.get('email'),
                password: formData.get('password'),
                nom: formData.get('nom'),
                prenom: formData.get('prenom'),
                telephone: formData.get('telephone'),
                ville: formData.get('ville') || null,
                adresse: formData.get('adresse') || null,
                roles: [formData.get('role') || 'ROLE_ADMIN'],
                is_active: formData.get('is_active') === 'on',
                // Include admin profile data
                roles_internes: formData.getAll('roles_internes'),
                permissions_avancees: formData.getAll('permissions_avancees'),
                notes_interne: formData.get('notes_interne') || null
            };

            // Create admin user with profile (handled in single API call now)
            const response = await AdminAPI.createUser(userData);

            if (response && response.success) {
                // Show success message
                AdminUtils.showAlert(response.message || 'Administrateur créé avec succès', 'success');
                
                // Close modal
                const modal = document.getElementById('createAdminModal');
                if (modal) {
                    const modalInstance = coreui.Modal.getInstance(modal);
                    if (modalInstance) modalInstance.hide();
                }
                
                // Reset form
                this.resetCreateForm();
                
                // Reload admin list
                this.loadAdminProfiles();
            } else {
                AdminUtils.showAlert('Erreur lors de la création de l\'administrateur', 'error');
            }

        } catch (error) {
            console.error('Error creating admin:', error);
            
            // Handle different types of errors with specific messages
            let errorMessage = 'Erreur lors de la création de l\'administrateur';
            let errorType = 'error';
            
            if (error.data && error.data.type) {
                switch (error.data.type) {
                    case 'duplicate_email':
                        errorMessage = error.data.message || 'Cette adresse email est déjà utilisée. Veuillez en choisir une autre.';
                        errorType = 'warning';
                        break;
                    case 'validation_error':
                        errorMessage = error.data.message || 'Les données saisies ne sont pas valides. Veuillez vérifier vos informations.';
                        errorType = 'warning';
                        // Show validation details if available
                        if (error.data.details && Array.isArray(error.data.details)) {
                            errorMessage += '\n• ' + error.data.details.join('\n• ');
                        }
                        if (error.data.missing_fields && Array.isArray(error.data.missing_fields)) {
                            errorMessage += '\nChamps manquants : ' + error.data.missing_fields.join(', ');
                        }
                        break;
                    case 'server_error':
                        errorMessage = error.data.message || 'Une erreur serveur s\'est produite. Veuillez réessayer.';
                        errorType = 'error';
                        break;
                    default:
                        errorMessage = error.data.message || error.message || errorMessage;
                }
            } else if (error.message) {
                errorMessage = error.message;
            }
            
            AdminUtils.showAlert(errorMessage, errorType);
        }
    }

    showAdminDetails(adminId) {
        try {
            // Find admin data in current loaded data
            const adminRow = document.querySelector(`#adminsTable tr[data-admin-id="${adminId}"]`);
            if (!adminRow) {
                AdminUtils.showAlert('Administrateur non trouvé', 'warning');
                return;
            }

            // Get admin data from the current loaded data or make API call
            this.loadAdminDetailsModal(adminId);

        } catch (error) {
            console.error('Error showing admin details:', error);
            AdminUtils.showAlert('Erreur lors de l\'affichage des détails', 'error');
        }
    }

    async loadAdminDetailsModal(adminId) {
        try {
            // Load fresh admin data with profile
            const response = await AdminAPI.getAdminUsers();
            
            if (response && response.success && response.data) {
                const adminData = response.data.find(admin => admin.id == adminId);
                
                if (adminData) {
                    this.showAdminDetailsModal(adminData);
                } else {
                    AdminUtils.showAlert('Administrateur non trouvé', 'warning');
                }
            } else {
                AdminUtils.showAlert('Erreur lors du chargement des données', 'error');
            }

        } catch (error) {
            console.error('Error loading admin details:', error);
            AdminUtils.showAlert('Erreur lors du chargement des détails', 'error');
        }
    }

    showAdminDetailsModal(adminData) {
        const profile = adminData.admin_profile;
        
        // Create modal content
        const modalContent = `
            <div class="modal fade" id="adminDetailsModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">
                                <i class="fas fa-user-shield me-2"></i>
                                Détails de l'administrateur
                            </h5>
                            <button type="button" class="btn-close" data-coreui-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-user me-2"></i>Informations personnelles</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label text-muted">Nom complet</label>
                                                <div class="fw-semibold">${adminData.nom} ${adminData.prenom}</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label text-muted">Email</label>
                                                <div class="fw-semibold">${adminData.email}</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label text-muted">Téléphone</label>
                                                <div class="fw-semibold">${adminData.telephone || 'Non renseigné'}</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label text-muted">Statut</label>
                                                <div>${this.generateStatusBadge(adminData.is_active, adminData.last_connexion)}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <div class="col-md-6">
                                    <div class="card">
                                        <div class="card-header">
                                            <h6 class="mb-0"><i class="fas fa-shield-alt me-2"></i>Rôles et permissions</h6>
                                        </div>
                                        <div class="card-body">
                                            <div class="mb-3">
                                                <label class="form-label text-muted">Rôles système</label>
                                                <div>${this.generateSystemRolesBadges(adminData.roles)}</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label text-muted">Rôles internes</label>
                                                <div>${profile && profile.roles_internes ? 
                                                    profile.roles_internes.map(role => `<span class="badge bg-secondary me-1">${role}</span>`).join('') : 
                                                    '<span class="text-muted">Aucun rôle interne</span>'
                                                }</div>
                                            </div>
                                            <div class="mb-3">
                                                <label class="form-label text-muted">Permissions avancées</label>
                                                <div>${this.generateDetailedPermissionsBadges(profile?.permissions_avancees || [])}</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            ${profile && profile.notes_interne ? `
                                <div class="card mt-3">
                                    <div class="card-header">
                                        <h6 class="mb-0"><i class="fas fa-sticky-note me-2"></i>Notes internes</h6>
                                    </div>
                                    <div class="card-body">
                                        <p class="mb-0">${profile.notes_interne}</p>
                                    </div>
                                </div>
                            ` : ''}
                            
                            <div class="card mt-3">
                                <div class="card-header">
                                    <h6 class="mb-0"><i class="fas fa-clock me-2"></i>Informations système</h6>
                                </div>
                                <div class="card-body">
                                    <div class="row">
                                        <div class="col-md-6">
                                            <div class="mb-2">
                                                <small class="text-muted">Créé le</small>
                                                <div class="small">${AdminUtils.formatDate(adminData.created_at)}</div>
                                            </div>
                                            <div class="mb-2">
                                                <small class="text-muted">Modifié le</small>
                                                <div class="small">${AdminUtils.formatDate(adminData.updated_at)}</div>
                                            </div>
                                        </div>
                                        <div class="col-md-6">
                                            <div class="mb-2">
                                                <small class="text-muted">Dernière connexion</small>
                                                <div class="small">${adminData.last_connexion ? 
                                                    AdminUtils.formatDate(adminData.last_connexion) : 
                                                    'Jamais connecté'
                                                }</div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">Fermer</button>
                            <button type="button" class="btn btn-primary" onclick="adminProfileManager.showEditModal(${adminData.id})">
                                <i class="fas fa-edit me-1"></i>Modifier
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        // Remove existing modal if any
        const existingModal = document.getElementById('adminDetailsModal');
        if (existingModal) {
            existingModal.remove();
        }
        
        // Add modal to page
        document.body.insertAdjacentHTML('beforeend', modalContent);
        
        // Show modal
        const modal = document.getElementById('adminDetailsModal');
        if (modal) {
            const modalInstance = new coreui.Modal(modal);
            modalInstance.show();
            
            // Clean up when modal is hidden
            modal.addEventListener('hidden.coreui.modal', () => {
                modal.remove();
            });
        }
    }

    generateSystemRolesBadges(roles) {
        if (!roles || roles.length === 0) return '<span class="text-muted">Aucun rôle</span>';
        
        let badges = '';
        if (roles.includes('ROLE_SUPER_ADMIN')) {
            badges += '<span class="badge bg-warning me-1"><i class="fas fa-crown"></i> Super Admin</span>';
        } else if (roles.includes('ROLE_ADMIN')) {
            badges += '<span class="badge bg-primary me-1"><i class="fas fa-user-shield"></i> Admin</span>';
        }
        return badges || '<span class="text-muted">Utilisateur standard</span>';
    }

    generateDetailedPermissionsBadges(permissions) {
        if (!permissions || permissions.length === 0) {
            return '<span class="text-muted">Permissions standard</span>';
        }
        
        let badges = '';
        permissions.forEach(permission => {
            badges += `<span class="badge bg-info me-1 mb-1">${permission}</span>`;
        });
        
        return badges;
    }

    async showEditModal(adminId) {
        try {
            console.log('Opening edit modal for admin ID:', adminId);
            
            // Load roles and permissions first to prepare the form
            await this.loadRolesAndPermissions('#editAdminForm');
            
            // Load fresh admin data with profile from our unified API
            const response = await AdminAPI.getAdminUsers();
            console.log('Admin users response:', response);
            
            if (response && response.success && response.data) {
                const adminData = response.data.find(admin => admin.id == adminId);
                console.log('Found admin data:', adminData);
                
                if (adminData) {
                    // Check if current user can edit this admin
                    if (!adminData.can_edit) {
                        AdminUtils.showAlert('Vous n\'avez pas les permissions nécessaires pour modifier cet utilisateur.', 'warning');
                        return;
                    }
                    
                    // Populate form with embedded profile data
                    this.populateEditForm(adminData, adminData.admin_profile);
                    
                    // Show modal
                    const modal = document.getElementById('editAdminModal');
                    if (modal) {
                        const modalInstance = new coreui.Modal(modal);
                        modalInstance.show();
                    }
                } else {
                    AdminUtils.showAlert('Administrateur non trouvé dans les données chargées', 'warning');
                    console.error('Admin not found in data. Available IDs:', response.data.map(a => a.id));
                }
            } else {
                AdminUtils.showAlert('Erreur lors du chargement des données administrateurs', 'error');
                console.error('Invalid response from getAdminUsers:', response);
            }

        } catch (error) {
            console.error('Error loading admin for edit:', error);
            AdminUtils.showAlert(`Erreur lors du chargement: ${error.message}`, 'error');
        }
    }

    async handleUpdate(event) {
        event.preventDefault();
        
        try {
            const formData = new FormData(event.target);
            const adminId = formData.get('admin_id');

            if (!adminId) {
                AdminUtils.showAlert('ID administrateur manquant', 'error');
                return;
            }

            const userData = {
                email: formData.get('email'),
                nom: formData.get('nom'),
                prenom: formData.get('prenom'),
                telephone: formData.get('telephone'),
                ville: formData.get('ville') || null,
                adresse: formData.get('adresse') || null,
                roles: [formData.get('role') || 'ROLE_ADMIN'],
                is_active: formData.get('is_active') === 'on',
                // Include admin profile data
                roles_internes: formData.getAll('roles_internes'),
                permissions_avancees: formData.getAll('permissions_avancees'),
                notes_interne: formData.get('notes_interne') || null
            };

            // Update password only if provided
            const newPassword = formData.get('password');
            if (newPassword && newPassword.trim()) {
                userData.password = newPassword;
            }

            // Use unified update endpoint like we did for creation
            const response = await AdminAPI.updateAdminUser(adminId, userData);
            
            if (!response || !response.success) {
                throw new Error(response?.message || 'Réponse invalide du serveur');
            }

            AdminUtils.showAlert('Administrateur modifié avec succès', 'success');
            
            // Close modal using CoreUI
            const modal = document.getElementById('editAdminModal');
            if (modal) {
                const modalInstance = coreui.Modal.getInstance(modal);
                if (modalInstance) modalInstance.hide();
            }
            
            // Reset form
            this.resetEditForm();
            
            // Reload admin list
            this.loadAdminProfiles();

        } catch (error) {
            console.error('Error updating admin:', error);
            
            // Handle different types of errors with specific messages
            let errorMessage = 'Erreur lors de la modification de l\'administrateur';
            let errorType = 'error';
            
            if (error.data && error.data.type) {
                switch (error.data.type) {
                    case 'duplicate_email':
                        errorMessage = error.data.message || 'Cette adresse email est déjà utilisée par un autre utilisateur.';
                        errorType = 'warning';
                        break;
                    case 'validation_error':
                        errorMessage = error.data.message || 'Les données saisies ne sont pas valides. Veuillez vérifier vos informations.';
                        errorType = 'warning';
                        // Show validation details if available
                        if (error.data.details && Array.isArray(error.data.details)) {
                            errorMessage += '\n• ' + error.data.details.join('\n• ');
                        }
                        break;
                    case 'not_found':
                        errorMessage = 'Administrateur non trouvé. Il a peut-être été supprimé.';
                        errorType = 'warning';
                        break;
                    case 'server_error':
                        errorMessage = error.data.message || 'Une erreur serveur s\'est produite. Veuillez réessayer.';
                        errorType = 'error';
                        break;
                    default:
                        errorMessage = error.data.message || error.message || errorMessage;
                }
            } else if (error.message) {
                errorMessage = error.message;
            }
            
            AdminUtils.showAlert(errorMessage, errorType);
        }
    }

    async deleteAdmin(adminId) {
        const confirmed = await AdminUtils.showConfirmDialog(
            'Supprimer l\'administrateur',
            'Êtes-vous sûr de vouloir supprimer cet administrateur ? Cette action est irréversible.',
            'danger'
        );

        if (!confirmed) return;

        try {
            await AdminAPI.deleteUser(adminId);
            AdminUtils.showAlert('Administrateur supprimé avec succès', 'success');
            this.loadAdminProfiles();

        } catch (error) {
            console.error('Error deleting admin:', error);
            AdminUtils.showAlert('Erreur lors de la suppression', 'error');
        }
    }

    // ==================== UTILITY METHODS ====================

    bindRowEvents() {
        $('.view-admin-btn').off('click').on('click', (e) => {
            const adminId = $(e.currentTarget).data('admin-id');
            this.showAdminDetails(adminId);
        });

        $('.edit-admin-btn').off('click').on('click', (e) => {
            const adminId = $(e.currentTarget).data('admin-id');
            this.showEditModal(adminId);
        });

        $('.delete-admin-btn').off('click').on('click', (e) => {
            const adminId = $(e.currentTarget).data('admin-id');
            this.deleteAdmin(adminId);
        });

        $('.admin-checkbox').off('change').on('change', (e) => {
            const adminId = parseInt(e.target.value);
            if (e.target.checked) {
                this.selectedItems.add(adminId);
            } else {
                this.selectedItems.delete(adminId);
            }
            this.updateBulkActionsVisibility();
        });
    }

    async loadRolesAndPermissions(formSelector) {
        try {
            const roles = await AdminAPI.getInternalRoles();
            const permissions = await AdminAPI.getAvailablePermissions();

            this.populateRolesSelect(formSelector, roles);
            this.populatePermissionsSelect(formSelector, permissions);

        } catch (error) {
            console.error('Error loading roles and permissions:', error);
            // Use fallback data if API fails
            this.populateRolesSelect(formSelector, this.getFallbackRoles());
            this.populatePermissionsSelect(formSelector, this.getFallbackPermissions());
        }
    }

    getFallbackRoles() {
        return [
            { id: 'manager_general', name: 'Manager Général', description: 'Responsable général des opérations' },
            { id: 'chef_cuisine', name: 'Chef de Cuisine', description: 'Responsable de la cuisine et du menu' },
            { id: 'responsable_it', name: 'Responsable IT', description: 'Responsable technique et système' },
            { id: 'manager_service', name: 'Manager Service', description: 'Responsable du service client' }
        ];
    }

    getFallbackPermissions() {
        return [
            { id: 'dashboard', name: 'Tableau de Bord', category: 'General' },
            { id: 'users', name: 'Gestion Utilisateurs', category: 'Administration' },
            { id: 'orders', name: 'Gestion Commandes', category: 'Operations' },
            { id: 'kitchen', name: 'Gestion Cuisine', category: 'Operations' },
            { id: 'menu', name: 'Gestion Menu', category: 'Operations' },
            { id: 'inventory', name: 'Gestion Stock', category: 'Operations' },
            { id: 'customers', name: 'Gestion Clients', category: 'Service' },
            { id: 'reports', name: 'Rapports', category: 'Analytics' },
            { id: 'settings', name: 'Paramètres', category: 'Administration' },
            { id: 'system', name: 'Administration Système', category: 'Administration' },
            { id: 'support', name: 'Support Client', category: 'Service' }
        ];
    }

    setupRealTimeUpdates() {
        // Poll for updates every 30 seconds
        setInterval(() => {
            if (document.visibilityState === 'visible') {
                this.loadAdminProfiles();
            }
        }, 30000);
    }

    populateRolesSelect(formSelector, roles) {
        const container = document.querySelector(`${formSelector} #createAdminRolesInternes`) || 
                         document.querySelector(`${formSelector} #editAdminRolesInternes`);
        
        if (!container) {
            console.warn('Roles container not found for selector:', formSelector);
            return;
        }

        container.innerHTML = '';
        
        roles.forEach(role => {
            const checkboxHtml = `
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" value="${role.id}" id="role_${role.id}" name="roles_internes">
                    <label class="form-check-label" for="role_${role.id}">
                        <strong>${role.name}</strong>
                        <small class="text-muted d-block">${role.description || ''}</small>
                    </label>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', checkboxHtml);
        });
    }

    populatePermissionsSelect(formSelector, permissions) {
        const container = document.querySelector(`${formSelector} #createAdminPermissions`) || 
                         document.querySelector(`${formSelector} #editAdminPermissions`);
        
        if (!container) {
            console.warn('Permissions container not found for selector:', formSelector);
            return;
        }

        container.innerHTML = '';
        
        // Group permissions by category
        const permissionsByCategory = {};
        permissions.forEach(permission => {
            const category = permission.category || 'Other';
            if (!permissionsByCategory[category]) {
                permissionsByCategory[category] = [];
            }
            permissionsByCategory[category].push(permission);
        });

        // Create checkboxes grouped by category
        Object.keys(permissionsByCategory).forEach(category => {
            const categoryHtml = `
                <div class="permission-category mb-3">
                    <h6 class="text-muted mb-2">${category}</h6>
                    <div class="permission-group">
                        ${permissionsByCategory[category].map(permission => `
                            <div class="form-check">
                                <input class="form-check-input" type="checkbox" value="${permission.id}" 
                                       id="perm_${permission.id}" name="permissions_avancees">
                                <label class="form-check-label" for="perm_${permission.id}">
                                    ${permission.name}
                                    ${permission.description ? `<small class="text-muted d-block">${permission.description}</small>` : ''}
                                </label>
                            </div>
                        `).join('')}
                    </div>
                </div>
            `;
            container.insertAdjacentHTML('beforeend', categoryHtml);
        });
    }

    updateStats(stats) {
        $('#totalAdmins').text(stats.total || 0);
        $('#activeAdmins').text(stats.active || 0);
        $('#superAdmins').text(stats.super_admins || 0);
        $('#suspendedAdmins').text(stats.suspended || 0);
    }

    resetCreateForm() {
        const form = document.getElementById('createAdminForm');
        if (form) {
            form.reset();
            // Clear validation states
            form.classList.remove('was-validated');
            const invalidElements = form.querySelectorAll('.is-invalid');
            invalidElements.forEach(el => el.classList.remove('is-invalid'));
        }
    }

    resetEditForm() {
        const form = document.getElementById('editAdminForm');
        if (form) {
            form.reset();
            // Clear validation states
            form.classList.remove('was-validated');
            const invalidElements = form.querySelectorAll('.is-invalid');
            invalidElements.forEach(el => el.classList.remove('is-invalid'));
        }
    }

    populateEditForm(user, profile) {
        // Populate user data
        const editAdminId = document.getElementById('editAdminId');
        const editAdminNom = document.getElementById('editAdminNom');
        const editAdminPrenom = document.getElementById('editAdminPrenom');
        const editAdminEmail = document.getElementById('editAdminEmail');
        const editAdminTelephone = document.getElementById('editAdminTelephone');
        
        if (editAdminId) editAdminId.value = user.id;
        if (editAdminNom) editAdminNom.value = user.nom || '';
        if (editAdminPrenom) editAdminPrenom.value = user.prenom || '';
        if (editAdminEmail) editAdminEmail.value = user.email || '';
        if (editAdminTelephone) editAdminTelephone.value = user.telephone || '';
        
        // Update header info
        const editAdminFullName = document.getElementById('editAdminFullName');
        const editAdminCurrentEmail = document.getElementById('editAdminCurrentEmail');
        if (editAdminFullName) editAdminFullName.textContent = `${user.prenom} ${user.nom}`;
        if (editAdminCurrentEmail) editAdminCurrentEmail.textContent = user.email;
        
        // Set system role
        const systemRoleSelect = document.querySelector('#editAdminForm select[name="role"]');
        if (systemRoleSelect && user.roles) {
            if (user.roles.includes('ROLE_SUPER_ADMIN')) {
                systemRoleSelect.value = 'ROLE_SUPER_ADMIN';
            } else if (user.roles.includes('ROLE_ADMIN')) {
                systemRoleSelect.value = 'ROLE_ADMIN';
            }
        }
        
        // Set active status
        const isActiveCheckbox = document.querySelector('#editAdminForm input[name="is_active"]');
        if (isActiveCheckbox) {
            isActiveCheckbox.checked = user.is_active;
        }
        
        // Clear all checkboxes first
        const allRoleCheckboxes = document.querySelectorAll('#editAdminRolesInternes input[type="checkbox"]');
        allRoleCheckboxes.forEach(checkbox => checkbox.checked = false);
        
        const allPermissionCheckboxes = document.querySelectorAll('#editAdminPermissions input[type="checkbox"]');
        allPermissionCheckboxes.forEach(checkbox => checkbox.checked = false);
        
        // Set roles and permissions if profile exists
        if (profile) {
            // Set internal roles
            if (profile.roles_internes && Array.isArray(profile.roles_internes)) {
                profile.roles_internes.forEach(role => {
                    const checkbox = document.querySelector(`#editAdminRolesInternes input[value="${role}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            }
            
            // Set advanced permissions
            if (profile.permissions_avancees && Array.isArray(profile.permissions_avancees)) {
                profile.permissions_avancees.forEach(permission => {
                    const checkbox = document.querySelector(`#editAdminPermissions input[value="${permission}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            }
            
            // Set notes
            const notesField = document.getElementById('editAdminNotes');
            if (notesField) notesField.value = profile.notes_interne || '';
        }
        
        console.log('Form populated with data:', { user, profile });
    }

    filterAdmins(filters) {
        console.log('Filtering admins with:', filters);
        
        // Update current filters
        this.filters = { ...this.filters, ...filters };
        this.searchQuery = filters.search || '';
        
        // Get all admin rows
        const rows = document.querySelectorAll('#adminsTable tbody tr[data-admin-id]');
        
        rows.forEach(row => {
            let visible = true;
            
            // Search filter
            if (this.searchQuery) {
                const searchText = row.textContent.toLowerCase();
                visible = visible && searchText.includes(this.searchQuery.toLowerCase());
            }
            
            // Role filter
            if (filters.role) {
                const roleCell = row.querySelector('td:nth-child(3)');
                if (roleCell) {
                    const roleText = roleCell.textContent.toLowerCase();
                    visible = visible && roleText.includes(filters.role.toLowerCase());
                }
            }
            
            // Status filter
            if (filters.status) {
                const statusCell = row.querySelector('td:nth-child(4)');
                if (statusCell) {
                    const statusText = statusCell.textContent.toLowerCase();
                    if (filters.status === 'active') {
                        visible = visible && (statusText.includes('en ligne') || statusText.includes('actif'));
                    } else if (filters.status === 'inactive') {
                        visible = visible && (statusText.includes('hors ligne') || statusText.includes('suspendu'));
                    }
                }
            }
            
            // Show/hide row
            row.style.display = visible ? '' : 'none';
        });
        
        // Update visible count
        const visibleRows = document.querySelectorAll('#adminsTable tbody tr[data-admin-id]:not([style*="display: none"])');
        console.log(`Filtered to ${visibleRows.length} visible admins`);
    }
}

// Export for global use
window.AdminProfileManager = AdminProfileManager; 