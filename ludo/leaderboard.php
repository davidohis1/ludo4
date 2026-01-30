<?php
session_start();

// ==================== CONFIGURATION ====================
$FIREBASE_API_KEY = "AIzaSyBpmiHaduU-jPR2zBlFiS3uZAByWy5IiiE";
$FIREBASE_PROJECT_ID = "champ-7b072";

// ==================== FIREBASE FUNCTIONS ====================

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

/**
 * Get all users for leaderboard
 */
function getAllUsers($idToken) {
    global $FIREBASE_PROJECT_ID;
    
    $url = "https://firestore.googleapis.com/v1/projects/{$FIREBASE_PROJECT_ID}/databases/(default)/documents/users";
    
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
    $users = [];
    
    if (isset($data['documents'])) {
        foreach ($data['documents'] as $doc) {
            $fields = $doc['fields'];
            $userId = basename($doc['name']);
            
            $users[] = [
                'id' => $userId,
                'displayName' => $fields['displayName']['stringValue'] ?? 'Player',
                'winningCoins' => intval($fields['winningCoins']['integerValue'] ?? 0),
                'avatarUrl' => $fields['avatarUrl']['stringValue'] ?? ''
            ];
        }
    }
    
    // Sort by winning coins (highest first)
    usort($users, function($a, $b) {
        return $b['winningCoins'] - $a['winningCoins'];
    });
    
    return $users;
}

/**
 * Calculate time until next Monday 00:00:00
 */
function getTimeUntilNextMonday() {
    $now = new DateTime('now', new DateTimeZone('Africa/Lagos'));
    $nextMonday = clone $now;
    
    // If today is Monday, check if we're past midnight
    if ($now->format('N') == 1) {
        // Set to next Monday
        $nextMonday->modify('+1 week');
    } else {
        // Set to upcoming Monday
        $nextMonday->modify('next Monday');
    }
    
    $nextMonday->setTime(0, 0, 0);
    
    $diff = $now->diff($nextMonday);
    
    return [
        'days' => $diff->days,
        'hours' => $diff->h,
        'minutes' => $diff->i,
        'seconds' => $diff->s
    ];
}

// Check if user is logged in
if (!isset($_SESSION['user']) || !isset($_SESSION['idToken'])) {
    header('Location: index.php');
    exit();
}

// Pagination settings
$perPage = 10;
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$offset = ($page - 1) * $perPage;

// Get all users
$allUsers = getAllUsers($_SESSION['idToken']);
$totalUsers = count($allUsers);
$totalPages = ceil($totalUsers / $perPage);

// Get paginated users
$paginatedUsers = array_slice($allUsers, $offset, $perPage);

// Find current user's rank
$currentUserRank = 0;
foreach ($allUsers as $index => $user) {
    if ($user['id'] === $_SESSION['user']['id']) {
        $currentUserRank = $index + 1;
        break;
    }
}

// Get timer
$timer = getTimeUntilNextMonday();

