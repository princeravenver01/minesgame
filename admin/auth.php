<?php
require_once '../config/db.php';

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: login.php");
    exit();
}

$username = trim($_POST['username'] ?? '');
$password = trim($_POST['password'] ?? '');

if (empty($username) || empty($password)) {
    header("Location: login.php?error=empty");
    exit();
}

$stmt = $conn->prepare("SELECT id, password, is_admin FROM users WHERE username = ?");
$stmt->bind_param("s", $username);
$stmt->execute();
$result = $stmt->get_result();

if ($user = $result->fetch_assoc()) {
    if (password_verify($password, $user['password'])) {
        if ($user['is_admin'] == 1) {
            session_regenerate_id(true);
            
            $_SESSION['admin_id'] = $user['id'];
            $_SESSION['is_admin'] = true;

            header("Location: index.php");
            exit();
        }
    }
}

header("Location: login.php?error=invalid");
exit();