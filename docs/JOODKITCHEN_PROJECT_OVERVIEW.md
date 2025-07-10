# 🍽️ JoodKitchen - Complete Project Overview & Reference

## 📋 **Project Concept & Vision**

**JoodKitchen** is a sophisticated, enterprise-grade food delivery and restaurant management system built with modern web technologies. The application serves as a complete digital solution for restaurant operations, from order management to kitchen workflow and customer experience.

### **🎯 Core Business Model**
- **Multi-Cuisine Restaurant**: Moroccan, Italian & International dishes
- **Daily Menu System**: 3 different cuisine menus every day (MAROCAIN, ITALIEN, INTERNATIONAL)
- **Multi-service Platform**: Dine-in, Takeaway, Home Delivery
- **B2C Customer App** + **B2B Admin Management System**
- **Real-time Order Tracking** with live kitchen integration
- **Loyalty Program** with points system
- **Flexible Ordering**: Individual dishes or complete daily menus

---

## 🏗️ **Technical Architecture**

### **🔄 Separation of Concerns - API-First Architecture**
```
┌─────────────────┐    HTTP/REST API    ┌─────────────────┐
│                 │ ◄─────────────────► │                 │
│   FRONTEND      │                     │    BACKEND      │
│  (JavaScript)   │                     │   (Symfony)     │
│                 │                     │                 │
└─────────────────┘                     └─────────────────┘
```

**✅ 100% SEPARATED Frontend & Backend**
- **Backend**: Symfony 6+ API-only (no server-side rendering for business logic)
- **Frontend**: JavaScript-based with API consumption
- **Communication**: Exclusively through REST API endpoints
- **Authentication**: JWT tokens for stateless communication

### **🛠️ Technology Stack**

#### **Backend (Symfony 6+)**
- **Framework**: Symfony 6+ with API Platform
- **Database**: MySQL with Doctrine ORM
- **Authentication**: JWT (LexikJWTAuthenticationBundle)
- **Real-time**: Mercure for live updates
- **Caching**: Symfony Cache (Redis-compatible)
- **Security**: Role-based access control (RBAC)

#### **Frontend**
- **Admin Interface**: CoreUI 5.x framework
- **JavaScript**: ES6+ with modular architecture
- **API Client**: Custom AdminAPI and managers
- **Styling**: SCSS with JoodKitchen brand colors
- **Real-time**: Mercure client integration

#### **Infrastructure**
- **Web Server**: Apache/Nginx compatible
- **PHP**: 8.1+
- **Database**: MySQL 8.0+
- **Cache**: Redis (optional, falls back to filesystem)

---

## 🍽️ **Business Domain Model**

### **🧑‍🍳 User Types & Roles**

#### **1. System Roles (Symfony Security)**
```php
ROLE_USER          // Base authentication
ROLE_CLIENT        // Customer access  
ROLE_KITCHEN       // Kitchen staff access
ROLE_ADMIN         // Admin interface access
ROLE_SUPER_ADMIN   // Full system access
```

#### **2. Business Profiles**
- **ClientProfile**: Customer data, loyalty points, order history
- **KitchenProfile**: Kitchen staff, specializations, schedules
- **AdminProfile**: Admin users with internal roles and permissions

#### **3. Internal Roles (Business Logic)**
```php
manager_general    // Full admin capabilities
chef_cuisine       // Kitchen and menu management  
responsable_it     // Technical system management
manager_service    // Customer and order management
```

#### **4. Advanced Permissions v2.0 (Granular Control) - ✨ ENHANCED**

**NEW**: Complete overhaul from hardcoded role checks to flexible permission-based system:

```php
// OLD v1.0: Hardcoded role checks
if (in_array('ROLE_SUPER_ADMIN', $user->getRoles())) {
    return true; // Super admin gets everything
}

// NEW v2.0: Permission-based logic
if (in_array('ROLE_SUPER_ADMIN', $targetRoles)) {
    return $this->hasPermission($currentUser, 'edit_super_admin');
}
```

**Key Improvements:**
- **🎛️ Granular Control**: Even SUPER_ADMIN needs specific permissions
- **🔒 Enhanced Security**: Multiple permission requirements for sensitive operations
- **🧪 Testing Interface**: Built-in permission testing at `/admin/users/admins`
- **📊 Transparency**: Detailed explanations of permission decisions

**Permission Categories:**
```php
// User Management (Enhanced)
'manage_admins'       // Create and manage admin users
'edit_admin'          // Edit regular admin users  
'edit_super_admin'    // Edit super admin users (highly restricted)
'delete_admin'        // Delete admin users
'view_user_details'   // View detailed user information

// Advanced Examples:
Super Admin → Super Admin: Requires 'edit_super_admin' permission
Super Admin → Regular Admin: Requires 'edit_admin' OR 'manage_admins'
Regular Admin → Super Admin: Requires 'edit_super_admin' (can be granted!)
Delete Operations: Require MULTIPLE permissions for safety
```

