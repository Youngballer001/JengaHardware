<?php
/**
 * Jengo Hardware - Actions Handler
 * Secure server-side handling of cart, profile, and order actions
 */

session_start();

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'jengo_hardware');

// Connect to database
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );
} catch (PDOException $e) {
    error_log("Database connection failed: " . $e->getMessage());
    jsonResponse(false, "System error. Please try again later.");
}

// Security: CSRF Token Validation
function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// JSON Response Helper
function jsonResponse($success, $message, $data = []) {
    header('Content-Type: application/json');
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit();
}

// Redirect Helper
function redirectWithMessage($location, $message, $type = 'error') {
    $_SESSION['redirect_message'] = $message;
    $_SESSION['redirect_type'] = $type;
    header("Location: $location");
    exit();
}

// Sanitize Input
function sanitize($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Get action
$action = $_POST['action'] ?? $_GET['action'] ?? '';

// Route actions
switch ($action) {
    
    // ============================================
    // CART ACTIONS
    // ============================================
    
    case 'add_to_cart':
        handleAddToCart($pdo);
        break;
        
    case 'update_cart':
        handleUpdateCart($pdo);
        break;
        
    case 'remove_from_cart':
        handleRemoveFromCart();
        break;
        
    case 'clear_cart':
        handleClearCart();
        break;
        
    case 'get_cart_count':
        handleGetCartCount();
        break;
    
    // ============================================
    // ORDER ACTIONS
    // ============================================
    
    case 'create_order':
        handleCreateOrder($pdo);
        break;
        
    case 'get_order_status':
        handleGetOrderStatus($pdo);
        break;
        
    case 'cancel_order':
        handleCancelOrder($pdo);
        break;
    
    // ============================================
    // PROFILE ACTIONS
    // ============================================
    
    case 'update_profile':
        handleUpdateProfile($pdo);
        break;
        
    case 'change_password':
        handleChangePassword($pdo);
        break;
    
    // ============================================
    // DEFAULT
    // ============================================
    
    default:
        jsonResponse(false, "Invalid action");
}

// ============================================
// CART ACTION HANDLERS
// ============================================

function handleAddToCart($pdo) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 1);
    
    if ($product_id <= 0 || $quantity <= 0) {
        jsonResponse(false, "Invalid product or quantity");
    }
    
    // Fetch product from database
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        jsonResponse(false, "Product not found");
    }
    
    // Initialize cart if not exists
    if (!isset($_SESSION['cart'])) {
        $_SESSION['cart'] = [];
    }
    
    // Check stock availability
    $existing_qty = $_SESSION['cart'][$product_id]['quantity'] ?? 0;
    $new_qty = $existing_qty + $quantity;
    
    if ($new_qty > $product['stock']) {
        jsonResponse(false, "Not enough stock available. Only {$product['stock']} items in stock.");
    }
    
    // Add or update cart
    if (isset($_SESSION['cart'][$product_id])) {
        $_SESSION['cart'][$product_id]['quantity'] = $new_qty;
    } else {
        $_SESSION['cart'][$product_id] = [
            'id' => $product['id'],
            'name' => $product['name'],
            'price' => $product['price'],
            'quantity' => $quantity,
            'image' => $product['image']
        ];
    }
    
    // Calculate cart count
    $cart_count = array_sum(array_column($_SESSION['cart'], 'quantity'));
    
    jsonResponse(true, "Product added to cart", ['cart_count' => $cart_count]);
}

function handleUpdateCart($pdo) {
    $product_id = (int)($_POST['product_id'] ?? 0);
    $quantity = (int)($_POST['quantity'] ?? 0);
    
    if ($product_id <= 0) {
        jsonResponse(false, "Invalid product");
    }
    
    if (!isset($_SESSION['cart'][$product_id])) {
        jsonResponse(false, "Product not in cart");
    }
    
    // If quantity is 0, remove item
    if ($quantity <= 0) {
        unset($_SESSION['cart'][$product_id]);
        jsonResponse(true, "Item removed from cart");
    }
    
    // Verify stock
    $stmt = $pdo->prepare("SELECT stock FROM products WHERE id = ? AND status = 'active'");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        unset($_SESSION['cart'][$product_id]);
        jsonResponse(false, "Product no longer available");
    }
    
    if ($quantity > $product['stock']) {
        jsonResponse(false, "Only {$product['stock']} items available in stock");
    }
    
    // Update quantity
    $_SESSION['cart'][$product_id]['quantity'] = $quantity;
    
    // Calculate new totals
    $subtotal = 0;
    foreach ($_SESSION['cart'] as $item) {
        $subtotal += $item['price'] * $item['quantity'];
    }
    
    jsonResponse(true, "Cart updated", [
        'subtotal' => $subtotal,
        'cart_count' => array_sum(array_column($_SESSION['cart'], 'quantity'))
    ]);
}

