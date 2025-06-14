<?php
// Start session and generate simple captcha at the absolute top, BEFORE any HTML
session_start();
$num1 = rand(1, 9);
$num2 = rand(1, 9);
$_SESSION['captcha'] = $num1 + $num2;

// Now start your HTML output
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Register - Mines Game</title>
    <!-- IMPORTANT: Add the viewport meta tag for mobile responsiveness -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Link to your main stylesheet (essential for color variables and base body styles) -->
    <link rel="stylesheet" href="assets/css/style.css">

    <!-- Link to the new auth-specific stylesheet -->
    <!-- This should come AFTER style.css so it can use the color variables like var(--primary-color) -->
    <!-- If you put the styles below into a separate auth-styles.css, link it here -->
    <!-- <link rel="stylesheet" href="assets/css/auth-styles.css"> -->


    <style>
        /* --- Basic Variables (Add this if they are not in your style.css or auth-styles.css) --- */
        :root {
            --primary-color: #4CAF50; /* Example Green */
            --primary-dark: #388E3C;  /* Example Dark Green */
            --dark-bg: #212121;       /* Example Dark Background */
            --light-text: #ffffff;    /* Example Light Text */
            --win-color: #4CAF50;     /* Example Win Color (Often Green) */
        }

        body {
            background-color: var(--dark-bg);
            color: var(--light-text);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding-top: 0;
            padding-bottom: 0;
            min-height: 100vh;
            box-sizing: border-box;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: flex-start;
            width: 100%;
            overflow-x: hidden;
        }

        .auth-header-section {
            width: 100%;
            background: linear-gradient(to bottom, var(--primary-color), var(--primary-dark));
            color: white;
            text-align: center;
            padding: 40px 20px 80px 20px;
            box-sizing: border-box;
            position: relative;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }

        .auth-header-section .header-logo {
            width: 400px; /* Adjust size as needed */
            height: 110px; /* Adjust size as needed */
            margin-bottom: 15px;
            background-image: url('assets/images/GAMELOGO.png'); /* <-- REPLACE with the actual path */
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            /* Optional filters */
        }

        .auth-header-section h1 {
            color: white;
            margin: 0 0 5px 0;
            font-size: 2em;
            font-weight: bold;
        }

        .auth-header-section h2 {
            color: white;
            margin: 0;
            font-size: 1.5em;
            font-weight: normal;
        }

        .auth-card-container {
            width: 90%;
            max-width: 400px;
            background: white;
            border-radius: 20px;
            padding: 30px;
            box-sizing: border-box;
            margin: -60px auto 30px auto;
            position: relative;
            z-index: 1;
            text-align: center;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2);
            color: #333;
            flex-shrink: 0;
        }

        .auth-card-container form {
            display: flex;
            flex-direction: column;
            gap: 15px;
            margin: 20px 0;
            width: 100%;
            box-sizing: border-box;
        }

        .input-group {
            position: relative;
            width: 100%;
        }

        .input-group input,
        .auth-card-container form input[type="number"] {
            width: 100%;
            padding: 12px 15px 12px 45px; /* Default icon padding */
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            background-color: #f8f8f8;
            color: #333;
            font-size: 1em;
            box-sizing: border-box;
            outline: none;
            transition: border-color 0.2s ease, background-color 0.2s ease;
        }

         .auth-card-container form input[type="number"] {
             padding-left: 15px; /* Captcha has no icon, normal padding */
        }

        .input-group input::placeholder,
        .auth-card-container form input[type="number"]::placeholder {
            color: #999;
            opacity: 1;
        }

        .input-group input:focus,
        .auth-card-container form input[type="number"]:focus {
            border-color: var(--primary-color);
            background-color: #fff;
        }

        .input-group .input-icon {
            position: absolute;
            left: 15px;
            top: 50%;
            transform: translateY(-50%);
            width: 20px;
            height: 20px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            filter: none;
            opacity: 0.6;
            pointer-events: none;
        }

        /* Specific icons - need background-image set here or in auth-styles.css */
        /* .input-group .user-icon { background-image: url('assets/images/user-icon.png'); } */
        /* .input-group .lock-icon { background-image: url('assets/images/lock-icon.png'); } */
        /* .input-group .code-icon { background-image: url('assets/images/code-icon.png'); } */


        .auth-card-container form label[for="captcha"] {
            display: block;
            text-align: left;
            font-size: 1em;
            color: #555;
            margin-bottom: 5px;
            margin-top: 5px;
        }

        .auth-card-container button[type="submit"] {
            width: 100%;
            padding: 12px;
            background: linear-gradient(to bottom, var(--primary-color), var(--primary-dark));
            color: white;
            font-size: 1.2em;
            font-weight: bold;
            border: none;
            border-radius: 25px;
            cursor: pointer;
            transition: opacity 0.2s ease;
            margin-top: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2);
        }

        .auth-card-container button[type="submit"]:hover {
            opacity: 0.9;
        }

        .auth-card-container button[type="submit"]:active {
            opacity: 0.8;
        }

        .auth-card-container p {
            margin-top: 20px;
            font-size: 1em;
            color: #555;
        }

        .auth-card-container p a {
            color: var(--primary-color);
            text-decoration: none;
            font-weight: bold;
            transition: color 0.2s ease;
        }

        .auth-card-container p a:hover {
            color: var(--primary-dark);
            text-decoration: underline;
        }

        .auth-card-container .error-message {
            color: #ff5252;
            margin-bottom: 15px;
            font-weight: bold;
            font-size: 0.9em;
        }

        .auth-card-container .success-message {
            color: var(--win-color);
            margin-bottom: 15px;
            font-weight: bold;
            font-size: 0.9em;
        }

        @media (max-width: 600px) {
            .auth-header-section h1 {
                font-size: 1.8em;
            }
            .auth-header-section h2 {
                font-size: 1.3em;
            }
             /* Optional mobile adjustments */
        }

        @media (max-width: 360px) {
             /* Further reductions if necessary */
        }

    </style>