**Testing & Validation:**
- **Permission Testing Panel**: Interactive interface for testing permissions
- **API Endpoint**: `GET /api/admin/check-permissions/{userId}`
- **Logic Explanations**: Detailed reasoning for permission decisions
- **Backward Compatibility**: Legacy JSON permissions still supported

### **🍕 Product & Menu Management**

#### **Dish (Plat) System**
```php
class Plat {
    // Core properties
    private string $nom;                    // Dish name
    private string $description;            // Description
    private string $prix;                   // Price (decimal)
    private string $categorie;              // Category (entree, plat_principal, dessert)
    private int $tempsPreparation;          // Preparation time (minutes)
    private array $allergenes;              // Allergen information
    private bool $disponible;               // Availability status
    private string $image;                  // Image URL/path
}
```

#### **Menu System Architecture**
```php
Menu ──► MenuPlat ──► Plat
  │         │
  │         └── ordre (sequence order for display)
  │
  ├── type: 'normal' | 'menu_du_jour'
  ├── prix: Global menu price
  ├── date: Specific date (for menu_du_jour)
  ├── jourSemaine: Day of week (for menu_du_jour)
  ├── tag: Cuisine type for daily menus
  └── actif: Availability status
```

**Menu Types:**

#### **1. Normal Menus** (`type: 'normal'`)
- **Regular menus** available for ordering anytime
- **Classic combinations** like "Menu Complet Traditionnel"
- **Pricing**: Fixed price for complete menu

#### **2. Menu du Jour** (`type: 'menu_du_jour'`) - **DAILY SPECIALTIES**
**Every day has 3 distinct cuisine menus:**

```php
Daily Menu Structure (per day):
├── MENU MAROCAIN
│   ├── Entrée: Salade Marocaine/Zaalouk/Taktouka/etc.
│   ├── Plat Principal: Tajine Poulet/Couscous/Rfissa/etc.
│   └── Dessert: Orange Cannelle/Lben/Pastilla au Lait/etc.
│
├── MENU ITALIEN  
│   ├── Entrée: Salade Caprese/Bruschetta/Frittata/etc.
│   ├── Plat Principal: Lasagne/Risotto/Spaghetti/etc.
│   └── Dessert: Tiramisu/Gelato/Panna Cotta/etc.
│
└── MENU INTERNATIONAL
    ├── Entrée: Salade Grecque/Fettouch/Mexicaine/etc.
    ├── Plat Principal: Shawarma/Burrito/Burger/etc.
    └── Dessert: Mhalabiah/Cheesecake/Flan/etc.
```

**Implementation Details:**
- **Each day** = 3 separate Menu entities with `type: 'menu_du_jour'`
- **tag field** identifies cuisine: `'marocain'`, `'italien'`, `'international'`
- **date field** specifies the exact day
- **MenuPlat relationships** define the 3-course structure (Entrée → Plat Principal → Dessert)

#### **Dish Categories & Organization**
```php
Dish Categories: [
    // Core menu structure
    'entree'          => 'Entrées',
    'plat_principal'  => 'Plats Principaux', 
    'dessert'         => 'Desserts',
    
    // Extended categories
    'boisson'         => 'Boissons',
    'pizza'           => 'Pizzas', 
    'sandwich'        => 'Sandwichs',
    'salade'          => 'Salades',
    'pasta'           => 'Pâtes',
    'risotto'         => 'Risotto'
]
```

#### **Cuisine-Specific Dish Examples (from Menu du Jour)**

**🇲🇦 MAROCAIN (Moroccan)**
```php
Entrées: ['Salade Marocaine', 'Salade Zaalouk', 'Salade Taktouka', 'Salade de Concombres', 'Salade de Carottes', 'Salade de Courgettes']
Plats: ['Tajine Poulet', 'Tajine Viande', 'Rfissa Poulet', 'Tajine de Poisson', 'Couscous', 'Tajine de Viande Hachée']
Desserts: ['Orange Cannelle', 'Raib', 'Pastilla au Lait', 'Salade de Fruits', 'Lben', 'Besbousa']
```

**🇮🇹 ITALIEN (Italian)**
```php
Entrées: ['Salade Caprese', 'Bruschetta', 'Frittata aux Légumes', 'Arancini', 'Ahubergine à l\'Italienne', 'Salade Italienne']
Plats: ['Lasagne à la Bolognaise', 'Risottto aux Champignons', 'Spaghetti à la Carbonara', 'Penne Poulet', 'Pizza Quatre Fromages', 'Raviolis']
Desserts: ['Tiramisu', 'Gelato', 'Panna Cotta', 'Cannolis', 'Semi Freddo', 'Sorbet au Citron']
```

**🌍 INTERNATIONAL (Global)**
```php
Entrées: ['Salade Grecque', 'Salade Fettouch', 'Salade Mexicaine', 'Salade Caesar', 'Salade Nioise', 'Salade Haricots']
Plats: ['Moussaka', 'Shawarma Poulet', 'Burrito', 'Burger + Frites', 'Steak Viande', 'Poisson Friture']
Desserts: ['Yaourt', 'Mhalabiah', 'Cake aux Trois Lait', 'Cheesecake', 'Flan Caramel', 'Mousse Chocolat']
```

