/**
 * Permission Matrix Component
 * 
 * Enterprise-grade permission management interface with:
 * - Interactive permission matrix visualization
 * - Bulk permission assignment
 * - Role-based permission inheritance
 * - Real-time permission updates
 */
class PermissionMatrix {
    constructor() {
        this.matrixData = null;
        this.permissions = [];
        this.roles = [];
        this.users = [];
        this.selectedUsers = new Set();
        this.selectedPermissions = new Set();
        this.currentView = 'permissions'; // 'permissions' or 'roles'
        this.bulkOperations = [];
        
        this.init();
    }

    async init() {
        try {
            await this.loadMatrixData();
            this.renderInterface();
            this.attachEventListeners();
        } catch (error) {
            console.error('Failed to initialize Permission Matrix:', error);
            AdminUtils.showAlert('Failed to load permission matrix', 'danger');
        }
    }

    async loadMatrixData() {
        try {
            const response = await AdminAPI.request('GET', '/permission-management/matrix');
            this.matrixData = response;
            this.permissions = response.available_permissions;
            this.roles = response.available_roles;
            this.users = response.matrix.map(item => item.user);
            
            console.log('Permission Matrix loaded:', {
                users: this.users.length,
                permissions: this.permissions.length,
                roles: this.roles.length
            });
        } catch (error) {
            console.error('Error loading matrix data:', error);
            throw error;
        }
    }

    renderInterface() {
        const container = document.getElementById('permissionMatrixContainer') || this.createContainer();
        
        container.innerHTML = `
            <div class="permission-matrix">
                <!-- Header Controls -->
                <div class="matrix-header mb-4">
                    <div class="row align-items-center">
                        <div class="col-md-6">
                            <h4 class="mb-0">
                                <i class="fas fa-shield-alt text-primary me-2"></i>
                                Permission Matrix
                            </h4>
                            <small class="text-muted">
                                ${this.users.length} users • ${this.permissions.length} permissions • ${this.roles.length} roles
                            </small>
                        </div>
                        <div class="col-md-6 text-end">
                            <div class="btn-group me-2" role="group">
                                <input type="radio" class="btn-check" name="viewType" id="viewPermissions" value="permissions" checked>
                                <label class="btn btn-outline-primary" for="viewPermissions">
                                    <i class="fas fa-key me-1"></i>Permissions
                                </label>
                                <input type="radio" class="btn-check" name="viewType" id="viewRoles" value="roles">
                                <label class="btn btn-outline-primary" for="viewRoles">
                                    <i class="fas fa-users-cog me-1"></i>Roles
                                </label>
                            </div>
                            <button class="btn btn-success" id="bulkUpdateBtn" disabled>
                                <i class="fas fa-magic me-1"></i>Bulk Update
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Filters and Search -->
                <div class="matrix-filters mb-3">
                    <div class="row">
                        <div class="col-md-4">
                            <input type="text" class="form-control" id="searchUsers" placeholder="Search users...">
                        </div>
                        <div class="col-md-4">
                            <select class="form-select" id="filterCategory">
                                <option value="">All Categories</option>
                                ${this.getCategories().map(cat => `<option value="${cat}">${cat}</option>`).join('')}
                            </select>
                        </div>
                        <div class="col-md-4">
                            <div class="form-check form-switch">
                                <input class="form-check-input" type="checkbox" id="showInheritedPermissions" checked>
                                <label class="form-check-label" for="showInheritedPermissions">
                                    Show inherited permissions
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Matrix Table -->
                <div class="matrix-table-container">
                    <div class="table-responsive">
                        <table class="table table-bordered table-hover matrix-table">
                            <thead class="table-dark sticky-top">
                                ${this.renderTableHeader()}
                            </thead>
                            <tbody>
                                ${this.renderTableBody()}
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Summary Statistics -->
                <div class="matrix-summary mt-4">
                    <div class="row">
                        <div class="col-md-3">
                            <div class="card bg-primary text-white">
                                <div class="card-body text-center">
                                    <h5 class="card-title">${this.users.length}</h5>
                                    <p class="card-text">Total Users</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-success text-white">
                                <div class="card-body text-center">
                                    <h5 class="card-title">${this.permissions.length}</h5>
                                    <p class="card-text">Permissions</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-info text-white">
                                <div class="card-body text-center">
                                    <h5 class="card-title">${this.roles.length}</h5>
                                    <p class="card-text">Roles</p>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3">
                            <div class="card bg-warning text-white">
                                <div class="card-body text-center">
                                    <h5 class="card-title">${this.bulkOperations.length}</h5>
                                    <p class="card-text">Pending Operations</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        `;
    }

