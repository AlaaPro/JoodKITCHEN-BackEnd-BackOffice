/**
 * Orders API Client
 * Handles all API communications for orders management
 */
class OrdersAPI {
    constructor() {
        this.baseUrl = '/api/admin/orders';
        this.token = AdminAuth.getToken();
    }

    /**
     * Get orders with filtering and pagination
     */
    async getOrders(filters = {}) {
        const params = new URLSearchParams();
        
        if (filters.page) params.append('page', filters.page);
        if (filters.limit) params.append('limit', filters.limit);
        if (filters.status) params.append('status', filters.status);
        if (filters.type) params.append('type', filters.type);
        if (filters.search) params.append('search', filters.search);
        if (filters.date) params.append('date', filters.date);

        const url = `${this.baseUrl}?${params.toString()}`;
        
        const response = await fetch(url, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${this.token}`,
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            if (response.status === 401) {
                AdminAuth.handleTokenExpired();
                throw new Error('Token expiré, veuillez vous reconnecter');
            }
            throw new Error(`Erreur HTTP: ${response.status}`);
        }

        return await response.json();
    }

    /**
     * Get orders statistics with optional date range
     */
    async getOrdersStats(startDate = null, endDate = null) {
        try {
            let url = '/api/admin/orders/stats';
            
            // Add date parameters if provided
            const params = new URLSearchParams();
            if (startDate) params.append('start_date', startDate);
            if (endDate) params.append('end_date', endDate);
            
            if (params.toString()) {
                url += '?' + params.toString();
            }
            
            console.log('🔗 Calling stats API:', url);
            
            const response = await fetch(url, {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${this.token}`
                }
            });

            console.log('📡 Stats API Response Status:', response.status);

            if (!response.ok) {
                throw new Error(`HTTP error! status: ${response.status}`);
            }

            const data = await response.json();
            console.log('📦 Stats API Raw Data:', data);

            if (!data.success) {
                throw new Error(data.error || data.message || 'Erreur lors de la récupération des statistiques');
            }

            return data.data;
            
        } catch (error) {
            console.error('❌ Stats API Error:', error);
            throw error;
        }
    }

    /**
     * Get order details by ID
     */
    async getOrderDetails(orderId) {
        const response = await fetch(`${this.baseUrl}/${orderId}`, {
            method: 'GET',
            headers: {
                'Authorization': `Bearer ${this.token}`,
                'Accept': 'application/json'
            }
        });

        if (!response.ok) {
            if (response.status === 401) {
                AdminAuth.handleTokenExpired();
                throw new Error('Token expiré, veuillez vous reconnecter');
            }
            if (response.status === 404) {
                throw new Error('Commande non trouvée');
            }
            throw new Error(`Erreur HTTP: ${response.status}`);
        }

        return await response.json();
    }

    /**
     * Update order status
     */
    async updateOrderStatus(orderId, status) {
        const response = await fetch(`${this.baseUrl}/${orderId}/status`, {
            method: 'PUT',
            headers: {
                'Authorization': `Bearer ${this.token}`,
                'Content-Type': 'application/json',
                'Accept': 'application/json'
            },
            body: JSON.stringify({ status })
        });

        if (!response.ok) {
            if (response.status === 401) {
                AdminAuth.handleTokenExpired();
                throw new Error('Token expiré, veuillez vous reconnecter');
            }
            if (response.status === 404) {
                throw new Error('Commande non trouvée');
            }
            if (response.status === 400) {
                throw new Error('Statut invalide');
            }
            throw new Error(`Erreur HTTP: ${response.status}`);
        }

        return await response.json();
    }
} 