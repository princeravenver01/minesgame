document.addEventListener('DOMContentLoaded', () => {

    // --- DOM Element Selection (for the withdrawal page) ---
    const withdrawAmountInput = document.getElementById('withdraw-amount');
    const gcashNumberInput = document.getElementById('gcash-number');
    const gcashNameInput = document.getElementById('gcash-name');
    const submitWithdrawalBtn = document.getElementById('submit-withdrawal-btn');
    const playerWithdrawalHistoryTableBody = document.querySelector('#player-withdrawal-history-table tbody');
    const withdrawalStatusDiv = document.getElementById('withdrawal-status'); // Status message area on the page
    const userBalanceSpan = document.getElementById('user-balance'); // User balance in the header


    // --- Constants ---
    const MIN_WITHDRAWAL = 100; // Example minimum withdrawal amount (Match backend and HTML min attribute)
    const MAX_WITHDRAWAL = 100000; // Example maximum withdrawal amount (Match backend)
    // --- End Constants ---


    // Helper to display withdrawal status messages (calls inline function in withdrawal.php)
    // Using the inline function ensures status messages appear in the correct div on the page.
     function showWithdrawalStatus(message, type = 'info') {
         // Check if the inline function exists before calling it
         if (typeof window.showWithdrawalStatus === 'function') {
              window.showWithdrawalStatus(message, type);
         } else {
             console.error("showWithdrawalStatus function not found in withdrawal.php");
             // Fallback to console log if the inline function isn't available
             console.log(`Status (${type}): ${message}`);
         }
     }

     function hideWithdrawalStatus() {
          // Check if the inline function exists before calling it
          if (typeof window.hideWithdrawalStatus === 'function') {
               window.hideWithdrawalStatus();
          } else {
              console.error("hideWithdrawalStatus function not found in withdrawal.php");
          }
     }


    // --- Withdrawal Functions ---

    function validateWithdrawal(amount, number, name) {
         // Get user balance from the header span, remove currency symbol and comma
         const userBalanceText = userBalanceSpan.textContent.replace(/[₱,]/g, '');
         const userBalance = parseFloat(userBalanceText);
         if (isNaN(userBalance)) {
             console.error("Could not parse user balance for withdrawal validation:", userBalanceSpan.textContent);
             showWithdrawalStatus('Error reading balance. Please refresh.', 'error');
             return false;
         }

         if (isNaN(amount) || amount <= 0) {
             showWithdrawalStatus(`Please enter a valid withdrawal amount.`, 'error');
             return false;
         }
         // Check against constants
         if (amount < MIN_WITHDRAWAL) {
             showWithdrawalStatus(`Minimum withdrawal is ₱${MIN_WITHDRAWAL.toFixed(2)}.`, 'error');
             return false;
         }
          if (amount > MAX_WITHDRAWAL) {
              showWithdrawalStatus(`Maximum withdrawal is ₱${MAX_WITHDRAWAL.toFixed(2)}.`, 'error');
              return false;
          }
         if (amount > userBalance) {
             showWithdrawalStatus('Insufficient balance.', 'error');
             return false;
         }
         if (!number || number.trim() === '') {
             showWithdrawalStatus('Please enter your GCash number.', 'error');
             return false;
         }
         // Basic number format check (optional but good)
         // Allows optional +63 prefix or starts directly with 09, followed by exactly 9 digits
         if (!/^(09|\+639)\d{9}$/.test(number.trim())) {
             showWithdrawalStatus('Please enter a valid 11-digit GCash number (e.g., 09171234567 or +639171234567).', 'error');
             return false;
         }
          if (!name || name.trim() === '') {
             showWithdrawalStatus('Please enter your GCash full name.', 'error');
             return false;
         }
         // Basic name check (optional) - require at least two parts? Or just length?
         if (name.trim().length < 3) { // Simple length check
             showWithdrawalStatus('Please enter your full name.', 'error');
             return false;
         }

         return true;
    }

    async function submitWithdrawalRequest() {
         hideWithdrawalStatus(); // Hide previous status

         const amount = parseFloat(withdrawAmountInput.value);
         const gcashNumber = gcashNumberInput.value.trim();
         const gcashName = gcashNameInput.value.trim();

         // --- Perform validation first ---
         if (!validateWithdrawal(amount, gcashNumber, gcashName)) {
             return; // Stop if validation fails
         }
         // --- End Validation ---

         // --- ADD THE CONFIRMATION POP-UP HERE ---
         // Use the browser's built-in confirmation box
         const isConfirmed = confirm("Are you sure you want to proceed with the withdrawal?");

         // If the user clicks 'Cancel', stop the process
         if (!isConfirmed) {
             console.log("Withdrawal cancelled by user.");
             showWithdrawalStatus('Withdrawal cancelled.', 'info'); // Optional: show cancellation message
             return; // Stop the function here
         }
         // --- END CONFIRMATION POP-UP ---


         // --- Rest of your existing withdrawal submission logic goes here ---
         // This part only runs if the user clicked 'OK' on the confirmation box

         submitWithdrawalBtn.disabled = true; // Disable button during submission
         showWithdrawalStatus('Submitting withdrawal request...', 'info');

         const formData = new FormData();
         formData.append('action', 'submit_withdrawal');
         formData.append('amount', amount.toFixed(2)); // Ensure amount is sent with 2 decimal places
         formData.append('gcash_number', gcashNumber);
         formData.append('gcash_name', gcashName);

         try {
             // Use the new withdrawal API file
             const response = await fetch('api/withdrawal.php', { method: 'POST', body: formData });
              if (!response.ok) {
                  const errorText = await response.text();
                  throw new Error(`Server responded with status ${response.status}. Detail: ${errorText}`);
              }
             const data = await response.json();

             if (data.success) {
                 showWithdrawalStatus('Withdrawal request submitted! It is now pending admin approval.', 'success');
                 // Clear form fields
                 withdrawAmountInput.value = '';
                 gcashNumberInput.value = '';
                 gcashNameInput.value = '';
                 // Update balance display (it is *not* deducted by the API anymore, but update with value from API response)
                  if (data.new_balance !== undefined) {
                       // Format the balance before updating the span
                       const formattedBalance = parseFloat(data.new_balance).toFixed(2);
                      userBalanceSpan.textContent = formattedBalance;
                  }
                 // Refresh the player's withdrawal history table
                 fetchPlayerWithdrawalHistory();

             } else {
                 showWithdrawalStatus('Error submitting withdrawal: ' + (data.error || 'An unknown error occurred.'), 'error');
                 console.error("Withdrawal submission error:", data.error);
             }

         } catch (error) {
             console.error("Withdrawal submission failed:", error);
             showWithdrawalStatus('A network error occurred during withdrawal submission. Details: ' + error.message, 'error');
         } finally {
             submitWithdrawalBtn.disabled = false; // Re-enable button
         }
    }

    async function fetchPlayerWithdrawalHistory() {
         playerWithdrawalHistoryTableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">Loading history...</td></tr>';
         try {
              // Use the new withdrawal API file
              const response = await fetch('api/withdrawal.php?action=get_player_history');
              if (!response.ok) {
                  const errorDetail = await response.text();
                  throw new Error(`Server responded with status ${response.status}. Detail: ${errorDetail}`);
              }
             const data = await response.json();

              if (data.error) {
                  console.error("API error fetching player withdrawal history:", data.error);
                  playerWithdrawalHistoryTableBody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:red;">Error loading history.</td></tr>';
                  return;
              }

             playerWithdrawalHistoryTableBody.innerHTML = ''; // Clear current list
             if (Array.isArray(data.history) && data.history.length > 0) {
                 // Sort history by requested_at descending (most recent first)
                 data.history.sort((a, b) => new Date(b.requested_at) - new Date(a.requested_at));

                 data.history.forEach(request => {
                     const row = document.createElement('tr');
                      const amount = parseFloat(request.amount).toFixed(2);
                      // Check if requested_at is a valid date string before formatting
                      const requestedAt = request.requested_at ? new Date(request.requested_at).toLocaleString() : 'N/A'; // Format date/time
                      // Processed At is optional
                      const processedAt = request.processed_at ? new Date(request.processed_at).toLocaleString() : '--';

                      // Determine status classes
                      let statusClass = '';
                      // Use lowercase status from DB for class name consistency
                      if (request.status) {
                           statusClass = `status-${request.status.toLowerCase()}`;
                      } else {
                          statusClass = 'status-unknown'; // Fallback
                      }
                      const statusDisplay = request.status ? (request.status.charAt(0).toUpperCase() + request.status.slice(1)) : 'Unknown'; // Capitalize status

                     row.innerHTML = `
                         <td>₱${amount}</td>
                         <td>${request.gcash_number || 'N/A'}</td>
                         <td>${request.gcash_name || 'N/A'}</td>
                         <td>${requestedAt}</td>
                         <td class="${statusClass}">${statusDisplay}</td>
                         <!-- Optional: Add processed_at and notes if available/desired in player view -->
                         <!--
                         <td>${processedAt}</td>
                         <td>${request.admin_notes || '--'}</td>
                         -->
                     `;
                     playerWithdrawalHistoryTableBody.appendChild(row);
                 });
             } else {
                 playerWithdrawalHistoryTableBody.innerHTML = '<tr><td colspan="5" style="text-align:center;">No withdrawal history found.</td></tr>';
             }

         } catch (error) {
             console.error('Failed to fetch player withdrawal history:', error);
             playerWithdrawalHistoryTableBody.innerHTML = '<tr><td colspan="5" style="text-align:center; color:red;">Network error loading history.</td></tr>';
         }
    }


    // --- Initial Setup and Event Listeners ---
    function initialize() {
        // Add withdrawal event listener
        // The submitWithdrawalRequest function now includes the confirm() check
        submitWithdrawalBtn.addEventListener('click', submitWithdrawalRequest);

        // Fetch and update player's own withdrawal history on page load
        fetchPlayerWithdrawalHistory();

        // Optional: Add a basic keypress listener to submit form on Enter key
        // Check if the element exists before adding listener
         if (withdrawAmountInput) withdrawAmountInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') { e.preventDefault(); submitWithdrawalRequest(); } });
         if (gcashNumberInput) gcashNumberInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') { e.preventDefault(); submitWithdrawalRequest(); } });
         if (gcashNameInput) gcashNameInput.addEventListener('keypress', (e) => { if (e.key === 'Enter') { e.preventDefault(); submitWithdrawalRequest(); } });

         // Set interval to refresh player withdrawal history periodically (e.g., every 30 seconds)
         setInterval(fetchPlayerWithdrawalHistory, 30000);
    }

    // Initialize the page when the DOM is fully loaded
    initialize();
});