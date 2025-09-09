<?php
// rules.php
// Page to display and manage all recurring payment rules.

// Set timezone
date_default_timezone_set('Asia/Kolkata');

// db_connect.php is not strictly needed for the shell, but will be for future features on this page.
require_once 'db_connect.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Payment Rules - Payments Dashboard</title>
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Reusing spinner style from index.php */
        .loader {
            border: 5px solid #f3f3f3;
            border-top: 5px solid #3b82f6;
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

        <header class="mb-8">
            <div class="flex flex-wrap justify-between items-center gap-4 mb-4">
                <h1 class="text-3xl md:text-4xl font-bold text-gray-800">My Payments Dashboard</h1>
                <button id="add-payment-btn" class="bg-indigo-600 hover:bg-indigo-700 text-white font-bold py-2 px-4 rounded-lg shadow-md transition-colors">
                    + Add New Payment
                </button>
            </div>
            <nav class="bg-white p-3 rounded-lg shadow-md">
                <ul class="flex items-center gap-x-6 text-sm sm:text-base">
                    <li><a href="index.php" class="text-gray-500 hover:text-indigo-600 font-semibold">Dashboard</a></li>
                    <li><a href="history.php" class="text-gray-500 hover:text-indigo-600 font-semibold">Payment History</a></li>
                    <li><a href="rules.php" class="text-indigo-600 font-bold border-b-2 border-indigo-600 pb-2">Manage Rules</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <h2 class="text-2xl font-bold text-gray-800 mb-6">Manage Recurring Payment Rules</h2>

            <!-- Filter Controls -->
            <div class="mb-6">
                <form id="filter-form" class="flex flex-wrap items-end gap-4 p-4 bg-gray-100 rounded-lg">
                    <div>
                        <label for="filter_type" class="block text-sm font-medium text-gray-700">Payment Type</label>
                        <select name="filter_type" id="filter_type" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                            <option value="all">All Types</option>
                            <option>Loan</option><option>EMI</option><option>Rent</option><option>Recharge</option><option>Insurance</option><option>Other</option>
                        </select>
                    </div>
                    <div>
                        <label for="filter_status" class="block text-sm font-medium text-gray-700">Status</label>
                        <select name="filter_status" id="filter_status" class="mt-1 block w-full pl-3 pr-10 py-2 text-base border-gray-300 focus:outline-none focus:ring-indigo-500 focus:border-indigo-500 sm:text-sm rounded-md">
                            <option value="all">All Statuses</option>
                            <option value="1">Active</option>
                            <option value="0">Inactive</option>
                        </select>
                    </div>
                    <button type="submit" class="bg-indigo-600 hover:bg-indigo-700 text-white font-semibold py-2 px-4 rounded-md shadow-sm">Apply Filters</button>
                </form>
            </div>

            <!-- Rules Table -->
            <div id="rules-container" class="bg-white rounded-lg shadow-md">
                <div id="loader" class="loader" style="display: none;"></div>
                <div class="overflow-x-auto">
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Name</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Type</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Amount</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Due Day</th>
                                <th scope="col" class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Date Range</th>
                            </tr>
                        </thead>
                        <tbody id="rules-table-body" class="bg-white divide-y divide-gray-200">
                            <!-- JS will populate this -->
                        </tbody>
                    </table>
                </div>
                <p id="no-rules-message" class="hidden text-center text-gray-500 py-8">No rules found matching your criteria.</p>
                <!-- Pagination -->
                <div id="pagination-container" class="px-6 py-4 border-t flex justify-between items-center">
                    <!-- JS will populate this -->
                </div>
            </div>
        </main>

    </div>

    <!-- Modal from index.php is needed here as well for the 'Add New Payment' button -->
    <!-- Add New Payment Modal (copied from index.php) -->
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

    <script>
    document.addEventListener('DOMContentLoaded', () => {
        const loader = document.getElementById('loader');
        const tableBody = document.getElementById('rules-table-body');
        const noRulesMsg = document.getElementById('no-rules-message');
        const paginationContainer = document.getElementById('pagination-container');
        const filterForm = document.getElementById('filter-form');

        let currentPage = 1;

        // --- Helper Functions ---
        const formatCurrency = (amount) => new Intl.NumberFormat('en-IN', { style: 'currency', currency: 'INR' }).format(amount);
        const formatDate = (dateString) => {
            if (!dateString) return 'N/A';
            const date = new Date(dateString + 'T00:00:00');
            const day = String(date.getDate()).padStart(2, '0');
            const month = String(date.getMonth() + 1).padStart(2, '0');
            const year = date.getFullYear();
            return `${day}/${month}/${year}`;
        };

        // --- Main Fetch Function ---
        const fetchRules = async () => {
            loader.style.display = 'block';
            tableBody.innerHTML = '';
            noRulesMsg.classList.add('hidden');
            paginationContainer.innerHTML = '';

            const filterType = document.getElementById('filter_type').value;
            const filterStatus = document.getElementById('filter_status').value;

            const url = `api.php?action=get_rules&page=${currentPage}&filter_type=${filterType}&filter_status=${filterStatus}`;

            try {
                const response = await fetch(url);
                const result = await response.json();

                if (result.status === 'success') {
                    renderTable(result.data.data);
                    renderPagination(result.data.pagination);
                } else {
                    tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-red-500 py-4">${result.message}</td></tr>`;
                }
            } catch (error) {
                tableBody.innerHTML = `<tr><td colspan="6" class="text-center text-red-500 py-4">An error occurred: ${error.message}</td></tr>`;
            } finally {
                loader.style.display = 'none';
            }
        };

        // --- Render Functions ---
        const renderTable = (rules) => {
            if (rules.length === 0) {
                noRulesMsg.classList.remove('hidden');
                return;
            }
            let content = '';
            rules.forEach(rule => {
                const statusClass = rule.is_active ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800';
                content += `
                    <tr>
                        <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${rule.payment_name}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${rule.payment_type}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${formatCurrency(rule.amount)}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 text-center">${rule.due_day}</td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-center">
                            <span class="px-2 inline-flex text-xs leading-5 font-semibold rounded-full ${statusClass}">
                                ${rule.is_active ? 'Active' : 'Inactive'}
                            </span>
                        </td>
                        <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${formatDate(rule.start_date)} - ${formatDate(rule.end_date)}</td>
                    </tr>
                `;
            });
            tableBody.innerHTML = content;
        };

        const renderPagination = (pagination) => {
            const { total_items, total_pages, current_page } = pagination;
            if (total_pages <= 1) return;

            let content = `<div class="flex-1 text-sm text-gray-700">Showing <span class="font-medium">${((current_page - 1) * 10) + 1}</span> to <span class="font-medium">${Math.min(current_page * 10, total_items)}</span> of <span class="font-medium">${total_items}</span> results</div>`;
            content += '<div><nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px">';

            // Previous button
            content += `<button data-page="${current_page - 1}" class="relative inline-flex items-center px-2 py-2 rounded-l-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50" ${current_page === 1 ? 'disabled' : ''}>Prev</button>`;

            // Page numbers (simplified)
            for (let i = 1; i <= total_pages; i++) {
                const activeClass = i === current_page ? 'z-10 bg-indigo-50 border-indigo-500 text-indigo-600' : 'bg-white border-gray-300 text-gray-500 hover:bg-gray-50';
                content += `<button data-page="${i}" class="relative inline-flex items-center px-4 py-2 border text-sm font-medium ${activeClass}">${i}</button>`;
            }

            // Next button
            content += `<button data-page="${current_page + 1}" class="relative inline-flex items-center px-2 py-2 rounded-r-md border border-gray-300 bg-white text-sm font-medium text-gray-500 hover:bg-gray-50" ${current_page === total_pages ? 'disabled' : ''}>Next</button>`;

            content += '</nav></div>';
            paginationContainer.innerHTML = content;
        };

        // --- Event Listeners ---
        filterForm.addEventListener('submit', (e) => {
            e.preventDefault();
            currentPage = 1;
            fetchRules();
        });

        paginationContainer.addEventListener('click', (e) => {
            if (e.target.tagName === 'BUTTON' && e.target.dataset.page) {
                const page = parseInt(e.target.dataset.page, 10);
                if (page !== currentPage) {
                    currentPage = page;
                    fetchRules();
                }
            }
        });

        // --- Initial Load ---
        fetchRules();

        // --- Modal Handling (copied and adapted from index.php) ---
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
        modal.addEventListener('click', (event) => {
            if (event.target === modal) closeModal();
        });

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
                    // Refresh the rules list on this page
                    fetchRules();
                } else {
                    if (result.data && result.data.errors) {
                        formErrorMessages.innerHTML = result.data.errors.join('<br>');
                        formErrorMessages.classList.remove('hidden');
                    } else {
                        alert(result.message || 'An unknown error occurred.');
                    }
                }
            } catch (error) {
                alert('A network error occurred. Please try again.');
            } finally {
                submitBtn.disabled = false;
                submitBtn.innerHTML = 'Save Payment';
            }
        });
    });
    </script>
</body>
</html>
