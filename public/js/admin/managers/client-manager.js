/**
 * ClientManager - CRUD operations for Client Profiles
 * Following same pattern as KitchenStaffManager for consistency
 */
class ClientManager {
    constructor() {
        this.currentPage = 1;
        this.perPage = 10;
        this.searchQuery = '';
        this.filters = {};
        this.sort = {
            field: 'id',
            order: 'ASC'
        };
        this.selectedItems = new Set();
        
        this.init().catch(error => {
            console.error('❌ Failed to initialize Client Manager:', error);
        });
    }

    async init() {
        console.log('🔧 Initializing Client Manager...');
        
        this.bindEvents();
        await this.waitForDOM();
        await this.loadClients();
        console.log('✅ Client Manager fully initialized');
    }

    async waitForDOM() {
        if (document.readyState === 'loading') {
            await new Promise(resolve => document.addEventListener('DOMContentLoaded', resolve));
        }
        
        let attempts = 0;
        const maxAttempts = 20;
        
        while (attempts < maxAttempts) {
            const tbody = document.querySelector('table tbody');
            if (tbody) {
                console.log('✅ DOM ready - table found after', attempts, 'attempts');
                return;
            }
            
            console.log(`🔄 Waiting for DOM... attempt ${attempts + 1}/${maxAttempts}`);
            await new Promise(resolve => setTimeout(resolve, 100));
            attempts++;
        }
    }

    bindEvents() {
        // Search input
        const searchInput = document.getElementById('clientSearchInput');
        if (searchInput) {
            searchInput.addEventListener('input', (e) => {
                this.searchQuery = e.target.value;
                this.loadClients();
            });
        }

        // Filter dropdowns
        const statusFilter = document.querySelector('select[name="status"]');
        if (statusFilter) {
            statusFilter.addEventListener('change', (e) => {
                this.filters.status = e.target.value;
                this.loadClients();
            });
        }

        const dateFilter = document.querySelector('input[type="date"]');
        if (dateFilter) {
            dateFilter.addEventListener('change', (e) => {
                this.filters.date = e.target.value;
                this.loadClients();
            });
        }

        const zoneFilter = document.querySelector('select[name="zone"]');
        if (zoneFilter) {
            zoneFilter.addEventListener('change', (e) => {
                this.filters.zone = e.target.value;
                this.loadClients();
            });
        }

        // Reset filters
        const resetBtn = document.querySelector('button[type="reset"]');
        if (resetBtn) {
            resetBtn.addEventListener('click', () => {
                this.resetFilters();
            });
        }

        // Per page selection
        const perPageSelect = document.getElementById('perPageSelect');
        if (perPageSelect) {
            perPageSelect.addEventListener('change', (e) => {
                this.perPage = parseInt(e.target.value);
                this.currentPage = 1; // Reset to first page when changing items per page
                this.loadClients();
            });
        }

        // Pagination controls
        document.addEventListener('click', (e) => {
            if (e.target.matches('.page-link')) {
                e.preventDefault();
                const page = e.target.dataset.page;
                if (page) {
                    this.currentPage = parseInt(page);
                    this.loadClients();
                }
            }
        });

        // Add sort handlers
        const headers = document.querySelectorAll('th[data-sort]');
        headers.forEach(header => {
            header.addEventListener('click', () => {
                const field = header.dataset.sort;
                if (field) {
                    // Toggle sort order if same field, otherwise default to ASC
                    this.sort.order = (field === this.sort.field) 
                        ? (this.sort.order === 'ASC' ? 'DESC' : 'ASC')
                        : 'ASC';
                    this.sort.field = field;
                    
                    // Update sort indicators
                    headers.forEach(h => h.classList.remove('sorting_asc', 'sorting_desc'));
                    header.classList.add(this.sort.order === 'ASC' ? 'sorting_asc' : 'sorting_desc');
                    
                    this.loadClients();
                }
            });
        });
    }

