/**
 * JoodKitchen Category Management System
 * Handles hierarchical category CRUD operations with drag & drop
 */

class CategoryManager {
    constructor() {
        this.api = new MenuAPI();
        this.categories = [];
        this.draggedElement = null;
        this.initializeEventListeners();
        this.setupDragAndDrop();
    }

    initializeEventListeners() {
        // Create category button
        const createBtn = document.querySelector('[data-bs-target="#addCategoryModal"]');
        if (createBtn) {
            createBtn.addEventListener('click', () => this.showCreateModal());
        }

        // Save category button
        const saveBtn = document.getElementById('saveCategoryBtn');
        if (saveBtn) {
            saveBtn.addEventListener('click', () => this.saveCategory());
        }

        // Modal form reset when closed
        const modal = document.getElementById('addCategoryModal');
        if (modal) {
            // Listen for both Bootstrap and CoreUI modal events
            modal.addEventListener('hidden.bs.modal', () => this.resetForm());
            modal.addEventListener('hidden.coreui.modal', () => this.resetForm());
            
            // Close modal on backdrop click
            modal.addEventListener('click', (e) => {
                if (e.target === modal) {
                    console.log('🔲 Modal backdrop clicked - closing modal');
                    this.hideModal();
                }
            });
        }

        // Color picker update preview
        const colorInput = document.querySelector('[name="couleur"]');
        if (colorInput) {
            colorInput.addEventListener('change', (e) => this.updateColorPreview(e.target.value));
        }

        // Icon input with preview
        const iconInput = document.querySelector('[name="icon"]');
        if (iconInput) {
            iconInput.addEventListener('input', (e) => this.updateIconPreview(e.target.value));
        }

        // Global keyboard listener for ESC key and Enter
        document.addEventListener('keydown', (e) => {
            const modal = document.getElementById('addCategoryModal');
            if (modal && modal.classList.contains('show')) {
                if (e.key === 'Escape') {
                    console.log('⌨️ ESC key pressed - closing modal');
                    this.hideModal();
                } else if (e.key === 'Enter' && !e.shiftKey) {
                    // Allow Shift+Enter for new lines in textarea
                    const activeElement = document.activeElement;
                    if (activeElement.tagName !== 'TEXTAREA') {
                        e.preventDefault();
                        console.log('⌨️ Enter key pressed - saving category');
                        this.saveCategory();
                    }
                }
            }
        });
    }

    setupDragAndDrop() {
        console.log('🔄 Setting up drag and drop...');
        
        // Setup sortable for category reordering
        const categoryTree = document.querySelector('.category-tree');
        if (!categoryTree) {
            console.warn('⚠️ No .category-tree element found for drag & drop');
            return;
        }
        
        console.log('📦 Category tree element found:', categoryTree);
        
        if (typeof Sortable === 'undefined') {
            console.error('❌ Sortable.js is not loaded');
            return;
        }
        
        console.log('✅ Sortable.js is available');
        
        try {
            const sortableInstance = new Sortable(categoryTree, {
                animation: 150,
                ghostClass: 'sortable-ghost',
                chosenClass: 'sortable-chosen',
                dragClass: 'sortable-drag',
                handle: '.category-handle', // Only allow dragging by handle
                onStart: (evt) => {
                    console.log('🎯 Drag started:', evt);
                },
                onEnd: (evt) => {
                    console.log('🎯 Drag ended:', evt);
                    console.log('🔢 Old index:', evt.oldIndex, 'New index:', evt.newIndex);
                    this.handleCategoryReorder(evt);
                }
            });
            console.log('✅ Sortable instance created:', sortableInstance);
        } catch (error) {
            console.error('💥 Error setting up Sortable:', error);
        }
    }

