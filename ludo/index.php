<?php
session_start();

// Redirect to dashboard if already logged in
if (isset($_SESSION['user'])) {
    header('Location: dashboard.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>LudoTitans - Play & Earn Real Money</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #0a0a0a;
            color: white;
            overflow-x: hidden;
        }

        /* Animated Background */
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

        /* Navigation */
        nav {
            position: fixed;
            top: 0;
            width: 100%;
            background: rgba(26, 15, 31, 0.9);
            backdrop-filter: blur(10px);
            padding: 20px 0;
            z-index: 1000;
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
            font-size: 28px;
            font-weight: bold;
            background: linear-gradient(135deg, #ff4757, #ffa502);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .nav-links {
            display: flex;
            gap: 30px;
            align-items: center;
        }

        .nav-links a {
            color: white;
            text-decoration: none;
            font-weight: 500;
            transition: color 0.3s;
        }

        .nav-links a:hover {
            color: #ff4757;
        }

        .btn-primary {
            background: linear-gradient(135deg, #ff4757, #ff6b81);
            padding: 12px 30px;
            border-radius: 25px;
            border: none;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: transform 0.3s, box-shadow 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(255, 71, 87, 0.4);
        }

        /* Hero Section */
        .hero {
            position: relative;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 100px 20px 50px;
        }

        .hero-content {
            position: relative;
            z-index: 1;
            max-width: 800px;
        }

        .hero h1 {
            font-size: 64px;
            margin-bottom: 20px;
            background: linear-gradient(135deg, #ff4757, #ffa502, #ff4757);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: slideDown 1s ease;
        }

        @keyframes slideDown {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .hero p {
            font-size: 24px;
            color: #9ca3af;
            margin-bottom: 40px;
            animation: fadeIn 1s ease 0.3s both;
        }

        @keyframes fadeIn {
            from { opacity: 0; }
            to { opacity: 1; }
        }

        .hero-buttons {
            display: flex;
            gap: 20px;
            justify-content: center;
            flex-wrap: wrap;
            animation: fadeIn 1s ease 0.6s both;
        }

        .btn-secondary {
            background: transparent;
            border: 2px solid #ff4757;
            padding: 12px 30px;
            border-radius: 25px;
            color: white;
            font-weight: bold;
            cursor: pointer;
            transition: all 0.3s;
            text-decoration: none;
            display: inline-block;
        }

        .btn-secondary:hover {
            background: #ff4757;
            transform: translateY(-2px);
        }

        /* Features Section */
        .features {
            position: relative;
            padding: 100px 20px;
            background: rgba(0, 0, 0, 0.3);
        }

        .section-title {
            text-align: center;
            font-size: 48px;
            margin-bottom: 60px;
            background: linear-gradient(135deg, #fff, #9ca3af);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
        }

        .features-grid {
            max-width: 1200px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 30px;
        }

        .feature-card {
            background: rgba(255, 255, 255, 0.05);
            padding: 40px;
            border-radius: 20px;
            border: 1px solid rgba(255, 71, 87, 0.2);
            transition: all 0.3s;
            animation: scaleIn 0.5s ease both;
        }

        .feature-card:nth-child(1) { animation-delay: 0.1s; }
        .feature-card:nth-child(2) { animation-delay: 0.2s; }
        .feature-card:nth-child(3) { animation-delay: 0.3s; }
        .feature-card:nth-child(4) { animation-delay: 0.4s; }

        @keyframes scaleIn {
            from {
                opacity: 0;
                transform: scale(0.9);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .feature-card:hover {
            transform: translateY(-10px);
            border-color: #ff4757;
            box-shadow: 0 20px 40px rgba(255, 71, 87, 0.3);
        }

        .feature-icon {
            font-size: 48px;
            margin-bottom: 20px;
        }

        .feature-card h3 {
            font-size: 24px;
            margin-bottom: 15px;
            color: #ff4757;
        }

        .feature-card p {
            color: #9ca3af;
            line-height: 1.6;
        }

        /* How It Works */
        .how-it-works {
            padding: 100px 20px;
            position: relative;
        }

        .steps {
            max-width: 1000px;
            margin: 0 auto;
            display: grid;
            gap: 40px;
        }

        .step {
            display: flex;
            align-items: center;
            gap: 30px;
            animation: slideRight 0.8s ease both;
        }

        .step:nth-child(even) {
            flex-direction: row-reverse;
            animation-name: slideLeft;
        }

        .step:nth-child(1) { animation-delay: 0.1s; }
        .step:nth-child(2) { animation-delay: 0.2s; }
        .step:nth-child(3) { animation-delay: 0.3s; }

        @keyframes slideRight {
            from {
                opacity: 0;
                transform: translateX(-50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        @keyframes slideLeft {
            from {
                opacity: 0;
                transform: translateX(50px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }

        .step-number {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            background: linear-gradient(135deg, #ff4757, #ffa502);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            font-weight: bold;
            flex-shrink: 0;
        }

        .step-content {
            flex: 1;
        }

        .step-content h3 {
            font-size: 28px;
            margin-bottom: 10px;
        }

        .step-content p {
            color: #9ca3af;
            font-size: 18px;
        }

        /* Prizes Section */
        .prizes {
            padding: 100px 20px;
            background: rgba(0, 0, 0, 0.3);
        }

        .prizes-grid {
            max-width: 1000px;
            margin: 0 auto;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 30px;
        }

        .prize-card {
            background: linear-gradient(135deg, rgba(255, 71, 87, 0.1), rgba(255, 165, 2, 0.1));
            padding: 40px;
            border-radius: 20px;
            text-align: center;
            border: 2px solid transparent;
            transition: all 0.3s;
        }

        .prize-card:hover {
            border-color: #ff4757;
            transform: scale(1.05);
        }

        .prize-card.first {
            border-color: #fbbf24;
        }

        .prize-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }

        .prize-amount {
            font-size: 36px;
            font-weight: bold;
            color: #ff4757;
            margin-bottom: 10px;
        }

        .prize-rank {
            color: #9ca3af;
            font-size: 18px;
        }

        /* CTA Section */
        .cta {
            padding: 100px 20px;
            text-align: center;
        }

        .cta h2 {
            font-size: 48px;
            margin-bottom: 30px;
        }

        /* Footer */
        footer {
            background: rgba(0, 0, 0, 0.5);
            padding: 40px 20px;
            text-align: center;
            border-top: 1px solid rgba(255, 71, 87, 0.2);
        }

        footer p {
            color: #9ca3af;
        }

        /* Mobile Menu */
        .mobile-menu-btn {
            display: none;
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .hero h1 {
                font-size: 42px;
            }

            .hero p {
                font-size: 18px;
            }

            .section-title {
                font-size: 36px;
            }

            .nav-links {
                display: none;
            }

            .mobile-menu-btn {
                display: block;
            }

            .step {
                flex-direction: column !important;
                text-align: center;
            }

            .hero-buttons {
                flex-direction: column;
                align-items: stretch;
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

    <!-- Navigation -->
    <nav>
        <div class="nav-container">
            <div class="logo">
                🎲 LudoTitans
            </div>
            <div class="nav-links">
                <a href="#features">Features</a>
                <a href="#how-it-works">How It Works</a>
                <a href="#prizes">Prizes</a>
                <a href="login.php" class="btn-primary">Login</a>
            </div>
            <button class="mobile-menu-btn">☰</button>
        </div>
    </nav>

    <!-- Hero Section -->
    <section class="hero">
        <div class="hero-content">
            <h1>Play Ludo, Win Real Money</h1>
            <p>Join thousands of players competing for cash prizes every week. Play your favorite board game and earn while having fun!</p>
            <div class="hero-buttons">
                <a href="login.php" class="btn-primary">Start Playing Now</a>
                <a href="#how-it-works" class="btn-secondary">Learn More</a>
            </div>
        </div>
    </section>

    <!-- Features Section -->
    <section class="features" id="features">
        <h2 class="section-title">Why Choose LudoTitans?</h2>
        <div class="features-grid">
            <div class="feature-card">
                <div class="feature-icon">💰</div>
                <h3>Real Money Rewards</h3>
                <p>Win actual cash prizes that you can withdraw directly to your bank account. No gimmicks, just real earnings.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">⚡</div>
                <h3>Instant Withdrawals</h3>
                <p>Cash out your winnings anytime via Paystack. Fast, secure, and hassle-free payment processing.</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🏆</div>
                <h3>Weekly Tournaments</h3>
                <p>Compete in weekly leaderboards for huge prizes. Top 3 players win ₦10,000, ₦5,000, and ₦3,000!</p>
            </div>
            <div class="feature-card">
                <div class="feature-icon">🎮</div>
                <h3>Fair & Fun Gameplay</h3>
                <p>Play classic Ludo with modern features. Fair matchmaking ensures everyone has a chance to win.</p>
            </div>
        </div>
    </section>

    <!-- How It Works -->
    <section class="how-it-works" id="how-it-works">
        <h2 class="section-title">How It Works</h2>
        <div class="steps">
            <div class="step">
                <div class="step-number">1</div>
                <div class="step-content">
                    <h3>Create Your Account</h3>
                    <p>Sign up in seconds with just your email. No complex verification needed to get started.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">2</div>
                <div class="step-content">
                    <h3>Add Coins</h3>
                    <p>Deposit coins using Paystack. Start from as low as ₦100. Your coins are stored securely.</p>
                </div>
            </div>
            <div class="step">
                <div class="step-number">3</div>
                <div class="step-content">
                    <h3>Play & Win</h3>
                    <p>Challenge players, win matches, and earn winning coins. Climb the leaderboard for bigger prizes!</p>
                </div>
            </div>
        </div>
    </section>

    <!-- Prizes Section -->
    <section class="prizes" id="prizes">
        <h2 class="section-title">Weekly Cash Prizes</h2>
        <div class="prizes-grid">
            <div class="prize-card first">
                <div class="prize-icon">🥇</div>
                <div class="prize-amount">₦10,000</div>
                <div class="prize-rank">1st Place</div>
            </div>
            <div class="prize-card">
                <div class="prize-icon">🥈</div>
                <div class="prize-amount">₦5,000</div>
                <div class="prize-rank">2nd Place</div>
            </div>
            <div class="prize-card">
                <div class="prize-icon">🥉</div>
                <div class="prize-amount">₦3,000</div>
                <div class="prize-rank">3rd Place</div>
            </div>
        </div>
    </section>

    <!-- CTA Section -->
    <section class="cta">
        <h2>Ready to Become a LudoTitan?</h2>
        <p style="color: #9ca3af; font-size: 20px; margin-bottom: 30px;">Join now and get 5 free lives to start playing!</p>
        <a href="login.php" class="btn-primary" style="font-size: 20px; padding: 15px 50px;">Get Started Free</a>
    </section>

    <!-- Footer -->
    <footer>
        <p>&copy; 2026 LudoTitans. All rights reserved.</p>
        <p style="margin-top: 10px; font-size: 14px;">Play responsibly. Must be 18+ to participate.</p>
    </footer>

    <script>
        // Smooth scrolling for anchor links
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function (e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({
                        behavior: 'smooth',
                        block: 'start'
                    });
                }
            });
        });
    </script>
</body>
</html>