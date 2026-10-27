# Jengo Hardware - Admin & Attendant Guide

Complete guide for using the admin and attendant dashboards.

## Table of Contents

1. [Admin Dashboard](#admin-dashboard)
2. [Attendant Dashboard](#attendant-dashboard)
3. [Product Management](#product-management)
4. [Category Management](#category-management)
5. [Order Management](#order-management)
6. [User Management](#user-management)
7. [Reports](#reports)

---

## Admin Dashboard

### Access
- URL: `http://localhost/Jengo%20Hardware/admin.php`
- Login with admin account
- Auto-redirected if logged in as admin

### Features
- **Overview**: Revenue stats, order stats, recent orders, low stock alerts
- **Orders**: View all orders, filter by status, update order and payment status
- **Products**: Add, edit, delete products, manage stock
- **Categories**: Add, edit, delete categories
- **Users**: View users, update roles and status
- **Reports**: Revenue, payment methods, order status breakdowns

---

## Attendant Dashboard

### Access
- URL: `http://localhost/Jengo%20Hardware/attendant.php`
- Login with attendant account
- Auto-redirected if logged in as attendant

### Features
- **Overview**: Order statistics, inventory alerts
- **Orders**: View and update order status
- **Inventory**: Check stock levels, view low stock and out-of-stock items

---

## Product Management

### Add Product

1. Go to **Admin Dashboard → Products**
2. Click **"+ Add Product"**
3. Fill in product details:
   - **Product Name*** (required)
   - **SKU*** (required, must be unique)
   - **Price*** (required, in KSh)
   - **Stock*** (required, cannot be negative)
   - **Category** (optional)
   - **Status*** (active/inactive)
   - **Description** (optional)
   - **Product Image** (optional, max 5MB, JPG/PNG/GIF/WebP)
4. Click **"Save Product"**

### Edit Product

1. Find product in products list
2. Click **"Edit"** button
3. Update fields as needed
4. Click **"Save Product"**

### Delete Product

1. Find product in products list
2. Click **"Delete"** button
3. Confirm deletion
4. Product status is set to "inactive" (soft delete)

### Stock Indicators

- **Green/Good**: Stock ≥ 10 units
- **Yellow/Low**: Stock < 10 units
- **Red/Out**: Stock = 0 units

### Filters

- **All Products**: View all active products
- **Low Stock**: Products with stock < 10
- **Out of Stock**: Products with stock = 0

---

## Category Management

### Add Category

1. Go to **Admin Dashboard → Categories**
2. Click **"+ Add Category"**
3. Fill in category details:
   - **Category Name*** (required, must be unique)
   - **Description** (optional)
   - **Status*** (active/inactive)
4. Click **"Save Category"**

### Edit Category

1. Find category in categories grid
2. Click **"Edit"** button
3. Update fields as needed
4. Click **"Save Category"**

### Delete Category

1. Find category in categories grid
2. Click **"Delete"** button
3. Confirm deletion
4. **Note**: Cannot delete categories with active products

---

## Order Management

### View Orders

**Admin Dashboard:**
- View all orders with full details
- Filter by status: All, Pending, Processing, Shipped, Delivered
- Search by order number, customer name, or email

**Attendant Dashboard:**
- View orders
- Filter by status
- Update order status

### Update Order Status

1. Find order in orders list
2. Click **"Status"** button (Admin) or **"Update Status"** button (Attendant)
3. Select new status:
   - **Pending**: Order placed, awaiting processing
   - **Processing**: Order being prepared
   - **Shipped**: Order shipped to customer
   - **Delivered**: Order received by customer
   - **Cancelled**: Order cancelled
4. Click **"Update Status"**

### Update Payment Status (Admin Only)

1. Find order in orders list
2. Click **"Payment"** button
3. Select new payment status:
   - **Pending**: Payment not received
   - **Paid**: Payment confirmed
   - **Failed**: Payment failed
   - **Refunded**: Payment refunded
4. Click **"Update Status"**

### Order Workflow

```
Pending → Processing → Shipped → Delivered
             ↓
          Cancelled (at any stage)
```

### Payment Workflow

```
Pending → Paid
   ↓
Failed → Refunded (optional)
```

---

## User Management

### View Users

- Go to **Admin Dashboard → Users**
- View all registered users with roles and status

### Update User Status

1. Find user in users list
2. Click **"Status"** button
3. Select new status:
   - **Active**: User can log in and use the system
   - **Inactive**: User cannot log in
   - **Suspended**: User is temporarily blocked
4. Click **"Update Status"**
5. **Note**: Cannot change your own status

### Update User Role

1. Find user in users list
2. Click **"Role"** button
3. Select new role:
   - **Customer**: Can browse, shop, place orders
   - **Attendant**: Can manage orders and inventory
   - **Admin**: Full system access
4. Click **"Update Role"**
5. **Note**: Cannot change your own role

---

## Reports

### Revenue Overview

- **Total Revenue**: All-time revenue from paid orders
- **Last 7 Days**: Revenue from past week
- **Last 30 Days**: Revenue from past month

### Payment Methods

- Shows breakdown by payment method (M-Pesa, Paystack)
- Number of orders per method
- Total revenue per method

### Order Status

- Count of orders by status
- Helps identify bottlenecks in order processing

---

## Security Features

### CSRF Protection

All forms include CSRF tokens to prevent cross-site request forgery attacks.

### Role-Based Access Control

- Customers: Shopping and order tracking only
- Attendants: Order processing and inventory checks
- Admins: Full system access

### Validation

- Server-side validation for all inputs
- Unique SKU enforcement
- Price and stock validation
- Email format validation
- File upload restrictions (type, size)

### Soft Deletes

Products and categories are set to "inactive" rather than permanently deleted, preserving historical data.

---

## File Uploads

### Product Images

- **Location**: `assets/images/products/`
- **Max Size**: 5MB
- **Formats**: JPG, PNG, GIF, WebP
- **Naming**: Automatic unique filename generation (`product_[uniqid].[ext]`)

### Upload Process

1. File type validation
2. File size check
3. Secure upload to designated directory
4. Path stored in database

---

## Troubleshooting

### Cannot Delete Category

**Error**: "Cannot delete category with active products"

**Solution**: Either delete/deactivate all products in the category first, or reassign them to another category.

### Image Upload Fails

**Possible Causes**:
- File size exceeds 5MB
- Invalid file format
- Directory permissions

**Solution**:
1. Check file size and format
2. Ensure `assets/images/products/` directory exists
3. Verify directory has write permissions (755)

### SKU Already Exists

**Error**: "SKU already exists"

**Solution**: SKUs must be unique. Use a different SKU or check if the product already exists.

### Cannot Change Own Role/Status

**Error**: "Cannot change your own role/status"

**Solution**: For security, admins cannot change their own role or status. Ask another admin to make changes.

---

## Best Practices

### Product Management

1. Use descriptive, unique SKUs (e.g., `HW-001`, `HAMMER-001`)
2. Keep product descriptions concise but informative
3. Upload high-quality product images
4. Set accurate stock levels
5. Assign products to appropriate categories

### Order Processing

1. Update order status promptly
2. Confirm payment status before shipping
3. Mark orders as "Shipped" when dispatched
4. Mark as "Delivered" after customer confirmation

### Inventory Management

1. Check "Low Stock" alerts daily
2. Restock items before they run out
3. Update stock levels after receiving inventory
4. Deactivate discontinued products rather than deleting

### User Management

1. Assign roles carefully
2. Use "Suspended" for temporary blocks
3. Use "Inactive" for permanent deactivation
4. Regular review of user accounts

---

## Technical Details

### Files

- `admin.php` - Admin dashboard
- `admin-actions.php` - Admin action handlers
- `attendant.php` - Attendant dashboard
- `attendant-actions.php` - Attendant action handlers
- `includes/admin-modals.php` - Admin modal dialogs
- `includes/attendant-modals.php` - Attendant modal dialogs

### Database Tables

- `products` - Product information
- `categories` - Product categories
- `orders` - Customer orders
- `order_items` - Order line items
- `users` - User accounts

### Security

- PDO prepared statements for SQL injection prevention
- CSRF token validation on all forms
- Role-based access control
- Session-based authentication
- Password hashing with bcrypt (cost 12)
- XSS prevention with `htmlspecialchars()`

---

## Support

For technical support or feature requests, please contact the system administrator.

---

**Last Updated**: October 23, 2026
**Version**: 1.0.0