    async loadCategories() {
        console.log('🔄 CategoryManager.loadCategories() - Starting...');
        
        try {
            this.showLoading(true);
            console.log('📡 Calling API to get categories...');
            
            const response = await this.api.getCategories();
            console.log('📦 API Response received:', response);
            
            if (response && response.success) {
                console.log('✅ Categories loaded successfully:', response.data);
                console.log('📊 Number of categories:', response.data?.length || 0);
                
                this.categories = response.data;
                this.renderCategoriesTree(response.data);
                this.updateCategoryStats(response.data);
                this.populateParentCategorySelect(response.data);
                
                console.log('🎨 UI components updated');
            } else {
                const errorMsg = response?.message || 'Error loading categories - Invalid response format';
                console.error('❌ Categories loading failed:', errorMsg);
                console.error('❌ Full response:', response);
                this.showError(errorMsg);
            }
        } catch (error) {
            console.error('💥 CategoryManager.loadCategories() Error:', error);
            this.showError('Error loading categories: ' + error.message);
        } finally {
            this.showLoading(false);
            console.log('🔄 CategoryManager.loadCategories() - Finished');
        }
    }

    renderCategoriesTree(categories) {
        const container = document.querySelector('.category-tree');
        if (!container) return;

        container.innerHTML = '';
        
        categories.forEach(category => {
            const categoryElement = this.createCategoryElement(category);
            container.appendChild(categoryElement);
        });

        // Re-setup drag and drop after rendering
        this.setupDragAndDrop();
    }

    createCategoryElement(category) {
        const div = document.createElement('div');
        div.className = 'category-item mb-3';
        div.dataset.categoryId = category.id;
        div.dataset.position = category.position;
        
        div.innerHTML = `
            <div class="d-flex align-items-center p-3 border rounded ${!category.actif ? 'opacity-50' : ''}">
                <div class="category-handle me-3 cursor-move">
                    <i class="fas fa-grip-vertical text-muted"></i>
                </div>
                <div class="category-icon me-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" 
                         style="width: 40px; height: 40px; background: ${category.couleur || '#a9b73e'};">
                        <i class="fas ${category.icon || 'fa-utensils'} text-white"></i>
                    </div>
                </div>
                <div class="flex-grow-1">
                    <h6 class="mb-1 fw-bold">${category.nom}</h6>
                    <small class="text-muted">
                        ${category.dishCount || 0} plat${category.dishCount > 1 ? 's' : ''} • Position ${category.position}
                        ${!category.visible ? ' • Masqué' : ''}
                    </small>
                    ${category.description ? `<div class="small text-muted mt-1">${category.description}</div>` : ''}
                </div>
                <div class="category-stats me-3">
                    <span class="badge jood-primary-bg">${category.dishCount || 0} plat${category.dishCount > 1 ? 's' : ''}</span>
                    ${category.sousCategories?.length > 0 ? `<span class="badge bg-info ms-1">${category.sousCategories.length} sous</span>` : ''}
                </div>
                <div class="category-actions">
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary" onclick="categoryManager.editCategory(${category.id})" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </button>
                        <button class="btn btn-outline-success" onclick="categoryManager.addSubCategory(${category.id})" title="Sous-catégorie">
                            <i class="fas fa-plus"></i>
                        </button>
                        <button class="btn btn-outline-secondary" onclick="categoryManager.toggleVisibility(${category.id})" title="${category.visible ? 'Masquer' : 'Afficher'}">
                            <i class="fas ${category.visible ? 'fa-eye-slash' : 'fa-eye'}"></i>
                        </button>
                        <button class="btn btn-outline-danger" onclick="categoryManager.deleteCategory(${category.id})" title="Supprimer">
                            <i class="fas fa-trash"></i>
                        </button>
                    </div>
                </div>
            </div>
            ${this.renderSubCategories(category.sousCategories || [])}
        `;
        
        return div;
    }

