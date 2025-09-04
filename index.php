<?php
// index.php (PHP Part)
// This top block handles server-side rendering for critical information.

// Set timezone to India Standard Time
date_default_timezone_set('Asia/Kolkata');

// Include the database connection script
require_once 'db_connect.php';
// No server-side fetching needed anymore, the frontend handles it all.
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Payments Dashboard</title>
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Custom style for the loading spinner */
        .loader {
            border: 5px solid #f3f3f3; /* Light grey */
            border-top: 5px solid #3b82f6; /* Blue-500 */
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 40px auto;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body class="bg-gray-50 text-gray-800 font-sans">

    <div class="container mx-auto p-4 sm:p-6 lg:p-8">

        <header class="mb-8 flex flex-wrap justify-between items-center gap-4">
            <h1 class="text-3xl md:text-4xl font-bold text-gray-800">My Payments Dashboard</h1>
            <button id="add-payment-btn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition-colors">
                + Add New Payment
            </button>
        </header>

        <main>
            <!-- Main interactive payment list -->
            <section id="all-payments">
                <h2 class="text-2xl font-bold text-gray-800 mb-6">Upcoming Payments (Next 10 Days)</h2>

                <!-- Loading Spinner - shown while AJAX call is in progress -->
                <div id="loading-spinner" class="text-center py-8">
                    <div class="loader"></div>
                    <p class="text-gray-500">Fetching payments...</p>
                </div>

                <!-- Container where payment cards will be injected by JavaScript -->
                <div id="payment-list-container" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                    <!-- JS will populate this area -->
                </div>

                <!-- Message to show if no payments are found by the AJAX call -->
                <p id="no-payments-message" class="hidden text-center text-gray-500 py-8">No payments found in the next 45 days.</p>
            </section>
        </main>

    </div>

    <!-- JavaScript for AJAX calls and interactivity -->
    <script>
    document.addEventListener('DOMContentLoaded', () => {

        const spinner = document.getElementById('loading-spinner');
        const container = document.getElementById('payment-list-container');
        const noPaymentsMsg = document.getElementById('no-payments-message');

        // --- Helper Functions ---
        const formatCurrency = (amount) => {
            return new Intl.NumberFormat('en-US', { style: 'currency', currency: 'USD' }).format(amount);
        };

        const formatDate = (dateString) => {
            const date = new Date(dateString + 'T00:00:00'); // Treat date as local
            return date.toLocaleDateString('en-US', { year: 'numeric', month: 'short', day: 'numeric', weekday: 'short' });
        };

        // --- Main Function to Fetch and Render Payments ---
        const fetchAndRenderPayments = async () => {
            spinner.style.display = 'block';
            container.innerHTML = '';
            noPaymentsMsg.classList.add('hidden');

            try {
                const response = await fetch('api.php?action=get_payments');
                if (!response.ok) {
                    throw new Error(`HTTP error! status: ${response.status}`);
                }
                const result = await response.json();

                spinner.style.display = 'none';

                if (result.status === 'success' && result.data.length > 0) {
                    renderPayments(result.data);
                } else if (result.status === 'success') {
                    noPaymentsMsg.classList.remove('hidden');
                } else {
                    throw new Error(result.message || 'Failed to fetch payments.');
                }
            } catch (error) {
                spinner.style.display = 'none';
                container.innerHTML = `<p class="text-red-500 text-center col-span-full">${error.message}</p>`;
            }
        };

        // --- Function to Render Payment Cards ---
        const renderPayments = (payments) => {
            const today = new Date();
            today.setHours(0, 0, 0, 0); // Normalize to the beginning of the day for accurate comparison

            let content = '';
            payments.forEach(p => {
                const dueDate = new Date(p.due_date + 'T00:00:00');
                const isPaid = p.status === 'paid';
                const isPayable = today >= dueDate;

                // Card background and border styles based on status
                const cardClasses = isPaid
                    ? 'bg-green-50 border-green-200'
                    : 'bg-white';

                // Button or Paid status indicator
                let buttonHtml = '';
                if (isPaid) {
                    buttonHtml = `<div class="text-center font-semibold text-green-600 flex items-center justify-center gap-2">
                                    <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                    Paid
                                  </div>`;
                } else {
                    const disabledAttr = isPayable ? '' : 'disabled';
                    const buttonClasses = isPayable
                        ? 'bg-blue-500 hover:bg-blue-600 text-white'
                        : 'bg-gray-300 cursor-not-allowed text-gray-500';
                    const buttonTitle = isPayable ? 'Mark this payment as paid' : 'You can mark this as paid on or after the due date.';

                    buttonHtml = `<button class="w-full py-2 px-4 rounded font-semibold transition-colors ${buttonClasses} mark-paid-btn"
                                          data-log-id="${p.log_id}" ${disabledAttr} title="${buttonTitle}">
                                    Mark as Paid
                                  </button>`;
                }

                content += `
                    <div id="payment-card-${p.log_id}" class="p-5 rounded-lg shadow-md border ${cardClasses} flex flex-col justify-between gap-4">
                        <div>
                            <div class="flex justify-between items-start">
                                <h3 class="text-lg font-bold text-gray-800">${p.payment_name}</h3>
                                <span class="bg-gray-200 text-gray-600 text-xs font-semibold px-2 py-1 rounded-full">${p.payment_type}</span>
                            </div>
                            <p class="text-2xl font-light text-gray-900 mt-2">${formatCurrency(p.amount)}</p>
                        </div>
                        <div>
                            <p class="text-sm text-gray-500 mb-4 text-center">Due: ${formatDate(p.due_date)}</p>
                            ${buttonHtml}
                        </div>
                    </div>
                `;
            });
            container.innerHTML = content;
        };

        // --- Event Listener for "Mark as Paid" button clicks (using event delegation) ---
        container.addEventListener('click', async (event) => {
            if (event.target.classList.contains('mark-paid-btn')) {
                const button = event.target;
                const logId = button.dataset.logId;

                button.disabled = true;
                button.textContent = 'Processing...';

                try {
                    const response = await fetch(`api.php?action=mark_paid&log_id=${logId}`);
                    const result = await response.json();

                    if (result.status === 'success') {
                        // Update UI instantly without a full refresh
                        const card = document.getElementById(`payment-card-${logId}`);
                        card.classList.remove('bg-white');
                        card.classList.add('bg-green-50', 'border-green-200');
                        const buttonContainer = button.parentElement;
                        buttonContainer.innerHTML = `<div class="text-center font-semibold text-green-600 flex items-center justify-center gap-2">
                                                      <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd" /></svg>
                                                      Paid
                                                    </div>`;
                    } else {
                        // On failure (e.g., trying to pay too early), show an alert and revert button
                        alert(`Error: ${result.message}`);
                        button.disabled = false;
                        button.textContent = 'Mark as Paid';
                    }
                } catch (error) {
                    alert('An unexpected error occurred. Please try again.');
                    button.disabled = false;
                    button.textContent = 'Mark as Paid';
                }
            }
        });

        // --- Initial Load ---
        fetchAndRenderPayments();

        // --- Modal Handling ---
        const modal = document.getElementById('add-payment-modal');
        const addPaymentBtn = document.getElementById('add-payment-btn');
        const closeModalBtn = document.getElementById('close-modal-btn');
        const addPaymentForm = document.getElementById('add-payment-form');
        const formErrorMessages = document.getElementById('form-error-messages');
        const submitBtn = document.getElementById('submit-payment-btn');

        const openModal = () => modal.classList.remove('hidden');
        const closeModal = () => {
            modal.classList.add('hidden');
            addPaymentForm.reset();
            formErrorMessages.classList.add('hidden');
            formErrorMessages.innerHTML = '';
        };

        addPaymentBtn.addEventListener('click', openModal);
        closeModalBtn.addEventListener('click', closeModal);
        // Also close modal if user clicks outside the form
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });

        // --- Form Submission ---
        addPaymentForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            submitBtn.disabled = true;
            submitBtn.innerHTML = 'Saving...';
            formErrorMessages.classList.add('hidden');

            const formData = new FormData(addPaymentForm);

            try {
                const response = await fetch('api.php?action=add_payment', {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (response.ok && result.status === 'success') {
                    closeModal();
                    // Refresh the list to show the new payment. The cron job will handle future logs.
                    fetchAndRenderPayments();
                } else {
                    // Display validation errors from the API
                    if (result.data && result.data.errors) {
                        formErrorMessages.innerHTML = result.data.errors.join('<br>');
                        formErrorMessages.classList.remove('hidden');
                    } else {
                        alert(result.message || 'An unknown error occurred.');
                    }
                }
            } catch (error) {
                alert('A network error occurred. Please check your connection and try again.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Save Payment';
            }
        });
    });
    </script>

    <!-- Add New Payment Modal -->
    <div id="add-payment-modal" class="hidden fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full z-50">
        <div class="relative top-10 md:top-20 mx-auto p-5 border w-full max-w-lg shadow-lg rounded-md bg-white">
            <div class="flex justify-between items-center pb-3 border-b">
                <h3 class="text-2xl font-bold text-gray-800">Add New Recurring Payment</h3>
                <button id="close-modal-btn" class="text-gray-400 bg-transparent hover:bg-gray-200 hover:text-gray-900 rounded-lg text-sm p-1.5 ml-auto inline-flex items-center">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>
                </button>
            </div>
            <div class="mt-4">
                <form id="add-payment-form">
                    <div id="form-error-messages" class="hidden mb-4 p-3 bg-red-100 text-red-800 border border-red-300 rounded-lg text-sm"></div>
                    <div class="space-y-4">
                        <div>
                            <label for="payment_name" class="block text-sm font-medium text-gray-700">Payment Name</label>
                            <input type="text" name="payment_name" id="payment_name" placeholder="e.g., Netflix Subscription" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="payment_type" class="block text-sm font-medium text-gray-700">Payment Type</label>
                                <select name="payment_type" id="payment_type" class="mt-1 block w-full px-3 py-2 border border-gray-300 bg-white rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                                    <option>Loan</option><option>EMI</option><option>Rent</option><option>Recharge</option><option>Insurance</option><option>Other</option>
                                </select>
                            </div>
                            <div>
                                <label for="amount" class="block text-sm font-medium text-gray-700">Amount ($)</label>
                                <input type="number" name="amount" id="amount" step="0.01" min="0.01" placeholder="e.g., 15.99" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                            </div>
                        </div>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div>
                                <label for="due_day" class="block text-sm font-medium text-gray-700">Due Day (1-31)</label>
                                <input type="number" name="due_day" id="due_day" min="1" max="31" placeholder="e.g., 15" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                            </div>
                             <div>
                                <label for="start_date" class="block text-sm font-medium text-gray-700">Start Date</label>
                                <input type="date" name="start_date" id="start_date" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500" required>
                            </div>
                        </div>
                        <div>
                            <label for="end_date" class="block text-sm font-medium text-gray-700">End Date <span class="text-gray-500">(Optional)</span></label>
                            <input type="date" name="end_date" id="end_date" class="mt-1 block w-full px-3 py-2 border border-gray-300 rounded-md shadow-sm focus:outline-none focus:ring-indigo-500 focus:border-indigo-500">
                        </div>
                    </div>
                    <div class="mt-6 pt-4 border-t">
                        <button type="submit" id="submit-payment-btn" class="w-full flex justify-center py-2.5 px-4 border border-transparent rounded-md shadow-sm text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500">
                            Save Payment
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

</body>
</html>
