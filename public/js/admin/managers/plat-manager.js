/**
 * JoodKitchen Plat Management System
 * Handles plat CRUD operations with advanced filtering and views
 */

class PlatManager {
    constructor() {
        this.api = new MenuAPI();
        this.currentView = 'grid';
        this.currentPage = 1;
        this.itemsPerPage = 12;
        this.selectedPlats = new Set();
        this.filters = {
            category: '',
            status: '',
            search: '',
            minPrice: '',
            maxPrice: '',
            popular: false,
            vegetarian: false
        };
        this.categories = [];
        this.plats = [];
        this.isBulkMode = false;
        
        // Modal instances
        this.platModalInstance = null;
        this.deleteModalInstance = null;

        this.initializeEventListeners();
        this.initializeModals();
        this.initializeTooltips();
        
        // Add additional event listeners after DOM is ready
        setTimeout(() => {
            this.setupAdditionalEventListeners();
        }, 100);
    }

    setupAdditionalEventListeners() {
        console.log('🔧 Setting up additional event listeners');
        
        // Ensure "Nouveau Plat" button works
        const nouveauPlatBtn = document.querySelector('button[data-coreui-target="#platModal"]');
        if (nouveauPlatBtn) {
            console.log('✅ Found Nouveau Plat button, adding click listener');
            nouveauPlatBtn.addEventListener('click', (e) => {
                console.log('🖱️ Nouveau Plat button clicked');
                e.preventDefault();
                this.showAddModal();
            });
        } else {
            console.warn('⚠️ Nouveau Plat button not found');
        }
        
        // Test if platManager is accessible globally
        if (window.platManager) {
            console.log('✅ window.platManager is accessible');
        } else {
            console.error('❌ window.platManager is NOT accessible');
        }
    }

