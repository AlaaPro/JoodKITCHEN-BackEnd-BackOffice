/**
 * KitchenStaffManager - CRUD operations for Kitchen Staff Profiles
 * Following AdminProfileManager pattern: 100% API separation with CoreUI integration
 */
class KitchenStaffManager {
    constructor() {
        this.currentPage = 1;
        this.perPage = 10;
        this.searchQuery = '';
        this.filters = {};
        this.selectedItems = new Set();
        
        this.init().catch(error => {
            console.error('❌ Failed to initialize Kitchen Staff Manager:', error);
        });
    }

    async init() {
        console.log('🔧 Initializing Kitchen Staff Manager...');
        this.bindEvents();
        
        // Wait for complete DOM readiness
        await this.waitForDOM();
        
        await this.loadKitchenStaff();
        this.setupRealTimeUpdates();
        console.log('✅ Kitchen Staff Manager fully initialized');
    }

    async waitForDOM() {
        // Wait for document ready
        if (document.readyState === 'loading') {
            await new Promise(resolve => document.addEventListener('DOMContentLoaded', resolve));
        }
        
        // Additional check - wait for table to be available
        let attempts = 0;
        const maxAttempts = 20;
        
        while (attempts < maxAttempts) {
            const tbody = document.getElementById('staffTableBody');
            if (tbody) {
                console.log('✅ DOM ready - table found after', attempts, 'attempts');
                return;
            }
            
            console.log(`🔄 Waiting for DOM... attempt ${attempts + 1}/${maxAttempts}`);
            await new Promise(resolve => setTimeout(resolve, 100));
            attempts++;
        }
        
        console.error('❌ Table not found after waiting. Current DOM state:');
        console.log('Document ready state:', document.readyState);
        console.log('Available elements:', Array.from(document.querySelectorAll('[id]')).map(el => el.id));
    }

    // ==================== EVENT BINDINGS ====================

