<?php
/**
 * M-Pesa Payment Status Check
 * Allows frontend to poll for payment completion
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
    
    // Get checkout request ID
    $checkout_request_id = $_GET['checkout_request_id'] ?? '';
    
    if (empty($checkout_request_id)) {
        jsonResponse(false, 'Checkout request ID required');
    }
    
    // Check transaction status in database
    $stmt = $pdo->prepare("
        SELECT status, result_desc, mpesa_receipt_number, created_at, updated_at
        FROM mpesa_transactions
        WHERE checkout_request_id = ?
        LIMIT 1
    ");
    $stmt->execute([$checkout_request_id]);
    $transaction = $stmt->fetch();
    
    if (!$transaction) {
        jsonResponse(false, 'Transaction not found');
    }
    
    // Check if transaction has been updated recently (within last 60 seconds)
    $updated_timestamp = strtotime($transaction['updated_at']);
    $age = time() - $updated_timestamp;
    
    // Return status
    $status_messages = [
        'pending' => 'Payment request sent. Please check your phone.',
        'completed' => 'Payment received successfully!',
        'failed' => 'Payment failed or was cancelled.'
    ];
    
    $response_data = [
        'status' => $transaction['status'],
        'receipt_number' => $transaction['mpesa_receipt_number'],
        'age_seconds' => $age
    ];
    
    jsonResponse(
        true,
        $status_messages[$transaction['status']] ?? 'Unknown status',
        $response_data
    );
    
} catch (Exception $e) {
    error_log('M-Pesa Status Check Error: ' . $e->getMessage());
    jsonResponse(false, 'System error');
}
