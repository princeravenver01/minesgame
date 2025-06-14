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
    <title>Finance History - Admin Panel</title>
    <link rel="stylesheet" href="style.css">
    <style>
        /* Add specific styles for finance history page here */
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
        .filter-controls select,
        .filter-controls input[type="text"] {
            padding: 8px;
            border: 1px solid #ccc;
            border-radius: 4px;
             width: auto; /* Auto width for select */
        }
         .filter-controls input[type="text"] {
             width: 120px; /* Fixed width for date inputs */
         }
        .filter-controls button {
            padding: 8px 15px;
            background-color: #007bff;
            color: white;
            border: none;
            border-radius: 4改为4px;
            cursor: pointer;
        }
         .filter-controls button:hover {
             background-color: #0056b3;
         }

/* Table styles inherited from style.css */
     #finance-history-table {
         width: 100%;
         border-collapse: collapse;
         margin-top: 15px;
     }
     #finance-history-table th,
     #finance-history-table td {
         border: 1px solid #ddd;
         padding: 10px;
         text-align: left;
     }
      #finance-history-table thead {
          background-color: #f2f2f2;
      }
      #finance-history-table tbody tr:nth-child(even) {
          background-color: #f9f9f9;
      }
     /* Add status colors if needed for type/status */
     .status-topup { color: green; }
     .status-withdrawal { color: red; }
     .status-withdrawal_pending { color: orange; } /* Corrected class name based on API enum */
     .status-withdrawal_cancelled_return { color: blue; } /* Corrected class name based on API enum */
     .status-game_win { color: green; } /* Include game types if they can appear here */
     .status-game_loss { color: red; } /* Include game types if they can appear here */
      .status-referral_bonus { color: purple; }


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
        <h1>Admin Dashboard - Finance History</h1>
        <nav>
             <a href="index.php">Back to Dashboard</a>
             <a href="logout.php">Logout</a>
        </nav>
    </header>

<main>
    <div class="panel">
        <h2>Withdrawal and Deposit History</h2>
        <div id="finance-history-status" class="status-message"></div>

        <div class="filter-controls">
            <label for="transaction-type-filter">Type:</label>
            <select id="transaction-type-filter">
                <option value="">All (Finance Types Only)</option>
                <option value="topup">Top-up</option>
                <option value="withdrawal">Withdrawal (Processed)</option>
                <option value="withdrawal_pending">Withdrawal (Pending)</option>
                 <option value="withdrawal_cancelled_return">Withdrawal (Cancelled Return)</option>
                 <option value="referral_bonus">Referral Bonus</option>
                <!-- Add other finance-related types here -->
                 <option value="game_win">Game Win (Player)</option> <!-- Added game types if you want to see them here -->
                 <option value="game_loss">Game Loss (Player)</option>
            </select>

             <label for="finance-start-date">Date Range:</label>
             <input type="text" id="finance-start-date" placeholder="MM/DD/YYYY">
             <input type="text" id="finance-end-date" placeholder="MM/DD/YYYY">
             <button id="apply-finance-date-range">Apply Filter</button>

             <label for="finance-items-per-page">Items per page:</label>
              <select id="finance-items-per-page">
                  <option value="10">10</option>
                  <option value="25">25</option>
                  <option value="50">50</option>
                  <option value="100">100</option>
              </select>

             <!-- Add a search input for username or description if needed -->
             <!-- <label for="finance-search">Search:</label>
             <input type="text" id="finance-search" placeholder="Username or description"> -->
             <!-- <button id="apply-finance-search">Search</button> -->

        </div>

        <table id="finance-history-table">
            <thead>
                <tr>
                    <th>ID</th>
                    <th>User</th>
                    <th>Admin (if applicable)</th>
                    <th>Type</th>
                    <th>Amount (₱)</th>
                    <th>Description</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
                <!-- Data will be loaded here by JavaScript -->
                 <tr><td colspan="7" style="text-align:center;">Loading finance history...</td></tr>
            </tbody>
        </table>

        <div class="pagination-controls">
            <button id="finance-first-page">First</button>
            <button id="finance-prev-page">Previous</button>
            <span class="pagination-info">Page <span id="finance-current-page">1</span> of <span id="finance-total-pages">1</span></span>
            <button id="finance-next-page">Next</button>
            <button id="finance-last-page">Last</button>
        </div>
    </div>
