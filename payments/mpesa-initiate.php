<?php
/**
 * M-Pesa Payment Initiation Endpoint
 * Handles STK Push requests from checkout page
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
    $phone_number = trim($_POST['phone_number'] ?? '');
    
    if (empty($order_number) || empty($phone_number)) {
        jsonResponse(false, 'Order number and phone number are required');
    }
    
    // Fetch order details
    $stmt = $pdo->prepare("
        SELECT id, order_number, total_amount, payment_status, customer_name
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
    
    // Load M-Pesa configuration
    require_once __DIR__ . '/mpesa-config.php';
    require_once __DIR__ . '/mpesa.php';
    
    // Initialize M-Pesa API
    $mpesa = new MpesaAPI($pdo, MPESA_CONFIG);
    
    // Prepare STK Push parameters
    $amount = (int)ceil($order['total_amount']); // M-Pesa doesn't accept decimals
    $account_reference = $order_number;
    $transaction_desc = "Payment for Order " . $order_number;
    $callback_url = MPESA_CONFIG['callback_url'];
    
    // Initiate STK Push
    $result = $mpesa->initiateSTKPush(
        $phone_number,
        $amount,
        $account_reference,
        $transaction_desc,
        $callback_url
    );
    
    if ($result['success']) {
        // Store checkout request ID in session for status checking
        $_SESSION['mpesa_checkout_request_id'] = $result['checkout_request_id'];
        $_SESSION['mpesa_order_number'] = $order_number;
        
        jsonResponse(true, $result['message'], [
            'checkout_request_id' => $result['checkout_request_id']
        ]);
    } else {
        jsonResponse(false, $result['message']);
    }
    
} catch (Exception $e) {
    error_log('M-Pesa Initiation Error: ' . $e->getMessage());
    jsonResponse(false, 'System error. Please try again later.');
}