</head>
<body>

    <!-- Top Green Header Section -->
    <div class="auth-header-section">
        <div class="header-logo"></div>
        <h1>REGISTER</h1>
        <h2>Create a New Account</h2>
    </div>

    <!-- White Card Container -->
    <div class="auth-card-container">

        <?php
            // Display status messages based on $_GET parameters
             if (isset($_GET['status'])) {
                if ($_GET['status'] == 'captcha_error') {
                    echo '<p class="error-message">Incorrect CAPTCHA answer.</p>';
                } elseif ($_GET['status'] == 'username_exists') {
                     echo '<p class="error-message">Username already exists.</p>';
                } elseif ($_GET['status'] == 'invalid_referral') { // Added this status handler
                     echo '<p class="error-message">Invalid referral code.</p>';
                } elseif ($_GET['status'] == 'registration_failed') {
                     // Display the optional message from the URL if present
                     $message = isset($_GET['message']) ? htmlspecialchars($_GET['message']) : 'Registration failed. Please try again.';
                     echo '<p class="error-message">' . $message . '</p>';
                }
            }
        ?>

        <form action="api/register_handler.php" method="POST">
            <!-- Username Input Group with Icon -->
            <div class="input-group">
                <span class="input-icon user-icon"></span>
                <input type="text" name="username" placeholder="Username" required>
            </div>

            <!-- Password Input Group with Icon -->
            <div class="input-group">
                <span class="input-icon lock-icon"></span>
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <!-- Referral Code Input Group with Icon (Optional) -->
             <div class="input-group">
                <span class="input-icon code-icon"></span>
                <input type="text" name="referral_code" placeholder="Referral Code (Optional)">
            </div>

            <!-- CAPTCHA Label and Input -->
            <label for="captcha">What is <?php echo "$num1 + $num2"; ?>?</label>
            <input type="number" name="captcha" placeholder="Answer" required>

            <!-- Register Button -->
            <button type="submit">Register</button>
        </form>

        <!-- Switch to Login Link -->
        <p>Already have an account? <a href="login.php">Login here</a></p>
    </div>

</body>
</html>