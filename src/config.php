<?php
// Database configuration
$db_host = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'paralize_monitoring';

// Create connection
$conn = new mysqli($db_host, $db_user, $db_pass);

// Check connection
if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}

// Select the database
if (!$conn->select_db($db_name)) {
    die("Database selection failed: " . $conn->error);
}

// Set charset to utf8mb4
$conn->set_charset("utf8mb4");
?> 