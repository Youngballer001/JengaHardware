<?php
/**
 * Jengo Hardware - Configuration File
 * Central configuration for database, app settings, and constants
 * 
 * @package JengoHardware
 * @version 1.1.1
 */

// Prevent direct access
defined('JENGO_INIT') or die('Direct access not permitted');

// ============================================================================
// ENVIRONMENT CONFIGURATION
// ============================================================================

// Set environment: 'development', 'staging', 'production'
define('APP_ENV', getenv('APP_ENV') ?: 'development');
define('APP_DEBUG', APP_ENV === 'development');

// Error reporting based on environment
if (APP_DEBUG) {
    error_reporting(E_ALL);
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
} else {
    error_reporting(E_ALL & ~E_NOTICE & ~E_DEPRECATED);
    ini_set('display_errors', 0);
    ini_set('log_errors', 1);
    ini_set('error_log', __DIR__ . '/../config/storage/logs/error.log');
}

// ============================================================================
// DATABASE CONFIGURATION
// ============================================================================

define('DB_HOST', getenv('DB_HOST') ?: 'localhost');
define('DB_NAME', getenv('DB_NAME') ?: 'jengo_hardware');
define('DB_USER', getenv('DB_USER') ?: 'root');
define('DB_PASS', getenv('DB_PASS') ?: '');
define('DB_CHARSET', 'utf8mb4');

// Database connection options
define('DB_OPTIONS', [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
    PDO::ATTR_PERSISTENT => false, // Set to true for persistent connections
    PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4 COLLATE utf8mb4_unicode_ci"
]);

// ============================================================================
// APPLICATION SETTINGS
// ============================================================================

define('APP_NAME', 'Jengo Hardware');
define('APP_VERSION', '1.1.1');
define('APP_URL', getenv('APP_URL') ?: 'http://localhost/Jengo%20Hardware');
define('APP_TIMEZONE', 'Africa/Nairobi');

// Set timezone
date_default_timezone_set(APP_TIMEZONE);

// ============================================================================
// PATH CONFIGURATION
// ============================================================================

define('APP_ROOT', dirname(__DIR__));
define('APP_PATH', APP_ROOT . '/');
define('CONFIG_PATH', APP_ROOT . '/config/');
define('INCLUDES_PATH', APP_ROOT . '/includes/');
define('STORAGE_PATH', CONFIG_PATH . 'storage/');
define('LOGS_PATH', STORAGE_PATH . 'logs/');
define('UPLOADS_PATH', APP_ROOT . '/assets/images/products/');

// URL paths
define('BASE_URL', APP_URL . '/');
define('ASSETS_URL', BASE_URL . 'assets/');
define('UPLOADS_URL', ASSETS_URL . 'images/products/');

// ============================================================================
// SESSION CONFIGURATION
// ============================================================================

define('SESSION_NAME', getenv('SESSION_COOKIE_NAME') ?: 'jengo_session');
define('SESSION_LIFETIME', (int)(getenv('SESSION_LIFETIME') ?: 7200)); // 2 hours
define('SESSION_COOKIE_SECURE', APP_ENV === 'production'); // HTTPS only in production
define('SESSION_COOKIE_HTTPONLY', true);
define('SESSION_COOKIE_SAMESITE', 'Lax');

// ============================================================================
// SECURITY CONFIGURATION
// ============================================================================

define('CSRF_TOKEN_NAME', 'csrf_token');
define('CSRF_TOKEN_LIFETIME', (int)(getenv('CSRF_TOKEN_LIFETIME') ?: 3600)); // 1 hour

// Password policy
define('PASSWORD_MIN_LENGTH', 8);
define('PASSWORD_REQUIRE_UPPERCASE', true);
define('PASSWORD_REQUIRE_NUMBERS', true);
define('PASSWORD_REQUIRE_SPECIAL', false);
define('PASSWORD_HASH_COST', 12);

// Rate limiting
define('RATE_LIMIT_LOGIN', 5); // Max login attempts per 15 minutes
define('RATE_LIMIT_API', 60); // Max API requests per minute

// ============================================================================
// FILE UPLOAD CONFIGURATION
// ============================================================================

define('MAX_UPLOAD_SIZE', (int)(getenv('MAX_UPLOAD_SIZE') ?: 5242880)); // 5MB
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);
define('ALLOWED_IMAGE_MIMES', [
    'image/jpeg',
    'image/png',
    'image/gif',
    'image/webp'
]);

