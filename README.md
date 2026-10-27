# 🛠️ Jengo Hardware - E-Commerce Platform

A complete, production-ready e-commerce platform for hardware stores built with PHP, MySQL, and integrated payment gateways (M-Pesa and Paystack).

![Version](https://img.shields.io/badge/version-1.1.0-blue.svg)
![PHP](https://img.shields.io/badge/PHP-7.4%2B-777BB4.svg)
![MySQL](https://img.shields.io/badge/MySQL-5.7%2B-4479A1.svg)
![License](https://img.shields.io/badge/license-Proprietary-red.svg)

---

## 📋 Table of Contents

- [Features](#features)
- [Screenshots](#screenshots)
- [Requirements](#requirements)
- [Installation](#installation)
- [Configuration](#configuration)
- [Usage](#usage)
- [Payment Integration](#payment-integration)
- [Security](#security)
- [Documentation](#documentation)
- [Troubleshooting](#troubleshooting)
- [Contributing](#contributing)
- [License](#license)

---

## ✨ Features

### Customer Features
- 🛍️ **Product Browsing** - Browse products with category filters and search
- 🛒 **Shopping Cart** - Add/remove items with real-time updates
- 💳 **Multiple Payment Options** - M-Pesa (mobile money) and Paystack (cards/bank)
- 📦 **Order Tracking** - Real-time order status updates
- 👤 **Account Management** - Profile updates, password change
- 📱 **Responsive Design** - Works on all devices

### Admin Features
- 📊 **Dashboard** - Revenue stats, order metrics, low stock alerts
- 🏷️ **Product Management** - Full CRUD with image uploads
- 📁 **Category Management** - Organize products efficiently
- 📦 **Order Management** - Update order and payment status
- 👥 **User Management** - Manage roles and access control
- 📈 **Reports** - Revenue, payment methods, order analytics

### Attendant Features
- 📋 **Order Processing** - View and update order status
- 📊 **Inventory Management** - Stock checks with alerts
- ⚠️ **Low Stock Alerts** - Proactive inventory monitoring

### Technical Features
- 🔐 **Security** - CSRF protection, SQL injection prevention, XSS protection
- 💰 **Payment Integration** - M-Pesa STK Push, Paystack webhooks
- 🎨 **Modern UI** - Clean, responsive design with modal dialogs
- 📝 **Logging** - Comprehensive payment and error logging
- 🔄 **Idempotent Webhooks** - Prevents duplicate payment processing
- 🔑 **Role-Based Access** - Customer, Attendant, Admin roles

---

## 📸 Screenshots

### Customer Experience
- **Home Page** - Hero section with featured products
- **Shop** - Product catalog with filters and search
- **Product Details** - Images, descriptions, related products
- **Cart** - Review items before checkout
- **Checkout** - Delivery options and payment selection

### Admin Dashboard
- **Overview** - Key metrics and statistics
- **Products** - Add/edit products with image upload
- **Orders** - Manage order lifecycle
- **Reports** - Business analytics

---

## 💻 Requirements

### Server Requirements
- **PHP** 7.4 or higher
- **MySQL** 5.7 or higher
- **Apache** Web Server
- **PHP Extensions**:
  - PDO
  - mbstring
  - openssl
  - curl
  - json
  - fileinfo

### Recommended
- **XAMPP** / LAMP / WAMP / MAMP
- **ngrok** (for local webhook testing)
- **Git** (for version control)

---

## 🚀 Installation

### Step 1: Clone/Download

```bash
# Clone repository (if using Git)
git clone https://github.com/yourusername/jengo-hardware.git

# Or download and extract to web server directory
# Windows (XAMPP): c:\xampp\htdocs\Jengo Hardware\
# Mac (MAMP): /Applications/MAMP/htdocs/Jengo Hardware/
# Linux (LAMP): /var/www/html/Jengo Hardware/
```

### Step 2: Database Setup

1. Create database:
```sql
CREATE DATABASE jengo_hardware CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
```

2. Import schema:
```bash
mysql -u root -p jengo_hardware < databases/schema.sql
```

Or use phpMyAdmin:
- Open `http://localhost/phpmyadmin`
- Select `jengo_hardware` database
- Import `databases/schema.sql`

### Step 3: Configuration

1. Copy environment file:
```bash
cp .env.example .env
```

2. Update `.env` with your settings:
```env
DB_USER=root
DB_PASS=your_password

MPESA_CONSUMER_KEY=your_key
MPESA_CONSUMER_SECRET=your_secret

PAYSTACK_PUBLIC_KEY=pk_test_xxxxx
PAYSTACK_SECRET_KEY=sk_test_xxxxx
```

3. Update payment config files:
- `config/includes.php/payments/mpesa-config.php`
- `config/includes.php/payments/paystack-config.php`

### Step 4: Set Permissions

```bash
# Linux/Mac
chmod 755 assets/images/products/
chmod 755 config/storage/logs/

# Windows: Right-click folders → Properties → Security → Allow Write
```

### Step 5: Access Application

```
http://localhost/Jengo%20Hardware/
```

**Default Test Accounts:**
| Email | Password | Role |
|-------|----------|------|
| admin@jengo.com | password123 | Admin |
| attendant@jengo.com | password123 | Attendant |
| customer@jengo.com | password123 | Customer |

---

## ⚙️ Configuration

### Database Configuration

Update in all PHP files:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', 'your_password');
define('DB_NAME', 'jengo_hardware');
```

### M-Pesa Setup

1. Register at [Safaricom Daraja](https://developer.safaricom.co.ke/)
2. Create an app (sandbox or production)
3. Get Consumer Key and Secret
4. Update `config/includes.php/payments/mpesa-config.php`

**Test Credentials (Sandbox):**
- Phone: 254708374149
- Test user details provided by Safaricom

### Paystack Setup

1. Register at [Paystack](https://paystack.com/)
2. Get API keys from Settings → API Keys
3. Update `config/includes.php/payments/paystack-config.php`

**Test Cards:**
- Card: 4084084084084081
- CVV: 408
- Expiry: Any future date

### Webhook Testing (Local Development)

1. Install ngrok:
```bash
ngrok http 80
```

2. Update callback URLs with ngrok HTTPS URL:
```
https://abc123.ngrok.io/Jengo%20Hardware/config/includes.php/payments/mpesa-callback.php
```

3. Configure webhook in Paystack dashboard

---

## 📖 Usage

### For Customers

1. **Browse Products**
   - Visit shop page
   - Filter by category
   - Search products

2. **Add to Cart**
   - Click "Add to Cart"
   - Update quantities
   - Proceed to checkout

3. **Checkout**
   - Select delivery method
   - Enter delivery address
   - Choose payment method
   - Complete payment

4. **Track Orders**
   - View order history in dashboard
   - Track order status
   - Download receipts

### For Attendants

1. **Process Orders**
   - View pending orders
   - Update order status
   - Mark as shipped/delivered

2. **Check Inventory**
   - View stock levels
   - Identify low stock items
   - Alert admin for restocking

### For Admins

1. **Manage Products**
   - Add new products with images
   - Update prices and stock
   - Organize into categories

2. **Process Orders**
   - Update order status
   - Update payment status
   - View order details

3. **Manage Users**
   - Update user roles
   - Activate/deactivate accounts
   - Monitor user activity

4. **View Reports**
   - Revenue analytics
   - Payment method breakdown
   - Order status distribution

---

## 💳 Payment Integration

### M-Pesa (Safaricom)

**Features:**
- STK Push (customer initiated)
- Real-time callbacks
- Transaction verification
- Receipt generation

**Flow:**
```
Customer → Checkout → M-Pesa Payment →
  STK Push → Customer enters PIN →
  Callback → Payment Confirmed → Order Updated
```

### Paystack

**Features:**
- Cards (Visa, Mastercard)
- Bank transfers
- USSD
- Mobile money
- Webhook verification

**Flow:**
```
Customer → Checkout → Paystack Payment →
  Enter Card Details → Paystack Processes →
  Webhook → Payment Confirmed → Order Updated
```

### Security Measures

- ✅ HMAC signature verification (Paystack)
- ✅ Amount verification (both)
- ✅ Idempotent processing (prevents duplicates)
- ✅ Safe logging (sensitive data redacted)
- ✅ Database transactions (atomic operations)

---

## 🔐 Security

### Authentication & Authorization
- Bcrypt password hashing (cost 12)
- Session-based authentication
- Role-based access control (RBAC)
- CSRF token validation

### Data Protection
- SQL injection prevention (prepared statements)
- XSS protection (htmlspecialchars)
- Input sanitization
- Output encoding

### Payment Security
- Webhook signature verification
- Amount validation
- Idempotent processing
- Secure credential storage

### File Upload Security
- Type validation (JPG, PNG, GIF, WebP)
- Size limits (5MB max)
- Secure file naming
- Directory permissions

### Best Practices
- ✅ Use HTTPS in production
- ✅ Keep credentials in `.env` (not committed)
- ✅ Regular security updates
- ✅ Error logging (not display)
- ✅ Rate limiting on sensitive endpoints

---

## 📚 Documentation

### Complete Guides

- **[Setup Guide](SETUP_GUIDE.md)** - Complete installation instructions
- **[Admin Guide](ADMIN_GUIDE.md)** - Admin and attendant documentation
- **[Changelog](CHANGELOG.md)** - Version history and updates
- **[Payment Integration](config/includes.php/payments/README.md)** - Payment setup guide

### Quick Links

- **Admin Dashboard**: `/admin.php`
- **Attendant Dashboard**: `/attendant.php`
- **Customer Dashboard**: `/customer.php`
- **Shop**: `/shop.php`
- **API Docs**: `/api-documentation.md` (coming soon)

---

## 🐛 Troubleshooting

### Common Issues

#### Database Connection Failed
```
Solution:
1. Verify MySQL is running
2. Check credentials in PHP files
3. Ensure database exists
4. Test connection: mysql -u root -p
```

#### Cannot Upload Images
```
Solution:
1. Check directory exists: assets/images/products/
2. Set permissions: chmod 755 assets/images/products/
3. Verify PHP upload_max_filesize setting
4. Check file is under 5MB
```

#### Payment Webhooks Not Working
```
Solution (M-Pesa):
1. Verify callback URL is accessible
2. Check ngrok is running (local)
3. Verify phone number format (254XXXXXXXXX)
4. Check API credentials

Solution (Paystack):
1. Ensure callback URL is HTTPS
2. Verify webhook signature validation
3. Check webhook URL in Paystack dashboard
4. Review Paystack logs
```

#### Session Issues
```
Solution:
1. Clear browser cookies
2. Check session_start() is called
3. Verify session directory permissions
4. Restart web server
```

### Error Logs

Check logs for detailed errors:
```
config/storage/logs/payment.log
config/storage/logs/error.log
```

---

## 🤝 Contributing

### Development Workflow

1. **Fork the repository**
2. **Create feature branch**: `git checkout -b feature/amazing-feature`
3. **Commit changes**: `git commit -m 'Add amazing feature'`
4. **Push to branch**: `git push origin feature/amazing-feature`
5. **Open Pull Request**

### Coding Standards

- Follow PSR-12 coding standards
- Comment complex logic
- Write descriptive commit messages
- Test thoroughly before submitting

### Testing Checklist

- [ ] Authentication works (all roles)
- [ ] CRUD operations functional
- [ ] Payments process correctly
- [ ] Security measures in place
- [ ] No SQL injection vulnerabilities
- [ ] XSS protection working
- [ ] Mobile responsive
- [ ] Cross-browser compatible

---

## 📊 Project Structure

```
Jengo Hardware/
├── assets/
│   ├── css/
│   │   └── style.css
│   ├── js/
│   │   └── app.js
│   └── images/
│       └── products/
├── config/
│   ├── includes.php/
│   │   └── payments/
│   │       ├── mpesa.php
│   │       ├── mpesa-config.php
│   │       ├── paystack.php
│   │       └── paystack-config.php
│   └── storage/
│       └── logs/
├── databases/
│   └── schema.sql
├── includes/
│   ├── header.php
│   ├── footer.php
│   ├── admin-modals.php
│   └── attendant-modals.php
├── index.php
├── auth.php
├── shop.php
├── product.php
├── cart.php
├── checkout.php
├── order-confirmation.php
├── payment-status.php
├── admin.php
├── admin-actions.php
├── attendant.php
├── attendant-actions.php
├── customer.php
├── actions.php
├── .env.example
├── .gitignore
├── README.md
├── SETUP_GUIDE.md
├── ADMIN_GUIDE.md
└── CHANGELOG.md
```

---

## 📈 Roadmap

### Version 1.2.0
- [ ] Email notifications
- [ ] SMS notifications (Africa's Talking)
- [ ] Advanced reporting with charts
- [ ] Export reports (CSV/PDF)
- [ ] Product image gallery
- [ ] Discount codes

### Version 1.3.0
- [ ] Bulk product import/export
- [ ] Advanced search filters
- [ ] Customer reviews
- [ ] Wishlist
- [ ] Product recommendations
- [ ] Multi-language support

---

## 📞 Support

### Get Help

- **Email**: support@jengohardware.com
- **Documentation**: Check guides in `/docs`
- **Issues**: Open issue on GitHub
- **Community**: Join our Discord/Slack

### Professional Support

For enterprise support and custom development:
- **Email**: enterprise@jengohardware.com
- **Phone**: +254 700 000 000

---

## 📄 License

This project is proprietary software owned by Jengo Hardware.

**Copyright © 2026 Jengo Hardware. All rights reserved.**

Unauthorized copying, modification, distribution, or use of this software is strictly prohibited without explicit written permission from Jengo Hardware.

---

## 🙏 Acknowledgments

- **Safaricom Daraja API** - M-Pesa integration
- **Paystack** - Payment processing
- **PHP Community** - Libraries and resources
- **Contributors** - All who helped build this platform

---

## 📊 Stats

- **Version**: 1.1.0
- **Last Updated**: October 23, 2026
- **PHP Version**: 7.4+
- **Database**: MySQL 5.7+
- **Lines of Code**: ~15,000+
- **Files**: 50+

---

## 🚀 Quick Start

```bash
# 1. Clone repository
git clone https://github.com/yourusername/jengo-hardware.git

# 2. Import database
mysql -u root -p jengo_hardware < databases/schema.sql

# 3. Configure environment
cp .env.example .env
# Edit .env with your settings

# 4. Set permissions
chmod 755 assets/images/products/

# 5. Access application
# http://localhost/Jengo%20Hardware/

# 6. Login
# Email: admin@jengo.com
# Password: password123
```

---

**Built with ❤️ for hardware store owners**

**Happy Selling! 🛠️**
