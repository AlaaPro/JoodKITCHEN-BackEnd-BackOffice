/**
 * ProfilePictureManager - Handle profile picture upload, display and management
 * Features: Drag & drop, image preview, validation, API integration
 */
class ProfilePictureManager {
    constructor() {
        this.maxFileSize = 2 * 1024 * 1024; // 2MB
        this.allowedTypes = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
        this.API_BASE_URL = '/api/profile-picture';
        this.clickListenerAdded = false;
        
        this.initializeEventListeners();
    }

    initializeEventListeners() {
        // Handle file input changes
        document.addEventListener('change', (e) => {
            if (e.target.classList.contains('profile-picture-input')) {
                this.handleFileSelect(e);
            }
        });

        // Handle drag and drop events
        document.addEventListener('dragover', (e) => {
            if (e.target.closest('.profile-picture-dropzone')) {
                e.preventDefault();
                e.target.closest('.profile-picture-dropzone').classList.add('dragover');
            }
        });

        document.addEventListener('dragleave', (e) => {
            if (e.target.closest('.profile-picture-dropzone')) {
                e.target.closest('.profile-picture-dropzone').classList.remove('dragover');
            }
        });

        document.addEventListener('drop', (e) => {
            if (e.target.closest('.profile-picture-dropzone')) {
                e.preventDefault();
                const dropzone = e.target.closest('.profile-picture-dropzone');
                dropzone.classList.remove('dragover');
                
                const files = e.dataTransfer.files;
                if (files.length > 0) {
                    this.handleFileUpload(files[0], dropzone);
                }
            }
        });

        // Handle remove picture buttons
        document.addEventListener('click', (e) => {
            if (e.target.closest('.remove-profile-picture')) {
                e.preventDefault();
                const button = e.target.closest('.remove-profile-picture');
                const userId = button.dataset.userId;
                this.removeProfilePicture(userId);
            }
        });

        // Handle profile picture clicks for upload (only once per instance)
        if (!this.clickListenerAdded) {
            document.addEventListener('click', (e) => {
                if (e.target.closest('.profile-picture-upload-trigger')) {
                    e.preventDefault();
                    e.stopPropagation();
                    
                    const trigger = e.target.closest('.profile-picture-upload-trigger');
                    console.log('🖱️ Profile picture upload trigger clicked:', trigger);
                    
                    // Look for file input in multiple places
                    let fileInput = trigger.querySelector('.profile-picture-input');
                    if (!fileInput) {
                        fileInput = trigger.closest('.profile-picture-container')?.querySelector('.profile-picture-input');
                    }
                    if (!fileInput) {
                        fileInput = trigger.parentElement?.querySelector('.profile-picture-input');
                    }
                    
                    if (fileInput) {
                        console.log('📁 Found file input, triggering click:', fileInput);
                        fileInput.click();
                    } else {
                        console.warn('⚠️ No file input found for profile picture upload trigger');
                        // Create a temporary file input if none exists
                        const tempInput = document.createElement('input');
                        tempInput.type = 'file';
                        tempInput.accept = 'image/*';
                        tempInput.className = 'profile-picture-input';
                        tempInput.style.display = 'none';
                        
                        tempInput.addEventListener('change', (evt) => {
                            this.handleFileSelect(evt);
                            tempInput.remove();
                        });
                        
                        trigger.appendChild(tempInput);
                        tempInput.click();
                    }
                }
            });
            this.clickListenerAdded = true;
        }
    }

    /**
     * Handle file selection from input
     */
    handleFileSelect(event) {
        console.log('📂 File selected:', event.target.files);
        const file = event.target.files[0];
        if (file) {
            console.log('✅ File details:', {
                name: file.name,
                size: file.size,
                type: file.type
            });
            
            // Find the container - try multiple approaches
            let container = event.target.closest('.profile-picture-container');
            if (!container) {
                container = event.target.closest('.profile-picture-dropzone');
            }
            if (!container) {
                // Create a temporary container with user ID if available
                const userId = event.target.dataset.userId || 
                              event.target.closest('[data-user-id]')?.dataset.userId;
                container = document.createElement('div');
                container.className = 'profile-picture-container';
                if (userId) {
                    container.dataset.userId = userId;
                }
                document.body.appendChild(container);
            }
            
            console.log('📦 Using container:', container);
            this.handleFileUpload(file, container);
        } else {
            console.warn('⚠️ No file selected');
        }
    }

