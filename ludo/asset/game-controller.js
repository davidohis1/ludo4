import { BASE_POSITIONS, HOME_ENTRANCE, HOME_POSITIONS, PLAYERS, SAFE_POSITIONS, START_POSITIONS, STATE, TURNING_POINTS } from './constants.js';
import { UI } from './UI.js';

// Firebase configuration
const firebaseConfig = {
    apiKey: "AIzaSyBpmiHaduU-jPR2zBlFiS3uZAByWy5IiiE",
    projectId: "champ-7b072",
    databaseURL: "https://champ-7b072-default-rtdb.firebaseio.com",
    storageBucket: "champ-7b072.appspot.com"
};

if (!firebase.apps.length) {
    firebase.initializeApp(firebaseConfig);
}

const db = firebase.firestore();
const auth = firebase.auth();

// Dice animation
const dice = document.querySelector('.dice');

const rollDice = (random) => {
    dice.style.animation = 'rolling 1s';
    setTimeout(() => {
        switch (random) {
            case 1: dice.style.transform = 'rotateX(0deg) rotateY(0deg)'; break;
            case 2: dice.style.transform = 'rotateX(-90deg) rotateY(0deg)'; break;
            case 3: dice.style.transform = 'rotateX(0deg) rotateY(90deg)'; break;
            case 4: dice.style.transform = 'rotateX(0deg) rotateY(-90deg)'; break;
            case 5: dice.style.transform = 'rotateX(90deg) rotateY(0deg)'; break;
            case 6: dice.style.transform = 'rotateX(180deg) rotateY(0deg)'; break;
        }
        dice.style.animation = 'none';
    }, 1050);
};

const randomDice = () => {
    const random = Math.floor(Math.random() * 6) + 1;
    rollDice(random);
    return random;
};

class GameController {
    constructor() {
        this.gameId = new URLSearchParams(window.location.search).get('gameId');
        this.myUserId = null;
        this.myPlayerId = null; // P1, P2, P3, or P4
        this.gameData = null;
        this.gameListener = null;
        this.currentPositions = {};
        this.pieceSteps = {};
        this.playerPoints = {};
        this.state = STATE.DICE_NOT_ROLLED;
        this.diceValue = null;
        this.turnIndex = 0;
        this.gameTimer = null;
        this.turnTimer = null;
        this.gameTimeRemaining = 420;
        this.turnTimeRemaining = 10;
        this.playerUserIds = {}; // Maps P1->userId, P2->userId, etc
        
        this.initialize();
    }

    async initialize() {
    try {
        // Show loading
        this.showLoadingMessage('Loading game...');
        
        // ✅ WAIT FOR AUTH TO INITIALIZE
        const user = await new Promise((resolve) => {
            const unsubscribe = auth.onAuthStateChanged((user) => {
                unsubscribe();
                resolve(user);
            });
        });
        
        if (!user) {
            this.hideLoadingMessage();
            alert('Please log in first');
            window.location.href = 'login.php';
            return;
        }
        
        this.myUserId = user.uid;
        console.log('✅ Authenticated as:', this.myUserId);
        
        if (!this.gameId) {
            this.hideLoadingMessage();
            alert('No game ID provided');
            window.location.href = 'tier-selection.html';
            return;
        }

        // Load game data
        await this.loadGame();
        
        this.hideLoadingMessage();
        
        // Listen to game updates
        this.listenToGame();
        
        // Setup UI listeners
        this.setupUIListeners();
        
        // Initialize game state
        this.resetGameState();
        
        console.log('✅ Game initialized');
        
    } catch (error) {
        console.error('❌ Error initializing game:', error);
        this.hideLoadingMessage();
        alert('Failed to initialize game: ' + error.message);
    }
}

// Add these helper methods to GameController class
showLoadingMessage(message) {
    const loadingDiv = document.createElement('div');
    loadingDiv.id = 'game-loading-overlay';
    loadingDiv.style.cssText = `
        position: fixed;
        top: 0;
        left: 0;
        width: 100%;
        height: 100%;
        background: rgba(0, 0, 0, 0.9);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 9999;
    `;
    loadingDiv.innerHTML = `
        <div style="text-align: center; color: white;">
            <div style="width: 60px; height: 60px; border: 5px solid rgba(255,255,255,0.2); border-top-color: white; border-radius: 50%; animation: spin 1s linear infinite; margin: 0 auto 20px;"></div>
            <p style="font-size: 18px; font-family: 'Poppins', sans-serif;">${message}</p>
        </div>
    `;
    document.body.appendChild(loadingDiv);
}

hideLoadingMessage() {
    const loadingDiv = document.getElementById('game-loading-overlay');
    if (loadingDiv) {
        loadingDiv.remove();
    }
}                                                                                                                                                            

