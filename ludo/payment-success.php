<?php
require_once 'config/firebase-config.php';

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

$amount = isset($_GET['amount']) ? intval($_GET['amount']) : 0;
$coins = isset($_GET['coins']) ? intval($_GET['coins']) : 0;
$userId = $_SESSION['user']['uid'];
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        body {
            background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
            min-height: 100vh;
            display: flex;
            justify-content: center;
            align-items: center;
            padding: 20px;
        }
        
        .success-container {
            background: white;
            border-radius: 25px;
            padding: 50px;
            text-align: center;
            box-shadow: 0 30px 80px rgba(0, 0, 0, 0.3);
            max-width: 600px;
            width: 100%;
            animation: slideIn 0.6s ease-out;
        }
        
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .success-icon {
            font-size: 100px;
            color: #00b09b;
            margin-bottom: 30px;
            animation: bounce 1s ease infinite alternate;
        }
        
        @keyframes bounce {
            from { transform: translateY(0); }
            to { transform: translateY(-20px); }
        }
        
        h1 {
            color: #333;
            margin-bottom: 20px;
            font-size: 36px;
        }
        
        .success-message {
            font-size: 18px;
            color: #666;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        
        .coins-added {
            background: linear-gradient(135deg, #00b09b 0%, #96c93d 100%);
            color: white;
            padding: 30px;
            border-radius: 20px;
            margin: 30px 0;
            animation: pulse 2s infinite;
        }
        
        @keyframes pulse {
            0% { transform: scale(1); }
            50% { transform: scale(1.05); }
            100% { transform: scale(1); }
        }
        
        .coins-amount {
            font-size: 60px;
            font-weight: bold;
            margin-bottom: 10px;
        }
        
        .coins-label {
            font-size: 20px;
            opacity: 0.9;
        }
        
        .balance-update {
            background: #f8f9fa;
            padding: 25px;
            border-radius: 15px;
            margin: 30px 0;
            text-align: left;
        }
        
        .balance-update h3 {
            margin-bottom: 15px;
            color: #495057;
            text-align: center;
        }
        
        .balance-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        
        .balance-item {
            background: white;
            padding: 15px;
            border-radius: 10px;
            text-align: center;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.05);
        }
        
        .balance-item .label {
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 5px;
        }
        
        .balance-item .amount {
            font-size: 24px;
            font-weight: bold;
            color: #495057;
        }
        
        .actions {
            display: flex;
            justify-content: center;
            gap: 20px;
            margin-top: 40px;
        }
        
        .action-btn {
            padding: 15px 40px;
            border-radius: 12px;
            text-decoration: none;
            font-weight: bold;
            font-size: 16px;
            transition: all 0.3s;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }
        
        .action-btn.play {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        
        .action-btn.play:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 30px rgba(102, 126, 234, 0.4);
        }
        
        .action-btn.more {
            background: #6c757d;
            color: white;
        }
        
        .action-btn.more:hover {
            background: #5a6268;
            transform: translateY(-5px);
        }
        
        .confirmation-details {
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #dee2e6;
            font-size: 14px;
            color: #6c757d;
        }
        
        @media (max-width: 768px) {
            .success-container {
                padding: 30px;
            }
            
            .balance-grid {
                grid-template-columns: 1fr;
            }
            
            .actions {
                flex-direction: column;
            }
            
            .action-btn {
                width: 100%;
            }
        }
    </style>
    <script>
        // Auto-refresh user balance
        setTimeout(() => {
            location.reload();
        }, 30000); // Refresh every 30 seconds
    </script>
</head>
<body>
    <div class="success-container">
        <div class="success-icon">🎉</div>
        
        <h1>Payment Successful!</h1>
        
        <p class="success-message">
            Thank you for your purchase! Your coins have been added to your account 
            and are ready to use immediately.
        </p>
        
        <div class="coins-added">
            <div class="coins-amount">+<?php echo number_format($coins); ?></div>
            <div class="coins-label">COINS ADDED</div>
        </div>
        
        <div class="balance-update">
            <h3>Updated Balance</h3>
            <div class="balance-grid">
                <div class="balance-item">
                    <div class="label">Total Coins</div>
                    <div class="amount"><?php echo number_format($_SESSION['user']['totalCoins']); ?></div>
                </div>
                <div class="balance-item">
                    <div class="label">Deposit Coins</div>
                    <div class="amount"><?php echo number_format($_SESSION['user']['depositCoins']); ?></div>
                </div>
                <div class="balance-item">
                    <div class="label">Winning Coins</div>
                    <div class="amount"><?php echo number_format($_SESSION['user']['winningCoins']); ?></div>
                </div>
                <div class="balance-item">
                    <div class="label">Lives</div>
                    <div class="amount"><?php echo $_SESSION['user']['lives']; ?></div>
                </div>
            </div>
        </div>
        
        <div class="confirmation-details">
            <p>Transaction ID: PAY_<?php echo $userId . '_' . time(); ?></p>
            <p>Amount: ₦<?php echo number_format($amount, 2); ?></p>
            <p>Date: <?php echo date('F j, Y, g:i a'); ?></p>
        </div>
        
        <div class="actions">
            <a href="dashboard.php" class="action-btn play">
                <span>🏠</span> Go to Dashboard
            </a>
            <a href="payment.php" class="action-btn more">
                <span>💰</span> Add More Coins
            </a>
        </div>
    </div>
    
    <script>
        // Show confetti animation
        setTimeout(() => {
            const emojis = ['🎉', '💰', '🎮', '🏆', '⭐', '👑'];
            const container = document.querySelector('.success-container');
            
            for (let i = 0; i < 50; i++) {
                const emoji = document.createElement('div');
                emoji.textContent = emojis[Math.floor(Math.random() * emojis.length)];
                emoji.style.position = 'fixed';
                emoji.style.left = Math.random() * 100 + 'vw';
                emoji.style.top = '-50px';
                emoji.style.fontSize = Math.random() * 30 + 20 + 'px';
                emoji.style.opacity = '0.8';
                emoji.style.zIndex = '9999';
                emoji.style.pointerEvents = 'none';
                emoji.style.animation = `fall ${Math.random() * 3 + 2}s linear forwards`;
                
                document.body.appendChild(emoji);
                
                setTimeout(() => {
                    emoji.remove();
                }, 5000);
            }
        }, 1000);
        
        // Add CSS for falling animation
        const style = document.createElement('style');
        style.textContent = `
            @keyframes fall {
                to {
                    transform: translateY(100vh) rotate(360deg);
                    opacity: 0;
                }
            }
        `;
        document.head.appendChild(style);
    </script>
</body>
</html>