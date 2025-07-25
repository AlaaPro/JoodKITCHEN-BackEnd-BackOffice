# 🍽️ JoodKitchen Menu & Order System Guide

## 📋 **Overview**

This guide explains JoodKitchen's sophisticated **menu system** and **client order management** - the core business features that make JoodKitchen unique in the food delivery market.

---

## 🍽️ **Menu System Architecture**

### **🎯 Core Concept: Daily Menu System**

JoodKitchen operates on an innovative **daily menu system** where **every day offers 3 distinct cuisine menus**:

```
📅 Daily Menu Structure (per day):
├── 🇲🇦 MENU MAROCAIN
│   ├── Entrée: Salade Marocaine/Zaalouk/Taktouka/etc.
│   ├── Plat Principal: Tajine Poulet/Couscous/Rfissa/etc.
│   └── Dessert: Orange Cannelle/Lben/Pastilla au Lait/etc.
│
├── 🇮🇹 MENU ITALIEN  
│   ├── Entrée: Salade Caprese/Bruschetta/Frittata/etc.
│   ├── Plat Principal: Lasagne/Risotto/Spaghetti/etc.
│   └── Dessert: Tiramisu/Gelato/Panna Cotta/etc.
│
└── 🌍 MENU INTERNATIONAL
    ├── Entrée: Salade Grecque/Fettouch/Mexicaine/etc.
    ├── Plat Principal: Shawarma/Burrito/Burger/etc.
    └── Dessert: Mhalabiah/Cheesecake/Flan/etc.
```

### **🏗️ Menu Types**

#### **1. Menu du Jour** (`type: 'menu_du_jour'`) - **DAILY SPECIALTIES**
- **3 different cuisine menus** every day
- **Rotating dishes** based on cuisine type
- **Specific date assignment** for each menu
- **Tagged by cuisine**: `marocain`, `italien`, `international`
- **3-course structure**: Entrée → Plat Principal → Dessert

#### **2. Normal Menus** (`type: 'normal'`) - **REGULAR MENUS**
- **Classic combinations** available anytime
- **Fixed pricing** for complete menus
- **Examples**: "Menu Complet Traditionnel", "Menu Végétarien"

### **🍽️ Dish Categories & Organization**

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

### **🌍 Cuisine-Specific Dish Examples**

#### **🇲🇦 MAROCAIN (Moroccan)**
```php
Entrées: ['Salade Marocaine', 'Salade Zaalouk', 'Salade Taktouka', 'Salade de Concombres', 'Salade de Carottes', 'Salade de Courgettes']
Plats: ['Tajine Poulet', 'Tajine Viande', 'Rfissa Poulet', 'Tajine de Poisson', 'Couscous', 'Tajine de Viande Hachée']
Desserts: ['Orange Cannelle', 'Raib', 'Pastilla au Lait', 'Salade de Fruits', 'Lben', 'Besbousa']
```

#### **🇮🇹 ITALIEN (Italian)**
```php
Entrées: ['Salade Caprese', 'Bruschetta', 'Frittata aux Légumes', 'Arancini', 'Ahubergine à l\'Italienne', 'Salade Italienne']
Plats: ['Lasagne à la Bolognaise', 'Risottto aux Champignons', 'Spaghetti à la Carbonara', 'Penne Poulet', 'Pizza Quatre Fromages', 'Raviolis']
Desserts: ['Tiramisu', 'Gelato', 'Panna Cotta', 'Cannolis', 'Semi Freddo', 'Sorbet au Citron']
```

#### **🌍 INTERNATIONAL (Global)**
```php
Entrées: ['Salade Grecque', 'Salade Fettouch', 'Salade Mexicaine', 'Salade Caesar', 'Salade Nioise', 'Salade Haricots']
Plats: ['Moussaka', 'Shawarma Poulet', 'Burrito', 'Burger + Frites', 'Steak Viande', 'Poisson Friture']
Desserts: ['Yaourt', 'Mhalabiah', 'Cake aux Trois Lait', 'Cheesecake', 'Flan Caramel', 'Mousse Chocolat']
```

---

## 🥤 **Individual Beverage Ordering**

### **✅ How Beverage Orders Work**

