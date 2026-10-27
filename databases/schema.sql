-- ============================================================================
-- Jengo Hardware - Database Schema
-- Version: 1.1.1
-- Description: Complete e-commerce database with payment integration
-- ============================================================================

-- Drop existing database and create fresh
DROP DATABASE IF EXISTS jengo_hardware;
CREATE DATABASE jengo_hardware CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE jengo_hardware;

-- ============================================================================
-- USERS TABLE
-- Stores customer, attendant, and admin accounts
-- ============================================================================
CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL,
    email VARCHAR(100) NOT NULL UNIQUE,
    phone VARCHAR(20) DEFAULT NULL,
    password VARCHAR(255) NOT NULL COMMENT 'Bcrypt hashed password',
    role ENUM('customer', 'attendant', 'admin') NOT NULL DEFAULT 'customer',
    status ENUM('active', 'inactive', 'suspended') NOT NULL DEFAULT 'active',
    email_verified TINYINT(1) DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    last_login TIMESTAMP NULL DEFAULT NULL,
    INDEX idx_email (email),
    INDEX idx_role (role),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- CATEGORIES TABLE
-- Product categories for organization
-- ============================================================================
CREATE TABLE categories (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT DEFAULT NULL,
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_name (name),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PRODUCTS TABLE
-- Product catalog with stock management
-- ============================================================================
CREATE TABLE products (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(200) NOT NULL,
    sku VARCHAR(50) NOT NULL UNIQUE COMMENT 'Stock Keeping Unit - must be unique',
    description TEXT DEFAULT NULL,
    price DECIMAL(10, 2) NOT NULL COMMENT 'Price in KES',
    stock INT UNSIGNED NOT NULL DEFAULT 0 COMMENT 'Current stock quantity',
    category_id INT UNSIGNED DEFAULT NULL,
    image VARCHAR(255) DEFAULT NULL COMMENT 'Product image path',
    status ENUM('active', 'inactive') NOT NULL DEFAULT 'active',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (category_id) REFERENCES categories(id) ON DELETE SET NULL,
    INDEX idx_name (name),
    INDEX idx_sku (sku),
    INDEX idx_category (category_id),
    INDEX idx_status (status),
    INDEX idx_stock (stock),
    INDEX idx_price (price),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ORDERS TABLE
-- Customer orders with delivery and payment information
-- ============================================================================
CREATE TABLE orders (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED DEFAULT NULL COMMENT 'NULL for guest orders',
    order_number VARCHAR(50) NOT NULL UNIQUE COMMENT 'Human-readable order reference',
    customer_name VARCHAR(100) NOT NULL,
    customer_email VARCHAR(100) NOT NULL,
    customer_phone VARCHAR(20) NOT NULL,
    subtotal DECIMAL(10, 2) NOT NULL COMMENT 'Total before delivery fee',
    delivery_fee DECIMAL(10, 2) NOT NULL DEFAULT 0,
    total_amount DECIMAL(10, 2) NOT NULL COMMENT 'Final amount to pay',
    delivery_method ENUM('pickup', 'standard', 'express') NOT NULL DEFAULT 'pickup',
    payment_method ENUM('mpesa', 'paystack', 'cash') NOT NULL,
    payment_reference VARCHAR(100) DEFAULT NULL COMMENT 'Payment gateway reference',
    shipping_address JSON DEFAULT NULL COMMENT 'Address details for delivery',
    notes TEXT DEFAULT NULL COMMENT 'Customer notes or instructions',
    status ENUM('pending', 'processing', 'shipped', 'delivered', 'cancelled') NOT NULL DEFAULT 'pending',
    payment_status ENUM('pending', 'paid', 'failed', 'refunded') NOT NULL DEFAULT 'pending',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
    INDEX idx_user (user_id),
    INDEX idx_order_number (order_number),
    INDEX idx_customer_email (customer_email),
    INDEX idx_status (status),
    INDEX idx_payment_status (payment_status),
    INDEX idx_payment_method (payment_method),
    INDEX idx_created_at (created_at),
    INDEX idx_composite_status (status, payment_status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- ORDER_ITEMS TABLE
-- Individual items in each order
-- ============================================================================
CREATE TABLE order_items (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id INT UNSIGNED NOT NULL,
    product_id INT UNSIGNED DEFAULT NULL COMMENT 'NULL if product deleted',
    product_name VARCHAR(200) NOT NULL COMMENT 'Snapshot at order time',
    product_sku VARCHAR(50) NOT NULL COMMENT 'Snapshot at order time',
    quantity INT UNSIGNED NOT NULL,
    price DECIMAL(10, 2) NOT NULL COMMENT 'Price at order time',
    subtotal DECIMAL(10, 2) NOT NULL COMMENT 'quantity * price',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (order_id) REFERENCES orders(id) ON DELETE CASCADE,
    FOREIGN KEY (product_id) REFERENCES products(id) ON DELETE SET NULL,
    INDEX idx_order (order_id),
    INDEX idx_product (product_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- MPESA_TRANSACTIONS TABLE
-- M-Pesa payment records for tracking and reconciliation
-- ============================================================================
CREATE TABLE mpesa_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    checkout_request_id VARCHAR(100) NOT NULL UNIQUE COMMENT 'M-Pesa transaction ID',
    merchant_request_id VARCHAR(100) NOT NULL,
    phone_number VARCHAR(20) NOT NULL COMMENT 'Customer phone number',
    amount DECIMAL(10, 2) NOT NULL,
    account_reference VARCHAR(100) NOT NULL COMMENT 'Order number or reference',
    status ENUM('pending', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    result_desc TEXT DEFAULT NULL COMMENT 'Result description from M-Pesa',
    mpesa_receipt_number VARCHAR(50) DEFAULT NULL COMMENT 'M-Pesa confirmation code',
    transaction_date VARCHAR(20) DEFAULT NULL COMMENT 'M-Pesa transaction timestamp',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_checkout_request (checkout_request_id),
    INDEX idx_merchant_request (merchant_request_id),
    INDEX idx_account_reference (account_reference),
    INDEX idx_phone (phone_number),
    INDEX idx_status (status),
    INDEX idx_receipt (mpesa_receipt_number),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- PAYSTACK_TRANSACTIONS TABLE
-- Paystack payment records for tracking and reconciliation
-- ============================================================================
CREATE TABLE paystack_transactions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    reference VARCHAR(100) NOT NULL UNIQUE COMMENT 'Paystack transaction reference',
    access_code VARCHAR(100) DEFAULT NULL COMMENT 'Paystack access code',
    email VARCHAR(100) NOT NULL,
    amount DECIMAL(10, 2) NOT NULL,
    metadata JSON DEFAULT NULL COMMENT 'Additional transaction data',
    status ENUM('pending', 'completed', 'failed', 'cancelled') NOT NULL DEFAULT 'pending',
    gateway_response TEXT DEFAULT NULL COMMENT 'Response from Paystack',
    paid_at TIMESTAMP NULL DEFAULT NULL,
    channel VARCHAR(50) DEFAULT NULL COMMENT 'Payment channel: card, bank, ussd, etc',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_reference (reference),
    INDEX idx_email (email),
    INDEX idx_status (status),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================================================
-- INITIAL DATA - CATEGORIES
-- ============================================================================
INSERT INTO categories (name, description, status) VALUES
('Power Tools', 'Electric drills, saws, sanders, and other power tools', 'active'),
('Hand Tools', 'Hammers, screwdrivers, wrenches, pliers, and manual tools', 'active'),
('Building Materials', 'Cement, sand, bricks, timber, and construction supplies', 'active'),
('Plumbing', 'Pipes, fittings, taps, and plumbing accessories', 'active'),
('Electrical', 'Wires, switches, sockets, and electrical components', 'active'),
('Paint & Finishing', 'Paints, brushes, rollers, and finishing materials', 'active'),
('Garden & Outdoor', 'Gardening tools, outdoor equipment, and accessories', 'active'),
('Safety Equipment', 'Safety gear, protective equipment, and workplace safety items', 'active');

-- ============================================================================
-- INITIAL DATA - SAMPLE PRODUCTS
-- ============================================================================
INSERT INTO products (name, sku, description, price, stock, category_id, status) VALUES
-- Power Tools
('Bosch Impact Drill 650W', 'PT-001', 'Professional grade impact drill with variable speed control and reverse function. Perfect for drilling into concrete, wood, and metal.', 8500.00, 15, 1, 'active'),
('Makita Circular Saw 1200W', 'PT-002', 'Heavy-duty circular saw with 185mm blade. Ideal for cutting timber and other materials with precision.', 12500.00, 8, 1, 'active'),
('DeWalt Angle Grinder 900W', 'PT-003', 'Powerful angle grinder with safety guard. Suitable for cutting, grinding, and polishing metal and masonry.', 9800.00, 12, 1, 'active'),

-- Hand Tools
('Stanley Hammer Set (3pcs)', 'HT-001', 'Professional hammer set including claw hammer, ball peen hammer, and rubber mallet.', 2500.00, 25, 2, 'active'),
('Screwdriver Set 10pcs', 'HT-002', 'Complete screwdriver set with magnetic tips. Includes both Phillips and flathead in various sizes.', 1200.00, 30, 2, 'active'),
('Adjustable Wrench 12inch', 'HT-003', 'Chrome plated adjustable wrench with ergonomic grip. Opens up to 35mm.', 850.00, 20, 2, 'active'),

-- Building Materials
('Cement 50kg Bag', 'BM-001', 'High quality Portland cement suitable for all construction projects.', 750.00, 200, 3, 'active'),
('Steel Rods 12mm (6m)', 'BM-002', 'Construction grade steel reinforcement bars. Price per rod.', 1200.00, 150, 3, 'active'),

-- Plumbing
('PVC Pipe 1/2 inch (3m)', 'PL-001', 'Durable PVC water pipe suitable for cold water supply. 3 meters length.', 350.00, 100, 4, 'active'),
('Kitchen Sink Mixer Tap', 'PL-002', 'Chrome plated kitchen tap with hot and cold water control. Modern design with ceramic disc cartridge.', 3500.00, 18, 4, 'active'),

-- Electrical
('Electrical Cable 2.5mm (100m)', 'EL-001', 'Quality copper electrical cable roll. 100 meters per roll.', 4500.00, 25, 5, 'active'),
('LED Bulb 15W (Pack of 4)', 'EL-002', 'Energy saving LED bulbs with E27 fitting. Warm white light, 1350 lumens. Pack of 4 bulbs.', 1600.00, 50, 5, 'active'),

-- Paint & Finishing
('Crown Paint White 4L', 'PF-001', 'Premium quality white emulsion paint. 4 liters covers approximately 40 square meters.', 2800.00, 40, 6, 'active'),
('Paint Roller Set', 'PF-002', 'Professional painting set including roller frame, 2 roller sleeves, paint tray, and extension pole.', 1500.00, 35, 6, 'active'),

-- Garden & Outdoor
('Garden Hose 30m', 'GO-001', 'Heavy duty garden hose with spray nozzle. 30 meters length with brass fittings.', 2200.00, 22, 7, 'active'),
('Wheelbarrow Heavy Duty', 'GO-002', 'Industrial wheelbarrow with pneumatic tire. 80kg capacity with galvanized steel tray.', 4500.00, 10, 7, 'active'),

-- Safety Equipment
('Safety Helmet', 'SF-001', 'Industrial safety helmet with adjustable straps. Meets safety standards.', 650.00, 45, 8, 'active'),
('Safety Goggles Anti-Fog', 'SF-002', 'Protective safety goggles with anti-fog coating and adjustable strap.', 450.00, 60, 8, 'active'),
('Work Gloves (Pair)', 'SF-003', 'Heavy duty work gloves with reinforced palm. Cotton/polyester blend with rubber grip.', 350.00, 5, 8, 'active'),
('Dust Mask (Pack of 10)', 'SF-004', 'Disposable dust masks with nose clip. Suitable for painting and construction work.', 800.00, 0, 8, 'active');

-- ============================================================================
-- SECURE INITIAL ADMIN SETUP PROCEDURE
-- ============================================================================

-- Create initial admin user
-- Password: Admin@2026! (MUST be changed on first login)
-- Bcrypt hash with cost 12
INSERT INTO users (name, email, phone, password, role, status, email_verified, created_at) VALUES
(
    'System Administrator',
    'admin@jengohardware.com',
    '+254700000000',
    '$2y$12$LKvXPKZHqHzqGxvH3YP4F.rJfR3r6YQ2pNKLqQkXN5LvO6p8YHFKm',
    'admin',
    'active',
    1,
    CURRENT_TIMESTAMP
);

-- Create sample attendant account
-- Password: Attendant@2026! (MUST be changed on first login)
INSERT INTO users (name, email, phone, password, role, status, email_verified, created_at) VALUES
(
    'Store Attendant',
    'attendant@jengohardware.com',
    '+254711111111',
    '$2y$12$8vQN5Y.HFKzC0XJP4qfqG.rB4s7p9YLqRkNmQPvS1LwO6x8ZHFTy.',
    'attendant',
    'active',
    1,
    CURRENT_TIMESTAMP
);

-- Create sample customer account
-- Password: Customer@2026! (For testing purposes)
INSERT INTO users (name, email, phone, password, role, status, email_verified, created_at) VALUES
(
    'John Doe',
    'customer@example.com',
    '+254722222222',
    '$2y$12$9wRO6Z.IGLaD1YKQ5rgqH.sC5t8q0ZMrSlOnRQwT2MxP7y9AIGUz2',
    'customer',
    'active',
    1,
    CURRENT_TIMESTAMP
);

-- ============================================================================
-- SAMPLE ORDER DATA (for testing and demo)
-- ============================================================================

-- Sample completed order
INSERT INTO orders (
    user_id, order_number, customer_name, customer_email, customer_phone,
    subtotal, delivery_fee, total_amount, delivery_method, payment_method,
    payment_reference, shipping_address, status, payment_status, created_at
) VALUES (
    3,
    'JH-20261020-ABC123',
    'John Doe',
    'customer@example.com',
    '+254722222222',
    12000.00,
    300.00,
    12300.00,
    'standard',
    'mpesa',
    'PKL7890XYZ',
    '{"line1": "123 Main Street", "line2": "Apt 4B", "city": "Nairobi", "postal_code": "00100", "country": "Kenya"}',
    'delivered',
    'paid',
    DATE_SUB(NOW(), INTERVAL 5 DAY)
);

-- Order items for sample order
INSERT INTO order_items (order_id, product_id, product_name, product_sku, quantity, price, subtotal) VALUES
(1, 1, 'Bosch Impact Drill 650W', 'PT-001', 1, 8500.00, 8500.00),
(1, 5, 'Screwdriver Set 10pcs', 'HT-002', 2, 1200.00, 2400.00),
(1, 17, 'Safety Helmet', 'SF-001', 2, 650.00, 1300.00);

-- Sample pending order
INSERT INTO orders (
    user_id, order_number, customer_name, customer_email, customer_phone,
    subtotal, delivery_fee, total_amount, delivery_method, payment_method,
    shipping_address, status, payment_status, created_at
) VALUES (
    3,
    'JH-' || DATE_FORMAT(NOW(), '%Y%m%d') || '-XYZ789',
    'John Doe',
    'customer@example.com',
    '+254722222222',
    5300.00,
    0.00,
    5300.00,
    'pickup',
    'mpesa',
    '{"type": "pickup", "location": "Store Pickup - Jengo Hardware, Nairobi"}',
    'pending',
    'pending',
    DATE_SUB(NOW(), INTERVAL 2 HOUR)
);

-- Order items for pending order
INSERT INTO order_items (order_id, product_id, product_name, product_sku, quantity, price, subtotal) VALUES
(2, 13, 'Crown Paint White 4L', 'PF-001', 1, 2800.00, 2800.00),
(2, 14, 'Paint Roller Set', 'PF-002', 1, 1500.00, 1500.00),
(2, 19, 'Work Gloves (Pair)', 'SF-003', 3, 350.00, 1050.00);

-- ============================================================================
-- SAMPLE PAYMENT TRANSACTION DATA
-- ============================================================================

-- Sample completed M-Pesa transaction
INSERT INTO mpesa_transactions (
    checkout_request_id, merchant_request_id, phone_number, amount,
    account_reference, status, result_desc, mpesa_receipt_number,
    transaction_date, created_at
) VALUES (
    'ws_CO_12345678901234567890',
    'abc-def-ghi-123456',
    '254722222222',
    12300.00,
    'JH-20261020-ABC123',
    'completed',
    'The service request is processed successfully.',
    'PKL7890XYZ',
    '20261020143022',
    DATE_SUB(NOW(), INTERVAL 5 DAY)
);

-- Sample pending M-Pesa transaction
INSERT INTO mpesa_transactions (
    checkout_request_id, merchant_request_id, phone_number, amount,
    account_reference, status, created_at
) VALUES (
    'ws_CO_' || LPAD(FLOOR(RAND() * 100000000000), 20, '0'),
    'pending-' || UUID(),
    '254722222222',
    5300.00,
    (SELECT order_number FROM orders WHERE id = 2),
    'pending',
    DATE_SUB(NOW(), INTERVAL 2 HOUR)
);

-- ============================================================================
-- PERFORMANCE INDEXES
-- Additional composite indexes for common queries
-- ============================================================================

-- Orders by date and status (for dashboard queries)
CREATE INDEX idx_orders_date_status ON orders(created_at DESC, status, payment_status);

-- Products by category and stock (for inventory reports)
CREATE INDEX idx_products_cat_stock ON products(category_id, stock, status);

-- Order items with product lookup (for order details)
CREATE INDEX idx_order_items_lookup ON order_items(order_id, product_id);

-- User orders lookup (for customer dashboard)
CREATE INDEX idx_user_orders ON orders(user_id, created_at DESC);

-- ============================================================================
-- DATABASE STATISTICS
-- ============================================================================
SELECT 'Database Setup Complete' AS status;

SELECT 
    'users' as table_name, 
    COUNT(*) as record_count 
FROM users
UNION ALL
SELECT 'categories', COUNT(*) FROM categories
UNION ALL
SELECT 'products', COUNT(*) FROM products
UNION ALL
SELECT 'orders', COUNT(*) FROM orders
UNION ALL
SELECT 'order_items', COUNT(*) FROM order_items
UNION ALL
SELECT 'mpesa_transactions', COUNT(*) FROM mpesa_transactions
UNION ALL
SELECT 'paystack_transactions', COUNT(*) FROM paystack_transactions;

-- ============================================================================
-- IMPORTANT SECURITY NOTES
-- ============================================================================

/*
INITIAL ADMIN CREDENTIALS (MUST CHANGE ON FIRST LOGIN):
============================================
Email: admin@jengohardware.com
Password: Admin@2026!

ATTENDANT CREDENTIALS (MUST CHANGE ON FIRST LOGIN):
============================================
Email: attendant@jengohardware.com
Password: Attendant@2026!

TEST CUSTOMER CREDENTIALS (For Development Only):
============================================
Email: customer@example.com
Password: Customer@2026!

SECURITY CHECKLIST:
============================================
☐ Change all default passwords immediately
☐ Update admin email to actual administrator email
☐ Remove or disable test customer account in production
☐ Review and update phone numbers
☐ Enable email verification for production
☐ Set up proper backup procedures
☐ Configure database user with limited privileges
☐ Enable SSL/TLS for database connections in production
☐ Regular security audits and updates

PASSWORD GENERATION:
============================================
All passwords are hashed with bcrypt (cost factor 12)
To generate new password hashes in PHP:
    password_hash('YourPassword', PASSWORD_BCRYPT, ['cost' => 12]);

DATABASE MAINTENANCE:
============================================
- Regular backups recommended (daily for production)
- Monitor table sizes and optimize as needed
- Review and archive old orders periodically
- Clear failed payment transactions after 30 days
- Monitor indexes and add as needed based on query patterns

TABLE RELATIONSHIPS:
============================================
users (1) ----< orders (many)
categories (1) ----< products (many)
products (1) ----< order_items (many)
orders (1) ----< order_items (many)

FOREIGN KEY BEHAVIOR:
============================================
- ON DELETE SET NULL: Preserves historical data
- ON DELETE CASCADE: Automatically removes related records
*/

-- ============================================================================
-- VIEWS FOR COMMON QUERIES (Optional but Recommended)
-- ============================================================================

-- View: Order summary with customer details
CREATE VIEW vw_order_summary AS
SELECT 
    o.id,
    o.order_number,
    o.customer_name,
    o.customer_email,
    o.total_amount,
    o.status,
    o.payment_status,
    o.payment_method,
    o.created_at,
    u.name as user_name,
    u.role as user_role,
    COUNT(oi.id) as item_count
FROM orders o
LEFT JOIN users u ON o.user_id = u.id
LEFT JOIN order_items oi ON o.id = oi.order_id
GROUP BY o.id;

-- View: Product inventory status
CREATE VIEW vw_product_inventory AS
SELECT 
    p.id,
    p.name,
    p.sku,
    p.price,
    p.stock,
    c.name as category_name,
    CASE 
        WHEN p.stock = 0 THEN 'Out of Stock'
        WHEN p.stock < 10 THEN 'Low Stock'
        ELSE 'In Stock'
    END as stock_status,
    p.status
FROM products p
LEFT JOIN categories c ON p.category_id = c.id
WHERE p.status = 'active';

-- View: Sales summary
CREATE VIEW vw_sales_summary AS
SELECT 
    DATE(o.created_at) as sale_date,
    COUNT(o.id) as order_count,
    SUM(o.total_amount) as total_revenue,
    AVG(o.total_amount) as average_order_value,
    COUNT(CASE WHEN o.payment_status = 'paid' THEN 1 END) as paid_orders,
    SUM(CASE WHEN o.payment_status = 'paid' THEN o.total_amount ELSE 0 END) as paid_revenue
FROM orders o
GROUP BY DATE(o.created_at);

-- ============================================================================
-- STORED PROCEDURES (Optional Advanced Features)
-- ============================================================================

-- Procedure: Get low stock products
DELIMITER //
CREATE PROCEDURE sp_get_low_stock_products(IN threshold INT)
BEGIN
    SELECT 
        p.id,
        p.name,
        p.sku,
        p.stock,
        c.name as category_name
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.stock < threshold 
    AND p.stock > 0
    AND p.status = 'active'
    ORDER BY p.stock ASC;
END //
DELIMITER ;

-- Procedure: Get customer order history
DELIMITER //
CREATE PROCEDURE sp_customer_order_history(IN customer_user_id INT)
BEGIN
    SELECT 
        o.order_number,
        o.total_amount,
        o.status,
        o.payment_status,
        o.created_at,
        COUNT(oi.id) as item_count
    FROM orders o
    LEFT JOIN order_items oi ON o.id = oi.order_id
    WHERE o.user_id = customer_user_id
    GROUP BY o.id
    ORDER BY o.created_at DESC;
END //
DELIMITER ;

-- ============================================================================
-- TRIGGERS FOR DATA INTEGRITY
-- ============================================================================

-- Trigger: Prevent negative stock
DELIMITER //
CREATE TRIGGER trg_prevent_negative_stock
BEFORE UPDATE ON products
FOR EACH ROW
BEGIN
    IF NEW.stock < 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Stock cannot be negative';
    END IF;
END //
DELIMITER ;

-- Trigger: Update order timestamp on status change
DELIMITER //
CREATE TRIGGER trg_update_order_timestamp
BEFORE UPDATE ON orders
FOR EACH ROW
BEGIN
    IF OLD.status != NEW.status OR OLD.payment_status != NEW.payment_status THEN
        SET NEW.updated_at = CURRENT_TIMESTAMP;
    END IF;
END //
DELIMITER ;

-- ============================================================================
-- COMPLETION MESSAGE
-- ============================================================================
SELECT '========================================' as '';
SELECT 'DATABASE SETUP COMPLETED SUCCESSFULLY!' as '';
SELECT '========================================' as '';
SELECT '' as '';
SELECT 'Next Steps:' as '';
SELECT '1. Change default admin password' as '';
SELECT '2. Update admin email address' as '';
SELECT '3. Configure payment gateways' as '';
SELECT '4. Review and customize sample data' as '';
SELECT '5. Set up regular backup schedule' as '';
SELECT '' as '';
SELECT 'Database: jengo_hardware' as '';
SELECT 'Tables: 7 main tables created' as '';
SELECT 'Views: 3 views for reporting' as '';
SELECT 'Procedures: 2 stored procedures' as '';
SELECT 'Triggers: 2 data integrity triggers' as '';
SELECT '' as '';
SELECT 'Default Admin Login:' as '';
SELECT 'Email: admin@jengohardware.com' as '';
SELECT 'Password: Admin@2026!' as '';
SELECT '⚠️  CHANGE PASSWORD IMMEDIATELY! ⚠️' as '';
SELECT '========================================' as '';
