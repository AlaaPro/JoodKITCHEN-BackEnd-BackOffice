/**
 * JoodKitchen Menu Image Manager
 * Professional image handling with drag & drop, validation, and smooth UX
 */

class MenuImageManager {
    constructor(containerId, api) {
        this.container = document.getElementById(containerId);
        this.api = api;
        this.currentMenuId = null;
        this.currentImageFile = null;
        
        // Event handler references for cleanup
        this.eventHandlers = {
            uploadZoneClick: null,
            browseFilesClick: null,
            changeImageClick: null,
            removeImageClick: null,
            dragOver: null,
            dragLeave: null,
            drop: null,
            fileChange: null
        };
        
        // Configuration
        this.config = {
            maxFileSize: 5 * 1024 * 1024, // 5MB
            allowedTypes: ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'],
            allowedExtensions: ['.jpg', '.jpeg', '.png', '.webp'],
            minWidth: 200,
            minHeight: 150,
            maxWidth: 4000,
            maxHeight: 4000
        };
        
        this.init();
    }

    init() {
        if (!this.container) {
            console.error('❌ MenuImageManager: Container not found');
            return;
        }
        
        console.log('🚀 Initializing MenuImageManager...');
        this.setupImageUploadArea();
        this.setupEventListeners();
        console.log('✅ MenuImageManager initialized');
    }

    setupImageUploadArea() {
        this.container.innerHTML = `
            <div class="menu-image-upload-container">
                <!-- Image Preview Area -->
                <div class="image-preview-area" id="imagePreviewArea" style="display: none;">
                    <div class="image-preview-wrapper">
                        <img id="menuImagePreview" src="" alt="Menu Preview" class="menu-image-preview">
                        <div class="image-overlay">
                            <div class="image-actions">
                                <button type="button" class="btn btn-sm btn-outline-light" id="changeImageBtn">
                                    <i class="fas fa-edit"></i> Modifier
                                </button>
                                <button type="button" class="btn btn-sm btn-outline-danger" id="removeImageBtn">
                                    <i class="fas fa-trash"></i> Supprimer
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="image-info">
                        <small class="text-muted" id="imageInfo"></small>
                    </div>
                </div>

                <!-- Upload Area -->
                <div class="image-upload-area" id="imageUploadArea">
                    <div class="upload-zone" id="uploadZone">
                        <div class="upload-content">
                            <div class="upload-icon">
                                <i class="fas fa-cloud-upload-alt fa-3x text-primary"></i>
                            </div>
                            <h5 class="upload-title">Ajouter une image</h5>
                            <p class="upload-description">
                                Glissez-déposez votre image ici ou <span class="text-primary" id="browseFiles">cliquez pour parcourir</span>
                            </p>
                            <div class="upload-requirements">
                                <small class="text-muted">
                                    Formats: JPEG, PNG, WebP • Max: 5MB • Min: 200x150px
                                </small>
                            </div>
                        </div>
                        <input type="file" id="menuImageInput" class="d-none" accept="${this.config.allowedExtensions.join(',')}">
                    </div>
                </div>

                <!-- Upload Progress -->
                <div class="upload-progress" id="uploadProgress" style="display: none;">
                    <div class="progress mb-2">
                        <div class="progress-bar progress-bar-striped progress-bar-animated" 
                             role="progressbar" style="width: 0%" id="progressBar">
                        </div>
                    </div>
                    <div class="d-flex justify-content-between align-items-center">
                        <small class="text-muted" id="uploadStatus">Téléchargement en cours...</small>
                        <button type="button" class="btn btn-sm btn-outline-secondary" id="cancelUploadBtn">
                            Annuler
                        </button>
                    </div>
                </div>

                <!-- Error Messages -->
                <div class="alert alert-danger" id="imageError" style="display: none;"></div>
            </div>
        `;
    }

