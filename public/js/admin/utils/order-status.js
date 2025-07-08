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
            if (!response.ok) throw new Error('Failed to load status config');
            this.config = await response.json();
        } catch (error) {
            console.error('Error loading status config:', error);
        }
    },

    // Get status value by name
    getValue(statusName) {
        return this.config?.[statusName]?.value;
    },

    // Get status label
    getLabel(status) {
        return this.config?.[status]?.label || status;
    },

    // Get badge class for status
    getBadgeClass(status) {
        return this.config?.[status]?.badge_class || 'bg-secondary';
    },

    // Get icon class for status
    getIconClass(statusName) {
        return this.config?.[statusName]?.icon_class;
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
        return Object.keys(this.config || {}).map(key => ({
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
    isFinal(statusValue) {
        const status = Object.values(this.config || {}).find(s => s.value === statusValue);
        return !status?.next_possible_statuses?.length;
    },

    // Check if status can be changed to newStatus
    canChangeTo(currentStatus, newStatus) {
        const status = Object.values(this.config || {}).find(s => s.value === currentStatus);
        return status?.next_possible_statuses?.includes(newStatus) || false;
    }
}; 