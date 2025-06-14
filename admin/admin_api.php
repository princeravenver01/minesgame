<?php
// Per the logic in index.php, we need to include the database file first.
// This ensures the session is started and the database is connected consistently.
require_once '../config/db.php';

// --- Set the default timezone for date/time functions ---
// IMPORTANT: Replace 'Asia/Manila' with the correct timezone for your server/location.
date_default_timezone_set('Asia/Manila'); // <-- ADD THIS LINE

// Now that the session is started, set the content type and perform security checks.
header('Content-Type: application/json');

// Step 2: Perform the critical security check.
// We use the same relaxed check that was applied in the previous fix.
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] != 1) {
    http_response_code(403); // Forbidden
    echo json_encode([
        'error' => 'Access Denied. The API could not verify your admin session.',
        'session_data_seen_by_api' => $_SESSION ?? []
    ]);
    exit(); // <-- Exit after sending forbidden response
}

// Step 3: Check for a valid database connection.
if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . ($conn ? $conn->connect_error : 'Unknown error')]);
    exit(); // <-- Exit after sending DB error response
}

$admin_user_id = $_SESSION['user_id']; // Get logged-in admin ID for logging

// --- Helper function (already exists, ensures compatibility) ---
if (!function_exists('get_setting')) {
    function get_setting($conn, $setting_name) {
        if (!$conn) return null;
        $stmt = $conn->prepare("SELECT setting_value FROM game_settings WHERE setting_name = ?");
        if (!$stmt) return null;
        $stmt->bind_param("s", $setting_name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            return $row['setting_value'];
        }
        return null;
    }
}

$method = $_SERVER['REQUEST_METHOD'];

