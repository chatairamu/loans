# Personal Recurring Payments Manager

A simple, full-stack web application designed to help you track all your recurring monthly payments in one place. Never miss a due date for your loans, EMIs, rent, subscriptions, or insurance premiums again.

## Key Features

- **Clean Dashboard:** A modern, responsive dashboard that shows all your upcoming payments.
- **Payment Tracking:** View all payments due in the next 10 days, with clear details on the name, type, amount, and due date.
- **Mark as Paid:** Interactively mark payments as paid directly from the dashboard. The UI updates in real-time without needing a page reload.
- **Smart Buttons:** The "Mark as Paid" button is only enabled on or after the payment's due date, preventing accidental early marking.
- **Add New Payments:** Easily add new recurring payments through a simple and intuitive modal form.
- **Automated Log Generation:** A backend cron job automatically generates monthly payment logs from your master list of recurring payments.
- **Secure Backend:** The PHP backend uses PDO and prepared statements to protect against SQL injection attacks.

## Technology Stack

- **Backend:** PHP
- **Database:** MySQL
- **Frontend:** HTML, CSS, JavaScript
- **Styling:** Tailwind CSS (via CDN)

## Prerequisites

- A web server with PHP support (e.g., Apache, Nginx)
- PHP (version 7.4 or higher recommended)
- MySQL Database Server

## Setup and Installation

Follow these steps to get the application running on your local server:

1.  **Download Files:**
    Download all the project files (`index.php`, `api.php`, `cron_job.php`, `db_connect.php`, `database.sql`) and place them in a directory on your web server.

2.  **Create Database:**
    Create a new database in your MySQL server. You can name it whatever you like, for example, `payments_db`.

3.  **Import Schema:**
    Import the `database.sql` file into your newly created database. This will create the necessary `recurring_payments` and `payment_log` tables and populate them with some sample data.
    ```sh
    mysql -u your_username -p your_database_name < database.sql
    ```

4.  **Configure Database Connection:**
    Open the `db_connect.php` file and update the following constants with your database credentials:
    ```php
    define('DB_HOST', '127.0.0.1');
    define('DB_NAME', 'payments_db'); // Your database name
    define('DB_USER', 'your_username'); // Your database username
    define('DB_PASS', 'your_password'); // Your database password
    ```

5.  **Set Up Cron Job:**
    The `cron_job.php` script needs to be run daily to automatically create payment logs for the current month. Set up a cron job on your server to execute this script once a day (e.g., just after midnight).

    Example cron command:
    ```sh
    # Runs every day at 00:01 (1 minute past midnight)
    1 0 * * * /usr/bin/php /path/to/your/project/cron_job.php >> /path/to/your/project/cron.log 2>&1
    ```
    *Make sure to use the correct absolute paths for your PHP executable and the `cron_job.php` script.*

6.  **Run Application:**
    Open your web browser and navigate to the URL where you placed the project files (e.g., `http://localhost/payments-manager/`).

## File Structure

-   `database.sql`: The database schema and sample data.
-   `db_connect.php`: Handles the secure connection to the MySQL database using PDO.
-   `cron_job.php`: The backend script that generates monthly payment logs. Should be run daily.
-   `api.php`: The backend API endpoint that handles all AJAX requests from the frontend (fetching payments, marking as paid, adding new payments).
-   `index.php`: The main frontend file. It contains the HTML, CSS, and JavaScript for the user interface and application logic.
-   `README.md`: This file.
