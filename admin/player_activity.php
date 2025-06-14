<?php
require_once '../config/db.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit();
}

// Get the user ID from the URL parameter
$user_id = isset($_GET['user_id']) ? intval($_GET['user_id']) : 0;

// Optional: Fetch user details to display username on the page
$username = 'Unknown User';
if ($user_id > 0) {
    if ($stmt = $conn->prepare("SELECT username FROM users WHERE id = ? LIMIT 1")) {
        $stmt->bind_param("i", $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        if ($user = $result->fetch_assoc()) {
            $username = htmlspecialchars($user['username']);
        }
        $stmt->close();
    } else {
        error_log("Failed to prepare user query for player_activity.php: " . $conn->error);
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activity Log for <?php echo $username; ?> - Admin Panel</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Add specific styles for player activity page here */
        .filter-controls {
            display: flex;
            gap: 15px;
            margin-bottom: 20px;
            align-items: center;
            flex-wrap: wrap;
        }
        .filter-controls label {
            font-weight: bold;
        }
         .filter-controls input[type="text"],
         .filter-controls select { /* Added select for items per page */
             padding: 8px;
             border: 1px solid #ccc;
             border-radius: 4px;
              width: auto; /* Auto width for date/select inputs */
         }
        .filter-controls input[type="text"] {
             width: 120px; /* Fixed width for date inputs */
         }
        .filter-controls button {
            padding: 8px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
         .filter-controls button:hover {
             background-color: #0056b3;
         }

/* Table styles inherited from style.css */
     #player-activity-table {
         width: 100%;
         border-collapse: collapse;
         margin-top: 15px;
     }
     #player-activity-table th,
     #player-activity-table td {
         border: 1px solid #ddd;
         padding: 10px;
         text-align: left;
     }
      #player-activity-table thead {
          background-color: #f2f2f2;
      }
      #player-activity-table tbody tr:nth-child(even) {
          background-color: #f9f9f9;
      }

    /* Add colors for different activity types if needed */
     .activity-topup { color: green; }
     .activity-withdrawal { color: red; } /* deduction/processed */
     .activity-withdrawal_pending { color: orange; } /* request submitted */
     .activity-withdrawal_cancelled_return { color: blue; } /* coins returned */
     .activity-game_win { color: green; } /* Player won game */
     .activity-game_loss { color: red; } /* Player lost game */
     .activity-referral_bonus { color: purple; }


    /* Pagination styles */
    .pagination-controls {
         margin-top: 20px;
         display: flex;
         justify-content: center; /* Center the pagination */
         align-items: center;
         gap: 10px; /* Space between items */
         flex-wrap: wrap; /* Allow wrapping on smaller screens */
     }

     .pagination-controls button {
          padding: 8px 12px;
          background-color: #007bff;
          color: white;
          border: none;
          border-radius: 4px;
          cursor: pointer;
          font-size: 0.9em;
     }

     .pagination-controls button:disabled {
          background-color: #cccccc;
          cursor: not-allowed;
     }

      .pagination-controls button:hover:not(:disabled) {
          background-color: #0056b3;
      }

     .pagination-info {
          font-size: 0.9em;
          color: #333;
     }

    /* Mobile adjustments */
     @media (max-width: 768px) {
          .filter-controls {
               flex-direction: column;
               align-items: stretch;
               gap: 10px;
          }
          .filter-controls label {
               width: 100%;
               text-align: left;
          }
          .filter-controls select,
          .filter-controls input[type="text"],
          .filter-controls button {
               width: 100%;
               box-sizing: border-box;
          }
           .pagination-controls {
                flex-direction: column; /* Stack pagination controls vertically */
                gap: 5px;
           }
           .pagination-controls button {
                width: 100%; /* Make buttons full width */
                box-sizing: border-box;
           }
     }

</style>

</head>
<body>
    <header>
        <h1>Admin Dashboard - Activity Log for <?php echo $username; ?></h1>
         <nav>
              <a href="index.php">Back to Dashboard</a>
              <a href="logout.php">Logout</a>
         </nav>
    </header>

