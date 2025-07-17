/**
 * JoodKitchen Abonnement Management System
 * Comprehensive subscription management with statistics, calendar, and workflow
 * 
 * Features:
 * - Real-time statistics dashboard
 * - Advanced table view with filtering and pagination
 * - Interactive weekly calendar for meal planning
 * - Status workflow management
 * - Bulk operations and export functionality
 * - Performance optimized with caching
 */

class AbonnementManager {
    constructor(apiEndpoints = {}) {
        // API configuration
        this.apiEndpoints = {
            subscriptions: '/admin/abonnements',
            statistics: '/admin/abonnements/stats',
            calendar: '/admin/abonnements/calendar',
            pending_count: '/admin/abonnements/pending-count',
            status_update: '/admin/abonnements/status-update',
            bulk_actions: '/admin/abonnements/bulk',
            export: '/admin/abonnements/export',
            selections: '/admin/abonnement-selections',
            ...apiEndpoints
        };

        // State management
        this.currentTab = 'table-view';
        this.currentPage = 1;
        this.currentLimit = 20;
        this.currentFilters = {};
        this.selectedSubscriptions = new Set();
        this.currentWeek = this.getCurrentWeekStart();
        this.refreshInterval = 30000; // 30 seconds
        this.refreshTimer = null;

        // Chart instances
        this.charts = {
            conversion: null,
            cuisinePreferences: null,
            revenueTrends: null
        };

        // Cache for performance
        this.cache = {
            statistics: null,
            subscriptions: null,
            calendar: null,
            lastUpdate: null
        };

        console.log('🚀 AbonnementManager initialized with endpoints:', this.apiEndpoints);
    }

    /**
     * Initialize the entire system
     */
    async initialize() {
        console.log('🔄 Initializing Abonnement Management System...');
        
        try {
            this.bindEvents();
            this.loadFiltersFromLocalStorage();
            await this.loadInitialData();
            this.setupRealTimeUpdates();
            this.initializeCharts();
            
            console.log('✅ Abonnement Management System ready!');
        } catch (error) {
            console.error('❌ Error initializing Abonnement Management:', error);
            this.showNotification('Erreur lors de l\'initialisation du système', 'error');
        }
    }

    /**
     * Bind all event listeners
     */
    bindEvents() {
        console.log('🎯 Binding event listeners...');

        // Tab navigation
        document.querySelectorAll('#abonnementsMainTabs .nav-link').forEach(tab => {
            tab.addEventListener('click', (e) => {
                const tabId = e.target.getAttribute('data-coreui-target');
                if (tabId) {
                    this.switchTab(tabId.replace('#', ''));
                }
            });
        });

        // Global search
        const globalSearch = document.getElementById('globalSearchInput');
        if (globalSearch) {
            globalSearch.addEventListener('input', this.debounce((e) => {
                this.performGlobalSearch(e.target.value);
            }, 300));
        }

        // Header actions
        this.bindHeaderActions();

        // Table view events
        this.bindTableViewEvents();

        // Calendar view events
        this.bindCalendarViewEvents();

        // Status management events
        this.bindStatusManagementEvents();

        // View controls
        this.bindViewControls();

        console.log('✅ Event listeners bound successfully');
    }

    /**
     * Bind header action events
     */
    bindHeaderActions() {
        // Refresh data
        document.getElementById('refreshDataBtn')?.addEventListener('click', () => {
            this.refreshAllData();
        });

        // Export
        document.getElementById('exportAbonnementsBtn')?.addEventListener('click', () => {
            this.exportData();
        });

        // Send reminders
        document.getElementById('sendRemindersBtn')?.addEventListener('click', () => {
            this.sendPaymentReminders();
        });

        // Activate pending
        document.getElementById('activatePendingBtn')?.addEventListener('click', () => {
            this.activateAllPending();
        });

        // Clear cache
        document.getElementById('clearCacheBtn')?.addEventListener('click', () => {
            this.clearCache();
        });

        // Smart actions
        document.getElementById('autoExpireOldBtn')?.addEventListener('click', () => {
            this.autoExpireOldSubscriptions();
        });

        document.getElementById('findIncompleteSelectionsBtn')?.addEventListener('click', () => {
            this.findIncompleteSelections();
        });

        document.getElementById('proposeRenewalBtn')?.addEventListener('click', () => {
            this.proposeRenewalToExpired();
        });
    }

    /**
     * Bind table view events
     */
    bindTableViewEvents() {
        // Filters
        document.getElementById('statusFilter')?.addEventListener('change', (e) => {
            this.updateFilter('status', e.target.value);
        });

        document.getElementById('typeFilter')?.addEventListener('change', (e) => {
            this.updateFilter('type', e.target.value);
        });

        document.getElementById('dateFromFilter')?.addEventListener('change', (e) => {
            this.updateFilter('date_from', e.target.value);
        });

        document.getElementById('dateToFilter')?.addEventListener('change', (e) => {
            this.updateFilter('date_to', e.target.value);
        });

        document.getElementById('customerSearchFilter')?.addEventListener('input', this.debounce((e) => {
            this.updateFilter('search', e.target.value);
        }, 300));

        // Clear filters
        document.getElementById('clearFiltersBtn')?.addEventListener('click', () => {
            this.clearFilters();
        });

        // Select all checkbox
        document.getElementById('selectAllCheckbox')?.addEventListener('change', (e) => {
            this.toggleSelectAll(e.target.checked);
        });
    }

    /**
     * Bind calendar view events
     */
    bindCalendarViewEvents() {
        // Week navigation
        document.getElementById('prevWeekBtn')?.addEventListener('click', () => {
            this.navigateWeek(-1);
        });

        document.getElementById('nextWeekBtn')?.addEventListener('click', () => {
            this.navigateWeek(1);
        });

        document.getElementById('currentWeekBtn')?.addEventListener('click', () => {
            this.goToCurrentWeek();
        });
    }

    /**
     * Navigate to previous/next week
     */
    navigateWeek(direction) {
        const currentWeek = new Date(this.currentWeek);
        currentWeek.setDate(currentWeek.getDate() + (direction * 7));
        this.currentWeek = currentWeek.toISOString().split('T')[0];
        this.loadCalendarData();
    }

    /**
     * Go to current week
     */
    goToCurrentWeek() {
        this.currentWeek = this.getCurrentWeekStart();
        this.loadCalendarData();
    }

