# 🧪 JoodKitchen API Testing Guide

## 🌟 **Using API Platform Web Interface**

### **1. Public Endpoints (No Authentication)**

#### Test Dishes:
1. Navigate to `https://127.0.0.1:8000/api/docs/`
2. Find **"Plat"** section → **GET `/api/plats`**
3. Click **"Try it out"** → **"Execute"**

Expected Response:
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
      "categorie": "plat_principal"
    }
  ]
}
```

#### Test Menus:
1. Find **"Menu"** section → **GET `/api/menus`**
2. Click **"Try it out"** → **"Execute"**

### **2. Authentication Endpoints**

#### Register a New User:
1. Find **"Auth"** section (if available) or navigate to `POST /api/auth/register`
2. Use this test data:

```json
{
  "email": "test@joodkitchen.com",
  "password": "password123",
  "nom": "Test",
  "prenom": "User",
  "telephone": "12345678",
  "role": "ROLE_CLIENT"
}
```

Expected Response (201 Created):
```json
{
  "message": "User registered successfully",
  "user": {
    "id": 4,
    "email": "test@joodkitchen.com",
    "nom": "Test",
    "prenom": "User",
    "roles": ["ROLE_CLIENT"]
  }
}
```

#### Login with User:
1. Test login at `POST /api/auth/login`:
```json
{
  "email": "test@joodkitchen.com",
  "password": "password123"
}
```

### **3. Testing Protected Endpoints**

Since we haven't implemented JWT yet, you can test with the existing fixture users:

#### Existing Test Users from Fixtures:
- **Admin**: `admin@joodkitchen.com` / `admin123`
- **Kitchen**: `chef@joodkitchen.com` / `chef123`  
- **Client**: `client@joodkitchen.com` / `client123`

## 🔧 **Alternative: Testing with cURL Commands**

### Public Endpoints:
```bash
# Get all dishes
curl -X GET "https://127.0.0.1:8000/api/plats" \
  -H "Accept: application/ld+json" \
  -k

# Get all menus  
curl -X GET "https://127.0.0.1:8000/api/menus" \
  -H "Accept: application/ld+json" \
  -k
```

### Authentication:
```bash
# Register new user
curl -X POST "https://127.0.0.1:8000/api/auth/register" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "newuser@test.com",
    "password": "password123",
    "nom": "New",
    "prenom": "User", 
    "telephone": "98765432"
  }' \
  -k

# Login
curl -X POST "https://127.0.0.1:8000/api/auth/login" \
  -H "Content-Type: application/json" \
  -d '{
    "email": "newuser@test.com",
    "password": "password123"
  }' \
  -k
```

## 🎯 **Testing Different Scenarios**

### 1. **Create a New Dish** (Requires ROLE_KITCHEN or ROLE_ADMIN)
```json
POST /api/plats
{
  "nom": "Pizza Margherita",
  "description": "Pizza classique à la tomate et mozzarella",
  "prix": "12.00",
  "categorie": "pizza",
  "tempsPreparation": 15,
  "disponible": true
}
```

### 2. **Create a New Order** (Requires ROLE_CLIENT)
```json
POST /api/commandes  
{
  "typeLivraison": "livraison",
  "adresseLivraison": "123 Rue de Test",
  "statut": "en_attente",
  "commentaire": "Sans oignons svp"
}
```

### 3. **Get User Profile** (Requires Authentication)
```
GET /api/auth/profile
```

## 🚨 **Common Testing Issues & Solutions**

### Issue 1: "Not Acceptable" Error
**Problem**: Using `application/json` instead of `application/ld+json`
**Solution**: Set `Accept: application/ld+json` header

### Issue 2: 401 Unauthorized
**Problem**: Accessing protected endpoint without authentication
**Solution**: First register/login, then use the session

### Issue 3: 403 Forbidden  
**Problem**: Insufficient permissions for the endpoint
**Solution**: Use a user with appropriate role (ADMIN, KITCHEN, CLIENT)

### Issue 4: 422 Validation Error
**Problem**: Missing required fields or invalid data
**Solution**: Check the entity requirements and provide all mandatory fields

## 📊 **API Platform Interface Features**

### **Available Formats:**
- **JSON-LD**: `application/ld+json` (default)
- **JSON**: `application/json`  
- **HTML**: `text/html` (for documentation)

### **HTTP Methods Available:**
- **GET**: Retrieve resources
- **POST**: Create new resources
- **PUT**: Replace entire resource
- **PATCH**: Partial update
- **DELETE**: Remove resource

### **Query Parameters:**
- **page**: Pagination
- **itemsPerPage**: Items per page
- **order[field]**: Sorting
- **field=value**: Filtering

### **Example Queries:**
```
GET /api/plats?categorie=plat_principal
GET /api/plats?order[prix]=asc
GET /api/plats?page=2&itemsPerPage=5
GET /api/commandes?statut=en_attente
```

## 🔧 **Admin API Endpoints (Recent Additions)**

### **Admin-Specific Endpoints** (Requires ROLE_ADMIN)

#### Get Internal Roles:
```bash
curl -X GET "https://127.0.0.1:8000/api/admin/roles/internal" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -k
```

Expected Response:
```json
{
  "manager_general": {
    "name": "Manager Général",
    "description": "Accès complet à toutes les fonctionnalités"
  },
  "chef_cuisine": {
    "name": "Chef de Cuisine",
    "description": "Gestion cuisine et menus"
  },
  "responsable_it": {
    "name": "Responsable IT", 
    "description": "Gestion technique et système"
  },
  "manager_service": {
    "name": "Manager Service",
    "description": "Gestion clients et commandes"
  }
}
```

#### Get Available Permissions:
```bash
curl -X GET "https://127.0.0.1:8000/api/admin/permissions" \
  -H "Accept: application/json" \
  -H "Authorization: Bearer YOUR_JWT_TOKEN" \
  -k
```

Expected Response:
```json
{
  "dashboard": ["view_dashboard", "view_analytics"],
  "users": ["manage_admins", "manage_clients", "manage_staff"],
  "orders": ["view_orders", "manage_orders", "cancel_orders"],
  "kitchen": ["manage_kitchen", "view_preparation_queue"],
  "menu": ["manage_dishes", "manage_menus", "manage_categories"],
  "inventory": ["view_inventory", "manage_stock"],
  "customers": ["view_customers", "manage_customer_data"],
  "reports": ["view_reports", "export_data"],
  "settings": ["manage_settings", "system_configuration"],
  "system": ["view_logs", "system_maintenance"],
  "support": ["manage_tickets", "customer_support"]
}
```

## 🎪 **Advanced Testing**

### **Test Complex Relationships:**
```json
// Create order with articles
POST /api/commandes
{
  "typeLivraison": "sur_place",
  "statut": "en_attente",
  "commandeArticles": [
    {
      "plat": "/api/plats/1",
      "quantite": 2,
      "prixUnitaire": "18.50"
    }
  ]
}
```

### **Test Filtering:**
```
GET /api/plats?disponible=true
GET /api/commandes?user.email=client@joodkitchen.com
GET /api/menus?actif=true&type=normal
```

This guide should help you test every aspect of your JoodKitchen API! 🚀 