JoodKitchen fully supports **individual beverage orders** (juice, water, soft drinks, etc.) through the existing dish system:

#### **Beverage as Individual Dish**
```php
// Beverages are created as Plat entities with category 'boisson'
Plat {
    nom: "Jus d'Orange Naturel",
    description: "Jus d'orange frais pressé",
    prix: "4.50",
    categorie: "boisson",  // ✅ Beverage category
    tempsPreparation: 2,   // Quick preparation
    disponible: true
}
```

#### **Individual Beverage Ordering**
```php
// Customer orders individual beverage
CommandeArticle {
    plat: Plat (beverage),     // ✅ Individual beverage
    menu: null,                // Not part of a menu
    quantite: 2,              // Can order multiple
    prixUnitaire: "4.50",
    commentaire: "Sans glace"  // Special instructions
}
```

#### **Beverage Order Examples**
```php
// Example 1: Just water
CommandeArticle {
    plat: { nom: "Eau Minérale", categorie: "boisson", prix: "2.00" },
    quantite: 1,
    commentaire: "Température ambiante"
}

// Example 2: Fresh juice
CommandeArticle {
    plat: { nom: "Jus d'Orange Frais", categorie: "boisson", prix: "4.50" },
    quantite: 2,
    commentaire: "Très froid, avec glace"
}

// Example 3: Hot beverage
CommandeArticle {
    plat: { nom: "Thé à la Menthe", categorie: "boisson", prix: "3.00" },
    quantite: 1,
    commentaire: "Très chaud"
}
```

### **📊 Supported Beverage Types**

```php
Beverage Categories Available:
├── 🥤 Soft Drinks (Coca-Cola, Sprite, Fanta)
├── 🧃 Fresh Juices (Orange, Apple, Carrot)
├── ☕ Hot Beverages (Coffee, Tea, Hot Chocolate)
├── 💧 Water (Mineral, Sparkling)
├── 🥛 Dairy Drinks (Milk, Yogurt Drinks)
├── 🍹 Traditional Drinks (Mint Tea, Ayran)
└── 🥤 Energy Drinks (Red Bull, Monster)
```

### **⚡ Beverage-Only Order Benefits**

- **Quick Preparation**: 1-3 minutes preparation time
- **Express Delivery**: Priority handling for beverage-only orders
- **Customization**: Temperature, ice, size options
- **Same Workflow**: Uses existing order management system

---

## 📦 **Client Order Management System**

### **🎯 Flexible Ordering System**

JoodKitchen allows customers to order in **multiple ways**:

1. **Individual Dishes** - Order specific dishes (`CommandeArticle.plat`)
2. **Individual Beverages** - Order juice, water, soft drinks (`CommandeArticle.plat` with `categorie: 'boisson'`)
3. **Complete Menus** - Order full daily menus (`CommandeArticle.menu`)
4. **Weekly Subscriptions** - Subscribe for 5 meals/week with daily selections
5. **Mixed Orders** - Combine all types in one order

### **🏗️ Order Structure**

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
    ├── CommandeArticles[] (Order Items) - **FLEXIBLE ORDERING**
    │   ├── Plat (Individual dish/beverage) - Optional
    │   ├── Menu (Complete menu) - Optional
    │   ├── quantite: Item quantity
    │   ├── prixUnitaire: Unit price at time of order
    │   └── commentaire: Special instructions per item
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

### **🔄 Order Workflow States**

```php
Order Status Flow:
en_attente ──► en_preparation ──► pret ──► en_livraison ──► livre
     │                                                        ▲
     └── annule ◄─────────────────────────────────────────────┘
```

**Status Descriptions:**
- **`en_attente`** - Order received, waiting for confirmation
- **`en_preparation`** - Kitchen is preparing the order
- **`pret`** - Order is ready for pickup/delivery
- **`en_livraison`** - Order is out for delivery
- **`livre`** - Order delivered successfully
- **`annule`** - Order cancelled (can happen at any stage)

### **💳 Payment System**

#### **Supported Payment Methods**
- **`carte`** - Credit/Debit cards
- **`especes`** - Cash payments
- **`cheque`** - Check payments  
- **`virement`** - Bank transfers
- **`cmi`** - CMI payment gateway (Morocco)

