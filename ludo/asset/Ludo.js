import { BASE_POSITIONS, HOME_ENTRANCE, HOME_POSITIONS, PLAYERS, SAFE_POSITIONS, START_POSITIONS, STATE, TURNING_POINTS } from './constants.js';
import { UI } from './UI.js';

const dice = document.querySelector('.dice');
const rollBtn = document.querySelector('.roll');
const resultDisplay = document.querySelector('.result');

const randomDice = () => {
    const random = Math.floor(Math.random() * 6) + 1;
    rollDice(random);
    return random;
}

const rollDice = (random) => {
    dice.style.animation = 'rolling 1s';

    setTimeout(() => {
        switch (random) {
            case 1:
                dice.style.transform = 'rotateX(0deg) rotateY(0deg)';
                break;
            case 2:
                dice.style.transform = 'rotateX(-90deg) rotateY(0deg)';
                break;
            case 3:
                dice.style.transform = 'rotateX(0deg) rotateY(90deg)';
                break;
            case 4:
                dice.style.transform = 'rotateX(0deg) rotateY(-90deg)';
                break;
            case 5:
                dice.style.transform = 'rotateX(90deg) rotateY(0deg)';
                break;
            case 6:
                dice.style.transform = 'rotateX(180deg) rotateY(0deg)';
                break;
            default:
                break;
        }

        dice.style.animation = 'none';
        resultDisplay.textContent = `Result: ${random}`;
    }, 1050);
}

export class Ludo {
    currentPositions = {
        P1: [],
        P2: [],
        P3: [],
        P4: []
    };

    pieceSteps = {
        P1: [0, 0, 0, 0],
        P2: [0, 0, 0, 0],
        P3: [0, 0, 0, 0],
        P4: [0, 0, 0, 0]
    };

    playerPoints = {
        P1: 0,
        P2: 0,
        P3: 0,
        P4: 0
    };

    _diceValue;
    get diceValue() {
        return this._diceValue;
    }
    set diceValue(value) {
        this._diceValue = value;
        UI.setDiceValue(value);
    }

    _turn;
    get turn() {
        return this._turn;
    }
    set turn(value) {
        this._turn = value;
        UI.setTurn(value);
    }

    _state;
    get state() {
        return this._state;
    }
    set state(value) {
        this._state = value;

        if (value === STATE.DICE_NOT_ROLLED) {
            UI.enableDice();
            UI.unhighlightPieces();
        } else {
            UI.disableDice();
        }
    }

    // Multiplayer properties
    ws = null;
    myPlayerId = null;
    myPlayerNumber = null;
    roomId = null;
    gameStarted = false;
    TURN_ORDER = [0, 1, 2, 3]; // P1=Blue, P2=Red, P3=Green, P4=Yellow
    turnIndex = -1;
    
    // Timer properties
    gameTimer = null;
    gameTimeRemaining = 420; // 7 minutes in seconds
    turnTimer = null;
    turnTimeRemaining = 10; // 10 seconds per turn

    constructor() {
    console.log('Hello World! Lets play Ludo!');
    
    this.turnIndex = -1;
    this.listenRollButtonClick();  // Make sure this line exists
    this.listenResetClick();
    this.listenPieceClick();
    this.resetGame();
    
    this.initWebSocket();
}

    initWebSocket() {
        this.ws = new WebSocket('ws://localhost:8080');

        this.ws.onopen = () => {
            console.log('Connected to game server');
            UI.showMessage('Connecting to game...', 'info');
            
            this.ws.send(JSON.stringify({
                type: 'JOIN_GAME'
            }));
        };

        this.ws.onmessage = (event) => {
            const data = JSON.parse(event.data);
            this.handleServerMessage(data);
        };

        this.ws.onclose = () => {
            console.log('Disconnected from game server');
            UI.showMessage('Disconnected from server', 'error');
            this.stopAllTimers();
        };

        this.ws.onerror = (error) => {
            console.error('WebSocket error:', error);
            UI.showMessage('Connection error', 'error');
        };
    }

