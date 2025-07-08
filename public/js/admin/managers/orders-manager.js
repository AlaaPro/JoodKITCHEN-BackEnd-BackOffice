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

    init() {
        this.bindEvents();
        this.loadOrdersStats();
        this.loadOrders();
    }

    bindEvents() {
        // Search and filter events
        const searchBtn = document.querySelector('#searchOrders');
        const resetBtn = document.querySelector('#resetFilters');
        const limitSelect = document.querySelector('#ordersLimit');

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

    async loadOrdersStats() {
        try {
            const response = await this.api.getOrdersStats();
            
            if (response.success) {
                this.updateStatsCards(response.data);
            }
        } catch (error) {
            console.error('Erreur lors du chargement des statistiques:', error);
            this.showNotification('Erreur lors du chargement des statistiques', 'error');
        }
    }

    updateStatsCards(stats) {
        const pendingCard = document.querySelector('.stats-pending .widget-value');
        const preparingCard = document.querySelector('.stats-preparing .widget-value');
        const completedCard = document.querySelector('.stats-completed .widget-value');
        const revenueCard = document.querySelector('.stats-revenue .widget-value');

        if (pendingCard) pendingCard.textContent = stats.pending;
        if (preparingCard) preparingCard.textContent = stats.preparing;
        if (completedCard) completedCard.textContent = stats.completed;
        if (revenueCard) revenueCard.textContent = `${stats.todayRevenue.toFixed(2)}€`;
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
        const statusMap = {
            'en_attente': { class: 'jood-warning-bg', text: 'En attente' },
            'en_preparation': { class: 'bg-info', text: 'En préparation' },
            'pret': { class: 'bg-primary', text: 'Prête' },
            'en_livraison': { class: 'bg-warning', text: 'En livraison' },
            'livre': { class: 'jood-primary-bg', text: 'Livrée' },
            'annule': { class: 'bg-danger', text: 'Annulée' }
        };

        const statusInfo = statusMap[status] || { class: 'bg-secondary', text: status };
        return `<span class="badge ${statusInfo.class}">${statusInfo.text}</span>`;
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
        const searchInput = document.querySelector('#orderSearch');
        const statusSelect = document.querySelector('#orderStatus');
        const typeSelect = document.querySelector('#orderType');
        const dateInput = document.querySelector('#orderDate');

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
        const searchInput = document.querySelector('#orderSearch');
        const statusSelect = document.querySelector('#orderStatus');
        const typeSelect = document.querySelector('#orderType');
        const dateInput = document.querySelector('#orderDate');

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

    openOrderDetailsModal(order) {
        const modalHtml = this.renderOrderDetailsModal(order);
        document.body.insertAdjacentHTML('beforeend', modalHtml);
        
        const modal = new coreui.Modal(document.getElementById('orderDetailsModal'));
        modal.show();

        // Clean up modal on hide
        document.getElementById('orderDetailsModal').addEventListener('hidden.coreui.modal', function() {
            this.remove();
        });
    }

    renderOrderDetailsModal(order) {
        const articlesHtml = order.articles.map(article => `
            <tr>
                <td>${article.nom}</td>
                <td class="text-center">${article.quantite}</td>
                <td class="text-end">${article.prixUnitaire}€</td>
                <td class="text-end fw-bold">${article.total.toFixed(2)}€</td>
            </tr>
        `).join('');

        return `
            <div class="modal fade" id="orderDetailsModal" tabindex="-1" aria-labelledby="orderDetailsModalLabel" aria-hidden="true">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="orderDetailsModalLabel">
                                Détails de la commande ${order.numero}
                            </h5>
                            <button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            <div class="row">
                                <div class="col-md-6">
                                    <h6>Informations client</h6>
                                    <p><strong>Nom:</strong> ${order.client.nom}</p>
                                    ${order.client.email ? `<p><strong>Email:</strong> ${order.client.email}</p>` : ''}
                                    ${order.client.telephone ? `<p><strong>Téléphone:</strong> ${order.client.telephone}</p>` : ''}
                                </div>
                                <div class="col-md-6">
                                    <h6>Informations commande</h6>
                                    <p><strong>Date:</strong> ${order.dateCommande}</p>
                                    <p><strong>Type:</strong> ${this.getTypeBadge(order.typeLivraison)}</p>
                                    <p><strong>Statut:</strong> ${this.getStatusBadge(order.statut)}</p>
                                    ${order.adresseLivraison ? `<p><strong>Adresse:</strong> ${order.adresseLivraison}</p>` : ''}
                                </div>
                            </div>
                            
                            ${order.commentaire ? `
                                <div class="mt-3">
                                    <h6>Commentaire</h6>
                                    <p class="bg-light p-2 rounded">${order.commentaire}</p>
                                </div>
                            ` : ''}

                            <div class="mt-4">
                                <h6>Articles commandés</h6>
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
                                            <tr class="table-dark">
                                                <th colspan="3">Total de la commande</th>
                                                <th class="text-end">${order.total}€</th>
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
        const statuses = [
            { value: 'en_attente', label: 'En attente' },
            { value: 'en_preparation', label: 'En préparation' },
            { value: 'pret', label: 'Prête' },
            { value: 'en_livraison', label: 'En livraison' },
            { value: 'livre', label: 'Livrée' },
            { value: 'annule', label: 'Annulée' }
        ];

        const statusOptions = statuses.map(status => 
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
document.addEventListener('DOMContentLoaded', function() {
    if (document.querySelector('#ordersTableBody')) {
        window.ordersManager = new OrdersManager();
    }
}); 