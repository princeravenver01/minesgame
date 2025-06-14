<?php
require_once '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'];
    $password = $_POST['password'];

    if (empty($username) || empty($password)) {
        header("Location: ../login.php?status=error");
        exit();
    }

    $stmt = $conn->prepare("SELECT id, password, is_admin FROM users WHERE username = ?");
    $stmt->bind_param("s", $username);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($user = $result->fetch_assoc()) {
        // Verify the password
        if (password_verify($password, $user['password'])) {
            // Password is correct, start the session
            $_SESSION['user_id'] = $user['id'];
            $_SESSION['username'] = $username;
            $_SESSION['is_admin'] = $user['is_admin'];

            // Update last_played timestamp
            $conn->query("UPDATE users SET last_played = NOW() WHERE id = " . $user['id']);

            // Redirect admin to admin panel, others to game
            if ($user['is_admin'] == 1) {
                header("Location: ../admin/index.php");
            } else {
                header("Location: ../game.php");
            }
            exit();
        }
    }
    
    // If we reach here, login was unsuccessful
    header("Location: ../login.php?status=error");
    exit();

    $stmt->close();
    $conn->close();
}
?>