#### **Payment Status Management**
```php
Payment States:
en_attente ──► valide
     │           ▲
     └── refuse  │
         │       │
         └── rembourse
```

## Order Status Management

### Status Centralization (Updated July 2025)
All order statuses are now centralized using the `OrderStatus` enum for consistency:

```php
enum OrderStatus: string
{
    case PENDING = 'en_attente';      // Order created, not paid
    case CONFIRMED = 'confirme';      // Order paid/confirmed
    case PREPARING = 'en_preparation'; // Kitchen is preparing
    case READY = 'pret';              // Ready for pickup/delivery
    case DELIVERING = 'en_livraison'; // Out for delivery
    case DELIVERED = 'livre';         // Successfully delivered
    case CANCELLED = 'annule';        // Order cancelled
}
```

**Benefits:**
- ✅ Single source of truth for all order statuses
- ✅ Prevents typos and inconsistencies
- ✅ Easy to maintain and update
- ✅ Type safety in PHP 8.1+

---

## 🚀 **Enhanced Order Display System (January 2025)**

### **OrderDisplayService - Reusable Order Management**

JoodKitchen now features a comprehensive `OrderDisplayService` that provides consistent order handling across the entire application.

#### **Key Service Methods**
```php
use App\Service\OrderDisplayService;

// Complete order details with validation
$orderDetails = $orderDisplayService->getOrderDetails($commande);

// Quick article list for displays
$articles = $orderDisplayService->getArticlesList($commande);

// Summary for tables and lists
$summary = $orderDisplayService->getOrderSummary($commande);

// Order health and validation
$validation = $orderDisplayService->validateOrder($commande);

// Quick checks
$hasDeletedItems = $orderDisplayService->hasDeletedItems($commande);
$deletedCount = $orderDisplayService->getDeletedItemsCount($commande);
```

#### **Enhanced CommandeArticle Methods**
The `CommandeArticle` entity now properly handles both plat and menu relationships:

```php
// NEW ENHANCED METHODS
$article->getDisplayName();     // Handles both plat AND menu names
$article->isDeleted();          // Only deleted if BOTH plat and menu are null
$article->getItemType();        // Returns 'plat', 'menu', or 'deleted'
$article->getCurrentItem();     // Gets the actual item entity
$article->getItemInfo();        // Comprehensive item information array
```

#### **CRITICAL BUG FIXED: "Article supprimé" Issue**
- **Problem**: Orders containing menus showed items as "Article supprimé" (Deleted Item)
- **Root Cause**: System only checked `plat` relationships, ignored `menu` relationships
- **Solution**: Enhanced logic to check BOTH relationships properly
- **Result**: ✅ All order types now display correctly

#### **Order Health Scoring**
Orders now receive health scores based on data integrity:
- **80%+ (Green)**: All items have proper data, no issues
- **60-79% (Yellow)**: Some items missing optional data or warnings
- **<60% (Red)**: Critical issues like deleted items or missing data

### Order Status Flow

---

## 💳 **Weekly Subscription System (v2.0)**

### **🎯 Subscription Concept**

JoodKitchen offers a sophisticated **weekly subscription system** where customers subscribe for **5 meals per week** (Monday-Friday) and make individual selections for each day.

### **🏗️ Subscription Architecture**

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

### **🎨 Selection Types**

#### **1. Menu du Jour** (`typeSelection: 'menu_du_jour'`)
- **Daily special menu** with 3 cuisine choices
- **Cuisine selection**: `marocain`, `italien`, `international`
- **3-course meal**: Entrée + Plat Principal + Dessert

#### **2. Normal Menu** (`typeSelection: 'menu_normal'`)
- **Regular menu selection** from available menus
- **Fixed menu combinations**

#### **3. Individual Dish** (`typeSelection: 'plat_individuel'`)
- **Single dish selection** from available dishes
- **Custom quantity** and special instructions

### **📅 Selection Workflow**

```php
Selection States:
selectionne ──► confirme ──► prepare ──► livre
```

**State Descriptions:**
- **`selectionne`** - Customer has selected their meal for the day
- **`confirme`** - Kitchen has confirmed the selection
- **`prepare`** - Kitchen is preparing the meal
- **`livre`** - Meal has been delivered

