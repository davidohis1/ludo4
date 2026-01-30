<?php
require_once 'config/firebase-config.php';
require_once 'config/paystack-config.php';

// Only accept POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    die('Method not allowed');
}

// Get Paystack signature header
$paystackSignature = $_SERVER['HTTP_X_PAYSTACK_SIGNATURE'] ?? '';

// Get input data
$input = @file_get_contents("php://input");
$payload = json_decode($input, true);

if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    die('Invalid JSON');
}

// Verify webhook signature (for security)
$computedSignature = hash_hmac('sha512', $input, PAYSTACK_SECRET_KEY);

if (!hash_equals($paystackSignature, $computedSignature)) {
    http_response_code(401);
    die('Invalid signature');
}

// Handle webhook events
$event = $payload['event'] ?? '';
$data = $payload['data'] ?? [];

switch ($event) {
    case 'charge.success':
        handleSuccessfulCharge($data);
        break;
        
    case 'charge.failed':
        handleFailedCharge($data);
        break;
        
    case 'transfer.success':
        handleTransferSuccess($data);
        break;
        
    case 'transfer.failed':
        handleTransferFailed($data);
        break;
        
    default:
        // Log unknown event
        file_put_contents('paystack_webhook.log', date('Y-m-d H:i:s') . " - Unknown event: $event\n", FILE_APPEND);
        break;
}

http_response_code(200);
echo 'Webhook processed';

// ==================== HANDLER FUNCTIONS ====================

function handleSuccessfulCharge($data) {
    global $database;
    
    $reference = $data['reference'] ?? '';
    $amount = $data['amount'] / 100; // Convert to Naira
    $userId = $data['metadata']['user_id'] ?? '';
    
    if (!$userId || !$reference) {
        return;
    }
    
    try {
        // Check if already processed
        $transactionsQuery = $database->collection('users')
            ->document($userId)
            ->collection('transactions')
            ->where('paymentReference', '=', $reference)
            ->where('status', '=', 'completed')
            ->limit(1);
        
        $existing = $transactionsQuery->documents();
        
        if (iterator_count($existing) === 0) {
            // Add coins to user account
            $userRef = $database->collection('users')->document($userId);
            $userDoc = $userRef->snapshot();
            
            if ($userDoc->exists()) {
                $currentData = $userDoc->data();
                $coinsToAdd = $amount; // 1:1 ratio
                $newDepositCoins = ($currentData['depositCoins'] ?? 0) + $coinsToAdd;
                $newTotalCoins = ($currentData['totalCoins'] ?? 0) + $coinsToAdd;
                
                $userRef->update([
                    ['path' => 'depositCoins', 'value' => $newDepositCoins],
                    ['path' => 'totalCoins', 'value' => $newTotalCoins]
                ]);
                
                // Add transaction record
                $database->collection('users')->document($userId)
                    ->collection('transactions')
                    ->add([
                        'type' => 'deposit',
                        'amount' => $coinsToAdd,
                        'description' => 'Added ' . $coinsToAdd . ' deposit coins via Paystack webhook',
                        'timestamp' => new \Google\Cloud\Core\Timestamp(new DateTime()),
                        'paymentReference' => $reference,
                        'status' => 'completed',
                        'paymentData' => $data
                    ]);
                
                file_put_contents('paystack_webhook.log', 
                    date('Y-m-d H:i:s') . " - Added $coinsToAdd coins to user $userId\n", 
                    FILE_APPEND);
            }
        }
    } catch (Exception $e) {
        file_put_contents('paystack_webhook.log', 
            date('Y-m-d H:i:s') . " - Error: " . $e->getMessage() . "\n", 
            FILE_APPEND);
    }
}

function handleFailedCharge($data) {
    $reference = $data['reference'] ?? '';
    $reason = $data['message'] ?? 'Unknown reason';
    
    file_put_contents('paystack_webhook.log', 
        date('Y-m-d H:i:s') . " - Payment failed: $reference - $reason\n", 
        FILE_APPEND);
}

// Add other handler functions as needed...
?>