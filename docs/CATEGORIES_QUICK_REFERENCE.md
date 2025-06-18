# Categories Management - Quick Reference

## 🚀 Quick Start

### Frontend Usage
```javascript
// Initialize the category manager
const categoryManager = new CategoryManager();

// Load and display categories
await categoryManager.loadCategories();

// Toggle category status
await categoryManager.toggleActive(categoryId);
await categoryManager.toggleVisibility(categoryId);
```

### API Usage
```javascript
// Get all categories
const response = await fetch('/api/admin/menu/categories', {
    headers: { 'Authorization': `Bearer ${token}` }
});

// Create category
await fetch('/api/admin/menu/categories', {
    method: 'POST',
    headers: { 
        'Authorization': `Bearer ${token}`,
        'Content-Type': 'application/json'
    },
    body: JSON.stringify({
        nom: 'Category Name',
        actif: true,
        visible: true
    })
});
```

## 📋 Key Files

| File | Purpose |
|------|---------|
| `src/Entity/Category.php` | Category entity |
| `src/Repository/CategoryRepository.php` | Database queries |
| `src/Controller/Api/MenuController.php` | API endpoints |
| `public/js/admin/managers/menu-api.js` | API client |
| `public/js/admin/managers/category-manager.js` | UI manager |
| `templates/admin/menu/categories.html.twig` | User interface |

## 🔌 API Endpoints

| Endpoint | Method | Description |
|----------|--------|-------------|
| `/api/admin/menu/categories` | GET | List categories |
| `/api/admin/menu/categories` | POST | Create category |
| `/api/admin/menu/categories/{id}` | PUT | Update category |
| `/api/admin/menu/categories/{id}` | DELETE | Delete category |
| `/api/admin/menu/categories/reorder` | PUT | Reorder positions |

## 🎯 Key Features

### Toggle Functions
- `toggleActive(id)` - Enable/disable category
- `toggleVisibility(id)` - Show/hide category
- Both work for main and sub-categories

### Visual Indicators
- **Inactive**: 50% opacity + "Inactif" badge
- **Hidden**: "Masqué" badge + eye-slash icon
- **Active**: Full opacity + primary colors

### Database Fields
- `actif` (boolean) - Category enabled/disabled
- `visible` (boolean) - Category visible on site
- `parent_id` (int|null) - Parent category for hierarchy
- `position` (int) - Sort order within level

## 🐛 Common Issues

### Authentication Error
```
Error: Invalid JWT Token
Solution: Check AdminAuth.getToken() returns valid token
```

### Modal Not Opening
```
Error: bootstrap is not defined
Solution: System uses CoreUI, not Bootstrap directly
```

### Reorder Not Working
```
Error: 500 on /categories/reorder
Solution: Clear cache: php bin/console cache:clear
```

## 🔧 Debug Commands

```bash
# Check categories in database
php bin/console dbal:run-sql "SELECT id, nom, actif, visible, parent_id FROM category"

# Clear cache
php bin/console cache:clear

# Check API routes
php bin/console debug:router | grep categories
```

---
*JoodKitchen Categories - Quick Reference v1.0* 