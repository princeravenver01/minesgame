<?php
require_once '../config/db.php';

if (!isset($_SESSION['is_admin']) || $_SESSION['is_admin'] !== true) {
    header('Location: login.php');
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Game Activity Log - Admin Panel</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Add specific styles for game activity page here */
        #game-activity-status {
             margin-bottom: 15px;
        }

        .filter-controls { /* Added filter controls for potential filtering */
             display: flex;
             gap: 15px;
             margin-bottom: 20px;
             align-items: center;
             flex-wrap: wrap;
         }
         .filter-controls label {
             font-weight: bold;
         }
          .filter-controls select,
         .filter-controls input[type="text"] {
             padding: 8px;
             border: 1px solid #ccc;
             border-radius: 4px;
             width: auto; /* Auto width */
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
     #game-activity-table {
         width: 100%;
         border-collapse: collapse;
         margin-top: 15px;
     }
     #game-activity-table th,
     #game-activity-table td {
         border: 1px solid #ddd;
         padding: 10px;
         text-align: left;
     }
      #game-activity-table thead {
          background-color: #f2f2f2;
      }
      #game-activity-table tbody tr:nth-child(even) {
          background-color: #f9f9f9;
      }

    /* Row highlighting for win/loss (game's perspective) */
    /* Note: The game history profit_loss is positive for game WIN, negative for game LOSS */
    /* Player Win = Game Loss (row turns red) */
    .game-loss-row td {
         background-color: #f8d7da; /* Light red background */
    }
    /* Player Loss = Game Win (row turns green) */
     .game-win-row td {
          background-color: #d4edda; /* Light green background */
     }
    /* Ensure original background is preserved or overridden */
    .game-loss-row:nth-child(even) td { background-color: #f5c6cb; } /* Slightly darker red for even rows */
    .game-win-row:nth-child(even) td { background-color: #c3e6cb; } /* Slightly darker green for even rows */


    /* Optional: Animation for new rows (Removed for pagination) */
    /* @keyframes highlightNewRow { ... } */
    /* .new-row { ... } */

     /* Pagination styles */
     .pagination-controls {
          margin-top: 20px;
          display: flex;
          justify-content: center;
          align-items: center;
          gap: 10px;
           flex-wrap: wrap;
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
        <h1>Admin Dashboard - Game Activity Log</h1>
         <nav>
              <a href="index.php">Back to Dashboard</a>
              <a href="logout.php">Logout</a>
         </nav>
    </header>

<main>
    <div class="panel">
        <h2>Game Activity History</h2> <!-- Changed from Live -->
         <div id="game-activity-status" class="status-message"></div>

         <div class="filter-controls">
              <label for="game-activity-start-date">Date Range:</label>
              <input type="text" id="game-activity-start-date" placeholder="MM/DD/YYYY">
              <input type="text" id="game-activity-end-date" placeholder="MM/DD/YYYY">
              <button id="apply-game-activity-date-range">Apply Filter</button>

              <label for="game-activity-items-per-page">Items per page:</label>
               <select id="game-activity-items-per-page">
                   <option value="10">10</option>
                   <option value="25">25</option>
                   <option value="50">50</option>
                   <option value="100">100</option>
               </select>

               <!-- Optional: Add filters for username, outcome etc. if needed -->
          </div>


        <table id="game-activity-table">
            <thead>
                <tr>
                    <th>Timestamp</th>
                    <th>User</th>
                    <th>Bet Amount (₱)</th>
                    <th>Outcome</th>
                    <th>Game Profit/Loss (₱)</th>
                     <!-- Add more columns if game_history stores more details like bombs, hits, etc. -->
                     <!-- <th>Bombs</th> -->
                     <!-- <th>Hits</th> -->
                </tr>
            </thead>
            <tbody>
                <!-- Data will be loaded here by JavaScript -->
                 <tr><td colspan="5" style="text-align:center;">Loading game activity...</td></tr>
            </tbody>
        </table>

        <div class="pagination-controls">
             <button id="game-first-page">First</button>
             <button id="game-prev-page">Previous</button>
             <span class="pagination-info">Page <span id="game-current-page">1</span> of <span id="game-total-pages">1</span></span>
             <button id="game-next-page">Next</button>
             <button id="game-last-page">Last</button>
         </div>
    </div>
</main>

 <script>
     document.addEventListener('DOMContentLoaded', () => {
         const gameActivityTableBody = document.querySelector('#game-activity-table tbody');
         const gameActivityStatusDiv = document.getElementById('game-activity-status');

          // Filter controls (added)
         const gameActivityStartDateInput = document.getElementById('game-activity-start-date');
         const gameActivityEndDateInput = document.getElementById('game-activity-end-date');
         const applyGameActivityDateRangeBtn = document.getElementById('apply-game-activity-date-range');
         const itemsPerPageSelect = document.getElementById('game-activity-items-per-page'); // New items per page select


         // Pagination controls
         const firstPageBtn = document.getElementById('game-first-page');
         const prevPageBtn = document.getElementById('game-prev-page');
         const currentPageSpan = document.getElementById('game-current-page');
         const totalPagesSpan = document.getElementById('game-total-pages');
         const nextPageBtn = document.getElementById('game-next-page');
         const lastPageBtn = document.getElementById('game-last-page');

         // Pagination state
         let currentPage = 1;
         let itemsPerPage = parseInt(itemsPerPageSelect.value); // Get default value
         let totalPages = 1; // Will be updated from API response


         function showStatus(message, type = 'info', element = gameActivityStatusDiv) {
             element.textContent = message;
             element.classList.remove('status-info', 'status-success', 'status-error');
             element.className = `status-message status-${type}`;
             element.style.display = 'block';
         }

         function hideStatus(element = gameActivityStatusDiv) {
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


         async function loadGameActivity() {
              hideStatus();
             gameActivityTableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Loading game activity...</td></tr>';


              const startDate = gameActivityStartDateInput.value; // Get filter values
              const endDate = gameActivityEndDateInput.value;

             let apiUrl = `admin_api.php?action=get_all_game_activity`;

             // Add pagination parameters
             apiUrl += `&page=${currentPage}&limit=${itemsPerPage}`;

             // Add date filter parameters (if implemented in backend)
             if (startDate && endDate) {
                  const dateRegex = /^(0[1-9]|1[0-2])\/(0[1-9]|[1-2]\d|3[0-1])\/\d{4}$/;
                  if (!dateRegex.test(startDate) || !dateRegex.test(endDate)) {
                       showStatus('Please enter dates in MM/DD/YYYY format.', 'error');
                       gameActivityTableBody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:red;">Invalid date format.</td></tr>';
                       renderPaginationControls();
                       return;
                  }
                 apiUrl += `&startDate=${encodeURIComponent(startDate)}&endDate=${encodeURIComponent(endDate)}`;
             }


             try {
                 const response = await fetch(apiUrl);
                 if (!response.ok) {
                     const errorDetail = await response.text();
                      if (response.status === 403) { throw new Error(`Access Denied: ${errorDetail}`); }
                     throw new Error(`Failed to fetch game activity. Server status ${response.status}. Detail: ${errorDetail}`);
                 }
                 const data = await response.json();

                 // --- Robust Check for API Response Structure ---
                 if (!data || typeof data !== 'object' || data.error || !Array.isArray(data.activity)) {
                     const errorMessage = data && data.error ? `API error fetching activity: ${data.error}` : 'Invalid or unexpected response structure from server.';
                      // Log more details if possible
                     console.error("Unexpected API response:", data);
                     throw new Error(errorMessage);
                 }
                 // --- End Robust Check ---


                 // Update pagination state from response
                 currentPage = data.current_page;
                 itemsPerPage = data.items_per_page; // Use actual limit from backend
                 totalPages = Math.ceil(data.total_records / data.items_per_page);
                  if (totalPages === 0 && data.total_records > 0) totalPages = 1; // Handle case with items < limit
                  if (totalPages === 0 && data.total_records === 0) totalPages = 1; // Handle case with 0 items


                 gameActivityTableBody.innerHTML = ''; // Clear table

                 // --- Use map().join().innerHTML for efficiency and to avoid forEach error ---
                 if (data.activity.length > 0) {
                     const rowsHtml = data.activity.map(item => {
                          const timestamp = item.timestamp ? new Date(item.timestamp).toLocaleString() : 'N/A'; // Use timestamp alias from API
                          const betAmount = parseFloat(item.bet_amount).toFixed(2);
                          const profitLoss = parseFloat(item.profit_loss).toFixed(2);

                          // Game Profit/Loss colors: Green for Game Win (Player Loss), Red for Game Loss (Player Win)
                          let rowClass = '';
                          let profitLossDisplay = profitLoss;
                          if (parseFloat(item.profit_loss) > 0) { // Game Win (Player Loss)
                               rowClass = 'game-win-row';
                               // profitLossDisplay = `+${profitLoss}`; // Show + sign if desired
                          } else if (parseFloat(item.profit_loss) < 0) { // Game Loss (Player Win)
                               rowClass = 'game-loss-row';
                               // profitLossDisplay = profitLoss; // Already has - sign
                          }

                         return `
                             <tr class="${rowClass}">
                                 <td>${timestamp}</td>
                                 <td>${item.username || 'N/A'}</td> <!-- Assuming username joined in API -->
                                 <td>₱${betAmount}</td>
                                 <td>${item.outcome || 'Unknown'}</td>
                                 <td>₱${profitLossDisplay}</td>
                                  <!-- Add more cells based on columns in header -->
                             </tr>
                         `;
                     }).join(''); // Join all HTML strings into one

                     gameActivityTableBody.innerHTML = rowsHtml; // Set the table body content once


                 } else {
                      // Only show "No activity" if nothing is found for the filters
                     gameActivityTableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No game activity found for this filter.</td></tr>';
                      // Reset total pages if no data found for filter
                     totalPages = 1; // Ensure totalPages is at least 1 even if 0 records
                     currentPage = 1;
                 }

                 // Always render pagination controls after load attempt
                 renderPaginationControls();
                 hideStatus(); // Hide loading status

             } catch (error) {
                 console.error("Error loading game activity:", error);
                 showStatus(`Error loading activity: ${error.message}`, 'error');
                 gameActivityTableBody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: red;"><strong>Error loading activity:</strong> ${error.message}</td></tr>`;
                 // Ensure pagination controls are updated even on error
                 totalPages = 1; // Set to 1 page on error
                 currentPage = 1;
                 renderPaginationControls();
             }
         }

         // Event Listeners

          // Filter button listener
         applyGameActivityDateRangeBtn.addEventListener('click', () => {
              currentPage = 1; // Reset to first page when applying filters
              loadGameActivity();
         });

         // Items per page change listener
         itemsPerPageSelect.addEventListener('change', () => {
               itemsPerPage = parseInt(itemsPerPageSelect.value);
               currentPage = 1; // Reset to first page when changing items per page
               loadGameActivity();
          });

         // Pagination button listeners
         firstPageBtn.addEventListener('click', () => {
             if (currentPage > 1) {
                 currentPage = 1;
                 loadGameActivity();
             }
         });

         prevPageBtn.addEventListener('click', () => {
             if (currentPage > 1) {
                 currentPage--;
                 loadGameActivity();
             }
         });

         nextPageBtn.addEventListener('click', () => {
             if (currentPage < totalPages) {
                 currentPage++;
                 loadGameActivity();
             }
         });

         lastPageBtn.addEventListener('click', () => {
             if (currentPage < totalPages) {
                 currentPage = totalPages;
                 loadGameActivity();
             }
         });


         // Initial load
         loadGameActivity();

         // Removed: setInterval for real-time updates
     });
 </script>
</body>
</html>