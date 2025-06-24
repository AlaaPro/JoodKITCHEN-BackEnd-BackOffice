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
    // PLATS API
    // ========================

    async getPlats(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const url = `${this.baseUrl}/plats?${queryString}`;
        const headers = await this.getAuthHeaders();

        try {
            const response = await fetch(url, { headers });
            if (!response.ok) {
                throw new Error(`HTTP ${response.status}: ${response.statusText}`);
            }
            return response.json();
        } catch (error) {
            console.error('💥 MenuAPI.getPlats() Error:', error);
            throw error;
        }
    }

    async createPlat(data) {
        const formData = new FormData();
        for (const key in data) {
            if (data[key] instanceof File) {
                formData.append(key, data[key]);
            } else if (data[key] !== null && data[key] !== undefined) {
                formData.append(key, data[key]);
            }
        }

        const response = await fetch(`${this.baseUrl}/plats`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('admin_token')}`
            },
            body: formData
        });
        return response.json();
    }

    async getPlat(id) {
        const response = await fetch(`${this.baseUrl}/plats/${id}`, {
            headers: await this.getAuthHeaders()
        });
        return response.json();
    }

    async updatePlat(id, data) {
        // For data updates, we send a standard PATCH request
        const response = await fetch(`${this.baseUrl}/plats/${id}`, {
            method: 'PATCH',
            headers: {
                ...await this.getAuthHeaders(),
                'Content-Type': 'application/merge-patch+json',
            },
            body: JSON.stringify(data)
        });
        return response.json();
    }

    async uploadPlatImage(id, imageFile) {
        const formData = new FormData();
        formData.append('image', imageFile);

        const response = await fetch(`${this.baseUrl}/plats/${id}/image`, {
            method: 'POST',
            headers: {
                'Authorization': `Bearer ${localStorage.getItem('admin_token')}`
            },
            body: formData
        });
        return response.json();
    }

    async deletePlat(id) {
        const response = await fetch(`${this.baseUrl}/plats/${id}`, {
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
        const url = `${this.baseUrl}/menus/${id}`;
        const headers = await this.getAuthHeaders();
        const body = JSON.stringify(data);
        
        console.log('🔄 DEBUG - MenuAPI.updateMenu() called');
        console.log('🔄 DEBUG - URL:', url);
        console.log('🔄 DEBUG - Method: PUT');
        console.log('🔄 DEBUG - Headers:', headers);
        console.log('🔄 DEBUG - Body:', body);
        
        const response = await fetch(url, {
            method: 'PUT',
            headers,
            body
        });
        
        console.log('🔄 DEBUG - Response status:', response.status);
        console.log('🔄 DEBUG - Response ok:', response.ok);
        
        const responseData = await response.json();
        console.log('🔄 DEBUG - Response data:', responseData);
        
        return responseData;
    }

    async deleteMenu(id) {
        const response = await fetch(`${this.baseUrl}/menus/${id}`, {
            method: 'DELETE',
            headers: await this.getAuthHeaders()
        });
        return response.json();
    }

    async getMenuStats() {
        const response = await fetch(`${this.baseUrl}/stats`, {
            headers: await this.getAuthHeaders()
        });
        return response.json();
    }

    async getDishesByCuisine(params = {}) {
        const queryString = new URLSearchParams(params).toString();
        const response = await fetch(`${this.baseUrl}/dishes/by-cuisine?${queryString}`, {
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