    initializeEventListeners() {
        // View toggle buttons
        const gridBtn = document.getElementById('gridViewBtn');
        const listBtn = document.getElementById('listViewBtn');
        
        if (gridBtn) gridBtn.addEventListener('click', () => this.setViewMode('grid'));
        if (listBtn) listBtn.addEventListener('click', () => this.setViewMode('list'));

        // Filter controls
        const categoryFilter = document.getElementById('categoryFilter');
        const statusFilter = document.getElementById('statusFilter');
        const searchInput = document.getElementById('searchInput');
        const minPriceFilter = document.getElementById('minPriceFilter');
        const maxPriceFilter = document.getElementById('maxPriceFilter');
        const popularFilter = document.getElementById('popularFilter');
        const vegetarianFilter = document.getElementById('vegetarianFilter');
        const clearFilters = document.getElementById('clearFilters');
        const applyFilters = document.getElementById('applyFilters');

        if (categoryFilter) {
            categoryFilter.addEventListener('change', (e) => {
                console.log('🏷️ Category filter changed:', e.target.value);
                this.filters.category = e.target.value;
                this.applyFilters();
            });
        }

        if (statusFilter) {
            statusFilter.addEventListener('change', (e) => {
                console.log('📊 Status filter changed:', e.target.value);
                this.filters.status = e.target.value;
                this.applyFilters();
            });
        }

        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    console.log('🔍 Search filter changed:', e.target.value);
                    this.filters.search = e.target.value;
                    this.applyFilters();
                }, 300);
            });
        }

        if (minPriceFilter) {
            minPriceFilter.addEventListener('change', (e) => {
                console.log('💰 Min price filter changed:', e.target.value);
                this.filters.minPrice = e.target.value;
                this.applyFilters();
            });
        }

        if (maxPriceFilter) {
            maxPriceFilter.addEventListener('change', (e) => {
                console.log('💰 Max price filter changed:', e.target.value);
                this.filters.maxPrice = e.target.value;
                this.applyFilters();
            });
        }

        if (popularFilter) {
            popularFilter.addEventListener('change', (e) => {
                console.log('⭐ Popular filter changed:', e.target.checked);
                this.filters.popular = e.target.checked;
                this.applyFilters();
            });
        }

        if (vegetarianFilter) {
            vegetarianFilter.addEventListener('change', (e) => {
                console.log('🌱 Vegetarian filter changed:', e.target.checked);
                this.filters.vegetarian = e.target.checked;
                this.applyFilters();
            });
        }

        if (clearFilters) {
            clearFilters.addEventListener('click', () => this.clearFilters());
        }

        if (applyFilters) {
            applyFilters.addEventListener('click', () => this.applyFilters());
        }

        // Save plat button
        const saveBtn = document.getElementById('savePlatBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.savePlat());
        }

        // Delete confirmation
        const confirmDeleteBtn = document.getElementById('confirmDeleteBtn');
        if (confirmDeleteBtn) {
            confirmDeleteBtn.addEventListener('click', async () => {
                const platId = document.getElementById('deleteConfirmModal').dataset.platId;
                if (platId) {
                    // Close the modal first
                    this.hideModal('delete');
                    // Then delete the plat (this will refresh the data)
                    await this.deletePlat(platId);
                }
            });
        }

        // Bulk operations
        const bulkActivateBtn = document.getElementById('bulkActivateBtn');
        const bulkDeactivateBtn = document.getElementById('bulkDeactivateBtn');
        const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');

        if (bulkActivateBtn) {
            bulkActivateBtn.addEventListener('click', () => this.bulkActivate());
        }
        if (bulkDeactivateBtn) {
            bulkDeactivateBtn.addEventListener('click', () => this.bulkDeactivate());
        }
        if (bulkDeleteBtn) {
            bulkDeleteBtn.addEventListener('click', () => this.bulkDelete());
        }
    }

    initializeModals() {
        const platModalEl = document.getElementById('platModal');
        if (platModalEl) {
            this.platModalInstance = new coreui.Modal(platModalEl);
            
            // Add event listener for when modal is shown (Bootstrap/CoreUI uses 'show.bs.modal')
            platModalEl.addEventListener('show.bs.modal', () => {
                // Check if this is for a new plat (no editId set)
                const form = document.getElementById('platForm');
                if (!form.dataset.editId) {
                    // Reset form when modal opens for new plats
                    this.resetPlatForm();
                    const modalTitle = document.getElementById('platModalTitle');
                    if (modalTitle) {
                        modalTitle.innerHTML = '<i class="fas fa-plus"></i> Nouveau Plat';
                    }
                }
            });
        }

        const deleteModalEl = document.getElementById('deleteConfirmModal');
        if (deleteModalEl) {
            this.deleteModalInstance = new coreui.Modal(deleteModalEl);
        }
        
        // Add manual event listener for "Nouveau Plat" button as fallback
        const newPlatBtn = document.querySelector('[data-coreui-target="#platModal"]');
        if (newPlatBtn) {
            newPlatBtn.addEventListener('click', () => {
                this.showAddModal();
            });
        }
    }

    initializeTooltips() {
        const tooltipTriggerList = [].slice.call(document.querySelectorAll('[title]'));
        tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new coreui.Tooltip(tooltipTriggerEl);
        });
    }

    async loadPlats() {
        try {
            console.log('🔄 Loading plats with filters...', this.filters);
            this.showLoading();
            
            const params = {
                page: this.currentPage,
                limit: this.itemsPerPage,
                ...this.filters
            };
            
            // Clean up empty values to avoid sending empty strings to backend
            Object.keys(params).forEach(key => {
                if (params[key] === '' || params[key] === null || params[key] === undefined) {
                    delete params[key];
                }
            });
            
            console.log('📤 API request params:', params);

            const response = await this.api.getPlats(params);
            console.log('📡 Load plats response:', response);
            
            if (response.success) {
                this.plats = response.data;
                this.renderPlats(response.data);
                this.updatePagination(response.pagination);
                this.updatePlatStats(response.stats);
                this.hideLoading();
                this.initializeTooltips();
                
                // Clear any selected plats after refresh
                this.selectedPlats.clear();
                this.updateBulkButtons();
                
                console.log('✅ Plats loaded successfully, count:', response.data.length);
            } else {
                console.error('❌ Failed to load plats:', response.message);
                this.showError('Erreur lors du chargement des plats: ' + (response.message || 'Erreur inconnue'));
            }
        } catch (error) {
            console.error('💥 Load plats error:', error);
            this.showError('Erreur lors du chargement des plats: ' + error.message);
            this.hideLoading();
        }
    }

    async loadCategories() {
        try {
            const response = await this.api.getCategories();
            if (response.success) {
                this.categories = response.data;
                this.populateCategoryFilters(response.data);
            }
        } catch (error) {
            console.error('Error loading categories:', error);
        }
    }

    renderPlats(plats) {
        const container = document.getElementById('platsContainer');
        if (!container) return;

        if (plats.length === 0) {
            this.showEmptyState(container);
            return;
        }

        if (this.currentView === 'grid') {
            this.renderGridView(plats, container);
        } else {
            this.renderListView(plats, container);
        }
    }

    showEmptyState(container) {
        container.innerHTML = `
            <div class="empty-state">
                <i class="fas fa-utensils"></i>
                <h4>Aucun plat trouvé</h4>
                <p class="text-muted">Aucun plat ne correspond à vos critères de recherche.</p>
                <button class="btn btn-primary" onclick="platManager.showAddModal()">
                    <i class="fas fa-plus"></i> Ajouter un plat
                </button>
            </div>
        `;
    }

    renderGridView(plats, container) {
        const gridContainer = document.createElement('div');
        gridContainer.id = 'gridView';
        gridContainer.className = 'row g-4';
        gridContainer.innerHTML = '';

        plats.forEach(plat => {
            const platCard = this.createPlatCard(plat);
            gridContainer.appendChild(platCard);
        });

        container.innerHTML = '';
        container.appendChild(gridContainer);
    }

    renderListView(plats, container) {
        const listContainer = document.createElement('div');
        listContainer.id = 'listView';
        listContainer.className = 'table-responsive';
        
        listContainer.innerHTML = `
            <table class="table table-hover">
                <thead>
                    <tr>
                        <th width="50">
                            <input type="checkbox" class="form-check-input" id="selectAllPlats">
                        </th>
                        <th width="80">Image</th>
                        <th>Nom</th>
                        <th>Catégorie</th>
                        <th>Prix</th>
                        <th>Statut</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    ${plats.map(plat => this.createPlatRow(plat)).join('')}
                </tbody>
            </table>
        `;

        container.innerHTML = '';
        container.appendChild(listContainer);

        // Add select all functionality
        const selectAllCheckbox = document.getElementById('selectAllPlats');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                const checkboxes = listContainer.querySelectorAll('input[type="checkbox"]:not(#selectAllPlats)');
                checkboxes.forEach(checkbox => {
                    checkbox.checked = e.target.checked;
                    const platId = checkbox.value;
                    if (e.target.checked) {
                        this.selectedPlats.add(platId);
                    } else {
                        this.selectedPlats.delete(platId);
                    }
                });
                this.updateBulkButtons();
            });
        }
    }

    createPlatCard(plat) {
        const col = document.createElement('div');
        col.className = 'col-lg-4 col-md-6 col-sm-12 mb-4';
        col.dataset.platId = plat.id;
        col.dataset.categoryId = plat.category?.id || '';
        col.dataset.status = plat.disponible ? 'available' : 'unavailable';
        
        const isSelected = this.selectedPlats.has(plat.id.toString());
        if (isSelected) {
            col.classList.add('selected');
        }
        
        col.innerHTML = `
            <div class="card h-100 plat-card ${isSelected ? 'selected' : ''}" onclick="platManager.togglePlatSelection(${plat.id})">
                <div class="position-relative">
                    <img src="${plat.image || 'https://via.placeholder.com/300x200/a9b73e/ffffff?text=' + encodeURIComponent(plat.nom)}" 
                         class="card-img-top" alt="${plat.nom}" style="height: 200px; object-fit: cover;">
                    <div class="position-absolute top-0 end-0 m-2">
                        ${plat.populaire ? '<span class="badge jood-primary-bg"><i class="fas fa-star"></i> Populaire</span>' : ''}
                        ${plat.vegetarien ? '<span class="badge bg-success"><i class="fas fa-leaf"></i> Végétarien</span>' : ''}
                    </div>
                    <div class="position-absolute top-0 start-0 m-2">
                        <span class="badge ${plat.disponible ? 'bg-success' : 'bg-danger'}">
                            <span class="status-indicator status-${plat.disponible ? 'available' : 'unavailable'}"></span>
                            ${plat.disponible ? 'Disponible' : 'Indisponible'}
                        </span>
                    </div>
                    <div class="position-absolute top-50 start-50 translate-middle" style="display: ${isSelected ? 'block' : 'none'};">
                        <i class="fas fa-check-circle fa-2x text-primary"></i>
                    </div>
                </div>
                <div class="card-body d-flex flex-column">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0">${plat.nom}</h5>
                        <span class="fw-bold jood-primary fs-5">${plat.prix}€</span>
                    </div>
                    <p class="card-text text-muted small mb-3 flex-grow-1">
                        ${plat.description || 'Aucune description disponible'}
                    </p>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted">
                            ${plat.tempsPreparation ? `<i class="fas fa-clock"></i> ${plat.tempsPreparation} min` : ''}
                        </small>
                        <small class="text-muted">
                            ${plat.allergenes ? `<i class="fas fa-exclamation-triangle"></i> Allergènes` : ''}
                        </small>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="badge bg-secondary">${plat.category?.nom || 'Sans catégorie'}</span>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="event.stopPropagation(); platManager.editPlat(${plat.id})" title="Modifier">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-outline-success" onclick="event.stopPropagation(); platManager.duplicatePlat(${plat.id})" title="Dupliquer">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="event.stopPropagation(); platManager.confirmDelete(${plat.id})" title="Supprimer">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        return col;
    }

    createPlatRow(plat) {
        const isSelected = this.selectedPlats.has(plat.id.toString());
        return `
            <tr data-plat-id="${plat.id}">
                <td>
                    <input type="checkbox" class="form-check-input" value="${plat.id}" 
                           ${isSelected ? 'checked' : ''} 
                           onchange="platManager.togglePlatSelection(${plat.id})">
                </td>
                <td>
                    <img src="${plat.image || 'https://via.placeholder.com/50x50/a9b73e/ffffff?text=' + encodeURIComponent(plat.nom.charAt(0))}" 
                         class="rounded" alt="${plat.nom}" style="width: 50px; height: 50px; object-fit: cover;">
                </td>
                <td>
                    <strong>${plat.nom}</strong>
                    ${plat.populaire ? '<i class="fas fa-star text-warning ms-1" title="Populaire"></i>' : ''}
                    ${plat.vegetarien ? '<i class="fas fa-leaf text-success ms-1" title="Végétarien"></i>' : ''}
                </td>
                <td>
                    <span class="badge bg-secondary">${plat.category?.nom || 'Sans catégorie'}</span>
                </td>
                <td>
                    <span class="fw-bold jood-primary">${plat.prix}€</span>
                </td>
                <td>
                    <span class="badge ${plat.disponible ? 'bg-success' : 'bg-danger'}">
                        <span class="status-indicator status-${plat.disponible ? 'available' : 'unavailable'}"></span>
                        ${plat.disponible ? 'Disponible' : 'Indisponible'}
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="platManager.editPlat(${plat.id})" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="platManager.duplicatePlat(${plat.id})" title="Dupliquer">
                            <i class="fas fa-copy"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="platManager.confirmDelete(${plat.id})" title="Supprimer">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }

    setViewMode(view) {
        this.currentView = view;
        this.renderPlats(this.plats);
        
        // Update button states
        const gridBtn = document.getElementById('gridViewBtn');
        const listBtn = document.getElementById('listViewBtn');
        
        if (gridBtn) gridBtn.classList.toggle('active', view === 'grid');
        if (listBtn) listBtn.classList.toggle('active', view === 'list');
    }

    populateCategoryFilters(categories) {
        const categoryFilter = document.getElementById('categoryFilter');
        const modalCategorySelect = document.querySelector('#platModal select[name="categoryId"]');
        
        if (categoryFilter) {
            categoryFilter.innerHTML = '<option value="">Toutes les catégories</option>';
            categories.forEach(category => {
                categoryFilter.innerHTML += `<option value="${category.id}">${category.nom}</option>`;
            });
        }
        
        if (modalCategorySelect) {
            modalCategorySelect.innerHTML = '<option value="">Sélectionner une catégorie...</option>';
            categories.forEach(category => {
                modalCategorySelect.innerHTML += `<option value="${category.id}">${category.nom}</option>`;
            });
        }
    }

    showAddModal() {
        console.log('📝 showAddModal() called');
        const modal = document.getElementById('platModal');
        const modalTitle = document.getElementById('platModalTitle');
        const form = document.getElementById('platForm');
        
        modalTitle.innerHTML = '<i class="fas fa-plus"></i> Nouveau Plat';
        form.reset();
        form.dataset.editId = '';
        
        // Ensure categories are populated in the modal
        if (this.categories) {
            this.populateCategoryFilters(this.categories);
        }
        
        if (this.platModalInstance) {
            console.log('🔄 Opening modal via platModalInstance.show()');
            this.platModalInstance.show();
        } else {
            console.error('❌ platModalInstance not found');
        }
    }

    async editPlat(id) {
        try {
            console.log('📝 Editing plat with ID:', id);
            const response = await this.api.getPlat(id);
            console.log('📡 Get plat response for edit:', response);
            
            if (response.success) {
                this.showEditModal(response.data);
            } else {
                this.showError('Plat non trouvé: ' + (response.message || 'Le plat a peut-être été supprimé'));
                // Refresh the data to sync with server
                this.loadPlats();
            }
        } catch (error) {
            console.error('💥 Edit plat error:', error);
            this.showError('Erreur lors du chargement du plat: ' + error.message);
            // Refresh the data to sync with server
            this.loadPlats();
        }
    }

    showEditModal(plat) {
        const modal = document.getElementById('platModal');
        const modalTitle = document.getElementById('platModalTitle');
        const form = document.getElementById('platForm');
        
        modalTitle.innerHTML = '<i class="fas fa-edit"></i> Modifier le Plat';
        this.fillPlatForm(plat);
        form.dataset.editId = plat.id;
        
        if (this.platModalInstance) {
            this.platModalInstance.show();
        }
    }

    async savePlat() {
        try {
            const form = document.getElementById('platForm');
            const platData = {};
            
            // Collect form data manually to ensure we get all values
            platData.nom = form.querySelector('[name="nom"]')?.value || '';
            platData.prix = form.querySelector('[name="prix"]')?.value || '';
            platData.description = form.querySelector('[name="description"]')?.value || '';
            platData.categoryId = form.querySelector('[name="categoryId"]')?.value || '';
            platData.tempsPreparation = form.querySelector('[name="tempsPreparation"]')?.value || '';
            platData.allergenes = form.querySelector('[name="allergenes"]')?.value || '';
            platData.ingredients = form.querySelector('[name="ingredients"]')?.value || '';
            
            // Convert checkboxes to boolean (explicitly check form elements)
            platData.disponible = document.getElementById('platAvailable')?.checked || false;
            platData.populaire = document.getElementById('platPopular')?.checked || false;
            platData.vegetarien = document.getElementById('platVegetarian')?.checked || false;
            
            console.log('📝 Form data collected:', platData);
            
            // Validate required fields
            if (!platData.nom.trim()) {
                this.showError('Le nom du plat est obligatoire');
                return;
            }
            if (!platData.prix || parseFloat(platData.prix) <= 0) {
                this.showError('Le prix doit être supérieur à 0');
                return;
            }
            
            const imageFile = document.getElementById('platImageInput').files[0];
            const isEditing = form.dataset.editId;
            
            let platId = isEditing;
            let response;

            if (isEditing) {
                // Update plat data first
                response = await this.api.updatePlat(isEditing, platData);
            } else {
                // Create plat first, then we can upload image
                response = await this.api.createPlat(platData);
                if (response.success && response.data.id) {
                    platId = response.data.id;
                }
            }

            if (!response.success) {
                this.showError('Erreur lors de la sauvegarde des données du plat.');
                return;
            }

            // If there's an image, upload it now
            if (imageFile && platId) {
                // Direct upload to the correct endpoint
                const formData = new FormData();
                formData.append('image', imageFile);
                const responseImg = await fetch(`/api/admin/plats/${platId}/image`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${localStorage.getItem('admin_token')}`
                    },
                    body: formData
                });
                const imageResponse = await responseImg.json();
                if (!imageResponse.success) {
                    this.showError('Données sauvegardées, mais l\'upload de l\'image a échoué.');
                    return;
                }
            }
            
            this.showSuccess(isEditing ? 'Plat modifié avec succès!' : 'Plat créé avec succès!');
            
            // Force close modal with multiple approaches
            this.forceCloseModal();
            
            // Refresh data after a small delay to ensure modal closes first
            setTimeout(() => {
                this.loadPlats();
            }, 100);

        } catch (error) {
            this.showError('Erreur lors de la sauvegarde: ' + error.message);
        }
    }

    async deletePlat(id) {
        try {
            console.log('🗑️ Deleting plat with ID:', id);
            const response = await this.api.deletePlat(id);
            console.log('📡 Delete response:', response);
            
            if (response.success) {
                this.showSuccess('Plat supprimé avec succès!');
                this.selectedPlats.delete(id.toString());
                
                console.log('🔄 Refreshing data after successful deletion...');
                // Refresh data immediately
                await this.loadPlats();
                console.log('✅ Data refresh completed after deletion');
            } else {
                this.showError('Erreur lors de la suppression: ' + (response.message || 'Erreur inconnue'));
            }
        } catch (error) {
            console.error('💥 Delete error:', error);
            this.showError('Erreur lors de la suppression: ' + error.message);
        }
    }

    confirmDelete(id) {
        const modal = document.getElementById('deleteConfirmModal');
        modal.dataset.platId = id;
        if (this.deleteModalInstance) {
            this.deleteModalInstance.show();
        }
    }

    async duplicatePlat(id) {
        console.log('🔄 Duplicating plat with ID:', id);
        try {
            const response = await this.api.getPlat(id);
            console.log('📡 Get plat response:', response);
            if (response.success) {
                const plat = response.data;
                // Modify the name to indicate it's a copy
                plat.nom = `${plat.nom} (Copie)`;
                // Remove ID so it will be treated as a new plat
                delete plat.id;
                
                console.log('📝 Opening create modal with duplicated plat data:', plat);
                
                // Open the add modal and pre-fill with duplicated data
                this.showAddModal();
                this.fillPlatForm(plat);
                
                // Change modal title to indicate duplication
                const modalTitle = document.getElementById('platModalTitle');
                if (modalTitle) {
                    modalTitle.innerHTML = '<i class="fas fa-copy"></i> Dupliquer le Plat';
                }
            } else {
                this.showError('Erreur lors du chargement du plat à dupliquer: ' + (response.message || 'Erreur inconnue'));
            }
        } catch (error) {
            console.error('💥 Duplicate error:', error);
            this.showError('Erreur lors de la duplication: ' + error.message);
        }
    }

    // Advanced filtering methods
    applyFilters() {
        console.log('🎯 Applying filters:', this.filters);
        this.currentPage = 1;
        this.updateApplyButtonState();
        this.loadPlats();
    }

    clearFilters() {
        console.log('🧹 Clearing all filters');
        
        this.filters = {
            category: '',
            status: '',
            search: '',
            minPrice: '',
            maxPrice: '',
            popular: false,
            vegetarian: false
        };
        
        // Reset form inputs
        const filterInputs = [
            { id: 'categoryFilter', type: 'select' },
            { id: 'statusFilter', type: 'select' },
            { id: 'searchInput', type: 'text' },
            { id: 'minPriceFilter', type: 'number' },
            { id: 'maxPriceFilter', type: 'number' },
            { id: 'popularFilter', type: 'checkbox' },
            { id: 'vegetarianFilter', type: 'checkbox' }
        ];
        
        filterInputs.forEach(filterInput => {
            const input = document.getElementById(filterInput.id);
            if (input) {
                if (filterInput.type === 'checkbox') {
                    input.checked = false;
                } else {
                    input.value = '';
                }
                console.log(`✅ Reset filter: ${filterInput.id}`);
            } else {
                console.warn(`⚠️ Filter input not found: ${filterInput.id}`);
            }
        });
        
        this.currentPage = 1;
        this.updateApplyButtonState();
        this.loadPlats();
        
        console.log('✨ Filters cleared successfully');
    }

    updateApplyButtonState() {
        const hasActiveFilters = Object.entries(this.filters).some(([key, value]) => {
            if (typeof value === 'boolean') {
                return value === true;
            }
            return value !== '';
        });
        
        const applyBtn = document.getElementById('applyFilters');
        const clearBtn = document.getElementById('clearFilters');
        
        if (applyBtn) {
            if (hasActiveFilters) {
                applyBtn.classList.add('btn-warning');
                applyBtn.classList.remove('btn-primary');
                applyBtn.innerHTML = '<i class="fas fa-filter"></i> Filtres Actifs';
            } else {
                applyBtn.classList.add('btn-primary');
                applyBtn.classList.remove('btn-warning');
                applyBtn.innerHTML = '<i class="fas fa-search"></i> Appliquer';
            }
        }
        
        if (clearBtn) {
            clearBtn.style.display = hasActiveFilters ? 'inline-block' : 'none';
        }
        
        console.log('🎛️ Filter button state updated, hasActiveFilters:', hasActiveFilters);
    }

    // Bulk operations
    togglePlatSelection(platId) {
        const platIdStr = platId.toString();
        if (this.selectedPlats.has(platIdStr)) {
            this.selectedPlats.delete(platIdStr);
        } else {
            this.selectedPlats.add(platIdStr);
        }
        
        this.updateBulkButtons();
        this.updatePlatCardSelection(platId);
    }

    updatePlatCardSelection(platId) {
        const platElement = document.querySelector(`[data-plat-id="${platId}"]`);
        if (platElement) {
            const isSelected = this.selectedPlats.has(platId.toString());
            platElement.classList.toggle('selected', isSelected);
            
            const checkIcon = platElement.querySelector('.position-absolute.top-50.start-50');
            if (checkIcon) {
                checkIcon.style.display = isSelected ? 'block' : 'none';
            }
        }
    }

    updateBulkButtons() {
        const bulkButtons = ['bulkActivateBtn', 'bulkDeactivateBtn', 'bulkDeleteBtn'];
        const hasSelection = this.selectedPlats.size > 0;
        
        bulkButtons.forEach(btnId => {
            const btn = document.getElementById(btnId);
            if (btn) {
                btn.style.display = hasSelection ? 'inline-block' : 'none';
            }
        });
    }

    async bulkActivate() {
        if (this.selectedPlats.size === 0) return;
        
        try {
            const promises = Array.from(this.selectedPlats).map(id => 
                this.api.updatePlat(id, { disponible: true })
            );
            
            await Promise.all(promises);
            this.showSuccess(`${this.selectedPlats.size} plat(s) activé(s) avec succès!`);
            this.selectedPlats.clear();
            this.updateBulkButtons();
            this.loadPlats();
        } catch (error) {
            this.showError('Erreur lors de l\'activation en masse: ' + error.message);
        }
    }

    async bulkDeactivate() {
        if (this.selectedPlats.size === 0) return;
        
        try {
            const promises = Array.from(this.selectedPlats).map(id => 
                this.api.updatePlat(id, { disponible: false })
            );
            
            await Promise.all(promises);
            this.showSuccess(`${this.selectedPlats.size} plat(s) désactivé(s) avec succès!`);
            this.selectedPlats.clear();
            this.updateBulkButtons();
            this.loadPlats();
        } catch (error) {
            this.showError('Erreur lors de la désactivation en masse: ' + error.message);
        }
    }

    async bulkDelete() {
        if (this.selectedPlats.size === 0) return;
        
        if (!confirm(`Êtes-vous sûr de vouloir supprimer ${this.selectedPlats.size} plat(s) ?`)) {
            return;
        }
        
        try {
            const promises = Array.from(this.selectedPlats).map(id => 
                this.api.deletePlat(id)
            );
            
            await Promise.all(promises);
            this.showSuccess(`${this.selectedPlats.size} plat(s) supprimé(s) avec succès!`);
            this.selectedPlats.clear();
            this.updateBulkButtons();
            this.loadPlats();
        } catch (error) {
            this.showError('Erreur lors de la suppression en masse: ' + error.message);
        }
    }

    // Utility methods
    resetPlatForm() {
        const form = document.getElementById('platForm');
        if (form) {
            form.reset();
            form.dataset.editId = '';
            
            // Clear image preview
            const imagePreview = document.getElementById('imagePreview');
            if (imagePreview) {
                imagePreview.style.display = 'none';
                const img = imagePreview.querySelector('img');
                if (img) {
                    img.src = '';
                }
            }
            
            // Reset file input
            const imageInput = document.getElementById('platImageInput');
            if (imageInput) {
                imageInput.value = '';
            }
        }
    }

    fillPlatForm(plat) {
        const form = document.getElementById('platForm');
        if (!form) return;

        const fields = ['nom', 'prix', 'description', 'tempsPreparation', 'allergenes', 'ingredients'];
        fields.forEach(field => {
            const input = form.querySelector(`[name="${field}"]`);
            if (input) {
                input.value = plat[field] || '';
            }
        });

        // Handle category
        const categorySelect = form.querySelector('[name="categoryId"]');
        if (categorySelect) {
            categorySelect.value = plat.category?.id || '';
        }

        // Handle checkboxes
        const checkboxes = ['disponible', 'populaire', 'vegetarien'];
        checkboxes.forEach(field => {
            const checkbox = form.querySelector(`[name="${field}"]`);
            if (checkbox) {
                checkbox.checked = plat[field] || false;
            }
        });
    }

    updatePlatStats(stats) {
        if (!stats) return;
        document.querySelector('.stat-total').textContent = stats.total || 0;
        document.querySelector('.stat-available').textContent = stats.available || 0;
        document.querySelector('.stat-unavailable').textContent = stats.unavailable || 0;
        document.querySelector('.stat-average-price').textContent = (stats.averagePrice || 0).toFixed(2) + '€';
    }

    updatePagination(pagination) {
        const container = document.getElementById('paginationContainer');
        const info = document.getElementById('paginationInfo');
        const paginationEl = document.getElementById('pagination');
        
        if (!container || !info || !paginationEl) return;
        
        if (pagination && pagination.totalPages > 1) {
            const start = (pagination.currentPage - 1) * pagination.limit + 1;
            const end = Math.min(start + pagination.limit - 1, pagination.totalItems);
            
            info.textContent = `${start}-${end} sur ${pagination.totalItems}`;
            
            let paginationHTML = '';
            
            // Previous button
            paginationHTML += `
                <li class="page-item ${pagination.currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="platManager.goToPage(${pagination.currentPage - 1})">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                </li>
            `;
            
            // Page numbers
            for (let i = 1; i <= pagination.totalPages; i++) {
                if (i === 1 || i === pagination.totalPages || (i >= pagination.currentPage - 2 && i <= pagination.currentPage + 2)) {
                    paginationHTML += `
                        <li class="page-item ${i === pagination.currentPage ? 'active' : ''}">
                            <a class="page-link" href="#" onclick="platManager.goToPage(${i})">${i}</a>
                        </li>
                    `;
                } else if (i === pagination.currentPage - 3 || i === pagination.currentPage + 3) {
                    paginationHTML += '<li class="page-item disabled"><span class="page-link">...</span></li>';
                }
            }
            
            // Next button
            paginationHTML += `
                <li class="page-item ${pagination.currentPage === pagination.totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" onclick="platManager.goToPage(${pagination.currentPage + 1})">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </li>
            `;
            
            paginationEl.innerHTML = paginationHTML;
            container.style.display = 'flex';
        } else {
            container.style.display = 'none';
        }
    }

    goToPage(page) {
        this.currentPage = page;
        this.loadPlats();
    }

    showLoading() {
        const container = document.getElementById('platsContainer');
        if (container) {
            container.innerHTML = `
                <div class="text-center p-4">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-2 text-muted">Chargement des plats...</p>
                </div>
            `;
        }
    }

    hideLoading() {
        // Loading is handled by renderPlats
    }

    showError(message) {
        console.error(message);
        // Simple alert fallback since CoreUI Toast API may not be available
        alert('Erreur: ' + message);
    }

    showSuccess(message) {
        console.log(message);
        // Simple alert fallback since CoreUI Toast API may not be available
        alert('Succès: ' + message);
    }

    forceCloseModal() {
        console.log('🔐 Force closing modal...');
        
        // Method 1: Try CoreUI Modal instance
        if (this.platModalInstance) {
            try {
                console.log('🔄 Attempting CoreUI modal.hide()');
                this.platModalInstance.hide();
            } catch (error) {
                console.warn('⚠️ CoreUI modal.hide() failed:', error);
            }
        }
        
        // Method 2: Try Bootstrap Modal (fallback)
        const modalEl = document.getElementById('platModal');
        if (modalEl && window.bootstrap) {
            try {
                console.log('🔄 Attempting Bootstrap modal.hide()');
                const bsModal = window.bootstrap.Modal.getInstance(modalEl);
                if (bsModal) {
                    bsModal.hide();
                }
            } catch (error) {
                console.warn('⚠️ Bootstrap modal.hide() failed:', error);
            }
        }
        
        // Method 3: Direct DOM manipulation (force close)
        setTimeout(() => {
            const modal = document.getElementById('platModal');
            if (modal) {
                console.log('🔄 Force closing with DOM manipulation');
                modal.style.display = 'none';
                modal.classList.remove('show');
                modal.setAttribute('aria-hidden', 'true');
                modal.removeAttribute('aria-modal');
                modal.removeAttribute('role');
                
                // Remove backdrop
                document.querySelectorAll('.modal-backdrop').forEach(backdrop => {
                    backdrop.remove();
                });
                
                // Remove modal-open class from body
                document.body.classList.remove('modal-open');
                document.body.style.overflow = '';
                document.body.style.paddingRight = '';
            }
        }, 50);
    }

    hideModal(type = 'plat') {
        if (type === 'plat') {
            this.forceCloseModal();
        } else if (type === 'delete' && this.deleteModalInstance) {
            this.deleteModalInstance.hide();
        }
    }
}