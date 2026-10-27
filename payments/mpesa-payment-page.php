<?php
/**
 * M-Pesa Payment Page
 * Shows after order creation for M-Pesa payment
 */

session_start();

// Redirect if no order in session
if (!isset($_SESSION['last_order'])) {
    header("Location: shop.php");
    exit();
}

$order = $_SESSION['last_order'];

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
    
    // Get order details
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE order_number = ?");
    $stmt->execute([$order['order_number']]);
    $order_details = $stmt->fetch();
    
    if (!$order_details) {
        header("Location: shop.php");
        exit();
    }
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$page_title = "M-Pesa Payment - Jengo Hardware";
include '../includes/header.php';
?>

<div class="mpesa-payment-page">
    <div class="container">
        <div class="payment-container">
            <div class="payment-header">
                <div class="mpesa-logo">M-PESA</div>
                <h1>Complete Payment</h1>
                <p>Order: <?php echo htmlspecialchars($order['order_number']); ?></p>
            </div>

            <div class="payment-amount">
                <div class="amount-label">Amount to Pay</div>
                <div class="amount-value">KSh <?php echo number_format($order_details['total_amount'], 2); ?></div>
            </div>

            <div id="paymentStatus" class="payment-status" style="display: none;">
                <div class="status-icon"></div>
                <div class="status-message"></div>
            </div>

            <div id="paymentForm" class="payment-form">
                <div class="form-group">
                    <label for="phone_number">M-Pesa Phone Number</label>
                    <input type="tel" id="phone_number" class="form-control" 
                           placeholder="e.g., 0712345678 or 254712345678"
                           value="<?php echo htmlspecialchars($order_details['customer_phone']); ?>">
                    <small class="form-text">Enter the M-Pesa registered phone number</small>
                </div>

                <button id="initiatePayment" class="btn btn-primary btn-lg btn-block">
                    Send Payment Request
                </button>

                <div class="payment-info">
                    <h4>How it works:</h4>
                    <ol>
                        <li>Click "Send Payment Request"</li>
                        <li>You'll receive an STK Push on your phone</li>
                        <li>Enter your M-Pesa PIN to complete</li>
                        <li>Wait for confirmation</li>
                    </ol>
                </div>
            </div>

            <div id="loadingStatus" class="loading-status" style="display: none;">
                <div class="spinner"></div>
                <p>Waiting for payment confirmation...</p>
                <small>Please check your phone and enter your M-Pesa PIN</small>
            </div>

            <div class="payment-footer">
                <a href="order-confirmation.php?order=<?php echo urlencode($order['order_number']); ?>" 
                   class="btn btn-outline">View Order Details</a>
            </div>
        </div>
    </div>
</div>

