<?php
/**
 * Jengo Hardware - Initialization Script
 * Bootstrap file that loads configuration, establishes database connection,
 * starts session, and loads required dependencies
 * 
 * Include this file at the top of every page:
 * require_once __DIR__ . '/includes/init.php';
 * 
 * @package JengoHardware
 * @version 1.1.1
 */

// Prevent direct access
if (!defined('JENGO_INIT')) {
    define('JENGO_INIT', true);
}

// ============================================================================
// ERROR HANDLING - Set early
// ============================================================================

// Custom error handler
set_error_handler(function($errno, $errstr, $errfile, $errline) {
    // Don't log suppressed errors (@)
    if (!(error_reporting() & $errno)) {
        return false;
    }
    
    $error_types = [
        E_ERROR => 'ERROR',
        E_WARNING => 'WARNING',
        E_NOTICE => 'NOTICE',
        E_USER_ERROR => 'USER_ERROR',
        E_USER_WARNING => 'USER_WARNING',
        E_USER_NOTICE => 'USER_NOTICE',
        E_STRICT => 'STRICT',
        E_DEPRECATED => 'DEPRECATED'
    ];
    
    $type = $error_types[$errno] ?? 'UNKNOWN';
    $message = "[$type] $errstr in $errfile on line $errline";
    
    error_log($message);
    
    // In development, display error
    if (defined('APP_DEBUG') && APP_DEBUG) {
        echo "<div style='background:#fee;border:2px solid #c00;padding:10px;margin:10px;'>";
        echo "<strong>$type:</strong> $errstr<br>";
        echo "<strong>File:</strong> $errfile<br>";
        echo "<strong>Line:</strong> $errline";
        echo "</div>";
    }
    
    return true;
});

// Exception handler
set_exception_handler(function($exception) {
    $message = "Uncaught Exception: " . $exception->getMessage() . 
               " in " . $exception->getFile() . 
               " on line " . $exception->getLine();
    
    error_log($message);
    error_log($exception->getTraceAsString());
    
    if (defined('APP_DEBUG') && APP_DEBUG) {
        echo "<div style='background:#fee;border:2px solid #c00;padding:20px;margin:10px;'>";
        echo "<h3>Uncaught Exception</h3>";
        echo "<p><strong>Message:</strong> " . htmlspecialchars($exception->getMessage()) . "</p>";
        echo "<p><strong>File:</strong> " . $exception->getFile() . "</p>";
        echo "<p><strong>Line:</strong> " . $exception->getLine() . "</p>";
        echo "<pre>" . htmlspecialchars($exception->getTraceAsString()) . "</pre>";
        echo "</div>";
    } else {
        http_response_code(500);
        echo "An error occurred. Please try again later.";
    }
    
    exit(1);
});

// ============================================================================
// LOAD CONFIGURATION
// ============================================================================

$config_file = __DIR__ . '/../config/config.php';
if (!file_exists($config_file)) {
    die('Configuration file not found. Please ensure config/config.php exists.');
}

require_once $config_file;

// Verify configuration loaded
if (!defined('CONFIG_LOADED')) {
    die('Configuration failed to load properly.');
}

// ============================================================================
// MAINTENANCE MODE CHECK
// ============================================================================

