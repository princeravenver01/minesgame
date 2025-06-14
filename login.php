<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Login - Mines Game</title>
    <!-- IMPORTANT: Add the viewport meta tag for mobile responsiveness -->
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <!-- Link to your main stylesheet (essential for color variables and base body styles) -->
    <!-- <link rel="stylesheet" href="style.css"> -->

    <!-- Link to the new auth-specific stylesheet -->
    <!-- This must come AFTER style.css so it can use the color variables like var(--primary-color) -->
    <!-- <link rel="stylesheet" href="auth-styles.css"> -->


    <style>
        /* --- Basic Variables (Add this if they are not in your style.css) --- */
        /* These are needed for the provided CSS to work */
        :root {
            --primary-color: #4CAF50; /* Example Green */
            --primary-dark: #388E3C;  /* Example Dark Green */
            --dark-bg: #212121;       /* Example Dark Background */
            --light-text: #ffffff;    /* Example Light Text */
            --win-color: #4CAF50;     /* Example Win Color (Often Green) */
             /* Add other variables from your style.css as needed */
        }


        body {
            /* Use variables for background and text color */
            background-color: var(--dark-bg);
            color: var(--light-text); /* Assuming --light-text is defined in your style.css root, if not, use #ffffff */

            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; /* Or your preferred font */
            margin: 0; /* Ensure no default margin */
            padding-top: 0; /* Ensure no top padding */
            padding-bottom: 0;
            min-height: 100vh; /* Ensure body covers at least the viewport height */
            box-sizing: border-box;
            display: flex; /* Use flex to stack header and card */
            flex-direction: column;
            align-items: center; /* Center header and card horizontally */
            justify-content: flex-start; /* Align content from the top */
            width: 100%; /* Take full width */
            overflow-x: hidden; /* Prevent horizontal scrolling */
            /* Remove any game-specific padding if it was part of the original body rule */
        }

        /* --- Styles for Login and Register Pages (Matching image style - Green Theme) --- */
        /* These styles define the specific layout and appearance of the auth pages */


        /* Container for the colored header section (green area) */
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

        /* --- NEW: Style for the Header Logo --- */
        .auth-header-section .header-logo {
            width: 400px; /* Adjust size as needed */
            height: 110px; /* Adjust size as needed */
            margin-bottom: 15px; /* Space below logo */
            /* Remove background-color and border-radius if you don't want a circle behind the logo */
            /* background-color: rgba(255, 255, 255, 0.2); */
            /* border-radius: 50%; */

            /* Set your logo image */
            background-image: url('assets/images/GAMELOGO.png'); /* <-- REPLACE with the actual path to your logo */
            background-size: contain; /* or 'cover' or specific size */
            background-repeat: no-repeat;
            background-position: center;

            /* Optional: Filter the logo to white if needed (assuming your logo is dark) */
            /* filter: brightness(0) invert(1); */

             /* Optional: Add a subtle shadow to the logo */
             /* filter: drop-shadow(0px 2px 4px rgba(0, 0, 0, 0.3)); */
        }

        /* --- REMOVE or comment out the old checkmark icon styles --- */
        /*
        .auth-header-section .checkmark-icon {
            width: 60px; height: 60px; background-color: rgba(255, 255, 255, 0.2); border-radius: 50%;
            margin-bottom: 15px; display: flex; justify-content: center; align-items: center;
            font-size: 2em; font-weight: bold; line-height: 1;
        }
        .auth-header-section .checkmark-icon::before {
            content: '\2713';
             color: white;
        }
        */

        /* Style for the checkmark icon/div in the header */
        .auth-header-section .checkmark-icon {
            width: 60px; /* Icon size */
            height: 60px; /* Icon size */
            background-color: rgba(255, 255, 255, 0.2); /* Semi-transparent white circle background */
            border-radius: 50%; /* Make it a circle */
            margin-bottom: 15px;
            display: flex;
            justify-content: center;
            align-items: center;
            /* Add a white checkmark icon inside */
            font-size: 2em; /* Size for a text checkmark */
            font-weight: bold;
            line-height: 1;
        }
        .auth-header-section .checkmark-icon::before {
            content: '\2713'; /* Unicode checkmark */
             color: white;
        }


        /* Style for the main title (WELCOME!! / REGISTER) in the header */
        .auth-header-section h1 {
            color: white; /* White text */
            margin: 0 0 5px 0;
            font-size: 2em; /* Base size */
            font-weight: bold;
        }

        /* Style for the subtitle (e.g., hidden on login, visible on register) */
        .auth-header-section h2 {
            color: white; /* White text */
            margin: 0;
            font-size: 1.5em; /* Smaller than main title */
            font-weight: normal;
        }


        /* Container for the white card */
        .auth-card-container {
            width: 90%; /* Works on mobile */
            max-width: 400px; /* Prevents it from being too wide on desktop */
            background: white; /* White background */
            border-radius: 20px; /* Large border radius for the card */
            padding: 30px; /* Padding inside the card - usually fine, could adjust with media query if needed */
            box-sizing: border-box;
            margin: -60px auto 30px auto; /* Negative margin to pull up, auto left/right for centering */
            position: relative;
            z-index: 1; /* Ensure it's above the background */
            text-align: center;
            box-shadow: 0 10px 20px rgba(0, 0, 0, 0.2); /* Add shadow */
            color: #333; /* Default text color inside white card */
            flex-shrink: 0; /* Prevent card from shrinking */
        }

        /* Form styling inside the white card */
        .auth-card-container form {
            display: flex;
            flex-direction: column;
            gap: 15px; /* Space between form elements */
            margin: 20px 0; /* Space above and below the form */
            width: 100%; /* Ensure form takes full width of its container (card padding applied) */
            box-sizing: border-box;
        }

        /* Input Group (wrapper for input + icon) */
        .input-group {
            position: relative;
            width: 100%; /* Take full width of parent (form) */
        }

        .input-group input {
            width: 100%; /* Take full width of parent (input-group) */
            /* Padding for text, extra left padding for icon */
            padding: 12px 15px 12px 45px;
            border: 1px solid #e0e0e0; /* Light grey border */
            border-radius: 8px; /* Rounded corners */
            background-color: #f8f8f8; /* Very light grey background */
            color: #333; /* Dark text color */
            font-size: 1em;
            box-sizing: border-box; /* Include padding and border in width */
            outline: none;
            transition: border-color 0.2s ease, background-color 0.2s ease;
        }

        /* Input placeholder text */
        .input-group input::placeholder {
            color: #999; /* Muted grey placeholder */
            opacity: 1;
        }

        .input-group input:focus {
            border-color: var(--primary-color); /* Green border on focus */
            background-color: #fff; /* White background on focus */
        }

        /* Icon inside the input group */
        .input-group .input-icon {
            position: absolute;
            left: 15px; /* Position icon inside padding */
            top: 50%;
            transform: translateY(-50%);
            width: 20px; /* Icon size */
            height: 20px; /* Icon size */
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            /* Default icon color (dark grey/black) */
            filter: none; /* Remove any previous filters */
            opacity: 0.6; /* Slightly transparent */
            pointer-events: none; /* Ensure icon doesn't interfere with input clicks */
        }

        /* Specific icons - You need these image files in assets/images/ */
        /* .input-group .user-icon { background-image: url('assets/images/user-icon.png'); } */
        /* .input-group .lock-icon { background-image: url('assets/images/lock-icon.png'); } */


        /* Forgot Password link */
        .auth-card-container .forgot-password {
            display: block;
            text-align: right;
            font-size: 0.9em;
            margin-top: -10px; /* Pull up closer to password input */
            margin-bottom: 10px; /* Space before button */
        }
        .auth-card-container .forgot-password a {
             color: #777; /* Dark grey link */
            text-decoration: none;
            transition: color 0.2s ease;
        }
        .auth-card-container .forgot-password a:hover {
            color: var(--primary-dark); /* Green on hover */
        }

        /* Submit Button */
        .auth-card-container button[type="submit"] {
            width: 100%; /* Take full width of parent (form) */
            padding: 12px;
            /* Gradient matching the image style with green */
            background: linear-gradient(to bottom, var(--primary-color), var(--primary-dark));
            color: white;
            font-size: 1.2em;
            font-weight: bold;
            border: none;
            border-radius: 25px; /* More rounded corners like the image */
            cursor: pointer;
            transition: opacity 0.2s ease;
            margin-top: 10px;
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.2); /* Add subtle shadow */
        }

        .auth-card-container button[type="submit"]:hover {
            opacity: 0.9;
        }

        .auth-card-container button[type="submit"]:active {
            opacity: 0.8;
        }

        /* Bottom link (Don't have an account / Already a member) */
        .auth-card-container p {
            margin-top: 20px;
            font-size: 1em;
            color: #555; /* Darker grey text */
        }

        .auth-card-container p a {
            color: var(--primary-color); /* Primary green link */
            text-decoration: none;
            font-weight: bold;
            transition: color 0.2s ease;
        }

        .auth-card-container p a:hover {
            color: var(--primary-dark); /* Darker green on hover */
            text-decoration: underline;
        }

        /* Message Styling */
        .auth-card-container .error-message {
            color: #ff5252; /* Red */
            margin-bottom: 15px;
            font-weight: bold;
            font-size: 0.9em;
        }

        .auth-card-container .success-message {
            color: var(--win-color); /* Green (assuming --win-color is green) */
            margin-bottom: 15px;
            font-weight: bold;
            font-size: 0.9em;
        }

        /* --- Media Queries for Mobile Adjustments --- */

        /* Adjust header font sizes on smaller screens */
        @media (max-width: 600px) {
            .auth-header-section h1 {
                font-size: 1.8em; /* Slightly smaller title */
            }
            .auth-header-section h2 {
                font-size: 1.3em; /* Slightly smaller subtitle */
            }
             /* Optionally reduce header/card padding slightly on very small screens */
            /*
            .auth-header-section {
                padding-top: 30px;
                padding-bottom: 60px;
            }
            .auth-card-container {
                 margin-top: -40px; // Adjust negative margin to match reduced header padding
                 padding: 20px; // Slightly less internal padding
            }
            */
             /* You could also potentially slightly reduce input padding */
            /*
             .input-group input {
                 padding: 10px 15px 10px 40px;
             }
             .input-group .input-icon {
                 left: 10px;
             }
            */
        }

        /* Optional: Adjust for even smaller screens if needed */
         @media (max-width: 360px) {
             /* Further reductions if necessary */
         }


        </style>