    async loadGame() {
        const gameDoc = await db.collection('games').doc(this.gameId).get();
        
        if (!gameDoc.exists) {
            throw new Error('Game not found');
        }
        
        this.gameData = gameDoc.data();
        
        // Find my player ID (P1, P2, P3, or P4)
        const playerIds = this.gameData.playerIds || [];
        const myIndex = playerIds.indexOf(this.myUserId);
        
        if (myIndex === -1) {
            throw new Error('You are not in this game');
        }
        
        this.myPlayerId = `P${myIndex + 1}`;
        
        // Map user IDs to player IDs
        playerIds.forEach((userId, index) => {
            this.playerUserIds[`P${index + 1}`] = userId;
        });
        
        console.log(`✅ You are ${this.myPlayerId}`);
        
        // Show player info
        const colorName = this.getPlayerColorName(this.myPlayerId);
        UI.showPlayerInfo(this.myPlayerId, colorName);
    }

    listenToGame() {
        this.gameListener = db.collection('games').doc(this.gameId).onSnapshot((doc) => {
            if (!doc.exists) {
                alert('Game has been deleted');
                window.location.href = 'tier-selection.html';
                return;
            }
            
            const newData = doc.data();
            this.handleGameUpdate(newData);
        });
    }

    handleGameUpdate(newData) {
    const oldData = this.gameData;
    this.gameData = newData;
    
    // Check if game started
    if (newData.status === 1 && (!oldData || oldData.status === 0)) {
        this.onGameStart();
    }
    
    // Check if game ended
    if (newData.status === 2 && (!oldData || oldData.status !== 2)) {
        this.onGameEnd();
    }
    
    // Check if turn changed
    if (newData.currentPlayerId && (!oldData || oldData.currentPlayerId !== newData.currentPlayerId)) {
        const playerIds = newData.playerIds || [];
        this.turnIndex = playerIds.indexOf(newData.currentPlayerId);
        this.updateTurnDisplay();
        
        // Restart turn timer when turn changes
        this.resetTurnTimer();
    }
    
    // Update token positions
    if (newData.tokenPositions) {
        this.updateTokenPositions(newData.tokenPositions);
    }
    
    // Update scores
    if (newData.playerPoints) {
        this.playerPoints = newData.playerPoints;
        UI.updateScoreboard(this.playerPoints);
    }
}


    onGameStart() {
        console.log('🎮 Game started!');
        UI.showMessage('Game Started!', 'success');
        setTimeout(() => UI.hideMessage(), 2000);
        
        this.startGameTimer();
        this.startTurnTimer();
        this.updateTurnDisplay();
    }

    async onGameEnd() {
        console.log('🏁 Game ended!');
        this.stopAllTimers();
        
        // Award coins to winner
        await this.awardCoinsToWinner();
        
        // Show game over screen
        const sortedPoints = Object.entries(this.playerPoints).sort((a, b) => b[1] - a[1]);
        const winnerPlayerId = sortedPoints[0][0];
        
        UI.showGameOver(winnerPlayerId, this.playerPoints);
    }

    async awardCoinsToWinner() {
        try {
            // Find winner
            const sortedPoints = Object.entries(this.playerPoints).sort((a, b) => b[1] - a[1]);
            const winnerPlayerId = sortedPoints[0][0];
            const winnerUserId = this.playerUserIds[winnerPlayerId];
            
            // Calculate winnings
            const entryFee = this.gameData.entryFee || 0;
            const prizePool = this.gameData.prizePool || (entryFee * 4);
            
            console.log(`💰 Awarding ${prizePool} coins to winner: ${winnerPlayerId} (${winnerUserId})`);
            
            // Update winner's coins in Firebase
            await db.collection('users').doc(winnerUserId).update({
                winningCoins: firebase.firestore.FieldValue.increment(prizePool),
                totalCoins: firebase.firestore.FieldValue.increment(prizePool),
                weeklyWinnings: firebase.firestore.FieldValue.increment(prizePool)
            });
            
            // Add transaction
            await db.collection('users').doc(winnerUserId).collection('transactions').add({
                type: 'win',
                amount: prizePool,
                description: `Won ${prizePool} coins in ${this.gameData.tier} tier`,
                timestamp: firebase.firestore.FieldValue.serverTimestamp()
            });
            
            console.log('✅ Coins awarded successfully');
            
        } catch (error) {
            console.error('❌ Error awarding coins:', error);
        }
    }

