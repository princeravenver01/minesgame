document.addEventListener('DOMContentLoaded', () => {
    // --- DOM Element Selection (Settings) ---
    const jackpotLimitInput = document.getElementById('jackpot-limit');
    const jackpotBasePrizeInput = document.getElementById('jackpot-base-prize');
    const settingsForm = document.getElementById('settings-form');
    const settingsStatusDiv = document.getElementById('settings-status');

    // --- DOM Element Selection (Players Table) ---
    const playersTableBody = document.querySelector('#players-table tbody');
    const playersTable = document.getElementById('players-table'); // Get table element for delegation

    // --- DOM Element Selection (Top-up) ---
    const topupUsernameInput = document.getElementById('topup-username');
    const searchUserBtn = document.getElementById('search-user-btn');
    const userDetailsDisplay = document.getElementById('user-details-display');
    const topupForm = document.getElementById('topup-form');
    const topupUserIdInput = document.getElementById('topup-user-id');
    const topupAmountInput = document.getElementById('topup-amount');
    const topupPaymentMethodSelect = document.getElementById('topup-payment-method');
    const topupReferenceNumberInput = document.getElementById('topup-reference-number');
    const topupDescriptionInput = document.getElementById('topup-description');
    const processTopupBtn = document.getElementById('process-topup-btn'); // ADDED: Select the Process button
    const topupStatusDiv = document.getElementById('topup-status');
    const gcashReferenceRow = document.getElementById('gcash-reference-row');

    // --- DOM Element Selection (Withdrawals) ---
    const withdrawalRequestsTableBody = document.querySelector('#withdrawal-requests-table tbody');
    const withdrawalStatusMessageDiv = document.getElementById('withdrawal-status-message');


     // --- DOM Element Selection (Profit Tracking) ---
     const profitRangeSelect = document.getElementById('profit-range');
     const profitDateRangeInputs = document.getElementById('profit-date-range-inputs');
     const profitStartDateInput = document.getElementById('profit-start-date');
     const profitEndDateInput = document.getElementById('profit-end-date');
     const profitApplyRangeBtn = document.getElementById('profit-apply-range-btn');
     const statTotalBetsSpan = document.getElementById('stat-total-bets');
     const statTotalPayoutsSpan = document.getElementById('stat-total-payouts');
     const statTotalGameProfitSpan = document.getElementById('stat-total-game-profit');
     const statTotalPlayerProfitSpan = document.getElementById('stat-total-player-profit');
     const profitStatsStatusDiv = document.getElementById('profit-stats-status');

     let profitRefreshInterval = null; // Variable to hold the interval ID


    // Helper to display messages (reused)
    function showStatus(message, type = 'info', element = settingsStatusDiv) {
        element.textContent = message;
        // Remove existing status classes before adding the new one
        element.classList.remove('status-info', 'status-success', 'status-error');
        element.className = `status-message status-${type}`; // Use classes for styling
        element.style.display = 'block'; // Make visible
    }

    function hideStatus(element = settingsStatusDiv) {
         element.style.display = 'none'; // Hide message
    }


    // --- Robust data loading with error handling (Modified to load all initial data) ---
    async function loadInitialData() {
        hideStatus(settingsStatusDiv); // Hide status message on load start
        hideStatus(topupStatusDiv); // Hide topup status
        hideStatus(withdrawalStatusMessageDiv); // Hide withdrawal status
        hideStatus(profitStatsStatusDiv); // Hide profit status

        await loadSettings();
        await loadAllUsers();
        await loadWithdrawalRequests();
        // Load initial profit stats (Today)
        // Stop any existing interval before starting a new one or loading manually
        stopProfitRefresh();
        await loadProfitStats('today'); // Load today's stats initially
        startProfitRefresh(); // Start refreshing today's stats
    }

    async function loadSettings() {
         try {
             const settingsResponse = await fetch('admin_api.php?action=get_settings');
             if (!settingsResponse.ok) {
                 const errorDetail = await settingsResponse.text();
                  if (settingsResponse.status === 403) { throw new Error(`Access Denied: ${errorDetail}`); }
                 throw new Error(`Failed to fetch settings. Server responded with status ${settingsResponse.status}. Detail: ${errorDetail}`);
             }
             const settings = await settingsResponse.json();
             if (settings.error) { throw new Error(`API error when fetching settings: ${settings.error}`); }

             // Populate settings form
             if (settings.jackpot_mines_challenge_limit !== undefined) {
                 const limit = parseInt(settings.jackpot_mines_challenge_limit);
                 jackpotLimitInput.value = limit;
             }
             if (settings.jackpot_mines_challenge_base_prize !== undefined) {
                  const prize = parseFloat(settings.jackpot_mines_challenge_base_prize);
                  jackpotBasePrizeInput.value = prize.toFixed(2); // Format to 2 decimal places
              }

             // Reset form state after successful load
             settingsForm.dataset.loadedFailed = 'false'; // Mark as loaded successfully
             settingsForm.classList.remove('loading-failed');

         } catch (error) {
             console.error("Error loading settings:", error);
             showStatus(`Error loading settings: ${error.message}`, 'error', settingsStatusDiv);
             settingsForm.dataset.loadedFailed = 'true';
             settingsForm.classList.add('loading-failed'); // Add a class to style the form
         }
    }

    async function loadAllUsers() {
         try {
             const usersResponse = await fetch('admin_api.php?action=get_all_users');
             if (!usersResponse.ok) {
                  const errorDetail = await usersResponse.text();
                  if (usersResponse.status === 403) { throw new Error(`Access Denied: ${errorDetail}`); }
                 throw new Error(`Failed to fetch players. Server responded with status ${usersResponse.status}. Detail: ${errorDetail}`);
             }
             const users = await usersResponse.json();
             if (users.error) { throw new Error(`API error when fetching players: ${users.error}`); }

             // Populate players table
             playersTableBody.innerHTML = ''; // Clear table before adding new data
             if (Array.isArray(users) && users.length > 0) {
                 users.forEach(user => {
                     const row = document.createElement('tr');
                     row.innerHTML = `
                         <td>${user.id}</td>
                         <td>${user.username}</td>
                         <td>${parseFloat(user.coins).toFixed(2)}</td>
                         <td>${user.last_played || 'Never'}</td>
                         <td>
                             <button class="activity-log-btn" data-user-id="${user.id}">Activity Log</button>
                         </td>
                     `;
                     playersTableBody.appendChild(row);
                 });
                 // Event listener for activity log buttons is added via delegation below
             } else {
                 const row = document.createElement('tr');
                 row.innerHTML = `<td colspan="5" style="text-align: center;">No players found.</td>`;
                 playersTableBody.appendChild(row);
             }

         } catch (error) {
             console.error("Error loading player data:", error);
             // Display a helpful error message in the table area if possible
             playersTableBody.innerHTML = `
                 <tr>
                     <td colspan="5" style="text-align: center; color: red;">
                         <strong>Error loading player data:</strong> ${error.message}
                     </td>
                 </tr>`;
         }
    }

     async function loadWithdrawalRequests() {
         hideStatus(withdrawalStatusMessageDiv);
         withdrawalRequestsTableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;">Loading requests...</td></tr>';
          try {
              const response = await fetch('admin_api.php?action=get_withdrawal_requests');
               if (!response.ok) {
                   const errorDetail = await response.text();
                    if (response.status === 403) { throw new Error(`Access Denied: ${errorDetail}`); }
                   throw new Error(`Failed to fetch withdrawal requests. Server status ${response.status}. Detail: ${errorDetail}`);
               }
              const data = await response.json();

               if (data.error) { throw new Error(`API error fetching withdrawal requests: ${data.error}`); }

              withdrawalRequestsTableBody.innerHTML = ''; // Clear table
              if (Array.isArray(data.requests) && data.requests.length > 0) {
                   data.requests.forEach(request => {
                       const row = document.createElement('tr');
                        const amount = parseFloat(request.amount).toFixed(2);
                        const requestedAt = request.requested_at ? new Date(request.requested_at).toLocaleString() : 'N/A';
                        const processedAt = request.processed_at ? new Date(request.processed_at).toLocaleString() : '--';

                        // MODIFIED: Use DB status names directly for classes and options
                        const status = request.status.toLowerCase(); // Ensure lowercase for consistent class name
                        let statusClass = '';
                        if (status === 'pending') statusClass = 'status-pending';
                        else if (status === 'processing') statusClass = 'status-processing'; // Using 'processing' class
                        else if (status === 'completed') statusClass = 'status-completed'; // Using 'completed' class
                        else if (status === 'cancelled') statusClass = 'status-cancelled';
                        else statusClass = 'status-unknown'; // Fallback for unknown status

                        const statusDisplay = status.charAt(0).toUpperCase() + status.slice(1); // Capitalize for display

                       row.innerHTML = `
                           <td>${request.username}</td>
                           <td>${request.gcash_number}<br>${request.gcash_name}</td>
                           <td>₱${amount}</td>
                           <td>${requestedAt}</td>
                           <td><span class="${statusClass}">${statusDisplay}</span></td>
                           <td>
                               ${status === 'pending' || status === 'processing' ?
                                    `<select class="status-select" data-id="${request.id}">
                                        <option value="pending" ${status === 'pending' ? 'selected' : ''}>Pending</option>
                                        <option value="processing" ${status === 'processing' ? 'selected' : ''}>Processing</option>
                                        <option value="completed" ${status === 'completed' ? 'selected' : ''}>Completed</option>
                                        <option value="cancelled" ${status === 'cancelled' ? 'selected' : ''}>Cancelled</option>
                                    </select>
                                    <button class="process-btn" data-id="${request.id}" data-action="update_withdrawal_status">Update Status</button>`
                                    :
                                    '--' // No action for Completed or Cancelled
                                }
                           </td>
                       `;
                       withdrawalRequestsTableBody.appendChild(row);
                   });
                   // Event listeners for the new buttons/selects are added via delegation below

              } else {
                  withdrawalRequestsTableBody.innerHTML = '<tr><td colspan="6" style="text-align:center;">No withdrawal requests.</td></tr>';
              }

          } catch (error) {
              console.error("Error loading withdrawal requests:", error);
              showStatus(`Error loading withdrawal requests: ${error.message}`, 'error', withdrawalStatusMessageDiv);
              withdrawalRequestsTableBody.innerHTML = `
                   <tr>
                       <td colspan="6" style="text-align: center; color: red;">
                           <strong>Error loading requests:</strong> ${error.message}
                       </td>
                   </tr>`;
          }
     }

     /**
      * Fetches and displays profit statistics for a given range.
      * @param {string} range - 'today', 'yesterday', or 'date_range'.
      * @param {string} [startDate=null] - Start date string (MM/DD/YYYY) for date_range.
      * @param {string} [endDate=null] - End date string (MM/DD/YYYY) for date_range.
      */
     async function loadProfitStats(range, startDate = null, endDate = null) {
         hideStatus(profitStatsStatusDiv);
         showStatus('Loading profit stats...', 'info', profitStatsStatusDiv);

         // Set placeholders while loading
         statTotalBetsSpan.textContent = `--`;
         statTotalPayoutsSpan.textContent = `--`;
         statTotalGameProfitSpan.textContent = `--`;
         statTotalPlayerProfitSpan.textContent = `--`;
          statTotalGameProfitSpan.classList.remove('status-win', 'status-loss');
          statTotalPlayerProfitSpan.classList.remove('status-win', 'status-loss');


         let apiUrl = `admin_api.php?action=get_profit_stats&range=${encodeURIComponent(range)}`;
         if (range === 'date_range' && startDate && endDate) {
              apiUrl += `&startDate=${encodeURIComponent(startDate)}&endDate=${encodeURIComponent(endDate)}`;
         }

         try {
             const response = await fetch(apiUrl);
              if (!response.ok) {
                  const errorDetail = await response.text();
                   if (response.status === 403) { throw new Error(`Access Denied: ${errorDetail}`); }
                  throw new Error(`Failed to fetch profit stats. Server status ${response.status}. Detail: ${errorDetail}`);
              }
             const data = await response.json();

             if (data.error) { throw new Error(`API error fetching profit stats: ${data.error}`); }

             // Update the display spans
             const totalBets = parseFloat(data.total_bets || 0).toFixed(2);
             const totalPayouts = parseFloat(data.total_payouts || 0).toFixed(2);
             const totalGameProfit = parseFloat(data.total_game_profit || 0).toFixed(2);
             const totalPlayerProfit = parseFloat(data.total_player_profit || 0).toFixed(2);


             statTotalBetsSpan.textContent = `₱${totalBets}`;
             statTotalPayoutsSpan.textContent = `₱${totalPayouts}`;
             statTotalGameProfitSpan.textContent = `₱${totalGameProfit}`;
             statTotalPlayerProfitSpan.textContent = `₱${totalPlayerProfit}`;

              // Add color based on profit/loss for the two profit stats
              // Game Profit: Positive is good (Green/Win), Negative is bad (Red/Loss)
             statTotalGameProfitSpan.classList.remove('status-win', 'status-loss'); // Clear previous classes
             if (parseFloat(totalGameProfit) > 0) {
                 statTotalGameProfitSpan.classList.add('status-win');
             } else if (parseFloat(totalGameProfit) < 0) {
                 statTotalGameProfitSpan.classList.add('status-loss');
             }

              // Player Profit: Positive is good for player (Red/Loss for game), Negative is bad for player (Green/Win for game)
             statTotalPlayerProfitSpan.classList.remove('status-win', 'status-loss'); // Clear previous classes
             if (parseFloat(totalPlayerProfit) > 0) {
                 statTotalPlayerProfitSpan.classList.add('status-loss'); // Player win is Game loss (Red/Loss)
             } else if (parseFloat(totalPlayerProfit) < 0) {
                 statTotalPlayerProfitSpan.classList.add('status-win'); // Player loss is Game win (Green/Win)
             }


             hideStatus(profitStatsStatusDiv); // Hide loading message

         } catch (error) {
             console.error("Error loading profit stats:", error);
             showStatus(`Error loading profit stats: ${error.message}`, 'error', profitStatsStatusDiv);
             // Display Error text
             statTotalBetsSpan.textContent = `Error`;
             statTotalPayoutsSpan.textContent = `Error`;
             statTotalGameProfitSpan.textContent = `Error`;
             statTotalPlayerProfitSpan.textContent = `Error`;
             statTotalGameProfitSpan.classList.remove('status-win', 'status-loss');
             statTotalPlayerProfitSpan.classList.remove('status-win', 'status-loss');
         }
     }

    // Function to stop the profit refresh interval
     function stopProfitRefresh() {
         if (profitRefreshInterval) {
             clearInterval(profitRefreshInterval);
             profitRefreshInterval = null;
             console.log("Stopped profit refresh interval.");
         }
     }

     // Function to start the profit refresh interval (only for 'today')
     function startProfitRefresh() {
         // Only start if the selected range is 'today' and no interval is running
         if (profitRangeSelect.value === 'today' && !profitRefreshInterval) {
             // Refresh 'today' stats every 60 seconds (1 minute)
             profitRefreshInterval = setInterval(() => {
                  console.log("Auto-refreshing profit stats for 'today'...");
                 loadProfitStats('today');
             }, 60000); // 60000 milliseconds = 1 minute
              console.log("Started profit refresh interval for 'today'.");
         }
     }


    // --- Handle Settings Update (Keep existing) ---
    settingsForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        hideStatus(settingsStatusDiv); // Hide previous status message

        const formData = new FormData(settingsForm);
        // Win rate is no longer in the form data
        formData.append('jackpot_mines_challenge_limit', jackpotLimitInput.value);
        formData.append('jackpot_mines_challenge_base_prize', jackpotBasePrizeInput.value);
        formData.append('action', 'update_game_settings'); // Ensure action is included

        try {
            const response = await fetch('admin_api.php', {
                method: 'POST',
                body: formData
            });

            // Check for non-200 status codes
            if (!response.ok) {
                 const errorDetail = await response.text();
                 // Check if it's a forbidden error (access denied)
                 if (response.status === 403) {
                      throw new Error(`Access Denied: ${errorDetail}`);
                 }
                throw new Error(`Server responded with status ${response.status}: ${errorDetail}`);
            }

            const result = await response.json();

            if (result.success) {
                showStatus('Settings updated successfully!', 'success', settingsStatusDiv);
                // Re-load relevant data (like profit stats or anything dependent on settings)
                loadInitialData(); // Refresh all data after settings change (including jackpot info which uses settings)
            } else {
                // Show specific error message from API if available
                showStatus('Error updating settings: ' + (result.message || 'An unknown error occurred.'), 'error', settingsStatusDiv);
                console.error("API Error Response:", result); // Log the full error response
            }
        } catch (error) {
            showStatus('A network or server error occurred: ' + error.message, 'error', settingsStatusDiv);
            console.error("Settings update failed:", error);
        }
    });

    // --- Handle Top-up Actions ---
    searchUserBtn.addEventListener('click', async () => {
        hideStatus(topupStatusDiv);
        userDetailsDisplay.innerHTML = ''; // Clear previous user details
        topupForm.style.display = 'none'; // Hide top-up form initially
        topupUserIdInput.value = ''; // Clear user ID

        const username = topupUsernameInput.value.trim();
        if (username.length < 3) { // Basic validation
            showStatus('Please enter at least 3 characters for username search.', 'info', topupStatusDiv);
            return;
        }

        showStatus('Searching...', 'info', topupStatusDiv);

        try {
            const response = await fetch(`admin_api.php?action=get_user_for_topup&username=${encodeURIComponent(username)}`);
             if (!response.ok) {
                 const errorDetail = await response.text();
                  if (response.status === 403) { throw new Error(`Access Denied: ${errorDetail}`); }
                 throw new Error(`Failed to search user. Server status ${response.status}. Detail: ${errorDetail}`);
             }
            const data = await response.json();

             if (data.error || !data.user) { // Check both error flag and if user exists
                 showStatus('Search Error: ' + (data.error || 'User not found.'), 'error', topupStatusDiv);
                 console.error("User search error:", data.error);
                 userDetailsDisplay.innerHTML = '<p>User not found or search failed.</p>';
                 topupForm.style.display = 'none'; // Hide form if user not found
             } else if (data.user) {
                 hideStatus(topupStatusDiv); // Hide "Searching..." message
                 userDetailsDisplay.innerHTML = `
                     <p><strong>User Found:</strong> ${data.user.username}</p>
                     <p><strong>Current Balance:</strong> ₱${parseFloat(data.user.coins).toFixed(2)}</p>
                     <p><strong>Last Played:</strong> ${data.user.last_played || 'Never'} (<a href="player_activity.php?user_id=${data.user.id}" target="_blank">View Activity</a>)</p>
                 `; // Added link to activity log
                 topupUserIdInput.value = data.user.id; // Store user ID for top-up
                 topupForm.style.display = 'flex'; // Show top-up form
             }

        } catch (error) {
            showStatus('A network error occurred during search: ' + error.message, 'error', topupStatusDiv);
            console.error("User search failed:", error);
            userDetailsDisplay.innerHTML = '<p>Search failed.</p>';
            topupForm.style.display = 'none'; // Hide form on error
        }
    });

    topupForm.addEventListener('submit', async (e) => {
        e.preventDefault(); // Prevent default form submission
        hideStatus(topupStatusDiv);

        const userId = topupUserIdInput.value;
        const amount = parseFloat(topupAmountInput.value);
        const paymentMethod = topupPaymentMethodSelect.value;
        const referenceNumber = topupReferenceNumberInput.value.trim();
        const description = topupDescriptionInput.value.trim();

        // --- Validation ---
        if (!userId) {
             showStatus('No user selected for top-up.', 'error', topupStatusDiv);
             return;
        }
        // Validate amount: must be positive and up to 2 decimal places
        // Using a small epsilon for floating point comparison
        const epsilon = 0.0001;
         if (isNaN(amount) || amount <= 0.00 || Math.abs(roundToTwoDecimals(amount) - amount) > epsilon) {
            showStatus('Please enter a valid amount (positive with up to 2 decimal places).', 'error', topupStatusDiv);
            return;
        }
        if (paymentMethod === '') {
             showStatus('Please select a payment method.', 'error', topupStatusDiv);
             return;
        }
        if (paymentMethod.toLowerCase() === 'gcash' && referenceNumber === '') { // Use toLowerCase for robustness
             showStatus('Reference number is required for GCash payments.', 'error', topupStatusDiv);
             return;
        }
         // --- End Validation ---

        // --- ADD THE CONFIRMATION POP-UP HERE ---
        const userName = userDetailsDisplay.querySelector('p strong').nextSibling.textContent.trim(); // Get username from displayed details
        const isConfirmed = confirm(`Are you sure you want to top-up ₱${amount.toFixed(2)} to user "${userName}"?`);

        // If the admin clicks 'Cancel', stop the process
        if (!isConfirmed) {
            console.log("Top-up cancelled by admin.");
            showStatus('Top-up cancelled.', 'info', topupStatusDiv); // Optional: show cancellation message
            processTopupBtn.disabled = false; // Ensure button is re-enabled if it was disabled by validation
            return; // Stop the function here
        }
        // --- END CONFIRMATION POP-UP ---


        processTopupBtn.disabled = true; // Disable button during processing
        showStatus('Processing top-up...', 'info', topupStatusDiv);

        const formData = new FormData();
        formData.append('action', 'process_topup');
        formData.append('user_id', userId);
        formData.append('amount', amount.toFixed(2)); // Send formatted amount
        formData.append('payment_method', paymentMethod);
        formData.append('reference_number', referenceNumber);
        formData.append('description', description);


        try {
            const response = await fetch('admin_api.php', { method: 'POST', body: formData });
             if (!response.ok) {
                 const errorDetail = await response.text();
                  if (response.status === 403) { throw new Error(`Access Denied: ${errorDetail}`); }
                 throw new Error(`Failed to process top-up. Server status ${response.status}. Detail: ${errorDetail}`);
             }
            const data = await response.json();

            if (data.success) {
                showStatus('Top-up successful! New balance: ₱' + parseFloat(data.new_balance).toFixed(2), 'success', topupStatusDiv);
                // Clear form and details
                topupUsernameInput.value = '';
                userDetailsDisplay.innerHTML = '';
                topupForm.style.display = 'none';
                topupUserIdInput.value = '';
                topupAmountInput.value = '';
                topupPaymentMethodSelect.value = ''; // Reset payment method
                topupReferenceNumberInput.value = ''; // Reset reference number
                topupDescriptionInput.value = '';
                 // Trigger change event to hide reference field if not GCash
                 topupPaymentMethodSelect.dispatchEvent(new Event('change'));


                 // Refresh players table to show updated balance
                 loadAllUsers();
                 // Refresh profit stats (Top-ups affect overall transactions)
                 // Use the current range to refresh
                 const currentRange = profitRangeSelect.value;
                 const startDate = profitStartDateInput.value;
                 const endDate = profitEndDateInput.value;
                 loadProfitStats(currentRange, currentRange === 'date_range' ? startDate : null, currentRange === 'date_range' ? endDate : null);

            } else {
                showStatus('Top-up failed: ' + (data.error || 'An unknown error occurred.'), 'error', topupStatusDiv);
                console.error("Top-up error:", data.error);
            }

        } catch (error) {
             showStatus('A network error occurred during top-up: ' + error.message, 'error', topupStatusDiv);
             console.error("Top-up failed:", error);
        } finally {
            processTopupBtn.disabled = false; // Re-enable button
        }
    });

     // Helper function to round to 2 decimal places for validation
     function roundToTwoDecimals(num) {
         return Math.round(num * 100) / 100;
     }

    // Conditional display for GCash reference number
    topupPaymentMethodSelect.addEventListener('change', (e) => {
         if (e.target.value.toLowerCase() === 'gcash') {
             gcashReferenceRow.style.display = 'flex'; // Use flex as parent form is flex
             topupReferenceNumberInput.setAttribute('required', 'required');
         } else {
             gcashReferenceRow.style.display = 'none';
             topupReferenceNumberInput.removeAttribute('required');
             topupReferenceNumberInput.value = ''; // Clear reference number if not GCash
         }
    });
     // Set initial state on load
    // topupPaymentMethodSelect.dispatchEvent(new Event('change')); // No need to trigger on load unless default is GCash


    // --- Handle Withdrawal Actions ---
     async function handleWithdrawalAction(event) {
         hideStatus(withdrawalStatusMessageDiv);

         const button = event.target;
         const requestId = button.dataset.id;
         const select = button.closest('td').querySelector('.status-select');
         // MODIFIED: Get the selected status value directly (which now matches DB enum)
         const newStatus = select ? select.value : null;

         if (!requestId || !newStatus) {
             showStatus('Invalid withdrawal request or status.', 'error', withdrawalStatusMessageDiv);
             return;
         }

         // Optional: Confirmation dialog
         if (!confirm(`Are you sure you want to change the status of request ${requestId} to "${newStatus.charAt(0).toUpperCase() + newStatus.slice(1)}"?`)) {
              // Revert the select dropdown visually if user cancels
              // This is a bit tricky without storing the original state.
              // Reloading the table is the most reliable way to reset visuals.
              loadWithdrawalRequests(); // Reload table to reset dropdowns on cancel
              return;
         }


         button.disabled = true; // Disable button during processing
         showStatus(`Updating request ${requestId} status to ${newStatus}...`, 'info', withdrawalStatusMessageDiv);

         const formData = new FormData();
         formData.append('action', 'update_withdrawal_status');
         formData.append('request_id', requestId);
         formData.append('status', newStatus); // Send the DB status value

         try {
              const response = await fetch('admin_api.php', { method: 'POST', body: formData });
              if (!response.ok) {
                  const errorDetail = await response.text();
                   if (response.status === 403) { throw new Error(`Access Denied: ${errorDetail}`); }
                   // Try to parse JSON error if available
                   try {
                        const errorJson = JSON.parse(errorDetail);
                        if (errorJson.error) {
                            throw new Error(errorJson.error); // Throw the specific API error message
                        } else {
                             throw new Error(`Server responded with status ${response.status}. Detail: ${errorDetail}`);
                        }
                   } catch (e) {
                       // If JSON parsing fails, throw the original error text
                       throw new Error(`Failed to update withdrawal status. Server status ${response.status}. Detail: ${errorDetail}`);
                   }
              }
              const data = await response.json();

              if (data.success) {
                  showStatus(`Request ${requestId} status updated to ${newStatus}!`, 'success', withdrawalStatusMessageDiv);
                  // Refresh the withdrawal table
                  loadWithdrawalRequests();
                  // Refresh players table and profit stats if balance changed (deducted or returned)
                   if (data.deduction_happened || data.return_happened) {
                       loadAllUsers(); // Refresh player balances
                       // Refresh profit stats (Withdrawals affect payouts)
                        const currentRange = profitRangeSelect.value;
                        const startDate = profitStartDateInput.value;
                        const endDate = profitEndDateInput.value;
                        loadProfitStats(currentRange, currentRange === 'date_range' ? startDate : null, currentRange === 'date_range' ? endDate : null);
                   }
              } else {
                  // MODIFIED: Error message already comes from the API in the catch block above if it's an API error
                   showStatus('Status update failed: ' + (data.error || 'An unknown error occurred.'), 'error', withdrawalStatusMessageDiv);
                  console.error("Withdrawal status update error:", data.error);
              }

         } catch (error) {
              // This catch block handles network errors and errors thrown from non-200 status codes above
              showStatus('A network error occurred during status update: ' + error.message, 'error', withdrawalStatusMessageDiv);
              console.error("Withdrawal status update failed:", error);
         } finally {
             button.disabled = false; // Re-enable button
             // MODIFIED: Always reload withdrawal requests table after an update attempt
             // This ensures the UI state syncs with the backend, especially after failures
             loadWithdrawalRequests();
         }
     }


    // --- Initial Data Load and Event Listeners ---
    function initialize() {
        // Add event listeners for settings form submit (already exists, keep)
        settingsForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            hideStatus(settingsStatusDiv);

            const formData = new FormData(settingsForm);
            formData.append('jackpot_mines_challenge_limit', jackpotLimitInput.value);
            formData.append('jackpot_mines_challenge_base_prize', jackpotBasePrizeInput.value);
            formData.append('action', 'update_game_settings');

            try {
                const response = await fetch('admin_api.php', { method: 'POST', body: formData });
                if (!response.ok) {
                    const errorDetail = await response.text();
                     if (response.status === 403) { throw new Error(`Access Denied: ${errorDetail}`); }
                    throw new Error(`Server responded with status ${response.status}: ${errorDetail}`);
                }
                const result = await response.json();

                if (result.success) {
                    showStatus('Settings updated successfully!', 'success', settingsStatusDiv);
                    // Re-load relevant data (like profit stats or anything dependent on settings)
                    loadInitialData(); // Refresh all data after settings change
                } else {
                     showStatus('Error updating settings: ' + (result.message || 'An unknown error occurred.'), 'error', settingsStatusDiv);
                    console.error("API Error Response:", result);
                }
            } catch (error) {
                showStatus('A network or server error occurred: ' + error.message, 'error', settingsStatusDiv);
                console.error("Settings update failed:", error);
            }
        });


        // Add event listeners for withdrawal actions (delegated)
         // Instead of adding listeners to each button (which are added dynamically),
         // add a listener to the table body and check the clicked element.
         withdrawalRequestsTableBody.addEventListener('click', async (event) => {
              if (event.target.classList.contains('process-btn')) {
                  handleWithdrawalAction(event);
              }
         });

         // MODIFIED: Add event listener for Player Activity Log buttons (delegated)
         playersTable.addEventListener('click', (event) => {
             if (event.target.classList.contains('activity-log-btn')) {
                 const userId = event.target.dataset.userId;
                 if (userId) {
                     // Redirect to the new player activity page
                     // NOTE: player_activity.php needs to be created separately
                     window.location.href = `player_activity.php?user_id=${userId}`;
                 } else {
                     console.error("Activity Log button clicked but no user ID found.");
                 }
             }
         });


         // --- Event listeners for Profit Tracking Range Selection ---
         profitRangeSelect.addEventListener('change', (e) => {
             const selectedRange = e.target.value;
             if (selectedRange === 'date_range') {
                 profitDateRangeInputs.style.display = 'flex'; // Show date inputs
                  stopProfitRefresh(); // Stop automatic refresh when using date range
             } else {
                 profitDateRangeInputs.style.display = 'none'; // Hide date inputs
                 // Immediately load stats for the selected predefined range
                 loadProfitStats(selectedRange);
                 // Start automatic refresh only for 'today'
                 if (selectedRange === 'today') {
                      startProfitRefresh();
                 } else {
                     stopProfitRefresh(); // Ensure stopped for 'yesterday' too
                 }
             }
         });

         // Event listener for the 'Apply Range' button
         profitApplyRangeBtn.addEventListener('click', () => {
             const startDate = profitStartDateInput.value;
             const endDate = profitEndDateInput.value;
             // Basic date format validation (MM/DD/YYYY)
             const dateRegex = /^(0[1-9]|1[0-2])\/(0[1-9]|[1-2]\d|3[0-1])\/\d{4}$/;
             if (!dateRegex.test(startDate) || !dateRegex.test(endDate)) {
                  showStatus('Please enter dates in MM/DD/YYYY format.', 'error', profitStatsStatusDiv);
                  return;
             }
              // Optional: Add logic to check if start date is before or equal to end date
             const start = new Date(startDate);
             const end = new Date(endDate);
             if (start > end) {
                  showStatus('Start date cannot be after end date.', 'error', profitStatsStatusDiv);
                  return;
             }


             stopProfitRefresh(); // Stop automatic refresh when using date range
             loadProfitStats('date_range', startDate, endDate);
         });


        // Initial data load
        loadInitialData();

         // Set interval to refresh withdrawal requests periodically (e.g., every 30 seconds)
         // Profit stats refresh for 'today' is handled by startProfitRefresh()
         setInterval(loadWithdrawalRequests, 30000); // e.g., every 30 seconds


         // Set initial state for date range inputs based on default select value ('today')
         // This will also trigger the initial loadProfitStats('today') and startProfitRefresh()
         profitRangeSelect.dispatchEvent(new Event('change'));

    }

    // Initialize the admin panel features
    initialize();
});