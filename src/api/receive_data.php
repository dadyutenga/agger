<?php
require_once '../config.php';

// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Log raw POST data
error_log("Raw POST data: " . file_get_contents('php://input'));

// Get POST data
$data = $_POST;

// Log received data
error_log("Received data: " . print_r($data, true));

// Validate device_id
if (!isset($data['device_id']) || empty($data['device_id'])) {
    error_log("Error: device_id is required");
    echo json_encode(['success' => false, 'message' => 'device_id is required']);
    exit;
}

$device_id = $data['device_id'];

// Get patient_id from device_id
$sql = "SELECT id FROM patients WHERE device_id = ? AND is_active = TRUE";
$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database prepare error']);
    exit;
}

$stmt->bind_param("s", $device_id);
$stmt->execute();
$result = $stmt->get_result();

if ($result->num_rows === 0) {
    error_log("Error: Invalid device ID or patient not active");
    echo json_encode(['success' => false, 'message' => 'Invalid device ID or patient not active']);
    exit;
}

$patient = $result->fetch_assoc();
$patient_id = $patient['id'];
$stmt->close();

// Prepare data for insertion
$heart_rate = isset($data['heart_rate']) ? floatval($data['heart_rate']) : null;
$heart_rate_status = isset($data['heart_rate_status']) ? $data['heart_rate_status'] : 'no_finger';
$spo2 = isset($data['spo2']) ? floatval($data['spo2']) : null;
$spo2_status = isset($data['spo2_status']) ? $data['spo2_status'] : 'no_finger';
$temperature = isset($data['temperature']) ? floatval($data['temperature']) : null;
$temperature_status = isset($data['temperature_status']) ? $data['temperature_status'] : 'normal';
$tilt_message = isset($data['tilt_message']) ? $data['tilt_message'] : null;

// Log processed data
error_log("Processed data: " . print_r([
    'device_id' => $device_id,
    'patient_id' => $patient_id,
    'heart_rate' => $heart_rate,
    'heart_rate_status' => $heart_rate_status,
    'spo2' => $spo2,
    'spo2_status' => $spo2_status,
    'temperature' => $temperature,
    'temperature_status' => $temperature_status,
    'tilt_message' => $tilt_message
], true));

// Insert vital signs data
$sql = "INSERT INTO vital_signs (
    device_id, patient_id,
    heart_rate, heart_rate_status,
    spo2, spo2_status,
    temperature, temperature_status,
    tilt_message
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";

$stmt = $conn->prepare($sql);

if (!$stmt) {
    error_log("Prepare failed: " . $conn->error);
    echo json_encode(['success' => false, 'message' => 'Database prepare error: ' . $conn->error]);
    exit;
}

$stmt->bind_param("sisdsdsds", 
    $device_id,
    $patient_id,
    $heart_rate,
    $heart_rate_status,
    $spo2,
    $spo2_status,
    $temperature,
    $temperature_status,
    $tilt_message
);

if (!$stmt->execute()) {
    error_log("Execute failed: " . $stmt->error);
    echo json_encode(['success' => false, 'message' => 'Database insert error: ' . $stmt->error]);
    exit;
}

$vital_signs_id = $stmt->insert_id;
$stmt->close();

// Verify inserted data
$verify_stmt = $conn->prepare("SELECT * FROM vital_signs WHERE id = ?");
$verify_stmt->bind_param("i", $vital_signs_id);
$verify_stmt->execute();
$result = $verify_stmt->get_result();
$inserted_data = $result->fetch_assoc();
$verify_stmt->close();

error_log("Inserted data: " . print_r($inserted_data, true));

// Check for alerts based on status
$alerts = [];
if ($heart_rate_status === 'high' || $heart_rate_status === 'low') {
    $alerts[] = [
        'type' => 'Heart Rate',
        'severity' => $heart_rate_status === 'high' ? 'Critical' : 'Warning',
        'message' => "Heart Rate is " . ($heart_rate_status === 'high' ? 'high' : 'low') . ": " . $heart_rate . " BPM"
    ];
}
if ($spo2_status === 'high' || $spo2_status === 'low') {
    $alerts[] = [
        'type' => 'SpO2',
        'severity' => $spo2_status === 'low' ? 'Critical' : 'Warning',
        'message' => "SpO2 is " . ($spo2_status === 'high' ? 'high' : 'low') . ": " . $spo2 . "%"
    ];
}
if ($temperature_status === 'high' || $temperature_status === 'low') {
    $alerts[] = [
        'type' => 'Temperature',
        'severity' => $temperature_status === 'high' ? 'Critical' : 'Warning',
        'message' => "Temperature is " . ($temperature_status === 'high' ? 'high' : 'low') . ": " . $temperature . "°C"
    ];
}

// Insert alerts if any
if (!empty($alerts)) {
    $alert_sql = "INSERT INTO alerts (patient_id, vital_sign_id, alert_type, severity, message) VALUES (?, ?, ?, ?, ?)";
    $alert_stmt = $conn->prepare($alert_sql);
    
    if ($alert_stmt) {
        foreach ($alerts as $alert) {
            $alert_stmt->bind_param("iisss", 
                $patient_id,
                $vital_signs_id,
                $alert['type'],
                $alert['severity'],
                $alert['message']
            );
            $alert_stmt->execute();
        }
        $alert_stmt->close();
    } else {
        error_log("Alert prepare failed: " . $conn->error);
    }
}

// Return success response with inserted data
echo json_encode([
    'success' => true,
    'message' => 'Data received and stored successfully',
    'alerts' => $alerts,
    'data' => $inserted_data
]);
?> 