### **📦 Order Management System**

#### **🚀 Enhanced Order Display System (January 2025)**

JoodKitchen now features a comprehensive **OrderDisplayService** that provides consistent, reusable order handling across the entire application.

```php
// NEW OrderDisplayService - Reusable Across Application
use App\Service\OrderDisplayService;

$orderDisplayService->getOrderDetails($commande);     // Complete order with validation
$orderDisplayService->getArticlesList($commande);     // Simplified article list
$orderDisplayService->getOrderSummary($commande);     // Table/list summary
$orderDisplayService->validateOrder($commande);       // Health score & validation
$orderDisplayService->hasDeletedItems($commande);     // Quick deleted items check
```

#### **CRITICAL BUG FIXED: Enhanced CommandeArticle**

**Problem Solved**: Orders containing menus were showing "Article supprimé" (Deleted Item) even when data existed.

```php
// ENHANCED CommandeArticle Methods
$article->getDisplayName();     // ✅ Now checks BOTH plat AND menu
$article->isDeleted();          // ✅ Only deleted if BOTH are null
$article->getItemType();        // ✅ Returns 'plat', 'menu', or 'deleted'
$article->getCurrentItem();     // ✅ Gets actual item entity (plat or menu)
$article->getItemInfo();        // ✅ Comprehensive item data array
```

#### **Order Health Scoring & Validation**
- **Health Score**: 0-100% based on data integrity
- **Validation Alerts**: Proactive issue detection
- **Visual Indicators**: Color-coded health status (Green/Yellow/Red)

#### **Order Workflow States**
```php
Order Status Flow:
en_attente ──► en_preparation ──► pret ──► en_livraison ──► livre
     │                                                        ▲
     └── annule ◄─────────────────────────────────────────────┘
```

#### **Order Structure**
```php
Commande (Order) {
    ├── User (Customer)
    ├── typeLivraison: 'livraison' | 'a_emporter' | 'sur_place'
    ├── adresseLivraison: Delivery address
    ├── statut: Order status
    ├── total: Final price after reductions
    ├── totalAvantReduction: Price before discounts
    ├── dateCommande: Order timestamp
    ├── commentaire: Special instructions
    │
    ├── CommandeArticles[] (Order Items) - **ENHANCED FLEXIBLE ORDERING**
    │   ├── Plat (Individual dish) - Optional
    │   ├── Menu (Complete menu) - Optional
    │   ├── quantite: Item quantity
    │   ├── prixUnitaire: Unit price at time of order
    │   ├── commentaire: Special instructions per item
    │   ├── nomOriginal: Original item name (for history)
    │   ├── descriptionOriginale: Original description
    │   └── dateSnapshot: When item was captured
    │
    ├── CommandeReductions[] (Applied Discounts)
    │   ├── type: 'pourcentage' | 'montant_fixe'
    │   ├── valeur: Discount amount
    │   └── description: Discount description
    │
    └── Payments[] (Payment Records)
        ├── montant: Payment amount
        ├── methodePaiement: 'carte' | 'especes' | 'cheque' | 'virement' | 'cmi'
        ├── statut: 'en_attente' | 'valide' | 'refuse' | 'rembourse'
        └── referenceTransaction: Payment gateway reference
}
```

#### **Order Item Flexibility**
```php
CommandeArticle supports both (ENHANCED):
1. Individual Dishes: CommandeArticle.plat = Plat entity (menu = null)
2. Complete Menus: CommandeArticle.menu = Menu entity (plat = null)
3. Mixed Orders: Combination of both types in single order

Examples:
- Order individual "Tajine Poulet" → CommandeArticle.plat = Tajine
- Order "Menu du Jour Marocain" → CommandeArticle.menu = Menu entity  
- Mixed order: 2x individual dishes + 1x complete menu → ✅ ALL DISPLAY CORRECTLY
```

### **💳 Payment System**

#### **Supported Payment Methods**
- **carte**: Credit/Debit cards
- **especes**: Cash payments
- **cheque**: Check payments  
- **virement**: Bank transfers
- **cmi**: CMI payment gateway (Morocco)

#### **Payment Status Management**
```php
Payment States:
en_attente ──► valide
     │           ▲
     └── refuse  │
         │       │
         └── rembourse
```

### **💳 Subscription & Selection Management**

#### **Weekly Subscription System (Enhanced v2.0)**

JoodKitchen offers a sophisticated **weekly subscription system** where customers subscribe for 5 meals per week (Monday-Friday) and make individual selections for each day.

```php
Subscription Workflow:
Abonnement (Weekly Subscription) ──► AbonnementSelection (Daily Meal Choices)
     │                                        │
     ├── dateDebut: Start date                ├── dateRepas: Specific meal date
     ├── dateFin: End date                    ├── jourSemaine: Day of week
     ├── nombreRepas: 5 meals/week            ├── typeSelection: menu_du_jour | menu_normal | plat_individuel
     ├── statut: actif | suspendu | expire    ├── cuisineType: marocain | italien | international
     └── tauxReduction: Weekly discount       └── statut: selectionne | confirme | prepare | livre
```