    /**
     * Update week display
     */
    updateWeekDisplay() {
        const weekStart = new Date(this.currentWeek);
        const weekEnd = new Date(weekStart);
        weekEnd.setDate(weekEnd.getDate() + 6);

        const weekRangeDisplay = document.getElementById('weekRangeDisplay');
        if (weekRangeDisplay) {
            weekRangeDisplay.textContent = `Semaine du ${weekStart.toLocaleDateString('fr-FR')} au ${weekEnd.toLocaleDateString('fr-FR')}`;
        }
    }

    /**
     * Send selection reminder
     */
    async sendSelectionReminder(subscriptionId, date) {
        try {
            const result = await AdminAPI.request('POST', `${this.apiEndpoints.selections}/send-reminder`, {
                subscription_id: subscriptionId,
                date: date
            });

            if (result.success) {
                this.showNotification('Rappel envoyé avec succès', 'success');
            } else {
                throw new Error(result.error || 'Failed to send reminder');
            }
        } catch (error) {
            console.error('❌ Error sending reminder:', error);
            this.showNotification('Erreur lors de l\'envoi du rappel', 'error');
        }
    }

    /**
     * View day selections
     */
    async viewDaySelections(date) {
        // This could open a detailed view or redirect to a specific page
        window.location.href = `/admin/abonnements/day/${date}`;
    }

    /**
     * Export day data
     */
    async exportDayData(date) {
        try {
            const result = await AdminAPI.request('GET', `${this.apiEndpoints.export}/day/${date}`);
            
            if (result.success && result.download_url) {
                window.open(result.download_url, '_blank');
                this.showNotification('Export généré avec succès', 'success');
            } else {
                throw new Error(result.error || 'Export failed');
            }
        } catch (error) {
            console.error('❌ Export error:', error);
            this.showNotification('Erreur lors de l\'export', 'error');
        }

        // Incomplete selections
        document.getElementById('viewIncompleteBtn')?.addEventListener('click', () => {
            this.showIncompleteSelections();
        });
    }

    /**
     * Bind status management events
     */
    bindStatusManagementEvents() {
        // Quick actions
        document.getElementById('activateAllPendingBtn')?.addEventListener('click', () => {
            this.bulkStatusUpdate('en_confirmation', 'actif');
        });

        document.getElementById('sendPaymentRemindersBtn')?.addEventListener('click', () => {
            this.sendBulkReminders();
        });

        document.getElementById('reactivateSuspendedBtn')?.addEventListener('click', () => {
            this.bulkStatusUpdate('suspendu', 'actif');
        });

        document.getElementById('expireOldSubscriptionsBtn')?.addEventListener('click', () => {
            this.expireOldSubscriptions();
        });
    }

    /**
     * Bind view control events
     */
    bindViewControls() {
        // Compact/Detailed view
        document.getElementById('compactViewBtn')?.addEventListener('click', () => {
            this.switchTableView('compact');
        });

        document.getElementById('detailedViewBtn')?.addEventListener('click', () => {
            this.switchTableView('detailed');
        });

        // Analytics period buttons
        document.querySelectorAll('[data-period]').forEach(btn => {
            btn.addEventListener('click', (e) => {
                const period = e.target.getAttribute('data-period');
                this.updateAnalyticsPeriod(period);
            });
        });
    }

    /**
     * Bind table checkbox events for bulk operations
     */
    bindTableCheckboxEvents() {
        // Select all checkbox
        const selectAllCheckbox = document.getElementById('selectAllSubscriptions');
        if (selectAllCheckbox) {
            selectAllCheckbox.addEventListener('change', (e) => {
                this.toggleSelectAll(e.target.checked);
            });
        }

        // Individual subscription checkboxes
        document.querySelectorAll('.subscription-checkbox').forEach(checkbox => {
            checkbox.addEventListener('change', (e) => {
                const subscriptionId = e.target.value;
                if (e.target.checked) {
                    this.selectedSubscriptions.add(subscriptionId);
                } else {
                    this.selectedSubscriptions.delete(subscriptionId);
                }
                this.updateBulkActionsVisibility();
            });
        });

        // Update bulk actions visibility
        this.updateBulkActionsVisibility();
    }

    /**
     * Toggle select all subscriptions
     */
    toggleSelectAll(checked) {
        this.selectedSubscriptions.clear();
        
        document.querySelectorAll('.subscription-checkbox').forEach(checkbox => {
            checkbox.checked = checked;
            if (checked) {
                this.selectedSubscriptions.add(checkbox.value);
            }
        });
        
        this.updateBulkActionsVisibility();
    }

    /**
     * Update bulk actions visibility based on selection
     */
    updateBulkActionsVisibility() {
        const bulkActions = document.querySelector('.bulk-actions');
        const selectedCount = this.selectedSubscriptions.size;
        
        if (bulkActions) {
            if (selectedCount > 0) {
                bulkActions.style.display = 'block';
                bulkActions.querySelector('.selected-count').textContent = selectedCount;
            } else {
                bulkActions.style.display = 'none';
            }
        }
    }

    /**
     * Load initial data for all views
     */
    async loadInitialData() {
        console.log('📊 Loading initial data...');
        
        this.showGlobalLoading(true);
        
        try {
            // Load in parallel for better performance
            await Promise.all([
                this.loadStatistics(),
                this.loadSubscriptions(),
                this.loadCalendarData(),
                this.updatePendingBadge()
            ]);
            
            console.log('✅ Initial data loaded successfully');
        } catch (error) {
            console.error('❌ Error loading initial data:', error);
            this.showNotification('Erreur lors du chargement des données', 'error');
        } finally {
            this.showGlobalLoading(false);
        }
    }

    /**
     * Load statistics dashboard data
     */
    async loadStatistics() {
        try {
            const result = await AdminAPI.request('GET', this.apiEndpoints.statistics);
            
            if (result.success) {
                this.cache.statistics = result.data;
                this.updateStatisticsDashboard(result.data);
            } else {
                throw new Error(result.error || 'Failed to load statistics');
            }
        } catch (error) {
            console.error('❌ Error loading statistics:', error);
            this.showNotification('Erreur lors du chargement des statistiques', 'error');
        }
    }

