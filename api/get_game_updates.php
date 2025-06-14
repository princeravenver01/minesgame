<?php
require_once '../config/db.php'; // Ensure session and DB connection are available

header('Content-Type: application/json');

// Check if DB connection is valid (important if this file is called standalone)
if (!$conn || $conn->connect_error) {
    // If DB connection fails, at least return empty data structures
    echo json_encode([
        'error' => 'Database connection failed: Could not fetch game updates.',
        'feed' => [],
        'top_winners' => [],
        'jackpot_info' => [
             'challenge_description' => 'Challenge info unavailable.',
             'prize' => '0.00',
             'winner_limit' => 0,
             'current_winners' => 0,
             'is_available' => false
        ]
    ]);
     error_log("API get_game_updates: Database Connection failed.");
    exit();
}


// Helper function to get settings (already exists in db.php, but kept for clarity)
if (!function_exists('get_setting')) {
    function get_setting($conn, $setting_name) {
         if (!$conn) return null;
        $stmt = $conn->prepare("SELECT setting_value FROM game_settings WHERE setting_name = ? LIMIT 1");
         if (!$stmt) { error_log("Failed to prepare get_setting in get_game_updates: " . $conn->error); return null; } // Added source to log
        $stmt->bind_param("s", $setting_name);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
             $stmt->close();
            return $row['setting_value'];
        }
         $stmt->close();
        return null;
    }
}


$response = [];

// 1. Fetch recent game history (feed)
$feed_limit = 10; // Number of recent plays to show
// MODIFIED: Changed table join to use username from users table directly
$stmt = $conn->prepare("SELECT gh.bet_amount, gh.outcome, gh.profit_loss, u.username, gh.played_at
                        FROM game_history gh JOIN users u ON gh.user_id = u.id
                        ORDER BY gh.played_at DESC LIMIT ?");
if ($stmt) { // Added check if prepare succeeded
    $stmt->bind_param("i", $feed_limit);
    $stmt->execute();
    $feed_result = $stmt->get_result();
    $response['feed'] = $feed_result ? $feed_result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
     error_log("Failed to prepare feed query in get_game_updates: " . $conn->error);
     $response['feed'] = []; // Ensure feed key exists even on error
}


// 2. Fetch top winners (leaderboard) - based on highest profit
// Assuming 'top_wins' table exists and is populated on win/cashout.
$leaderboard_limit = 5; // Number of top winners to show
$stmt = $conn->prepare("SELECT username, profit_amount FROM top_wins ORDER BY profit_amount DESC LIMIT ?");
if ($stmt) { // Added check if prepare succeeded
    $stmt->bind_param("i", $leaderboard_limit);
    $stmt->execute();
    $top_winners_result = $stmt->get_result();
    $response['top_winners'] = $top_winners_result ? $top_winners_result->fetch_all(MYSQLI_ASSOC) : [];
    $stmt->close();
} else {
    error_log("Failed to prepare top winners query in get_game_updates: " . $conn->error);
    $response['top_winners'] = []; // Ensure top_winners key exists even on error
}


// 3. Fetch Jackpot Challenge Info
$jackpot_challenge_type = 'mines_10_22_3'; // The challenge type name isn't changing for now
$jackpot_limit_setting = get_setting($conn, 'jackpot_mines_challenge_limit'); // Get setting
$base_prize_setting = get_setting($conn, 'jackpot_mines_challenge_base_prize'); // Get setting

// Ensure settings are valid numbers, fallback to defaults if not
$jackpot_limit = ($jackpot_limit_setting !== null && is_numeric($jackpot_limit_setting)) ? (int)$jackpot_limit_setting : 0;
$base_prize = ($base_prize_setting !== null && is_numeric($base_prize_setting)) ? (float)$base_prize_setting : 0.00;

$display_prize = round($base_prize, 2); // Just display the base prize

// Check completion count for the challenge
$stmt = $conn->prepare("SELECT COUNT(*) FROM challenge_completions WHERE challenge_type = ?");
if ($stmt) { // Added check if prepare succeeded
    $stmt->bind_param("s", $jackpot_challenge_type);
    $stmt->execute();
    $completion_count = $stmt->get_result()->fetch_row()[0] ?? 0; // Default to 0 if result is null
    $stmt->close();
} else {
    error_log("Failed to prepare challenge completions query in get_game_updates: " . $conn->error);
    $completion_count = 0; // Default to 0 on error
}


$is_available = true; // Assume available unless limit is hit or base prize is 0

// Only check limit if limit is set (> 0) AND base prize is greater than 0
if ($jackpot_limit > 0 && $completion_count >= $jackpot_limit) {
    $is_available = false; // Not available if limit reached
}
// Also consider not available if base prize is zero
if ($base_prize <= 0) {
     $is_available = false;
}

// MODIFIED: Updated the challenge description text
$response['jackpot_info'] = [
    'challenge_description' => 'Hit 3 coins with 22 bombs on a ₱10 bet', // Hardcode description for banner
    'prize' => $display_prize, // Display the base prize now
    'winner_limit' => $jackpot_limit,
    'current_winners' => $completion_count,
    'is_available' => $is_available // Boolean reflecting availability based on limit and prize
];

// NOTE: Profit stats are now fetched by the admin_api.php, not here.
// This file is for general game updates visible to players.


echo json_encode($response);

// Don't close connection if other files might use it (though unlikely for this API)
// $conn->close(); // Closing here is fine if this is the only API called per request
?>