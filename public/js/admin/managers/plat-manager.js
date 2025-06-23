/**
 * JoodKitchen Plat Management System
 * Handles plat CRUD operations with advanced filtering and views
 */

class PlatManager {
    constructor() {
        this.api = new MenuAPI();
        this.currentView = 'grid';
        this.currentPage = 1;
        this.filters = {
            category: '',
            status: '',
            search: ''
        };
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        // View toggle buttons
        const gridBtn = document.getElementById('gridViewBtn');
        const listBtn = document.getElementById('listViewBtn');
        
        if (gridBtn) gridBtn.addEventListener('click', () => this.setView('grid'));
        if (listBtn) listBtn.addEventListener('click', () => this.setView('list'));

        // Create plat button
        const createBtn = document.querySelector('[data-bs-target="#addPlatModal"]');
        if (createBtn) {
            createBtn.addEventListener('click', () => this.showCreateModal());
        }

        // Filter controls
        const categoryFilter = document.getElementById('categoryFilter');
        const statusFilter = document.getElementById('statusFilter');
        const searchInput = document.getElementById('searchInput');
        const clearFilters = document.getElementById('clearFilters');

        if (categoryFilter) {
            categoryFilter.addEventListener('change', (e) => {
                this.filters.category = e.target.value;
                this.loadPlats();
            });
        }

        if (statusFilter) {
            statusFilter.addEventListener('change', (e) => {
                this.filters.status = e.target.value;
                this.loadPlats();
            });
        }

        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.filters.search = e.target.value;
                    this.loadPlats();
                }, 500);
            });
        }

        if (clearFilters) {
            clearFilters.addEventListener('click', () => this.clearAllFilters());
        }

        // Save plat button
        const saveBtn = document.getElementById('savePlatBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.savePlat());
        }
    }

    async loadPlats() {
        try {
            const params = {
                page: this.currentPage,
                limit: 20,
                ...this.filters
            };

            const response = await this.api.getPlats(params);
            if (response.success) {
                this.renderPlats(response.data);
                this.updatePagination(response.pagination);
                this.updatePlatStats(response.data);
            }
        } catch (error) {
            this.showError('Error loading plats: ' + error.message);
        }
    }

    async loadCategories() {
        try {
            const response = await this.api.getCategories();
            if (response.success) {
                this.populateCategoryFilters(response.data);
            }
        } catch (error) {
            console.error('Error loading categories:', error);
        }
    }

    renderPlats(plats) {
        const container = document.getElementById('platsContainer');
        if (!container) return;

        if (this.currentView === 'grid') {
            this.renderGridView(plats, container);
        } else {
            this.renderListView(plats, container);
        }
    }

    renderGridView(plats, container) {
        const gridContainer = document.getElementById('gridView') || document.createElement('div');
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
                        <th>Image</th>
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
    }

    createPlatCard(plat) {
        const col = document.createElement('div');
        col.className = 'col-lg-4 col-md-6 plat-card';
        col.dataset.categoryId = plat.category?.id || '';
        col.dataset.status = plat.disponible ? 'available' : 'unavailable';
        
        col.innerHTML = `
            <div class="card h-100">
                <div class="position-relative">
                    <img src="${plat.image || 'https://via.placeholder.com/300x200/a9b73e/ffffff?text=' + encodeURIComponent(plat.nom)}" 
                         class="card-img-top" alt="${plat.nom}" style="height: 200px; object-fit: cover;">
                    <div class="position-absolute top-0 end-0 m-2">
                        ${plat.popular ? '<span class="badge jood-primary-bg">Populaire</span>' : ''}
                    </div>
                    <div class="position-absolute top-0 start-0 m-2">
                        <span class="badge ${plat.disponible ? 'bg-success' : 'bg-danger'}">
                            ${plat.disponible ? 'Disponible' : 'Indisponible'}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0">${plat.nom}</h5>
                        <span class="fw-bold jood-primary fs-5">${plat.prix}€</span>
                    </div>
                    <p class="card-text text-muted small mb-3">
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
                            <button class="btn btn-outline-primary" onclick="platManager.editPlat(${plat.id})" title="Modifier">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-outline-success" onclick="platManager.duplicatePlat(${plat.id})" title="Dupliquer">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="platManager.deletePlat(${plat.id})" title="Supprimer">
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
        return `
            <tr>
                <td>
                    <img src="${plat.image || 'https://via.placeholder.com/50x50/a9b73e/ffffff'}"
                         alt="${plat.nom}" style="width: 50px; height: 50px; object-fit: cover;" class="rounded">
                </td>
                <td>
                    <strong>${plat.nom}</strong>
                    <br><small class="text-muted">${plat.description ? plat.description.substring(0, 50) + '...' : ''}</small>
                </td>
                <td>
                    <span class="badge bg-secondary">${plat.category?.nom || 'Sans catégorie'}</span>
                </td>
                <td>
                    <span class="fw-bold jood-primary">${plat.prix}€</span>
                </td>
                <td>
                    <span class="badge ${plat.disponible ? 'bg-success' : 'bg-danger'}">
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
                        <button class="btn btn-outline-danger" onclick="platManager.deletePlat(${plat.id})" title="Supprimer">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }

    setView(view) {
        this.currentView = view;
        
        // Update button states
        const gridBtn = document.getElementById('gridViewBtn');
        const listBtn = document.getElementById('listViewBtn');
        
        if (gridBtn) gridBtn.classList.toggle('active', view === 'grid');
        if (listBtn) listBtn.classList.toggle('active', view === 'list');
        
        // Reload plats to render in new view
        this.loadPlats();
    }

    populateCategoryFilters(categories) {
        const categoryFilter = document.getElementById('categoryFilter');
        const categorySelect = document.querySelector('[name="categoryId"]');
        
        if (categoryFilter) {
            categoryFilter.innerHTML = '<option value="">Toutes les catégories</option>';
            categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.id;
                option.textContent = category.nom;
                categoryFilter.appendChild(option);
            });
        }
        
        if (categorySelect) {
            categorySelect.innerHTML = '<option value="">Sélectionner...</option>';
            categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.id;
                option.textContent = category.nom;
                categorySelect.appendChild(option);
            });
        }
    }

    showCreateModal() {
        this.resetPlatForm();
        const modal = document.getElementById('addPlatModal');
        if (modal) {
            const modalInstance = new coreui.Modal(modal);
            modalInstance.show();
        }
    }

    async editPlat(id) {
        try {
            const response = await this.api.getPlat(id);
            if (response.success) {
                this.fillPlatForm(response.data);
                this.showEditModal(id);
            }
        } catch (error) {
            this.showError('Error loading plat: ' + error.message);
        }
    }

    async savePlat() {
        const form = document.getElementById('platForm');
        const formData = new FormData(form);
        
        const data = {
            nom: formData.get('nom'),
            description: formData.get('description'),
            prix: formData.get('prix'),
            categoryId: formData.get('categoryId') || null,
            image: formData.get('image'),
            disponible: formData.has('disponible'),
            allergenes: formData.get('allergenes'),
            tempsPreparation: formData.get('tempsPreparation') ? parseInt(formData.get('tempsPreparation')) : null
        };

        try {
            const platId = form.dataset.platId;
            let response;

            if (platId) {
                response = await this.api.updatePlat(platId, data);
            } else {
                response = await this.api.createPlat(data);
            }

            if (response.success) {
                this.hideModal();
                this.loadPlats();
                this.showSuccess(response.message);
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            this.showError('Error saving plat: ' + error.message);
        }
    }

    async deletePlat(id) {
        if (!confirm('Êtes-vous sûr de vouloir supprimer ce plat ?')) {
            return;
        }

        try {
            const response = await this.api.deletePlat(id);
            if (response.success) {
                this.loadPlats();
                this.showSuccess(response.message);
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            this.showError('Error deleting plat: ' + error.message);
        }
    }

    async duplicatePlat(id) {
        try {
            const response = await this.api.getPlat(id);
            if (response.success) {
                const plat = response.data;
                plat.nom = plat.nom + ' (Copie)';
                delete plat.id;
                
                const createResponse = await this.api.createPlat(plat);
                if (createResponse.success) {
                    this.loadPlats();
                    this.showSuccess('Plat dupliqué avec succès');
                }
            }
        } catch (error) {
            this.showError('Error duplicating plat: ' + error.message);
        }
    }

    clearAllFilters() {
        this.filters = {
            category: '',
            status: '',
            search: ''
        };

        // Reset form controls
        const categoryFilter = document.getElementById('categoryFilter');
        const statusFilter = document.getElementById('statusFilter');
        const searchInput = document.getElementById('searchInput');

        if (categoryFilter) categoryFilter.value = '';
        if (statusFilter) statusFilter.value = '';
        if (searchInput) searchInput.value = '';

        this.loadPlats();
    }

    resetPlatForm() {
        const form = document.getElementById('platForm');
        if (form) {
            form.reset();
            delete form.dataset.platId;
        }
    }

    fillPlatForm(plat) {
        const form = document.getElementById('platForm');
        if (!form) return;

        form.dataset.platId = plat.id;
        
        const fields = ['nom', 'description', 'prix', 'image', 'allergenes', 'tempsPreparation'];
        fields.forEach(field => {
            const input = form.querySelector(`[name="${field}"]`);
            if (input && plat[field] !== undefined) {
                input.value = plat[field];
            }
        });

        const categorySelect = form.querySelector('[name="categoryId"]');
        if (categorySelect && plat.category) {
            categorySelect.value = plat.category.id;
        }

        const disponibleCheckbox = form.querySelector('[name="disponible"]');
        if (disponibleCheckbox) {
            disponibleCheckbox.checked = plat.disponible;
        }
    }

    updatePlatStats(plats) {
        // Update stats widgets if they exist
        const totalWidget = document.querySelector('.widget-value');
        if (totalWidget) {
            totalWidget.textContent = plats.length;
        }
    }

    updatePagination(pagination) {
        // Implement pagination controls
        console.log('Pagination:', pagination);
    }

    showError(message) {
        console.error(message);
        // Implement toast notification
    }

    showSuccess(message) {
        console.log(message);
        // Implement toast notification
    }

    hideModal() {
        const modal = document.getElementById('addPlatModal');
        if (modal) {
            const modalInstance = coreui.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance.hide();
            }
        }
    }
} 