    handleServerMessage(data) {
        switch (data.type) {
            case 'JOINED':
                this.myPlayerId = data.playerId;
                this.myPlayerNumber = data.playerNumber;
                this.roomId = data.roomId;
                const playerColor = this.getPlayerColor(this.myPlayerId);
                console.log(`Joined as ${this.myPlayerId} in room ${this.roomId}`);
                UI.showPlayerInfo(this.myPlayerId, playerColor);
                UI.showMessage(`You are ${this.myPlayerId} (${playerColor}). Waiting for ${4 - data.playersCount} more players...`, 'info');
                break;

            case 'PLAYER_JOINED':
                console.log('Another player joined. Total players:', data.playersCount);
                UI.showMessage(`Player joined! ${data.playersCount}/4 players ready`, 'info');
                break;

            case 'GAME_START':
                this.gameStarted = true;
                this.turnIndex = 0;
                this.turn = this.TURN_ORDER[0];
                this.state = STATE.DICE_NOT_ROLLED;
                console.log('Game started!');
                UI.showMessage('Game Started! All players ready!', 'success');
                setTimeout(() => UI.hideMessage(), 2000);
                this.updateTurnDisplay();
                this.startGameTimer();
                this.startTurnTimer();
                break;

            case 'DICE_ROLLED':
                if (data.playerId !== this.myPlayerId) {
                    this.diceValue = data.diceValue;
                    rollDice(data.diceValue);
                    this.state = STATE.DICE_ROLLED;
                    this.checkForEligiblePieces();
                }
                if (data.points) {
                    this.playerPoints = data.points;
                    UI.updateScoreboard(this.playerPoints);
                }
                this.updateTurnDisplay();
                break;

            case 'PIECE_POSITION_UPDATE':
                if (data.playerId !== this.myPlayerId) {
                    this.currentPositions[data.playerId][data.piece] = data.position;
                    UI.setPiecePosition(data.playerId, data.piece, data.position);
                }
                if (data.pieceSteps) {
                    this.pieceSteps = data.pieceSteps;
                }
                break;

            case 'TURN_END':
                // Update to the turn sent by the server
                const nextTurnIndex = this.TURN_ORDER.indexOf(data.nextTurn);
                if (nextTurnIndex !== -1) {
                    this.turnIndex = nextTurnIndex;
                    this.turn = data.nextTurn;
                }
                this.state = STATE.DICE_NOT_ROLLED;
                this.updateTurnDisplay();
                this.resetTurnTimer();
                if (data.points) {
                    this.playerPoints = data.points;
                    UI.updateScoreboard(this.playerPoints);
                }
                break;

            case 'TURN_TIMEOUT':
                if (data.playerId === this.myPlayerId) {
                    UI.showMessage('Time up! Turn skipped', 'warning');
                    setTimeout(() => UI.hideMessage(), 2000);
                }
                // Update to the turn sent by the server
                const timeoutNextIndex = this.TURN_ORDER.indexOf(data.nextTurn);
                if (timeoutNextIndex !== -1) {
                    this.turnIndex = timeoutNextIndex;
                    this.turn = data.nextTurn;
                }
                this.state = STATE.DICE_NOT_ROLLED;
                this.updateTurnDisplay();
                this.resetTurnTimer();
                break;

            case 'PLAYER_WON':
                this.stopAllTimers();
                alert(`Player: ${data.playerId} has won!`);
                break;

            case 'GAME_TIMEOUT':
                this.stopAllTimers();
                const winner = data.winner;
                const finalScores = data.scores;
                UI.showGameOver(winner, finalScores);
                break;

            case 'POINTS_UPDATE':
                this.playerPoints = data.points;
                UI.updateScoreboard(this.playerPoints);
                break;

            case 'GAME_RESET':
                this.resetGame();
                break;

            case 'PLAYER_LEFT':
                UI.showMessage('A player left the game', 'warning');
                break;

            case 'ERROR':
                console.error('Server error:', data.message);
                UI.showMessage(data.message, 'error');
                break;
        }
    }

    listenRollButtonClick() {
    UI.listenDiceClick(this.onDiceClick.bind(this));
}
    onDiceClick() {
        if (!this.gameStarted) {
            UI.showMessage('Game not started yet', 'warning');
            setTimeout(() => UI.hideMessage(), 2000);
            return;
        }

        const currentPlayer = PLAYERS[this.turn];
        if (currentPlayer !== this.myPlayerId) {
            UI.showMessage('Not your turn!', 'warning');
            setTimeout(() => UI.hideMessage(), 2000);
            return;
        }

        console.log('dice clicked!');
        const diceRoll = randomDice();
        this.diceValue = diceRoll;
        this.state = STATE.DICE_ROLLED;

        // Add points for dice roll
        this.playerPoints[this.myPlayerId] += diceRoll;
        UI.updateScoreboard(this.playerPoints);

        this.ws.send(JSON.stringify({
            type: 'ROLL_DICE',
            playerId: this.myPlayerId,
            diceValue: diceRoll,
            points: this.playerPoints
        }));

        this.checkForEligiblePieces();
    }

