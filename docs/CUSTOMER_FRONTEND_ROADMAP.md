# 🛍️ Customer Frontend Development Roadmap

## 📋 **Overview**

The JoodKitchen customer frontend is the **highest priority missing component**. While the backend infrastructure is excellent with subscription system v2.0 and daily menu management, customers currently have no interface to interact with the platform.

## 🎯 **Core Features to Implement**

### **1. Customer Authentication & Registration System**

**Pages needed:**
- `public/customer/register.html` - Customer registration
- `public/customer/login.html` - Customer login  
- `public/customer/profile.html` - Profile management
- `public/customer/dashboard.html` - Customer dashboard

**Backend Integration:**
- Use existing `/api/auth/register` and `/api/auth/login` endpoints
- Integrate with existing `ClientProfile` entity
- JWT token management for customer sessions

### **2. Daily Menu Browsing System** ✨ **FLAGSHIP FEATURE**

**Key Components:**
```html
<!-- Daily Menu Display -->
<div class="daily-menus">
    <div class="cuisine-selector">
        <button class="cuisine-btn active" data-cuisine="marocain">
            🇲🇦 Marocain
        </button>
        <button class="cuisine-btn" data-cuisine="italien">
            🇮🇹 Italien  
        </button>
        <button class="cuisine-btn" data-cuisine="international">
            🌍 International
        </button>
    </div>
    
    <div class="menu-display">
        <!-- 3-course meal display for selected cuisine -->
        <div class="course-section">
            <h3>Entrée</h3>
            <div class="dish-card"><!-- Dish details --></div>
        </div>
        <div class="course-section">
            <h3>Plat Principal</h3>
            <div class="dish-card"><!-- Dish details --></div>
        </div>
        <div class="course-section">
            <h3>Dessert</h3>
            <div class="dish-card"><!-- Dish details --></div>
        </div>
    </div>
</div>
```

**API Integration:**
- `GET /api/menus?type=menu_du_jour&date={date}&tag={cuisine}`
- Real-time updates with Mercure for menu changes
- Caching integration with existing `CacheService`

### **3. Weekly Subscription Management Interface** ✨ **PRIORITY FEATURE**

**Subscription Workflow:**
```javascript
class SubscriptionManager {
    async createWeeklySubscription(weekStart) {
        // Create subscription for 5 days (Monday-Friday)
        const response = await CustomerAPI.createSubscription({
            type: 'hebdo',
            dateDebut: weekStart,
            repasParJour: 1
        });
        
        // Guide user through daily meal selections
        this.showWeeklyPlanningInterface(response.subscription_id);
    }
    
    showWeeklyPlanningInterface(subscriptionId) {
        // Interactive calendar for daily meal selection
        // Integration with AbonnementSelection entity
        // Real-time price calculation with discounts
    }
}
```

**UI Components:**
- Weekly calendar view for meal planning
- Daily meal selection modals (3 cuisine choices)
- Price calculator with subscription discounts
- Payment integration (CMI gateway support)

### **4. Mixed Ordering System**

**Allow customers to order:**
- Individual dishes (`CommandeArticle.plat`)
- Complete menus (`CommandeArticle.menu`) 
- Subscription meals (`AbonnementSelection`)
- Combined orders (mix of all types)

**Shopping Cart Component:**
```javascript
class ShoppingCart {
    addDish(platId, quantity) { /* Individual dish */ }
    addMenu(menuId, quantity) { /* Complete menu */ }
    addSubscription(subscriptionData) { /* Weekly subscription */ }
    
    calculateTotal() {
        // Apply subscription discounts
        // Handle mixed pricing
        // Show breakdown by type
    }
}
```

### **5. Real-time Order Tracking**

**Order Status Flow:**
```
en_attente → en_preparation → pret → en_livraison → livre
```

**Real-time Updates:**
- Mercure integration for live status updates
- Estimated delivery times
- Delivery tracking (when integrated with delivery service)
- Kitchen preparation progress

## 🛠️ **Technical Implementation Plan**

### **Phase 1: Foundation (Week 1-2)**
- Customer authentication pages
- Basic profile management
- API client for customer endpoints
- JWT token handling

### **Phase 2: Menu Browsing (Week 3-4)**
- Daily menu display system
- 3-cuisine switching interface
- Individual dish ordering
- Basic shopping cart

### **Phase 3: Subscription System (Week 5-6)**
- Weekly subscription interface
- Daily meal selection system
- AbonnementSelection integration
- Payment flow (CMI integration)

### **Phase 4: Advanced Features (Week 7-8)**
- Real-time order tracking
- Mixed ordering system
- Customer dashboard enhancements
- Mobile responsiveness

## 🎨 **Design System**

**Use JoodKitchen Brand Guidelines:**
- Primary Color: `#a9b73e` (JoodKitchen Green)
- Secondary Color: `#da3c33` (Accent Red)
- Typography: Montserrat family
- Consistent with admin interface styling

**Framework Choice:**
- Option 1: Pure JavaScript + CSS (lightweight, fast)
- Option 2: Vue.js (reactive, component-based)
- Option 3: React (modern, extensive ecosystem)

## 📊 **Success Metrics**

**Customer Engagement:**
- Registration conversion rate
- Daily menu browsing time
- Subscription adoption rate
- Order completion rate

**Business Impact:**
- Revenue per customer
- Subscription retention rate
- Kitchen efficiency improvements
- Customer satisfaction scores

## 🚀 **Next Steps**

1. **Prototype the daily menu browsing system** (highest visual impact)
2. **Implement customer registration/login** (foundation)
3. **Build subscription planning interface** (core business feature)
4. **Add real-time order tracking** (customer satisfaction)

This frontend development will transform JoodKitchen from a backend-only system into a complete customer-facing platform, enabling the full business model of multi-cuisine daily menus with flexible subscription options. 