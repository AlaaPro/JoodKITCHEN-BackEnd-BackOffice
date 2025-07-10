/**
 * Orders Manager
 * Handles UI interactions and data management for orders
 * Follows JoodKitchen project structure and CoreUI standards
 */
class OrdersManager {
    constructor() {
        this.api = new OrdersAPI();
        this.currentPage = 1;
        this.currentLimit = 25;
        this.currentFilters = {};
        this.init();
    }

    async init() {
        console.log('🔄 Initializing OrdersManager...');
        
        // Initialize OrderStatus first
        await OrderStatus.init();
        
        // Populate status filter options dynamically
        this.populateStatusFilter();
        
        this.bindEvents();
        this.initializeDatePickers();
        this.loadOrdersStats();
        this.loadOrders();
    }

    /**
     * Initialize date picker event listeners
     */
    initializeDatePickers() {
        const applyBtn = document.getElementById('applyDateFilter');
        const resetBtn = document.getElementById('resetDateFilter');
        const startDateInput = document.getElementById('statsDateStart');
        const endDateInput = document.getElementById('statsDateEnd');
        const periodText = document.getElementById('currentPeriodText');

        if (applyBtn) {
            applyBtn.addEventListener('click', () => {
                const startDate = startDateInput?.value;
                const endDate = endDateInput?.value;
                
                if (startDate && endDate) {
                    if (new Date(startDate) > new Date(endDate)) {
                        this.showNotification('La date de début doit être antérieure à la date de fin', 'error');
                        return;
                    }
                    
                    this.loadOrdersStats(startDate, endDate);
                    this.updatePeriodText(startDate, endDate);
                } else {
                    this.showNotification('Veuillez sélectionner les deux dates', 'error');
                }
            });
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                const today = new Date().toISOString().split('T')[0];
                if (startDateInput) startDateInput.value = today;
                if (endDateInput) endDateInput.value = today;
                this.loadOrdersStats(today, today);
                this.updatePeriodText(today, today);
            });
        }
    }

    /**
     * Update period text display
     */
    updatePeriodText(startDate, endDate) {
        const periodText = document.getElementById('currentPeriodText');
        if (!periodText) return;

        const today = new Date().toISOString().split('T')[0];
        
        if (startDate === endDate) {
            if (startDate === today) {
                periodText.textContent = "Aujourd'hui";
            } else {
                periodText.textContent = new Date(startDate).toLocaleDateString('fr-FR');
            }
        } else {
            const start = new Date(startDate).toLocaleDateString('fr-FR');
            const end = new Date(endDate).toLocaleDateString('fr-FR');
            periodText.textContent = `${start} - ${end}`;
        }
    }

    /**
     * Populate status filter options dynamically from OrderStatus utility
     */
    populateStatusFilter() {
        if (!OrderStatus.config) {
            // Retry if config not loaded yet
            setTimeout(() => this.populateStatusFilter(), 500);
            return;
        }
        
        if (!document.getElementById('statusFilter')) {
            // Retry if DOM element not ready yet
            setTimeout(() => this.populateStatusFilter(), 500);
            return;
        }
        
        const success = OrderStatus.populateSelect('statusFilter', 'Tous');
        if (!success) {
            // Retry on failure
            setTimeout(() => this.populateStatusFilter(), 500);
        }
    }

    bindEvents() {
        // Search and filter events
        const searchBtn = document.querySelector('#searchBtn');
        const resetBtn = document.querySelector('#resetBtn');
        const limitSelect = document.querySelector('#ordersLimit');
        const searchInput = document.querySelector('#searchOrders');

        if (searchBtn) {
            searchBtn.addEventListener('click', () => this.applyFilters());
        }

        if (resetBtn) {
            resetBtn.addEventListener('click', () => this.resetFilters());
        }

        if (limitSelect) {
            limitSelect.addEventListener('change', () => {
                this.currentLimit = parseInt(limitSelect.value);
                this.currentPage = 1;
                this.loadOrders();
            });
        }

        // Allow Enter key to trigger search
        if (searchInput) {
            searchInput.addEventListener('keypress', (e) => {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    this.applyFilters();
                }
            });
        }

        // Toggle filters panel
        const toggleFilters = document.querySelector('#toggleFilters');
        if (toggleFilters) {
            toggleFilters.addEventListener('click', () => {
                const panel = document.querySelector('#filtersPanel');
                if (panel) {
                    panel.style.display = panel.style.display === 'none' ? 'block' : 'none';
                }
            });
        }
    }

    /**
     * Load orders statistics
     */
    async loadOrdersStats(startDate = null, endDate = null) {
        console.log('🔄 Loading orders stats...');
        
        try {
            const stats = await this.api.getOrdersStats(startDate, endDate);
            console.log('📊 Stats loaded:', stats);
            
            this.updateStatsCards(stats);
            this.updateBusinessInsights(stats);
            
        } catch (error) {
            console.error('❌ Stats loading error:', error);
            this.showNotification('Erreur lors du chargement des statistiques', 'error');
            
            // Show empty stats on error
            this.updateStatsCards({
                pending: 0,
                confirmed: 0,
                preparing: 0,
                ready: 0,
                completed: 0,
                delivering: 0,
                cancelled: 0,
                totalRevenue: 0
            });
        }
    }

    /**
     * Update the statistics cards
     */
    updateStatsCards(stats) {
        console.log('📊 Updating stats cards with:', stats);

        // Define the card mappings with correct CSS selectors (only existing cards)
        const cardMappings = [
            { selector: '.stats-pending .widget-value', key: 'pending', label: 'En attente' },
            { selector: '.stats-confirmed .widget-value', key: 'confirmed', label: 'Confirmées' },
            { selector: '.stats-preparing .widget-value', key: 'preparing', label: 'En préparation' },
            { selector: '.stats-ready .widget-value', key: 'ready', label: 'Prêtes' },
            { selector: '.stats-completed .widget-value', key: 'completed', label: 'Livrées' }
        ];

        // Update each status card
        cardMappings.forEach(mapping => {
            const element = document.querySelector(mapping.selector);
            if (element) {
                const count = stats[mapping.key] || 0;
                element.textContent = count;
                console.log(`✅ Updated ${mapping.label}: ${count}`);
            } else {
                console.warn(`❌ Element not found: ${mapping.selector}`);
            }
        });

        // Update revenue card
        const revenueElement = document.querySelector('.stats-revenue .widget-value');
        if (revenueElement) {
            const revenue = stats.totalRevenue || stats.todayRevenue || 0;
            revenueElement.textContent = `${revenue.toFixed(2)}€`;
            console.log('✅ Updated Revenue:', revenue);
        } else {
            console.warn('❌ Revenue element not found');
        }

        console.log('📊 All stats cards updated successfully');
    }

    /**
     * Update a single status card
     */
    updateStatusCard(selector, value) {
        const card = document.querySelector(selector);
        if (card) {
            card.textContent = value;
        }
    }

    /**
     * Update business insights section
     */
    updateBusinessInsights(stats) {
        console.log('📈 Updating business insights with:', stats);
        
        // Average order value
        const avgOrderCard = document.querySelector('.stats-avg-order');
        if (avgOrderCard && stats.totalOrdersCount > 0) {
            const avgOrder = stats.totalRevenue / stats.totalOrdersCount;
            const formattedAvg = new Intl.NumberFormat('fr-FR', {
                style: 'currency',
                currency: 'EUR',
                minimumFractionDigits: 2
            }).format(avgOrder);
            avgOrderCard.textContent = formattedAvg;
            console.log('✅ Updated Average Order Value:', formattedAvg);
        } else if (avgOrderCard) {
            avgOrderCard.textContent = '0.00€';
        }

        // Orders per hour (last hour)
        const ordersPerHourCard = document.querySelector('.stats-orders-per-hour');
        if (ordersPerHourCard) {
            ordersPerHourCard.textContent = stats.ordersLastHour || 0;
            console.log('✅ Updated Orders Last Hour:', stats.ordersLastHour);
        }

        // Conversion rate (pending to confirmed)
        const conversionRateCard = document.querySelector('.stats-conversion-rate');
        if (conversionRateCard) {
            let conversionRate = 0;
            const totalPending = stats.pending || 0;
            const totalConfirmed = stats.confirmed || 0;
            
            if (totalPending > 0 && totalConfirmed > 0) {
                conversionRate = ((totalConfirmed / (totalPending + totalConfirmed)) * 100).toFixed(1);
            } else if (totalConfirmed > 0 && totalPending === 0) {
                conversionRate = 100;
            }
            conversionRateCard.textContent = conversionRate + '%';
            console.log('✅ Updated Conversion Rate:', conversionRate + '%');
        }

        // Average preparation time
        const avgTimeCard = document.querySelector('.stats-avg-time');
        if (avgTimeCard) {
            if (stats.avgPreparationTime && stats.avgPreparationTime > 0) {
                const minutes = Math.floor(stats.avgPreparationTime);
                const seconds = Math.floor((stats.avgPreparationTime - minutes) * 60);
                avgTimeCard.textContent = `${minutes}:${seconds.toString().padStart(2, '0')}`;
            } else {
                avgTimeCard.textContent = '--:--';
            }
            console.log('✅ Updated Average Prep Time:', avgTimeCard.textContent);
        }
        
        console.log('📈 Business insights updated successfully');
    }

    async loadOrders() {
        try {
            this.showLoading(true);
            
            const filters = {
                page: this.currentPage,
                limit: this.currentLimit,
                ...this.currentFilters
            };

            const response = await this.api.getOrders(filters);
            
            if (response.success) {
                this.displayOrders(response.data);
                this.updatePagination(response.pagination);
            }
        } catch (error) {
            console.error('Erreur lors du chargement des commandes:', error);
            this.showNotification('Erreur lors du chargement des commandes', 'error');
        } finally {
            this.showLoading(false);
        }
    }

    displayOrders(orders) {
        const tbody = document.querySelector('#ordersTableBody');
        if (!tbody) return;

        if (orders.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <div class="text-muted">
                            <i class="fas fa-inbox fa-2x mb-2"></i>
                            <p>Aucune commande trouvée</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = orders.map(order => this.renderOrderRow(order)).join('');
    }

    renderOrderRow(order) {
        const statusBadge = this.getStatusBadge(order.statut);
        const typeBadge = this.getTypeBadge(order.typeLivraison);
        
        return `
            <tr data-order-id="${order.id}">
                <td class="ps-4">
                    <input type="checkbox" class="form-check-input" value="${order.id}">
                </td>
                <td><strong>${order.numero}</strong></td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="green-icon-bg me-2">
                            <i class="fas fa-user"></i>
                        </div>
                        <div>
                            <div class="fw-semibold">${order.client.nom}</div>
                            ${order.client.email ? `<small class="text-muted">${order.client.email}</small>` : ''}
                        </div>
                    </div>
                </td>
                <td>
                    <div>${order.dateCommande.split(' ')[0]}</div>
                    <small class="text-muted">${order.dateCommande.split(' ')[1]}</small>
                </td>
                <td>${typeBadge}</td>
                <td class="fw-bold jood-primary">${order.total}€</td>
                <td>${statusBadge}</td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" title="Voir détails" 
                                onclick="ordersManager.showOrderDetails(${order.id})">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-success" title="Modifier statut"
                                onclick="ordersManager.showStatusModal(${order.id}, '${order.statut}')">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-secondary" title="Imprimer"
                                onclick="ordersManager.printOrder(${order.id})">
                            <i class="fas fa-print"></i>
                        </button>
                    </div>
                </td>
            </tr>
        `;
    }

    getStatusBadge(status) {
        // Use centralized OrderStatus utility
        return OrderStatus.getBadgeHtml(status);
    }

    getTypeBadge(type) {
        const typeMap = {
            'livraison': { class: 'bg-primary', text: 'Livraison' },
            'a_emporter': { class: 'bg-warning', text: 'À emporter' },
            'sur_place': { class: 'jood-info-bg', text: 'Sur place' }
        };

        const typeInfo = typeMap[type] || { class: 'bg-secondary', text: type };
        return `<span class="badge ${typeInfo.class}">${typeInfo.text}</span>`;
    }

    updatePagination(pagination) {
        const paginationInfo = document.querySelector('.pagination-info');
        if (paginationInfo) {
            const start = ((pagination.page - 1) * pagination.limit) + 1;
            const end = Math.min(pagination.page * pagination.limit, pagination.total);
            paginationInfo.textContent = `Affichage de ${start} à ${end} sur ${pagination.total} commandes`;
        }

        // Update pagination buttons
        this.renderPaginationButtons(pagination);
    }

    renderPaginationButtons(pagination) {
        const paginationContainer = document.querySelector('.pagination');
        if (!paginationContainer) return;

        const buttons = [];

        // Previous button
        buttons.push(`
            <li class="page-item ${pagination.page === 1 ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.page - 1}">Précédent</a>
            </li>
        `);

        // Page numbers
        const startPage = Math.max(1, pagination.page - 2);
        const endPage = Math.min(pagination.pages, pagination.page + 2);

        for (let i = startPage; i <= endPage; i++) {
            buttons.push(`
                <li class="page-item ${i === pagination.page ? 'active' : ''}">
                    <a class="page-link" href="#" data-page="${i}">${i}</a>
                </li>
            `);
        }

        // Next button
        buttons.push(`
            <li class="page-item ${pagination.page === pagination.pages ? 'disabled' : ''}">
                <a class="page-link" href="#" data-page="${pagination.page + 1}">Suivant</a>
            </li>
        `);

        paginationContainer.innerHTML = buttons.join('');

        // Bind pagination events
        paginationContainer.addEventListener('click', (e) => {
            e.preventDefault();
            if (e.target.classList.contains('page-link') && !e.target.closest('.disabled')) {
                const page = parseInt(e.target.getAttribute('data-page'));
                if (page && page !== pagination.page) {
                    this.currentPage = page;
                    this.loadOrders();
                }
            }
        });
    }

    applyFilters() {
        const searchInput = document.querySelector('#searchOrders');
        const statusSelect = document.querySelector('#statusFilter');
        const typeSelect = document.querySelector('#typeFilter');
        const dateInput = document.querySelector('#dateFilter');

        this.currentFilters = {};

        if (searchInput && searchInput.value.trim()) {
            this.currentFilters.search = searchInput.value.trim();
        }
        if (statusSelect && statusSelect.value) {
            this.currentFilters.status = statusSelect.value;
        }
        if (typeSelect && typeSelect.value) {
            this.currentFilters.type = typeSelect.value;
        }
        if (dateInput && dateInput.value) {
            this.currentFilters.date = dateInput.value;
        }

        this.currentPage = 1;
        this.loadOrders();
    }

    resetFilters() {
        const searchInput = document.querySelector('#searchOrders');
        const statusSelect = document.querySelector('#statusFilter');
        const typeSelect = document.querySelector('#typeFilter');
        const dateInput = document.querySelector('#dateFilter');

        if (searchInput) searchInput.value = '';
        if (statusSelect) statusSelect.value = '';
        if (typeSelect) typeSelect.value = '';
        if (dateInput) dateInput.value = '';

        this.currentFilters = {};
        this.currentPage = 1;
        this.loadOrders();
    }

    async showOrderDetails(orderId) {
        try {
            const response = await this.api.getOrderDetails(orderId);
            
            if (response.success) {
                this.openOrderDetailsModal(response.data);
            }
        } catch (error) {
            console.error('Erreur lors du chargement des détails:', error);
            this.showNotification('Erreur lors du chargement des détails de la commande', 'error');
        }
    }

    openOrderDetailsModal(orderData) {
        // Handle both old and new response format
        const order = orderData.order || orderData; // New format has nested 'order' object
        const articles = orderData.articles || orderData.articles || [];
        const validation = orderData.validation || {};
        
        const modalHtml = this.renderOrderDetailsModal(order, articles, validation);
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        const modal = new coreui.Modal(document.getElementById('orderDetailsModal'));
        modal.show();

        // Clean up modal on hide
        document.getElementById('orderDetailsModal').addEventListener('hidden.coreui.modal', function() {
            this.remove();
        });
    }

    renderOrderDetailsModal(order, articles = [], validation = {}) {
        const articlesHtml = articles.map(article => {
            const rowClass = article.isDeleted ? 'table-warning' : '';
            const nameStyle = article.isDeleted ? 'text-muted fst-italic' : '';
            const tooltip = article.isDeleted ? 'title="Cet article a été supprimé du menu"' : '';
            const itemIcon = article.isDeleted ? '🗑️ ' : '';
            const typeInfo = article.type !== 'deleted' ? `<small class="text-muted">(${article.type})</small>` : '';
            
            return `
                <tr class="${rowClass}" ${tooltip}>
                    <td class="${nameStyle}">
                        ${itemIcon}${article.name || article.nom}
                        ${typeInfo}
                        ${article.originalName && article.originalName !== (article.name || article.nom) ? 
                            `<br><small class="text-success">Original: ${article.originalName}</small>` : ''}
                        ${article.snapshotDate ? 
                            `<br><small class="text-info">Snapshot: ${article.snapshotDate}</small>` : ''}
                    </td>
                    <td class="text-center">${article.quantite || article.quantity}</td>
                    <td class="text-end">${(article.prixUnitaire || article.price)}€</td>
                    <td class="text-end fw-bold">${article.total.toFixed(2)}€</td>
                </tr>
            `;
        }).join('');

        // Validation alerts
        const validationAlerts = [];
        if (validation.issues && validation.issues.length > 0) {
            validationAlerts.push(`
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <strong>⚠️ Problèmes détectés:</strong>
                    <ul class="mb-0 mt-2">
                        ${validation.issues.map(issue => `<li>${issue}</li>`).join('')}
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `);
        }
        if (validation.warnings && validation.warnings.length > 0) {
            validationAlerts.push(`
                <div class="alert alert-info alert-dismissible fade show" role="alert">
                    <strong>ℹ️ Informations:</strong>
                    <ul class="mb-0 mt-2">
                        ${validation.warnings.map(warning => `<li>${warning}</li>`).join('')}
                    </ul>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
            `);
        }

        const clientInfo = order.client || {};
        const deliveryInfo = order.delivery || {};
        const totals = order.totals || {};

        return `
            <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="orderDetailsModalLabel">
                                Détails de la commande ${order.numeroCommande || order.numero}
                                ${validation.score ? `<span class="badge bg-${validation.score >= 80 ? 'success' : validation.score >= 60 ? 'warning' : 'danger'} ms-2">${validation.score}% santé</span>` : ''}
                            </h5>
                            <button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            ${validationAlerts.join('')}
                            
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Informations client</h6>
                                    <p><strong>Nom:</strong> ${clientInfo.nom || clientInfo.prenom ? `${clientInfo.prenom || ''} ${clientInfo.nom || ''}`.trim() : 'Client Anonyme'}</p>
                                    ${clientInfo.email ? `<p><strong>Email:</strong> ${clientInfo.email}</p>` : ''}
                                    ${clientInfo.telephone ? `<p><strong>Téléphone:</strong> ${clientInfo.telephone}</p>` : ''}
                                    ${clientInfo.adresse ? `<p><strong>Adresse:</strong> ${clientInfo.adresse}</p>` : ''}
                                </div>
                                <div class="col-md-6">
                                    <h6>Informations commande</h6>
                                    <p><strong>Date:</strong> ${order.dateCommande}</p>
                                    <p><strong>Type:</strong> ${this.getTypeBadge(deliveryInfo.type || order.typeLivraison)}</p>
                                    <p><strong>Statut:</strong> ${this.getStatusBadge(order.status || order.statut)}</p>
                                    ${deliveryInfo.adresse ? `<p><strong>Adresse livraison:</strong> ${deliveryInfo.adresse}</p>` : ''}
                                </div>
                            </div>
                            
                            ${(deliveryInfo.commentaire || order.commentaire) ? `
                                <div class="mt-3">
                                    <h6>Commentaire</h6>
                                    <p class="bg-light p-2 rounded">${deliveryInfo.commentaire || order.commentaire}</p>
                                </div>
                            ` : ''}

                            <div class="mt-4">
                                <div class="d-flex justify-content-between align-items-center mb-3">
                                    <h6>Articles commandés (${totals.items || articles.length})</h6>
                                    ${totals.deleted > 0 ? `<span class="badge bg-warning">${totals.deleted} supprimé(s)</span>` : ''}
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-sm">
                                        <thead>
                                            <tr>
                                                <th>Article</th>
                                                <th class="text-center">Quantité</th>
                                                <th class="text-end">Prix unitaire</th>
                                                <th class="text-end">Total</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            ${articlesHtml}
                                        </tbody>
                                        <tfoot>
                                            ${totals.discount > 0 ? `
                                                <tr>
                                                    <th colspan="3">Sous-total</th>
                                                    <th class="text-end">${totals.amount || order.total}€</th>
                                                </tr>
                                                <tr>
                                                    <th colspan="3">Réduction</th>
                                                    <th class="text-end text-success">-${totals.discount}€</th>
                                                </tr>
                                            ` : ''}
                                            <tr class="table-dark">
                                                <th colspan="3">Total de la commande</th>
                                                <th class="text-end">${totals.final || order.total}€</th>
                                            </tr>
                                        </tfoot>
                                    </table>
                                </div>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">Fermer</button>
                            <button type="button" class="btn btn-primary" onclick="ordersManager.printOrder(${order.id})">
                                <i class="fas fa-print"></i> Imprimer
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    showStatusModal(orderId, currentStatus) {
        const modalHtml = this.renderStatusModal(orderId, currentStatus);
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        const modal = new coreui.Modal(document.getElementById('statusModal'));
        modal.show();

        // Clean up modal on hide
        document.getElementById('statusModal').addEventListener('hidden.coreui.modal', function() {
            this.remove();
        });
    }

    renderStatusModal(orderId, currentStatus) {
        // Use centralized OrderStatus utility
        const statusOptions = OrderStatus.getAll().map(status => 
            `<option value="${status.value}" ${status.value === currentStatus ? 'selected' : ''}>${status.label}</option>`
        ).join('');

        return `
            <div class="modal fade" id="statusModal" tabindex="-1" aria-labelledby="statusModalLabel" aria-hidden="true">
                <div class="modal-dialog">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="statusModalLabel">Modifier le statut</h5>
                            <button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <form id="statusForm">
                                <div class="mb-3">
                                    <label for="newStatus" class="form-label">Nouveau statut</label>
                                    <select class="form-select" id="newStatus" required>
                                        ${statusOptions}
                                    </select>
                                </div>
                            </form>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">Annuler</button>
                            <button type="button" class="btn btn-primary" onclick="ordersManager.updateStatus(${orderId})">
                                Mettre à jour
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    async updateStatus(orderId) {
        const newStatus = document.querySelector('#newStatus').value;
        
        try {
            const response = await this.api.updateOrderStatus(orderId, newStatus);
            
            if (response.success) {
                this.showNotification('Statut mis à jour avec succès', 'success');
                this.loadOrders(); // Reload orders
                this.loadOrdersStats(); // Reload stats
                
                // Close modal
                const modal = coreui.Modal.getInstance(document.getElementById('statusModal'));
                modal.hide();
            }
        } catch (error) {
            console.error('Erreur lors de la mise à jour du statut:', error);
            this.showNotification('Erreur lors de la mise à jour du statut', 'error');
        }
    }

    printOrder(orderId) {
        // TODO: Implement print functionality
        this.showNotification('Fonction d\'impression en cours de développement', 'info');
    }

    showLoading(show) {
        const tbody = document.querySelector('#ordersTableBody');
        if (!tbody) return;

        if (show) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-4">
                        <div class="spinner-border text-primary" role="status">
                            <span class="visually-hidden">Chargement...</span>
                        </div>
                        <p class="mt-2 text-muted">Chargement des commandes...</p>
                    </td>
                </tr>
            `;
        }
    }

    /**
     * Show a notification message
     * @param {string} message The message to show
     * @param {string} type The type of notification ('success', 'error', 'warning')
     */
    showNotification(message, type = 'info') {
        // Simple notification system
        const alertClass = type === 'error' ? 'alert-danger' : 
                          type === 'success' ? 'alert-success' : 
                          type === 'warning' ? 'alert-warning' : 'alert-info';
        
        const notification = document.createElement('div');
        notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-coreui-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }
}

// Initialize when DOM is loaded
document.addEventListener('DOMContentLoaded', async function() {
    if (document.querySelector('#ordersTableBody')) {
        window.ordersManager = new OrdersManager();
    }
}); 