#### **AbonnementSelection System (NEW v2.0)**

The new `AbonnementSelection` entity enables **flexible daily meal choices** within subscriptions:

**Selection Types:**
```php
'menu_du_jour'     // Daily special menu (3 cuisine choices)
'menu_normal'      // Regular menu selection  
'plat_individuel'  // Individual dish selection
```

**Daily Cuisine Options for Menu du Jour:**
```php
'marocain'         // Moroccan cuisine 🇲🇦
'italien'          // Italian cuisine 🇮🇹  
'international'    // International cuisine 🌍
```

**Selection Workflow:**
```php
Selection States:
selectionne ──► confirme ──► prepare ──► livre
```

#### **Advanced Selection Features**

**Weekly Planning System:**
```php
class AbonnementSelection {
    // Core selection data
    private Abonnement $abonnement;         // Parent subscription
    private \DateTime $dateRepas;           // Specific meal date
    private string $jourSemaine;            // lundi|mardi|mercredi|jeudi|vendredi
    
    // Flexible meal selection (one of these)
    private ?Menu $menu;                    // Complete menu selection
    private ?Plat $plat;                    // Individual dish selection
    
    // Selection metadata
    private string $typeSelection;          // Selection type
    private ?string $cuisineType;           // Cuisine preference for menu_du_jour
    private string $prix;                   // Selection price
    private string $statut;                 // Processing status
    private ?string $notes;                 // Customer notes
}
```

**Kitchen Integration:**
- **Cuisine-specific preparation**: Selections grouped by cuisine type
- **Daily preparation lists**: All selections for specific dates
- **Status tracking**: Real-time updates from kitchen to customer

**Analytics & Insights:**
```php
// Repository methods for business intelligence
findIncompleteWeeks()              // Track incomplete subscription weeks
countByCuisineTypeForWeek()       // Cuisine popularity analytics
findForPreparation()              // Kitchen preparation scheduling
```

---

## 🔧 **Technical Implementation Details**

### **🗄️ Database Architecture**

#### **Core Entities & Relationships**
```sql
User (1:1) ──► AdminProfile
     (1:1) ──► ClientProfile  
     (1:1) ──► KitchenProfile
     (1:M) ──► Commandes
     (1:M) ──► Abonnements ──► (1:M) AbonnementSelections ──► (M:1) Menu
     (1:M) ──► Notifications                                    (M:1) Plat

Commande (1:M) ──► CommandeArticles ──► (M:1) Plat
         (1:M) ──► CommandeReductions           Menu
         (1:M) ──► Payments

Menu (1:M) ──► MenuPlats ──► (M:1) Plat

AdminProfile (M:M) ──► Permissions
             (M:M) ──► Roles ──► (M:M) Permissions

Payment (M:1) ──► Commande
        (M:1) ──► Abonnement  // NEW: Support subscription payments
```

#### **Key Database Features**
- **Unique Constraints**: Email (User), Name (Permission, Role)
- **Lifecycle Callbacks**: Auto-timestamps, price calculations
- **JSON Fields**: permissions_avancees, roles_internes, metadonnees
- **Decimal Precision**: 10,2 for all monetary values
- **Soft Deletes**: Available for audit trails

### **🚀 Performance & Caching Strategy**

#### **Multi-layer Caching System**
```php
CacheService TTL Strategy:
├── Dishes (Available): 3600s (1 hour) - Static content
├── Menus (Active): 1800s (30 minutes) - Semi-dynamic
├── Menu du Jour: 1800s (30 minutes) - Daily updates
├── User Orders: 900s (15 minutes) - Dynamic content
└── Notifications: 30s - Real-time requirements
```

#### **Cache Keys Pattern**
```php
'dishes.available'              // All available dishes by category
'menus.active'                  // All active menus with dishes
'menu.day.{date}'              // Menu du jour for specific day (all 3 cuisines)
'menu.day.{date}.{tag}'        // Specific cuisine menu for day (marocain/italien/international)
'user.orders.{userId}'         // User's recent order history
'notifications.unread.{userId}' // User's unread notifications
```

#### **Daily Menu Caching Strategy**
```php
Daily Menu Cache Structure:
├── 'menu.day.2024-12-15' → [All 3 menus for the day]
│   ├── Menu Marocain (tag: 'marocain')
│   ├── Menu Italien (tag: 'italien')  
│   └── Menu International (tag: 'international')
│
├── 'menu.day.2024-12-15.marocain' → [Specific Moroccan menu]
├── 'menu.day.2024-12-15.italien' → [Specific Italian menu]
└── 'menu.day.2024-12-15.international' → [Specific International menu]

TTL: 30 minutes (dynamic daily updates)
```

### **⚡ Real-time Features (Mercure Integration)**