    /**
     * Update statistics dashboard UI
     */
    updateStatisticsDashboard(data) {
        // Update main counter cards
        this.updateElement('totalAbonnementsCount', data.overview?.total || 0);
        this.updateElement('activeAbonnementsCount', data.overview?.actif || 0);
        this.updateElement('pendingConfirmationsCount', data.overview?.en_confirmation || 0);
        this.updateElement('weeklyRevenueAmount', `${data.revenue?.weekly_total || 0} MAD`);

        // Update tab badges
        this.updateElement('tableViewCount', data.overview?.total || 0);
        this.updateElement('statusManagementCount', data.overview?.en_confirmation || 0);

        // Update analytics metrics
        this.updateElement('conversionRateMetric', `${data.conversion?.conversion_rate || 0}%`);
        this.updateElement('averageRevenueMetric', `${data.revenue?.average_subscription_value || 0} MAD`);
        this.updateElement('retentionRateMetric', `${data.retention?.rate || 0}%`);
        this.updateElement('growthRateMetric', `+${data.revenue?.growth_rate || 0}%`);

        // Update status overview
        if (data.overview) {
            this.updateElement('statusEnConfirmation', data.overview.en_confirmation || 0);
            this.updateElement('statusActif', data.overview.actif || 0);
            this.updateElement('statusSuspendu', data.overview.suspendu || 0);
            this.updateElement('statusExpire', data.overview.expire || 0);
            this.updateElement('statusAnnule', data.overview.annule || 0);
        }

        console.log('✅ Statistics dashboard updated');
    }

    /**
     * Load subscriptions table data
     */
    async loadSubscriptions() {
        if (this.currentTab !== 'table-view') return;

        this.showTableLoading(true);

        try {
            const params = new URLSearchParams({
                page: this.currentPage,
                limit: this.currentLimit,
                ...this.currentFilters
            });

            const result = await AdminAPI.request('GET', `${this.apiEndpoints.subscriptions}?${params}`);

            if (result.success) {
                this.cache.subscriptions = result.data;
                this.renderSubscriptionsTable(result.data);
                // TODO: Implement pagination UI
                // this.updatePagination(result.pagination);
            } else {
                throw new Error(result.error || 'Failed to load subscriptions');
            }
        } catch (error) {
            console.error('❌ Error loading subscriptions:', error);
            this.showNotification('Erreur lors du chargement des abonnements', 'error');
        } finally {
            this.showTableLoading(false);
        }
    }

    /**
     * Render subscriptions table
     */
    renderSubscriptionsTable(subscriptions) {
        const tbody = document.getElementById('subscriptionsTableBody');
        if (!tbody) return;

        if (!subscriptions || subscriptions.length === 0) {
            tbody.innerHTML = `
                <tr>
                    <td colspan="9" class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x mb-2"></i>
                        <div>Aucun abonnement trouvé</div>
                    </td>
                </tr>
            `;
            return;
        }

        tbody.innerHTML = subscriptions.map(subscription => `
            <tr data-subscription-id="${subscription.id}">
                <td>
                    <div class="form-check">
                        <input class="form-check-input subscription-checkbox" type="checkbox" 
                               value="${subscription.id}" data-subscription-id="${subscription.id}">
                    </div>
                </td>
                <td>
                    <div class="d-flex align-items-center">
                        <div class="avatar avatar-sm me-2">
                            <div class="avatar-initial bg-light text-dark rounded-circle">
                                ${subscription.user.prenom.charAt(0)}${subscription.user.nom.charAt(0)}
                            </div>
                        </div>
                        <div>
                            <div class="fw-semibold">${subscription.user.prenom} ${subscription.user.nom}</div>
                            <div class="small text-muted">${subscription.user.email}</div>
                        </div>
                    </div>
                </td>
                <td>
                    <span class="badge bg-light text-dark">${subscription.type_label}</span>
                </td>
                <td>
                    <span class="status-badge status-${subscription.statut}" style="color: ${subscription.statut_color};">
                        <i class="${subscription.statut_icon}"></i> ${subscription.statut_label}
                    </span>
                </td>
                <td>
                    <div class="small">
                        <div><strong>Du:</strong> ${subscription.date_debut || 'N/A'}</div>
                        <div><strong>Au:</strong> ${subscription.date_fin || 'N/A'}</div>
                    </div>
                </td>
                <td>
                    <div class="text-center">
                        <span class="fw-bold">${subscription.selections_count}</span> / 5
                        <div class="progress mt-1" style="height: 4px;">
                            <div class="progress-bar" style="width: ${(subscription.selections_count / 5) * 100}%"></div>
                        </div>
                    </div>
                </td>
                <td>
                    <div class="fw-semibold">${subscription.weekly_price} MAD</div>
                    ${subscription.discount_rate > 0 ? `<div class="small text-success">-${subscription.discount_rate}%</div>` : ''}
                </td>
                <td>
                    ${this.renderProgressIndicator(subscription)}
                </td>
                <td>
                    <div class="btn-group btn-group-sm">
                        <button class="btn btn-outline-primary btn-sm" onclick="abonnementManager.viewSubscriptionDetails(${subscription.id})" title="Voir détails">
                            <i class="fas fa-eye"></i>
                        </button>
                        <button class="btn btn-outline-secondary btn-sm" onclick="abonnementManager.editSubscription(${subscription.id})" title="Modifier">
                            <i class="fas fa-edit"></i>
                        </button>
                        ${this.renderActionButtons(subscription)}
                    </div>
                </td>
            </tr>
        `).join('');

        // Bind checkbox events
        this.bindTableCheckboxEvents();

        console.log(`✅ Rendered ${subscriptions.length} subscriptions`);
    }

    /**
     * Render progress indicator based on subscription status
     */
    renderProgressIndicator(subscription) {
        if (subscription.statut === 'en_confirmation') {
            return `
                <div class="small text-warning">
                    <i class="fas fa-clock"></i> En attente
                </div>
            `;
        }

        if (subscription.statut === 'actif') {
            const progress = Math.min(100, (subscription.selections_count / 5) * 100);
            return `
                <div class="small">
                    <div class="text-success">✓ Actif</div>
                    <div class="progress mt-1" style="height: 3px;">
                        <div class="progress-bar bg-success" style="width: ${progress}%"></div>
                    </div>
                </div>
            `;
        }

        return `<span class="text-muted">-</span>`;
    }

    /**
     * Render action buttons based on subscription status
     */
    renderActionButtons(subscription) {
        let buttons = '';

        if (subscription.can_be_activated) {
            buttons += `
                <button class="btn btn-success btn-sm" onclick="abonnementManager.activateSubscription(${subscription.id})" title="Activer">
                    <i class="fas fa-play"></i>
                </button>
            `;
        }

        if (subscription.can_be_suspended) {
            buttons += `
                <button class="btn btn-warning btn-sm" onclick="abonnementManager.suspendSubscription(${subscription.id})" title="Suspendre">
                    <i class="fas fa-pause"></i>
                </button>
            `;
        }

        return buttons;
    }

