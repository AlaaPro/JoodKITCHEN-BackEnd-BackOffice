/**
 * AdminUtils - Utility functions for JoodKitchen Admin
 * Provides common UI operations, alerts, modals, validation, and helper functions
 */
(function(global) {
    'use strict';
    
    // Prevent redeclaration
    if (global.AdminUtils) {
        return;
    }

    class AdminUtils {
        constructor() {
            this.toastContainer = null;
            this.modalCount = 0;
            this.init();
        }

        init() {
            // Initialize toast container
            this.initToastContainer();
            
            // Set up global event listeners
            this.setupGlobalEvents();
        }

        initToastContainer() {
            let container = document.getElementById('toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'toast-container';
                container.className = 'toast-container position-fixed top-0 end-0 p-3';
                document.body.appendChild(container);
            }
            this.toastContainer = container;
        }

        setupGlobalEvents() {
            // Handle keyboard shortcuts
            document.addEventListener('keydown', (e) => {
                // Escape key to close modals
                if (e.key === 'Escape') {
                    this.closeTopModal();
                }
                
                // Ctrl+S to save forms
                if (e.ctrlKey && e.key === 's') {
                    e.preventDefault();
                    this.triggerFormSave();
                }
            });
        }

        // ==================== LOADING STATES ====================

        showLoading(message = 'Chargement...') {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = 'flex';
                
                // Update message if provided
                let messageEl = overlay.querySelector('.loading-message');
                if (!messageEl && message !== 'Chargement...') {
                    messageEl = document.createElement('div');
                    messageEl.className = 'loading-message text-white mt-3';
                    overlay.querySelector('.loading-spinner').parentNode.appendChild(messageEl);
                }
                if (messageEl) {
                    messageEl.textContent = message;
                }
            }
        }

        hideLoading() {
            const overlay = document.getElementById('loadingOverlay');
            if (overlay) {
                overlay.style.display = 'none';
            }
        }

        // ==================== ALERTS & NOTIFICATIONS ====================

        showAlert(message, type = 'info', duration = 5000) {
            const toast = this.createToast(message, type, duration);
            this.toastContainer.appendChild(toast);
            
            // Show toast with animation
            setTimeout(() => {
                toast.classList.add('show');
            }, 100);
            
            // Auto-hide after duration
            if (duration > 0) {
                setTimeout(() => {
                    this.hideToast(toast);
                }, duration);
            }
            
            return toast;
        }

        createToast(message, type, duration) {
            const toastId = `toast-${Date.now()}-${Math.random().toString(36).substr(2, 9)}`;
            
            const typeClasses = {
                'success': 'bg-success',
                'error': 'bg-danger',
                'warning': 'bg-warning',
                'info': 'bg-info'
            };
            
            const icons = {
                'success': 'fas fa-check-circle',
                'error': 'fas fa-exclamation-circle',
                'warning': 'fas fa-exclamation-triangle',
                'info': 'fas fa-info-circle'
            };
            
            const toast = document.createElement('div');
            toast.id = toastId;
            toast.className = `toast ${typeClasses[type] || typeClasses.info}`;
            toast.setAttribute('role', 'alert');
            toast.innerHTML = `
                <div class="toast-header">
                    <i class="${icons[type] || icons.info} me-2"></i>
                    <strong class="me-auto text-white">${this.capitalizeFirst(type)}</strong>
                    <button type="button" class="btn-close btn-close-white" onclick="AdminUtils.hideToast(document.getElementById('${toastId}'))"></button>
                </div>
                <div class="toast-body text-white">
                    ${message}
                </div>
            `;
            
            return toast;
        }

        hideToast(toast) {
            if (toast) {
                toast.classList.remove('show');
                setTimeout(() => {
                    if (toast.parentNode) {
                        toast.parentNode.removeChild(toast);
                    }
                }, 300);
            }
        }

        // ==================== MODALS ====================

        createModal(options = {}) {
            const modalId = `modal-${++this.modalCount}`;
            const {
                title = 'Modal',
                body = '',
                size = '', // 'lg', 'xl', 'sm'
                backdrop = true,
                keyboard = true,
                buttons = []
            } = options;
            
            const sizeClass = size ? `modal-${size}` : '';
            
            const modal = document.createElement('div');
            modal.className = 'modal fade';
            modal.id = modalId;
            modal.setAttribute('tabindex', '-1');
            modal.setAttribute('aria-labelledby', `${modalId}Label`);
            modal.setAttribute('aria-hidden', 'true');
            
            const buttonsHtml = buttons.map(btn => {
                const btnClass = btn.class || 'btn-secondary';
                const btnAction = btn.action || 'dismiss';
                const btnAttrs = btnAction === 'dismiss' ? 'data-coreui-dismiss="modal"' : '';
                
                return `<button type="button" class="btn ${btnClass}" ${btnAttrs} onclick="${btn.onclick || ''}">${btn.text}</button>`;
            }).join('');
            
            modal.innerHTML = `
                <div class="modal-dialog ${sizeClass}">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title" id="${modalId}Label">${title}</h5>
                            <button type="button" class="btn-close" data-coreui-dismiss="modal" aria-label="Close"></button>
                        </div>
                        <div class="modal-body">
                            ${body}
                        </div>
                        ${buttons.length > 0 ? `
                        <div class="modal-footer">
                            ${buttonsHtml}
                        </div>
                        ` : ''}
                    </div>
                </div>
            `;
            
            // Add to modals container
            const container = document.getElementById('modals-container') || document.body;
            container.appendChild(modal);
            
            // Initialize CoreUI modal
            const modalInstance = new coreui.Modal(modal, {
                backdrop: backdrop,
                keyboard: keyboard
            });
            
            return { modal, modalInstance, modalId };
        }

        showModal(options = {}) {
            const { modal, modalInstance } = this.createModal(options);
            modalInstance.show();
            return { modal, modalInstance };
        }

        confirmDialog(message, options = {}) {
            return new Promise((resolve) => {
                const {
                    title = 'Confirmation',
                    confirmText = 'Confirmer',
                    cancelText = 'Annuler',
                    type = 'warning'
                } = options;
                
                const typeIcons = {
                    'warning': 'fas fa-exclamation-triangle text-warning',
                    'danger': 'fas fa-exclamation-circle text-danger',
                    'info': 'fas fa-info-circle text-info'
                };
                
                const body = `
                    <div class="text-center">
                        <i class="${typeIcons[type] || typeIcons.warning}" style="font-size: 3rem;"></i>
                        <p class="mt-3">${message}</p>
                    </div>
                `;
                
                const { modal, modalInstance } = this.showModal({
                    title,
                    body,
                    buttons: [
                        {
                            text: cancelText,
                            class: 'btn-secondary',
                            onclick: `AdminUtils.resolveConfirm('${modal.id}', false)`
                        },
                        {
                            text: confirmText,
                            class: type === 'danger' ? 'btn-danger' : 'btn-primary',
                            onclick: `AdminUtils.resolveConfirm('${modal.id}', true)`
                        }
                    ]
                });
                
                // Store resolve function
                modal.confirmResolve = resolve;
                
                // Clean up when modal is hidden
                modal.addEventListener('hidden.coreui.modal', () => {
                    if (modal.parentNode) {
                        modal.parentNode.removeChild(modal);
                    }
                });
            });
        }

        resolveConfirm(modalId, result) {
            const modal = document.getElementById(modalId);
            if (modal && modal.confirmResolve) {
                modal.confirmResolve(result);
                coreui.Modal.getInstance(modal).hide();
            }
        }

        closeTopModal() {
            const modals = document.querySelectorAll('.modal.show');
            if (modals.length > 0) {
                const topModal = modals[modals.length - 1];
                coreui.Modal.getInstance(topModal)?.hide();
            }
        }

        // ==================== FORM UTILITIES ====================

        validateForm(form) {
            const errors = [];
            const requiredFields = form.querySelectorAll('[required]');
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    errors.push(`Le champ "${this.getFieldLabel(field)}" est requis`);
                    this.addFieldError(field, 'Ce champ est requis');
                } else {
                    this.removeFieldError(field);
                }
            });
            
            // Email validation
            const emailFields = form.querySelectorAll('input[type="email"]');
            emailFields.forEach(field => {
                if (field.value && !this.isValidEmail(field.value)) {
                    errors.push(`L'email "${field.value}" n'est pas valide`);
                    this.addFieldError(field, 'Email invalide');
                }
            });
            
            return errors;
        }

        addFieldError(field, message) {
            field.classList.add('is-invalid');
            
            // Remove existing error message
            this.removeFieldError(field);
            
            // Add new error message
            const feedback = document.createElement('div');
            feedback.className = 'invalid-feedback';
            feedback.textContent = message;
            field.parentNode.appendChild(feedback);
        }

        removeFieldError(field) {
            field.classList.remove('is-invalid');
            const feedback = field.parentNode.querySelector('.invalid-feedback');
            if (feedback) {
                feedback.remove();
            }
        }

        getFieldLabel(field) {
            const label = field.closest('.form-group')?.querySelector('label');
            return label?.textContent?.replace('*', '').trim() || field.name || field.placeholder || 'Champ';
        }

        serializeForm(form) {
            const formData = new FormData(form);
            const data = {};
            
            for (const [key, value] of formData.entries()) {
                if (data[key]) {
                    // Handle multiple values (checkboxes, multiple selects)
                    if (Array.isArray(data[key])) {
                        data[key].push(value);
                    } else {
                        data[key] = [data[key], value];
                    }
                } else {
                    data[key] = value;
                }
            }
            
            return data;
        }

        fillForm(form, data) {
            Object.keys(data).forEach(key => {
                const field = form.querySelector(`[name="${key}"]`);
                if (field) {
                    if (field.type === 'checkbox' || field.type === 'radio') {
                        field.checked = data[key];
                    } else {
                        field.value = data[key] || '';
                    }
                }
            });
        }

        triggerFormSave() {
            const saveButtons = document.querySelectorAll('[data-action="save"], .btn-save, button[type="submit"]');
            if (saveButtons.length > 0) {
                saveButtons[0].click();
            }
        }

        // ==================== DATA TABLES ====================

        initDataTable(tableId, options = {}) {
            const table = document.getElementById(tableId);
            if (!table) return null;
            
            const defaultOptions = {
                pageLength: 25,
                responsive: true,
                order: [[0, 'desc']],
                language: {
                    url: '//cdn.datatables.net/plug-ins/1.13.7/i18n/fr-FR.json'
                }
            };
            
            const mergedOptions = { ...defaultOptions, ...options };
            
            // Initialize DataTable (if available)
            if (typeof $.fn.DataTable !== 'undefined') {
                return $(table).DataTable(mergedOptions);
            }
            
            return null;
        }

        updateTableRow(tableId, rowId, data) {
            const table = document.getElementById(tableId);
            if (!table) return;
            
            const row = table.querySelector(`tr[data-id="${rowId}"]`);
            if (row) {
                Object.keys(data).forEach(key => {
                    const cell = row.querySelector(`[data-field="${key}"]`);
                    if (cell) {
                        cell.textContent = data[key];
                    }
                });
            }
        }

        // ==================== VALIDATION HELPERS ====================

        isValidEmail(email) {
            const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
            return emailRegex.test(email);
        }

        isValidPhone(phone) {
            const phoneRegex = /^[\+]?[1-9][\d]{0,15}$/;
            return phoneRegex.test(phone.replace(/\s+/g, ''));
        }

        isValidUrl(url) {
            try {
                new URL(url);
                return true;
            } catch {
                return false;
            }
        }

        // ==================== STRING UTILITIES ====================

        capitalizeFirst(str) {
            return str.charAt(0).toUpperCase() + str.slice(1);
        }

        truncate(str, length = 50) {
            return str.length > length ? str.substring(0, length) + '...' : str;
        }

        slugify(str) {
            return str
                .toString()
                .toLowerCase()
                .trim()
                .replace(/\s+/g, '-')
                .replace(/[^\w\-]+/g, '')
                .replace(/\-\-+/g, '-')
                .replace(/^-+/, '')
                .replace(/-+$/, '');
        }

        // ==================== DATE UTILITIES ====================

        formatDate(date, format = 'YYYY-MM-DD') {
            const d = new Date(date);
            const year = d.getFullYear();
            const month = String(d.getMonth() + 1).padStart(2, '0');
            const day = String(d.getDate()).padStart(2, '0');
            const hours = String(d.getHours()).padStart(2, '0');
            const minutes = String(d.getMinutes()).padStart(2, '0');
            
            return format
                .replace('YYYY', year)
                .replace('MM', month)
                .replace('DD', day)
                .replace('HH', hours)
                .replace('mm', minutes);
        }

        timeAgo(date) {
            const now = new Date();
            const diffMs = now - new Date(date);
            const diffMins = Math.floor(diffMs / 60000);
            
            if (diffMins < 1) return 'À l\'instant';
            if (diffMins < 60) return `${diffMins} min`;
            
            const diffHours = Math.floor(diffMins / 60);
            if (diffHours < 24) return `${diffHours}h`;
            
            const diffDays = Math.floor(diffHours / 24);
            return `${diffDays}j`;
        }

        formatFileSize(bytes) {
            if (bytes === 0) return '0 Bytes';
            const k = 1024;
            const sizes = ['Bytes', 'KB', 'MB', 'GB'];
            const i = Math.floor(Math.log(bytes) / Math.log(k));
            return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
        }

        isImageFile(file) {
            return file && file.type && file.type.startsWith('image/');
        }

        // ==================== LOCAL STORAGE ====================

        setLocalStorage(key, value) {
            try {
                localStorage.setItem(key, JSON.stringify(value));
                return true;
            } catch (error) {
                console.error('Error saving to localStorage:', error);
                return false;
            }
        }

        getLocalStorage(key, defaultValue = null) {
            try {
                const item = localStorage.getItem(key);
                return item ? JSON.parse(item) : defaultValue;
            } catch (error) {
                console.error('Error reading from localStorage:', error);
                return defaultValue;
            }
        }

        removeLocalStorage(key) {
            try {
                localStorage.removeItem(key);
                return true;
            } catch (error) {
                console.error('Error removing from localStorage:', error);
                return false;
            }
        }

        // ==================== URL UTILITIES ====================

        getQueryParam(param) {
            const urlParams = new URLSearchParams(window.location.search);
            return urlParams.get(param);
        }

        setQueryParam(param, value) {
            const url = new URL(window.location);
            url.searchParams.set(param, value);
            window.history.replaceState({}, '', url);
        }

        removeQueryParam(param) {
            const url = new URL(window.location);
            url.searchParams.delete(param);
            window.history.replaceState({}, '', url);
        }

        // ==================== SINGLETON ====================

        static getInstance() {
            if (!AdminUtils.instance) {
                AdminUtils.instance = new AdminUtils();
            }
            return AdminUtils.instance;
        }
    }

    // Create global instance
    global.AdminUtils = AdminUtils.getInstance();

})(window); 