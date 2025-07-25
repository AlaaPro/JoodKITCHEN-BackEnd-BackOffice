# 📧 JoodKitchen Email Service & Account Verification System

**Version:** 1.0.0  
**Date:** July 25, 2025  
**Author:** Development Team  
**Status:** ✅ Production Ready

## 📋 Table of Contents

1. [Overview](#overview)
2. [Email Service Architecture](#email-service-architecture)
3. [Account Verification Flow](#account-verification-flow)
4. [Database Schema](#database-schema)
5. [API Endpoints](#api-endpoints)
6. [Email Templates](#email-templates)
7. [Security Features](#security-features)
8. [Mobile App Integration](#mobile-app-integration)
9. [Testing & Validation](#testing--validation)
10. [Configuration](#configuration)
11. [Troubleshooting](#troubleshooting)

## Overview

The JoodKitchen Email Service & Account Verification System provides a complete, secure, and professional email communication system with account activation through email verification. The system ensures that only users with verified email addresses can access the application.

### Key Features

- ✅ **Professional Email Templates** with JoodKitchen branding
- ✅ **SMTP Integration** with configurable email providers
- ✅ **Account Activation Flow** via email verification
- ✅ **6-digit Verification Codes** with expiration
- ✅ **Rate Limiting** to prevent spam
- ✅ **Security-First Design** with code clearing after use
- ✅ **Mobile App Ready** API endpoints
- ✅ **Multi-language Support** (French)

## Email Service Architecture

### System Components

```
┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐    ┌─────────────────┐
│   Mobile App    │    │ AuthController  │    │ EmailService    │    │  SMTP Server    │
│                 │◄──►│                 │◄──►│                 │◄──►│                 │
│ Registration    │    │ API Endpoints   │    │ Template Engine │    │ my.jood.ma:465  │
└─────────────────┘    └─────────────────┘    └─────────────────┘    └─────────────────┘
         ▲                       ▲                       ▲                       ▲
         │                       │                       │                       │
    User Actions           JWT Authentication      Twig Templates         Email Delivery
```

### Core Services

#### 1. **EmailService** (`src/Service/EmailService.php`)
Main service for sending emails with dynamic content.

```php
// Key Methods:
sendEmail()                    // Generic email sending
sendOrderConfirmation()        // Order confirmation emails
sendEmailVerification()        // Email verification codes
sendOrderStatusUpdate()        // Order status notifications
sendSubscriptionConfirmation() // Subscription confirmations
sendWeeklyMealReminder()       // Meal selection reminders
```

#### 2. **EmailVerificationService** (`src/Service/EmailVerificationService.php`)
Specialized service for managing email verification codes and account activation.

```php
// Key Methods:
generateVerificationCode()     // Generate 6-digit codes
sendVerificationEmail()        // Send verification email & save code
verifyEmailCode()             // Verify code & activate account
canRequestNewCode()           // Rate limiting check
getTimeUntilNewCodeAllowed()  // Rate limiting status
```

### Email Templates Structure

```
templates/emails/
├── base.html.twig              # Base template with branding
├── order_confirmation.html.twig # Order confirmation
├── email_verification.html.twig # Email verification
└── [future templates]          # Additional email types
```

## Account Verification Flow

### 🔐 Security-First Account Activation

The system implements a **secure account activation flow** where accounts are **inactive by default** and only activated after email verification.

### Complete Workflow

```
1. Registration → Account Created (INACTIVE)
2. Send Verification → 6-digit code sent to email
3. Email Verification → Account ACTIVATED + JWT token
4. Login Success → Full app access
```

### Detailed Flow Steps

#### Step 1: User Registration
```javascript
POST /api/auth/register
{
  "email": "user@example.com",
  "password": "password123",
  "nom": "Doe",
  "prenom": "John",
  "telephone": "0600000000"
}

Response:
{
  "success": true,
  "message": "Compte créé avec succès. Veuillez vérifier votre email pour activer votre compte.",
  "requires_verification": true,
  "user": {
    "id": 13,
    "email": "user@example.com",
    "is_active": false,      // ⚠️ INACTIVE until verified
    "email_verified": false  // ⚠️ NOT VERIFIED
  }
  // ❌ No JWT token provided
}
```

#### Step 2: Send Verification Email
```javascript
POST /api/auth/send-verification
{
  "email": "user@example.com"
}

Response:
{
  "success": true,
  "message": "Code de vérification envoyé à votre email"
}

// Database State:
email_verification_code = "123456"
email_verification_expires_at = "2025-07-25 16:45:00" (15 minutes)
```

#### Step 3: Email Verification
```javascript
POST /api/auth/verify-email
{
  "email": "user@example.com",
  "code": "123456"
}

Response:
{
  "success": true,
  "message": "Email vérifié avec succès!",
  "token": "eyJ0eXAiOiJKV1Q...", // ✅ JWT token provided
  "user": {
    "id": 13,
    "email": "user@example.com",
    "email_verified": true,    // ✅ VERIFIED
    "roles": ["ROLE_CLIENT", "ROLE_USER"]
  }
}

// Database State:
email_verified_at = "2025-07-25 16:30:00"
email_verification_code = NULL        // ✅ Cleared for security
email_verification_expires_at = NULL  // ✅ Cleared
is_active = 1                         // ✅ ACCOUNT ACTIVATED
```

#### Step 4: Login with Activated Account
```javascript
POST /api/auth/login
{
  "email": "user@example.com",
  "password": "password123"
}

Response:
{
  "message": "Login successful",
  "token": "eyJ0eXAiOiJKV1Q...",
  "user": {
    "is_active": true,        // ✅ ACTIVE
    "email_verified": true    // ✅ VERIFIED
  }
}
```

## Database Schema

### User Entity Enhancements

Added three new fields to the `user` table for email verification:

```sql
ALTER TABLE user ADD COLUMN email_verified_at DATETIME DEFAULT NULL;
ALTER TABLE user ADD COLUMN email_verification_code VARCHAR(6) DEFAULT NULL;
ALTER TABLE user ADD COLUMN email_verification_expires_at DATETIME DEFAULT NULL;
```

### Database Field Details

| Field | Type | Purpose | Security |
|-------|------|---------|----------|
| `email_verified_at` | DATETIME | Timestamp when email was verified | Permanent verification record |
| `email_verification_code` | VARCHAR(6) | 6-digit verification code | Cleared after use |
| `email_verification_expires_at` | DATETIME | Code expiration time (15 min) | Cleared after use |
| `is_active` | BOOLEAN | Account activation status | Activated after verification |

### Entity Methods Added

```php
// User.php - New Methods
getEmailVerifiedAt(): ?\DateTimeInterface
setEmailVerifiedAt(?\DateTimeInterface $emailVerifiedAt): static
isEmailVerified(): bool                    // Check if email is verified
getEmailVerificationCode(): ?string
setEmailVerificationCode(?string $code): static
getEmailVerificationExpiresAt(): ?\DateTimeInterface
setEmailVerificationExpiresAt(?\DateTimeInterface $expires): static
isEmailVerificationExpired(): bool        // Check if code expired
```

## API Endpoints

### Mobile App Integration Endpoints

All endpoints are located under `/api/auth/` and designed for mobile app consumption.

#### 1. Send Verification Email
```
POST /api/auth/send-verification

Request:
{
  "email": "user@example.com"
}

Responses:
✅ Success (200):
{
  "success": true,
  "message": "Code de vérification envoyé à votre email"
}

❌ Already Verified (400):
{
  "error": "Email is already verified"
}

❌ Rate Limited (429):
{
  "error": "Too many requests. Please wait before requesting a new code.",
  "retry_after_seconds": 45
}

❌ User Not Found (404):
{
  "error": "User not found"
}
```

#### 2. Verify Email Code
```
POST /api/auth/verify-email

Request:
{
  "email": "user@example.com",
  "code": "123456"
}

Responses:
✅ Success (200):
{
  "success": true,
  "message": "Email vérifié avec succès!",
  "token": "eyJ0eXAiOiJKV1Q...",
  "user": {
    "id": 13,
    "email": "user@example.com",
    "nom": "Doe",
    "prenom": "John",
    "email_verified": true,
    "roles": ["ROLE_CLIENT", "ROLE_USER"]
  }
}

❌ Invalid Code (400):
{
  "error": "Code de vérification incorrect."
}

❌ Expired Code (400):
{
  "error": "Le code de vérification a expiré. Demandez un nouveau code."
}

❌ No Code Found (400):
{
  "error": "Aucun code de vérification trouvé. Demandez un nouveau code."
}
```

#### 3. Resend Verification Email
```
POST /api/auth/resend-verification

Request:
{
  "email": "user@example.com"
}

Responses:
✅ Success (200):
{
  "success": true,
  "message": "Nouveau code de vérification envoyé à votre email"
}

❌ Rate Limited (429):
{
  "error": "Too many requests. Please wait before requesting a new code.",
  "retry_after_seconds": 30
}
```

### Updated Authentication Endpoints

#### Modified Registration Response
```
POST /api/auth/register

Old Response (Before):
{
  "message": "User registered successfully",
  "token": "...",  // ❌ Token provided immediately
  "user": {...}
}

New Response (After):
{
  "success": true,
  "message": "Compte créé avec succès. Veuillez vérifier votre email pour activer votre compte.",
  "requires_verification": true,  // ✅ Clear instruction
  "user": {
    "is_active": false,          // ✅ Inactive until verified
    "email_verified": false      // ✅ Verification status
  }
  // ❌ No token until verification
}
```

#### Enhanced Login Response
```
POST /api/auth/login

Inactive Account Response:
{
  "error": "Compte non activé. Veuillez vérifier votre email d'abord.",
  "requires_verification": true,
  "email": "user@example.com"
}

Active Account Response:
{
  "message": "Login successful",
  "token": "...",
  "user": {
    "is_active": true,           // ✅ Account status
    "email_verified": true       // ✅ Verification status
  }
}
```

## Email Templates

### Base Template Design

The email system uses a **professional, responsive base template** with JoodKitchen branding.

#### Design Features
- **JoodKitchen Branding**: Logo, colors (#a9b73e green, #202d5b dark blue)
- **Email-Safe CSS**: Compatible with all email clients
- **Responsive Design**: Mobile-friendly layout
- **Professional Footer**: Contact info, social links, unsubscribe options

#### Base Template Structure
```html
<!-- templates/emails/base.html.twig -->
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{% block email_title %}JoodKitchen{% endblock %}</title>
    <!-- Email-safe CSS styles -->
</head>
<body>
    <div class="email-container">
        <!-- Header with Logo -->
        <div class="email-header">
            <div class="logo">🍽️ JoodKitchen</div>
            <p class="tagline">Cuisines du Monde • Saveurs Authentiques</p>
        </div>
        
        <!-- Dynamic Content -->
        <div class="email-content">
            {% block email_content %}
            <!-- Template-specific content -->
            {% endblock %}
        </div>
        
        <!-- Professional Footer -->
        <div class="email-footer">
            <!-- Contact info, social links, unsubscribe -->
        </div>
    </div>
</body>
</html>
```

### Email Verification Template

#### Design Highlights
- **Prominent 6-digit code** in highlighted box
- **Clear instructions** for verification process
- **15-minute expiration notice**
- **JoodKitchen cuisine showcase** (🇲🇦🇮🇹🌍)
- **Call-to-action button**

#### Template Content
```twig
<!-- templates/emails/email_verification.html.twig -->
{% extends 'emails/base.html.twig' %}

{% block email_content %}
    <h2>🔐 Vérifiez votre adresse email</h2>
    
    <p>Bienvenue chez JoodKitchen !</p>
    
    <div style="text-align: center; margin: 30px 0;">
        <div style="background-color: #f8f9fa; border: 2px dashed #a9b73e; padding: 20px; border-radius: 10px;">
            <p style="margin: 0; font-size: 14px; color: #666;">Code de vérification</p>
            <p style="font-size: 32px; font-weight: bold; color: #a9b73e; letter-spacing: 3px;">
                {{ verification_code }}
            </p>
        </div>
    </div>
    
    <p style="text-align: center; color: #666;">
        ⏰ Ce code est valide pendant <strong>15 minutes</strong>
    </p>
    
    <!-- Instructions and cuisine showcase -->
{% endblock %}
```

## Security Features

### Code Generation & Management

#### Secure Code Generation
```php
// EmailVerificationService.php
public function generateVerificationCode(): string
{
    return str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
}
```

- **6-digit codes**: Range 100000-999999
- **Cryptographically secure**: Uses `random_int()`
- **Zero-padded**: Always 6 characters

#### Code Lifecycle Management

```php
// Verification Process:
1. Code Generated → Saved to database with 15-minute expiration
2. Email Sent → Code transmitted via SMTP
3. Verification Success → Code cleared, account activated
4. Verification Failed → Code remains for retry (until expiration)
5. Code Expired → Must request new code
```

#### Security Best Practices

✅ **Code Clearing**: Verification codes are **permanently deleted** after successful verification  
✅ **Expiration**: 15-minute automatic expiration  
✅ **Rate Limiting**: 60-second cooldown between requests  
✅ **One-Time Use**: Codes become invalid after verification  
✅ **Account Activation**: Accounts inactive until email verified  

### Rate Limiting Implementation

```php
public function canRequestNewCode(User $user): bool
{
    // Allow new code if no code exists or if current code is older than 1 minute
    if (!$user->getEmailVerificationExpiresAt()) {
        return true;
    }

    $now = new \DateTime();
    $expiresAt = $user->getEmailVerificationExpiresAt();
    
    // Calculate when code was created (15 minutes before expiration)
    $codeCreatedTimestamp = $expiresAt->getTimestamp() - (15 * 60);
    $timeDiff = $now->getTimestamp() - $codeCreatedTimestamp;
    
    // Allow new code if more than 60 seconds have passed
    return $timeDiff >= 60;
}
```

## Mobile App Integration

### React Native Integration Example

```javascript
// Mobile App Service Example
class AuthService {
    
    // Registration (no token returned)
    async register(userData) {
        const response = await fetch('/api/auth/register', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(userData)
        });
        
        const result = await response.json();
        
        if (result.requires_verification) {
            // Navigate to email verification screen
            return { success: true, needsVerification: true };
        }
        
        return result;
    }
    
    // Send verification email
    async sendVerificationEmail(email) {
        const response = await fetch('/api/auth/send-verification', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email })
        });
        
        return await response.json();
    }
    
    // Verify email and get JWT token
    async verifyEmail(email, code) {
        const response = await fetch('/api/auth/verify-email', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, code })
        });
        
        const result = await response.json();
        
        if (result.success && result.token) {
            // Store JWT token and navigate to main app
            await AsyncStorage.setItem('jwt_token', result.token);
            await AsyncStorage.setItem('user_data', JSON.stringify(result.user));
            return { success: true, user: result.user };
        }
        
        return result;
    }
    
    // Login with activated account
    async login(email, password) {
        const response = await fetch('/api/auth/login', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ email, password })
        });
        
        const result = await response.json();
        
        if (result.requires_verification) {
            // Navigate to email verification screen
            return { success: false, needsVerification: true, email };
        }
        
        if (result.token) {
            await AsyncStorage.setItem('jwt_token', result.token);
            await AsyncStorage.setItem('user_data', JSON.stringify(result.user));
        }
        
        return result;
    }
}
```

### Mobile App UI Flow

```javascript
// App Navigation Flow
1. Registration Screen → Submit → Show "Check your email" message
2. Email Verification Screen → Enter 6-digit code → Submit
3. Success → Store JWT token → Navigate to main app
4. Login Screen → If inactive account → Redirect to verification
```

## Testing & Validation

### Test Endpoints

#### Debug Endpoint
```
GET /test/debug-verification

Response:
{
  "success": true,
  "user_id": 11,
  "email": "valaa4@gmail.com",
  "test_code_generated": "787219",
  "send_email_result": true,
  "before": {
    "code": null,
    "expires": null,
    "verified": null
  },
  "after": {
    "code": "347502",
    "expires": "2025-07-25 16:36:14",
    "verified": null
  },
  "code_was_set": true,
  "code_changed": true
}
```

### Validation Results

#### Complete Flow Test Results

```
✅ Registration: Account created (inactive)
✅ Login Attempt: Rejected (403 Forbidden)
✅ Send Verification: Code generated and saved
✅ Email Sent: Professional email delivered
✅ Code Verification: Account activated + JWT token
✅ Login Success: Full access granted

Database Verification:
Before: is_active=0, email_verified_at=NULL, code="123456"
After:  is_active=1, email_verified_at="2025-07-25 16:30:00", code=NULL
```

## Configuration

### SMTP Configuration

#### Environment Variables (.env)
```bash
# JoodKitchen SMTP Configuration
MAILER_DSN=smtp://notification%40my.jood.ma:%40mY%40JoOd%40notification%402025@my.jood.ma:465?encryption=ssl
MAILER_FROM_EMAIL=notification@my.jood.ma
MAILER_FROM_NAME="JoodKitchen"
```

#### Supported SMTP Providers

**Production (Recommended):**
```bash
# SendGrid
MAILER_DSN=sendgrid+smtp://apikey:YOUR_API_KEY@default

# Mailgun
MAILER_DSN=mailgun+smtp://username:password@default

# Custom SMTP (JoodKitchen)
MAILER_DSN=smtp://username:password@host:port?encryption=ssl
```

**Development:**
```bash
# Gmail (for testing)
MAILER_DSN=gmail+smtp://your-email@gmail.com:app-password@default
```

### Service Configuration

#### Verification Settings
- **Code Length**: 6 digits
- **Code Expiration**: 15 minutes
- **Rate Limiting**: 60 seconds between requests
- **Max Attempts**: Unlimited (until expiration)

#### Account Settings
- **Default Status**: Inactive (`is_active = false`)
- **Activation Method**: Email verification only
- **JWT Issuance**: Only after verification

## Troubleshooting

### Common Issues & Solutions

#### 1. Emails Not Sending
**Symptoms**: Verification emails not received
```php
// Check SMTP configuration
php bin/console debug:config framework mailer

// Test email sending
GET /test/email

// Check logs
tail -f var/log/dev.log
```

#### 2. Codes Not Saving to Database
**Symptoms**: email_verification_code remains NULL
```php
// Debug verification service
GET /test/debug-verification

// Check database connection
php bin/console doctrine:query:sql "SELECT 1"
```

#### 3. Account Not Activating
**Symptoms**: is_active remains 0 after verification
```sql
-- Check user status
SELECT id, email, is_active, email_verified_at FROM user WHERE email = 'user@example.com';

-- Manual activation (emergency)
UPDATE user SET is_active = 1, email_verified_at = NOW() WHERE email = 'user@example.com';
```

#### 4. Rate Limiting Issues
**Symptoms**: "Too many requests" error
```php
// Check rate limiting status
$timeRemaining = $emailVerificationService->getTimeUntilNewCodeAllowed($user);

// Reset rate limiting (emergency)
UPDATE user SET email_verification_expires_at = NULL WHERE email = 'user@example.com';
```

### Error Messages Reference

| Error | HTTP Code | Cause | Solution |
|-------|-----------|-------|----------|
| "Email is already verified" | 400 | User already verified | Check verification status |
| "Too many requests" | 429 | Rate limiting active | Wait or reset rate limit |
| "User not found" | 404 | Invalid email | Check email spelling |
| "Code de vérification incorrect" | 400 | Wrong code entered | Check code accuracy |
| "Le code a expiré" | 400 | Code expired | Request new code |
| "Compte non activé" | 403 | Account inactive | Complete verification |

### Performance Monitoring

#### Key Metrics
- **Email Delivery Rate**: >95%
- **Verification Success Rate**: >90%
- **Code Expiration Rate**: <10%
- **Rate Limiting Hits**: <5%

#### Monitoring Queries
```sql
-- Verification success rate
SELECT 
    COUNT(*) as total_codes_sent,
    COUNT(email_verified_at) as successful_verifications,
    (COUNT(email_verified_at) / COUNT(*) * 100) as success_rate
FROM user 
WHERE email_verification_code IS NOT NULL OR email_verified_at IS NOT NULL;

-- Active vs inactive accounts
SELECT 
    is_active,
    COUNT(*) as count
FROM user 
GROUP BY is_active;
```

## Implementation Files

### Created Files
```
src/Service/EmailService.php                    # Main email service
src/Service/EmailVerificationService.php        # Verification service
templates/emails/base.html.twig                 # Base email template
templates/emails/email_verification.html.twig   # Verification template
templates/emails/order_confirmation.html.twig   # Order confirmation template
src/Controller/TestController.php               # Testing endpoints (debug)
```

### Modified Files
```
src/Entity/User.php                             # Added verification fields
src/Controller/AuthController.php               # Updated auth endpoints
.env                                            # SMTP configuration
```

### Database Migration
```sql
-- Migration: Version20250725151003
ALTER TABLE user ADD email_verified_at DATETIME DEFAULT NULL;
ALTER TABLE user ADD email_verification_code VARCHAR(6) DEFAULT NULL;
ALTER TABLE user ADD email_verification_expires_at DATETIME DEFAULT NULL;
```

## Future Enhancements

### Planned Features
- **SMS Verification**: Alternative to email verification
- **Social Login**: Google/Facebook with email verification
- **Email Templates**: Additional template types
- **Multi-language**: Support for Arabic and English
- **Analytics Dashboard**: Verification metrics and insights
- **Advanced Rate Limiting**: IP-based and user-based limits

### Integration Opportunities
- **Push Notifications**: Mobile app verification notifications
- **WhatsApp**: Verification via WhatsApp Business API
- **Customer Support**: Live chat integration for verification issues
- **Marketing Automation**: Welcome email sequences

---

**📅 Created**: July 25, 2025  
**🔧 Status**: Production Ready  
**👥 Team**: JoodKitchen Development Team  
**🎯 Purpose**: Secure account activation via email verification  

## 🚀 Success Metrics

### ✅ Implementation Complete
- **Email Service**: 100% functional with SMTP integration
- **Account Verification**: Secure 6-digit code system
- **Account Activation**: Email verification required
- **API Endpoints**: Mobile app ready
- **Email Templates**: Professional JoodKitchen branding
- **Security**: Rate limiting, code clearing, expiration
- **Testing**: Complete flow validated

### 📊 System Status: PRODUCTION READY ✅

The email service and account verification system is fully implemented, tested, and ready for production deployment. All security requirements are met, and the system follows industry best practices for email verification and account activation. 