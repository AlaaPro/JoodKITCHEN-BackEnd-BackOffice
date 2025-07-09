/**
 * Kitchen Manager - Real-time kitchen dashboard for JoodKitchen
 * Handles order workflow: New → In Progress → Ready → Delivered
 */
class KitchenManager {
    constructor() {
        this.apiBaseUrl = '/api';
        this.refreshInterval = 30000; // 30 seconds
        this.refreshTimer = null;
        this.countdownTimers = new Map();
        this.init();
    }

    init() {
        this.setupEventListeners();
        this.loadKitchenData();
        this.startAutoRefresh();
        this.initializeCountdowns();
    }

    setupEventListeners() {
        // Manual refresh button
        const refreshBtn = document.getElementById('refreshOrders');
        if (refreshBtn) {
            refreshBtn.addEventListener('click', () => this.handleManualRefresh());
        }

        // Print all button
        const printBtn = document.querySelector('[data-action="print-all"]');
        if (printBtn) {
            printBtn.addEventListener('click', () => this.printAllOrders());
        }
    }

    async loadKitchenData() {
        try {
            const response = await fetch(`${this.apiBaseUrl}/orders/kitchen/dashboard`, {
                headers: {
                    'Accept': 'application/json',
                    'Authorization': `Bearer ${localStorage.getItem('admin_token') || ''}`
                }
            });

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            this.updateDashboard(data);
        } catch (error) {
            console.error('Error loading kitchen data:', error);
            this.showErrorNotification('Erreur lors du chargement des données cuisine');
        }
    }

    updateDashboard(data) {
        // Update statistics widgets
        this.updateStatistics(data.statistics);
        
        // Update order columns
        this.updateNewOrders(data.pending_orders || []);
        this.updateInProgressOrders(data.preparing_orders || []);
        this.updateReadyOrders(data.ready_orders || []);
    }

    updateStatistics(stats) {
        if (!stats) return;

        // Update widget values
        this.updateWidgetValue('.widget-nouvelles', stats.total_pending || 0);
        this.updateWidgetValue('.widget-en-cours', stats.total_preparing || 0);
        this.updateWidgetValue('.widget-pretes', stats.total_ready || 0);
        
        // Calculate and update average time
        const avgTime = stats.avg_preparation_time || 18;
        this.updateWidgetValue('.widget-temps-moyen', `${avgTime} min`);
    }

    updateWidgetValue(selector, value) {
        const widget = document.querySelector(selector);
        if (widget) {
            const valueElement = widget.querySelector('.widget-value');
            if (valueElement) {
                valueElement.textContent = value;
            }
        }
    }

    updateNewOrders(orders) {
        const container = document.querySelector('#nouvelles-commandes .card-body');
        if (!container) return;

        container.innerHTML = '';
        
        orders.forEach(order => {
            const orderCard = this.createOrderCard(order, 'new');
            container.appendChild(orderCard);
        });

        // Update badge count
        this.updateColumnBadge('nouvelles', orders.length);
    }

    updateInProgressOrders(orders) {
        const container = document.querySelector('#en-cours-commandes .card-body');
        if (!container) return;

        container.innerHTML = '';
        
        orders.forEach(order => {
            const orderCard = this.createOrderCard(order, 'progress');
            container.appendChild(orderCard);
        });

        // Update badge count
        this.updateColumnBadge('en-cours', orders.length);
    }

    updateReadyOrders(orders) {
        const container = document.querySelector('#pretes-commandes .card-body');
        if (!container) return;

        container.innerHTML = '';
        
        orders.forEach(order => {
            const orderCard = this.createOrderCard(order, 'ready');
            container.appendChild(orderCard);
        });

        // Update badge count
        this.updateColumnBadge('pretes', orders.length);
    }

    createOrderCard(order, type) {
        const card = document.createElement('div');
        card.className = 'p-3 border-bottom kitchen-order';
        card.dataset.orderId = order.id;
        card.dataset.type = type;

        const timeInfo = this.getTimeInfo(order, type);
        const progressHtml = type === 'progress' ? this.getProgressHtml(order) : '';
        const itemsHtml = this.getOrderItemsHtml(order.items || []);
        const actionsHtml = this.getOrderActionsHtml(order, type);

        card.innerHTML = `
            <div class="d-flex justify-content-between align-items-start mb-2">
                <div>
                    <h6 class="mb-1 fw-bold">#CMD-${order.id.toString().padStart(3, '0')}</h6>
                    <small class="text-muted">${timeInfo.label}</small>
                </div>
                <div class="text-end">
                    <span class="badge ${this.getStatusBadgeClass(type)}">${this.getStatusLabel(type)}</span>
                    <div class="mt-1">
                        <small class="${timeInfo.class} countdown-timer" data-seconds="${timeInfo.seconds}">
                            ${timeInfo.display}
                        </small>
                    </div>
                </div>
            </div>
            ${progressHtml}
            <div class="order-items mb-3">
                ${itemsHtml}
            </div>
            <div class="d-flex gap-2">
                ${actionsHtml}
            </div>
        `;

        return card;
    }

