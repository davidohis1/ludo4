<?php
session_start();

// Configuration
$FIREBASE_API_KEY = "AIzaSyBpmiHaduU-jPR2zBlFiS3uZAByWy5IiiE";
$FIREBASE_PROJECT_ID = "champ-7b072";

// Redirect if already logged in
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit();
}

/**
 * Firebase Login
 */
function firebaseLogin($email, $password) {
    global $FIREBASE_API_KEY;
    
    $url = "https://identitytoolkit.googleapis.com/v1/accounts:signInWithPassword?key=" . $FIREBASE_API_KEY;
    
    $data = [
        'email' => $email,
        'password' => $password,
        'returnSecureToken' => true
    ];
    
    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_HTTPHEADER => ['Content-Type: application/json']
    ]);
    
    $response = curl_exec($ch);
    curl_close($ch);
    
    return json_decode($response, true);
}

/**
 * Get user from Firestore
 */
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

// Handle login form
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['login'])) {
    $email = $_POST['email'];
    $password = $_POST['password'];
    
    $loginResult = firebaseLogin($email, $password);
    
    if (isset($loginResult['idToken'])) {
        $userId = $loginResult['localId'];
        $idToken = $loginResult['idToken'];
        
        $user = getFirestoreUser($userId, $idToken);
        
        if ($user) {
            $_SESSION['user'] = $user;
            $_SESSION['idToken'] = $idToken;
            header('Location: dashboard.php');
            exit();
        } else {
            $error = "User not found in database";
        }
    } else {
        $error = "Login failed: " . ($loginResult['error']['message'] ?? 'Invalid credentials');
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - LudoTitans</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #0a0a0a;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px;
            position: relative;
            overflow: hidden;
        }

        /* Background Animation */
        .background-animation {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: 0;
            background: linear-gradient(45deg, #1a0f1f, #2d1b3d, #1a0f1f);
            background-size: 400% 400%;
            animation: gradientShift 15s ease infinite;
        }

        @keyframes gradientShift {
            0% { background-position: 0% 50%; }
            50% { background-position: 100% 50%; }
            100% { background-position: 0% 50%; }
        }

        .floating-dice {
            position: absolute;
            font-size: 40px;
            opacity: 0.1;
            animation: float 20s infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0) rotate(0deg); }
            50% { transform: translateY(-100px) rotate(180deg); }
        }

        .login-container {
            position: relative;
            z-index: 1;
            background: rgba(26, 15, 31, 0.9);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 71, 87, 0.2);
            border-radius: 30px;
            padding: 50px;
            width: 100%;
            max-width: 450px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.5);
            animation: slideUp 0.6s ease;
        }

        @keyframes slideUp {
            from {
                opacity: 0;
                transform: translateY(50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .logo {
            text-align: center;
            margin-bottom: 40px;
        }

        .logo-icon {
            font-size: 64px;
            margin-bottom: 10px;
        }

        .logo-text {
            font-size: 32px;
            font-weight: bold;
            background: linear-gradient(135deg, #ff4757, #ffa502);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .logo-subtitle {
            color: #9ca3af;
            font-size: 14px;
            margin-top: 5px;
        }

        .form-group {
            margin-bottom: 25px;
        }

        label {
            display: block;
            color: #9ca3af;
            margin-bottom: 8px;
            font-size: 14px;
            font-weight: 500;
        }

        input {
            width: 100%;
            padding: 15px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            color: white;
            font-size: 16px;
            transition: all 0.3s;
        }

        input:focus {
            outline: none;
            border-color: #ff4757;
            background: rgba(255, 255, 255, 0.08);
        }

        input::placeholder {
            color: #6b7280;
        }

        .btn-login {
            width: 100%;
            padding: 15px;
            background: linear-gradient(135deg, #ff4757, #ff6b81);
            border: none;
            border-radius: 12px;
            color: white;
            font-size: 18px;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            margin-top: 10px;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 71, 87, 0.4);
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .error-message {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid #ef4444;
            color: #fca5a5;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            text-align: center;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }

        .divider {
            text-align: center;
            margin: 30px 0;
            color: #6b7280;
            position: relative;
        }

        .divider::before,
        .divider::after {
            content: '';
            position: absolute;
            top: 50%;
            width: 40%;
            height: 1px;
            background: rgba(255, 255, 255, 0.1);
        }

        .divider::before {
            left: 0;
        }

        .divider::after {
            right: 0;
        }

        .back-link {
            text-align: center;
            margin-top: 30px;
        }

        .back-link a {
            color: #9ca3af;
            text-decoration: none;
            transition: color 0.3s;
            font-size: 14px;
        }

        .back-link a:hover {
            color: #ff4757;
        }

        .info-box {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.3);
            color: #93c5fd;
            padding: 15px;
            border-radius: 12px;
            margin-bottom: 25px;
            font-size: 14px;
            text-align: center;
        }

        @media (max-width: 480px) {
            .login-container {
                padding: 30px 20px;
            }

            .logo-text {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <!-- Background Animation -->
    <div class="background-animation">
        <div class="floating-dice" style="top: 10%; left: 10%;">🎲</div>
        <div class="floating-dice" style="top: 20%; right: 15%; animation-delay: 3s;">🎲</div>
        <div class="floating-dice" style="bottom: 20%; left: 20%; animation-delay: 6s;">🎲</div>
        <div class="floating-dice" style="bottom: 30%; right: 10%; animation-delay: 9s;">🎲</div>
    </div>

    <div class="login-container">
        <div class="logo">
            <div class="logo-icon">🎲</div>
            <div class="logo-text">LudoTitans</div>
            <div class="logo-subtitle">Play & Earn Real Money</div>
        </div>

        <?php if (isset($error)): ?>
            <div class="error-message">
                ❌ <?php echo htmlspecialchars($error); ?>
            </div>
        <?php endif; ?>

        <div class="info-box">
            💡 Use the same email and password from your mobile app
        </div>

        <form method="POST">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input 
                    type="email" 
                    id="email" 
                    name="email" 
                    placeholder="your@email.com" 
                    required 
                    autocomplete="email"
                >
            </div>

            <div class="form-group">
                <label for="password">Password</label>
                <input 
                    type="password" 
                    id="password" 
                    name="password" 
                    placeholder="Enter your password" 
                    required 
                    autocomplete="current-password"
                >
            </div>

            <button type="submit" name="login" class="btn-login">
                Login to Dashboard
            </button>
        </form>

        <div class="divider">OR</div>

        <div class="back-link">
            <a href="index.php">← Back to Home</a>
        </div>
    </div>

    <script>
        // Auto-focus email field
        document.getElementById('email').focus();
    </script>
</body>
</html>