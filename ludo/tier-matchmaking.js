// Firebase configuration
const firebaseConfig = {
    apiKey: "AIzaSyBpmiHaduU-jPR2zBlFiS3uZAByWy5IiiE",
    projectId: "champ-7b072",
    databaseURL: "https://champ-7b072-default-rtdb.firebaseio.com",
    storageBucket: "champ-7b072.appspot.com"
};

// Initialize Firebase
if (!firebase.apps.length) {
    firebase.initializeApp(firebaseConfig);
}

const db = firebase.firestore();
const rtdb = firebase.database();
const auth = firebase.auth();

let currentUser = null;
let currentGameId = null;
let currentTier = null;
let gameListener = null;
let countdownInterval = null;

// DOM Elements
const overlay = document.getElementById('matchmaking-overlay');
const cancelBtn = document.getElementById('cancel-btn');
const playersCountEl = document.getElementById('players-count');
const countdownTextEl = document.getElementById('countdown-text');
const countdownEl = document.getElementById('countdown');

// Initialize - Sign in anonymously
async function initializeUser() {
    try {
        // Sign in anonymously
        const userCredential = await auth.signInAnonymously();
        const user = userCredential.user;
        
        console.log('✅ Signed in with UID:', user.uid);
        
        // Check if user exists in Firestore
        const userDoc = await db.collection('users').doc(user.uid).get();
        
        if (!userDoc.exists) {
            // Create new user
            currentUser = {
                id: user.uid,
                displayName: 'Player ' + Math.floor(Math.random() * 1000),
                email: user.email || `${user.uid}@anonymous.com`,
                photoUrl: '',
                coins: 10000,
                totalCoins: 10000,
                depositCoins: 10000,
                winningCoins: 0,
                rating: 1000,
                lives: 5
            };
            
            await db.collection('users').doc(user.uid).set(currentUser);
            console.log('✅ User created');
        } else {
            currentUser = userDoc.data();
            console.log('✅ User loaded:', currentUser.displayName);
        }
        
        // Enable tier selection
        enableTierSelection();
        
    } catch (error) {
        console.error('❌ Error initializing user:', error);
        alert('Failed to initialize. Please refresh the page.');
    }
}

// Enable tier selection after user is ready
function enableTierSelection() {
    document.querySelectorAll('.tier-card').forEach(card => {
        const joinBtn = card.querySelector('.join-btn');
        
        joinBtn.addEventListener('click', async () => {
            const tier = card.dataset.tier;
            const entryFee = parseInt(card.dataset.entry);
            const prizePool = parseInt(card.dataset.prize);
            
            // Check if user has enough coins
            if (currentUser.totalCoins < entryFee) {
                alert(`Insufficient coins! You need ${entryFee} coins to join this tier.`);
                return;
            }
            
            await joinGame(tier, entryFee, prizePool);
        });
    });
}

// Initialize on page load
initializeUser();

// Cancel button handler
cancelBtn.addEventListener('click', async () => {
    await leaveGame();
});

/**
 * Join or create a game
 */
async function joinGame(tier, entryFee, prizePool) {
    try {
        console.log(`🎮 Joining ${tier} tier...`);
        
        if (!currentUser) {
            alert('Please wait, initializing...');
            return;
        }
        
        currentTier = tier;
        showOverlay();
        
        // Look for available games in this tier
        const availableGames = await db.collection('games')
            .where('tier', '==', tier)
            .where('status', '==', 0) // GameStatus.waiting
            .orderBy('createdAt', 'desc')
            .limit(5)
            .get();
        
        // Find a game that's not full and was created in last 30 mins
        let joinableGame = null;
        const thirtyMinsAgo = new Date(Date.now() - 30 * 60 * 1000);
        
        for (const doc of availableGames.docs) {
            const data = doc.data();
            const createdAt = data.createdAt?.toDate();
            
            if (data.playerIds.length < 4 && createdAt > thirtyMinsAgo) {
                joinableGame = { id: doc.id, data: data };
                break;
            }
        }
        
        if (joinableGame) {
            // Join existing game
            currentGameId = joinableGame.id;
            const gameData = joinableGame.data;
            
            console.log(`✅ Joining existing game: ${currentGameId}`);
            
            // Determine next color index
            const colorIndex = gameData.playerIds.length;
            
            // Add player to game
            await db.collection('games').doc(currentGameId).update({
                playerIds: firebase.firestore.FieldValue.arrayUnion(currentUser.id),
                [`playerNames.${currentUser.id}`]: currentUser.displayName,
                [`playerPhotos.${currentUser.id}`]: currentUser.photoUrl || '',
                [`playerColors.${currentUser.id}`]: colorIndex,
                [`playerCoins.${currentUser.id}`]: entryFee
            });
            
        } else {
            // Create new game
            console.log(`🆕 Creating new game...`);
            
            const newGame = {
                tier: tier,
                entryFee: entryFee,
                prizePool: prizePool,
                playerIds: [currentUser.id],
                playerNames: { [currentUser.id]: currentUser.displayName },
                playerPhotos: { [currentUser.id]: currentUser.photoUrl || '' },
                playerColors: { [currentUser.id]: 0 }, // P1 = Blue
                playerCoins: { [currentUser.id]: entryFee },
                status: 0, // waiting
                createdAt: firebase.firestore.FieldValue.serverTimestamp(),
                currentPlayerId: currentUser.id,
                tokenPositions: {}
            };
            
            const docRef = await db.collection('games').add(newGame);
            currentGameId = docRef.id;
            
            // Update with game ID
            await db.collection('games').doc(currentGameId).update({ id: currentGameId });
            
            console.log(`✅ Game created: ${currentGameId}`);
        }
        
        // Listen for game updates
        listenToGame();
        
    } catch (error) {
        console.error('❌ Error joining game:', error);
        alert('Failed to join game: ' + error.message);
        hideOverlay();
    }
}

