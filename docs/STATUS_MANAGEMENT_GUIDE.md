# JoodKitchen - Centralized Status Management Guide

## 🎯 **Overview**

JoodKitchen uses a **centralized status management system** that ensures consistency across the entire application. All order statuses are managed through a single PHP enum and synchronized to the frontend via a JavaScript utility.

---

## 🏗️ **Architecture**

```
PHP Enum (OrderStatus) → API Endpoint → JavaScript Utility → UI Components
```

- **Single Source of Truth**: All status logic in `src/Enum/OrderStatus.php`
- **Type Safety**: PHP 8.1 enum prevents invalid statuses
- **Real-time Sync**: JavaScript utility loads config from API
- **Full Backward Compatibility**: Supports both enum names and values

---

## 🔧 **PHP Backend Usage**

### Using OrderStatus Enum

```php
use App\Enum\OrderStatus;

// Setting status
$order->setStatut(OrderStatus::PENDING->value);

// Getting enum from value
$statusEnum = OrderStatus::from($order->getStatut());

// Using enum methods
$label = $statusEnum->getLabel();           // "En attente"
$badge = $statusEnum->getBadgeClass();      // "jood-warning-bg"  
$icon = $statusEnum->getIconClass();        // "fas fa-clock"
$html = $statusEnum->getBadgeHtml();        // Full HTML badge

// Status transitions
if ($currentStatus->canTransitionTo(OrderStatus::PREPARING)) {
    $order->setStatut(OrderStatus::PREPARING->value);
}

// Get all valid statuses
$allStatuses = OrderStatus::getAll();       // ['en_attente', 'confirme', ...]
$choices = OrderStatus::getChoices();       // ['En attente' => 'en_attente', ...]
```

### Repository Queries

```php
use App\Enum\OrderStatus;

// Correct way - using enum values
$pendingOrders = $repository->findBy(['statut' => OrderStatus::PENDING->value]);

// For multiple statuses
$activeOrders = $repository->createQueryBuilder('o')
    ->where('o.statut IN (:statuses)')
    ->setParameter('statuses', [
        OrderStatus::PREPARING->value,
        OrderStatus::READY->value
    ])
    ->getQuery()
    ->getResult();
```

### Validation

```php
// Entity validation using enum
#[Assert\Choice(callback: [OrderStatus::class, 'getAll'])]
private ?string $statut = OrderStatus::PENDING->value;

// Controller validation
try {
    $newStatus = OrderStatus::from($data['statut']);
} catch (\ValueError $e) {
    return new JsonResponse(['error' => 'Invalid status'], 400);
}
```

### API Configuration

The JavaScript utility automatically loads configuration from:

```php
// Endpoint: /api/order-status-config  
// Returns complete status configuration including:
[
    'PENDING' => [
        'value' => 'en_attente',
        'label' => 'En attente', 
        'badge_class' => 'jood-warning-bg',
        'icon_class' => 'fas fa-clock',
        'next_possible_statuses' => ['confirme', 'en_preparation', 'annule'],
        'notification' => ['message' => '...', 'type' => 'info'],
        'estimated_minutes' => 30
    ],
    // ... other statuses
]
```

---

## 💻 **JavaScript Frontend Usage**

### Basic Setup

```html
<!-- Include the utility -->
<script src="{{ asset('js/admin/utils/order-status.js') }}"></script>

<script>
// Initialize before using
await OrderStatus.init();
</script>
```

### Core Methods

```javascript
// Get status information
const label = OrderStatus.getLabel('en_attente');           // "En attente"
const badge = OrderStatus.getBadgeClass('en_attente');      // "jood-warning-bg"
const icon = OrderStatus.getIconClass('en_attente');        // "fas fa-clock"

// Generate HTML
const badgeHtml = OrderStatus.getBadgeHtml('en_attente');   // Complete badge HTML

// Get all statuses
const allStatuses = OrderStatus.getAll();
// Returns: [{ name: 'PENDING', value: 'en_attente', label: 'En attente', ... }, ...]

// Status validation  
const isValid = OrderStatus.findStatus('en_attente') !== null;
const isFinal = OrderStatus.isFinal('livre');              // true

// Backward compatibility - supports both formats
const label1 = OrderStatus.getLabel('en_attente');         // By value
const label2 = OrderStatus.getLabel('PENDING');            // By enum name (legacy)
const canChange = OrderStatus.canChangeTo('en_attente', 'confirme');
```

### UI Helper Methods (NEW)

