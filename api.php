<?php
// api.php
// This script acts as a single endpoint for all AJAX requests from the frontend.

// Set the proper header for JSON output
header('Content-Type: application/json');

// Set timezone to India Standard Time
date_default_timezone_set('Asia/Kolkata');

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
                    pl.due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 10 DAY)
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

        // --- ACTION: get_rules ---
        case 'get_rules':
            $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
            $items_per_page = 10;
            $offset = ($page - 1) * $items_per_page;

            $filter_type = $_GET['filter_type'] ?? 'all';
            $filter_status = $_GET['filter_status'] ?? 'all';

            // --- Build WHERE clause for filtering ---
            $base_sql = "FROM recurring_payments";
            $where_clauses = [];
            $params = [];

            if ($filter_type !== 'all') {
                $where_clauses[] = "payment_type = ?";
                $params[] = $filter_type;
            }
            if ($filter_status !== 'all') {
                $where_clauses[] = "is_active = ?";
                $params[] = $filter_status;
            }

            $where_sql = "";
            if (!empty($where_clauses)) {
                $where_sql = " WHERE " . implode(' AND ', $where_clauses);
            }

            // --- Get total count for pagination ---
            $count_stmt = $pdo->prepare("SELECT COUNT(*) as total " . $base_sql . $where_sql);
            $count_stmt->execute($params);
            $total_items = $count_stmt->fetchColumn();
            $total_pages = ceil($total_items / $items_per_page);

            // --- Get data for the current page ---
            $data_sql = "SELECT * " . $base_sql . $where_sql . " ORDER BY created_at DESC LIMIT ? OFFSET ?";
            $data_params = array_merge($params, [$items_per_page, $offset]);

            $data_stmt = $pdo->prepare($data_sql);
            // PDO can't bind two integers (LIMIT, OFFSET) directly with array_merge if there are other params.
            // We need to bind them manually with correct types.
            $i = 1;
            foreach ($params as $param) {
                $data_stmt->bindValue($i++, $param);
            }
            $data_stmt->bindValue($i++, $items_per_page, PDO::PARAM_INT);
            $data_stmt->bindValue($i++, $offset, PDO::PARAM_INT);

            $data_stmt->execute();
            $rules = $data_stmt->fetchAll();

            $response = [
                'pagination' => [
                    'total_items' => (int)$total_items,
                    'total_pages' => (int)$total_pages,
                    'current_page' => $page,
                    'items_per_page' => $items_per_page
                ],
                'data' => $rules
            ];
            json_response('success', $response);
            break;

        // --- ACTION: get_history ---
        case 'get_history':
            $page = filter_input(INPUT_GET, 'page', FILTER_VALIDATE_INT, ['options' => ['default' => 1, 'min_range' => 1]]);
            $items_per_page = 15;
            $offset = ($page - 1) * $items_per_page;

            $filter_type = $_GET['filter_type'] ?? 'all';
            $filter_status = $_GET['filter_status'] ?? 'all';
            $filter_start_date = $_GET['filter_start_date'] ?? '';
            $filter_end_date = $_GET['filter_end_date'] ?? '';

            // --- Build WHERE clause for filtering ---
            $base_sql = "FROM payment_log AS pl JOIN recurring_payments AS rp ON pl.payment_id = rp.id";
            $where_clauses = [];
            $params = [];

            if ($filter_type !== 'all') {
                $where_clauses[] = "rp.payment_type = ?";
                $params[] = $filter_type;
            }
            if ($filter_status !== 'all') {
                $where_clauses[] = "pl.status = ?";
                $params[] = $filter_status;
            }
            if (!empty($filter_start_date)) {
                $where_clauses[] = "pl.due_date >= ?";
                $params[] = $filter_start_date;
            }
            if (!empty($filter_end_date)) {
                $where_clauses[] = "pl.due_date <= ?";
                $params[] = $filter_end_date;
            }

            $where_sql = !empty($where_clauses) ? " WHERE " . implode(' AND ', $where_clauses) : "";

            // --- Get total count for pagination ---
            $count_stmt = $pdo->prepare("SELECT COUNT(pl.id) " . $base_sql . $where_sql);
            $count_stmt->execute($params);
            $total_items = $count_stmt->fetchColumn();
            $total_pages = ceil($total_items / $items_per_page);

            // --- Get data for the current page ---
            $select_cols = "pl.id as log_id, pl.due_date, pl.status, pl.paid_on, rp.payment_name, rp.payment_type, rp.amount";
            $data_sql = "SELECT " . $select_cols . " " . $base_sql . $where_sql . " ORDER BY pl.due_date DESC LIMIT ? OFFSET ?";

            $data_stmt = $pdo->prepare($data_sql);
            $i = 1;
            foreach ($params as $param) {
                $data_stmt->bindValue($i++, $param);
            }
            $data_stmt->bindValue($i++, $items_per_page, PDO::PARAM_INT);
            $data_stmt->bindValue($i++, $offset, PDO::PARAM_INT);

            $data_stmt->execute();
            $history = $data_stmt->fetchAll();

            $response = [
                'pagination' => [
                    'total_items' => (int)$total_items,
                    'total_pages' => (int)$total_pages,
                    'current_page' => $page,
                    'items_per_page' => $items_per_page
                ],
                'data' => $history
            ];
            json_response('success', $response);
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
