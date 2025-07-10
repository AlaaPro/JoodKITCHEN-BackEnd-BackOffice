/**
 * OrderStatus utility class that gets its configuration from the PHP OrderStatus enum
 */
const OrderStatus = {
    // Status configuration will be loaded from backend
    config: null,

    // Initialize the configuration
    async init() {
        try {
            const response = await fetch('/api/order-status-config');
            if (!response.ok) throw new Error(`API Error: ${response.status} ${response.statusText}`);
            this.config = await response.json();
        } catch (error) {
            console.error('Error loading status config:', error);
            this.config = null;
        }
    },

    // Get status value by name
    getValue(statusName) {
        return this.config?.[statusName]?.value;
    },

    // Find status config by name or value (backward compatible)
    findStatus(statusInput) {
        if (!this.config) return null;
        
        // First try direct access by name (e.g., 'PENDING')
        if (this.config[statusInput]) {
            return this.config[statusInput];
        }
        
        // Then try by value (e.g., 'en_attente')
        return Object.values(this.config).find(s => s.value === statusInput);
    },

    // Get status label (supports both names and values)
    getLabel(status) {
        const statusConfig = this.findStatus(status);
        return statusConfig?.label || status;
    },

    // Get badge class for status (supports both names and values)
    getBadgeClass(status) {
        const statusConfig = this.findStatus(status);
        return statusConfig?.badge_class || 'bg-secondary';
    },

    // Get icon class for status (supports both names and values)
    getIconClass(status) {
        const statusConfig = this.findStatus(status);
        return statusConfig?.icon_class;
    },

    // Get notification message for status
    getNotification(statusName) {
        return this.config?.[statusName]?.notification;
    },

    // Get estimated delivery minutes for status
    getEstimatedDeliveryMinutes(statusName) {
        return this.config?.[statusName]?.estimated_delivery_minutes;
    },

    // Check if status can transition to another status
    canTransitionTo(fromStatus, toStatus) {
        const transitions = this.config?.[fromStatus]?.allowed_transitions || [];
        return transitions.includes(toStatus);
    },

    // Get all available statuses
    getAll() {
        if (!this.config) return [];
        
        return Object.keys(this.config).map(key => ({
            name: key,
            ...this.config[key]
        }));
    },

    // Get status badge HTML
    getBadgeHtml(statusValue) {
        return `<span class="badge ${this.getBadgeClass(statusValue)}">
            <i class="${this.getIconClass(statusValue)} me-1"></i>
            ${this.getLabel(statusValue)}
        </span>`;
    },

    // Get status options for select elements
    getSelectOptions(selectedStatus = '') {
        return this.getAll()
            .map(status => `<option value="${status.name}" ${status.name === selectedStatus ? 'selected' : ''}>
                ${this.getLabel(status.name)}
            </option>`)
            .join('');
    },

    // Check if status is final (no more changes allowed)
    isFinal(statusInput) {
        const statusConfig = this.findStatus(statusInput);
        return !statusConfig?.next_possible_statuses?.length;
    },

    // Check if status can be changed to newStatus
    canChangeTo(currentStatus, newStatus) {
        const statusConfig = this.findStatus(currentStatus);
        return statusConfig?.next_possible_statuses?.includes(newStatus) || false;
    },

    // ====== REUSABLE UI HELPERS ======

    /**
     * Populate any select element with status options
     * @param {string} selectId - ID of select element to populate
     * @param {string} placeholder - Placeholder option text (default: "Sélectionner un statut")
     * @param {string} selectedValue - Pre-selected status value
     */
    populateSelect(selectId, placeholder = "Sélectionner un statut", selectedValue = '') {
        const select = document.getElementById(selectId);
        if (!select || !this.config) return false;

        let optionsHtml = placeholder ? `<option value="">${placeholder}</option>` : '';
        
        this.getAll().forEach(status => {
            const selected = status.value === selectedValue ? 'selected' : '';
            optionsHtml += `<option value="${status.value}" ${selected}>${status.label}</option>`;
        });

        select.innerHTML = optionsHtml;
        return true;
    },

    /**
     * Create a complete status filter with all options
     * @param {string} filterId - ID for the filter select
     * @param {string} allOptionText - Text for "all" option (default: "Tous")
     */
    createStatusFilter(filterId, allOptionText = "Tous") {
        return `<select class="form-select" id="${filterId}">
            <option value="">${allOptionText}</option>
            ${this.getAll().map(status => 
                `<option value="${status.value}">${status.label}</option>`
            ).join('')}
        </select>`;
    },

    /**
     * Create status badges for any status array
     * @param {Array} statuses - Array of status values
     */
    createStatusBadges(statuses) {
        return statuses.map(status => this.getBadgeHtml(status)).join(' ');
    }
}; 