    setupEventListeners() {
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('menuImageInput');
        const browseFiles = document.getElementById('browseFiles');
        const changeImageBtn = document.getElementById('changeImageBtn');
        const removeImageBtn = document.getElementById('removeImageBtn');

        console.log('🔧 Setting up event listeners for MenuImageManager');
        console.log('Elements found:', {
            uploadZone: !!uploadZone,
            fileInput: !!fileInput,
            browseFiles: !!browseFiles,
            changeImageBtn: !!changeImageBtn,
            removeImageBtn: !!removeImageBtn
        });

        if (!uploadZone || !fileInput || !browseFiles) {
            console.warn('⚠️ Some required elements not found, event listeners will be set up later');
            return;
        }

        // Remove existing event listeners to prevent duplicates
        this.removeEventListeners();

        // Store handler references for cleanup
        this.eventHandlers.dragOver = this.handleDragOver.bind(this);
        this.eventHandlers.dragLeave = this.handleDragLeave.bind(this);
        this.eventHandlers.drop = this.handleDrop.bind(this);
        this.eventHandlers.fileChange = this.handleFileSelect.bind(this);
        this.eventHandlers.uploadZoneClick = () => {
            console.log('📁 Upload zone clicked');
            fileInput.click();
        };
        this.eventHandlers.browseFilesClick = (e) => {
            e.stopPropagation(); // Prevent event bubbling to uploadZone
            console.log('📁 Browse files text clicked');
            fileInput.click();
        };

        // Drag & Drop events
        uploadZone.addEventListener('dragover', this.eventHandlers.dragOver);
        uploadZone.addEventListener('dragleave', this.eventHandlers.dragLeave);
        uploadZone.addEventListener('drop', this.eventHandlers.drop);

        // File input events
        fileInput.addEventListener('change', this.eventHandlers.fileChange);
        
        // Make the entire upload zone clickable, not just the browse text
        uploadZone.addEventListener('click', this.eventHandlers.uploadZoneClick);
        
        // Keep the browse text clickable too (but prevent event bubbling to avoid double trigger)
        browseFiles.addEventListener('click', this.eventHandlers.browseFilesClick);
        
        // Optional elements
        if (changeImageBtn) {
            this.eventHandlers.changeImageClick = () => {
                console.log('🔄 Change image clicked');
                fileInput.click();
            };
            changeImageBtn.addEventListener('click', this.eventHandlers.changeImageClick);
        }

        if (removeImageBtn) {
            this.eventHandlers.removeImageClick = this.removeImage.bind(this);
            removeImageBtn.addEventListener('click', this.eventHandlers.removeImageClick);
        }

        console.log('✅ Event listeners set up successfully');
    }

    // Drag & Drop handlers
    handleDragOver(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('uploadZone').classList.add('drag-over');
    }

    handleDragLeave(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('uploadZone').classList.remove('drag-over');
    }

    handleDrop(e) {
        e.preventDefault();
        e.stopPropagation();
        document.getElementById('uploadZone').classList.remove('drag-over');

        const files = e.dataTransfer.files;
        if (files.length > 0) {
            this.processFile(files[0]);
        }
    }

    handleFileSelect(e) {
        const files = e.target.files;
        if (files.length > 0) {
            this.processFile(files[0]);
        }
    }

    async processFile(file) {
        console.log('📸 Processing file:', file.name);

        // Validate file
        const validation = this.validateFile(file);
        if (!validation.valid) {
            this.showError(validation.message);
            return;
        }

        // Show preview immediately
        this.showImagePreview(file);
        this.currentImageFile = file;

        // If we have a menu ID, upload immediately
        if (this.currentMenuId) {
            await this.uploadImage();
        }
    }

    validateFile(file) {
        // Check file type
        if (!this.config.allowedTypes.includes(file.type)) {
            return {
                valid: false,
                message: 'Type de fichier non autorisé. Utilisez: JPEG, PNG ou WebP'
            };
        }

        // Check file size
        if (file.size > this.config.maxFileSize) {
            const maxSizeMB = this.config.maxFileSize / (1024 * 1024);
            return {
                valid: false,
                message: `Fichier trop volumineux. Taille maximum: ${maxSizeMB}MB`
            };
        }

        return { valid: true };
    }

    showImagePreview(file) {
        const reader = new FileReader();
        reader.onload = (e) => {
            const preview = document.getElementById('menuImagePreview');
            const previewArea = document.getElementById('imagePreviewArea');
            const uploadArea = document.getElementById('imageUploadArea');
            const imageInfo = document.getElementById('imageInfo');

            preview.src = e.target.result;
            previewArea.style.display = 'block';
            uploadArea.style.display = 'none';

            // Show file info
            const sizeKB = (file.size / 1024).toFixed(1);
            imageInfo.textContent = `${file.name} • ${sizeKB} KB`;

            // Add smooth animation
            previewArea.style.opacity = '0';
            setTimeout(() => {
                previewArea.style.transition = 'opacity 0.3s ease';
                previewArea.style.opacity = '1';
            }, 10);
        };
        reader.readAsDataURL(file);
    }

    async uploadImage() {
        if (!this.currentImageFile || !this.currentMenuId) {
            console.error('❌ No file or menu ID for upload');
            return;
        }

        try {
            this.showUploadProgress();
            
            const response = await this.api.uploadMenuImage(this.currentMenuId, this.currentImageFile);
            
            if (response.success) {
                this.showSuccess('Image uploadée avec succès');
                this.updateImageDisplay(response.data);
                this.currentImageFile = null;
            } else {
                this.showError(response.message || 'Erreur lors de l\'upload');
                this.resetToUploadArea();
            }
        } catch (error) {
            console.error('❌ Upload error:', error);
            this.showError('Erreur lors de l\'upload de l\'image');
            this.resetToUploadArea();
        } finally {
            this.hideUploadProgress();
        }
    }

    async removeImage() {
        if (!this.currentMenuId) {
            console.error('❌ No menu ID for image removal');
            return;
        }

        if (!confirm('Êtes-vous sûr de vouloir supprimer cette image ?')) {
            return;
        }

        try {
            const response = await this.api.deleteMenuImage(this.currentMenuId);
            
            if (response.success) {
                this.showSuccess('Image supprimée avec succès');
                this.resetToUploadArea();
            } else {
                this.showError(response.message || 'Erreur lors de la suppression');
            }
        } catch (error) {
            console.error('❌ Delete error:', error);
            this.showError('Erreur lors de la suppression de l\'image');
        }
    }

