-- Create database
CREATE DATABASE IF NOT EXISTS paralize_monitoring;
USE paralize_monitoring;

-- Create doctors table
CREATE TABLE IF NOT EXISTS doctors (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) UNIQUE NOT NULL,
    password VARCHAR(255) NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(100) UNIQUE NOT NULL,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create patients table
CREATE TABLE IF NOT EXISTS patients (
    id INT AUTO_INCREMENT PRIMARY KEY,
    full_name VARCHAR(100) NOT NULL,
    age INT NOT NULL,
    gender ENUM('Male', 'Female', 'Other') NOT NULL,
    address TEXT NOT NULL,
    emergency_contact VARCHAR(100) NOT NULL,
    emergency_phone VARCHAR(20) NOT NULL,
    doctor_id INT,
    device_id VARCHAR(50) UNIQUE,
    is_active BOOLEAN DEFAULT TRUE,
    registration_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create caretaker_credentials table (renamed from caretakers)
CREATE TABLE IF NOT EXISTS caretaker_credentials (
    id INT AUTO_INCREMENT PRIMARY KEY,
    first_name VARCHAR(50) NOT NULL,
    last_name VARCHAR(50) NOT NULL,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    patient_id INT DEFAULT NULL,
    email VARCHAR(100) UNIQUE,
    phone VARCHAR(20),
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create vital_signs table
CREATE TABLE IF NOT EXISTS vital_signs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    device_id VARCHAR(50) NOT NULL,
    patient_id INT NOT NULL,
    heart_rate FLOAT,
    spo2 FLOAT,
    temperature FLOAT,
    status VARCHAR(20) DEFAULT 'normal',
    tilt_message TEXT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    FOREIGN KEY (device_id) REFERENCES patients(device_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create messages table for chat functionality
CREATE TABLE IF NOT EXISTS messages (
    id INT AUTO_INCREMENT PRIMARY KEY,
    sender_id INT NOT NULL,
    sender_type ENUM('doctor', 'caretaker') NOT NULL,
    receiver_id INT NOT NULL,
    receiver_type ENUM('doctor', 'caretaker') NOT NULL,
    message TEXT NOT NULL,
    is_read BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_sender (sender_type, sender_id),
    INDEX idx_receiver (receiver_type, receiver_id),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create alerts table
CREATE TABLE IF NOT EXISTS alerts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    patient_id INT NOT NULL,
    alert_type VARCHAR(50) NOT NULL,
    severity ENUM('low', 'medium', 'high') NOT NULL,
    message TEXT NOT NULL,
    is_resolved BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL DEFAULT NULL,
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE,
    INDEX idx_patient_alert (patient_id, alert_type, severity),
    INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Create indexes for faster queries
CREATE INDEX idx_vital_signs_device ON vital_signs(device_id);
CREATE INDEX idx_vital_signs_timestamp ON vital_signs(timestamp);

-- Create a users view that combines doctors and caretakers for messaging
CREATE OR REPLACE VIEW users AS
SELECT 
    id, 
    username, 
    full_name, 
    'doctor' AS user_type,
    created_at
FROM doctors
UNION ALL
SELECT 
    id, 
    username, 
    CONCAT(first_name, ' ', last_name) AS full_name, 
    'caretaker' AS user_type,
    created_at
FROM caretaker_credentials;