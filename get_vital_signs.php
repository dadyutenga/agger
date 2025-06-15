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
$sql = "SELECT heart_rate, spo2, temperature 
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
            'heart_rate' => floatval($row['heart_rate']),
            'spo2' => floatval($row['spo2']),
            'temperature' => floatval($row['temperature'])
        ]);
    } else {
        // If no data found, return default values
        header('Content-Type: application/json');
        echo json_encode([
            'heart_rate' => 0,
            'spo2' => 0,
            'temperature' => 0
        ]);
    }
    mysqli_stmt_close($stmt);
} else {
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Database error']);
}

mysqli_close($conn);
?> 