<?php
/**
 * Paystack Webhook Endpoint
 * Receives payment notifications from Paystack
 * Verifies webhook signature for security
 */

// Disable output buffering
if (ob_get_level()) ob_end_clean();

// Set response headers
header('Content-Type: application/json');

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'jengo_hardware');

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
    
    // Get webhook payload
    $payload = file_get_contents('php://input');
    
    // Get signature from headers
    $signature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';
    
    if (empty($payload) || empty($signature)) {
        http_response_code(400);
        echo json_encode(['status' => 'error', 'message' => 'Invalid request']);
        exit;
    }
    
    // Load Paystack configuration
    require_once __DIR__ . '/paystack-config.php';
    require_once __DIR__ . '/paystack.php';
    
    // Initialize Paystack API
    $paystack = new PaystackAPI($pdo, PAYSTACK_CONFIG);
    
    // Handle webhook
    $result = $paystack->handleWebhook($payload, $signature);
    
    // Return success response
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Webhook processed'
    ]);
    
} catch (Exception $e) {
    // Log error but return success to prevent retries
    error_log('Paystack Webhook Error: ' . $e->getMessage());
    
    http_response_code(200);
    echo json_encode([
        'status' => 'success',
        'message' => 'Received'
    ]);
}

exit;