    /**
     * Load calendar data for the current week
     */
    async loadCalendarData() {
        if (this.currentTab !== 'calendar-view') return;

        try {
            const params = new URLSearchParams({
                week_start: this.currentWeek
            });

            const result = await AdminAPI.request('GET', `${this.apiEndpoints.calendar}?${params}`);

            if (result.success) {
                this.cache.calendar = result.data;
                this.renderWeeklyCalendar(result.data);
                this.updateWeekDisplay();
            } else {
                throw new Error(result.error || 'Failed to load calendar data');
            }
        } catch (error) {
            console.error('❌ Error loading calendar data:', error);
            this.showNotification('Erreur lors du chargement du calendrier', 'error');
        }
    }

    /**
     * Render weekly calendar
     */
    renderWeeklyCalendar(calendarData) {
        const tbody = document.getElementById('weeklyCalendarBody');
        if (!tbody || !calendarData.daily_data) return;

        tbody.innerHTML = `
            <tr>
                ${calendarData.daily_data.map((day, index) => {
                    const isToday = this.isToday(day.date);
                    const isPastDay = this.isPastDay(day.date);
                    const hasSelections = day.total_selections > 0;
                    
                    return `
                    <td class="calendar-day ${isToday ? 'today' : ''} ${isPastDay ? 'past-day' : ''} ${hasSelections ? 'has-selections' : ''}" 
                        data-date="${day.date}" 
                        style="height: 160px; vertical-align: top; cursor: pointer;"
                        onclick="abonnementManager.showDayDetails('${day.date}')">
                        
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <div class="fw-bold ${isToday ? 'text-primary' : 'text-muted'}">${day.day_name}</div>
                            <small class="text-muted">${this.formatDateShort(day.date)}</small>
                        </div>
                        
                        <!-- Cuisine Counts -->
                        <div class="cuisine-overview mb-2">
                            ${Object.entries(day.cuisine_counts).map(([cuisine, count]) => {
                                if (count === 0) return '';
                                
                                const percentage = day.total_selections > 0 ? Math.round((count / day.total_selections) * 100) : 0;
                                return `
                                    <div class="cuisine-item d-flex justify-content-between align-items-center mb-1" 
                                         data-cuisine="${cuisine}">
                                        <div class="cuisine-label">
                                            <span class="cuisine-icon">${this.getCuisineIcon(cuisine)}</span>
                                            <span class="small">${cuisine.charAt(0).toUpperCase() + cuisine.slice(1)}</span>
                                        </div>
                                        <div class="cuisine-stats">
                                            <span class="badge bg-light text-dark">${count}</span>
                                            <small class="text-muted">${percentage}%</small>
                                        </div>
                                    </div>
                                `;
                            }).join('')}
                        </div>
                        
                        <!-- Status Indicators -->
                        <div class="day-status">
                            ${day.incomplete_count > 0 ? `
                                <div class="alert alert-warning alert-sm py-1 px-2 mb-1">
                                    <i class="fas fa-exclamation-triangle fa-sm"></i>
                                    <small>${day.incomplete_count} incomplet${day.incomplete_count > 1 ? 's' : ''}</small>
                                </div>
                            ` : ''}
                            
                            ${day.total_selections > 0 ? `
                                <div class="total-selections text-center">
                                    <div class="badge bg-primary">${day.total_selections} sélection${day.total_selections > 1 ? 's' : ''}</div>
                                </div>
                            ` : `
                                <div class="no-selections text-center">
                                    <small class="text-muted">Aucune sélection</small>
                                </div>
                            `}
                        </div>
                        
                        <!-- Hover overlay for additional actions -->
                        <div class="day-actions" style="display: none;">
                            <div class="btn-group btn-group-sm">
                                <button class="btn btn-outline-primary btn-xs" onclick="event.stopPropagation(); abonnementManager.viewDaySelections('${day.date}')">
                                    <i class="fas fa-eye"></i>
                                </button>
                                <button class="btn btn-outline-success btn-xs" onclick="event.stopPropagation(); abonnementManager.exportDayData('${day.date}')">
                                    <i class="fas fa-download"></i>
                                </button>
                            </div>
                        </div>
                    </td>
                `;
                }).join('')}
            </tr>
        `;

        // Show/hide incomplete selections alert
        const hasIncomplete = calendarData.daily_data.some(day => day.incomplete_count > 0);
        const alert = document.getElementById('incompleteSelectionsAlert');
        if (alert) {
            alert.style.display = hasIncomplete ? 'block' : 'none';
            
            // Update alert details
            if (hasIncomplete) {
                const totalIncomplete = calendarData.daily_data.reduce((sum, day) => sum + day.incomplete_count, 0);
                const alertText = alert.querySelector('.alert p');
                if (alertText) {
                    alertText.textContent = `${totalIncomplete} sélection${totalIncomplete > 1 ? 's' : ''} incomplète${totalIncomplete > 1 ? 's' : ''} détectée${totalIncomplete > 1 ? 's' : ''} cette semaine.`;
                }
            }
        }

        // Add hover effects for day actions
        this.addCalendarHoverEffects();

        console.log('✅ Enhanced weekly calendar rendered');
    }

    /**
     * Add hover effects for calendar days
     */
    addCalendarHoverEffects() {
        document.querySelectorAll('.calendar-day').forEach(day => {
            day.addEventListener('mouseenter', () => {
                const actions = day.querySelector('.day-actions');
                if (actions) actions.style.display = 'block';
            });
            
            day.addEventListener('mouseleave', () => {
                const actions = day.querySelector('.day-actions');
                if (actions) actions.style.display = 'none';
            });
        });
    }

    /**
     * Show detailed day information
     */
    async showDayDetails(date) {
        try {
            const result = await AdminAPI.request('GET', `${this.apiEndpoints.selections}/day/${date}`);
            
            if (result.success) {
                this.displayDayDetailsModal(date, result.data);
            } else {
                throw new Error(result.error || 'Failed to load day details');
            }
        } catch (error) {
            console.error('❌ Error loading day details:', error);
            this.showNotification('Erreur lors du chargement des détails du jour', 'error');
        }
    }

