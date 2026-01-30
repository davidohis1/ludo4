// server.js - Node.js WebSocket Server for Ludo Game
const WebSocket = require('ws');
const http = require('http');

const server = http.createServer();
const wss = new WebSocket.Server({ server });

const gameRooms = new Map();

class GameRoom {
    constructor(roomId) {
        this.roomId = roomId;
        this.players = [];
        this.gameStarted = false;
        this.currentTurnIndex = 0;
        this.turnOrder = [0, 1, 2, 3]; // P1=Blue, P2=Red, P3=Green, P4=Yellow
        this.diceValue = null;
        this.positions = {
            P1: [500, 501, 502, 503],
            P2: [600, 601, 602, 603],
            P3: [700, 701, 702, 703],
            P4: [800, 801, 802, 803]
        };
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
        this.gameTimer = null;
        this.turnTimer = null;
        this.gameTimeRemaining = 420; // 7 minutes
        this.turnTimeRemaining = 10; // 10 seconds
    }

    addPlayer(ws, playerId) {
        if (this.players.length >= 4) {
            return false;
        }
        
        const playerNumber = this.players.length;
        const playerData = {
            ws,
            playerId: `P${playerNumber + 1}`,
            socketId: playerId,
            playerNumber
        };
        
        this.players.push(playerData);
        return playerData;
    }

    removePlayer(playerId) {
        const index = this.players.findIndex(p => p.socketId === playerId);
        if (index !== -1) {
            this.players.splice(index, 1);
            this.stopTimers();
            return true;
        }
        return false;
    }

    isFull() {
        return this.players.length === 4;
    }

    broadcast(message, excludeId = null) {
        this.players.forEach(player => {
            if (player.socketId !== excludeId && player.ws.readyState === WebSocket.OPEN) {
                player.ws.send(JSON.stringify(message));
            }
        });
    }

    sendToAll(message) {
        this.players.forEach(player => {
            if (player.ws.readyState === WebSocket.OPEN) {
                player.ws.send(JSON.stringify(message));
            }
        });
    }

    startGameTimer() {
        this.gameTimeRemaining = 420;
        
        this.gameTimer = setInterval(() => {
            this.gameTimeRemaining--;
            
            if (this.gameTimeRemaining <= 0) {
                this.handleGameTimeout();
            }
        }, 1000);
    }

