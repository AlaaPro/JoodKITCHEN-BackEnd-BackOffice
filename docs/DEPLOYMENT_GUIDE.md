# 🚀 JoodKitchen API - cPanel Deployment Guide

## 📋 **Overview**

This guide covers deploying the enhanced JoodKitchen API on cPanel hosting with all the major improvements implemented:

✅ **JWT Authentication**  
✅ **Real-time Notifications** (cPanel-compatible)  
✅ **File-based Caching**  
✅ **Mobile-optimized API**  
✅ **Comprehensive Analytics**  
✅ **100% API-First Architecture**

---

## 🛠️ **Pre-Deployment Checklist**

### **1. cPanel Requirements**
- PHP 8.1 or higher
- MySQL/MariaDB database
- Composer installed
- SSL certificate (for JWT security)

### **2. File Structure for cPanel**
```
public_html/
├── api/              # Symfony public directory content
│   ├── index.php
│   └── .htaccess
├── app/              # Symfony application (outside public_html for security)
│   ├── bin/
│   ├── config/
│   ├── src/
│   ├── var/
│   └── vendor/
└── .env             # Environment configuration
```

---

## 📦 **Deployment Steps**

### **Step 1: Upload Files**

1. **Upload to cPanel File Manager:**
   ```bash
   # Upload everything EXCEPT public/ directory to app/
   # Upload public/ directory contents to public_html/api/
   ```

2. **Set correct permissions:**
   ```bash
   chmod -R 755 app/
   chmod -R 777 app/var/
   chmod -R 755 public_html/api/
   ```

### **Step 2: Environment Configuration**

1. **Create `.env` file in app/ directory:**
   ```env
   APP_ENV=prod
   APP_SECRET=YOUR_SECURE_SECRET_HERE
   DATABASE_URL="mysql://username:password@localhost:3306/database_name?serverVersion=10.5.27-MariaDB&charset=utf8mb4"
   
   # JWT Configuration
   JWT_SECRET_KEY=%kernel.project_dir%/config/jwt/private.pem
   JWT_PUBLIC_KEY=%kernel.project_dir%/config/jwt/public.pem
   JWT_PASSPHRASE=
   
   # CORS (adjust for your domains)
   CORS_ALLOW_ORIGIN='^https?://(yourdomain\.com|www\.yourdomain\.com)(:[0-9]+)?$'
   ```

### **Step 3: Database Setup**

1. **Create database in cPanel MySQL Databases**
2. **Import existing database or run migrations:**
   ```bash
   cd app/
   php bin/console doctrine:migrations:migrate --no-interaction
   ```

3. **Create JWT keys:**
   ```bash
   mkdir -p config/jwt
   openssl genrsa -out config/jwt/private.pem 2048
   openssl rsa -in config/jwt/private.pem -pubout -out config/jwt/public.pem
   chmod 600 config/jwt/private.pem
   chmod 644 config/jwt/public.pem
   ```

### **Step 4: Web Server Configuration**

1. **Update `public_html/api/.htaccess`:**
   ```apache
   RewriteEngine On
   
   # Handle Authorization Header
   RewriteCond %{HTTP:Authorization} ^(.+)$
   RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
   
   # Redirect to front controller
   RewriteCond %{REQUEST_FILENAME} !-f
   RewriteRule ^(.*)$ index.php [QSA,L]
   
   # CORS Headers for API
   Header always set Access-Control-Allow-Origin "*"
   Header always set Access-Control-Allow-Methods "GET, POST, PUT, DELETE, OPTIONS"
   Header always set Access-Control-Allow-Headers "Authorization, Content-Type, Accept"
   
   # Handle preflight requests
   RewriteCond %{REQUEST_METHOD} OPTIONS
   RewriteRule ^(.*)$ $1 [R=200,L]
   ```

2. **Update `public_html/api/index.php`:**
   ```php
   <?php
   use App\Kernel;
   
   require_once dirname(__DIR__).'/app/vendor/autoload_runtime.php';
   
   return function (array $context) {
       return new Kernel($context['APP_ENV'], (bool) $context['APP_DEBUG']);
   };
   ```

---

## 🔧 **Production Optimizations**

### **1. Cache Warming**
```bash
cd app/
php bin/console cache:warmup --env=prod
```

### **2. Optimize Composer**
```bash
cd app/
composer dump-autoload --optimize --no-dev
```

### **3. Clear Development Files**
```bash
rm -rf app/var/cache/dev/
rm -rf app/var/log/dev.log
```

---

## 🌐 **API Endpoints Overview**

### **Authentication**
```
POST /api/auth/login       # Login with JWT
POST /api/auth/register    # User registration
GET  /api/auth/profile     # Get user profile
POST /api/auth/refresh     # Refresh JWT token
```

### **Mobile API** (Optimized for apps)
```
GET  /api/mobile/dashboard    # Mobile dashboard
GET  /api/mobile/menu/today   # Today's menu
GET  /api/mobile/orders/status # Order status polling
GET  /api/mobile/sync         # Offline sync data
```

### **Real-time Features** (cPanel-compatible)
```
GET  /api/mobile/orders/status # Polling-based updates
GET  /api/orders/tracking/subscribe # Real-time subscription info
```