#### **Real-time Channels**
```php
Mercure Topics:
├── "order/user/{userId}"       // Private: User's order updates
├── "order/kitchen"             // Kitchen staff: All order updates  
├── "order/admin"               // Admin: System-wide order monitoring
├── "notification/user/{userId}" // Private: User notifications
└── "kitchen/updates"           // Kitchen: General updates
```

#### **Real-time Events**
- **Order Status Changes**: Instant notifications to customers
- **Kitchen Updates**: Live order queue for kitchen staff
- **Payment Confirmations**: Real-time payment status
- **System Notifications**: Alerts and messages

### **🔐 Security Implementation**

#### **Authentication Flow**
```php
1. User Login ──► JWT Token Generation
2. API Requests ──► JWT Validation
3. Role Checking ──► Permission Verification
4. Resource Access ──► Granted/Denied
```

#### **Permission Resolution Order**
```php
1. Check ROLE_SUPER_ADMIN (bypass all checks)
2. Check normalized permissions (new system)
3. Check legacy JSON permissions (backward compatibility)
4. Check contextual permissions (resource-specific)
```

---

## 🎨 **Frontend Architecture**

### **🖥️ Admin Interface Structure**

#### **CoreUI 5.x Implementation**
- **CDN-only Resources**: Zero 404 errors, reliable delivery
- **Brand Integration**: JoodKitchen colors (#a9b73e, #da3c33, #202d5b)
- **Responsive Design**: Mobile-first approach
- **Theme Support**: Light/Dark/Auto switching

#### **JavaScript Architecture**
```javascript
Frontend Structure:
├── /js/admin/
│   ├── api.js              // AdminAPI class (HTTP client)
│   ├── auth.js             // Authentication handling
│   ├── dashboard.js        // Dashboard functionality
│   ├── utils.js            // Utility functions
│   │
│   ├── /managers/          // Business logic managers
│   │   └── admin-profiles.js // AdminProfileManager
│   │
│   ├── /components/        // Reusable UI components
│   │   └── permission-matrix.js
│   │
│   └── /base/              // Core functionality
│       ├── config.js       // CoreUI configuration
│       ├── color-modes.js  // Theme switching
│       └── main.js         // Initialization
```

#### **API Communication Pattern**
```javascript
class AdminAPI {
    // RESTful endpoint communication
    async request(method, endpoint, data = null) {
        // JWT token handling
        // Error handling
        // Response processing
    }
    
    // Business-specific methods
    async getInternalRoles()
    async getAvailablePermissions()
    async createAdminUser(userData)
    async updateOrderStatus(id, status)
}
```

### **📱 Admin Interface Pages**

#### **Complete Admin Dashboard (12 Pages)**
```php
Route Mapping:
├── /admin/dashboard          → Dashboard overview
├── /admin/orders            → Order management
├── /admin/orders/tracking   → Delivery tracking
├── /admin/orders/kitchen    → Kitchen workflow
├── /admin/menu/dishes       → Dish management
├── /admin/menu/menus        → Menu composition
├── /admin/menu/categories   → Category management
├── /admin/users             → Customer management
├── /admin/users/staff       → Staff management
├── /admin/users/admins      → Admin management
├── /admin/system/logs       → System monitoring
└── /admin/settings          → Configuration
```

#### **Key Features per Page**
- **Real-time Updates**: Live data refresh
- **Interactive Elements**: Drag & drop, modals, forms
- **Data Visualization**: Charts, progress bars, statistics
- **Export Functions**: CSV, PDF, Excel formats
- **Advanced Filtering**: Multi-criteria search and sort

---

## 🔗 **API Endpoints Architecture**

### **🌐 Public API Endpoints**
```php
// Entity Resources (API Platform)
GET    /api/plats             // Browse dishes
GET    /api/menus             // Browse menus  
GET    /api/plats/{id}        // Dish details
GET    /api/menus/{id}        // Menu details

// Authentication
POST   /api/auth/register     // User registration
POST   /api/auth/login        // User login
GET    /api/auth/profile      // Current user profile
```

### **🔒 Protected API Endpoints**

#### **Customer Endpoints (ROLE_CLIENT)**
```php
POST   /api/commandes         // Create order
GET    /api/commandes         // User's orders
PATCH  /api/commandes/{id}    // Update order
GET    /api/notifications     // User notifications

// Subscription Management (NEW v2.0)
POST   /api/abonnements       // Create subscription
GET    /api/abonnements       // User's subscriptions
PATCH  /api/abonnements/{id}  // Update subscription

// Subscription Selections (NEW v2.0)
POST   /api/abonnement_selections        // Create daily meal selection
GET    /api/abonnement_selections        // User's meal selections
PATCH  /api/abonnement_selections/{id}   // Update meal selection
GET    /api/abonnement_selections?abonnement={id}&week={date}  // Week selections

// Menu du Jour specific endpoints
GET    /api/menus?type=menu_du_jour&date=2024-12-15    // Daily menus
GET    /api/menus?type=menu_du_jour&tag=marocain       // Moroccan menus
GET    /api/menus?type=menu_du_jour&tag=italien        // Italian menus
GET    /api/menus?type=menu_du_jour&tag=international  // International menus
```

#### **Kitchen Endpoints (ROLE_KITCHEN)**
```php
PATCH  /api/orders/{id}/status    // Update order status
GET    /api/orders/tracking/subscribe // Mercure subscription
```

#### **Admin Endpoints (ROLE_ADMIN)**
```php
GET    /api/admin/roles/internal      // Internal roles list
GET    /api/admin/permissions         // Available permissions
POST   /api/admin/users              // Create admin user
GET    /api/admin/analytics          // System analytics

// Enhanced Permission System v2.0 (NEW)
GET    /api/admin/check-permissions/{userId}  // Test permission system
GET    /api/admin/users              // List admins with embedded profiles
PUT    /api/admin/update-user/{id}   // Update admin with unified endpoint

// Subscription Analytics (NEW)
GET    /api/admin/subscriptions      // All subscriptions
GET    /api/admin/subscriptions/analytics  // Subscription performance
GET    /api/admin/selections/kitchen       // Kitchen preparation data
```

### **📊 API Response Formats**

#### **Standard API Platform Response**
```json
{
  "@context": "/api/contexts/Plat",
  "@id": "/api/plats",
  "@type": "hydra:Collection",
  "hydra:member": [
    {
      "@id": "/api/plats/1",
      "@type": "Plat",
      "id": 1,
      "nom": "Couscous Royal",
      "description": "Couscous traditionnel avec agneau, poulet et merguez",
      "prix": "18.50",
      "categorie": "plat_principal",
      "disponible": true
    }
  ]
}
```

#### **Custom API Response**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "status": "en_preparation",
    "estimated_delivery": "18:30"
  },
  "message": "Order status updated successfully"
}
```

---

## 📈 **Current Implementation Status**

### **✅ Completed Features (100%)**

#### **Backend Core**
- ✅ Complete entity model with relationships
- ✅ JWT authentication system
- ✅ **Role-based permission system v2.0** (Advanced permissions replacing hardcoded checks) ✨ **NEW**
- ✅ API Platform integration
- ✅ Real-time Mercure integration
- ✅ Comprehensive caching system
- ✅ Order workflow management
- ✅ **Subscription system with daily meal selections** ✨ **NEW**
- ✅ Payment system architecture (orders + subscriptions)
- ✅ Notification system

#### **Admin Interface**
- ✅ Complete CoreUI 5.x integration
- ✅ All 12 admin pages implemented
- ✅ JavaScript API communication layer
- ✅ Modal system (CoreUI-compatible)
- ✅ Form validation and error handling
- ✅ Real-time updates integration
- ✅ JoodKitchen brand integration
- ✅ **Permission testing interface** ✨ **NEW**
- ✅ **Enhanced admin user management with unified CRUD** ✨ **NEW**

#### **API Endpoints**
- ✅ Public endpoints (dishes, menus)
- ✅ Authentication endpoints
- ✅ **Enhanced admin management endpoints** ✨ **NEW**
- ✅ Order management endpoints
- ✅ **Subscription management endpoints** ✨ **NEW**
- ✅ **Subscription selection endpoints** ✨ **NEW**
- ✅ Real-time subscription endpoints

### **⚠️ In Progress Features (85-95%)**

#### **Admin User Management v2.0**
- ✅ UI and modal system complete
- ✅ **Unified API endpoints implemented** ✨ **ENHANCED**
- ✅ **Database constraint handling** (email uniqueness) ✨ **FIXED**
- ✅ **AdminProfile creation** alongside User creation ✨ **FIXED**
- ✅ **Advanced error handling** and user feedback ✨ **ENHANCED**
- ⚠️ **Permission assignment interface** (UI for assigning permissions)

#### **Subscription System v2.0**
- ✅ **Core subscription entities and logic** ✨ **NEW**
- ✅ **Daily meal selection system** ✨ **NEW**
- ✅ **Kitchen integration endpoints** ✨ **NEW**
- ⚠️ **Frontend interface for subscription management**
- ⚠️ **Customer selection interface for daily meals**

### **🔜 Planned Features (0-25%)**

#### **Customer Frontend**
- 🔜 Customer registration/login interface
- 🔜 **Daily Menu Browsing**: Interactive display of 3 daily cuisine choices
- 🔜 **Subscription Management Interface**: Subscribe to weekly meals with daily selections ✨ **PRIORITY**
- 🔜 **Weekly Meal Planning**: Interactive calendar for selecting daily meals ✨ **PRIORITY**
- 🔜 **Mixed Ordering System**: Individual dishes + complete menus + subscriptions in one order
- 🔜 Order tracking interface
- 🔜 Customer profile management

#### **Kitchen Interface**
- 🔜 Kitchen staff dashboard with cuisine-specific workflows
- 🔜 **Multi-cuisine Preparation Management**: Moroccan, Italian, International stations
- 🔜 **Subscription Preparation Board**: Daily view of all subscription selections ✨ **NEW**
- 🔜 Real-time kitchen notifications by cuisine type

#### **Subscription Analytics & Management**
- 🔜 **Subscription Performance Dashboard**: Weekly/monthly analytics ✨ **NEW**
- 🔜 **Cuisine Popularity Tracking**: Which cuisines are most popular by day ✨ **NEW**
- 🔜 **Incomplete Subscription Alerts**: Notify customers of incomplete weekly selections ✨ **NEW**
- 🔜 **Automated Reminders**: Email/SMS reminders for weekly meal selections ✨ **NEW**

#### **Mobile Applications**
- 🔜 Customer mobile app with daily menu highlights and subscription management
- 🔜 Kitchen staff mobile interface (cuisine-specific with subscription orders)
- 🔜 Delivery tracking mobile app

#### **Advanced Menu Management**
- 🔜 **Weekly Menu Planning**: Pre-plan daily menus for each cuisine
- 🔜 **Seasonal Menu Variations**: Adjust dishes based on season/availability
- 🔜 **Menu Analytics**: Track popularity by cuisine type and day
- 🔜 **Smart Menu Suggestions**: AI-powered daily menu recommendations
- 🔜 **Subscription Menu Optimization**: Adjust menus based on subscription demand ✨ **NEW**

#### **Advanced Features**
- 🔜 Loyalty program implementation
- 🔜 Promotion and discount system (for subscriptions and individual orders)
- 🔜 **Cuisine-specific Analytics**: Track performance by cuisine type
- 🔜 **Subscription Billing System**: Automated weekly/monthly billing ✨ **NEW**
- 🔜 Integration with external delivery services

---

## 🛠️ **Development Environment**

### **📋 Requirements**
```bash
# Backend Requirements
PHP >= 8.1
Composer
MySQL >= 8.0
Symfony CLI (optional)