    renderSubCategories(subCategories) {
        if (!subCategories || subCategories.length === 0) return '';

        const subContainer = document.createElement('div');
        subContainer.className = 'sub-categories ms-5 mt-2';
        
        subCategories.forEach(subCategory => {
            const subDiv = document.createElement('div');
            subDiv.className = 'd-flex align-items-center p-2 border rounded mb-2';
            subDiv.dataset.categoryId = subCategory.id;
            
            subDiv.innerHTML = `
                <div class="category-icon me-3">
                    <div class="rounded-circle d-flex align-items-center justify-content-center" 
                         style="width: 30px; height: 30px; background: ${subCategory.couleur || '#6c757d'};">
                        <i class="fas ${subCategory.icon || 'fa-tag'} text-white"></i>
                    </div>
                </div>
                <div class="flex-grow-1">
                    <span class="fw-semibold">${subCategory.nom}</span>
                    <small class="text-muted ms-2">${subCategory.dishCount || 0} plat${subCategory.dishCount > 1 ? 's' : ''}</small>
                </div>
                <span class="badge bg-secondary me-2">${subCategory.dishCount || 0}</span>
                <div class="btn-group btn-group-sm">
                    <button class="btn btn-outline-primary btn-sm" onclick="categoryManager.editCategory(${subCategory.id})" title="Modifier">
                        <i class="fas fa-edit"></i>
                    </button>
                    <button class="btn btn-outline-danger btn-sm" onclick="categoryManager.deleteCategory(${subCategory.id})" title="Supprimer">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
            `;
            subContainer.appendChild(subDiv);
        });
        
        return subContainer.outerHTML;
    }

    showCreateModal() {
        this.resetForm();
        this.setModalTitle('Nouvelle Catégorie');
        const modal = document.getElementById('addCategoryModal');
        if (modal) {
            try {
                // Try different CoreUI namespaces
                let Modal = null;
                
                if (typeof window.coreui !== 'undefined' && window.coreui.Modal) {
                    Modal = window.coreui.Modal;
                    console.log('🎯 Using window.coreui.Modal');
                } else if (typeof coreui !== 'undefined' && coreui.Modal) {
                    Modal = coreui.Modal;
                    console.log('🎯 Using coreui.Modal');
                } else if (typeof window.Modal !== 'undefined') {
                    Modal = window.Modal;
                    console.log('🎯 Using window.Modal');
                }
                
                if (Modal) {
                    const modalInstance = new Modal(modal);
                    modalInstance.show();
                    console.log('✅ Modal opened with CoreUI');
                } else {
                    // Manual show with Bootstrap classes
                    console.log('⚠️ Using manual modal show');
                    modal.style.display = 'block';
                    modal.classList.add('show');
                    document.body.classList.add('modal-open');
                    
                    // Add backdrop
                    const backdrop = document.createElement('div');
                    backdrop.className = 'modal-backdrop fade show';
                    backdrop.id = 'category-modal-backdrop';
                    document.body.appendChild(backdrop);
                }
            } catch (error) {
                console.error('Error opening modal:', error);
                // Force manual fallback
                modal.style.display = 'block';
                modal.classList.add('show');
                document.body.classList.add('modal-open');
            }
        }
    }

    addSubCategory(parentId) {
        this.resetForm();
        this.setModalTitle('Nouvelle Sous-Catégorie');
        
        // Pre-select parent category
        const parentSelect = document.querySelector('[name="parentId"]');
        if (parentSelect) {
            parentSelect.value = parentId;
            parentSelect.disabled = true;
        }
        
        const modal = document.getElementById('addCategoryModal');
        if (modal) {
            const modalInstance = new coreui.Modal(modal);
            modalInstance.show();
        }
    }

    async editCategory(id) {
        try {
            const category = this.categories.find(c => c.id === id) || 
                            this.categories.flatMap(c => c.sousCategories || []).find(sc => sc.id === id);
            
            if (category) {
                this.fillForm(category);
                this.setModalTitle('Modifier Catégorie');
                const modal = document.getElementById('addCategoryModal');
                if (modal) {
                    const modalInstance = new coreui.Modal(modal);
                    modalInstance.show();
                }
            }
        } catch (error) {
            this.showError('Error loading category: ' + error.message);
        }
    }

