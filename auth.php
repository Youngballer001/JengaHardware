<?php
/**
 * Authentication System for Jengo Hardware
 * Handles registration, login, and logout with security features
 */

session_start();

// Database configuration - adjust these values for your setup
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'jengo hardware');

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

// CSRF Token Generation and Validation
function generateCSRFToken() {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Sanitize input
function sanitizeInput($data) {
    return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
}

// Validate email
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL) !== false;
}

// Validate password strength
function isValidPassword($password) {
    // At least 8 characters, 1 uppercase, 1 lowercase, 1 number
    return strlen($password) >= 8 
        && preg_match('/[A-Z]/', $password)
        && preg_match('/[a-z]/', $password)
        && preg_match('/[0-9]/', $password);
}

// Handle Registration
function handleRegistration($pdo) {
    global $error, $success;
    
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid security token. Please try again.";
        return false;
    }
    
    // Sanitize inputs
    $name = sanitizeInput($_POST['name'] ?? '');
    $email = sanitizeInput($_POST['email'] ?? '');
    $phone = sanitizeInput($_POST['phone'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($name) || empty($email) || empty($password)) {
        $error = "All fields are required.";
        return false;
    }
    
    if (!isValidEmail($email)) {
        $error = "Invalid email format.";
        return false;
    }
    
    if (!isValidPassword($password)) {
        $error = "Password must be at least 8 characters with uppercase, lowercase, and number.";
        return false;
    }
    
    if ($password !== $confirm_password) {
        $error = "Passwords do not match.";
        return false;
    }
    
    // Check if email already exists
    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
    $stmt->execute([$email]);
    if ($stmt->fetch()) {
        $error = "Email already registered.";
        return false;
    }
    
    // Hash password
    $hashed_password = password_hash($password, PASSWORD_BCRYPT, ['cost' => 12]);
    
    // Insert new customer (role = 'customer' only for public registration)
    try {
        $stmt = $pdo->prepare("
            INSERT INTO users (name, email, phone, password, role, status, created_at) 
            VALUES (?, ?, ?, ?, 'customer', 'active', NOW())
        ");
        $stmt->execute([$name, $email, $phone, $hashed_password]);
        
        $success = "Registration successful! Please log in.";
        return true;
    } catch (PDOException $e) {
        $error = "Registration failed. Please try again.";
        error_log("Registration error: " . $e->getMessage());
        return false;
    }
}

// Handle Login
function handleLogin($pdo) {
    global $error;
    
    // Validate CSRF token
    if (!validateCSRFToken($_POST['csrf_token'] ?? '')) {
        $error = "Invalid security token. Please try again.";
        return false;
    }
    
    // Sanitize inputs
    $email = sanitizeInput($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    
    // Validation
    if (empty($email) || empty($password)) {
        $error = "Email and password are required.";
        return false;
    }
    
    // Fetch user from database
    $stmt = $pdo->prepare("
        SELECT id, name, email, password, role, status 
        FROM users 
        WHERE email = ?
    ");
    $stmt->execute([$email]);
    $user = $stmt->fetch();
    
    // Verify user exists and password is correct
    if (!$user || !password_verify($password, $user['password'])) {
        $error = "Invalid email or password.";
        return false;
    }
    
    // Check if account is active
    if ($user['status'] !== 'active') {
        $error = "Your account has been suspended. Please contact support.";
        return false;
    }
    
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);
    
    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_name'] = $user['name'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['logged_in'] = true;
    $_SESSION['login_time'] = time();
    
    // Update last login timestamp
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);
    
    // Role-based redirect
    switch ($user['role']) {
        case 'admin':
            header("Location: admin.php");
            break;
        case 'attendant':
            header("Location: attendant.php");
            break;
        case 'customer':
        default:
            header("Location: customer.php");
            break;
    }
    exit();
}

// Handle Logout
function handleLogout() {
    // Unset all session variables
    $_SESSION = array();
    
    // Destroy the session cookie
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 3600, '/');
    }
    
    // Destroy the session
    session_destroy();
    
    // Redirect to login page
    header("Location: auth.php?action=login&logout=success");
    exit();
}

// Initialize variables
$error = '';
$success = '';
$action = $_GET['action'] ?? 'login';

