<?php
// Ensure session is started by db.php
// Also handles error reporting settings if configured in db.php
require_once '../config/db.php';

// Set error logging more verbosely temporarily for debugging if needed
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL); // Enable all error reporting for debugging

// Ensure session is started
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if user is logged in
if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['error' => 'Not logged in']);
    exit();
}

$user_id = $_SESSION['user_id'];
header('Content-Type: application/json');

// --- FIXED PLAYER WIN RATE AFTER NEW PLAYER PHASE ---
// This percentage applies AFTER a player has completed the initial new_player_game_count games.
const STANDARD_PLAYER_WIN_RATE_PERCENTAGE = 35.0; // Theoretical 65% house edge after initial phase
const NEW_PLAYER_GAME_COUNT = 10; // Number of initial games where win is guaranteed

// Helper function for combinations C(n, k) (Keep as it's used for multiplier calc)
function combinations($n, $k) {
    if ($k < 0 || $k > $n) return 0;
    if ($k == 0 || $k == $n) return 1;
    if ($k > $n / 2) $k = $n - $k;
    $res = 1;
    for ($i = 1; $i <= $k; $i++) {
        $res = $res * ($n - $i + 1) / $i;
    }
    return $res;
}

// Helper function to calculate multiplier for a given state
function calculate_multiplier($hits, $bombs) {
    if ($hits <= 0) return 1.0;

    $total_tiles = 25;
    $safe_tiles = $total_tiles - $bombs;

    if ($hits > $safe_tiles) return 0; // Impossible scenario (more hits than safe tiles)

    $probability_no_bomb_in_hits = 1.0;
    for($i = 0; $i < $hits; $i++) {
        // Ensure we don't divide by zero if something is wrong
        if (($total_tiles - $i) <= 0) {
             error_log("calculate_multiplier: Division by zero imminent at i={$i}, total_tiles={$total_tiles}. Returning 0.");
             return 0;
        }
        // Ensure ($safe_tiles - $i) is not negative (implies hits > safe_tiles, already checked)
         if (($safe_tiles - $i) < 0) {
              error_log("calculate_multiplier: ($safe_tiles - $i) is negative at i={$i}, safe_tiles={$safe_tiles}. Returning 0.");
             return 0;
         }
        $probability_no_bomb_in_hits *= ($safe_tiles - $i) / ($total_tiles - $i);
    }

    // --- MODIFIED: Reduced payout edge factor further ---
    $payout_edge_factor = 0.76; // Payout 76% of fair odds on revealed tiles
    // --- END MODIFIED ---

    if ($probability_no_bomb_in_hits <= 0) return 0; // Avoid division by zero

    $multiplier = $payout_edge_factor / $probability_no_bomb_in_hits;

    return round($multiplier, 2);
}

// Helper function to get game settings (kept for jackpot settings)
if (!function_exists('get_setting')) {
    function get_setting($conn, $setting_name) {
        // Ensure $conn is a valid database connection object before using it
        if (!$conn || $conn->connect_error) {
            // error_log("Database connection not available in get_setting."); // Log might be spammy
            return null;
        }
        $stmt = $conn->prepare("SELECT setting_value FROM game_settings WHERE setting_name = ? LIMIT 1"); // Added LIMIT 1
        if (!$stmt) {
            error_log("Failed to prepare statement in get_setting: " . $conn->error);
            return null;
        }
        $stmt->bind_param("s", $setting_name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
             $stmt->close(); // Close statement as soon as result is fetched
            return $row['setting_value'];
        }
        $stmt->close();
        return null; // Return null if setting not found
    }
}


$action = $_POST['action'] ?? '';