```javascript
// Populate any select element with automatic retry
OrderStatus.populateSelect('myStatusSelect', 'Choose status');
OrderStatus.populateSelect('statusFilter', 'Tous', 'en_attente'); // with selected value

// Get select options HTML
const options = OrderStatus.getSelectOptions('en_attente'); // current status pre-selected

// Create status table/filter components
const tableHtml = OrderStatus.createStatusTable(['en_attente', 'confirme', 'pret']);
const filterHtml = OrderStatus.createAdvancedFilter('statusFilter');
```

### Component Integration

```javascript
class MyOrderComponent {
    async init() {
        // Always initialize OrderStatus first
        await OrderStatus.init();
        
        // Populate UI elements
        OrderStatus.populateSelect('statusFilter', 'Tous');
        
        this.bindEvents();
        this.loadData();
    }
    
    renderOrderRow(order) {
        return `
            <tr>
                <td>${order.numero}</td>
                <td>${OrderStatus.getBadgeHtml(order.statut)}</td>
                <td>
                    <select onchange="updateStatus(${order.id}, this.value)">
                        ${OrderStatus.getSelectOptions(order.statut)}
                    </select>
                </td>
            </tr>
        `;
    }
}
```

---

## 🎨 **Template Usage**

### Dynamic Status Options

```twig
<!-- Instead of hardcoded options -->
<select class="form-select" id="statusFilter">
    <option value="">Tous</option>
    <!-- Options populated by OrderStatus.populateSelect() -->
</select>
```

### PHP-Generated Options (Alternative)

```twig
<select class="form-select" id="statusFilter">
    <option value="">Tous</option>
    {% for status in order_statuses %}
        <option value="{{ status.value }}">{{ status.label }}</option>
    {% endfor %}
</select>
```

---

## 🔄 **Real-world Implementation Examples**

### 1. Order Management Page

```javascript
class OrdersManager {
    async init() {
        await OrderStatus.init();
        OrderStatus.populateSelect('statusFilter', 'Tous');
        this.loadOrders();
    }
    
    renderOrder(order) {
        return `
            <div class="order-card">
                <h5>Order #${order.numero}</h5>
                ${OrderStatus.getBadgeHtml(order.statut)}
                <button onclick="changeStatus(${order.id})">
                    Change Status
                </button>
            </div>
        `;
    }
}
```

### 2. Kitchen Dashboard

```javascript
class KitchenDashboard {
    async init() {
        await OrderStatus.init();
        this.setupStatusColumns();
    }
    
    setupStatusColumns() {
        const statuses = ['en_attente', 'en_preparation', 'pret'];
        statuses.forEach(status => {
            document.getElementById(`${status}-column`).innerHTML = `
                <h3>${OrderStatus.getLabel(status)}</h3>
                <div class="orders-list" data-status="${status}"></div>
            `;
        });
    }
}
```

### 3. Status Modal

```javascript
function showStatusModal(orderId, currentStatus) {
    const modal = `
        <div class="modal">
            <h3>Change Status</h3>
            <select id="newStatus">
                ${OrderStatus.getSelectOptions(currentStatus)}
            </select>
            <button onclick="updateOrderStatus(${orderId})">Update</button>
        </div>
    `;
    document.body.insertAdjacentHTML('beforeend', modal);
}
```

---

## 🚀 **Quick Start Checklist**

For any new component using order statuses:

### PHP Component
- [ ] `use App\Enum\OrderStatus;`
- [ ] Use `OrderStatus::PENDING->value` instead of `'en_attente'`
- [ ] Use enum methods: `->getLabel()`, `->getBadgeClass()`, etc.
- [ ] Validate with `OrderStatus::from($value)`

### JavaScript Component  
- [ ] Include `order-status.js` in template
- [ ] Call `await OrderStatus.init()` before using
- [ ] Use `OrderStatus.getBadgeHtml()` for UI
- [ ] Use `OrderStatus.populateSelect()` for form elements

### Template
- [ ] Remove hardcoded status options
- [ ] Let JavaScript populate select elements
- [ ] Use PHP enum for server-side rendering if needed

---

## 💡 **Best Practices**

1. **Always use enum values in database**: Store `'en_attente'`, not `'pending'`
2. **Initialize early**: Call `OrderStatus.init()` at component startup
3. **Consistent UI**: Use `getBadgeHtml()` for consistent styling
4. **Validation**: Always validate status transitions on both frontend and backend
5. **Error handling**: Handle invalid statuses gracefully
6. **Caching**: OrderStatus config is loaded once and cached automatically