<style>
.mpesa-payment-page {
    padding: 40px 0;
    background: linear-gradient(135deg, #00a651 0%, #008844 100%);
    min-height: calc(100vh - 400px);
}

.payment-container {
    max-width: 500px;
    margin: 0 auto;
    background: white;
    border-radius: 12px;
    box-shadow: 0 10px 40px rgba(0, 0, 0, 0.2);
    overflow: hidden;
}

.payment-header {
    background: linear-gradient(135deg, #00a651, #008844);
    color: white;
    padding: 30px;
    text-align: center;
}

.mpesa-logo {
    font-size: 32px;
    font-weight: 900;
    letter-spacing: 2px;
    margin-bottom: 15px;
}

.payment-header h1 {
    font-size: 24px;
    margin-bottom: 5px;
}

.payment-header p {
    opacity: 0.9;
    font-size: 14px;
}

.payment-amount {
    padding: 30px;
    text-align: center;
    background: #f7fafc;
    border-bottom: 2px solid #e2e8f0;
}

.amount-label {
    font-size: 14px;
    color: #718096;
    margin-bottom: 10px;
}

.amount-value {
    font-size: 36px;
    font-weight: 700;
    color: #00a651;
}

.payment-form {
    padding: 30px;
}

.payment-status {
    padding: 30px;
    text-align: center;
}

.status-icon {
    width: 80px;
    height: 80px;
    margin: 0 auto 20px;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 48px;
}

.status-icon.success {
    background: #c6f6d5;
    color: #22543d;
}

.status-icon.success::before {
    content: '✓';
}

.status-icon.error {
    background: #fed7d7;
    color: #742a2a;
}

.status-icon.error::before {
    content: '✗';
}

.status-message {
    font-size: 18px;
    font-weight: 600;
    margin-bottom: 10px;
}

.loading-status {
    padding: 40px;
    text-align: center;
}

.spinner {
    width: 50px;
    height: 50px;
    margin: 0 auto 20px;
    border: 4px solid #e2e8f0;
    border-top-color: #00a651;
    border-radius: 50%;
    animation: spin 1s linear infinite;
}

@keyframes spin {
    to { transform: rotate(360deg); }
}

.payment-info {
    margin-top: 30px;
    padding: 20px;
    background: #f7fafc;
    border-radius: 8px;
}

.payment-info h4 {
    font-size: 16px;
    margin-bottom: 10px;
}

.payment-info ol {
    margin-left: 20px;
    font-size: 14px;
    color: #4a5568;
}

.payment-info li {
    margin-bottom: 5px;
}

.payment-footer {
    padding: 20px 30px;
    border-top: 1px solid #e2e8f0;
    text-align: center;
}

@media (max-width: 768px) {
    .payment-container {
        margin: 0 15px;
    }
}
</style>

<script>
let checkoutRequestId = null;
let statusCheckInterval = null;

document.getElementById('initiatePayment').addEventListener('click', function() {
    const phoneNumber = document.getElementById('phone_number').value.trim();
    
    if (!phoneNumber) {
        showToast('Please enter phone number', 'error');
        return;
    }
    
    // Disable button
    this.disabled = true;
    this.textContent = 'Sending request...';
    
    // Initiate payment
    const formData = new FormData();
    formData.append('order_number', '<?php echo $order['order_number']; ?>');
    formData.append('phone_number', phoneNumber);
    formData.append('csrf_token', '<?php echo $csrf_token; ?>');
    
    fetch('mpesa-initiate.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            checkoutRequestId = data.data.checkout_request_id;
            
            // Hide form, show loading
            document.getElementById('paymentForm').style.display = 'none';
            document.getElementById('loadingStatus').style.display = 'block';
            
            // Start polling for status
            startStatusCheck();
        } else {
            showToast(data.message, 'error');
            this.disabled = false;
            this.textContent = 'Send Payment Request';
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred. Please try again.', 'error');
        this.disabled = false;
        this.textContent = 'Send Payment Request';
    });
});

function startStatusCheck() {
    statusCheckInterval = setInterval(checkPaymentStatus, 3000);
    
    // Stop checking after 2 minutes
    setTimeout(() => {
        if (statusCheckInterval) {
            clearInterval(statusCheckInterval);
            showStatus('timeout');
        }
    }, 120000);
}

function checkPaymentStatus() {
    if (!checkoutRequestId) return;
    
    fetch(`mpesa-status.php?checkout_request_id=${encodeURIComponent(checkoutRequestId)}`)
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                const status = data.data.status;
                
                if (status === 'completed') {
                    clearInterval(statusCheckInterval);
                    showStatus('success', data.message);
                    
                    // Redirect to confirmation page after 3 seconds
                    setTimeout(() => {
                        window.location.href = 'order-confirmation.php?order=<?php echo urlencode($order['order_number']); ?>';
                    }, 3000);
                    
                } else if (status === 'failed') {
                    clearInterval(statusCheckInterval);
                    showStatus('error', data.message);
                }
                // If pending, keep checking
            }
        })
        .catch(error => {
            console.error('Status check error:', error);
        });
}

function showStatus(type, message = '') {
    document.getElementById('loadingStatus').style.display = 'none';
    const statusDiv = document.getElementById('paymentStatus');
    const statusIcon = statusDiv.querySelector('.status-icon');
    const statusMessage = statusDiv.querySelector('.status-message');
    
    statusDiv.style.display = 'block';
    
    if (type === 'success') {
        statusIcon.className = 'status-icon success';
        statusMessage.textContent = message || 'Payment Successful!';
        statusMessage.style.color = '#22543d';
    } else if (type === 'error') {
        statusIcon.className = 'status-icon error';
        statusMessage.textContent = message || 'Payment Failed';
        statusMessage.style.color = '#742a2a';
        
        // Show retry button
        setTimeout(() => {
            document.getElementById('paymentForm').style.display = 'block';
            document.getElementById('paymentStatus').style.display = 'none';
            document.getElementById('initiatePayment').disabled = false;
            document.getElementById('initiatePayment').textContent = 'Try Again';
        }, 5000);
    } else if (type === 'timeout') {
        statusIcon.className = 'status-icon error';
        statusMessage.textContent = 'Request Timeout - Please try again';
        statusMessage.style.color = '#742a2a';
        
        setTimeout(() => {
            document.getElementById('paymentForm').style.display = 'block';
            document.getElementById('paymentStatus').style.display = 'none';
            document.getElementById('initiatePayment').disabled = false;
            document.getElementById('initiatePayment').textContent = 'Try Again';
        }, 5000);
    }
}
</script>

<?php include '../includes/footer.php'; ?>
