<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');        // Default XAMPP MySQL user
define('DB_PASS', '');            // Default XAMPP MySQL password (empty)
define('DB_NAME', 'lost_and_found_db');

// Create connection
$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

// Check connection
if ($conn->connect_error) {
    die(json_encode([
        'success' => false,
        'message' => 'Database connection failed: ' . $conn->connect_error
    ]));
}

// Set charset to UTF-8 for proper encoding
$conn->set_charset('utf8mb4');
?>