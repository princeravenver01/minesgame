<?php
// This is the new, more secure way to protect the admin panel
require_once '../config/db.php'; // This starts the session

// Check if the user is authenticated as an admin.
// If not, redirect them to the admin login page.
if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0"> <!-- Added viewport for mobile -->
    <title>Admin Panel - Mines Game</title>
    <link rel="stylesheet" href="style.css">
     <style>
         /* Admin Panel specific styles for new sections */

         /* Admin Dashboard Grid Container */
         .admin-dashboard-grid {
             display: grid;
             grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); /* Flexible grid */
             gap: 20px;
             margin-top: 20px;
             margin-bottom: 20px;
         }
          /* Ensure panels take up width they need in flex layout */
         .panel {
             /* Default panel styles */
         }
         .panel.panel-small {
             max-width: 600px; /* Example max width for smaller panels */
             margin-bottom: 0; /* Grid handles margin */
         }

         /* Top-up panel styles */
         #topup-player-panel {
              /* Specific styles for the top-up panel if needed */
         }
         /* Top-up search form */
         #topup-user-search {
             display: flex;
             gap: 10px;
             margin-bottom: 15px;
             align-items: center;
         }
          #topup-user-search label {
              font-weight: bold;
              white-space: nowrap; /* Prevent label wrapping */
          }
         #topup-user-search input[type="text"] {
             flex-grow: 1;
             padding: 8px;
             border: 1px solid #ccc;
             border-radius: 4px;
         }
          #topup-user-search button {
              padding: 8px 15px;
              background-color: #007bff; /* Blue color for search */
              color: white;
              border: none;
              border-radius: 4px;
              cursor: pointer;
               white-space: nowrap; /* Prevent button wrapping */
          }
           #topup-user-search button:hover {
               background-color: #0056b3;
           }

         /* Top-up details and action form */
         #topup-form {
             display: flex;
             flex-direction: column;
             gap: 15px;
             border-top: 1px solid #eee; /* Separator line */
             padding-top: 15px;
             margin-top: 15px;
         }
          #topup-form label {
              font-weight: bold;
          }
          #topup-form input[type="number"],
          #topup-form input[type="text"],
          #topup-form select {
              padding: 8px;
              border: 1px solid #ccc;
              border-radius: 4px;
              width: 100%;
              box-sizing: border-box;
          }
           #topup-form input[type="number"]::-webkit-outer-spin-button,
           #topup-form input[type="number"]::-webkit-inner-spin-button {
               -webkit-appearance: none;
               margin: 0;
           }
           #topup-form input[type="number"] {
               -moz-appearance: textfield;
           }

          #topup-form button {
              background-color: green; /* Green for top-up */
              color: white;
              border: none;
              padding: 10px 20px;
              border-radius: 5px;
              font-size: 1em;
              cursor: pointer;
              transition: background-color 0.2s ease;
              width: auto; /* Make button size to content */
               align-self: flex-start; /* Align button to the left */
          }
           #topup-form button:hover:not(:disabled) {
               background-color: var(--primary-dark);
           }
           #topup-form button:disabled {
               background-color: #757575;
               cursor: not-allowed;
           }
         #topup-status {
             margin-top: 15px;
             padding: 10px;
             border-radius: 4px;
             text-align: center;
             display: none;
         }
          /* Row for GCash Reference, initially hidden */
         #gcash-reference-row {
             display: none; /* Managed by JS */
             flex-direction: column;
         }


         /* Withdrawal Requests Table */
         #withdrawal-requests-table {
             width: 100%;
             border-collapse: collapse;
             margin-top: 15px;
         }
         #withdrawal-requests-table th,
         #withdrawal-requests-table td {
             border: 1px solid #ddd;
             padding: 10px;
             text-align: left;
              color: #333; /* Dark text for table content */
         }
         #withdrawal-requests-table th {
             background-color: #f2f2f2; /* Light grey header */
             font-weight: bold;
         }
         #withdrawal-requests-table tbody tr:nth-child(even) td {
             background-color: #f9f9f9; /* Alternate row color */
         }
          #withdrawal-requests-table .status-select {
              padding: 5px;
              border-radius: 4px;
              margin-right: 5px; /* Space between select and button */
          }
         #withdrawal-requests-table .process-btn {
              padding: 5px 10px;
              background-color: #ffc107; /* Yellow for process */
              color: black;
              border: none;
              border-radius: 4px;
              cursor: pointer;
              transition: background-color 0.2s ease; /* Added transition */
         }
          #withdrawal-requests-table .process-btn:hover {
              background-color: #e0a800;
          }
          #withdrawal-requests-table .process-btn:disabled {
              background-color: #ccc;
              cursor: not-allowed;
          }
         #withdrawal-status-message {
             margin-top: 15px;
             padding: 10px;
             border-radius: 4px;
             text-align: center;
             display: none;
         }
          /* MODIFIED: Added/Updated Status colors for table */
          .status-pending { color: orange; font-weight: bold;}
          .status-processing { color: #2196f3; font-weight: bold;} /* Blue for processing */
          .status-completed { color: green; font-weight: bold;} /* Green for completed */
          .status-cancelled { color: red; font-weight: bold;}
          .status-unknown { color: gray; font-weight: normal;}


         /* Profit Tracking Section */
         #profit-tracking-panel {
             background: #e9ecef; /* Light background for tracking */
             padding: 20px;
             border-radius: 8px;
             margin-top: 0; /* Adjust margin as it's in grid now */
             color: #333; /* Dark text */
         }
         #profit-tracking-panel h2 {
             text-align: center;
             margin-top: 0;
             color: #333;
             margin-bottom: 20px;
         }
         /* Profit Range Controls */
         #profit-controls {
             display: flex;
             flex-wrap: wrap; /* Allow wrapping */
             gap: 10px;
             align-items: center;
             margin-bottom: 15px;
         }
          #profit-controls label {
              font-weight: bold;
          }
          #profit-controls select {
              padding: 8px;
              border-radius: 4px;
              border: 1px solid #ccc;
              background-color: white;
          }
          #profit-date-range-inputs {
              display: flex; /* Hidden by default */
              gap: 10px;
              align-items: center;
               /* Flex item properties to allow it to take space */
               flex-grow: 1;
               min-width: 250px; /* Minimum width before wrapping */
          }
           #profit-date-range-inputs label {
                font-weight: normal; /* Reset label weight inside this div */
           }
          #profit-date-range-inputs input[type="text"] {
              padding: 8px;
              border-radius: 4px;
              border: 1px solid #ccc;
              width: 120px; /* Fixed width for date input */
              box-sizing: border-box;
          }
           #profit-apply-range-btn {
              padding: 8px 15px;
              background-color: #28a745; /* Green button */
              color: white;
              border: none;
              border-radius: 4px;
              cursor: pointer;
               white-space: nowrap;
               transition: background-color 0.2s ease; /* Added transition */
          }
          #profit-apply-range-btn:hover {
              background-color: #218838;
          }


         #profit-stats {
             display: flex;
             flex-wrap: wrap; /* Allow wrapping on small screens */
             gap: 20px;
             justify-content: center;
             text-align: center;
         }
         #profit-stats div {
             background: #fff; /* White background for stat items */
             padding: 15px;
             border-radius: 8px;
             flex: 1 1 200px; /* Flexible base width */
             min-width: 150px; /* Minimum width */
         }
          #profit-stats h4 {
              margin-top: 0;
              margin-bottom: 5px;
              color: #555;
          }
          #profit-stats span {
              font-size: 1.4em;
              font-weight: bold;
          }
          /* Colors for stats */
          #profit-stats .stat-bets span { color: #007bff; } /* Blue for Bets */
          #profit-stats .stat-payouts span { color: #dc3545; } /* Red for Payouts */
          /* Game Profit: Green for profit, Red for loss */
          #profit-stats .stat-game-profit span.status-win { color: green; }
          #profit-stats .stat-game-profit span.status-loss { color: red; }
          #profit-stats .stat-game-profit span { color: #555; } /* Default color if 0 */

          /* Player Profit: Red for profit (game loss), Green for loss (game profit) */
          #profit-stats .stat-player-profit span.status-loss { color: red; } /* Player profit is game loss */
          #profit-stats .stat-player-profit span.status-win { color: green; } /* Player loss is game profit */
           #profit-stats .stat-player-profit span { color: #555; } /* Default color if 0 */

           #profit-stats-status {
               margin-top: 15px;
               padding: 10px;
               border-radius: 4px;
               text-align: center;
               display: none;
           }

         /* New sections for History and Logs */
         .admin-history-sections {
             display: grid;
             grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
             gap: 20px;
             margin-top: 20px;
         }
          .admin-history-sections .panel {
              margin-bottom: 0; /* Grid handles margin */
          }
          .admin-history-sections ul {
              list-style: none;
              padding: 0;
          }
          .admin-history-sections li {
              margin-bottom: 10px;
          }
          .admin-history-sections a {
              display: inline-block;
              padding: 10px 15px;
              background-color: #007bff;
              color: white;
              text-decoration: none;
              border-radius: 5px;
               transition: background-color 0.2s ease;
          }
           .admin-history-sections a:hover {
               background-color: #0056b3;
           }


         /* Mobile adjustments for admin panel */
         @media (max-width: 768px) {
             header {
                 padding: 1rem;
                 flex-direction: column;
                 align-items: flex-start;
             }
              header a {
                  margin-top: 10px;
              }
             main {
                 padding: 0.5rem; /* Further reduce padding on smaller screens */
             }
             .panel {
                 padding: 1rem; /* Further reduce padding */
                  margin-bottom: 10px; /* Adjust margin below panels */
             }
             /* Admin Dashboard Grid mobile stack */
              .admin-dashboard-grid {
                  grid-template-columns: 1fr; /* Stack on mobile */
                  gap: 15px;
                  margin-top: 15px; /* Adjust margin */
              }
              .panel.panel-small {
                  max-width: none; /* Allow panels to take full width */
                  margin-bottom: 0; /* Margin handled by grid gap */
              }

             #settings-form input[type="range"],
             #settings-form input[type="number"] {
                 max-width: 100%; /* Allow full width on small screens */
             }
             #players-table th, #players-table td {
                 padding: 0.4rem; /* Reduce table padding */
                  font-size: 0.85em; /* Smaller font */
             }

             /* Mobile Top-up adjustments */
              #topup-user-search {
                  flex-direction: column;
                  gap: 8px;
                  align-items: stretch;
              }
               #topup-user-search label {
                   width: 100%;
               }
               #topup-user-search input[type="text"],
               #topup-user-search button {
                    width: 100%;
                    box-sizing: border-box;
               }
              #topup-form button {
                   width: 100%;
                   align-self: stretch;
              }


             /* Mobile Withdrawal Requests adjustments */
              #withdrawal-requests-table th,
              #withdrawal-requests-table td {
                  padding: 8px;
                   font-size: 0.9em;
              }
               /* Decide which columns to hide on small screens if necessary */
               /* Example: hide GCash Name or Number to save space */
               /* #withdrawal-requests-table th:nth-child(2), */
               /* #withdrawal-requests-table td:nth-child(2) { display: none; } */

               #withdrawal-requests-table .status-select {
                   margin-right: 3px; /* Reduce margin */
                   width: 100%; /* Make select full width */
                   margin-bottom: 5px; /* Add space below select */
               }
               #withdrawal-requests-table .process-btn {
                    padding: 4px 8px; /* Smaller button */
                    width: 100%; /* Make button full width */
               }


             /* Mobile Profit Stats adjustments */
              #profit-controls {
                  flex-direction: column;
                  align-items: stretch;
                  gap: 8px;
              }
               #profit-controls select,
               #profit-apply-range-btn {
                    width: 100%;
                    box-sizing: border-box;
               }
              #profit-date-range-inputs {
                   flex-direction: column;
                   gap: 8px;
                   min-width: auto;
                   align-items: stretch;
              }
               #profit-date-range-inputs input[type="text"] {
                    width: 100%; /* Full width on mobile */
               }


             #profit-stats {
                  flex-direction: column;
                  gap: 10px;
             }
              #profit-stats div {
                   flex: 1 1 auto; /* Stack vertically */
                   min-width: auto;
              }
              #profit-stats h4 { font-size: 1em; }
              #profit-stats span { font-size: 1.2em; }

            /* New sections mobile stack */
             .admin-history-sections {
                 grid-template-columns: 1fr; /* Stack on mobile */
                 gap: 15px;
                 margin-top: 15px;
             }
         }

     </style>
