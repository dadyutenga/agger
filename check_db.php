<?php
require_once "config.php";

echo "<h2>Database Check Results</h2>";

// Check tables
echo "<h3>Tables in Database:</h3>";
$result = mysqli_query($conn, "SHOW TABLES");
while($row = mysqli_fetch_row($result)) {
    echo "- " . $row[0] . "<br>";
}

// Check caretaker_credentials table
echo "<h3>Caretaker Credentials Structure:</h3>";
$result = mysqli_query($conn, "DESCRIBE caretaker_credentials");
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        echo "- " . $row['Field'] . " (" . $row['Type'] . ")<br>";
    }
} else {
    echo "Error: Table caretaker_credentials not found<br>";
}

// Check caretaker data
echo "<h3>Caretaker Data:</h3>";
$result = mysqli_query($conn, "SELECT c.*, p.full_name as patient_name 
                              FROM caretaker_credentials c 
                              LEFT JOIN patients p ON c.patient_id = p.id");
if($result) {
    while($row = mysqli_fetch_assoc($result)) {
        echo "Username: " . $row['username'] . "<br>";
        echo "Patient ID: " . ($row['patient_id'] ?? 'Not assigned') . "<br>";
        echo "Patient Name: " . ($row['patient_name'] ?? 'Not found') . "<br>";
        echo "-------------------<br>";
    }
} else {
    echo "Error querying caretaker data: " . mysqli_error($conn) . "<br>";
}

// Check if test caretaker exists
echo "<h3>Test Caretaker Check:</h3>";
$stmt = mysqli_prepare($conn, "SELECT c.*, p.full_name as patient_name 
                              FROM caretaker_credentials c 
                              LEFT JOIN patients p ON c.patient_id = p.id 
                              WHERE c.username = ?");
mysqli_stmt_bind_param($stmt, "s", $test_username);
$test_username = "care_ESP32_001";
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if($row = mysqli_fetch_assoc($result)) {
    echo "Test caretaker found:<br>";
    echo "Username: " . $row['username'] . "<br>";
    echo "Patient ID: " . ($row['patient_id'] ?? 'Not assigned') . "<br>";
    echo "Patient Name: " . ($row['patient_name'] ?? 'Not found') . "<br>";
} else {
    echo "Test caretaker not found<br>";
}
?> 