    setupUIListeners() {
        UI.listenDiceClick(this.onDiceClick.bind(this));
        UI.listenPieceClick(this.onPieceClick.bind(this));
    }

    resetGameState() {
        this.currentPositions = structuredClone(BASE_POSITIONS);
        this.pieceSteps = {
            P1: [0, 0, 0, 0],
            P2: [0, 0, 0, 0],
            P3: [0, 0, 0, 0],
            P4: [0, 0, 0, 0]
        };
        this.playerPoints = {
            P1: 0,
            P2: 0,
            P3: 0,
            P4: 0
        };
        
        PLAYERS.forEach(player => {
            [0, 1, 2, 3].forEach(piece => {
                this.setPiecePosition(player, piece, this.currentPositions[player][piece]);
            });
        });
        
        UI.updateScoreboard(this.playerPoints);
    }

    async onDiceClick() {
        if (this.gameData.status !== 1) {
            UI.showMessage('Game not started yet', 'warning');
            setTimeout(() => UI.hideMessage(), 2000);
            return;
        }

        const currentPlayerUserId = this.gameData.currentPlayerId;
        if (currentPlayerUserId !== this.myUserId) {
            UI.showMessage('Not your turn!', 'warning');
            setTimeout(() => UI.hideMessage(), 2000);
            return;
        }

        console.log('🎲 Rolling dice...');
        const diceRoll = randomDice();
        this.diceValue = diceRoll;
        this.state = STATE.DICE_ROLLED;

        // Add points for dice roll
        this.playerPoints[this.myPlayerId] = (this.playerPoints[this.myPlayerId] || 0) + diceRoll;
        UI.updateScoreboard(this.playerPoints);

        // Update Firebase
        await db.collection('games').doc(this.gameId).update({
            lastDiceRoll: diceRoll,
            lastDiceRollBy: this.myUserId,
            lastDiceRollAt: firebase.firestore.FieldValue.serverTimestamp(),
            playerPoints: this.playerPoints
        });

        this.checkForEligiblePieces();
    }

    checkForEligiblePieces() {
        const player = this.myPlayerId;
        const eligiblePieces = this.getEligiblePieces(player);
        
        if (eligiblePieces.length) {
            UI.highlightPieces(player, eligiblePieces);
        } else {
            // No eligible pieces, skip turn
            setTimeout(() => {
                this.incrementTurn();
            }, 1000);
        }
    }

    getEligiblePieces(player) {
        return [0, 1, 2, 3].filter(piece => {
            const currentPosition = this.currentPositions[player][piece];

            if (currentPosition === HOME_POSITIONS[player]) {
                return false;
            }

            if (HOME_ENTRANCE[player].includes(currentPosition) &&
                this.diceValue > HOME_POSITIONS[player] - currentPosition) {
                return false;
            }

            return true;
        });
    }

    async incrementTurn() {
    const playerIds = this.gameData.playerIds || [];
    const playerStatuses = this.gameData.playerStatuses || {};
    const currentIndex = playerIds.indexOf(this.gameData.currentPlayerId);
    
    // Find next active player
    let nextIndex = (currentIndex + 1) % playerIds.length;
    let attempts = 0;
    
    while (attempts < playerIds.length) {
        const nextUserId = playerIds[nextIndex];
        
        // Check if player is still connected (not disconnected)
        if (playerStatuses[nextUserId] !== 'disconnected') {
            break;
        }
        
        nextIndex = (nextIndex + 1) % playerIds.length;
        attempts++;
    }
    
    // If all players are disconnected, end game
    if (attempts >= playerIds.length) {
        console.log('All players disconnected');
        return;
    }
    
    const nextPlayerId = playerIds[nextIndex];
    
    // Update Firebase
    await db.collection('games').doc(this.gameId).update({
        currentPlayerId: nextPlayerId,
        playerPoints: this.playerPoints,
        lastTurnChange: firebase.firestore.FieldValue.serverTimestamp()
    });
    
    this.state = STATE.DICE_NOT_ROLLED;
}

    async onPieceClick(event) {
        const target = event.target;

        if (!target.classList.contains('player-piece') || !target.classList.contains('highlight')) {
            return;
        }

        const player = target.getAttribute('player-id');
        if (player !== this.myPlayerId) {
            UI.showMessage('Not your piece!', 'warning');
            setTimeout(() => UI.hideMessage(), 2000);
            return;
        }

        const piece = parseInt(target.getAttribute('piece'));
        await this.handlePieceClick(player, piece);
    }