### **💰 Subscription Benefits**

- **Weekly discount rates** applied to total order
- **Payment options**: Moroccan CMI credit card or cash on first delivery
- **Flexible daily choices** within the subscription
- **Kitchen preparation optimization** through advance planning

---

## 🔄 **Real-time Features**

### **⚡ Live Updates**

JoodKitchen provides **real-time updates** through Mercure integration:

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

### **💾 Caching Strategy**

```php
CacheService TTL Strategy:
├── Dishes (Available): 3600s (1 hour) - Static content
├── Menus (Active): 1800s (30 minutes) - Semi-dynamic
├── Menu du Jour: 1800s (30 minutes) - Daily updates
├── User Orders: 900s (15 minutes) - Dynamic content
└── Notifications: 30s - Real-time requirements
```

#### **Daily Menu Caching**
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

---

## 🌐 **API Endpoints**

### **🍽️ Menu Endpoints**

#### **Public Menu Access**
```php
GET /api/plats                    // Browse all dishes
GET /api/menus                    // Browse all menus
GET /api/plats/{id}              // Dish details
GET /api/menus/{id}              // Menu details

// Daily Menu specific endpoints
GET /api/menus?type=menu_du_jour&date=2024-12-15    // Daily menus
GET /api/menus?type=menu_du_jour&tag=marocain       // Moroccan menus
GET /api/menus?type=menu_du_jour&tag=italien        // Italian menus
GET /api/menus?type=menu_du_jour&tag=international  // International menus
```

### **🥤 Beverage Endpoints**

#### **Beverage-Specific Access**
```php
GET /api/plats?categorie=boisson                    // All beverages
GET /api/plats?categorie=boisson&type=jus_frais     // Fresh juices only
GET /api/plats?categorie=boisson&type=soft_drinks   // Soft drinks only
GET /api/plats?categorie=boisson&type=hot_beverages // Hot beverages only
```

### **📦 Order Endpoints**

#### **Customer Order Management**
```php
POST /api/commandes               // Create order
GET /api/commandes                // User's orders
PATCH /api/commandes/{id}         // Update order
GET /api/notifications            // User notifications
```

### **💳 Subscription Endpoints**

#### **Subscription Management**
```php
POST /api/abonnements             // Create subscription
GET /api/abonnements              // User's subscriptions
PATCH /api/abonnements/{id}       // Update subscription

// Subscription Selections
POST /api/abonnement_selections           // Create daily meal selection
GET /api/abonnement_selections            // User's meal selections
PATCH /api/abonnement_selections/{id}     // Update meal selection
GET /api/abonnement_selections?abonnement={id}&week={date}  // Week selections
```

### **🔒 Authentication**
All customer endpoints require `ROLE_CLIENT` and JWT token authentication.

---

## 📊 **Example API Responses**

### **Daily Menu Response**
```json
{
  "@context": "/api/contexts/Menu",
  "@id": "/api/menus",
  "@type": "hydra:Collection",
  "hydra:member": [
    {
      "@id": "/api/menus/1",
      "@type": "Menu",
      "id": 1,
      "nom": "Menu du Jour Marocain",
      "type": "menu_du_jour",
      "tag": "marocain",
      "date": "2024-12-15",
      "prix": "25.00",
      "actif": true,
      "menuPlats": [
        {
          "ordre": 1,
          "plat": {
            "nom": "Salade Marocaine",
            "categorie": "entree"
          }
        },
        {
          "ordre": 2,
          "plat": {
            "nom": "Tajine Poulet",
            "categorie": "plat_principal"
          }
        },
        {
          "ordre": 3,
          "plat": {
            "nom": "Orange Cannelle",
            "categorie": "dessert"
          }
        }
      ]
    }
  ]
}
```

### **Beverage Order Response**
```json
{
  "success": true,
  "data": {
    "id": 124,
    "statut": "en_attente",
    "total": "9.00",
    "totalAvantReduction": "9.00",
    "dateCommande": "2024-12-15T14:30:00+00:00",
    "commandeArticles": [
      {
        "id": 1,
        "quantite": 2,
        "prixUnitaire": "4.50",
        "plat": {
          "nom": "Jus d'Orange Naturel",
          "categorie": "boisson",
          "tempsPreparation": 2
        },
        "commentaire": "Très froid, avec glace"
      }
    ]
  },
  "message": "Beverage order created successfully"
}
```

