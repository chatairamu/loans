-- Personal Recurring Payments Manager
-- Database Schema

-- Table to store the master list of all recurring payments.
CREATE TABLE `recurring_payments` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_name` varchar(255) NOT NULL,
  `payment_type` enum('Loan','EMI','Rent','Recharge','Insurance','Other') NOT NULL,
  `amount` decimal(10,2) NOT NULL,
  `due_day` int(11) NOT NULL COMMENT 'Day of the month payment is due (1-31)',
  `start_date` date NOT NULL,
  `end_date` date DEFAULT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Table to log each individual payment instance as it becomes due.
CREATE TABLE `payment_log` (
  `id` int(11) NOT NULL AUTO_INCREMENT,
  `payment_id` int(11) NOT NULL,
  `due_date` date NOT NULL,
  `status` enum('upcoming','paid') NOT NULL DEFAULT 'upcoming',
  `paid_on` datetime DEFAULT NULL,
  PRIMARY KEY (`id`),
  KEY `payment_id` (`payment_id`),
  CONSTRAINT `payment_log_ibfk_1` FOREIGN KEY (`payment_id`) REFERENCES `recurring_payments` (`id`) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- Sample Data (Optional, for testing)
INSERT INTO `recurring_payments` (`payment_name`, `payment_type`, `amount`, `due_day`, `start_date`, `end_date`, `is_active`) VALUES
('Home Loan EMI', 'EMI', 1250.00, 5, '2023-01-01', '2035-12-31', 1),
('Phone Bill', 'Recharge', 60.00, 15, '2023-01-01', NULL, 1),
('Car Insurance', 'Insurance', 85.50, 20, '2023-01-01', NULL, 1),
('Apartment Rent', 'Rent', 800.00, 1, '2023-01-01', NULL, 1);
