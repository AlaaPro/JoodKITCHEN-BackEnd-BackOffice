# 🚨 JoodKitchen System Error Monitoring & Tracking

## 📋 Overview

JoodKitchen implements a comprehensive **dual-layer error monitoring system** that tracks both **application errors** and **database operations**. This system provides real-time insights into system health and operational activities.

## 🏗️ Architecture

### **Two-Layer Error Detection**

1. **Application Error Layer** 📝
   - **PHP Error Logs**: Real application errors, exceptions, warnings
   - **System Log Files**: Critical issues, fatal errors, deprecations
   - **File Sources**: `php_errors.log`, `var/log/prod.log`, `var/log/dev.log`

2. **Database Operations Layer** 🗄️
   - **DataDog Audit Bundle**: CRUD operations tracking
   - **Audit Logs Table**: Database changes, entity modifications
   - **Actions Tracked**: `insert`, `update`, `remove`, `delete`, `associate`

## 🔧 Implementation Details

### **Core Service: LogSystemService**

Located: `src/Service/LogSystemService.php`

#### **Key Methods:**

**1. Error Statistics**
```php
public function getLogStatistics(): array
```
- Counts real errors from log files
- Tracks CRUD operations from audit logs
- Returns: `['logs_today' => int, 'errors' => int, 'warnings' => int, 'info' => int, 'debug' => int]`

**2. Recent Errors**
```php
public function getRecentErrors(int $limit = 5): array
```
- Combines log file errors + CRUD deletions
- Shows most recent critical issues
- Formats for sidebar widget display

**3. Detailed Error Analysis**
```php
public function getDetailedErrors(int $limit = 20): array
```
- Comprehensive error details for main table
- Includes timestamps, severity, messages, sources
- Supports pagination and filtering

**4. Log File Processing**
```php
private function getErrorsFromLogFiles(int $limit = 10): array
```
- Parses PHP error logs and system logs
- Detects error patterns: `[critical]`, `PHP Fatal error`, `SQLSTATE`, etc.
- Classifies severity levels: critical, error, warning, info

### **Error Pattern Detection**

**Recognized Patterns:**
```php
$errorPatterns = [
    '/\[critical\]/',
    '/\[error\]/',
    '/\[warning\]/',
    '/PHP Fatal error:/',
    '/PHP Warning:/',
    '/PHP Deprecated:/',
    '/SQLSTATE\[/',
    '/Exception/',
    '/Uncaught/',
    '/failed/',
    '/Error thrown/',
    '/Integrity constraint violation/',
    '/does not exist/',
    '/Permission denied/',
    '/Connection refused/'
];
```

**Severity Classification:**
- **Critical**: PHP Fatal errors, system crashes
- **Error**: Exceptions, database errors, failed operations
- **Warning**: PHP warnings, deprecated functions, CRUD deletions
- **Info**: Normal operations, successful actions

## 📊 User Interface Components

### **1. Dashboard Widgets**

**Statistics Cards:**
- **325 Logs aujourd'hui**: Total daily log entries
- **4 Erreurs**: Critical errors + CRUD deletions
- **0 Avertissements**: System warnings
- **119 Info**: Normal operations

**Distribution Chart (Répartition 24h):**
- **Error**: 4% (CRUD deletions counted as errors)
- **Warning**: 1% (System warnings)
- **Info**: 37% (Normal operations)
- **Debug**: 62% (Development logs)

### **2. Recent Errors Widget**

**Location**: Right sidebar
**Purpose**: Quick error overview
**Data Sources**:
- Real PHP errors from log files
- CRUD deletion operations
- Formatted with timestamps and components

### **3. Detailed Errors Table**

**Location**: Main content area (below logs tabs)
**Features**:
- **Full error messages** with expand/collapse
- **Severity badges** (Critical, Error, Warning, Info)
- **Component classification** (auth, admin, menu, database)
- **Source identification** (Application Log, Database Audit)
- **Interactive actions** (View details, Copy to clipboard)
- **Pagination support** (20/50/100 per page)

## 🔗 API Endpoints

### **Error Monitoring APIs**

**Base URL**: `/api/admin/logs/`

| Endpoint | Method | Purpose | Response |
|----------|--------|---------|----------|
| `/stats` | GET | Dashboard statistics | `{logs_today, errors, warnings, info, debug}` |
| `/errors` | GET | Recent errors for sidebar | `{title, time, component, severity}[]` |
| `/errors/detailed` | GET | Detailed errors table | `{id, timestamp, severity, component, message, full_message}[]` |
| `/distribution` | GET | Error distribution chart | `{info: %, warning: %, error: %, debug: %}` |

### **Request/Response Examples**

**Get Statistics:**
```bash
GET /api/admin/logs/stats
Authorization: Bearer {token}
```

```json
{
  "success": true,
  "data": {
    "logs_today": 325,
    "errors": 4,
    "warnings": 0,
    "info": 119,
    "debug": 202
  }
}
```

**Get Detailed Errors:**
```bash
GET /api/admin/logs/errors/detailed?limit=20
Authorization: Bearer {token}
```

