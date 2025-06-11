/**
 * Dashboard JavaScript for JoodKitchen Admin
 * Handles charts, stats loading, and dashboard interactions
 */

// Global variables
let mainChart = null;
let doughnutChart = null;
let cardCharts = [];

// Dashboard initialization
function initializeDashboard() {
    console.log('🚀 Initializing dashboard...');
    
    try {
        // Check if Chart.js is loaded
        if (typeof Chart === 'undefined') {
            console.error('Chart.js is not loaded');
            return;
        }
        
        // Load dashboard data
        loadDashboardStats();
        loadChartsData();
        loadRecentActivity();
        
        console.log('✅ Dashboard initialized successfully');
    } catch (error) {
        console.error('❌ Error initializing dashboard:', error);
    }
}

// Load dashboard statistics
async function loadDashboardStats() {
    try {
        console.log('📊 Loading dashboard stats...');
        
        // Mock data for now - replace with actual API calls
        const stats = {
            orders: 156,
            revenue: 2847.50,
            customers: 89,
            dishes: 12
        };
        
        // Update stat widgets
        updateStatWidget('stats-orders', stats.orders);
        updateStatWidget('stats-revenue', `${stats.revenue}€`);
        updateStatWidget('stats-users', stats.customers);
        updateStatWidget('stats-dishes', stats.dishes);
        
    } catch (error) {
        console.error('Error loading dashboard stats:', error);
    }
}

// Update individual stat widget
function updateStatWidget(elementId, value) {
    const element = document.getElementById(elementId);
    if (element) {
        element.textContent = value;
    }
}

// Load and initialize charts
function loadChartsData() {
    try {
        console.log('📈 Loading charts...');
        
        if (typeof Chart === 'undefined') {
            console.error('Chart.js not available');
            return;
        }
        
        initializeMainChart();
        initializeDoughnutChart();
        initializeCardCharts();
        
    } catch (error) {
        console.error('Error loading charts:', error);
    }
}