    /**
     * Handle file upload with validation
     */
    async handleFileUpload(file, container) {
        // Validate file
        const validation = this.validateFile(file);
        if (!validation.valid) {
            this.showError(container, validation.message);
            return;
        }

        // Show loading state
        this.showLoading(container);

        try {
            const userId = container.dataset.userId;
            const result = await this.uploadFile(file, userId);
            
            if (result.success) {
                this.showSuccess(container, 'Photo de profil mise à jour');
                this.updateProfilePictureDisplay(container, result.photo_url);
                
                // Trigger update event for other components
                this.triggerProfilePictureUpdate(userId || 'current', result);
            } else {
                this.showError(container, result.error || 'Erreur lors de l\'upload');
            }
        } catch (error) {
            console.error('Upload error:', error);
            this.showError(container, 'Erreur de connexion');
        } finally {
            this.hideLoading(container);
        }
    }

    /**
     * Validate uploaded file
     */
    validateFile(file) {
        if (!file) {
            return { valid: false, message: 'Aucun fichier sélectionné' };
        }

        if (file.size > this.maxFileSize) {
            return { valid: false, message: 'Fichier trop volumineux (max 2MB)' };
        }

        if (!this.allowedTypes.includes(file.type)) {
            return { valid: false, message: 'Type de fichier non autorisé (JPEG, PNG, GIF, WebP uniquement)' };
        }

        return { valid: true };
    }

    /**
     * Upload file to server
     */
    async uploadFile(file, userId = null) {
        const formData = new FormData();
        formData.append('profile_picture', file);

        const url = userId ? `${this.API_BASE_URL}/upload/${userId}` : `${this.API_BASE_URL}/upload`;
        
        // Get token from AdminAuth or localStorage
        const token = (window.AdminAuth && AdminAuth.getToken) ? 
                     AdminAuth.getToken() : 
                     localStorage.getItem('admin_token');
        
        const headers = {
            'X-Requested-With': 'XMLHttpRequest'
        };
        
        if (token) {
            headers['Authorization'] = `Bearer ${token}`;
        }
        
        const response = await fetch(url, {
            method: 'POST',
            headers: headers,
            body: formData
        });

        return await response.json();
    }

    /**
     * Remove profile picture
     */
    async removeProfilePicture(userId = null) {
        if (!confirm('Êtes-vous sûr de vouloir supprimer cette photo de profil ?')) {
            return;
        }

        try {
            const url = userId ? `${this.API_BASE_URL}/delete/${userId}` : `${this.API_BASE_URL}/delete`;
            
            // Get token from AdminAuth or localStorage
            const token = (window.AdminAuth && AdminAuth.getToken) ? 
                         AdminAuth.getToken() : 
                         localStorage.getItem('admin_token');
            
            const headers = {
                'X-Requested-With': 'XMLHttpRequest',
                'Content-Type': 'application/json'
            };
            
            if (token) {
                headers['Authorization'] = `Bearer ${token}`;
            }
            
            const response = await fetch(url, {
                method: 'DELETE',
                headers: headers
            });

            const result = await response.json();

            if (result.success) {
                // Find all containers for this user
                const containers = userId 
                    ? document.querySelectorAll(`[data-user-id="${userId}"] .profile-picture-container`)
                    : document.querySelectorAll('.profile-picture-container:not([data-user-id])');

                containers.forEach(container => {
                    this.updateProfilePictureDisplay(container, null);
                    this.showSuccess(container, 'Photo supprimée');
                });

                // Trigger update event
                this.triggerProfilePictureUpdate(userId || 'current', { photo_url: null });
            } else {
                alert('Erreur lors de la suppression: ' + (result.error || 'Erreur inconnue'));
            }
        } catch (error) {
            console.error('Delete error:', error);
            alert('Erreur de connexion');
        }
    }

    /**
     * Update profile picture display
     */
    updateProfilePictureDisplay(container, photoUrl) {
        const img = container.querySelector('.profile-picture-img');
        const placeholder = container.querySelector('.profile-picture-placeholder');
        const removeBtn = container.querySelector('.remove-profile-picture');

        if (photoUrl) {
            if (img) {
                img.src = photoUrl;
                img.style.display = 'block';
            }
            if (placeholder) {
                placeholder.style.display = 'none';
            }
            if (removeBtn) {
                removeBtn.style.display = 'block';
            }
        } else {
            if (img) {
                img.style.display = 'none';
            }
            if (placeholder) {
                placeholder.style.display = 'flex';
            }
            if (removeBtn) {
                removeBtn.style.display = 'none';
            }
        }
    }

    /**
     * Show loading state
     */
    showLoading(container) {
        const loading = container.querySelector('.profile-picture-loading');
        if (loading) {
            loading.style.display = 'flex';
        }

        // Disable upload controls
        const inputs = container.querySelectorAll('input, button');
        inputs.forEach(input => {
            input.disabled = true;
        });
    }

    /**
     * Hide loading state
     */
    hideLoading(container) {
        const loading = container.querySelector('.profile-picture-loading');
        if (loading) {
            loading.style.display = 'none';
        }

        // Re-enable upload controls
        const inputs = container.querySelectorAll('input, button');
        inputs.forEach(input => {
            input.disabled = false;
        });
    }

