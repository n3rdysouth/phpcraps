/**
 * Craps Game JavaScript - Casino Edition
 */

class CrapsGame {
    constructor() {
        this.apiUrl = 'api.php';
        this.playerId = null;
        this.playerName = null;
        this.playerRole = null;
        this.selectedChipValue = 10; // Default chip
        this.pollingInterval = null;
        this.playerBets = {}; // Track player's bets on table
        this.ws = null;
        this.useWebSocket = true; // Try WebSocket first, fallback to polling
        this.wsReconnectAttempts = 0;
        this.wsMaxReconnectAttempts = 5;
        this.timerInterval = null; // Client-side timer countdown
        this.shooterTimeRemaining = 20;
        this.bettingTimeRemaining = 15;
        this.bettingClosed = false;
        this.pendingBet = null; // Track pending bet for potential rollback

        this.init();
    }

    init() {
        // Try to connect WebSocket early
        if (this.useWebSocket) {
            this.connectWebSocket();
        }

        // Show join modal
        this.showJoinModal();

        // Event listeners
        document.getElementById('join-btn').addEventListener('click', () => this.joinGame());
        document.getElementById('roll-btn').addEventListener('click', () => this.rollDice());

        // Chip selection
        document.querySelectorAll('.chip-tray .chip').forEach(chip => {
            chip.addEventListener('click', (e) => {
                document.querySelectorAll('.chip-tray .chip').forEach(c => c.classList.remove('selected'));
                e.currentTarget.classList.add('selected');
                this.selectedChipValue = parseInt(e.currentTarget.dataset.value);
            });
        });

        // Betting area clicks
        document.querySelectorAll('.bet-area, .prop-bet, .place-bet').forEach(area => {
            area.addEventListener('click', (e) => {
                const betType = e.currentTarget.dataset.bet;
                if (betType && this.playerRole === 'player') {
                    this.placeBetOnTable(betType);
                }
            });
        });

        // Enter key in name input
        document.getElementById('player-name-input').addEventListener('keypress', (e) => {
            if (e.key === 'Enter') this.joinGame();
        });
    }

    showJoinModal() {
        document.getElementById('join-modal').classList.remove('hidden');
        document.getElementById('game-area').classList.add('hidden');
    }

