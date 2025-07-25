# Client API Endpoints Implementation

## 📋 Overview

This document outlines the implementation of client self-management API endpoints for the JoodKitchen mobile application. These endpoints follow the same architectural patterns used in AdminController and KitchenController, providing secure and comprehensive client profile management capabilities.

## 🏗️ Architecture

The implementation extends the existing `WebApp/src/Controller/Api/ClientController.php` with additional endpoints specifically designed for client self-management while maintaining the existing admin endpoints.

### File Structure
```
WebApp/src/Controller/Api/ClientController.php
├── Admin Endpoints (existing)
│   ├── GET /api/clients (admin list)
│   ├── GET /api/clients/{id} (admin view details)
│   ├── GET /api/clients/{id}/history (admin view history)
│   └── POST /api/clients/{id}/toggle-status (admin status management)
└── Client Self-Management Endpoints (NEW)
    ├── GET /api/clients/profile/me
    ├── PUT /api/clients/profile/update
    ├── DELETE /api/clients/profile/delete
    ├── GET /api/clients/orders/history
    └── GET /api/clients/fidelite/points
```

## 🔐 Security Implementation

### Authentication & Authorization
- **Admin Endpoints**: Require `ROLE_ADMIN` authorization
- **Client Endpoints**: Require `ROLE_CLIENT` authorization
- **JWT Authentication**: All endpoints use JWT token authentication
- **Self-Access Only**: Clients can only access/modify their own data

### Security Features
- Email uniqueness validation
- Password confirmation for account deletion
- Active order validation before account deletion
- Input sanitization and validation
- Comprehensive error handling

## 📡 API Endpoints Documentation

### 1. Get Client Profile
```http
GET /api/clients/profile/me
Authorization: Bearer {jwt_token}
Content-Type: application/json
```

**Response:**
```json
{
  "success": true,
  "data": {
    "id": 123,
    "nom": "Dupont",
    "prenom": "Jean",
    "email": "jean.dupont@example.com",
    "telephone": "0600000000",
    "ville": "Casablanca",
    "adresse": "123 Rue Mohammed V",
    "genre": "homme",
    "date_naissance": "1990-01-15",
    "is_active": true,
    "email_verified": true,
    "last_connexion": "2024-01-15 10:30:00",
    "created_at": "2024-01-01 08:00:00",
    "updated_at": "2024-01-15 10:30:00",
    "client_profile": {
      "id": 456,
      "adresse_livraison": "456 Avenue Hassan II",
      "points_fidelite": 150,
      "created_at": "2024-01-01 08:00:00",
      "updated_at": "2024-01-15 10:30:00"
    }
  }
}
```

### 2. Update Client Profile
```http
PUT /api/clients/profile/update
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "nom": "Dupont",
  "prenom": "Jean",
  "email": "jean.dupont@example.com",
  "telephone": "0600000000",
  "ville": "Casablanca",
  "adresse": "123 Rue Mohammed V",
  "genre": "homme",
  "date_naissance": "1990-01-15",
  "adresse_livraison": "456 Avenue Hassan II",
  "password": "new_password"  // optional
}
```

**Response:**
```json
{
  "success": true,
  "message": "Profil mis à jour avec succès",
  "type": "success",
  "data": {
    // Updated profile data
  }
}
```

### 3. Delete Client Account
```http
DELETE /api/clients/profile/delete
Authorization: Bearer {jwt_token}
Content-Type: application/json

{
  "password": "current_password"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Votre compte a été supprimé avec succès.",
  "type": "success"
}
```

### 4. Get Order History
```http
GET /api/clients/orders/history?page=1&limit=10&status=completed
Authorization: Bearer {jwt_token}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 789,
      "numero": "CMD-789",
      "date_commande": "2024-01-15 12:00:00",
      "type_livraison": "livraison",
      "adresse_livraison": "456 Avenue Hassan II",
      "total": 85.50,
      "total_avant_reduction": 95.00,
      "statut": "completed",
      "commentaire": "Livraison rapide SVP",
      "articles": [
        {
          "nom": "Tajine Poulet",
          "quantite": 1,
          "prix_unitaire": 45.00,
          "commentaire": "Bien épicé"
        }
      ],
      "articles_count": 1
    }
  ],
  "pagination": {
    "page": 1,
    "limit": 10,
    "total": 25,
    "pages": 3
  }
}
```

### 5. Get Fidelity Points
```http
GET /api/clients/fidelite/points
Authorization: Bearer {jwt_token}
```

