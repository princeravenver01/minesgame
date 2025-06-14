<?php
require_once '../config/db.php';

if (!isset($_SESSION['user_id'])) {
    http_response_code(401);
    echo json_encode(['success' => false, 'error' => 'Not logged in']);
    exit();
}

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $user_id = $_SESSION['user_id'];
    $amount = intval($_POST['amount'] ?? 0);
    
    // Whitelist of allowed top-up amounts to prevent manipulation
    $allowed_packages = [1000, 2000, 5000, 8000, 10000, 15000, 20000, 30000, 40000, 50000];
    if (!in_array($amount, $allowed_packages)) {
        echo json_encode(['success' => false, 'error' => 'Invalid top-up package.']);
        exit();
    }

    $conn->begin_transaction();
    try {
        // Add coins to the user's account
        $stmt_update = $conn->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
        $stmt_update->bind_param("di", $amount, $user_id);
        $stmt_update->execute();

        // Log the transaction
        $description = "Simulated G-Cash Top-up of " . $amount . " coins.";
        $stmt_insert = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'topup', ?, ?)");
        $stmt_insert->bind_param("ids", $user_id, $amount, $description);
        $stmt_insert->execute();

        // Get the new balance
        $stmt_select = $conn->prepare("SELECT coins FROM users WHERE id = ?");
        $stmt_select->bind_param("i", $user_id);
        $stmt_select->execute();
        $new_balance = $stmt_select->get_result()->fetch_assoc()['coins'];

        $conn->commit();
        echo json_encode([
            'success' => true, 
            'amount' => $amount,
            'new_balance' => number_format($new_balance, 2)
        ]);

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback();
        echo json_encode(['success' => false, 'error' => 'Transaction failed.']);
    }
}
?>