<main>
     <div class="panel">
         <h2>Activity History</h2>
          <div id="player-activity-status" class="status-message"></div>

          <div class="filter-controls">
               <label for="activity-start-date">Date Range:</label>
               <input type="text" id="activity-start-date" placeholder="MM/DD/YYYY">
               <input type="text" id="activity-end-date" placeholder="MM/DD/YYYY">
               <button id="apply-activity-date-range">Apply Filter</button>

               <label for="activity-items-per-page">Items per page:</label>
               <select id="activity-items-per-page">
                   <option value="10">10</option>
                   <option value="25">25</option>
                   <option value="50">50</option>
                   <option value="100">100</option>
               </select>

                <!-- Optional: Add filter by type if needed -->
               <!-- <label for="activity-type-filter">Type:</label>
               <select id="activity-type-filter">
                   <option value="">All Types</option>
                    <option value="game">Game Activity</option>
                    <option value="finance">Financial Activity</option>
                   ... specific types ...
               </select> -->
          </div>


         <table id="player-activity-table">
             <thead>
                 <tr>
                     <th>Timestamp</th>
                     <th>Type</th>
                     <th>Amount (₱)</th>
                     <th>Profit/Loss (₱)</th> <!-- Applies mainly to game activity -->
                     <th>Description</th>
                      <!-- Add more columns if needed, e.g., game specific details -->
                      <!-- <th>Game Details</th> -->
                 </tr>
             </thead>
             <tbody>
                 <!-- Data will be loaded here by JavaScript -->
                  <tr><td colspan="5" style="text-align:center;">Loading activity log...</td></tr>
             </tbody>
         </table>

         <div class="pagination-controls">
             <button id="activity-first-page">First</button>
             <button id="activity-prev-page">Previous</button>
             <span class="pagination-info">Page <span id="activity-current-page">1</span> of <span id="activity-total-pages">1</span></span>
             <button id="activity-next-page">Next</button>
             <button id="activity-last-page">Last</button>
         </div>
     </div>
