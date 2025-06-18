/**
 * JoodKitchen Menu Management System
 * Handles menu composition and menu-specific CRUD operations
 */

class MenuManager {
    constructor() {
        this.api = new MenuAPI();
        this.currentMenu = null;
        this.availableDishes = [];
        this.selectedDishes = [];
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        // Create menu button
        const createBtn = document.querySelector('[data-bs-target="#addMenuModal"]');
        if (createBtn) {
            createBtn.addEventListener('click', () => this.showCreateModal());
        }

        // Save menu button
        const saveBtn = document.getElementById('saveMenuBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveMenu());
        }

        // Menu type change handler
        const typeSelect = document.querySelector('[name="type"]');
        if (typeSelect) {
            typeSelect.addEventListener('change', (e) => this.handleMenuTypeChange(e.target.value));
        }

        // Tab filters
        const tabButtons = document.querySelectorAll('[data-bs-toggle="tab"]');
        tabButtons.forEach(btn => {
            btn.addEventListener('click', (e) => {
                const target = e.target.dataset.bsTarget;
                if (target) {
                    this.filterMenusByTab(target.replace('#', ''));
                }
            });
        });
    }

    async loadMenus(filters = {}) {
        try {
            const response = await this.api.getMenus(filters);
            if (response.success) {
                this.renderMenusGrid(response.data);
                this.updateMenuStats(response.data);
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            this.showError('Error loading menus: ' + error.message);
        }
    }

    async loadAvailableDishes() {
        try {
            const response = await this.api.getDishes({ limit: 100 });
            if (response.success) {
                this.availableDishes = response.data;
                this.populateDishSelector();
            }
        } catch (error) {
            console.error('Error loading dishes:', error);
        }
    }

    renderMenusGrid(menus) {
        const container = document.getElementById('menusGrid');
        if (!container) return;

        container.innerHTML = '';
        
        menus.forEach(menu => {
            const menuCard = this.createMenuCard(menu);
            container.appendChild(menuCard);
        });
    }

    createMenuCard(menu) {
        const col = document.createElement('div');
        col.className = 'col-lg-6 col-xl-4';
        
        const typeColor = this.getMenuTypeColor(menu.type);
        const tagBadge = menu.tag ? `<span class="badge ${this.getTagBadge(menu.tag)}">${menu.tag}</span>` : '';
        
        col.innerHTML = `
            <div class="card h-100">
                <div class="position-relative">
                    <img src="${menu.image || 'https://via.placeholder.com/350x200/' + typeColor.replace('#', '') + '/ffffff?text=' + encodeURIComponent(menu.nom)}" 
                         class="card-img-top" alt="${menu.nom}" style="height: 200px; object-fit: cover;">
                    <div class="position-absolute top-0 end-0 m-2">
                        ${tagBadge}
                    </div>
                    <div class="position-absolute top-0 start-0 m-2">
                        <span class="badge ${menu.actif ? 'bg-success' : 'bg-danger'}">
                            ${menu.actif ? 'Actif' : 'Inactif'}
                        </span>
                    </div>
                </div>
                <div class="card-body">
                    <div class="d-flex justify-content-between align-items-start mb-2">
                        <h5 class="card-title mb-0">${menu.nom}</h5>
                        <span class="fw-bold jood-primary fs-5">${menu.prix}€</span>
                    </div>
                    <p class="card-text text-muted small mb-3">
                        ${menu.description || 'Aucune description disponible'}
                    </p>
                    
                    <!-- Menu Composition -->
                    <div class="mb-3">
                        <h6 class="small fw-bold text-muted mb-2">COMPOSITION (${menu.dishCount} plats):</h6>
                        <div class="menu-items">
                            ${this.renderMenuComposition(menu.dishes)}
                        </div>
                    </div>
                    
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted">
                            <i class="fas fa-calendar"></i> ${this.formatMenuDate(menu)}
                        </small>
                        <div class="btn-group btn-group-sm">
                            <button class="btn btn-outline-primary" onclick="menuManager.editMenu(${menu.id})" title="Modifier">
                                <i class="fas fa-edit"></i>
                            </button>
                            <button class="btn btn-outline-success" onclick="menuManager.duplicateMenu(${menu.id})" title="Dupliquer">
                                <i class="fas fa-copy"></i>
                            </button>
                            <button class="btn btn-outline-danger" onclick="menuManager.deleteMenu(${menu.id})" title="Supprimer">
                                <i class="fas fa-trash"></i>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
        
        return col;
    }

    renderMenuComposition(dishes) {
        if (!dishes || dishes.length === 0) {
            return '<small class="text-muted">Aucun plat assigné</small>';
        }

        return dishes.slice(0, 3).map(dish => `
            <div class="d-flex align-items-center mb-1">
                <span class="badge ${this.getCategoryBadge(dish.category)} me-2">${dish.category?.nom || 'Sans cat.'}</span>
                <small>${dish.nom}</small>
            </div>
        `).join('') + (dishes.length > 3 ? `<small class="text-muted">... et ${dishes.length - 3} autres plats</small>` : '');
    }

    getMenuTypeColor(type) {
        switch (type) {
            case 'menu_du_jour': return '#a9b73e';
            case 'normal': return '#202d5b';
            default: return '#6c757d';
        }
    }

    getTagBadge(tag) {
        switch (tag) {
            case 'marocain': return 'jood-primary-bg';
            case 'italien': return 'jood-secondary-bg';
            case 'international': return 'jood-info-bg';
            default: return 'bg-secondary';
        }
    }

    getCategoryBadge(category) {
        if (!category) return 'bg-secondary';
        // Use category color if available
        return 'bg-secondary';
    }

    formatMenuDate(menu) {
        if (menu.date) {
            return new Date(menu.date).toLocaleDateString('fr-FR');
        } else if (menu.jourSemaine) {
            return menu.jourSemaine;
        }
        return 'Menu permanent';
    }

    filterMenusByTab(tabId) {
        const filters = {};
        
        switch (tabId) {
            case 'dejeuner':
                filters.jourSemaine = 'dejeuner';
                break;
            case 'diner':
                filters.jourSemaine = 'diner';
                break;
            case 'familial':
                filters.tag = 'familial';
                break;
            case 'decouverte':
                filters.tag = 'decouverte';
                break;
            default:
                // Show all menus
                break;
        }
        
        this.loadMenus(filters);
    }

    showCreateModal() {
        this.resetMenuForm();
        this.loadAvailableDishes();
        const modal = document.getElementById('addMenuModal');
        if (modal) {
            const modalInstance = new coreui.Modal(modal);
            modalInstance.show();
        }
    }

    async editMenu(id) {
        try {
            const response = await this.api.getMenu(id);
            if (response.success) {
                this.currentMenu = response.data;
                this.fillMenuForm(response.data);
                this.loadAvailableDishes();
                const modal = document.getElementById('addMenuModal');
                if (modal) {
                    const modalInstance = new coreui.Modal(modal);
                    modalInstance.show();
                }
            }
        } catch (error) {
            this.showError('Error loading menu: ' + error.message);
        }
    }

    async saveMenu() {
        const form = document.getElementById('menuForm');
        const formData = new FormData(form);
        
        const data = {
            nom: formData.get('nom'),
            description: formData.get('description'),
            type: formData.get('type'),
            jourSemaine: formData.get('jourSemaine'),
            prix: formData.get('prix'),
            tag: formData.get('tag'),
            date: formData.get('date') || null,
            actif: formData.has('actif'),
            dishes: this.selectedDishes
        };

        try {
            let response;
            const menuId = form.dataset.menuId;

            if (menuId) {
                response = await this.api.updateMenu(menuId, data);
            } else {
                response = await this.api.createMenu(data);
            }

            if (response.success) {
                this.hideModal();
                this.loadMenus();
                this.showSuccess(response.message);
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            this.showError('Error saving menu: ' + error.message);
        }
    }

    async deleteMenu(id) {
        if (!confirm('Êtes-vous sûr de vouloir supprimer ce menu ?')) {
            return;
        }

        try {
            const response = await this.api.deleteMenu(id);
            if (response.success) {
                this.loadMenus();
                this.showSuccess(response.message);
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            this.showError('Error deleting menu: ' + error.message);
        }
    }

    async duplicateMenu(id) {
        try {
            const response = await this.api.getMenu(id);
            if (response.success) {
                const menu = response.data;
                menu.nom = menu.nom + ' (Copie)';
                delete menu.id;
                
                const createResponse = await this.api.createMenu(menu);
                if (createResponse.success) {
                    this.loadMenus();
                    this.showSuccess('Menu dupliqué avec succès');
                }
            }
        } catch (error) {
            this.showError('Error duplicating menu: ' + error.message);
        }
    }

    handleMenuTypeChange(type) {
        const dateField = document.querySelector('[name="date"]');
        const jourSemaineField = document.querySelector('[name="jourSemaine"]');
        const tagField = document.querySelector('[name="tag"]');

        if (type === 'menu_du_jour') {
            if (dateField) dateField.closest('.form-group').style.display = 'block';
            if (jourSemaineField) jourSemaineField.closest('.form-group').style.display = 'block';
            if (tagField) tagField.closest('.form-group').style.display = 'block';
        } else {
            if (dateField) dateField.closest('.form-group').style.display = 'none';
            if (jourSemaineField) jourSemaineField.closest('.form-group').style.display = 'none';
            if (tagField) tagField.closest('.form-group').style.display = 'none';
        }
    }

    populateDishSelector() {
        // Implementation for dish selection interface
        // This would populate a modal or section for selecting dishes for the menu
    }

    fillMenuForm(menu) {
        const form = document.getElementById('menuForm');
        if (!form) return;

        form.dataset.menuId = menu.id;
        
        const fields = ['nom', 'description', 'type', 'jourSemaine', 'prix', 'tag', 'date'];
        fields.forEach(field => {
            const input = form.querySelector(`[name="${field}"]`);
            if (input && menu[field] !== undefined) {
                input.value = menu[field];
            }
        });

        const actifCheckbox = form.querySelector('[name="actif"]');
        if (actifCheckbox) {
            actifCheckbox.checked = menu.actif;
        }

        this.selectedDishes = menu.dishes || [];
        this.handleMenuTypeChange(menu.type);
    }

    resetMenuForm() {
        const form = document.getElementById('menuForm');
        if (form) {
            form.reset();
            delete form.dataset.menuId;
            this.selectedDishes = [];
            this.currentMenu = null;
        }
    }

    updateMenuStats(menus) {
        const total = menus.length;
        const menuDuJour = menus.filter(m => m.type === 'menu_du_jour').length;
        const avgPrice = menus.reduce((sum, m) => sum + parseFloat(m.prix), 0) / total;

        // Update stats widgets
        const widgets = document.querySelectorAll('.widget-value');
        if (widgets[0]) widgets[0].textContent = total;
        if (widgets[1]) widgets[1].textContent = menuDuJour;
        if (widgets[2]) widgets[2].textContent = avgPrice.toFixed(2) + '€';
    }

    hideModal() {
        const modal = document.getElementById('addMenuModal');
        if (modal) {
            const modalInstance = coreui.Modal.getInstance(modal);
            if (modalInstance) {
                modalInstance.hide();
            }
        }
    }

    showError(message) {
        console.error(message);
        // TODO: Implement toast notification
    }

    showSuccess(message) {
        console.log(message);
        // TODO: Implement toast notification
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', function() {
    if (document.getElementById('menusGrid')) {
        window.menuManager = new MenuManager();
        menuManager.loadMenus();
    }
});