if (MAINTENANCE_MODE && !defined('BYPASS_MAINTENANCE')) {
    // Allow admin access during maintenance
    $allowed_ips = ['127.0.0.1', '::1']; // Add admin IPs here
    
    if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', $allowed_ips)) {
        http_response_code(503);
        ?>
        <!DOCTYPE html>
        <html lang="en">
        <head>
            <meta charset="UTF-8">
            <meta name="viewport" content="width=device-width, initial-scale=1.0">
            <title>Maintenance Mode - <?php echo APP_NAME; ?></title>
            <style>
                body { font-family: Arial, sans-serif; text-align: center; padding: 50px; background: #f0f0f0; }
                .container { background: white; max-width: 600px; margin: 0 auto; padding: 40px; border-radius: 10px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
                h1 { color: #333; }
                p { color: #666; line-height: 1.6; }
                .icon { font-size: 60px; margin-bottom: 20px; }
            </style>
        </head>
        <body>
            <div class="container">
                <div class="icon">🔧</div>
                <h1>Under Maintenance</h1>
                <p><?php echo htmlspecialchars(MAINTENANCE_MESSAGE); ?></p>
            </div>
        </body>
        </html>
        <?php
        exit;
    }
}

// ============================================================================
// SESSION MANAGEMENT
// ============================================================================

// Configure session settings
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_samesite', SESSION_COOKIE_SAMESITE);
    
    if (SESSION_COOKIE_SECURE) {
        ini_set('session.cookie_secure', '1');
    }
    
    // Set session name
    session_name(SESSION_NAME);
    
    // Set session lifetime
    ini_set('session.gc_maxlifetime', SESSION_LIFETIME);
    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'domain' => '',
        'secure' => SESSION_COOKIE_SECURE,
        'httponly' => SESSION_COOKIE_HTTPONLY,
        'samesite' => SESSION_COOKIE_SAMESITE
    ]);
    
    // Start session
    session_start();
    
    // Session hijacking prevention
    if (!isset($_SESSION['user_agent'])) {
        $_SESSION['user_agent'] = $_SERVER['HTTP_USER_AGENT'] ?? '';
    } elseif ($_SESSION['user_agent'] !== ($_SERVER['HTTP_USER_AGENT'] ?? '')) {
        session_destroy();
        session_start();
    }
    
    // Session timeout check
    if (isset($_SESSION['last_activity']) && 
        (time() - $_SESSION['last_activity']) > SESSION_LIFETIME) {
        session_destroy();
        session_start();
    }
    
    $_SESSION['last_activity'] = time();
    
    // Regenerate session ID periodically (every 30 minutes)
    if (!isset($_SESSION['created'])) {
        $_SESSION['created'] = time();
    } elseif (time() - $_SESSION['created'] > 1800) {
        session_regenerate_id(true);
        $_SESSION['created'] = time();
    }
}

// ============================================================================
// DATABASE CONNECTION
// ============================================================================

// Create PDO connection
try {
    $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;
    $pdo = new PDO($dsn, DB_USER, DB_PASS, DB_OPTIONS);
    
    // Store in global scope for compatibility
    $GLOBALS['pdo'] = $pdo;
    
} catch (PDOException $e) {
    error_log('Database Connection Error: ' . $e->getMessage());
    
    if (APP_DEBUG) {
        die('Database Connection Error: ' . $e->getMessage());
    } else {
        http_response_code(500);
        die('Database connection failed. Please try again later.');
    }
}

// ============================================================================
// LOAD HELPER FUNCTIONS
// ============================================================================

$functions_file = __DIR__ . '/functions.php';
if (file_exists($functions_file)) {
    require_once $functions_file;
}

// ============================================================================
// LOAD LAYOUT FUNCTIONS
// ============================================================================

$layout_file = __DIR__ . '/layout.php';
if (file_exists($layout_file)) {
    require_once $layout_file;
}

// ============================================================================
// AUTOLOADER FOR CLASSES (Optional - for future OOP refactoring)
// ============================================================================

spl_autoload_register(function ($class) {
    // Convert namespace to file path
    $class = str_replace('\\', '/', $class);
    $file = APP_ROOT . '/classes/' . $class . '.php';
    
    if (file_exists($file)) {
        require_once $file;
    }
});

// ============================================================================
// CSRF TOKEN GENERATION
// ============================================================================

// Generate CSRF token if not exists
if (!isset($_SESSION[CSRF_TOKEN_NAME])) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    $_SESSION[CSRF_TOKEN_NAME . '_time'] = time();
}

// Regenerate token if expired
if (isset($_SESSION[CSRF_TOKEN_NAME . '_time']) && 
    (time() - $_SESSION[CSRF_TOKEN_NAME . '_time']) > CSRF_TOKEN_LIFETIME) {
    $_SESSION[CSRF_TOKEN_NAME] = bin2hex(random_bytes(32));
    $_SESSION[CSRF_TOKEN_NAME . '_time'] = time();
}

