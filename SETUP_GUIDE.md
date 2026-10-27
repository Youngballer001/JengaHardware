# Jengo Hardware - Setup Guide

Complete installation and configuration guide for the Jengo Hardware e-commerce system.

## Table of Contents

1. [Prerequisites](#prerequisites)
2. [Installation](#installation)
3. [Database Setup](#database-setup)
4. [Configuration](#configuration)
5. [Testing](#testing)
6. [Going Live](#going-live)

---

## Prerequisites

### Required Software

- **XAMPP** (or LAMP/WAMP/MAMP)
  - PHP 7.4 or higher
  - MySQL 5.7 or higher
  - Apache Web Server
- **Web Browser** (Chrome, Firefox, Edge, Safari)

### Optional Tools

- **ngrok** - For testing payment webhooks locally
- **Git** - For version control
- **Composer** - If adding PHP dependencies

---

## Installation

### Step 1: Extract Files

1. Extract the project to your web server directory:
   - **Windows (XAMPP)**: `c:\xampp\htdocs\Jengo Hardware\`
   - **Mac (MAMP)**: `/Applications/MAMP/htdocs/Jengo Hardware/`
   - **Linux (LAMP)**: `/var/www/html/Jengo Hardware/`

2. Verify all files are present:
   ```
   Jengo Hardware/
   ├── assets/
   ├── config/
   ├── databases/
   ├── includes/
   ├── index.php
   ├── auth.php
   ├── shop.php
   ├── cart.php
   ├── checkout.php
   ├── admin.php
   ├── attendant.php
   ├── customer.php
   └── ...
   ```

### Step 2: Set Permissions

Ensure the following directories are writable:

```bash
chmod 755 assets/images/products/
chmod 755 config/storage/logs/
```

On Windows, right-click folder → Properties → Security → Edit → Allow "Write" for your user.

---

## Database Setup

### Step 1: Create Database

1. Open **phpMyAdmin**: `http://localhost/phpmyadmin`
2. Click **"New"** to create database
3. Database name: `jengo_hardware`
4. Collation: `utf8mb4_general_ci`
5. Click **"Create"**

### Step 2: Import Schema

1. Select `jengo_hardware` database
2. Click **"Import"** tab
3. Click **"Choose File"**
4. Select: `databases/schema.sql`
5. Click **"Go"**

**OR** use command line:

```bash
mysql -u root -p jengo_hardware < databases/schema.sql
```

### Step 3: Verify Tables

Check that these tables were created:
- ✅ users
- ✅ products
- ✅ categories
- ✅ orders
- ✅ order_items
- ✅ mpesa_transactions
- ✅ paystack_transactions

### Step 4: Test Data

The schema includes sample data:
- **3 users**: admin@jengo.com, attendant@jengo.com, customer@jengo.com (password: password123)
- **10 products** across 5 categories
- **Sample orders** with different statuses

---

## Configuration

### Database Configuration

Update database credentials in **ALL** files:

**Files to update:**
- `auth.php`
- `dashboard.php`
- `admin.php`
- `attendant.php`
- `customer.php`
- `shop.php`
- `product.php`
- `cart.php`
- `checkout.php`
- `actions.php`
- `admin-actions.php`
- `attendant-actions.php`

**Change these lines:**

```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');           // Your MySQL username
define('DB_PASS', '');               // Your MySQL password
define('DB_NAME', 'jengo_hardware');
```

### M-Pesa Configuration

1. Open: `config/includes.php/payments/mpesa-config.php`

2. Update with your Safaricom credentials:

```php
define('MPESA_ENVIRONMENT', 'sandbox'); // or 'production'
define('MPESA_CONSUMER_KEY', 'YOUR_CONSUMER_KEY');
define('MPESA_CONSUMER_SECRET', 'YOUR_CONSUMER_SECRET');
define('MPESA_SHORTCODE', 'YOUR_SHORTCODE');
define('MPESA_PASSKEY', 'YOUR_PASSKEY');
define('MPESA_INITIATOR_NAME', 'testapi');
define('MPESA_INITIATOR_PASSWORD', 'YOUR_SECURITY_CREDENTIAL');
```

3. Update callback URL (use ngrok for local testing):

```php
define('MPESA_CALLBACK_URL', 'https://your-domain.com/Jengo Hardware/config/includes.php/payments/mpesa-callback.php');
```

### Paystack Configuration

1. Open: `config/includes.php/payments/paystack-config.php`

2. Update with your Paystack credentials:

```php
define('PAYSTACK_PUBLIC_KEY', 'pk_test_YOUR_PUBLIC_KEY');
define('PAYSTACK_SECRET_KEY', 'sk_test_YOUR_SECRET_KEY');
```

3. Update callback URLs:

```php
define('PAYSTACK_CALLBACK_URL', 'https://your-domain.com/Jengo Hardware/config/includes.php/payments/paystack-callback.php');
define('PAYSTACK_WEBHOOK_URL', 'https://your-domain.com/Jengo Hardware/config/includes.php/payments/paystack-webhook.php');
```

### Environment Variables (Optional)

Create `.env` file for sensitive data:

```env
DB_HOST=localhost
DB_USER=root
DB_PASS=your_password
DB_NAME=jengo_hardware

MPESA_CONSUMER_KEY=your_key
MPESA_CONSUMER_SECRET=your_secret
MPESA_SHORTCODE=your_shortcode
MPESA_PASSKEY=your_passkey

PAYSTACK_PUBLIC_KEY=pk_test_xxxxx
PAYSTACK_SECRET_KEY=sk_test_xxxxx
```

---

## Testing

### Test User Accounts

| Email                | Password    | Role      |
|---------------------|-------------|-----------|
| admin@jengo.com     | password123 | Admin     |
| attendant@jengo.com | password123 | Attendant |
| customer@jengo.com  | password123 | Customer  |

### Testing Checklist

#### 1. Authentication
- [ ] Register new customer account
- [ ] Login with each role (admin, attendant, customer)
- [ ] Logout and verify session cleared
- [ ] Test role-based redirects

#### 2. Shopping (Customer)
- [ ] Browse products on shop page
- [ ] Filter by category
- [ ] Search for products
- [ ] View product details
- [ ] Add products to cart
- [ ] Update cart quantities
- [ ] Remove items from cart
- [ ] Proceed to checkout

#### 3. Checkout & Payments
- [ ] Select delivery method (pickup, standard, express)
- [ ] Enter delivery address
- [ ] Create order with "pending" status
- [ ] Test M-Pesa payment (use test phone: 254708374149)
- [ ] Test Paystack payment (use test card: 4084084084084081, CVV: 408, Expiry: any future date)
- [ ] Verify payment status updates
- [ ] Check order confirmation page

#### 4. Admin Dashboard
- [ ] View overview with stats
- [ ] Add new product
- [ ] Edit existing product
- [ ] Delete product (soft delete)
- [ ] Add new category
- [ ] Edit category
- [ ] Delete category
- [ ] View all orders
- [ ] Update order status
- [ ] Update payment status
- [ ] View users list
- [ ] Update user status
- [ ] Update user role
- [ ] View reports

#### 5. Attendant Dashboard
- [ ] View overview stats
- [ ] View orders
- [ ] Update order status
- [ ] Check inventory
- [ ] View low stock alerts
- [ ] View out of stock items

#### 6. Customer Dashboard
- [ ] View order history
- [ ] Track order status
- [ ] View order details
- [ ] Update profile information
- [ ] Change password
- [ ] Cancel pending order

### Local Webhook Testing

Use **ngrok** for webhook testing:

1. Install ngrok: https://ngrok.com/download

2. Start ngrok:
   ```bash
   ngrok http 80
   ```

3. Copy HTTPS URL (e.g., `https://abc123.ngrok.io`)

4. Update callback URLs in config files:
   - M-Pesa: `https://abc123.ngrok.io/Jengo Hardware/config/includes.php/payments/mpesa-callback.php`
   - Paystack: `https://abc123.ngrok.io/Jengo Hardware/config/includes.php/payments/paystack-webhook.php`

5. Update Paystack webhook URL in Paystack Dashboard

6. Test payments and verify webhooks are received

---

## Going Live

### Pre-Launch Checklist

#### 1. Security

- [ ] Change all default passwords
- [ ] Update database credentials
- [ ] Use strong passwords (min 12 characters)
- [ ] Enable HTTPS/SSL certificate
- [ ] Update `.gitignore` to protect sensitive files
- [ ] Remove test data from database
- [ ] Disable error display in production:
  ```php
  error_reporting(0);
  ini_set('display_errors', 0);
  ```

#### 2. Payment Configuration

- [ ] Switch M-Pesa to production mode:
  ```php
  define('MPESA_ENVIRONMENT', 'production');
  ```
- [ ] Use production API credentials
- [ ] Switch Paystack to live keys (`pk_live_`, `sk_live_`)
- [ ] Update callback URLs to production domain
- [ ] Test production payment flow
- [ ] Configure Paystack webhook in dashboard

#### 3. Email Configuration

- [ ] Set up SMTP for email notifications
- [ ] Configure "From" email address
- [ ] Test order confirmation emails
- [ ] Test password reset emails

#### 4. Database

- [ ] Backup database before going live
- [ ] Remove test orders and transactions
- [ ] Verify all product data is correct
- [ ] Check stock levels are accurate

#### 5. Content

- [ ] Add real product images
- [ ] Write product descriptions
- [ ] Set accurate prices
- [ ] Configure delivery fees
- [ ] Add company information
- [ ] Update contact details

#### 6. Performance

- [ ] Enable PHP opcache
- [ ] Optimize images
- [ ] Enable gzip compression
- [ ] Test page load speeds
- [ ] Configure caching

### Production Server Setup

1. **Upload Files**
   - Use FTP/SFTP to upload all files
   - Exclude `.git`, `node_modules`, `.env.example`

2. **Set Permissions**
   ```bash
   chmod 755 assets/images/products/
   chmod 755 config/storage/logs/
   chmod 644 *.php
   ```

3. **SSL Certificate**
   - Install SSL certificate (Let's Encrypt recommended)
   - Force HTTPS in `.htaccess`:
     ```apache
     RewriteEngine On
     RewriteCond %{HTTPS} off
     RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
     ```

4. **Database**
   - Create production database
   - Import schema
   - Update connection credentials

5. **Payment Gateways**
   - Register webhooks with live URLs
   - Test with small real transactions

### Monitoring

- [ ] Set up error logging
- [ ] Monitor payment webhooks
- [ ] Track order fulfillment
- [ ] Monitor stock levels
- [ ] Check system performance

### Backup Strategy

1. **Database Backups**
   - Daily automated backups
   - Weekly full backups
   - Monthly archive backups

2. **File Backups**
   - Backup product images
   - Backup configuration files
   - Version control with Git

3. **Backup Location**
   - Store offsite (cloud storage)
   - Keep 30 days of backups
   - Test restore procedures

---

## Troubleshooting

### Common Issues

#### 1. Database Connection Failed

**Error**: "Database connection failed"

**Solutions**:
- Verify MySQL is running
- Check database credentials
- Ensure database exists
- Check PHP MySQL extension is enabled

#### 2. Cannot Upload Images

**Error**: "Failed to upload image"

**Solutions**:
- Check directory exists: `assets/images/products/`
- Verify directory permissions (755)
- Check PHP `upload_max_filesize` and `post_max_size` settings
- Ensure file is under 5MB and correct format

#### 3. Payments Not Working

**M-Pesa**:
- Verify API credentials are correct
- Check callback URL is accessible
- Ensure phone number format is correct (254XXXXXXXXX)
- Check ngrok is running (local testing)

**Paystack**:
- Verify API keys are correct
- Check callback URL is HTTPS
- Verify webhook signature validation
- Check Paystack dashboard for errors

#### 4. Session Issues

**Error**: "Unauthorized access" or auto-logout

**Solutions**:
- Check session configuration in `php.ini`
- Verify `session_start()` is called
- Clear browser cookies
- Check session directory permissions

#### 5. CSRF Token Errors

**Error**: "Invalid security token"

**Solutions**:
- Clear browser cache and cookies
- Verify session is active
- Check CSRF token generation
- Refresh the page and try again

---

## Support & Resources

### Documentation

- **Admin Guide**: `ADMIN_GUIDE.md`
- **Payment Integration**: `config/includes.php/payments/README.md`
- **Database Schema**: `databases/schema.sql`

### External Resources

- **M-Pesa Daraja API**: https://developer.safaricom.co.ke/
- **Paystack API**: https://paystack.com/docs
- **PHP Manual**: https://www.php.net/manual/
- **MySQL Documentation**: https://dev.mysql.com/doc/

### Getting Help

For technical support:
1. Check error logs: `config/storage/logs/`
2. Review documentation
3. Contact system administrator

---

## Next Steps

After successful setup:

1. **Customize Design**
   - Update logo and branding
   - Customize colors in `assets/css/style.css`
   - Add company information

2. **Add Content**
   - Add real products
   - Write compelling descriptions
   - Upload high-quality images
   - Create product categories

3. **Configure Settings**
   - Set delivery fees
   - Configure tax rates (if needed)
   - Set up email notifications
   - Configure SMS notifications (optional)

4. **Marketing**
   - Set up Google Analytics
   - Configure SEO meta tags
   - Add social media links
   - Create promotional campaigns

5. **Training**
   - Train admin staff
   - Train attendants
   - Create user manuals
   - Document procedures

---

**Last Updated**: October 23, 2026
**Version**: 1.0.0
