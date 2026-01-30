<?php
require_once 'config/firebase-config.php';
require_once 'config/paystack-config.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$userId = $_SESSION['user']['uid'];
$userData = $_SESSION['user'];
$error = '';
$success = '';

// Process payment initiation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['amount'])) {
    $amount = intval($_POST['amount']) * 100; // Convert to kobo/pesewas
    $email = $_SESSION['user']['email'];
    $reference = 'PAY_' . $userId . '_' . time() . '_' . rand(1000, 9999);
    
    // Create a pending transaction record
    try {
        $transactionsRef = $database->collection('users')->document($userId)->collection('transactions');
        $transactionsRef->add([
            'type' => 'deposit_pending',
            'amount' => $amount / 100,
            'description' => 'Coin purchase - Pending',
            'timestamp' => new \Google\Cloud\Core\Timestamp(new DateTime()),
            'paymentReference' => $reference,
            'status' => 'pending'
        ]);
        
        // Store reference in session for verification
        $_SESSION['payment_reference'] = $reference;
        $_SESSION['payment_amount'] = $amount / 100;
        
        // Initialize Paystack payment
        $curl = curl_init();
        
        $fields = [
            'email' => $email,
            'amount' => $amount,
            'reference' => $reference,
            'callback_url' => PAYSTACK_CALLBACK_URL,
            'metadata' => [
                'user_id' => $userId,
                'coins' => $amount / 100,
                'displayName' => $userData['displayName']
            ]
        ];
        
        curl_setopt_array($curl, [
            CURLOPT_URL => "https://api.paystack.co/transaction/initialize",
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => "POST",
            CURLOPT_POSTFIELDS => json_encode($fields),
            CURLOPT_HTTPHEADER => [
                "Authorization: Bearer " . PAYSTACK_SECRET_KEY,
                "Content-Type: application/json",
                "Cache-Control: no-cache"
            ],
        ]);
        
        $response = curl_exec($curl);
        $err = curl_error($curl);
        curl_close($curl);
        
        if ($err) {
            $error = "Connection error: " . $err;
        } else {
            $result = json_decode($response, true);
            
            if ($result['status'] && isset($result['data']['authorization_url'])) {
                // Redirect to Paystack payment page
                header('Location: ' . $result['data']['authorization_url']);
                exit();
            } else {
                $error = "Payment initialization failed: " . ($result['message'] ?? 'Unknown error');
            }
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Coins - Paystack</title>
    <script src="https://js.paystack.co/v1/inline.js"></script>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .container {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
            width: 100%;
            max-width: 500px;
            overflow: hidden;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
        }
        
        .header .subtitle {
            opacity: 0.9;
            font-size: 14px;
        }
        
        .balance-card {
            background: #f8f9fa;
            border-radius: 15px;
            padding: 25px;
            margin: 20px;
            text-align: center;
            border: 2px solid #e9ecef;
        }
        
        .balance-card h3 {
            color: #495057;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .balance-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 15px;
        }
        
        .balance-item {
            background: white;
            padding: 15px;
            border-radius: 10px;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .balance-item .label {
            font-size: 12px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .balance-item .amount {
            font-size: 18px;
            font-weight: bold;
            color: #495057;
        }
        
        .packages-section {
            padding: 20px;
        }
        
        .packages-section h3 {
            text-align: center;
            margin-bottom: 25px;
            color: #495057;
            font-size: 18px;
        }
        
        .package-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .package-btn {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border: none;
            border-radius: 12px;
            padding: 20px;
            color: white;
            cursor: pointer;
            transition: all 0.3s ease;
            text-align: center;
            box-shadow: 0 8px 25px rgba(245, 87, 108, 0.3);
        }
        
        .package-btn:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(245, 87, 108, 0.4);
        }
        
        .package-btn:active {
            transform: translateY(-2px);
        }
        
        .package-btn .coins {
            font-size: 24px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .package-btn .price {
            font-size: 14px;
            opacity: 0.9;
        }
        
        .custom-amount {
            padding: 20px;
            background: #f8f9fa;
            margin: 20px;
            border-radius: 15px;
            display: none;
        }
        
        .custom-amount h4 {
            text-align: center;
            margin-bottom: 15px;
            color: #495057;
        }
        
        .custom-input-group {
            display: flex;
            gap: 10px;
        }
        
        .custom-input-group input {
            flex: 1;
            padding: 12px 15px;
            border: 2px solid #dee2e6;
            border-radius: 8px;
            font-size: 16px;
            text-align: center;
        }
        
        .custom-input-group input:focus {
            outline: none;
            border-color: #667eea;
        }
        
        .custom-input-group button {
            background: #667eea;
            color: white;
            border: none;
            border-radius: 8px;
            padding: 12px 25px;
            cursor: pointer;
            font-weight: bold;
            transition: background 0.3s;
        }
        
        .custom-input-group button:hover {
            background: #5a67d8;
        }
        
        .toggle-custom {
            text-align: center;
            padding: 15px;
            color: #667eea;
            cursor: pointer;
            font-weight: bold;
        }
        
        .toggle-custom:hover {
            text-decoration: underline;
        }
        
        .messages {
            padding: 0 20px 20px;
        }
        
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 15px;
        }
        
        .navigation {
            display: flex;
            justify-content: space-between;
            padding: 20px;
            border-top: 1px solid #dee2e6;
        }
        
        .nav-btn {
            padding: 12px 30px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: bold;
            transition: all 0.3s;
        }
        
        .nav-btn.dashboard {
            background: #6c757d;
            color: white;
        }
        
        .nav-btn.dashboard:hover {
            background: #5a6268;
        }
        
        .nav-btn.logout {
            background: #dc3545;
            color: white;
        }
        
        .nav-btn.logout:hover {
            background: #c82333;
        }
        
        .payment-options {
            padding: 20px;
            text-align: center;
        }
        
        .payment-options img {
            height: 30px;
            margin: 0 10px;
            opacity: 0.7;
        }
        
        @media (max-width: 768px) {
            .package-grid {
                grid-template-columns: 1fr;
            }
            
            .balance-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>💰 Add Game Coins</h1>
            <p class="subtitle">Power up your gaming experience</p>
        </div>
        
        <?php if ($error): ?>
            <div class="messages">
                <div class="error"><?php echo htmlspecialchars($error); ?></div>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="messages">
                <div class="success"><?php echo htmlspecialchars($success); ?></div>
            </div>
        <?php endif; ?>
        
        <div class="balance-card">
            <h3>Your Current Balance</h3>
            <div class="balance-grid">
                <div class="balance-item">
                    <div class="label">Total Coins</div>
                    <div class="amount"><?php echo number_format($userData['totalCoins']); ?></div>
                </div>
                <div class="balance-item">
                    <div class="label">Deposit Coins</div>
                    <div class="amount"><?php echo number_format($userData['depositCoins']); ?></div>
                </div>
                <div class="balance-item">
                    <div class="label">Winning Coins</div>
                    <div class="amount"><?php echo number_format($userData['winningCoins']); ?></div>
                </div>
            </div>
        </div>
        
        <div class="packages-section">
            <h3>Select Coin Package</h3>
            
            <div class="package-grid">
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="amount" value="100">
                    <button type="submit" class="package-btn">
                        <div class="coins">100 Coins</div>
                        <div class="price">₦100.00</div>
                    </button>
                </form>
                
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="amount" value="500">
                    <button type="submit" class="package-btn">
                        <div class="coins">500 Coins</div>
                        <div class="price">₦500.00</div>
                    </button>
                </form>
                
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="amount" value="1000">
                    <button type="submit" class="package-btn">
                        <div class="coins">1,000 Coins</div>
                        <div class="price">₦1,000.00</div>
                    </button>
                </form>
                
                <form method="POST" style="display: inline;">
                    <input type="hidden" name="amount" value="5000">
                    <button type="submit" class="package-btn">
                        <div class="coins">5,000 Coins</div>
                        <div class="price">₦5,000.00</div>
                    </button>
                </form>
            </div>
            
            <div class="toggle-custom" onclick="toggleCustomAmount()">
                Want a different amount? Click here
            </div>
            
            <div class="custom-amount" id="customAmountSection">
                <h4>Enter Custom Amount</h4>
                <form method="POST" id="customForm">
                    <div class="custom-input-group">
                        <input type="number" name="custom_amount" id="customAmount" 
                               min="100" max="100000" step="100" 
                               placeholder="Min: 100 coins" required>
                        <button type="button" onclick="submitCustomAmount()">Buy</button>
                    </div>
                </form>
            </div>
        </div>
        
        <div class="payment-options">
            <p style="margin-bottom: 10px; color: #6c757d; font-size: 14px;">Secure payments powered by</p>
            <img src="https://www.paystack.com/assets/website/images/logo.svg" alt="Paystack">
            <img src="https://upload.wikimedia.org/wikipedia/commons/a/a4/Mastercard_2019_logo.svg" alt="Mastercard">
            <img src="https://upload.wikimedia.org/wikipedia/commons/5/5e/Visa_Inc._logo.svg" alt="Visa">
            <img src="https://upload.wikimedia.org/wikipedia/commons/thumb/7/72/Interswitch_logo.svg/2560px-Interswitch_logo.svg.png" alt="Interswitch" style="height: 20px;">
        </div>
        
        <div class="navigation">
            <a href="dashboard.php" class="nav-btn dashboard">← Back to Dashboard</a>
            <a href="logout.php" class="nav-btn logout">Logout</a>
        </div>
    </div>
    
    <script>
        function toggleCustomAmount() {
            const section = document.getElementById('customAmountSection');
            if (section.style.display === 'block') {
                section.style.display = 'none';
            } else {
                section.style.display = 'block';
                document.getElementById('customAmount').focus();
            }
        }
        
        function submitCustomAmount() {
            const amount = document.getElementById('customAmount').value;
            if (amount >= 100) {
                // Create a hidden form and submit
                const form = document.createElement('form');
                form.method = 'POST';
                
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'amount';
                input.value = amount;
                form.appendChild(input);
                
                document.body.appendChild(form);
                form.submit();
            } else {
                alert('Minimum purchase is 100 coins (₦100)');
            }
        }
        
        // Auto-hide messages after 5 seconds
        setTimeout(() => {
            const messages = document.querySelectorAll('.error, .success');
            messages.forEach(msg => {
                msg.style.transition = 'opacity 0.5s';
                msg.style.opacity = '0';
                setTimeout(() => msg.remove(), 500);
            });
        }, 5000);
    </script>
</body>
</html>