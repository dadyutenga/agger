<?php
require_once "config.php";

function executeSQL($conn, $sql) {
    if (mysqli_query($conn, $sql)) {
        echo "✓ Success: " . substr($sql, 0, 50) . "...\n";
    } else {
        echo "✗ Error: " . mysqli_error($conn) . "\n";
    }
}

// Create database if not exists
$sql = "CREATE DATABASE IF NOT EXISTS paralize_monitoring";
executeSQL($conn, $sql);

// Select the database
mysqli_select_db($conn, "paralize_monitoring");

// Create doctors table
$sql = "CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
)";
executeSQL($conn, $sql);

// Drop existing patients table if exists
$sql = "DROP TABLE IF EXISTS vital_signs";
executeSQL($conn, $sql);
$sql = "DROP TABLE IF EXISTS patients";
executeSQL($conn, $sql);

// Create patients table with new structure
$sql = "CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    age INT NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    region VARCHAR(100) NOT NULL,
    ward VARCHAR(100) NOT NULL,
    street VARCHAR(100) NOT NULL,
    emergency_contact VARCHAR(100) NOT NULL,
    emergency_phone VARCHAR(20) NOT NULL,
    doctor_id INT,
    device_id VARCHAR(50) UNIQUE,
    registration_date DATE NOT NULL,
    image_path VARCHAR(255),
    status ENUM('Active', 'Critical', 'Stable', 'Recovering') DEFAULT 'Stable',
    notes TEXT,
    is_active BOOLEAN DEFAULT TRUE,
    deleted_at TIMESTAMP NULL,
    last_update TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
)";
executeSQL($conn, $sql);

// Create vital_signs table
$sql = "CREATE TABLE IF NOT EXISTS vital_signs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(50) NOT NULL,
    patient_id INT NOT NULL,
    heart_rate FLOAT,
    heart_rate_status ENUM('normal', 'high', 'low', 'no_finger') DEFAULT 'no_finger',
    spo2 FLOAT,
    spo2_status ENUM('normal', 'high', 'low', 'no_finger') DEFAULT 'no_finger',
    temperature FLOAT,
    temperature_status ENUM('normal', 'high', 'low') DEFAULT 'normal',
    tilt_message TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (device_id) REFERENCES patients(device_id)
)";
executeSQL($conn, $sql);

// Drop existing vital_signs table if exists and recreate
$sql = "DROP TABLE IF EXISTS vital_signs";
executeSQL($conn, $sql);

// Create vital_signs table with new structure
$sql = "CREATE TABLE vital_signs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(50) NOT NULL,
    patient_id INT NOT NULL,
    heart_rate FLOAT,
    heart_rate_status ENUM('normal', 'high', 'low', 'no_finger') DEFAULT 'no_finger',
    spo2 FLOAT,
    spo2_status ENUM('normal', 'high', 'low', 'no_finger') DEFAULT 'no_finger',
    temperature FLOAT,
    temperature_status ENUM('normal', 'high', 'low') DEFAULT 'normal',
    tilt_message TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (device_id) REFERENCES patients(device_id)
)";
executeSQL($conn, $sql);

// Create index for faster queries
$sql = "CREATE INDEX idx_vital_signs_device ON vital_signs(device_id)";
executeSQL($conn, $sql);

$sql = "CREATE INDEX idx_vital_signs_timestamp ON vital_signs(timestamp)";
executeSQL($conn, $sql);

// Create alerts table for tracking patient alerts
$sql = "CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    vital_sign_id INT NOT NULL,
    alert_type ENUM('Heart Rate', 'SpO2', 'Temperature', 'Multiple') NOT NULL,
    severity ENUM('Warning', 'Critical', 'Emergency') NOT NULL,
    message TEXT NOT NULL,
    acknowledged BOOLEAN DEFAULT FALSE,
    acknowledged_by INT,
    acknowledged_at TIMESTAMP NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (vital_sign_id) REFERENCES vital_signs(id),
    FOREIGN KEY (acknowledged_by) REFERENCES doctors(id)
)";
executeSQL($conn, $sql);

// Create caretaker_credentials table
$sql = "DROP TABLE IF EXISTS caretaker_credentials";
executeSQL($conn, $sql);

$sql = "CREATE TABLE IF NOT EXISTS caretaker_credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id)
)";
executeSQL($conn, $sql);

// Create messages table
$sql = "CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_type ENUM('doctor', 'caretaker') NOT NULL,
    sender_id INT NOT NULL,
    receiver_type ENUM('doctor', 'caretaker') NOT NULL,
    receiver_id INT NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_sender (sender_type, sender_id),
    INDEX idx_receiver (receiver_type, receiver_id)
)";

