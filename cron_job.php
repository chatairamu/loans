<?php
// cron_job.php
// This script should be run daily via a cron job (e.g., once after midnight).
// It populates the payment_log table with upcoming payments for the current month.

// Set timezone to India Standard Time to align with user's locale
date_default_timezone_set('Asia/Kolkata');

// Include the database connection
require_once 'db_connect.php';

echo "Cron Job Started: " . date('Y-m-d H:i:s') . "\n";

try {
    // 1. Get all active recurring payments
    $stmt = $pdo->query("SELECT * FROM recurring_payments WHERE is_active = 1");
    $activePayments = $stmt->fetchAll();

    if (empty($activePayments)) {
        echo "No active payments found. Exiting.\n";
        exit;
    }

    $currentDate = new DateTime('now');
    $currentYear = $currentDate->format('Y');
    $currentMonth = $currentDate->format('m');
    echo "Processing for Year: $currentYear, Month: $currentMonth\n";
    echo "--------------------------------------------------\n";

    $logCreatedCount = 0;

    // 2. Loop through each active payment
    foreach ($activePayments as $payment) {
        echo "Processing '{$payment['payment_name']}' (ID: {$payment['id']})... ";

        // 3. Check if the payment is within its active date range.
        // A log should be created if the payment's active period overlaps with the current month.
        $firstDayOfCurrentMonth = new DateTime('first day of this month 00:00:00');
        $lastDayOfCurrentMonth = new DateTime('last day of this month 23:59:59');
        $startDate = new DateTime($payment['start_date']);
        $endDate = $payment['end_date'] ? new DateTime($payment['end_date']) : null;

        // Skip if the payment's start date is after the current month ends.
        if ($startDate > $lastDayOfCurrentMonth) {
            echo "Skipped (Start date is in the future).\n";
            continue;
        }

        // Skip if the payment's end date was before the current month began.
        if ($endDate && $endDate < $firstDayOfCurrentMonth) {
            echo "Skipped (Payment has ended).\n";
            continue;
        }

        // 4. Validate the due day for the current month (e.g., skip day 31 in February)
        $dueDay = $payment['due_day'];
        if (!checkdate($currentMonth, $dueDay, $currentYear)) {
            echo "Skipped (Invalid due day '$dueDay' for $currentYear-$currentMonth).\n";
            continue;
        }

        // 5. Construct the due date for the current month
        $dueDate = new DateTime("$currentYear-$currentMonth-$dueDay");
        $dueDateForDb = $dueDate->format('Y-m-d');

        // 6. Check if a log entry already exists for this payment and this specific due date
        $logStmt = $pdo->prepare("SELECT id FROM payment_log WHERE payment_id = ? AND due_date = ?");
        $logStmt->execute([$payment['id'], $dueDateForDb]);

        if ($logStmt->fetch()) {
            echo "Skipped (Log entry already exists for $dueDateForDb).\n";
            continue;
        }

        // 7. If no log exists, create one
        $insertStmt = $pdo->prepare(
            "INSERT INTO payment_log (payment_id, due_date, status) VALUES (?, ?, 'upcoming')"
        );
        $insertStmt->execute([$payment['id'], $dueDateForDb]);
        $logCreatedCount++;
        echo "OK (Created new log entry for $dueDateForDb).\n";
    }

    echo "--------------------------------------------------\n";
    echo "Cron Job Finished. Total new log entries created: $logCreatedCount\n";

} catch (PDOException $e) {
    // Log errors to a file or stderr for cron job debugging
    error_log("Cron Job Failed: " . $e->getMessage());
    echo "Error: Cron job failed. Check error logs.\n";
    exit(1); // Exit with a non-zero status code to indicate failure
}

?>