?>
<!DOCTYPE html>
<html>
<head>
    <title>Weekly Leaderboard</title>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        
        body { 
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: #1a0f1f;
            color: white;
            min-height: 100vh;
            padding-bottom: 20px;
        }
        
        .header {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 20px;
            border-bottom: 1px solid rgba(255,255,255,0.1);
        }
        
        .back-btn {
            font-size: 24px;
            color: white;
            text-decoration: none;
            cursor: pointer;
        }
        
        .header h1 {
            flex: 1;
            text-align: center;
            font-size: 22px;
            font-weight: 600;
        }
        
        .weekly-badge {
            color: #ff4757;
            font-size: 16px;
            font-weight: 600;
        }
        
        .timer-section {
            text-align: center;
            padding: 30px 20px;
        }
        
        .timer-label {
            color: #9ca3af;
            margin-bottom: 15px;
            font-size: 16px;
        }
        
        .timer-boxes {
            display: flex;
            justify-content: center;
            gap: 15px;
        }
        
        .timer-box {
            background: rgba(255,255,255,0.08);
            border-radius: 25px;
            padding: 15px 30px;
            min-width: 80px;
        }
        
        .timer-value {
            font-size: 28px;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .timer-unit {
            color: #9ca3af;
            font-size: 14px;
        }
        
        .top-three {
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 20px;
            padding: 30px 20px;
        }
        
        .podium {
            text-align: center;
            position: relative;
        }
        
        .podium.first {
            order: 2;
        }
        
        .podium.second {
            order: 1;
        }
        
        .podium.third {
            order: 3;
        }
        
        .avatar-container {
            position: relative;
            margin-bottom: 10px;
        }
        
        .avatar {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            border: 4px solid transparent;
            background: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 40px;
            color: white;
        }
        
        .podium.first .avatar {
            width: 120px;
            height: 120px;
            border-color: #fbbf24;
            box-shadow: 0 10px 30px rgba(251, 191, 36, 0.3);
        }
        
        .podium.second .avatar {
            border-color: #d1d5db;
        }
        
        .podium.third .avatar {
            border-color: #f97316;
        }
        
        .trophy {
            position: absolute;
            top: -10px;
            right: -10px;
            font-size: 30px;
        }
        
        .podium-name {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .podium-score {
            font-size: 18px;
            font-weight: bold;
            color: #fbbf24;
        }
        
        .tabs {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 20px;
        }
        
        .tab {
            background: transparent;
            border: none;
            color: #9ca3af;
            padding: 10px 30px;
            border-radius: 20px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .tab.active {
            background: #ff4757;
            color: white;
        }
        
        .rankings-list {
            padding: 0 20px;
        }
        
        .rank-item {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 15px 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .rank-item.current-user {
            border: 2px solid #ff4757;
            background: rgba(255, 71, 87, 0.1);
        }
        
        .rank-number {
            font-size: 18px;
            font-weight: bold;
            color: #9ca3af;
            min-width: 30px;
        }
        
        .rank-avatar {
            width: 50px;
            height: 50px;
            border-radius: 50%;
            background: #6b7280;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
        }
        
        .rank-info {
            flex: 1;
        }
        
        .rank-name {
            font-weight: 600;
            margin-bottom: 3px;
        }
        
        .rank-score {
            color: #ff4757;
            font-size: 20px;
            font-weight: bold;
        }
        
        .prizes-section {
            padding: 30px 20px;
        }
        
        .prizes-title {
            text-align: center;
            font-size: 22px;
            font-weight: bold;
            margin-bottom: 20px;
        }
        
        .prize-item {
            background: rgba(255,255,255,0.05);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
            gap: 15px;
        }
        
        .prize-icon {
            font-size: 35px;
        }
        
        .prize-info h3 {
            font-size: 18px;
            margin-bottom: 5px;
        }
        
        .prize-info p {
            color: #9ca3af;
            font-size: 14px;
        }
        
        .pagination {
            display: flex;
            justify-content: center;
            gap: 10px;
            padding: 20px;
            margin-top: 20px;
        }
        
        .page-btn {
            background: rgba(255,255,255,0.08);
            border: none;
            color: white;
            width: 40px;
            height: 40px;
            border-radius: 10px;
            cursor: pointer;
            font-weight: 600;
        }
        
        .page-btn.active {
            background: #ff4757;
        }
        
        .page-btn:disabled {
            opacity: 0.3;
            cursor: not-allowed;
        }
        
        @media (max-width: 600px) {
            .top-three {
                gap: 10px;
                padding: 20px 10px;
            }
            
            .avatar {
                width: 80px;
                height: 80px;
            }
            
            .podium.first .avatar {
                width: 100px;
                height: 100px;
            }
            
            .timer-boxes {
                gap: 10px;
            }
            
            .timer-box {
                padding: 10px 20px;
                min-width: 70px;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="index.php" class="back-btn">‹</a>
        <h1>Leaderboard</h1>
        <div class="weekly-badge">Weekly</div>
    </div>
    
    <div class="timer-section">
        <div class="timer-label">New Season in:</div>
        <div class="timer-boxes">
            <div class="timer-box">
                <div class="timer-value" id="days"><?php echo $timer['days']; ?></div>
                <div class="timer-unit">Days</div>
            </div>
            <div class="timer-box">
                <div class="timer-value" id="hours"><?php echo $timer['hours']; ?></div>
                <div class="timer-unit">Hours</div>
            </div>
            <div class="timer-box">
                <div class="timer-value" id="minutes"><?php echo $timer['minutes']; ?></div>
                <div class="timer-unit">Minutes</div>
            </div>
        </div>
    </div>
    
    <?php if (count($allUsers) >= 3): ?>
    <div class="top-three">
        <!-- Second Place -->
        <div class="podium second">
            <div class="avatar-container">
                <div class="avatar">
                    <?php if ($allUsers[1]['avatarUrl']): ?>
                        <img src="<?php echo htmlspecialchars($allUsers[1]['avatarUrl']); ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                    <?php else: ?>
                        🧑
                    <?php endif; ?>
                </div>
                <div class="trophy">🥈</div>
            </div>
            <div class="podium-name"><?php echo htmlspecialchars($allUsers[1]['displayName']); ?></div>
            <div class="podium-score"><?php echo number_format($allUsers[1]['winningCoins']); ?></div>
        </div>
        
        <!-- First Place -->
        <div class="podium first">
            <div class="avatar-container">
                <div class="avatar">
                    <?php if ($allUsers[0]['avatarUrl']): ?>
                        <img src="<?php echo htmlspecialchars($allUsers[0]['avatarUrl']); ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                    <?php else: ?>
                        👑
                    <?php endif; ?>
                </div>
                <div class="trophy">🏆</div>
            </div>
            <div class="podium-name"><?php echo htmlspecialchars($allUsers[0]['displayName']); ?></div>
            <div class="podium-score"><?php echo number_format($allUsers[0]['winningCoins']); ?></div>
        </div>
        
        <!-- Third Place -->
        <div class="podium third">
            <div class="avatar-container">
                <div class="avatar">
                    <?php if ($allUsers[2]['avatarUrl']): ?>
                        <img src="<?php echo htmlspecialchars($allUsers[2]['avatarUrl']); ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                    <?php else: ?>
                        👤
                    <?php endif; ?>
                </div>
                <div class="trophy">🥉</div>
            </div>
            <div class="podium-name"><?php echo htmlspecialchars($allUsers[2]['displayName']); ?></div>
            <div class="podium-score"><?php echo number_format($allUsers[2]['winningCoins']); ?></div>
        </div>
    </div>
    <?php endif; ?>
    
    <div class="tabs">
        <button class="tab active" onclick="showTab('rankings')">Rankings</button>
        <button class="tab" onclick="showTab('prizes')">Prizes</button>
    </div>
    
    <div id="rankingsTab">
        <div class="rankings-list">
            <?php 
            $startRank = $offset + 1;
            foreach ($paginatedUsers as $index => $user): 
                $rank = $startRank + $index;
                $isCurrentUser = ($user['id'] === $_SESSION['user']['id']);
            ?>
            <div class="rank-item <?php echo $isCurrentUser ? 'current-user' : ''; ?>">
                <div class="rank-number"><?php echo $rank; ?></div>
                <div class="rank-avatar">
                    <?php if ($user['avatarUrl']): ?>
                        <img src="<?php echo htmlspecialchars($user['avatarUrl']); ?>" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">
                    <?php else: ?>
                        <?php echo $isCurrentUser ? '😊' : '👤'; ?>
                    <?php endif; ?>
                </div>
                <div class="rank-info">
                    <div class="rank-name">
                        <?php echo htmlspecialchars($user['displayName']); ?>
                        <?php echo $isCurrentUser ? ' (You)' : ''; ?>
                    </div>
                </div>
                <div class="rank-score"><?php echo number_format($user['winningCoins']); ?></div>
            </div>
            <?php endforeach; ?>
        </div>
        
        <?php if ($totalPages > 1): ?>
        <div class="pagination">
            <button class="page-btn" onclick="changePage(1)" <?php echo $page == 1 ? 'disabled' : ''; ?>>«</button>
            <button class="page-btn" onclick="changePage(<?php echo $page - 1; ?>)" <?php echo $page == 1 ? 'disabled' : ''; ?>>‹</button>
            
            <?php
            $start = max(1, $page - 2);
            $end = min($totalPages, $page + 2);
            for ($i = $start; $i <= $end; $i++):
            ?>
            <button class="page-btn <?php echo $i == $page ? 'active' : ''; ?>" onclick="changePage(<?php echo $i; ?>)"><?php echo $i; ?></button>
            <?php endfor; ?>
            
            <button class="page-btn" onclick="changePage(<?php echo $page + 1; ?>)" <?php echo $page == $totalPages ? 'disabled' : ''; ?>>›</button>
            <button class="page-btn" onclick="changePage(<?php echo $totalPages; ?>)" <?php echo $page == $totalPages ? 'disabled' : ''; ?>>»</button>
        </div>
        <?php endif; ?>
        
        <?php if ($currentUserRank > 0 && $currentUserRank > 10): ?>
        <div class="rankings-list" style="margin-top: 30px;">
            <div class="rank-item current-user">
                <div class="rank-number"><?php echo $currentUserRank; ?></div>
                <div class="rank-avatar">😊</div>
                <div class="rank-info">
                    <div class="rank-name">You</div>
                </div>
                <div class="rank-score"><?php echo number_format($_SESSION['user']['winningCoins']); ?></div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    
    <div id="prizesTab" style="display: none;">
        <div class="prizes-section">
            <h2 class="prizes-title">Weekly Prizes</h2>
            
            <div class="prize-item">
                <div class="prize-icon">🏅</div>
                <div class="prize-info">
                    <h3>Rank 1</h3>
                    <p>₦10,000 Cash Prize</p>
                </div>
            </div>
            
            <div class="prize-item">
                <div class="prize-icon">🥈</div>
                <div class="prize-info">
                    <h3>Rank 2</h3>
                    <p>₦5,000 Cash Prize</p>
                </div>
            </div>
            
            <div class="prize-item">
                <div class="prize-icon">🥉</div>
                <div class="prize-info">
                    <h3>Rank 3</h3>
                    <p>₦3,000 Cash Prize</p>
                </div>
            </div>
            
            <div style="margin-top: 30px; padding: 20px; background: rgba(255,71,87,0.1); border-radius: 15px; text-align: center;">
                <p style="color: #9ca3af; margin-bottom: 10px;">Current Weekly Prize Pool</p>
                <p style="font-size: 32px; font-weight: bold; color: #fbbf24;">₦18,000</p>
                <p style="color: #9ca3af; margin-top: 10px; font-size: 14px;">Prizes are distributed every Monday at midnight</p>
            </div>
        </div>
    </div>
    
    <script>
        function showTab(tab) {
            const tabs = document.querySelectorAll('.tab');
            tabs.forEach(t => t.classList.remove('active'));
            
            if (tab === 'rankings') {
                event.target.classList.add('active');
                document.getElementById('rankingsTab').style.display = 'block';
                document.getElementById('prizesTab').style.display = 'none';
            } else {
                event.target.classList.add('active');
                document.getElementById('rankingsTab').style.display = 'none';
                document.getElementById('prizesTab').style.display = 'block';
            }
        }
        
        function changePage(page) {
            window.location.href = '?page=' + page;
        }
        
        // Update timer every second
        function updateTimer() {
            const days = parseInt(document.getElementById('days').textContent);
            let hours = parseInt(document.getElementById('hours').textContent);
            let minutes = parseInt(document.getElementById('minutes').textContent);
            let seconds = <?php echo $timer['seconds']; ?>;
            
            setInterval(() => {
                seconds--;
                
                if (seconds < 0) {
                    seconds = 59;
                    minutes--;
                    
                    if (minutes < 0) {
                        minutes = 59;
                        hours--;
                        
                        if (hours < 0) {
                            // Reload page when timer expires
                            location.reload();
                        }
                    }
                }
                
                document.getElementById('hours').textContent = hours;
                document.getElementById('minutes').textContent = minutes;
            }, 1000);
        }
        
        updateTimer();
    </script>
</body>
</html>