    showUploadProgress() {
        document.getElementById('uploadProgress').style.display = 'block';
        document.getElementById('imagePreviewArea').style.opacity = '0.7';
        
        // Simulate progress (in real app, you'd track actual progress)
        let progress = 0;
        const interval = setInterval(() => {
            progress += Math.random() * 30;
            if (progress > 90) progress = 90;
            
            document.getElementById('progressBar').style.width = progress + '%';
            
            if (progress >= 90) {
                clearInterval(interval);
            }
        }, 200);
    }

    hideUploadProgress() {
        document.getElementById('uploadProgress').style.display = 'none';
        document.getElementById('progressBar').style.width = '100%';
        document.getElementById('imagePreviewArea').style.opacity = '1';
    }

    updateImageDisplay(imageData) {
        const preview = document.getElementById('menuImagePreview');
        const imageInfo = document.getElementById('imageInfo');
        
        preview.src = imageData.imageUrl;
        
        const sizeKB = imageData.imageSize ? (imageData.imageSize / 1024).toFixed(1) : 'N/A';
        imageInfo.textContent = `${imageData.imageName} • ${sizeKB} KB`;
    }

    resetToUploadArea() {
        document.getElementById('imagePreviewArea').style.display = 'none';
        document.getElementById('imageUploadArea').style.display = 'block';
        document.getElementById('menuImageInput').value = '';
        this.currentImageFile = null;
    }

    showError(message) {
        const errorDiv = document.getElementById('imageError');
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        
        setTimeout(() => {
            errorDiv.style.display = 'none';
        }, 5000);
    }

    showSuccess(message) {
        // You can implement a toast notification here
        console.log('✅', message);
    }

    // Public methods
    setMenuId(menuId) {
        this.currentMenuId = menuId;
        console.log('📸 MenuImageManager: Set menu ID to', menuId);
    }

    loadExistingImage(imageData) {
        console.log('📸 MenuImageManager.loadExistingImage() called with:', imageData);
        
        if (imageData && imageData.imageUrl) {
            console.log('✅ Loading existing image:', imageData.imageUrl);
            
            const preview = document.getElementById('menuImagePreview');
            const previewArea = document.getElementById('imagePreviewArea');
            const uploadArea = document.getElementById('imageUploadArea');
            const imageInfo = document.getElementById('imageInfo');

            if (preview && previewArea && uploadArea && imageInfo) {
                preview.src = imageData.imageUrl;
                previewArea.style.display = 'block';
                uploadArea.style.display = 'none';

                const sizeKB = imageData.imageSize ? (imageData.imageSize / 1024).toFixed(1) : 'N/A';
                imageInfo.textContent = `${imageData.imageName || 'Image'} • ${sizeKB} KB`;
                console.log('✅ Image preview displayed successfully');
            } else {
                console.error('❌ Required DOM elements not found for image preview');
            }
        } else {
            console.log('⚠️ No image data or imageUrl, showing upload area');
            this.resetToUploadArea();
        }
    }

    reset() {
        this.resetToUploadArea();
        this.currentMenuId = null;
        this.currentImageFile = null;
    }

    // Public method to re-setup event listeners if needed
    refreshEventListeners() {
        console.log('🔄 Refreshing event listeners...');
        this.setupEventListeners();
    }

    removeEventListeners() {
        const uploadZone = document.getElementById('uploadZone');
        const fileInput = document.getElementById('menuImageInput');
        const browseFiles = document.getElementById('browseFiles');
        const changeImageBtn = document.getElementById('changeImageBtn');
        const removeImageBtn = document.getElementById('removeImageBtn');

        if (uploadZone && this.eventHandlers.dragOver) {
            uploadZone.removeEventListener('dragover', this.eventHandlers.dragOver);
            uploadZone.removeEventListener('dragleave', this.eventHandlers.dragLeave);
            uploadZone.removeEventListener('drop', this.eventHandlers.drop);
            uploadZone.removeEventListener('click', this.eventHandlers.uploadZoneClick);
        }

        if (fileInput && this.eventHandlers.fileChange) {
            fileInput.removeEventListener('change', this.eventHandlers.fileChange);
        }

        if (browseFiles && this.eventHandlers.browseFilesClick) {
            browseFiles.removeEventListener('click', this.eventHandlers.browseFilesClick);
        }

        if (changeImageBtn && this.eventHandlers.changeImageClick) {
            changeImageBtn.removeEventListener('click', this.eventHandlers.changeImageClick);
        }

        if (removeImageBtn && this.eventHandlers.removeImageClick) {
            removeImageBtn.removeEventListener('click', this.eventHandlers.removeImageClick);
        }

        console.log('🧹 Event listeners removed');
    }
}

// Export for use in other files
if (typeof module !== 'undefined' && module.exports) {
    module.exports = MenuImageManager;
} else {
    window.MenuImageManager = MenuImageManager;
} 