// ============================================================================
// AUTHENTICATION STATE
// ============================================================================

// Check if user is logged in
define('IS_LOGGED_IN', isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true);
define('USER_ID', $_SESSION['user_id'] ?? null);
define('USER_EMAIL', $_SESSION['user_email'] ?? null);
define('USER_NAME', $_SESSION['user_name'] ?? null);
define('USER_ROLE', $_SESSION['user_role'] ?? null);

// ============================================================================
// CART INITIALIZATION
// ============================================================================

// Initialize cart if not exists
if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [];
}

// ============================================================================
// TIMEZONE SETUP
// ============================================================================

// Already set in config.php, but ensure it's set
if (!ini_get('date.timezone')) {
    date_default_timezone_set(APP_TIMEZONE);
}

// ============================================================================
// FLASH MESSAGE HANDLER
// ============================================================================

/**
 * Get and clear flash messages
 * 
 * @return array Flash messages
 */
function getFlashMessages() {
    $messages = [];
    
    if (isset($_SESSION['flash_messages'])) {
        $messages = $_SESSION['flash_messages'];
        unset($_SESSION['flash_messages']);
    }
    
    // Legacy support for old message format
    if (isset($_SESSION['redirect_message'])) {
        $messages[] = [
            'type' => $_SESSION['redirect_type'] ?? 'info',
            'message' => $_SESSION['redirect_message']
        ];
        unset($_SESSION['redirect_message'], $_SESSION['redirect_type']);
    }
    
    if (isset($_SESSION['admin_message'])) {
        $messages[] = [
            'type' => $_SESSION['admin_message_type'] ?? 'info',
            'message' => $_SESSION['admin_message']
        ];
        unset($_SESSION['admin_message'], $_SESSION['admin_message_type']);
    }
    
    if (isset($_SESSION['attendant_message'])) {
        $messages[] = [
            'type' => $_SESSION['attendant_message_type'] ?? 'info',
            'message' => $_SESSION['attendant_message']
        ];
        unset($_SESSION['attendant_message'], $_SESSION['attendant_message_type']);
    }
    
    return $messages;
}

// ============================================================================
// HTTP SECURITY HEADERS
// ============================================================================

if (!headers_sent()) {
    // Prevent clickjacking
    header('X-Frame-Options: SAMEORIGIN');
    
    // Prevent MIME type sniffing
    header('X-Content-Type-Options: nosniff');
    
    // XSS Protection
    header('X-XSS-Protection: 1; mode=block');
    
    // Referrer Policy
    header('Referrer-Policy: strict-origin-when-cross-origin');
    
    // Content Security Policy (basic)
    if (isProduction()) {
        header("Content-Security-Policy: default-src 'self'; script-src 'self' 'unsafe-inline' 'unsafe-eval'; style-src 'self' 'unsafe-inline'; img-src 'self' data: https:;");
    }
}

// ============================================================================
// REQUEST INFORMATION
// ============================================================================

// Store request information for later use
define('REQUEST_METHOD', $_SERVER['REQUEST_METHOD'] ?? 'GET');
define('IS_POST', REQUEST_METHOD === 'POST');
define('IS_GET', REQUEST_METHOD === 'GET');
define('IS_AJAX', !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && 
                   strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

// ============================================================================
// IP ADDRESS
// ============================================================================

/**
 * Get user's real IP address
 * 
 * @return string
 */
function getUserIP() {
    $ip_keys = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($ip_keys as $key) {
        if (array_key_exists($key, $_SERVER)) {
            $ip_list = explode(',', $_SERVER[$key]);
            foreach ($ip_list as $ip) {
                $ip = trim($ip);
                if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE)) {
                    return $ip;
                }
            }
        }
    }
    
    return $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
}

define('USER_IP', getUserIP());

// ============================================================================
// INITIALIZATION COMPLETE
// ============================================================================

define('INIT_LOADED', true);

// Log successful initialization in debug mode
if (APP_DEBUG) {
    error_log('Jengo Hardware initialized successfully');
}