    async handlePieceClick(player, piece) {
        const currentPosition = this.currentPositions[player][piece];

        if (BASE_POSITIONS[player].includes(currentPosition)) {
            await this.movePieceToStart(player, piece);
        } else {
            UI.unhighlightPieces();
            await this.movePiece(player, piece, this.diceValue);
        }
    }

    async movePieceToStart(player, piece) {
        this.setPiecePosition(player, piece, START_POSITIONS[player]);
        this.pieceSteps[player][piece] = 1;
        
        // Update Firebase
        const tokenKey = `${player}_${piece}`;
        await db.collection('games').doc(this.gameId).update({
            [`tokenPositions.${tokenKey}`]: START_POSITIONS[player],
            pieceSteps: this.pieceSteps
        });
        
        if (this.diceValue === 6) {
            this.state = STATE.DICE_NOT_ROLLED;
            this.resetTurnTimer();
        } else {
            setTimeout(() => {
                this.incrementTurn();
            }, 500);
        }
    }

    async movePiece(player, piece, moveBy) {
        const moves = [];
        
        for (let i = 0; i < moveBy; i++) {
            this.incrementPiecePosition(player, piece);
            this.pieceSteps[player][piece]++;
            moves.push(this.currentPositions[player][piece]);
            await new Promise(resolve => setTimeout(resolve, 200));
        }
        
        // Update Firebase with final position
        const tokenKey = `${player}_${piece}`;
        await db.collection('games').doc(this.gameId).update({
            [`tokenPositions.${tokenKey}`]: this.currentPositions[player][piece],
            pieceSteps: this.pieceSteps
        });
        
        // Check if player won
        if (this.hasPlayerWon(player)) {
            await this.handlePlayerWon(player);
            return;
        }
        
        const isKill = await this.checkForKill(player, piece);
        const reachedHome = this.currentPositions[player][piece] === HOME_POSITIONS[player];
        
        if (reachedHome) {
            this.playerPoints[player] = (this.playerPoints[player] || 0) + 10;
            UI.updateScoreboard(this.playerPoints);
            await db.collection('games').doc(this.gameId).update({
                playerPoints: this.playerPoints
            });
        }
        
        if (isKill || this.diceValue === 6) {
            this.state = STATE.DICE_NOT_ROLLED;
            this.resetTurnTimer();
        } else {
            setTimeout(() => {
                this.incrementTurn();
            }, 500);
        }
    }

    async handlePlayerWon(player) {
        console.log(`🏆 ${player} won!`);
        
        // Update game status to completed
        await db.collection('games').doc(this.gameId).update({
            status: 2, // completed
            winnerId: this.playerUserIds[player],
            completedAt: firebase.firestore.FieldValue.serverTimestamp(),
            playerPoints: this.playerPoints
        });
    }

    async checkForKill(player, piece) {
        const currentPosition = this.currentPositions[player][piece];
        let kill = false;

        for (const opponent of PLAYERS) {
            if (opponent !== player) {
                for (let opponentPiece = 0; opponentPiece < 4; opponentPiece++) {
                    const opponentPosition = this.currentPositions[opponent][opponentPiece];

                    if (currentPosition === opponentPosition && !SAFE_POSITIONS.includes(currentPosition)) {
                        const stepsLost = this.pieceSteps[opponent][opponentPiece];
                        
                        this.playerPoints[opponent] = (this.playerPoints[opponent] || 0) - stepsLost;
                        this.playerPoints[player] = (this.playerPoints[player] || 0) + 10;
                        this.pieceSteps[opponent][opponentPiece] = 0;
                        
                        this.setPiecePosition(opponent, opponentPiece, BASE_POSITIONS[opponent][opponentPiece]);
                        
                        // Update Firebase
                        const opponentTokenKey = `${opponent}_${opponentPiece}`;
                        await db.collection('games').doc(this.gameId).update({
                            [`tokenPositions.${opponentTokenKey}`]: BASE_POSITIONS[opponent][opponentPiece],
                            pieceSteps: this.pieceSteps,
                            playerPoints: this.playerPoints
                        });
                        
                        UI.updateScoreboard(this.playerPoints);
                        kill = true;
                    }
                }
            }
        }

        return kill;
    }

    hasPlayerWon(player) {
        return [0, 1, 2, 3].every(piece => this.currentPositions[player][piece] === HOME_POSITIONS[player]);
    }

    incrementPiecePosition(player, piece) {
        this.setPiecePosition(player, piece, this.getIncrementedPosition(player, piece));
    }

    getIncrementedPosition(player, piece) {
        const currentPosition = this.currentPositions[player][piece];

        if (currentPosition === TURNING_POINTS[player]) {
            return HOME_ENTRANCE[player][0];
        } else if (currentPosition === 51) {
            return 0;
        }
        return currentPosition + 1;
    }