function handleRemoveFromCart() {
    $product_id = (int)($_POST['product_id'] ?? 0);
    
    if ($product_id <= 0) {
        jsonResponse(false, "Invalid product");
    }
    
    if (isset($_SESSION['cart'][$product_id])) {
        unset($_SESSION['cart'][$product_id]);
        jsonResponse(true, "Item removed from cart", [
            'cart_count' => isset($_SESSION['cart']) ? array_sum(array_column($_SESSION['cart'], 'quantity')) : 0
        ]);
    }
    
    jsonResponse(false, "Item not found in cart");
}

function handleClearCart() {
    $_SESSION['cart'] = [];
    jsonResponse(true, "Cart cleared");
}

function handleGetCartCount() {
    $count = 0;
    if (isset($_SESSION['cart'])) {
        $count = array_sum(array_column($_SESSION['cart'], 'quantity'));
    }
    jsonResponse(true, "", ['count' => $count]);
}

// ============================================
// ORDER ACTION HANDLERS
// ============================================

function handleCreateOrder($pdo) {
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('checkout.php', "Invalid security token. Please try again.");
    }
    
    // Check if cart is empty
    if (empty($_SESSION['cart'])) {
        redirectWithMessage('cart.php', "Your cart is empty.");
    }
    
    // Get user ID if logged in
    $user_id = $_SESSION['user_id'] ?? null;
    
    // Sanitize and validate inputs
    $customer_name = sanitize($_POST['customer_name'] ?? '');
    $customer_email = sanitize($_POST['customer_email'] ?? '');
    $customer_phone = sanitize($_POST['customer_phone'] ?? '');
    $delivery_method = sanitize($_POST['delivery_method'] ?? 'pickup');
    $payment_method = sanitize($_POST['payment_method'] ?? 'cash');
    $order_notes = sanitize($_POST['order_notes'] ?? '');
    
    // Validate required fields
    if (empty($customer_name) || empty($customer_email) || empty($customer_phone)) {
        redirectWithMessage('checkout.php', "Please fill in all required fields.");
    }
    
    // Validate email
    if (!filter_var($customer_email, FILTER_VALIDATE_EMAIL)) {
        redirectWithMessage('checkout.php', "Invalid email address.");
    }
    
    // Build shipping address
    $shipping_address = [];
    
    if ($delivery_method !== 'pickup') {
        $address_line1 = sanitize($_POST['address_line1'] ?? '');
        $address_line2 = sanitize($_POST['address_line2'] ?? '');
        $city = sanitize($_POST['city'] ?? '');
        $postal_code = sanitize($_POST['postal_code'] ?? '');
        
        if (empty($address_line1) || empty($city)) {
            redirectWithMessage('checkout.php', "Please provide complete shipping address.");
        }
        
        $shipping_address = [
            'line1' => $address_line1,
            'line2' => $address_line2,
            'city' => $city,
            'postal_code' => $postal_code,
            'country' => 'Kenya'
        ];
    } else {
        $shipping_address = ['type' => 'pickup', 'location' => 'Store Pickup - Jengo Hardware, Nairobi'];
    }
    
    // Calculate delivery fee
    $delivery_fees = [
        'pickup' => 0,
        'standard' => 300,
        'express' => 600
    ];
    $delivery_fee = $delivery_fees[$delivery_method] ?? 0;
    
    // Begin transaction
    $pdo->beginTransaction();
    
    try {
        // Validate cart items and calculate totals
        $cart_items = $_SESSION['cart'];
        $subtotal = 0;
        $order_items = [];
        
        foreach ($cart_items as $product_id => $item) {
            // Lock row for update to prevent race conditions
            $stmt = $pdo->prepare("
                SELECT id, name, sku, price, stock 
                FROM products 
                WHERE id = ? AND status = 'active' 
                FOR UPDATE
            ");
            $stmt->execute([$product_id]);
            $product = $stmt->fetch();
            
            if (!$product) {
                throw new Exception("Product '{$item['name']}' is no longer available.");
            }
            
            // Verify stock
            if ($product['stock'] < $item['quantity']) {
                throw new Exception("Insufficient stock for '{$product['name']}'. Only {$product['stock']} available.");
            }
            
            // Use database price for security
            $line_subtotal = $product['price'] * $item['quantity'];
            $subtotal += $line_subtotal;
            
            $order_items[] = [
                'product_id' => $product['id'],
                'product_name' => $product['name'],
                'product_sku' => $product['sku'],
                'quantity' => $item['quantity'],
                'price' => $product['price'],
                'subtotal' => $line_subtotal
            ];
        }
        
        $total_amount = $subtotal + $delivery_fee;
        
        // Generate unique order number
        $order_number = 'JH-' . date('Ymd') . '-' . strtoupper(substr(uniqid(), -6));
        
        // Insert order
        $stmt = $pdo->prepare("
            INSERT INTO orders (
                user_id, order_number, customer_name, customer_email, customer_phone,
                subtotal, delivery_fee, total_amount, delivery_method, payment_method,
                shipping_address, notes, status, payment_status, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'pending', NOW())
        ");
        
        $stmt->execute([
            $user_id,
            $order_number,
            $customer_name,
            $customer_email,
            $customer_phone,
            $subtotal,
            $delivery_fee,
            $total_amount,
            $delivery_method,
            $payment_method,
            json_encode($shipping_address),
            $order_notes
        ]);
        
        $order_id = $pdo->lastInsertId();
        
        // Insert order items and update stock
        $stmt_item = $pdo->prepare("
            INSERT INTO order_items (
                order_id, product_id, product_name, product_sku,
                quantity, price, subtotal, created_at
            ) VALUES (?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt_stock = $pdo->prepare("
            UPDATE products SET stock = stock - ? WHERE id = ?
        ");
        
        foreach ($order_items as $item) {
            // Insert order item
            $stmt_item->execute([
                $order_id,
                $item['product_id'],
                $item['product_name'],
                $item['product_sku'],
                $item['quantity'],
                $item['price'],
                $item['subtotal']
            ]);
            
            // Update stock
            $stmt_stock->execute([
                $item['quantity'],
                $item['product_id']
            ]);
        }
        
        // Commit transaction
        $pdo->commit();
        
        // Clear cart
        $_SESSION['cart'] = [];
        
        // Store order info in session for confirmation page
        $_SESSION['last_order'] = [
            'order_id' => $order_id,
            'order_number' => $order_number,
            'total' => $total_amount
        ];
        
        // Redirect to order confirmation
        redirectWithMessage(
            "order-confirmation.php?order=" . $order_number,
            "Order placed successfully!",
            'success'
        );
        
    } catch (Exception $e) {
        // Rollback on error
        $pdo->rollBack();
        error_log("Order creation failed: " . $e->getMessage());
        redirectWithMessage('checkout.php', $e->getMessage());
    }
}

function handleGetOrderStatus($pdo) {
    $order_number = sanitize($_GET['order_number'] ?? '');
    
    if (empty($order_number)) {
        jsonResponse(false, "Order number required");
    }
    
    // Get order with items
    $stmt = $pdo->prepare("
        SELECT o.*, 
               GROUP_CONCAT(
                   JSON_OBJECT(
                       'name', oi.product_name,
                       'quantity', oi.quantity,
                       'price', oi.price,
                       'subtotal', oi.subtotal
                   )
               ) as items
        FROM orders o
        LEFT JOIN order_items oi ON o.id = oi.order_id
        WHERE o.order_number = ?
        GROUP BY o.id
    ");
    $stmt->execute([$order_number]);
    $order = $stmt->fetch();
    
    if (!$order) {
        jsonResponse(false, "Order not found");
    }
    
    // Check if user has access (logged in user or email match)
    $user_id = $_SESSION['user_id'] ?? null;
    $has_access = ($user_id && $order['user_id'] == $user_id) || 
                  ($_SESSION['guest_email'] ?? '') == $order['customer_email'];
    
    if (!$has_access) {
        jsonResponse(false, "Access denied");
    }
    
    jsonResponse(true, "Order found", [
        'order' => $order
    ]);
}

function handleCancelOrder($pdo) {
    // Must be logged in
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        jsonResponse(false, "Please login to cancel orders");
    }
    
    $order_id = (int)($_POST['order_id'] ?? 0);
    $user_id = $_SESSION['user_id'];
    
    if ($order_id <= 0) {
        jsonResponse(false, "Invalid order");
    }
    
    // Validate CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(false, "Invalid security token");
    }
    
    $pdo->beginTransaction();
    
    try {
        // Get order
        $stmt = $pdo->prepare("
            SELECT id, status, user_id 
            FROM orders 
            WHERE id = ? 
            FOR UPDATE
        ");
        $stmt->execute([$order_id]);
        $order = $stmt->fetch();
        
        if (!$order) {
            throw new Exception("Order not found");
        }
        
        // Verify ownership
        if ($order['user_id'] != $user_id) {
            throw new Exception("Access denied");
        }
        
        // Check if cancellable
        if (!in_array($order['status'], ['pending', 'processing'])) {
            throw new Exception("Order cannot be cancelled at this stage");
        }
        
        // Get order items to restore stock
        $stmt = $pdo->prepare("SELECT product_id, quantity FROM order_items WHERE order_id = ?");
        $stmt->execute([$order_id]);
        $items = $stmt->fetchAll();
        
        // Restore stock
        $stmt_stock = $pdo->prepare("UPDATE products SET stock = stock + ? WHERE id = ?");
        foreach ($items as $item) {
            $stmt_stock->execute([$item['quantity'], $item['product_id']]);
        }
        
        // Update order status
        $stmt = $pdo->prepare("UPDATE orders SET status = 'cancelled', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$order_id]);
        
        $pdo->commit();
        
        jsonResponse(true, "Order cancelled successfully");
        
    } catch (Exception $e) {
        $pdo->rollBack();
        jsonResponse(false, $e->getMessage());
    }
}

// ============================================
// PROFILE ACTION HANDLERS
// ============================================

function handleUpdateProfile($pdo) {
    // Must be logged in
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        redirectWithMessage('auth.php', "Please login to update profile");
    }
    
    // Validate CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        redirectWithMessage('profile.php', "Invalid security token");
    }
    
    $user_id = $_SESSION['user_id'];
    $name = sanitize($_POST['name'] ?? '');
    $phone = sanitize($_POST['phone'] ?? '');
    
    if (empty($name)) {
        redirectWithMessage('profile.php', "Name is required");
    }
    
    try {
        $stmt = $pdo->prepare("UPDATE users SET name = ?, phone = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$name, $phone, $user_id]);
        
        // Update session
        $_SESSION['user_name'] = $name;
        
        redirectWithMessage('profile.php', "Profile updated successfully", 'success');
        
    } catch (PDOException $e) {
        error_log("Profile update failed: " . $e->getMessage());
        redirectWithMessage('profile.php', "Failed to update profile");
    }
}

function handleChangePassword($pdo) {
    // Must be logged in
    if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
        jsonResponse(false, "Please login to change password");
    }
    
    // Validate CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(false, "Invalid security token");
    }
    
    $user_id = $_SESSION['user_id'];
    $current_password = $_POST['current_password'] ?? '';
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($current_password) || empty($new_password)) {
        jsonResponse(false, "All fields are required");
    }
    
    if ($new_password !== $confirm_password) {
        jsonResponse(false, "New passwords do not match");
    }
    
    if (strlen($new_password) < 8) {
        jsonResponse(false, "Password must be at least 8 characters");
    }
    
    try {
        // Verify current password
        $stmt = $pdo->prepare("SELECT password FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
        
        if (!$user || !password_verify($current_password, $user['password'])) {
            jsonResponse(false, "Current password is incorrect");
        }
        
        // Update password
        $hashed_password = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
        $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$hashed_password, $user_id]);
        
        jsonResponse(true, "Password changed successfully");
        
    } catch (PDOException $e) {
        error_log("Password change failed: " . $e->getMessage());
        jsonResponse(false, "Failed to change password");
    }
}
