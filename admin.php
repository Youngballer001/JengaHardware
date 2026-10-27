<?php
/**
 * Admin Dashboard
 * Complete management: products, categories, orders, users, reports
 */

session_start();

// Check authentication and role
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in'] || $_SESSION['user_role'] !== 'admin') {
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

// Get comprehensive statistics
$stmt = $pdo->query("
    SELECT 
        COUNT(*) as total_orders,
        SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_orders,
        SUM(CASE WHEN status = 'delivered' THEN 1 ELSE 0 END) as delivered_orders,
        SUM(total_amount) as total_revenue,
        SUM(CASE WHEN DATE(created_at) = CURDATE() THEN total_amount ELSE 0 END) as today_revenue
    FROM orders
");
$order_stats = $stmt->fetch();

$stmt = $pdo->query("SELECT COUNT(*) as total_products FROM products WHERE status = 'active'");
$product_count = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) as total_users FROM users WHERE role = 'customer'");
$customer_count = $stmt->fetchColumn();

$stmt = $pdo->query("SELECT COUNT(*) FROM products WHERE stock < 10 AND status = 'active'");
$low_stock_count = $stmt->fetchColumn();

// Generate CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$page_title = "Admin Dashboard - Jengo Hardware";
include 'includes/header.php';
?>

<div class="admin-dashboard">
    <div class="container-fluid">
        <div class="dashboard-header">
            <h1>🛠️ Admin Dashboard</h1>
            <div class="header-actions">
                <span class="admin-badge">Administrator</span>
                <span class="user-name"><?php echo htmlspecialchars($_SESSION['user_name']); ?></span>
                <a href="auth.php?action=logout" class="btn btn-outline btn-sm">Logout</a>
            </div>
        </div>

        <div class="dashboard-tabs">
            <a href="?tab=overview" class="tab-btn <?php echo $active_tab == 'overview' ? 'active' : ''; ?>">📊 Overview</a>
            <a href="?tab=orders" class="tab-btn <?php echo $active_tab == 'orders' ? 'active' : ''; ?>">📦 Orders</a>
            <a href="?tab=products" class="tab-btn <?php echo $active_tab == 'products' ? 'active' : ''; ?>">📋 Products</a>
            <a href="?tab=categories" class="tab-btn <?php echo $active_tab == 'categories' ? 'active' : ''; ?>">🏷️ Categories</a>
            <a href="?tab=users" class="tab-btn <?php echo $active_tab == 'users' ? 'active' : ''; ?>">👥 Users</a>
            <a href="?tab=reports" class="tab-btn <?php echo $active_tab == 'reports' ? 'active' : ''; ?>">📈 Reports</a>
        </div>

        <?php if ($active_tab == 'overview'): ?>
            <!-- Overview Tab -->
            <div class="stats-grid-large">
                <div class="stat-card revenue">
                    <div class="stat-icon">💰</div>
                    <div class="stat-details">
                        <div class="stat-value">KSh <?php echo number_format($order_stats['total_revenue'] ?? 0, 2); ?></div>
                        <div class="stat-label">Total Revenue</div>
                        <div class="stat-subtext">Today: KSh <?php echo number_format($order_stats['today_revenue'] ?? 0, 2); ?></div>
                    </div>
                </div>

                <div class="stat-card orders">
                    <div class="stat-icon">📦</div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo $order_stats['total_orders']; ?></div>
                        <div class="stat-label">Total Orders</div>
                        <div class="stat-subtext"><?php echo $order_stats['pending_orders']; ?> pending</div>
                    </div>
                </div>

                <div class="stat-card products">
                    <div class="stat-icon">📋</div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo $product_count; ?></div>
                        <div class="stat-label">Active Products</div>
                        <div class="stat-subtext warning"><?php echo $low_stock_count; ?> low stock</div>
                    </div>
                </div>

                <div class="stat-card customers">
                    <div class="stat-icon">👥</div>
                    <div class="stat-details">
                        <div class="stat-value"><?php echo $customer_count; ?></div>
                        <div class="stat-label">Customers</div>
                    </div>
                </div>
            </div>

            <div class="dashboard-grid-three">
                <!-- Recent Orders -->
                <div class="dashboard-card span-2">
                    <div class="card-header">
                        <h2>Recent Orders</h2>
                        <a href="?tab=orders" class="btn btn-sm btn-outline">View All</a>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $pdo->query("
                            SELECT o.*, u.name as customer_name
                            FROM orders o
                            LEFT JOIN users u ON o.user_id = u.id
                            ORDER BY o.created_at DESC
                            LIMIT 8
                        ");
                        $recent_orders = $stmt->fetchAll();
                        ?>
                        
                        <div class="table-responsive">
                            <table class="data-table compact">
                                <thead>
                                    <tr>
                                        <th>Order #</th>
                                        <th>Customer</th>
                                        <th>Amount</th>
                                        <th>Status</th>
                                        <th>Date</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($recent_orders as $order): ?>
                                        <tr>
                                            <td><strong><?php echo htmlspecialchars($order['order_number']); ?></strong></td>
                                            <td><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></td>
                                            <td>KSh <?php echo number_format($order['total_amount'], 2); ?></td>
                                            <td><span class="status-badge status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                            <td><?php echo date('M j', strtotime($order['created_at'])); ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- Low Stock Alert -->
                <div class="dashboard-card">
                    <div class="card-header">
                        <h2>⚠️ Low Stock</h2>
                    </div>
                    <div class="card-body">
                        <?php
                        $stmt = $pdo->query("
                            SELECT name, sku, stock
                            FROM products
                            WHERE stock < 10 AND stock > 0 AND status = 'active'
                            ORDER BY stock ASC
                            LIMIT 5
                        ");
                        $low_stock_products = $stmt->fetchAll();
                        ?>
                        
                        <?php if (empty($low_stock_products)): ?>
                            <p class="text-muted">All products well stocked!</p>
                        <?php else: ?>
                            <?php foreach ($low_stock_products as $product): ?>
                                <div class="alert-item">
                                    <div>
                                        <strong><?php echo htmlspecialchars($product['name']); ?></strong>
                                        <br><small class="text-muted"><?php echo htmlspecialchars($product['sku']); ?></small>
                                    </div>
                                    <span class="stock-badge low"><?php echo $product['stock']; ?></span>
                                </div>
                            <?php endforeach; ?>
                            <a href="?tab=products&filter=low" class="btn btn-sm btn-primary">View All</a>
                        <?php endif; ?>
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
                    $search = $_GET['search'] ?? '';
                    
                    $query = "
                        SELECT o.*, u.name as customer_name, u.email as customer_email
                        FROM orders o
                        LEFT JOIN users u ON o.user_id = u.id
                        WHERE 1=1
                    ";
                    $params = [];
                    
                    if ($filter_status) {
                        $query .= " AND o.status = ?";
                        $params[] = $filter_status;
                    }
                    
                    if ($search) {
                        $query .= " AND (o.order_number LIKE ? OR u.name LIKE ? OR o.customer_email LIKE ?)";
                        $search_param = "%{$search}%";
                        $params[] = $search_param;
                        $params[] = $search_param;
                        $params[] = $search_param;
                    }
                    
                    $query .= " ORDER BY o.created_at DESC LIMIT 100";
                    
                    $stmt = $pdo->prepare($query);
                    $stmt->execute($params);
                    $orders = $stmt->fetchAll();
                    ?>
                    
                    <div class="filters-row">
                        <div class="orders-filter">
                            <a href="?tab=orders" class="filter-btn <?php echo !$filter_status ? 'active' : ''; ?>">All</a>
                            <a href="?tab=orders&status=pending" class="filter-btn <?php echo $filter_status == 'pending' ? 'active' : ''; ?>">Pending</a>
                            <a href="?tab=orders&status=processing" class="filter-btn <?php echo $filter_status == 'processing' ? 'active' : ''; ?>">Processing</a>
                            <a href="?tab=orders&status=shipped" class="filter-btn <?php echo $filter_status == 'shipped' ? 'active' : ''; ?>">Shipped</a>
                            <a href="?tab=orders&status=delivered" class="filter-btn <?php echo $filter_status == 'delivered' ? 'active' : ''; ?>">Delivered</a>
                        </div>
                        
                        <form method="GET" class="search-form">
                            <input type="hidden" name="tab" value="orders">
                            <input type="text" name="search" placeholder="Search orders..." value="<?php echo htmlspecialchars($search); ?>" class="form-control">
                            <button type="submit" class="btn btn-primary">Search</button>
                        </form>
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
                                    <th>Delivery</th>
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
                                            <br><small><?php echo htmlspecialchars($order['customer_email']); ?></small>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                        <td>KSh <?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td><span class="payment-badge payment-<?php echo $order['payment_status']; ?>"><?php echo ucfirst($order['payment_status']); ?></span></td>
                                        <td><?php echo ucfirst($order['delivery_method']); ?></td>
                                        <td><span class="status-badge status-<?php echo $order['status']; ?>"><?php echo ucfirst($order['status']); ?></span></td>
                                        <td>
                                            <a href="order-confirmation.php?order=<?php echo urlencode($order['order_number']); ?>" class="btn btn-xs btn-outline">View</a>
                                            <button onclick="openOrderStatusModal(<?php echo $order['id']; ?>, '<?php echo $order['order_number']; ?>', '<?php echo $order['status']; ?>')" class="btn btn-xs btn-primary">Status</button>
                                            <button onclick="openPaymentStatusModal(<?php echo $order['id']; ?>, '<?php echo $order['order_number']; ?>', '<?php echo $order['payment_status']; ?>')" class="btn btn-xs btn-success">Payment</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab == 'products'): ?>
            <!-- Products Tab -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Product Management</h2>
                    <button class="btn btn-primary btn-sm" onclick="openProductModal('create')">+ Add Product</button>
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
                    
                    $query .= " ORDER BY p.name ASC";
                    
                    $stmt = $pdo->query($query);
                    $products = $stmt->fetchAll();
                    ?>
                    
                    <div class="orders-filter">
                        <a href="?tab=products" class="filter-btn <?php echo !$filter ? 'active' : ''; ?>">All Products</a>
                        <a href="?tab=products&filter=low" class="filter-btn <?php echo $filter == 'low' ? 'active' : ''; ?>">Low Stock</a>
                        <a href="?tab=products&filter=out" class="filter-btn <?php echo $filter == 'out' ? 'active' : ''; ?>">Out of Stock</a>
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
                                    <th>Actions</th>
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
                                            <span class="stock-badge <?php 
                                                echo $product['stock'] == 0 ? 'out' : ($product['stock'] < 10 ? 'low' : 'good'); 
                                            ?>">
                                                <?php echo $product['stock']; ?>
                                            </span>
                                        </td>
                                        <td>
                                            <?php if ($product['stock'] == 0): ?>
                                                <span class="badge badge-danger">Out</span>
                                            <?php elseif ($product['stock'] < 10): ?>
                                                <span class="badge badge-warning">Low</span>
                                            <?php else: ?>
                                                <span class="badge badge-success">Good</span>
                                            <?php endif; ?>
                                        </td>
                                        <td>
                                            <a href="product.php?id=<?php echo $product['id']; ?>" class="btn btn-xs btn-outline">View</a>
                                            <button onclick="openProductModal('edit', <?php echo $product['id']; ?>)" class="btn btn-xs btn-primary">Edit</button>
                                            <button onclick="confirmDelete('product', <?php echo $product['id']; ?>, '<?php echo addslashes($product['name']); ?>')" class="btn btn-xs btn-danger">Delete</button>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab == 'categories'): ?>
            <!-- Categories Tab -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Category Management</h2>
                    <button class="btn btn-primary btn-sm" onclick="openCategoryModal('create')">+ Add Category</button>
                </div>
                <div class="card-body">
                    <?php
                    $stmt = $pdo->query("
                        SELECT c.*, COUNT(p.id) as product_count
                        FROM categories c
                        LEFT JOIN products p ON c.id = p.category_id AND p.status = 'active'
                        WHERE c.status = 'active'
                        GROUP BY c.id
                        ORDER BY c.name ASC
                    ");
                    $categories = $stmt->fetchAll();
                    ?>
                    
                    <div class="categories-grid">
                        <?php foreach ($categories as $category): ?>
                            <div class="category-card-admin">
                                <div class="category-header">
                                    <h3><?php echo htmlspecialchars($category['name']); ?></h3>
                                    <span class="product-count"><?php echo $category['product_count']; ?> products</span>
                                </div>
                                <p class="category-description">
                                    <?php echo htmlspecialchars($category['description'] ?? 'No description'); ?>
                                </p>
                                <div class="category-actions">
                                    <a href="shop.php?category=<?php echo $category['id']; ?>" class="btn btn-xs btn-outline">View Products</a>
                                    <button onclick="openCategoryModal('edit', <?php echo $category['id']; ?>)" class="btn btn-xs btn-primary">Edit</button>
                                    <button onclick="confirmDelete('category', <?php echo $category['id']; ?>, '<?php echo addslashes($category['name']); ?>')" class="btn btn-xs btn-danger">Delete</button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab == 'users'): ?>
            <!-- Users Tab -->
            <div class="content-card">
                <div class="card-header">
                    <h2>User Management</h2>
                </div>
                <div class="card-body">
                    <?php
                    $stmt = $pdo->query("
                        SELECT * FROM users
                        ORDER BY created_at DESC
                    ");
                    $users = $stmt->fetchAll();
                    ?>
                    
                    <div class="table-responsive">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Email</th>
                                    <th>Phone</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Registered</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($users as $user): ?>
                                    <tr>
                                        <td><strong><?php echo htmlspecialchars($user['name']); ?></strong></td>
                                        <td><?php echo htmlspecialchars($user['email']); ?></td>
                                        <td><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></td>
                                        <td>
                                            <span class="role-badge role-<?php echo $user['role']; ?>">
                                                <?php echo ucfirst($user['role']); ?>
                                            </span>
                                        </td>
                                        <td>
                                            <span class="status-badge status-<?php echo $user['status']; ?>">
                                                <?php echo ucfirst($user['status']); ?>
                                            </span>
                                        </td>
                                        <td><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                        <td>
                                            <?php if ($user['id'] != $_SESSION['user_id']): ?>
                                                <button onclick="openUserStatusModal(<?php echo $user['id']; ?>, '<?php echo addslashes($user['name']); ?>', '<?php echo $user['status']; ?>')" class="btn btn-xs btn-primary">Status</button>
                                                <button onclick="openUserRoleModal(<?php echo $user['id']; ?>, '<?php echo addslashes($user['name']); ?>', '<?php echo $user['role']; ?>')" class="btn btn-xs btn-success">Role</button>
                                            <?php else: ?>
                                                <span class="text-muted">You</span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

        <?php elseif ($active_tab == 'reports'): ?>
            <!-- Reports Tab -->
            <div class="content-card">
                <div class="card-header">
                    <h2>Sales & Payment Reports</h2>
                </div>
                <div class="card-body">
                    <div class="reports-grid">
                        <!-- Revenue Report -->
                        <div class="report-card">
                            <h3>💰 Revenue Overview</h3>
                            <?php
                            $stmt = $pdo->query("
                                SELECT 
                                    SUM(total_amount) as total,
                                    SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 7 DAY) THEN total_amount ELSE 0 END) as last_7_days,
                                    SUM(CASE WHEN DATE(created_at) >= DATE_SUB(CURDATE(), INTERVAL 30 DAY) THEN total_amount ELSE 0 END) as last_30_days
                                FROM orders
                                WHERE payment_status = 'paid'
                            ");
                            $revenue = $stmt->fetch();
                            ?>
                            <div class="report-stat">
                                <span>Total Revenue:</span>
                                <strong>KSh <?php echo number_format($revenue['total'] ?? 0, 2); ?></strong>
                            </div>
                            <div class="report-stat">
                                <span>Last 7 Days:</span>
                                <strong>KSh <?php echo number_format($revenue['last_7_days'] ?? 0, 2); ?></strong>
                            </div>
                            <div class="report-stat">
                                <span>Last 30 Days:</span>
                                <strong>KSh <?php echo number_format($revenue['last_30_days'] ?? 0, 2); ?></strong>
                            </div>
                        </div>

                        <!-- Payment Methods Report -->
                        <div class="report-card">
                            <h3>💳 Payment Methods</h3>
                            <?php
                            $stmt = $pdo->query("
                                SELECT 
                                    payment_method,
                                    COUNT(*) as count,
                                    SUM(total_amount) as total
                                FROM orders
                                WHERE payment_status = 'paid'
                                GROUP BY payment_method
                            ");
                            $payment_methods = $stmt->fetchAll();
                            ?>
                            <?php foreach ($payment_methods as $method): ?>
                                <div class="report-stat">
                                    <span><?php echo ucfirst($method['payment_method']); ?>:</span>
                                    <strong><?php echo $method['count']; ?> orders (KSh <?php echo number_format($method['total'], 2); ?>)</strong>
                                </div>
                            <?php endforeach; ?>
                        </div>

                        <!-- Order Status Report -->
                        <div class="report-card">
                            <h3>📦 Order Status</h3>
                            <?php
                            $stmt = $pdo->query("
                                SELECT 
                                    status,
                                    COUNT(*) as count
                                FROM orders
                                GROUP BY status
                            ");
                            $order_statuses = $stmt->fetchAll();
                            ?>
                            <?php foreach ($order_statuses as $status): ?>
                                <div class="report-stat">
                                    <span><?php echo ucfirst($status['status']); ?>:</span>
                                    <strong><?php echo $status['count']; ?> orders</strong>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
        <?php endif; ?>
    </div>
</div>

<style>
.admin-dashboard {
    padding: 20px 0;
    background: #f7fafc;
    min-height: calc(100vh - 400px);
}

.container-fluid {
    max-width: 1400px;
    margin: 0 auto;
    padding: 0 20px;
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

.admin-badge {
    background: linear-gradient(135deg, #667eea, #764ba2);
    color: white;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 700;
}

.stats-grid-large {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
    gap: 20px;
    margin-bottom: 30px;
}

.stat-card {
    background: white;
    padding: 30px;
    border-radius: 10px;
    display: flex;
    align-items: center;
    gap: 20px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    transition: transform 0.3s;
}

.stat-card:hover {
    transform: translateY(-5px);
}

.stat-card.revenue { border-left: 5px solid #48bb78; }
.stat-card.orders { border-left: 5px solid #4299e1; }
.stat-card.products { border-left: 5px solid #ed8936; }
.stat-card.customers { border-left: 5px solid #9f7aea; }

.stat-value {
    font-size: 28px;
    font-weight: 700;
    color: #2d3748;
    margin-bottom: 5px;
}

.stat-subtext {
    font-size: 13px;
    color: #718096;
}

.stat-subtext.warning {
    color: #ed8936;
    font-weight: 600;
}

.dashboard-grid-three {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 20px;
    margin-bottom: 30px;
}

.span-2 {
    grid-column: span 2;
}

.filters-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
    flex-wrap: wrap;
    gap: 15px;
}

.search-form {
    display: flex;
    gap: 10px;
}

.search-form input {
    min-width: 300px;
}

.categories-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
    gap: 20px;
}

.category-card-admin {
    background: white;
    border: 1px solid #e2e8f0;
    border-radius: 8px;
    padding: 20px;
    transition: all 0.3s;
}

.category-card-admin:hover {
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}

.category-header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 10px;
}

.category-header h3 {
    font-size: 18px;
    color: #2d3748;
}

.product-count {
    background: #f7fafc;
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    color: #718096;
}

.category-description {
    color: #718096;
    font-size: 14px;
    margin-bottom: 15px;
}

.role-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: 600;
}

.role-admin { background: #fed7d7; color: #742a2a; }
.role-attendant { background: #bee3f8; color: #2c5282; }
.role-customer { background: #c6f6d5; color: #22543d; }

.reports-grid {
    display: grid;
    grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
    gap: 20px;
}

.report-card {
    background: #f7fafc;
    padding: 25px;
    border-radius: 8px;
    border-left: 4px solid #667eea;
}

.report-card h3 {
    font-size: 18px;
    margin-bottom: 20px;
    color: #2d3748;
}

.report-stat {
    display: flex;
    justify-content: space-between;
    padding: 10px 0;
    border-bottom: 1px solid #e2e8f0;
}

.report-stat:last-child {
    border-bottom: none;
}

.stock-badge {
    padding: 4px 12px;
    border-radius: 12px;
    font-weight: 600;
    font-size: 14px;
}

.stock-badge.good { background: #c6f6d5; color: #22543d; }
.stock-badge.low { background: #feebc8; color: #7c2d12; }
.stock-badge.out { background: #fed7d7; color: #742a2a; }

.btn-xs {
    padding: 4px 10px;
    font-size: 12px;
}

@media (max-width: 992px) {
    .dashboard-grid-three {
        grid-template-columns: 1fr;
    }
    
    .span-2 {
        grid-column: span 1;
    }
}

@media (max-width: 768px) {
    .dashboard-header {
        flex-direction: column;
        align-items: flex-start;
        gap: 15px;
    }
    
    .stats-grid-large {
        grid-template-columns: 1fr;
    }
    
    .search-form input {
        min-width: 200px;
    }
}
</style>

<?php
// Display admin messages
if (isset($_SESSION['admin_message'])):
    $message_type = $_SESSION['admin_message_type'] ?? 'success';
    $message = $_SESSION['admin_message'];
    unset($_SESSION['admin_message'], $_SESSION['admin_message_type']);
?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        showToast('<?php echo addslashes($message); ?>', '<?php echo $message_type; ?>');
    });
</script>
<?php endif; ?>

<?php include 'includes/admin-modals.php'; ?>

<?php include 'includes/footer.php'; ?>
