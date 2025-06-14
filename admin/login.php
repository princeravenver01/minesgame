<?php
// If the admin is already logged in, redirect them to the dashboard
session_start();
if (isset($_SESSION['is_admin']) && $_SESSION['is_admin'] === true) {
    header('Location: index.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Admin Login</title>
    <link rel="stylesheet" href="style.css"> <!-- We can reuse the admin panel's CSS -->
    <style>
        body {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: #333;
        }
        .login-panel {
            background: white;
            padding: 2rem 3rem;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            width: 100%;
            max-width: 400px;
        }
        .login-panel h1 {
            text-align: center;
            margin-bottom: 1.5rem;
        }

        .login-panel h3 {
            text-align: center;

        }
        
        .login-panel input {
            width: 100%;
            padding: 10px;
            margin-bottom: 1rem;
            border: 1px solid #ddd;
            border-radius: 5px;
            box-sizing: border-box;
        }
        .login-panel button {
            width: 100%;
            padding: 12px;
            background-color: #e53935;
            color: white;
            border: none;
            border-radius: 5px;
            font-size: 1.1em;
            cursor: pointer;
        }
        .error-message {
            color: #d32f2f;
            text-align: center;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <div class="login-panel">
        <h3>DPHS-DNHS-TCNCHS ALUMNI <br> Mine Versus Coin Game</h3>
      
        <h1>Admin Panel Login</h1>

        <?php if (isset($_GET['error'])): ?>
            <p class="error-message">Invalid credentials or not an admin.</p>
        <?php endif; ?>

        <form action="auth.php" method="POST">
            <input type="text" name="username" placeholder="Username" required>
            <input type="password" name="password" placeholder="Password" required>
            <button type="submit">Login</button>
        </form>
    </div>
</body>
</html>