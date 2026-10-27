<?php
/**
 * Dashboard Router
 * Redirects users to role-appropriate dashboard
 */

session_start();

// Check if user is logged in
if (!isset($_SESSION['logged_in']) || !$_SESSION['logged_in']) {
    header("Location: auth.php?action=login");
    exit();
}

// Route based on user role
$role = $_SESSION['user_role'] ?? 'customer';

switch ($role) {
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
