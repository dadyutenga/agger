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
);

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
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (doctor_id) REFERENCES doctors(id)
);

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
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (device_id) REFERENCES patients(device_id)
);

-- Create index for faster queries
CREATE INDEX idx_vital_signs_device ON vital_signs(device_id);
CREATE INDEX idx_vital_signs_timestamp ON vital_signs(timestamp); 