    /**
     * Show error message
     */
    showError(container, message) {
        this.showMessage(container, message, 'error');
    }

    /**
     * Show success message
     */
    showSuccess(container, message) {
        this.showMessage(container, message, 'success');
    }

    /**
     * Show message with type
     */
    showMessage(container, message, type = 'info') {
        // Remove existing messages
        const existingMessages = container.querySelectorAll('.profile-picture-message');
        existingMessages.forEach(msg => msg.remove());

        // Create new message
        const messageDiv = document.createElement('div');
        messageDiv.className = `profile-picture-message alert alert-${type === 'error' ? 'danger' : type === 'success' ? 'success' : 'info'} alert-dismissible fade show`;
        messageDiv.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;

        container.appendChild(messageDiv);

        // Auto-hide success messages
        if (type === 'success') {
            setTimeout(() => {
                if (messageDiv.parentNode) {
                    messageDiv.remove();
                }
            }, 3000);
        }
    }

    /**
     * Trigger custom event for profile picture update
     */
    triggerProfilePictureUpdate(userId, data) {
        const event = new CustomEvent('profilePictureUpdated', {
            detail: { userId, data }
        });
        document.dispatchEvent(event);
    }

    /**
     * Generate profile picture HTML for user
     */
    generateProfilePictureHTML(user, options = {}) {
        const {
            size = 'medium',
            editable = false,
            showRemoveButton = false,
            containerClasses = '',
            userId = null
        } = options;

        const photoUrl = user.photo_profil_url || user.photo_url || null;
        const userName = `${user.nom} ${user.prenom}`;
        const userInitials = `${user.nom?.charAt(0) || ''}${user.prenom?.charAt(0) || ''}`.toUpperCase();

        const sizeClasses = {
            small: 'profile-picture-sm',
            medium: 'profile-picture-md',
            large: 'profile-picture-lg'
        };

        return `
            <div class="profile-picture-container ${containerClasses} ${sizeClasses[size]}" 
                 ${userId ? `data-user-id="${userId}"` : ''}>
                
                <div class="profile-picture-wrapper ${editable ? 'profile-picture-upload-trigger' : ''}">
                    ${photoUrl ? `
                        <img src="${photoUrl}" alt="${userName}" class="profile-picture-img">
                    ` : `
                        <div class="profile-picture-placeholder">
                            <span class="profile-picture-initials">${userInitials}</span>
                        </div>
                    `}
                    
                    ${editable ? `
                        <div class="profile-picture-overlay">
                            <i class="fas fa-camera"></i>
                            <span>Changer</span>
                        </div>
                    ` : ''}
                </div>

                ${showRemoveButton && photoUrl ? `
                    <button type="button" class="btn btn-sm btn-outline-danger remove-profile-picture" 
                            data-user-id="${userId || ''}" title="Supprimer la photo">
                        <i class="fas fa-times"></i>
                    </button>
                ` : ''}

                ${editable ? `
                    <input type="file" class="profile-picture-input" accept="image/*" style="display: none;" 
                           ${userId ? `data-user-id="${userId}"` : ''}>
                ` : ''}

                <div class="profile-picture-loading" style="display: none;">
                    <div class="spinner-border spinner-border-sm" role="status">
                        <span class="visually-hidden">Upload...</span>
                    </div>
                </div>
            </div>
        `;
    }

    /**
     * Create dropzone HTML
     */
    createDropzoneHTML(userId = null) {
        return `
            <div class="profile-picture-container profile-picture-dropzone ${userId ? `data-user-id="${userId}"` : ''}">
                <div class="dropzone-content">
                    <i class="fas fa-cloud-upload-alt fa-2x mb-2"></i>
                    <p class="mb-2">Glissez votre photo ici</p>
                    <p class="small text-muted">ou</p>
                    <button type="button" class="btn btn-outline-primary btn-sm profile-picture-upload-trigger">
                        Parcourir
                    </button>
                    <input type="file" class="profile-picture-input" accept="image/*" style="display: none;">
                </div>
                
                <div class="profile-picture-loading" style="display: none;">
                    <div class="spinner-border" role="status">
                        <span class="visually-hidden">Upload...</span>
                    </div>
                </div>
            </div>
        `;
    }
}

// Initialize ProfilePictureManager
window.ProfilePictureManager = ProfilePictureManager;

// Auto-initialize when DOM is ready (only once)
document.addEventListener('DOMContentLoaded', function() {
    if (typeof window.profilePictureManager === 'undefined') {
        window.profilePictureManager = new ProfilePictureManager();
        console.log('✅ ProfilePictureManager initialized');
    } else {
        console.log('ℹ️ ProfilePictureManager already exists, skipping initialization');
    }
}); 