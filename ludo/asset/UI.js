import { COORDINATES_MAP, PLAYERS, STEP_LENGTH } from './constants.js';

const diceButtonElement = document.querySelector('#dice-btn');
const playerPiecesElements = {
    P1: document.querySelectorAll('[player-id="P1"].player-piece'),
    P2: document.querySelectorAll('[player-id="P2"].player-piece'),
    P3: document.querySelectorAll('[player-id="P3"].player-piece'),
    P4: document.querySelectorAll('[player-id="P4"].player-piece'),
}

export class UI {
    static isMobile() {
        return window.innerWidth <= 768;
    }

    static listenDiceClick(callback) {
        // Listen to dice element itself instead of button
        const dice = document.querySelector('.dice');
        if (dice) {
            dice.addEventListener('click', callback);
        }
    }

    static listenResetClick(callback) {
        document.querySelector('button#reset-btn')?.addEventListener('click', callback);
    }

    static listenPieceClick(callback) {
        document.querySelector('.player-pieces').addEventListener('click', callback);
    }

    static setPiecePosition(player, piece, newPosition) {
        if (!playerPiecesElements[player] || !playerPiecesElements[player][piece]) {
            console.error(`Player element of given player: ${player} and piece: ${piece} not found`);
            return;
        }

        const [x, y] = COORDINATES_MAP[newPosition];

        const pieceElement = playerPiecesElements[player][piece];
        pieceElement.style.top = y * STEP_LENGTH + '%';
        pieceElement.style.left = x * STEP_LENGTH + '%';
    }

    static setTurn(index) {
        if (index < 0 || index >= PLAYERS.length) {
            console.error('index out of bound!');
            return;
        }

        const player = PLAYERS[index];

        const activePlayerSpan = document.querySelector('.active-player span');
        if (activePlayerSpan) {
            activePlayerSpan.innerText = player;
        }

        const activePlayerBase = document.querySelector('.player-base.highlight');
        if (activePlayerBase) {
            activePlayerBase.classList.remove('highlight');
        }
        
        const playerBase = document.querySelector(`[player-id="${player}"].player-base`);
        if (playerBase) {
            playerBase.classList.add('highlight');
        }
    }

    static enableDice() {
        const dice = document.querySelector('.dice');
        if (dice) {
            dice.style.pointerEvents = 'auto';
            dice.style.opacity = '1';
            dice.style.cursor = 'pointer';
        }
    }

    static disableDice() {
        const dice = document.querySelector('.dice');
        if (dice) {
            dice.style.pointerEvents = 'none';
            dice.style.opacity = '0.6';
            dice.style.cursor = 'not-allowed';
        }
    }

    static highlightPieces(player, pieces) {
        pieces.forEach(piece => {
            const pieceElement = playerPiecesElements[player][piece];
            pieceElement.classList.add('highlight');
        });
    }

    static unhighlightPieces() {
        document.querySelectorAll('.player-piece.highlight').forEach(ele => {
            ele.classList.remove('highlight');
        });
    }

    static setDiceValue(value) {
        const diceValueElement = document.querySelector('.dice-value');
        if (diceValueElement) {
            diceValueElement.innerText = value;
        }
    }

