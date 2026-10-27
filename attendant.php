<?php
/**
 * Attendant Dashboard
 * Order processing, inventory checks, and customer service
 */

session_start();

// Check authentication and role
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_role'] !== 'attendant') {
    header("Location: dashboard.php");
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

$active_tab = $_GET['tab'] ?? 'overview';

// Get statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'processing' THEN 1 ELSE 0 END) as processing_orders,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN 1 ELSE 0 END) as today_orders
    FROM orders
");
$order_stats = $stmt->fetch();

$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_products,
        SUM(CASE WHEN stock < 10 THEN 1 ELSE 0 END) as low_stock_count,
        SUM(CASE WHEN stock = 0 THEN 1 ELSE 0 END) as out_of_stock_count
    FROM products
    WHERE status = 'active'
");
$inventory_stats = $stmt->fetch();

$page_title = "Attendant Dashboard - Jengo Hardware";
include 'includes/header.php';
?>

<div class="attendant-dashboard">
    <div class="container">
        <div class="dashboard-header">
            <h1>Attendant Dashboard</h1>
            <div class="header-actions">
                <span class="user-name">Welcome, <?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                <a href="auth.php?action=logout" class="btn btn-outline btn-sm">Logout</a>
            </div>
        </div>

        <div class="dashboard-tabs">
            <a href="?tab=overview" class="tab-btn <?php echo $active_tab == 'overview' ? 'active' : ''; ?>">
                📊 Overview
            </a>
            <a href="?tab=orders" class="tab-btn <?php echo $active_tab == 'orders' ? 'active' : ''; ?>">
                📦 Orders
            </a>
            <a href="?tab=inventory" class="tab-btn <?php echo $active_tab == 'inventory' ? 'active' : ''; ?>">
                📋 Inventory
            </a>
            <a href="shop.php" class="tab-btn">
                🛍️ Shop
            </a>
        </div>

        <?php if ($active_tab == 'overview'): ?>
            <!-- Overview Tab -->
            <div class="stats-grid">
                <div class="stat-card blue">
                    <div class="stat-icon">📦</div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo $order_stats['total_orders']; ?></div>
                        <div class="stat-label">Total Orders</div>
                    </div>
                </div>

                <div class="stat-card yellow">
                    <div class="stat-icon">⏳</div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo $order_stats['pending_orders']; ?></div>
                        <div class="stat-label">Pending Orders</div>
                    </div>
                </div>

                <div class="stat-card green">
                    <div class="stat-icon">🔄</div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo $order_stats['processing_orders']; ?></div>
                        <div class="stat-label">Processing</div>
                    </div>
                </div>

                <div class="stat-card purple">
                    <div class="stat-icon">📅</div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo $order_stats['today_orders']; ?></div>
                        <div class="stat-label">Today's Orders</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid">
                <!-- Low Stock Alert -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>⚠️ Inventory Alerts</h2>
                    </div>
                    <div class="card-body">
                        <div class="alert-item">
                            <span class="alert-label">Low Stock Items:</span>
                            <span class="alert-value warning"><?php echo $inventory_stats['low_stock_count']; ?></span>
                        </div>
                        <div class="alert-item">
                            <span class="alert-label">Out of Stock:</span>
                            <span class="alert-value danger"><?php echo $inventory_stats['out_of_stock_count']; ?></span>
                        </div>
                        <a href="?tab=inventory&filter=low" class="btn btn-sm btn-outline">View Details</a>
                    </div>
                </div>

                <!-- Recent Orders -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>📦 Recent Orders</h2>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $pdo->query("
                            SELECT * FROM orders
                            ORDER BY created_at DESC
                            LIMIT 5
                        ");
                        $recent_orders = $stmt->fetchAll();
                        ?>
                        
                        <?php if (empty($recent_orders)): ?>
                            <p class="text-muted">No recent orders</p>
                        <?php else: ?>
                            <?php foreach ($recent_orders as $order): ?>
                                <div class="mini-order-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                                        <span class="status-badge status-<?php echo $order['status']; ?>">
                                            <?php echo ucfirst($order['status']); ?>
                                        </span>
                                    </div>
                                    <div class="text-muted">KSh <?php echo number_format($order['total_amount'], 2); ?></div>
                                </div>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        
                        <a href="?tab=orders" class="btn btn-sm btn-primary">View All Orders</a>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab == 'orders'): ?>
            <!-- Orders Tab -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Order Management</h2>
                </div>
                <div class="card-body">
                    <?php
                    $filter_status = $_GET['status'] ?? '';
                    
                    $query = "
                        SELECT o.*, u.name as customer_name, u.email as customer_email
                        FROM orders o
                        LEFT JOIN users u ON o.user_id = u.id
                    ";
                    $params = [];
                    
                    if ($filter_status) {
                        $query .= " WHERE o.status = ?";
                        $params[] = $filter_status;
                    }
                    
                    $query .= " ORDER BY o.created_at DESC LIMIT 50";
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $orders = $stmt->fetchAll();
                    ?>
                    
                    <div class="orders-filter">
                        <a href="?tab=orders" class="filter-btn <?php echo !$filter_status ? 'active' : ''; ?>">All</a>
                        <a href="?tab=orders&status=pending" class="filter-btn <?php echo $filter_status == 'pending' ? 'active' : ''; ?>">Pending</a>
                        <a href="?tab=orders&status=processing" class="filter-btn <?php echo $filter_status == 'processing' ? 'active' : ''; ?>">Processing</a>
                        <a href="?tab=orders&status=shipped" class="filter-btn <?php echo $filter_status == 'shipped' ? 'active' : ''; ?>">Shipped</a>
                        <a href="?tab=orders&status=delivered" class="filter-btn <?php echo $filter_status == 'delivered' ? 'active' : ''; ?>">Delivered</a>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Order #</th>
                                    <th>Customer</th>
                                    <th>Date</th>
                                    <th>Amount</th>
                                    <th>Payment</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                        <td>
                                            <?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?>
                                            <br><small class="text-muted"><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                        <td>KSh <?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td>
                                            <span class="payment-badge payment-<?php echo $order['payment_status']; ?>">
                                                <?php echo ucfirst($order['payment_status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <a href="order-confirmation.php?order=<?php echo urlencode($order['order_number']); ?>" 
                                               class="btn btn-xs btn-outline">View</a>
                                            <button onclick="openOrderStatusModal(<?php echo $order['id']; ?>, '<?php echo $order['order_number']; ?>', '<?php echo addslashes($order['customer_name'] ?? 'N/A'); ?>', '<?php echo $order['status']; ?>')" 
                                                    class="btn btn-xs btn-primary">Update Status</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab == 'inventory'): ?>
            <!-- Inventory Tab -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Inventory Management</h2>
                </div>
                <div class="card-body">
                    <?php
                    $filter = $_GET['filter'] ?? '';
                    
                    $query = "
                        SELECT p.*, c.name as category_name
                        FROM products p
                        LEFT JOIN categories c ON p.category_id = c.id
                        WHERE p.status = 'active'
                    ";
                    
                    if ($filter == 'low') {
                        $query .= " AND p.stock < 10 AND p.stock > 0";
                    } elseif ($filter == 'out') {
                        $query .= " AND p.stock = 0";
                    }
                    
                    $query .= " ORDER BY p.stock ASC, p.name ASC";
                    
                    $stmt = $pdo->query($query);
                    $products = $stmt->fetchAll();
                    ?>
                    
                    <div class="orders-filter">
                        <a href="?tab=inventory" class="filter-btn <?php echo !$filter ? 'active' : ''; ?>">All Products</a>
                        <a href="?tab=inventory&filter=low" class="filter-btn <?php echo $filter == 'low' ? 'active' : ''; ?>">
                            Low Stock (<?php echo $inventory_stats['low_stock_count']; ?>)
                        </a>
                        <a href="?tab=inventory&filter=out" class="filter-btn <?php echo $filter == 'out' ? 'active' : ''; ?>">
                            Out of Stock (<?php echo $inventory_stats['out_of_stock_count']; ?>)
                        </a>
                    </div>

                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>SKU</th>
                                    <th>Product Name</th>
                                    <th>Category</th>
                                    <th>Price</th>
                                    <th>Stock</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($products as $product): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($product['sku']); ?></td>
                                        <td><strong><?php echo htmlspecialchars($product['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($product['category_name'] ?? 'N/A'); ?></td>
                                        <td>KSh <?php echo number_format($product['price'], 2); ?></td>
                                        <td>
                                            <span class="stock-indicator <?php 
                                                echo $product['stock'] == 0 ? 'out' : ($product['stock'] < 10 ? 'low' : 'good'); 
                                            ?>">
                                                <?php echo $product['stock']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($product['stock'] == 0): ?>
                                                <span class="badge badge-danger">Out of Stock</span>
                                            <?php elseif ($product['stock'] < 10): ?>
                                                <span class="badge badge-warning">Low Stock</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">In Stock</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.attendant-dashboard {
    padding: 30px 0;
    background: #f7fafc;
    min-height: calc(100vh - 400px);
}

.dashboard-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 30px;
}

.dashboard-header h1 {
    font-size: 28px;
    color: #2d3748;
}

.header-actions {
    display: flex;
    align-items: center;
    gap: 15px;
}

.user-name {
    font-size: 14px;
    color: #718096;
}

.dashboard-tabs {
    display: flex;
    gap: 10px;
    margin-bottom: 30px;
    flex-wrap: wrap;
}

.tab-btn {
    padding: 10px 20px;
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-weight: 500;
    transition: all 0.3s;
}

.tab-btn:hover,
.tab-btn.active {
    background: #667eea;
    color: white;
    border-color: #667eea;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 25px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.stat-card.blue { border-left: 4px solid #4299e1; }
.stat-card.yellow { border-left: 4px solid #ed8936; }
.stat-card.green { border-left: 4px solid #48bb78; }
.stat-card.purple { border-left: 4px solid #9f7aea; }

.stat-icon {
    font-size: 48px;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    color: #2d3748;
}

.stat-label {
    font-size: 14px;
    color: #718096;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.dashboard-card,
.content-card {
    background: white;
    border-radius: 8px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    overflow: hidden;
}

.card-header {
    padding: 20px;
    border-bottom: 1px solid #e2e8f0;
}

.card-header h2 {
    font-size: 18px;
    color: #2d3748;
}

.card-body {
    padding: 20px;
}

.alert-item {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #e2e8f0;
}

.alert-value {
    font-weight: 700;
    font-size: 18px;
}

.alert-value.warning { color: #ed8936; }
.alert-value.danger { color: #f56565; }

.mini-order-item {
    padding: 12px 0;
    border-bottom: 1px solid #e2e8f0;
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.stock-indicator {
    padding: 4px 12px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
}

.stock-indicator.good { background: #c6f6d5; color: #22543d; }
.stock-indicator.low { background: #feebc8; color: #7c2d12; }
.stock-indicator.out { background: #fed7d7; color: #742a2a; }

.badge {
    padding: 4px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.badge-success { background: #c6f6d5; color: #22543d; }
.badge-warning { background: #feebc8; color: #7c2d12; }
.badge-danger { background: #fed7d7; color: #742a2a; }

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<?php
// Display attendant messages
if (isset($_SESSION['attendant_message'])):
    $message_type = $_SESSION['attendant_message_type'] ?? 'success';
    $message = $_SESSION['attendant_message'];
    unset($_SESSION['attendant_message'], $_SESSION['attendant_message_type']);
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        showToast('<?php echo addslashes($message); ?>', '<?php echo $message_type; ?>');
    });
</script>
<?php endif; ?>

<?php
// Generate CSRF token if not exists
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
?>

<?php include 'includes/attendant-modals.php'; ?>

<?php include 'includes/footer.php'; ?>
