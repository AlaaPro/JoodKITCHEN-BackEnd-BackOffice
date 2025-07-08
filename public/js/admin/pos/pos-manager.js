/**
 * JoodKitchen POS Manager - Enterprise Point of Sale System
 * Professional implementation with real-time features and touch optimization
 * 
 * @author Professional Developer Team
 * @version 2.0.0
 * @license Commercial
 */

class PosManager {
    constructor(statusConfig) {
        if (!statusConfig) {
            throw new Error('OrderStatus configuration is required');
        }
        
        this.statusConfig = statusConfig;
        console.log('🚀 Initializing JoodKitchen POS Manager v1.0.0 - Simplified...');
        
        // Core properties
        this.apiBaseUrl = '/api/pos';
        this.menuCategories = [];
        this.categories = []; // For advanced filtering
        this.currentCategory = 'daily'; // Start with daily menus
        this.filters = {
            category: '',
            minPrice: '',
            maxPrice: '',
            search: '',
            popular: false,
            vegetarian: false
        };
        this.currentOrder = {
            items: [],
            customer: null,
            type: 'sur_place',
            subtotal: 0,
            tax: 0,
            discount: 0,
            total: 0,
            payment_method: 'cash'
        };
        
        // Initialize API client (use global AdminAPI instance)
        if (typeof AdminAPI !== 'undefined') {
            this.api = AdminAPI;
        } else if (window.AdminAPI) {
            this.api = window.AdminAPI;
        } else {
            console.error('❌ AdminAPI not found! Make sure api.js is loaded.');
            throw new Error('AdminAPI not available');
        }
        console.log('✅ AdminAPI instance initialized for POS');
        
        // Touch detection
        this.isTouchDevice = 'ontouchstart' in window || navigator.maxTouchPoints > 0;
        
        // Initialize components
        this.initializeComponents();
        this.loadInitialData();
        this.updateCurrentTime();
        
        console.log('✅ POS Manager initialized successfully');
    }

    /**
     * Initialize all POS components
     */
    initializeComponents() {
        this.initializeEventListeners();
        this.initializeModals();
        this.initializeTouch();
        this.initializeCustomerSearch();
        this.setupOrderTypeHandlers();
        this.setupPaymentHandlers();
    }