</main>

 <script>
     document.addEventListener('DOMContentLoaded', () => {
         const financeHistoryTableBody = document.querySelector('#finance-history-table tbody');
         const financeHistoryStatusDiv = document.getElementById('finance-history-status');
         const transactionTypeFilter = document.getElementById('transaction-type-filter');
         const financeStartDateInput = document.getElementById('finance-start-date');
         const financeEndDateInput = document.getElementById('finance-end-date');
         const applyFinanceDateRangeBtn = document.getElementById('apply-finance-date-range');
         const itemsPerPageSelect = document.getElementById('finance-items-per-page'); // New items per page select
         // const financeSearchInput = document.getElementById('finance-search'); // Uncomment if adding search
         // const applyFinanceSearchBtn = document.getElementById('apply-finance-search'); // Uncomment if adding search

         // Pagination controls
         const firstPageBtn = document.getElementById('finance-first-page');
         const prevPageBtn = document.getElementById('finance-prev-page');
         const currentPageSpan = document.getElementById('finance-current-page');
         const totalPagesSpan = document.getElementById('finance-total-pages');
         const nextPageBtn = document.getElementById('finance-next-page');
         const lastPageBtn = document.getElementById('finance-last-page');

         // Pagination state
         let currentPage = 1;
         let itemsPerPage = parseInt(itemsPerPageSelect.value); // Get default value
         let totalPages = 1; // Will be updated from API response


         function showStatus(message, type = 'info', element = financeHistoryStatusDiv) {
             element.textContent = message;
             element.classList.remove('status-info', 'status-success', 'status-error');
             element.className = `status-message status-${type}`;
             element.style.display = 'block';
         }

         function hideStatus(element = financeHistoryStatusDiv) {
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


         async function loadFinanceHistory() {
             hideStatus();
             financeHistoryTableBody.innerHTML = '<tr><td colspan="7" style="text-align:center;">Loading history...</td></tr>';

             const type = transactionTypeFilter.value;
             const startDate = financeStartDateInput.value;
             const endDate = financeEndDateInput.value;
             // const searchTerm = financeSearchInput.value; // Uncomment if adding search

             let apiUrl = `admin_api.php?action=get_finance_history`;

             // Add pagination parameters
             apiUrl += `&page=${currentPage}&limit=${itemsPerPage}`;


             // Add filters
             if (type) {
                  apiUrl += `&type=${encodeURIComponent(type)}`;
             }
             if (startDate && endDate) {
                  // Basic date format validation (MM/DD/YYYY) - Add robust validation client-side or rely on backend
                 const dateRegex = /^(0[1-9]|1[0-2])\/(0[1-9]|[1-2]\d|3[0-1])\/\d{4}$/;
                 if (!dateRegex.test(startDate) || !dateRegex.test(endDate)) {
                     showStatus('Please enter dates in MM/DD/YYYY format.', 'error');
                      financeHistoryTableBody.innerHTML = '<tr><td colspan="7" style="text-align:center; color:red;">Invalid date format.</td></tr>';
                      renderPaginationControls(); // Update pagination based on potentially old data or set to 0/1
                     return; // Stop execution if date format is wrong
                 }
                 apiUrl += `&startDate=${encodeURIComponent(startDate)}&endDate=${encodeURIComponent(endDate)}`;
             } // else if only one date, handle error or use default range? Backend should handle this validation.

              // if (searchTerm) { // Uncomment if adding search
              //    apiUrl += `&search=${encodeURIComponent(searchTerm)}`;
              // }


             try {
                 // NOTE: This API endpoint needs to be added to admin_api.php
                 const response = await fetch(apiUrl);
                 if (!response.ok) {
                     const errorDetail = await response.text();
                     if (response.status === 403) { throw new Error(`Access Denied: ${errorDetail}`); }
                     throw new Error(`Failed to fetch finance history. Server status ${response.status}. Detail: ${errorDetail}`);
                 }
                 const data = await response.json();

                 if (data.error) {
                     throw new Error(`API error fetching history: ${data.error}`);
                 }

                  // Update pagination state from response
                  currentPage = data.current_page;
                  itemsPerPage = data.items_per_page; // Use actual limit from backend
                  totalPages = Math.ceil(data.total_records / data.items_per_page);
                   if (totalPages === 0 && data.total_records > 0) totalPages = 1; // Handle case with items < limit
                   if (totalPages === 0 && data.total_records === 0) totalPages = 1; // Handle case with 0 items


                 financeHistoryTableBody.innerHTML = ''; // Clear table
                 if (Array.isArray(data.history) && data.history.length > 0) {
                      // Assuming history comes sorted descending from backend
                     data.history.forEach(item => {
                         const row = document.createElement('tr');
                         const amount = parseFloat(item.amount).toFixed(2);
                         const timestamp = item.timestamp ? new Date(item.timestamp).toLocaleString() : 'N/A';
                         // Sanitize class name: convert to lowercase, replace spaces/non-alphanumeric with underscore
                         const typeClass = `status-${(item.type || 'unknown').toLowerCase().replace(/[^a-z0-9_]/g, '')}`;


                         row.innerHTML = `
                             <td>${item.id || '--'}</td>
                             <td>${item.username || 'N/A'}</td> <!-- Assuming username is joined from users table in API -->
                             <td>${item.admin_username || '--'}</td> <!-- Assuming admin username is joined in API -->
                             <td><span class="${typeClass}">${item.type || 'Unknown'}</span></td>
                             <td>₱${amount}</td>
                             <td>${item.description || '--'}</td>
                             <td>${timestamp}</td>
                         `;
                         financeHistoryTableBody.appendChild(row);
                     });
                 } else {
                     financeHistoryTableBody.innerHTML = '<tr><td colspan="7" style="text-align:center;">No history found for this filter.</td></tr>';
                     // Reset total pages if no data found for filter
                    totalPages = 1; // Ensure totalPages is at least 1 even if 0 records
                    currentPage = 1;
                 }

                 // Always render pagination controls after load attempt
                 renderPaginationControls();
                 hideStatus(); // Hide loading status


             } catch (error) {
                 console.error("Error loading finance history:", error);
                 showStatus(`Error loading history: ${error.message}`, 'error');
                 financeHistoryTableBody.innerHTML = `<tr><td colspan="7" style="text-align: center; color: red;"><strong>Error loading history:</strong> ${error.message}</td></tr>`;
                 // Ensure pagination controls are updated even on error
                 totalPages = 1; // Set to 1 page on error
                 currentPage = 1;
                 renderPaginationControls();
             }
         }

         // Event Listeners

         // Filter/Search Listeners - Reset to page 1 when filter changes
         transactionTypeFilter.addEventListener('change', () => {
             currentPage = 1;
             loadFinanceHistory();
         });
         applyFinanceDateRangeBtn.addEventListener('click', () => {
             currentPage = 1;
             loadFinanceHistory();
         });
         // applyFinanceSearchBtn.addEventListener('click', () => { // Uncomment if adding search
         //    currentPage = 1;
         //    loadFinanceHistory();
         // });

         // Items per page change listener
         itemsPerPageSelect.addEventListener('change', () => {
               itemsPerPage = parseInt(itemsPerPageSelect.value);
               currentPage = 1; // Reset to first page
               loadFinanceHistory();
          });


         // Pagination button listeners
          firstPageBtn.addEventListener('click', () => {
              if (currentPage > 1) {
                  currentPage = 1;
                  loadFinanceHistory();
              }
          });

          prevPageBtn.addEventListener('click', () => {
              if (currentPage > 1) {
                  currentPage--;
                  loadFinanceHistory();
              }
          });

          nextPageBtn.addEventListener('click', () => {
              if (currentPage < totalPages) {
                  currentPage++;
                  loadFinanceHistory();
              }
          });

          lastPageBtn.addEventListener('click', () => {
              if (currentPage < totalPages) {
                  currentPage = totalPages;
                  loadFinanceHistory();
              }
          });


         // Initial load
         loadFinanceHistory();

     });
 </script>
</body>
</html>
