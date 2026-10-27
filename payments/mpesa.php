<?php
/**
 * M-Pesa Daraja API Integration
 * Handles STK Push, Callbacks, and Transaction Verification
 * 
 * Features:
 * - OAuth 2.0 Authentication
 * - STK Push (Lipa Na M-Pesa Online)
 * - Callback handling with verification
 * - Idempotent payment confirmation
 * - Sandbox and production support
 * - Safe logging (no sensitive data)
 * - Transaction matching and validation
 */

class MpesaAPI {
    
    // Configuration
    private $environment; // 'sandbox' or 'production'
    private $consumer_key;
    private $consumer_secret;
    private $passkey;
    private $shortcode;
    private $initiator_name;
    private $security_credential;
    
    // API Endpoints
    private $base_url;
    private $oauth_url;
    private $stk_push_url;
    private $query_url;
    
    // Database connection
    private $pdo;
    
    // Logging
    private $log_file;
    private $enable_logging = true;
    
    /**
     * Initialize M-Pesa API
     */
    public function __construct($pdo, $config = []) {
        $this->pdo = $pdo;
        
        // Load configuration
        $this->environment = $config['environment'] ?? 'sandbox';
        $this->consumer_key = $config['consumer_key'] ?? '';
        $this->consumer_secret = $config['consumer_secret'] ?? '';
        $this->passkey = $config['passkey'] ?? '';
        $this->shortcode = $config['shortcode'] ?? '';
        $this->initiator_name = $config['initiator_name'] ?? '';
        $this->security_credential = $config['security_credential'] ?? '';
        
        // Set API URLs based on environment
        if ($this->environment === 'production') {
            $this->base_url = 'https://api.safaricom.co.ke';
        } else {
            $this->base_url = 'https://sandbox.safaricom.co.ke';
        }
        
        $this->oauth_url = $this->base_url . '/oauth/v1/generate?grant_type=client_credentials';
        $this->stk_push_url = $this->base_url . '/mpesa/stkpush/v1/processrequest';
        $this->query_url = $this->base_url . '/mpesa/stkpushquery/v1/query';
        
        // Set log file
        $this->log_file = __DIR__ . '/../config/storage/logs/mpesa_' . date('Y-m-d') . '.log';
        
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
            'consumer_key', 'consumer_secret', 'password', 
            'passkey', 'security_credential', 'access_token',
            'Authorization'
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
     * Get OAuth Access Token
     */
    public function getAccessToken() {
        try {
            $credentials = base64_encode($this->consumer_key . ':' . $this->consumer_secret);
            
            $ch = curl_init($this->oauth_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Basic ' . $credentials,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                $this->log('OAuth cURL Error', ['error' => $curl_error], 'ERROR');
                return ['success' => false, 'message' => 'Connection error'];
            }
            
            $result = json_decode($response, true);
            
            if ($http_code === 200 && isset($result['access_token'])) {
                $this->log('OAuth Token Retrieved', ['http_code' => $http_code]);
                return [
                    'success' => true,
                    'access_token' => $result['access_token']
                ];
            }
            
            $this->log('OAuth Failed', [
                'http_code' => $http_code,
                'response' => $result
            ], 'ERROR');
            
            return [
                'success' => false,
                'message' => $result['errorMessage'] ?? 'Failed to get access token'
            ];
            
        } catch (Exception $e) {
            $this->log('OAuth Exception', ['error' => $e->getMessage()], 'ERROR');
            return ['success' => false, 'message' => 'System error'];
        }
    }
    
    /**
     * Generate Password for STK Push
     */
    private function generatePassword($timestamp) {
        return base64_encode($this->shortcode . $this->passkey . $timestamp);
    }
    
    /**
     * Initiate STK Push
     * 
     * @param string $phone_number Phone number (format: 254XXXXXXXXX)
     * @param float $amount Amount to charge
     * @param string $account_reference Order number or reference
     * @param string $transaction_desc Description
     * @param string $callback_url URL for M-Pesa callback
     */
    public function initiateSTKPush($phone_number, $amount, $account_reference, $transaction_desc, $callback_url) {
        try {
            // Validate inputs
            $phone_number = $this->formatPhoneNumber($phone_number);
            if (!$phone_number) {
                return ['success' => false, 'message' => 'Invalid phone number format'];
            }
            
            $amount = (int)$amount;
            if ($amount < 1) {
                return ['success' => false, 'message' => 'Invalid amount'];
            }
            
            // Get access token
            $token_response = $this->getAccessToken();
            if (!$token_response['success']) {
                return $token_response;
            }
            
            $access_token = $token_response['access_token'];
            $timestamp = date('YmdHis');
            $password = $this->generatePassword($timestamp);
            
            // Prepare request payload
            $payload = [
                'BusinessShortCode' => $this->shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'TransactionType' => 'CustomerPayBillOnline',
                'Amount' => $amount,
                'PartyA' => $phone_number,
                'PartyB' => $this->shortcode,
                'PhoneNumber' => $phone_number,
                'CallBackURL' => $callback_url,
                'AccountReference' => $account_reference,
                'TransactionDesc' => $transaction_desc
            ];
            
            // Make API request
            $ch = curl_init($this->stk_push_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $access_token,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curl_error = curl_error($ch);
            curl_close($ch);
            
            if ($curl_error) {
                $this->log('STK Push cURL Error', ['error' => $curl_error], 'ERROR');
                return ['success' => false, 'message' => 'Connection error'];
            }
            
            $result = json_decode($response, true);
            
            // Log request (without sensitive data)
            $this->log('STK Push Initiated', [
                'http_code' => $http_code,
                'phone_number' => substr($phone_number, 0, 6) . 'XXX',
                'amount' => $amount,
                'reference' => $account_reference,
                'response_code' => $result['ResponseCode'] ?? null
            ]);
            
            if ($http_code === 200 && $result['ResponseCode'] == '0') {
                // Store transaction in database
                $this->storePendingTransaction(
                    $result['CheckoutRequestID'],
                    $result['MerchantRequestID'],
                    $phone_number,
                    $amount,
                    $account_reference
                );
                
                return [
                    'success' => true,
                    'message' => $result['CustomerMessage'] ?? 'STK Push sent successfully',
                    'checkout_request_id' => $result['CheckoutRequestID'],
                    'merchant_request_id' => $result['MerchantRequestID']
                ];
            }
            
            $this->log('STK Push Failed', [
                'http_code' => $http_code,
                'response' => $result
            ], 'ERROR');
            
            return [
                'success' => false,
                'message' => $result['errorMessage'] ?? $result['ResponseDescription'] ?? 'STK Push failed'
            ];
            
        } catch (Exception $e) {
            $this->log('STK Push Exception', ['error' => $e->getMessage()], 'ERROR');
            return ['success' => false, 'message' => 'System error'];
        }
    }
    
    /**
     * Format phone number to required format (254XXXXXXXXX)
     */
    private function formatPhoneNumber($phone) {
        // Remove all non-numeric characters
        $phone = preg_replace('/[^0-9]/', '', $phone);
        
        // Handle different formats
        if (strlen($phone) === 10 && substr($phone, 0, 1) === '0') {
            // 07XXXXXXXX -> 2547XXXXXXXX
            return '254' . substr($phone, 1);
        } elseif (strlen($phone) === 9) {
            // 7XXXXXXXX -> 2547XXXXXXXX
            return '254' . $phone;
        } elseif (strlen($phone) === 12 && substr($phone, 0, 3) === '254') {
            // Already in correct format
            return $phone;
        }
        
        return false;
    }
    
    /**
     * Store pending transaction in database
     */
    private function storePendingTransaction($checkout_request_id, $merchant_request_id, $phone_number, $amount, $account_reference) {
        try {
            $stmt = $this->pdo->prepare("
                INSERT INTO mpesa_transactions (
                    checkout_request_id, merchant_request_id, phone_number,
                    amount, account_reference, status, created_at
                ) VALUES (?, ?, ?, ?, ?, 'pending', NOW())
                ON DUPLICATE KEY UPDATE
                    updated_at = NOW()
            ");
            
            $stmt->execute([
                $checkout_request_id,
                $merchant_request_id,
                $phone_number,
                $amount,
                $account_reference
            ]);
            
            $this->log('Transaction Stored', [
                'checkout_request_id' => $checkout_request_id,
                'account_reference' => $account_reference
            ]);
            
        } catch (PDOException $e) {
            $this->log('Database Error', ['error' => $e->getMessage()], 'ERROR');
        }
    }
    
    /**
     * Handle M-Pesa Callback
     * This method should be called from your callback endpoint
     */
    public function handleCallback() {
        try {
            // Get callback data
            $callback_json = file_get_contents('php://input');
            $callback_data = json_decode($callback_json, true);
            
            if (!$callback_data) {
                $this->log('Invalid Callback Data', [], 'ERROR');
                return ['success' => false, 'message' => 'Invalid data'];
            }
            
            // Log callback (sanitized)
            $this->log('Callback Received', [
                'has_body' => isset($callback_data['Body']),
                'has_stk_callback' => isset($callback_data['Body']['stkCallback'])
            ]);
            
            // Extract callback data
            $stk_callback = $callback_data['Body']['stkCallback'] ?? null;
            if (!$stk_callback) {
                $this->log('Missing STK Callback', [], 'ERROR');
                return ['success' => false, 'message' => 'Missing callback data'];
            }
            
            $merchant_request_id = $stk_callback['MerchantRequestID'] ?? null;
            $checkout_request_id = $stk_callback['CheckoutRequestID'] ?? null;
            $result_code = $stk_callback['ResultCode'] ?? null;
            $result_desc = $stk_callback['ResultDesc'] ?? '';
            
            if (!$checkout_request_id) {
                $this->log('Missing Checkout Request ID', [], 'ERROR');
                return ['success' => false, 'message' => 'Invalid callback'];
            }
            
            // Check if transaction exists
            $transaction = $this->getTransaction($checkout_request_id);
            if (!$transaction) {
                $this->log('Transaction Not Found', [
                    'checkout_request_id' => $checkout_request_id
                ], 'WARNING');
                return ['success' => false, 'message' => 'Transaction not found'];
            }
            
            // Idempotency check - if already processed, return success
            if ($transaction['status'] !== 'pending') {
                $this->log('Transaction Already Processed', [
                    'checkout_request_id' => $checkout_request_id,
                    'current_status' => $transaction['status']
                ], 'WARNING');
                
                return [
                    'success' => true,
                    'message' => 'Already processed',
                    'status' => $transaction['status']
                ];
            }
            
            // Process based on result code
            if ($result_code == 0) {
                // Success - Extract payment details
                $callback_metadata = $stk_callback['CallbackMetadata']['Item'] ?? [];
                $payment_details = $this->extractCallbackMetadata($callback_metadata);
                
                // Verify amount matches
                if ($payment_details['amount'] != $transaction['amount']) {
                    $this->log('Amount Mismatch', [
                        'expected' => $transaction['amount'],
                        'received' => $payment_details['amount']
                    ], 'ERROR');
                    
                    $this->updateTransactionStatus(
                        $checkout_request_id,
                        'failed',
                        'Amount mismatch',
                        []
                    );
                    
                    return ['success' => false, 'message' => 'Amount mismatch'];
                }
                
                // Update transaction status to completed
                $this->updateTransactionStatus(
                    $checkout_request_id,
                    'completed',
                    $result_desc,
                    $payment_details
                );
                
                // Update order payment status
                $this->updateOrderPaymentStatus(
                    $transaction['account_reference'],
                    'paid',
                    $payment_details['mpesa_receipt_number']
                );
                
                $this->log('Payment Completed', [
                    'checkout_request_id' => $checkout_request_id,
                    'receipt' => $payment_details['mpesa_receipt_number'],
                    'account_reference' => $transaction['account_reference']
                ]);
                
                return [
                    'success' => true,
                    'message' => 'Payment processed successfully',
                    'receipt_number' => $payment_details['mpesa_receipt_number']
                ];
                
            } else {
                // Failed or cancelled
                $this->updateTransactionStatus(
                    $checkout_request_id,
                    'failed',
                    $result_desc,
                    []
                );
                
                $this->log('Payment Failed', [
                    'checkout_request_id' => $checkout_request_id,
                    'result_code' => $result_code,
                    'result_desc' => $result_desc
                ], 'WARNING');
                
                return [
                    'success' => false,
                    'message' => $result_desc,
                    'result_code' => $result_code
                ];
            }
            
        } catch (Exception $e) {
            $this->log('Callback Exception', ['error' => $e->getMessage()], 'ERROR');
            return ['success' => false, 'message' => 'System error'];
        }
    }
    
    /**
     * Extract metadata from callback
     */
    private function extractCallbackMetadata($items) {
        $metadata = [
            'amount' => 0,
            'mpesa_receipt_number' => '',
            'transaction_date' => '',
            'phone_number' => ''
        ];
        
        foreach ($items as $item) {
            $name = $item['Name'] ?? '';
            $value = $item['Value'] ?? '';
            
            switch ($name) {
                case 'Amount':
                    $metadata['amount'] = $value;
                    break;
                case 'MpesaReceiptNumber':
                    $metadata['mpesa_receipt_number'] = $value;
                    break;
                case 'TransactionDate':
                    $metadata['transaction_date'] = $value;
                    break;
                case 'PhoneNumber':
                    $metadata['phone_number'] = $value;
                    break;
            }
        }
        
        return $metadata;
    }
    
    /**
     * Get transaction from database
     */
    private function getTransaction($checkout_request_id) {
        try {
            $stmt = $this->pdo->prepare("
                SELECT * FROM mpesa_transactions
                WHERE checkout_request_id = ?
                LIMIT 1
            ");
            $stmt->execute([$checkout_request_id]);
            return $stmt->fetch();
        } catch (PDOException $e) {
            $this->log('Database Error', ['error' => $e->getMessage()], 'ERROR');
            return null;
        }
    }
    
    /**
     * Update transaction status (idempotent)
     */
    private function updateTransactionStatus($checkout_request_id, $status, $result_desc, $payment_details) {
        try {
            $stmt = $this->pdo->prepare("
                UPDATE mpesa_transactions
                SET status = ?,
                    result_desc = ?,
                    mpesa_receipt_number = ?,
                    transaction_date = ?,
                    updated_at = NOW()
                WHERE checkout_request_id = ?
                AND status = 'pending'
            ");
            
            $stmt->execute([
                $status,
                $result_desc,
                $payment_details['mpesa_receipt_number'] ?? null,
                $payment_details['transaction_date'] ?? null,
                $checkout_request_id
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
    private function updateOrderPaymentStatus($order_number, $payment_status, $receipt_number) {
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
                $receipt_number,
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
     * Query STK Push transaction status
     */
    public function queryTransaction($checkout_request_id) {
        try {
            // Get access token
            $token_response = $this->getAccessToken();
            if (!$token_response['success']) {
                return $token_response;
            }
            
            $access_token = $token_response['access_token'];
            $timestamp = date('YmdHis');
            $password = $this->generatePassword($timestamp);
            
            // Prepare request payload
            $payload = [
                'BusinessShortCode' => $this->shortcode,
                'Password' => $password,
                'Timestamp' => $timestamp,
                'CheckoutRequestID' => $checkout_request_id
            ];
            
            // Make API request
            $ch = curl_init($this->query_url);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Authorization: Bearer ' . $access_token,
                'Content-Type: application/json'
            ]);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_TIMEOUT, 30);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            
            $result = json_decode($response, true);
            
            $this->log('Transaction Query', [
                'checkout_request_id' => $checkout_request_id,
                'http_code' => $http_code,
                'result_code' => $result['ResultCode'] ?? null
            ]);
            
            if ($http_code === 200) {
                return [
                    'success' => true,
                    'result' => $result
                ];
            }
            
            return [
                'success' => false,
                'message' => 'Query failed'
            ];
            
        } catch (Exception $e) {
            $this->log('Query Exception', ['error' => $e->getMessage()], 'ERROR');
            return ['success' => false, 'message' => 'System error'];
        }
    }
}

// Example usage and configuration
if (basename(__FILE__) == basename($_SERVER['SCRIPT_FILENAME'])) {
    echo "M-Pesa API Library - Do not access directly\n";
    exit;
}