// Initialize main sales chart
function initializeMainChart() {
    const ctx = document.getElementById('main-chart');
    if (!ctx) return;
    
    // Destroy existing chart if it exists
    if (mainChart) {
        mainChart.destroy();
    }
    
    // Mock data
    const data = {
        labels: ['Lun', 'Mar', 'Mer', 'Jeu', 'Ven', 'Sam', 'Dim'],
        datasets: [{
            label: 'Ventes (€)',
            data: [300, 450, 380, 520, 680, 890, 750],
            borderColor: '#667eea',
            backgroundColor: 'rgba(102, 126, 234, 0.1)',
            tension: 0.4,
            fill: true
        }]
    };
    
    mainChart = new Chart(ctx, {
        type: 'line',
        data: data,
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
                    grid: {
                        color: 'rgba(0,0,0,0.1)'
                    }
                },
                x: {
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
}

// Initialize doughnut chart for top dishes
function initializeDoughnutChart() {
    const ctx = document.getElementById('doughnut-chart');
    if (!ctx) return;
    
    // Destroy existing chart if it exists
    if (doughnutChart) {
        doughnutChart.destroy();
    }
    
    // Mock data
    const data = {
        labels: ['Pizza Margherita', 'Burger Classique', 'Salade César', 'Pasta Carbonara', 'Autres'],
        datasets: [{
            data: [30, 25, 20, 15, 10],
            backgroundColor: [
                '#667eea',
                '#764ba2',
                '#f093fb',
                '#f5576c',
                '#4facfe'
            ],
            borderWidth: 0
        }]
    };
    
    doughnutChart = new Chart(ctx, {
        type: 'doughnut',
        data: data,
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

// Initialize small card charts
function initializeCardCharts() {
    const cardChartIds = ['card-chart1', 'card-chart2', 'card-chart3', 'card-chart4'];
    
    cardChartIds.forEach((chartId, index) => {
        const ctx = document.getElementById(chartId);
        if (!ctx) return;
        
        // Destroy existing chart if it exists
        if (cardCharts[index]) {
            cardCharts[index].destroy();
        }
        
        // Mock data - different for each card
        const mockData = [
            [10, 15, 12, 20, 18, 25, 22], // Orders
            [300, 450, 380, 520, 480, 620, 580], // Revenue
            [5, 8, 6, 10, 12, 9, 15], // Customers
            [2, 3, 4, 2, 5, 3, 4] // Dishes
        ];
        
        const colors = ['#ffffff', '#ffffff', '#ffffff', '#ffffff'];
        
        cardCharts[index] = new Chart(ctx, {
            type: 'line',
            data: {
                labels: ['', '', '', '', '', '', ''],
                datasets: [{
                    data: mockData[index],
                    borderColor: colors[index],
                    backgroundColor: 'transparent',
                    borderWidth: 2,
                    pointRadius: 0,
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
                    x: {
                        display: false
                    },
                    y: {
                        display: false
                    }
                },
                elements: {
                    line: {
                        borderWidth: 2
                    }
                }
            }
        });
    });
}

// Load recent activity
function loadRecentActivity() {
    try {
        console.log('📝 Loading recent activity...');
        
        // Mock recent orders data
        const recentOrders = [
            { id: '#12345', customer: 'Jean Dupont', total: '25.50€', status: 'confirmed' },
            { id: '#12346', customer: 'Marie Martin', total: '18.20€', status: 'preparing' },
            { id: '#12347', customer: 'Pierre Bernard', total: '32.00€', status: 'ready' },
            { id: '#12348', customer: 'Sophie Laurent', total: '21.80€', status: 'delivered' }
        ];
        
        updateRecentOrdersTable(recentOrders);
        
    } catch (error) {
        console.error('Error loading recent activity:', error);
    }
}

// Update recent orders table
function updateRecentOrdersTable(orders) {
    const tableBody = document.querySelector('#recent-orders');
    if (!tableBody) return;
    
    tableBody.innerHTML = '';
    
    orders.forEach(order => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td>${order.id}</td>
            <td>${order.customer}</td>
            <td>${order.total}</td>
            <td><span class="status-badge status-${order.status}">${getStatusText(order.status)}</span></td>
            <td>
                <button class="btn btn-sm btn-outline-primary" onclick="viewOrder('${order.id}')">
                    <i class="fas fa-eye"></i>
                </button>
            </td>
        `;
        tableBody.appendChild(row);
    });
}

// Get status text in French
function getStatusText(status) {
    const statusMap = {
        'pending': 'En attente',
        'confirmed': 'Confirmée',
        'preparing': 'En préparation',
        'ready': 'Prête',
        'delivered': 'Livrée',
        'cancelled': 'Annulée'
    };
    return statusMap[status] || status;
}

// Change chart period
function changePeriod(period) {
    console.log('📅 Changing period to:', period);
    
    // Update active button
    document.querySelectorAll('.btn-group button').forEach(btn => {
        btn.classList.remove('btn-primary');
        btn.classList.add('btn-outline-secondary');
    });
    
    event.target.classList.remove('btn-outline-secondary');
    event.target.classList.add('btn-primary');
    
    // Reload chart data with new period
    loadChartsData();
}

// View order details
function viewOrder(orderId) {
    console.log('👁️ Viewing order:', orderId);
    // Navigate to order details page
    window.location.href = `/admin/orders/${orderId.replace('#', '')}`;
}

// Auto-refresh functionality
function startAutoRefresh() {
    // Refresh stats every 30 seconds
    setInterval(() => {
        loadDashboardStats();
    }, 30000);
    
    // Refresh charts every 5 minutes
    setInterval(() => {
        loadChartsData();
    }, 300000);
}

// Export functions for global access
window.initializeDashboard = initializeDashboard;
window.changePeriod = changePeriod;
window.viewOrder = viewOrder;

// Initialize when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Wait a bit for other scripts to load
    setTimeout(() => {
        if (document.querySelector('#main-chart')) {
            initializeDashboard();
        }
    }, 500);
}); 