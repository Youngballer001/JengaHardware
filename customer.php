<?php
/**
 * Customer Dashboard
 * Order tracking, profile management, order history
 */

session_start();

// Check authentication
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: auth.php?action=login");
    exit();
}

// Check role
if ($_SESSION['user_role'] !== 'customer') {
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
    die("Database connection failed: " . $e->getMessage());
}

$user_id = $_SESSION['user_id'];
$active_tab = $_GET['tab'] ?? 'overview';

// Get user details
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

// Get order statistics
$stmt = $pdo->prepare("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as completed_orders,
        SUM(total_amount) as total_spent
    FROM orders
    WHERE user_id = ?
");
$stmt->execute([$user_id]);
$stats = $stmt->fetch();

// Get recent orders
$stmt = $pdo->prepare("
    SELECT * FROM orders
    WHERE user_id = ?
    ORDER BY created_at DESC
    LIMIT 10
");
$stmt->execute([$user_id]);
$recent_orders = $stmt->fetchAll();

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = "My Account - Jengo Hardware";
include 'includes/header.php';
?>

<div class="customer-dashboard">
    <div class="container">
        <div class="dashboard-layout">
            <!-- Sidebar -->
            <aside class="dashboard-sidebar">
                <div class="user-profile">
                    <div class="user-avatar">
                        <?php echo strtoupper(substr($user['name'], 0, 2)); ?>
                    </div>
                    <h3><?php echo htmlspecialchars($user['name']); ?></h3>
                    <p class="user-email"><?php echo htmlspecialchars($user['email']); ?></p>
                </div>

                <nav class="dashboard-nav">
                    <a href="?tab=overview" class="<?php echo $active_tab == 'overview' ? 'active' : ''; ?>">
                        📊 Overview
                    </a>
                    <a href="?tab=orders" class="<?php echo $active_tab == 'orders' ? 'active' : ''; ?>">
                        📦 My Orders
                    </a>
                    <a href="?tab=profile" class="<?php echo $active_tab == 'profile' ? 'active' : ''; ?>">
                        👤 Profile Settings
                    </a>
                    <a href="shop.php">
                        🛍️ Continue Shopping
                    </a>
                    <a href="auth.php?action=logout" class="text-danger">
                        🚪 Logout
                    </a>
                </nav>
            </aside>

            <!-- Main Content -->
            <main class="dashboard-content">
                <?php if ($active_tab == 'overview'): ?>
                    <!-- Overview Tab -->
                    <h1>Dashboard Overview</h1>
                    
                    <div class="stats-grid">
                        <div class="stat-card">
                            <div class="stat-icon">📦</div>
                            <div class="stat-details">
                                <div class="stat-value"><?php echo $stats['total_orders']; ?></div>
                                <div class="stat-label">Total Orders</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">⏳</div>
                            <div class="stat-details">
                                <div class="stat-value"><?php echo $stats['pending_orders']; ?></div>
                                <div class="stat-label">Pending Orders</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">✅</div>
                            <div class="stat-details">
                                <div class="stat-value"><?php echo $stats['completed_orders']; ?></div>
                                <div class="stat-label">Completed</div>
                            </div>
                        </div>
                        
                        <div class="stat-card">
                            <div class="stat-icon">💰</div>
                            <div class="stat-details">
                                <div class="stat-value">KSh <?php echo number_format($stats['total_spent'] ?? 0, 2); ?></div>
                                <div class="stat-label">Total Spent</div>
                            </div>
                        </div>
                    </div>

                    <div class="recent-orders-section">
                        <h2>Recent Orders</h2>
                        <?php if (empty($recent_orders)): ?>
                            <div class="empty-state">
                                <p>No orders yet</p>
                                <a href="shop.php" class="btn btn-primary">Start Shopping</a>
                            </div>
                        <?php else: ?>
                            <div class="orders-list">
                                <?php foreach ($recent_orders as $order): ?>
                                    <div class="order-item">
                                        <div class="order-header">
                                            <div>
                                                <strong>Order #<?php echo htmlspecialchars($order['order_number']); ?></strong>
                                                <span class="order-date"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></span>
                                            </div>
                                            <span class="status-badge status-<?php echo $order['status']; ?>">
                                                <?php echo ucfirst($order['status']); ?>
                                            </span>
                                        </div>
                                        <div class="order-details">
                                            <div class="order-amount">KSh <?php echo number_format($order['total_amount'], 2); ?></div>
                                            <div class="order-payment">
                                                Payment: <span class="payment-<?php echo $order['payment_status']; ?>">
                                                    <?php echo ucfirst($order['payment_status']); ?>
                                                </span>
                                            </div>
                                        </div>
                                        <div class="order-actions">
                                            <a href="order-confirmation.php?order=<?php echo urlencode($order['order_number']); ?>" 
                                               class="btn btn-sm btn-outline">View Details</a>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>

                <?php elseif ($active_tab == 'orders'): ?>
                    <!-- Orders Tab -->
                    <h1>My Orders</h1>
                    
                    <?php
                    // Get all orders with filters
                    $filter_status = $_GET['status'] ?? '';
                    
                    $query = "SELECT * FROM orders WHERE user_id = ?";
                    $params = [$user_id];
                    
                    if ($filter_status) {
                        $query .= " AND status = ?";
                        $params[] = $filter_status;
                    }
                    
                    $query .= " ORDER BY created_at DESC";
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $all_orders = $stmt->fetchAll();
                    ?>
                    
                    <div class="orders-filter">
                        <a href="?tab=orders" class="filter-btn <?php echo !$filter_status ? 'active' : ''; ?>">
                            All Orders
                        </a>
                        <a href="?tab=orders&status=pending" class="filter-btn <?php echo $filter_status == 'pending' ? 'active' : ''; ?>">
                            Pending
                        </a>
                        <a href="?tab=orders&status=processing" class="filter-btn <?php echo $filter_status == 'processing' ? 'active' : ''; ?>">
                            Processing
                        </a>
                        <a href="?tab=orders&status=shipped" class="filter-btn <?php echo $filter_status == 'shipped' ? 'active' : ''; ?>">
                            Shipped
                        </a>
                        <a href="?tab=orders&status=delivered" class="filter-btn <?php echo $filter_status == 'delivered' ? 'active' : ''; ?>">
                            Delivered
                        </a>
                    </div>

                    <?php if (empty($all_orders)): ?>
                        <div class="empty-state">
                            <p>No orders found</p>
                        </div>
                    <?php else: ?>
                        <div class="orders-table-container">
                            <table class="orders-table">
                                <thead>
                                    <tr>
                                        <th>Order Number</th>
                                        <th>Date</th>
                                        <th>Amount</th>
                                        <th>Payment</th>
                                        <th>Status</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($all_orders as $order): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
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
                                                   class="btn btn-sm btn-outline">View</a>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    <?php endif; ?>

                <?php elseif ($active_tab == 'profile'): ?>
                    <!-- Profile Tab -->
                    <h1>Profile Settings</h1>
                    
                    <?php if (isset($_SESSION['profile_success'])): ?>
                        <div class="alert alert-success"><?php echo $_SESSION['profile_success']; unset($_SESSION['profile_success']); ?></div>
                    <?php endif; ?>
                    
                    <?php if (isset($_SESSION['profile_error'])): ?>
                        <div class="alert alert-error"><?php echo $_SESSION['profile_error']; unset($_SESSION['profile_error']); ?></div>
                    <?php endif; ?>

                    <div class="profile-section">
                        <h2>Personal Information</h2>
                        <form action="actions.php" method="POST" class="profile-form">
                            <input type="hidden" name="action" value="update_profile">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="form-group">
                                <label for="name">Full Name</label>
                                <input type="text" id="name" name="name" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['name']); ?>" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email" id="email" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['email']); ?>" disabled>
                                <small>Email cannot be changed</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="phone">Phone Number</label>
                                <input type="tel" id="phone" name="phone" class="form-control" 
                                       value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Update Profile</button>
                        </form>
                    </div>

                    <div class="profile-section">
                        <h2>Change Password</h2>
                        <form id="passwordForm" class="profile-form">
                            <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>">
                            
                            <div class="form-group">
                                <label for="current_password">Current Password</label>
                                <input type="password" id="current_password" name="current_password" 
                                       class="form-control" required>
                            </div>
                            
                            <div class="form-group">
                                <label for="new_password">New Password</label>
                                <input type="password" id="new_password" name="new_password" 
                                       class="form-control" required>
                                <small>At least 8 characters with uppercase, lowercase, and number</small>
                            </div>
                            
                            <div class="form-group">
                                <label for="confirm_password">Confirm New Password</label>
                                <input type="password" id="confirm_password" name="confirm_password" 
                                       class="form-control" required>
                            </div>
                            
                            <button type="submit" class="btn btn-primary">Change Password</button>
                        </form>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</div>

