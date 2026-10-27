<?php
/**
 * Attendant Actions Handler
 * Secure server-side handling of attendant operations: order status updates
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

// Security: Check attendant or admin authentication
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    jsonResponse(false, "Unauthorized access");
}

if (!in_array($_SESSION['user_role'], ['attendant', 'admin'])) {
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
    $_SESSION['attendant_message'] = $message;
    $_SESSION['attendant_message_type'] = $type;
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
    
    case 'update_order_status':
        handleUpdateOrderStatus($pdo);
        break;
    
    default:
        jsonResponse(false, "Invalid action");
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
        
        redirectWithMessage("attendant.php?tab=orders", "Order status updated successfully!");
        
    } catch (PDOException $e) {
        error_log("Update order status error: " . $e->getMessage());
        jsonResponse(false, "Failed to update order status");
    }
}