    setPiecePosition(player, piece, newPosition) {
        this.currentPositions[player][piece] = newPosition;
        UI.setPiecePosition(player, piece, newPosition);
    }

    updateTokenPositions(tokenPositions) {
        Object.entries(tokenPositions).forEach(([key, position]) => {
            const [player, piece] = key.split('_');
            if (player && piece !== undefined) {
                this.currentPositions[player][piece] = position;
                UI.setPiecePosition(player, parseInt(piece), position);
            }
        });
    }

    updateTurnDisplay() {
        const currentPlayerUserId = this.gameData.currentPlayerId;
        const playerIds = this.gameData.playerIds || [];
        const playerIndex = playerIds.indexOf(currentPlayerUserId);
        const currentPlayerId = `P${playerIndex + 1}`;
        const colorName = this.getPlayerColorName(currentPlayerId);
        
        if (currentPlayerUserId === this.myUserId) {
            UI.showTurnMessage(`🎲 YOUR TURN! (${colorName})`, 'your-turn');
        } else {
            UI.showTurnMessage(`${currentPlayerId}'s turn (${colorName})`, 'other-turn');
        }
    }

    getPlayerColorName(playerId) {
        const colors = {
            P1: 'Blue',
            P2: 'Red',
            P3: 'Green',
            P4: 'Yellow'
        };
        return colors[playerId] || 'Unknown';
    }

    startGameTimer() {
        this.gameTimeRemaining = 420;
        UI.updateGameTimer(this.gameTimeRemaining);
        
        this.gameTimer = setInterval(() => {
            this.gameTimeRemaining--;
            UI.updateGameTimer(this.gameTimeRemaining);
            
            if (this.gameTimeRemaining <= 0) {
                this.handleGameTimeout();
            }
        }, 1000);
    }

    startTurnTimer() {
    // Clear existing timer
    if (this.turnTimer) {
        clearInterval(this.turnTimer);
        this.turnTimer = null;
    }
    
    // Only show timer if it's your turn
    if (this.gameData.currentPlayerId !== this.myUserId) {
        const turnTimerBox = document.getElementById('turn-timer');
        if (turnTimerBox) {
            turnTimerBox.style.display = 'none';
        }
        return;
    }
    
    // Show timer for current player
    const turnTimerBox = document.getElementById('turn-timer');
    if (turnTimerBox) {
        turnTimerBox.style.display = 'block';
    }
    
    this.turnTimeRemaining = 10;
    UI.updateTurnTimer(this.turnTimeRemaining);
    
    this.turnTimer = setInterval(() => {
        this.turnTimeRemaining--;
        UI.updateTurnTimer(this.turnTimeRemaining);
        
        if (this.turnTimeRemaining <= 0) {
            clearInterval(this.turnTimer);
            this.turnTimer = null;
            this.handleTurnTimeout();
        }
    }, 1000);
}

    resetTurnTimer() {
    if (this.turnTimer) {
        clearInterval(this.turnTimer);
        this.turnTimer = null;
    }
    this.startTurnTimer();
}

    stopAllTimers() {
    if (this.gameTimer) {
        clearInterval(this.gameTimer);
        this.gameTimer = null;
    }
    if (this.turnTimer) {
        clearInterval(this.turnTimer);
        this.turnTimer = null;
    }
    
    // Hide timers
    const turnTimerBox = document.getElementById('turn-timer');
    if (turnTimerBox) {
        turnTimerBox.style.display = 'none';
    }
}

    async handleGameTimeout() {
        console.log('⏰ Game timeout!');
        this.stopAllTimers();
        
        // Find winner
        const sortedPoints = Object.entries(this.playerPoints).sort((a, b) => b[1] - a[1]);
        const winnerPlayerId = sortedPoints[0][0];
        
        // Update game status
        await db.collection('games').doc(this.gameId).update({
            status: 2,
            winnerId: this.playerUserIds[winnerPlayerId],
            completedAt: firebase.firestore.FieldValue.serverTimestamp(),
            playerPoints: this.playerPoints,
            endReason: 'timeout'
        });
    }

    async handleTurnTimeout() {
    // Only current player handles timeout
    if (this.gameData.currentPlayerId === this.myUserId) {
        console.log('⏰ Turn timeout!');
        
        UI.showMessage('Time up! Turn skipped', 'warning');
        setTimeout(() => UI.hideMessage(), 2000);
        
        // Move to next turn
        await this.incrementTurn();
    }
}
}

// Initialize game when page loads
window.addEventListener('load', () => {
    new GameController();
});