/**
 * JoodKitchen Dish Management System
 * Handles dish CRUD operations with advanced filtering and views
 */

class DishManager {
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

        // Create dish button
        const createBtn = document.querySelector('[data-bs-target="#addDishModal"]');
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
                this.loadDishes();
            });
        }

        if (statusFilter) {
            statusFilter.addEventListener('change', (e) => {
                this.filters.status = e.target.value;
                this.loadDishes();
            });
        }

        if (searchInput) {
            let searchTimeout;
            searchInput.addEventListener('input', (e) => {
                clearTimeout(searchTimeout);
                searchTimeout = setTimeout(() => {
                    this.filters.search = e.target.value;
                    this.loadDishes();
                }, 500);
            });
        }

        if (clearFilters) {
            clearFilters.addEventListener('click', () => this.clearAllFilters());
        }

        // Save dish button
        const saveBtn = document.getElementById('saveDishBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveDish());
        }
    }

    async loadDishes() {
        try {
            const params = {
                page: this.currentPage,
                limit: 20,
                ...this.filters
            };

            const response = await this.api.getDishes(params);
            if (response.success) {
                this.renderDishes(response.data);
                this.updatePagination(response.pagination);
                this.updateDishStats(response.data);
            }
        } catch (error) {
            this.showError('Error loading dishes: ' + error.message);
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

    renderDishes(dishes) {
        const container = document.getElementById('dishesContainer');
        if (!container) return;

        if (this.currentView === 'grid') {
            this.renderGridView(dishes, container);
        } else {
            this.renderListView(dishes, container);
        }
    }

    renderGridView(dishes, container) {
        const gridContainer = document.getElementById('gridView') || document.createElement('div');
        gridContainer.id = 'gridView';
        gridContainer.className = 'row g-4';
        gridContainer.innerHTML = '';

        dishes.forEach(dish => {
            const dishCard = this.createDishCard(dish);
            gridContainer.appendChild(dishCard);
        });

        container.innerHTML = '';
        container.appendChild(gridContainer);
    }

    renderListView(dishes, container) {
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
                    ${dishes.map(dish => this.createDishRow(dish)).join('')}
                </tbody>
            </table>
        `;

        container.innerHTML = '';
        container.appendChild(listContainer);
    }

    createDishCard(dish) {
        const col = document.createElement('div');
        col.className = 'col-lg-4 col-md-6 dish-card';
        col.dataset.categoryId = dish.category?.id || '';
        col.dataset.status = dish.disponible ? 'available' : 'unavailable';
        
        col.innerHTML = `
            <div class="card h-100">
                <div class="position-relative">
                    <img src="${dish.image || 'https://via.placeholder.com/300x200/a9b73e/ffffff?text=' + encodeURIComponent(dish.nom)}" 
                         class="card-img-top" alt="${dish.nom}" style="height: 200px; object-fit: cover;">
                    <div class="position-absolute top-0 end-0 m-2">
                        ${dish.popular ? '<span class="badge jood-primary-bg">Populaire</span>' : ''}
                    </div>
                    <div class="position-absolute top-0 start-0 m-2">
                        <span class="badge ${dish.disponible ? 'bg-success' : 'bg-danger'}">
                            ${dish.disponible ? 'Disponible' : 'Indisponible'}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0">${dish.nom}</h5>
                        <span class="fw-bold jood-primary fs-5">${dish.prix}€</span>
                    </div>
                    <p class="card-text text-muted small mb-3">
                        ${dish.description || 'Aucune description disponible'}
                    </p>
                    <div class="d-flex justify-content-between align-items-center mb-2">
                        <small class="text-muted">
                            ${dish.tempsPreparation ? `<i class="fas fa-clock"></i> ${dish.tempsPreparation} min` : ''}
                        </small>
                        <small class="text-muted">
                            ${dish.allergenes ? `<i class="fas fa-exclamation-triangle"></i> Allergènes` : ''}
                        </small>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <span class="badge bg-secondary">${dish.category?.nom || 'Sans catégorie'}</span>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="dishManager.editDish(${dish.id})" title="Modifier">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-outline-success" onclick="dishManager.duplicateDish(${dish.id})" title="Dupliquer">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="dishManager.deleteDish(${dish.id})" title="Supprimer">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        return col;
    }

    createDishRow(dish) {
        return `
            <tr>
                <td>
                    <img src="${dish.image || 'https://via.placeholder.com/50x50/a9b73e/ffffff'}" 
                         alt="${dish.nom}" style="width: 50px; height: 50px; object-fit: cover;" class="rounded">
                </td>
                <td>
                    <strong>${dish.nom}</strong>
                    <br><small class="text-muted">${dish.description ? dish.description.substring(0, 50) + '...' : ''}</small>
                </td>
                <td>
                    <span class="badge bg-secondary">${dish.category?.nom || 'Sans catégorie'}</span>
                </td>
                <td>
                    <span class="fw-bold jood-primary">${dish.prix}€</span>
                </td>
                <td>
                    <span class="badge ${dish.disponible ? 'bg-success' : 'bg-danger'}">
                        ${dish.disponible ? 'Disponible' : 'Indisponible'}
                    </span>
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="dishManager.editDish(${dish.id})" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="dishManager.duplicateDish(${dish.id})" title="Dupliquer">
                            <i class="fas fa-copy"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="dishManager.deleteDish(${dish.id})" title="Supprimer">
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
        
        if (gridBtn && listBtn) {
            gridBtn.classList.toggle('active', view === 'grid');
            listBtn.classList.toggle('active', view === 'list');
        }
        
        this.loadDishes();
    }

    populateCategoryFilters(categories) {
        const categoryFilter = document.getElementById('categoryFilter');
        if (!categoryFilter) return;

        // Clear existing options except first one
        while (categoryFilter.children.length > 1) {
            categoryFilter.removeChild(categoryFilter.lastChild);
        }

        categories.forEach(category => {
            const option = document.createElement('option');
            option.value = category.id;
            option.textContent = category.nom;
            categoryFilter.appendChild(option);

            // Add subcategories
            if (category.sousCategories) {
                category.sousCategories.forEach(subCategory => {
                    const subOption = document.createElement('option');
                    subOption.value = subCategory.id;
                    subOption.textContent = `-- ${subCategory.nom}`;
                    categoryFilter.appendChild(subOption);
                });
            }
        });
    }

    showCreateModal() {
        this.resetDishForm();
        const modal = document.getElementById('addDishModal');
        if (modal) {
            const modalInstance = new coreui.Modal(modal);
            modalInstance.show();
        }
    }

    async editDish(id) {
        try {
            const response = await this.api.getDish(id);
            if (response.success) {
                this.fillDishForm(response.data);
                this.showEditModal(id);
            }
        } catch (error) {
            this.showError('Error loading dish: ' + error.message);
        }
    }

    async saveDish() {
        const form = document.getElementById('dishForm');
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
            const dishId = form.dataset.dishId;
            let response;

            if (dishId) {
                response = await this.api.updateDish(dishId, data);
            } else {
                response = await this.api.createDish(data);
            }

            if (response.success) {
                this.hideModal();
                this.loadDishes();
                this.showSuccess(response.message);
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            this.showError('Error saving dish: ' + error.message);
        }
    }

    async deleteDish(id) {
        if (!confirm('Êtes-vous sûr de vouloir supprimer ce plat ?')) {
            return;
        }

        try {
            const response = await this.api.deleteDish(id);
            if (response.success) {
                this.loadDishes();
                this.showSuccess(response.message);
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            this.showError('Error deleting dish: ' + error.message);
        }
    }

    async duplicateDish(id) {
        try {
            const response = await this.api.getDish(id);
            if (response.success) {
                const dish = response.data;
                dish.nom = dish.nom + ' (Copie)';
                delete dish.id;
                
                const createResponse = await this.api.createDish(dish);
                if (createResponse.success) {
                    this.loadDishes();
                    this.showSuccess('Plat dupliqué avec succès');
                }
            }
        } catch (error) {
            this.showError('Error duplicating dish: ' + error.message);
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

        this.loadDishes();
    }

    resetDishForm() {
        const form = document.getElementById('dishForm');
        if (form) {
            form.reset();
            delete form.dataset.dishId;
        }
    }

    fillDishForm(dish) {
        const form = document.getElementById('dishForm');
        if (!form) return;

        form.dataset.dishId = dish.id;
        
        const fields = ['nom', 'description', 'prix', 'image', 'allergenes', 'tempsPreparation'];
        fields.forEach(field => {
            const input = form.querySelector(`[name="${field}"]`);
            if (input && dish[field] !== undefined) {
                input.value = dish[field];
            }
        });

        const categorySelect = form.querySelector('[name="categoryId"]');
        if (categorySelect && dish.category) {
            categorySelect.value = dish.category.id;
        }

        const disponibleCheckbox = form.querySelector('[name="disponible"]');
        if (disponibleCheckbox) {
            disponibleCheckbox.checked = dish.disponible;
        }
    }

    updateDishStats(dishes) {
        // Update stats widgets if they exist
        const totalWidget = document.querySelector('.widget-value');
        if (totalWidget) {
            totalWidget.textContent = dishes.length;
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
        const modal = document.getElementById('addDishModal');
        if (modal) {
            const modalInstance = coreui.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance.hide();
            }
        }
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('dishesContainer')) {
        window.dishManager = new DishManager();
        dishManager.loadCategories();
        dishManager.loadDishes();
    }
}); 