    renderTableHeader() {
        if (this.currentView === 'permissions') {
            const categories = this.getCategories();
            let headerHtml = `
                <tr>
                    <th rowspan="2" class="user-column">
                        <input type="checkbox" id="selectAllUsers" class="form-check-input">
                        <span class="ms-2">Users</span>
                    </th>
            `;

            // Category headers
            categories.forEach(category => {
                const categoryPermissions = this.permissions.filter(p => p.category === category);
                headerHtml += `<th colspan="${categoryPermissions.length}" class="text-center category-header">${category}</th>`;
            });

            headerHtml += `</tr><tr>`;

            // Permission headers
            categories.forEach(category => {
                const categoryPermissions = this.permissions.filter(p => p.category === category);
                categoryPermissions.forEach(permission => {
                    headerHtml += `
                        <th class="permission-header" data-permission-id="${permission.id}" title="${permission.name}">
                            <div class="permission-name">${this.truncateText(permission.name, 12)}</div>
                            <input type="checkbox" class="form-check-input permission-selector" 
                                   data-permission-id="${permission.id}">
                        </th>
                    `;
                });
            });

            headerHtml += `</tr>`;
            return headerHtml;
        } else {
            // Roles view
            return `
                <tr>
                    <th class="user-column">
                        <input type="checkbox" id="selectAllUsers" class="form-check-input">
                        <span class="ms-2">Users</span>
                    </th>
                    ${this.roles.map(role => `
                        <th class="role-header" data-role-id="${role.id}" title="${role.name}">
                            <div class="role-name">${role.name}</div>
                            <small>(${role.permission_count} perms)</small>
                            <input type="checkbox" class="form-check-input role-selector" 
                                   data-role-id="${role.id}">
                        </th>
                    `).join('')}
                </tr>
            `;
        }
    }

    renderTableBody() {
        return this.matrixData.matrix.map(userMatrix => {
            const user = userMatrix.user;
            const userPermissions = userMatrix.permissions;
            
            if (this.currentView === 'permissions') {
                return this.renderUserPermissionRow(user, userPermissions);
            } else {
                return this.renderUserRoleRow(user, userMatrix);
            }
        }).join('');
    }

    renderUserPermissionRow(user, userPermissions) {
        const categories = this.getCategories();
        let rowHtml = `
            <tr class="user-row" data-user-id="${user.id}">
                <td class="user-info">
                    <input type="checkbox" class="form-check-input user-selector" data-user-id="${user.id}">
                    <div class="ms-2">
                        <strong>${user.name}</strong>
                        <br><small class="text-muted">${user.email}</small>
                        <div class="user-roles mt-1">
                            ${user.roles.map(role => `<span class="badge bg-secondary me-1">${role}</span>`).join('')}
                        </div>
                    </div>
                </td>
        `;

        categories.forEach(category => {
            const categoryPermissions = this.permissions.filter(p => p.category === category);
            categoryPermissions.forEach(permission => {
                const hasPermission = userPermissions.includes(permission.name);
                const isInherited = this.isPermissionInherited(user, permission.name);
                
                rowHtml += `
                    <td class="permission-cell text-center">
                        <div class="permission-indicator ${hasPermission ? 'has-permission' : ''} ${isInherited ? 'inherited' : 'direct'}"
                             data-user-id="${user.id}" 
                             data-permission-id="${permission.id}"
                             title="${hasPermission ? 'Has permission' : 'No permission'} (${isInherited ? 'inherited' : 'direct'})">
                            ${hasPermission ? (isInherited ? '🔗' : '✅') : '❌'}
                        </div>
                    </td>
                `;
            });
        });

        rowHtml += `</tr>`;
        return rowHtml;
    }

    renderUserRoleRow(user, userMatrix) {
        // Get user's roles from admin profile
        const userRoleIds = []; // This would come from the matrix data
        
        let rowHtml = `
            <tr class="user-row" data-user-id="${user.id}">
                <td class="user-info">
                    <input type="checkbox" class="form-check-input user-selector" data-user-id="${user.id}">
                    <div class="ms-2">
                        <strong>${user.name}</strong>
                        <br><small class="text-muted">${user.email}</small>
                        <div class="permission-sources mt-1">
                            <small>
                                Direct: ${userMatrix.permission_sources.direct} | 
                                Roles: ${userMatrix.permission_sources.from_roles} |
                                Legacy: ${userMatrix.permission_sources.legacy}
                            </small>
                        </div>
                    </div>
                </td>
        `;

        this.roles.forEach(role => {
            const hasRole = userRoleIds.includes(role.id);
            
            rowHtml += `
                <td class="role-cell text-center">
                    <div class="role-indicator ${hasRole ? 'has-role' : ''}"
                         data-user-id="${user.id}" 
                         data-role-id="${role.id}"
                         title="${hasRole ? 'Has role' : 'No role'}">
                        ${hasRole ? '✅' : '❌'}
                    </div>
                </td>
            `;
        });

        rowHtml += `</tr>`;
        return rowHtml;
    }