    getTimeInfo(order, type) {
        const now = new Date();
        const orderDate = new Date(order.dateCommande || order.created_at);
        const elapsed = Math.floor((now - orderDate) / 1000);

        switch (type) {
            case 'new':
                const waitingTime = elapsed;
                return {
                    label: `Commande à ${orderDate.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`,
                    seconds: Math.max(0, 1800 - waitingTime), // 30 min limit
                    display: this.formatTime(Math.max(0, 1800 - waitingTime)),
                    class: waitingTime > 300 ? 'text-danger' : waitingTime > 180 ? 'text-warning' : 'text-muted'
                };
            
            case 'progress':
                const prepTime = elapsed;
                return {
                    label: `Démarrée à ${orderDate.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`,
                    seconds: prepTime,
                    display: this.formatTime(prepTime),
                    class: prepTime > 1200 ? 'text-danger' : prepTime > 900 ? 'text-warning' : 'text-success'
                };
            
            case 'ready':
                const readyTime = elapsed;
                return {
                    label: `Terminée à ${orderDate.toLocaleTimeString('fr-FR', { hour: '2-digit', minute: '2-digit' })}`,
                    seconds: readyTime,
                    display: `Depuis ${this.formatTime(readyTime)}`,
                    class: 'text-success'
                };
            
            default:
                return {
                    label: orderDate.toLocaleTimeString('fr-FR'),
                    seconds: 0,
                    display: '0:00',
                    class: 'text-muted'
                };
        }
    }

    getProgressHtml(order) {
        const progress = this.calculateOrderProgress(order);
        return `
            <div class="progress mb-2" style="height: 6px;">
                <div class="progress-bar jood-primary-bg" style="width: ${progress}%"></div>
            </div>
        `;
    }

    calculateOrderProgress(order) {
        // Simple progress calculation based on items or time
        if (order.items && order.items.length > 0) {
            const completedItems = order.items.filter(item => item.status === 'completed').length;
            return Math.round((completedItems / order.items.length) * 100);
        }
        
        // Fallback: time-based progress
        const now = new Date();
        const orderDate = new Date(order.dateCommande || order.created_at);
        const elapsed = Math.floor((now - orderDate) / 60000); // minutes
        const expectedTime = 20; // 20 minutes expected prep time
        
        return Math.min(Math.round((elapsed / expectedTime) * 100), 100);
    }

    getOrderItemsHtml(items) {
        if (!items || items.length === 0) {
            return '<div class="text-muted">Aucun article</div>';
        }

        return items.slice(0, 3).map(item => {
            const badge = this.getItemStatusBadge(item.status);
            return `
                <div class="d-flex justify-content-between mb-1">
                    <span>${item.quantite || 1}x ${item.nom || 'Article'}</span>
                    ${badge}
                </div>
            `;
        }).join('') + (items.length > 3 ? 
            `<div class="text-muted small">+${items.length - 3} autres articles</div>` : ''
        );
    }

    getItemStatusBadge(status) {
        switch (status) {
            case 'completed':
                return '<span class="badge bg-success">✓</span>';
            case 'preparing':
                return '<span class="text-warning">En cours</span>';
            case 'urgent':
                return '<span class="badge bg-danger">Urgent</span>';
            default:
                return '<span class="text-muted">En attente</span>';
        }
    }

    getOrderActionsHtml(order, type) {
        switch (type) {
            case 'new':
                return `
                    <button class="btn btn-primary btn-sm flex-fill" onclick="kitchenManager.startOrder(${order.id})">
                        <i class="fas fa-play"></i> Commencer
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="kitchenManager.viewOrderDetails(${order.id})" title="Détails">
                        <i class="fas fa-eye"></i>
                    </button>
                `;
            
            case 'progress':
                return `
                    <button class="btn btn-success btn-sm flex-fill" onclick="kitchenManager.completeOrder(${order.id})">
                        <i class="fas fa-check"></i> Terminer
                    </button>
                    <button class="btn btn-outline-warning btn-sm" onclick="kitchenManager.pauseOrder(${order.id})" title="Pause">
                        <i class="fas fa-pause"></i>
                    </button>
                `;
            
            case 'ready':
                return `
                    <button class="btn btn-warning btn-sm flex-fill" onclick="kitchenManager.deliverOrder(${order.id})">
                        <i class="fas fa-truck"></i> Livrer
                    </button>
                    <button class="btn btn-outline-secondary btn-sm" onclick="kitchenManager.viewOrderDetails(${order.id})" title="Détails">
                        <i class="fas fa-eye"></i>
                    </button>
                `;
            
            default:
                return '';
        }
    }

    getStatusBadgeClass(type) {
        switch (type) {
            case 'new': return 'jood-warning-bg';
            case 'progress': return 'jood-primary-bg';
            case 'ready': return 'jood-success-bg';
            default: return 'bg-secondary';
        }
    }

    getStatusLabel(type) {
        switch (type) {
            case 'new': return 'Nouveau';
            case 'progress': return 'En cours';
            case 'ready': return 'Prête';
            default: return 'Inconnu';
        }
    }

