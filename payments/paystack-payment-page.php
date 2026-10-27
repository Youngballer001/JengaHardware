<?php
/**
 * Paystack Payment Page
 * Inline payment with Paystack Popup
 */

session_start();

// Redirect if no order in session
if (!isset($_SESSION['last_order'])) {
    header("Location: ../shop.php");
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
        header("Location: ../shop.php");
        exit();
    }
    
    // Load Paystack config to get public key
    require_once 'paystack-config.php';
    $public_key = PAYSTACK_CONFIG['public_key'];
    
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf_token = $_SESSION['csrf_token'];

$page_title = "Paystack Payment - Jengo Hardware";
include '../includes/header.php';
?>

<div class="paystack-payment-page">
    <div class="container">
        <div class="payment-container">
            <div class="payment-header">
                <div class="paystack-logo">
                    <svg width="120" height="40" viewBox="0 0 120 40" fill="#00C3F7">
                        <text x="10" y="25" font-family="Arial" font-size="20" font-weight="bold">Paystack</text>
                    </svg>
                </div>
                <h1>Complete Payment</h1>
                <p>Order: <?php echo htmlspecialchars($order['order_number']); ?></p>
            </div>

            <div class="payment-amount">
                <div class="amount-label">Amount to Pay</div>
                <div class="amount-value">KSh <?php echo number_format($order_details['total_amount'], 2); ?></div>
            </div>

            <div class="payment-info">
                <p><strong>Customer:</strong> <?php echo htmlspecialchars($order_details['customer_name']); ?></p>
                <p><strong>Email:</strong> <?php echo htmlspecialchars($order_details['customer_email']); ?></p>
            </div>

            <button id="paystackButton" class="btn btn-primary btn-lg btn-block">
                Pay with Paystack
            </button>

            <div class="payment-methods">
                <h4>We Accept:</h4>
                <div class="method-icons">
                    <span>💳 Cards</span>
                    <span>📱 Mobile Money</span>
                    <span>🏦 Bank Transfer</span>
                </div>
            </div>

            <div class="payment-footer">
                <a href="../order-confirmation.php?order=<?php echo urlencode($order['order_number']); ?>" 
                   class="btn btn-outline">View Order Details</a>
            </div>
        </div>
    </div>
</div>

<style>
.paystack-payment-page {
    padding: 40px 0;
    background: linear-gradient(135deg, #00C3F7 0%, #0082B3 100%);
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
    background: linear-gradient(135deg, #00C3F7, #0082B3);
    color: white;
    padding: 30px;
    text-align: center;
}

.paystack-logo {
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
    color: #00C3F7;
}

.payment-info {
    padding: 20px 30px;
    border-bottom: 1px solid #e2e8f0;
}

.payment-info p {
    margin: 10px 0;
    font-size: 14px;
}

.btn-block {
    margin: 30px;
}

.payment-methods {
    padding: 20px 30px;
    background: #f7fafc;
}

.payment-methods h4 {
    font-size: 14px;
    margin-bottom: 10px;
    text-align: center;
}

.method-icons {
    display: flex;
    justify-content: space-around;
    font-size: 13px;
}

.payment-footer {
    padding: 20px 30px;
    border-top: 1px solid #e2e8f0;
    text-align: center;
}
</style>

<script src="https://js.paystack.co/v1/inline.js"></script>
<script>
document.getElementById('paystackButton').addEventListener('click', function() {
    // Initialize payment
    fetch('paystack-initialize.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: new URLSearchParams({
            'order_number': '<?php echo $order['order_number']; ?>',
            'csrf_token': '<?php echo $csrf_token; ?>'
        })
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            // Redirect to Paystack payment page
            window.location.href = data.data.authorization_url;
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        console.error('Error:', error);
        showToast('An error occurred. Please try again.', 'error');
    });
});

// Alternative: Inline Paystack Popup (uncomment to use)
/*
function payWithPaystack() {
    var handler = PaystackPop.setup({
        key: '<?php echo $public_key; ?>',
        email: '<?php echo htmlspecialchars($order_details['customer_email']); ?>',
        amount: <?php echo $order_details['total_amount'] * 100; ?>, // Amount in kobo
        currency: 'KES',
        ref: 'PS-<?php echo $order['order_number']; ?>',
        metadata: {
            order_number: '<?php echo $order['order_number']; ?>',
            customer_name: '<?php echo htmlspecialchars($order_details['customer_name']); ?>'
        },
        callback: function(response) {
            // Payment successful
            window.location.href = 'paystack-callback.php?reference=' + response.reference;
        },
        onClose: function() {
            showToast('Payment cancelled', 'warning');
        }
    });
    handler.openIframe();
}
*/
</script>

<?php include '../includes/footer.php'; ?>
