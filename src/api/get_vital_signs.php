<?php
session_start();
require_once "config.php";

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Check if patient ID is provided
if(!isset($_GET["patient_id"])){
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Patient ID required']);
    exit;
}

$patient_id = $_GET["patient_id"];

// Get latest vital signs
$sql = "SELECT device_id, heart_rate, spo2, temperature, status, tilt_message, timestamp 
        FROM vital_signs 
        WHERE patient_id = ? 
        ORDER BY timestamp DESC 
        LIMIT 1";

if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if($row = mysqli_fetch_array($result)){
        header('Content-Type: application/json');
        echo json_encode([
            'device_id' => $row['device_id'],
            'heart_rate' => $row['heart_rate'] !== null ? floatval($row['heart_rate']) : null,
            'spo2' => $row['spo2'] !== null ? floatval($row['spo2']) : null,
            'temperature' => $row['temperature'] !== null ? floatval($row['temperature']) : null,
            'status' => $row['status'],
            'tilt_message' => $row['tilt_message'],
            'timestamp' => $row['timestamp']
        ]);
    } else {
        // If no data found, return null values
        header('Content-Type: application/json');
        echo json_encode([
            'device_id' => null,
            'heart_rate' => null,
            'spo2' => null,
            'temperature' => null,
            'status' => 'no_data',
            'tilt_message' => null,
            'timestamp' => null
        ]);
    }
    mysqli_stmt_close($stmt);
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error']);
}

mysqli_close($conn);
?> 