// ============================================================================
// BUSINESS CONFIGURATION
// ============================================================================

// Stock management
define('LOW_STOCK_THRESHOLD', (int)(getenv('LOW_STOCK_THRESHOLD') ?: 10));

// Delivery fees (in KES)
define('DELIVERY_FEE_PICKUP', 0);
define('DELIVERY_FEE_STANDARD', 300);
define('DELIVERY_FEE_EXPRESS', 600);

// Currency
define('CURRENCY_CODE', 'KES');
define('CURRENCY_SYMBOL', 'KSh');

// Pagination
define('PRODUCTS_PER_PAGE', 12);
define('ORDERS_PER_PAGE', 20);

// ============================================================================
// PAYMENT GATEWAY CONFIGURATION
// ============================================================================

// M-Pesa
define('MPESA_ENABLED', true);
define('MPESA_ENVIRONMENT', getenv('MPESA_ENVIRONMENT') ?: 'sandbox');
define('MPESA_CONSUMER_KEY', getenv('MPESA_CONSUMER_KEY') ?: '');
define('MPESA_CONSUMER_SECRET', getenv('MPESA_CONSUMER_SECRET') ?: '');
define('MPESA_SHORTCODE', getenv('MPESA_SHORTCODE') ?: '174379');
define('MPESA_PASSKEY', getenv('MPESA_PASSKEY') ?: '');
define('MPESA_CALLBACK_URL', getenv('MPESA_CALLBACK_URL') ?: BASE_URL . 'payments/mpesa-callback.php');

// Paystack
define('PAYSTACK_ENABLED', true);
define('PAYSTACK_PUBLIC_KEY', getenv('PAYSTACK_PUBLIC_KEY') ?: '');
define('PAYSTACK_SECRET_KEY', getenv('PAYSTACK_SECRET_KEY') ?: '');
define('PAYSTACK_CALLBACK_URL', getenv('PAYSTACK_CALLBACK_URL') ?: BASE_URL . 'payments/paystack-callback.php');
define('PAYSTACK_WEBHOOK_URL', getenv('PAYSTACK_WEBHOOK_URL') ?: BASE_URL . 'payments/paystack-webhook.php');

// ============================================================================
// EMAIL CONFIGURATION (Optional)
// ============================================================================

define('MAIL_ENABLED', (bool)getenv('MAIL_ENABLED') ?: false);
define('MAIL_DRIVER', getenv('MAIL_DRIVER') ?: 'smtp');
define('MAIL_HOST', getenv('MAIL_HOST') ?: 'smtp.mailtrap.io');
define('MAIL_PORT', (int)(getenv('MAIL_PORT') ?: 2525));
define('MAIL_USERNAME', getenv('MAIL_USERNAME') ?: '');
define('MAIL_PASSWORD', getenv('MAIL_PASSWORD') ?: '');
define('MAIL_ENCRYPTION', getenv('MAIL_ENCRYPTION') ?: 'tls');
define('MAIL_FROM_ADDRESS', getenv('MAIL_FROM_ADDRESS') ?: 'noreply@jengohardware.com');
define('MAIL_FROM_NAME', getenv('MAIL_FROM_NAME') ?: APP_NAME);

// ============================================================================
// SMS CONFIGURATION (Optional - Africa's Talking)
// ============================================================================

define('SMS_ENABLED', (bool)getenv('SMS_ENABLED') ?: false);
define('SMS_USERNAME', getenv('SMS_USERNAME') ?: '');
define('SMS_API_KEY', getenv('SMS_API_KEY') ?: '');
define('SMS_SENDER_ID', getenv('SMS_SENDER_ID') ?: 'JENGO');

// ============================================================================
// COMPANY INFORMATION
// ============================================================================

define('COMPANY_NAME', getenv('COMPANY_NAME') ?: 'Jengo Hardware');
define('COMPANY_EMAIL', getenv('COMPANY_EMAIL') ?: 'info@jengohardware.com');
define('COMPANY_PHONE', getenv('COMPANY_PHONE') ?: '+254700000000');
define('COMPANY_ADDRESS', getenv('COMPANY_ADDRESS') ?: '123 Main Street, Nairobi, Kenya');

define('SUPPORT_EMAIL', getenv('SUPPORT_EMAIL') ?: 'support@jengohardware.com');
define('SUPPORT_PHONE', getenv('SUPPORT_PHONE') ?: '+254700000000');