**Response:**
```json
{
  "success": true,
  "data": {
    "current_points": 150,
    "history": [
      {
        "id": 101,
        "points": 10,
        "type": "earned",
        "description": "Points gagnés pour commande CMD-789",
        "date": "2024-01-15 12:30:00"
      }
    ],
    "history_count": 15
  }
}
```

## 🔄 Integration with Existing Systems

### Authentication Flow
1. **Registration**: Already handled by `/api/auth/register` (creates ClientProfile automatically)
2. **Login**: Already handled by `/api/auth/login`
3. **Profile Management**: New endpoints handle profile updates and management

### Data Relationships
- **User Entity**: Core user information
- **ClientProfile Entity**: Client-specific data (delivery address, loyalty points)
- **Commande Entity**: Order history integration
- **FidelitePointHistory Entity**: Loyalty points tracking

## 🚨 Error Handling

### Common Error Responses
```json
// Authentication Error
{
  "error": "Utilisateur non authentifié",
  "message": "Vous devez être connecté pour accéder à votre profil.",
  "type": "authentication_error"
}

// Validation Error
{
  "error": "Erreurs de validation",
  "message": "Les données saisies ne sont pas valides.",
  "details": ["email: Cette adresse email n'est pas valide"],
  "type": "validation_error"
}

// Duplicate Email Error
{
  "error": "Email déjà utilisé",
  "message": "Cette adresse email est déjà utilisée par un autre compte.",
  "type": "duplicate_email"
}

// Server Error
{
  "error": "Erreur lors de la mise à jour",
  "message": "Une erreur inattendue s'est produite. Veuillez réessayer.",
  "type": "server_error",
  "debug": "Exception details (dev environment only)"
}
```

## 📱 Mobile App Integration

### Required Changes in React Native
1. **Update API Configuration**: Add new client endpoints to API config
2. **Create Client Services**: Implement client profile management services
3. **Update Profile Screens**: Connect profile screens to new endpoints
4. **Add Order History**: Implement order history viewing
5. **Add Loyalty Points**: Display and manage loyalty points

### Example Service Implementation
```javascript
// src/services/ClientService.js
import api from '../config/api';

class ClientService {
  async getProfile() {
    const response = await api.get('/clients/profile/me');
    return response.data;
  }

  async updateProfile(profileData) {
    const response = await api.put('/clients/profile/update', profileData);
    return response.data;
  }

  async deleteAccount(password) {
    const response = await api.delete('/clients/profile/delete', {
      data: { password }
    });
    return response.data;
  }

  async getOrderHistory(page = 1, limit = 10, status = null) {
    const params = new URLSearchParams({ page, limit });
    if (status) params.append('status', status);
    
    const response = await api.get(`/clients/orders/history?${params}`);
    return response.data;
  }

  async getFidelityPoints() {
    const response = await api.get('/clients/fidelite/points');
    return response.data;
  }
}

export default new ClientService();
```

## ✅ Features Implemented

### Core Functionality
- ✅ **Profile Retrieval**: Get complete client profile with related data
- ✅ **Profile Update**: Update all user and client profile fields
- ✅ **Account Deletion**: Secure account deletion with password confirmation
- ✅ **Order History**: Paginated order history with filtering
- ✅ **Loyalty Points**: Current points and detailed history

### Security Features
- ✅ **JWT Authentication**: Secure endpoint access
- ✅ **Role-based Authorization**: Client-only access to self-management endpoints
- ✅ **Email Validation**: Prevent duplicate emails
- ✅ **Password Validation**: Secure password updates
- ✅ **Active Order Protection**: Prevent account deletion with pending orders

### Data Management
- ✅ **Comprehensive Validation**: Server-side data validation
- ✅ **Error Handling**: Detailed error responses with types
- ✅ **Pagination Support**: Efficient data loading for large datasets
- ✅ **Soft Delete**: Account anonymization instead of hard deletion

## 🔜 Next Steps

1. **Test Integration**: Test all endpoints with mobile app
2. **Update Mobile Services**: Implement React Native service layer
3. **Update UI Components**: Connect profile screens to new API
4. **Add Error Handling**: Implement comprehensive error handling in mobile app
5. **Performance Testing**: Test with real data and optimize if needed

## 📝 Notes

- **Backward Compatibility**: All existing admin endpoints remain unchanged
- **Security First**: All endpoints follow security best practices
- **Error Standards**: Consistent error response format across all endpoints
- **Mobile Optimized**: Response structure optimized for mobile app consumption
- **Extensible**: Architecture allows for easy addition of new client features

---

**Status**: ✅ **COMPLETED** - Ready for mobile app integration
**Last Updated**: January 2024
**Author**: AI Assistant 