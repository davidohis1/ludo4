<?php
session_start();

// ==================== CONFIGURATION ====================
$FIREBASE_API_KEY = "AIzaSyBpmiHaduU-jPR2zBlFiS3uZAByWy5IiiE";
$FIREBASE_PROJECT_ID = "champ-7b072";
$PAYSTACK_SECRET_KEY = "sk_live_c0f51b10fb9361d6ed0b49a83a4f8e79379defad";
$dp ="sk_live_c0f51b10fb9361d6ed0b49a83a4f8e79379defad";
$SITE_URL = "http://" . $_SERVER['HTTP_HOST'] . "/ludo";

// Redirect if not logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['idToken'])) {
    header('Location: login.php');
    exit();                                            
}

// ==================== FIREBASE FUNCTIONS ====================

function getFirestoreUser($userId, $idToken) {
    global $FIREBASE_PROJECT_ID;
    
    $url = "https://firestore.googleapis.com/v1/projects/{$FIREBASE_PROJECT_ID}/databases/(default)/documents/users/{$userId}";
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $idToken,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    $data = json_decode($response, true);
    
    if (isset($data['fields'])) {
        $fields = $data['fields'];
        return [
            'id' => $userId,
            'email' => $fields['email']['stringValue'] ?? '',
            'displayName' => $fields['displayName']['stringValue'] ?? 'Player',
            'totalCoins' => $fields['totalCoins']['integerValue'] ?? 0,
            'depositCoins' => $fields['depositCoins']['integerValue'] ?? 0,
            'winningCoins' => $fields['winningCoins']['integerValue'] ?? 0,
            'lives' => $fields['lives']['integerValue'] ?? 5,
            'avatarUrl' => $fields['avatarUrl']['stringValue'] ?? ''
        ];
    }
    
    return null;
}

