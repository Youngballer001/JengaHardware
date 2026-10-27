<?php
/**
 * Paystack Payment Initialization
 * Creates payment transaction and returns authorization URL
 */

session_start();
header('Content-Type: application/json');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'jengo_hardware');

// Response helper
function jsonResponse($success, $message, $data = []) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data
    ]);
    exit;
}

try {
    // Connect to database
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
    
    // Validate request method
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        jsonResponse(false, 'Invalid request method');
    }
    
    // Validate CSRF token
    if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')) {
        jsonResponse(false, 'Invalid security token');
    }
    
    // Get and validate inputs
    $order_number = trim($_POST['order_number'] ?? '');
    
    if (empty($order_number)) {
        jsonResponse(false, 'Order number is required');
    }
    
    // Fetch order details
    $stmt = $pdo->prepare("
        SELECT id, order_number, total_amount, payment_status, 
               customer_name, customer_email
        FROM orders
        WHERE order_number = ?
        LIMIT 1
    ");
    $stmt->execute([$order_number]);
    $order = $stmt->fetch();
    
    if (!$order) {
        jsonResponse(false, 'Order not found');
    }
    
    // Check if already paid
    if ($order['payment_status'] === 'paid') {
        jsonResponse(false, 'Order already paid');
    }
    
    // Load Paystack configuration
    require_once __DIR__ . '/paystack-config.php';
    require_once __DIR__ . '/paystack.php';
    
    // Initialize Paystack API
    $paystack = new PaystackAPI($pdo, PAYSTACK_CONFIG);
    
    // Generate unique reference
    $reference = 'PS-' . strtoupper($order_number);
    
    // Prepare metadata
    $metadata = [
        'order_number' => $order_number,
        'customer_name' => $order['customer_name'],
        'cart_id' => $order['id']
    ];
    
    // Prepare callback URL
    $callback_url = PAYSTACK_CONFIG['callback_url'] . '?reference=' . urlencode($reference);
    
    // Initialize transaction
    $result = $paystack->initializeTransaction(
        $order['total_amount'],
        $order['customer_email'],
        $reference,
        $metadata,
        $callback_url
    );
    
    if ($result['success']) {
        // Store reference in session for verification
        $_SESSION['paystack_reference'] = $reference;
        $_SESSION['paystack_order_number'] = $order_number;
        
        jsonResponse(true, $result['message'], [
            'authorization_url' => $result['authorization_url'],
            'reference' => $reference
        ]);
    } else {
        jsonResponse(false, $result['message']);
    }
    
} catch (Exception $e) {
    error_log('Paystack Initialization Error: ' . $e->getMessage());
    jsonResponse(false, 'System error. Please try again later.');
}