```json
{
  "success": true,
  "data": [
    {
      "id": "unique_id",
      "timestamp": "2025-01-18 14:30:15",
      "formatted_time": "Aujourd'hui 14:30:15",
      "severity": "error",
      "component": "database",
      "message": "Suppression d'entité en menu",
      "full_message": "Action 'remove' sur table 'menu' par Admin User le 18/01/2025 14:30:15",
      "type": "audit_log",
      "source": "Database Audit"
    }
  ],
  "count": 4
}
```

## 🗂️ Log File Sources

### **Application Logs**
```
php_errors.log              # Main PHP error log
var/log/prod.log            # Production environment logs  
var/log/dev.log             # Development environment logs
public/php_errors.log       # Public directory errors
```

### **Database Audit Logs**
```sql
-- Table: audit_logs
SELECT 
    id,
    action,           -- insert, update, remove, delete, associate
    tbl,             -- Target table name
    logged_at,       -- Timestamp
    diff,            -- Changes made
    blame_id         -- User who performed action
FROM audit_logs 
WHERE logged_at >= CURDATE()
ORDER BY logged_at DESC;
```

## ⚙️ Configuration

### **Log Files Configuration**

**PHP Error Logging** (`php.ini`):
```ini
log_errors = On
error_log = php_errors.log
display_errors = Off
```

**Symfony Logging** (`config/packages/monolog.yaml`):
```yaml
monolog:
    channels: ['audit', 'security', 'app']
    handlers:
        main:
            type: rotating_file
            path: '%kernel.logs_dir%/%kernel.environment%.log'
            level: info
```

### **DataDog Audit Bundle** (`config/packages/data_dog_audit.yaml`):
```yaml
data_dog_audit:
    audited_entities:
        - App\Entity\User
        - App\Entity\AdminProfile
        - App\Entity\Menu
        - App\Entity\Plat
        - App\Entity\Commande
        - App\Entity\Permission
```

## 🔍 Error Investigation Workflow

### **1. Dashboard Review**
1. Check **Statistics Cards** for error counts
2. Review **Distribution Chart** for patterns
3. Monitor **Recent Errors** widget for urgent issues

### **2. Detailed Analysis**
1. Access **Detailed Errors Table**
2. Sort by **severity** or **timestamp**
3. Expand **full error messages** for context
4. Use **Copy** feature for error sharing

### **3. Log File Investigation**
```bash
# View recent PHP errors
tail -n 50 php_errors.log

# Search for specific error patterns  
grep -i "fatal\|error\|exception" php_errors.log

# Monitor real-time errors
tail -f php_errors.log
```

### **4. Database Query Analysis**
```sql
-- Recent CRUD operations
SELECT action, tbl, logged_at, blame_id 
FROM audit_logs 
WHERE action IN ('remove', 'delete')
ORDER BY logged_at DESC 
LIMIT 10;

-- Error patterns by table
SELECT tbl, action, COUNT(*) as count
FROM audit_logs 
WHERE logged_at >= CURDATE()
GROUP BY tbl, action
ORDER BY count DESC;
```

## 🚀 Performance Considerations

### **Optimizations Implemented**

1. **Efficient Log Reading**:
   - `readLastLines()` method for tail-like functionality
   - Limited file processing to prevent memory issues
   - Configurable limits for API responses

2. **Caching Strategy**:
   - Statistics cached for 1 minute
   - Error patterns compiled once per request
   - Database queries optimized with indexes

3. **Pagination**:
   - Default 20 errors per page
   - Configurable limits (20/50/100)
   - "Load more" functionality for progressive loading

## 🛡️ Security & Access Control

### **Permission Requirements**
- **`view_logs`**: Required for all error monitoring endpoints
- **`export_logs`**: Required for log export functionality
- **JWT Authentication**: All API calls require valid admin token

### **Data Sanitization**
- Error messages HTML-escaped before display
- SQL injection prevention in audit queries
- XSS protection in error details modal

## 📈 Monitoring Metrics

### **Key Performance Indicators**
- **Daily Error Count**: Track system stability trends
- **Error Rate by Component**: Identify problematic modules
- **Response Time**: API endpoint performance
- **Log File Growth**: Storage usage monitoring

### **Alerting Thresholds**
- **Critical**: > 10 fatal errors per hour
- **Warning**: > 50 errors per hour  
- **Info**: > 1000 operations per hour

## 🔧 Troubleshooting

### **Common Issues**

**1. No Errors Showing**
- Check log file permissions
- Verify PHP error logging enabled
- Confirm audit bundle configuration

**2. Performance Issues**
- Monitor log file sizes
- Check database indexes on `audit_logs`
- Review API response times

**3. Missing Real-Time Updates**
- Verify JavaScript initialization
- Check API authentication
- Monitor browser console for errors

### **Debug Commands**
```bash
# Test log statistics
php bin/console debug:container LogSystemService

# Check audit logs
php bin/console dbal:run-sql "SELECT COUNT(*) FROM audit_logs"

# Verify API endpoints
curl -H "Authorization: Bearer {token}" /api/admin/logs/stats
```

## 📚 Related Documentation

- [API Testing Guide](api_testing_guide.md)
- [Admin Implementation Summary](ADMIN_IMPLEMENTATION_SUMMARY.md)
- [User Activities Integration](USER_ACTIVITIES_INTEGRATION_GUIDE.md)
- [Permission System](PERMISSION_SYSTEM_MIGRATION.md)

---

**Last Updated**: January 2025  
**Version**: 1.0  
**Author**: JoodKitchen Development Team 