---

## 🔍 **Troubleshooting**

### Common Issues

**Empty Status Filter Dropdown**
```javascript
// Problem: Filter dropdown shows no options
// Solution: Ensure proper initialization order
class OrdersManager {
    async init() {
        // 1. Initialize OrderStatus first
        await OrderStatus.init();
        
        // 2. Then populate UI elements  
        this.populateStatusFilter();
        
        // 3. Finally bind events and load data
        this.bindEvents();
        this.loadOrders();
    }
    
    populateStatusFilter() {
        // Built-in retry mechanism handles timing issues
        const success = OrderStatus.populateSelect('statusFilter', 'Tous');
        // No need for manual retry - automatic fallback included
    }
}
```

**Status not found error**
```javascript
// Check if OrderStatus is initialized
if (!OrderStatus.config) {
    console.log('Initializing OrderStatus...');
    await OrderStatus.init();
}

// Verify config loaded
console.log('Available statuses:', OrderStatus.getAll());
```

**API Configuration Missing next_possible_statuses**
```php
// Ensure PHP enum includes all required fields
public static function getJavaScriptConfig(): array
{
    $config = [];
    foreach (self::cases() as $case) {
        $config[$case->name] = [
            'value' => $case->value,
            'label' => $case->getLabel(),
            'badge_class' => $case->getBadgeClass(),
            'icon_class' => $case->getIconClass(),
            // ✅ Required: Include next possible statuses
            'next_possible_statuses' => array_map(
                fn($status) => $status->value, 
                $case->getNextPossibleStatuses()
            ),
            // ... other fields
        ];
    }
    return $config;
}
```

**Hardcoded status in template**
```html
<!-- ❌ Wrong -->
<option value="en_attente">En attente</option>

<!-- ✅ Correct -->
<select id="statusFilter">
    <option value="">Tous</option>
    <!-- Options populated by OrderStatus.populateSelect() -->
</select>
<script>
// In JavaScript initialization
OrderStatus.populateSelect('statusFilter', 'Tous');
</script>
```

**Filter Not Working After Population**
```javascript
// Ensure event handlers are bound after DOM population
async init() {
    await OrderStatus.init();
    this.populateStatusFilter();
    
    // Bind events AFTER populating DOM elements
    this.bindEvents();
}

bindEvents() {
    // Use correct element IDs that match your HTML
    const searchBtn = document.querySelector('#searchBtn');      // ✅ Correct
    const statusFilter = document.querySelector('#statusFilter'); // ✅ Correct
    
    if (searchBtn) {
        searchBtn.addEventListener('click', () => this.applyFilters());
    }
}
```

**Invalid status transition**
```php
// Always check before updating
if (!$currentStatus->canTransitionTo($newStatus)) {
    throw new InvalidArgumentException('Invalid transition');
}
```

---

## 📊 **Status Flow Reference**

```
en_attente ──► confirme ──► en_preparation ──► pret ──► en_livraison ──► livre
     │                           │                │                        ▲
     └─────────────── annule ◄───┴────────────────┴────────────────────────┘
```

**Available Statuses:**
- `en_attente` - Order created, awaiting confirmation
- `confirme` - Order confirmed and paid
- `en_preparation` - Kitchen is preparing the order  
- `pret` - Order ready for pickup/delivery
- `en_livraison` - Order out for delivery
- `livre` - Order successfully delivered
- `annule` - Order cancelled (possible from any status)

---

## 🆕 **Recent Updates & Improvements**

### Version 2.1 - Filter Integration Fix (2025-07-10)

- ✅ **Fixed Empty Filter Dropdowns**: Added automatic retry mechanism for timing issues
- ✅ **Enhanced API Endpoint**: Added `next_possible_statuses` field to configuration
- ✅ **Backward Compatibility**: Support for both enum names and status values
- ✅ **Improved Error Handling**: Better debugging and fallback mechanisms
- ✅ **Template Integration**: Seamless integration with CoreUI components

### Migration Notes

If upgrading from previous version:

1. **PHP**: Ensure `getJavaScriptConfig()` includes `next_possible_statuses`
2. **JavaScript**: Update initialization to use `await OrderStatus.init()`
3. **Templates**: Remove hardcoded status options, use `populateSelect()`
4. **Event Binding**: Ensure correct element IDs match your HTML

### Breaking Changes

- None - fully backward compatible

---

This centralized approach ensures **consistency**, **type safety**, and **easy maintenance** across your entire JoodKitchen application! 🚀 