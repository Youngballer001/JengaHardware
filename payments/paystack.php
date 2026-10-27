<?php
/**
 * Paystack Payment Integration
 * Handles transaction initialization, verification, and webhooks
 * 
 * Features:
 * - Transaction initialization
 * - Payment verification
 * - Webhook signature verification
 * - Amount and reference validation
 * - Idempotent processing
 * - Safe logging
 * - Test and live mode support
 */

class PaystackAPI {
    
    // Configuration
    private $environment; // 'test' or 'live'
    private $secret_key;
    private $public_key;
    
    // API Endpoints
    private $base_url = 'https://api.paystack.co';
    
    // Database connection
    private $pdo;
    
    // Logging
    private $log_file;
    private $enable_logging = true;
    
    /**
     * Initialize Paystack API
     */
    public function __construct($pdo, $config = []) {
        $this->pdo = $pdo;
        
        // Load configuration
        $this->environment = $config['environment'] ?? 'test';
        $this->secret_key = $config['secret_key'] ?? '';
        $this->public_key = $config['public_key'] ?? '';
        
        // Set log file
        $this->log_file = __DIR__ . '/../config/storage/logs/paystack_' . date('Y-m-d') . '.log';
        
        // Ensure log directory exists
        $log_dir = dirname($this->log_file);
        if (!is_dir($log_dir)) {
            mkdir($log_dir, 0755, true);
        }
    }
    
    /**
     * Safe logging - excludes sensitive information
     */
    private function log($message, $data = [], $level = 'INFO') {
        if (!$this->enable_logging) return;
        
        // Remove sensitive data before logging
        $safe_data = $this->sanitizeLogData($data);
        
        $log_entry = [
            'timestamp' => date('Y-m-d H:i:s'),
            'level' => $level,
            'message' => $message,
            'data' => $safe_data,
            'environment' => $this->environment
        ];
        
        file_put_contents(
            $this->log_file,
            json_encode($log_entry) . PHP_EOL,
            FILE_APPEND
        );
    }
    