    attachEventListeners() {
        // View type toggle
        document.querySelectorAll('input[name="viewType"]').forEach(radio => {
            radio.addEventListener('change', (e) => {
                this.currentView = e.target.value;
                this.renderInterface();
                this.attachEventListeners();
            });
        });

        // Permission/Role cell clicks
        document.querySelectorAll('.permission-indicator, .role-indicator').forEach(cell => {
            cell.addEventListener('click', (e) => {
                this.handleCellClick(e);
            });
        });

        // User selection
        document.querySelectorAll('.user-selector').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                this.handleUserSelection(e);
            });
        });

        // Select all users
        const selectAllUsers = document.getElementById('selectAllUsers');
        if (selectAllUsers) {
            selectAllUsers.addEventListener('change', (e) => {
                this.handleSelectAllUsers(e.target.checked);
            });
        }

        // Bulk update button
        const bulkUpdateBtn = document.getElementById('bulkUpdateBtn');
        if (bulkUpdateBtn) {
            bulkUpdateBtn.addEventListener('click', () => {
                this.showBulkUpdateModal();
            });
        }

        // Search and filters
        const searchUsers = document.getElementById('searchUsers');
        if (searchUsers) {
            searchUsers.addEventListener('input', (e) => {
                this.filterUsers(e.target.value);
            });
        }

        const filterCategory = document.getElementById('filterCategory');
        if (filterCategory) {
            filterCategory.addEventListener('change', (e) => {
                this.filterByCategory(e.target.value);
            });
        }
    }

    handleCellClick(e) {
        const cell = e.target;
        const userId = parseInt(cell.dataset.userId);
        const permissionId = cell.dataset.permissionId ? parseInt(cell.dataset.permissionId) : null;
        const roleId = cell.dataset.roleId ? parseInt(cell.dataset.roleId) : null;

        if (permissionId) {
            this.togglePermission(userId, permissionId);
        } else if (roleId) {
            this.toggleRole(userId, roleId);
        }
    }

    togglePermission(userId, permissionId) {
        const user = this.users.find(u => u.id === userId);
        const permission = this.permissions.find(p => p.id === permissionId);
        
        if (!user || !permission) return;

        const userMatrix = this.matrixData.matrix.find(m => m.user.id === userId);
        const hasPermission = userMatrix.permissions.includes(permission.name);

        const operation = {
            user_id: userId,
            action: hasPermission ? 'remove_permission' : 'add_permission',
            target_id: permissionId,
            user_name: user.name,
            permission_name: permission.name
        };

        this.addBulkOperation(operation);
        this.updateCellVisually(userId, permissionId, !hasPermission);
    }

    toggleRole(userId, roleId) {
        const user = this.users.find(u => u.id === userId);
        const role = this.roles.find(r => r.id === roleId);
        
        if (!user || !role) return;

        // This would need to check current role assignment
        const hasRole = false; // Placeholder

        const operation = {
            user_id: userId,
            action: hasRole ? 'remove_role' : 'add_role',
            target_id: roleId,
            user_name: user.name,
            role_name: role.name
        };

        this.addBulkOperation(operation);
    }

    addBulkOperation(operation) {
        // Remove existing operation for same user/target
        this.bulkOperations = this.bulkOperations.filter(op => 
            !(op.user_id === operation.user_id && op.target_id === operation.target_id)
        );

        this.bulkOperations.push(operation);
        this.updateBulkUpdateButton();
    }

    updateBulkUpdateButton() {
        const bulkUpdateBtn = document.getElementById('bulkUpdateBtn');
        if (bulkUpdateBtn) {
            bulkUpdateBtn.disabled = this.bulkOperations.length === 0;
            bulkUpdateBtn.innerHTML = `
                <i class="fas fa-magic me-1"></i>
                Bulk Update (${this.bulkOperations.length})
            `;
        }
    }

    async showBulkUpdateModal() {
        if (this.bulkOperations.length === 0) return;

        const modal = `
            <div class="modal fade" id="bulkUpdateModal" tabindex="-1">
                <div class="modal-dialog modal-lg">
                    <div class="modal-content">
                        <div class="modal-header">
                            <h5 class="modal-title">Confirm Bulk Permission Update</h5>
                            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                        </div>
                        <div class="modal-body">
                            <p>You are about to apply <strong>${this.bulkOperations.length}</strong> permission changes:</p>
                            <div class="table-responsive">
                                <table class="table table-sm">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Action</th>
                                            <th>Target</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${this.bulkOperations.map(op => `
                                            <tr>
                                                <td>${op.user_name}</td>
                                                <td>
                                                    <span class="badge ${op.action.includes('add') ? 'bg-success' : 'bg-danger'}">
                                                        ${op.action.replace('_', ' ')}
                                                    </span>
                                                </td>
                                                <td>${op.permission_name || op.role_name}</td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                            <button type="button" class="btn btn-primary" id="confirmBulkUpdate">
                                <i class="fas fa-check me-1"></i>Apply Changes
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        `;

        document.body.insertAdjacentHTML('beforeend', modal);
        const modalElement = new coreui.Modal(document.getElementById('bulkUpdateModal'));
        modalElement.show();

        document.getElementById('confirmBulkUpdate').addEventListener('click', async () => {
            await this.executeBulkUpdate();
            modalElement.hide();
        });

        // Clean up modal when hidden
        document.getElementById('bulkUpdateModal').addEventListener('hidden.coreui.modal', () => {
            document.getElementById('bulkUpdateModal').remove();
        });
    }

    async executeBulkUpdate() {
        try {
            const response = await AdminAPI.request('POST', '/permission-management/bulk-update', {
                operations: this.bulkOperations
            });

            if (response.success) {
                AdminUtils.showAlert(`Successfully applied ${response.successful} of ${response.processed} changes`, 'success');
                this.bulkOperations = [];
                await this.loadMatrixData();
                this.renderInterface();
                this.attachEventListeners();
            }
        } catch (error) {
            console.error('Bulk update failed:', error);
            AdminUtils.showAlert('Failed to apply bulk changes', 'danger');
        }
    }

    // Utility methods
    getCategories() {
        const categories = [...new Set(this.permissions.map(p => p.category))];
        return categories.sort();
    }

    truncateText(text, maxLength) {
        return text.length > maxLength ? text.substring(0, maxLength) + '...' : text;
    }

    isPermissionInherited(user, permissionName) {
        // This would check if permission comes from roles vs direct assignment
        return false; // Placeholder
    }

    updateCellVisually(userId, permissionId, hasPermission) {
        const cell = document.querySelector(`[data-user-id="${userId}"][data-permission-id="${permissionId}"]`);
        if (cell) {
            cell.classList.toggle('has-permission', hasPermission);
            cell.innerHTML = hasPermission ? '✅' : '❌';
            cell.title = hasPermission ? 'Has permission (pending)' : 'No permission (pending)';
        }
    }

    createContainer() {
        const container = document.createElement('div');
        container.id = 'permissionMatrixContainer';
        container.className = 'permission-matrix-wrapper';
        
        // Find a suitable parent or create one
        const content = document.querySelector('.content') || document.body;
        content.appendChild(container);
        
        return container;
    }

    filterUsers(searchTerm) {
        const rows = document.querySelectorAll('.user-row');
        rows.forEach(row => {
            const userInfo = row.querySelector('.user-info').textContent.toLowerCase();
            const matches = userInfo.includes(searchTerm.toLowerCase());
            row.style.display = matches ? '' : 'none';
        });
    }

    filterByCategory(category) {
        // This would filter visible columns by category
        console.log('Filtering by category:', category);
    }

    handleUserSelection(e) {
        const userId = parseInt(e.target.dataset.userId);
        if (e.target.checked) {
            this.selectedUsers.add(userId);
        } else {
            this.selectedUsers.delete(userId);
        }
    }

    handleSelectAllUsers(checked) {
        const userCheckboxes = document.querySelectorAll('.user-selector');
        userCheckboxes.forEach(checkbox => {
            checkbox.checked = checked;
            const userId = parseInt(checkbox.dataset.userId);
            if (checked) {
                this.selectedUsers.add(userId);
            } else {
                this.selectedUsers.delete(userId);
            }
        });
    }
}

// Export for use in other modules
window.PermissionMatrix = PermissionMatrix; 