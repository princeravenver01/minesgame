<?php
    // Ensure session is started before requiring config/db.php
    if (session_status() == PHP_SESSION_NONE) {
        session_start();
    }

    require_once 'config/db.php';
    // Check if user is logged in
    if (!isset($_SESSION['user_id'])) {
        header('Location: login.php');
        exit();
    }
    $user_id = $_SESSION['user_id'];

    // Fetch user details to display balance
    $result = $conn->query("SELECT username, coins FROM users WHERE id = $user_id");
    if ($result->num_rows > 0) {
         $user = $result->fetch_assoc();
    } else {
         // Log them out or show an error
         header('Location: logout.php');
         exit();
    }
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Withdrawal - Mines Game</title>
    <link rel="stylesheet" href="assets/css/style.css">
    <!-- You might want to add a favicon -->
    <!-- <link rel="icon" href="assets/images/favicon.png" type="image/png"> -->
    <style>
         /* Specific layout styles for the withdrawal page */

         /* Header styles are mostly inherited from style.css, adding specific groups/buttons */
         header .button-group {
             display: flex;
             gap: 10px; /* Space between buttons */
             align-items: center;
         }
          .back-button {
              background-color: var(--medium-bg); /* Example color */
              color: var(--light-text);
              text-decoration: none;
              padding: 8px 15px;
              border-radius: 5px;
              font-weight: bold;
              transition: background-color 0.2s ease;
              white-space: nowrap;
              display: inline-block;
              line-height: normal;
          }
           .back-button:hover {
               background-color: #555;
           }
          .withdrawal-button, .logout-button {
              /* Inherited from game.php styles */
          }


        /* Styles for the main content area specifically for withdrawal page */
        /* This class is added to the existing .main-content-container */
        .main-content-container.withdrawal-page-container {
            /* .main-content-container is already flex column on mobile, flex row on desktop */
            /* We want withdrawal content to always stack vertically within it */
            flex-direction: column; /* Force column layout for withdrawal content */
            gap: 20px; /* Space between sections */
            padding-top: 20px; /* Add some space below the header */
            /* Max-width and margin auto from .main-content-container in style.css already center it */
            /* Ensure it takes up available vertical space */
             flex-grow: 1;
             min-height: 0; /* Required for flex item */
             overflow-y: auto; /* Allow scrolling if content is long */
        }

        /* Styles for the withdrawal form section */
        .withdrawal-form {
            background-color: var(--medium-bg); /* Background color */
            padding: 20px;
            border-radius: 8px;
            display: flex;
            flex-direction: column;
            gap: 15px; /* Space between form groups */
             max-width: 500px; /* Limit form width */
             width: 100%;
             margin: 0 auto; /* Center the form */
             box-sizing: border-box;
        }

        .withdrawal-form div {
            display: flex;
            flex-direction: column; /* Stack label and input */
        }

        .withdrawal-form label {
            color: #ccc; /* Lighter color for labels */
            margin-bottom: 5px; /* Space below label */
            font-size: 0.9em;
            text-transform: uppercase;
        }

        .withdrawal-form input[type="number"],
        .withdrawal-form input[type="text"] {
            padding: 10px;
            border: 1px solid var(--light-bg); /* Subtle border */
            border-radius: 4px;
            background-color: var(--dark-bg); /* Darker background for input */
            color: var(--light-text); /* White text */
            font-size: 1em;
             width: 100%; /* Make input take full width of its container */
             box-sizing: border-box; /* Include padding in width */
        }

         /* Style for the submit button */
        .withdrawal-form button {
            background-color: var(--primary-color); /* Green color */
            color: white;
            font-size: 1.1em;
            font-weight: bold;
            border: none;
            padding: 12px 20px;
            border-radius: 8px;
            cursor: pointer;
            transition: background-color 0.2s ease;
             width: auto; /* Auto width */
             align-self: center; /* Center the button */
        }

        .withdrawal-form button:hover:not(:disabled) {
            background-color: var(--primary-dark); /* Darker green on hover */
        }

        .withdrawal-form button:disabled {
            background-color: #757575;
            cursor: not-allowed;
        }

        /* Status message styling - shared with admin, but redefine here or in main style.css */
         .status-message {
             padding: 10px;
             border-radius: 4px;
             margin-bottom: 15px;
             text-align: center;
             font-weight: bold;
             display: none; /* Initially hide status messages */
         }
         .status-message.status-success {
             background-color: #4CAF50; /* Green */
             color: white;
         }
          .status-message.status-error {
              background-color: #F44336; /* Red */
              color: white;
          }
          .status-message.status-info {
              background-color: #2196F3; /* Blue */
              color: white;
          }


        /* Styles for the withdrawal history section */
        .withdrawal-history-section {
             background-color: var(--medium-bg); /* Background color */
             padding: 20px;
             border-radius: 8px;
             max-width: 800px; /* Limit history width */
             width: 100%;
             margin: 0 auto; /* Center history table */
             box-sizing: border-box;
             flex-grow: 1; /* Allow history to take remaining space */
        }

        .withdrawal-history-section h3 {
            margin-top: 0;
            margin-bottom: 15px;
            color: var(--light-text);
            text-align: center;
        }

        .withdrawal-history-table {
            width: 100%;
            border-collapse: collapse; /* Remove space between borders */
            margin-top: 10px;
        }

        .withdrawal-history-table th,
        .withdrawal-history-table td {
            padding: 10px;
            text-align: left;
            border-bottom: 1px solid var(--light-bg); /* Separator lines */
            font-size: 0.9em;
             color: var(--light-text); /* White text for table content */
             vertical-align: top;
        }

        .withdrawal-history-table th {
            background-color: var(--darker-bg); /* Darker background for headers */
            color: #aaa; /* Slightly muted header text */
            font-weight: bold;
            text-transform: uppercase;
        }

         .withdrawal-history-table tbody tr:last-child td {
             border-bottom: none; /* No border for the last row */
         }

         /* Specific status colors in the table */
         .withdrawal-history-table .status-pending { color: #ffc107; /* Amber */ font-weight: bold;}
         .withdrawal-history-table .status-releasing { color: #2196f3; /* Blue */ font-weight: bold;}
         .withdrawal-history-table .status-released { color: var(--win-color); /* Green */ font-weight: bold;}
         .withdrawal-history-table .status-cancelled { color: var(--loss-color); /* Red */ font-weight: bold;}


         /* Responsive adjustments */
         @media (max-width: 768px) {
              header .button-group {
                  gap: 5px; /* Smaller gap */
              }
              .back-button {
                  padding: 5px 10px;
                  font-size: 0.8em;
              }

               .withdrawal-form {
                   padding: 15px; /* Smaller padding on mobile */
                   max-width: none; /* Allow full width */
              }

              .withdrawal-form button {
                   font-size: 1em;
                   padding: 10px 15px;
              }

               .withdrawal-history-section {
                   padding: 15px;
                   max-width: none; /* Allow full width */
              }

              .withdrawal-history-section h3 {
                   font-size: 1.2em;
              }

              .withdrawal-history-table th,
              .withdrawal-history-table td {
                  padding: 8px; /* Smaller padding in table */
                  font-size: 0.8em; /* Smaller font size */
              }
               /* Hide columns on smaller mobile screens for history table */
               @media (max-width: 480px) {
                    .withdrawal-history-table th:nth-child(2), /* GCash Number */
                    .withdrawal-history-table td:nth-child(2),
                    .withdrawal-history-table th:nth-child(3), /* GCash Name */
                    .withdrawal-history-table td:nth-child(3)
                     {
                         display: none;
                     }
               }
         }

    </style>
</head>
<body class="new-layout">
    <header>
        <div class="header-left">
             <img src="assets/images/GAMELOGO.png" alt="ALUMNI Logo" class="logo">
             <!-- <span class="site-title"></span> No site title on small mobile -->
        </div>
        <div class="header-center">
             <div class="user-balance-display">
                COINS: ₱<span id="user-balance"><?php echo number_format($user['coins'], 2); ?></span>
             </div>
        </div>
        <div class="header-right">
            <div class="button-group">
                <!-- Button to go back to the game -->
                <a href="game.php" class="back-button">Back to Game</a>
                <a href="logout.php" class="logout-button">Logout</a>
            </div>
        </div>
    </header>

    <!-- Main Content Container for withdrawal page -->
    <!-- Add the withdrawal-page-container class here -->
    <div class="main-content-container withdrawal-page-container">

        <h2>Withdrawal</h2>

        <div id="withdrawal-status" class="status-message"></div> <!-- Status message area -->

        <form id="withdrawal-form" class="withdrawal-form">
            <div>
                <label for="withdraw-amount">Amount (₱):</label>
                <input type="number" id="withdraw-amount" min="100" step="0.01" required placeholder="Min ₱100.00">
            </div>
             <div>
                 <label for="gcash-number">GCash Number:</label>
                 <input type="text" id="gcash-number" required placeholder="e.g., 09171234567">
             </div>
             <div>
                 <label for="gcash-name">GCash Full Name:</label>
                 <input type="text" id="gcash-name" required placeholder="Full Name">
             </div>
            <button type="button" id="submit-withdrawal-btn">Submit Withdrawal Request</button>
        </form>

        <!-- Wrap history table in a div with a class -->
        <div class="withdrawal-history-section">
            <h3>My Withdrawal History</h3>
            <table id="player-withdrawal-history-table" class="withdrawal-history-table">
                <thead>
                    <tr>
                        <th>Amount</th>
                        <th>GCash Number</th>
                        <th>GCash Name</th>
                        <th>Requested At</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <!-- Player withdrawal history will be inserted here by JavaScript -->
                     <tr><td colspan="5" style="text-align:center;">Loading history...</td></tr>
                </tbody>
            </table>
        </div>

    </div> <!-- End .main-content-container -->

    <!-- Modal Overlay (Keep - although unlikely to be used on withdrawal page, good to have standard elements) -->
    <div id="modal-overlay" class="hidden">
        <div id="modal-box">
            <h2 id="modal-title">Modal Title</h2>
            <p id="modal-message">This is the modal message.</p>
            <button id="modal-close-btn">Close</button> <!-- Changed text -->
        </div>
    </div>

    <script src="assets/js/withdrawal.js"></script> <!-- Link the new JS file -->
    <script>
        // Basic modal closing if needed on this page (can remove if only used on game.php)
         document.getElementById('modal-close-btn').addEventListener('click', () => {
             document.getElementById('modal-overlay').classList.add('hidden');
         });
         // Helper to show status messages (used by withdrawal.js) - Keep this inline or move to a shared JS file
         // Keeping it here for now as it's specifically for this page's status div
        function showWithdrawalStatus(message, type = 'info') {
             const statusDiv = document.getElementById('withdrawal-status');
             statusDiv.textContent = message;
             // Remove existing status classes before adding the new one
             statusDiv.classList.remove('status-info', 'status-success', 'status-error');
             statusDiv.classList.add('status-message', `status-${type}`); // Add base class and type class
             statusDiv.style.display = 'block'; // Make it visible
        }

        function hideWithdrawalStatus() {
             const statusDiv = document.getElementById('withdrawal-status');
             statusDiv.textContent = '';
             statusDiv.className = 'status-message'; // Reset classes
             statusDiv.style.display = 'none'; // Hide it
        }

    </script>


</body>
</html>