    checkForEligiblePieces() {
        const player = PLAYERS[this.turn];
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

    incrementTurn() {
        // Only send turn end if it's currently your turn
        const currentPlayer = PLAYERS[this.turn];
        
        this.turnIndex = (this.turnIndex + 1) % this.TURN_ORDER.length;
        this.turn = this.TURN_ORDER[this.turnIndex];
        this.state = STATE.DICE_NOT_ROLLED;

        this.updateTurnDisplay();

        // Only the current player should send the turn end message
        if (currentPlayer === this.myPlayerId && this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify({
                type: 'TURN_END',
                nextTurn: this.turn,
                points: this.playerPoints
            }));
        }
    }

    getEligiblePieces(player) {
        // ANY piece can come out regardless of dice value
        return [0, 1, 2, 3].filter(piece => {
            const currentPosition = this.currentPositions[player][piece];

            // Can't move if already at home
            if (currentPosition === HOME_POSITIONS[player]) {
                return false;
            }

            // Check if piece can't move beyond home
            if (
                HOME_ENTRANCE[player].includes(currentPosition) &&
                this.diceValue > HOME_POSITIONS[player] - currentPosition
            ) {
                return false;
            }

            return true;
        });
    }

    listenResetClick() {
        UI.listenResetClick(this.resetGame.bind(this));
    }

    resetGame() {
        console.log('reset game');
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
        
        this.turnIndex = -1;
        this.incrementTurn();
        this.state = STATE.DICE_NOT_ROLLED;
        this.gameTimeRemaining = 420;
        this.stopAllTimers();
        
        UI.updateScoreboard(this.playerPoints);
    }

    listenPieceClick() {
        UI.listenPieceClick(this.onPieceClick.bind(this));
    }

    onPieceClick(event) {
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

        console.log('piece clicked');
        const piece = target.getAttribute('piece');
        this.handlePieceClick(player, piece);
    }

    handlePieceClick(player, piece) {
        console.log(player, piece);
        const currentPosition = this.currentPositions[player][piece];

        if (BASE_POSITIONS[player].includes(currentPosition)) {
            this.setPiecePosition(player, piece, START_POSITIONS[player]);
            this.pieceSteps[player][piece] = 1;
            
            // Send update to server
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.ws.send(JSON.stringify({
                    type: 'PIECE_POSITION_UPDATE',
                    playerId: player,
                    piece: piece,
                    position: START_POSITIONS[player],
                    pieceSteps: this.pieceSteps
                }));
            }
            
            // If dice is 6, player gets another turn, otherwise pass turn
            if (this.diceValue === 6) {
                this.state = STATE.DICE_NOT_ROLLED;
                this.resetTurnTimer();
            } else {
                setTimeout(() => {
                    this.incrementTurn();
                }, 500);
            }
            return;
        }