    /**
     * Display day details modal
     */
    displayDayDetailsModal(date, dayData) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-calendar-day"></i> 
                            Détails du ${this.formatDateLong(date)}
                        </h5>
                        <button type="button" class="btn-close" data-coreui-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row g-3">
                            <!-- Day Statistics -->
                            <div class="col-12">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="h4 text-primary mb-0">${dayData.total_selections}</div>
                                            <small class="text-muted">Sélections Totales</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="h4 text-success mb-0">${dayData.completed_selections}</div>
                                            <small class="text-muted">Complètes</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="h4 text-warning mb-0">${dayData.incomplete_selections}</div>
                                            <small class="text-muted">Incomplètes</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3">
                                        <div class="text-center">
                                            <div class="h4 text-info mb-0">${dayData.active_subscriptions}</div>
                                            <small class="text-muted">Abonnements Actifs</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            
                            <!-- Cuisine Breakdown -->
                            <div class="col-md-6">
                                <h6>Répartition par Cuisine</h6>
                                <div class="cuisine-breakdown">
                                    ${Object.entries(dayData.cuisine_counts).map(([cuisine, count]) => `
                                        <div class="d-flex justify-content-between align-items-center mb-2">
                                            <div>
                                                ${this.getCuisineIcon(cuisine)} ${cuisine.charAt(0).toUpperCase() + cuisine.slice(1)}
                                            </div>
                                            <div>
                                                <span class="badge bg-primary">${count}</span>
                                                <small class="text-muted ms-1">${Math.round((count / dayData.total_selections) * 100)}%</small>
                                            </div>
                                        </div>
                                    `).join('')}
                                </div>
                            </div>
                            
                            <!-- Incomplete Subscriptions -->
                            ${dayData.incomplete_subscriptions && dayData.incomplete_subscriptions.length > 0 ? `
                                <div class="col-md-6">
                                    <h6>Sélections Incomplètes</h6>
                                    <div class="incomplete-list">
                                        ${dayData.incomplete_subscriptions.map(sub => `
                                            <div class="d-flex justify-content-between align-items-center mb-2">
                                                <div>
                                                    <div class="fw-semibold">${sub.user_name}</div>
                                                    <small class="text-muted">${sub.user_email}</small>
                                                </div>
                                                <button class="btn btn-outline-warning btn-sm" 
                                                        onclick="abonnementManager.sendSelectionReminder(${sub.id}, '${date}')">
                                                    <i class="fas fa-bell"></i> Rappel
                                                </button>
                                            </div>
                                        `).join('')}
                                    </div>
                                </div>
                            ` : ''}
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-primary" onclick="abonnementManager.exportDayData('${date}')">
                            <i class="fas fa-download"></i> Exporter
                        </button>
                        <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const modalInstance = new coreui.Modal(modal);
        modalInstance.show();
        
