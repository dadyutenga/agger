<?php
require_once "config.php";

// Check if all required data is present
if(!isset($_POST["device_id"]) || !isset($_POST["heart_rate"]) || !isset($_POST["spo2"]) || !isset($_POST["temperature"])) {
    http_response_code(400);
    echo json_encode(["error" => "Missing required data"]);
    exit;
}

$device_id = $_POST["device_id"];
$heart_rate = floatval($_POST["heart_rate"]);
$spo2 = floatval($_POST["spo2"]);
$temperature = floatval($_POST["temperature"]);

// Validate data ranges
if($heart_rate < 0 || $heart_rate > 300 || $spo2 < 0 || $spo2 > 100 || $temperature < 30 || $temperature > 45) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid data ranges"]);
    exit;
}

// Get patient ID from device ID
$sql = "SELECT id FROM patients WHERE device_id = ?";
if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "s", $device_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    if($row = mysqli_fetch_array($result)) {
        $patient_id = $row['id'];
        
        // Insert vital signs
        $sql = "INSERT INTO vital_signs (patient_id, heart_rate, spo2, temperature) VALUES (?, ?, ?, ?)";
        if($stmt = mysqli_prepare($conn, $sql)) {
            mysqli_stmt_bind_param($stmt, "iddd", $patient_id, $heart_rate, $spo2, $temperature);
            
            if(mysqli_stmt_execute($stmt)) {
                echo json_encode(["success" => true]);
            } else {
                http_response_code(500);
                echo json_encode(["error" => "Failed to save data"]);
            }
        }
    } else {
        http_response_code(404);
        echo json_encode(["error" => "Device not found"]);
    }
    
    mysqli_stmt_close($stmt);
}

mysqli_close($conn);
?> 