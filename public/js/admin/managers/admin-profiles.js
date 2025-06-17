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
        // TODO: Enable when activity endpoint is ready
        // this.loadActivityFeed();
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
            // Show loading state
            this.showLoadingState();
            
            console.log('🔄 Loading admin profiles from API...');
            
            // Load admin users from our unified API
            const response = await AdminAPI.getAdminUsers();
            
            if (response && response.success && response.data) {
                this.admins = response.data;
                this.renderAdminProfiles(this.admins);
                this.updateStats();
                console.log(`📊 Loaded ${this.admins.length} admin profiles`);
            } else {
                console.error('❌ Invalid API response format');
                throw new Error('Invalid API response format');
            }
            
        } catch (error) {
            console.error('❌ Error loading admin profiles:', error);
            this.handleLoadError(error);
        } finally {
            this.hideLoadingState();
        }
    }

    showLoadingState() {
        const loadingElement = document.getElementById('loadingState');
        const tableElement = document.getElementById('adminTableCard');
        
        if (loadingElement) loadingElement.style.display = 'block';
        if (tableElement) tableElement.style.display = 'none';
    }

    hideLoadingState() {
        const loadingElement = document.getElementById('loadingState');
        const tableElement = document.getElementById('adminTableCard');
        
        if (loadingElement) loadingElement.style.display = 'none';
        if (tableElement) tableElement.style.display = 'block';
    }

    handleLoadError(error) {
        console.error('Failed to load admin profiles:', error);
        
        // Show error message in the table
        const tbody = document.getElementById('adminTableBody');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="text-danger">
                            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                            <h5>Erreur de chargement</h5>
                            <p class="text-muted">Impossible de charger les administrateurs.</p>
                            <button class="btn btn-outline-primary" onclick="adminProfileManager.loadAdminProfiles()">
                                <i class="fas fa-refresh"></i> Réessayer
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }
        
        // Show alert
        AdminUtils.showAlert('Erreur lors du chargement des administrateurs', 'error');
    }

    renderAdminProfiles(admins) {
        const tbody = document.getElementById('adminTableBody');
        if (!tbody) {
            console.error('❌ Admin table body not found');
            return;
        }

        if (!admins || admins.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-users fa-3x mb-3"></i>
                            <h5>Aucun administrateur trouvé</h5>
                            <p>Commencez par créer un administrateur.</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }
        
        // Clear existing content
        tbody.innerHTML = '';
        
        // Render each admin
        admins.forEach((admin, index) => {
            try {
                const rowHtml = this.createAdminRow(admin, admin.admin_profile);
                
                // Create a temporary table to properly parse the TR element
                const tempTable = document.createElement('table');
                const tempTbody = document.createElement('tbody');
                tempTbody.innerHTML = rowHtml;
                tempTable.appendChild(tempTbody);
                
                const row = tempTbody.firstElementChild;
                
                if (row && row.tagName === 'TR') {
                    tbody.appendChild(row);
                } else {
                    console.error(`❌ Failed to create TR element for admin ${admin.id}`);
                }
            } catch (error) {
                console.error(`❌ Error processing admin ${admin.id}:`, error);
            }
        });
        
        // Re-bind events after DOM update
        this.bindRowEvents();
        
        console.log(`✅ Rendered ${admins.length} admin profiles successfully`);
    }

    // ==================== STATIC DATA FALLBACK ====================
    
    renderStaticAdminData() {
        console.warn('🚨 Using static data fallback - API not available');
        
        // For now, just keep the existing static data and remove loading
        // The static admin data is already in the template
        console.log('Loaded admin profiles successfully - displaying static data');
        
        // Update stats with static data
        this.updateStats({
            total: 4,
            super_admins: 1,
            active: 3,
            suspended: 1
        });
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
                        <div class="me-3">
                            <div class="profile-picture-container profile-picture-sm" data-user-id="${user.id}">
                                <div class="profile-picture-wrapper ${user.can_edit ? 'profile-picture-upload-trigger' : ''}">
                                    ${user.photo_profil_url || user.photoProfilUrl ? 
                                        `<img src="${user.photo_profil_url || user.photoProfilUrl}" alt="${user.nom}" class="profile-picture-img">` :
                                        `<div class="profile-picture-placeholder">
                                            <span class="profile-picture-initials">${user.nom?.charAt(0) || ''}${user.prenom?.charAt(0) || ''}</span>
                                        </div>`
                                    }
                                    ${user.can_edit ? `
                                        <div class="profile-picture-overlay">
                                            <i class="fas fa-camera"></i>
                                            <span>Changer</span>
                                        </div>
                                    ` : ''}
                                </div>
                                ${user.can_edit ? `
                                    <input type="file" class="profile-picture-input" accept="image/*" style="display: none;" data-user-id="${user.id}">
                                ` : ''}
                                <div class="profile-picture-loading" style="display: none;">
                                    <div class="spinner-border spinner-border-sm" role="status">
                                        <span class="visually-hidden">Upload...</span>
                                    </div>
                                </div>
                            </div>
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
        // ✨ NEW: Close any existing modals first to prevent stacking
        this.closeAllModals();
        
        // Using CoreUI modal instead of Bootstrap
        const modal = document.getElementById('createAdminModal');
        if (modal) {
            const modalInstance = new coreui.Modal(modal);
            modalInstance.show();
            
            // Initialize profile picture upload container
            setTimeout(() => {
                const container = document.getElementById('createAdminProfilePictureContainer');
                if (container) {
                    container.innerHTML = `
                        <div class="profile-picture-container profile-picture-lg profile-picture-dropzone">
                            <div class="dropzone-content">
                                <div class="profile-picture-wrapper profile-picture-upload-trigger">
                                    <div class="profile-picture-placeholder">
                                        <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                                    </div>
                                    <div class="profile-picture-overlay">
                                        <i class="fas fa-camera"></i>
                                        <span>Ajouter une photo</span>
                                    </div>
                                </div>
                                <input type="file" class="profile-picture-input" accept="image/*" style="display: none;">
                                <div class="profile-picture-loading" style="display: none;">
                                    <div class="spinner-border" role="status">
                                        <span class="visually-hidden">Upload...</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    `;
                }
            }, 100);
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
                            <!-- Profile Picture Section -->
                            <div class="text-center mb-4">
                                <div class="profile-picture-container profile-picture-lg" data-user-id="${adminData.id}">
                                    <div class="profile-picture-wrapper">
                                        ${adminData.photo_profil_url || adminData.photoProfilUrl ? 
                                            `<img src="${adminData.photo_profil_url || adminData.photoProfilUrl}" alt="${adminData.nom}" class="profile-picture-img">` :
                                            `<div class="profile-picture-placeholder">
                                                <span class="profile-picture-initials">${adminData.nom?.charAt(0) || ''}${adminData.prenom?.charAt(0) || ''}</span>
                                            </div>`
                                        }
                                    </div>
                                </div>
                                <h5 class="mt-3 mb-1">${adminData.nom} ${adminData.prenom}</h5>
                                <p class="text-muted mb-0">${adminData.email}</p>
                            </div>

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
                            ${adminData.can_edit ? `
                                <button type="button" class="btn btn-primary" onclick="adminProfileManager.showEditModal(${adminData.id})" id="editFromDetailsBtn">
                                    <i class="fas fa-edit me-1"></i>Modifier
                                </button>
                            ` : `
                                <button type="button" class="btn btn-outline-secondary" disabled title="Vous n'avez pas les permissions pour modifier cet utilisateur">
                                    <i class="fas fa-lock me-1"></i>Modification non autorisée
                                </button>
                            `}
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
            
            // ✨ NEW: Close any existing modals first to prevent stacking
            this.closeAllModals();
            
            // Small delay to ensure modal transition is complete
            await new Promise(resolve => setTimeout(resolve, 300));
            
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
                        
                        // Initialize profile picture components
                        setTimeout(() => {
                            this.initializeProfilePictureForEdit(adminData);
                        }, 100);
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

    /**
     * Initialize profile picture display and management for edit modal
     */
    initializeProfilePictureForEdit(adminData) {
        console.log('🖼️ Initializing profile picture for edit modal:', adminData);
        
        // Update the profile picture display in the header
        const displayContainer = document.getElementById('editAdminProfilePictureDisplay');
        if (displayContainer) {
            displayContainer.innerHTML = `
                <div class="profile-picture-container profile-picture-md" data-user-id="${adminData.id}">
                    <div class="profile-picture-wrapper">
                        ${adminData.photo_profil_url || adminData.photoProfilUrl ? 
                            `<img src="${adminData.photo_profil_url || adminData.photoProfilUrl}" alt="${adminData.nom}" class="profile-picture-img">` :
                            `<div class="profile-picture-placeholder">
                                <span class="profile-picture-initials">${adminData.nom?.charAt(0) || ''}${adminData.prenom?.charAt(0) || ''}</span>
                            </div>`
                        }
                    </div>
                </div>
            `;
        }
        
        // Initialize the profile picture management container with upload functionality
        const managementContainer = document.getElementById('editAdminProfilePictureContainer');
        if (managementContainer) {
            managementContainer.innerHTML = `
                <div class="profile-picture-container profile-picture-lg" data-user-id="${adminData.id}">
                    <div class="profile-picture-wrapper profile-picture-upload-trigger">
                        ${adminData.photo_profil_url || adminData.photoProfilUrl ? 
                            `<img src="${adminData.photo_profil_url || adminData.photoProfilUrl}" alt="${adminData.nom}" class="profile-picture-img">` :
                            `<div class="profile-picture-placeholder">
                                <span class="profile-picture-initials">${adminData.nom?.charAt(0) || ''}${adminData.prenom?.charAt(0) || ''}</span>
                            </div>`
                        }
                        <div class="profile-picture-overlay">
                            <i class="fas fa-camera"></i>
                            <span>Changer la photo</span>
                        </div>
                    </div>
                    ${adminData.photo_profil_url || adminData.photoProfilUrl ? `
                        <button type="button" class="btn btn-sm btn-outline-danger remove-profile-picture" 
                                data-user-id="${adminData.id}" title="Supprimer la photo">
                            <i class="fas fa-times"></i>
                        </button>
                    ` : ''}
                    <input type="file" class="profile-picture-input" accept="image/*" style="display: none;" data-user-id="${adminData.id}">
                    <div class="profile-picture-loading" style="display: none;">
                        <div class="spinner-border" role="status">
                            <span class="visually-hidden">Upload...</span>
                        </div>
                    </div>
                </div>
            `;
        }
        
        // Listen for profile picture updates to refresh the display
        document.addEventListener('profilePictureUpdated', (event) => {
            if (event.detail.userId == adminData.id) {
                console.log('🔄 Profile picture updated for user:', adminData.id, event.detail.data);
                
                // Update header display
                if (displayContainer) {
                    const headerImg = displayContainer.querySelector('.profile-picture-img');
                    const headerPlaceholder = displayContainer.querySelector('.profile-picture-placeholder');
                    
                    if (event.detail.data.photo_url) {
                        if (headerImg) {
                            headerImg.src = event.detail.data.photo_url;
                            headerImg.style.display = 'block';
                        } else if (headerPlaceholder) {
                            headerPlaceholder.parentElement.innerHTML = `<img src="${event.detail.data.photo_url}" alt="${adminData.nom}" class="profile-picture-img">`;
                        }
                        if (headerPlaceholder) headerPlaceholder.style.display = 'none';
                    } else {
                        if (headerImg) headerImg.style.display = 'none';
                        if (headerPlaceholder) {
                            headerPlaceholder.style.display = 'flex';
                        } else {
                            const wrapper = displayContainer.querySelector('.profile-picture-wrapper');
                            if (wrapper) {
                                wrapper.innerHTML = `<div class="profile-picture-placeholder">
                                    <span class="profile-picture-initials">${adminData.nom?.charAt(0) || ''}${adminData.prenom?.charAt(0) || ''}</span>
                                </div>`;
                            }
                        }
                    }
                }
                
                // Update management container
                if (managementContainer) {
                    const managementImg = managementContainer.querySelector('.profile-picture-img');
                    const managementPlaceholder = managementContainer.querySelector('.profile-picture-placeholder');
                    const removeBtn = managementContainer.querySelector('.remove-profile-picture');
                    
                    if (event.detail.data.photo_url) {
                        if (managementImg) {
                            managementImg.src = event.detail.data.photo_url;
                            managementImg.style.display = 'block';
                        } else if (managementPlaceholder) {
                            managementPlaceholder.parentElement.innerHTML = `<img src="${event.detail.data.photo_url}" alt="${adminData.nom}" class="profile-picture-img">
                                <div class="profile-picture-overlay">
                                    <i class="fas fa-camera"></i>
                                    <span>Changer la photo</span>
                                </div>`;
                        }
                        if (managementPlaceholder) managementPlaceholder.style.display = 'none';
                        if (removeBtn) {
                            removeBtn.style.display = 'block';
                        } else {
                            // Add remove button if it doesn't exist
                            const container = managementContainer.querySelector('.profile-picture-container');
                            if (container) {
                                const newRemoveBtn = document.createElement('button');
                                newRemoveBtn.type = 'button';
                                newRemoveBtn.className = 'btn btn-sm btn-outline-danger remove-profile-picture';
                                newRemoveBtn.dataset.userId = adminData.id;
                                newRemoveBtn.title = 'Supprimer la photo';
                                newRemoveBtn.innerHTML = '<i class="fas fa-times"></i>';
                                container.appendChild(newRemoveBtn);
                            }
                        }
                    } else {
                        if (managementImg) managementImg.style.display = 'none';
                        if (managementPlaceholder) {
                            managementPlaceholder.style.display = 'flex';
                        } else {
                            const wrapper = managementContainer.querySelector('.profile-picture-wrapper');
                            if (wrapper) {
                                wrapper.innerHTML = `<div class="profile-picture-placeholder">
                                    <span class="profile-picture-initials">${adminData.nom?.charAt(0) || ''}${adminData.prenom?.charAt(0) || ''}</span>
                                </div>
                                <div class="profile-picture-overlay">
                                    <i class="fas fa-camera"></i>
                                    <span>Changer la photo</span>
                                </div>`;
                            }
                        }
                        if (removeBtn) removeBtn.style.display = 'none';
                    }
                }
                
                // Also update the table row if visible
                const tableRow = document.querySelector(`tr[data-admin-id="${adminData.id}"]`);
                if (tableRow) {
                    const tableImg = tableRow.querySelector('.profile-picture-img');
                    const tablePlaceholder = tableRow.querySelector('.profile-picture-placeholder');
                    
                    if (event.detail.data.photo_url) {
                        if (tableImg) {
                            tableImg.src = event.detail.data.photo_url;
                            tableImg.style.display = 'block';
                        }
                        if (tablePlaceholder) tablePlaceholder.style.display = 'none';
                    } else {
                        if (tableImg) tableImg.style.display = 'none';
                        if (tablePlaceholder) tablePlaceholder.style.display = 'flex';
                    }
                }
            }
        });
    }

    /**
     * ✨ NEW: Close all open modals to prevent stacking
     */
    closeAllModals() {
        // List of modal IDs that might be open
        const modalIds = [
            'adminDetailsModal',
            'editAdminModal',
            'createAdminModal',
            'deleteConfirmModal'
        ];
        
        modalIds.forEach(modalId => {
            const modalElement = document.getElementById(modalId);
            if (modalElement) {
                const modalInstance = coreui.Modal.getInstance(modalElement);
                if (modalInstance) {
                    modalInstance.hide();
                }
            }
        });
        
        // Also close any backdrop that might be lingering
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
    }

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
            console.log('🔄 Loading roles and permissions from database...');
            const roles = await AdminAPI.getInternalRoles();
            const permissions = await AdminAPI.getAvailablePermissions();

            console.log('✅ Loaded roles from database:', roles);
            console.log('✅ Loaded permissions from database:', permissions);

            this.populateRolesSelect(formSelector, roles);
            this.populatePermissionsSelect(formSelector, permissions);

        } catch (error) {
            console.error('❌ Error loading roles and permissions:', error);
            // Use fallback data if API fails
            console.log('🔄 Using fallback data...');
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

    updateStats(providedStats = null) {
        if (providedStats) {
            // Use provided stats (for backward compatibility)
            const totalElement = document.getElementById('totalAdmins');
            const activeElement = document.getElementById('activeAdmins');
            const superAdminElement = document.getElementById('superAdminCount');
            const suspendedElement = document.getElementById('suspendedAdmins');

            if (totalElement) totalElement.textContent = providedStats.total || providedStats.total_admins || 0;
            if (activeElement) activeElement.textContent = providedStats.active || providedStats.active_admins || 0;
            if (superAdminElement) superAdminElement.textContent = providedStats.super_admins || 0;
            if (suspendedElement) suspendedElement.textContent = providedStats.suspended || 0;
            
            return;
        }

        // Calculate stats from loaded admin data
        if (!this.admins || !Array.isArray(this.admins)) {
            console.warn('No admin data available for stats calculation');
            return;
        }

        const stats = {
            total: this.admins.length,
            active: this.admins.filter(admin => admin.is_active).length,
            super_admins: this.admins.filter(admin => admin.roles && admin.roles.includes('ROLE_SUPER_ADMIN')).length,
            suspended: this.admins.filter(admin => !admin.is_active).length
        };

        // Update DOM elements
        const totalElement = document.getElementById('totalAdmins');
        const activeElement = document.getElementById('activeAdmins');
        const superAdminElement = document.getElementById('superAdminCount');
        const suspendedElement = document.getElementById('suspendedAdmins');

        if (totalElement) {
            totalElement.textContent = stats.total;
            totalElement.parentElement.parentElement.classList.toggle('animate__pulse', stats.total > 0);
        }
        if (activeElement) {
            activeElement.textContent = stats.active;
            activeElement.parentElement.parentElement.classList.toggle('animate__pulse', stats.active > 0);
        }
        if (superAdminElement) {
            superAdminElement.textContent = stats.super_admins;
            superAdminElement.parentElement.parentElement.classList.toggle('animate__pulse', stats.super_admins > 0);
        }
        if (suspendedElement) {
            suspendedElement.textContent = stats.suspended;
            suspendedElement.parentElement.parentElement.classList.toggle('animate__pulse', stats.suspended > 0);
        }

        console.log('📊 Updated admin statistics:', stats);
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
            console.log('📝 Setting profile data:', profile);
            
            // Set internal roles
            if (profile.roles_internes && Array.isArray(profile.roles_internes)) {
                console.log('🎭 Setting internal roles:', profile.roles_internes);
                profile.roles_internes.forEach(role => {
                    const checkbox = document.querySelector(`#editAdminRolesInternes input[value="${role}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                        console.log(`✅ Checked role: ${role}`);
                    } else {
                        console.warn(`❌ Role checkbox not found: ${role}`);
                    }
                });
            }
            
            // Set advanced permissions
            if (profile.permissions_avancees && Array.isArray(profile.permissions_avancees)) {
                console.log('🔐 Setting permissions:', profile.permissions_avancees);
                profile.permissions_avancees.forEach(permission => {
                    const checkbox = document.querySelector(`#editAdminPermissions input[value="${permission}"]`);
                    if (checkbox) {
                        checkbox.checked = true;
                        console.log(`✅ Checked permission: ${permission}`);
                    } else {
                        console.warn(`❌ Permission checkbox not found: ${permission}`);
                    }
                });
            }
            
            // Set notes
            const notesField = document.getElementById('editAdminNotes');
            if (notesField) notesField.value = profile.notes_interne || '';
        } else {
            console.warn('❌ No profile data provided to populate form');
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

    // ==================== ACTIVITY FEED ====================
    // TODO: Implement when activity endpoint is ready

    async loadActivityFeed() {
        // Temporarily disabled - endpoint not ready
        console.log('🔄 Activity feed temporarily disabled');
        this.showActivityError('Fonctionnalité en cours de développement');
        return;
    }

    renderActivityFeed(activities) {
        const timeline = document.getElementById('activityTimeline');
        if (!timeline) return;

        if (!activities || activities.length === 0) {
            timeline.innerHTML = `
                <div class="text-center text-muted py-3">
                    <i class="fas fa-info-circle"></i>
                    Aucune activité récente
                </div>
            `;
            return;
        }

        const activityHtml = activities.map(activity => {
            const actionIcon = this.getActionIcon(activity.action);
            const actionColor = this.getActionColor(activity.action);
            
            return `
                <div class="activity-item d-flex align-items-start mb-3">
                    <div class="activity-icon me-3">
                        <div class="rounded-circle bg-${actionColor} text-white d-flex align-items-center justify-content-center" 
                             style="width: 32px; height: 32px; font-size: 12px;">
                            <i class="${actionIcon}"></i>
                        </div>
                    </div>
                    <div class="activity-content flex-grow-1">
                        <div class="activity-text">
                            <strong>${activity.user_name}</strong> 
                            ${this.getActionText(activity.action)} 
                            <span class="text-primary">${activity.entity_type}</span>
                            ${activity.entity_label ? `"${activity.entity_label}"` : ''}
                        </div>
                        <div class="activity-time text-muted small">
                            <i class="fas fa-clock me-1"></i>
                            ${activity.logged_at_formatted}
                        </div>
                        ${activity.changes && Object.keys(activity.changes).length > 0 ? `
                            <div class="activity-changes mt-1">
                                <small class="text-muted">
                                    ${Object.keys(activity.changes).length} champ(s) modifié(s)
                                </small>
                            </div>
                        ` : ''}
                    </div>
                </div>
            `;
        }).join('');

        timeline.innerHTML = activityHtml;
    }

    getActionIcon(action) {
        const icons = {
            'insert': 'fas fa-plus',
            'update': 'fas fa-edit',
            'remove': 'fas fa-trash',
            'delete': 'fas fa-trash'
        };
        return icons[action] || 'fas fa-circle';
    }

    getActionColor(action) {
        const colors = {
            'insert': 'success',
            'update': 'warning',
            'remove': 'danger',
            'delete': 'danger'
        };
        return colors[action] || 'secondary';
    }

    getActionText(action) {
        const texts = {
            'insert': 'a créé',
            'update': 'a modifié',
            'remove': 'a supprimé',
            'delete': 'a supprimé'
        };
        return texts[action] || 'a effectué une action sur';
    }

    showActivityError(message) {
        const timeline = document.getElementById('activityTimeline');
        if (timeline) {
            timeline.innerHTML = `
                <div class="text-center text-danger py-3">
                    <i class="fas fa-exclamation-triangle"></i>
                    ${message}
                </div>
            `;
        }
    }
}

// Export for global use
window.AdminProfileManager = AdminProfileManager; 