    async saveCategory() {
        const form = document.getElementById('categoryForm');
        const formData = new FormData(form);
        
        const data = {
            nom: formData.get('nom').trim(),
            description: formData.get('description')?.trim() || null,
            icon: formData.get('icon')?.trim() || 'fa-utensils',
            couleur: formData.get('couleur') || '#a9b73e',
            parentId: formData.get('parentId') || null,
            actif: formData.has('actif'),
            visible: formData.has('visible')
        };

        // Validation
        if (!data.nom) {
            this.showError('Le nom de la catégorie est requis');
            return;
        }

        try {
            const categoryId = form.dataset.categoryId;
            let response;

            this.setFormLoading(true);

            if (categoryId) {
                response = await this.api.updateCategory(categoryId, data);
            } else {
                response = await this.api.createCategory(data);
            }

            if (response.success) {
                this.hideModal();
                await this.loadCategories();
                this.showSuccess(response.message);
            } else {
                this.showError(response.message || 'Error saving category');
            }
        } catch (error) {
            console.error('CategoryManager saveCategory error:', error);
            this.showError('Erreur lors de l\'enregistrement: ' + error.message);
        } finally {
            this.setFormLoading(false);
        }
    }

    async deleteCategory(id) {
        const category = this.categories.find(c => c.id === id) || 
                        this.categories.flatMap(c => c.sousCategories || []).find(sc => sc.id === id);
        
        if (!category) return;

        const dishCount = category.dishCount || 0;
        const confirmMessage = dishCount > 0 
            ? `Cette catégorie contient ${dishCount} plat(s). Êtes-vous sûr de vouloir la supprimer ?`
            : `Êtes-vous sûr de vouloir supprimer la catégorie "${category.nom}" ?`;

        if (!confirm(confirmMessage)) return;

        try {
            const response = await this.api.deleteCategory(id);
            if (response.success) {
                await this.loadCategories();
                this.showSuccess(response.message);
            } else {
                this.showError(response.message);
            }
        } catch (error) {
            this.showError('Error deleting category: ' + error.message);
        }
    }

    async toggleVisibility(id) {
        const category = this.categories.find(c => c.id === id);
        if (!category) return;

        try {
            const response = await this.api.updateCategory(id, {
                ...category,
                visible: !category.visible
            });

            if (response.success) {
                await this.loadCategories();
                this.showSuccess(`Catégorie ${category.visible ? 'masquée' : 'affichée'}`);
            }
        } catch (error) {
            this.showError('Error updating category visibility: ' + error.message);
        }
    }

    async handleCategoryReorder(evt) {
        console.log('🔄 handleCategoryReorder called with event:', evt);
        
        const categoryElement = evt.item;
        const newPosition = evt.newIndex + 1;
        const categoryId = parseInt(categoryElement.dataset.categoryId);

        console.log('📦 Reorder details:');
        console.log('  - Category element:', categoryElement);
        console.log('  - Category ID:', categoryId);
        console.log('  - Old index:', evt.oldIndex);
        console.log('  - New index:', evt.newIndex);
        console.log('  - New position:', newPosition);

        // Check if position actually changed
        if (evt.oldIndex === evt.newIndex) {
            console.log('⏭️ No position change detected - skipping reorder');
            return;
        }

        try {
            // Build new positions array
            const positions = {};
            const categoryItems = document.querySelectorAll('.category-item[data-category-id]');
            
            console.log('📋 Found category items:', categoryItems.length);
            
            categoryItems.forEach((item, index) => {
                const id = parseInt(item.dataset.categoryId);
                positions[id] = index + 1;
                console.log(`  - Category ${id} -> Position ${index + 1}`);
            });

            console.log('📡 Calling API with positions:', positions);
            const response = await this.api.reorderCategories(positions);
            console.log('📦 API Response:', response);
            
            if (response && response.success) {
                console.log('✅ Reorder successful - reloading categories');
                this.showSuccess('Ordre des catégories mis à jour');
                // Update local data
                await this.loadCategories();
            } else {
                console.error('❌ API returned error:', response?.message || 'Unknown error');
                console.error('❌ Full response:', response);
                this.showError('Erreur lors du réordonnancement: ' + (response?.message || 'Erreur inconnue'));
                
                // DON'T reload the page - just revert the UI
                console.log('🔄 Reverting drag operation...');
                this.revertDragOperation(evt);
            }
        } catch (error) {
            console.error('💥 Exception in handleCategoryReorder:', error);
            console.error('💥 Error details:', {
                name: error.name,
                message: error.message,
                stack: error.stack
            });
            
            this.showError('Erreur technique lors du réordonnancement: ' + error.message);
            
            // DON'T reload the page - just revert the UI
            console.log('🔄 Reverting drag operation due to exception...');
            this.revertDragOperation(evt);
        }
    }