    /**
     * Initialize event listeners
     */
    initializeEventListeners() {
        // Category tab clicks
        document.addEventListener('click', (e) => {
            if (e.target.closest('.pos-category-tab')) {
                const tab = e.target.closest('.pos-category-tab');
                const category = tab.getAttribute('data-category');
                this.switchCategory(category);
            }
        });

        // Item card clicks
        document.addEventListener('click', (e) => {
            if (e.target.closest('.pos-item-card')) {
                const card = e.target.closest('.pos-item-card');
                if (!card.classList.contains('unavailable')) {
                    const itemData = JSON.parse(card.getAttribute('data-item'));
                    this.addItemToOrder(itemData);
                }
            }
        });

        // Quantity control buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('.pos-qty-btn')) {
                const btn = e.target.closest('.pos-qty-btn');
                const action = btn.getAttribute('data-action');
                const itemIndex = parseInt(btn.getAttribute('data-index'));
                
                if (action === 'increase') {
                    this.increaseQuantity(itemIndex);
                } else if (action === 'decrease') {
                    this.decreaseQuantity(itemIndex);
                } else if (action === 'remove') {
                    this.removeItem(itemIndex);
                }
            }
        });

        // Finalize order button
        document.getElementById('finalizeOrderBtn').addEventListener('click', () => {
            this.finalizeOrder();
        });

        // New order button (in success modal)
        document.getElementById('newOrderBtn').addEventListener('click', () => {
            this.startNewOrder();
        });

        // Print receipt button
        document.getElementById('printReceiptBtn').addEventListener('click', () => {
            this.printReceipt();
        });

        // Order history button
        document.getElementById('btnOrderHistory').addEventListener('click', () => {
            this.showOrderHistory();
        });

        // Refresh menu button
        document.getElementById('btnRefreshMenu').addEventListener('click', () => {
            this.loadMenuCategories();
        });

        // Listen for window resize events (triggered by fullscreen toggle)
        window.addEventListener('resize', () => {
            this.handleLayoutResize();
        });

        // Advanced filter event listeners
        document.getElementById('categoryFilter').addEventListener('change', () => this.applyFilters());
        // Note: status filter removed - only showing available items
        document.getElementById('minPriceFilter').addEventListener('input', () => this.applyFilters());
        document.getElementById('maxPriceFilter').addEventListener('input', () => this.applyFilters());
        document.getElementById('searchInput').addEventListener('input', () => {
            clearTimeout(this.filterTimeout);
            this.filterTimeout = setTimeout(() => this.applyFilters(), 300);
        });
        document.getElementById('popularFilter').addEventListener('change', () => this.applyFilters());
        document.getElementById('vegetarianFilter').addEventListener('change', () => this.applyFilters());
        
        // Filter buttons
        document.getElementById('clearFilters').addEventListener('click', () => this.clearFilters());
        document.getElementById('applyFilters').addEventListener('click', () => this.applyFilters());

        // Update status change handler to use OrderStatus
        document.getElementById('orderStatusSelect')?.addEventListener('change', async (e) => {
            const newStatus = e.target.value;
            const orderId = e.target.dataset.orderId;
            
            try {
                await OrderStatus.init(); // Ensure status config is loaded
                const statusConfig = OrderStatus.config[newStatus];
                
                if (statusConfig) {
                    // Update order status using existing updateOrderStatus method
                    await this.updateOrderStatus(orderId, newStatus);
                    
                    // Show notification using status config
                    this.showNotification(
                        statusConfig.notification.message,
                        statusConfig.notification.type
                    );
                }
            } catch (error) {
                console.error('Failed to update order status:', error);
                this.showNotification('Erreur lors de la mise à jour du statut', 'error');
            }
        });
    }

    /**
     * Initialize CoreUI/Bootstrap modals with better conflict handling
     */
    initializeModals() {
        console.log('🔧 Initializing modals with conflict resolution...');
        
        try {
            // Enhanced modal initialization with better conflict detection
            this.modalInstances = {};
            
            const modalIds = ['customerSearchModal', 'newCustomerModal', 'orderSuccessModal', 'orderHistoryModal'];
            
            modalIds.forEach(modalId => {
                const modalElement = document.getElementById(modalId);
                if (!modalElement) {
                    console.warn(`⚠️ Modal element not found: ${modalId}`);
                    return;
                }
                
                try {
                    // Try CoreUI first
                    if (typeof coreui !== 'undefined' && coreui.Modal) {
                        this.modalInstances[modalId] = new coreui.Modal(modalElement, {
                            backdrop: 'static',
                            keyboard: true
                        });
                        console.log(`✅ ${modalId} initialized with CoreUI`);
                    }
                    // Fallback to Bootstrap
                    else if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                        this.modalInstances[modalId] = new bootstrap.Modal(modalElement, {
                            backdrop: 'static',
                            keyboard: true
                        });
                        console.log(`✅ ${modalId} initialized with Bootstrap`);
                    }
                    // DOM fallback
                    else {
                        this.modalInstances[modalId] = this.createModalFallback(modalElement);
                        console.log(`✅ ${modalId} initialized with DOM fallback`);
                    }
                } catch (error) {
                    console.error(`❌ Error initializing ${modalId}:`, error);
                    this.modalInstances[modalId] = this.createModalFallback(modalElement);
                }
            });
            
            // Set references for backward compatibility
            this.customerSearchModal = this.modalInstances.customerSearchModal;
            this.newCustomerModal = this.modalInstances.newCustomerModal;
            this.orderSuccessModal = this.modalInstances.orderSuccessModal;
            this.orderHistoryModal = this.modalInstances.orderHistoryModal;
            
            console.log('✅ All modals initialized successfully');
            
        } catch (error) {
            console.error('❌ Critical error in modal initialization:', error);
            this.initializeModalsFallback();
        }
        
        // New customer form handler
        document.getElementById('newCustomerForm').addEventListener('submit', (e) => {
            e.preventDefault();
            this.createNewCustomer();
        });

        // Order history search and filter handlers
        document.getElementById('orderHistorySearch').addEventListener('input', (e) => {
            clearTimeout(this.historySearchTimeout);
            this.historySearchTimeout = setTimeout(() => {
                this.loadOrderHistory();
            }, 500);
        });

        document.getElementById('orderHistoryFilter').addEventListener('change', () => {
            this.loadOrderHistory();
        });

        document.getElementById('refreshHistoryBtn').addEventListener('click', () => {
            this.loadOrderHistory();
        });
    }

    /**
     * Create a modal fallback object for DOM manipulation
     */
    createModalFallback(modalElement) {
        return {
            show: () => this.showModalFallback(modalElement.id),
            hide: () => this.hideModalFallback(modalElement.id),
            element: modalElement
        };
    }

    /**
     * Show modal using DOM fallback
     */
    showModalFallback(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        // Check if in fullscreen mode and adjust z-index
        const isFullscreen = document.body.classList.contains('pos-fullscreen');
        if (isFullscreen) {
            modal.style.zIndex = '100000';
            console.log('🖥️ Modal z-index adjusted for fullscreen mode');
        }
        
        modal.classList.add('show');
        modal.style.display = 'block';
        modal.setAttribute('aria-modal', 'true');
        modal.removeAttribute('aria-hidden');
        document.body.classList.add('modal-open');
        
        // Add backdrop with appropriate z-index
        if (!document.querySelector('.modal-backdrop')) {
            const backdrop = document.createElement('div');
            backdrop.className = 'modal-backdrop fade show';
            if (isFullscreen) {
                backdrop.style.zIndex = '99998';
            }
            document.body.appendChild(backdrop);
        }
    }

    /**
     * Hide modal using DOM fallback
     */
    hideModalFallback(modalId) {
        const modal = document.getElementById(modalId);
        if (!modal) return;
        
        modal.classList.remove('show');
        modal.style.display = 'none';
        modal.setAttribute('aria-hidden', 'true');
        modal.removeAttribute('aria-modal');
        
        // Reset z-index if it was modified for fullscreen
        modal.style.removeProperty('z-index');
        
        document.body.classList.remove('modal-open');
        
        // Remove backdrop
        const backdrop = document.querySelector('.modal-backdrop');
        if (backdrop) {
            backdrop.remove();
        }
        
        // Only remove padding if not in fullscreen mode
        const isFullscreen = document.body.classList.contains('pos-fullscreen');
        if (!isFullscreen) {
            document.body.style.removeProperty('padding-right');
        }
    }

    /**
     * Fallback modal implementation using native DOM
     */
    initializeModalsFallback() {
        console.log('🔧 Initializing modal fallback...');
        
        // Create simple modal objects with show/hide methods
        this.customerSearchModal = {
            show: () => {
                const modal = document.getElementById('customerSearchModal');
                modal.classList.add('show');
                modal.style.display = 'block';
                document.body.classList.add('modal-open');
            },
            hide: () => {
                const modal = document.getElementById('customerSearchModal');
                modal.classList.remove('show');
                modal.style.display = 'none';
                document.body.classList.remove('modal-open');
            }
        };

        this.newCustomerModal = {
            show: () => {
                const modal = document.getElementById('newCustomerModal');
                modal.classList.add('show');
                modal.style.display = 'block';
                document.body.classList.add('modal-open');
            },
            hide: () => {
                const modal = document.getElementById('newCustomerModal');
                modal.classList.remove('show');
                modal.style.display = 'none';
                document.body.classList.remove('modal-open');
            }
        };

        this.orderSuccessModal = {
            show: () => {
                const modal = document.getElementById('orderSuccessModal');
                modal.classList.add('show');
                modal.style.display = 'block';
                document.body.classList.add('modal-open');
            },
            hide: () => {
                const modal = document.getElementById('orderSuccessModal');
                modal.classList.remove('show');
                modal.style.display = 'none';
                document.body.classList.remove('modal-open');
            }
        };

        this.orderHistoryModal = {
            show: () => {
                const modal = document.getElementById('orderHistoryModal');
                modal.classList.add('show');
                modal.style.display = 'block';
                document.body.classList.add('modal-open');
            },
            hide: () => {
                const modal = document.getElementById('orderHistoryModal');
                modal.classList.remove('show');
                modal.style.display = 'none';
                document.body.classList.remove('modal-open');
            }
        };

        // Add click handlers to close buttons
        document.querySelectorAll('[data-bs-dismiss="modal"]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const modal = e.target.closest('.modal');
                if (modal) {
                    modal.classList.remove('show');
                    modal.style.display = 'none';
                    document.body.classList.remove('modal-open');
                }
            });
        });

        console.log('✅ Modal fallback initialized');
    }

    /**
     * Initialize touch optimizations
     */
    initializeTouch() {
        if (this.isTouchDevice) {
            document.body.classList.add('touch-device');
            console.log('👆 Touch device detected - enabling touch optimizations');
        }
    }

    /**
     * Initialize customer search functionality
     */
    initializeCustomerSearch() {
        const customerSearchInput = document.getElementById('customerSearch');
        const modalSearchInput = document.getElementById('modalCustomerSearch');
        
        // Main search input - open modal on focus
        customerSearchInput.addEventListener('focus', () => {
            this.customerSearchModal.show();
            setTimeout(() => {
                modalSearchInput.focus();
            }, 300);
        });

        // Modal search input - real-time search
        let searchTimeout;
        modalSearchInput.addEventListener('input', (e) => {
            clearTimeout(searchTimeout);
            const query = e.target.value.trim();
            
            if (query.length >= 2) {
                searchTimeout = setTimeout(() => {
                    this.searchCustomers(query);
                }, 300);
            } else {
                this.showCustomerSearchPlaceholder();
            }
        });

        // New customer buttons
        document.getElementById('btnNewCustomer').addEventListener('click', () => {
            this.customerSearchModal.hide();
            this.newCustomerModal.show();
        });

        document.getElementById('btnCreateNewCustomer').addEventListener('click', () => {
            this.customerSearchModal.hide();
            this.newCustomerModal.show();
        });
    }

    /**
     * Setup order type handlers
     */
    setupOrderTypeHandlers() {
        document.querySelectorAll('.pos-order-type-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active class from all buttons
                document.querySelectorAll('.pos-order-type-btn').forEach(b => 
                    b.classList.remove('active'));
                
                // Add active class to clicked button
                btn.classList.add('active');
                
                // Update order type
                this.currentOrder.type = btn.getAttribute('data-type');
                console.log('📦 Order type changed to:', this.currentOrder.type);
                
                // Show/hide additional fields based on type
                this.handleOrderTypeChange();
            });
        });
    }

    /**
     * Setup payment method handlers
     */
    setupPaymentHandlers() {
        document.querySelectorAll('.pos-payment-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                // Remove active class from all buttons
                document.querySelectorAll('.pos-payment-btn').forEach(b => 
                    b.classList.remove('active'));
                
                // Add active class to clicked button
                btn.classList.add('active');
                
                // Update payment method
                this.currentOrder.payment_method = btn.getAttribute('data-method');
                console.log('💳 Payment method changed to:', this.currentOrder.payment_method);
            });
        });
    }

    /**
     * Load initial data
     */
    async loadInitialData() {
        try {
            console.log('📡 Loading initial POS data...');
            
            // Load menu categories and dish categories
            await this.loadMenuCategories();
            await this.loadDishCategories();
            
            console.log('✅ Initial data loaded successfully');
        } catch (error) {
            console.error('❌ Error loading initial data:', error);
            this.showMessage('Erreur lors du chargement des données', 'error');
        }
    }

    /**
     * Load dish categories for advanced filtering
     */
    async loadDishCategories() {
        try {
            const data = await this.api.request('GET', '/pos/categories');
            if (data.success) {
                this.categories = data.data;
                this.populateCategoryFilter();
                console.log('✅ Loaded categories for filtering:', this.categories.length);
            }
        } catch (error) {
            console.error('❌ Error loading dish categories:', error);
        }
    }

    /**
     * Populate category filter dropdown
     */
    populateCategoryFilter() {
        const select = document.getElementById('categoryFilter');
        const options = this.categories.map(cat => 
            `<option value="${cat.id}">${cat.nom}</option>`
        ).join('');
        select.innerHTML = '<option value="">Toutes catégories</option>' + options;
    }

    /**
     * Load menu categories
     */
    async loadMenuCategories() {
        try {
            const data = await this.api.request('GET', '/pos/menu/categories');
            
            if (data.success) {
                this.menuCategories = data.data;
                this.renderMenuItems();
                console.log(`📋 Loaded ${data.stats.total_categories} categories with ${data.stats.total_items} items`);
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('❌ Error loading menu categories:', error);
            document.getElementById('posMenuLoading').innerHTML = `
                <div class="text-center text-danger">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>
                    Erreur lors du chargement du menu<br>
                    <small>${error.message}</small>
                </div>
            `;
        }
    }

    /**
     * Show order history modal
     */
    showOrderHistory() {
        const today = new Date().toLocaleDateString('fr-FR', {
            weekday: 'long',
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        document.getElementById('historyDate').textContent = today;
        this.orderHistoryModal.show();
        this.loadOrderHistory();
    }

    /**
     * Load order history for today
     */
    async loadOrderHistory() {
        try {
            const search = document.getElementById('orderHistorySearch').value;
            const status = document.getElementById('orderHistoryFilter').value;
            
            const params = new URLSearchParams({
                date: new Date().toISOString().split('T')[0],
                search: search,
                status: status
            });
            
            const data = await this.api.request('GET', `/pos/orders/history?${params}`);
            
            if (data.success) {
                this.renderOrderHistory(data.data);
                console.log(`📋 Loaded ${data.count} orders for today`);
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('❌ Error loading order history:', error);
            document.getElementById('orderHistoryContent').innerHTML = `
                <div class="text-center text-danger py-4">
                    <i class="fas fa-exclamation-triangle fa-2x mb-2"></i><br>
                    Erreur lors du chargement de l'historique<br>
                    <small>${error.message}</small>
                </div>
            `;
        }
    }

    /**
     * Render order history
     */
    renderOrderHistory(orders) {
        const container = document.getElementById('orderHistoryContent');
        
        if (orders.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted py-4">
                    <i class="fas fa-inbox fa-3x mb-3"></i><br>
                    <h5>Aucune commande trouvée</h5>
                    <p>Aucune commande ne correspond aux critères de recherche.</p>
                </div>
            `;
            return;
        }
        
        const ordersHtml = orders.map(order => `
            <div class="card mb-3">
                <div class="card-body">
                    <div class="row align-items-center">
                        <div class="col-md-3">
                            <h6 class="mb-1">${order.order_number}</h6>
                            <small class="text-muted">${order.created_at}</small>
                        </div>
                        <div class="col-md-3">
                            <strong>${order.customer.name}</strong><br>
                            <small class="text-muted">
                                ${order.customer.email || order.customer.telephone || ''}
                            </small>
                        </div>
                        <div class="col-md-2">
                            <span class="badge bg-${this.getStatusBadgeColor(order.status)}">
                                ${order.status_label}
                            </span><br>
                            <small class="text-muted">${order.type_label}</small>
                        </div>
                        <div class="col-md-2">
                            <strong>${order.items_count} article${order.items_count > 1 ? 's' : ''}</strong><br>
                            <small class="text-muted">
                                ${order.items.slice(0, 2).map(item => item.nom).join(', ')}
                                ${order.items.length > 2 ? '...' : ''}
                            </small>
                        </div>
                        <div class="col-md-2 text-end">
                            <h5 class="mb-0 text-success">${order.total}€</h5>
                            <button class="btn btn-sm btn-outline-primary mt-1" 
                                    onclick="posManager.viewOrderDetails(${order.id})">
                                <i class="fas fa-eye"></i> Détails
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `).join('');
        
        container.innerHTML = ordersHtml;
    }

    /**
     * Get status badge color
     */
    getStatusBadgeColor(status) {
        const colorMap = {
            'en_attente': 'warning',
            'confirme': 'info',
            'en_preparation': 'primary',
            'pret': 'success',
            'livre': 'success',
            'annule': 'danger'
        };
        return colorMap[status] || 'secondary';
    }

    /**
     * View order details (placeholder for future implementation)
     */
    viewOrderDetails(orderId) {
        console.log('👁️ View order details:', orderId);
        this.showMessage('Détails de commande - fonctionnalité bientôt disponible', 'success');
    }

    /**
     * Update current time display
     */
    updateCurrentTime() {
        const now = new Date();
        const timeString = now.toLocaleTimeString('fr-FR', {
            hour: '2-digit',
            minute: '2-digit'
        });
        const dateString = now.toLocaleDateString('fr-FR', {
            weekday: 'long',
            day: 'numeric',
            month: 'long'
        });
        
        document.getElementById('posCurrentTime').textContent = `${dateString} • ${timeString}`;
    }

    /**
     * Render category tabs
     */
    renderCategoryTabs() {
        const container = document.getElementById('posCategoryTabs');
        
        // Keep the default "Tout" tab and add dynamic categories
        const dynamicTabs = this.menuCategories.map(category => `
            <button class="pos-category-tab" data-category="${category.id}">
                <span class="pos-category-icon">${category.icon}</span>
                <span>${category.nom}</span>
                <span class="badge bg-secondary ms-1">${category.items.length}</span>
            </button>
        `).join('');
        
        // Find existing tabs and add new ones
        const existingTabs = container.innerHTML;
        container.innerHTML = existingTabs + dynamicTabs;
    }

    /**
     * Switch category
     */
    switchCategory(categoryId) {
        // Update active tab
        document.querySelectorAll('.pos-category-tab').forEach(tab => {
            tab.classList.remove('active');
        });
        document.querySelector(`[data-category="${categoryId}"]`).classList.add('active');
        
        this.currentCategory = categoryId;
        
        // Show/hide advanced filters based on category
        const advancedFilters = document.getElementById('posAdvancedFilters');
        if (categoryId === 'plats') {
            advancedFilters.style.display = 'block';
        } else {
            advancedFilters.style.display = 'none';
            this.clearFilters();
        }
        
        this.renderMenuItems();
        
        console.log('🏷️ Switched to category:', categoryId);
    }

    /**
     * Render menu items
     */
    renderMenuItems() {
        const container = document.getElementById('posItemsGrid');
        const loadingElement = document.getElementById('posMenuLoading');
        
        let itemsToShow = [];
        
        // Filter items based on current category
        if (this.currentCategory === 'daily') {
            // Show only daily menus
            const dailyCategory = this.menuCategories.find(cat => cat.id === 'daily_menus');
            if (dailyCategory) {
                itemsToShow = dailyCategory.items;
            }
        } else if (this.currentCategory === 'normal') {
            // Show only normal menus
            const normalCategory = this.menuCategories.find(cat => cat.id === 'normal_menus');
            if (normalCategory) {
                itemsToShow = normalCategory.items;
            }
        } else if (this.currentCategory === 'plats') {
            // Show all dishes from all dish categories
            this.menuCategories.forEach(category => {
                if (category.id.startsWith('category_')) {
                    itemsToShow.push(...category.items);
                }
            });
            
            // Apply advanced filters if in plats mode
            itemsToShow = this.applyAdvancedFilters(itemsToShow);
        }
        
        // Render items
        if (itemsToShow.length === 0) {
            container.innerHTML = `
                <div class="col-12 text-center text-muted py-4">
                    <i class="fas fa-inbox fa-3x mb-3"></i><br>
                    <h5>Aucun article disponible</h5>
                    <p>Cette catégorie ne contient pas d'articles disponibles pour le moment.</p>
                </div>
            `;
        } else {
            container.innerHTML = itemsToShow.map(item => this.renderItemCard(item)).join('');
        }
        
        // Hide loading, show items
        loadingElement.style.display = 'none';
        container.style.display = 'grid';
        
        // Add animation
        container.classList.add('pos-fadeIn');
        setTimeout(() => container.classList.remove('pos-fadeIn'), 300);
    }

    /**
     * Apply advanced filters to dishes
     */
    applyAdvancedFilters(items) {
        let filteredItems = [...items];

        // Category filter - use category_id for plats
        if (this.filters.category) {
            filteredItems = filteredItems.filter(item => 
                item.category_id && item.category_id.toString() === this.filters.category
            );
        }

        // Note: Status filter removed - only showing available items by default

        // Price filters
        if (this.filters.minPrice) {
            filteredItems = filteredItems.filter(item => 
                parseFloat(item.prix) >= parseFloat(this.filters.minPrice)
            );
        }
        if (this.filters.maxPrice) {
            filteredItems = filteredItems.filter(item => 
                parseFloat(item.prix) <= parseFloat(this.filters.maxPrice)
            );
        }

        // Search filter
        if (this.filters.search) {
            const searchTerm = this.filters.search.toLowerCase();
            filteredItems = filteredItems.filter(item => 
                item.nom.toLowerCase().includes(searchTerm) ||
                (item.description && item.description.toLowerCase().includes(searchTerm))
            );
        }

        // Popular filter
        if (this.filters.popular) {
            filteredItems = filteredItems.filter(item => item.populaire);
        }

        // Vegetarian filter
        if (this.filters.vegetarian) {
            filteredItems = filteredItems.filter(item => item.vegetarien);
        }

        console.log(`🔍 Applied filters: ${filteredItems.length}/${items.length} items remaining`);
        return filteredItems;
    }

    /**
     * Update filters from form inputs
     */
    updateFilters() {
        this.filters.category = document.getElementById('categoryFilter').value;
        // Note: status filter removed - only showing available items
        this.filters.minPrice = document.getElementById('minPriceFilter').value;
        this.filters.maxPrice = document.getElementById('maxPriceFilter').value;
        this.filters.search = document.getElementById('searchInput').value;
        this.filters.popular = document.getElementById('popularFilter').checked;
        this.filters.vegetarian = document.getElementById('vegetarianFilter').checked;
    }

    /**
     * Apply filters
     */
    applyFilters() {
        if (this.currentCategory === 'plats') {
            this.updateFilters();
            this.renderMenuItems();
        }
    }

    /**
     * Clear all filters
     */
    clearFilters() {
        document.getElementById('categoryFilter').value = '';
        // Note: status filter removed
        document.getElementById('minPriceFilter').value = '';
        document.getElementById('maxPriceFilter').value = '';
        document.getElementById('searchInput').value = '';
        document.getElementById('popularFilter').checked = false;
        document.getElementById('vegetarianFilter').checked = false;
        
        this.filters = {
            category: '',
            minPrice: '',
            maxPrice: '',
            search: '',
            popular: false,
            vegetarian: false
        };
        
        if (this.currentCategory === 'plats') {
            this.renderMenuItems();
        }
    }

    /**
     * Render individual item card
     */
    renderItemCard(item) {
        const badges = [];
        
        if (item.populaire) {
            badges.push('<span class="pos-item-badge pos-badge-popular">⭐ Populaire</span>');
        }
        if (item.vegetarien) {
            badges.push('<span class="pos-item-badge pos-badge-vegetarian">🌱 Végétarien</span>');
        }
        
        const imageContent = item.image 
            ? `background-image: url('${item.image}')` 
            : '';
        
        const imageIcon = !item.image 
            ? (item.type === 'menu' ? '🍽️' : '🍴')
            : '';
            
        return `
            <div class="pos-item-card ${!item.available ? 'unavailable' : ''}" 
                 data-item='${JSON.stringify(item)}'>
                <div class="pos-item-image" style="${imageContent}">
                    ${imageIcon}
                </div>
                <div class="pos-item-badges">
                    ${badges.join('')}
                </div>
                <div class="pos-item-name">${item.nom}</div>
                <div class="pos-item-description">${item.description || ''}</div>
                <div class="pos-item-price">${item.prix}€</div>
                ${!item.available ? '<div class="text-center mt-2"><small class="text-danger">Non disponible</small></div>' : ''}
            </div>
        `;
    }

    /**
     * Add item to order
     */
    addItemToOrder(item) {
        // Check if item already exists in order
        const existingIndex = this.currentOrder.items.findIndex(orderItem => 
            orderItem.type === item.type && orderItem.id === item.id
        );
        
        if (existingIndex !== -1) {
            // Increase quantity of existing item
            this.currentOrder.items[existingIndex].quantite++;
        } else {
            // Add new item to order
            this.currentOrder.items.push({
                ...item,
                quantite: 1,
                commentaire: ''
            });
        }
        
        this.updateOrderDisplay();
        this.showMessage(`${item.nom} ajouté à la commande`, 'success');
        
        console.log('➕ Item added to order:', item.nom);
    }

    /**
     * Increase item quantity
     */
    increaseQuantity(index) {
        if (this.currentOrder.items[index]) {
            this.currentOrder.items[index].quantite++;
            this.updateOrderDisplay();
        }
    }

    /**
     * Decrease item quantity
     */
    decreaseQuantity(index) {
        if (this.currentOrder.items[index] && this.currentOrder.items[index].quantite > 1) {
            this.currentOrder.items[index].quantite--;
            this.updateOrderDisplay();
        }
    }

    /**
     * Remove item from order
     */
    removeItem(index) {
        if (this.currentOrder.items[index]) {
            const itemName = this.currentOrder.items[index].nom;
            this.currentOrder.items.splice(index, 1);
            this.updateOrderDisplay();
            this.showMessage(`${itemName} retiré de la commande`, 'success');
        }
    }

    /**
     * Update order display
     */
    updateOrderDisplay() {
        const container = document.getElementById('orderItemsList');
        
        if (this.currentOrder.items.length === 0) {
            container.innerHTML = `
                <div class="pos-order-empty">
                    <i class="fas fa-shopping-cart fa-2x text-muted mb-2"></i><br>
                    Aucun article dans la commande<br>
                    <small class="text-muted">Sélectionnez des plats pour commencer</small>
                </div>
            `;
            document.getElementById('finalizeOrderBtn').disabled = true;
        } else {
            container.innerHTML = this.currentOrder.items.map((item, index) => `
                <div class="pos-order-item">
                    <div class="pos-item-details">
                        <div class="pos-item-order-name">${item.nom}</div>
                        <div class="pos-item-order-price">${item.prix}€ l'unité</div>
                    </div>
                    <div class="pos-quantity-controls">
                        <button class="pos-qty-btn remove" data-action="remove" data-index="${index}">
                            <i class="fas fa-trash"></i>
                        </button>
                        <button class="pos-qty-btn" data-action="decrease" data-index="${index}">-</button>
                        <span class="pos-qty-display">${item.quantite}</span>
                        <button class="pos-qty-btn" data-action="increase" data-index="${index}">+</button>
                    </div>
                    <div class="pos-item-total">
                        ${(parseFloat(item.prix) * item.quantite).toFixed(2)}€
                    </div>
                </div>
            `).join('');
            
            document.getElementById('finalizeOrderBtn').disabled = false;
        }
        
        this.calculateOrderTotals();
    }

    /**
     * Calculate order totals
     */
    calculateOrderTotals() {
        const subtotal = this.currentOrder.items.reduce((sum, item) => {
            return sum + (parseFloat(item.prix) * item.quantite);
        }, 0);
        
        const tax = subtotal * 0.10; // 10% TVA
        const discount = this.currentOrder.discount || 0;
        const total = subtotal + tax - discount;
        
        this.currentOrder.subtotal = subtotal;
        this.currentOrder.tax = tax;
        this.currentOrder.total = total;
        
        // Update display
        document.getElementById('orderSubtotal').textContent = subtotal.toFixed(2) + '€';
        document.getElementById('orderTax').textContent = tax.toFixed(2) + '€';
        document.getElementById('orderDiscount').textContent = '-' + discount.toFixed(2) + '€';
        document.getElementById('orderTotal').textContent = total.toFixed(2) + '€';
    }

    /**
     * Search customers
     */
    async searchCustomers(query) {
        try {
            const data = await this.api.request('GET', `/pos/customers/search?q=${encodeURIComponent(query)}`);
            
            if (data.success) {
                this.renderCustomerSearchResults(data.data);
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('❌ Error searching customers:', error);
            document.getElementById('customerSearchResults').innerHTML = `
                <div class="text-center text-danger py-3">
                    <i class="fas fa-exclamation-triangle"></i><br>
                    Erreur lors de la recherche
                </div>
            `;
        }
    }

    /**
     * Render customer search results
     */
    renderCustomerSearchResults(customers) {
        const container = document.getElementById('customerSearchResults');
        
        if (customers.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted py-3">
                    <i class="fas fa-user-slash fa-2x"></i><br>
                    Aucun client trouvé
                </div>
            `;
            return;
        }
        
        container.innerHTML = customers.map(customer => `
            <div class="d-flex align-items-center p-2 border-bottom customer-result" 
                 style="cursor: pointer;" 
                 data-customer='${JSON.stringify(customer)}'>
                <div class="flex-grow-1">
                    <strong>${customer.full_name}</strong>
                    ${customer.email ? `<br><small class="text-muted">${customer.email}</small>` : ''}
                    ${customer.telephone ? `<br><small class="text-muted">${customer.telephone}</small>` : ''}
                </div>
                <div class="text-end">
                    <div class="badge bg-success">${customer.loyalty_points} pts</div>
                    ${customer.is_vip ? '<div class="badge bg-warning mt-1">VIP</div>' : ''}
                </div>
            </div>
        `).join('');
        
        // Add click handlers for customer selection
        container.querySelectorAll('.customer-result').forEach(element => {
            element.addEventListener('click', () => {
                const customer = JSON.parse(element.getAttribute('data-customer'));
                this.selectCustomer(customer);
            });
        });
    }

    /**
     * Show customer search placeholder
     */
    showCustomerSearchPlaceholder() {
        document.getElementById('customerSearchResults').innerHTML = `
            <div class="text-center text-muted py-3">
                <i class="fas fa-search fa-2x"></i><br>
                Saisissez au moins 2 caractères pour rechercher
            </div>
        `;
    }

    /**
     * Select customer
     */
    selectCustomer(customer) {
        this.currentOrder.customer = customer;
        
        // Update customer display
        document.getElementById('customerName').textContent = customer.full_name;
        document.getElementById('customerDetails').textContent = 
            customer.email || customer.telephone || 'Client enregistré';
        document.getElementById('customerPoints').textContent = customer.loyalty_points;
        document.getElementById('customerInfo').classList.add('active');
        
        // Update search input
        document.getElementById('customerSearch').value = customer.full_name;
        
        // Close modal
        this.customerSearchModal.hide();
        
        console.log('👤 Customer selected:', customer.full_name);
        this.showMessage(`Client sélectionné: ${customer.full_name}`, 'success');
    }

    /**
     * Create new customer
     */
    async createNewCustomer() {
        try {
            const form = document.getElementById('newCustomerForm');
            const formData = new FormData(form);
            
            const customerData = {
                nom: formData.get('nom'),
                prenom: formData.get('prenom'),
                email: formData.get('email'),
                telephone: formData.get('telephone')
            };
            
            const data = await this.api.request('POST', '/pos/customers/quick-create', customerData);
            
            if (data.success) {
                this.selectCustomer(data.data);
                this.newCustomerModal.hide();
                form.reset();
                this.showMessage('Nouveau client créé avec succès', 'success');
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('❌ Error creating customer:', error);
            this.showMessage('Erreur lors de la création du client: ' + error.message, 'error');
        }
    }

    /**
     * Handle order type change
     */
    handleOrderTypeChange() {
        // Could add specific logic here for different order types
        // e.g., show table selection for dine-in, address for delivery
        console.log('📋 Order type changed to:', this.currentOrder.type);
    }

    /**
     * Finalize order
     */
    async finalizeOrder() {
        if (this.currentOrder.items.length === 0) {
            this.showMessage('Aucun article dans la commande', 'error');
            return;
        }
        
        try {
            // Prepare order data
            const orderData = {
                items: this.currentOrder.items.map(item => ({
                    type: item.type,
                    [item.type === 'plat' ? 'plat_id' : 'menu_id']: item.id,
                    quantite: item.quantite,
                    commentaire: item.commentaire
                })),
                type_livraison: this.currentOrder.type,
                payment: {
                    method: this.currentOrder.payment_method
                }
            };
            
            // Add customer data (optional for anonymous orders)
            if (this.currentOrder.customer) {
                orderData.customer_id = this.currentOrder.customer.id;
            }
            // Note: Customer is optional for POS - anonymous orders are allowed
            
            // Add discount if any
            if (this.currentOrder.discount > 0) {
                orderData.discount = {
                    amount: this.currentOrder.discount
                };
            }
            
            console.log('📝 Creating order:', orderData);
            
            const data = await this.api.request('POST', '/pos/orders', orderData);
            
            if (data.success) {
                this.showOrderSuccess(data.data);
                console.log('✅ Order created, no stats refresh in simplified version');
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('❌ Error creating order:', error);
            this.showMessage('Erreur lors de la création de la commande: ' + error.message, 'error');
        }
    }

    /**
     * Show order success modal
     */
    showOrderSuccess(orderData) {
        document.getElementById('orderNumber').textContent = orderData.order_number;
        document.getElementById('orderCustomerName').textContent = orderData.customer.name;
        document.getElementById('orderFinalTotal').textContent = orderData.total + '€';
        
        this.orderSuccessModal.show();
        
        console.log('✅ Order created successfully:', orderData.order_number);
    }

    /**
     * Start new order
     */
    startNewOrder() {
        // Reset order state
        this.currentOrder = {
            items: [],
            customer: null,
            type: 'sur_place',
            subtotal: 0,
            tax: 0,
            discount: 0,
            total: 0,
            payment_method: 'cash'
        };
        
        // Reset UI
        this.updateOrderDisplay();
        document.getElementById('customerSearch').value = '';
        document.getElementById('customerInfo').classList.remove('active');
        
        // Reset order type and payment method buttons
        document.querySelectorAll('.pos-order-type-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector('[data-type="sur_place"]').classList.add('active');
        
        document.querySelectorAll('.pos-payment-btn').forEach(btn => {
            btn.classList.remove('active');
        });
        document.querySelector('[data-method="cash"]').classList.add('active');
        
        // Close modal
        this.orderSuccessModal.hide();
        
        console.log('🆕 New order started');
        this.showMessage('Nouvelle commande démarrée', 'success');
    }

    /**
     * Print receipt (placeholder)
     */
    printReceipt() {
        // In a real implementation, this would connect to a receipt printer
        console.log('🖨️ Print receipt requested');
        this.showMessage('Fonctionnalité d\'impression bientôt disponible', 'success');
    }

    /**
     * Handle layout resize (useful for fullscreen toggle)
     */
    handleLayoutResize() {
        console.log('📐 Handling layout resize...');
        
        // Re-render items grid to adjust to new dimensions
        this.renderMenuItems();
        
        // Update any responsive components
        const isFullscreen = document.body.classList.contains('pos-fullscreen');
        console.log('🖥️ Current mode:', isFullscreen ? 'Fullscreen' : 'Normal');
        
        // Adjust grid layout if needed
        const itemsGrid = document.getElementById('posItemsGrid');
        if (itemsGrid && isFullscreen) {
            // In fullscreen, we can show more items per row
            itemsGrid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(180px, 1fr))';
        } else if (itemsGrid) {
            // Normal mode
            itemsGrid.style.gridTemplateColumns = 'repeat(auto-fill, minmax(200px, 1fr))';
        }
        
        // Refresh order display to adjust heights
        this.updateOrderDisplay();
    }

    /**
     * Placeholder for future real-time features (disabled in simplified version)
     */
    startRealTimeUpdates() {
        console.log('🔄 Real-time updates disabled in simplified version');
    }

    /**
     * Show success/error message
     */
    showMessage(message, type = 'success') {
        const messageEl = document.createElement('div');
        messageEl.className = `pos-message ${type}`;
        messageEl.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : 'exclamation-triangle'} me-2"></i>
            ${message}
        `;
        
        document.body.appendChild(messageEl);
        
        // Remove message after 3 seconds
        setTimeout(() => {
            messageEl.remove();
        }, 3000);
    }

    // Get status label
    getStatusLabel(statusValue) {
        const status = Object.values(this.statusConfig).find(s => s.value === statusValue);
        return status?.label || statusValue;
    }

    // Get status badge class
    getStatusBadgeClass(statusValue) {
        const status = Object.values(this.statusConfig).find(s => s.value === statusValue);
        return status?.badge_class || 'bg-secondary';
    }

    // Get status icon class
    getStatusIconClass(statusValue) {
        const status = Object.values(this.statusConfig).find(s => s.value === statusValue);
        return status?.icon_class || 'fas fa-question';
    }

    // Get status badge HTML
    getStatusBadgeHtml(statusValue) {
        return `<span class="badge ${this.getStatusBadgeClass(statusValue)}">
            <i class="${this.getStatusIconClass(statusValue)} me-1"></i>
            ${this.getStatusLabel(statusValue)}
        </span>`;
    }

    // Check if status is final
    isStatusFinal(statusValue) {
        const status = Object.values(this.statusConfig).find(s => s.value === statusValue);
        return !status?.next_possible_statuses?.length;
    }

    // Get next possible statuses
    getNextPossibleStatuses(currentStatus) {
        const status = Object.values(this.statusConfig).find(s => s.value === currentStatus);
        return status?.next_possible_statuses || [];
    }

    // Get status notification config
    getStatusNotification(statusValue) {
        const status = Object.values(this.statusConfig).find(s => s.value === statusValue);
        return status?.notification || { message: 'Status updated', type: 'info' };
    }

    // Update order status
    async updateOrderStatus(orderId, newStatus) {
        if (!this.statusConfig[newStatus]) {
            console.error('Invalid status:', newStatus);
            return false;
        }

        try {
            const response = await fetch(`/api/orders/${orderId}/status`, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({ status: newStatus })
            });

            if (!response.ok) {
                throw new Error('Failed to update order status');
            }

            const notification = this.getStatusNotification(newStatus);
            this.showNotification(notification.message, notification.type);
            return true;

        } catch (error) {
            console.error('Error updating order status:', error);
            this.showNotification('Failed to update order status', 'error');
            return false;
        }
    }

    // Show notification
    showNotification(message, type = 'info') {
        // Add your notification logic here
    }

    // Render status select options
    renderStatusSelect(currentStatus) {
        const nextStatuses = this.getNextPossibleStatuses(currentStatus);
        
        return `
            <select class="form-select form-select-sm" ${this.isStatusFinal(currentStatus) ? 'disabled' : ''}>
                <option value="${currentStatus}" selected>
                    ${this.getStatusLabel(currentStatus)}
                </option>
                ${nextStatuses.map(status => `
                    <option value="${status}">
                        ${this.getStatusLabel(status)}
                    </option>
                `).join('')}
            </select>
        `;
    }

    // Update status display helper
    updateStatusDisplay(element, status) {
        if (!element) return;
        
        OrderStatus.init().then(() => {
            const statusConfig = OrderStatus.config[status];
            if (statusConfig) {
                element.textContent = statusConfig.label;
                element.className = `badge ${statusConfig.badge_class}`;
                
                // Update icon if it exists
                const icon = element.querySelector('i');
                if (icon) {
                    icon.className = statusConfig.icon_class;
                }
            }
        });
    }
}

// Export for global access
window.PosManager = PosManager; 