    /**
     * Remove sensitive data from logs
     */
    private function sanitizeLogData($data) {
        $sensitive_keys = [
            'secret_key', 'public_key', 'authorization', 
            'card_number', 'cvv', 'pin', 'otp',
            'Authorization', 'X-Paystack-Signature'
        ];
        
        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (in_array($key, $sensitive_keys)) {
                    $data[$key] = '[REDACTED]';
                } elseif (is_array($value)) {
                    $data[$key] = $this->sanitizeLogData($value);
                }
            }
        }
        
        return $data;
    }
    
    /**
     * Make API request to Paystack
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        try {
            $url = $this->base_url . $endpoint;
            
            $headers = [
                'Authorization: Bearer ' . $this->secret_key,
                'Content-Type: application/json',
                'Cache-Control: no-cache'
            ];
            
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            if ($method === 'POST') {
                curl_setopt($ch, CURLOPT_POST, true);
                if ($data) {
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                }
            }
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                $this->log('API cURL Error', ['error' => $curl_error], 'ERROR');
                return [
                    'success' => false,
                    'message' => 'Connection error',
                    'http_code' => 0
                ];
            }
            
            $result = json_decode($response, true);
            
            return [
                'success' => $http_code >= 200 && $http_code < 300,
                'http_code' => $http_code,
                'data' => $result
            ];
            
        } catch (Exception $e) {
            $this->log('API Exception', ['error' => $e->getMessage()], 'ERROR');
            return [
                'success' => false,
                'message' => 'System error'
            ];
        }
    }
    
    /**
     * Initialize Transaction
     * 
     * @param float $amount Amount in kobo (multiply Naira by 100) or cents
     * @param string $email Customer email
     * @param string $reference Unique transaction reference
     * @param array $metadata Additional data
     * @param string $callback_url URL to redirect after payment
     */
    public function initializeTransaction($amount, $email, $reference, $metadata = [], $callback_url = null) {
        try {
            // Convert amount to smallest currency unit (kobo for NGN, cents for KES)
            // Paystack expects amount in kobo/cents
            $amount_in_cents = (int)($amount * 100);
            
            // Validate inputs
            if ($amount_in_cents < 1) {
                return ['success' => false, 'message' => 'Invalid amount'];
            }
            
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                return ['success' => false, 'message' => 'Invalid email'];
            }
            
            if (empty($reference)) {
                return ['success' => false, 'message' => 'Reference is required'];
            }
            
            // Prepare request data
            $data = [
                'amount' => $amount_in_cents,
                'email' => $email,
                'reference' => $reference,
                'currency' => 'KES', // Kenyan Shillings
                'metadata' => $metadata
            ];
            
            if ($callback_url) {
                $data['callback_url'] = $callback_url;
            }
            
            // Make API request
            $response = $this->makeRequest('/transaction/initialize', 'POST', $data);
            
            $this->log('Transaction Initialized', [
                'reference' => $reference,
                'amount' => $amount,
                'email' => substr($email, 0, 3) . '***',
                'http_code' => $response['http_code']
            ]);
            
            if ($response['success'] && isset($response['data']['status']) && $response['data']['status']) {
                $data = $response['data']['data'];
                
                // Store transaction in database
                $this->storeTransaction(
                    $reference,
                    $amount,
                    $email,
                    $data['access_code'],
                    $metadata
                );
                
                return [
                    'success' => true,
                    'message' => 'Transaction initialized',
                    'authorization_url' => $data['authorization_url'],
                    'access_code' => $data['access_code'],
                    'reference' => $data['reference']
                ];
            }
            
            $this->log('Transaction Initialization Failed', [
                'response' => $response
            ], 'ERROR');
            
            return [
                'success' => false,
                'message' => $response['data']['message'] ?? 'Failed to initialize transaction'
            ];
            
        } catch (Exception $e) {
            $this->log('Initialize Exception', ['error' => $e->getMessage()], 'ERROR');
            return ['success' => false, 'message' => 'System error'];
        }
    }
    
    /**
     * Verify Transaction
     * 
     * @param string $reference Transaction reference
     */
    public function verifyTransaction($reference) {
        try {
            if (empty($reference)) {
                return ['success' => false, 'message' => 'Reference is required'];
            }
            
            // Make API request
            $response = $this->makeRequest('/transaction/verify/' . urlencode($reference));
            
            $this->log('Transaction Verified', [
                'reference' => $reference,
                'http_code' => $response['http_code']
            ]);
            
            if ($response['success'] && isset($response['data']['status']) && $response['data']['status']) {
                $transaction = $response['data']['data'];
                
                // Check if transaction was successful
                if ($transaction['status'] === 'success') {
                    // Get stored transaction for comparison
                    $stored = $this->getTransaction($reference);
                    
                    if (!$stored) {
                        $this->log('Transaction Not Found in Database', [
                            'reference' => $reference
                        ], 'WARNING');
                        
                        return [
                            'success' => false,
                            'message' => 'Transaction not found in our records'
                        ];
                    }
                    
                    // Verify amount matches (convert from kobo/cents)
                    $received_amount = $transaction['amount'] / 100;
                    $expected_amount = $stored['amount'];
                    
                    if (abs($received_amount - $expected_amount) > 0.01) {
                        $this->log('Amount Mismatch', [
                            'reference' => $reference,
                            'expected' => $expected_amount,
                            'received' => $received_amount
                        ], 'ERROR');
                        
                        $this->updateTransactionStatus($reference, 'failed', 'Amount mismatch');
                        
                        return [
                            'success' => false,
                            'message' => 'Amount verification failed'
                        ];
                    }
                    
                    // Verify reference matches
                    if ($transaction['reference'] !== $stored['reference']) {
                        $this->log('Reference Mismatch', [
                            'expected' => $stored['reference'],
                            'received' => $transaction['reference']
                        ], 'ERROR');
                        
                        return [
                            'success' => false,
                            'message' => 'Reference verification failed'
                        ];
                    }
                    
                    // Update transaction status
                    $this->updateTransactionStatus(
                        $reference,
                        'completed',
                        'Payment successful',
                        [
                            'gateway_response' => $transaction['gateway_response'],
                            'paid_at' => $transaction['paid_at'],
                            'channel' => $transaction['channel']
                        ]
                    );
                    
                    return [
                        'success' => true,
                        'message' => 'Payment verified successfully',
                        'transaction' => $transaction
                    ];
                    
                } else {
                    // Transaction failed or pending
                    $this->updateTransactionStatus(
                        $reference,
                        'failed',
                        $transaction['gateway_response'] ?? 'Payment failed'
                    );
                    
                    return [
                        'success' => false,
                        'message' => $transaction['gateway_response'] ?? 'Payment was not successful'
                    ];
                }
            }
            
            $this->log('Verification Failed', [
                'reference' => $reference,
                'response' => $response
            ], 'ERROR');
            
            return [
                'success' => false,
                'message' => $response['data']['message'] ?? 'Verification failed'
            ];
            
        } catch (Exception $e) {
            $this->log('Verify Exception', ['error' => $e->getMessage()], 'ERROR');
            return ['success' => false, 'message' => 'System error'];
        }
    }
    
    /**
     * Handle Webhook
     * Verifies signature and processes payment notification
     */
    public function handleWebhook($payload, $signature) {
        try {
            // Verify webhook signature
            if (!$this->verifyWebhookSignature($payload, $signature)) {
                $this->log('Invalid Webhook Signature', [], 'ERROR');
                return ['success' => false, 'message' => 'Invalid signature'];
            }
            
            $data = json_decode($payload, true);
            
            if (!$data) {
                $this->log('Invalid Webhook Data', [], 'ERROR');
                return ['success' => false, 'message' => 'Invalid data'];
            }
            
            $event = $data['event'] ?? '';
            
            $this->log('Webhook Received', [
                'event' => $event
            ]);
            
            // Handle different event types
            switch ($event) {
                case 'charge.success':
                    return $this->handleChargeSuccess($data['data']);
                    
                case 'charge.failed':
                    return $this->handleChargeFailed($data['data']);
                    
                default:
                    $this->log('Unhandled Event', ['event' => $event], 'WARNING');
                    return ['success' => true, 'message' => 'Event acknowledged'];
            }
            
        } catch (Exception $e) {
            $this->log('Webhook Exception', ['error' => $e->getMessage()], 'ERROR');
            return ['success' => false, 'message' => 'System error'];
        }
    }
    
    /**
     * Verify webhook signature
     */
    private function verifyWebhookSignature($payload, $signature) {
        $computed_signature = hash_hmac('sha512', $payload, $this->secret_key);
        return hash_equals($computed_signature, $signature);
    }
    
    /**
     * Handle successful charge webhook
     */
    private function handleChargeSuccess($data) {
        $reference = $data['reference'] ?? '';
        
        if (empty($reference)) {
            return ['success' => false, 'message' => 'Missing reference'];
        }
        
        // Get stored transaction
        $stored = $this->getTransaction($reference);
        
        if (!$stored) {
            $this->log('Transaction Not Found', ['reference' => $reference], 'WARNING');
            return ['success' => false, 'message' => 'Transaction not found'];
        }
        
        // Idempotency check
        if ($stored['status'] !== 'pending') {
            $this->log('Transaction Already Processed', [
                'reference' => $reference,
                'current_status' => $stored['status']
            ], 'WARNING');
            
            return ['success' => true, 'message' => 'Already processed'];
        }
        
        // Verify amount
        $received_amount = $data['amount'] / 100;
        $expected_amount = $stored['amount'];
        
        if (abs($received_amount - $expected_amount) > 0.01) {
            $this->log('Amount Mismatch in Webhook', [
                'reference' => $reference,
                'expected' => $expected_amount,
                'received' => $received_amount
            ], 'ERROR');
            
            $this->updateTransactionStatus($reference, 'failed', 'Amount mismatch');
            return ['success' => false, 'message' => 'Amount mismatch'];
        }
        
        // Update transaction
        $this->updateTransactionStatus(
            $reference,
            'completed',
            'Payment successful',
            [
                'gateway_response' => $data['gateway_response'] ?? '',
                'paid_at' => $data['paid_at'] ?? '',
                'channel' => $data['channel'] ?? ''
            ]
        );
        
        // Update order payment status
        $metadata = json_decode($stored['metadata'], true);
        if (isset($metadata['order_number'])) {
            $this->updateOrderPaymentStatus(
                $metadata['order_number'],
                'paid',
                $reference
            );
        }
        
        $this->log('Payment Completed via Webhook', [
            'reference' => $reference,
            'amount' => $received_amount
        ]);
        
        return ['success' => true, 'message' => 'Payment processed'];
    }
    
    /**
     * Handle failed charge webhook
     */
    private function handleChargeFailed($data) {
        $reference = $data['reference'] ?? '';
        
        if (empty($reference)) {
            return ['success' => false, 'message' => 'Missing reference'];
        }
        
        $this->updateTransactionStatus(
            $reference,
            'failed',
            $data['gateway_response'] ?? 'Payment failed'
        );
        
        $this->log('Payment Failed via Webhook', [
            'reference' => $reference
        ], 'WARNING');
        
        return ['success' => true, 'message' => 'Failure recorded'];
    }
    
    /**
     * Store transaction in database
     */
    private function storeTransaction($reference, $amount, $email, $access_code, $metadata) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO paystack_transactions (
                    reference, amount, email, access_code, 
                    metadata, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ON DUPLICATE KEY UPDATE
                    updated_at = NOW()
            ");
            
            $stmt->execute([
                $reference,
                $amount,
                $email,
                $access_code,
                json_encode($metadata)
            ]);
            
            $this->log('Transaction Stored', ['reference' => $reference]);
            
        } catch (PDOException $e) {
            $this->log('Database Error', ['error' => $e->getMessage()], 'ERROR');
        }
    }
    
    /**
     * Get transaction from database
     */
    private function getTransaction($reference) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM paystack_transactions
                WHERE reference = ?
                LIMIT 1
            ");
            $stmt->execute([$reference]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            $this->log('Database Error', ['error' => $e->getMessage()], 'ERROR');
            return null;
        }
    }
    
    /**
     * Update transaction status (idempotent)
     */
    private function updateTransactionStatus($reference, $status, $gateway_response, $extra_data = []) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE paystack_transactions
                SET status = ?,
                    gateway_response = ?,
                    paid_at = ?,
                    channel = ?,
                    updated_at = NOW()
                WHERE reference = ?
                AND status = 'pending'
            ");
            
            $stmt->execute([
                $status,
                $gateway_response,
                $extra_data['paid_at'] ?? null,
                $extra_data['channel'] ?? null,
                $reference
            ]);
            
            return $stmt->rowCount() > 0;
            
        } catch (PDOException $e) {
            $this->log('Database Error', ['error' => $e->getMessage()], 'ERROR');
            return false;
        }
    }
    
    /**
     * Update order payment status
     */
    private function updateOrderPaymentStatus($order_number, $payment_status, $reference) {
        try {
            // Only update if payment is currently pending (idempotency)
            $stmt = $this->pdo->prepare("
                UPDATE orders
                SET payment_status = ?,
                    payment_reference = ?,
                    updated_at = NOW()
                WHERE order_number = ?
                AND payment_status = 'pending'
            ");
            
            $stmt->execute([
                $payment_status,
                $reference,
                $order_number
            ]);
            
            if ($stmt->rowCount() > 0) {
                $this->log('Order Payment Status Updated', [
                    'order_number' => $order_number,
                    'payment_status' => $payment_status
                ]);
            } else {
                $this->log('Order Already Processed', [
                    'order_number' => $order_number
                ], 'WARNING');
            }
            
            return true;
            
        } catch (PDOException $e) {
            $this->log('Database Error', ['error' => $e->getMessage()], 'ERROR');
            return false;
        }
    }
    
    /**
     * Get public key (safe to expose to frontend)
     */
    public function getPublicKey() {
        return $this->public_key;
    }
}

// Example usage prevention
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    echo "Paystack API Library - Do not access directly\n";
    exit;
}
