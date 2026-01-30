<?php
require_once 'config/firebase-config.php';
require_once 'config/paystack-config.php';

// Check if payment reference exists
if (!isset($_GET['reference']) && isset($_SESSION['payment_reference'])) {
    $reference = $_SESSION['payment_reference'];
} elseif (isset($_GET['reference'])) {
    $reference = $_GET['reference'];
} else {
    die('No payment reference found');
}

// Verify payment with Paystack
$curl = curl_init();

curl_setopt_array($curl, [
    CURLOPT_URL => "https://api.paystack.co/transaction/verify/" . rawurlencode($reference),
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_CUSTOMREQUEST => "GET",
    CURLOPT_HTTPHEADER => [
        "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
        "Cache-Control: no-cache",
    ],
]);

$response = curl_exec($curl);
$err = curl_error($curl);
curl_close($curl);

if ($err) {
    die("cURL Error: " . $err);
}

$result = json_decode($response, true);

if ($result['status']) {
    $paymentData = $result['data'];
    
    if ($paymentData['status'] === 'success') {
        // Payment successful
        $amount = $paymentData['amount'] / 100; // Convert from kobo to Naira
        $userId = $paymentData['metadata']['user_id'] ?? '';
        
        if (!$userId && isset($_SESSION['user']['uid'])) {
            $userId = $_SESSION['user']['uid'];
        }
        
        if ($userId) {
            try {
                // Use transaction for atomic update (like your Dart code)
                $transaction = $database->runTransaction(function ($transaction) use ($database, $userId, $amount, $reference) {
                    // Get user document
                    $userRef = $database->collection('users')->document($userId);
                    $userDoc = $transaction->snapshot($userRef);
                    
                    if (!$userDoc->exists()) {
                        throw new Exception('User not found');
                    }
                    
                    $currentData = $userDoc->data();
                    
                    // Calculate new values (1 Naira = 1 coin)
                    $coinsToAdd = $amount; // 1:1 ratio
                    $newDepositCoins = ($currentData['depositCoins'] ?? 0) + $coinsToAdd;
                    $newTotalCoins = ($currentData['totalCoins'] ?? 0) + $coinsToAdd;
                    
                    // Update user document
                    $transaction->update($userRef, [
                        [
                            'path' => 'depositCoins',
                            'value' => $newDepositCoins
                        ],
                        [
                            'path' => 'totalCoins',
                            'value' => $newTotalCoins
                        ]
                    ]);
                    
                    // Update session
                    $_SESSION['user']['depositCoins'] = $newDepositCoins;
                    $_SESSION['user']['totalCoins'] = $newTotalCoins;
                    
                    return [
                        'success' => true,
                        'coinsAdded' => $coinsToAdd,
                        'newDepositCoins' => $newDepositCoins,
                        'newTotalCoins' => $newTotalCoins
                    ];
                });
                
                // Update transaction record status
                $transactionsQuery = $database->collection('users')
                    ->document($userId)
                    ->collection('transactions')
                    ->where('paymentReference', '=', $reference)
                    ->limit(1);
                
                $transactionsSnapshot = $transactionsQuery->documents();
                
                foreach ($transactionsSnapshot as $doc) {
                    $doc->reference()->update([
                        ['path' => 'status', 'value' => 'completed'],
                        ['path' => 'description', 'value' => 'Added ' . $amount . ' deposit coins via Paystack'],
                        ['path' => 'paymentData', 'value' => $paymentData]
                    ]);
                }
                
                // Add a new completed transaction record
                $database->collection('users')->document($userId)
                    ->collection('transactions')
                    ->add([
                        'type' => 'deposit',
                        'amount' => $amount,
                        'description' => 'Added ' . $amount . ' deposit coins via Paystack',
                        'timestamp' => new \Google\Cloud\Core\Timestamp(new DateTime()),
                        'paymentReference' => $reference,
                        'status' => 'completed',
                        'paymentData' => $paymentData
                    ]);
                
                // Clear payment session
                unset($_SESSION['payment_reference']);
                unset($_SESSION['payment_amount']);
                
                // Redirect to success page
                header('Location: payment-success.php?amount=' . $amount . '&coins=' . $amount);
                exit();
                
            } catch (Exception $e) {
                $error = "Database error: " . $e->getMessage();
            }
        } else {
            $error = "User ID not found in payment metadata";
        }
    } else {
        $error = "Payment not successful. Status: " . $paymentData['status'];
    }
} else {
    $error = "Payment verification failed: " . ($result['message'] ?? 'Unknown error');
}

// If we get here, there was an error
require_once 'config/firebase-config.php';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Failed</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
            margin: 0;
        }
        
        .container {
            background: white;
            padding: 40px;
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.2);
            text-align: center;
            max-width: 500px;
            width: 90%;
        }
        
        .icon {
            font-size: 80px;
            color: #ff6b6b;
            margin-bottom: 20px;
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
        }
        
        .error-message {
            background: #ffeaea;
            padding: 15px;
            border-radius: 10px;
            margin: 20px 0;
            color: #d32f2f;
            font-family: monospace;
            font-size: 14px;
        }
        
        .buttons {
            margin-top: 30px;
        }
        
        .btn {
            display: inline-block;
            padding: 12px 30px;
            margin: 0 10px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .btn-retry {
            background: #ff6b6b;
            color: white;
        }
        
        .btn-retry:hover {
            background: #ff5252;
        }
        
        .btn-dashboard {
            background: #6c757d;
            color: white;
        }
        
        .btn-dashboard:hover {
            background: #5a6268;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="icon">❌</div>
        <h1>Payment Failed</h1>
        
        <div class="error-message">
            <?php echo htmlspecialchars($error ?? 'An unknown error occurred'); ?>
        </div>
        
        <p>Please try again or contact support if the problem persists.</p>
        
        <div class="buttons">
            <a href="payment.php" class="btn btn-retry">Try Again</a>
            <a href="dashboard.php" class="btn btn-dashboard">Go to Dashboard</a>
        </div>
    </div>
</body>
</html>