function addCoinsToUser($userId, $idToken, $coinsToAdd) {
    global $FIREBASE_PROJECT_ID;
    
    $user = getFirestoreUser($userId, $idToken);
    if (!$user) return false;
    
    $newDepositCoins = $user['depositCoins'] + $coinsToAdd;
    $newTotalCoins = $user['totalCoins'] + $coinsToAdd;
    
    $url = "https://firestore.googleapis.com/v1/projects/{$FIREBASE_PROJECT_ID}/databases/(default)/documents/users/{$userId}";
    
    $data = [
        'fields' => [
            'depositCoins' => ['integerValue' => $newDepositCoins],
            'totalCoins' => ['integerValue' => $newTotalCoins]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url . '?updateMask.fieldPaths=depositCoins&updateMask.fieldPaths=totalCoins',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $idToken,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

function deductWinningCoins($userId, $idToken, $amount) {
    global $FIREBASE_PROJECT_ID;
    
    $user = getFirestoreUser($userId, $idToken);
    if (!$user || $user['winningCoins'] < $amount) return false;
    
    $newWinningCoins = $user['winningCoins'] - $amount;
    $newTotalCoins = $user['totalCoins'] - $amount;
    
    $url = "https://firestore.googleapis.com/v1/projects/{$FIREBASE_PROJECT_ID}/databases/(default)/documents/users/{$userId}";
    
    $data = [
        'fields' => [
            'winningCoins' => ['integerValue' => $newWinningCoins],
            'totalCoins' => ['integerValue' => $newTotalCoins]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url . '?updateMask.fieldPaths=winningCoins&updateMask.fieldPaths=totalCoins',
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => 'PATCH',
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $idToken,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    return $httpCode === 200;
}

function addTransaction($userId, $idToken, $type, $amount, $description) {
    global $FIREBASE_PROJECT_ID;
    
    $url = "https://firestore.googleapis.com/v1/projects/{$FIREBASE_PROJECT_ID}/databases/(default)/documents/users/{$userId}/transactions";
    
    $data = [
        'fields' => [
            'type' => ['stringValue' => $type],
            'amount' => ['integerValue' => $amount],
            'description' => ['stringValue' => $description],
            'timestamp' => ['timestampValue' => date('c')]
        ]
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $idToken,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

// ==================== PAYSTACK FUNCTIONS ====================

function initPaystackPayment($email, $amount, $reference, $metadata = []) {
    global $PAYSTACK_SECRET_KEY, $SITE_URL;
    
    $url = "https://api.paystack.co/transaction/initialize";
    
    $data = [
        'email' => $email,
        'amount' => $amount * 100,
        'reference' => $reference,
        'callback_url' => $SITE_URL . '/dashboard.php',
        'metadata' => $metadata
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $PAYSTACK_SECRET_KEY,
            'Content-Type: application/json'
        ]
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

function verifyPaystackPayment($reference) {
    global $PAYSTACK_SECRET_KEY;
    
    $url = "https://api.paystack.co/transaction/verify/" . $reference;
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $PAYSTACK_SECRET_KEY
        ]
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

function createPaystackTransfer($accountNumber, $bankCode, $amount, $reason) {
    global $PAYSTACK_SECRET_KEY;
    
    // Create transfer recipient
    $recipientUrl = "https://api.paystack.co/transferrecipient";
    $recipientData = [
        'type' => 'nuban',
        'name' => $reason,
        'account_number' => $accountNumber,
        'bank_code' => $bankCode,
        'currency' => 'NGN'
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $recipientUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($recipientData),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $PAYSTACK_SECRET_KEY,
            'Content-Type: application/json'
        ]
    ]);
    
    $recipientResponse = curl_exec($ch);
    curl_close($ch);
    $recipientResult = json_decode($recipientResponse, true);
    
    if (!$recipientResult['status']) {
        return $recipientResult;
    }
    
    // Initiate transfer
    $transferUrl = "https://api.paystack.co/transfer";
    $transferData = [
        'source' => 'balance',
        'amount' => $amount * 100,
        'recipient' => $recipientResult['data']['recipient_code'],
        'reason' => $reason
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $transferUrl,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($transferData),
        CURLOPT_HTTPHEADER => [
            'Authorization: Bearer ' . $PAYSTACK_SECRET_KEY,
            'Content-Type: application/json'
        ]
    ]);
    
    $transferResponse = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($transferResponse, true);
}

// ==================== HANDLE ACTIONS ====================

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit();
}

// Handle deposit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['deposit'])) {
    $amount = intval($_POST['amount']);
    if ($amount < 100) {
        $error = "Minimum deposit is ₦100";
    } else {
        $reference = 'DEP_' . $_SESSION['user']['id'] . '_' . time();
        
        $payment = initPaystackPayment(
            $_SESSION['user']['email'],
            $amount,
            $reference,
            ['user_id' => $_SESSION['user']['id'], 'coins' => $amount]
        );
        
        if ($payment['status']) {
            $_SESSION['payment_reference'] = $reference;
            $_SESSION['payment_amount'] = $amount;
            header('Location: ' . $payment['data']['authorization_url']);
            exit();
        } else {
            $error = "Payment initialization failed";
        }
    }
}

// Handle withdrawal
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['withdraw'])) {
    $amount = intval($_POST['withdraw_amount']);
    $accountNumber = $_POST['account_number'];
    $bankCode = $_POST['bank_code'];
    
    if ($amount < 1000) {
        $error = "Minimum withdrawal is ₦1,000";
    } elseif ($amount > $_SESSION['user']['winningCoins']) {
        $error = "Insufficient winning coins balance";
    } else {
        // Deduct coins first
        if (deductWinningCoins($_SESSION['user']['id'], $_SESSION['idToken'], $amount)) {
            // Process withdrawal
            $withdrawal = createPaystackTransfer(
                $accountNumber,
                $bankCode,
                $amount,
                'LudoTitans Withdrawal - ' . $_SESSION['user']['displayName']
            );
            
            if ($withdrawal['status']) {
                addTransaction(
                    $_SESSION['user']['id'],
                    $_SESSION['idToken'],
                    'withdrawal',
                    $amount,
                    'Withdrawn ₦' . number_format($amount) . ' to bank account'
                );
                
                $_SESSION['user']['winningCoins'] -= $amount;
                $_SESSION['user']['totalCoins'] -= $amount;
                $success = "Withdrawal of ₦" . number_format($amount) . " processed successfully!";
            } else {
                // Refund coins if transfer failed
                addCoinsToUser($_SESSION['user']['id'], $_SESSION['idToken'], $amount);
                $error = "Withdrawal failed. Please try again.";
            }
        } else {
            $error = "Failed to process withdrawal";
        }
    }
}

// Handle Paystack callback
if (isset($_GET['reference'])) {
    $reference = $_GET['reference'];
    
    $verification = verifyPaystackPayment($reference);
    
    if ($verification['status'] && $verification['data']['status'] === 'success') {
        $amount = $verification['data']['amount'] / 100;
        
        if (addCoinsToUser($_SESSION['user']['id'], $_SESSION['idToken'], $amount)) {
            addTransaction(
                $_SESSION['user']['id'],
                $_SESSION['idToken'],
                'deposit',
                $amount,
                'Added ' . $amount . ' coins via Paystack'
            );
            
            $_SESSION['user']['depositCoins'] += $amount;
            $_SESSION['user']['totalCoins'] += $amount;
            $_SESSION['payment_success'] = "₦" . number_format($amount) . " added successfully!";
        }
    }
    
    header('Location: dashboard.php');
    exit();
}

// Check for success message
if (isset($_SESSION['payment_success'])) {
    $success = $_SESSION['payment_success'];
    unset($_SESSION['payment_success']);
}

// Refresh user data
$_SESSION['user'] = getFirestoreUser($_SESSION['user']['id'], $_SESSION['idToken']);

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - LudoTitans</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #1a0f1f;
            color: white;
            min-height: 100vh;
        }

        /* Navigation */
        nav {
            background: rgba(0, 0, 0, 0.5);
            padding: 20px 0;
            border-bottom: 1px solid rgba(255, 71, 87, 0.2);
        }

        .nav-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 0 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .logo {
            font-size: 24px;
            font-weight: bold;
            background: linear-gradient(135deg, #ff4757, #ffa502);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .nav-links {
            display: flex;
            gap: 20px;
            align-items: center;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 8px;
            transition: all 0.3s;
        }

        .nav-links a:hover {
            background: rgba(255, 71, 87, 0.1);
        }

        .btn-logout {
            background: #ef4444;
            padding: 10px 20px;
            border-radius: 8px;
            border: none;
            color: white;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }

        /* Dashboard Container */
        .dashboard {
            max-width: 1200px;
            margin: 40px auto;
            padding: 0 20px;
        }

        /* Welcome Section */
        .welcome {
            background: linear-gradient(135deg, rgba(255, 71, 87, 0.1), rgba(255, 165, 2, 0.1));
            padding: 30px;
            border-radius: 20px;
            border: 1px solid rgba(255, 71, 87, 0.2);
            margin-bottom: 30px;
        }

        .welcome h1 {
            font-size: 32px;
            margin-bottom: 10px;
        }

        .welcome p {
            color: #9ca3af;
        }

        /* Balance Cards */
        .balance-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .balance-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 25px;
            border-radius: 15px;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .balance-card h3 {
            color: #9ca3af;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .balance-amount {
            font-size: 32px;
            font-weight: bold;
            color: #ff4757;
        }

        /* Tabs */
        .tabs {
            display: flex;
            gap: 10px;
            margin-bottom: 30px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.1);
        }

        .tab {
            background: transparent;
            border: none;
            color: #9ca3af;
            padding: 15px 30px;
            cursor: pointer;
            font-size: 16px;
            font-weight: 600;
            border-bottom: 3px solid transparent;
            transition: all 0.3s;
        }

        .tab.active {
            color: #ff4757;
            border-bottom-color: #ff4757;
        }

        /* Tab Content */
        .tab-content {
            display: none;
        }

        .tab-content.active {
            display: block;
        }

        /* Forms */
        .form-container {
            background: rgba(255, 255, 255, 0.05);
            padding: 30px;
            border-radius: 15px;
            max-width: 600px;
        }

        .form-group {
            margin-bottom: 20px;
        }

        label {
            display: block;
            color: #9ca3af;
            margin-bottom: 8px;
            font-size: 14px;
        }

        input, select {
            width: 100%;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: white;
            font-size: 16px;
        }

        input:focus, select:focus {
            outline: none;
            border-color: #ff4757;
        }

        .btn {
            background: linear-gradient(135deg, #ff4757, #ff6b81);
            padding: 15px 30px;
            border-radius: 10px;
            border: none;
            color: white;
            font-weight: bold;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            margin-top: 10px;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 71, 87, 0.4);
        }

        .package-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
            margin: 20px 0;
        }

        .package-btn {
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            padding: 20px;
            border-radius: 10px;
            cursor: pointer;
            transition: all 0.3s;
            text-align: center;
            color: white;
        }

        .package-btn:hover {
            background: #ff4757;
            border-color: #ff4757;
        }

        .message {
            padding: 15px;
            border-radius: 10px;
            margin-bottom: 20px;
            text-align: center;
        }

        .success {
            background: rgba(34, 197, 94, 0.1);
            border: 1px solid #22c55e;
            color: #86efac;
        }

        .error {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #fca5a5;
        }

        @media (max-width: 768px) {
            .package-grid {
                grid-template-columns: 1fr;
            }
            
            .nav-links {
                gap: 10px;
            }
            
            .nav-links a span {
                display: none;
            }
        }
    </style>