    async loadClients() {
        try {
            console.log('🔄 Loading clients...');
            
            const response = await ClientAPI.getClients({
                page: this.currentPage,
                perPage: this.perPage,
                search: this.searchQuery,
                sort: this.sort.field,
                order: this.sort.order,
                ...this.filters
            });
            
            if (response && response.success) {
                this.clients = response.data;
                this.stats = response.stats;
                this.renderClients(this.clients);
                this.updateStats(this.stats);
                this.updatePagination(
                    response.pagination.total,
                    response.pagination.page,
                    response.pagination.perPage
                );
                console.log(`📊 Loaded ${this.clients.length} clients`);
            } else {
                throw new Error('Invalid API response format');
            }
        } catch (error) {
            console.error('❌ Error loading clients:', error);
            this.handleLoadError(error);
        }
    }

    renderClients(clients) {
        const tbody = document.querySelector('table tbody');
        if (!tbody) return;

        if (!clients || clients.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="text-muted">
                            <i class="fas fa-users fa-3x mb-3"></i>
                            <h5>Aucun client trouvé</h5>
                            <p>Aucun client ne correspond aux critères de recherche.</p>
                        </div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = clients.map(client => this.createClientRow(client)).join('');
    }

    createClientRow(client) {
        const profile = client.client_profile;
        const lastOrder = client.last_order;
        const timeAgo = this.getTimeAgo(client.created_at);
        const commandeCount = client.total_orders || 0;
        const hasActiveOrders = commandeCount > 0 && lastOrder?.statut !== 'livre' && lastOrder?.statut !== 'annule';
        
        // Actions column
        const actions = `
            <div class="btn-group btn-group-sm">
                <button type="button" class="btn btn-outline-primary" onclick="clientManager.showClientDetails(${client.id})" title="Voir détails">
                    <i class="fas fa-eye"></i>
                </button>
            </div>
        `;

        // Status column with toggle switch
        const statusToggle = `
            <div class="form-check form-switch">
                <input class="form-check-input" type="checkbox" role="switch" 
                    id="statusToggle_${client.id}" 
                    ${client.is_active ? 'checked' : ''} 
                    onchange="clientManager.toggleClientStatus(${client.id}, this.checked)">
                <label class="form-check-label" for="statusToggle_${client.id}">
                    <span class="badge ${client.is_active ? 'jood-primary-bg' : 'bg-secondary'}">
                        ${client.is_active ? 'Actif' : 'Inactif'}
                    </span>
                </label>
            </div>
        `;

        return `
            <tr data-client-id="${client.id}">
                <td class="ps-4">
                    <input type="checkbox" class="form-check-input">
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="green-icon-bg me-3">
                            ${client.photo_profil_url 
                                ? `<img src="${client.photo_profil_url}" alt="${client.nom}" class="profile-picture-img">`
                                : `<i class="fas fa-user"></i>`
                            }
                        </div>
                        <div>
                            <div class="fw-semibold">${client.prenom} ${client.nom}</div>
                            <small class="text-muted">#CLT-${String(client.id).padStart(3, '0')}</small>
                        </div>
                    </div>
                </td>
                <td>
                    <div>${client.email}</div>
                    <small class="text-muted">${client.telephone || 'N/A'}</small>
                </td>
                <td>
                    <div>${new Date(client.created_at).toLocaleDateString('fr-FR')}</div>
                    <small class="text-muted">${timeAgo}</small>
                </td>
                <td>
                    <span class="fw-bold ${commandeCount > 0 ? 'jood-primary' : 'text-muted'}">${commandeCount}</span> 
                    ${commandeCount === 1 ? 'commande' : 'commandes'}
                    <div>
                        <small class="text-muted">
                            ${lastOrder ? 'Dernière: ' + new Date(lastOrder.date).toLocaleDateString('fr-FR') : 'Aucune commande'}
                        </small>
                    </div>
                </td>
                <td class="fw-bold ${client.total_spent > 0 ? 'jood-primary' : 'text-muted'}">
                    ${this.formatPrice(client.total_spent || 0)}
                </td>
                <td>
                    ${statusToggle}
                </td>
                <td>
                    ${actions}
                </td>
            </tr>
        `;
    }

    generateStatusBadge(isActive) {
        return isActive 
            ? '<span class="badge jood-primary-bg">Actif</span>'
            : '<span class="badge bg-secondary">Inactif</span>';
    }

    updateStats(stats) {
        // Update widget values
        const elements = {
            'totalClients': stats.total || 0,
            'newClients': stats.new_30_days || 0,
            'activeClients': stats.active || 0,
            'averageOrder': this.formatPrice(stats.average_order || 0)
        };

        for (const [id, value] of Object.entries(elements)) {
            const element = document.querySelector(`.widget-value[data-stat="${id}"]`);
            if (element) element.textContent = value;
        }
    }

    updatePagination(total, currentPage, perPage) {
        const totalPages = Math.ceil(total / perPage);
        const paginationElement = document.getElementById('pagination');
        const paginationInfo = document.getElementById('paginationInfo');
        const paginationContainer = document.querySelector('.card-footer');
        
        // Hide pagination if all items fit on one page
        if (total <= perPage) {
            if (paginationContainer) {
                paginationContainer.style.display = 'none';
            }
            return;
        } else {
            if (paginationContainer) {
                paginationContainer.style.display = 'block';
            }
        }
        
        if (paginationInfo) {
            const start = (currentPage - 1) * perPage + 1;
            const end = Math.min(start + perPage - 1, total);
            paginationInfo.textContent = `Affichage de ${start} à ${end} sur ${total} clients`;
        }
        
        if (paginationElement) {
            let html = '';
            
            // Previous button
            html += `
                <li class="page-item ${currentPage === 1 ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentPage - 1}" ${currentPage === 1 ? 'tabindex="-1"' : ''}>
                        Précédent
                    </a>
                </li>
            `;
            
            // Page numbers
            for (let i = 1; i <= totalPages; i++) {
                if (
                    i === 1 || // First page
                    i === totalPages || // Last page
                    (i >= currentPage - 1 && i <= currentPage + 1) // Pages around current
                ) {
                    html += `
                        <li class="page-item ${i === currentPage ? 'active' : ''}">
                            <a class="page-link" href="#" data-page="${i}">${i}</a>
                        </li>
                    `;
                } else if (
                    i === currentPage - 2 ||
                    i === currentPage + 2
                ) {
                    html += `
                        <li class="page-item disabled">
                            <span class="page-link">...</span>
                        </li>
                    `;
                }
            }
            
            // Next button
            html += `
                <li class="page-item ${currentPage === totalPages ? 'disabled' : ''}">
                    <a class="page-link" href="#" data-page="${currentPage + 1}" ${currentPage === totalPages ? 'tabindex="-1"' : ''}>
                        Suivant
                    </a>
                </li>
            `;
            
            paginationElement.innerHTML = html;
        }
    }

    handleLoadError(error) {
        const tbody = document.querySelector('table tbody');
        if (tbody) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="8" class="text-center py-5">
                        <div class="text-danger">
                            <i class="fas fa-exclamation-triangle fa-3x mb-3"></i>
                            <h5>Erreur de chargement</h5>
                            <p class="text-muted">Impossible de charger la liste des clients.</p>
                            <button class="btn btn-outline-primary" onclick="clientManager.loadClients()">
                                <i class="fas fa-refresh"></i> Réessayer
                            </button>
                        </div>
                    </td>
                </tr>
            `;
        }
    }

    resetFilters() {
        this.searchQuery = '';
        this.filters = {};
        
        // Reset form inputs
        const searchInput = document.getElementById('clientSearchInput');
        if (searchInput) searchInput.value = '';
        
        const filterInputs = document.querySelectorAll('#filtersPanel select, #filtersPanel input');
        filterInputs.forEach(input => {
            if (input.type === 'date') input.value = '';
            else input.selectedIndex = 0;
        });
        
        this.loadClients();
    }

    formatPrice(price) {
        return new Intl.NumberFormat('fr-FR', { 
            style: 'currency', 
            currency: 'EUR'
        }).format(price);
    }

    getTimeAgo(date) {
        const now = new Date();
        const past = new Date(date);
        const diffTime = Math.abs(now - past);
        const diffDays = Math.ceil(diffTime / (1000 * 60 * 60 * 24));
        
        if (diffDays < 30) return `Il y a ${diffDays} jours`;
        if (diffDays < 365) {
            const months = Math.floor(diffDays / 30);
            return `Il y a ${months} mois`;
        }
        const years = Math.floor(diffDays / 365);
        return `Il y a ${years} an${years > 1 ? 's' : ''}`;
    }

    async showClientDetails(clientId) {
        try {
            console.log('🔍 Loading client details for ID:', clientId);
            
            // Get modal elements
            const modal = new coreui.Modal(document.getElementById('clientDetailsModal'));
            const modalBody = document.querySelector('#clientDetailsModal .modal-body');
            const originalContent = modalBody.innerHTML;
            
            // Show modal first
            modal.show();
            
            // Show loading state while preserving modal structure
            const loadingHtml = `
                <div class="text-center py-5">
                    <div class="spinner-border text-primary" role="status">
                        <span class="visually-hidden">Chargement...</span>
                    </div>
                    <p class="mt-3 text-muted">Chargement des détails du client...</p>
                </div>
            `;
            
            // Create a temporary container for loading state
            const loadingContainer = document.createElement('div');
            loadingContainer.innerHTML = loadingHtml;
            modalBody.appendChild(loadingContainer);
            
            // Hide original content
            Array.from(modalBody.children).forEach(child => {
                if (child !== loadingContainer) {
                    child.style.display = 'none';
                }
            });
            
            try {
                // Load client details
                const response = await ClientAPI.getClientDetails(clientId);
                
                if (!response || !response.success) {
                    throw new Error('Failed to load client details');
                }
                
                const client = response.data;
                
                // Load client history
                const historyResponse = await ClientAPI.getClientHistory(clientId);
                const history = historyResponse.success ? historyResponse.data : null;
                
                // Remove loading state and show original content
                loadingContainer.remove();
                Array.from(modalBody.children).forEach(child => {
                    child.style.display = '';
                });
                
                // Update modal content
                this.updateClientDetailsModal(client, history);
                
            } catch (error) {
                // In case of error, restore original content
                modalBody.innerHTML = originalContent;
                throw error;
            }
            
        } catch (error) {
            console.error('Error showing client details:', error);
            AdminUtils.showAlert('Erreur lors du chargement des détails du client', 'error');
            
            // Close modal on error
            const modal = coreui.Modal.getInstance(document.getElementById('clientDetailsModal'));
            if (modal) {
                modal.hide();
            }
        }
    }

    updateClientDetailsModal(client, history) {
        // Helper function to safely update element text content
        const updateElement = (id, content) => {
            const element = document.getElementById(id);
            if (element) {
                element.innerHTML = content;
            }
        };

        // Update profile picture
        updateElement('clientDetailsPicture', client.photo_profil_url 
            ? `<img src="${client.photo_profil_url}" alt="${client.nom}" class="profile-picture-img">`
            : `<img src="/uploads/profile_pictures/default.jpg" alt="Default" class="profile-picture-img">`
        );

        // Update client name and ID
        updateElement('clientDetailsName', `${client.prenom} ${client.nom}`);
        updateElement('clientDetailsId', `#CLT-${String(client.id).padStart(3, '0')}`);

        // Update personal information
        updateElement('clientDetailsLastName', client.nom);
        updateElement('clientDetailsFirstName', client.prenom);
        updateElement('clientDetailsEmail', client.email);
        updateElement('clientDetailsPhone', client.telephone || 'Non renseigné');
        updateElement('clientDetailsStatus', `<span class="badge ${client.is_active ? 'jood-primary-bg' : 'bg-secondary'}">${client.is_active ? 'Actif' : 'Inactif'}</span>`);
        updateElement('clientDetailsCreatedAt', new Date(client.created_at).toLocaleDateString('fr-FR'));

        // Update order information
        const orderCount = history?.orders?.length || 0;
        const totalSpent = history?.orders?.reduce((sum, order) => sum + parseFloat(order.total || 0), 0) || 0;
        const lastOrder = history?.orders?.[0];  // Orders should be sorted by date desc

        updateElement('clientDetailsTotalOrders', `${orderCount} commande${orderCount > 1 ? 's' : ''}`);
        updateElement('clientDetailsLastOrder', lastOrder 
            ? `${new Date(lastOrder.date).toLocaleDateString('fr-FR')} (${this.formatPrice(lastOrder.total)})`
            : 'Aucune commande'
        );
        updateElement('clientDetailsTotalSpent', this.formatPrice(totalSpent));
        updateElement('clientDetailsPoints', client.client_profile?.points_fidelite || 0);
        updateElement('clientDetailsAddress', client.client_profile?.adresse_livraison || 'Non renseignée');

        // Update order history table
        const ordersTableBody = document.querySelector('#clientOrdersTable tbody');
        if (ordersTableBody) {
            if (!history?.orders || history.orders.length === 0) {
                ordersTableBody.innerHTML = `
                    <tr>
                        <td colspan="4" class="text-center text-muted">
                            Aucun historique de commandes
                        </td>
                    </tr>
                `;
            } else {
                ordersTableBody.innerHTML = history.orders.map(order => `
                    <tr>
                        <td>${new Date(order.date).toLocaleDateString('fr-FR')}</td>
                        <td>#CMD-${String(order.id).padStart(3, '0')}</td>
                        <td><span class="badge ${this.getOrderStatusClass(order.statut)}">${this.getOrderStatusLabel(order.statut)}</span></td>
                        <td>${this.formatPrice(order.total)}</td>
                    </tr>
                `).join('');
            }
        }
    }

    getOrderStatusClass(status) {
        const statusClasses = {
            'en_livraison': 'bg-info',
            'livre': 'bg-success',
            'en_preparation': 'bg-warning',
            'annule': 'bg-danger',
            'en_attente': 'bg-secondary'
        };
        return statusClasses[status] || 'bg-secondary';
    }

    getOrderStatusLabel(status) {
        const statusLabels = {
            'en_livraison': 'En livraison',
            'livre': 'Livrée',
            'en_preparation': 'En préparation',
            'annule': 'Annulée',
            'en_attente': 'En attente'
        };
        return statusLabels[status] || status;
    }

    // Add toggle status handler
    async toggleClientStatus(clientId, isActive) {
        try {
            // Get token from AdminAuth
            const token = AdminAuth.getToken();
            if (!token) {
                throw new Error('Not authenticated');
            }

            const response = await fetch(`/api/clients/${clientId}/toggle-status`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`,
                    'X-Requested-With': 'XMLHttpRequest'
                },
                credentials: 'include',
                body: JSON.stringify({ is_active: isActive })
            });

            if (!response.ok) {
                const data = await response.json();
                throw new Error(data.message || 'Failed to update status');
            }

            const data = await response.json();

            // Update the badge text and class
            const label = document.querySelector(`#statusToggle_${clientId}`).nextElementSibling;
            const badge = label.querySelector('.badge');
            badge.className = `badge ${isActive ? 'jood-primary-bg' : 'bg-secondary'}`;
            badge.textContent = isActive ? 'Actif' : 'Inactif';

            // Show success message
            this.showNotification('success', data.message || `Le statut du client a été ${isActive ? 'activé' : 'désactivé'}`);

        } catch (error) {
            console.error('Error toggling client status:', error);
            // Revert the toggle if there was an error
            document.querySelector(`#statusToggle_${clientId}`).checked = !isActive;
            this.showNotification('error', error.message || 'Erreur lors de la mise à jour du statut');
        }
    }

    showNotification(type, message) {
        // You can implement this using your preferred notification library
        // For now, we'll use a simple alert
        alert(message);
    }
}

// Global Client API class
class ClientAPI {
    static async getClients(params = {}) {
        const queryParams = new URLSearchParams(params).toString();
        const response = await AdminAPI.request('GET', `/clients?${queryParams}`);
        return response;
    }
    
    static async getClientDetails(id) {
        const response = await AdminAPI.request('GET', `/clients/${id}`);
        return response;
    }
    
    static async updateClient(id, data) {
        const response = await AdminAPI.request('PUT', `/clients/${id}`, data);
        return response;
    }
    
    static async getClientHistory(id) {
        const response = await AdminAPI.request('GET', `/clients/${id}/history`);
        return response;
    }
}

// Export for global use
window.ClientManager = ClientManager;
window.ClientAPI = ClientAPI; 