### **Analytics** (Admin only)
```
GET  /api/analytics/dashboard    # Complete dashboard
GET  /api/analytics/daily        # Daily sales report
GET  /api/analytics/weekly       # Weekly performance
GET  /api/analytics/financial    # Financial summary
GET  /api/analytics/customers    # Customer analytics
```

---

## 🔄 **Real-time Architecture (cPanel-Compatible)**

Since cPanel doesn't support WebSockets, we've implemented **polling-based real-time updates**:

### **Client-side Implementation Example:**
```javascript
// Mobile app polling for order updates
setInterval(() => {
    fetch('/api/mobile/orders/status?since=' + lastCheckTime, {
        headers: {'Authorization': 'Bearer ' + token}
    })
    .then(response => response.json())
    .then(data => {
        if (data.has_updates) {
            updateOrderStatus(data.order_updates);
            showNotifications(data.notifications);
        }
        lastCheckTime = data.last_check;
    });
}, 10000); // Check every 10 seconds
```

### **Kitchen Dashboard Polling:**
```javascript
// Kitchen dashboard real-time updates
setInterval(() => {
    fetch('/api/analytics/realtime', {
        headers: {'Authorization': 'Bearer ' + token}
    })
    .then(response => response.json())
    .then(data => {
        updateKitchenStats(data);
    });
}, 30000); // Check every 30 seconds
```

---

## 📱 **Mobile App Integration**

### **API Design Principles:**
- **Lightweight responses** - minimal data transfer
- **Efficient caching** - 30s-1h cache TTL
- **Offline support** - `/sync` endpoint for essential data
- **Polling-based real-time** - works with any hosting

### **Mobile-specific Features:**
- User preferences API
- Quick order creation
- Optimized search
- Health check endpoint

---

## 📊 **Analytics & Reporting**

### **Available Reports:**
- **Daily Sales** - Orders, revenue, popular dishes
- **Weekly Performance** - Customer stats, menu performance
- **Financial Summary** - Payment methods, category revenue
- **Customer Analytics** - Top customers, retention rates
- **Inventory Insights** - Dish performance, recommendations
- **Operational Metrics** - Preparation times, efficiency

### **Export Capabilities:**
- JSON format for dashboards
- CSV export for Excel analysis
- Real-time stats for monitoring

---

## 🛡️ **Security Features**

### **JWT Authentication:**
- 1-hour token TTL
- Secure key-pair generation
- Role-based access control
- Token refresh mechanism

### **API Security:**
- CORS properly configured
- Input validation
- SQL injection protection via Doctrine ORM
- Role-based endpoint access

---

## 🚀 **Performance Optimizations**

### **Caching Strategy:**
- **File-based caching** (cPanel compatible)
- **Aggressive caching** for menu data (30 min - 1 hour)
- **Short-term caching** for real-time data (30 seconds)
- **Analytics caching** to reduce database load

### **Database Optimization:**
- Efficient queries with JOIN operations
- Indexed fields for better performance
- Pagination for large datasets
- Query result caching

---

## 🔧 **Maintenance Tasks**

### **Cache Management:**
```bash
# Clear all caches
php bin/console cache:clear --env=prod

# Clear specific analytics cache
curl -X POST https://yourdomain.com/api/analytics/cache/clear \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

### **Regular Backups:**
- Database backups via cPanel
- File backups including JWT keys
- Environment configuration backup

---

## 🐛 **Troubleshooting**

### **Common Issues:**

1. **JWT Token Issues:**
   ```bash
   # Regenerate keys if needed
   openssl genrsa -out config/jwt/private.pem 2048
   openssl rsa -in config/jwt/private.pem -pubout -out config/jwt/public.pem
   ```

2. **File Permissions:**
   ```bash
   chmod -R 777 var/cache/ var/log/
   chmod 600 config/jwt/private.pem
   ```

3. **Database Connection:**
   - Check DATABASE_URL in .env
   - Verify database credentials in cPanel
   - Ensure database exists

4. **API Not Accessible:**
   - Check .htaccess file
   - Verify mod_rewrite is enabled
   - Check file paths in index.php

---

## 📞 **Support & Monitoring**

### **Health Check:**
```bash
curl https://yourdomain.com/api/mobile/health
```

### **Real-time Monitoring:**
```bash
curl https://yourdomain.com/api/analytics/realtime \
  -H "Authorization: Bearer YOUR_ADMIN_TOKEN"
```

---

## 🎯 **Next Steps for Growth**

When ready to scale beyond cPanel:

1. **Move to VPS/Cloud** for WebSocket support
2. **Implement Redis** for better caching
3. **Add message queues** for background processing
4. **Microservices architecture** for specific domains
5. **Auto-scaling** based on demand

---

## ✅ **Deployment Verification**

After deployment, test these endpoints:

```bash
# Test authentication
curl -X POST https://yourdomain.com/api/auth/login \
  -H "Content-Type: application/json" \
  -d '{"email":"test@example.com","password":"password"}'

# Test mobile API
curl https://yourdomain.com/api/mobile/health

# Test protected endpoint
curl https://yourdomain.com/api/mobile/dashboard \
  -H "Authorization: Bearer YOUR_TOKEN"
```

Your **JoodKitchen API** is now production-ready with enterprise-grade features optimized for cPanel hosting! 🎉 