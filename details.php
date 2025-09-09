<?php
// details.php
// Page to display the full amortization schedule and details for a single loan.

date_default_timezone_set('Asia/Kolkata');
require_once 'db_connect.php';

// Get the recurring payment ID from the URL, ensure it's an integer.
$payment_id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);

// If the ID is missing or invalid, redirect to the dashboard.
if (!$payment_id) {
    header("Location: index.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Loan Details - Payments Dashboard</title>
    <!-- Tailwind CSS via CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        /* Reusing spinner style */
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
                <!-- The 'Add New Payment' button is not needed on the details page -->
            </div>
            <nav class="bg-white p-3 rounded-lg shadow-md">
                <ul class="flex items-center gap-x-6 text-sm sm:text-base">
                    <li><a href="index.php" class="text-gray-500 hover:text-indigo-600 font-semibold">Dashboard</a></li>
                    <li><a href="history.php" class="text-gray-500 hover:text-indigo-600 font-semibold">Payment History</a></li>
                    <li><a href="rules.php" class="text-gray-500 hover:text-indigo-600 font-semibold">Manage Rules</a></li>
                </ul>
            </nav>
        </header>

        <main>
            <div id="details-container">
                <div id="loader" class="loader"></div>
                <div id="error-message" class="hidden text-red-500 bg-red-100 p-4 rounded-lg"></div>

                <div id="loan-summary" class="mb-8"></div>

                <h3 class="text-xl font-bold text-gray-800 mb-4">Amortization Schedule</h3>
                <div id="amortization-schedule" class="bg-white rounded-lg shadow-md overflow-x-auto"></div>
            </div>
        </main>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const paymentId = <?= json_encode($payment_id) ?>;
            const loader = document.getElementById('loader');
            const errorContainer = document.getElementById('error-message');
            const summaryContainer = document.getElementById('loan-summary');
            const scheduleContainer = document.getElementById('amortization-schedule');

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

            // --- Render Functions ---
            const renderSummary = (data) => {
                const { loan_details, calculated_annual_rate_percent, tenure_months } = data;
                summaryContainer.innerHTML = `
                    <div class="bg-white p-6 rounded-lg shadow-md">
                        <h2 class="text-2xl font-bold text-gray-800 mb-4">${loan_details.payment_name}</h2>
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-center">
                            <div>
                                <p class="text-sm text-gray-500">Principal Amount</p>
                                <p class="text-xl font-semibold">${formatCurrency(loan_details.principal_amount)}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Monthly EMI</p>
                                <p class="text-xl font-semibold">${formatCurrency(loan_details.amount)}</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Tenure</p>
                                <p class="text-xl font-semibold">${tenure_months} Months</p>
                            </div>
                            <div>
                                <p class="text-sm text-gray-500">Calculated Annual Rate</p>
                                <p class="text-xl font-semibold">${calculated_annual_rate_percent.toFixed(2)}%</p>
                            </div>
                        </div>
                    </div>
                `;
            };

            const renderSchedule = (schedule) => {
                let tableHtml = `
                    <table class="min-w-full divide-y divide-gray-200">
                        <thead class="bg-gray-50">
                            <tr>
                                <th class="px-6 py-3 text-center text-xs font-medium text-gray-500 uppercase tracking-wider">Month</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">EMI</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Principal Paid</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Interest Paid</th>
                                <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase tracking-wider">Remaining Balance</th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-gray-200">
                `;
                schedule.forEach(row => {
                    tableHtml += `
                        <tr>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-center text-gray-500">${row.month}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${formatCurrency(row.emi)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-gray-500">${formatCurrency(row.principal_paid)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm text-red-500">${formatCurrency(row.interest_paid)}</td>
                            <td class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900">${formatCurrency(row.remaining_balance)}</td>
                        </tr>
                    `;
                });
                tableHtml += '</tbody></table>';
                scheduleContainer.innerHTML = tableHtml;
            };

            const fetchDetails = async () => {
                try {
                    const response = await fetch(`api.php?action=get_loan_details&id=${paymentId}`);
                    const result = await response.json();

                    if (result.status === 'success') {
                        renderSummary(result.data);
                        renderSchedule(result.data.amortization_schedule);
                    } else {
                        errorContainer.textContent = `Error: ${result.message}`;
                        errorContainer.classList.remove('hidden');
                    }
                } catch (error) {
                    errorContainer.textContent = `An unexpected error occurred. Please try again.`;
                    errorContainer.classList.remove('hidden');
                } finally {
                    loader.style.display = 'none';
                }
            };

            fetchDetails();
        });
    </script>
</body>
</html>
