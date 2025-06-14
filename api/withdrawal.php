<?php
// Ensure session is started by db.php
require_once '../config/db.php';

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

// Constants for withdrawal limits (Match JS constant)
const MIN_WITHDRAWAL = 100;
const MAX_WITHDRAWAL = 100000;

$action = $_REQUEST['action'] ?? ''; // Use $_REQUEST to handle both POST and GET

// --- SUBMIT A WITHDRAWAL REQUEST ---
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $action === 'submit_withdrawal') {
    $amount = floatval($_POST['amount'] ?? 0);
    $gcash_number = trim($_POST['gcash_number'] ?? '');
    $gcash_name = trim($_POST['gcash_name'] ?? '');

    // Basic validation
    if ($amount <= 0) {
        echo json_encode(['error' => 'Invalid withdrawal amount.']);
        exit();
    }
    if ($amount < MIN_WITHDRAWAL) {
        echo json_encode(['error' => 'Minimum withdrawal is ' . number_format(MIN_WITHDRAWAL, 2) . '.']);
        exit();
    }
     if ($amount > MAX_WITHDRAWAL) {
         echo json_encode(['error' => 'Maximum withdrawal is ' . number_format(MAX_WITHDRAWAL, 2) . '.']);
         exit();
     }
    if (empty($gcash_number)) {
        echo json_encode(['error' => 'GCash number is required.']);
        exit();
    }
    // Basic GCash number format check
     if (!preg_match('/^(09|\+639)\d{9}$/', $gcash_number)) {
          echo json_encode(['error' => 'Invalid GCash number format. Must be 09... or +639... followed by 9 digits.']);
          exit();
     }
    if (empty($gcash_name)) {
        echo json_encode(['error' => 'GCash full name is required.']);
        exit();
    }
     // Basic name length check
     if (strlen($gcash_name) < 3) {
         echo json_encode(['error' => 'Please enter your full name.']);
         exit();
     }


    // Fetch user balance just for the check (balance is NOT deducted here)
    $stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if (!$user || $user['coins'] < $amount) {
        echo json_encode(['error' => 'Insufficient balance.']);
        exit();
    }

    // Process withdrawal request insertion using a transaction
    $conn->begin_transaction();
    $request_id = null; // Initialize request_id

    try {
        // Insert withdrawal request into the database with status 'pending'
        $stmt = $conn->prepare("INSERT INTO withdrawal_requests (user_id, amount, gcash_number, gcash_name, status) VALUES (?, ?, ?, ?, 'pending')");
        if (!$stmt) {
             throw new mysqli_sql_exception("Failed to prepare withdrawal request insert statement: " . $conn->error);
        }
        $stmt->bind_param("idss", $user_id, $amount, $gcash_number, $gcash_name);
        if (!$stmt->execute()) {
             throw new mysqli_sql_exception("Failed to insert withdrawal request: " . $stmt->error);
        }
        // Get the ID of the newly inserted request
        $request_id = $conn->insert_id;
        $stmt->close();

        // Log this as a transaction type 'withdrawal_pending' (optional, for tracking requests)
        // The amount here would be the positive value being requested for withdrawal
        $stmt = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'withdrawal_pending', ?, ?)");
         if (!$stmt) {
             // Log failure to prepare, but continue the main transaction commit
             error_log("Failed to prepare transaction insert statement in withdrawal.php submit_withdrawal: " . $conn->error);
         } else {
             $description = "Withdrawal request submitted for {$gcash_number} ({$gcash_name})";
             // Use the positive amount
             $stmt->bind_param("ids", $user_id, $amount, $description);
             if (!$stmt->execute()) {
                 // Correct the error_log string interpolation
                 $log_request_id = $request_id ?? 'N/A'; // Evaluate the request ID or fallback outside the string
                 error_log("Failed to log 'withdrawal_pending' transaction for user {$user_id} request {$log_request_id}: " . $stmt->error);
                 // Decide if this is critical enough to throw or just log. Let's just log.
             }
             $stmt->close();
         }


        $conn->commit(); // Commit the transaction if all steps succeed

        // Fetch the *current* balance (which wasn't deducted) to return
         $stmt = $conn->prepare("SELECT coins FROM users WHERE id = ?");
         if (!$stmt) {
             // Log failure to prepare, but return success anyway since request was logged
             error_log("Failed to prepare select balance statement after withdrawal submission: " . $conn->error);
             $current_balance = $user['coins']; // Use the balance fetched earlier
         } else {
            $stmt->bind_param("i", $user_id);
            $stmt->execute();
            $current_balance = $stmt->get_result()->fetch_assoc()['coins'];
            $stmt->close();
         }


        echo json_encode(['success' => true, 'message' => 'Withdrawal request submitted successfully. It is now pending admin approval.', 'new_balance' => number_format($current_balance, 2)]);

    } catch (mysqli_sql_exception $exception) {
        $conn->rollback(); // Rollback changes if any step fails
        error_log("DB error during withdrawal transaction submission for user {$user_id}: " . $exception->getMessage());
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Database error during withdrawal submission.']);
    } catch (\Exception $e) {
         $conn->rollback();
         error_log("Unexpected error during withdrawal transaction submission for user {$user_id}: " . $e->getMessage());
         http_response_code(500);
         echo json_encode(['error' => 'An unexpected error occurred during submission.']);
    }
    exit();
}

// --- GET PLAYER'S WITHDRAWAL HISTORY ---
if ($_SERVER['REQUEST_METHOD'] === 'GET' && $action === 'get_player_history') {
    try {
        // Select relevant columns from withdrawal_requests
        $stmt = $conn->prepare("SELECT amount, gcash_number, gcash_name, requested_at, processed_at, status, admin_notes FROM withdrawal_requests WHERE user_id = ? ORDER BY requested_at DESC");
         if (!$stmt) {
             throw new mysqli_sql_exception("Failed to prepare withdrawal history select statement: " . $conn->error);
         }
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $history = [];
        while ($row = $result->fetch_assoc()) {
            $history[] = $row;
        }
        $stmt->close();

        echo json_encode(['success' => true, 'history' => $history]);

    } catch (mysqli_sql_exception $exception) {
        error_log("DB error fetching withdrawal history for user {$user_id}: " . $exception->getMessage());
        http_response_code(500); // Internal Server Error
        echo json_encode(['error' => 'Database error fetching history.']);
    } catch (\Exception $e) {
        error_log("Unexpected error fetching withdrawal history for user {$user_id}: " . $e->getMessage());
        http_response_code(500);
        echo json_encode(['error' => 'An unexpected error occurred fetching history.']);
    }
    exit();
}

// If no action was matched or method is incorrect
http_response_code(400);
echo json_encode(['error' => 'Invalid request method or action specified.']);
?>