// Handle POST requests
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $submit_action = $_POST['action'] ?? '';
    
    if ($submit_action === 'register') {
        handleRegistration($pdo);
    } elseif ($submit_action === 'login') {
        handleLogin($pdo);
    }
}

// Handle logout
if ($action === 'logout') {
    handleLogout();
}

// Check if user is already logged in
if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true) {
    // Redirect based on role
    switch ($_SESSION['user_role'] ?? 'customer') {
        case 'admin':
            header("Location: admin.php");
            break;
        case 'attendant':
            header("Location: attendant.php");
            break;
        case 'customer':
        default:
            header("Location: customer.php");
            break;
    }
    exit();
}

// Generate CSRF token for forms
$csrf_token = generateCSRFToken();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $action === 'register' ? 'Register' : 'Login'; ?> - Jengo Hardware</title>
    <link rel="stylesheet" href="assets/css/style.css?v=1.2.0">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: linear-gradient(115deg, #202428 0 78%, #f97316 78% 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .auth-container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.1);
            overflow: hidden;
            width: 100%;
            max-width: 450px;
        }
        
        .auth-header {
            background: linear-gradient(115deg, #202428 0 78%, #f97316 78% 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .auth-header h1 {
            font-size: 28px;
            margin-bottom: 5px;
        }
        
        .auth-header p {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .auth-body {
            padding: 30px;
        }
        
        .alert {
            padding: 12px 15px;
            border-radius: 5px;
            margin-bottom: 20px;
            font-size: 14px;
        }
        
        .alert-error {
            background-color: #fee;
            color: #c33;
            border: 1px solid #fcc;
        }
        
        .alert-success {
            background-color: #efe;
            color: #3c3;
            border: 1px solid #cfc;
        }
        
        .alert-info {
            background-color: #e7f3ff;
            color: #0066cc;
            border: 1px solid #b3d9ff;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            margin-bottom: 8px;
            font-weight: 500;
            color: #333;
            font-size: 14px;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #f97316;
        }
        
        .password-requirements {
            font-size: 12px;
            color: #666;
            margin-top: 5px;
        }
        
        .btn {
            width: 100%;
            padding: 12px;
            border: none;
            border-radius: 5px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.3s;
        }
        
        .btn-primary {
            background: #f97316;
            color: white;
        }
        
        .btn-primary:hover {
            opacity: 0.9;
        }
        
        .auth-footer {
            text-align: center;
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #eee;
            font-size: 14px;
        }
        
        .auth-footer a {
            color: #f97316;
            text-decoration: none;
            font-weight: 600;
        }
        
        .auth-footer a:hover {
            text-decoration: underline;
        }
        
        .tabs {
            display: flex;
            margin-bottom: 20px;
        }
        
        .tab {
            flex: 1;
            padding: 10px;
            text-align: center;
            background: #f5f5f5;
            border: none;
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s;
        }
        
        .tab.active {
            background: #f97316;
            color: white;
        }

        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background:
                linear-gradient(120deg, rgba(26, 29, 32, 0.98) 0 62%, rgba(249, 115, 22, 0.95) 62% 100%);
            color: #24282c;
        }

        .auth-container {
            max-width: 480px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            box-shadow: 0 24px 70px rgba(0, 0, 0, 0.35);
        }

        .auth-header {
            position: relative;
            overflow: hidden;
            background: linear-gradient(115deg, #202428 0%, #30363c 78%, #f97316 78%, #f97316 100%);
            padding: 34px 30px;
            text-align: left;
        }

        .auth-header h1 {
            font-size: 30px;
            font-weight: 900;
            letter-spacing: -0.7px;
            text-transform: uppercase;
        }

        .auth-header p {
            color: #f5f5f4;
            font-size: 15px;
        }

        .auth-body {
            padding: 30px;
        }

        .form-group label {
            color: #30353a;
            font-weight: 700;
        }

        .form-group input {
            border: 1px solid #d3d6d8;
            border-radius: 6px;
            padding: 13px 14px;
        }

        .form-group input:focus {
            border-color: #f97316;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.15);
        }

        .password-field input {
            padding-right: 76px;
        }

        .btn-primary {
            background: #f97316;
            border-radius: 6px;
            color: #fff;
            font-weight: 800;
        }

        .btn-primary:hover {
            background: #ea580c;
        }

        .tab.active {
            background: #292e33;
            box-shadow: inset 0 -3px #f97316;
        }

        .auth-footer a {
            color: #ea580c;
        }

        .password-requirements {
            line-height: 1.5;
        }

        .alert-error {
            background: #fff1ee;
            color: #9c2f1e;
            border-color: #f2b4a8;
        }

        .alert-success {
            background: #edf8ef;
            color: #22623a;
            border-color: #b7dfc0;
        }

        .alert-info {
            background: #fff7ed;
            color: #9a4a0b;
            border-color: #fed7aa;
        }

        .btn:disabled {
            cursor: wait;
            opacity: 0.75;
        }

        @media (max-width: 520px) {
            body {
                padding: 14px;
            }

            .auth-body {
                padding: 24px 20px;
            }
        }
    </style>
</head>
<body>
    <div class="auth-container">
        <div class="auth-header">
            <h1>Jengo Hardware</h1>
            <p><?php echo $action === 'register' ? 'Create your account' : 'Welcome back!'; ?></p>
        </div>
        
        <div class="auth-body">
            <!-- Display alerts -->
            <?php if (!empty($error)): ?>
                <div class="alert alert-error"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <?php if (!empty($success)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success); ?></div>
            <?php endif; ?>
            
            <?php if (isset($_GET['logout']) && $_GET['logout'] === 'success'): ?>
                <div class="alert alert-info">You have been logged out successfully.</div>
            <?php endif; ?>
            
            <!-- Tabs -->
            <div class="tabs">
                <button class="tab <?php echo $action === 'login' ? 'active' : ''; ?>" 
                        onclick="window.location.href='auth.php?action=login'">
                    Login
                </button>
                <button class="tab <?php echo $action === 'register' ? 'active' : ''; ?>" 
                        onclick="window.location.href='auth.php?action=register'">
                    Register
                </button>
            </div>
            
            <?php if ($action === 'register'): ?>
                <!-- Registration Form -->
                <form method="POST" action="auth.php?action=register" id="registrationForm">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="register">
                    
                    <div class="form-group">
                        <label for="name">Full Name *</label>
                        <input type="text" id="name" name="name" 
                               value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="email">Email Address *</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                               required>
                    </div>
                    
                    <div class="form-group">
                        <label for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" 
                               value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                               placeholder="+254 XXX XXX XXX">
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password *</label>
                        <div class="password-field">
                            <input type="password" id="password" name="password" autocomplete="new-password" required>
                            <button type="button" class="password-toggle" data-password-toggle="password"
                                    aria-controls="password" aria-pressed="false">Show</button>
                        </div>
                        <div class="password-requirements">
                            Must be at least 8 characters with uppercase, lowercase, and number
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="confirm_password">Confirm Password *</label>
                        <div class="password-field">
                            <input type="password" id="confirm_password" name="confirm_password" autocomplete="new-password" required>
                            <button type="button" class="password-toggle" data-password-toggle="confirm_password"
                                    aria-controls="confirm_password" aria-pressed="false">Show</button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary" id="registrationSubmit">Create Account</button>
                </form>
                
            <?php else: ?>
                <!-- Login Form -->
                <form method="POST" action="auth.php?action=login">
                    <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                    <input type="hidden" name="action" value="login">
                    
                    <div class="form-group">
                        <label for="email">Email Address</label>
                        <input type="email" id="email" name="email" 
                               value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                               required autofocus>
                    </div>
                    
                    <div class="form-group">
                        <label for="password">Password</label>
                        <div class="password-field">
                            <input type="password" id="password" name="password" autocomplete="current-password" required>
                            <button type="button" class="password-toggle" data-password-toggle="password"
                                    aria-controls="password" aria-pressed="false">Show</button>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Login</button>
                </form>
            <?php endif; ?>
            
            <div class="auth-footer">
                <?php if ($action === 'register'): ?>
                    Already have an account? <a href="auth.php?action=login">Login here</a>
                <?php else: ?>
                    Don't have an account? <a href="auth.php?action=register">Register here</a>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <script src="assets/js/app.js?v=1.2.0"></script>
    <script>
        const registrationForm = document.getElementById('registrationForm');
        if (registrationForm) {
            registrationForm.addEventListener('submit', function () {
                const submitButton = document.getElementById('registrationSubmit');
                if (submitButton) {
                    submitButton.disabled = true;
                    submitButton.textContent = 'Creating account...';
                }
            });
        }
    </script>
</body>
</html>