/**
 * Listen to game updates
 */
function listenToGame() {
    if (gameListener) gameListener();
    
    gameListener = db.collection('games').doc(currentGameId).onSnapshot(async (doc) => {
        if (!doc.exists) {
            console.log('❌ Game no longer exists');
            alert('Game was cancelled by host');
            await leaveGame();
            return;
        }
        
        const gameData = doc.data();
        const playerIds = gameData.playerIds || [];
        
        console.log(`👥 Players: ${playerIds.length}/4`);
        
        // Update players display
        updatePlayersDisplay(gameData);
        
        // Update count
        playersCountEl.textContent = `${playerIds.length}/4 Players`;
        
        // Check if game is full
        if (playerIds.length === 4 && gameData.status === 0) {
            // Game is full, start countdown
            startGameCountdown();
        }
    });
}

/**
 * Update players display in overlay
 */
function updatePlayersDisplay(gameData) {
    const playerIds = gameData.playerIds || [];
    const playerNames = gameData.playerNames || {};
    const playerPhotos = gameData.playerPhotos || {};
    
    // Update each slot
    for (let i = 0; i < 4; i++) {
        const slot = document.getElementById(`slot-${i}`);
        const avatar = slot.querySelector('.player-avatar');
        const name = slot.querySelector('.player-name');
        
        if (i < playerIds.length) {
            // Player joined
            const playerId = playerIds[i];
            const playerName = playerNames[playerId] || 'Player';
            const playerPhoto = playerPhotos[playerId];
            
            avatar.classList.remove('empty');
            avatar.classList.add('filled');
            
            if (playerPhoto) {
                avatar.innerHTML = `<img src="${playerPhoto}" style="width:100%;height:100%;border-radius:50%;object-fit:cover;">`;
            } else {
                avatar.innerHTML = playerId === currentUser.id ? '😊' : '👤';
            }
            
            name.textContent = playerId === currentUser.id ? 'You' : playerName;
        } else {
            // Empty slot
            avatar.classList.add('empty');
            avatar.classList.remove('filled');
            avatar.innerHTML = '<span class="waiting-icon">?</span>';
            name.textContent = 'Waiting...';
        }
    }
}

/**
 * Start game countdown
 */
function startGameCountdown() {
    countdownTextEl.classList.remove('hidden');
    let timeLeft = 10;
    
    countdownEl.textContent = timeLeft;
    
    if (countdownInterval) clearInterval(countdownInterval);
    
    countdownInterval = setInterval(() => {
        timeLeft--;
        countdownEl.textContent = timeLeft;
        
        if (timeLeft <= 0) {
            clearInterval(countdownInterval);
            startGame();
        }
    }, 1000);
}

/**
 * Start the game
 */
/**
 * Start the game
 */
