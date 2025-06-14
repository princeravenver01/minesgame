<?php
// Requires db.php to potentially fetch settings if multiplier calculation ever depended on them,
// but currently it only depends on hits and bombs, and payout_edge_factor is hardcoded.
// Let's include it anyway for consistency, though not strictly necessary for this version.
require_once '../config/db.php'; // Ensure session/conn are available if needed later

header('Content-Type: application/json');

// Helper function for combinations C(n, k) (Same as game_logic)
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

    if ($hits > $safe_tiles) return 0; // Impossible scenario

    $probability_no_bomb_in_hits = 1.0;
    for($i = 0; $i < $hits; $i++) {
        $probability_no_bomb_in_hits *= ($safe_tiles - $i) / ($total_tiles - $i);
    }

    // --- MODIFIED: Reduced payout edge factor further ---
    $payout_edge_factor = 0.76; // Payout 76% of fair odds on revealed tiles
    // --- END MODIFIED ---

    if ($probability_no_bomb_in_hits <= 0) return 0;

    $multiplier = $payout_edge_factor / $probability_no_bomb_in_hits;

    return round($multiplier, 2);
}


$bombs = intval($_GET['bombs'] ?? 3); // Default to 3 bombs if not provided
// Ensure bombs are within valid range for calculation
if ($bombs < 1 || $bombs > 24) {
    $bombs = 3; // Use a default valid value
}

$multipliers = [];
$max_hits = 25 - $bombs; // Max possible hits for this bomb count

// Calculate the multipliers up to the maximum possible hits
for ($i = 1; $i <= $max_hits; $i++) {
     if ($i > 25) break; // Safety break
    $multipliers[] = [
        'hit' => $i,
        'multiplier' => calculate_multiplier($i, $bombs)
    ];
}

echo json_encode($multipliers);
?>