// ============================================================================
// SOCIAL MEDIA (Optional)
// ============================================================================

define('FACEBOOK_URL', getenv('FACEBOOK_URL') ?: '');
define('TWITTER_URL', getenv('TWITTER_URL') ?: '');
define('INSTAGRAM_URL', getenv('INSTAGRAM_URL') ?: '');
define('WHATSAPP_NUMBER', getenv('WHATSAPP_NUMBER') ?: '');

// ============================================================================
// ANALYTICS (Optional)
// ============================================================================

define('GOOGLE_ANALYTICS_ID', getenv('GOOGLE_ANALYTICS_ID') ?: '');
define('FACEBOOK_PIXEL_ID', getenv('FACEBOOK_PIXEL_ID') ?: '');

// ============================================================================
// MAINTENANCE MODE
// ============================================================================

define('MAINTENANCE_MODE', (bool)getenv('MAINTENANCE_MODE') ?: false);
define('MAINTENANCE_MESSAGE', getenv('MAINTENANCE_MESSAGE') ?: 
    "We're currently performing maintenance. Please check back soon.");

// ============================================================================
// CACHE CONFIGURATION
// ============================================================================

define('CACHE_ENABLED', (bool)getenv('CACHE_ENABLED') ?: false);
define('CACHE_DRIVER', getenv('CACHE_DRIVER') ?: 'file');
define('CACHE_TTL', (int)(getenv('CACHE_TTL') ?: 3600)); // 1 hour

// ============================================================================
// LOGGING CONFIGURATION
// ============================================================================

define('LOG_LEVEL', getenv('LOG_LEVEL') ?: 'debug'); // debug, info, warning, error
define('LOG_PATH', LOGS_PATH);

// ============================================================================
// USER ROLES
// ============================================================================

define('ROLE_CUSTOMER', 'customer');
define('ROLE_ATTENDANT', 'attendant');
define('ROLE_ADMIN', 'admin');

// Role permissions (for future ACL implementation)
define('ROLE_PERMISSIONS', [
    ROLE_CUSTOMER => [
        'view_products',
        'create_order',
        'view_own_orders',
        'update_own_profile'
    ],
    ROLE_ATTENDANT => [
        'view_products',
        'view_all_orders',
        'update_order_status',
        'view_inventory'
    ],
    ROLE_ADMIN => [
        'all' // Full access
    ]
]);

// ============================================================================
// ORDER STATUS CONSTANTS
// ============================================================================

define('ORDER_STATUS_PENDING', 'pending');
define('ORDER_STATUS_PROCESSING', 'processing');
define('ORDER_STATUS_SHIPPED', 'shipped');
define('ORDER_STATUS_DELIVERED', 'delivered');
define('ORDER_STATUS_CANCELLED', 'cancelled');

define('ORDER_STATUSES', [
    ORDER_STATUS_PENDING => 'Pending',
    ORDER_STATUS_PROCESSING => 'Processing',
    ORDER_STATUS_SHIPPED => 'Shipped',
    ORDER_STATUS_DELIVERED => 'Delivered',
    ORDER_STATUS_CANCELLED => 'Cancelled'
]);

// ============================================================================
// PAYMENT STATUS CONSTANTS
// ============================================================================

define('PAYMENT_STATUS_PENDING', 'pending');
define('PAYMENT_STATUS_PAID', 'paid');
define('PAYMENT_STATUS_FAILED', 'failed');
define('PAYMENT_STATUS_REFUNDED', 'refunded');

define('PAYMENT_STATUSES', [
    PAYMENT_STATUS_PENDING => 'Pending',
    PAYMENT_STATUS_PAID => 'Paid',
    PAYMENT_STATUS_FAILED => 'Failed',
    PAYMENT_STATUS_REFUNDED => 'Refunded'
]);

// ============================================================================
// DELIVERY METHODS
// ============================================================================

define('DELIVERY_METHOD_PICKUP', 'pickup');
define('DELIVERY_METHOD_STANDARD', 'standard');
define('DELIVERY_METHOD_EXPRESS', 'express');