    static showMessage(message, type = 'info') {
        let messageBox = document.getElementById('game-message');
        
        if (!messageBox) {
            messageBox = document.createElement('div');
            messageBox.id = 'game-message';
            messageBox.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                padding: 12px 25px;
                border-radius: 20px;
                font-size: clamp(13px, 3vw, 15px);
                font-weight: 600;
                font-family: 'Poppins', sans-serif;
                z-index: 1000;
                box-shadow: 0 8px 25px rgba(0,0,0,0.3);
                transition: opacity 0.3s;
                max-width: 300px;
                backdrop-filter: blur(10px);
            `;
            document.body.appendChild(messageBox);
        }

        const colors = {
            info: { bg: 'rgba(52, 152, 219, 0.95)', text: '#fff' },
            success: { bg: 'rgba(46, 204, 113, 0.95)', text: '#fff' },
            warning: { bg: 'rgba(243, 156, 18, 0.95)', text: '#fff' },
            error: { bg: 'rgba(231, 76, 60, 0.95)', text: '#fff' }
        };

        const color = colors[type] || colors.info;
        messageBox.style.backgroundColor = color.bg;
        messageBox.style.color = color.text;
        messageBox.textContent = message;
        messageBox.style.opacity = '1';
        messageBox.style.display = 'block';
    }

    static hideMessage() {
        const messageBox = document.getElementById('game-message');
        if (messageBox) {
            messageBox.style.opacity = '0';
            setTimeout(() => {
                messageBox.style.display = 'none';
            }, 300);
        }
    }

    static showPlayerInfo(playerId, color) {
        let infoBox = document.getElementById('info');
        
        if (!infoBox) {
            infoBox = document.createElement('div');
            infoBox.id = 'player-info';
            infoBox.style.cssText = `
                position: fixed;
                top: 20px;
                left: 50%;
                transform: translateX(-50%);
                padding: 12px 25px;
                border-radius: 20px;
                font-size: clamp(14px, 3.5vw, 16px);
                font-weight: 700;
                font-family: 'Poppins', sans-serif;
                z-index: 999;
                box-shadow: 0 8px 25px rgba(0,0,0,0.3);
                background: rgba(255, 255, 255, 0.95);
                color: #333;
                border: 3px solid ${this.getColorHex(playerId)};
                max-width: 90vw;
                text-align: center;
                backdrop-filter: blur(10px);
            `;
            document.body.appendChild(infoBox);
        }

        infoBox.innerHTML = `👤 You are: <span style="font-size: clamp(16px, 4vw, 18px); color: ${this.getColorHex(playerId)}; font-weight: 800;">${playerId}</span> <span style="color: ${this.getColorHex(playerId)}; font-weight: 700;">(${color})</span>`;
    }

    static showTurnMessage(message, type) {
        let turnBox = document.getElementById('turn-message');
        
        if (!turnBox) {
            turnBox = document.createElement('div');
            turnBox.id = 'turn-message';
            turnBox.style.cssText = `
                position: fixed;
                bottom: ${this.isMobile() ? '160px' : '20px'};
                left: 50%;
                transform: translateX(-50%);
                padding: 15px 30px;
                border-radius: 25px;
                font-size: clamp(16px, 4vw, 20px);
                font-weight: 700;
                font-family: 'Fredoka', sans-serif;
                z-index: 998;
                box-shadow: 0 8px 25px rgba(0,0,0,0.3);
                transition: all 0.3s ease;
                text-align: center;
                min-width: 250px;
                max-width: 90vw;
                backdrop-filter: blur(10px);
            `;
            document.body.appendChild(turnBox);
        }

        if (type === 'your-turn') {
            turnBox.style.background = 'linear-gradient(135deg, #11998e 0%, #38ef7d 100%)';
            turnBox.style.color = 'white';
            turnBox.style.border = '3px solid #fff';
            turnBox.style.animation = 'pulse 1.5s infinite';
            
            if (!document.getElementById('pulse-animation')) {
                const style = document.createElement('style');
                style.id = 'pulse-animation';
                style.textContent = `
                    @keyframes pulse {
                        0%, 100% { transform: translateX(-50%) scale(1); }
                        50% { transform: translateX(-50%) scale(1.05); }
                    }
                `;
                document.head.appendChild(style);
            }
        } else {
            turnBox.style.background = 'rgba(255, 255, 255, 0.95)';
            turnBox.style.color = '#333';
            turnBox.style.border = '3px solid #ddd';
            turnBox.style.animation = 'none';
        }

        turnBox.textContent = message;
    }

    static getColorHex(playerId) {
        const colors = {
            P1: '#2eafff',
            P2: '#ff4757',
            P3: '#00b550',
            P4: '#ffd700'
        };
        return colors[playerId] || '#ffffff';
    }

    static updateScoreboard(points) {
        let scoreboard = document.getElementById('scoreboard');
        
        if (!scoreboard) {
            scoreboard = document.createElement('div');
            scoreboard.id = 'scoreboard';
            scoreboard.style.cssText = `
                position: fixed;
                top: 80px;
                left: 50%;
                transform: translateX(-50%);
                padding: 15px 20px;
                border-radius: 15px;
                background: rgba(255, 255, 255, 0.95);
                color: #333;
                z-index: 997;
                box-shadow: 0 8px 25px rgba(0,0,0,0.3);
                font-family: 'Poppins', sans-serif;
                max-width: 90vw;
                overflow-x: auto;
                backdrop-filter: blur(10px);
            `;
            document.body.appendChild(scoreboard);
        }

        const sortedPlayers = Object.entries(points).sort((a, b) => b[1] - a[1]);
        
        let html = '<div style="display: flex; gap: 15px; align-items: center; white-space: nowrap;">';
        
        sortedPlayers.forEach(([player, score], index) => {
            const color = this.getColorHex(player);
            const colorName = this.getPlayerColorName(player);
            const medal = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : '👤';
            html += `
                <div style="
                    display: inline-flex;
                    align-items: center;
                    gap: 8px;
                    padding: 10px 15px;
                    background: linear-gradient(135deg, ${color}22 0%, ${color}44 100%);
                    border-radius: 20px;
                    border: 2px solid ${color};
                    font-size: clamp(12px, 2.5vw, 14px);
                    font-weight: 600;
                ">
                    <span style="font-size: clamp(16px, 3vw, 20px);">${medal}</span>
                    <span style="color: ${color}; font-weight: 700;">${player}</span>
                    <span style="color: #666; font-size: clamp(10px, 2vw, 12px);">(${colorName})</span>
                    <span style="
                        font-size: clamp(14px, 3vw, 16px);
                        font-weight: 700;
                        color: ${score >= 0 ? '#2ecc71' : '#e74c3c'};
                        margin-left: 5px;
                    ">
                        ${score}pts
                    </span>
                </div>
            `;
        });
        
        html += '</div>';
        scoreboard.innerHTML = html;
    }

    static getPlayerColorName(playerId) {
        const colors = {
            P1: 'Blue',
            P2: 'Red',
            P3: 'Green',
            P4: 'Yellow'
        };
        return colors[playerId] || 'Unknown';
    }

    static updateGameTimer(seconds) {
        let timerBox = document.getElementById('game-timer');
        
        if (!timerBox) {
            timerBox = document.createElement('div');
            timerBox.id = 'game-timer';
            timerBox.style.cssText = `
                position: fixed;
                top: 20px;
                left: 20px;
                padding: 12px 25px;
                border-radius: 20px;
                background: rgba(231, 76, 60, 0.95);
                color: white;
                z-index: 996;
                box-shadow: 0 8px 25px rgba(0,0,0,0.3);
                font-family: 'Poppins', sans-serif;
                font-size: clamp(16px, 4vw, 22px);
                font-weight: 700;
                text-align: center;
                backdrop-filter: blur(10px);
            `;
            document.body.appendChild(timerBox);
        }

        const minutes = Math.floor(seconds / 60);
        const secs = seconds % 60;
        timerBox.innerHTML = `⏱️ ${minutes}:${secs.toString().padStart(2, '0')}`;
        
        if (seconds <= 30) {
            timerBox.style.animation = 'pulse 0.5s infinite';
        }
    }

    static updateTurnTimer(seconds) {
        let turnTimerBox = document.getElementById('turn-timer');
        
        if (!turnTimerBox) {
            turnTimerBox = document.createElement('div');
            turnTimerBox.id = 'turn-timer';
            const isMobile = this.isMobile();
            turnTimerBox.style.cssText = `
                position: fixed;
                bottom: ${isMobile ? '220px' : '20px'};
                right: ${isMobile ? '10px' : '20px'};
                padding: 10px 20px;
                border-radius: 20px;
                background: rgba(52, 152, 219, 0.95);
                color: white;
                z-index: 995;
                box-shadow: 0 6px 20px rgba(0,0,0,0.3);
                font-family: 'Poppins', sans-serif;
                font-size: clamp(14px, 3.5vw, 16px);
                font-weight: 700;
                text-align: center;
                backdrop-filter: blur(10px);
            `;
            document.body.appendChild(turnTimerBox);
        }

        turnTimerBox.textContent = `⏳ ${seconds}s`;
        
        if (seconds <= 3) {
            turnTimerBox.style.background = 'rgba(231, 76, 60, 0.95)';
            turnTimerBox.style.animation = 'pulse 0.3s infinite';
        } else {
            turnTimerBox.style.background = 'rgba(52, 152, 219, 0.95)';
            turnTimerBox.style.animation = 'none';
        }
    }

    static showGameOver(winner, scores) {
        let overlay = document.getElementById('game-over-overlay');
        
        if (!overlay) {
            overlay = document.createElement('div');
            overlay.id = 'game-over-overlay';
            overlay.style.cssText = `
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0,0,0,0.85);
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
                padding: 20px;
                backdrop-filter: blur(5px);
            `;
            document.body.appendChild(overlay);
        }

        const winnerColor = this.getColorHex(winner);
        const winnerColorName = this.getPlayerColorName(winner);
        const sortedScores = Object.entries(scores).sort((a, b) => b[1] - a[1]);
        const isMobile = this.isMobile();
        
        let scoresHTML = '';
        sortedScores.forEach(([player, score], index) => {
            const color = this.getColorHex(player);
            const colorName = this.getPlayerColorName(player);
            const medal = index === 0 ? '🥇' : index === 1 ? '🥈' : index === 2 ? '🥉' : '4️⃣';
            scoresHTML += `
                <div style="margin: 12px 0; font-size: clamp(15px, 3.5vw, 18px); font-family: 'Poppins', sans-serif; ${player === winner ? 'font-weight: 700; font-size: clamp(17px, 4.5vw, 22px);' : ''}">
                    ${medal} <span style="color: ${color}; font-weight: 700;">${player} (${colorName})</span>: <span style="font-weight: 700;">${score} points</span>
                </div>
            `;
        });

        overlay.innerHTML = `
            <div style="
                background: rgba(255, 255, 255, 0.98);
                padding: ${isMobile ? '30px 20px' : '40px'};
                border-radius: 25px;
                text-align: center;
                box-shadow: 0 15px 50px rgba(0,0,0,0.5);
                max-width: ${isMobile ? '95vw' : '500px'};
                width: 100%;
                font-family: 'Poppins', sans-serif;
            ">
                <h1 style="
                    color: #764ba2;
                    font-size: clamp(28px, 7vw, 42px);
                    margin-bottom: 20px;
                    font-family: 'Fredoka', sans-serif;
                    font-weight: 800;
                ">🎉 GAME OVER! 🎉</h1>
                <h2 style="
                    color: ${winnerColor};
                    font-size: clamp(22px, 6vw, 32px);
                    margin-bottom: 25px;
                    font-weight: 800;
                    font-family: 'Fredoka', sans-serif;
                    text-shadow: 2px 2px 4px rgba(0,0,0,0.1);
                ">
                    ${winner} (${winnerColorName}) WINS!
                </h2>
                <div style="
                    background: linear-gradient(135deg, #667eea22 0%, #764ba244 100%);
                    padding: ${isMobile ? '15px' : '20px'};
                    border-radius: 15px;
                    margin-bottom: 25px;
                    border: 2px solid #667eea;
                ">
                    <h3 style="color: #333; margin-bottom: 15px; font-size: clamp(18px, 5vw, 24px); font-weight: 700;">Final Scores</h3>
                    ${scoresHTML}
                </div>
                <button onclick="location.reload()" style="
                    padding: ${isMobile ? '12px 30px' : '15px 40px'};
                    font-size: clamp(16px, 4vw, 20px);
                    background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                    color: white;
                    border: none;
                    border-radius: 25px;
                    cursor: pointer;
                    font-weight: 700;
                    font-family: 'Poppins', sans-serif;
                    box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
                    transition: all 0.3s;
                " onmouseover="this.style.transform='translateY(-2px)'; this.style.boxShadow='0 8px 25px rgba(102, 126, 234, 0.6)';" onmouseout="this.style.transform='translateY(0)'; this.style.boxShadow='0 6px 20px rgba(102, 126, 234, 0.4)';">
                    🎮 Play Again
                </button>
            </div>
        `;
    }
}