</head>
<body>
    <header>
        <h1>Admin Dashboard</h1>
        <a href="logout.php">Logout</a>
    </header>

    <main>
        <!-- Game Settings Panel (Keep existing) -->
        <div class="panel">
            <h2>Game Settings</h2>
            <div id="settings-status" class="status-message"></div> <!-- ADDED: Status message area -->
            <form id="settings-form" method="post"> <!-- ADDED method="post" -->
                <!-- Win Rate setting removed -->

                <!-- Jackpot Winner Limit -->
                 <label for="jackpot-limit" style="margin-top: 15px;">Mines Jackpot Winner Limit:</label>
                <!-- MODIFIED: Updated description text -->
                <p>Limit for the special "3 hits, 22 bombs, ₱10 bet" jackpot (0 for unlimited).</p>
                 <input type="number" id="jackpot-limit" name="jackpot_mines_challenge_limit" min="0" value="10" required>

                <!-- Jackpot Base Prize -->
                <label for="jackpot-base-prize" style="margin-top: 15px;">Mines Jackpot Base Prize:</label>
                 <p>Base amount for calculation (e.g., 50000).</p>
                 <input type="number" id="jackpot-base-prize" name="jackpot_mines_challenge_base_prize" min="0" step="0.01" value="50000.00" required>


                <button type="submit">Update Settings</button>
            </form>
        </div>

        <!-- Admin Dashboard Grid Container for new panels -->
         <div class="admin-dashboard-grid">
             <!-- New Top-up Panel -->
            <div class="panel panel-small" id="topup-player-panel">
                 <h2>Top-up Player Balance</h2>
                 <div id="topup-status" class="status-message"></div>

                 <div id="topup-user-search">
                      <label for="topup-username">Search Username:</label>
                     <input type="text" id="topup-username" placeholder="Enter username">
                     <button id="search-user-btn">Search</button>
                 </div>

                 <div id="user-details-display" style="margin-bottom: 15px;">
                     <!-- User details will be loaded here by JS -->
                 </div>

                 <form id="topup-form" method="post" style="display: none;"> <!-- ADDED method="post" -->
                      <input type="hidden" id="topup-user-id">
                      <div>
                           <label for="topup-amount">Amount to Add (₱):</label>
                           <input type="number" id="topup-amount" min="0.01" step="0.01" required>
                      </div>
                       <div>
                           <label for="topup-payment-method">Payment Method:</label>
                           <select id="topup-payment-method" required>
                                <option value="">-- Select Method --</option>
                                <option value="Gcash">GCash</option>
                                <option value="Cash">Cash</option>
                                <!-- Add other methods as needed -->
                           </select>
                       </div>
                       <div id="gcash-reference-row"> <!-- Hidden by default, shown for GCash -->
                           <label for="topup-reference-number">Reference Number (IF GCASH):</label>
                           <input type="text" id="topup-reference-number" placeholder="GCash Reference #">
                       </div>
                       <div>
                           <label for="topup-description">Notes (Optional):</label>
                           <input type="text" id="topup-description" placeholder="e.g., Manual adjustment">
                       </div>
                      <button type="submit" id="process-topup-btn">Process Top-up</button> <!-- ADDED: The Proceed Button -->
                 </form>
             </div>

             <!-- New Profit Tracking Panel -->
             <div class="panel panel-small" id="profit-tracking-panel">
                 <h2>Profit Tracking</h2>
                  <div id="profit-stats-status" class="status-message"></div>

                  <div id="profit-controls">
                       <label for="profit-range">Show:</label>
                       <select id="profit-range">
                           <option value="today">Today</option>
                           <option value="yesterday">Yesterday</option>
                           <option value="date_range">Date Range</option>
                       </select>
                       <div id="profit-date-range-inputs" style="display: none;">
                            <label for="profit-start-date">From:</label>
                           <input type="text" id="profit-start-date" placeholder="MM/DD/YYYY">
                            <label for="profit-end-date">To:</label>
                           <input type="text" id="profit-end-date" placeholder="MM/DD/YYYY">
                           <button id="profit-apply-range-btn">Apply</button>
                       </div>
                  </div>

                 <div id="profit-stats">
                     <div class="stat-bets">
                         <h4>Total Bets</h4>
                         <span id="stat-total-bets">--</span>
                     </div>
                      <div class="stat-payouts">
                          <h4>Total Payouts</h4>
                          <span id="stat-total-payouts">--</span>
                      </div>
                       <div class="stat-player-profit"> <!-- Swapped order with game profit for better readability? Or keep game first? Let's keep game first for consistency with PHP calc -->
                           <h4>Total Game Profit</h4> <!-- Renamed from Player Profit -->
                           <span id="stat-total-player-profit">--</span> <!-- ID remains as stat-total-player-profit for now -->
                       </div>
                      <div class="stat-game-profit"> <!-- Swapped order -->
                          <h4>Total Player Profit</h4> <!-- Renamed from Game Profit -->
                          <span id="stat-total-game-profit">--</span> <!-- ID remains as stat-total-game-profit for now -->
                      </div>
                 </div>
             </div>
         </div>


        <!-- Players Table (Keep existing) -->
        <div class="panel">
            <h2>Players</h2>
            <table id="players-table">
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>Username</th>
                        <th>Coins</th>
                        <th>Last Played</th>
                        <th>Actions</th> <!-- ADDED: Actions column header -->
                    </tr>
                </thead>
                <tbody>
                    <!-- Player data will be inserted here by JavaScript -->
                </tbody>
            </table>
        </div>

         <!-- New Withdrawal Requests Panel -->
         <div class="panel">
             <h2>Withdrawal Requests</h2>
              <div id="withdrawal-status-message" class="status-message"></div>
             <table id="withdrawal-requests-table">
                 <thead>
                     <tr>
                         <th>User</th>
                         <th>GCash Details</th>
                         <th>Amount</th>
                         <th>Requested At</th>
                         <th>Status</th>
                         <th>Action</th>
                     </tr>
                 </thead>
                 <tbody>
                     <!-- Withdrawal data will be inserted here by JavaScript -->
                     <tr><td colspan="6" style="text-align:center;">Loading requests...</td></tr>
                 </tbody>
             </table>
         </div>

        <!-- New Sections for History and Logs -->
        <div class="admin-history-sections">
             <div class="panel">
                 <h2>History & Logs</h2>
                 <ul>
                     <li>
                         <!-- NOTE: The page admin/finance_history.php and its API endpoint need to be created separately -->
                          <a href="finance_history.php">Withdrawal and Deposit History</a>
                     </li>
                      <li>
                          <!-- NOTE: The page admin/game_activity.php and its API endpoint needs to be created separately -->
                          <a href="game_activity.php">General Game Activity Log</a>
                      </li>
                     <!-- Individual Player Activity Log is linked from the Players table -->
                 </ul>
             </div>
        </div>


    </main>

    <script src="admin.js"></script>
    <!-- NOTE: The Activity Log, Withdrawal/Deposit History, and General Game Activity Log
         features require new PHP and potentially JS files (e.g., player_activity.php,
         finance_history.php, game_activity.php, and corresponding API endpoints
         in admin/admin_api.php) that were not provided in the original request.
         The links above are placeholders and will not work until those files are created.
    -->
</body>
</html>