<style>
.customer-dashboard {
    padding: 30px 0;
    background: #f7fafc;
    min-height: calc(100vh - 400px);
}

.dashboard-layout {
    display: grid;
    grid-template-columns: 280px 1fr;
    gap: 30px;
}

.dashboard-sidebar {
    background: white;
    border-radius: 8px;
    padding: 20px;
    height: fit-content;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.user-profile {
    text-align: center;
    padding-bottom: 20px;
    border-bottom: 1px solid #e2e8f0;
    margin-bottom: 20px;
}

.user-avatar {
    width: 80px;
    height: 80px;
    border-radius: 50%;
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 32px;
    font-weight: 700;
    margin: 0 auto 15px;
}

.user-profile h3 {
    font-size: 18px;
    margin-bottom: 5px;
}

.user-email {
    font-size: 14px;
    color: #718096;
}

.dashboard-nav a {
    display: block;
    padding: 12px 15px;
    color: #4a5568;
    border-radius: 6px;
    margin-bottom: 5px;
    transition: all 0.3s;
}

.dashboard-nav a:hover,
.dashboard-nav a.active {
    background: #f7fafc;
    color: #667eea;
}

.dashboard-nav a.text-danger:hover {
    background: #fed7d7;
    color: #c53030;
}

.dashboard-content {
    background: white;
    border-radius: 8px;
    padding: 30px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
}

.dashboard-content h1 {
    font-size: 28px;
    margin-bottom: 30px;
    color: #2d3748;
}

.stats-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
    gap: 20px;
    margin-bottom: 40px;
}

