# JoodKitchen - Backend API

## 🍽️ Overview

JoodKitchen is a comprehensive restaurant management system backend built with Symfony 6.4 and API Platform. It provides a complete RESTful API for managing a restaurant's operations including users, menus, dishes, orders, subscriptions, payments, and notifications.

**🔥 NEW**: Now features a complete **stateless token authentication system** perfect for mobile apps and frontend applications, with beautiful token generator interfaces and full API Platform docs integration!

## 🏗️ Architecture

### Tech Stack
- **Framework**: Symfony 6.4
- **API**: API Platform 3.x
- **Database**: MariaDB 10.5.27
- **ORM**: Doctrine ORM
- **Authentication**: Custom Token Authenticator (stateless, mobile-ready)
- **Server**: Symfony Development Server
- **PHP**: 8.1.31

### Database Schema
The system includes 13 main entities with comprehensive relationships:

#### User Management
- **User**: Core user entity with authentication
- **ClientProfile**: Customer-specific data (loyalty points, delivery address)
- **KitchenProfile**: Kitchen staff information (position, availability)
- **AdminProfile**: Administrative user data (internal roles, permissions)

#### Menu & Food Management
- **Menu**: Restaurant menus (regular and daily specials)
- **Plat**: Individual dishes with categories and pricing
- **MenuPlat**: Junction table linking menus and dishes

#### Order Management
- **Commande**: Customer orders with status tracking
- **CommandeArticle**: Order line items (dishes/menus with quantities)
- **Payment**: Payment processing and tracking
- **CommandeReduction**: Discounts and promotions

#### System Features
- **Abonnement**: Subscription management (weekly/monthly meal plans)
- **Notification**: User notification system
- **FidelitePointHistory**: Loyalty points transaction history

## 🚀 Features

### 🔐 Authentication & Authorization

#### **NEW: Custom Token Authentication System**
- **Stateless authentication** perfect for mobile apps (React Native, Flutter)
- **Custom token format**: `user_id:email:timestamp:hash`
- **SHA256 security** with secret key
- **24-hour token expiration**
- **Bearer token support** for API Platform docs
- **Role-based access control** with hierarchical permissions

#### Multi-Role System
- `ROLE_CLIENT`: Regular customers
- `ROLE_KITCHEN`: Kitchen staff  
- `ROLE_ADMIN`: Restaurant administrators
- `ROLE_SUPER_ADMIN`: System administrators

### 🎯 **NEW: Token Generation Tools**

#### **1. Web Interface Token Generator**
Beautiful, professional web interface at `/test-browser-auth`:
- 🎨 **Modern gradient UI** with responsive design
- 👑 **Quick user selection** (Admin, Kitchen, Client)
- 🔑 **One-click token generation** with loading states
- 📋 **Instant copy-to-clipboard** functionality
- 📖 **Direct API Platform docs integration**
- 📱 **Mobile-responsive** design

#### **2. CLI Token Generator**
Command-line tool for automation and scripts:
```bash
# Generate token for specific user
php get_token.php admin    # Admin user
php get_token.php kitchen  # Kitchen user
php get_token.php client   # Client user

# Show all users and tokens
php get_token.php
```

### 📱 **NEW: Enhanced API Documentation**

#### **API Platform Docs with Authentication**
- 🔒 **Working "Authorize" button** in Swagger UI
- 🎯 **Bearer token authentication** fully integrated
- 📖 **Interactive endpoint testing** with proper auth
- 🚀 **Real-time API exploration** with live tokens

### 📱 API Endpoints

#### Authentication Endpoints
- `POST /api/auth/login` - Generate authentication token
- `POST /api/auth/logout` - Logout (stateless explanation)
- `GET /api/auth/profile` - Get authenticated user profile

#### Public Endpoints
- `GET /api/plats` - Browse available dishes
- `GET /api/menus` - Browse available menus
- `GET /api/docs` - Interactive API documentation

#### Protected API Resources
All CRUD operations available for:
- Users (`/api/users`)
- Client Profiles (`/api/client_profiles`)
- Kitchen Profiles (`/api/kitchen_profiles`)
- Admin Profiles (`/api/admin_profiles`)
- Menus (`/api/menus`)
- Dishes (`/api/plats`)
- Orders (`/api/commandes`)
- Subscriptions (`/api/abonnements`)
- Payments (`/api/payments`)
- Notifications (`/api/notifications`)
- And more...

### 🎯 Business Logic Features

#### Order Management
- Order status tracking (pending → confirmed → preparing → ready → delivered)
- Automatic total calculation with discounts
- Order history and analytics

#### Loyalty System
- Points earned on purchases
- Points redemption for discounts
- Complete transaction history

#### Subscription System
- Weekly and monthly meal plans
- Subscription status management
- Automatic renewal tracking

#### Notification System
- User notifications (info, success, warning, error)
- Order status updates
- Promotional messages

## 🛠️ Installation & Setup

### Prerequisites
- PHP 8.1+
- Composer
- MariaDB/MySQL
- Symfony CLI (recommended)

