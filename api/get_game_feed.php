<?php
require_once '../config/db.php';

header('Content-Type: application/json');

if (!$conn || $conn->connect_error) {
    http_response_code(500);
    echo json_encode(['error' => 'Database connection failed: ' . ($conn ? $conn->connect_error : 'Unknown error')]);
    exit();
}

// --- Fetch Recent Outcomes ---
// Join game_history with users to get the username
$recent_outcomes = [];
$stmt_recent = $conn->prepare("SELECT u.username, gh.outcome, gh.bet_amount, gh.profit_loss, gh.played_at, gh.bomb_count, gh.tiles_revealed 
                                FROM game_history gh 
                                JOIN users u ON gh.user_id = u.id 
                                ORDER BY gh.played_at DESC 
                                LIMIT 20"); // Get the last 20 game outcomes

if ($stmt_recent) {
    $stmt_recent->execute();
    $result_recent = $stmt_recent->get_result();
    while ($row = $result_recent->fetch_assoc()) {
        $recent_outcomes[] = $row;
    }
    $stmt_recent->close();
} else {
    error_log("Failed to prepare recent game history query: " . $conn->error);
}


// --- Fetch Top 3 Highest Wins (Profit) ---
$top_wins = [];
$stmt_top = $conn->prepare("SELECT u.username, gh.profit_loss 
                            FROM game_history gh 
                            JOIN users u ON gh.user_id = u.id 
                            WHERE gh.outcome = 'win' AND gh.profit_loss > 0 
                            ORDER BY gh.profit_loss DESC 
                            LIMIT 3");

if ($stmt_top) {
    $stmt_top->execute();
    $result_top = $stmt_top->get_result();
    while ($row = $result_top->fetch_assoc()) {
        $top_wins[] = $row;
    }
    $stmt_top->close();
} else {
     error_log("Failed to prepare top wins query: " . $conn->error);
}

// --- Fetch Admin Bomb Count Setting ---
$admin_bomb_count = get_setting($conn, 'bomb_count');


// Return combined data
echo json_encode([
    'recent_outcomes' => $recent_outcomes,
    'top_wins' => $top_wins,
    'admin_bomb_count' => (int)$admin_bomb_count // Ensure it's an integer
]);

$conn->close();
?>