    bindEvents() {
        // Create Staff button
        const createBtn = document.getElementById('createStaffBtn');
        if (createBtn) {
            createBtn.addEventListener('click', () => this.showCreateModal());
        }
        
        // Search input
        const searchInput = document.getElementById('staffSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.searchQuery = e.target.value;
                this.loadKitchenStaff();
            });
        }

        // Filter dropdowns
        const positionFilter = document.getElementById('positionFilter');
        if (positionFilter) {
            positionFilter.addEventListener('change', (e) => {
                this.filters.position = e.target.value;
                this.loadKitchenStaff();
            });
        }

        const statusFilter = document.getElementById('statusFilter');
        if (statusFilter) {
            statusFilter.addEventListener('change', (e) => {
                this.filters.status = e.target.value;
                this.loadKitchenStaff();
            });
        }

        // Form submissions
        const createForm = document.getElementById('createStaffForm');
        if (createForm) {
            createForm.addEventListener('submit', (e) => this.handleCreate(e));
        }

        const editForm = document.getElementById('editStaffForm');
        if (editForm) {
            editForm.addEventListener('submit', (e) => this.handleUpdate(e));
        }
    }

    // ==================== DATA LOADING ====================

    async loadKitchenStaff() {
        try {
            // Show loading state
            this.showLoadingState();
            
            console.log('🔄 Loading kitchen staff from API...');
            
            // Load kitchen staff from API
            const response = await KitchenAPI.getKitchenStaff();
            
            if (response && response.success && response.data) {
                this.staff = response.data;
                this.stats = response.stats;
                this.renderKitchenStaff(this.staff);
                this.updateStats(this.stats);
                console.log(`📊 Loaded ${this.staff.length} kitchen staff`);
            } else {
                console.error('❌ Invalid API response format');
                throw new Error('Invalid API response format');
            }
            
        } catch (error) {
            console.error('❌ Error loading kitchen staff:', error);
            this.handleLoadError(error);
        } finally {
            this.hideLoadingState();
        }
    }

    showLoadingState() {
        const loadingElement = document.getElementById('loadingState');
        const tableElement = document.getElementById('staffTableCard');
        
        if (loadingElement) loadingElement.style.display = 'block';
        if (tableElement) tableElement.style.display = 'none';
    }

    hideLoadingState() {
        const loadingElement = document.getElementById('loadingState');
        const tableElement = document.getElementById('staffTableCard');
        
        if (loadingElement) loadingElement.style.display = 'none';
        if (tableElement) tableElement.style.display = 'block';
    }

    handleLoadError(error) {
        console.error('Failed to load kitchen staff:', error);
        
        // Show error message in the table
        const tbody = document.getElementById('staffTableBody');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="text-danger">
                            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                            <h5>Erreur de chargement</h5>
                            <p class="text-muted">Impossible de charger le personnel de cuisine.</p>
                            <button class="btn btn-outline-primary" onclick="kitchenStaffManager.loadKitchenStaff()">
                                <i class="fas fa-refresh"></i> Réessayer
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }
        
        // Show alert
        AdminUtils.showAlert('Erreur lors du chargement du personnel de cuisine', 'error');
    }

    renderKitchenStaff(staff) {
        const tbody = document.getElementById('staffTableBody');
        if (!tbody) {
            console.error('❌ Staff table body not found');
            console.log('🔍 Available elements with ID containing "table":', 
                Array.from(document.querySelectorAll('[id*="table"]')).map(el => el.id));
            console.log('🔍 Available tbody elements:', 
                Array.from(document.querySelectorAll('tbody')).map(el => ({ id: el.id, classes: el.className })));
            console.log('🔍 Document ready state:', document.readyState);
            console.log('🔍 Full DOM scan - all elements with IDs:', 
                Array.from(document.querySelectorAll('[id]')).map(el => el.id));
            
            // Retry after a delay in case DOM is still loading
            console.log('🔄 Retrying in 500ms...');
            setTimeout(() => this.renderKitchenStaff(staff), 500);
            return;
        }

        if (!staff || staff.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="7" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-users fa-3x mb-3"></i>
                            <h5>Aucun personnel trouvé</h5>
                            <p>Commencez par ajouter du personnel de cuisine.</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }
        
        // Clear existing content
        tbody.innerHTML = '';
        
        // Render each staff member
        staff.forEach((staffMember) => {
            try {
                const rowHtml = this.createStaffRow(staffMember, staffMember.kitchen_profile);
                
                // Create a temporary table to properly parse the TR element
                const tempTable = document.createElement('table');
                const tempTbody = document.createElement('tbody');
                tempTbody.innerHTML = rowHtml;
                tempTable.appendChild(tempTbody);
                
                const row = tempTbody.firstElementChild;
                
                if (row && row.tagName === 'TR') {
                    tbody.appendChild(row);
                } else {
                    console.error(`❌ Failed to create TR element for staff ${staffMember.id}`);
                }
            } catch (error) {
                console.error(`❌ Error processing staff ${staffMember.id}:`, error);
            }
        });
        
        // Re-bind events after DOM update
        this.bindRowEvents();
        
        console.log(`✅ Rendered ${staff.length} kitchen staff successfully`);
    }

    createStaffRow(user, profile) {
        const statusBadge = this.generateStatusBadge(profile?.statut_travail, user.last_connexion);
        const positionBadge = this.generatePositionBadge(profile?.poste_cuisine);
        const specialitesBadges = this.generateSpecialitesBadges(profile?.specialites || []);

        return `
            <tr data-staff-id="${user.id}" data-profile-id="${profile?.id || ''}">
                <td>
                    <div class="d-flex align-items-center">
                        <div class="me-3">
                            <div class="profile-picture-container profile-picture-sm" data-user-id="${user.id}">
                                <div class="profile-picture-wrapper">
                                    ${user.photo_profil_url 
                                        ? `<img src="${user.photo_profil_url}" alt="${user.prenom} ${user.nom}" class="profile-picture-img">`
                                        : `<div class="profile-picture-initials bg-primary">${(user.prenom?.charAt(0) || '') + (user.nom?.charAt(0) || '')}</div>`
                                    }
                                </div>
                            </div>
                        </div>
                        <div>
                            <div class="fw-semibold">${user.prenom} ${user.nom}</div>
                            <small class="text-muted">${user.email}</small>
                        </div>
                    </div>
                </td>
                <td>
                    ${positionBadge}
                    <br>
                    <small class="text-muted">${profile?.experience_formatted || 'N/A'}</small>
                </td>
                <td>
                    ${statusBadge}
                    <br>
                    <small class="text-muted">
                        ${user.last_connexion 
                            ? `Dernière connexion: ${new Date(user.last_connexion).toLocaleDateString('fr-FR')}`
                            : 'Jamais connecté'
                        }
                    </small>
                </td>
                <td>
                    ${specialitesBadges}
                </td>
                <td>
                    <small class="text-muted">
                        ${profile?.salaire_horaire ? profile.salaire_horaire + '€/h' : 'N/A'}<br>
                        ${profile?.heures_par_semaine ? profile.heures_par_semaine + 'h/sem' : 'N/A'}
                    </small>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="kitchenStaffManager.showStaffDetails(${user.id})" title="Voir détails">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="kitchenStaffManager.showEditModal(${user.id})" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="kitchenStaffManager.deleteStaff(${user.id})" title="Supprimer">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }

    generateStatusBadge(status, lastConnexion) {
        const statusMap = {
            'actif': { label: 'En service', color: 'success', icon: 'user-check' },
            'pause': { label: 'En pause', color: 'warning', icon: 'pause-circle' },
            'absent': { label: 'Absent', color: 'danger', icon: 'user-times' },
            'conge': { label: 'En congé', color: 'info', icon: 'calendar-times' }
        };

        const statusInfo = statusMap[status] || { label: 'Inconnu', color: 'secondary', icon: 'question' };
        
        return `
            <span class="badge bg-${statusInfo.color}">
                <i class="fas fa-${statusInfo.icon}"></i> ${statusInfo.label}
            </span>
        `;
    }

    generatePositionBadge(position) {
        const positionMap = {
            'chef_executif': { label: 'Chef Exécutif', color: 'danger', icon: 'crown' },
            'chef_cuisine': { label: 'Chef de Cuisine', color: 'warning', icon: 'fire' },
            'sous_chef': { label: 'Sous Chef', color: 'info', icon: 'user-tie' },
            'cuisinier': { label: 'Cuisinier', color: 'primary', icon: 'utensils' },
            'commis': { label: 'Commis', color: 'success', icon: 'user-cog' },
            'plongeur': { label: 'Plongeur', color: 'secondary', icon: 'tint' }
        };

        const positionInfo = positionMap[position] || { label: position || 'N/A', color: 'secondary', icon: 'user' };
        
        return `
            <span class="badge bg-${positionInfo.color}">
                <i class="fas fa-${positionInfo.icon}"></i> ${positionInfo.label}
            </span>
        `;
    }

    generateSpecialitesBadges(specialites) {
        if (!specialites || specialites.length === 0) {
            return '<small class="text-muted">Aucune spécialité</small>';
        }

        const specialiteMap = {
            'marocain': { label: '🇲🇦 Marocain', color: 'primary' },
            'italien': { label: '🇮🇹 Italien', color: 'success' },
            'international': { label: '🌍 International', color: 'info' },
            'polyvalent': { label: '✨ Polyvalent', color: 'warning' },
            'patisserie': { label: '🧁 Pâtisserie', color: 'secondary' },
            'grillade': { label: '🔥 Grillades', color: 'danger' }
        };

        return specialites.slice(0, 2).map(specialite => {
            const spec = specialiteMap[specialite] || { label: specialite, color: 'secondary' };
            return `<span class="badge bg-${spec.color} me-1">${spec.label}</span>`;
        }).join('') + (specialites.length > 2 ? `<span class="badge bg-light text-dark">+${specialites.length - 2}</span>` : '');
    }

    // ==================== MODAL OPERATIONS ====================

    async showCreateModal() {
        try {
            // Load positions and specialties
            await this.loadPositionsAndSpecialties('#createStaffForm');
            
            // Reset form
            this.resetCreateForm();
            
            // Show modal
            const modal = new coreui.Modal(document.getElementById('createStaffModal'));
            modal.show();
            
        } catch (error) {
            console.error('Error showing create modal:', error);
            AdminUtils.showAlert('Erreur lors de l\'ouverture du formulaire', 'error');
        }
    }

    async showStaffDetails(staffId) {
        try {
            console.log('🔍 Showing staff details for ID:', staffId);
            
            // Find the staff member in current data
            const staffMember = this.staff.find(s => s.id === staffId);
            if (!staffMember) {
                throw new Error('Personnel non trouvé');
            }
            
            const profile = staffMember.kitchen_profile;
            
            // Create details modal content
            const detailsContent = `
                <div class="modal fade" id="staffDetailsModal" tabindex="-1" aria-labelledby="staffDetailsModalLabel" aria-hidden="true">
                    <div class="modal-dialog modal-lg">
                        <div class="modal-content">
                            <div class="modal-header jood-gradient">
                                <h5 class="modal-title text-white" id="staffDetailsModalLabel">
                                    <i class="fas fa-user"></i> Détails du Personnel
                                </h5>
                                <button type="button" class="btn-close btn-close-white" data-coreui-dismiss="modal" aria-label="Fermer"></button>
                            </div>
                            <div class="modal-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <h6 class="fw-bold mb-3 jood-primary-color">Informations Personnelles</h6>
                                        <table class="table table-borderless">
                                            <tr><td><strong>Nom:</strong></td><td>${staffMember.nom}</td></tr>
                                            <tr><td><strong>Prénom:</strong></td><td>${staffMember.prenom}</td></tr>
                                            <tr><td><strong>Email:</strong></td><td>${staffMember.email}</td></tr>
                                            <tr><td><strong>Téléphone:</strong></td><td>${staffMember.telephone || 'N/A'}</td></tr>
                                            <tr><td><strong>Statut compte:</strong></td><td>
                                                <span class="badge ${staffMember.is_active ? 'bg-success' : 'bg-danger'}">
                                                    ${staffMember.is_active ? 'Actif' : 'Inactif'}
                                                </span>
                                            </td></tr>
                                            <tr><td><strong>Dernière connexion:</strong></td><td>
                                                ${staffMember.last_connexion ? new Date(staffMember.last_connexion).toLocaleString('fr-FR') : 'Jamais'}
                                            </td></tr>
                                        </table>
                                    </div>
                                    <div class="col-md-6">
                                        <h6 class="fw-bold mb-3 jood-primary-color">Informations Cuisine</h6>
                                        <table class="table table-borderless">
                                            <tr><td><strong>Poste:</strong></td><td>${this.generatePositionBadge(profile?.poste_cuisine)}</td></tr>
                                            <tr><td><strong>Statut travail:</strong></td><td>${this.generateStatusBadge(profile?.statut_travail, staffMember.last_connexion)}</td></tr>
                                            <tr><td><strong>Expérience:</strong></td><td>${profile?.experience_annees || 'N/A'} ans</td></tr>
                                            <tr><td><strong>Salaire horaire:</strong></td><td>${profile?.salaire_horaire || 'N/A'}€/h</td></tr>
                                            <tr><td><strong>Heures/semaine:</strong></td><td>${profile?.heures_par_semaine || 'N/A'}h</td></tr>
                                            <tr><td><strong>Date embauche:</strong></td><td>
                                                ${profile?.date_embauche ? new Date(profile.date_embauche).toLocaleDateString('fr-FR') : 'N/A'}
                                            </td></tr>
                                        </table>
                                    </div>
                                </div>
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <h6 class="fw-bold mb-3 jood-primary-color">Spécialités</h6>
                                        <div>${this.generateSpecialitesBadges(profile?.specialites || [])}</div>
                                    </div>
                                </div>
                                ${profile?.notes_interne ? `
                                <div class="row mt-3">
                                    <div class="col-12">
                                        <h6 class="fw-bold mb-3 jood-primary-color">Notes Internes</h6>
                                        <div class="bg-light p-3 rounded">${profile.notes_interne}</div>
                                    </div>
                                </div>
                                ` : ''}
                            </div>
                            <div class="modal-footer">
                                <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">Fermer</button>
                                <button type="button" class="btn btn-primary" onclick="kitchenStaffManager.showEditModal(${staffId})">
                                    <i class="fas fa-edit"></i> Modifier
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            `;
            
            // Remove existing modal if any
            const existingModal = document.getElementById('staffDetailsModal');
            if (existingModal) {
                existingModal.remove();
            }
            
            // Add modal to page
            document.body.insertAdjacentHTML('beforeend', detailsContent);
            
            // Show modal
            const modal = new coreui.Modal(document.getElementById('staffDetailsModal'));
            modal.show();
            
            // Clean up modal after hide
            modal._element.addEventListener('hidden.coreui.modal', () => {
                modal._element.remove();
            });
            
        } catch (error) {
            console.error('Error showing staff details:', error);
            AdminUtils.showAlert('Erreur lors de l\'affichage des détails', 'error');
        }
    }

    async handleCreate(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        
        // Handle arrays
        data.specialites = formData.getAll('specialites');
        data.permissions_kitchen = formData.getAll('permissions_kitchen');
        
        try {
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Création...';
            submitBtn.disabled = true;
            
            const response = await KitchenAPI.createKitchenStaff(data);
            
            if (response && response.success) {
                AdminUtils.showAlert('Personnel créé avec succès!', 'success');
                
                // Close modal
                const modal = coreui.Modal.getInstance(document.getElementById('createStaffModal'));
                modal.hide();
                
                // Reload data
                this.loadKitchenStaff();
                
                // Reset form
                this.resetCreateForm();
                
            } else {
                throw new Error(response?.message || 'Erreur lors de la création');
            }
            
        } catch (error) {
            console.error('Error creating staff:', error);
            AdminUtils.showAlert(error.message || 'Erreur lors de la création du personnel', 'error');
        } finally {
            // Restore button
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Créer le Personnel';
            submitBtn.disabled = false;
        }
    }

    async showEditModal(staffId) {
        try {
            console.log('🔍 Opening edit modal for staff ID:', staffId);
            
            // ✨ NEW: Close any existing modals first to prevent stacking
            this.closeAllModals();
            
            // Small delay to ensure modal transition is complete
            await new Promise(resolve => setTimeout(resolve, 300));
            
            // Find staff data
            const staffMember = this.staff.find(s => s.id === staffId);
            if (!staffMember) {
                throw new Error('Personnel non trouvé');
            }
            
            // Load positions and specialties
            await this.loadPositionsAndSpecialties('#editStaffForm');
            
            // Populate form
            this.populateEditForm(staffMember, staffMember.kitchen_profile);
            
            // Show modal
            const modal = new coreui.Modal(document.getElementById('editStaffModal'));
            modal.show();
            
        } catch (error) {
            console.error('Error showing edit modal:', error);
            AdminUtils.showAlert('Erreur lors de l\'ouverture du formulaire', 'error');
        }
    }

    async handleUpdate(event) {
        event.preventDefault();
        
        const form = event.target;
        const formData = new FormData(form);
        const data = Object.fromEntries(formData.entries());
        const staffId = data.staff_id;
        
        // Handle arrays
        data.specialites = formData.getAll('specialites');
        data.permissions_kitchen = formData.getAll('permissions_kitchen');
        
        try {
            // Show loading state
            const submitBtn = form.querySelector('button[type="submit"]');
            const originalText = submitBtn.innerHTML;
            submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Mise à jour...';
            submitBtn.disabled = true;
            
            const response = await KitchenAPI.updateKitchenStaff(staffId, data);
            
            if (response && response.success) {
                AdminUtils.showAlert('Personnel mis à jour avec succès!', 'success');
                
                // Close modal
                const modal = coreui.Modal.getInstance(document.getElementById('editStaffModal'));
                modal.hide();
                
                // Reload data
                this.loadKitchenStaff();
                
            } else {
                throw new Error(response?.message || 'Erreur lors de la mise à jour');
            }
            
        } catch (error) {
            console.error('Error updating staff:', error);
            AdminUtils.showAlert(error.message || 'Erreur lors de la mise à jour du personnel', 'error');
        } finally {
            // Restore button
            const submitBtn = form.querySelector('button[type="submit"]');
            submitBtn.innerHTML = '<i class="fas fa-save"></i> Mettre à jour';
            submitBtn.disabled = false;
        }
    }

    async deleteStaff(staffId) {
        try {
            const staffMember = this.staff.find(s => s.id === staffId);
            if (!staffMember) {
                throw new Error('Personnel non trouvé');
            }
            
            const confirmed = await AdminUtils.showConfirm(
                'Supprimer ce personnel?',
                `Êtes-vous sûr de vouloir supprimer ${staffMember.prenom} ${staffMember.nom}? Cette action est irréversible.`,
                'danger'
            );
            
            if (!confirmed) return;
            
            const response = await KitchenAPI.deleteKitchenStaff(staffId);
            
            if (response && response.success) {
                AdminUtils.showAlert('Personnel supprimé avec succès!', 'success');
                this.loadKitchenStaff();
            } else {
                throw new Error(response?.message || 'Erreur lors de la suppression');
            }
            
        } catch (error) {
            console.error('Error deleting staff:', error);
            AdminUtils.showAlert(error.message || 'Erreur lors de la suppression', 'error');
        }
    }

    // ==================== HELPER METHODS ====================

    /**
     * ✨ NEW: Close all open modals to prevent stacking
     */
    closeAllModals() {
        // List of modal IDs that might be open
        const modalIds = [
            'staffDetailsModal',
            'editStaffModal',
            'createStaffModal',
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

    async loadPositionsAndSpecialties(formSelector) {
        try {
            const [positionsResponse, specialtiesResponse] = await Promise.all([
                KitchenAPI.getKitchenPositions(),
                KitchenAPI.getKitchenSpecialties()
            ]);
            
            this.populatePositionsSelect(formSelector, positionsResponse.data);
            this.populateSpecialtiesSelect(formSelector, specialtiesResponse.data);
            
        } catch (error) {
            console.error('Error loading form data:', error);
            // Use fallback data
            this.populatePositionsSelect(formSelector, this.getFallbackPositions());
            this.populateSpecialtiesSelect(formSelector, this.getFallbackSpecialties());
        }
    }

    populatePositionsSelect(formSelector, positions) {
        const select = document.querySelector(`${formSelector} select[name="poste_cuisine"]`);
        if (!select) return;
        
        // Clear existing options except first
        while (select.children.length > 1) {
            select.removeChild(select.lastChild);
        }
        
        Object.entries(positions).forEach(([key, position]) => {
            const option = document.createElement('option');
            option.value = key;
            option.textContent = position.name || position;
            if (position.description) {
                option.title = position.description;
            }
            select.appendChild(option);
        });
    }

    populateSpecialtiesSelect(formSelector, specialties) {
        const container = document.querySelector(`${formSelector} .specialties-container`);
        if (!container) return;
        
        container.innerHTML = '';
        
        Object.entries(specialties).forEach(([key, name]) => {
            const checkboxDiv = document.createElement('div');
            checkboxDiv.className = 'form-check form-check-inline';
            checkboxDiv.innerHTML = `
                <input class="form-check-input" type="checkbox" name="specialites" value="${key}" id="${formSelector.replace('#', '')}_specialty_${key}">
                <label class="form-check-label" for="${formSelector.replace('#', '')}_specialty_${key}">
                    ${name}
                </label>
            `;
            container.appendChild(checkboxDiv);
        });
    }

    updateStats(stats = null) {
        if (!stats && this.stats) {
            stats = this.stats;
        }
        
        if (!stats) return;
        
        // Update stat widgets
        const totalElement = document.getElementById('totalStaff');
        const activeElement = document.getElementById('activeStaff');
        const pauseElement = document.getElementById('pauseStaff');
        const absentElement = document.getElementById('absentStaff');
        
        if (totalElement) totalElement.textContent = stats.total || 0;
        if (activeElement) activeElement.textContent = stats.actif || 0;
        if (pauseElement) pauseElement.textContent = stats.pause || 0;
        if (absentElement) absentElement.textContent = stats.absent || 0;
    }

    resetCreateForm() {
        const form = document.getElementById('createStaffForm');
        if (form) {
            form.reset();
            // Clear checkboxes
            form.querySelectorAll('input[type="checkbox"]').forEach(cb => cb.checked = false);
        }
    }

    populateEditForm(user, profile) {
        const form = document.getElementById('editStaffForm');
        if (!form) return;
        
        // Populate basic user data
        form.querySelector('input[name="staff_id"]').value = user.id;
        form.querySelector('input[name="nom"]').value = user.nom || '';
        form.querySelector('input[name="prenom"]').value = user.prenom || '';
        form.querySelector('input[name="email"]').value = user.email || '';
        form.querySelector('input[name="telephone"]').value = user.telephone || '';
        form.querySelector('input[name="is_active"]').checked = user.is_active;
        
        // Populate kitchen profile data
        if (profile) {
            const positionSelect = form.querySelector('select[name="poste_cuisine"]');
            if (positionSelect) positionSelect.value = profile.poste_cuisine || '';
            
            const statusSelect = form.querySelector('select[name="statut_travail"]');
            if (statusSelect) statusSelect.value = profile.statut_travail || 'actif';
            
            const experienceInput = form.querySelector('input[name="experience_annees"]');
            if (experienceInput) experienceInput.value = profile.experience_annees || '';
            
            const salaireInput = form.querySelector('input[name="salaire_horaire"]');
            if (salaireInput) salaireInput.value = profile.salaire_horaire || '';
            
            const heuresInput = form.querySelector('input[name="heures_par_semaine"]');
            if (heuresInput) heuresInput.value = profile.heures_par_semaine || '';
            
            const notesTextarea = form.querySelector('textarea[name="notes_interne"]');
            if (notesTextarea) notesTextarea.value = profile.notes_interne || '';
            
            // Set specialties checkboxes
            if (profile.specialites) {
                profile.specialites.forEach(specialite => {
                    const checkbox = form.querySelector(`input[name="specialites"][value="${specialite}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            }
            
            // Set permissions checkboxes
            if (profile.permissions_kitchen) {
                profile.permissions_kitchen.forEach(permission => {
                    const checkbox = form.querySelector(`input[name="permissions_kitchen"][value="${permission}"]`);
                    if (checkbox) checkbox.checked = true;
                });
            }
        }
    }

    bindRowEvents() {
        // This will be called after rendering to bind any row-specific events
        console.log('Row events bound for kitchen staff table');
    }

    setupRealTimeUpdates() {
        // Set up real-time updates for kitchen staff status
        console.log('Real-time updates setup for kitchen staff');
    }

    getFallbackPositions() {
        return {
            'chef_executif': 'Chef Exécutif',
            'chef_cuisine': 'Chef de Cuisine',
            'sous_chef': 'Sous Chef',
            'cuisinier': 'Cuisinier',
            'commis': 'Commis de Cuisine',
            'plongeur': 'Plongeur'
        };
    }

    getFallbackSpecialties() {
        return {
            'marocain': 'Cuisine Marocaine',
            'italien': 'Cuisine Italienne',
            'international': 'Cuisine Internationale',
            'polyvalent': 'Polyvalent'
        };
    }

    getFallbackPermissions() {
        return {
            'kitchen': {
                'view_kitchen_dashboard': 'Voir tableau de bord cuisine',
                'manage_orders': 'Gérer les commandes',
                'update_order_status': 'Mettre à jour statut commandes'
            }
        };
    }
}

// Global Kitchen API class
class KitchenAPI {
    static async getKitchenStaff() {
        const response = await AdminAPI.request('GET', '/kitchen/staff');
        return response;
    }
    
    static async createKitchenStaff(data) {
        const response = await AdminAPI.request('POST', '/kitchen/create-staff', data);
        return response;
    }
    
    static async updateKitchenStaff(id, data) {
        const response = await AdminAPI.request('PUT', `/kitchen/update-staff/${id}`, data);
        return response;
    }
    
    static async deleteKitchenStaff(id) {
        const response = await AdminAPI.request('DELETE', `/kitchen/delete-staff/${id}`);
        return response;
    }
    
    static async getKitchenPositions() {
        const response = await AdminAPI.request('GET', '/kitchen/positions');
        return response;
    }
    
    static async getKitchenSpecialties() {
        const response = await AdminAPI.request('GET', '/kitchen/specialties');
        return response;
    }
    
    static async getKitchenPermissions() {
        const response = await AdminAPI.request('GET', '/kitchen/permissions');
        return response;
    }
}

// Export for global use
window.KitchenStaffManager = KitchenStaffManager;
window.KitchenAPI = KitchenAPI;
