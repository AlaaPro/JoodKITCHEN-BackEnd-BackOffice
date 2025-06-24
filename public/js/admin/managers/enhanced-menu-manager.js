/**
 * Enhanced Smart Menu Management System
 * Handles both Normal and Daily menus with intelligent UI adaptation
 */

class EnhancedMenuManager {
    constructor() {
        this.api = new MenuAPI();
        this.currentFilter = 'all';
        this.currentView = 'grid';
        this.currentMenuType = 'normal';
        this.selectedMenus = new Set();
        this.availableDishes = [];
        this.selectedDishes = [];
        this.editingMenu = null;
        this.currentWeek = new Date();
        this.currentCourseFilter = null;
        
        // Initialize the manager
        this.init();
    }

    async init() {
        console.log('🚀 Initializing Enhanced Menu Manager...');
        
        try {
            // Set up event listeners
            this.setupEventListeners();
            
            // Load initial data
            await this.loadInitialData();
            
            // Load menus
            await this.loadMenus();
            
            console.log('✅ Enhanced Menu Manager initialized successfully');
        } catch (error) {
            console.error('❌ Error initializing Enhanced Menu Manager:', error);
            this.showError('Erreur lors de l\'initialisation du gestionnaire de menus');
        }
    }

    setupEventListeners() {
        // Tab navigation - handle both direct buttons and dropdown items
        document.querySelectorAll('[data-filter]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const filter = e.target.dataset.filter;
                this.applyFilter(filter);
            });
        });

        // View mode buttons
        document.querySelectorAll('[data-view]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const view = e.target.dataset.view;
                this.switchView(view);
            });
        });

        // Quick search
        const searchInput = document.getElementById('quickSearch');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce((e) => {
                this.performQuickSearch(e.target.value);
            }, 300));
        }

        // Add menu button
        const addMenuBtn = document.getElementById('addMenuBtn');
        if (addMenuBtn) {
            addMenuBtn.addEventListener('click', () => this.showCreateModal());
        }

        // Menu modal events
        this.setupModalEventListeners();

        // Clear filters button
        document.getElementById('clearFilters')?.addEventListener('click', () => {
            this.applyFilter('all');
        });

        // Initialize CoreUI dropdowns
        this.initializeDropdowns();
    }

    initializeDropdowns() {
        // Initialize CoreUI dropdowns
        const dropdownTriggerList = [].slice.call(document.querySelectorAll('[data-coreui-toggle="dropdown"]'));
        dropdownTriggerList.map(function (dropdownTriggerEl) {
            return new coreui.Dropdown(dropdownTriggerEl);
        });
    }

    setupModalEventListeners() {
        // Menu type selector
        document.querySelectorAll('input[name="menuType"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.handleMenuTypeChange(e.target.value);
            });
        });

        // Save menu button
        const saveBtn = document.getElementById('saveMenuBtn');
        if (saveBtn) {
            // Remove any existing event listeners first
            saveBtn.replaceWith(saveBtn.cloneNode(true));
            const newSaveBtn = document.getElementById('saveMenuBtn');
            newSaveBtn.addEventListener('click', () => {
                console.log('💾 Save button clicked - this.editingMenu:', this.editingMenu);
                this.saveMenu();
            });
        }

        // Add dish button
        const addDishBtn = document.getElementById('addDishBtn');
        if (addDishBtn) {
            addDishBtn.addEventListener('click', () => this.showDishSelectionModal());
        }

        // Course-specific dish addition
        document.querySelectorAll('.add-course-dish').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const course = e.target.dataset.course;
                this.showDishSelectionModal(course);
            });
        });

        // Confirm dish selection
        const confirmBtn = document.getElementById('confirmDishSelection');
        if (confirmBtn) {
            confirmBtn.addEventListener('click', () => this.confirmDishSelection());
        }

        // Setup modal cleanup event listeners
        this.setupModalCleanupEvents();

        // Image upload
        this.setupImageUpload();
    }

    setupModalCleanupEvents() {
        // Setup cleanup for all modals
        const modals = ['menuModal', 'dishSelectionModal'];
        
        modals.forEach(modalId => {
            const modalElement = document.getElementById(modalId);
            if (modalElement) {
                // Listen for modal hidden events
                modalElement.addEventListener('hidden.coreui.modal', () => {
                    console.log('🧹 Modal hidden event fired for:', modalId);
                    this.cleanupModalBackdrops();
                });

                modalElement.addEventListener('hidden.bs.modal', () => {
                    console.log('🧹 Bootstrap modal hidden event fired for:', modalId);
                    this.cleanupModalBackdrops();
                });

                // Also handle manual close button clicks
                const closeButtons = modalElement.querySelectorAll('[data-coreui-dismiss="modal"], [data-bs-dismiss="modal"], .btn-close');
                closeButtons.forEach(btn => {
                    btn.addEventListener('click', () => {
                        console.log('🧹 Close button clicked for:', modalId);
                        setTimeout(() => this.cleanupModalBackdrops(), 300);
                    });
                });
            }
        });

        // Global cleanup on escape key
        document.addEventListener('keydown', (e) => {
            if (e.key === 'Escape') {
                setTimeout(() => this.cleanupModalBackdrops(), 300);
            }
        });

        // Cleanup when clicking on backdrop
        document.addEventListener('click', (e) => {
            if (e.target.classList.contains('modal') || e.target.classList.contains('modal-backdrop')) {
                setTimeout(() => this.cleanupModalBackdrops(), 300);
            }
        });
    }

    setupImageUpload() {
        const uploadArea = document.getElementById('imageUploadArea');
        const fileInput = document.getElementById('menuImage');
        
        if (uploadArea && fileInput) {
            uploadArea.addEventListener('click', () => fileInput.click());
            
            fileInput.addEventListener('change', (e) => {
                if (e.target.files && e.target.files[0]) {
                    this.handleImagePreview(e.target.files[0]);
                }
            });
            
            // Drag and drop
            uploadArea.addEventListener('dragover', (e) => {
                e.preventDefault();
                uploadArea.classList.add('dragover');
            });
            
            uploadArea.addEventListener('dragleave', () => {
                uploadArea.classList.remove('dragover');
            });
            
            uploadArea.addEventListener('drop', (e) => {
                e.preventDefault();
                uploadArea.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    this.handleImagePreview(files[0]);
                    fileInput.files = files;
                }
            });
        }
    }

    async loadInitialData() {
        try {
            // Load available dishes for menu composition
            const dishesResponse = await this.api.getPlats({ limit: 100 });
            if (dishesResponse && dishesResponse.success) {
                this.availableDishes = dishesResponse.data;
            } else {
                // Fallback mock data for testing
                this.availableDishes = this.getMockDishes();
            }

            // Load categories for dish filtering
            const categoriesResponse = await this.api.getCategories();
            if (categoriesResponse && categoriesResponse.success) {
                this.populateCategoryFilter(categoriesResponse.data);
            } else {
                // Fallback mock categories
                this.populateCategoryFilter(this.getMockCategories());
            }
        } catch (error) {
            console.error('Error loading initial data:', error);
            // Use mock data as fallback
            this.availableDishes = this.getMockDishes();
            this.populateCategoryFilter(this.getMockCategories());
        }
    }

    async loadAvailableDishes(cuisine = null, course = null) {
        try {
            const params = {};
            
            // Add filters based on course/category if specified
            if (course) {
                // Map course to category filter if needed
                const categoryMapping = {
                    'entree': 'entrée',
                    'plat_principal': 'plat',
                    'dessert': 'dessert'
                };
                params.category = categoryMapping[course] || course;
            }
            
            // Note: We don't filter by cuisine because dishes aren't organized by cuisine
            // They are filtered by status and categories only
            console.log('🔍 Loading dishes with params:', params);
            
            // Use the working plats endpoint that filters by status and categories
            const response = await this.api.getPlats(params);
            
            if (response.success) {
                this.availableDishes = response.data;
                console.log('✅ Loaded dishes from plats API:', this.availableDishes.length, 'dishes');
                return response.data;
            } else {
                console.warn('⚠️ Plats API returned no success, using mock data');
                this.availableDishes = this.getMockDishes();
                return this.availableDishes;
            }
        } catch (error) {
            console.error('❌ Error loading dishes from API:', error);
            console.warn('🔄 Falling back to mock dishes');
            this.availableDishes = this.getMockDishes();
            return this.availableDishes;
        }
    }

    async loadMenus(filters = {}) {
        try {
            this.showLoadingState();

            // Combine current filter with additional filters
            const finalFilters = {
                ...this.buildFilterParams(),
                ...filters
            };

            console.log('🔍 Loading menus with filters:', finalFilters);

            let response;
            try {
                response = await this.api.getMenus(finalFilters);
            } catch (apiError) {
                console.warn('API error, using mock data:', apiError);
                response = { success: true, data: this.getMockMenus(finalFilters) };
            }
            
            if (response && response.success) {
                this.renderMenus(response.data);
                this.updateStatistics(response.data);
                this.updateContextInfo();
            } else {
                this.showError(response?.message || 'Erreur lors du chargement des menus');
            }
        } catch (error) {
            console.error('Error loading menus:', error);
            // Use mock data as fallback
            const mockMenus = this.getMockMenus(filters);
            this.renderMenus(mockMenus);
            this.updateStatistics(mockMenus);
            this.updateContextInfo();
        } finally {
            this.hideLoadingState();
        }
    }

    buildFilterParams() {
        const params = {};

        switch (this.currentFilter) {
            case 'normal':
                params.type = 'normal';
                break;
            case 'daily-marocain':
                params.type = 'menu_du_jour';
                params.tag = 'marocain';
                break;
            case 'daily-italien':
                params.type = 'menu_du_jour';
                params.tag = 'italien';
                break;
            case 'daily-international':
                params.type = 'menu_du_jour';
                params.tag = 'international';
                break;
            case 'all':
            default:
                // No additional filters
                break;
        }

        return params;
    }

    applyFilter(filter) {
        this.currentFilter = filter;
        this.updateTabStates();
        this.updateAddButtonContext();
        this.loadMenus();
    }

    updateTabStates() {
        // Update tab active states
        document.querySelectorAll('[data-filter]').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.filter === this.currentFilter);
        });
    }

    updateAddButtonContext() {
        const addMenuText = document.getElementById('addMenuText');
        
        if (addMenuText) {
            switch (this.currentFilter) {
                case 'normal':
                    addMenuText.textContent = 'Nouveau Menu Normal';
                    break;
                case 'daily-marocain':
                    addMenuText.textContent = 'Menu du Jour Marocain';
                    break;
                case 'daily-italien':
                    addMenuText.textContent = 'Menu du Jour Italien';
                    break;
                case 'daily-international':
                    addMenuText.textContent = 'Menu du Jour International';
                    break;
                default:
                    addMenuText.textContent = 'Nouveau Menu';
            }
        }
    }

    switchView(view) {
        this.currentView = view;
        
        // Update view button states
        document.querySelectorAll('[data-view]').forEach(btn => {
            btn.classList.toggle('active', btn.dataset.view === view);
        });

        // Show/hide view containers
        document.querySelectorAll('.menu-view').forEach(container => {
            container.classList.toggle('active', container.id === `${view}View`);
        });

        // Load appropriate content for the view
        if (view === 'calendar') {
            this.renderCalendarView();
        }
    }

    renderMenus(menus) {
        if (this.currentView === 'grid') {
            this.renderGridView(menus);
        } else if (this.currentView === 'list') {
            this.renderListView(menus);
        }
    }

    renderGridView(menus) {
        const container = document.getElementById('menusGrid');
        if (!container) return;

        if (menus.length === 0) {
            container.innerHTML = this.getEmptyStateHTML();
            return;
        }

        container.innerHTML = menus.map(menu => this.createMenuCard(menu)).join('');
    }

    createMenuCard(menu) {
        const typeInfo = this.getMenuTypeInfo(menu);
        const cuisineFlag = this.getCuisineFlag(menu.tag);
        
        return `
            <div class="col-lg-6 col-xl-4">
                <div class="card menu-card h-100" data-menu-id="${menu.id}">
                    <div class="position-relative">
                        <img src="${menu.image || this.getDefaultMenuImage(menu.type, menu.tag)}" 
                             class="card-img-top" alt="${menu.nom}" style="height: 200px; object-fit: cover;">
                        
                        <!-- Menu Type Badge -->
                        <div class="menu-type-badge">
                            <span class="badge ${typeInfo.badgeClass}">${typeInfo.label}</span>
                        </div>
                        
                        <!-- Status Badge -->
                        <div class="menu-status-badge">
                            <span class="badge ${menu.actif ? 'bg-success' : 'bg-danger'}">
                                ${menu.actif ? 'Actif' : 'Inactif'}
                            </span>
                        </div>
                        
                        <!-- Cuisine Flag -->
                        ${cuisineFlag ? `<div class="menu-cuisine-flag">${cuisineFlag}</div>` : ''}
                    </div>
                    
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-2">
                            <h5 class="card-title mb-0">${menu.nom}</h5>
                            <span class="fw-bold jood-primary fs-5">${menu.prix}€</span>
                        </div>
                        
                        <p class="card-text text-muted small mb-3">
                            ${menu.description || 'Aucune description disponible'}
                        </p>
                        
                        <!-- Menu Date/Info -->
                        <div class="mb-2">
                            <small class="text-muted">
                                <i class="fas fa-calendar"></i> ${this.formatMenuDate(menu)}
                            </small>
                        </div>
                        
                        <!-- Menu Composition -->
                        <div class="mb-3">
                            <h6 class="small fw-bold text-muted mb-2">COMPOSITION (${menu.dishCount || 0} plats):</h6>
                            <div class="menu-items">
                                ${this.renderMenuComposition(menu.dishes || [])}
                            </div>
                        </div>
                        
                        <div class="d-flex justify-content-between align-items-center">
                            <small class="text-muted">
                                <i class="fas fa-utensils"></i> ${menu.dishCount || 0} plats
                            </small>
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary" onclick="enhancedMenuManager.editMenu(${menu.id})" title="Modifier">
                                    <i class="fas fa-edit"></i>
                                </button>
                                <button class="btn btn-outline-success" onclick="enhancedMenuManager.duplicateMenu(${menu.id})" title="Dupliquer">
                                    <i class="fas fa-copy"></i>
                                </button>
                                <button class="btn btn-outline-danger" onclick="enhancedMenuManager.deleteMenu(${menu.id})" title="Supprimer">
                                    <i class="fas fa-trash"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    // Menu CRUD Operations
    async saveMenu() {
        try {
            const formData = this.collectFormData();
            
            console.log('🔍 DEBUG - saveMenu() called');
            console.log('🔍 DEBUG - this.editingMenu:', this.editingMenu);
            console.log('🔍 DEBUG - formData:', formData);
            
            if (!this.validateMenuForm(formData)) {
                return;
            }
            
            this.showSaveLoading();
            
            let response;
            if (this.editingMenu) {
                console.log('🔄 DEBUG - Calling UPDATE for menu ID:', this.editingMenu.id);
                response = await this.api.updateMenu(this.editingMenu.id, formData);
                console.log('🔄 DEBUG - Update response:', response);
            } else {
                console.log('➕ DEBUG - Calling CREATE for new menu');
                response = await this.api.createMenu(formData);
                console.log('➕ DEBUG - Create response:', response);
            }
            
            if (response.success) {
                this.showSuccess(this.editingMenu ? 'Menu modifié avec succès' : 'Menu créé avec succès');
                this.hideModal('menuModal');
                
                // Reset editing state
                this.editingMenu = null;
                
                await this.loadMenus();
            } else {
                this.showError(response.message || 'Erreur lors de l\'enregistrement du menu');
            }
        } catch (error) {
            console.error('❌ Error saving menu:', error);
            this.showError('Erreur lors de l\'enregistrement du menu');
        } finally {
            this.hideSaveLoading();
        }
    }

    async editMenu(id) {
        try {
            console.log('📝 DEBUG - editMenu() called with ID:', id);
            
            const response = await this.api.getMenu(id);
            console.log('📝 DEBUG - getMenu response:', response);
            
            if (response.success) {
                this.editingMenu = response.data;
                console.log('📝 DEBUG - Set this.editingMenu to:', this.editingMenu);
                
                this.hideMenuTypeSelector(); // Hide type selector when editing
                this.fillMenuForm(response.data);
                this.setModalTitle('Modifier le Menu');
                this.showModal('menuModal');
            } else {
                this.showError('Menu introuvable');
            }
        } catch (error) {
            console.error('❌ Error loading menu for edit:', error);
            this.showError('Erreur lors du chargement du menu');
        }
    }

    async deleteMenu(id) {
        if (!confirm('Êtes-vous sûr de vouloir supprimer ce menu ?')) {
            return;
        }

        try {
            const response = await this.api.deleteMenu(id);
            
            if (response.success) {
                this.showSuccess('Menu supprimé avec succès');
                await this.loadMenus();
            } else {
                this.showError(response.message || 'Erreur lors de la suppression du menu');
            }
        } catch (error) {
            console.error('Error deleting menu:', error);
            this.showError('Erreur lors de la suppression du menu');
        }
    }

    // Modal Management
    showCreateModal() {
        this.editingMenu = null;
        this.resetMenuForm();
        this.setModalTitle('Nouveau Menu');
        this.showMenuTypeSelector(); // Show type selector for creation
        this.preselectMenuType();
        this.showModal('menuModal');
    }

    preselectMenuType() {
        // Pre-select menu type based on current filter
        let menuType = 'normal';
        
        if (this.currentFilter.startsWith('daily-')) {
            menuType = 'menu_du_jour';
        }
        
        document.querySelector(`input[value="${menuType}"]`).checked = true;
        this.handleMenuTypeChange(menuType);
        
        // Pre-fill cuisine if specific cuisine filter is active
        if (this.currentFilter.startsWith('daily-')) {
            const cuisine = this.currentFilter.replace('daily-', '');
            const cuisineSelect = document.getElementById('menuCuisine');
            if (cuisineSelect) {
                cuisineSelect.value = cuisine;
            }
        }
    }

    handleMenuTypeChange(menuType) {
        this.currentMenuType = menuType;
        
        // Show/hide appropriate fields
        const normalFields = document.getElementById('normalMenuFields');
        const dailyFields = document.getElementById('dailyMenuFields');
        const normalStructure = document.getElementById('normalMenuStructure');
        const dailyStructure = document.getElementById('dailyMenuStructure');
        
        if (menuType === 'menu_du_jour') {
            normalFields?.classList.add('d-none');
            dailyFields?.classList.remove('d-none');
            normalStructure?.classList.add('d-none');
            dailyStructure?.classList.remove('d-none');
        } else {
            normalFields?.classList.remove('d-none');
            dailyFields?.classList.add('d-none');
            normalStructure?.classList.remove('d-none');
            dailyStructure?.classList.add('d-none');
        }
        
        // Update modal header color
        const modalHeader = document.getElementById('modalHeader');
        if (modalHeader) {
            if (menuType === 'menu_du_jour') {
                modalHeader.className = 'modal-header jood-info-bg';
            } else {
                modalHeader.className = 'modal-header jood-primary-bg';
            }
        }
        
        // Clear selected dishes when switching types (only during creation, not editing)
        if (!this.editingMenu) {
            this.selectedDishes = [];
            this.updateMenuComposition();
        } else {
            // When editing, just update the composition display without clearing dishes
            console.log('📝 Updating composition for existing menu with', this.selectedDishes.length, 'dishes');
        }
    }

    showMenuTypeSelector() {
        const selector = document.getElementById('menuTypeSelector');
        if (selector) {
            selector.closest('.row').classList.remove('d-none');
        }
    }

    hideMenuTypeSelector() {
        const selector = document.getElementById('menuTypeSelector');
        if (selector) {
            selector.closest('.row').classList.add('d-none');
        }
    }

    // Utility Methods
    getMenuTypeInfo(menu) {
        if (menu.type === 'menu_du_jour') {
            return {
                label: 'Menu du Jour',
                badgeClass: 'jood-info-bg'
            };
        } else {
            return {
                label: 'Menu Normal',
                badgeClass: 'jood-secondary-bg'
            };
        }
    }

    getCuisineFlag(tag) {
        const flags = {
            'marocain': '🇲🇦',
            'italien': '🇮🇹',
            'international': '🌍'
        };
        return flags[tag] || '';
    }

    getDefaultMenuImage(type, tag) {
        // Use a local placeholder or data URL instead of external service
        // This avoids network issues with via.placeholder.com
        
        // Option 1: Use your existing logo
        const logoPath = '/image/Logo-JoodKITCHEN.png';
        
        // Option 2: Create a simple data URL placeholder
        const color = type === 'menu_du_jour' ? '#a9b73e' : '#202d5b';
        const textColor = '#ffffff';
        const text = tag || type;
        
        // Create a simple SVG placeholder
        const svg = `
            <svg width="350" height="200" xmlns="http://www.w3.org/2000/svg">
                <rect width="100%" height="100%" fill="${color}"/>
                <text x="50%" y="50%" font-family="Arial, sans-serif" font-size="18" 
                      fill="${textColor}" text-anchor="middle" dominant-baseline="middle">
                    Menu ${text}
                </text>
            </svg>
        `;
        
        // Convert SVG to data URL
        const dataUrl = `data:image/svg+xml;base64,${btoa(unescape(encodeURIComponent(svg)))}`;
        
        return dataUrl;
    }

    formatMenuDate(menu) {
        if (menu.date) {
            return new Date(menu.date).toLocaleDateString('fr-FR');
        } else if (menu.jourSemaine) {
            return menu.jourSemaine;
        }
        return 'Menu permanent';
    }

    renderMenuComposition(dishes) {
        if (!dishes || dishes.length === 0) {
            return '<small class="text-muted">Aucun plat assigné</small>';
        }

        return dishes.slice(0, 3).map(dish => `
            <div class="d-flex align-items-center mb-1">
                <span class="badge bg-secondary me-2">${dish.category?.nom || 'Sans cat.'}</span>
                <small>${dish.nom}</small>
            </div>
        `).join('') + (dishes.length > 3 ? `<small class="text-muted">... et ${dishes.length - 3} autres plats</small>` : '');
    }

    updateMenuComposition() {
        if (this.currentMenuType === 'menu_du_jour') {
            this.updateDailyMenuComposition();
        } else {
            this.updateNormalMenuComposition();
        }
    }

    updateDailyMenuComposition() {
        console.log('📝 Updating daily menu composition with', this.selectedDishes.length, 'dishes:', this.selectedDishes);
        
        // Define course mappings (API category name => HTML container ID)
        const courseMapping = {
            'entree': { containerId: 'entreeDishes', countId: 'entreeCount' },
            'plat_principal': { containerId: 'platDishes', countId: 'platCount' },
            'dessert': { containerId: 'dessertDishes', countId: 'dessertCount' }
        };
        
        Object.entries(courseMapping).forEach(([course, elements]) => {
            const { containerId, countId } = elements;
            const container = document.getElementById(containerId);
            const countElement = document.getElementById(countId);
            
            console.log(`📝 Looking for ${course} dishes in container:`, containerId);
            
            const courseDishes = this.selectedDishes.filter(d => {
                if (!d.category || !d.category.nom) {
                    console.log(`📝 Dish "${d.nom}" has no category`);
                    return false;
                }
                
                const categoryName = d.category.nom.toLowerCase();
                const courseMatches = [
                    // Exact match
                    categoryName === course,
                    // Partial matches for different naming conventions
                    course === 'entree' && (categoryName.includes('entree') || categoryName.includes('entrée')),
                    course === 'plat_principal' && (categoryName.includes('plat') || categoryName.includes('principal')),
                    course === 'dessert' && categoryName.includes('dessert')
                ];
                
                const categoryMatch = courseMatches.some(match => match);
                console.log(`📝 Dish "${d.nom}" category: "${d.category.nom}", matches ${course}:`, categoryMatch);
                return categoryMatch;
            });
            
            console.log(`📝 Found ${courseDishes.length} dishes for ${course}:`, courseDishes);
            
            if (container) {
                if (courseDishes.length > 0) {
                    container.innerHTML = courseDishes.map(dish => this.createCourseDishItem(dish)).join('');
                    console.log(`📝 ✅ Rendered ${courseDishes.length} dishes in ${containerId}`);
                } else {
                    container.innerHTML = `
                        <div class="text-center py-3 text-muted">
                            <small>Aucun ${this.getCourseName(course).toLowerCase()} sélectionné</small>
                        </div>
                    `;
                    console.log(`📝 ⚠️ No dishes found for ${course}, showing empty message`);
                }
            } else {
                console.error(`📝 ❌ Container not found: ${containerId}`);
            }
            
            if (countElement) {
                countElement.textContent = `${courseDishes.length}/1`;
            } else {
                console.error(`📝 ❌ Count element not found: ${countId}`);
            }
        });
    }

    updateNormalMenuComposition() {
        const container = document.getElementById('selectedDishes');
        const noDishesMessage = document.getElementById('noDishesMessage');
        
        if (this.selectedDishes.length === 0) {
            if (container) container.innerHTML = '';
            noDishesMessage?.classList.remove('d-none');
        } else {
            noDishesMessage?.classList.add('d-none');
            if (container) {
                container.innerHTML = this.selectedDishes.map(dish => this.createSelectedDishItem(dish)).join('');
            }
        }
    }

    createCourseDishItem(dish) {
        return `
            <div class="course-dish-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${dish.nom}</strong>
                        <div><small class="text-muted">${dish.prix}€</small></div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="enhancedMenuManager.removeDish(${dish.id})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
    }

    createSelectedDishItem(dish) {
        return `
            <div class="dish-item">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <strong>${dish.nom}</strong>
                        <div>
                            <small class="text-muted">${dish.prix}€</small>
                            <span class="badge bg-secondary ms-2">${dish.category?.nom || 'Sans catégorie'}</span>
                        </div>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline-danger" onclick="enhancedMenuManager.removeDish(${dish.id})">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        `;
    }

    // Statistics and Updates
    async updateStatistics(menus) {
        try {
            // Try to get real statistics from API
            const response = await this.api.getMenuStats();
            
            if (response.success) {
                console.log('✅ Loaded statistics from API:', response.data);
                this.renderAPIStatistics(response.data);
            } else {
                // Fallback to calculated statistics
                console.warn('⚠️ API stats failed, calculating from loaded menus');
                this.renderCalculatedStatistics(menus);
            }
        } catch (error) {
            console.error('❌ Error loading statistics, calculating from loaded menus:', error);
            this.renderCalculatedStatistics(menus);
        }
    }

    renderAPIStatistics(data) {
        const elements = {
            totalMenusCount: data.totalMenus || 0,
            normalMenusCount: data.normalMenus || 0,
            dailyMenusCount: data.dailyMenus || 0,
            todayMenusCount: `${data.todayMenus || 0}/3`,
            averagePrice: `${data.pricing?.average || 0}€`,
            priceRange: `Min: ${data.pricing?.min || 0}€ - Max: ${data.pricing?.max || 0}€`,
            dailyMenusBadge: data.dailyMenus || 0,
            marocainCount: data.cuisine?.marocain || 0,
            italienCount: data.cuisine?.italien || 0,
            internationalCount: data.cuisine?.international || 0
        };

        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    }

    renderCalculatedStatistics(menus) {
        const stats = this.calculateStatistics(menus);
        
        const elements = {
            totalMenusCount: stats.total,
            normalMenusCount: stats.normal,
            dailyMenusCount: stats.daily,
            todayMenusCount: `${stats.today}/3`,
            averagePrice: `${stats.averagePrice}€`,
            priceRange: `Min: ${stats.minPrice}€ - Max: ${stats.maxPrice}€`,
            dailyMenusBadge: stats.daily,
            marocainCount: stats.marocain,
            italienCount: stats.italien,
            internationalCount: stats.international
        };

        Object.entries(elements).forEach(([id, value]) => {
            const element = document.getElementById(id);
            if (element) {
                element.textContent = value;
            }
        });
    }

    calculateStatistics(menus) {
        const today = new Date().toISOString().split('T')[0];
        
        const stats = {
            total: menus.length,
            normal: menus.filter(m => m.type === 'normal').length,
            daily: menus.filter(m => m.type === 'menu_du_jour').length,
            today: menus.filter(m => m.date === today).length,
            marocain: menus.filter(m => m.tag === 'marocain').length,
            italien: menus.filter(m => m.tag === 'italien').length,
            international: menus.filter(m => m.tag === 'international').length,
            averagePrice: '0.00',
            minPrice: '0.00',
            maxPrice: '0.00'
        };

        if (menus.length > 0) {
            const prices = menus.map(m => parseFloat(m.prix)).filter(p => !isNaN(p));
            if (prices.length > 0) {
                stats.averagePrice = (prices.reduce((a, b) => a + b, 0) / prices.length).toFixed(2);
                stats.minPrice = Math.min(...prices).toFixed(2);
                stats.maxPrice = Math.max(...prices).toFixed(2);
            }
        }

        return stats;
    }

    // Utility Methods
    collectFormData() {
        const form = document.getElementById('smartMenuForm');
        if (!form) return {};

        const formData = new FormData(form);
        const data = {};

        for (let [key, value] of formData.entries()) {
            data[key] = value;
        }

        // Add menu type
        data.type = this.currentMenuType;
        
        // Add selected dishes in the correct format expected by the API
        data.dishes = this.selectedDishes.map((dish, index) => ({
            id: dish.id,
            ordre: index + 1
        }));

        // For daily menus, set the tag to the cuisine value
        if (this.currentMenuType === 'menu_du_jour' && data.cuisine) {
            data.tag = data.cuisine;
        }

        console.log('📋 Collected form data:', data);
        return data;
    }

    validateMenuForm(data) {
        const errors = [];

        if (!data.nom || data.nom.trim() === '') {
            errors.push('Le nom du menu est obligatoire');
        }

        if (!data.prix || parseFloat(data.prix) <= 0) {
            errors.push('Le prix doit être supérieur à 0');
        }

        if (data.type === 'menu_du_jour') {
            if (!data.date) {
                errors.push('La date est obligatoire pour un menu du jour');
            }
            if (!data.cuisine) {
                errors.push('La cuisine est obligatoire pour un menu du jour');
            }
        }

        if (errors.length > 0) {
            this.showError(errors.join('\n'));
            return false;
        }

        return true;
    }

    resetMenuForm() {
        console.log('🔄 DEBUG - resetMenuForm() called');
        console.log('🔄 DEBUG - Current editingMenu before reset:', this.editingMenu);
        console.trace('🔄 DEBUG - Call stack for resetMenuForm');
        
        const form = document.getElementById('smartMenuForm');
        if (form) {
            form.reset();
        }
        this.selectedDishes = [];
        this.currentMenuType = 'normal';
        this.editingMenu = null; // Only reset editing menu when explicitly called
        this.updateMenuComposition();
    }

    setModalTitle(title) {
        const titleElement = document.getElementById('modalTitleText');
        if (titleElement) {
            titleElement.textContent = title;
        }
    }

    showModal(modalId) {
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
            try {
                // Try CoreUI first
                if (typeof coreui !== 'undefined' && coreui.Modal) {
                    // Check if modal instance already exists
                    let modal = coreui.Modal.getInstance(modalElement);
                    if (!modal) {
                        modal = new coreui.Modal(modalElement);
                    }
                    modal.show();
                } else if (typeof window.coreui !== 'undefined' && window.coreui.Modal) {
                    // Check if modal instance already exists
                    let modal = window.coreui.Modal.getInstance(modalElement);
                    if (!modal) {
                        modal = new window.coreui.Modal(modalElement);
                    }
                    modal.show();
                } else {
                    console.warn('CoreUI Modal not found, falling back to direct DOM manipulation');
                    this.cleanupModalBackdrops(); // Clean before showing
                    modalElement.classList.add('show');
                    modalElement.style.display = 'block';
                    modalElement.setAttribute('aria-modal', 'true');
                    modalElement.removeAttribute('aria-hidden');
                    document.body.classList.add('modal-open');
                }
            } catch (error) {
                console.error('Error showing modal:', error);
                // Fallback to direct manipulation
                this.cleanupModalBackdrops();
                modalElement.classList.add('show');
                modalElement.style.display = 'block';
                modalElement.setAttribute('aria-modal', 'true');
                modalElement.removeAttribute('aria-hidden');
                document.body.classList.add('modal-open');
            }
        }
    }

    hideModal(modalId) {
        const modalElement = document.getElementById(modalId);
        if (modalElement) {
            try {
                // Try CoreUI first
                if (typeof coreui !== 'undefined' && coreui.Modal) {
                    const modal = coreui.Modal.getInstance(modalElement);
                    if (modal) {
                        modal.hide();
                    }
                } else if (typeof window.coreui !== 'undefined' && window.coreui.Modal) {
                    const modal = window.coreui.Modal.getInstance(modalElement);
                    if (modal) {
                        modal.hide();
                    }
                } else {
                    console.warn('CoreUI Modal not found, falling back to direct DOM manipulation');
                    this.hideModalDirectly(modalElement);
                }
            } catch (error) {
                console.error('Error hiding modal:', error);
                // Fallback to direct manipulation
                this.hideModalDirectly(modalElement);
            }
            
            // Always ensure cleanup after a short delay
            setTimeout(() => {
                this.cleanupModalBackdrops();
            }, 300);
        }
    }

    hideModalDirectly(modalElement) {
        modalElement.classList.remove('show');
        modalElement.style.display = 'none';
        modalElement.setAttribute('aria-hidden', 'true');
        modalElement.removeAttribute('aria-modal');
        this.cleanupModalBackdrops();
    }

    cleanupModalBackdrops() {
        // Remove all modal backdrops
        const backdrops = document.querySelectorAll('.modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
        
        // Remove modal-open class from body if no modals are visible
        const visibleModals = document.querySelectorAll('.modal.show');
        if (visibleModals.length === 0) {
            document.body.classList.remove('modal-open');
            document.body.style.overflow = '';
            document.body.style.paddingRight = '';
        }
    }

    // UI State Management
    showLoadingState() {
        const loadingState = document.getElementById('loadingState');
        if (loadingState) {
            loadingState.style.display = 'block';
        }
    }

    hideLoadingState() {
        const loadingState = document.getElementById('loadingState');
        if (loadingState) {
            loadingState.style.display = 'none';
        }
    }

    showSaveLoading() {
        const saveBtn = document.getElementById('saveMenuBtn');
        const saveText = document.getElementById('saveButtonText');
        
        if (saveBtn && saveText) {
            saveBtn.disabled = true;
            saveText.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Enregistrement...';
        }
    }

    hideSaveLoading() {
        const saveBtn = document.getElementById('saveMenuBtn');
        const saveText = document.getElementById('saveButtonText');
        
        if (saveBtn && saveText) {
            saveBtn.disabled = false;
            saveText.textContent = 'Enregistrer';
        }
    }

    getEmptyStateHTML() {
        return `
            <div class="col-12 text-center py-5">
                <i class="fas fa-clipboard-list fa-4x text-muted mb-3"></i>
                <h5 class="text-muted">Aucun menu trouvé</h5>
                <p class="text-muted">Commencez par créer votre premier menu</p>
                <button class="btn btn-primary" onclick="enhancedMenuManager.showCreateModal()">
                    <i class="fas fa-plus"></i> Créer un menu
                </button>
            </div>
        `;
    }

    updateContextInfo() {
        const contextInfo = document.getElementById('contextInfo');
        const contextText = document.getElementById('contextText');
        
        if (this.currentFilter !== 'all' && contextInfo && contextText) {
            const text = this.getContextText();
            contextText.textContent = text;
            contextInfo.classList.remove('d-none');
        } else if (contextInfo) {
            contextInfo.classList.add('d-none');
        }
    }

    getContextText() {
        const texts = {
            'normal': 'Affichage des menus permanents uniquement',
            'daily-marocain': 'Affichage des menus du jour de cuisine marocaine',
            'daily-italien': 'Affichage des menus du jour de cuisine italienne',
            'daily-international': 'Affichage des menus du jour de cuisine internationale'
        };
        
        return texts[this.currentFilter] || '';
    }

    // Notification Methods
    showSuccess(message) {
        console.log('✅ Success:', message);
        // TODO: Implement toast notification
    }

    showError(message) {
        console.error('❌ Error:', message);
        alert(message); // Temporary implementation
    }

    // Utility function for debouncing
    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Dish Selection Implementation
    showDishSelectionModal(courseFilter = null) {
        console.log('🍽️ Opening dish selection modal for course:', courseFilter);
        this.currentCourseFilter = courseFilter;
        
        // Update course filter badge
        const badge = document.getElementById('courseFilterBadge');
        if (courseFilter && badge) {
            badge.textContent = this.getCourseName(courseFilter);
            badge.classList.remove('d-none');
        } else if (badge) {
            badge.classList.add('d-none');
        }
        
        // Load and render available dishes
        const cuisine = this.currentMenuType === 'menu_du_jour' ? document.getElementById('menuCuisine')?.value : null;
        const course = courseFilter;
        
        this.loadAvailableDishes(cuisine, course).then(() => {
            this.renderAvailableDishes();
        }).catch(error => {
            console.error('Error loading dishes for modal:', error);
            this.renderAvailableDishes(); // Fallback to existing dishes
        });
        
        // Setup dish selection event listeners
        this.setupDishSelectionEvents();
        
        // Show the modal
        this.showModal('dishSelectionModal');
    }

    renderAvailableDishes() {
        const container = document.getElementById('availableDishesGrid');
        if (!container) {
            console.error('❌ availableDishesGrid container not found');
            return;
        }

        let dishes = [...this.availableDishes]; // Copy the array
        
        console.log('🔍 All available dishes:', dishes);
        console.log('🔍 Current course filter:', this.currentCourseFilter);
        console.log('🔍 Current menu type:', this.currentMenuType);

        // Apply course filter for daily menus
        if (this.currentMenuType === 'menu_du_jour' && this.currentCourseFilter) {
            dishes = dishes.filter(dish => {
                const matches = dish.category && dish.category.nom === this.currentCourseFilter;
                console.log(`🔍 Dish ${dish.nom} matches course ${this.currentCourseFilter}:`, matches);
                return matches;
            });
        }

        // Note: We don't filter by cuisine since dishes aren't organized by cuisine
        // They are filtered by status and categories only
        // For daily menus, any active dish can be used regardless of the menu's cuisine type

        console.log('🍽️ Filtered dishes for display:', dishes);

        if (dishes.length === 0) {
            container.innerHTML = `
                <div class="col-12 text-center py-4">
                    <i class="fas fa-utensils fa-3x text-muted mb-3"></i>
                    <p class="text-muted">Aucun plat disponible pour cette sélection</p>
                    ${this.currentCourseFilter ? `<small class="text-muted">Cours: ${this.getCourseName(this.currentCourseFilter)}</small>` : ''}
                </div>
            `;
            return;
        }

        container.innerHTML = dishes.map(dish => this.createDishCard(dish)).join('');
        
        // Setup click handlers for dish cards
        this.setupDishCardEvents();
    }

    createDishCard(dish) {
        const isSelected = this.selectedDishes.some(d => d.id === dish.id);
        
        return `
            <div class="col-md-6 col-lg-4">
                <div class="available-dish-card ${isSelected ? 'selected' : ''}" data-dish-id="${dish.id}">
                    <div class="d-flex align-items-center">
                        <img src="${dish.image || '/image/default-dish.jpg'}" alt="${dish.nom}" class="dish-image me-3">
                        <div class="flex-grow-1">
                            <h6 class="mb-1">${dish.nom}</h6>
                            <p class="text-muted small mb-1">${dish.prix}€</p>
                            <span class="badge ${this.getCategoryBadge(dish.category)}">${dish.category?.nom || 'Sans catégorie'}</span>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input dish-checkbox" type="checkbox" 
                                   data-dish-id="${dish.id}" ${isSelected ? 'checked' : ''}>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    setupDishSelectionEvents() {
        // Search functionality
        const searchInput = document.getElementById('dishSearch');
        if (searchInput) {
            searchInput.addEventListener('input', this.debounce((e) => {
                this.filterDishesDisplay(e.target.value);
            }, 300));
        }

        // Category filter
        const categoryFilter = document.getElementById('dishCategoryFilter');
        if (categoryFilter) {
            categoryFilter.addEventListener('change', (e) => {
                this.filterDishesByCategory(e.target.value);
            });
        }

        // Note: Cuisine filter removed since dishes aren't organized by cuisine
    }

    setupDishCardEvents() {
        // Handle dish card clicks (both card and checkbox)
        document.querySelectorAll('.available-dish-card').forEach(card => {
            const dishId = parseInt(card.dataset.dishId);
            const checkbox = card.querySelector('.dish-checkbox');
            
            // Card click handler
            card.addEventListener('click', (e) => {
                // Don't trigger if clicking directly on checkbox
                if (e.target === checkbox) return;
                
                this.toggleDishSelection(dishId, card, checkbox);
            });
            
            // Checkbox change handler
            checkbox.addEventListener('change', (e) => {
                e.stopPropagation(); // Prevent card click
                this.toggleDishSelection(dishId, card, checkbox);
            });
        });
    }

    toggleDishSelection(dishId, card, checkbox) {
        const dish = this.availableDishes.find(d => d.id === dishId);
        if (!dish) return;

        const isCurrentlySelected = this.selectedDishes.some(d => d.id === dishId);
        
        if (this.currentMenuType === 'menu_du_jour' && this.currentCourseFilter) {
            // For daily menus with course filter, only allow one dish per course
            if (!isCurrentlySelected) {
                // Remove any existing dish from this course
                this.selectedDishes = this.selectedDishes.filter(d => 
                    !d.category || d.category.nom !== this.currentCourseFilter
                );
                
                // Add the new dish
                this.selectedDishes.push(dish);
                
                // Update all checkboxes in this modal (uncheck others, check this one)
                document.querySelectorAll('.dish-checkbox').forEach(cb => {
                    const cbDishId = parseInt(cb.dataset.dishId);
                    const cbCard = cb.closest('.available-dish-card');
                    
                    if (cbDishId === dishId) {
                        cb.checked = true;
                        cbCard.classList.add('selected');
                    } else {
                        cb.checked = false;
                        cbCard.classList.remove('selected');
                    }
                });
            } else {
                // Remove the dish
                this.selectedDishes = this.selectedDishes.filter(d => d.id !== dishId);
                checkbox.checked = false;
                card.classList.remove('selected');
            }
        } else {
            // For normal menus, allow multiple selections
            if (!isCurrentlySelected) {
                this.selectedDishes.push(dish);
                checkbox.checked = true;
                card.classList.add('selected');
            } else {
                this.selectedDishes = this.selectedDishes.filter(d => d.id !== dishId);
                checkbox.checked = false;
                card.classList.remove('selected');
            }
        }

        console.log('🍽️ Updated selected dishes:', this.selectedDishes);
        
        // Update the live preview in the background
        this.updateMenuComposition();
    }

    confirmDishSelection() {
        console.log('✅ Confirming dish selection:', this.selectedDishes);
        
        // Update the composition display
        this.updateMenuComposition();
        
        // Close the modal
        this.hideModal('dishSelectionModal');
        
        // Show success message
        const dishCount = this.selectedDishes.length;
        const message = dishCount === 1 ? '1 plat ajouté' : `${dishCount} plats ajoutés`;
        console.log(`✅ ${message}`);
    }

    // Filter methods for dish selection
    filterDishesDisplay(searchTerm) {
        if (!searchTerm || searchTerm.trim().length < 2) {
            this.renderAvailableDishes();
            return;
        }

        const cards = document.querySelectorAll('.available-dish-card');
        cards.forEach(card => {
            const dishName = card.querySelector('h6')?.textContent.toLowerCase() || '';
            const matches = dishName.includes(searchTerm.toLowerCase());
            card.parentElement.style.display = matches ? 'block' : 'none';
        });
    }

    filterDishesByCategory(categoryFilter) {
        if (!categoryFilter) {
            this.renderAvailableDishes();
            return;
        }

        const cards = document.querySelectorAll('.available-dish-card');
        cards.forEach(card => {
            const dishId = parseInt(card.dataset.dishId);
            const dish = this.availableDishes.find(d => d.id === dishId);
            const matches = dish && dish.category && dish.category.nom === categoryFilter;
            card.parentElement.style.display = matches ? 'block' : 'none';
        });
    }

    // Note: Cuisine filtering removed since dishes aren't organized by cuisine

    getCategoryBadge(category) {
        if (!category) return 'bg-secondary';
        
        const badgeMap = {
            'entree': 'bg-secondary',
            'plat_principal': 'jood-primary-bg', 
            'dessert': 'jood-warning-bg',
            'boisson': 'bg-info'
        };
        
        return badgeMap[category.nom] || 'bg-secondary';
    }

    // Placeholder methods for future implementation
    renderCalendarView() {
        console.log('Rendering calendar view');
        // TODO: Implement calendar view
    }

    renderListView(menus) {
        console.log('Rendering list view for', menus.length, 'menus');
        // TODO: Implement list view
    }

    performQuickSearch(query) {
        console.log('🔍 Quick search for:', query);
        if (!query || query.trim().length < 2) {
            // If query is empty or too short, reload all menus
            this.loadMenus();
            return;
        }

        // Filter current displayed menus by search query
        const currentCards = document.querySelectorAll('.menu-card');
        currentCards.forEach(card => {
            const menuName = card.querySelector('.card-title')?.textContent.toLowerCase() || '';
            const description = card.querySelector('.card-text')?.textContent.toLowerCase() || '';
            const searchTerm = query.toLowerCase();
            
            const matches = menuName.includes(searchTerm) || description.includes(searchTerm);
            card.parentElement.style.display = matches ? 'block' : 'none';
        });
    }

    populateCategoryFilter(categories) {
        const categoryFilter = document.getElementById('dishCategoryFilter');
        if (categoryFilter && categories) {
            // Clear existing options except first one
            while (categoryFilter.children.length > 1) {
                categoryFilter.removeChild(categoryFilter.lastChild);
            }
            
            // Add category options
            categories.forEach(category => {
                const option = document.createElement('option');
                option.value = category.nom;
                option.textContent = category.libelle || category.nom;
                categoryFilter.appendChild(option);
            });
        }
    }

    handleImagePreview(file) {
        const reader = new FileReader();
        reader.onload = function(e) {
            const placeholder = document.getElementById('imageUploadPlaceholder');
            const preview = document.getElementById('imagePreview');
            const previewImage = document.getElementById('previewImage');
            
            if (placeholder && preview && previewImage) {
                placeholder.classList.add('d-none');
                preview.classList.remove('d-none');
                previewImage.src = e.target.result;
                
                // Handle remove image button
                const removeBtn = document.getElementById('removeImage');
                if (removeBtn) {
                    removeBtn.onclick = function() {
                        placeholder.classList.remove('d-none');
                        preview.classList.add('d-none');
                        document.getElementById('menuImage').value = '';
                    };
                }
            }
        };
        reader.readAsDataURL(file);
    }

    removeDish(dishId) {
        this.selectedDishes = this.selectedDishes.filter(d => d.id !== dishId);
        this.updateMenuComposition();
    }

    getCourseName(course) {
        const names = {
            'entree': 'Entrée',
            'plat_principal': 'Plat Principal', 
            'dessert': 'Dessert'
        };
        return names[course] || course;
    }

    fillMenuForm(menu) {
        console.log('📝 Filling menu form with:', menu);
        
        // Basic fields
        const fields = {
            'menuNom': menu.nom || '',
            'menuPrix': menu.prix || '',
            'menuDescription': menu.description || '',
        };
        
        Object.entries(fields).forEach(([fieldId, value]) => {
            const field = document.getElementById(fieldId);
            if (field) {
                field.value = value;
            }
        });
        
        // Checkbox
        const actifCheckbox = document.getElementById('menuActif');
        if (actifCheckbox) {
            actifCheckbox.checked = menu.actif || false;
        }

        // Menu type
        if (menu.type === 'menu_du_jour') {
            const dailyRadio = document.getElementById('dailyMenuType');
            if (dailyRadio) {
                dailyRadio.checked = true;
            }
            
            const dateField = document.getElementById('menuDate');
            if (dateField) {
                dateField.value = menu.date || '';
            }
            
            const cuisineField = document.getElementById('menuCuisine');
            if (cuisineField) {
                cuisineField.value = menu.tag || '';
            }
        } else {
            const normalRadio = document.getElementById('normalMenuType');
            if (normalRadio) {
                normalRadio.checked = true;
            }
            
            const tagField = document.getElementById('menuTag');
            if (tagField) {
                tagField.value = menu.tag || '';
            }
        }

        // Handle menu type change FIRST (sets up the UI structure)
        this.handleMenuTypeChange(menu.type || 'normal');
        
        // Load menu dishes AFTER the UI is set up
        if (menu.dishes && menu.dishes.length > 0) {
            console.log('📝 Loading dishes for editing:', menu.dishes);
            this.selectedDishes = menu.dishes;
            
            // Update the composition display immediately
            this.updateMenuComposition();
        } else {
            // Clear dishes if none exist
            this.selectedDishes = [];
            this.updateMenuComposition();
        }
    }

    async duplicateMenu(id) {
        console.log('🔄 Duplicating menu with ID:', id);
        
        try {
            // In a real implementation, we'd fetch from API
            // For now, use mock data
            const mockMenus = this.getMockMenus();
            const originalMenu = mockMenus.find(m => m.id === id);
            
            if (originalMenu) {
                this.editingMenu = null;
                
                // Fill form with duplicated data
                this.fillMenuForm({
                    ...originalMenu,
                    nom: originalMenu.nom + ' (Copie)',
                    date: null // Clear date for daily menus
                });
                
                this.setModalTitle('Dupliquer le Menu');
                this.showModal('menuModal');
            } else {
                this.showError('Menu introuvable');
            }
        } catch (error) {
            console.error('Error duplicating menu:', error);
            this.showError('Erreur lors de la duplication du menu');
        }
    }

    // Mock data methods for testing
    getMockMenus(filters = {}) {
        const allMenus = [
            {
                id: 1,
                nom: "Menu Complet Traditionnel",
                type: "normal",
                tag: "familial",
                prix: 35.00,
                description: "Menu complet avec entrée, plat principal et dessert",
                actif: true,
                dishCount: 3,
                dishes: [
                    { id: 1, nom: "Salade Méchouia", category: { nom: "entree" } },
                    { id: 2, nom: "Couscous Royal", category: { nom: "plat_principal" } },
                    { id: 3, nom: "Makroudh", category: { nom: "dessert" } }
                ]
            },
            {
                id: 2,
                nom: "Menu du Jour",
                type: "menu_du_jour",
                tag: "marocain",
                prix: 20.00,
                description: "Spécialité du chef du jour",
                actif: true,
                date: "2025-01-19",
                dishCount: 1,
                dishes: [
                    { id: 4, nom: "Tajine de Poulet aux Olives", category: { nom: "plat_principal" } }
                ]
            },
            {
                id: 3,
                nom: "Menu Italien du Jour",
                type: "menu_du_jour",
                tag: "italien",
                prix: 22.50,
                description: "Saveurs italiennes authentiques",
                actif: true,
                date: "2025-01-19",
                dishCount: 3,
                dishes: [
                    { id: 5, nom: "Bruschetta", category: { nom: "entree" } },
                    { id: 6, nom: "Spaghetti Carbonara", category: { nom: "plat_principal" } },
                    { id: 7, nom: "Tiramisu", category: { nom: "dessert" } }
                ]
            },
            {
                id: 4,
                nom: "Menu Découverte",
                type: "normal",
                tag: "decouverte",
                prix: 24.90,
                description: "Parfait pour découvrir nos saveurs",
                actif: true,
                dishCount: 2,
                dishes: [
                    { id: 8, nom: "Pastilla", category: { nom: "entree" } },
                    { id: 9, nom: "Tagine d'Agneau", category: { nom: "plat_principal" } }
                ]
            }
        ];

        // Apply filters
        let filteredMenus = allMenus;

        if (filters.type) {
            filteredMenus = filteredMenus.filter(menu => menu.type === filters.type);
        }

        if (filters.tag) {
            filteredMenus = filteredMenus.filter(menu => menu.tag === filters.tag);
        }

        return filteredMenus;
    }

    getMockDishes() {
        return [
            { id: 1, nom: "Salade Méchouia", prix: 8.50, category: { nom: "entree" }, cuisine: "marocain" },
            { id: 2, nom: "Couscous Royal", prix: 18.00, category: { nom: "plat_principal" }, cuisine: "marocain" },
            { id: 3, nom: "Makroudh", prix: 6.00, category: { nom: "dessert" }, cuisine: "marocain" },
            { id: 4, nom: "Tajine de Poulet", prix: 16.50, category: { nom: "plat_principal" }, cuisine: "marocain" },
            { id: 5, nom: "Bruschetta", prix: 7.00, category: { nom: "entree" }, cuisine: "italien" },
            { id: 6, nom: "Spaghetti Carbonara", prix: 14.00, category: { nom: "plat_principal" }, cuisine: "italien" },
            { id: 7, nom: "Tiramisu", prix: 6.50, category: { nom: "dessert" }, cuisine: "italien" },
            { id: 8, nom: "Pastilla", prix: 9.00, category: { nom: "entree" }, cuisine: "marocain" },
            { id: 9, nom: "Tagine d'Agneau", prix: 19.00, category: { nom: "plat_principal" }, cuisine: "marocain" }
        ];
    }

    getMockCategories() {
        return [
            { id: 1, nom: "entree", libelle: "Entrées" },
            { id: 2, nom: "plat_principal", libelle: "Plats Principaux" },
            { id: 3, nom: "dessert", libelle: "Desserts" },
            { id: 4, nom: "boisson", libelle: "Boissons" }
        ];
    }
}

// Initialize the enhanced menu manager when DOM is loaded
document.addEventListener('DOMContentLoaded', () => {
    console.log('🚀 DOM Content Loaded - Starting Enhanced Menu Manager initialization...');
    
    // Prevent multiple instances
    if (window.enhancedMenuManager) {
        console.log('⚠️ Enhanced Menu Manager already exists, skipping initialization');
        return;
    }
    
    // Debug: Check what's available
    console.log('🔍 Checking available systems:');
    console.log('- window.coreui:', typeof window.coreui);
    console.log('- coreui global:', typeof coreui);
    console.log('- MenuAPI:', typeof MenuAPI);
    
    // Check if we're on the menu page
    const menuGrid = document.getElementById('menusGrid');
    if (!menuGrid) {
        console.log('❌ Not on menu page - MenuGrid not found');
        return;
    }
    
    console.log('✅ Menu page detected - initializing Enhanced Menu Manager');
    
    // Wait for CoreUI to be fully loaded
    function waitForCoreUI(callback, attempts = 0) {
        if (attempts > 20) {
            console.warn('⚠️ CoreUI timeout - proceeding anyway');
            callback();
            return;
        }
        
        if (typeof window.coreui !== 'undefined' || typeof coreui !== 'undefined') {
            console.log('✅ CoreUI detected - proceeding with initialization');
            callback();
        } else {
            console.log(`⏳ Waiting for CoreUI... (attempt ${attempts + 1})`);
            setTimeout(() => waitForCoreUI(callback, attempts + 1), 100);
        }
    }
    
    waitForCoreUI(function() {
        try {
            window.enhancedMenuManager = new EnhancedMenuManager();
            console.log('✅ Enhanced Menu Manager initialized successfully');
        } catch (error) {
            console.error('❌ Error initializing Enhanced Menu Manager:', error);
        }
    });
}); 