<?php
// This MUST be the very first line of the file, before any output (even whitespace)
// If there is ANYTHING before <?php, or if ../config/db.php outputs ANY text

session_start();

// Now include the DB file (assuming it doesn't output anything)
require_once '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // --- CAPTCHA Verification ---
    $submitted_captcha = $_POST['captcha'] ?? null;
    $stored_captcha = $_SESSION['captcha'] ?? null;

    // --- DEBUGGING OUTPUT ---
    // TEMPORARILY UNCOMMENT THESE LINES TO SEE THE VALUES
    echo "Debugging CAPTCHA validation:<br>";
    echo "Submitted CAPTCHA (Type: " . gettype($submitted_captcha) . "): " . $submitted_captcha . "<br>";
    echo "Stored CAPTCHA (Type: " . gettype($stored_captcha) . "): " . $stored_captcha . "<br>";
    echo "Comparison Result (intval(Submitted) === Stored): "; var_dump(intval($submitted_captcha) === $stored_captcha); echo "<br>";
    echo "<br>"; // Add some space
    // END DEBUGGING OUTPUT

    // Check if submitted captcha and stored captcha exist and match
    // Use === for strict comparison after intval()
    if ($submitted_captcha === null || $stored_captcha === null || intval($submitted_captcha) !== $stored_captcha) {
        // CAPTCHA validation failed

        // --- DEBUGGING: Temporarily comment out the header/exit below ---
        // header("Location: ../register.php?status=captcha_error");
        // exit();
        // --- END DEBUGGING ---

        unset($_SESSION['captcha']); // Unset captcha immediately on failure
        die("CAPTCHA validation failed. Please go back and try again. (See debug output above)"); // Use die temporarily for debugging output
    }

    // CAPTCHA passed, unset it so it can't be reused
    unset($_SESSION['captcha']);

    // --- Input Validation ---
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $referral_code_used = trim($_POST['referral_code']);

    if (empty($username) || empty($password)) {
         // Redirect instead of die
        header("Location: ../register.php?status=registration_failed&message=" . urlencode("Username and password cannot be empty."));
        exit();
    }
    if (strlen($password) < 6) {
         // Redirect instead of die
        header("Location: ../register.php?status=registration_failed&message=" . urlencode("Password must be at least 6 characters long."));
        exit();
    }

    // --- Check if username already exists ---
    $stmt = $conn->prepare("SELECT id FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $stmt->store_result();
    if ($stmt->num_rows > 0) {
        $stmt->close();
        header("Location: ../register.php?status=username_exists");
        exit();
    }
    $stmt->close();

    // --- Handle Referral ---
    $referred_by_id = null;
    if (!empty($referral_code_used)) {
        $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
        $stmt->bind_param("s", $referral_code_used);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($row = $result->fetch_assoc()) {
            $referred_by_id = $row['id'];
        } else {
            $stmt->close();
            header("Location: ../register.php?status=invalid_referral");
            exit();
        }
        $stmt->close();
    }

    // --- Create User ---
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
    // Ensure referral code is truly unique (basic implementation)
    do {
         $user_referral_code = substr(str_shuffle("ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789"), 0, 8);
         $stmt = $conn->prepare("SELECT id FROM users WHERE referral_code = ?");
         $stmt->bind_param("s", $user_referral_code);
         $stmt->execute();
         $stmt->store_result();
         $code_exists = $stmt->num_rows > 0;
         $stmt->close();
    } while ($code_exists);

    $initial_coins = 0.00;

    $stmt = $conn->prepare("INSERT INTO users (username, password, referral_code, referred_by, coins) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssid", $username, $hashed_password, $user_referral_code, $referred_by_id, $initial_coins);

    if ($stmt->execute()) {
        $new_user_id = $stmt->insert_id;
        // If a valid referral code was used, give bonus to the referrer
        if ($referred_by_id) {
            $bonus_amount = 500.00;
            $stmt_bonus = $conn->prepare("UPDATE users SET coins = coins + ? WHERE id = ?");
            $stmt_bonus->bind_param("di", $bonus_amount, $referred_by_id);
            $stmt_bonus->execute();
            $stmt_bonus->close();

            $stmt_trans = $conn->prepare("INSERT INTO transactions (user_id, type, amount, description) VALUES (?, 'referral_bonus', ?, ?)");
            $description = 'Bonus for referring user ' . $username;
            $stmt_trans->bind_param("ids", $referred_by_id, $bonus_amount, $description);
            $stmt_trans->execute();
            $stmt_trans->close();
        }
        $stmt->close();
        $conn->close();
        header("Location: ../login.php?status=success");
        exit();
    } else {
        $stmt->close();
        $conn->close();
        header("Location: ../register.php?status=registration_failed&message=" . urlencode($conn->error));
        exit();
    }

} else {
    // Not a POST request, redirect to registration page
    header("Location: ../register.php");
    exit();
}
?>