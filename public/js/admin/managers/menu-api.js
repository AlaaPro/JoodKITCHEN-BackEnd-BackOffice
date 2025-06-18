/**
 * JoodKitchen Menu API
 * Shared API client for all menu-related operations
 */

class MenuAPI {
    constructor() {
        this.baseUrl = '/api/admin/menu';
    }

    async getAuthHeaders() {
        const token = localStorage.getItem('admin_token');
        return {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${token}`
        };
    }

    // ========================
    // CATEGORIES API
    // ========================

    async getCategories() {
        const url = `${this.baseUrl}/categories`;
        const headers = await this.getAuthHeaders();
        
        console.log('🔍 MenuAPI.getCategories() - Making request to:', url);
        console.log('🔍 Request headers:', headers);
        
        try {
            const response = await fetch(url, { headers });
            
            console.log('📡 Response status:', response.status);
            console.log('📡 Response ok:', response.ok);
            console.log('📡 Response headers:', Object.fromEntries(response.headers.entries()));
            
            if (!response.ok) {
                console.error('❌ API Error - Status:', response.status);
                console.error('❌ API Error - StatusText:', response.statusText);
                const errorText = await response.text();
                console.error('❌ API Error - Body:', errorText);
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('✅ Categories API Response:', data);
            
            return data;
        } catch (error) {
            console.error('💥 MenuAPI.getCategories() Error:', error);
            throw error;
        }
    }

    async createCategory(data) {
        const response = await fetch(`${this.baseUrl}/categories`, {
            method: 'POST',
            headers: await this.getAuthHeaders(),
            body: JSON.stringify(data)
        });
        return response.json();
    }

    async updateCategory(id, data) {
        const response = await fetch(`${this.baseUrl}/categories/${id}`, {
            method: 'PUT',
            headers: await this.getAuthHeaders(),
            body: JSON.stringify(data)
        });
        return response.json();
    }

    async deleteCategory(id) {
        const response = await fetch(`${this.baseUrl}/categories/${id}`, {
            method: 'DELETE',
            headers: await this.getAuthHeaders()
        });
        return response.json();
    }

    async reorderCategories(positions) {
        const url = `${this.baseUrl}/categories/reorder`;
        const headers = await this.getAuthHeaders();
        const body = JSON.stringify({ positions });
        
        console.log('🔄 MenuAPI.reorderCategories() - Making request to:', url);
        console.log('🔄 Request headers:', headers);
        console.log('🔄 Request body:', body);
        console.log('🔄 Positions data:', positions);
        
        try {
            const response = await fetch(url, {
                method: 'PUT',
                headers,
                body
            });
            
            console.log('📡 Reorder response status:', response.status);
            console.log('📡 Reorder response ok:', response.ok);
            
            if (!response.ok) {
                console.error('❌ Reorder API Error - Status:', response.status);
                console.error('❌ Reorder API Error - StatusText:', response.statusText);
                const errorText = await response.text();
                console.error('❌ Reorder API Error - Body:', errorText);
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            
            const data = await response.json();
            console.log('✅ Reorder API Response:', data);
            
            return data;
        } catch (error) {
            console.error('💥 MenuAPI.reorderCategories() Error:', error);
            throw error;
        }
    }

    // ========================
    // DISHES API
    // ========================

    async getDishes(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const response = await fetch(`${this.baseUrl}/dishes?${queryString}`, {
            headers: await this.getAuthHeaders()
        });
        return response.json();
    }

    async createDish(data) {
        const response = await fetch(`${this.baseUrl}/dishes`, {
            method: 'POST',
            headers: await this.getAuthHeaders(),
            body: JSON.stringify(data)
        });
        return response.json();
    }

    async getDish(id) {
        const response = await fetch(`${this.baseUrl}/dishes/${id}`, {
            headers: await this.getAuthHeaders()
        });
        return response.json();
    }

    async updateDish(id, data) {
        const response = await fetch(`${this.baseUrl}/dishes/${id}`, {
            method: 'PUT',
            headers: await this.getAuthHeaders(),
            body: JSON.stringify(data)
        });
        return response.json();
    }

    async deleteDish(id) {
        const response = await fetch(`${this.baseUrl}/dishes/${id}`, {
            method: 'DELETE',
            headers: await this.getAuthHeaders()
        });
        return response.json();
    }

    // ========================
    // MENUS API
    // ========================

    async getMenus(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const response = await fetch(`${this.baseUrl}/menus?${queryString}`, {
            headers: await this.getAuthHeaders()
        });
        return response.json();
    }

    async createMenu(data) {
        const response = await fetch(`${this.baseUrl}/menus`, {
            method: 'POST',
            headers: await this.getAuthHeaders(),
            body: JSON.stringify(data)
        });
        return response.json();
    }

    async getMenu(id) {
        const response = await fetch(`${this.baseUrl}/menus/${id}`, {
            headers: await this.getAuthHeaders()
        });
        return response.json();
    }

    async updateMenu(id, data) {
        const response = await fetch(`${this.baseUrl}/menus/${id}`, {
            method: 'PUT',
            headers: await this.getAuthHeaders(),
            body: JSON.stringify(data)
        });
        return response.json();
    }

    async deleteMenu(id) {
        const response = await fetch(`${this.baseUrl}/menus/${id}`, {
            method: 'DELETE',
            headers: await this.getAuthHeaders()
        });
        return response.json();
    }

    // ========================
    // STATISTICS
    // ========================

    async getStats() {
        const response = await fetch(`${this.baseUrl}/stats`, {
            headers: await this.getAuthHeaders()
        });
        return response.json();
    }
}

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MenuAPI;
} else {
    window.MenuAPI = MenuAPI;
} 