# Frontend Requirements  
Modern browser with ES6+ support
Node.js (for development tools, optional)

# Optional
Redis (for enhanced caching)
Docker (for containerized development)
```

### **🚀 Quick Setup**
```bash
# Backend Setup
composer install
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
php bin/console doctrine:fixtures:load

# Start Development Server
symfony server:start

# Or with PHP built-in server
php -S 127.0.0.1:8000 -t public/
```

### **🌍 Environment Configuration**
```bash
# Key Environment Variables
DATABASE_URL="mysql://user:pass@localhost:3306/joodkitchen"
JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
MERCURE_URL=https://localhost/.well-known/mercure
MERCURE_PUBLIC_URL=https://localhost/.well-known/mercure
```

---

## 🎯 **Business Value & Goals**

### **🏆 Primary Objectives**
1. **Streamline Restaurant Operations**: Reduce manual work, improve efficiency
2. **Enhance Customer Experience**: Real-time tracking, easy ordering
3. **Optimize Kitchen Workflow**: Live order management, preparation tracking
4. **Data-Driven Decisions**: Analytics, reporting, performance metrics
5. **Scalable Growth**: Multi-location ready, franchise-friendly

### **📊 Key Performance Indicators (KPIs)**
- **Order Processing Time**: Target <5 minutes from order to kitchen
- **Customer Satisfaction**: Real-time feedback and tracking
- **Kitchen Efficiency**: Preparation time optimization
- **System Performance**: <100ms API response times
- **Admin Productivity**: Streamlined management workflows

### **🎨 Brand Identity**
- **Primary Color**: #a9b73e (JoodKitchen Green)
- **Secondary Color**: #da3c33 (Accent Red)  
- **Typography**: Montserrat (400, 600, 700, 800)
- **Cuisine Focus**: Multi-cultural (Moroccan, Italian, International)
- **Unique Selling Point**: Daily rotating menu system with 3 cuisine choices
- **Values**: Authenticity, Quality, Innovation, Service, Cultural Diversity

---

## 📝 **Development Notes & Decisions**

### **🏗️ Architectural Decisions**

#### **API-First Approach**
- **Rationale**: Maximum flexibility for future frontend implementations
- **Benefit**: Mobile apps, third-party integrations, microservices ready
- **Implementation**: Complete separation of concerns

#### **Three-Layer Permission System**
- **System Roles**: Symfony security integration
- **Internal Roles**: Business logic grouping
- **Advanced Permissions**: Granular feature control
- **Benefit**: Flexible, scalable, backward-compatible

#### **Real-time Integration**
- **Technology**: Mercure (modern WebSocket alternative)
- **Use Cases**: Order tracking, kitchen updates, notifications
- **Benefit**: Enhanced user experience, operational efficiency

#### **Caching Strategy**
- **Multi-TTL Approach**: Different cache lifetimes for different data types
- **Performance Impact**: Sub-100ms response times for cached data
- **Scalability**: Redis-ready for high-traffic scenarios

### **🔧 Recent Technical Fixes & Enhancements (December 2024)**

#### **Permission System v2.0 Revolution**
- **Advanced Permission Logic**: Completely replaced hardcoded role checks with flexible permission system
- **Security Enhancement**: Even SUPER_ADMIN users now require specific permissions for actions
- **Testing Interface**: Built-in permission testing panel at `/admin/users/admins` with detailed explanations
- **API Endpoint**: New `/api/admin/check-permissions/{userId}` for permission validation
- **Backward Compatibility**: Legacy JSON permissions still supported during transition

#### **Admin User Management v2.0**
- **Modal System**: Fixed Bootstrap→CoreUI compatibility issues
- **Unified CRUD**: Single API endpoints for admin creation with User+AdminProfile in transactions
- **Database Constraints**: Resolved email uniqueness violations with pre-creation validation
- **Enhanced Error Handling**: Structured error responses with user-friendly French messages
- **Permission Integration**: Admin creation now properly handles role and permission assignment

#### **Subscription System Architecture**
- **AbonnementSelection Entity**: New entity for daily meal selections within subscriptions
- **Database Migration**: Version20250617095228.php adds subscription selection tables
- **Flexible Selection Types**: Support for daily specials, normal menus, and individual dishes
- **Cuisine Integration**: Direct integration with 3-cuisine daily menu system
- **Kitchen Workflow**: Preparation endpoints grouped by cuisine and date

#### **API Enhancements**
- **Subscription Endpoints**: Complete REST API for subscription management
- **Selection Management**: CRUD operations for daily meal selections
- **Enhanced Responses**: Structured error handling with contextual information
- **Security Configuration**: Updated for admin API access with proper permission checks

---

## 📚 **Documentation References**

### **📖 Related Documentation Files**
- `COMPLETE_ADMIN_TEMPLATES_SUMMARY.md` - Admin interface overview
- `COREUI_STRUCTURE_FIXES.md` - Frontend framework implementation
- `PERMISSION_SYSTEM_MIGRATION.md` - Permission system architecture
- `api_testing_guide.md` - API endpoint testing and examples
- `ADMIN_IMPLEMENTATION_SUMMARY.md` - Current implementation status
- `RECENT_ADMIN_FIXES.md` - Latest fixes and improvements

### **🔗 External Resources**
- [Symfony Documentation](https://symfony.com/doc)
- [API Platform Documentation](https://api-platform.com/docs)
- [CoreUI Documentation](https://coreui.io/docs)
- [Mercure Documentation](https://mercure.rocks/docs)
- [JWT Authentication Bundle](https://github.com/lexik/LexikJWTAuthenticationBundle)

---

## 🚀 **Future Roadmap**

### **🎯 Short-term Goals (Next 1-3 months)**
1. **Complete Admin User Management**: Fix database constraints, enhance error handling
2. **Customer Frontend Development**: Registration, ordering, tracking interfaces
3. **Kitchen Staff Interface**: Workflow management, real-time updates
4. **Mobile Responsiveness**: Optimize for mobile devices

### **🎯 Medium-term Goals (3-6 months)**
1. **Mobile Applications**: Native or PWA customer and staff apps
2. **Advanced Analytics**: Business intelligence, reporting dashboards
3. **Loyalty Program**: Points system, rewards, promotions
4. **Integration APIs**: Third-party delivery services, payment gateways

### **🎯 Long-term Goals (6+ months)**
1. **Multi-location Support**: Franchise management, chain operations
2. **AI Integration**: Demand forecasting, recommendation engine
3. **IoT Integration**: Kitchen equipment monitoring, smart inventory
4. **International Expansion**: Multi-language, multi-currency support

---

**📅 Last Updated**: December 17, 2024  
**👨‍💻 Maintainer**: Development Team  
**🔄 Version**: 2.0.0  
**📊 Project Status**: Advanced Development (Admin System v2.0 Complete, Subscription System v2.0 Backend Complete, Customer Interface In Progress) 

## 🚀 Recent Major Developments

### Enhanced Order Display System (January 2025) 🏆
- **🐛 CRITICAL BUG FIXED**: "Article supprimé" issue - Orders containing menus now display correctly
- **OrderDisplayService**: New comprehensive service for consistent order handling across entire application
- **Enhanced CommandeArticle**: Now properly handles both `plat` AND `menu` relationships
- **Order Health Scoring**: Orders receive health scores (0-100%) based on data integrity
- **Validation System**: Proactive detection of order issues with visual alerts
- **Reusable Architecture**: Service usable in admin, kitchen, POS, mobile apps, and all modules
- **Enhanced Frontend**: Order details modal with validation alerts and comprehensive information

### Order Management & Dashboard Enhancements (July 2025)
- **Order Status Centralization**: All hardcoded order statuses centralized to `OrderStatus` enum for consistency
- **Dashboard Date Filtering**: Complete date range filtering system for historical order analysis
- **Real-time Statistics**: Enhanced dashboard with business insights and flexible time period views
- **API Improvements**: Robust stats API with proper caching and error handling

### Advanced POS System (June 2025) 