### Installation Steps

1. **Clone and install dependencies**
```bash
composer install
```

2. **Configure database**
Edit `.env` or create `.env.local`:
```
DATABASE_URL="mysql://root:@localhost:3306/joodkitchen"
```

3. **Create database and run migrations**
```bash
php bin/console doctrine:database:create
php bin/console doctrine:migrations:migrate
```

4. **Load sample data**
```bash
php bin/console doctrine:fixtures:load
```

5. **Start development server**
```bash
symfony server:start --port=8000
```

## 🧪 Testing & Usage

### **🔑 Token Generation (Multiple Methods)**

#### **Method 1: Beautiful Web Interface**
1. Go to: `https://127.0.0.1:8000/test-browser-auth`
2. Select user type (Admin/Kitchen/Client)
3. Click "Generate Token"
4. Copy token and use in API Platform docs!

#### **Method 2: Command Line**
```bash
# Quick token for admin
php get_token.php admin

# See all available users
php get_token.php
```

### **🚀 API Platform Testing**

#### **Complete Workflow:**
1. **Generate Token**: Use web interface or CLI
2. **Open API Docs**: `https://127.0.0.1:8000/api/docs/`
3. **Authorize**: Click 🔒 button, paste Bearer token
4. **Test Endpoints**: Try any protected endpoint!

### Sample Test Users
- **Admin**: `admin@joodkitchen.com` / `admin123` (ROLE_SUPER_ADMIN)
- **Kitchen**: `chef@joodkitchen.com` / `chef123` (ROLE_KITCHEN)
- **Client**: `client@joodkitchen.com` / `client123` (ROLE_CLIENT)

### **🧪 Comprehensive Testing Scripts**
```bash
# Test complete authentication flow
php test_token_auth.php

# Interactive API testing
https://127.0.0.1:8000/test-browser-auth
```

## 📊 Database Statistics

After loading fixtures:
- **27 tables** with proper relationships
- **Comprehensive indexing** for performance
- **Foreign key constraints** for data integrity
- **5 traditional Tunisian dishes**
- **2 complete menus**
- **Sample orders and user data**

## 🔒 Security Features

- **Custom token authentication** with SHA256 hashing
- **Stateless security** (perfect for mobile apps)
- **Role-based access control** (RBAC)
- **Method-specific API permissions**
- **Token expiration** (24 hours)
- **SQL injection protection** via Doctrine ORM

## 📚 API Documentation

### **🔥 NEW: Interactive Documentation**
- **Swagger UI** with working authentication
- **Bearer token support** built-in
- **Live endpoint testing** with real data
- **Complete request/response examples**
- **Role-based endpoint filtering**

Access at: `https://127.0.0.1:8000/api/docs/`

## 🎯 Mobile App Integration

### **Perfect for React Native / Flutter**

#### **Authentication Flow:**
```javascript
// 1. Login to get token
const response = await fetch('/api/auth/login', {
  method: 'POST',
  headers: { 'Content-Type': 'application/json' },
  body: JSON.stringify({ email, password })
});
const { token } = await response.json();

// 2. Store token
await AsyncStorage.setItem('auth_token', token);

// 3. Use in API calls
const apiResponse = await fetch('/api/protected-endpoint', {
  headers: { 'Authorization': `Bearer ${token}` }
});
```

#### **Token Management:**
- 24-hour expiration
- Automatic validation
- Stateless (no server sessions)
- Perfect for mobile apps

## 🚧 Future Enhancements

### Immediate Next Steps
1. **File Upload**: Add image upload for dishes and profiles
2. **Email Notifications**: Integrate email service
3. **SMS Integration**: Order status notifications
4. **Payment Gateway**: Real payment processing
5. **Admin Dashboard**: Web interface for management

### Advanced Features
1. **Multi-restaurant Support**: Extend for restaurant chains
2. **Delivery Integration**: GPS tracking and delivery management
3. **Inventory Management**: Stock tracking and automatic alerts
4. **Analytics Dashboard**: Business intelligence and reporting
5. **Push Notifications**: Mobile app notifications

## 🎉 Recent Achievements

### ✅ **Authentication System Complete**
- Custom stateless token authenticator
- API Platform docs integration
- Beautiful token generator interfaces
- Mobile-ready authentication flow

### ✅ **Developer Experience**
- Professional token generation tools
- Interactive API documentation
- Comprehensive testing scripts
- Real-world authentication examples

### ✅ **Production Ready**
- Secure token handling
- Role-based access control
- Proper error handling
- Performance optimized

## 🤝 Contributing

This system follows Symfony best practices and PSR standards:
- Domain-driven design
- SOLID principles
- Comprehensive validation
- Security-first approach
- Mobile-first API design

## 📄 License

Private project for JoodKitchen restaurant management system.

---

**🚀 Built with ❤️ using Symfony 6.4, API Platform, and modern authentication practices**

**Ready for production • Mobile app compatible • Developer friendly** 