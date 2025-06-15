<?php
require_once "config.php";

echo "<h2>Caretaker Credentials Check</h2>";

// Check caretaker_credentials table
$sql = "SELECT c.*, p.full_name as patient_name, p.device_id 
        FROM caretaker_credentials c 
        LEFT JOIN patients p ON c.patient_id = p.id";
$result = mysqli_query($conn, $sql);

if($result) {
    echo "<h3>All Caretaker Credentials:</h3>";
    while($row = mysqli_fetch_assoc($result)) {
        echo "<div style='margin-bottom: 20px; padding: 10px; border: 1px solid #ccc;'>";
        echo "<strong>Username:</strong> " . htmlspecialchars($row['username']) . "<br>";
        echo "<strong>Patient Name:</strong> " . htmlspecialchars($row['patient_name']) . "<br>";
        echo "<strong>Device ID:</strong> " . htmlspecialchars($row['device_id']) . "<br>";
        echo "<strong>Created At:</strong> " . htmlspecialchars($row['created_at']) . "<br>";
        echo "</div>";
    }
} else {
    echo "<p style='color: red;'>Error querying database: " . mysqli_error($conn) . "</p>";
}

// Check for specific username
$test_username = "ESP32_1742691228";
echo "<h3>Checking for username: " . htmlspecialchars($test_username) . "</h3>";

$sql = "SELECT c.*, p.full_name as patient_name, p.device_id 
        FROM caretaker_credentials c 
        LEFT JOIN patients p ON c.patient_id = p.id 
        WHERE c.username = ? OR c.username = ?";
$stmt = mysqli_prepare($conn, $sql);
$with_prefix = "care_" . $test_username;
mysqli_stmt_bind_param($stmt, "ss", $test_username, $with_prefix);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if($row = mysqli_fetch_assoc($result)) {
    echo "<p style='color: green;'>Found matching credentials:</p>";
    echo "<div style='margin-bottom: 20px; padding: 10px; border: 1px solid #ccc;'>";
    echo "<strong>Username:</strong> " . htmlspecialchars($row['username']) . "<br>";
    echo "<strong>Patient Name:</strong> " . htmlspecialchars($row['patient_name']) . "<br>";
    echo "<strong>Device ID:</strong> " . htmlspecialchars($row['device_id']) . "<br>";
    echo "</div>";
} else {
    echo "<p style='color: red;'>No credentials found for this username or with 'care_' prefix</p>";
}
?> 