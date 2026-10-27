<?php
/**
 * M-Pesa Callback Endpoint
 * Receives payment notifications from Safaricom
 */

// Disable output buffering for immediate response
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
    
    // Load M-Pesa configuration
    require_once __DIR__ . '/mpesa-config.php';
    require_once __DIR__ . '/mpesa.php';
    
    // Initialize M-Pesa API
    $mpesa = new MpesaAPI($pdo, MPESA_CONFIG);
    
    // Handle callback
    $result = $mpesa->handleCallback();
    
    // Return success response to M-Pesa
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Success'
    ]);
    
} catch (Exception $e) {
    // Log error but still return success to prevent retries
    error_log('M-Pesa Callback Error: ' . $e->getMessage());
    
    echo json_encode([
        'ResultCode' => 0,
        'ResultDesc' => 'Accepted'
    ]);
}

// Ensure response is sent
exit;