define('DELIVERY_METHODS', [
    DELIVERY_METHOD_PICKUP => [
        'name' => 'Store Pickup',
        'description' => 'Collect from our store in Nairobi',
        'fee' => DELIVERY_FEE_PICKUP,
        'duration' => 'Same day'
    ],
    DELIVERY_METHOD_STANDARD => [
        'name' => 'Standard Delivery',
        'description' => 'Delivered to your address',
        'fee' => DELIVERY_FEE_STANDARD,
        'duration' => '2-3 business days'
    ],
    DELIVERY_METHOD_EXPRESS => [
        'name' => 'Express Delivery',
        'description' => 'Fast delivery to your address',
        'fee' => DELIVERY_FEE_EXPRESS,
        'duration' => 'Next business day'
    ]
]);

// ============================================================================
// PAYMENT METHODS
// ============================================================================

define('PAYMENT_METHOD_MPESA', 'mpesa');
define('PAYMENT_METHOD_PAYSTACK', 'paystack');
define('PAYMENT_METHOD_CASH', 'cash');

define('PAYMENT_METHODS', [
    PAYMENT_METHOD_MPESA => [
        'name' => 'M-Pesa',
        'description' => 'Pay via M-Pesa mobile money',
        'enabled' => MPESA_ENABLED,
        'icon' => 'mpesa.png'
    ],
    PAYMENT_METHOD_PAYSTACK => [
        'name' => 'Card/Bank',
        'description' => 'Pay with card or bank transfer',
        'enabled' => PAYSTACK_ENABLED,
        'icon' => 'paystack.png'
    ],
    PAYMENT_METHOD_CASH => [
        'name' => 'Cash on Pickup',
        'description' => 'Pay cash when collecting',
        'enabled' => true,
        'icon' => 'cash.png'
    ]
]);

// ============================================================================
// VALIDATION RULES
// ============================================================================

define('VALIDATION_RULES', [
    'email' => [
        'required' => true,
        'max_length' => 100,
        'pattern' => FILTER_VALIDATE_EMAIL
    ],
    'phone' => [
        'required' => true,
        'pattern' => '/^[+]?[\d\s\-\(\)]+$/',
        'min_length' => 10,
        'max_length' => 20
    ],
    'name' => [
        'required' => true,
        'min_length' => 2,
        'max_length' => 100
    ],
    'password' => [
        'required' => true,
        'min_length' => PASSWORD_MIN_LENGTH
    ]
]);

// ============================================================================
// HELPER FUNCTIONS
// ============================================================================

/**
 * Get configuration value
 * 
 * @param string $key Configuration key
 * @param mixed $default Default value if not found
 * @return mixed
 */
function config($key, $default = null) {
    return defined($key) ? constant($key) : $default;
}

/**
 * Check if in production environment
 * 
 * @return bool
 */
function isProduction() {
    return APP_ENV === 'production';
}

/**
 * Check if in development environment
 * 
 * @return bool
 */
function isDevelopment() {
    return APP_ENV === 'development';
}

/**
 * Get delivery fee for method
 * 
 * @param string $method Delivery method
 * @return int
 */
function getDeliveryFee($method) {
    $fees = [
        DELIVERY_METHOD_PICKUP => DELIVERY_FEE_PICKUP,
        DELIVERY_METHOD_STANDARD => DELIVERY_FEE_STANDARD,
        DELIVERY_METHOD_EXPRESS => DELIVERY_FEE_EXPRESS
    ];
    
    return $fees[$method] ?? 0;
}

/**
 * Format currency amount
 * 
 * @param float $amount Amount to format
 * @param bool $include_symbol Include currency symbol
 * @return string
 */
function formatCurrency($amount, $include_symbol = true) {
    $formatted = number_format($amount, 2);
    return $include_symbol ? CURRENCY_SYMBOL . ' ' . $formatted : $formatted;
}

/**
 * Get asset URL
 * 
 * @param string $path Asset path
 * @return string
 */
function asset($path) {
    return ASSETS_URL . ltrim($path, '/');
}

/**
 * Get upload URL
 * 
 * @param string $path Upload path
 * @return string
 */
function upload($path) {
    return $path ? UPLOADS_URL . ltrim($path, '/') : '';
}

// ============================================================================
// CONFIGURATION VALIDATION
// ============================================================================

// Ensure required directories exist
$required_dirs = [STORAGE_PATH, LOGS_PATH, UPLOADS_PATH];
foreach ($required_dirs as $dir) {
    if (!is_dir($dir)) {
        @mkdir($dir, 0755, true);
    }
}

// Ensure required directories are writable
foreach ($required_dirs as $dir) {
    if (!is_writable($dir)) {
        error_log("Warning: Directory not writable: $dir");
    }
}

// Configuration loaded successfully
define('CONFIG_LOADED', true);
