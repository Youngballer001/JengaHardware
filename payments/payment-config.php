<?php

$getPaymentEnv = require __DIR__ . '/payment-env.php';
$mpesaEnvironment = strtolower($getPaymentEnv('MPESA_ENVIRONMENT', 'sandbox'));
$paystackSecretKey = $getPaymentEnv('PAYSTACK_SECRET_KEY');
$paystackPublicKey = $getPaymentEnv('PAYSTACK_PUBLIC_KEY');
$paystackEnvironment = $getPaymentEnv('PAYSTACK_ENVIRONMENT');
$appUrl = rtrim($getPaymentEnv('APP_URL', 'http://localhost/Jengo%20Hardware'), '/');
$paystackCallbackUrl = $getPaymentEnv('PAYSTACK_CALLBACK_URL');
$paystackWebhookUrl = $getPaymentEnv('PAYSTACK_WEBHOOK_URL');

if ($paystackEnvironment === '') {
    $paystackEnvironment = strpos($paystackSecretKey, 'sk_live_') === 0 ? 'live' : 'test';
}
if ($paystackCallbackUrl === '') {
    $paystackCallbackUrl = $appUrl . '/payments/paystack-callback.php';
}
if ($paystackWebhookUrl === '') {
    $paystackWebhookUrl = $appUrl . '/payments/paystack-webhook.php';
}

define('MPESA_CONFIG', [
    'environment' => $mpesaEnvironment,
    'consumer_key' => $getPaymentEnv('MPESA_CONSUMER_KEY'),
    'consumer_secret' => $getPaymentEnv('MPESA_CONSUMER_SECRET'),
    'shortcode' => $getPaymentEnv('MPESA_SHORTCODE'),
    'passkey' => $getPaymentEnv('MPESA_PASSKEY'),
    'transaction_type' => $getPaymentEnv('MPESA_TRANSACTION_TYPE', 'CustomerPayBillOnline'),
    'business_phone' => $getPaymentEnv('MPESA_BUSINESS_PHONE', '+254117721068'),
    'callback_url' => $getPaymentEnv('MPESA_CALLBACK_URL')
]);

define('PAYSTACK_CONFIG', [
    'environment' => $paystackEnvironment,
    'secret_key' => $paystackSecretKey,
    'public_key' => $paystackPublicKey,
    'callback_url' => $paystackCallbackUrl,
    'webhook_url' => $paystackWebhookUrl
]);

unset(
    $getPaymentEnv,
    $mpesaEnvironment,
    $paystackSecretKey,
    $paystackPublicKey,
    $paystackEnvironment,
    $appUrl,
    $paystackCallbackUrl,
    $paystackWebhookUrl
);
