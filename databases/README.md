# Jengo Hardware - Database Documentation

## Overview

This directory contains the complete database schema for the Jengo Hardware e-commerce platform.

## Files

- **schema.sql** - Complete database setup script with tables, indexes, views, and initial data

## Quick Start

### Option 1: Command Line (Recommended)

```bash
# Create database and import schema
mysql -u root -p < databases/schema.sql
```

### Option 2: phpMyAdmin

1. Open phpMyAdmin: `http://localhost/phpmyadmin`
2. Click **"Import"** tab
3. Choose file: `databases/schema.sql`
4. Click **"Go"**
5. Verify database `jengo_hardware` is created

### Option 3: MySQL Workbench

1. Open MySQL Workbench
2. File → Run SQL Script
3. Select `databases/schema.sql`
4. Execute

---

## Database Structure

### Tables Overview

| Table | Purpose | Key Features |
|-------|---------|--------------|
| **users** | User accounts | Bcrypt passwords, role-based access |
| **categories** | Product categories | Hierarchical organization |
| **products** | Product catalog | Stock tracking, SKU management |
| **orders** | Customer orders | Status workflow, delivery options |
| **order_items** | Order line items | Price/product snapshots |
| **mpesa_transactions** | M-Pesa payments | Transaction tracking, idempotency |
| **paystack_transactions** | Paystack payments | Card/bank payment records |

### Relationships

```
users (1) ----< orders (many)
    └─ Customers can place multiple orders

categories (1) ----< products (many)
    └─ Each product belongs to one category

products (1) ----< order_items (many)
    └─ Products can appear in multiple orders

orders (1) ----< order_items (many)
    └─ Each order contains multiple items
```

---

## Initial Data

### Default Users

| Role | Email | Password | Status |
|------|-------|----------|--------|
| **Admin** | admin@jengohardware.com | `Admin@2026!` | Active |
| **Attendant** | attendant@jengohardware.com | `Attendant@2026!` | Active |
| **Customer** | customer@example.com | `Customer@2026!` | Active (Test Only) |

⚠️ **CRITICAL: Change all default passwords immediately after first login!**

### Sample Data Included

- ✅ **8 Categories**: Power Tools, Hand Tools, Building Materials, Plumbing, Electrical, Paint & Finishing, Garden & Outdoor, Safety Equipment
- ✅ **20 Products**: Realistic hardware inventory with stock levels
- ✅ **2 Sample Orders**: One completed (paid) and one pending
- ✅ **Payment Records**: Sample M-Pesa transaction data

---

## Security Features

### Password Hashing

All passwords use bcrypt with cost factor 12:

```php
// Generate new password hash
$hash = password_hash('YourPassword', PASSWORD_BCRYPT, ['cost' => 12]);
```

### Role-Based Access Control

- **customer**: Shopping, order tracking, profile management
- **attendant**: Order processing, inventory checks
- **admin**: Full system access, user management, reports

### Data Integrity

- Foreign key constraints prevent orphaned records
- Triggers prevent negative stock levels
- Unique indexes on emails and SKUs
- ON DELETE behaviors preserve historical data

---

## Database Views

### vw_order_summary
Complete order information with customer details and item counts.

```sql
SELECT * FROM vw_order_summary
WHERE payment_status = 'paid'
ORDER BY created_at DESC;
```

### vw_product_inventory
Product stock status with category information.

```sql
SELECT * FROM vw_product_inventory
WHERE stock_status = 'Low Stock';
```

### vw_sales_summary
Daily sales statistics and revenue.

```sql
SELECT * FROM vw_sales_summary
WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 30 DAY);
```

---

## Stored Procedures

### sp_get_low_stock_products

Get products below a stock threshold:

```sql
CALL sp_get_low_stock_products(10);
```

### sp_customer_order_history

Get order history for a specific customer:

```sql
CALL sp_customer_order_history(3);
```

---

## Indexes

### Primary Indexes

All tables have auto-incrementing primary keys for optimal performance.