async function startGame() {
    try {
        console.log('🎮 Starting game...');
        
        // Wait a bit for all players to be ready
        await new Promise(resolve => setTimeout(resolve, 1000));
        
        // Get fresh game data
        const gameDoc = await db.collection('games').doc(currentGameId).get();
        
        if (!gameDoc.exists) {
            console.error('Game document does not exist!');
            alert('Game not found. Please try again.');
            hideOverlay();
            return;
        }
        
        const gameData = gameDoc.data();
        const playerIds = gameData.playerIds || [];
        
        // Verify we have 4 players
        if (playerIds.length !== 4) {
            console.error('Not enough players');
            return;
        }
        
        // Only first player updates game status
        if (playerIds[0] === currentUser.id && gameData.status === 0) {
            console.log('🎮 First player initializing game...');
            
            // Initialize complete game state
            await db.collection('games').doc(currentGameId).update({
                status: 1, // inProgress
                startedAt: firebase.firestore.FieldValue.serverTimestamp(),
                currentPlayerId: playerIds[0], // First player starts
                lastTurnChange: firebase.firestore.FieldValue.serverTimestamp(),
                playerPoints: {
                    [playerIds[0]]: 0,
                    [playerIds[1]]: 0,
                    [playerIds[2]]: 0,
                    [playerIds[3]]: 0
                },
                pieceSteps: {
                    P1: [0, 0, 0, 0],
                    P2: [0, 0, 0, 0],
                    P3: [0, 0, 0, 0],
                    P4: [0, 0, 0, 0]
                },
                tokenPositions: {}
            });
            
            console.log('✅ Game initialized by first player');
        }
        
        // All players wait for game to be ready
        let attempts = 0;
        while (attempts < 10) {
            const checkDoc = await db.collection('games').doc(currentGameId).get();
            const checkData = checkDoc.data();
            
            if (checkData.status === 1 && checkData.playerPoints) {
                console.log('✅ Game is ready!');
                break;
            }
            
            console.log(`Waiting for game to be ready... (${attempts + 1}/10)`);
            await new Promise(resolve => setTimeout(resolve, 500));
            attempts++;
        }
        
        // Store in session
        sessionStorage.setItem('currentGameId', currentGameId);
        sessionStorage.setItem('currentPlayerId', currentUser.id);
        
        console.log('🎮 Redirecting to game...');
        
        // Redirect
        window.location.href = `game.html?gameId=${currentGameId}`;
        
    } catch (error) {
        console.error('❌ Error starting game:', error);
        alert('Failed to start game: ' + error.message);
        hideOverlay();
    }
}

/**
 * Leave game
 */
async function leaveGame() {
    try {
        if (!currentGameId) {
            hideOverlay();
            return;
        }
        
        console.log('👋 Leaving game...');
        
        // Stop listeners
        if (gameListener) gameListener();
        if (countdownInterval) clearInterval(countdownInterval);
        
        // Get game data
        const gameDoc = await db.collection('games').doc(currentGameId).get();
        
        if (gameDoc.exists) {
            const gameData = gameDoc.data();
            const playerIds = gameData.playerIds || [];
            
            // If you're the only player or the host, delete the game
            if (playerIds.length === 1 || playerIds[0] === currentUser.id) {
                await db.collection('games').doc(currentGameId).delete();
                console.log('🗑️ Game deleted');
            } 
            // Otherwise, just remove yourself
            else {
                const updatedPlayerIds = playerIds.filter(id => id !== currentUser.id);
                const updatedPlayerNames = {...gameData.playerNames};
                const updatedPlayerPhotos = {...gameData.playerPhotos};
                const updatedPlayerColors = {...gameData.playerColors};
                const updatedPlayerCoins = {...gameData.playerCoins};
                
                delete updatedPlayerNames[currentUser.id];
                delete updatedPlayerPhotos[currentUser.id];
                delete updatedPlayerColors[currentUser.id];
                delete updatedPlayerCoins[currentUser.id];
                
                await db.collection('games').doc(currentGameId).update({
                    playerIds: updatedPlayerIds,
                    playerNames: updatedPlayerNames,
                    playerPhotos: updatedPlayerPhotos,
                    playerColors: updatedPlayerColors,
                    playerCoins: updatedPlayerCoins
                });
                
                console.log('✅ Left game');
            }
        }
        
        currentGameId = null;
        currentTier = null;
        hideOverlay();
        
    } catch (error) {
        console.error('❌ Error leaving game:', error);
        hideOverlay();
    }
}

/**
 * Show overlay
 */
function showOverlay() {
    overlay.classList.remove('hidden');
    // Reset display
    countdownTextEl.classList.add('hidden');
    playersCountEl.textContent = '0/4 Players';
    
    // Reset slots
    for (let i = 0; i < 4; i++) {
        const slot = document.getElementById(`slot-${i}`);
        const avatar = slot.querySelector('.player-avatar');
        const name = slot.querySelector('.player-name');
        
        avatar.classList.add('empty');
        avatar.classList.remove('filled');
        avatar.innerHTML = '<span class="waiting-icon">?</span>';
        name.textContent = 'Waiting...';
    }
}

/**
 * Hide overlay
 */
function hideOverlay() {
    overlay.classList.add('hidden');
    if (countdownInterval) {
        clearInterval(countdownInterval);
        countdownInterval = null;
    }
}

// Cleanup on page unload
window.addEventListener('beforeunload', async () => {
    if (currentGameId) {
        await leaveGame();
    }
});