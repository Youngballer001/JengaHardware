<?php
/**
 * Paystack Payment Callback
 * User returns here after completing/cancelling payment
 */

session_start();

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
    
    // Get reference from URL
    $reference = $_GET['reference'] ?? '';
    
    if (empty($reference)) {
        $_SESSION['payment_error'] = 'Invalid payment reference';
        header("Location: ../cart.php");
        exit();
    }
    
    // Load Paystack configuration
    require_once __DIR__ . '/paystack-config.php';
    require_once __DIR__ . '/paystack.php';
    
    // Initialize Paystack API
    $paystack = new PaystackAPI($pdo, PAYSTACK_CONFIG);
    
    // Verify transaction
    $result = $paystack->verifyTransaction($reference);
    
    if ($result['success']) {
        // Payment successful
        $_SESSION['payment_success'] = 'Payment completed successfully!';
        
        // Get order number from reference
        $order_number = str_replace('PS-', '', $reference);
        
        // Clear cart
        $_SESSION['cart'] = [];
        
        // Redirect to confirmation page
        header("Location: ../order-confirmation.php?order=" . urlencode($order_number));
        exit();
    } else {
        // Payment failed
        $_SESSION['payment_error'] = $result['message'];
        header("Location: ../checkout.php");
        exit();
    }
    
} catch (Exception $e) {
    error_log('Paystack Callback Error: ' . $e->getMessage());
    $_SESSION['payment_error'] = 'Payment verification failed';
    header("Location: ../checkout.php");
    exit();
}