// Handle GET Requests
if ($method === 'GET') {
    $action = $_GET['action'] ?? '';

    if ($action === 'get_settings') {
        // Fetch settings (excluding win_rate_percentage)
        $jackpot_mines_challenge_limit = get_setting($conn, 'jackpot_mines_challenge_limit');
        $jackpot_mines_challenge_base_prize = get_setting($conn, 'jackpot_mines_challenge_base_prize');

        echo json_encode([
            'jackpot_mines_challenge_limit' => $jackpot_mines_challenge_limit,
            'jackpot_mines_challenge_base_prize' => $jackpot_mines_challenge_base_prize
        ]);
        exit(); // <-- Exit after successful GET response

    } elseif ($action === 'get_all_users') {
        // MODIFIED: Include player ID in the select for the Activity Log button
        $result = $conn->query("SELECT id, username, coins, last_played, created_at FROM users WHERE is_admin = 0 ORDER BY id DESC");
        if (!$result) {
            http_response_code(500);
            echo json_encode(['error' => 'Database query failed: ' . $conn->error]);
            exit(); // <-- Exit after DB error response
        } else {
            $users = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode($users);
            exit(); // <-- Exit after successful GET response
        }
    } elseif ($action === 'get_user_for_topup') {
        $username = $_GET['username'] ?? '';
        if (empty($username) || strlen($username) < 3) {
            http_response_code(400);
            echo json_encode(['error' => 'Invalid username provided.']);
            exit(); // <-- Exit after validation error
        }
         // Search for user (case-insensitive search using LIKE)
        $stmt = $conn->prepare("SELECT id, username, coins, last_played FROM users WHERE username LIKE ? AND is_admin = 0 LIMIT 1");
        // Use concat for wildcards to allow searching anywhere in username
        $search_term = "%" . $username . "%";
        $stmt->bind_param("s", $search_term);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($user) {
             // Format coins for display
             $user['coins'] = number_format($user['coins'], 2, '.', ''); // Use '.' for decimal, '' for thousands separator
            echo json_encode(['success' => true, 'user' => $user]);
            exit(); // <-- Exit after successful GET response
        } else {
            echo json_encode(['success' => false, 'error' => 'User not found.']);
            exit(); // <-- Exit after user not found response
        }
    } elseif ($action === 'get_withdrawal_requests') {
        // Fetch withdrawal requests, joining with users table for username
        // MODIFIED: Changed status order to match JS dropdown: pending, processing, completed, cancelled
        $result = $conn->query("SELECT wr.id, wr.user_id, wr.amount, wr.gcash_number, wr.gcash_name, wr.status, wr.requested_at, wr.processed_at, u.username
                                FROM withdrawal_requests wr
                                JOIN users u ON wr.user_id = u.id
                                ORDER BY FIELD(wr.status, 'pending', 'processing', 'completed', 'cancelled'), wr.requested_at DESC");
        if (!$result) {
            http_response_code(500);
            echo json_encode(['error' => 'Database query failed: ' . $conn->error]);
            exit(); // <-- Exit after DB error response
        } else {
            $requests = $result->fetch_all(MYSQLI_ASSOC);
            echo json_encode(['success' => true, 'requests' => $requests]);
            exit(); // <-- Exit after successful GET response
        }
     } elseif ($action === 'get_profit_stats') {
        $range = $_GET['range'] ?? 'today'; // 'today', 'yesterday', 'date_range'
        $startDate = $_GET['startDate'] ?? null; // MM/DD/YYYY
        $endDate = $_GET['endDate'] ?? null; // MM/DD/YYYY

        $bet_date_filter_sql = ""; // Filter for transactions (bets)
        $payout_date_filter_sql = ""; // Filter for withdrawal requests (payouts)
        $game_profit_date_filter_sql = ""; // Filter for game history (game profit/loss)

        $bet_bind_params = [];
        $bet_bind_types = "";
        $payout_bind_params = [];
        $payout_bind_types = "";
        $game_profit_bind_params = [];
        $game_profit_bind_types = "";


        try {
            $startOfDay = null;
            $endOfDay = null;

            if ($range === 'today') {
                // Correctly sets start of today 00:00:00 based on configured timezone
                $startOfDay = (new DateTime('today'))->format('Y-m-d H:i:s');
                // Correctly sets end of today 23:59:59 based on configured timezone
                $endOfDay = (new DateTime('tomorrow - 1 second'))->format('Y-m-d H:i:s');
            } elseif ($range === 'yesterday') {
                // Correctly sets start of yesterday 00:00:00 based on configured timezone
                $startOfDay = (new DateTime('yesterday'))->format('Y-m-d H:i:s');
                // Correctly sets end of yesterday 23:59:59 based on configured timezone
                $endOfDay = (new DateTime('today - 1 second'))->format('Y-m-d H:i:s');
            } elseif ($range === 'date_range' && $startDate && $endDate) {
                $parsedStartDate = DateTime::createFromFormat('m/d/Y', $startDate);
                $parsedEndDate = DateTime::createFromFormat('m/d/Y', $endDate);

                if ($parsedStartDate === false || $parsedEndDate === false) {
                     http_response_code(400);
                     echo json_encode(['error' => 'Invalid date format for date range. Use MM/DD/YYYY.']);
                     exit(); // <-- Exit after validation error
                }
                // Correctly sets start of range start date 00:00:00
                $startOfDay = $parsedStartDate->format('Y-m-d 00:00:00');
                // Correctly sets end of range end date 23:59:59
                $endOfDay = $parsedEndDate->format('Y-m-d 23:59:59');

            } else {
                 http_response_code(400);
                 echo json_encode(['error' => 'Invalid date range or missing dates for range.']);
                 exit(); // <-- Exit after validation error
            }

             // Apply date filters using BETWEEN
             if ($startOfDay && $endOfDay) {
                  // Use created_at for transactions (bets)
                  $bet_date_filter_sql = "WHERE created_at BETWEEN ? AND ?";
                  $bet_bind_types = "ss";
                  $bet_bind_params[] = $startOfDay;
                  $bet_bind_params[] = $endOfDay;

                  // Use processed_at for completed withdrawal requests (payouts)
                  $payout_date_filter_sql = "WHERE processed_at BETWEEN ? AND ?";
                  $payout_bind_types = "ss";
                  $payout_bind_params[] = $startOfDay;
                  $payout_bind_params[] = $endOfDay;

                  // Use played_at for game history (game profit/loss)
                  $game_profit_date_filter_sql = "WHERE played_at BETWEEN ? AND ?";
                  $game_profit_bind_types = "ss";
                  $game_profit_bind_params[] = $startOfDay;
                  $game_profit_bind_params[] = $endOfDay;
             } else {
                 // This case should not be reached if date logic above is correct, but include for safety
                 http_response_code(500);
                 echo json_encode(['error' => 'Date filtering logic failed.']);
                 exit(); // <-- Exit after logic error
             }


            // --- Calculate Total Bets ---
            // Bets are logged as game_loss transactions. Amount is positive value deducted from user.
            $sql_bets = "SELECT SUM(amount) FROM transactions {$bet_date_filter_sql} AND type = 'game_loss'";
            $stmt = $conn->prepare($sql_bets);
            if (!$stmt) { throw new mysqli_sql_exception("Prepare failed for bets: " . $conn->error); }
            if (!empty($bet_bind_params)) $stmt->bind_param($bet_bind_types, ...$bet_bind_params);
            $stmt->execute();
            $total_bets = $stmt->get_result()->fetch_row()[0] ?? 0;
            $stmt->close();

            // --- Calculate Total Payouts (Completed Withdrawals) ---
            // Payouts are completed withdrawal requests. Use processed_at for time filtering.
            $sql_payouts = "SELECT SUM(amount) FROM withdrawal_requests {$payout_date_filter_sql} AND status = 'completed'";
            $stmt = $conn->prepare($sql_payouts);
            if (!$stmt) { throw new mysqli_sql_exception("Prepare failed for payouts: " . $conn->error); }
            if (!empty($payout_bind_params)) $stmt->bind_param($payout_bind_types, ...$payout_bind_params);
            $stmt->execute();
            $total_payouts = $stmt->get_result()->fetch_row()[0] ?? 0;
            $stmt->close();


             // --- Calculate Total Game Profit (SUM profit_loss from game_history) ---
             // profit_loss is positive for game win (player loss) and negative for game loss (player win)
             // Summing this directly gives the game's net profit/loss.
             $sql_game_profit = "SELECT SUM(profit_loss) FROM game_history {$game_profit_date_filter_sql}";
             $stmt = $conn->prepare($sql_game_profit);
             if (!$stmt) { throw new mysqli_sql_exception("Prepare failed for game profit: " . $conn->error); }
              if (!empty($game_profit_bind_params)) $stmt->bind_param($game_profit_bind_types, ...$game_profit_bind_params);
             $stmt->execute();
             $total_game_profit = $stmt->get_result()->fetch_row()[0] ?? 0;
             $stmt->close();

             // --- Calculate Total Player Profit (Negative of Total Game Profit) ---
             // Player profit is the opposite of game profit.
             $total_player_profit = $total_game_profit * -1;

             // --- Calculate Total Top-ups (For context, maybe not needed in core profit calc) ---
             // Top-ups are 'topup' transactions.
             // $sql_topups = "SELECT SUM(amount) FROM transactions {$bet_date_filter_sql} AND type = 'topup'";
             // $stmt = $conn->prepare($sql_topups);
             // if (!$stmt) { throw new mysqli_sql_exception("Prepare failed for topups: " . $conn->error); }
             // if (!empty($bet_bind_params)) $stmt->bind_param($bet_bind_types, ...$bet_bind_params);
             // $stmt->execute();
             // $total_topups = $stmt->get_result()->fetch_row()[0] ?? 0;
             // $stmt->close();


            echo json_encode([
                'success' => true,
                'total_bets' => $total_bets,
                'total_payouts' => $total_payouts, // Sum of completed withdrawals
                'total_game_profit' => $total_game_profit, // Sum of profit_loss from game history
                'total_player_profit' => $total_player_profit // Negative of game profit (Sum of game wins - sum of game losses for the player)
            ]);
            exit(); // <-- Exit after successful GET response

        } catch (mysqli_sql_exception $exception) {
             error_log("DB error during profit stats query for range {$range}: " . $exception->getMessage());
             http_response_code(500);
             echo json_encode(['success' => false, 'error' => 'Database error fetching profit stats: ' . $exception->getMessage()]);
             exit(); // <-- Exit after DB error response
         } catch (\Exception $e) {
             error_log("Error during profit stats query for range {$range}: " . $e->getMessage());
             http_response_code(500);
             echo json_encode(['success' => false, 'error' => 'An unexpected error occurred fetching profit stats: ' . $e->getMessage()]);
             exit(); // <-- Exit after general error response
         }

    // --- NEW: Handle get_player_activity action ---
     } elseif ($action === 'get_player_activity') {
         $target_user_id = intval($_GET['user_id'] ?? 0);
         $startDate = $_GET['startDate'] ?? null; // MM/DD/YYYY
         $endDate = $_GET['endDate'] ?? null; // MM/DD/YYYY
         $page = max(1, intval($_GET['page'] ?? 1)); // Get page number, default to 1, ensure > 0
         $limit = max(1, intval($_GET['limit'] ?? 10)); // Get limit, default to 10, ensure > 0
         $offset = ($page - 1) * $limit;

         if ($target_user_id <= 0) {
             http_response_code(400);
             echo json_encode(['success' => false, 'error' => 'Invalid target user ID for activity log.']);
             exit();
         }

         // --- Build WHERE clause and Bind parameters for COUNT(*) and Main Query ---
         $where_clauses_tx = ["t.user_id = ?"];
         $where_clauses_gh = ["gh.user_id = ?"];
         $bind_params = [$target_user_id, $target_user_id]; // Bind user_id for both parts of UNION
         $bind_types = "ii"; // Start with two 'i' for user_id

         if ($startDate && $endDate) {
             $parsedStartDate = DateTime::createFromFormat('m/d/Y', $startDate);
             $parsedEndDate = DateTime::createFromFormat('m/d/Y', $endDate);

             if ($parsedStartDate === false || $parsedEndDate === false) {
                  http_response_code(400);
                  echo json_encode(['error' => 'Invalid date format for date range. Use MM/DD/YYYY.']);
                  exit();
             }

             $startSql = $parsedStartDate->format('Y-m-d 00:00:00');
             $endSql = $parsedEndDate->format('Y-m-d 23:59:59');

             $where_clauses_tx[] = "t.created_at BETWEEN ? AND ?";
             $where_clauses_gh[] = "gh.played_at BETWEEN ? AND ?";
             // Add date parameters to bind params (need two sets for the UNION)
             $bind_params[] = $startSql;
             $bind_params[] = $endSql;
             $bind_params[] = $startSql;
             $bind_params[] = $endSql;
             // Add date types to bind types string (need two sets for the UNION)
             $bind_types .= "ssss";
         }

         $where_sql_tx = "WHERE " . implode(" AND ", $where_clauses_tx);
         $where_sql_gh = "WHERE " . implode(" AND ", $where_clauses_gh);


         try {
             // --- 1. Get Total Count ---
             // Count needs to apply the same filters as the main query
             $sql_count = "
                 SELECT COUNT(*) FROM
                 (
                     SELECT 1 FROM transactions t JOIN users u ON t.user_id = u.id {$where_sql_tx}
                     UNION ALL
                     SELECT 1 FROM game_history gh JOIN users u ON gh.user_id = u.id {$where_sql_gh}
                 ) AS combined_count";

             $stmt_count = $conn->prepare($sql_count);
              if (!$stmt_count) {
                  throw new mysqli_sql_exception("Prepare failed for player activity COUNT query: " . $conn->error);
             }
             // Bind parameters for the COUNT query (same as WHERE clauses)
              if (!empty($bind_params)) {
                  $bind_names_count = [$bind_types];
                  for ($i = 0; $i < count($bind_params); $i++) {
                      $bind_names_count[] = &$bind_params[$i];
                  }
                 call_user_func_array([$stmt_count, 'bind_param'], $bind_names_count);
             }

             $stmt_count->execute();
             $total_records = $stmt_count->get_result()->fetch_row()[0] ?? 0;
             $stmt_count->close();


             // --- 2. Get Paginated Data ---
             $sql = "
                 (
                     SELECT
                         t.id AS activity_id,
                         t.user_id,
                         u.username,
                         t.type AS activity_type,
                         t.amount,
                         NULL AS profit_loss,
                         t.description,
                         t.created_at AS timestamp,
                         t.admin_user_id
                     FROM transactions t
                     JOIN users u ON t.user_id = u.id
                     {$where_sql_tx}
                 )
                 UNION ALL
                 (
                     SELECT
                         gh.id AS activity_id,
                         gh.user_id,
                         u.username,
                         CASE
                             WHEN gh.outcome = 'win' THEN 'game_win'
                             WHEN gh.outcome = 'loss' THEN 'game_loss'
                             ELSE 'game_unknown'
                         END AS activity_type,
                         gh.bet_amount AS amount,
                         gh.profit_loss,
                         CONCAT('Game: Bet ₱', FORMAT(gh.bet_amount, 2), ', Outcome: ', gh.outcome, ', P/L: ₱', FORMAT(gh.profit_loss, 2)) AS description,
                         gh.played_at AS timestamp,
                         NULL AS admin_user_id
                     FROM game_history gh
                     JOIN users u ON gh.user_id = u.id
                     {$where_sql_gh}
                 )
                 ORDER BY timestamp DESC
                 LIMIT ?, ?; -- Apply LIMIT and OFFSET to the combined result
             ";

             $stmt = $conn->prepare($sql);
             if (!$stmt) {
                  throw new mysqli_sql_exception("Prepare failed for player activity query: " . $conn->error);
             }

             // Bind parameters for the main query: WHERE clause params + LIMIT params
             // Bind types string for main query: original bind types + 'ii' for limit, offset
             $bind_types_main = $bind_types . "ii";
             // Bind parameters array for main query: original bind params + limit, offset
             $bind_params_main = $bind_params;
             $bind_params_main[] = $offset;
             $bind_params_main[] = $limit;


             if (!empty($bind_params_main)) {
                  $bind_names_main = [$bind_types_main];
                  for ($i = 0; $i < count($bind_params_main); $i++) {
                      $bind_names_main[] = &$bind_params_main[$i];
                  }
                 call_user_func_array([$stmt, 'bind_param'], $bind_names_main);
             }

             $stmt->execute();
             $result = $stmt->get_result();

             $activity = [];
             while ($row = $result->fetch_assoc()) {
                  $activity[] = $row;
             }
             $stmt->close();

             echo json_encode([
                 'success' => true,
                 'activity' => $activity,
                 'total_records' => $total_records,
                 'current_page' => $page,
                 'items_per_page' => $limit
             ]);
             exit();

         } catch (mysqli_sql_exception $exception) {
              error_log("DB error fetching player activity for user {$target_user_id}: " . $exception->getMessage());
              http_response_code(500);
              echo json_encode(['success' => false, 'error' => 'Database error fetching activity: ' . $exception->getMessage()]);
              exit();
          } catch (\Exception $e) {
              error_log("Error fetching player activity for user {$target_user_id}: " . $e->getMessage());
              http_response_code(500);
              echo json_encode(['success' => false, 'error' => 'An unexpected error occurred fetching activity: ' . $e->getMessage()]);
              exit();
          }

     // --- NEW: Handle get_finance_history action ---
     // This is similar to get_player_activity but for ALL users and primarily transactions
     } elseif ($action === 'get_finance_history') {
         $startDate = $_GET['startDate'] ?? null; // MM/DD/YYYY
         $endDate = $_GET['endDate'] ?? null; // MM/DD/YYYY
         $typeFilter = $_GET['type'] ?? ''; // Specific transaction type filter
         $page = max(1, intval($_GET['page'] ?? 1));
         $limit = max(1, intval($_GET['limit'] ?? 10));
         $offset = ($page - 1) * $limit;


         $bind_params = [];
         $bind_types = "";

         $where_clauses = []; // Array to build WHERE clause parts

           // Handle date filtering
         if ($startDate && $endDate) {
             $parsedStartDate = DateTime::createFromFormat('m/d/Y', $startDate);
             $parsedEndDate = DateTime::createFromFormat('m/d/Y', $endDate);

             if ($parsedStartDate === false || $parsedEndDate === false) {
                  http_response_code(400);
                  echo json_encode(['error' => 'Invalid date format for date range. Use MM/DD/YYYY.']);
                  exit();
             }

             $startSql = $parsedStartDate->format('Y-m-d 00:00:00');
             $endSql = $parsedEndDate->format('Y-m-d 23:59:59');

             $where_clauses[] = "t.created_at BETWEEN ? AND ?";
             $bind_params[] = $startSql;
             $bind_params[] = $endSql;
             $bind_types .= "ss";
         }

         // Handle type filtering
          if (!empty($typeFilter)) {
              // If a specific type is selected, filter by that type
              $allowed_tx_types = ['topup','referral_bonus','game_win','game_loss','withdrawal','withdrawal_pending','withdrawal_cancelled_return'];
              if (!in_array($typeFilter, $allowed_tx_types)) {
                   http_response_code(400);
                   echo json_encode(['error' => 'Invalid transaction type filter.']);
                   exit();
              }
             $where_clauses[] = "t.type = ?";
             $bind_params[] = $typeFilter;
             $bind_types .= "s";
         } else {
             // Filter by specific finance types when no type filter is provided
             // Note: This endpoint is *Finance History*, so we should probably *only* show finance types
             // unless a specific 'game' type is requested via the filter.
             // Let's adjust the default to only show topup, withdrawal types.
             $finance_types = ['topup', 'withdrawal', 'withdrawal_pending', 'withdrawal_cancelled_return', 'referral_bonus'];
             $placeholders = implode(',', array_fill(0, count($finance_types), '?'));
             $where_clauses[] = "t.type IN ({$placeholders})";
             // Add the finance types to the bind parameters
             $bind_params = array_merge($bind_params, $finance_types);
              $bind_types .= str_repeat('s', count($finance_types));
         }


        $sql_base = "
             FROM transactions t
             JOIN users u_user ON t.user_id = u_user.id
             LEFT JOIN users u_admin ON t.admin_user_id = u_admin.id
         ";

         if (!empty($where_clauses)) {
             $sql_base .= " WHERE " . implode(" AND ", $where_clauses);
         }


         try {
             // --- 1. Get Total Count ---
             $sql_count = "SELECT COUNT(*) {$sql_base}";
             $stmt_count = $conn->prepare($sql_count);
             if (!$stmt_count) {
                 throw new mysqli_sql_exception("Prepare failed for finance history COUNT query: " . $conn->error);
             }
              // Bind parameters for the COUNT query (same as WHERE clauses)
             if (!empty($bind_params)) {
                  $bind_names_count = [$bind_types];
                  for ($i = 0; $i < count($bind_params); $i++) {
                      $bind_names_count[] = &$bind_params[$i];
                  }
                 call_user_func_array([$stmt_count, 'bind_param'], $bind_names_count);
             }
             $stmt_count->execute();
             $total_records = $stmt_count->get_result()->fetch_row()[0] ?? 0;
             $stmt_count->close();


             // --- 2. Get Paginated Data ---
             $sql = "
                 SELECT
                     t.id,
                     t.user_id,
                     u_user.username,
                     u_admin.username AS admin_username,
                     t.type,
                     t.amount,
                     t.description,
                     t.created_at AS timestamp
                 {$sql_base}
                 ORDER BY t.created_at DESC
                 LIMIT ?, ?; -- Apply LIMIT and OFFSET
             ";

             $stmt = $conn->prepare($sql);
             if (!$stmt) {
                  throw new mysqli_sql_exception("Prepare failed for finance history query: " . $conn->error);
             }

             // Bind parameters for the main query: WHERE clause params + LIMIT params
             $bind_types_main = $bind_types . "ii";
             $bind_params_main = $bind_params;
             $bind_params_main[] = $offset;
             $bind_params_main[] = $limit;

             if (!empty($bind_params_main)) {
                 $bind_names_main = [$bind_types_main];
                 for ($i = 0; $i < count($bind_params_main); $i++) {
                     $bind_names_main[] = &$bind_params_main[$i];
                 }
                 call_user_func_array([$stmt, 'bind_param'], $bind_names_main);
             }

             $stmt->execute();
             $result = $stmt->get_result();

             $history = [];
             while ($row = $result->fetch_assoc()) {
                  $history[] = $row;
             }
             $stmt->close();

             echo json_encode([
                 'success' => true,
                 'history' => $history,
                 'total_records' => $total_records,
                 'current_page' => $page,
                 'items_per_page' => $limit
             ]);
             exit();

         } catch (mysqli_sql_exception $exception) {
              error_log("DB error fetching finance history: " . $exception->getMessage());
              http_response_code(500);
              echo json_encode(['success' => false, 'error' => 'Database error fetching history: ' . $exception->getMessage()]);
              exit();
          } catch (\Exception $e) {
              error_log("Error fetching finance history: " . $e->getMessage());
              http_response_code(500);
              echo json_encode(['success' => false, 'error' => 'An unexpected error occurred fetching history: ' . $e->getMessage()]);
              exit();
          }

     }
     // --- NEW: Handle get_all_game_activity action ---
     // This is for the game history table (now paginated, no live feed)
     elseif ($action === 'get_all_game_activity') {
        $page = max(1, intval($_GET['page'] ?? 1));
        $limit = max(1, intval($_GET['limit'] ?? 10));
        $offset = ($page - 1) * $limit;

        // --- Build WHERE clause (none for 'all' game activity unless filters are added) ---
        $where_clauses = []; // Add filters here if needed later (e.g., by user, outcome, date range)
        $bind_params = [];
        $bind_types = "";

        $sql_base = "
             FROM game_history gh
             JOIN users u ON gh.user_id = u.id
         ";

         if (!empty($where_clauses)) {
             $sql_base .= " WHERE " . implode(" AND ", $where_clauses);
         }


         try {
             // --- 1. Get Total Count ---
             $sql_count = "SELECT COUNT(*) {$sql_base}";
             $stmt_count = $conn->prepare($sql_count);
             if (!$stmt_count) {
                  throw new mysqli_sql_exception("Prepare failed for game activity COUNT query: " . $conn->error);
             }
              // Bind parameters for the COUNT query (same as WHERE clauses)
             if (!empty($bind_params)) {
                  $bind_names_count = [$bind_types];
                  for ($i = 0; $i < count($bind_params); $i++) {
                      $bind_names_count[] = &$bind_params[$i];
                  }
                 call_user_func_array([$stmt_count, 'bind_param'], $bind_names_count);
             }
             $stmt_count->execute();
             $total_records = $stmt_count->get_result()->fetch_row()[0] ?? 0;
             $stmt_count->close();


             // --- 2. Get Paginated Data ---
             $sql = "
                 SELECT
                     gh.id,
                     gh.user_id,
                     u.username, -- Username from joined users table
                     gh.bet_amount,
                     gh.outcome,
                     gh.profit_loss,
                     gh.played_at AS timestamp -- Alias for consistency
                 {$sql_base}
                 ORDER BY gh.played_at DESC
                 LIMIT ?, ?; -- Apply LIMIT and OFFSET
             ";

             $stmt = $conn->prepare($sql);
             if (!$stmt) {
                  throw new mysqli_sql_exception("Prepare failed for game activity query: " . $conn->error);
             }

             // Bind parameters for the main query: WHERE clause params + LIMIT params
             $bind_types_main = $bind_types . "ii";
             $bind_params_main = $bind_params;
             $bind_params_main[] = $offset;
             $bind_params_main[] = $limit;

             if (!empty($bind_params_main)) {
                 $bind_names_main = [$bind_types_main];
                 for ($i = 0; $i < count($bind_params_main); $i++) {
                     $bind_names_main[] = &$bind_params_main[$i];
                 }
                 call_user_func_array([$stmt, 'bind_param'], $bind_names_main);
             }


             $stmt->execute();
             $result = $stmt->get_result();

             $activity = [];
             while ($row = $result->fetch_assoc()) {
                  $activity[] = $row;
             }
             $stmt->close();

             echo json_encode([
                 'success' => true,
                 'activity' => $activity,
                 'total_records' => $total_records,
                 'current_page' => $page,
                 'items_per_page' => $limit
             ]);
             exit();

         } catch (mysqli_sql_exception $exception) {
              error_log("DB error fetching all game activity: " . $exception->getMessage());
              http_response_code(500);
              echo json_encode(['success' => false, 'error' => 'Database error fetching activity: ' . $exception->getMessage()]);
              exit();
          } catch (\Exception $e) {
              error_log("Error fetching all game activity: " . $e->getMessage());
              http_response_code(500);
              echo json_encode(['success' => false, 'error' => 'An unexpected error occurred fetching activity: ' . $e->getMessage()]);
              exit();
          }


    } else {
        // Method was GET, but the action was invalid or missing
        http_response_code(400);
        echo json_encode(['error' => 'Invalid GET action specified.']);
        exit(); // <-- Exit after sending invalid GET action response
    }


// Handle POST Requests
} elseif ($method === 'POST') {
    $action = $_POST['action'] ?? '';

    // Action to update game settings (Keep existing)
    if ($action === 'update_game_settings') {
        $new_jackpot_limit = isset($_POST['jackpot_mines_challenge_limit']) ? intval($_POST['jackpot_mines_challenge_limit']) : null;
         $new_jackpot_base_prize = isset($_POST['jackpot_mines_challenge_base_prize']) ? floatval($_POST['jackpot_mines_challenge_base_prize']) : null;

        $errors = [];
         if ($new_jackpot_limit === null || $new_jackpot_limit < 0) {
            $errors[] = 'Invalid jackpot winner limit (must be 0 or greater).';
        }
         if ($new_jackpot_base_prize === null || $new_jackpot_base_prize < 0) {
             $errors[] = 'Invalid jackpot base prize (must be 0 or greater).';
         }

        if (empty($errors)) {
            $conn->begin_transaction();
            $success = true;

            $stmt = $conn->prepare("UPDATE game_settings SET setting_value = ? WHERE setting_name = 'jackpot_mines_challenge_limit'");
            $stmt->bind_param("s", $new_jackpot_limit);
            if (!$stmt->execute()) $success = false;
            $stmt->close();

            $stmt = $conn->prepare("UPDATE game_settings SET setting_value = ? WHERE setting_name = 'jackpot_mines_challenge_base_prize'");
            $stmt->bind_param("s", $new_jackpot_base_prize);
            if (!$stmt->execute()) $success = false;
            $stmt->close();

            if ($success) {
                $conn->commit();
                echo json_encode(['success' => true, 'message' => 'Game settings updated.']);
                exit(); // <-- Exit after successful POST response
            } else {
                $conn->rollback();
                http_response_code(500);
                 echo json_encode(['success' => false, 'message' => 'Database update failed.', 'db_error' => $conn->error]);
                 exit(); // <-- Exit after DB error response
            }

        } else {
            http_response_code(400); // Bad request due to validation errors
            echo json_encode(['success' => false, 'message' => implode(" ", $errors)]);
            exit(); // <-- Exit after validation error response
        }
    } elseif ($action === 'process_topup') {
        $target_user_id = intval($_POST['user_id'] ?? 0);
        $amount = floatval($_POST['amount'] ?? 0);
        $payment_method = trim($_POST['payment_method'] ?? ''); // Added payment method
        $reference_number = trim($_POST['reference_number'] ?? ''); // Added reference number
        $description = trim($_POST['description'] ?? ''); // Use description for other notes


        if ($target_user_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid target user ID.']);
            exit(); // <-- Exit after validation error
        }
        // Validate amount
         if ($amount <= 0.00 || round($amount, 2) != $amount) { // Check for positive and up to 2 decimal places
             http_response_code(400);
             echo json_encode(['success' => false, 'error' => 'Invalid top-up amount (must be positive with up to 2 decimal places).']);
             exit(); // <-- Exit after validation error
         }
         if (empty($payment_method)) {
             http_response_code(400);
             echo json_encode(['success' => false, 'error' => 'Payment method is required.']);
             exit(); // <-- Exit after validation error
         }
         // Add specific validation for GCash reference if payment method is GCash
         if (strtolower($payment_method) === 'gcash' && empty($reference_number)) {
              http_response_code(400);
              echo json_encode(['success' => false, 'error' => 'Reference number is required for GCash payments.']);
              exit(); // <-- Exit after validation error
         }


        // Ensure the target user exists and is not an admin
        $stmt = $conn->prepare("SELECT id FROM users WHERE id = ? AND is_admin = 0 LIMIT 1");
        $stmt->bind_param("i", $target_user_id);
        $stmt->execute();
        $user_exists = $stmt->get_result()->num_rows > 0;
        $stmt->close();

        if (!$user_exists) {
             http_response_code(400);
             echo json_encode(['success' => false, 'error' => 'Target user not found or is an admin.']);
             exit(); // <-- Exit after user not found error
        }


        $conn->begin_transaction();
        try {
            // Add coins to user balance
            $stmt = $conn->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
            $stmt->bind_param("di", $amount, $target_user_id);
            if (!$stmt->execute()) {
                throw new mysqli_sql_exception("Failed to update user coins.");
            }
            $stmt->close();

            // Log the top-up transaction
            // MODIFIED: Ensure 'topup' type exists in transactions ENUM (handled in SQL update)
            $stmt = $conn->prepare("INSERT INTO transactions (user_id, admin_user_id, type, amount, description) VALUES (?, ?, 'topup', ?, ?)");
            $log_description_parts = ["Admin Top-up"];
             if (!empty($payment_method)) $log_description_parts[] = "Method: " . $payment_method;
             if (!empty($reference_number)) $log_description_parts[] = "Ref: " . $reference_number;
             if (!empty($description)) $log_description_parts[] = "Notes: " . $description;
            $log_description = implode(" | ", $log_description_parts);

            $stmt->bind_param("iids", $target_user_id, $admin_user_id, $amount, $log_description);
            if (!$stmt->execute()) {
                 throw new mysqli_sql_exception("Failed to log transaction.");
            }
            $stmt->close();

            $conn->commit();

             // Fetch new balance to return
             $stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
             $stmt->bind_param("i", $target_user_id);
             $stmt->execute();
             $updated_user = $stmt->get_result()->fetch_assoc();
             $new_balance_formatted = number_format($updated_user['coins'], 2, '.', ''); // Use '.' for decimal, '' for thousands separator
             $stmt->close();

            echo json_encode(['success' => true, 'message' => 'Top-up successful.', 'new_balance' => $new_balance_formatted]);
            exit(); // <-- Exit after successful POST response

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            error_log("DB error during top-up transaction for user {$target_user_id} by admin {$admin_user_id}: " . $exception->getMessage());
            http_response_code(500);
            echo json_encode(['success' => false, 'error' => 'Database error during top-up: ' . $exception->getMessage()]);
            exit(); // <-- Exit after DB error response
        } catch (\Exception $e) {
             $conn->rollback();
             error_log("Error during top-up transaction for user {$target_user_id} by admin {$admin_user_id}: " . $e->getMessage());
             http_response_code(500);
             echo json_encode(['success' => false, 'error' => 'An unexpected error occurred during top-up: ' . $e->getMessage()]);
             exit(); // <-- Exit after general error response
        }

    } elseif ($action === 'update_withdrawal_status') {
        $request_id = intval($_POST['request_id'] ?? 0);
        // MODIFIED: Expect status values from JS that match DB enum: 'pending', 'processing', 'completed', 'cancelled'
        $status = $_POST['status'] ?? '';

        if ($request_id <= 0) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid withdrawal request ID.']);
            exit(); // <-- Exit after validation error
        }

        // Validate the requested status (against DB enum values)
        $valid_statuses = ['pending', 'processing', 'completed', 'cancelled'];
        if (!in_array($status, $valid_statuses)) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => 'Invalid status value provided.']);
            exit(); // <-- Exit after validation error
        }

        // Fetch the current request details and status to check validity of transition
        // Use FOR UPDATE to lock the row while processing
        $stmt = $conn->prepare("SELECT user_id, amount, status FROM withdrawal_requests WHERE id = ? FOR UPDATE");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $request = $stmt->get_result()->fetch_assoc();
        $stmt->close(); // Close the select statement ASAP

        if (!$request) {
            http_response_code(404);
            echo json_encode(['success' => false, 'error' => 'Withdrawal request not found.']);
            exit(); // <-- Exit after request not found error
        }

        $current_status = $request['status'];
        $user_id_for_withdrawal = $request['user_id'];
        $withdrawal_amount = $request['amount'];

        // Define allowed status transitions using DB status names
        // MODIFIED: Refined allowed transitions. PENDING -> PROCESSING, PENDING -> CANCELLED. PROCESSING -> COMPLETED, PROCESSING -> CANCELLED.
        // Direct PENDING -> COMPLETED is NOT allowed in this refined flow.
        $allowed_transitions = [
            'pending' => ['processing', 'cancelled'],
            'processing' => ['completed', 'cancelled']
        ];

        // Check if the requested status is allowed from the current status
        $transition_allowed = false;
        if (isset($allowed_transitions[$current_status]) && in_array($status, $allowed_transitions[$current_status])) {
             $transition_allowed = true;
        }
        // If current status is already a final state, no transitions are allowed unless explicitly defined
        // (e.g., allowing an admin to "un-cancel" or "un-complete" - not typically desired)
        if ($current_status === 'completed' || $current_status === 'cancelled') {
             $transition_allowed = false; // Cannot change status from final states
        }
        // Special case: Allow changing TO the *same* status? No, update shouldn't do anything then.
        if ($current_status === $status) {
            http_response_code(400);
            echo json_encode(['success' => false, 'error' => "Request is already in status '{$current_status}'.", 'current_status' => $current_status, 'requested_status' => $status]);
            exit(); // <-- Exit if status is unchanged
        }


        if (!$transition_allowed) {
             http_response_code(400); // Bad request or logic error
             echo json_encode(['success' => false, 'error' => "Invalid status transition from '{$current_status}' to '{$status}'.", 'current_status' => $current_status, 'requested_status' => $status]);
             exit(); // <-- Exit after invalid transition error
        }

        $conn->begin_transaction();
        $deduction_happened = false;
        $return_happened = false;

        try {
            // --- Crucial Step: Deduct or Return coins based on transition ---
            // MODIFIED: Corrected deduction and return logic timing.

            // Deduct coins ONLY when transitioning from 'pending' TO 'processing'
            if ($current_status === 'pending' && $status === 'processing') {
                 // Get the user's current balance just before deduction, ensuring lock is still active from SELECT FOR UPDATE
                 $stmt_balance = $conn->prepare("SELECT coins FROM users WHERE id = ?"); // Use the same locked row
                 $stmt_balance->bind_param("i", $user_id_for_withdrawal);
                 $stmt_balance->execute();
                 $user_balance_row = $stmt_balance->get_result()->fetch_assoc();
                 $stmt_balance->close();

                 if (!$user_balance_row) {
                     throw new mysqli_sql_exception("User not found for withdrawal deduction (ID: {$user_id_for_withdrawal}).");
                 }

                 $current_user_coins = $user_balance_row['coins'];

                 if ($current_user_coins < $withdrawal_amount) {
                      // This indicates a severe issue - user shouldn't have less coins than requested *after* submitting.
                      // Possible race condition or manual DB edit. Log and error out.
                      throw new mysqli_sql_exception("INSUFFICIENT FUNDS ERROR: User ID {$user_id_for_withdrawal} balance ({$current_user_coins}) is less than requested withdrawal amount ({$withdrawal_amount}) for request ID {$request_id}.");
                 }

                 $new_balance = $current_user_coins - $withdrawal_amount;

                 $stmt_deduct = $conn->prepare("UPDATE users SET coins = ? WHERE id = ?");
                 $stmt_deduct->bind_param("di", $new_balance, $user_id_for_withdrawal);
                 if (!$stmt_deduct->execute()) {
                      throw new mysqli_sql_exception("Failed to deduct coins for withdrawal (ID: {$request_id}).");
                 }
                 $stmt_deduct->close();

                 // Log the withdrawal transaction (type 'withdrawal', negative amount for coins deducted)
                 // MODIFIED: Ensure 'withdrawal' type exists in transactions ENUM (handled in SQL update)
                 // NOTE: Your current SQL dump does *not* have 'withdrawal' in the transactions enum.
                 // You need to ALTER TABLE transactions MODIFY type ENUM('topup', 'referral_bonus', 'game_win', 'game_loss', 'withdrawal', 'withdrawal_pending', 'withdrawal_cancelled_return') NOT NULL;
                 // The code below assumes 'withdrawal' type is valid.
                 $stmt_log = $conn->prepare("INSERT INTO transactions (user_id, admin_user_id, type, amount, description) VALUES (?, ?, 'withdrawal', ?, ?)");
                 $log_amount = -$withdrawal_amount; // Log as negative amount to represent outflow
                 $log_description = "Withdrawal request {$request_id} status changed from '{$current_status}' to '{$status}' (Coins deducted)"; // Log the transition
                 $stmt_log->bind_param("iids", $user_id_for_withdrawal, $admin_user_id, $log_amount, $log_description);
                 if (!$stmt_log->execute()) {
                     // Log this failure but maybe don't rollback the coin deduction if that succeeded?
                     // Rollback entire transaction for safety.
                     throw new mysqli_sql_exception("Failed to log withdrawal transaction after deduction (ID: {$request_id}).");
                 }
                 $stmt_log->close();
                 $deduction_happened = true;
                 error_log("GAME_LOGIC ADMIN WITHDRAWAL: User {$user_id_for_withdrawal}, Request {$request_id}. Deducted {$withdrawal_amount} for status change from {$current_status} to {$status}.");

            }
            // Return coins ONLY when transitioning from 'processing' TO 'cancelled'
            elseif ($current_status === 'processing' && $status === 'cancelled') {
                 // Add amount back to user balance
                 $stmt_return = $conn->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
                 $stmt_return->bind_param("di", $withdrawal_amount, $user_id_for_withdrawal);
                 if (!$stmt_return->execute()) {
                      throw new mysqli_sql_exception("Failed to return coins for cancelled withdrawal (ID: {$request_id}).");
                 }
                 $stmt_return->close();

                 // Log the coin return transaction
                 // MODIFIED: Ensure 'withdrawal_cancelled_return' type exists in transactions ENUM (handled in SQL update)
                 // NOTE: Your current SQL dump does *not* have 'withdrawal_cancelled_return' in the transactions enum.
                 // You need to ALTER TABLE transactions MODIFY type ENUM('topup', 'referral_bonus', 'game_win', 'game_loss', 'withdrawal', 'withdrawal_pending', 'withdrawal_cancelled_return') NOT NULL;
                 // The code below assumes 'withdrawal_cancelled_return' type is valid.
                 $stmt_log_return = $conn->prepare("INSERT INTO transactions (user_id, admin_user_id, type, amount, description) VALUES (?, ?, 'withdrawal_cancelled_return', ?, ?)");
                 $log_amount = $withdrawal_amount; // Log as positive amount to represent inflow
                 $log_description = "Withdrawal request {$request_id} cancelled from '{$current_status}' (Coins returned)";
                 $stmt_log_return->bind_param("iids", $user_id_for_withdrawal, $admin_user_id, $log_amount, $log_description);
                 if (!$stmt_log_return->execute()) {
                      throw new mysqli_sql_exception("Failed to log coin return transaction (ID: {$request_id}).");
                 }
                 $stmt_log_return->close();
                 $return_happened = true;
                 error_log("GAME_LOGIC ADMIN WITHDRAWAL: User {$user_id_for_withdrawal}, Request {$request_id}. Returned {$withdrawal_amount} for status change from {$current_status} to {$status}.");

            }
            // No coin action for:
            // pending -> cancelled (no deduction happened yet) - Should we log this transition? Maybe add a transaction type like 'withdrawal_cancelled_noproc'
            // processing -> completed (deduction happened on pending -> processing) - Should we log this transition? Maybe add a transaction type like 'withdrawal_completed'


            // Update the withdrawal request status and processed_at timestamp
            $stmt_update = $conn->prepare("UPDATE withdrawal_requests SET status = ?, processed_by_admin_id = ?, processed_at = NOW() WHERE id = ?");
            $stmt_update->bind_param("sii", $status, $admin_user_id, $request_id);
            if (!$stmt_update->execute()) {
                 throw new mysqli_sql_exception("Failed to update withdrawal request status in DB.");
            }
            $stmt_update->close();


            $conn->commit(); // Commit the entire transaction

            echo json_encode(['success' => true, 'message' => 'Withdrawal status updated.', 'deduction_happened' => $deduction_happened, 'return_happened' => $return_happened]);
            exit(); // <-- Exit after successful POST response

        } catch (mysqli_sql_exception $exception) {
            $conn->rollback();
            error_log("DB error during withdrawal status update transaction for request {$request_id} by admin {$admin_user_id}: " . $exception->getMessage());
             // Check if the error was due to insufficient funds (specific SQLSTATE or error code might be needed)
             if (strpos($exception->getMessage(), 'INSUFFICIENT FUNDS ERROR') !== false) {
                  http_response_code(400); // Bad request or logic error
                  echo json_encode(['success' => false, 'error' => $exception->getMessage()]); // Send specific error
             } else {
                 http_response_code(500);
                 echo json_encode(['success' => false, 'error' => 'Database error updating status: ' . $exception->getMessage()]);
             }
             exit(); // <-- Exit after DB error response
        } catch (\Exception $e) {
             $conn->rollback();
             error_log("Error during withdrawal status update transaction for request {$request_id} by admin {$admin_user_id}: " . $e->getMessage());
             http_response_code(500);
             echo json_encode(['success' => false, 'error' => 'An unexpected error occurred: ' . $e->getMessage()]);
             exit(); // <-- Exit after general error response
        }

    } else {
        // Method is POST, but the action was invalid or missing
        http_response_code(400);
        echo json_encode(['error' => 'Invalid POST action specified.']);
        exit(); // <-- Exit after sending invalid POST action response
    }

} else {
    // Request method is neither GET nor POST
    http_response_code(405); // Method Not Allowed
    header('Allow: GET, POST'); // Suggest correct methods
    echo json_encode(['error' => 'Invalid request method. Allowed methods are GET and POST.']);
    exit(); // <-- Exit after sending invalid method response
}
?>