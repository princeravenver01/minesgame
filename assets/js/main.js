document.addEventListener('DOMContentLoaded', () => {
    // --- DOM Element Selection ---
    const grid = document.getElementById('game-grid');
    const startGameBtn = document.getElementById('start-game-btn');
    const cashoutBtn = document.getElementById('cashout-btn');
    const betAmountInput = document.getElementById('bet-amount');
    const bombSelect = document.getElementById('bomb-select');
    const userBalanceSpan = document.getElementById('user-balance'); // Header balance (still used)

    // Winnings Display Span (ensure this element exists in game.php now, likely inside .bet-amount-input-area or .cashout-area)
    const winningsDisplaySpan = document.getElementById('current-winnings-amount'); // Correct winnings display span


    const multiplierListHorizontal = document.getElementById('multiplier-list');
    const autoSelectBtn = document.getElementById('auto-select-btn');
    const statsCoinsCountSpan = document.getElementById('stats-coins-count');
    const statsBombsCountSpan = document.getElementById('stats-bombs-count');


    // --- REMOVED: UI Elements for Withdrawal ---
    // const withdrawAmountInput = document.getElementById('withdraw-amount');
    // const gcashNumberInput = document.getElementById('gcash-number');
    // const gcashNameInput = document.getElementById('gcash-name');
    // const submitWithdrawalBtn = document.getElementById('submit-withdrawal-btn');
    // const playerWithdrawalHistoryTableBody = document.querySelector('#player-withdrawal-history-table tbody');
    // const withdrawalStatusDiv = document.getElementById('withdrawal-status'); // Assuming you add a status message area


    // --- Re-added UI Elements (Keep) ---
    const promoBannerText = document.getElementById('jackpot-banner-text');
    const gameplayFeedList = document.getElementById('gameplay-feed-list'); // Renamed from recent-wins-list
    const topWinnersList = document.getElementById('top-winners-list');
    // --- End Re-added ---


    // --- Modal Elements (Keep) ---
    const modalOverlay = document.getElementById('modal-overlay');
    const modalTitle = document.getElementById('modal-title');
    const modalMessage = document.getElementById('modal-message');
    const modalCloseBtn = document.getElementById('modal-close-btn'); // Fix: Should be getElementById
    // --- End Modal Elements ---


    // --- State Variables ---
    const TILE_COUNT = 25;
    let inGame = false;
    let currentHits = 0;
    let currentMultiplier = 1.00;
    let currentWinnings = 0.00; // This will store the *calculated* winnings before cashout
    let currentBetAmount = 10.00; // Store the current bet amount


    // --- Constants ---
     const MAX_BET = 10000; // Assuming MAX_BET is needed for bet validation
     const MIN_BET = 1; // Assuming MIN_BET is needed for bet validation
     // REMOVED: Withdrawal constants (moved to withdrawal.js)
     // const MIN_WITHDRAWAL = 100; // Example minimum withdrawal amount
     // const MAX_WITHDRAWAL = 100000; // Example maximum withdrawal amount
     // Jackpot Challenge Requirements (Hardcoded for now)
     const JACKPOT_CHALLENGE_HITS = 10;
     const JACKPOT_CHALLENGE_BOMBS = 22;
     const JACKPOT_CHALLENGE_BET = 3.00; // Use float for comparison
    // --- End Constants ---


    // --- Custom Modal Function (Keep) ---
    function showCustomModal(title, message, type = 'info', buttonText = 'Continue', onclose = null) {
        modalTitle.textContent = title;
        modalMessage.innerHTML = message;
        modalCloseBtn.textContent = buttonText;
        modalOverlay.className = ''; // Reset classes first
        modalOverlay.classList.add('modal-overlay', `modal-${type}`); // Add base and type classes
        modalOverlay.classList.remove('hidden'); // Make visible

         modalCloseBtn.removeEventListener('click', handleModalClose); // Remove old listener
         modalCloseBtn.addEventListener('click', handleModalClose); // Add new listener

         modalOverlay._customCloseHandler = onclose; // Store custom close handler
    }

    function handleModalClose() {
         modalOverlay.classList.add('hidden'); // Hide modal overlay
         if (modalOverlay._customCloseHandler) {
             // Execute custom handler if it exists
             modalOverlay._customCloseHandler();
             modalOverlay._customCloseHandler = null; // Clear handler
         }
         // If game is not active after modal close, perform a full UI reset
         if (!inGame) {
            setGameState(false, true); // Transition to non-playing state with full reset
         }
    }

    // REMOVED: Helper to display withdrawal status messages (moved to withdrawal.js)
    // function showWithdrawalStatus(message, type = 'info') { ... }
    // function hideWithdrawalStatus() { ... }


    // --- Core Game Flow Functions (Keep previous logic) ---

    async function startGame() {
        if (inGame) return; // Prevent starting if already in game
        const bet = parseFloat(betAmountInput.value);
        if (isNaN(bet) || bet < MIN_BET || bet > MAX_BET) { // Validate against MIN/MAX
            showCustomModal('Invalid Bet', `Please enter a bet between ${MIN_BET.toFixed(2)} and ${MAX_BET.toFixed(2)}.`, 'info', 'OK');
            return;
        }
         const bombs = parseInt(bombSelect.value);
        if (isNaN(bombs) || bombs < 1 || bombs > 24) {
             showCustomModal('Invalid Bomb Count', 'Please select a valid number of bombs (1-24).', 'info', 'OK');
             return;
         }

        currentBetAmount = bet; // Store the bet amount for this round

        const formData = new FormData();
        formData.append('action', 'start');
        formData.append('bet', bet);
        formData.append('bombs', bombs); // Send the selected bombs to the backend
        try {
            const response = await fetch('api/game_logic.php', { method: 'POST', body: formData });
            if (!response.ok) {
                const errorText = await response.text();
                throw new Error(`Server responded with status ${response.status}. Detail: ${errorText}`);
            }
            const data = await response.json();

            if (data.error) {
                showCustomModal('Error', data.error || 'Something went wrong!', 'loss', 'OK');
                 setGameState(false, true); // Reset UI immediately on start error
            } else if (data.success) {
                // Game started successfully
                currentHits = 0;
                currentMultiplier = 1.00; // Reset multiplier
                currentWinnings = 0.00; // Reset winnings
                // updateWinningsDisplay() is called by setGameState(true, false) below

                // Update balances displayed on the page (Header balance only)
                userBalanceSpan.textContent = data.new_balance;

                // Call setGameState(true, false) to make tiles clickable and update UI
                // This transitions to playing state and does NOT reset the board (as it was just built blank)
                setGameState(true, false);

                 // Update bomb stat display with the *selected* number
                 const currentBombs = parseInt(bombSelect.value);
                 updateStatsDisplay(currentHits, currentBombs);

                 // Highlight the '1 Hit' multiplier as the first target
                 highlightNextHit();


            } else {
                 showCustomModal('Game Error', 'Received unexpected response from server when starting game.', 'loss', 'OK');
                 console.error("Unexpected start game response:", data);
                 setGameState(false, true); // Reset UI on unexpected start response
            }
        } catch (error) {
            console.error('Error starting game:', error);
            showCustomModal('Network Error', 'Could not connect to the server or process response. Details: ' + error.message, 'loss', 'OK');
            setGameState(false, true); // Reset UI on network error
        }
    }

    async function handleTileClick(index) {
        const tile = grid.querySelector(`[data-index='${index}']`);
        // Prevent clicks if not in game, tile is already disabled, or modal is open
        if (!inGame || tile.classList.contains('disabled') || !modalOverlay.classList.contains('hidden')) return;

        // Visually disable the tile immediately to prevent double-clicking
        tile.classList.add('disabled');

        const formData = new FormData();
        formData.append('action', 'reveal');
        formData.append('tile_index', index);

        try {
            const response = await fetch('api/game_logic.php', { method: 'POST', body: formData });
             if (!response.ok) {
                 const errorText = await response.text();
                 throw new Error(`Server responded with status ${response.status}. Detail: ${errorText}`);
             }
            const data = await response.json();

            if (data.error) {
                 showCustomModal('Error', data.error, 'loss', 'OK', () => {
                     setGameState(false, true); // Reset game on error
                 });
                 console.error("Reveal error:", data.error);
                 return;
            }

            // Process successful reveal response
            if (data.outcome === 'coin') {
                // Player hit a coin
                tile.classList.add('revealed-coin'); // Add coin class
                tile.innerHTML = `<img src="assets/images/coin.png" alt="Coin">`; // Add coin image
                // Keep tile disabled? No, remove disabled after successful reveal to allow potential re-click if needed (though not in Mines)
                // The disabled state is managed by setGameState and inGame flag. Let's remove disabled here.
                 tile.classList.remove('disabled'); // Make it visually active but unclickable by game logic

                currentHits = data.hits; // Update hits count from server response
                currentMultiplier = data.multiplier; // Update multiplier from server
                currentWinnings = data.winnings; // Update *current* winnings from server
                updateWinningsDisplay(); // Update the winnings display span and cashout button state

                // Update stats display
                 const currentBombs = parseInt(bombSelect.value); // Get selected bombs from dropdown
                 updateStatsDisplay(currentHits, currentBombs);

                 // Highlight the *current* multiplier payout after hitting a coin
                highlightNextHit();


            } else if (data.outcome === 'bomb') {
                // Player hit a bomb (game over loss)

                 // Set game state to not playing, but *without* resetting the board yet
                setGameState(false, false);

                 // Log received data
                 console.log(`Tile ${index}: Bomb hit. Server sent outcome: bomb. Board data received: `, data.board);

                 // Always reveal full board on bomb hit, and ensure the clicked tile is a bomb
                 if (data.board) { // Server should always send the board on loss now
                    // Use the board data from the server to reveal the board state.
                    // The server now ensures the clicked tile is marked as bomb in data.board on loss.
                    revealFullBoard(data.board);
                 } else {
                     // This else block should ideally not be hit if the PHP is updated correctly
                     console.error("Bomb outcome received but no board data! Cannot reveal full board.");
                      // Fallback: Manually ensure the clicked tile is marked as bomb and others disabled
                     const clickedTile = grid.querySelector(`[data-index='${index}']`);
                     if (clickedTile) {
                         clickedTile.classList.remove('revealed-coin');
                         clickedTile.classList.add('revealed-bomb', 'disabled');
                         clickedTile.innerHTML = '';
                         clickedTile.style.opacity = '';
                     }
                     // Ensure all other tiles are disabled
                     grid.querySelectorAll('.tile').forEach(tile => {
                         if (parseInt(tile.dataset.index) !== index) {
                              tile.classList.add('disabled');
                         }
                     });
                 }

                 if (data.new_balance !== undefined) {
                     // Update balance in UI after the loss (Header balance only)
                     userBalanceSpan.textContent = data.new_balance;
                 }

                 currentWinnings = 0.00; // Reset winnings on loss
                 updateWinningsDisplay(); // Set winnings display to 0 on loss and disable button


                // Show BOOM modal after a slight delay
                setTimeout(() => {
                    showCustomModal('BOOM!', 'You hit a bomb! Game over.', 'loss', 'Play Again', () => {
                         // This function runs AFTER the modal is closed
                         fetchGameUpdates(); // Refresh feed/leaderboard after modal closes
                         // setGameState(false, true) is called by handleModalClose if !inGame
                    });
                }, 800); // Delay matches CSS transition

            } else if (data.outcome === 'win_cleared') {
                // Player cleared the board (game over win)
                setGameState(false, false); // End game state, don't reset yet (so revealFullBoard can show the result)

                 // Log the received board data on win_cleared
                 console.log(`Tile ${index}: Board cleared win. Server sent outcome: win_cleared. Board data received: `, data.board);
                 if (data.board) {
                     // Server sent the full board state (should all be coins and original bombs), reveal it
                      revealFullBoard(data.board);
                 } else {
                      console.error("Win cleared outcome received but no board data on board cleared win.");
                      // Fallback: try to manually reveal all as coins? Not ideal. Log error and just leave as is.
                 }

                currentMultiplier = data.multiplier; // Final multiplier from server
                currentWinnings = data.final_winnings; // Final winnings from server
                updateWinningsDisplay(); // Update winnings display with final amount and disable button

                 if (data.new_balance !== undefined) {
                     // Update balance after win (Header balance only)
                     userBalanceSpan.textContent = data.new_balance;
                 }

                let modalMessage = `You cleared the board! You won <strong>${parseFloat(data.final_winnings).toFixed(2)}</strong> coins!`;
                let modalType = 'win';

                 if (data.challenge_bonus !== undefined && parseFloat(data.challenge_bonus) > 0) {
                      modalMessage += `<br>PLUS a special Jackpot bonus of <strong>${parseFloat(data.challenge_bonus).toFixed(2)}</strong> coins!`;
                 }

                // Show win modal after a slight delay
                setTimeout(() => {
                    showCustomModal('Board Cleared!', modalMessage, modalType, 'Awesome!', () => {
                         // This function runs AFTER the modal is closed
                         fetchGameUpdates(); // Refresh feed/leaderboard after modal closes
                         // setGameState(false, true) is called by handleModalClose if !inGame
                    });
                }, 800);

            } else {
                // Unexpected outcome from server
                showCustomModal('Game Error', data.message || 'An unexpected error occurred during tile reveal.', 'loss', 'OK', () => {
                     // This function runs AFTER the modal is closed
                     setGameState(false, true); // Reset game on unexpected error
                });
                console.error("Unexpected game logic response:", data);
            }
        } catch (error) {
            // Handle network or server errors during reveal
            console.error('Error revealing tile:', error);
            showCustomModal('Network Error', 'Could not connect to the server or process response. Details: ' + error.message, 'loss', 'OK', () => {
                 // This function runs AFTER the modal is closed
                 setGameState(false, true); // Reset game on network error
            });
        }
    }

    async function cashOut() {
        // Prevent cashout if conditions aren't met (inGame, button disabled by state)
        if (!inGame || cashoutBtn.disabled) {
             // This is a safeguard, the button should be disabled via JS already
             console.warn("Attempted to cash out while button is disabled.");
             return;
         }

         cashoutBtn.disabled = true; // Ensure button stays disabled during API call

        const formData = new FormData();
        formData.append('action', 'cashout');
        try {
            const response = await fetch('api/game_logic.php', { method: 'POST', body: formData });
             if (!response.ok) {
                 const errorText = await response.text();
                 throw new Error(`Server responded with status ${response.status}. Detail: ${errorText}`);
             }
            const data = await response.json();

            if (data.success) {
                setGameState(false, false); // End game state, don't reset board (it just stays as is)

                const winningsFormatted = `<strong>${parseFloat(data.winnings).toFixed(2)}</strong> coins!`;
                 let modalMessage = `Congratulations! You cashed out for ${winningsFormatted}`;
                 let modalType = 'win';

                 if (data.challenge_bonus !== undefined && parseFloat(data.challenge_bonus) > 0) {
                      modalMessage += `<br>PLUS a special Jackpot bonus of <strong>${parseFloat(data.challenge_bonus).toFixed(2)}</strong> coins!`;
                 }

                // Show cashout win modal
                showCustomModal('Cashed Out!', modalMessage, modalType, 'Awesome!', () => {
                     // This function runs AFTER the modal is closed
                     fetchGameUpdates(); // Refresh feed/leaderboard after modal closes
                     // setGameState(false, true) is called by handleModalClose if !inGame
                });

                // Update balances in UI (Header balance only)
                userBalanceSpan.textContent = data.new_balance;

                currentWinnings = 0.00; // Reset winnings on cashout success
                updateWinningsDisplay(); // Update winnings display to 0 on cashout success and disable button

            } else {
                // Handle server-side errors during cashout
                showCustomModal('Error', data.error || 'Could not cash out.', 'loss', 'OK');
                // On cashout failure, the game is technically still active on the backend until refresh.
                // It's safer to fully reset the state and require a new game.
                setGameState(false, true); // Full reset on cashout error
            }
        } catch (error) {
            // Handle network or server errors during cashout
            console.error('Error cashing out:', error);
            showCustomModal('Network Error', 'A network error occurred during cashout. Details: ' + error.message, 'loss', 'OK', () => {
                 // This function runs AFTER the modal is closed
                 setGameState(false, true); // Reset game on network error
            });
        }
    }


    // --- UI, State, and Helper Functions ---

    // Function to update the winnings display span and cashout button state
    // Called after every coin reveal, game end, cashout, and setGameState
    function updateWinningsDisplay(amount = currentWinnings) {
        // Ensure amount is a number and format it to 2 decimal places
        const formattedAmount = parseFloat(amount).toFixed(2);

        // Update the text content of the winnings display span
        // Use '0.00' if the formatted amount is invalid (NaN)
        winningsDisplaySpan.textContent = isNaN(formattedAmount) ? '0.00' : formattedAmount;

         // FIX: Update currentWinnings state variable if amount is explicitly passed
         // This ensures the state reflects the exact value being displayed and used for cashout checks
         if (typeof amount === 'number') { // Only update if the passed value is a number
              currentWinnings = amount;
         } else if (typeof amount === 'string') {
              // Try to parse string amount if necessary, though function is designed to take number
              const parsedAmount = parseFloat(amount);
              if (!isNaN(parsedAmount)) {
                   currentWinnings = parsedAmount;
              }
         } else {
              // If amount is not provided or not a number, use the existing currentWinnings
         }


         // Update cashout button disabled state whenever winnings update
         // Button should be enabled only if the game is active AND currentWinnings > 0
         // We also check currentHits > 0 as a redundancy, though winnings > 0 implies hits > 0
         cashoutBtn.disabled = !inGame || currentWinnings <= 0 || currentHits === 0;
    }


    // setGameState(isPlaying, doReset)
    // isPlaying: boolean - true if game is starting, false if ending
    // doReset: boolean - true if board should be reset (tiles cleared), false otherwise (e.g., show end-of-game state)
    function setGameState(isPlaying, doReset = true) {
        inGame = isPlaying; // Update game state flag

        // Set state of control buttons/inputs
        startGameBtn.disabled = isPlaying; // Disable Start if playing
        // Cashout button disabled state is now primarily managed by updateWinningsDisplay

        betAmountInput.disabled = isPlaying; // Disable bet input while playing
        bombSelect.disabled = isPlaying; // Disable bomb select while playing
         autoSelectBtn.disabled = isPlaying; // Disable auto select while playing


        if (doReset) {
            // If performing a full reset (start of new game, error recovery, modal close after game end)
            resetBoard(); // Clear and redraw initial blank tiles

            // Reset internal game state variables
            currentHits = 0; // Reset hits
            currentMultiplier = 1.00; // Reset multiplier
            currentWinnings = 0.00; // Reset winnings
            // updateWinningsDisplay() is called below this if/else block to ensure UI sync

            // Update stats display to initial state (0 hits, selected bombs)
             const currentBombs = parseInt(bombSelect.value); // Get selected bombs from dropdown
             updateStatsDisplay(currentHits, currentBombs); // Display 0 hits and selected bombs

            updateMultiplierList(); // Update payout list based on selected bombs
        }
        // else: game ended without full reset (e.g., bomb hit, cashout)
        // Tiles are handled below, winnings/hits are updated by handleTileClick/cashOut


        // Explicitly manage tile disabled state and appearance based on isPlaying
        // This loop runs AFTER resetBoard (if doReset is true) or just updates existing tiles (if doReset is false)
        grid.querySelectorAll('.tile').forEach(tile => {
            if (isPlaying) {
                // If transitioning to playing state:
                // Remove disabled class to make tiles clickable
                tile.classList.remove('disabled');
                // Also remove any previous game end states from the tiles
                 tile.classList.remove('revealed-coin', 'revealed-bomb');
                 tile.innerHTML = ''; // Clear any previous image content
                 tile.style.opacity = ''; // Reset any custom opacity
            } else {
                 // If transitioning to not playing state:
                 // Add disabled class to make tiles unclickable
                 // This ensures tiles are not clickable after game ends (bomb/cashout)
                 // and also on initial load before game starts.
                 tile.classList.add('disabled');
                 // Do NOT clear revealed state here, that's revealFullBoard's job on loss/clear
             }
        });

        // Always update winnings display and cashout button state after setGameState finishes
        // This ensures the correct initial state (0.00 winnings, button disabled) is shown,
        // and that the button state correctly reflects winnings and isPlaying flag.
        updateWinningsDisplay();
        // Highlight should reflect the state transition (0 hits when starting/resetting)
        highlightNextHit();
    }

    // Clears the grid and draws new blank tiles
    function resetBoard() {
        grid.innerHTML = ''; // Clear existing tiles
        for (let i = 0; i < TILE_COUNT; i++) {
            const tile = document.createElement('div');
            tile.classList.add('tile');
             // When initially creating tiles during a full reset, do *not* add disabled here.
             // The loop *after* the if/else in setGameState handles adding disabled if !inGame.
            tile.dataset.index = i; // Store index
            // Add click listener
            tile.addEventListener('click', () => handleTileClick(i));
            grid.appendChild(tile); // Add tile to grid
        }
        console.log("Board reset complete.");
    }

     /**
      * Reveals the full board state at the end of a LOSS or BOARD CLEAR WIN round.
      * Called with the board array received from the server.
      * The server now ensures the board data accurately reflects the outcome for the clicked tile on loss.
      * @param {Array<string>} boardData - The array of tile types ('coin' or 'bomb') for all 25 tiles as determined by the server.
      */
    function revealFullBoard(boardData) { // Removed clickedIndex parameter logic
         // Ensure boardData is an array and has 25 elements before proceeding
         if (!Array.isArray(boardData) || boardData.length !== TILE_COUNT) {
             console.error("Invalid board data received for revealFullBoard:", boardData);
             return;
         }

        grid.querySelectorAll('.tile').forEach((tile, index) => {
            // All tiles should be disabled after game ends. This is handled by setGameState(false, ...)
            // Ensure state is clean before revealing based on server data
            tile.classList.remove('revealed-coin', 'revealed-bomb', 'disabled');
            tile.innerHTML = '';
            tile.style.opacity = '';

            const tileContent = boardData[index]; // Get the actual content from server data

            // Reveal the tile content based *only* on the server-provided board data.
            // The server is now responsible for ensuring the clicked tile on a loss is marked as 'bomb' in this data.
            if (tileContent === 'bomb') {
                tile.classList.add('revealed-bomb'); // Add the bomb class (CSS adds background image)
            } else { // tileContent === 'coin'
                tile.classList.add('revealed-coin'); // Add the coin class (CSS adds green background)
                tile.innerHTML = `<img src="assets/images/coin.png" alt="Coin">`; // Add the coin image
            }

            // Add disabled back AFTER setting the revealed class, as game is over
            tile.classList.add('disabled');
        });
         console.log("Full board reveal complete based on server data.");

         // Optional: You could still use the clickedIndex from the server response
         // to add a *visual marker* or animation to the clicked tile on loss,
         // but not to override its content. The server already set the content.
         // Example (requires sending clicked_index_on_loss from PHP and handling it here):
         /*
         if (data.clicked_index_on_loss !== undefined) {
             const clickedTile = grid.querySelector(`[data-index='${data.clicked_index_on_loss}']`);
             if (clickedTile) {
                 // Add a distinct class for styling the specific bomb that was clicked
                 clickedTile.classList.add('clicked-bomb');
                 // You might also scroll to this tile
                 clickedTile.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
             }
         }
         */
    }

    /**
     * Updates the display of Coins hit and Bombs selected next to the grid.
     * @param {number} hits - The current number of coins revealed.
     * @param {number} bombs - The total number of bombs selected for the round.
     */
    function updateStatsDisplay(hits, bombs) {
         console.log(`Updating stats display: Hits = ${hits}, Bombs = ${bombs}`);
         statsCoinsCountSpan.textContent = hits;
         statsBombsCountSpan.textContent = bombs;
    }


    // Highlight the next multiplier based on current hits
    function highlightNextHit() {
        // Remove highlight from all multiplier items first
        multiplierListHorizontal.querySelectorAll('.multiplier-item').forEach(item => {
            item.classList.remove('next-hit');
             item.style.backgroundColor = ''; // Remove if class handles background
        });

        // Highlight the *current* hits (or 1 hit if game is active and hits is 0)
        let targetHit = 0;
        if (inGame) {
             if (currentHits > 0) {
                 targetHit = currentHits; // Highlight the payout for the current number of hits
             } else {
                 targetHit = 1; // Highlight the payout for the first hit when game starts
             }
        } else {
             // Not in game (initial load or after game ends), show the first payout (1 Hit) as potential start
             targetHit = 1; // Highlight the potential first hit payout
        }

        if (targetHit > 0) {
            const targetHitItem = multiplierListHorizontal.querySelector(`.multiplier-item[data-hits='${targetHit}']`);
            if (targetHitItem) {
                targetHitItem.classList.add('next-hit'); // Add highlight class
                // Scroll the list horizontally to bring the item into view
                targetHitItem.scrollIntoView({ behavior: 'smooth', inline: 'center', block: 'nearest' });
                console.log(`Highlighting target hit: ${targetHit}`);
            } else {
                console.log(`No multiplier item found for target hit: ${targetHit}`);
            }
        } else {
             console.log("Highlight target is 0 or less, skipping highlight.");
        }
    }

    // Simple obfuscation for usernames in feeds
    function obfuscateUsername(name) {
        if (!name) return '***';
        if (name.length <= 3) return name.substring(0, 1) + '***';
        // Reveal first two and last character, obfuscate middle
        return name.substring(0, 2) + '*'.repeat(Math.max(0, name.length - 3)) + name.slice(-1);
    }


    /**
     * Fetches game updates (feed, leaderboard, jackpot) from the API.
     */
    async function fetchGameUpdates() {
        try {
            const response = await fetch('api/get_game_updates.php');
             if (!response.ok) {
                const errorDetail = await response.text();
                throw new Error(`Server responded with status ${response.status}. Detail: ${errorDetail}`);
            }
            const data = await response.json();

             if (data.error) {
                 console.error("API error fetching game updates:", data.error);
                 gameplayFeedList.innerHTML = '<li class="feed-loss">Error loading feed.</li>';
                 topWinnersList.innerHTML = '<li class="leaderboard-item">Error loading leaderboard.</li>';
                 promoBannerText.textContent = 'Error loading jackpot info.';
                 promoBannerText.classList.remove('challenge-completed');
                 // Also reset banner parent styling
                 const bannerElement = promoBannerText.parentElement;
                 if(bannerElement) bannerElement.classList.remove('challenge-completed-banner');
                 return;
             }

            updateGameplayFeed(data.feed);
            updateTopWinners(data.top_winners);
            // FIX: Corrected typo from 'jackot_info' to 'jackpot_info'
            updateJackpotBanner(data.jackpot_info);
            // REMOVED: Call to fetchPlayerWithdrawalHistory() from here

        } catch (error) {
            console.error('Failed to fetch game updates:', error);
             gameplayFeedList.innerHTML = '<li class="feed-loss">Network error loading feed.</li>';
             topWinnersList.innerHTML = '<li class="leaderboard-item">Network error loading leaderboard.</li>';
             promoBannerText.textContent = 'Network error loading jackpot info.';
             promoBannerText.classList.remove('challenge-completed');
              // Also reset banner parent styling
             const bannerElement = promoBannerText.parentElement;
             if(bannerElement) bannerElement.classList.remove('challenge-completed-banner');
        }
    }

    /**
     * Populates the Gameplay Feed list.
     * @param {Array<Object>} feedData - Array of recent game outcomes.
     */
    function updateGameplayFeed(feedData) {
        gameplayFeedList.innerHTML = ''; // Clear current list
        if (feedData && Array.isArray(feedData) && feedData.length > 0) {
            feedData.forEach(item => {
                const li = document.createElement('li');
                const username = obfuscateUsername(item.username);
                // Ensure amounts are floats before formatting
                const betAmount = parseFloat(item.bet_amount);
                const profitLoss = parseFloat(item.profit_loss);


                let text = `${username} bet ${betAmount.toFixed(2)}`;
                let className = 'feed-item';

                if (item.outcome === 'win') {
                    // Display profit as positive win amount for the feed
                    text += ` and won ${Math.abs(profitLoss).toFixed(2)} coins!`;
                    className += ' feed-win';
                } else { // outcome === 'loss'
                    text += ` and lost.`;
                     className += ' feed-loss';
                }

                li.textContent = text;
                li.className = className;
                gameplayFeedList.appendChild(li); // Add to list
            });
        } else {
             gameplayFeedList.innerHTML = '<li>No recent games yet.</li>';
        }
    }

    /**
     * Populates the Top Winners list.
     * @param {Array<Object>} winnersData - Array of top winners (username, profit_amount).
     */
    function updateTopWinners(winnersData) {
        topWinnersList.innerHTML = ''; // Clear current list
         if (winnersData && Array.isArray(winnersData) && winnersData.length > 0) {
             // Sort by profit amount descending
             winnersData.sort((a, b) => parseFloat(b.profit_amount) - parseFloat(a.profit_amount));

             // Limit to top 3 explicitly
             winnersData = winnersData.slice(0, 3);

             winnersData.forEach((winner, index) => {
                 const li = document.createElement('li');
                 const rank = index + 1;
                 const username = obfuscateUsername(winner.username);
                 const profit = parseFloat(winner.profit_amount).toFixed(2); // Format profit

                 li.innerHTML = `<strong>#${rank}:</strong> ${username} won <span class="win-amount">${profit}</span> coins`;
                 li.classList.add('leaderboard-item');
                 // Add special class for top ranks
                 if (rank === 1) li.classList.add('leaderboard-gold');
                 else if (rank === 2) li.classList.add('leaderboard-silver');
                 else if (rank === 3) li.classList.add('leaderboard-bronze');

                 topWinnersList.appendChild(li); // Add to list
             });
         } else {
             topWinnersList.innerHTML = '<li>No top winners yet.</li>';
         }
    }

     /**
      * Updates the promotional banner text with dynamic jackpot info.
      * @param {Object} jackpotInfo - Jackpot data from the API.
      */
     function updateJackpotBanner(jackpotInfo) {
         const bannerElement = promoBannerText.parentElement; // Get the parent div (.promo-banner)
         if (jackpotInfo && bannerElement) {
             // Check if the 'jackpot_info' property exists and is not null/empty
             if (Object.keys(jackpotInfo).length === 0 && jackpotInfo.constructor === Object) {
                  // Handle case where jackpot info is not available or empty
                 promoBannerText.textContent = 'Special challenge info loading...'; // Default message
                 // bannerElement.style.display = 'none'; // Hide banner if no info? Or show default? Let's show default.
                 promoBannerText.classList.remove('challenge-completed');
                 if(bannerElement) bannerElement.classList.remove('challenge-completed-banner');
                 return;
             }

             const prize = parseFloat(jackpotInfo.prize).toFixed(2);
             const description = jackpotInfo.challenge_description;
             const winnerLimit = parseInt(jackpotInfo.winner_limit); // Parse as int
             const currentWinners = parseInt(jackpotInfo.current_winners); // Parse as int
             const isAvailable = jackpotInfo.is_available; // Boolean or string '1'/'0' from PHP? Treat as truthy/falsy


             let text = '';
             let isCompleted = false;

             // Assuming is_available comes as boolean or '1'/'0'
             if (isAvailable && isAvailable !== '0') {
                 // Check if limit is reached (if limit is > 0)
                 if (winnerLimit > 0 && currentWinners >= winnerLimit) {
                      text = `🏆 Challenge completed! Stay tuned for new challenges.`;
                      isCompleted = true;
                 } else {
                      text = `${description} to receive a prize of <strong>${prize}</strong> coins!`;
                      if (winnerLimit > 0) {
                           text += ` (${winnerLimit - currentWinners} spots left!)`;
                      }
                 }
             } else {
                 // If is_available is false/0 or not provided, assume challenge is not active/completed
                 text = `🏆 Special challenge is not active. Stay tuned for new challenges.`;
                 isCompleted = true; // Mark as completed/inactive state for styling
             }

             promoBannerText.innerHTML = text; // Set the text (allows HTML like <strong>)

             // Update styling based on completion status
             if (isCompleted) {
                 if(bannerElement) bannerElement.classList.add('challenge-completed-banner'); // Apply styling to the banner div
                 promoBannerText.classList.add('challenge-completed'); // Apply styling to the text span/p
             } else {
                  if(bannerElement) bannerElement.classList.remove('challenge-completed-banner');
                  promoBannerText.classList.remove('challenge-completed');
             }

             // Ensure banner is visible if info is loaded, even if completed
             if(bannerElement) bannerElement.style.display = 'flex';


         } else {
             // Handle case where jackpotInfo itself is null or undefined
             console.error("No jackpot info received or jackpotInfo is null:", jackpotInfo);
             promoBannerText.textContent = 'Error loading jackpot info.';
             if(bannerElement) {
                 bannerElement.classList.remove('challenge-completed-banner');
                 promoBannerText.classList.remove('challenge-completed');
                 // Keep banner visible but show error? Or hide? Let's show error.
                 bannerElement.style.display = 'flex';
             }
         }
     }


    /**
     * Fetches and displays multipliers for the currently selected bomb count.
     * Populates the horizontal multiplier list.
     */
    async function updateMultiplierList() {
        const bombCount = bombSelect.value;
        try {
            const response = await fetch(`api/get_multipliers.php?bombs=${bombCount}`);
             if (!response.ok) {
                 const errorDetail = await response.text();
                 throw new Error(`Network response was not ok. Status: ${response.status}, Details: ${errorDetail}`);
             }
            const multipliers = await response.json();

             if (multipliers.error) {
                 console.error("API error fetching multipliers:", multipliers.error);
                 multiplierListHorizontal.innerHTML = '<div class="multiplier-item" style="color:red;">Error loading payouts.</div>';
                 return;
             }

            multiplierListHorizontal.innerHTML = ''; // Clear current list

            if (multipliers.length > 0) {
                multipliers.forEach(m => {
                    const item = document.createElement('div');
                    item.classList.add('multiplier-item');
                    item.dataset.hits = m.hit; // Store hit count as data attribute
                    item.innerHTML = `
                        <div class="mult">${parseFloat(m.multiplier).toFixed(2)}x</div>
                        <div class="hits">${m.hit} Hit</div>
                    `;
                    multiplierListHorizontal.appendChild(item); // Add to list
                });
            } else {
                multiplierListHorizontal.innerHTML = '<div class="multiplier-item">No payouts available.</div>';
            }
            highlightNextHit(); // Update highlight after list is rebuilt
        } catch (error) {
            console.error('Failed to fetch multipliers:', error);
            multiplierListHorizontal.innerHTML = '<div class="multiplier-item" style="color:red;">Error loading payouts.</div>';
        }
    }


     function handleAutoSelect() {
         // Select a random number of bombs between 1 and 24
         const randomBombs = Math.floor(Math.random() * 24) + 1;
         bombSelect.value = randomBombs; // Set the select value
         bombSelect.dispatchEvent(new Event('change')); // Trigger the change event to update multiplier list
     }


    // --- REMOVED: Withdrawal Functions ---
    // function validateWithdrawal(amount, number, name) { ... }
    // async function submitWithdrawalRequest() { ... }
    // async function fetchPlayerWithdrawalHistory() { ... }


    // --- Initial Setup and Event Listeners ---
    function initialize() {
        // Add game event listeners
        startGameBtn.addEventListener('click', startGame);
        cashoutBtn.addEventListener('click', cashOut);
        bombSelect.addEventListener('change', updateMultiplierList);
        autoSelectBtn.addEventListener('click', handleAutoSelect);
        modalCloseBtn.addEventListener('click', handleModalClose);

        // REMOVED: Add withdrawal event listener
        // submitWithdrawalBtn.addEventListener('click', submitWithdrawalRequest);


        // Set initial game state (not active, reset board)
        setGameState(false, true);

        // Initial data fetch for feed, leaderboard, jackpot
        fetchGameUpdates(); // This function no longer includes fetchPlayerWithdrawalHistory()
        // Refresh feed, leaderboard, and jackpot info periodically (e.g., every 20 seconds)
        setInterval(fetchGameUpdates, 20000);

         // Update initial stats display beside the grid with 0 hits and selected bombs
         const initialBombs = parseInt(bombSelect.value);
         updateStatsDisplay(0, initialBombs);

        // REMOVED: Initial fetch for player withdrawal history

        // --- Simple Tab Switching Logic (No longer needed on game page) ---
        // ... (removed tab logic)
    }


    // Initialize the game when the DOM is fully loaded
    initialize();
});