// --- START A NEW GAME ---
if ($action === 'start') {
    $bet_amount = floatval($_POST['bet']);
    $player_bomb_selection = intval($_POST['bombs']); // Player's chosen bomb count

    // --- DEBUG LOG: Received bomb count ---
    // error_log("GAME_LOGIC START: User {$user_id}, Raw POST bombs: " . var_export($_POST['bombs'] ?? 'not set', true) . ", Parsed Int bombs: " . $player_bomb_selection);
    // --- END DEBUG LOG ---

    if ($player_bomb_selection < 1 || $player_bomb_selection > 24) {
        echo json_encode(['error' => 'Invalid bomb selection.']);
        exit();
    }

    // Validate bet
    $stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($bet_amount <= 0 || $user['coins'] < $bet_amount) {
        echo json_encode(['error' => 'Invalid bet amount or insufficient funds.']);
        exit();
    }

    // Deduct bet amount immediately
    $new_balance = $user['coins'] - $bet_amount;
    // Use transactional update for balance deduction and history
    $conn->begin_transaction();
    try {
        $stmt = $conn->prepare("UPDATE users SET coins = ? WHERE id = ?");
        $stmt->bind_param("di", $new_balance, $user_id);
        $stmt->execute();
        // Check for update success if needed, though insufficient funds is checked above
        $stmt->close();

        // Log transaction for the bet (initial deduction is recorded as game_loss type)
        // Ensure 'game_loss' type exists in transactions ENUM (handled in SQL update)
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'game_loss', ?, ?)");
        $description = "Bet for Mines game (Bombs: {$player_bomb_selection})";
        // Use absolute value for amount in transaction log as it represents coins deducted
        $bet_for_log = abs($bet_amount);
        $stmt->bind_param("ids", $user_id, $bet_for_log, $description);
        $stmt->execute();
        $stmt->close();

        $conn->commit();
    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        error_log("DB error during game start transaction for user {$user_id}: " . $exception->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'Database error during game start.']);
        exit();
    }


    // --- Determine Round Outcome based on Player History ---
    // Count games played by the user
    $stmt = $conn->prepare("SELECT COUNT(*) FROM game_history WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $games_played_count = $stmt->get_result()->fetch_row()[0];
    $stmt->close();

    $round_outcome = 'loss'; // Default to loss

    // Check if the user is still in the new player phase
    if ($games_played_count < NEW_PLAYER_GAME_COUNT) {
        // Guaranteed win for new players during their first X games
        $round_outcome = 'win';
         error_log("GAME_LOGIC START: User {$user_id} ({$games_played_count} games played). New player phase, forcing WIN outcome.");
    } else {
        // After the new player phase, use the standard win rate probability
        $random_percentage = mt_rand(0, 10000) / 100.0; // Generate random float 0.00 to 100.00
        if ($random_percentage <= STANDARD_PLAYER_WIN_RATE_PERCENTAGE) {
             $round_outcome = 'win';
             error_log("GAME_LOGIC START: User {$user_id} ({$games_played_count} games played). Standard phase, random {$random_percentage}% <= " . STANDARD_PLAYER_WIN_RATE_PERCENTAGE . "%. Outcome: WIN.");
        } else {
             $round_outcome = 'loss';
              error_log("GAME_LOGIC START: User {$user_id} ({$games_played_count} games played). Standard phase, random {$random_percentage}% > " . STANDARD_PLAYER_WIN_RATE_PERCENTAGE . "%. Outcome: LOSS.");
        }
    }

    // --- DEBUG LOG: Determined outcome ---
    // error_log("GAME_LOGIC START: User {$user_id}, Outcome: {$round_outcome}"); // Already logged above
    // --- END DEBUG LOG ---


    // Generate the game board based on the player's bomb selection
    $board = array_fill(0, 25, 'coin'); // Creates an array of 25 'coin' tiles

    // --- FIX START: Robust bomb placement ---
    $total_tiles = 25;
    // Ensure bombs_to_place is the player's selected number, clamped to 1-24
    $bombs_to_place = min(max(1, $player_bomb_selection), $total_tiles - 1);

    $bomb_indices = []; // Initialize array to store indices
    if ($bombs_to_place > 0) {
        $all_indices = range(0, $total_tiles - 1); // Get all possible indices [0, 1, ..., 24]
        shuffle($all_indices); // Randomly shuffle the indices
        // Take the first $bombs_to_place indices as bomb locations
        $bomb_indices = array_slice($all_indices, 0, $bombs_to_place);

        // Set these indices in the board
        foreach ($bomb_indices as $index) {
             // Double-check bounds (should not be needed with range/slice on valid input)
             if ($index >= 0 && $index < $total_tiles) {
                $board[$index] = 'bomb';
             } else {
                 error_log("GAME_LOGIC START: Invalid index {$index} generated for bombs_to_place {$bombs_to_place}. Board generation error?");
             }
        }
    }
    // If $player_bomb_selection is 0 (invalid per validation), bombs_to_place is 1, still places 1 bomb.
    // If $player_bomb_selection is 25 or more, bombs_to_place will be 24, places 24 bombs.

    // --- DEBUG LOG: Actual bomb count on the board ---
    $actual_bomb_count = 0;
    if (is_array($board)) {
        foreach($board as $tile_type) {
            if ($tile_type === 'bomb') {
                $actual_bomb_count++;
            }
        }
    }
    // This log confirms the generated board has the *correct* number of bombs matching the selected amount.
    // The +1 issue happens on the client side when revealing this board in a specific scenario.
    error_log("GAME_LOGIC START: User {$user_id}, Player Selected Bombs: {$player_bomb_selection}, Actual Bombs Placed on Board: {$actual_bomb_count}.");
    // --- END DEBUG LOG ---

    // --- FIX END: Robust bomb placement ---


    // Store game state in session
    $_SESSION['game_state'] = [
        'board' => $board, // Store the generated board
        'bet' => $bet_amount,
        'bombs' => $player_bomb_selection, // Store the *selected* bomb count (passed from front-end)
        'revealed_tiles' => [],
        'hits' => 0, // Initialize hits to 0
        'is_active' => true,
        'round_outcome' => $round_outcome, // Store determined outcome ('win' or 'loss')
        'current_winnings' => 0.0,
        'current_multiplier' => 1.00
    ];

    // Update last played time for user
    $stmt = $conn->prepare("UPDATE users SET last_played = NOW() WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $stmt->close();

    // Fetch the new balance to return
    $stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $updated_user = $stmt->get_result()->fetch_assoc();
    $new_balance_formatted = number_format($updated_user['coins'], 2);
    $stmt->close();


    echo json_encode([
        'success' => true,
        'message' => 'Game started!',
        'new_balance' => $new_balance_formatted
        // Do NOT send board data on start! This is revealed tile by tile.
        // 'board' => $board, // Removed this line
    ]);
    exit();
}

// --- REVEAL A TILE ---
if ($action === 'reveal') {
    // --- DEBUG LOGGING ---
    // error_log("GAME_LOGIC REVEAL: User {$user_id}, Attempting to reveal tile index: " . var_export($_POST['tile_index'] ?? 'not set', true));
    // error_log("GAME_LOGIC REVEAL: Session Game State: " . print_r($_SESSION['game_state'] ?? 'Not Set', true)); // Too verbose
    // --- END DEBUG LOGGING ---

    if (!isset($_SESSION['game_state']) || !$_SESSION['game_state']['is_active']) {
        error_log("GAME_LOGIC REVEAL: No active game state found for user {$user_id}.");
        http_response_code(400); // Bad request or conflict
        echo json_encode(['error' => 'No active game.']);
        exit();
    }

    $tile_index = intval($_POST['tile_index']);
    $game = &$_SESSION['game_state']; // Use reference for easier updates

    // Basic validation of tile index and if already revealed
    if ($tile_index < 0 || $tile_index >= 25) {
         error_log("GAME_LOGIC REVEAL: Invalid tile index {$tile_index} received for user {$user_id}.");
         http_response_code(400);
         echo json_encode(['error' => 'Invalid tile index.']);
         exit();
    }

    if (in_array($tile_index, $game['revealed_tiles'])) {
         error_log("GAME_LOGIC REVEAL: Tile index {$tile_index} already revealed for user {$user_id}.");
         echo json_encode(['error' => 'Tile already revealed.']);
         exit();
    }

    // Check the content of the tile on the generated board
    $tile_content_on_board = $game['board'][$tile_index];

    $response_data = ['tile_index' => $tile_index];
    $total_tiles = 25;
    // Use the stored bomb count (player selection) for calculations and checks
    $selected_bombs_count = $game['bombs'];
    $safe_tiles_count = $total_tiles - $selected_bombs_count;


    // --- Core Game Logic Decision: Win Round vs Loss Round vs Hitting Bomb ---

    // Determine if the round ends in a loss based on the predetermined outcome OR hitting a bomb on the board
    $round_ends_in_loss = false;
    $outcome_reported_to_client = 'coin'; // Default optimistic outcome (assuming coin hit)

    // Predetermined LOSS round takes precedence
    if ($game['round_outcome'] === 'loss') {
        // If the round was predetermined as a loss, *any* click results in losing the bet.
        // The client needs to be told this was a 'bomb' even if the generated board had a coin.
        $round_ends_in_loss = true;
        $outcome_reported_to_client = 'bomb'; // Force 'bomb' outcome report to client
         error_log("GAME_LOGIC REVEAL: User {$user_id}, Tile {$tile_index}. Predetermined LOSS round. Reporting outcome as 'bomb'.");

    } elseif ($tile_content_on_board === 'bomb') {
        // If it was a 'win' round, but the player clicked a bomb on the board.
        $round_ends_in_loss = true;
        $outcome_reported_to_client = 'bomb'; // Report 'bomb' outcome based on board
         error_log("GAME_LOGIC REVEAL: User {$user_id}, Tile {$tile_index}. WIN round, but hit a BOMB on the board. Reporting outcome as 'bomb'.");

    } else {
        // If it was a 'win' round AND the player clicked a coin on the board. Game continues.
        // The tile outcome is 'coin', and the server logic proceeds to update hits/winnings.
        $outcome_reported_to_client = 'coin'; // Report 'coin' outcome
         error_log("GAME_LOGIC REVEAL: User {$user_id}, Tile {$tile_index}. WIN round, hit COIN on board. Reporting outcome as 'coin'.");
    }


    if ($round_ends_in_loss) {
        // Game is over due to loss (either predetermined or hit a bomb)
        $game['is_active'] = false;
        $loss_amount = $game['bet']; // Bet is already deducted at start
        $profit_loss = -$loss_amount; // Full bet is lost

        // Log the outcome to game history
        // Ensure 'loss' outcome exists in game_history ENUM (should already exist)
        $stmt = $conn->prepare("INSERT INTO game_history (user_id, bet_amount, outcome, profit_loss) VALUES (?, ?, 'loss', ?)");
        $stmt->bind_param("idd", $user_id, $game['bet'], $profit_loss);
        $stmt->execute();
        $stmt->close();

        // Send response indicating loss
        $response_data['outcome'] = $outcome_reported_to_client; // Will be 'bomb'
        $response_data['message'] = 'You hit a bomb! Game over.';

        // --- FIX START: Ensure the board sent on loss always shows the clicked tile as a bomb ---
        // Create a copy of the board data to send back
        $board_to_reveal = $game['board'];

        // If the round was a predetermined loss AND the tile was originally a coin,
        // we must force the revealed tile at the clicked index to be a bomb in the data sent to the client.
        // This matches the 'bomb' outcome reported and fixes the +1 bomb display issue.
        if ($game['round_outcome'] === 'loss' && $tile_content_on_board === 'coin') {
             $board_to_reveal[$tile_index] = 'bomb';
             error_log("GAME_LOGIC REVEAL: Predetermined LOSS round on coin tile {$tile_index}. Forcing board data for reveal to show bomb at {$tile_index}.");
        }
        // If it was a WIN round but hit a BOMB on the board, the original board data is already correct.
        // If it was a LOSS round and happened to hit a BOMB on the board, the original board data is already correct.

        // Send the potentially modified board data to the client for revealing all tiles
        $response_data['board'] = $board_to_reveal;
        error_log("GAME_LOGIC REVEAL: Round ended in loss. Sending full board for revealing all tiles.");
        // --- FIX END ---

        // Also send the index of the tile that was clicked, so the client can potentially add a visual marker if needed (though not used in the JS fix below)
        // $response_data['clicked_index_on_loss'] = $tile_index; // Not strictly necessary with the board fix
        // error_log("GAME_LOGIC REVEAL: Sending clicked_index_on_loss: {$tile_index}");


        // Fetch updated balance after game state is cleared
         $stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
         $stmt->bind_param("i", $user_id);
         $stmt->execute();
         $updated_user = $stmt->get_result()->fetch_assoc();
         $response_data['new_balance'] = number_format($updated_user['coins'], 2);
         $stmt->close();

        unset($_SESSION['game_state']); // Clear game state

    } else { // outcome_reported_to_client === 'coin' (must be a 'win' round and clicked tile was 'coin' on board)
        // Player found a coin and game continues
        $game['hits']++; // Increment coin hits
        $game['revealed_tiles'][] = $tile_index; // Add to revealed list

        // Calculate current winnings based on hits and *selected* bombs
        $current_multiplier = calculate_multiplier($game['hits'], $selected_bombs_count); // Use $selected_bombs_count
        $game['current_winnings'] = $game['bet'] * $current_multiplier;
        $game['current_multiplier'] = $current_multiplier;

        // Send response indicating coin hit and updated game state
        $response_data['outcome'] = $outcome_reported_to_client; // Will be 'coin'
        $response_data['hits'] = $game['hits']; // Send updated hit count
        $response_data['multiplier'] = $current_multiplier; // Send updated multiplier
        $response_data['winnings'] = $game['current_winnings']; // Send updated winnings

        // --- DEBUG LOG: Coin hit hits count ---
        error_log("GAME_LOGIC REVEAL: User {$user_id}, Coin hit at index {$tile_index}. Hits after increment: {$game['hits']}. Winnings: {$game['current_winnings']}. Multiplier: {$game['current_multiplier']}.");
        // --- END DEBUG LOG ---


        // --- Check for automatic WIN on clearing board in a WIN round ---
        if ($game['hits'] === $safe_tiles_count) { // Player revealed all non-bomb tiles
             // Found all coins in a win round - GUARANTEED WIN payout
            $game['is_active'] = false; // Game ends
            $final_winnings = $game['current_winnings'];
            $profit = $final_winnings - $game['bet'];

             // Use transactional update for balance addition and history/top_wins
             $conn->begin_transaction();
             try {
                 // Add winnings to user balance
                 $stmt = $conn->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
                 $stmt->bind_param("di", $final_winnings, $user_id);
                 if (!$stmt->execute()) { // Added error check
                     throw new mysqli_sql_exception("Failed to update user coins during board clear win.");
                 }
                 $stmt->close();

                 // Log the win to game history
                 // Ensure 'win' outcome exists in game_history ENUM (should already exist)
                 $stmt = $conn->prepare("INSERT INTO game_history (user_id, bet_amount, outcome, profit_loss) VALUES (?, ?, 'win', ?)");
                 $stmt->bind_param("idd", $user_id, $game['bet'], $profit);
                 if (!$stmt->execute()) { // Added error check
                      throw new mysqli_sql_exception("Failed to log game history during board clear win.");
                 }
                 $stmt->close();

                 // Add to top wins if profitable
                  if ($profit > 0) {
                      $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
                      $stmt->bind_param("i", $user_id);
                      $stmt->execute();
                      $user = $stmt->get_result()->fetch_assoc();
                      $username = $user['username'];
                      $stmt->close();
                      // MODIFIED: Ensure username is fetched for top_wins
                      if (!$username) { // Add robustness if user somehow isn't found (unlikely)
                           error_log("User ID {$user_id} not found when trying to log top_win.");
                           // Decide how to handle: log with generic name? skip? Let's skip top_win logging but continue game win process
                      } else {
                         $stmt = $conn->prepare("INSERT INTO top_wins (user_id, username, win_amount, profit_amount) VALUES (?, ?, ?, ?)");
                         $stmt->bind_param("isdd", $user_id, $username, $final_winnings, $profit);
                         if (!$stmt->execute()) { // Added error check
                              error_log("Failed to insert into top_wins: " . $stmt->error);
                              // This might not be a critical failure for the user, just log it and continue
                         } else {
                            // Keep only top N wins (assuming top 3 as per previous comment)
                             $conn->query("DELETE FROM top_wins WHERE id NOT IN (SELECT id FROM (SELECT id FROM top_wins ORDER BY profit_amount DESC LIMIT 3) as sub)");
                         }
                         $stmt->close();
                      }
                  }

                  // Check for Jackpot Challenge completion (if all conditions met AND player cleared board)
                  $jackpot_challenge_type = 'mines_10_22_3'; // Challenge name remains the same
                  // MODIFIED: Updated required hits and bet amount
                  $required_hits = 3;
                  $required_bombs = 22;
                  $required_bet = 10.00; // Needs float comparison with tolerance

                  // Use the bomb count *selected by the player* ($selected_bombs_count) for challenge eligibility
                  if ($game['hits'] === $required_hits && $selected_bombs_count === $required_bombs && abs($game['bet'] - $required_bet) < 0.001) {
                       $jackpot_limit = (int)get_setting($conn, 'jackpot_mines_challenge_limit'); // Get setting
                       $stmt = $conn->prepare("SELECT COUNT(*) FROM challenge_completions WHERE challenge_type = ?");
                       $stmt->bind_param("s", $jackpot_challenge_type);
                       $stmt->execute();
                       $completion_count = $stmt->get_result()->fetch_row()[0];
                       $stmt->close();

                       if ($jackpot_limit === 0 || $completion_count < $jackpot_limit) { // Check limit (0 means unlimited)
                           $stmt = $conn->prepare("SELECT COUNT(*) FROM challenge_completions WHERE user_id = ? AND challenge_type = ?");
                           $stmt->bind_param("is", $user_id, $jackpot_challenge_type);
                           $stmt->execute();
                           $user_challenge_count = $stmt->get_result()->fetch_row()[0];
                           $stmt->close();

                           if ($user_challenge_count === 0) { // Check if user already completed it
                               // --- MODIFIED: Use fixed base prize for jackpot, removed win rate dependency ---
                               $base_prize = (float)get_setting($conn, 'jackpot_mines_challenge_base_prize'); // Get setting
                               // Removed dynamic prize calculation based on win rate
                               $dynamic_prize = round($base_prize, 2); // Just use the base prize
                               // --- END MODIFIED ---

                               // Add bonus to user balance
                               $stmt = $conn->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
                               $stmt->bind_param("di", $dynamic_prize, $user_id);
                               if (!$stmt->execute()) { // Added error check
                                   error_log("Failed to update user coins for jackpot bonus: " . $stmt->error);
                                   // This might not be a critical failure for the user, just log it and continue transaction
                               } else {
                                   $stmt = $conn->prepare("INSERT INTO challenge_completions (user_id, challenge_type, bonus_amount) VALUES (?, ?, ?)");
                                   $stmt->bind_param("isd", $user_id, $jackpot_challenge_type, $dynamic_prize);
                                   if (!$stmt->execute()) { // Added error check
                                       error_log("Failed to insert into challenge_completions: " . $stmt->error);
                                       // Log and continue
                                   }
                                   $stmt->close();
                                   $response_data['challenge_bonus'] = $dynamic_prize; // Only add to response if inserted
                               }
                               $stmt->close(); // Close update statement even if log fails

                           }
                       }
                  }
                  $conn->commit(); // Commit win and potential bonus

              } catch (mysqli_sql_exception $exception) {
                  $conn->rollback();
                  error_log("DB error during board clear win transaction for user {$user_id}: " . $exception->getMessage());
                  http_response_code(500);
                  echo json_encode(['error' => 'Database error during board clear win.']);
                  exit();
              }

            $response_data['outcome'] = 'win_cleared'; // Report as board cleared win
            $response_data['message'] = 'You cleared the board!';
            $response_data['final_winnings'] = $final_winnings;
            $response_data['profit'] = $profit; // Profit is winnings minus initial bet
             // Send the full board on successful board clear
             // On a board clear, the original board *is* the correct state to show (all coins and the original bomb placements)
             $response_data['board'] = $game['board'];
             error_log("GAME_LOGIC REVEAL: Board cleared win. Sending original board for reveal.");


             // Fetch updated balance after adding winnings and potential bonus
             $stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
             $stmt->bind_param("i", $user_id);
             $stmt->execute();
             $updated_user = $stmt->get_result()->fetch_assoc();
             $response_data['new_balance'] = number_format($updated_user['coins'], 2);
             $stmt->close();

            unset($_SESSION['game_state']); // Clear game state

        }
        // If not cleared, game continues, no balance update yet.
    }

    echo json_encode($response_data);
    exit();
}

// --- CASHOUT ---
if ($action === 'cashout') {
    // --- DEBUG LOGGING --- (Keep commented out unless actively debugging)
    // error_log("GAME_LOGIC CASHOUT: User {$user_id}, Attempting cashout.");
    // error_log("GAME_LOGIC CASHOUT: Session Game State: " . print_r($_SESSION['game_state'] ?? 'Not Set', true)); // Too verbose
    // --- END DEBUG LOGGING ---

    // Allow cashout only if game is active, player has revealed coins, AND it was a 'win' round type
    // A 'loss' round outcome means any click would have resulted in the bet loss. Cashout is not possible in such a round type.
     if (!isset($_SESSION['game_state']) || !$_SESSION['game_state']['is_active'] || $_SESSION['game_state']['hits'] == 0 || (isset($_SESSION['game_state']['round_outcome']) && $_SESSION['game_state']['round_outcome'] !== 'win')) {
        error_log("GAME_LOGIC CASHOUT: Cashout conditions not met for user {$user_id}. Active: " . var_export($_SESSION['game_state']['is_active'] ?? 'N/A', true) . ", Hits: " . var_export($_SESSION['game_state']['hits'] ?? 'N/A', true) . ", Outcome: " . var_export($_SESSION['game_state']['round_outcome'] ?? 'N/A', true));
        http_response_code(400);
        echo json_encode(['error' => 'Cannot cash out at this time.']);
        exit();
    }

    $game = &$_SESSION['game_state'];

    $winnings = $game['current_winnings']; // Get current winnings
    $profit = $winnings - $game['bet']; // Calculate profit

    // Basic validation - should have winnings to cashout
    if ($winnings <= 0) {
         error_log("GAME_LOGIC CASHOUT: User {$user_id} attempting cashout with 0 winnings.");
         http_response_code(400);
         echo json_encode(['error' => 'No winnings to cash out.']);
         exit();
    }

    // Use transactional update for balance addition and history/top_wins
    $conn->begin_transaction();
    try {
        // Add winnings to user balance
        $stmt = $conn->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
        $stmt->bind_param("di", $winnings, $user_id);
         if (!$stmt->execute()) { // Added error check
             throw new mysqli_sql_exception("Failed to update user coins during cashout.");
         }
        $stmt->close();

        // Log the win to game history
        // Ensure 'win' outcome exists in game_history ENUM (should already exist)
        $stmt = $conn->prepare("INSERT INTO game_history (user_id, bet_amount, outcome, profit_loss) VALUES (?, ?, 'win', ?)");
        $stmt->bind_param("idd", $user_id, $game['bet'], $profit);
         if (!$stmt->execute()) { // Added error check
              throw new mysqli_sql_exception("Failed to log game history during cashout.");
         }
        $stmt->close();

         // Add to top wins if profitable
         if ($profit > 0) {
             $stmt = $conn->prepare("SELECT username FROM users WHERE id = ?");
             $stmt->bind_param("i", $user_id);
             $stmt->execute();
             $user = $stmt->get_result()->fetch_assoc();
             $username = $user['username'];
             $stmt->close();

             // MODIFIED: Ensure username is fetched for top_wins
             if (!$username) { // Add robustness if user somehow isn't found (unlikely)
                  error_log("User ID {$user_id} not found when trying to log top_win on cashout.");
                  // Log with generic name? skip? Let's skip top_win logging but continue game win process
             } else {
                $stmt = $conn->prepare("INSERT INTO top_wins (user_id, username, win_amount, profit_amount) VALUES (?, ?, ?, ?)");
                $stmt->bind_param("isdd", $user_id, $username, $winnings, $profit);
                 if (!$stmt->execute()) { // Added error check
                      error_log("Failed to insert into top_wins on cashout: " . $stmt->error);
                      // This might not be a critical failure for the user, just log it and continue
                 } else {
                    // Keep only top N wins
                    $conn->query("DELETE FROM top_wins WHERE id NOT IN (SELECT id FROM (SELECT id FROM top_wins ORDER BY profit_amount DESC LIMIT 3) as sub)");
                 }
                $stmt->close();
             }
         }

         // Check for Jackpot Challenge completion *only if* it was a win round and conditions met
         // This logic applies to *any* win (cashout or board clear)
         $jackpot_challenge_type = 'mines_10_22_3'; // Challenge name remains the same
         // MODIFIED: Updated required hits and bet amount
         $required_hits = 3;
         $required_bombs = 22;
         $required_bet = 10.00; // Needs float comparison with tolerance

         // Use the bomb count *selected by the player* ($game['bombs']) for challenge eligibility
         if ($game['hits'] === $required_hits && $game['bombs'] === $required_bombs && abs($game['bet'] - $required_bet) < 0.001) {
              $jackpot_limit = (int)get_setting($conn, 'jackpot_mines_challenge_limit'); // Get setting
              $stmt = $conn->prepare("SELECT COUNT(*) FROM challenge_completions WHERE challenge_type = ?");
              $stmt->bind_param("s", $jackpot_challenge_type);
              $stmt->execute();
              $completion_count = $stmt->get_result()->fetch_row()[0];
              $stmt->close();

              if ($jackpot_limit === 0 || $completion_count < $jackpot_limit) { // Check limit (0 means unlimited)
                  $stmt = $conn->prepare("SELECT COUNT(*) FROM challenge_completions WHERE user_id = ? AND challenge_type = ?");
                  $stmt->bind_param("is", $user_id, $jackpot_challenge_type);
                  $stmt->execute();
                  $user_challenge_count = $stmt->get_result()->fetch_row()[0];
                  $stmt->close();

                  if ($user_challenge_count === 0) { // Check if user already completed it
                      // --- MODIFIED: Use fixed base prize for jackpot, removed win rate dependency ---
                      $base_prize = (float)get_setting($conn, 'jackpot_mines_challenge_base_prize'); // Get setting
                      // Removed dynamic prize calculation based on win rate
                      $dynamic_prize = round($base_prize, 2); // Just use the base prize
                      // --- END MODIFIED ---

                       // Add bonus to user balance
                      $stmt = $conn->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
                      $stmt->bind_param("di", $dynamic_prize, $user_id);
                      if (!$stmt->execute()) { // Added error check
                           error_log("Failed to update user coins for jackpot bonus on cashout: " . $stmt->error);
                           // Log and continue transaction
                      } else {
                          $stmt = $conn->prepare("INSERT INTO challenge_completions (user_id, challenge_type, bonus_amount) VALUES (?, ?, ?)");
                          $stmt->bind_param("isd", $user_id, $jackpot_challenge_type, $dynamic_prize);
                          if (!$stmt->execute()) { // Added error check
                              error_log("Failed to insert into challenge_completions on cashout: " . $stmt->error);
                              // Log and continue
                          }
                          $stmt->close();
                          $response_data['challenge_bonus'] = $dynamic_prize; // Only add to response if inserted
                      }
                       $stmt->close(); // Close update statement
                  }
              }
         }

        $conn->commit(); // Commit cashout and potential bonus


    } catch (mysqli_sql_exception $exception) {
         $conn->rollback();
         error_log("DB error during cashout transaction for user {$user_id}: " . $exception->getMessage());
         http_response_code(500);
         echo json_encode(['error' => 'Database error during cashout.']);
         exit();
     }

    unset($_SESSION['game_state']); // Clear game state

    // Fetch updated balance after adding winnings and potential bonus
    $stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $new_balance = $stmt->get_result()->fetch_assoc()['coins'];
    $stmt->close();

    $response_data['success'] = true;
    $response_data['winnings'] = $winnings;
    $response_data['new_balance'] = number_format($new_balance, 2);
    $response_data['profit'] = $profit; // Profit is winnings minus initial bet

    echo json_encode($response_data);
    exit();
}

// Any other actions would go here...

// If no action was matched
http_response_code(400);
echo json_encode(['error' => 'Invalid action specified.']);
?>