    revertDragOperation(evt) {
        try {
            console.log('🔄 Attempting to revert drag operation...');
            
            // Simple approach: reload categories to restore original order
            setTimeout(() => {
                console.log('🔄 Reloading categories to restore order...');
                this.loadCategories();
            }, 500);
            
        } catch (error) {
            console.error('💥 Error reverting drag operation:', error);
            // Only as last resort, reload the page
            console.log('💥 Last resort: reloading page...');
            setTimeout(() => location.reload(), 1000);
        }
    }

    populateParentCategorySelect(categories) {
        const parentSelect = document.querySelector('[name="parentId"]');
        if (!parentSelect) return;

        // Clear existing options except first one
        while (parentSelect.children.length > 1) {
            parentSelect.removeChild(parentSelect.lastChild);
        }

        // Add root categories only (no subcategories as parents of subcategories)
        categories.filter(c => !c.parent).forEach(category => {
            const option = document.createElement('option');
            option.value = category.id;
            option.textContent = category.nom;
            parentSelect.appendChild(option);
        });
    }

    updateCategoryStats(categories) {
        const totalCategories = categories.length;
        const totalSubCategories = categories.reduce((sum, cat) => sum + (cat.sousCategories?.length || 0), 0);
        const totalDishes = categories.reduce((sum, cat) => sum + (cat.dishCount || 0), 0);

        // Update stats widgets if they exist
        this.updateStatWidget('.stat-categories', totalCategories);
        this.updateStatWidget('.stat-dishes', totalDishes);
        this.updateStatWidget('.stat-active', categories.filter(c => c.dishCount > 0).length);
    }

    updateStatWidget(selector, value) {
        const widget = document.querySelector(selector);
        if (widget) {
            widget.textContent = value;
        }
    }

    fillForm(category) {
        const form = document.getElementById('categoryForm');
        if (!form) return;

        form.dataset.categoryId = category.id;
        
        // Fill form fields
        const fields = ['nom', 'description', 'icon', 'couleur'];
        fields.forEach(field => {
            const input = form.querySelector(`[name="${field}"]`);
            if (input && category[field] !== undefined && category[field] !== null) {
                input.value = category[field];
            }
        });

        // Handle checkboxes
        const actifCheck = form.querySelector('[name="actif"]');
        if (actifCheck) actifCheck.checked = category.actif;

        const visibleCheck = form.querySelector('[name="visible"]');
        if (visibleCheck) visibleCheck.checked = category.visible;

        // Handle parent selection
        const parentSelect = form.querySelector('[name="parentId"]');
        if (parentSelect && category.parent) {
            parentSelect.value = category.parent;
        }

        // Update previews
        this.updateColorPreview(category.couleur);
        this.updateIconPreview(category.icon);
    }

    resetForm() {
        const form = document.getElementById('categoryForm');
        if (form) {
            form.reset();
            delete form.dataset.categoryId;
            
            // Re-enable parent select
            const parentSelect = form.querySelector('[name="parentId"]');
            if (parentSelect) parentSelect.disabled = false;
            
            // Reset previews
            this.updateColorPreview('#a9b73e');
            this.updateIconPreview('fa-utensils');
        }
    }

