<?php
// api.php
// This script acts as a single endpoint for all AJAX requests from the frontend.

// Set the proper header for JSON output
header('Content-Type: application/json');

// Set timezone
date_default_timezone_set('UTC');

// Include the database connection
require_once 'db_connect.php';

// --- Helper function for sending JSON responses ---
function json_response($status, $data = null, $message = null, $statusCode = 200) {
    http_response_code($statusCode);
    $response = ['status' => $status];
    if ($data !== null) {
        $response['data'] = $data;
    }
    if ($message !== null) {
        $response['message'] = $message;
    }
    echo json_encode($response);
    exit;
}

// Get the requested action from the query string
$action = $_GET['action'] ?? null;

if (!$action) {
    json_response('error', null, 'No action specified.', 400);
}

try {
    // --- Main switch to handle different actions ---
    switch ($action) {
        // --- ACTION: get_payments ---
        case 'get_payments':
            // Fetches all payments due within the next 45 days.
            // This includes both 'upcoming' and 'paid' so the UI can show their status.
            $query = "
                SELECT
                    pl.id as log_id,
                    pl.due_date,
                    pl.status,
                    rp.payment_name,
                    rp.payment_type,
                    rp.amount
                FROM
                    payment_log AS pl
                JOIN
                    recurring_payments AS rp ON pl.payment_id = rp.id
                WHERE
                    pl.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 45 DAY)
                ORDER BY
                    pl.due_date ASC, rp.payment_name ASC
            ";

            $stmt = $pdo->query($query);
            $payments = $stmt->fetchAll();

            json_response('success', $payments);
            break;

        // --- ACTION: mark_paid ---
        case 'mark_paid':
            // Get the log_id from the request
            $log_id = filter_input(INPUT_GET, 'log_id', FILTER_VALIDATE_INT);

            if (!$log_id) {
                json_response('error', null, 'Invalid or missing log_id.', 400);
            }

            // First, get the payment's due date to enforce the business rule
            $stmt = $pdo->prepare("SELECT due_date, status FROM payment_log WHERE id = ?");
            $stmt->execute([$log_id]);
            $payment = $stmt->fetch();

            if (!$payment) {
                json_response('error', null, 'Payment log not found.', 404);
            }

            if ($payment['status'] === 'paid') {
                json_response('success', null, 'Payment was already marked as paid.');
            }

            // Business Rule: Cannot mark as paid before the due date
            $today = new DateTime('today');
            $dueDate = new DateTime($payment['due_date']);

            if ($today < $dueDate) {
                json_response('error', null, 'Payment cannot be marked as paid before its due date.', 403); // 403 Forbidden
            }

            // If the rule passes, update the record
            $updateStmt = $pdo->prepare(
                "UPDATE payment_log SET status = 'paid', paid_on = NOW() WHERE id = ?"
            );
            $success = $updateStmt->execute([$log_id]);

            if ($success) {
                json_response('success', ['log_id' => $log_id], 'Payment successfully marked as paid.');
            } else {
                json_response('error', null, 'Failed to update payment status.', 500);
            }
            break;

        // --- ACTION: add_payment ---
        case 'add_payment':
            // This action uses POST method
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                json_response('error', null, 'This action requires a POST request.', 405);
            }

            // --- Validation ---
            $errors = [];
            $payment_name = trim($_POST['payment_name'] ?? '');
            $payment_type = trim($_POST['payment_type'] ?? '');
            $amount = filter_var($_POST['amount'] ?? 0, FILTER_VALIDATE_FLOAT);
            $due_day = filter_var($_POST['due_day'] ?? 0, FILTER_VALIDATE_INT);
            $start_date = trim($_POST['start_date'] ?? '');
            $end_date = trim($_POST['end_date'] ?? '');

            if (empty($payment_name)) $errors[] = "Payment name is required.";
            $valid_types = ['Loan', 'EMI', 'Rent', 'Recharge', 'Insurance', 'Other'];
            if (!in_array($payment_type, $valid_types)) $errors[] = "Invalid payment type.";
            if ($amount === false || $amount <= 0) $errors[] = "Amount must be a positive number.";
            if ($due_day === false || $due_day < 1 || $due_day > 31) $errors[] = "Due day must be between 1 and 31.";

            $start_date_obj = DateTime::createFromFormat('Y-m-d', $start_date);
            if ($start_date === '' || !$start_date_obj || $start_date_obj->format('Y-m-d') !== $start_date) {
                $errors[] = "Start date is required and must be a valid date.";
            }

            if (!empty($end_date)) {
                $end_date_obj = DateTime::createFromFormat('Y-m-d', $end_date);
                if (!$end_date_obj || $end_date_obj->format('Y-m-d') !== $end_date) {
                    $errors[] = "End date must be a valid date.";
                } elseif ($start_date_obj && $end_date_obj < $start_date_obj) {
                    $errors[] = "End date cannot be before the start date.";
                }
            } else {
                $end_date = null; // Ensure it's NULL if empty
            }

            if (!empty($errors)) {
                json_response('error', ['errors' => $errors], 'Validation failed.', 400);
            }

            // --- Insertion ---
            $sql = "INSERT INTO recurring_payments (payment_name, payment_type, amount, due_day, start_date, end_date) VALUES (?, ?, ?, ?, ?, ?)";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$payment_name, $payment_type, $amount, $due_day, $start_date, $end_date]);

            if ($stmt->rowCount() > 0) {
                // We can optionally run the cron job logic for this single new item
                // For simplicity here, we'll just confirm creation. The daily cron will pick it up.
                json_response('success', ['id' => $pdo->lastInsertId()], 'New recurring payment added successfully.');
            } else {
                json_response('error', null, 'Failed to add new payment.', 500);
            }
            break;

        // --- Default case for unknown actions ---
        default:
            json_response('error', null, 'Unknown action specified.', 400);
            break;
    }
} catch (PDOException $e) {
    // --- Global error handler for database issues ---
    // In a real app, you would log this error.
    error_log('API Error: ' . $e->getMessage());
    json_response('error', null, 'A database error occurred.', 500);
}
?>