        UI.unhighlightPieces();
        this.movePiece(player, piece, this.diceValue);
    }

    setPiecePosition(player, piece, newPosition) {
        this.currentPositions[player][piece] = newPosition;
        UI.setPiecePosition(player, piece, newPosition);
    }

    movePiece(player, piece, moveBy) {
        const interval = setInterval(() => {
            this.incrementPiecePosition(player, piece);
            this.pieceSteps[player][piece]++;
            moveBy--;

            // Send position update to server
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.ws.send(JSON.stringify({
                    type: 'PIECE_POSITION_UPDATE',
                    playerId: player,
                    piece: piece,
                    position: this.currentPositions[player][piece],
                    pieceSteps: this.pieceSteps
                }));
            }

            if (moveBy === 0) {
                clearInterval(interval);

                // Check if player won
                if (this.hasPlayerWon(player)) {
                    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                        this.ws.send(JSON.stringify({
                            type: 'PLAYER_WON',
                            playerId: player
                        }));
                    }
                    this.stopAllTimers();
                    alert(`Player: ${player} has won!`);
                    this.resetGame();
                    return;
                }

                const isKill = this.checkForKill(player, piece);
                const reachedHome = this.currentPositions[player][piece] === HOME_POSITIONS[player];

                // Give +10 points for reaching home
                if (reachedHome) {
                    this.playerPoints[player] += 10;
                    UI.updateScoreboard(this.playerPoints);
                    
                    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                        this.ws.send(JSON.stringify({
                            type: 'POINTS_UPDATE',
                            points: this.playerPoints
                        }));
                    }
                }

                // Get another turn if rolled 6 or made a kill
                if (isKill || this.diceValue === 6) {
                    this.state = STATE.DICE_NOT_ROLLED;
                    this.resetTurnTimer();
                    return;
                }

                setTimeout(() => {
                    this.incrementTurn();
                }, 500);
            }
        }, 200);
    }

    checkForKill(player, piece) {
        const currentPosition = this.currentPositions[player][piece];
        let kill = false;

        PLAYERS.forEach(opponent => {
            if (opponent !== player) {
                [0, 1, 2, 3].forEach(opponentPiece => {
                    const opponentPosition = this.currentPositions[opponent][opponentPiece];

                    if (currentPosition === opponentPosition && !SAFE_POSITIONS.includes(currentPosition)) {
                        // Get the steps the opponent piece had moved
                        const stepsLost = this.pieceSteps[opponent][opponentPiece];
                        
                        // Deduct points from opponent
                        this.playerPoints[opponent] -= stepsLost;
                        
                        // Add +10 points to attacker
                        this.playerPoints[player] += 10;
                        
                        // Reset opponent piece steps
                        this.pieceSteps[opponent][opponentPiece] = 0;
                        
                        // Send opponent piece back to base
                        this.setPiecePosition(opponent, opponentPiece, BASE_POSITIONS[opponent][opponentPiece]);
                        
                        // Update server
                        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                            this.ws.send(JSON.stringify({
                                type: 'PIECE_POSITION_UPDATE',
                                playerId: opponent,
                                piece: opponentPiece,
                                position: BASE_POSITIONS[opponent][opponentPiece],
                                pieceSteps: this.pieceSteps
                            }));
                            
                            this.ws.send(JSON.stringify({
                                type: 'POINTS_UPDATE',
                                points: this.playerPoints
                            }));
                        }
                        
                        UI.updateScoreboard(this.playerPoints);
                        kill = true;
                    }
                });
            }
        });

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

    getPlayerColor(playerId) {
        const colors = {
            P1: 'Blue',
            P2: 'Red',
            P3: 'Green',
            P4: 'Yellow'
        };
        return colors[playerId] || 'Unknown';
    }

    updateTurnDisplay() {
        const currentPlayer = PLAYERS[this.turn];
        const currentColor = this.getPlayerColor(currentPlayer);
        
        if (currentPlayer === this.myPlayerId) {
            UI.showTurnMessage(`🎲 YOUR TURN! (${currentColor})`, 'your-turn');
        } else {
            UI.showTurnMessage(`${currentPlayer}'s turn (${currentColor})`, 'other-turn');
        }
    }

    // Timer methods
    startGameTimer() {
        this.gameTimeRemaining = 420; // 7 minutes
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
        this.turnTimeRemaining = 10; // 10 seconds
        UI.updateTurnTimer(this.turnTimeRemaining);
        
        this.turnTimer = setInterval(() => {
            this.turnTimeRemaining--;
            UI.updateTurnTimer(this.turnTimeRemaining);
            
            if (this.turnTimeRemaining <= 0) {
                this.handleTurnTimeout();
            }
        }, 1000);
    }

    resetTurnTimer() {
        if (this.turnTimer) {
            clearInterval(this.turnTimer);
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
    }

    handleGameTimeout() {
        this.stopAllTimers();
        
        // Find winner with highest points
        const maxPoints = Math.max(...Object.values(this.playerPoints));
        const winner = Object.keys(this.playerPoints).find(p => this.playerPoints[p] === maxPoints);
        
        if (this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify({
                type: 'GAME_TIMEOUT',
                winner: winner,
                scores: this.playerPoints
            }));
        }
        
        UI.showGameOver(winner, this.playerPoints);
    }

    handleTurnTimeout() {
        const currentPlayer = PLAYERS[this.turn];
        
        // Only the current player should send the timeout message
        if (currentPlayer === this.myPlayerId) {
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.ws.send(JSON.stringify({
                    type: 'TURN_TIMEOUT',
                    playerId: this.myPlayerId
                }));
            }
            
            UI.showMessage('Time up! Turn skipped', 'warning');
            setTimeout(() => UI.hideMessage(), 2000);
            this.incrementTurn();
        }
    }
}