</head>
<body>

    <!-- Top Green Header Section -->
    <div class="auth-header-section">
        <div class="header-logo"></div> <!-- Placeholder for the checkmark icon -->
        <h1>WELCOME!!</h1> <!-- Main Title as in the image -->
        <!-- H2 subtitle is not shown in the login image design, but would be here for register -->
        <!-- <h2>Create a New Account</h2> -->
    </div>

    <!-- White Card Container -->
    <div class="auth-card-container">

        <?php
            // Display status messages - Moved inside the card container to appear here
            if (isset($_GET['status'])) {
                if ($_GET['status'] == 'success') {
                    echo '<p class="success-message">Registration successful! Please log in.</p>';
                }
                if ($_GET['status'] == 'error') {
                    echo '<p class="error-message">Invalid username or password.</p>';
                }
                 if ($_GET['status'] == 'loggedout') {
                    echo '<p class="success-message">You have been logged out.</p>';
                }
            }
        ?>

        <form action="api/login_handler.php" method="POST">
            <!-- Username/Email Input Group with Icon -->
            <div class="input-group">
                <span class="input-icon user-icon"></span> <!-- Placeholder for user icon -->
                <input type="text" name="username" placeholder="Username / Email" required>
            </div>

            <!-- Password Input Group with Icon -->
            <div class="input-group">
                <span class="input-icon lock-icon"></span> <!-- Placeholder for lock icon -->
                <input type="password" name="password" placeholder="Password" required>
            </div>

            <!-- Forgot Password Link -->
            <div class="forgot-password">
                <a href="#">Forgot Password?</a> <!-- Update href to your actual forgot password page -->
            </div>

            <!-- Login Button -->
            <button type="submit">Login</button> <!-- Button text as in the image -->
        </form>

        <!-- Switch to Register Link -->
        <p>Don't have a Account? <a href="register.php">Register here</a></p> <!-- Text as in the image -->
    </div>

    <!-- No other elements below the card are part of the core design -->

</body>
</html>