### **Order Creation Response**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "statut": "en_attente",
    "total": "45.50",
    "totalAvantReduction": "50.00",
    "dateCommande": "2024-12-15T12:30:00+00:00",
    "commandeArticles": [
      {
        "id": 1,
        "quantite": 2,
        "prixUnitaire": "18.50",
        "plat": {
          "nom": "Tajine Poulet",
          "categorie": "plat_principal"
        }
      },
      {
        "id": 2,
        "quantite": 1,
        "prixUnitaire": "25.00",
        "menu": {
          "nom": "Menu du Jour Italien",
          "type": "menu_du_jour"
        }
      }
    ]
  },
  "message": "Order created successfully"
}
```

---

## 🎯 **Business Value & Unique Features**

### **🏆 What Makes JoodKitchen Unique**

1. **Multi-Cuisine Daily Rotation**: 3 distinct cuisine menus every day
2. **Flexible Ordering**: Individual dishes, beverages, complete menus, or subscriptions
3. **Weekly Subscription System**: 5 meals/week with daily meal selections
4. **Real-time Kitchen Integration**: Live order tracking and kitchen workflow
5. **Moroccan Market Focus**: CMI payment integration and local cuisine emphasis
6. **Beverage Flexibility**: Individual beverage ordering with customization options

### **📈 Key Benefits**

#### **For Customers:**
- **Daily variety** with 3 cuisine choices
- **Flexible ordering** options (food + beverages)
- **Quick beverage orders** with customization
- **Subscription discounts** for regular customers
- **Real-time tracking** of orders
- **Multiple payment methods** including local options

#### **For Kitchen:**
- **Advance planning** through subscription selections
- **Cuisine-specific preparation** workflows
- **Quick beverage preparation** (1-3 minutes)
- **Real-time order management**
- **Efficient resource allocation**

#### **For Business:**
- **Predictable demand** through subscriptions
- **Reduced waste** through advance planning
- **Customer loyalty** through subscription model
- **Operational efficiency** through real-time systems
- **Increased revenue** through beverage sales

---

## 🚀 **Current Status & Next Steps**

### **✅ Backend Complete (100%)**
- ✅ Complete menu and order management system
- ✅ Individual beverage ordering support
- ✅ Subscription system with daily selections
- ✅ Payment processing
- ✅ Real-time updates
- ✅ API endpoints for all operations

### **⚠️ Missing: Customer Frontend (0%)**
The **highest priority missing component** is the customer interface:

#### **🔜 Planned Customer Interface Features**
1. **Daily Menu Browsing** - Interactive display of 3 daily cuisine choices
2. **Beverage Browsing** - Dedicated beverage menu with customization options
3. **Subscription Management** - Weekly meal planning with daily selections
4. **Mixed Ordering System** - Individual dishes + beverages + complete menus + subscriptions
5. **Real-time Order Tracking** - Live status updates and delivery tracking

#### **🎨 Design Requirements**
- **JoodKitchen Brand Colors**: #a9b73e (Green), #da3c33 (Red), #202d5b (Blue)
- **Typography**: Montserrat family
- **Mobile-first** responsive design
- **Real-time updates** integration
- **Beverage customization** interface

---

## 📚 **Related Documentation**

- **`JOODKITCHEN_PROJECT_OVERVIEW.md`** - Complete project overview
- **`BEVERAGE_ORDERING_GUIDE.md`** - Detailed beverage ordering management
- **`CUSTOMER_FRONTEND_ROADMAP.md`** - Customer interface development plan
- **`CATEGORIES_MANAGEMENT_GUIDE.md`** - Menu category management
- **`api_testing_guide.md`** - API endpoint testing and examples

---

**📅 Last Updated**: Juin 23, 2025  
**👨‍💻 Maintainer**: Development Team  
**🔄 Version**: 2.0.0  
**📊 Status**: Backend Complete, Customer Frontend In Development
