<?php
/**
 * Jengo Hardware - Order Confirmation Page
 * Display order confirmation after successful checkout
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
    die("Database connection failed: " . $e->getMessage());
}

// Get order number from URL
$order_number = $_GET['order'] ?? '';

if (empty($order_number)) {
    header("Location: index.php");
    exit();
}

// Fetch order details
$stmt = $pdo->prepare("
    SELECT o.* 
    FROM orders o 
    WHERE o.order_number = ?
");
$stmt->execute([$order_number]);
$order = $stmt->fetch();

if (!$order) {
    header("Location: index.php");
    exit();
}

// Fetch order items
$stmt = $pdo->prepare("
    SELECT * FROM order_items 
    WHERE order_id = ?
    ORDER BY id
");
$stmt->execute([$order['id']]);
$order_items = $stmt->fetchAll();

// Decode shipping address
$shipping_address = json_decode($order['shipping_address'], true);

$page_title = "Order Confirmation - Jengo Hardware";
include 'includes/header.php';
?>

<div class="order-confirmation-page">
    <div class="container">
        <div class="confirmation-header">
            <div class="success-icon">✓</div>
            <h1>Order Confirmed!</h1>
            <p>Thank you for your order. We've received it and will process it shortly.</p>
        </div>

        <div class="order-details-card">
            <div class="order-header">
                <div>
                    <h2>Order Details</h2>
                    <p class="order-number">Order #<?php echo htmlspecialchars($order['order_number']); ?></p>
                </div>
                <div class="order-status">
                    <span class="status-badge status-<?php echo $order['status']; ?>">
                        <?php echo ucfirst($order['status']); ?>
                    </span>
                </div>
            </div>

            <div class="order-info-grid">
                <div class="info-section">
                    <h3>Customer Information</h3>
                    <p><strong>Name:</strong> <?php echo htmlspecialchars($order['customer_name']); ?></p>
                    <p><strong>Email:</strong> <?php echo htmlspecialchars($order['customer_email']); ?></p>
                    <p><strong>Phone:</strong> <?php echo htmlspecialchars($order['customer_phone']); ?></p>
                </div>

                <div class="info-section">
                    <h3>Delivery Information</h3>
                    <p><strong>Method:</strong> <?php echo ucfirst($order['delivery_method']); ?></p>
                    <?php if ($order['delivery_method'] === 'pickup'): ?>
                        <p><strong>Location:</strong> Jengo Hardware Store, Nairobi</p>
                        <p class="text-muted">You will be notified when ready for pickup</p>
                    <?php else: ?>
                        <p><strong>Address:</strong></p>
                        <p>
                            <?php echo htmlspecialchars($shipping_address['line1'] ?? ''); ?><br>
                            <?php if (!empty($shipping_address['line2'])): ?>
                                <?php echo htmlspecialchars($shipping_address['line2']); ?><br>
                            <?php endif; ?>
                            <?php echo htmlspecialchars($shipping_address['city'] ?? ''); ?>
                            <?php echo !empty($shipping_address['postal_code']) ? htmlspecialchars($shipping_address['postal_code']) : ''; ?>
                        </p>
                    <?php endif; ?>
                </div>

                <div class="info-section">
                    <h3>Payment Information</h3>
                    <p><strong>Method:</strong> <?php echo ucfirst($order['payment_method']); ?></p>
                    <p><strong>Status:</strong> 
                        <span class="payment-status payment-<?php echo $order['payment_status']; ?>">
                            <?php echo ucfirst($order['payment_status']); ?>
                        </span>
                    </p>
                    <?php if ($order['payment_status'] === 'pending'): ?>
                        <p class="text-muted">Payment instructions will be sent to your email</p>
                    <?php endif; ?>
                </div>
            </div>

            <div class="order-items-section">
                <h3>Order Items</h3>
                <table class="order-items-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>SKU</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($order_items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['product_sku']); ?></td>
                                <td>KSh <?php echo number_format($item['price'], 2); ?></td>
                                <td><?php echo $item['quantity']; ?></td>
                                <td>KSh <?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="4" class="text-right"><strong>Subtotal:</strong></td>
                            <td><strong>KSh <?php echo number_format($order['subtotal'], 2); ?></strong></td>
                        </tr>
                        <tr>
                            <td colspan="4" class="text-right"><strong>Delivery Fee:</strong></td>
                            <td><strong>KSh <?php echo number_format($order['delivery_fee'], 2); ?></strong></td>
                        </tr>
                        <tr class="total-row">
                            <td colspan="4" class="text-right"><strong>Total:</strong></td>
                            <td><strong>KSh <?php echo number_format($order['total_amount'], 2); ?></strong></td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <?php if (!empty($order['notes'])): ?>
                <div class="order-notes">
                    <h3>Order Notes</h3>
                    <p><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                </div>
            <?php endif; ?>

            <div class="order-actions">
                <a href="index.php" class="btn btn-primary">Continue Shopping</a>
                <button onclick="window.print()" class="btn btn-outline">Print Order</button>
                <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in']): ?>
                    <a href="customer.php?tab=orders" class="btn btn-outline">View All Orders</a>
                <?php endif; ?>
            </div>
        </div>

        <div class="confirmation-footer">
            <h3>What's Next?</h3>
            <div class="next-steps">
                <div class="step">
                    <div class="step-number">1</div>
                    <div class="step-content">
                        <h4>Order Confirmation</h4>
                        <p>You'll receive a confirmation email shortly with your order details.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">2</div>
                    <div class="step-content">
                        <h4>Processing</h4>
                        <p>We'll prepare your order and verify payment if applicable.</p>
                    </div>
                </div>
                <div class="step">
                    <div class="step-number">3</div>
                    <div class="step-content">
                        <h4><?php echo $order['delivery_method'] === 'pickup' ? 'Ready for Pickup' : 'Delivery'; ?></h4>
                        <p>
                            <?php if ($order['delivery_method'] === 'pickup'): ?>
                                We'll notify you when your order is ready for pickup.
                            <?php else: ?>
                                Your order will be delivered to your specified address.
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<style>
.order-confirmation-page {
    padding: 40px 0;
    background: #f7fafc;
    min-height: calc(100vh - 400px);
}

.confirmation-header {
    text-align: center;
    margin-bottom: 40px;
}

.success-icon {
    width: 80px;
    height: 80px;
    background: linear-gradient(135deg, #48bb78, #38a169);
    color: white;
    font-size: 50px;
    border-radius: 50%;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    margin-bottom: 20px;
    box-shadow: 0 10px 30px rgba(72, 187, 120, 0.3);
}

.confirmation-header h1 {
    font-size: 32px;
    color: #2d3748;
    margin-bottom: 10px;
}

.confirmation-header p {
    font-size: 18px;
    color: #718096;
}

.order-details-card {
    background: white;
    border-radius: 12px;
    padding: 40px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    margin-bottom: 30px;
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    padding-bottom: 20px;
    border-bottom: 2px solid #e2e8f0;
    margin-bottom: 30px;
}

.order-header h2 {
    font-size: 24px;
    color: #2d3748;
    margin-bottom: 5px;
}

.order-number {
    font-size: 14px;
    color: #718096;
}

.status-badge {
    display: inline-block;
    padding: 6px 16px;
    border-radius: 20px;
    font-size: 14px;
    font-weight: 600;
    text-transform: uppercase;
}

.status-pending {
    background: #feebc8;
    color: #7c2d12;
}

.status-processing {
    background: #bee3f8;
    color: #2c5282;
}

.status-shipped {
    background: #c6f6d5;
    color: #22543d;
}

.payment-status {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 13px;
    font-weight: 600;
}

.payment-pending {
    background: #feebc8;
    color: #7c2d12;
}

.payment-paid {
    background: #c6f6d5;
    color: #22543d;
}

.order-info-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 30px;
    margin-bottom: 30px;
}

.info-section h3 {
    font-size: 16px;
    color: #2d3748;
    margin-bottom: 15px;
    padding-bottom: 10px;
    border-bottom: 1px solid #e2e8f0;
}

.info-section p {
    margin-bottom: 8px;
    color: #4a5568;
    line-height: 1.6;
}

.order-items-section {
    margin: 30px 0;
}

.order-items-section h3 {
    font-size: 18px;
    margin-bottom: 20px;
}

.order-items-table {
    width: 100%;
    border-collapse: collapse;
}

.order-items-table th,
.order-items-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

.order-items-table th {
    background: #f7fafc;
    font-weight: 600;
    color: #2d3748;
}

.order-items-table tfoot td {
    font-weight: 600;
    padding-top: 15px;
}

.total-row td {
    font-size: 18px;
    color: #667eea;
    border-top: 2px solid #e2e8f0;
    padding-top: 15px;
}

.text-right {
    text-align: right;
}

.order-notes {
    background: #f7fafc;
    padding: 20px;
    border-radius: 8px;
    margin: 20px 0;
}

.order-notes h3 {
    font-size: 16px;
    margin-bottom: 10px;
}

.order-actions {
    display: flex;
    gap: 15px;
    margin-top: 30px;
    padding-top: 30px;
    border-top: 1px solid #e2e8f0;
}

.confirmation-footer {
    background: white;
    border-radius: 12px;
    padding: 40px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.confirmation-footer h3 {
    text-align: center;
    font-size: 24px;
    margin-bottom: 30px;
}

.next-steps {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 30px;
}

.step {
    display: flex;
    gap: 15px;
}

.step-number {
    width: 50px;
    height: 50px;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    border-radius: 50%;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 24px;
    font-weight: 700;
    flex-shrink: 0;
}

.step-content h4 {
    font-size: 18px;
    margin-bottom: 8px;
    color: #2d3748;
}

.step-content p {
    color: #718096;
    line-height: 1.6;
}

@media print {
    .order-actions,
    .confirmation-footer,
    .main-header,
    .main-footer {
        display: none;
    }
}

@media (max-width: 768px) {
    .order-details-card {
        padding: 20px;
    }
    
    .order-header {
        flex-direction: column;
        gap: 15px;
    }
    
    .order-info-grid {
        grid-template-columns: 1fr;
    }
    
    .order-items-table {
        font-size: 14px;
    }
    
    .order-items-table th,
    .order-items-table td {
        padding: 8px 5px;
    }
    
    .order-actions {
        flex-direction: column;
    }
    
    .next-steps {
        grid-template-columns: 1fr;
    }
}
</style>

<?php include 'includes/footer.php'; ?>