</head>
<body>
    <nav>
        <div class="nav-container">
            <div class="logo">🎲 LudoTitans</div>
            <div class="nav-links">
                <a href="leaderboard.php"><span>🏆 </span>Leaderboard</a>
                <a href="?logout=1" class="btn-logout">Logout</a>
            </div>
        </div>
    </nav>

    <div class="dashboard">
        <div class="welcome">
            <h1>Welcome, <?php echo htmlspecialchars($_SESSION['user']['displayName']); ?>! 👋</h1>
            <p>Manage your coins, deposits, and withdrawals</p>
        </div>

        <?php if (isset($success)): ?>
            <div class="message success">✅ <?php echo $success; ?></div>
        <?php endif; ?>

        <?php if (isset($error)): ?>
            <div class="message error">❌ <?php echo $error; ?></div>
        <?php endif; ?>

        <div class="balance-grid">
            <div class="balance-card">
                <h3>TOTAL COINS</h3>
                <div class="balance-amount"><?php echo number_format($_SESSION['user']['totalCoins']); ?></div>
            </div>
            <div class="balance-card">
                <h3>DEPOSIT COINS</h3>
                <div class="balance-amount"><?php echo number_format($_SESSION['user']['depositCoins']); ?></div>
            </div>
            <div class="balance-card">
                <h3>WINNING COINS</h3>
                <div class="balance-amount"><?php echo number_format($_SESSION['user']['winningCoins']); ?></div>
            </div>
            <div class="balance-card">
                <h3>LIVES</h3>
                <div class="balance-amount"><?php echo $_SESSION['user']['lives']; ?></div>
            </div>
        </div>

        <div class="tabs">
            <button class="tab active" onclick="switchTab('deposit')">💰 Add Coins</button>
            <button class="tab" onclick="switchTab('withdraw')">💸 Withdraw</button>
        </div>

        <!-- Deposit Tab -->
        <div id="deposit" class="tab-content active">
            <div class="form-container">
                <h2 style="margin-bottom: 20px;">Add Coins to Your Account</h2>
                
                <form method="POST">
                    <div class="package-grid">
                        <button type="button" class="package-btn" onclick="setAmount(100)">
                            <div style="font-size: 24px; font-weight: bold;">100</div>
                            <div>₦100.00</div>
                        </button>
                        <button type="button" class="package-btn" onclick="setAmount(500)">
                            <div style="font-size: 24px; font-weight: bold;">500</div>
                            <div>₦500.00</div>
                        </button>
                        <button type="button" class="package-btn" onclick="setAmount(1000)">
                            <div style="font-size: 24px; font-weight: bold;">1,000</div>
                            <div>₦1,000.00</div>
                        </button>
                        <button type="button" class="package-btn" onclick="setAmount(5000)">
                            <div style="font-size: 24px; font-weight: bold;">5,000</div>
                            <div>₦5,000.00</div>
                        </button>
                    </div>

                    <div class="form-group">
                        <label>Or enter custom amount (Min: ₦100)</label>
                        <input type="number" name="amount" id="amountInput" min="50" required placeholder="Enter amount">
                    </div>

                    <button type="submit" name="deposit" class="btn">Proceed to Payment</button>
                </form>
            </div>
        </div>

        <!-- Withdraw Tab -->
        <div id="withdraw" class="tab-content">
            <div class="form-container">
                <h2 style="margin-bottom: 20px;">Withdraw Winning Coins</h2>
                
                <div style="background: rgba(59, 130, 246, 0.1); border: 1px solid rgba(59, 130, 246, 0.3); padding: 15px; border-radius: 10px; margin-bottom: 20px;">
                    <p style="color: #93c5fd; font-size: 14px;">💡 Only winning coins can be withdrawn. Minimum withdrawal: ₦1,000</p>
                </div>

                <form method="POST">
                    <div class="form-group">
                        <label>Withdrawal Amount (₦)</label>
                        <input type="number" name="withdraw_amount" min="1000" max="<?php echo $_SESSION['user']['winningCoins']; ?>" required placeholder="Enter amount">
                    </div>

                    <div class="form-group">
                        <label>Account Number</label>
                        <input type="text" name="account_number" required placeholder="0123456789">
                    </div>

                    <div class="form-group">
                        <label>Bank</label>
                        <select name="bank_code" required>
                            <option value="">Select Bank</option>
                            <option value="044">Access Bank</option>
                            <option value="063">Access Bank (Diamond)</option>
                            <option value="050">Ecobank Nigeria</option>
                            <option value="070">Fidelity Bank</option>
                            <option value="011">First Bank of Nigeria</option>
                            <option value="214">First City Monument Bank</option>
                            <option value="058">Guaranty Trust Bank</option>
                            <option value="030">Heritage Bank</option>
                            <option value="301">Jaiz Bank</option>
                            <option value="082">Keystone Bank</option>
                            <option value="526">Parallex Bank</option>
                            <option value="076">Polaris Bank</option>
                            <option value="101">Providus Bank</option>
                            <option value="221">Stanbic IBTC Bank</option>
                            <option value="068">Standard Chartered Bank</option>
                            <option value="232">Sterling Bank</option>
                            <option value="100">Suntrust Bank</option>
                            <option value="032">Union Bank of Nigeria</option>
                            <option value="033">United Bank for Africa</option>
                            <option value="215">Unity Bank</option>
                            <option value="035">Wema Bank</option>
                            <option value="057">Zenith Bank</option>
                        </select>
                    </div>

                    <button type="submit" name="withdraw" class="btn">Process Withdrawal</button>
                </form>
            </div>
        </div>
    </div>

    <script>
        function switchTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.tab-content').forEach(tab => {
                tab.classList.remove('active');
            });
            document.querySelectorAll('.tab').forEach(tab => {
                tab.classList.remove('active');
            });

            // Show selected tab
            document.getElementById(tabName).classList.add('active');
            event.target.classList.add('active');
        }

        function setAmount(amount) {
            document.getElementById('amountInput').value = amount;
        }
    </script>
</body>
</html>