### Secondary Indexes

Optimized for common queries:

- **Email lookups**: `idx_email` on users table
- **Order searches**: `idx_order_number` on orders
- **Product searches**: `idx_sku`, `idx_name` on products
- **Status filtering**: `idx_status` on multiple tables
- **Date ranges**: `idx_created_at` on transactional tables

### Composite Indexes

For complex queries:

- `idx_orders_date_status` - Dashboard queries
- `idx_products_cat_stock` - Inventory reports
- `idx_composite_status` - Order filtering

---

## Data Types

### Money Values

All monetary values use `DECIMAL(10, 2)`:
- Precise to 2 decimal places
- No floating-point errors
- Max value: 99,999,999.99 KES

### Timestamps

- **created_at**: Record creation time
- **updated_at**: Auto-updates on record changes
- **Timezone**: Server timezone (should be Africa/Nairobi)

### JSON Fields

- **shipping_address**: Flexible address storage
- **metadata**: Payment gateway metadata

---

## Maintenance

### Daily Tasks

```sql
-- Check for failed transactions
SELECT * FROM mpesa_transactions 
WHERE status = 'failed' 
AND created_at > DATE_SUB(NOW(), INTERVAL 1 DAY);

-- Monitor low stock
SELECT * FROM vw_product_inventory 
WHERE stock_status IN ('Low Stock', 'Out of Stock');
```

### Weekly Tasks

```sql
-- Sales summary
SELECT * FROM vw_sales_summary 
WHERE sale_date >= DATE_SUB(CURDATE(), INTERVAL 7 DAY);

-- Top selling products
SELECT 
    p.name,
    SUM(oi.quantity) as total_sold,
    SUM(oi.subtotal) as revenue
FROM order_items oi
JOIN products p ON oi.product_id = p.id
JOIN orders o ON oi.order_id = o.id
WHERE o.payment_status = 'paid'
AND o.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY p.id
ORDER BY total_sold DESC
LIMIT 10;
```

### Monthly Tasks

```sql
-- Archive old failed transactions
DELETE FROM mpesa_transactions 
WHERE status = 'failed' 
AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

DELETE FROM paystack_transactions 
WHERE status = 'failed' 
AND created_at < DATE_SUB(NOW(), INTERVAL 90 DAY);

-- Optimize tables
OPTIMIZE TABLE users;
OPTIMIZE TABLE products;
OPTIMIZE TABLE orders;
OPTIMIZE TABLE order_items;
```

---

## Backup Procedures

### Manual Backup

```bash
# Full database backup
mysqldump -u root -p jengo_hardware > backup_$(date +%Y%m%d).sql

# Compress backup
gzip backup_$(date +%Y%m%d).sql
```

### Automated Backup Script

```bash
#!/bin/bash
# Save as: /usr/local/bin/backup-jengo.sh

BACKUP_DIR="/backups/jengo_hardware"
DATE=$(date +%Y%m%d_%H%M%S)
DB_NAME="jengo_hardware"
DB_USER="root"

mkdir -p $BACKUP_DIR

mysqldump -u $DB_USER -p$DB_PASS $DB_NAME | gzip > $BACKUP_DIR/backup_$DATE.sql.gz

# Keep only last 30 days
find $BACKUP_DIR -name "backup_*.sql.gz" -mtime +30 -delete

echo "Backup completed: $BACKUP_DIR/backup_$DATE.sql.gz"
```

### Restore from Backup

```bash
# Decompress
gunzip backup_20261023.sql.gz

# Restore
mysql -u root -p jengo_hardware < backup_20261023.sql
```

---

## Troubleshooting

### Issue: Foreign Key Constraint Errors

```sql
-- Disable foreign key checks temporarily
SET FOREIGN_KEY_CHECKS = 0;

-- Run your queries

-- Re-enable checks
SET FOREIGN_KEY_CHECKS = 1;
```

### Issue: Cannot Delete Category with Products