        modal.addEventListener('hidden.coreui.modal', () => {
            modal.remove();
        });
    }

    /**
     * Helper methods for calendar
     */
    isToday(date) {
        const today = new Date().toISOString().split('T')[0];
        return date === today;
    }

    isPastDay(date) {
        const today = new Date().toISOString().split('T')[0];
        return date < today;
    }

    formatDateShort(date) {
        return new Date(date).toLocaleDateString('fr-FR', { day: '2-digit', month: '2-digit' });
    }

    formatDateLong(date) {
        return new Date(date).toLocaleDateString('fr-FR', { 
            weekday: 'long', 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric' 
        });
    }

    /**
     * Initialize charts for analytics view
     */
    initializeCharts() {
        // Initialize Chart.js charts
        this.initConversionChart();
        this.initCuisinePreferencesChart();
        this.initRevenueTrendsChart();
    }

    /**
     * Initialize conversion chart
     */
    initConversionChart() {
        const ctx = document.getElementById('conversionChart');
        if (!ctx) return;

        this.charts.conversion = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['Sem 1', 'Sem 2', 'Sem 3', 'Sem 4'],
                datasets: [{
                    label: 'Taux de Conversion (%)',
                    data: [0, 0, 0, 0],
                    borderColor: '#a9b73e',
                    backgroundColor: 'rgba(169, 183, 62, 0.1)',
                    tension: 0.4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        max: 100
                    }
                }
            }
        });
    }

    /**
     * Initialize cuisine preferences chart
     */
    initCuisinePreferencesChart() {
        const ctx = document.getElementById('cuisinePreferencesChart');
        if (!ctx) return;

        this.charts.cuisinePreferences = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Marocain', 'Italien', 'International'],
                datasets: [{
                    data: [0, 0, 0],
                    backgroundColor: [
                        '#28a745',
                        '#007bff',
                        '#fd7e14'
                    ]
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });
    }

    /**
     * Initialize revenue trends chart
     */
    initRevenueTrendsChart() {
        const ctx = document.getElementById('revenueTrendsChart');
        if (!ctx) return;

        this.charts.revenueTrends = new Chart(ctx, {
            type: 'bar',
            data: {
                labels: [],
                datasets: [{
                    label: 'Revenus (MAD)',
                    data: [],
                    backgroundColor: 'rgba(169, 183, 62, 0.7)',
                    borderColor: '#a9b73e',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true
                    }
                }
            }
        });
    }

    /**
     * Update filter and reload data
     */
    updateFilter(key, value) {
        if (value === '') {
            delete this.currentFilters[key];
        } else {
            this.currentFilters[key] = value;
        }
        
        this.currentPage = 1; // Reset to first page
        this.saveFiltersToLocalStorage();
        this.updateActiveFiltersDisplay();
        this.loadSubscriptions();
    }

    /**
     * Save filters to localStorage for persistence
     */
    saveFiltersToLocalStorage() {
        localStorage.setItem('abonnement_filters', JSON.stringify(this.currentFilters));
    }

    /**
     * Load filters from localStorage
     */
    loadFiltersFromLocalStorage() {
        const savedFilters = localStorage.getItem('abonnement_filters');
        if (savedFilters) {
            try {
                this.currentFilters = JSON.parse(savedFilters);
                this.applyFiltersToUI();
                this.updateActiveFiltersDisplay();
            } catch (error) {
                console.error('Error loading saved filters:', error);
            }
        }
    }

    /**
     * Apply saved filters to UI elements
     */
    applyFiltersToUI() {
        if (this.currentFilters.status) {
            const statusFilter = document.getElementById('statusFilter');
            if (statusFilter) statusFilter.value = this.currentFilters.status;
        }
        if (this.currentFilters.type) {
            const typeFilter = document.getElementById('typeFilter');
            if (typeFilter) typeFilter.value = this.currentFilters.type;
        }
        if (this.currentFilters.date_from) {
            const dateFromFilter = document.getElementById('dateFromFilter');
            if (dateFromFilter) dateFromFilter.value = this.currentFilters.date_from;
        }
        if (this.currentFilters.date_to) {
            const dateToFilter = document.getElementById('dateToFilter');
            if (dateToFilter) dateToFilter.value = this.currentFilters.date_to;
        }
        if (this.currentFilters.search) {
            const customerSearchFilter = document.getElementById('customerSearchFilter');
            if (customerSearchFilter) customerSearchFilter.value = this.currentFilters.search;
        }
    }

    /**
     * Update active filters display
     */
    updateActiveFiltersDisplay() {
        const container = document.getElementById('activeFiltersContainer');
        if (!container) return;

        const activeCount = Object.keys(this.currentFilters).length;
        
        if (activeCount === 0) {
            container.style.display = 'none';
            return;
        }

        const filterTags = Object.entries(this.currentFilters).map(([key, value]) => {
            const label = this.getFilterLabel(key, value);
            return `
                <span class="badge bg-primary me-1 mb-1">
                    ${label}
                    <button type="button" class="btn-close btn-close-white ms-1" 
                            onclick="abonnementManager.removeFilter('${key}')" 
                            aria-label="Remove filter"></button>
                </span>
            `;
        }).join('');

        container.innerHTML = `
            <div class="d-flex align-items-center flex-wrap">
                <small class="text-muted me-2">Filtres actifs:</small>
                ${filterTags}
                <button class="btn btn-link btn-sm p-0 ms-2" onclick="abonnementManager.clearFilters()">
                    <small>Tout effacer</small>
                </button>
            </div>
        `;
        container.style.display = 'block';
    }

    /**
     * Get filter label for display
     */
    getFilterLabel(key, value) {
        const labels = {
            status: `Statut: ${value}`,
            type: `Type: ${value}`,
            date_from: `Du: ${value}`,
            date_to: `Au: ${value}`,
            search: `Recherche: ${value}`
        };
        return labels[key] || `${key}: ${value}`;
    }

    /**
     * Remove specific filter
     */
    removeFilter(key) {
        delete this.currentFilters[key];
        this.currentPage = 1;
        this.saveFiltersToLocalStorage();
        this.applyFiltersToUI();
        this.updateActiveFiltersDisplay();
        this.loadSubscriptions();
    }

    /**
     * Clear all filters
     */
    clearFilters() {
        this.currentFilters = {};
        this.currentPage = 1;
        
        // Reset filter inputs
        document.getElementById('statusFilter').value = '';
        document.getElementById('typeFilter').value = '';
        document.getElementById('dateFromFilter').value = '';
        document.getElementById('dateToFilter').value = '';
        document.getElementById('customerSearchFilter').value = '';
        
        // Clear localStorage and update display
        this.saveFiltersToLocalStorage();
        this.updateActiveFiltersDisplay();
        
        this.loadSubscriptions();
    }

    /**
     * Switch between tabs
     */
    switchTab(tabId) {
        this.currentTab = tabId;
        
        // Load data based on active tab
        switch (tabId) {
            case 'table-view':
                this.loadSubscriptions();
                break;
            case 'calendar-view':
                this.loadCalendarData();
                break;
            case 'analytics-view':
                this.loadAnalyticsData();
                break;
            case 'status-management':
                this.loadStatusManagementData();
                break;
        }
    }

    /**
     * Setup real-time updates
     */
    setupRealTimeUpdates() {
        // Update pending badge every 30 seconds
        this.refreshTimer = setInterval(() => {
            this.updatePendingBadge();
            
            // Refresh current view data
            if (this.currentTab === 'table-view') {
                this.loadSubscriptions();
            } else if (this.currentTab === 'calendar-view') {
                this.loadCalendarData();
            }
        }, this.refreshInterval);

        console.log('✅ Real-time updates enabled');
    }

    /**
     * Update pending confirmations badge
     */
    async updatePendingBadge() {
        try {
            const result = await AdminAPI.request('GET', this.apiEndpoints.pending_count);
            
            if (result.success) {
                const badge = document.getElementById('pending-confirmations-badge');
                if (badge) {
                    if (result.count > 0) {
                        badge.textContent = result.count;
                        badge.style.display = 'inline-block';
                    } else {
                        badge.style.display = 'none';
                    }
                }
                
                // Update sidebar badge as well
                if (window.updatePendingConfirmationsBadge) {
                    window.updatePendingConfirmationsBadge();
                }
            }
        } catch (error) {
            console.error('❌ Error updating pending badge:', error);
        }
    }

    /**
     * Bulk status update
     */
    async bulkStatusUpdate(fromStatus, toStatus) {
        if (!confirm(`Êtes-vous sûr de vouloir changer tous les abonnements "${fromStatus}" vers "${toStatus}" ?`)) {
            return;
        }

        try {
            // Get all subscriptions with the from status
            const subscriptions = this.cache.subscriptions?.filter(sub => sub.statut === fromStatus) || [];
            const subscriptionIds = subscriptions.map(sub => sub.id);

            if (subscriptionIds.length === 0) {
                this.showNotification(`Aucun abonnement "${fromStatus}" trouvé`, 'warning');
                return;
            }

            const result = await AdminAPI.request('POST', this.apiEndpoints.bulk_actions, {
                action: toStatus === 'actif' ? 'activate' : 
                       toStatus === 'suspendu' ? 'suspend' : 'cancel',
                subscription_ids: subscriptionIds
            });

            if (result.success) {
                this.showNotification(`${subscriptionIds.length} abonnement(s) mis à jour avec succès`, 'success');
                this.refreshAllData();
            } else {
                throw new Error(result.error || 'Bulk update failed');
            }
        } catch (error) {
            console.error('❌ Bulk status update error:', error);
            this.showNotification('Erreur lors de la mise à jour en lot', 'error');
        }
    }

    /**
     * Export data
     */
    async exportData() {
        try {
            const params = new URLSearchParams({
                format: 'csv',
                ...this.currentFilters
            });

            const result = await AdminAPI.request('GET', `${this.apiEndpoints.export}?${params}`);

            if (result.success) {
                this.showNotification('Export en cours de préparation...', 'info');
                // Handle download URL when ready
            } else {
                throw new Error(result.error || 'Export failed');
            }
        } catch (error) {
            console.error('❌ Export error:', error);
            this.showNotification('Erreur lors de l\'export', 'error');
        }
    }

    /**
     * Refresh all data
     */
    async refreshAllData() {
        console.log('🔄 Refreshing all data...');
        
        // Clear cache
        this.cache = {
            statistics: null,
            subscriptions: null,
            calendar: null,
            lastUpdate: null
        };

        // Reload current view
        await this.loadInitialData();
        
        this.showNotification('Données actualisées', 'success');
    }

    /**
     * Helper methods
     */
    getCurrentWeekStart() {
        const now = new Date();
        const monday = new Date(now.setDate(now.getDate() - now.getDay() + 1));
        return monday.toISOString().split('T')[0];
    }

    getCuisineIcon(cuisine) {
        const icons = {
            'marocain': '🇲🇦',
            'italien': '🇮🇹',
            'international': '🌍'
        };
        return icons[cuisine] || '🍽️';
    }

    updateElement(id, value) {
        const element = document.getElementById(id);
        if (element) {
            element.textContent = value;
        }
    }

    showGlobalLoading(show) {
        const overlay = document.getElementById('loadingOverlay');
        if (overlay) {
            overlay.style.display = show ? 'flex' : 'none';
        }
    }

    showTableLoading(show) {
        const loading = document.getElementById('tableLoadingState');
        const table = document.getElementById('subscriptionsTableContainer');
        
        if (loading) loading.style.display = show ? 'block' : 'none';
        if (table) table.style.display = show ? 'none' : 'block';
    }

    debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    showNotification(message, type = 'info') {
        const alertClass = type === 'error' ? 'alert-danger' : 
                          type === 'success' ? 'alert-success' : 
                          type === 'warning' ? 'alert-warning' : 'alert-info';
        
        const notification = document.createElement('div');
        notification.className = `alert ${alertClass} alert-dismissible fade show position-fixed`;
        notification.style.cssText = 'top: 20px; right: 20px; z-index: 9999; min-width: 300px;';
        notification.innerHTML = `
            <i class="fas fa-${type === 'success' ? 'check-circle' : type === 'error' ? 'exclamation-circle' : 'info-circle'}"></i>
            ${message}
            <button type="button" class="btn-close" data-coreui-dismiss="alert"></button>
        `;
        
        document.body.appendChild(notification);
        
        setTimeout(() => {
            if (notification.parentNode) {
                notification.remove();
            }
        }, 5000);
    }

    /**
     * Show status details modal
     */
    async showStatusDetails(status) {
        try {
            const result = await AdminAPI.request('GET', `${this.apiEndpoints.subscriptions}?status=${status}&limit=100`);
            
            if (result.success) {
                this.displayStatusDetailsModal(status, result.data);
            } else {
                throw new Error(result.error || 'Failed to load status details');
            }
        } catch (error) {
            console.error('❌ Error loading status details:', error);
            this.showNotification('Erreur lors du chargement des détails', 'error');
        }
    }

    /**
     * Display status details modal
     */
    displayStatusDetailsModal(status, subscriptions) {
        const statusLabels = {
            'en_confirmation': 'En Confirmation',
            'actif': 'Actif',
            'suspendu': 'Suspendu',
            'expire': 'Expiré',
            'annule': 'Annulé'
        };

        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-list"></i> 
                            Abonnements ${statusLabels[status]} (${subscriptions.length})
                        </h5>
                        <button type="button" class="btn-close" data-coreui-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${subscriptions.length === 0 ? `
                            <div class="text-center text-muted py-4">
                                <i class="fas fa-inbox fa-3x mb-3"></i>
                                <div>Aucun abonnement avec le statut "${statusLabels[status]}"</div>
                            </div>
                        ` : `
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Client</th>
                                            <th>Type</th>
                                            <th>Date Création</th>
                                            <th>Montant</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${subscriptions.map(sub => `
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">${sub.user.prenom} ${sub.user.nom}</div>
                                                    <small class="text-muted">${sub.user.email}</small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-light text-dark">${sub.type}</span>
                                                </td>
                                                <td>
                                                    <small>${new Date(sub.dateCreation).toLocaleDateString('fr-FR')}</small>
                                                </td>
                                                <td>
                                                    <strong>${sub.montant} MAD</strong>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-primary" onclick="abonnementManager.viewSubscriptionDetails(${sub.id})">
                                                        <i class="fas fa-eye"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                        `}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const modalInstance = new coreui.Modal(modal);
        modalInstance.show();
        
        modal.addEventListener('hidden.coreui.modal', () => {
            modal.remove();
        });
    }

    /**
     * Bulk status transition with confirmation
     */
    async bulkStatusTransition(fromStatus, toStatus) {
        const statusLabels = {
            'en_confirmation': 'En Confirmation',
            'actif': 'Actif',
            'suspendu': 'Suspendu',
            'expire': 'Expiré',
            'annule': 'Annulé'
        };

        const confirmMessage = `Êtes-vous sûr de vouloir changer tous les abonnements "${statusLabels[fromStatus]}" vers "${statusLabels[toStatus]}" ?`;
        
        if (!confirm(confirmMessage)) {
            return;
        }

        try {
            // Get all subscriptions with the from status
            const result = await AdminAPI.request('GET', `${this.apiEndpoints.subscriptions}?status=${fromStatus}`);

            if (!result.success || !result.data.length) {
                this.showNotification(`Aucun abonnement "${statusLabels[fromStatus]}" trouvé`, 'warning');
                return;
            }

            const subscriptionIds = result.data.map(sub => sub.id);

            const updateResult = await AdminAPI.request('POST', this.apiEndpoints.bulk_actions, {
                action: 'status_change',
                subscription_ids: subscriptionIds,
                new_status: toStatus
            });

            if (updateResult.success) {
                this.showNotification(`${subscriptionIds.length} abonnement(s) mis à jour avec succès`, 'success');
                this.refreshAllData();
            } else {
                throw new Error(updateResult.error || 'Bulk update failed');
            }
        } catch (error) {
            console.error('❌ Bulk status transition error:', error);
            this.showNotification('Erreur lors de la mise à jour en lot', 'error');
        }
    }

    /**
     * Auto-expire old subscriptions
     */
    async autoExpireOldSubscriptions() {
        if (!confirm('Voulez-vous automatiquement expirer les abonnements anciens (>30 jours) ?')) {
            return;
        }

        try {
            const result = await AdminAPI.request('POST', `${this.apiEndpoints.bulk_actions}/auto-expire`, {
                action: 'auto_expire'
            });

            if (result.success) {
                this.showNotification(`${result.expired_count || 0} abonnement(s) expiré(s) automatiquement`, 'success');
                this.refreshAllData();
            } else {
                throw new Error(result.error || 'Auto-expire failed');
            }
        } catch (error) {
            console.error('❌ Auto-expire error:', error);
            this.showNotification('Erreur lors de l\'expiration automatique', 'error');
        }
    }

    /**
     * Find incomplete selections
     */
    async findIncompleteSelections() {
        try {
            const result = await AdminAPI.request('GET', `${this.apiEndpoints.selections}/incomplete`);

            if (result.success) {
                this.displayIncompleteSelectionsModal(result.data);
            } else {
                throw new Error(result.error || 'Failed to find incomplete selections');
            }
        } catch (error) {
            console.error('❌ Error finding incomplete selections:', error);
            this.showNotification('Erreur lors de la recherche', 'error');
        }
    }

    /**
     * Display incomplete selections modal
     */
    displayIncompleteSelectionsModal(incompleteData) {
        const modal = document.createElement('div');
        modal.className = 'modal fade';
        modal.innerHTML = `
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <i class="fas fa-exclamation-triangle text-warning"></i> 
                            Sélections Incomplètes (${incompleteData.length})
                        </h5>
                        <button type="button" class="btn-close" data-coreui-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        ${incompleteData.length === 0 ? `
                            <div class="text-center text-success py-4">
                                <i class="fas fa-check-circle fa-3x mb-3"></i>
                                <div>Toutes les sélections sont complètes !</div>
                            </div>
                        ` : `
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead class="table-light">
                                        <tr>
                                            <th>Client</th>
                                            <th>Abonnement</th>
                                            <th>Jours Manquants</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        ${incompleteData.map(item => `
                                            <tr>
                                                <td>
                                                    <div class="fw-semibold">${item.user_name}</div>
                                                    <small class="text-muted">${item.user_email}</small>
                                                </td>
                                                <td>
                                                    <span class="badge bg-primary">${item.subscription_type}</span>
                                                </td>
                                                <td>
                                                    <div class="small">
                                                        ${item.missing_days.map(day => `
                                                            <span class="badge bg-warning me-1">${day}</span>
                                                        `).join('')}
                                                    </div>
                                                </td>
                                                <td>
                                                    <button class="btn btn-sm btn-outline-warning" 
                                                            onclick="abonnementManager.sendSelectionReminder(${item.subscription_id})">
                                                        <i class="fas fa-bell"></i> Rappel
                                                    </button>
                                                </td>
                                            </tr>
                                        `).join('')}
                                    </tbody>
                                </table>
                            </div>
                            <div class="mt-3">
                                <button class="btn btn-warning" onclick="abonnementManager.sendBulkReminders()">
                                    <i class="fas fa-envelope"></i> Envoyer Rappels à Tous
                                </button>
                            </div>
                        `}
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-coreui-dismiss="modal">Fermer</button>
                    </div>
                </div>
            </div>
        `;
        
        document.body.appendChild(modal);
        const modalInstance = new coreui.Modal(modal);
        modalInstance.show();
        
        modal.addEventListener('hidden.coreui.modal', () => {
            modal.remove();
        });
    }

    /**
     * Propose renewal to expired subscriptions
     */
    async proposeRenewalToExpired() {
        if (!confirm('Voulez-vous proposer un renouvellement à tous les abonnements expirés ?')) {
            return;
        }

        try {
            const result = await AdminAPI.request('POST', `${this.apiEndpoints.bulk_actions}/propose-renewal`, {
                action: 'propose_renewal'
            });

            if (result.success) {
                this.showNotification(`Proposition de renouvellement envoyée à ${result.sent_count || 0} client(s)`, 'success');
            } else {
                throw new Error(result.error || 'Renewal proposal failed');
            }
        } catch (error) {
            console.error('❌ Renewal proposal error:', error);
            this.showNotification('Erreur lors de l\'envoi des propositions', 'error');
        }
    }

    /**
     * Refresh status history
     */
    async refreshStatusHistory() {
        try {
            const result = await AdminAPI.request('GET', `${this.apiEndpoints.status_update}/history`);
            if (result.success) {
                this.renderStatusHistory(result.data);
            } else {
                throw new Error(result.error || 'Failed to load status history');
            }
        } catch (error) {
            console.error('❌ Error loading status history:', error);
            this.showNotification('Erreur lors du chargement de l\'historique', 'error');
        }
    }

    /**
     * Render status history
     */
    renderStatusHistory(historyData) {
        const container = document.getElementById('statusHistoryContainer');
        if (!container) return;

        if (!historyData || historyData.length === 0) {
            container.innerHTML = `
                <div class="text-center text-muted py-3">
                    <i class="fas fa-history fa-2x mb-2"></i>
                    <div>Aucun changement de statut récent</div>
                </div>
            `;
            return;
        }

        container.innerHTML = `
            <div class="timeline">
                ${historyData.map(item => `
                    <div class="timeline-item">
                        <div class="timeline-marker bg-${this.getStatusColor(item.new_status)}"></div>
                        <div class="timeline-content">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <div class="fw-semibold">
                                        ${item.user_name} 
                                        <span class="badge bg-${this.getStatusColor(item.old_status)}">${item.old_status}</span>
                                        →
                                        <span class="badge bg-${this.getStatusColor(item.new_status)}">${item.new_status}</span>
                                    </div>
                                    <small class="text-muted">${item.reason || 'Changement manuel'}</small>
                                </div>
                                <small class="text-muted">${new Date(item.created_at).toLocaleString('fr-FR')}</small>
                            </div>
                        </div>
                    </div>
                `).join('')}
            </div>
        `;
    }

    /**
     * Get status color
     */
    getStatusColor(status) {
        const colors = {
            'en_confirmation': 'warning',
            'actif': 'success',
            'suspendu': 'info',
            'expire': 'secondary',
            'annule': 'danger'
        };
        return colors[status] || 'secondary';
    }

    /**
     * Send bulk selection reminders
     */
    async sendBulkReminders() {
        try {
            const result = await AdminAPI.request('POST', `${this.apiEndpoints.selections}/send-bulk-reminders`, {
                action: 'send_bulk_reminders'
            });

            if (result.success) {
                this.showNotification(`Rappels envoyés à ${result.sent_count || 0} client(s)`, 'success');
            } else {
                throw new Error(result.error || 'Bulk reminders failed');
            }
        } catch (error) {
            console.error('❌ Bulk reminders error:', error);
            this.showNotification('Erreur lors de l\'envoi des rappels', 'error');
        }
    }

    /**
     * View subscription details
     */
    viewSubscriptionDetails(subscriptionId) {
        window.location.href = `/admin/abonnements/${subscriptionId}`;
    }

    /**
     * Cleanup on destroy
     */
    destroy() {
        if (this.refreshTimer) {
            clearInterval(this.refreshTimer);
        }
        
        // Destroy charts
        Object.values(this.charts).forEach(chart => {
            if (chart) {
                chart.destroy();
            }
        });
        
        console.log('🧹 AbonnementManager destroyed');
    }
}

// Global instance
window.AbonnementManager = AbonnementManager; 