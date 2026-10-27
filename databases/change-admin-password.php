<?php
/**
 * Admin Password Change Script
 * Use this script ONCE to change the default admin password
 * DELETE THIS FILE after use for security!
 */

// Prevent running in production by checking for production indicators
if (isset($_SERVER['HTTP_HOST']) && 
    (strpos($_SERVER['HTTP_HOST'], 'localhost') === false && 
     strpos($_SERVER['HTTP_HOST'], '127.0.0.1') === false)) {
    die("This script can only be run on localhost for security reasons.");
}

// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'jengo_hardware');

$success_message = '';
$error_message = '';

// Process form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $current_email = trim($_POST['email'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    
    // Validation
    if (empty($current_email) || empty($new_password) || empty($confirm_password)) {
        $error_message = "All fields are required.";
    } elseif ($new_password !== $confirm_password) {
        $error_message = "Passwords do not match.";
    } elseif (strlen($new_password) < 8) {
        $error_message = "Password must be at least 8 characters long.";
    } elseif (!preg_match('/[A-Z]/', $new_password)) {
        $error_message = "Password must contain at least one uppercase letter.";
    } elseif (!preg_match('/[a-z]/', $new_password)) {
        $error_message = "Password must contain at least one lowercase letter.";
    } elseif (!preg_match('/[0-9]/', $new_password)) {
        $error_message = "Password must contain at least one number.";
    } elseif (!preg_match('/[!@#$%^&*(),.?":{}|<>]/', $new_password)) {
        $error_message = "Password must contain at least one special character.";
    } else {
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
            
            // Verify user exists and is admin
            $stmt = $pdo->prepare("SELECT id, name, role FROM users WHERE email = ? AND role = 'admin'");
            $stmt->execute([$current_email]);
            $user = $stmt->fetch();
            
            if (!$user) {
                $error_message = "Admin user not found with that email address.";
            } else {
                // Hash new password
                $hashed_password = password_hash($new_password, PASSWORD_BCRYPT, ['cost' => 12]);
                
                // Update password
                $stmt = $pdo->prepare("UPDATE users SET password = ?, updated_at = NOW() WHERE id = ?");
                $stmt->execute([$hashed_password, $user['id']]);
                
                $success_message = "Password changed successfully for " . htmlspecialchars($user['name']) . "!";
                
                // Log the change
                error_log("Admin password changed for user ID: " . $user['id'] . " at " . date('Y-m-d H:i:s'));
            }
            
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Change Admin Password - Jengo Hardware</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            padding: 40px;
            max-width: 500px;
            width: 100%;
        }
        
        .warning-banner {
            background: #fff3cd;
            border: 2px solid #ffc107;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 30px;
            color: #856404;
        }
        
        .warning-banner h3 {
            font-size: 16px;
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        
        .warning-banner ul {
            margin-left: 20px;
            font-size: 14px;
        }
        
        .warning-banner li {
            margin: 5px 0;
        }
        
        h1 {
            color: #2d3748;
            margin-bottom: 10px;
            font-size: 28px;
        }
        
        .subtitle {
            color: #718096;
            margin-bottom: 30px;
            font-size: 14px;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        label {
            display: block;
            margin-bottom: 8px;
            color: #2d3748;
            font-weight: 600;
            font-size: 14px;
        }
        
        input {
            width: 100%;
            padding: 12px;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 14px;
            transition: border-color 0.3s;
        }
        
        input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .password-requirements {
            font-size: 12px;
            color: #718096;
            margin-top: 8px;
        }
        
        .password-requirements ul {
            margin-left: 20px;
            margin-top: 5px;
        }
        
        .btn {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: transform 0.2s;
        }
        
        .btn:hover {
            transform: translateY(-2px);
        }
        
        .btn:active {
            transform: translateY(0);
        }
        
        .success-message {
            background: #d4edda;
            border: 2px solid #28a745;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .error-message {
            background: #f8d7da;
            border: 2px solid #dc3545;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-weight: 600;
        }
        
        .delete-warning {
            background: #f8d7da;
            border: 2px solid #dc3545;
            border-radius: 10px;
            padding: 15px;
            margin-top: 30px;
            color: #721c24;
            font-size: 14px;
            text-align: center;
            font-weight: 600;
        }
        
        .default-credentials {
            background: #e3f2fd;
            border: 2px solid #2196f3;
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 20px;
            color: #0d47a1;
            font-size: 14px;
        }
        
        .default-credentials strong {
            display: block;
            margin-bottom: 10px;
        }
        
        .credential-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
        }
        
        code {
            background: #f7fafc;
            padding: 2px 6px;
            border-radius: 4px;
            font-family: monospace;
            color: #c53030;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>🔐 Change Admin Password</h1>
        <p class="subtitle">First-time security setup for Jengo Hardware</p>
        
        <div class="warning-banner">
            <h3>⚠️ Security Notice</h3>
            <ul>
                <li>This script should only be run ONCE</li>
                <li>DELETE this file after changing password</li>
                <li>Never run this on a public server</li>
            </ul>
        </div>
        
        <?php if (!empty($success_message)): ?>
            <div class="success-message">
                ✓ <?php echo $success_message; ?>
            </div>
            <div class="delete-warning">
                🚨 IMPORTANT: Delete this file now!<br>
                File: <code>databases/change-admin-password.php</code>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($error_message)): ?>
            <div class="error-message">
                ✗ <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        
        <?php if (empty($success_message)): ?>
            <div class="default-credentials">
                <strong>Default Admin Credentials:</strong>
                <div class="credential-row">
                    <span>Email:</span>
                    <code>admin@jengohardware.com</code>
                </div>
                <div class="credential-row">
                    <span>Password:</span>
                    <code>Admin@2026!</code>
                </div>
            </div>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="email">Admin Email Address</label>
                    <input type="email" id="email" name="email" required 
                           value="admin@jengohardware.com"
                           placeholder="Enter admin email">
                </div>
                
                <div class="form-group">
                    <label for="new_password">New Password</label>
                    <input type="password" id="new_password" name="new_password" required
                           placeholder="Enter new secure password">
                    <div class="password-requirements">
                        Password must contain:
                        <ul>
                            <li>At least 8 characters</li>
                            <li>One uppercase letter (A-Z)</li>
                            <li>One lowercase letter (a-z)</li>
                            <li>One number (0-9)</li>
                            <li>One special character (!@#$%^&*)</li>
                        </ul>
                    </div>
                </div>
                
                <div class="form-group">
                    <label for="confirm_password">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" required
                           placeholder="Re-enter new password">
                </div>
                
                <button type="submit" class="btn">Change Password</button>
            </form>
            
            <div class="delete-warning" style="margin-top: 30px;">
                Remember to delete this file after use!
            </div>
        <?php endif; ?>
    </div>
</body>
</html>