    setModalTitle(title) {
        const modalTitle = document.querySelector('#addCategoryModal .modal-title');
        if (modalTitle) modalTitle.textContent = title;
    }

    updateColorPreview(color) {
        const preview = document.getElementById('colorPreview');
        if (preview) {
            preview.style.backgroundColor = color;
        }
    }

    updateIconPreview(icon) {
        const preview = document.getElementById('iconPreview');
        if (preview) {
            preview.className = `fas ${icon || 'fa-utensils'}`;
        }
    }

    setFormLoading(loading) {
        const saveBtn = document.getElementById('saveCategoryBtn');
        const form = document.getElementById('categoryForm');
        
        if (saveBtn) {
            saveBtn.disabled = loading;
            saveBtn.innerHTML = loading ? '<i class="fas fa-spinner fa-spin"></i> Enregistrement...' : 'Enregistrer';
        }
        
        if (form) {
            const inputs = form.querySelectorAll('input, select, textarea');
            inputs.forEach(input => input.disabled = loading);
        }
    }

    showLoading(show) {
        const container = document.querySelector('.category-tree');
        if (container) {
            if (show) {
                container.innerHTML = '<div class="text-center p-4"><i class="fas fa-spinner fa-spin"></i> Chargement...</div>';
            }
        }
    }

    hideModal() {
        console.log('🔄 hideModal() called');
        const modal = document.getElementById('addCategoryModal');
        if (modal) {
            console.log('📱 Modal element found:', modal);
            console.log('📱 Modal classes:', modal.classList.toString());
            
            try {
                let Modal = null;
                
                if (typeof window.coreui !== 'undefined' && window.coreui.Modal) {
                    Modal = window.coreui.Modal;
                    console.log('🎯 Using window.coreui.Modal');
                } else if (typeof coreui !== 'undefined' && coreui.Modal) {
                    Modal = coreui.Modal;
                    console.log('🎯 Using coreui.Modal');
                } else if (typeof window.Modal !== 'undefined') {
                    Modal = window.Modal;
                    console.log('🎯 Using window.Modal');
                }
                
                if (Modal) {
                    const modalInstance = Modal.getInstance(modal);
                    console.log('📱 Modal instance:', modalInstance);
                    
                    if (modalInstance) {
                        modalInstance.hide();
                        console.log('✅ Modal hidden with CoreUI instance');
                    } else {
                        // Create new instance and hide
                        console.log('🔄 Creating new modal instance...');
                        const newInstance = new Modal(modal);
                        newInstance.hide();
                        console.log('✅ Modal hidden with new CoreUI instance');
                    }
                } else {
                    console.log('⚠️ No CoreUI Modal found - using manual hide');
                    this.manualHideModal(modal);
                }
            } catch (error) {
                console.error('💥 Error hiding modal:', error);
                this.manualHideModal(modal);
            }
        } else {
            console.error('❌ Modal element not found');
        }
    }

    manualHideModal(modal) {
        console.log('⚠️ Using manual modal hide');
        modal.style.display = 'none';
        modal.classList.remove('show');
        document.body.classList.remove('modal-open');
        
        // Remove all backdrops
        const backdrops = document.querySelectorAll('.modal-backdrop, #category-modal-backdrop');
        backdrops.forEach(backdrop => backdrop.remove());
    }

    showError(message) {
        console.error(message);
        // Create temporary error alert
        this.showAlert(message, 'danger');
    }

    showSuccess(message) {
        console.log(message);
        // Create temporary success alert
        this.showAlert(message, 'success');
    }

    showAlert(message, type) {
        const container = document.querySelector('.category-tree');
        if (container) {
            const alertDiv = document.createElement('div');
            alertDiv.className = `alert alert-${type} alert-dismissible fade show mb-3`;
            alertDiv.innerHTML = `
                ${message}
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            `;
            container.parentElement.insertBefore(alertDiv, container);
            
            // Auto-remove after 3 seconds
            setTimeout(() => {
                if (alertDiv.parentElement) {
                    alertDiv.remove();
                }
            }, 3000);
        }
    }
}