if ($conn->query($sql) === TRUE) {
    echo "Messages table created successfully<br>";
} else {
    echo "Error creating messages table: " . $conn->error . "<br>";
}

// Create a test doctor account
$username = "doctor";
$password = password_hash("doctor123", PASSWORD_DEFAULT);
$full_name = "Dr. John Doe";
$email = "doctor@example.com";
$phone = "1234567890";

$sql = "INSERT INTO doctors (username, password, full_name, email, phone) 
        VALUES (?, ?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE 
        password = VALUES(password),
        full_name = VALUES(full_name),
        phone = VALUES(phone)";

if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "sssss", $username, $password, $full_name, $email, $phone);
    if(mysqli_stmt_execute($stmt)) {
        echo "✓ Test doctor account created/updated successfully\n";
        echo "\nLogin Credentials:\n";
        echo "Username: doctor\n";
        echo "Password: doctor123\n";
    } else {
        echo "✗ Error creating test doctor account: " . mysqli_error($conn) . "\n";
    }
    mysqli_stmt_close($stmt);
}

// Create a test patient
$doctor_id = mysqli_insert_id($conn);
if($doctor_id == 0) {
    $result = mysqli_query($conn, "SELECT id FROM doctors WHERE username = 'doctor'");
    $row = mysqli_fetch_assoc($result);
    $doctor_id = $row['id'];
}

$sql = "INSERT INTO patients (
    full_name, age, gender, region, ward, street,
    emergency_contact, emergency_phone, doctor_id, device_id,
    registration_date, status, notes
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURDATE(), 'Stable', 'Initial test patient')";

$patient_name = "John Smith";
$age = 45;
$gender = "Male";
$region = "Dar es Salaam";
$ward = "Kinondoni";
$street = "Msasani";
$emergency_contact = "Jane Smith";
$emergency_phone = "9876543210";
$device_id = "ESP32_001";

if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "sissssssis", 
        $patient_name, $age, $gender, $region, $ward, $street,
        $emergency_contact, $emergency_phone, $doctor_id, $device_id
    );
    if(mysqli_stmt_execute($stmt)) {
        echo "✓ Test patient created/updated successfully\n";
        echo "\nDevice ID for ESP32: " . $device_id . "\n";
    } else {
        echo "✗ Error creating test patient: " . mysqli_error($conn) . "\n";
    }
    mysqli_stmt_close($stmt);
}

// After test patient creation, add caretaker credentials
$sql = "INSERT INTO caretaker_credentials (patient_id, username, password) 
        SELECT id, CONCAT('care_', device_id), ? 
        FROM patients 
        WHERE device_id = ?";

if($stmt = mysqli_prepare($conn, $sql)) {
    $care_password = password_hash("care123", PASSWORD_DEFAULT);
    mysqli_stmt_bind_param($stmt, "ss", $care_password, $device_id);
    if(mysqli_stmt_execute($stmt)) {
        echo "✓ Test caretaker credentials created successfully\n";
        echo "\nCaretaker Login Credentials:\n";
        echo "Username: care_" . $device_id . "\n";
        echo "Password: care123\n";
    } else {
        echo "✗ Error creating test caretaker credentials: " . mysqli_error($conn) . "\n";
    }
    mysqli_stmt_close($stmt);
}

// Add some test vital signs
$patient_id = mysqli_insert_id($conn);
if($patient_id == 0) {
    $result = mysqli_query($conn, "SELECT id FROM patients WHERE device_id = 'ESP32_001'");
    $row = mysqli_fetch_assoc($result);
    $patient_id = $row['id'];
}

$sql = "INSERT INTO vital_signs (
    device_id, patient_id, heart_rate, heart_rate_status, spo2, spo2_status, 
    temperature, temperature_status, tilt_message
) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NULL)";

if($stmt = mysqli_prepare($conn, $sql)) {
    $heart_rate = 75.5;
    $heart_rate_status = 'normal';
    $spo2 = 98.0;
    $spo2_status = 'normal';
    $temperature = 36.8;
    $temperature_status = 'normal';
    mysqli_stmt_bind_param($stmt, "sidsdsds", 
        $device_id, $patient_id, 
        $heart_rate, $heart_rate_status,
        $spo2, $spo2_status,
        $temperature, $temperature_status
    );
    if(mysqli_stmt_execute($stmt)) {
        echo "✓ Test vital signs added successfully\n";
    } else {
        echo "✗ Error adding test vital signs: " . mysqli_error($conn) . "\n";
    }
    mysqli_stmt_close($stmt);
}

echo "\nDatabase setup completed!\n";
mysqli_close($conn);
?> 