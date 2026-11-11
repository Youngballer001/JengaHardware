<?php

session_start();
header('Content-Type: application/json');

function jsonResponse($success, $message) {
    echo json_encode(['success' => $success, 'message' => $message]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    jsonResponse(false, 'Invalid request method.');
}

if (
    empty($_SESSION['csrf_token']) ||
    !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'] ?? '')
) {
    http_response_code(403);
    jsonResponse(false, 'Invalid security token. Refresh the page and try again.');
}

$order_number = $_SESSION['last_order']['order_number'] ?? '';
$receipt = strtoupper(trim($_POST['mpesa_receipt'] ?? ''));
if (!preg_match('/^[A-Z0-9]{8,12}$/', $receipt)) {
    jsonResponse(false, 'Enter a valid M-Pesa transaction code.');
}

if ($order_number === '') {
    jsonResponse(false, 'Your order could not be verified. Please reopen the payment page.');
}

try {
    $pdo = new PDO(
        'mysql:host=localhost;dbname=jengo hardware;charset=utf8mb4',
        'root',
        '',
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC
        ]
    );

    $stmt = $pdo->prepare("
        SELECT id, payment_status, payment_reference
        FROM orders
        WHERE order_number = ? AND payment_method = 'mpesa'
        LIMIT 1
    ");
    $stmt->execute([$order_number]);
    $order = $stmt->fetch();

    if (!$order) {
        jsonResponse(false, 'M-Pesa order not found.');
    }
    if ($order['payment_status'] === 'paid') {
        jsonResponse(false, 'This order is already paid.');
    }
    if ($order['payment_status'] !== 'pending') {
        jsonResponse(false, 'This order is not awaiting payment.');
    }

    $stmt = $pdo->prepare("
        SELECT id
        FROM orders
        WHERE payment_reference = ? AND id <> ?
        LIMIT 1
    ");
    $stmt->execute([$receipt, $order['id']]);
    if ($stmt->fetch()) {
        jsonResponse(false, 'This transaction code has already been submitted for another order.');
    }

    $stmt = $pdo->prepare("
        UPDATE orders
        SET payment_reference = ?, updated_at = NOW()
        WHERE id = ? AND payment_status = 'pending'
    ");
    $stmt->execute([$receipt, $order['id']]);

    jsonResponse(true, 'Transaction code submitted. Your order will remain pending until the store verifies the payment.');
} catch (PDOException $e) {
    error_log('M-Pesa manual receipt submission failed: ' . $e->getMessage());
    http_response_code(500);
    jsonResponse(false, 'Could not submit the transaction code. Please try again later.');
}