    connectWebSocket() {
        if (!this.useWebSocket) return;

        // Connect to WebSocket server directly on port 8080
        const wsUrl = `ws://${window.location.hostname}:8080`;

        try {
            this.ws = new WebSocket(wsUrl);

            this.ws.onopen = () => {
                console.log('WebSocket connected');
                this.wsReconnectAttempts = 0;

                // Register player with WebSocket
                if (this.playerId) {
                    this.ws.send(JSON.stringify({
                        action: 'register',
                        player_id: this.playerId
                    }));
                }

                // Send ping every 5 seconds to keep connection alive
                this.wsPingInterval = setInterval(() => {
                    if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                        this.ws.send(JSON.stringify({ action: 'ping' }));
                    }
                }, 5000);
            };

            this.ws.onmessage = (event) => {
                const message = JSON.parse(event.data);
                this.handleWebSocketMessage(message);
            };

            this.ws.onerror = (error) => {
                console.error('WebSocket error:', error);
            };

            this.ws.onclose = () => {
                console.log('WebSocket disconnected');
                clearInterval(this.wsPingInterval);

                // Try to reconnect or fallback to polling
                if (this.wsReconnectAttempts < this.wsMaxReconnectAttempts) {
                    this.wsReconnectAttempts++;
                    console.log(`Reconnecting... (${this.wsReconnectAttempts}/${this.wsMaxReconnectAttempts})`);
                    setTimeout(() => this.connectWebSocket(), 2000);
                } else {
                    console.log('WebSocket unavailable, falling back to polling');
                    this.useWebSocket = false;
                    this.startPolling();
                }
            };
        } catch (error) {
            console.error('Failed to create WebSocket:', error);
            this.useWebSocket = false;
            this.startPolling();
        }
    }

    handleWebSocketMessage(message) {
        switch (message.type) {
            case 'game_state':
                this.updateGameStateFromData(message.data);
                break;

            case 'join_result':
                if (message.data.success || message.data.player_id) {
                    this.playerId = message.data.player_id;
                    this.playerRole = message.data.role;
                    this.showGameArea();
                    this.showBanner(message.data.message, 'success');

                    // Fetch initial player info via HTTP since WebSocket doesn't handle this yet
                    this.updatePlayerInfo();
                } else {
                    alert(message.data.message || 'Failed to join game');
                }
                break;

            case 'bet_result':
                if (message.data.success) {
                    document.getElementById('player-bankroll').textContent = message.data.new_bankroll.toFixed(2);
                    this.showBanner(`Bet placed successfully!`, 'success');
                    this.pendingBet = null; // Clear pending bet
                } else {
                    // Bet failed - rollback visual chip
                    if (this.pendingBet) {
                        this.playerBets[this.pendingBet.betType] -= this.pendingBet.amount;
                        this.removeLastChipFromTable(this.pendingBet.betType, this.pendingBet.amount);
                        this.pendingBet = null;
                    }
                    this.showBanner(message.data.message, 'error');
                }
                break;

            case 'roll_result':
                if (message.data.success) {
                    const roll = message.data.roll;
                    this.animateDiceRoll(roll.roll.die1, roll.roll.die2, roll.roll.total);
                    this.showBanner(roll.message, 'info');

                    // Update player info after a delay to sync with resolved bets
                    setTimeout(() => {
                        this.updatePlayerInfo();
                        this.syncBetsWithServer();
                    }, 1000);
                }
                break;

            case 'error':
                this.showBanner(message.message, 'error');
                break;

            case 'pong':
                // Keepalive response
                break;
        }
    }

    async joinGame() {
        const nameInput = document.getElementById('player-name-input');
        const name = nameInput.value.trim() || 'Player';
        const role = document.querySelector('input[name="role"]:checked').value;

        // Try WebSocket first
        if (this.useWebSocket && this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.playerName = name;
            this.ws.send(JSON.stringify({
                action: 'join',
                name: name,
                role: role
            }));
            return;
        }

        // Fallback to HTTP API
        try {
            const response = await fetch(`${this.apiUrl}?action=join`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ name, role })
            });

            const data = await response.json();

            if (data.success || data.player_id) {
                this.playerId = data.player_id;
                this.playerName = name;
                this.playerRole = data.role;
                this.showGameArea();
                this.showBanner(data.message, 'success');

                // Connect WebSocket after joining
                if (this.useWebSocket) {
                    this.connectWebSocket();
                } else {
                    this.startPolling();
                }
            } else {
                alert(data.message || 'Failed to join game');
            }
        } catch (error) {
            console.error('Join error:', error);
            alert('Failed to join game');
        }
    }

    showGameArea() {
        document.getElementById('join-modal').classList.add('hidden');
        document.getElementById('top-bar').classList.remove('hidden');
        document.getElementById('game-area').classList.remove('hidden');
        document.getElementById('player-name').textContent = this.playerName;

        // Disable betting for spectators
        if (this.playerRole === 'spectator') {
            document.querySelectorAll('.bet-area, .prop-bet').forEach(area => {
                area.style.opacity = '0.6';
                area.style.pointerEvents = 'none';
            });
            document.querySelector('.chip-selector').style.opacity = '0.5';
        }

        // Fetch initial game state
        this.updateGameState();
    }

    async placeBetOnTable(betType) {
        const amount = this.selectedChipValue;

        // Track bet locally first for immediate visual feedback
        if (!this.playerBets[betType]) {
            this.playerBets[betType] = 0;
        }
        this.playerBets[betType] += amount;
        this.addChipToTable(betType, amount);

        // Try WebSocket first
        if (this.useWebSocket && this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify({
                action: 'bet',
                player_id: this.playerId,
                bet_type: betType,
                amount: amount
            }));
            // Store pending bet for potential rollback
            this.pendingBet = { betType, amount };
            return;
        }

        // Fallback to HTTP API
        try {
            const response = await fetch(`${this.apiUrl}?action=bet`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({
                    player_id: this.playerId,
                    bet_type: betType,
                    amount: amount
                })
            });

            const data = await response.json();

            if (data.success) {
                document.getElementById('player-bankroll').textContent = data.new_bankroll.toFixed(2);
                this.showBanner(`$${amount} bet placed on ${betType.replace('_', ' ').toUpperCase()}!`, 'success');
                await this.updatePlayerInfo();
            } else {
                // Remove visual chip if bet failed
                this.playerBets[betType] -= amount;
                this.removeLastChipFromTable(betType, amount);
                this.showBanner(data.message, 'error');
            }
        } catch (error) {
            console.error('Bet error:', error);
            this.playerBets[betType] -= amount;
            this.removeLastChipFromTable(betType, amount);
            this.showBanner('Failed to place bet', 'error');
        }
    }

    addChipToTable(betType, amount, pointNumber = null) {
        // For come/dont_come bets that have moved to a point, display on that number
        let targetBetType = betType;
        if ((betType === 'come' || betType === 'dont_come') && pointNumber) {
            targetBetType = `place_${pointNumber}`;
        }

        const betArea = document.querySelector(`[data-bet="${targetBetType}"]`);
        if (!betArea) return;

        const container = betArea.querySelector('.bet-chips-container');
        if (!container) return;

        const chip = document.createElement('div');
        chip.className = 'placed-chip';

        // Set chip color based on value
        if (amount >= 500) chip.style.background = 'radial-gradient(circle, #9c27b0 0%, #6a1b9a 100%)';
        else if (amount >= 100) chip.style.background = 'radial-gradient(circle, #000 0%, #333 100%)';
        else if (amount >= 25) chip.style.background = 'radial-gradient(circle, #4caf50 0%, #2e7d32 100%)';
        else if (amount >= 10) chip.style.background = 'radial-gradient(circle, #2196f3 0%, #1565c0 100%)';
        else if (amount >= 5) chip.style.background = 'radial-gradient(circle, #ff5252 0%, #d32f2f 100%)';
        else chip.style.background = 'radial-gradient(circle, #fff 0%, #ddd 100%)';

        chip.textContent = `$${amount}`;

        // Add a special indicator for come bets that have moved
        if ((betType === 'come' || betType === 'dont_come') && pointNumber) {
            chip.style.border = '2px solid #ffd700';  // Gold border to distinguish come bets
            chip.title = `${betType.toUpperCase()} bet on ${pointNumber}`;
        }

        container.appendChild(chip);
    }

    removeLastChipFromTable(betType, amount) {
        const betArea = document.querySelector(`[data-bet="${betType}"]`);
        if (!betArea) return;

        const container = betArea.querySelector('.bet-chips-container');
        if (!container) return;

        // Remove the last chip that was added
        const chips = container.querySelectorAll('.placed-chip');
        if (chips.length > 0) {
            const lastChip = chips[chips.length - 1];
            // Verify it's the right amount before removing
            if (lastChip.textContent === `$${amount}`) {
                lastChip.remove();
            }
        }
    }

    async rollDice() {
        // Try WebSocket first
        if (this.useWebSocket && this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify({
                action: 'roll',
                player_id: this.playerId
            }));
            return;
        }

        // Fallback to HTTP API
        try {
            const response = await fetch(`${this.apiUrl}?action=roll`, {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ player_id: this.playerId })
            });

            const data = await response.json();

            if (data.success) {
                const roll = data.roll;
                this.animateDiceRoll(roll.roll.die1, roll.roll.die2, roll.roll.total);
                this.showBanner(roll.message, 'info');

                // Update state and player info after animation
                setTimeout(async () => {
                    await this.updateGameState();
                    await this.updatePlayerInfo();
                    await this.syncBetsWithServer();
                }, 1000);
            } else {
                this.showBanner(data.message, 'error');
            }
        } catch (error) {
            console.error('Roll error:', error);
            this.showBanner('Failed to roll dice', 'error');
        }
    }

    animateDiceRoll(die1Value, die2Value, total) {
        const die1 = document.getElementById('die1');
        const die2 = document.getElementById('die2');
        const totalDisplay = document.getElementById('dice-total');

        // Rolling animation
        die1.style.animation = 'none';
        die2.style.animation = 'none';
        setTimeout(() => {
            die1.style.animation = 'dice-roll 0.5s ease-out';
            die2.style.animation = 'dice-roll 0.5s ease-out';
        }, 10);

        // Show result after animation
        setTimeout(() => {
            this.renderDieFace(die1, die1Value);
            this.renderDieFace(die2, die2Value);
            totalDisplay.textContent = total;
            totalDisplay.style.animation = 'total-pop 0.3s ease-out';
        }, 500);
    }

    renderDieFace(dieElement, value) {
        const face = dieElement.querySelector('.die-face');
        face.innerHTML = '';

        const pipPositions = {
            1: [[1, 1]],
            2: [[0, 0], [2, 2]],
            3: [[0, 0], [1, 1], [2, 2]],
            4: [[0, 0], [0, 2], [2, 0], [2, 2]],
            5: [[0, 0], [0, 2], [1, 1], [2, 0], [2, 2]],
            6: [[0, 0], [0, 2], [1, 0], [1, 2], [2, 0], [2, 2]]
        };

        const positions = pipPositions[value] || [];
        positions.forEach(([row, col]) => {
            const pip = document.createElement('span');
            pip.className = 'pip';
            pip.style.gridArea = `${row + 1} / ${col + 1}`;
            face.appendChild(pip);
        });
    }


    async updateGameState() {
        try {
            const response = await fetch(`${this.apiUrl}?action=state`);
            const data = await response.json();

            if (data.success) {
                this.updateGameStateFromData(data.state);
            }
        } catch (error) {
            console.error('State update error:', error);
        }
    }

    updateGameStateFromData(state) {
        // Store state for timer reference
        this.lastGameState = state;

        // Update phase
        const phaseText = state.game.phase === 'come_out' ? 'COME OUT' : 'POINT';
        document.getElementById('game-phase').textContent = phaseText;

        // Update point
        const pointDisplay = document.getElementById('game-point');
        const hasPoint = state.game.point && state.game.phase === 'point';

        if (hasPoint) {
            pointDisplay.textContent = state.game.point;
            document.getElementById('point-marker').classList.add('active');
        } else {
            pointDisplay.textContent = 'OFF';
            document.getElementById('point-marker').classList.remove('active');
        }

        // Enable/disable odds bets based on point status
        const oddsAreas = document.querySelectorAll('[data-bet="pass_odds"], [data-bet="dont_pass_odds"]');
        const oddsSubtext = document.getElementById('odds-subtext');

        oddsAreas.forEach(area => {
            if (hasPoint) {
                area.classList.remove('disabled');
                area.style.opacity = '1';
                area.style.pointerEvents = 'auto';

                // Show max odds multiplier based on point
                const point = state.game.point;
                let maxOdds = '3x';
                if (point === 4 || point === 10) maxOdds = '3x';
                else if (point === 5 || point === 9) maxOdds = '4x';
                else if (point === 6 || point === 8) maxOdds = '5x';

                if (oddsSubtext) {
                    oddsSubtext.textContent = `Max ${maxOdds} odds - True odds payout`;
                }
            } else {
                area.classList.add('disabled');
                area.style.opacity = '0.3';
                area.style.pointerEvents = 'none';

                if (oddsSubtext) {
                    oddsSubtext.textContent = 'Point Required';
                }
            }
        });

        // Disable place bets on the current point number
        document.querySelectorAll('.place-bet').forEach(area => {
            const placeNumber = parseInt(area.dataset.number);
            if (hasPoint && placeNumber === state.game.point) {
                area.classList.add('disabled');
                area.style.opacity = '0.3';
                area.style.pointerEvents = 'none';
                area.title = 'Cannot place bet on the point - use Pass Line and Odds';
            } else {
                area.classList.remove('disabled');
                // Don't reset opacity/pointerEvents here, let timer logic handle it
                area.title = '';
            }
        });

        // Update shooter
        const shooter = state.players.find(p => p.id == state.shooter_id);
        document.getElementById('shooter-name').textContent = shooter ? shooter.name : '-';

        // Enable/disable roll button - convert both to strings for comparison
        const isShooter = String(state.shooter_id) === String(this.playerId);
        console.log('Shooter check:', { shooter_id: state.shooter_id, playerId: this.playerId, isShooter });
        document.getElementById('roll-btn').disabled = !isShooter;

        // Highlight shooter's bet areas
        if (isShooter) {
            document.querySelectorAll('.bet-area').forEach(area => area.classList.add('active'));
        } else {
            document.querySelectorAll('.bet-area').forEach(area => area.classList.remove('active'));
        }

        // Update players list
        this.updatePlayersList(state.players, state.shooter_id);

        // Update recent rolls
        this.updateRecentRolls(state.recent_rolls);

        // Update timer
        if (state.timer) {
            this.updateTimer(state.timer);
        }
    }

    updateTimer(timerInfo) {
        this.shooterTimeRemaining = timerInfo.shooter_time_remaining;
        this.bettingTimeRemaining = timerInfo.betting_time_remaining;
        this.bettingClosed = timerInfo.betting_closed;

        // Start client-side countdown if not already running
        if (!this.timerInterval) {
            this.startClientSideTimer();
        }

        // Update display immediately with server data
        this.updateTimerDisplay();
    }

    startClientSideTimer() {
        // Clear any existing timer
        if (this.timerInterval) {
            clearInterval(this.timerInterval);
        }

        // Count down every second
        this.timerInterval = setInterval(() => {
            // Decrement timers
            if (this.shooterTimeRemaining > 0) {
                this.shooterTimeRemaining--;
            }
            if (this.bettingTimeRemaining > 0) {
                this.bettingTimeRemaining--;
            } else if (!this.bettingClosed) {
                this.bettingClosed = true;
            }

            // Update display
            this.updateTimerDisplay();

            // If shooter time runs out, force a game state refresh
            if (this.shooterTimeRemaining <= 0) {
                this.updateGameState();
            }
        }, 1000);
    }

    updateTimerDisplay() {
        const timerDisplay = document.getElementById('timer-display');
        const timerLabel = document.getElementById('timer-label');
        const timerValue = document.getElementById('timer-value');

        if (!timerDisplay || !timerLabel || !timerValue) return;

        // Determine if player is shooter
        const gameState = this.getLastGameState();
        const isShooter = gameState && String(gameState.shooter_id) === String(this.playerId);

        if (isShooter) {
            // Show shooter timer (20 seconds)
            timerLabel.textContent = 'TIME TO ROLL';
            timerValue.textContent = Math.max(0, this.shooterTimeRemaining);

            // Apply color based on time remaining
            timerValue.classList.remove('warning', 'danger');
            if (this.shooterTimeRemaining <= 5) {
                timerValue.classList.add('danger');
            } else if (this.shooterTimeRemaining <= 10) {
                timerValue.classList.add('warning');
            }
        } else {
            // Show betting timer (15 seconds)
            timerLabel.textContent = 'TIME TO BET';
            timerValue.textContent = Math.max(0, this.bettingTimeRemaining);

            // Apply color based on time remaining
            timerValue.classList.remove('warning', 'danger');
            if (this.bettingTimeRemaining <= 0) {
                timerValue.classList.add('danger');
                timerLabel.textContent = 'BETTING CLOSED';
            } else if (this.bettingTimeRemaining <= 5) {
                timerValue.classList.add('danger');
            } else if (this.bettingTimeRemaining <= 8) {
                timerValue.classList.add('warning');
            }
        }

        // Handle betting area availability based on shooter status and timer
        if (!isShooter && this.bettingClosed) {
            // Non-shooter with betting closed
            document.querySelectorAll('.bet-area, .prop-bet, .place-bet').forEach(area => {
                area.style.pointerEvents = 'none';
                area.style.opacity = '0.5';
            });
        } else {
            // Shooter (always can bet) OR non-shooter with betting open
            document.querySelectorAll('.bet-area, .prop-bet, .place-bet').forEach(area => {
                area.style.pointerEvents = 'auto';
                area.style.opacity = '1';
            });
        }
    }

    getLastGameState() {
        // Store last game state for reference
        return this.lastGameState || null;
    }

    async updatePlayerInfo() {
        try {
            const response = await fetch(`${this.apiUrl}?action=player&player_id=${this.playerId}`);
            const data = await response.json();

            if (data.success) {
                const info = data.info;
                document.getElementById('player-bankroll').textContent =
                    parseFloat(info.player.bankroll).toFixed(2);

                // Update active bets
                this.updateActiveBets(info.active_bets);
            }
        } catch (error) {
            console.error('Player info error:', error);
        }
    }

    updatePlayersList(players, shooterId) {
        const list = document.getElementById('players-list');
        list.innerHTML = '';

        if (players.length === 0) {
            list.innerHTML = '<p style="text-align: center; opacity: 0.6;">No players yet</p>';
            return;
        }

        players.forEach(player => {
            const div = document.createElement('div');
            div.className = 'player-item';
            if (player.id == shooterId) div.classList.add('shooter');
            if (player.role === 'spectator') div.classList.add('spectator');

            const shooterIcon = player.id == shooterId ? '🎲 ' : '';
            const watchIcon = player.role === 'spectator' ? '👁️ ' : '';

            div.innerHTML = `
                <span>${shooterIcon}${watchIcon}${player.name}</span>
                <span class="chips-display">$${parseFloat(player.bankroll).toFixed(2)}</span>
            `;
            list.appendChild(div);
        });
    }

    updateRecentRolls(rolls) {
        const container = document.getElementById('log-container');
        container.innerHTML = '';

        if (rolls.length === 0) {
            container.innerHTML = '<p style="text-align: center; opacity: 0.6;">No rolls yet</p>';
            return;
        }

        rolls.forEach(roll => {
            const div = document.createElement('div');
            div.className = 'log-entry';
            const diceEmoji = '🎲';
            div.textContent = `${diceEmoji} ${roll.player_name}: ${roll.die1} + ${roll.die2} = ${roll.total}`;
            container.appendChild(div);
        });
    }

    updateActiveBets(bets) {
        const list = document.getElementById('active-bets-list');
        list.innerHTML = '';

        if (bets.length === 0) {
            list.innerHTML = '<p style="text-align: center; opacity: 0.6;">No active bets</p>';
            return;
        }

        bets.forEach(bet => {
            const div = document.createElement('div');
            div.className = 'bet-item';

            // Show point number for come/dont_come bets that have moved
            let betDisplay = bet.bet_type.replace(/_/g, ' ').toUpperCase();
            if ((bet.bet_type === 'come' || bet.bet_type === 'dont_come') && bet.point_number) {
                betDisplay += ` ON ${bet.point_number}`;
            }

            div.innerHTML = `
                <span>${betDisplay}</span>
                <span style="color: #d4af37; font-weight: bold;">$${parseFloat(bet.amount).toFixed(2)}</span>
            `;
            list.appendChild(div);
        });
    }

    async syncBetsWithServer() {
        try {
            const response = await fetch(`${this.apiUrl}?action=player&player_id=${this.playerId}`);
            const data = await response.json();

            if (data.success) {
                const serverBets = data.info.active_bets;

                // Clear all visual chips first
                this.clearAllVisualChips();

                // Rebuild from server state
                this.playerBets = {};
                serverBets.forEach(bet => {
                    const betType = bet.bet_type;
                    const amount = parseFloat(bet.amount);
                    const pointNumber = bet.point_number ? parseInt(bet.point_number) : null;

                    if (!this.playerBets[betType]) {
                        this.playerBets[betType] = 0;
                    }
                    this.playerBets[betType] += amount;

                    // Add visual chip back (with point_number if it's a moved come bet)
                    this.addChipToTable(betType, amount, pointNumber);
                });
            }
        } catch (error) {
            console.error('Sync bets error:', error);
        }
    }

    clearAllVisualChips() {
        document.querySelectorAll('.bet-chips-container').forEach(container => {
            container.innerHTML = '';
        });
    }

    showBanner(message, type = 'info') {
        const banner = document.getElementById('message-banner');
        banner.textContent = message;
        banner.classList.remove('hidden');

        // Auto-hide after 3 seconds
        setTimeout(() => {
            banner.classList.add('hidden');
        }, 3000);
    }

    startPolling() {
        // Initial update
        this.updateGameState();
        this.updatePlayerInfo();

        // Poll every 2 seconds
        this.pollingInterval = setInterval(() => {
            this.updateGameState();
            this.updatePlayerInfo();
        }, 2000);
    }

    stopPolling() {
        if (this.pollingInterval) {
            clearInterval(this.pollingInterval);
        }
    }
}

// Add CSS animations dynamically
const style = document.createElement('style');
style.textContent = `
    @keyframes dice-roll {
        0% { transform: rotate(0deg) scale(1); }
        25% { transform: rotate(90deg) scale(1.2); }
        50% { transform: rotate(180deg) scale(1); }
        75% { transform: rotate(270deg) scale(1.2); }
        100% { transform: rotate(360deg) scale(1); }
    }

    @keyframes total-pop {
        0% { transform: scale(0.5); opacity: 0; }
        50% { transform: scale(1.2); }
        100% { transform: scale(1); opacity: 1; }
    }
`;
document.head.appendChild(style);

// Initialize game when page loads
document.addEventListener('DOMContentLoaded', () => {
    new CrapsGame();
});