.stat-card {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 25px;
    border-radius: 8px;
    display: flex;
    align-items: center;
    gap: 20px;
}

.stat-icon {
    font-size: 48px;
}

.stat-value {
    font-size: 32px;
    font-weight: 700;
    margin-bottom: 5px;
}

.stat-label {
    font-size: 14px;
    opacity: 0.9;
}

.recent-orders-section h2 {
    font-size: 20px;
    margin-bottom: 20px;
}

.orders-list {
    display: flex;
    flex-direction: column;
    gap: 15px;
}

.order-item {
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px;
    transition: all 0.3s;
}

.order-item:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.order-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 15px;
}

.order-date {
    font-size: 14px;
    color: #718096;
    margin-left: 10px;
}

.order-details {
    display: flex;
    gap: 30px;
    margin-bottom: 15px;
    font-size: 14px;
}

.order-amount {
    font-size: 18px;
    font-weight: 700;
    color: #667eea;
}

.status-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.status-pending { background: #feebc8; color: #7c2d12; }
.status-processing { background: #bee3f8; color: #2c5282; }
.status-shipped { background: #c6f6d5; color: #22543d; }
.status-delivered { background: #c6f6d5; color: #22543d; }
.status-cancelled { background: #fed7d7; color: #742a2a; }

.payment-badge, .payment-pending, .payment-paid, .payment-failed {
    padding: 3px 10px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.payment-pending { background: #feebc8; color: #7c2d12; }
.payment-paid { background: #c6f6d5; color: #22543d; }
.payment-failed { background: #fed7d7; color: #742a2a; }

.orders-filter {
    display: flex;
    gap: 10px;
    margin-bottom: 20px;
    flex-wrap: wrap;
}

.filter-btn {
    padding: 8px 16px;
    border: 1px solid #e2e8f0;
    border-radius: 6px;
    font-size: 14px;
    transition: all 0.3s;
}

.filter-btn:hover,
.filter-btn.active {
    background: #667eea;
    color: white;
    border-color: #667eea;
}

.orders-table-container {
    overflow-x: auto;
}

.orders-table {
    width: 100%;
    border-collapse: collapse;
}

.orders-table th,
.orders-table td {
    padding: 12px;
    text-align: left;
    border-bottom: 1px solid #e2e8f0;
}

.orders-table th {
    background: #f7fafc;
    font-weight: 600;
    color: #2d3748;
}

.profile-section {
    background: #f7fafc;
    padding: 25px;
    border-radius: 8px;
    margin-bottom: 30px;
}

.profile-section h2 {
    font-size: 18px;
    margin-bottom: 20px;
}

.profile-form .form-group {
    margin-bottom: 20px;
}

.empty-state {
    text-align: center;
    padding: 60px 20px;
    color: #718096;
}

@media (max-width: 768px) {
    .dashboard-layout {
        grid-template-columns: 1fr;
    }
    
    .stats-grid {
        grid-template-columns: 1fr;
    }
}
</style>

<script>
// Handle password change
document.getElementById('passwordForm')?.addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    formData.append('action', 'change_password');
    
    fetch('actions.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            showToast(data.message, 'success');
            this.reset();
        } else {
            showToast(data.message, 'error');
        }
    })
    .catch(error => {
        showToast('An error occurred', 'error');
    });
});
</script>

<?php include 'includes/footer.php'; ?>