    startTurnTimer() {
        this.turnTimeRemaining = 10;
        
        this.turnTimer = setInterval(() => {
            this.turnTimeRemaining--;
            
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

    stopTimers() {
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
        this.stopTimers();
        
        const maxPoints = Math.max(...Object.values(this.playerPoints));
        const winner = Object.keys(this.playerPoints).find(p => this.playerPoints[p] === maxPoints);
        
        this.sendToAll({
            type: 'GAME_TIMEOUT',
            winner: winner,
            scores: this.playerPoints
        });
        
        console.log(`Game timeout in room ${this.roomId}. Winner: ${winner}`);
    }

    handleTurnTimeout() {
        const currentPlayerIndex = this.turnOrder[this.currentTurnIndex];
        const currentPlayer = this.players[currentPlayerIndex];
        
        // Move to next turn
        this.currentTurnIndex = (this.currentTurnIndex + 1) % this.turnOrder.length;
        const nextTurnIndex = this.turnOrder[this.currentTurnIndex];
        
        this.sendToAll({
            type: 'TURN_TIMEOUT',
            playerId: currentPlayer.playerId,
            nextTurn: nextTurnIndex
        });
        
        this.resetTurnTimer();
    }
}

function generateRoomId() {
    return Math.random().toString(36).substring(2, 8).toUpperCase();
}

function findAvailableRoom() {
    for (let [roomId, room] of gameRooms.entries()) {
        if (!room.isFull() && !room.gameStarted) {
            return room;
        }
    }
    
    const newRoomId = generateRoomId();
    const newRoom = new GameRoom(newRoomId);
    gameRooms.set(newRoomId, newRoom);
    return newRoom;
}

wss.on('connection', (ws) => {
    const playerId = Math.random().toString(36).substring(2, 15);
    let currentRoom = null;

    console.log(`New player connected: ${playerId}`);

    ws.on('message', (message) => {
        try {
            const data = JSON.parse(message);

            switch (data.type) {
                case 'JOIN_GAME':
                    currentRoom = findAvailableRoom();
                    const playerData = currentRoom.addPlayer(ws, playerId);
                    
                    if (!playerData) {
                        ws.send(JSON.stringify({ type: 'ERROR', message: 'Room is full' }));
                        return;
                    }

                    ws.send(JSON.stringify({
                        type: 'JOINED',
                        roomId: currentRoom.roomId,
                        playerId: playerData.playerId,
                        playerNumber: playerData.playerNumber,
                        playersCount: currentRoom.players.length
                    }));

                    currentRoom.broadcast({
                        type: 'PLAYER_JOINED',
                        playersCount: currentRoom.players.length,
                        players: currentRoom.players.map(p => p.playerId)
                    }, playerId);

                    console.log(`Player ${playerData.playerId} joined room ${currentRoom.roomId}. Players: ${currentRoom.players.length}/4`);

                    if (currentRoom.isFull()) {
                        currentRoom.gameStarted = true;
                        currentRoom.currentTurnIndex = 0;
                        
                        setTimeout(() => {
                            currentRoom.sendToAll({
                                type: 'GAME_START',
                                players: currentRoom.players.map(p => p.playerId),
                                currentTurn: currentRoom.turnOrder[0]
                            });
                            console.log(`Game started in room ${currentRoom.roomId}`);
                            
                            // Start timers
                            currentRoom.startGameTimer();
                            currentRoom.startTurnTimer();
                        }, 1000);
                    }
                    break;

                case 'ROLL_DICE':
                    if (currentRoom && currentRoom.gameStarted) {
                        const diceValue = data.diceValue;
                        currentRoom.diceValue = diceValue;
                        
                        // Add points for dice roll
                        if (data.points) {
                            currentRoom.playerPoints = data.points;
                        }
                        
                        currentRoom.sendToAll({
                            type: 'DICE_ROLLED',
                            playerId: data.playerId,
                            diceValue: diceValue,
                            points: currentRoom.playerPoints
                        });
                    }
                    break;

                case 'PIECE_POSITION_UPDATE':
                    if (currentRoom && currentRoom.gameStarted) {
                        if (data.pieceSteps) {
                            currentRoom.pieceSteps = data.pieceSteps;
                        }
                        
                        currentRoom.broadcast({
                            type: 'PIECE_POSITION_UPDATE',
                            playerId: data.playerId,
                            piece: data.piece,
                            position: data.position,
                            pieceSteps: currentRoom.pieceSteps
                        }, playerId);
                    }
                    break;

                case 'TURN_END':
                    if (currentRoom && currentRoom.gameStarted) {
                        // Only process turn end from the current player
                        const currentPlayerIndex = currentRoom.turnOrder[currentRoom.currentTurnIndex];
                        const currentPlayerData = currentRoom.players[currentPlayerIndex];
                        
                        if (currentPlayerData && currentPlayerData.playerId === data.playerId) {
                            currentRoom.currentTurnIndex = (currentRoom.currentTurnIndex + 1) % currentRoom.turnOrder.length;
                            const nextTurnIndex = currentRoom.turnOrder[currentRoom.currentTurnIndex];
                            
                            if (data.points) {
                                currentRoom.playerPoints = data.points;
                            }
                            
                            // Send to everyone including sender
                            currentRoom.sendToAll({
                                type: 'TURN_END',
                                nextTurn: nextTurnIndex,
                                points: currentRoom.playerPoints
                            });
                            
                            currentRoom.resetTurnTimer();
                        }
                    }
                    break;

                case 'POINTS_UPDATE':
                    if (currentRoom && currentRoom.gameStarted) {
                        if (data.points) {
                            currentRoom.playerPoints = data.points;
                        }
                        
                        currentRoom.sendToAll({
                            type: 'POINTS_UPDATE',
                            points: currentRoom.playerPoints
                        });
                    }
                    break;

                case 'PLAYER_WON':
                    if (currentRoom && currentRoom.gameStarted) {
                        currentRoom.stopTimers();
                        currentRoom.sendToAll({
                            type: 'PLAYER_WON',
                            playerId: data.playerId
                        });
                    }
                    break;

                case 'TURN_TIMEOUT':
                    if (currentRoom && currentRoom.gameStarted) {
                        // Only process from the current player
                        const timeoutPlayerIndex = currentRoom.turnOrder[currentRoom.currentTurnIndex];
                        const timeoutPlayerData = currentRoom.players[timeoutPlayerIndex];
                        
                        if (timeoutPlayerData && timeoutPlayerData.playerId === data.playerId) {
                            currentRoom.currentTurnIndex = (currentRoom.currentTurnIndex + 1) % currentRoom.turnOrder.length;
                            const nextTurnIndex = currentRoom.turnOrder[currentRoom.currentTurnIndex];
                            
                            currentRoom.sendToAll({
                                type: 'TURN_TIMEOUT',
                                playerId: data.playerId,
                                nextTurn: nextTurnIndex
                            });
                            
                            currentRoom.resetTurnTimer();
                        }
                    }
                    break;

                case 'GAME_TIMEOUT':
                    if (currentRoom) {
                        currentRoom.stopTimers();
                        currentRoom.sendToAll({
                            type: 'GAME_TIMEOUT',
                            winner: data.winner,
                            scores: data.scores
                        });
                    }
                    break;

                case 'RESET_GAME':
                    if (currentRoom) {
                        currentRoom.gameStarted = false;
                        currentRoom.currentTurnIndex = 0;
                        currentRoom.positions = {
                            P1: [500, 501, 502, 503],
                            P2: [600, 601, 602, 603],
                            P3: [700, 701, 702, 703],
                            P4: [800, 801, 802, 803]
                        };
                        currentRoom.pieceSteps = {
                            P1: [0, 0, 0, 0],
                            P2: [0, 0, 0, 0],
                            P3: [0, 0, 0, 0],
                            P4: [0, 0, 0, 0]
                        };
                        currentRoom.playerPoints = {
                            P1: 0,
                            P2: 0,
                            P3: 0,
                            P4: 0
                        };
                        currentRoom.stopTimers();
                        
                        currentRoom.sendToAll({
                            type: 'GAME_RESET'
                        });
                    }
                    break;
            }
        } catch (error) {
            console.error('Error processing message:', error);
        }
    });

    ws.on('close', () => {
        console.log(`Player disconnected: ${playerId}`);
        
        if (currentRoom) {
            currentRoom.removePlayer(playerId);
            
            if (currentRoom.players.length === 0) {
                gameRooms.delete(currentRoom.roomId);
                console.log(`Room ${currentRoom.roomId} deleted - no players left`);
            } else {
                currentRoom.broadcast({
                    type: 'PLAYER_LEFT',
                    playersCount: currentRoom.players.length
                });
            }
        }
    });

    ws.on('error', (error) => {
        console.error('WebSocket error:', error);
    });
});

const PORT = process.env.PORT || 8080;
server.listen(PORT, () => {
    console.log(`WebSocket server running on port ${PORT}`);
});