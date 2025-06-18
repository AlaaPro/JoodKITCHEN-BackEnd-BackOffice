# User Activities Integration Guide - JoodKitchen Admin

## 📋 Table of Contents
1. [Overview](#overview)
2. [System Architecture](#system-architecture)
3. [API Endpoints](#api-endpoints)
4. [Frontend Integration](#frontend-integration)
5. [Filtering & Search](#filtering--search)
6. [Debugging Tools](#debugging-tools)
7. [Recent Updates](#recent-updates)
8. [Troubleshooting](#troubleshooting)

## 🔥 LATEST UPDATE - Unified Error & Activity Monitoring (January 2025)

### **INTEGRATION WITH ERROR MONITORING SYSTEM**

**Major Enhancement:** The user activities system is now fully integrated with the comprehensive error monitoring system implemented in JoodKitchen.

**Key Improvements:**
- ✅ **Dual-Source Error Tracking**: User activities now integrate with both PHP error logs and DataDog audit logs
- ✅ **Unified Dashboard**: Activities and errors displayed cohesively in the admin interface
- ✅ **Enhanced Error Classification**: CRUD operations properly classified and integrated with real application errors
- ✅ **Detailed Error Table**: Full-width detailed errors table showing both activities and system errors

### **NEW COMPREHENSIVE ERROR MONITORING**

#### 1. **Unified LogSystemService Architecture**
```php
// Integration of user activities with error monitoring
public function getDetailedErrors(int $limit = 20): array
{
    $detailedErrors = [];
    
    // Get real errors from log files
    $logFileErrors = $this->getErrorsFromLogFiles($limit);
    
    // Get CRUD activities from audit logs
    $auditErrors = $this->getDetailedAuditErrors($limit);
    
    return array_merge($detailedErrors, $auditErrors);
}
```

#### 2. **Enhanced Activity Classification**
```php
// Activities now properly classified as errors/warnings in error monitoring
if (in_array($action, ['remove', 'delete'])) {
    $errorCount++; // CRUD deletions shown in "Erreurs Récentes" counted as errors
} elseif (in_array($action, ['insert', 'update'])) {
    $infoCount++; // Normal operations
}
```

#### 3. **Unified Statistics & Distribution**
- **Error Statistics**: Activities contribute to overall error/warning counts
- **Distribution Charts**: Activities included in system-wide error distribution
- **Recent Errors Widget**: Shows both real PHP errors and critical CRUD operations
- **Detailed Errors Table**: Displays activities alongside application errors

### **NEW ERROR MONITORING FEATURES**

#### 1. **Detailed Errors Table**
- **Location**: Full-width table below main logs interface
- **Data Sources**: Real PHP errors + User activities (CRUD operations)
- **Features**: 
  - Severity classification (Critical, Error, Warning, Info)
  - Component identification (auth, admin, menu, database)
  - Source tracking (Application Log, Database Audit)
  - Interactive modal for full error details
  - Copy to clipboard functionality

#### 2. **Real PHP Error Detection**
```php
// New error patterns detected from log files
$errorPatterns = [
    '/\[critical\]/', '/PHP Fatal error:/', '/SQLSTATE\[/',
    '/Exception/', '/Uncaught/', '/failed/',
    '/Error thrown/', '/Permission denied/'
];
```

#### 3. **Consistent Widget Display**
- **Statistics**: 4 Erreurs (CRUD deletions + real errors)
- **Recent Errors**: Real activities from audit logs
- **Distribution**: Activities contribute to error percentages

### **API ENDPOINTS ENHANCED**

#### New Error Monitoring Endpoints
```
GET /api/admin/logs/errors/detailed     # Detailed errors table (activities + real errors)
GET /api/admin/logs/stats               # Enhanced statistics including activities
GET /api/admin/logs/distribution        # Distribution including activities as errors
```

### **UNIFIED DOCUMENTATION**
- 📋 **[System Error Monitoring Guide](SYSTEM_ERROR_MONITORING.md)** - Comprehensive error tracking
- 📋 **[User Activities Integration Guide](USER_ACTIVITIES_INTEGRATION_GUIDE.md)** - This document (activity focus)

### **EXPECTED RESULTS NOW**
1. ✅ **Unified Interface**: Activities and errors displayed together in logs interface
2. ✅ **Consistent Statistics**: Activity deletions counted as errors across all widgets
3. ✅ **Detailed Error Analysis**: Full-width table showing activities with real errors
4. ✅ **Real-time Monitoring**: Both application errors and user activities tracked simultaneously
5. ✅ **Enhanced Debugging**: Complete error context with activity correlation

### **FILES MODIFIED (Latest Update)**
- `src/Service/LogSystemService.php` - Unified error and activity tracking
- `src/Controller/AdminController.php` - Added detailed errors API endpoint
- `templates/admin/system/logs.html.twig` - Added detailed errors table
- `docs/SYSTEM_ERROR_MONITORING.md` - New comprehensive error monitoring guide
- `docs/USER_ACTIVITIES_INTEGRATION_GUIDE.md` - Updated with error monitoring integration

---

## 📋 Table of Contents

## Overview

This document explains the complete implementation of the user activities system integrated into the JoodKitchen admin logs interface. The user activities feature allows administrators to monitor and track all user actions across the platform in real-time.

## Project Structure

```
WebApp/
├── src/
│   ├── Controller/
│   │   ├── AdminController.php (User Activities API endpoints)
│   │   └── Admin/
│   │       ├── AdminController.php (Page routes)
│   │       └── UserActivityController.php (Dedicated endpoints)
│   ├── Service/
│   │   └── UserActivityService.php (Business logic)
│   └── Command/
│       └── GenerateTestAuditLogsCommand.php (Test data generation)
├── public/js/admin/
│   ├── logs-api.js (System logs management)
│   └── user-activities-api.js (User activities management)
├── templates/admin/system/
│   └── logs.html.twig (Main interface with tabs)
└── docs/
    └── USER_ACTIVITIES_INTEGRATION_GUIDE.md (This file)
```

## Backend Implementation

### 1. User Activities Service (UserActivityService.php)

**Purpose**: Handles all user activity tracking and retrieval using DataDogAuditBundle.

**Key Features**:
- Real-time activity tracking through audit logs
- Advanced filtering by profile type, action, entity, date range
- Activity statistics and distribution analytics
- Export functionality (CSV, JSON, TXT)
- Profile-based activity classification

**Main Methods**:
```php
getFormattedActivities(array $criteria = [], int $limit = 20): array
getActivityStats(): array
getActivityDistribution(): array
getProfileDistribution(): array
exportActivities(array $filters, string $format = 'csv'): array|string
```

### 2. API Controllers

#### AdminController.php - User Activities Endpoints

**Routes Implemented**:
- `GET /api/admin/activities/stats` - Activity statistics
- `GET /api/admin/activities` - Activities list with filtering
- `GET /api/admin/activities/recent` - Recent activities
- `GET /api/admin/activities/distribution` - Action distribution
- `GET /api/admin/activities/profiles` - Profile distribution
- `POST /api/admin/activities/export` - Export activities

**Security**: All endpoints require `view_logs` permission except export which requires `export_logs`.

#### Admin/AdminController.php - Page Routes

**Route Added**:
```php
#[Route('/system/user-activities', name: 'admin_system_user_activities', methods: ['GET'])]
```

### 3. Test Data Generation

**Command**: `php bin/console app:generate-test-audit-logs`

**Fixed Issues**:
- Telephone field constraint violation (NULL values)
- Proper phone number formatting for test data

## Frontend Implementation

### 1. JavaScript API (user-activities-api.js)

**Architecture**: Clean, simple implementation following the same pattern as `logs-api.js`.

**Classes**:

#### UserActivitiesAPI
- Handles all HTTP requests to user activities endpoints
- JWT token management
- Proper error handling and authentication flow

**Methods**:
```javascript
getStats()                           // Get activity statistics
getActivities(filters, limit)        // Get filtered activities
getDistribution()                    // Get action distribution
getProfiles()                        // Get profile distribution
exportActivities(filters, format)    // Export activities
```

#### ActivitiesManager
- Manages UI interactions and data display
- Handles both dedicated page and logs tab integration
- Manual refresh functionality (no auto-refresh loops)

**Key Features**:
- Dual element ID support for different page contexts
- Proper table formatting for activities display
- Real-time filter application
- Error handling and user feedback

### 2. Template Integration (logs.html.twig)

#### Tab Structure
The logs page now includes two main tabs:
1. **Logs Système** - System logs (original functionality)
2. **Activités Utilisateurs** - User activities (new functionality)

#### User Activities Tab Components

**Filter Controls**:
```html
<select id="activityProfileType">  <!-- Profile filter -->
<select id="activityAction">       <!-- Action filter (corrected mapping) -->
<select id="activityEntityType">   <!-- Entity filter -->
<button id="filterActivities">     <!-- Apply filters -->
```

**Action Filter Mapping** (Fixed in latest update):
```html
<option value="">Toutes les actions</option>
<option value="insert">Créations</option>    <!-- Maps to DataDogAuditBundle 'insert' -->
<option value="update">Modifications</option> <!-- Maps to DataDogAuditBundle 'update' -->
<option value="remove">Suppressions</option>  <!-- Maps to DataDogAuditBundle 'remove' -->
```

**Content Display**:
```html
<div id="activitiesContent">  <!-- Main activities table -->
```

**Sidebar Integration**:
```html
<div class="recent-activities-list">  <!-- Sidebar activities -->
```

#### JavaScript Integration

**Script Inclusions**:
```html
<script src="{{ asset('js/admin/logs-api.js') }}"></script>
<script src="{{ asset('js/admin/user-activities-api.js') }}"></script>
```

**Event Handlers**:
- Tab activation events for lazy loading
- Filter application events
- Sidebar activities loading
- Helper functions for formatting and display

## Key Problems Solved

### 1. Frontend-Backend Connection Issues

**Problem**: User activities tab wasn't triggering backend API calls.

**Solution**:
- Added `user-activities-api.js` script inclusion to `logs.html.twig`
- Implemented proper tab activation event listeners
- Added dual element ID support for filter compatibility

### 2. Data Display and Formatting

**Problem**: Activities data wasn't displaying properly in the logs tab context.

**Solution**:
- Updated `updateActivitiesList()` method to handle both page contexts
- Created proper table HTML structure for activities
- Added responsive table formatting with Bootstrap classes

### 3. Filter Integration

**Problem**: Filter elements had different IDs between dedicated page and logs tab.

**Solution**:
- Updated `applyFilters()` method to check multiple element ID patterns:
  ```javascript
  const profileTypeEl = document.getElementById('userProfileType') || document.getElementById('activityProfileType');
  const actionEl = document.getElementById('userActivityAction') || document.getElementById('activityAction');
  ```
- Added comprehensive debugging to show which elements are found and what values are being sent

### 4. Action Filter Mapping Issue

**Problem**: Frontend filter dropdown was mapping "Créations" to `create` but DataDogAuditBundle uses `insert`.

**Solution**:
- **Before**: "Créations" → `create` (incorrect mapping)
- **After**: "Créations" → `insert` (correct DataDogAuditBundle action)
- Updated template dropdown values to match actual database action values:
  ```html
  <option value="insert">Créations</option>
  <option value="update">Modifications</option>
  <option value="remove">Suppressions</option>
  ```

### 5. Initialization and Performance

**Problem**: Need to avoid auto-refresh loops while ensuring data loads when needed.

**Solution**:
- Lazy initialization on tab activation
- Manual refresh button functionality
- Single initialization check to prevent duplicates

### 6. Enhanced Debugging and Error Handling

**Problem**: Difficult to troubleshoot filter and API issues without proper debugging.

**Solution**:
- Added comprehensive console logging with emojis for easy identification:
  ```javascript
  console.log('🔍 Filter elements found:', {...});
  console.log('📋 Raw filter values:', {...});
  console.log('✅ Final filters to send:', {...});
  ```
- Enhanced error messages and user feedback
- Improved element detection with fallback logic

## API Endpoints Reference

### Statistics Endpoint
```
GET /api/admin/activities/stats
Authorization: Bearer {jwt_token}

Response:
{
  "success": true,
  "data": {
    "total_activities": 150,
    "today_activities": 25,
    "week_activities": 89,
    "active_users": [...]
  }
}
```

### Activities List Endpoint
```
GET /api/admin/activities?profileType=AdminProfile&action=create&limit=50
Authorization: Bearer {jwt_token}

Response:
{
  "success": true,
  "data": [
    {
      "id": 1,
      "action": "insert",
      "entity_type": "Utilisateur",
      "entity_id": 123,
      "user_name": "admin@joodkitchen.ma",
      "logged_at_formatted": "18/06/2025 14:30:15",
      "changes": {...}
    }
  ],
  "count": 25
}
```

### Filter Parameters
- `profileType`: AdminProfile, ClientProfile, KitchenProfile
- `action`: insert, update, remove (DataDogAuditBundle standard actions)
- `entityType`: User, Menu, Plat, Commande, Permission
- `userId`: Specific user ID
- `dateStart`: Start date (YYYY-MM-DD)
- `dateEnd`: End date (YYYY-MM-DD)
- `limit`: Maximum results (default: 50)

**Important**: DataDogAuditBundle uses specific action names:
- `insert` = Creation of new records
- `update` = Modification of existing records  
- `remove` = Deletion of records

## Testing Instructions

### 1. Generate Test Data
```bash
php bin/console app:generate-test-audit-logs
```

### 2. Access User Activities
1. Navigate to: `http://localhost:8000/admin/system/logs`
2. Click the "Activités Utilisateurs" tab
3. Activities should load automatically

### 3. Test Filtering
1. Select different profile types, actions, entities
2. Click "Filtrer" button
3. Results should update based on filters

### 4. Verify API Endpoints
```bash
# Test with proper JWT token
curl -H "Authorization: Bearer {token}" http://localhost:8000/api/admin/activities/stats
```

## Security Considerations

### Authentication
- All endpoints require valid JWT tokens
- 401 responses trigger automatic redirect to login
- Tokens stored in localStorage with proper cleanup

### Authorization
- `view_logs` permission required for viewing activities
- `export_logs` permission required for exporting
- Granular permission checking via PermissionVoter

### Data Protection
- Entity class validation for security
- SQL injection protection through Doctrine ORM
- Proper input sanitization and validation

## Performance Optimizations

### Database Queries
- Efficient QueryBuilder usage with proper joins
- Configurable result limits
- Indexed audit log tables

### Frontend Performance
- Lazy loading of activities data
- Manual refresh instead of auto-refresh loops
- Minimal DOM manipulation
- Efficient event handling

### Caching Strategy
- JWT token caching in localStorage
- API response caching for static data
- Browser-level HTTP caching headers

## Maintenance and Monitoring

### Log Cleanup
The audit logs are managed by DataDogAuditBundle and should be cleaned up periodically:
```bash
# Example cleanup command (implement as needed)
php bin/console app:cleanup-old-audit-logs --days=90
```

### Performance Monitoring
Monitor API response times and database query performance:
- `/api/admin/activities/stats` - Should respond < 200ms
- `/api/admin/activities` - Should respond < 500ms
- Large exports may take longer depending on data volume

### Error Handling
All errors are logged and displayed to users:
- Network errors: Connection issues display user-friendly messages
- Authentication errors: Automatic redirect to login
- Authorization errors: Permission denied messages
- Data errors: Graceful fallbacks with error indication

## Future Enhancements

### Planned Features
1. Real-time activity notifications
2. Advanced analytics dashboard
3. Activity trend visualization
4. Automated alert system for suspicious activities
5. Enhanced export formats (Excel, PDF)

### Technical Improvements
1. WebSocket integration for real-time updates
2. Advanced caching with Redis
3. API rate limiting
4. Audit log archiving system
5. Enhanced search capabilities

## Troubleshooting

### Common Issues

**Problem**: User activities tab shows "Aucune activité trouvée"
**Solution**: 
1. Check if test data exists: `php bin/console app:generate-test-audit-logs`
2. Verify JWT token in browser localStorage
3. Check browser console for API errors

**Problem**: Filter not working
**Solution**:
1. Check element IDs match between template and JavaScript
2. Verify filter values are being captured correctly (check browser console for debugging output)
3. Check network tab for API calls with correct parameters
4. **Action Filter Issue**: Ensure dropdown values match DataDogAuditBundle actions (`insert`, `update`, `remove`)
5. Use browser console debugging: Look for messages starting with 🔍, 📋, and ✅

**Problem**: 401 Authentication errors
**Solution**:
1. Clear localStorage and re-login
2. Check JWT token expiration
3. Verify user has `view_logs` permission

**Problem**: JavaScript errors
**Solution**:
1. Check if both script files are loaded correctly
2. Verify ActivitiesManager class is available
3. Check browser console for specific error messages

## Development Notes

### Code Standards
- Follow PSR-12 coding standards for PHP
- Use ESLint configuration for JavaScript
- Maintain consistent naming conventions
- Comprehensive error handling throughout

### Testing Strategy
- Unit tests for UserActivityService methods
- Integration tests for API endpoints
- Frontend tests for user interactions
- Performance tests for large datasets

### Documentation
- API documentation with OpenAPI/Swagger
- Code comments for complex business logic
- User manual for admin interface
- Deployment guides for production

## Recent Updates and Fixes (June 2025)

### Critical Issues Resolved

1. **Action Filter Mapping**: Fixed incorrect mapping between frontend filter values and DataDogAuditBundle action names
   - **Before**: "Créations" → `create` (incorrect)
   - **After**: "Créations" → `insert` (correct DataDogAuditBundle action)
   
2. **Element ID Detection**: Enhanced JavaScript to handle multiple element ID patterns with better fallback logic
   - Added proper element variable caching
   - Implemented comprehensive element detection debugging
   
3. **Debugging System**: Added comprehensive console logging for easier troubleshooting
   - 🔍 Filter elements found
   - 📋 Raw filter values  
   - ✅ Final filters sent to API
   - ❌ Error messages with context
   
4. **Template Consistency**: Standardized filter element IDs and improved user experience
   - Removed duplicate/conflicting action options
   - Aligned dropdown values with actual database values

### Performance Improvements

- Optimized filter detection with efficient element selection
- Reduced DOM queries through better element caching
- Enhanced error handling and user feedback
- Eliminated unnecessary API calls with better filter validation

### Developer Experience

- Added emoji-based console logging for easy debugging identification
- Improved error messages with actionable solutions
- Enhanced documentation with troubleshooting guides
- Clear mapping documentation for DataDogAuditBundle actions

### Testing the Latest Fixes

1. **Clear browser cache** and reload the logs page
2. **Open browser console** to see the new debugging output
3. **Select "Créations" filter** - should now correctly filter `insert` actions
4. **Look for emoji console messages** to verify proper element detection
5. **Check Network tab** to see API calls with correct action parameters

## Related Documentation

### 📋 **Comprehensive Documentation Suite**

- **[System Error Monitoring Guide](SYSTEM_ERROR_MONITORING.md)** - Complete error tracking and monitoring system
- **[User Activities Integration Guide](USER_ACTIVITIES_INTEGRATION_GUIDE.md)** - This document (user activities focus)
- **[Admin Implementation Summary](ADMIN_IMPLEMENTATION_SUMMARY.md)** - Overall admin interface documentation
- **[Permission System Migration](PERMISSION_SYSTEM_MIGRATION.md)** - Permission and security system

### 🔗 **System Integration**

The user activities system is now **fully integrated** with the comprehensive error monitoring system:

1. **Error Classification**: User activities (CRUD operations) contribute to overall system error statistics
2. **Unified Interface**: Activities and errors displayed together in the admin logs interface
3. **Real-time Monitoring**: Both PHP application errors and user activities tracked simultaneously
4. **Detailed Analysis**: Comprehensive error table showing activities alongside system errors

## Conclusion

The user activities system is now fully integrated into the JoodKitchen admin interface as part of a **comprehensive dual-layer monitoring system**. This integration provides:

✅ **Unified Error & Activity Tracking** - Complete visibility into both system errors and user behavior  
✅ **Real-time Monitoring** - Immediate insights into application health and user actions  
✅ **Detailed Analysis Tools** - Interactive error investigation with full context  
✅ **Consistent Data Classification** - CRUD activities properly integrated with error monitoring  

The implementation follows best practices for security, performance, and maintainability while offering administrators powerful tools to oversee both system stability and user behavior in real-time. The integration with error monitoring creates a comprehensive observability platform for the JoodKitchen application. 