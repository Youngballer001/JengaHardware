<?php
/**
 * Admin Actions Handler
 * Secure server-side handling of admin operations: products, categories, orders, users
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

// Security: Check admin authentication
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_role'] !== 'admin') {
    jsonResponse(false, "Unauthorized access");
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
function redirectWithMessage($location, $message, $type = 'success') {
    $_SESSION['admin_message'] = $message;
    $_SESSION['admin_message_type'] = $type;
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
    // PRODUCT ACTIONS
    // ============================================
    
    case 'create_product':
        handleCreateProduct($pdo);
        break;
        
    case 'update_product':
        handleUpdateProduct($pdo);
        break;
        
    case 'delete_product':
        handleDeleteProduct($pdo);
        break;
        
    case 'get_product':
        handleGetProduct($pdo);
        break;
    
    // ============================================
    // CATEGORY ACTIONS
    // ============================================
    
    case 'create_category':
        handleCreateCategory($pdo);
        break;
        
    case 'update_category':
        handleUpdateCategory($pdo);
        break;
        
    case 'delete_category':
        handleDeleteCategory($pdo);
        break;
        
    case 'get_category':
        handleGetCategory($pdo);
        break;
    
    // ============================================
    // ORDER ACTIONS
    // ============================================
    
    case 'update_order_status':
        handleUpdateOrderStatus($pdo);
        break;
        
    case 'update_payment_status':
        handleUpdatePaymentStatus($pdo);
        break;
    
    // ============================================
    // USER ACTIONS
    // ============================================
    
    case 'update_user_status':
        handleUpdateUserStatus($pdo);
        break;
        
    case 'update_user_role':
        handleUpdateUserRole($pdo);
        break;
    
    // ============================================
    // DEFAULT
    // ============================================
    
    default:
        jsonResponse(false, "Invalid action");
}

// ============================================
// PRODUCT ACTION HANDLERS
// ============================================

function handleCreateProduct($pdo) {
    // Validate CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(false, "Invalid security token");
    }
    
    // Validate inputs
    $name = sanitize($_POST['name'] ?? '');
    $sku = sanitize($_POST['sku'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'active');
    
    if (empty($name) || empty($sku)) {
        jsonResponse(false, "Product name and SKU are required");
    }
    
    if ($price <= 0) {
        jsonResponse(false, "Price must be greater than zero");
    }
    
    if ($stock < 0) {
        jsonResponse(false, "Stock cannot be negative");
    }
    
    // Check for duplicate SKU
    $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ?");
    $stmt->execute([$sku]);
    if ($stmt->fetch()) {
        jsonResponse(false, "SKU already exists");
    }
    
    // Handle image upload
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image = handleImageUpload($_FILES['image']);
        if (!$image) {
            jsonResponse(false, "Failed to upload image");
        }
    }
    
    // Insert product
    try {
        $stmt = $pdo->prepare("
            INSERT INTO products (name, sku, description, price, stock, category_id, image, status, created_at, updated_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([
            $name,
            $sku,
            $description,
            $price,
            $stock,
            $category_id > 0 ? $category_id : null,
            $image,
            $status
        ]);
        
        $product_id = $pdo->lastInsertId();
        
        redirectWithMessage("admin.php?tab=products", "Product created successfully!");
        
    } catch (PDOException $e) {
        error_log("Create product error: " . $e->getMessage());
        jsonResponse(false, "Failed to create product");
    }
}

function handleUpdateProduct($pdo) {
    // Validate CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(false, "Invalid security token");
    }
    
    $product_id = intval($_POST['product_id'] ?? 0);
    
    if ($product_id <= 0) {
        jsonResponse(false, "Invalid product ID");
    }
    
    // Validate inputs
    $name = sanitize($_POST['name'] ?? '');
    $sku = sanitize($_POST['sku'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $price = floatval($_POST['price'] ?? 0);
    $stock = intval($_POST['stock'] ?? 0);
    $category_id = intval($_POST['category_id'] ?? 0);
    $status = sanitize($_POST['status'] ?? 'active');
    
    if (empty($name) || empty($sku)) {
        jsonResponse(false, "Product name and SKU are required");
    }
    
    if ($price <= 0) {
        jsonResponse(false, "Price must be greater than zero");
    }
    
    if ($stock < 0) {
        jsonResponse(false, "Stock cannot be negative");
    }
    
    // Check for duplicate SKU (excluding current product)
    $stmt = $pdo->prepare("SELECT id FROM products WHERE sku = ? AND id != ?");
    $stmt->execute([$sku, $product_id]);
    if ($stmt->fetch()) {
        jsonResponse(false, "SKU already exists");
    }
    
    // Handle image upload
    $image = null;
    if (isset($_FILES['image']) && $_FILES['image']['error'] === UPLOAD_ERR_OK) {
        $image = handleImageUpload($_FILES['image']);
        if (!$image) {
            jsonResponse(false, "Failed to upload image");
        }
    }
    
    // Update product
    try {
        if ($image) {
            $stmt = $pdo->prepare("
                UPDATE products 
                SET name = ?, sku = ?, description = ?, price = ?, stock = ?, category_id = ?, image = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $name,
                $sku,
                $description,
                $price,
                $stock,
                $category_id > 0 ? $category_id : null,
                $image,
                $status,
                $product_id
            ]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE products 
                SET name = ?, sku = ?, description = ?, price = ?, stock = ?, category_id = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            
            $stmt->execute([
                $name,
                $sku,
                $description,
                $price,
                $stock,
                $category_id > 0 ? $category_id : null,
                $status,
                $product_id
            ]);
        }
        
        redirectWithMessage("admin.php?tab=products", "Product updated successfully!");
        
    } catch (PDOException $e) {
        error_log("Update product error: " . $e->getMessage());
        jsonResponse(false, "Failed to update product");
    }
}

function handleDeleteProduct($pdo) {
    // Validate CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(false, "Invalid security token");
    }
    
    $product_id = intval($_POST['product_id'] ?? 0);
    
    if ($product_id <= 0) {
        jsonResponse(false, "Invalid product ID");
    }
    
    // Soft delete (set status to inactive)
    try {
        $stmt = $pdo->prepare("UPDATE products SET status = 'inactive', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$product_id]);
        
        redirectWithMessage("admin.php?tab=products", "Product deleted successfully!");
        
    } catch (PDOException $e) {
        error_log("Delete product error: " . $e->getMessage());
        jsonResponse(false, "Failed to delete product");
    }
}

function handleGetProduct($pdo) {
    $product_id = intval($_GET['id'] ?? 0);
    
    if ($product_id <= 0) {
        jsonResponse(false, "Invalid product ID");
    }
    
    $stmt = $pdo->prepare("SELECT * FROM products WHERE id = ?");
    $stmt->execute([$product_id]);
    $product = $stmt->fetch();
    
    if (!$product) {
        jsonResponse(false, "Product not found");
    }
    
    jsonResponse(true, "Product retrieved", $product);
}

// ============================================
// CATEGORY ACTION HANDLERS
// ============================================

function handleCreateCategory($pdo) {
    // Validate CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(false, "Invalid security token");
    }
    
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $status = sanitize($_POST['status'] ?? 'active');
    
    if (empty($name)) {
        jsonResponse(false, "Category name is required");
    }
    
    // Check for duplicate name
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ?");
    $stmt->execute([$name]);
    if ($stmt->fetch()) {
        jsonResponse(false, "Category already exists");
    }
    
    // Insert category
    try {
        $stmt = $pdo->prepare("
            INSERT INTO categories (name, description, status, created_at, updated_at)
            VALUES (?, ?, ?, NOW(), NOW())
        ");
        
        $stmt->execute([$name, $description, $status]);
        
        redirectWithMessage("admin.php?tab=categories", "Category created successfully!");
        
    } catch (PDOException $e) {
        error_log("Create category error: " . $e->getMessage());
        jsonResponse(false, "Failed to create category");
    }
}

function handleUpdateCategory($pdo) {
    // Validate CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(false, "Invalid security token");
    }
    
    $category_id = intval($_POST['category_id'] ?? 0);
    
    if ($category_id <= 0) {
        jsonResponse(false, "Invalid category ID");
    }
    
    $name = sanitize($_POST['name'] ?? '');
    $description = sanitize($_POST['description'] ?? '');
    $status = sanitize($_POST['status'] ?? 'active');
    
    if (empty($name)) {
        jsonResponse(false, "Category name is required");
    }
    
    // Check for duplicate name (excluding current category)
    $stmt = $pdo->prepare("SELECT id FROM categories WHERE name = ? AND id != ?");
    $stmt->execute([$name, $category_id]);
    if ($stmt->fetch()) {
        jsonResponse(false, "Category name already exists");
    }
    
    // Update category
    try {
        $stmt = $pdo->prepare("
            UPDATE categories 
            SET name = ?, description = ?, status = ?, updated_at = NOW()
            WHERE id = ?
        ");
        
        $stmt->execute([$name, $description, $status, $category_id]);
        
        redirectWithMessage("admin.php?tab=categories", "Category updated successfully!");
        
    } catch (PDOException $e) {
        error_log("Update category error: " . $e->getMessage());
        jsonResponse(false, "Failed to update category");
    }
}

function handleDeleteCategory($pdo) {
    // Validate CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(false, "Invalid security token");
    }
    
    $category_id = intval($_POST['category_id'] ?? 0);
    
    if ($category_id <= 0) {
        jsonResponse(false, "Invalid category ID");
    }
    
    // Check if category has products
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM products WHERE category_id = ? AND status = 'active'");
    $stmt->execute([$category_id]);
    $product_count = $stmt->fetchColumn();
    
    if ($product_count > 0) {
        jsonResponse(false, "Cannot delete category with active products");
    }
    
    // Soft delete (set status to inactive)
    try {
        $stmt = $pdo->prepare("UPDATE categories SET status = 'inactive', updated_at = NOW() WHERE id = ?");
        $stmt->execute([$category_id]);
        
        redirectWithMessage("admin.php?tab=categories", "Category deleted successfully!");
        
    } catch (PDOException $e) {
        error_log("Delete category error: " . $e->getMessage());
        jsonResponse(false, "Failed to delete category");
    }
}

function handleGetCategory($pdo) {
    $category_id = intval($_GET['id'] ?? 0);
    
    if ($category_id <= 0) {
        jsonResponse(false, "Invalid category ID");
    }
    
    $stmt = $pdo->prepare("SELECT * FROM categories WHERE id = ?");
    $stmt->execute([$category_id]);
    $category = $stmt->fetch();
    
    if (!$category) {
        jsonResponse(false, "Category not found");
    }
    
    jsonResponse(true, "Category retrieved", $category);
}

// ============================================
// ORDER ACTION HANDLERS
// ============================================

function handleUpdateOrderStatus($pdo) {
    // Validate CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(false, "Invalid security token");
    }
    
    $order_id = intval($_POST['order_id'] ?? 0);
    $new_status = sanitize($_POST['status'] ?? '');
    
    if ($order_id <= 0) {
        jsonResponse(false, "Invalid order ID");
    }
    
    $allowed_statuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
    if (!in_array($new_status, $allowed_statuses)) {
        jsonResponse(false, "Invalid order status");
    }
    
    // Get current order
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE id = ?");
    $stmt->execute([$order_id]);
    $order = $stmt->fetch();
    
    if (!$order) {
        jsonResponse(false, "Order not found");
    }
    
    // Update order status
    try {
        $stmt = $pdo->prepare("UPDATE orders SET status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
        
        // TODO: Send email notification to customer about status change
        
        redirectWithMessage("admin.php?tab=orders", "Order status updated successfully!");
        
    } catch (PDOException $e) {
        error_log("Update order status error: " . $e->getMessage());
        jsonResponse(false, "Failed to update order status");
    }
}

function handleUpdatePaymentStatus($pdo) {
    // Validate CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(false, "Invalid security token");
    }
    
    $order_id = intval($_POST['order_id'] ?? 0);
    $new_status = sanitize($_POST['payment_status'] ?? '');
    
    if ($order_id <= 0) {
        jsonResponse(false, "Invalid order ID");
    }
    
    $allowed_statuses = ['pending', 'paid', 'failed', 'refunded'];
    if (!in_array($new_status, $allowed_statuses)) {
        jsonResponse(false, "Invalid payment status");
    }
    
    // Update payment status
    try {
        $stmt = $pdo->prepare("UPDATE orders SET payment_status = ?, updated_at = NOW() WHERE id = ?");
        $stmt->execute([$new_status, $order_id]);
        
        redirectWithMessage("admin.php?tab=orders", "Payment status updated successfully!");
        
    } catch (PDOException $e) {
        error_log("Update payment status error: " . $e->getMessage());
        jsonResponse(false, "Failed to update payment status");
    }
}

// ============================================
// USER ACTION HANDLERS
// ============================================

function handleUpdateUserStatus($pdo) {
    // Validate CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(false, "Invalid security token");
    }
    
    $user_id = intval($_POST['user_id'] ?? 0);
    $new_status = sanitize($_POST['status'] ?? '');
    
    if ($user_id <= 0) {
        jsonResponse(false, "Invalid user ID");
    }
    
    // Prevent self-deactivation
    if ($user_id == $_SESSION['user_id']) {
        jsonResponse(false, "Cannot change your own status");
    }
    
    $allowed_statuses = ['active', 'inactive', 'suspended'];
    if (!in_array($new_status, $allowed_statuses)) {
        jsonResponse(false, "Invalid user status");
    }
    
    // Update user status
    try {
        $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
        $stmt->execute([$new_status, $user_id]);
        
        redirectWithMessage("admin.php?tab=users", "User status updated successfully!");
        
    } catch (PDOException $e) {
        error_log("Update user status error: " . $e->getMessage());
        jsonResponse(false, "Failed to update user status");
    }
}

function handleUpdateUserRole($pdo) {
    // Validate CSRF
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        jsonResponse(false, "Invalid security token");
    }
    
    $user_id = intval($_POST['user_id'] ?? 0);
    $new_role = sanitize($_POST['role'] ?? '');
    
    if ($user_id <= 0) {
        jsonResponse(false, "Invalid user ID");
    }
    
    // Prevent self-role change
    if ($user_id == $_SESSION['user_id']) {
        jsonResponse(false, "Cannot change your own role");
    }
    
    $allowed_roles = ['customer', 'attendant', 'admin'];
    if (!in_array($new_role, $allowed_roles)) {
        jsonResponse(false, "Invalid user role");
    }
    
    // Update user role
    try {
        $stmt = $pdo->prepare("UPDATE users SET role = ? WHERE id = ?");
        $stmt->execute([$new_role, $user_id]);
        
        redirectWithMessage("admin.php?tab=users", "User role updated successfully!");
        
    } catch (PDOException $e) {
        error_log("Update user role error: " . $e->getMessage());
        jsonResponse(false, "Failed to update user role");
    }
}

// ============================================
// HELPER FUNCTIONS
// ============================================

function handleImageUpload($file) {
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'image/webp'];
    $max_size = 5 * 1024 * 1024; // 5MB
    
    if (!in_array($file['type'], $allowed_types)) {
        return false;
    }
    
    if ($file['size'] > $max_size) {
        return false;
    }
    
    $upload_dir = __DIR__ . '/assets/images/products/';
    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0755, true);
    }
    
    $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    $filename = 'product_' . uniqid() . '.' . $extension;
    $destination = $upload_dir . $filename;
    
    if (move_uploaded_file($file['tmp_name'], $destination)) {
        return 'assets/images/products/' . $filename;
    }
    
    return false;
}