```sql
-- Option 1: Reassign products to another category
UPDATE products 
SET category_id = 1 
WHERE category_id = 5;

-- Option 2: Set products to inactive
UPDATE products 
SET status = 'inactive' 
WHERE category_id = 5;

-- Then delete category
UPDATE categories 
SET status = 'inactive' 
WHERE id = 5;
```

### Issue: Negative Stock

The trigger `trg_prevent_negative_stock` prevents this, but if you need to manually fix:

```sql
-- Find products with negative stock (shouldn't exist)
SELECT * FROM products WHERE stock < 0;

-- Reset to zero
UPDATE products SET stock = 0 WHERE stock < 0;
```

---

## Performance Optimization

### Check Query Performance

```sql
-- Enable query logging
SET GLOBAL slow_query_log = 'ON';
SET GLOBAL long_query_time = 2;

-- Check slow queries
SELECT * FROM mysql.slow_log;
```

### Analyze Table Usage

```sql
-- Show table sizes
SELECT 
    table_name,
    ROUND(((data_length + index_length) / 1024 / 1024), 2) AS "Size (MB)"
FROM information_schema.TABLES
WHERE table_schema = 'jengo_hardware'
ORDER BY (data_length + index_length) DESC;
```

### Index Usage Statistics

```sql
-- Show unused indexes
SELECT * FROM sys.schema_unused_indexes
WHERE object_schema = 'jengo_hardware';
```

---

## Schema Versioning

Current Version: **1.1.1**

### Version History

| Version | Date | Changes |
|---------|------|---------|
| 1.0.0 | Oct 2026 | Initial schema |
| 1.1.0 | Oct 2026 | Added views and procedures |
| 1.1.1 | Oct 2026 | Enhanced indexes, triggers |

### Schema Updates

When updating schema in production:

1. **Backup first!**
2. Test changes in development
3. Use ALTER TABLE (not DROP/CREATE)
4. Add indexes with ALGORITHM=INPLACE
5. Monitor performance during changes

---

## Migration Guide

### From Legacy System

If migrating from another system:

1. Export data from old system
2. Map fields to new schema
3. Import users first
4. Then categories and products
5. Finally orders and transactions
6. Verify foreign key relationships
7. Update sequences/auto-increment values

---

## Security Checklist

Before going live:

- [ ] Change all default passwords
- [ ] Update admin email address
- [ ] Remove test customer account
- [ ] Review user permissions
- [ ] Enable SSL for database connections
- [ ] Set up firewall rules
- [ ] Configure automatic backups
- [ ] Enable audit logging
- [ ] Test restore procedures
- [ ] Document access procedures

---

## Support

For database issues:

1. Check error logs: `/var/log/mysql/error.log`
2. Review slow query log
3. Verify disk space: `df -h`
4. Check MySQL status: `systemctl status mysql`
5. Contact database administrator

---

## Useful Queries

### Dashboard Statistics

```sql
-- Today's orders
SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE();

-- Today's revenue
SELECT SUM(total_amount) FROM orders 
WHERE payment_status = 'paid' 
AND DATE(created_at) = CURDATE();

-- Pending orders
SELECT COUNT(*) FROM orders WHERE status = 'pending';

-- Low stock products
SELECT COUNT(*) FROM products WHERE stock < 10 AND status = 'active';
```

### Customer Statistics

```sql
-- Total customers
SELECT COUNT(*) FROM users WHERE role = 'customer';

-- Active customers (placed order in last 30 days)
SELECT COUNT(DISTINCT user_id) FROM orders 
WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY);

-- Top customers by revenue
SELECT 
    u.name,
    u.email,
    COUNT(o.id) as order_count,
    SUM(o.total_amount) as total_spent
FROM users u
JOIN orders o ON u.id = o.user_id
WHERE o.payment_status = 'paid'
GROUP BY u.id
ORDER BY total_spent DESC
LIMIT 10;
```

---

**Database Version:** 1.1.1  
**Last Updated:** October 23, 2026  
**Maintained By:** Jengo Hardware Dev Team