    updateColumnBadge(column, count) {
        const badge = document.querySelector(`#${column}-badge`);
        if (badge) {
            badge.textContent = count;
        }
    }

    // Order action methods
    async startOrder(orderId) {
        try {
            await this.updateOrderStatus(orderId, 'en_preparation');
            this.showNotification(`Commande #CMD-${orderId.toString().padStart(3, '0')} démarrée`, 'success');
            this.loadKitchenData(); // Refresh data
        } catch (error) {
            this.showErrorNotification('Erreur lors du démarrage de la commande');
        }
    }

    async completeOrder(orderId) {
        try {
            await this.updateOrderStatus(orderId, 'pret');
            this.showNotification(`Commande #CMD-${orderId.toString().padStart(3, '0')} terminée`, 'success');
            this.loadKitchenData(); // Refresh data
        } catch (error) {
            this.showErrorNotification('Erreur lors de la finalisation de la commande');
        }
    }

    async deliverOrder(orderId) {
        try {
            await this.updateOrderStatus(orderId, 'en_livraison');
            this.showNotification(`Commande #CMD-${orderId.toString().padStart(3, '0')} en livraison`, 'info');
            this.loadKitchenData(); // Refresh data
        } catch (error) {
            this.showErrorNotification('Erreur lors du passage en livraison');
        }
    }

    async pauseOrder(orderId) {
        // Implementation for pausing order
        this.showNotification(`Commande #CMD-${orderId.toString().padStart(3, '0')} mise en pause`, 'warning');
    }

    viewOrderDetails(orderId) {
        // Open order details modal or navigate to details page
        window.open(`/admin/orders/${orderId}`, '_blank');
    }

    async updateOrderStatus(orderId, newStatus) {
        const response = await fetch(`${this.apiBaseUrl}/orders/${orderId}/status`, {
            method: 'PATCH',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${localStorage.getItem('admin_token') || ''}`
            },
            body: JSON.stringify({ statut: newStatus })
        });

        if (!response.ok) {
            throw new Error(`Failed to update order status: ${response.status}`);
        }

        return response.json();
    }

    // Utility methods
    formatTime(seconds) {
        const minutes = Math.floor(seconds / 60);
        const remainingSeconds = seconds % 60;
        return `${minutes}:${remainingSeconds.toString().padStart(2, '0')}`;
    }

    initializeCountdowns() {
        this.updateCountdowns();
        setInterval(() => this.updateCountdowns(), 1000);
    }

    updateCountdowns() {
        document.querySelectorAll('.countdown-timer').forEach(timer => {
            let seconds = parseInt(timer.dataset.seconds);
            if (seconds >= 0) {
                timer.textContent = this.formatTime(seconds);
                timer.dataset.seconds = seconds + 1; // Increment for progress timers
                
                // Update color based on urgency for new orders
                const parentCard = timer.closest('.kitchen-order');
                if (parentCard && parentCard.dataset.type === 'new') {
                    if (seconds > 1200) { // More than 20 minutes
                        timer.className = 'text-danger countdown-timer';
                    } else if (seconds > 600) { // More than 10 minutes
                        timer.className = 'text-warning countdown-timer';
                    } else {
                        timer.className = 'text-muted countdown-timer';
                    }
                }
            }
        });
    }

    startAutoRefresh() {
        this.refreshTimer = setInterval(() => {
            this.loadKitchenData();
        }, this.refreshInterval);
    }

    stopAutoRefresh() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
            this.refreshTimer = null;
        }
    }

    handleManualRefresh() {
        const refreshBtn = document.getElementById('refreshOrders');
        if (refreshBtn) {
            refreshBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Actualisation...';
            refreshBtn.disabled = true;
        }

        this.loadKitchenData().finally(() => {
            if (refreshBtn) {
                refreshBtn.innerHTML = '<i class="fas fa-sync-alt"></i> Actualiser';
                refreshBtn.disabled = false;
            }
        });
    }

    printAllOrders() {
        window.print();
    }

    showNotification(message, type = 'info') {
        const iconMap = {
            success: 'fas fa-check-circle',
            error: 'fas fa-exclamation-triangle',
            warning: 'fas fa-exclamation-circle',
            info: 'fas fa-info-circle'
        };

        const toast = document.createElement('div');
        toast.className = `alert alert-${type} position-fixed`;
        toast.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        toast.innerHTML = `
            <div class="d-flex align-items-center">
                <i class="${iconMap[type] || iconMap.info} me-2"></i>
                ${message}
                <button type="button" class="btn-close ms-auto" onclick="this.parentElement.parentElement.remove()"></button>
            </div>
        `;
        document.body.appendChild(toast);

        // Auto-remove after 5 seconds
        setTimeout(() => {
            if (toast.parentElement) {
                toast.remove();
            }
        }, 5000);
    }

    showErrorNotification(message) {
        this.showNotification(message, 'error');
    }
}

// Initialize the kitchen manager when DOM is loaded
let kitchenManager;
document.addEventListener('DOMContentLoaded', function() {
    kitchenManager = new KitchenManager();
});

// Global functions for onclick handlers
window.kitchenManager = kitchenManager; 