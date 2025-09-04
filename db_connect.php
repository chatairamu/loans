<?php
// db_connect.php
// This file establishes a connection to the MySQL database using PDO.

// --- Database Configuration ---
// IMPORTANT: Replace these with your actual database credentials.
define('DB_HOST', '127.0.0.1');      // Often 'localhost' or an IP address
define('DB_NAME', 'payments_db');   // The name of your database
define('DB_USER', 'root');          // Your database username
define('DB_PASS', 'password');      // Your database password
define('DB_CHARSET', 'utf8mb4');

// --- Data Source Name (DSN) ---
// This string contains the information required to connect to the database.
$dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET;

// --- PDO Connection Options ---
// An array of options for the PDO connection.
$options = [
    // 1. Error Reporting: Throw exceptions on errors. This is more robust than warnings.
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    // 2. Default Fetch Mode: Fetch results as associative arrays.
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    // 3. Emulate Prepares: Use native prepared statements from the database driver.
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
    // --- Create PDO Instance ---
    // This is the actual connection object.
    $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
} catch (\PDOException $e) {
    // --- Connection Error Handling ---
    // If the connection fails, we stop the script and provide an error message.
    // In a production environment, you would log the error ($e->getMessage())
    // and show a more generic error message to the user.
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'Database connection failed. Please check your configuration in db_connect.php and ensure the database server is running.'
    ]);
    // For debugging during development, you might want to see the actual error:
    // throw new \PDOException($e->getMessage(), (int)$e->getCode());
    exit(); // Terminate the script
}

// If the script reaches this point, the $pdo object is successfully created and
// is ready to be used by any script that includes this file.
?>