</main>

 <script>
     document.addEventListener('DOMContentLoaded', () => {
          const playerActivityTableBody = document.querySelector('#player-activity-table tbody');
          const playerActivityStatusDiv = document.getElementById('player-activity-status');
          const activityStartDateInput = document.getElementById('activity-start-date');
          const activityEndDateInput = document.getElementById('activity-end-date');
          const applyActivityDateRangeBtn = document.getElementById('apply-activity-date-range');
          const itemsPerPageSelect = document.getElementById('activity-items-per-page'); // New items per page select

          // Pagination controls
          const firstPageBtn = document.getElementById('activity-first-page');
          const prevPageBtn = document.getElementById('activity-prev-page');
          const currentPageSpan = document.getElementById('activity-current-page');
          const totalPagesSpan = document.getElementById('activity-total-pages');
          const nextPageBtn = document.getElementById('activity-next-page');
          const lastPageBtn = document.getElementById('activity-last-page');


          // Pagination state
          let currentPage = 1;
          let itemsPerPage = parseInt(itemsPerPageSelect.value); // Get default value
          let totalPages = 1; // Will be updated from API response

          // Get the user ID from the URL
          const urlParams = new URLSearchParams(window.location.search);
          const userId = urlParams.get('user_id');

          if (!userId || parseInt(userId) <= 0) {
               showStatus('Invalid user ID.', 'error');
               playerActivityTableBody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:red;">Invalid user ID provided.</td></tr>';
               // Disable filters and pagination if no valid user ID
               applyActivityDateRangeBtn.disabled = true;
               itemsPerPageSelect.disabled = true;
               firstPageBtn.disabled = true;
               prevPageBtn.disabled = true;
               nextPageBtn.disabled = true;
               lastPageBtn.disabled = true;
               return;
          }


          function showStatus(message, type = 'info', element = playerActivityStatusDiv) {
              element.textContent = message;
              element.classList.remove('status-info', 'status-success', 'status-error');
              element.className = `status-message status-${type}`;
              element.style.display = 'block';
          }

          function hideStatus(element = playerActivityStatusDiv) {
              element.style.display = 'none';
          }

          function renderPaginationControls() {
              currentPageSpan.textContent = currentPage;
              totalPagesSpan.textContent = totalPages;

              firstPageBtn.disabled = currentPage === 1;
              prevPageBtn.disabled = currentPage === 1;
              nextPageBtn.disabled = currentPage === totalPages || totalPages <= 1;
              lastPageBtn.disabled = currentPage === totalPages || totalPages <= 1;
          }


          async function loadPlayerActivity() {
              hideStatus();
              playerActivityTableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Loading activity log...</td></tr>';

              const startDate = activityStartDateInput.value;
              const endDate = activityEndDateInput.value;

              // NOTE: This API endpoint needs to be added to admin_api.php
              let apiUrl = `admin_api.php?action=get_player_activity&user_id=${encodeURIComponent(userId)}`;

              // Add pagination parameters
              apiUrl += `&page=${currentPage}&limit=${itemsPerPage}`;


              if (startDate && endDate) {
                   // Basic date format validation (MM/DD/YYYY) - Add robust validation client-side or rely on backend
                  const dateRegex = /^(0[1-9]|1[0-2])\/(0[1-9]|[1-2]\d|3[0-1])\/\d{4}$/;
                  if (!dateRegex.test(startDate) || !dateRegex.test(endDate)) {
                      showStatus('Please enter dates in MM/DD/YYYY format.', 'error');
                       // Keep loading message or show specific error in table? Let's show error in table.
                       playerActivityTableBody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:red;">Invalid date format.</td></tr>';
                       renderPaginationControls(); // Update pagination based on potentially old data or set to 0/1
                      return; // Stop execution if date format is wrong
                  }
                  apiUrl += `&startDate=${encodeURIComponent(startDate)}&endDate=${encodeURIComponent(endDate)}`;
              } // else if only one date, handle error or use default range? Backend should handle this validation.

              try {
                  const response = await fetch(apiUrl);
                  if (!response.ok) {
                      const errorDetail = await response.text();
                       if (response.status === 403) { throw new Error(`Access Denied: ${errorDetail}`); }
                      throw new Error(`Failed to fetch player activity. Server status ${response.status}. Detail: ${errorDetail}`);
                  }
                  const data = await response.json();

                  if (data.error) {
                      throw new Error(`API error fetching activity: ${data.error}`);
                  }

                  // Update pagination state from response
                  currentPage = data.current_page;
                  itemsPerPage = data.items_per_page; // Use actual limit from backend
                  totalPages = Math.ceil(data.total_records / data.items_per_page);
                   if (totalPages === 0 && data.total_records > 0) totalPages = 1; // Handle case with items < limit
                   if (totalPages === 0 && data.total_records === 0) totalPages = 1; // Handle case with 0 items

                  playerActivityTableBody.innerHTML = ''; // Clear table
                  if (Array.isArray(data.activity) && data.activity.length > 0) {
                       // Assuming activity comes sorted descending by timestamp from backend, or sort here if needed
                      data.activity.forEach(item => {
                          const row = document.createElement('tr');
                           // Use type from backend for class
                          const activityType = item.activity_type ? item.activity_type.toLowerCase().replace(/[^a-z0-9_]/g, '') : 'unknown'; // Sanitize class name
                          const typeClass = `activity-${activityType}`;

                          const timestamp = item.timestamp ? new Date(item.timestamp).toLocaleString() : 'N/A'; // Assuming timestamp column name
                          const amount = parseFloat(item.amount).toFixed(2);
                          const profitLoss = item.profit_loss !== null ? parseFloat(item.profit_loss).toFixed(2) : '--'; // Only show profit/loss if it exists

                          row.innerHTML = `
                              <td>${timestamp}</td>
                              <td><span class="${typeClass}">${item.activity_type || 'Unknown'}</span></td>
                              <td>₱${amount}</td>
                              <td>${profitLoss !== '--' ? '₱' + profitLoss : '--'}</td> <!-- Add currency only if value exists -->
                              <td>${item.description || '--'}</td>
                               <!-- Add more cells based on columns in header -->
                          `;
                          playerActivityTableBody.appendChild(row);
                      });
                  } else {
                      playerActivityTableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No activity found for this user and filter.</td></tr>';
                       // Reset total pages if no data found for filter
                       totalPages = 1; // Ensure totalPages is at least 1 even if 0 records
                       currentPage = 1;
                  }

                   // Always render pagination controls after load attempt
                   renderPaginationControls();
                   hideStatus(); // Hide loading status


              } catch (error) {
                  console.error("Error loading player activity:", error);
                  showStatus(`Error loading activity: ${error.message}`, 'error');
                  playerActivityTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: red;"><strong>Error loading activity:</strong> ${error.message}</td></tr>`;
                  // Ensure pagination controls are updated even on error
                   totalPages = 1; // Set to 1 page on error
                   currentPage = 1;
                   renderPaginationControls();
              }
          }

          // Event Listeners

          // Filter button listener
          applyActivityDateRangeBtn.addEventListener('click', () => {
               currentPage = 1; // Reset to first page when applying filters
               loadPlayerActivity();
          });

          // Items per page change listener
          itemsPerPageSelect.addEventListener('change', () => {
               itemsPerPage = parseInt(itemsPerPageSelect.value);
               currentPage = 1; // Reset to first page when changing items per page
               loadPlayerActivity();
          });


          // Pagination button listeners
          firstPageBtn.addEventListener('click', () => {
              if (currentPage > 1) {
                  currentPage = 1;
                  loadPlayerActivity();
              }
          });

          prevPageBtn.addEventListener('click', () => {
              if (currentPage > 1) {
                  currentPage--;
                  loadPlayerActivity();
              }
          });

          nextPageBtn.addEventListener('click', () => {
              if (currentPage < totalPages) {
                  currentPage++;
                  loadPlayerActivity();
              }
          });

          lastPageBtn.addEventListener('click', () => {
              if (currentPage < totalPages) {
                  currentPage = totalPages;
                  loadPlayerActivity();
              }
          });


          // Initial load
          loadPlayerActivity();

      });
  </script>
</body>
</html>