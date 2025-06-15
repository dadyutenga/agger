<?php
session_start();
require_once "config.php";

// Check if user is logged in
if (!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}

// Get patient ID from request
$patient_id = isset($_GET['patient_id']) ? $_GET['patient_id'] : null;

if (!$patient_id) {
    http_response_code(400);
    echo json_encode(["error" => "Patient ID is required"]);
    exit;
}

// Verify user has access to this patient
if ($_SESSION["user_type"] === "caretaker") {
    $sql = "SELECT 1 FROM patients WHERE id = ? AND device_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "is", $patient_id, $_SESSION["username"]);
} else {
    $sql = "SELECT 1 FROM patients WHERE id = ? AND doctor_id = ?";
    $stmt = mysqli_prepare($conn, $sql);
    mysqli_stmt_bind_param($stmt, "ii", $patient_id, $_SESSION["id"]);
}

mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if (mysqli_num_rows($result) === 0) {
    http_response_code(403);
    echo json_encode(["error" => "Access denied"]);
    exit;
}

// Get latest vital signs
$sql = "SELECT heart_rate, spo2, temperature, timestamp 
        FROM vital_signs 
        WHERE patient_id = ? 
        ORDER BY timestamp DESC 
        LIMIT 1";

$stmt = mysqli_prepare($conn, $sql);
mysqli_stmt_bind_param($stmt, "i", $patient_id);
mysqli_stmt_execute($stmt);
$result = mysqli_stmt_get_result($stmt);

if ($row = mysqli_fetch_assoc($result)) {
    echo json_encode([
        "heart_rate" => (int)$row["heart_rate"],
        "spo2" => (int)$row["spo2"],
        "temperature" => (float)$row["temperature"],
        "timestamp" => $row["timestamp"]
    ]);
} else {
    // Return default values if no data available
    echo json_encode([
        "heart_rate" => 0,
        "spo2" => 0,
        "temperature" => 0,
        "timestamp" => date("Y-m-d H:i:s")
    ]);
}
?> 