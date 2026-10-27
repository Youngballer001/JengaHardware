<?php
/**
 * Payment Status Page
 * Shows payment processing status and provides options for different payment outcomes
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: auth.php?action=login");
    exit();
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'jengo_hardware');

try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );
} catch (PDOException $e) {
    die("Database connection failed");
}

// Get order reference from URL
$order_ref = $_GET['order'] ?? $_GET['reference'] ?? '';
$payment_status = $_GET['status'] ?? '';

$order = null;
$payment_info = null;

if ($order_ref) {
    // Try to find order by order number or payment reference
    $stmt = $pdo->prepare("
        SELECT * FROM orders 
        WHERE order_number = ? OR payment_reference = ?
        ORDER BY created_at DESC
        LIMIT 1
    ");
    $stmt->execute([$order_ref, $order_ref]);
    $order = $stmt->fetch();
    
    if ($order) {
        // Get payment transaction details
        if ($order['payment_method'] === 'mpesa') {
            $stmt = $pdo->prepare("
                SELECT * FROM mpesa_transactions 
                WHERE merchant_request_id = ? OR checkout_request_id = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$order['payment_reference'], $order['payment_reference']]);
            $payment_info = $stmt->fetch();
        } elseif ($order['payment_method'] === 'paystack') {
            $stmt = $pdo->prepare("
                SELECT * FROM paystack_transactions 
                WHERE reference = ?
                ORDER BY created_at DESC
                LIMIT 1
            ");
            $stmt->execute([$order['payment_reference']]);
            $payment_info = $stmt->fetch();
        }
    }
}

$page_title = "Payment Status - Jengo Hardware";
include 'includes/header.php';
?>

<div class="payment-status-page">
    <div class="container">
        <?php if (!$order): ?>
            <!-- Order Not Found -->
            <div class="status-card error">
                <div class="status-icon">❌</div>
                <h1>Order Not Found</h1>
                <p>We couldn't find an order with reference: <strong><?php echo htmlspecialchars($order_ref); ?></strong></p>
                <div class="status-actions">
                    <a href="shop.php" class="btn btn-primary">Continue Shopping</a>
                    <a href="customer.php" class="btn btn-outline">View My Orders</a>
                </div>
            </div>
            
        <?php elseif ($order['payment_status'] === 'paid'): ?>
            <!-- Payment Successful -->
            <div class="status-card success">
                <div class="status-icon">✅</div>
                <h1>Payment Successful!</h1>
                <p>Your payment has been confirmed. Thank you for your order!</p>
                
                <div class="order-summary">
                    <h3>Order Details</h3>
                    <div class="summary-row">
                        <span>Order Number:</span>
                        <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Amount Paid:</span>
                        <strong>KSh <?php echo number_format($order['total_amount'], 2); ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Payment Method:</span>
                        <strong><?php echo ucfirst($order['payment_method']); ?></strong>
                    </div>
                    <?php if ($payment_info && isset($payment_info['mpesa_receipt_number'])): ?>
                        <div class="summary-row">
                            <span>M-Pesa Receipt:</span>
                            <strong><?php echo htmlspecialchars($payment_info['mpesa_receipt_number']); ?></strong>
                        </div>
                    <?php endif; ?>
                    <div class="summary-row">
                        <span>Status:</span>
                        <span class="status-badge status-<?php echo $order['status']; ?>">
                            <?php echo ucfirst($order['status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="status-actions">
                    <a href="order-confirmation.php?order=<?php echo urlencode($order['order_number']); ?>" class="btn btn-primary">View Order Details</a>
                    <a href="shop.php" class="btn btn-outline">Continue Shopping</a>
                </div>
            </div>
            
        <?php elseif ($order['payment_status'] === 'pending'): ?>
            <!-- Payment Pending -->
            <div class="status-card pending">
                <div class="status-icon">⏳</div>
                <h1>Payment Pending</h1>
                <p>We're waiting for your payment confirmation.</p>
                
                <?php if ($order['payment_method'] === 'mpesa'): ?>
                    <div class="payment-instructions">
                        <h3>M-Pesa Payment Instructions</h3>
                        <ol>
                            <li>Check your phone for the M-Pesa payment prompt</li>
                            <li>Enter your M-Pesa PIN to complete the payment</li>
                            <li>You will receive a confirmation SMS</li>
                            <li>Your order will be processed automatically</li>
                        </ol>
                        <p class="text-muted">Didn't receive the prompt? Click below to retry.</p>
                    </div>
                <?php endif; ?>
                
                <div class="order-summary">
                    <h3>Order Details</h3>
                    <div class="summary-row">
                        <span>Order Number:</span>
                        <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Amount Due:</span>
                        <strong>KSh <?php echo number_format($order['total_amount'], 2); ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Payment Method:</span>
                        <strong><?php echo ucfirst($order['payment_method']); ?></strong>
                    </div>
                </div>
                
                <div class="status-actions">
                    <?php if ($order['payment_method'] === 'mpesa'): ?>
                        <a href="payments/mpesa-payment-page.php?order=<?php echo urlencode($order['order_number']); ?>" class="btn btn-primary">Retry M-Pesa Payment</a>
                    <?php elseif ($order['payment_method'] === 'paystack'): ?>
                        <a href="payments/paystack-payment-page.php?order=<?php echo urlencode($order['order_number']); ?>" class="btn btn-primary">Retry Paystack Payment</a>
                    <?php endif; ?>
                    <a href="customer.php" class="btn btn-outline">View My Orders</a>
                </div>
            </div>
            
        <?php elseif ($order['payment_status'] === 'failed'): ?>
            <!-- Payment Failed -->
            <div class="status-card error">
                <div class="status-icon">❌</div>
                <h1>Payment Failed</h1>
                <p>We couldn't process your payment. Please try again.</p>
                
                <?php if ($payment_info && isset($payment_info['gateway_response'])): ?>
                    <div class="error-details">
                        <p><strong>Reason:</strong> <?php echo htmlspecialchars($payment_info['gateway_response']); ?></p>
                    </div>
                <?php endif; ?>
                
                <div class="order-summary">
                    <h3>Order Details</h3>
                    <div class="summary-row">
                        <span>Order Number:</span>
                        <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Amount Due:</span>
                        <strong>KSh <?php echo number_format($order['total_amount'], 2); ?></strong>
                    </div>
                </div>
                
                <div class="status-actions">
                    <?php if ($order['payment_method'] === 'mpesa'): ?>
                        <a href="payments/mpesa-payment-page.php?order=<?php echo urlencode($order['order_number']); ?>" class="btn btn-primary">Retry M-Pesa Payment</a>
                    <?php elseif ($order['payment_method'] === 'paystack'): ?>
                        <a href="payments/paystack-payment-page.php?order=<?php echo urlencode($order['order_number']); ?>" class="btn btn-primary">Retry Paystack Payment</a>
                    <?php endif; ?>
                    <a href="customer.php" class="btn btn-outline">View My Orders</a>
                    <a href="shop.php" class="btn btn-outline">Continue Shopping</a>
                </div>
            </div>
            
        <?php else: ?>
            <!-- Unknown Status -->
            <div class="status-card warning">
                <div class="status-icon">⚠️</div>
                <h1>Payment Status Unknown</h1>
                <p>We're checking the status of your payment. Please wait a moment.</p>
                
                <div class="order-summary">
                    <h3>Order Details</h3>
                    <div class="summary-row">
                        <span>Order Number:</span>
                        <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Amount:</span>
                        <strong>KSh <?php echo number_format($order['total_amount'], 2); ?></strong>
                    </div>
                    <div class="summary-row">
                        <span>Payment Status:</span>
                        <span class="status-badge status-<?php echo $order['payment_status']; ?>">
                            <?php echo ucfirst($order['payment_status']); ?>
                        </span>
                    </div>
                </div>
                
                <div class="status-actions">
                    <button onclick="location.reload()" class="btn btn-primary">Refresh Status</button>
                    <a href="customer.php" class="btn btn-outline">View My Orders</a>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.payment-status-page {
    padding: 60px 0;
    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
    min-height: calc(100vh - 400px);
}

.status-card {
    max-width: 700px;
    margin: 0 auto;
    background: white;
    border-radius: 20px;
    padding: 50px;
    text-align: center;
    box-shadow: 0 20px 60px rgba(0, 0, 0, 0.2);
}

.status-icon {
    font-size: 80px;
    margin-bottom: 20px;
    animation: scaleIn 0.5s ease-out;
}

@keyframes scaleIn {
    from {
        transform: scale(0);
        opacity: 0;
    }
    to {
        transform: scale(1);
        opacity: 1;
    }
}

.status-card h1 {
    font-size: 32px;
    margin-bottom: 15px;
    color: #2d3748;
}

.status-card p {
    font-size: 16px;
    color: #718096;
    margin-bottom: 30px;
}

.status-card.success {
    border-top: 5px solid #48bb78;
}

.status-card.error {
    border-top: 5px solid #f56565;
}

.status-card.pending {
    border-top: 5px solid #ed8936;
}

.status-card.warning {
    border-top: 5px solid #ecc94b;
}

.order-summary {
    background: #f7fafc;
    border-radius: 12px;
    padding: 30px;
    margin: 30px 0;
    text-align: left;
}

.order-summary h3 {
    font-size: 18px;
    color: #2d3748;
    margin-bottom: 20px;
    text-align: center;
}

.summary-row {
    display: flex;
    justify-content: space-between;
    padding: 12px 0;
    border-bottom: 1px solid #e2e8f0;
}

.summary-row:last-child {
    border-bottom: none;
}

.summary-row span {
    color: #718096;
}

.summary-row strong {
    color: #2d3748;
    font-weight: 600;
}

.payment-instructions {
    background: #edf2f7;
    border-radius: 12px;
    padding: 25px;
    margin: 30px 0;
    text-align: left;
}

.payment-instructions h3 {
    font-size: 18px;
    color: #2d3748;
    margin-bottom: 15px;
}

.payment-instructions ol {
    margin: 15px 0;
    padding-left: 25px;
}

.payment-instructions li {
    margin: 10px 0;
    color: #4a5568;
}

.error-details {
    background: #fff5f5;
    border: 1px solid #fc8181;
    border-radius: 8px;
    padding: 20px;
    margin: 20px 0;
}

.error-details p {
    color: #c53030;
    margin: 0;
}

.status-actions {
    display: flex;
    gap: 15px;
    justify-content: center;
    flex-wrap: wrap;
    margin-top: 30px;
}

.status-badge {
    display: inline-block;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
}

.status-badge.status-pending {
    background: #fef5e7;
    color: #ed8936;
}

.status-badge.status-processing {
    background: #e3f2fd;
    color: #2196f3;
}

.status-badge.status-shipped {
    background: #e8f5e9;
    color: #4caf50;
}

.status-badge.status-delivered {
    background: #e8f5e9;
    color: #2e7d32;
}

@media (max-width: 768px) {
    .status-card {
        padding: 30px 20px;
    }
    
    .status-icon {
        font-size: 60px;
    }
    
    .status-card h1 {
        font-size: 24px;
    }
    
    .status-actions {
        flex-direction: column;
    }
    
    .status-actions .btn {
        width: 100%;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
