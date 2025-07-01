<?php
session_start();
require_once "config.php";
require_once "session.php";

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// Check if patient ID is provided
if(!isset($_GET["id"])){
    header("location: dashboard.php");
    exit;
}

$patient_id = $_GET["id"];

// For caretakers, verify they can only access their assigned patient
if($_SESSION["user_type"] === "caretaker" && $_SESSION["patient_id"] != $patient_id) {
    header("location: patient_details.php?id=" . $_SESSION["patient_id"]);
    exit;
}

// Get patient details
$sql = "SELECT p.*, d.id as doctor_id, d.full_name as doctor_name 
        FROM patients p 
        LEFT JOIN doctors d ON p.doctor_id = d.id 
        WHERE p.id = ?";

if($_SESSION["user_type"] === "doctor") {
    $sql .= " AND p.doctor_id = ?";
}

if($stmt = mysqli_prepare($conn, $sql)){
    if($_SESSION["user_type"] === "doctor") {
        mysqli_stmt_bind_param($stmt, "ii", $patient_id, $_SESSION["id"]);
    } else {
        mysqli_stmt_bind_param($stmt, "i", $patient_id);
    }
    
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $patient = mysqli_fetch_array($result);
    
    if(!$patient){
        header("location: dashboard.php");
        exit;
    }
}

// Parse address into components if it exists
$address_parts = array('region' => '', 'ward' => '', 'street' => '');
if (!empty($patient['address'])) {
    $address_array = explode(', ', $patient['address']);
    if (count($address_array) >= 3) {
        $address_parts['region'] = $address_array[0];
        $address_parts['ward'] = $address_array[1];
        $address_parts['street'] = $address_array[2];
    } else {
        $address_parts['region'] = $patient['address'];
    }
}

// Get latest vital signs
$latest_vitals = null;
$sql = "SELECT * FROM vital_signs WHERE patient_id = ? ORDER BY timestamp DESC LIMIT 1";
if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    $latest_vitals = mysqli_fetch_assoc($result);
}

// Get recent vital signs for charts (last 24 hours)
$chart_data = array();
$sql = "SELECT heart_rate, spo2, temperature, timestamp 
        FROM vital_signs 
        WHERE patient_id = ? AND timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR) 
        ORDER BY timestamp ASC";
if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($result)) {
        $chart_data[] = $row;
    }
}

// Get recent alerts
$recent_alerts = array();
$sql = "SELECT * FROM alerts WHERE patient_id = ? AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) ORDER BY created_at DESC LIMIT 10";
if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $patient_id);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($result)) {
        $recent_alerts[] = $row;
    }
}

// Set up chat URL based on user type
if ($_SESSION["user_type"] === "doctor") {
    // For doctors, chat with the patient's caretaker (device_id)
    $_SESSION['chat_url'] = "chat.php?receiver_type=caretaker&receiver_id=" . urlencode($patient['device_id']);
} else {
    // For caretakers, chat with the patient's doctor
    $_SESSION['chat_url'] = "chat.php?receiver_type=doctor&receiver_id=" . $patient['doctor_id'];
}

// Include the navbar
include "navbar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Patient Details - Patient Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        :root {
            --forest-green: #358927;
            --wattle-green: #D7DE50;
            --white: #ffffff;
            --light-gray: #f8f9fa;
            --dark-text: #2c3e50;
            --shadow: rgba(53, 137, 39, 0.15);
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            background-color: var(--white);
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            min-height: 100vh;
            padding-top: 76px;
        }

        /* Override Bootstrap navbar colors */
        .navbar {
            background: linear-gradient(135deg, var(--forest-green) 0%, #2d7a23 100%) !important;
            box-shadow: 0 2px 10px var(--shadow);
            position: fixed !important;
            top: 0;
            left: 0;
            right: 0;
            z-index: 1030;
        }

        .navbar-brand, .navbar-nav .nav-link {
            color: var(--white) !important;
            font-weight: 500;
        }

        .navbar-brand:hover, .navbar-nav .nav-link:hover,
        .navbar-brand:focus, .navbar-nav .nav-link:focus,
        .navbar-brand:active, .navbar-nav .nav-link:active {
            color: var(--white) !important;
            background-color: transparent !important;
            text-decoration: none !important;
        }

        .navbar-toggler {
            border-color: var(--white) !important;
        }

        .navbar-toggler:hover, .navbar-toggler:focus {
            border-color: var(--white) !important;
            box-shadow: none !important;
        }

        .patient-container {
            display: flex;
            max-width: 1400px;
            margin: 20px auto;
            gap: 20px;
            padding: 0 20px;
        }

        .sidebar {
            width: 350px;
            background: var(--white);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px var(--shadow);
            border: 1px solid rgba(53, 137, 39, 0.1);
            height: fit-content;
            position: sticky;
            top: 96px;
        }

        .main-content {
            flex: 1;
            background: var(--white);
            border-radius: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px var(--shadow);
            border: 1px solid rgba(53, 137, 39, 0.1);
        }

        .patient-image {
            width: 180px;
            height: 180px;
            border-radius: 90px;
            object-fit: cover;
            margin: 0 auto 20px;
            display: block;
            border: 4px solid var(--wattle-green);
            box-shadow: 0 8px 20px var(--shadow);
        }

        .status-badge {
            font-size: 1rem;
            padding: 8px 16px;
            border-radius: 25px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 25px;
            display: inline-block;
        }

        .status-Active, .status-Stable { 
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            color: var(--white);
            box-shadow: 0 4px 15px rgba(46, 204, 113, 0.3);
        }
        
        .status-Critical { 
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: var(--white);
            box-shadow: 0 4px 15px rgba(231, 76, 60, 0.3);
            animation: pulse 2s infinite;
        }
        
        .status-Recovering { 
            background: linear-gradient(135deg, #f1c40f, #f39c12);
            color: var(--white);
            box-shadow: 0 4px 15px rgba(241, 196, 15, 0.3);
        }

        .profile-section {
            margin-bottom: 25px;
            padding: 20px;
            background: linear-gradient(135deg, var(--light-gray), var(--white));
            border-radius: 15px;
            border-left: 4px solid var(--forest-green);
            box-shadow: 0 3px 10px rgba(53, 137, 39, 0.1);
        }

        .profile-section h5 {
            color: var(--forest-green);
            font-weight: 700;
            margin-bottom: 15px;
            font-size: 1.1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .profile-section p {
            margin-bottom: 8px;
            color: var(--dark-text);
            font-weight: 500;
        }

        .profile-section strong {
            color: var(--forest-green);
            font-weight: 600;
        }

        .main-content h2 {
            color: var(--forest-green);
            font-weight: 700;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2rem;
            position: relative;
        }

        .main-content h2::after {
            content: '';
            position: absolute;
            bottom: -10px;
            left: 50%;
            transform: translateX(-50%);
            width: 80px;
            height: 3px;
            background: linear-gradient(135deg, var(--forest-green), var(--wattle-green));
            border-radius: 2px;
        }

        .vital-sign {
            text-align: center;
            padding: 25px;
            border-radius: 20px;
            margin-bottom: 20px;
            transition: all 0.3s ease;
            box-shadow: 0 5px 15px var(--shadow);
            border: 2px solid transparent;
        }

        .vital-sign:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(53, 137, 39, 0.2);
        }

        .vital-sign h5 {
            margin-bottom: 15px;
            color: var(--dark-text);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
            font-size: 0.9rem;
        }

        .vital-sign h2 {
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }

        .vital-sign h2::after {
            display: none;
        }

        .heart-rate { 
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.1), rgba(192, 57, 43, 0.05));
            border-left: 6px solid #e74c3c;
            color: #e74c3c;
        }

        .spo2 { 
            background: linear-gradient(135deg, rgba(52, 152, 219, 0.1), rgba(41, 128, 185, 0.05));
            border-left: 6px solid #3498db;
            color: #3498db;
        }

        .temperature { 
            background: linear-gradient(135deg, rgba(46, 204, 113, 0.1), rgba(39, 174, 96, 0.05));
            border-left: 6px solid #2ecc71;
            color: #2ecc71;
        }

        .vital-sign.normal {
            border-color: var(--forest-green);
            background: linear-gradient(135deg, rgba(53, 137, 39, 0.1), rgba(45, 122, 35, 0.05));
        }

        .vital-sign.warning {
            border-color: #f1c40f;
            background: linear-gradient(135deg, rgba(241, 196, 15, 0.1), rgba(243, 156, 18, 0.05));
            animation: warning-pulse 2s infinite;
        }

        .vital-sign.critical {
            border-color: #e74c3c;
            background: linear-gradient(135deg, rgba(231, 76, 60, 0.2), rgba(192, 57, 43, 0.1));
            animation: critical-pulse 1.5s infinite;
        }

        @keyframes pulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }

        @keyframes warning-pulse {
            0%, 100% { box-shadow: 0 5px 15px var(--shadow); }
            50% { box-shadow: 0 5px 25px rgba(241, 196, 15, 0.4); }
        }

        @keyframes critical-pulse {
            0%, 100% { box-shadow: 0 5px 15px var(--shadow); }
            50% { box-shadow: 0 5px 25px rgba(231, 76, 60, 0.6); }
        }

        .chart-card {
            background: var(--white);
            border-radius: 20px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px var(--shadow);
            border: 2px solid rgba(53, 137, 39, 0.1);
            transition: all 0.3s ease;
        }

        .chart-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(53, 137, 39, 0.2);
            border-color: var(--wattle-green);
        }

        .chart-card h5 {
            color: var(--forest-green);
            font-weight: 700;
            margin-bottom: 20px;
            text-align: center;
            font-size: 1.2rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .chart-container {
            height: 280px;
            position: relative;
        }

        .alert {
            border-radius: 15px;
            border: none;
            padding: 15px 20px;
            margin-bottom: 15px;
            font-weight: 500;
            box-shadow: 0 3px 10px rgba(231, 76, 60, 0.2);
        }

        .alert-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: var(--white);
        }

        .alert-warning {
            background: linear-gradient(135deg, #f1c40f, #f39c12);
            color: var(--dark-text);
        }

        .fas {
            color: var(--forest-green);
            margin-right: 10px;
        }

        .alert .fas {
            color: var(--white);
        }

        .no-data {
            text-align: center;
            padding: 40px;
            color: var(--forest-green);
            opacity: 0.7;
        }

        .no-data i {
            font-size: 48px;
            margin-bottom: 15px;
        }

        .no-data h5 {
            color: var(--forest-green);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .no-data p {
            color: #6c757d;
            font-size: 0.9rem;
        }

        .last-update {
            text-align: center;
            margin-bottom: 20px;
            padding: 10px;
            background: linear-gradient(135deg, rgba(53, 137, 39, 0.05), rgba(215, 222, 80, 0.05));
            border-radius: 10px;
            font-size: 0.9rem;
            color: var(--forest-green);
            font-weight: 600;
        }

        /* Responsive design */
        @media (max-width: 1200px) {
            .patient-container {
                flex-direction: column;
                max-width: 100%;
                padding: 0 15px;
            }
            
            .sidebar {
                width: 100%;
                position: static;
            }
            
            .main-content {
                width: 100%;
            }
        }

        @media (max-width: 768px) {
            .patient-container {
                margin: 10px auto;
                padding: 0 10px;
            }
            
            .sidebar, .main-content {
                padding: 20px;
                border-radius: 15px;
            }
            
            .patient-image {
                width: 150px;
                height: 150px;
                border-radius: 75px;
            }
            
            .vital-sign {
                padding: 20px;
                margin-bottom: 15px;
            }
            
            .vital-sign h2 {
                font-size: 2rem;
            }
            
            .chart-container {
                height: 250px;
            }
        }

        /* Loading animation */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .vital-sign, .chart-card, .profile-section {
            animation: fadeIn 0.6s ease-out;
        }
    </style>
</head>
<body>
    <div class="patient-container">
        <!-- Sidebar with Patient Info -->
        <div class="sidebar">
            <img src="<?php echo !empty($patient['image_path']) && file_exists($patient['image_path']) ? htmlspecialchars($patient['image_path']) : 'images/default-patient.png'; ?>" 
                 class="patient-image" alt="Patient Photo" onerror="this.src='data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgdmlld0JveD0iMCAwIDIwMCAyMDAiIGZpbGw9Im5vbmUiIHhtbG5zPSJodHRwOi8vd3d3LnczLm9yZy8yMDAwL3N2ZyI+CjxyZWN0IHdpZHRoPSIyMDAiIGhlaWdodD0iMjAwIiBmaWxsPSIjRjhGOUZBIi8+CjxwYXRoIGQ9Ik0xMDAgODBDOTEuNzI4IDgwIDg1IDczLjI3MiA4NSA2NVM5MS43MjggNTAgMTAwIDUwUzExNSA1Ni43MjggMTE1IDY1UzEwOC4yNzIgODAgMTAwIDgwWk0xMDAgOTBDMTE4LjIyNSA5MCAxMzMuMzMzIDEwMS40ODUgMTM5LjE2OSAxMTcuNUg2MC44MzA5QzY2LjY2NjcgMTAxLjQ4NSA4MS43NzUgOTAgMTAwIDkwWiIgZmlsbD0iIzM1ODkyNyIvPgo8L3N2Zz4K'">
            
            <div class="text-center">
                <span class="status-badge status-<?php echo htmlspecialchars($patient['is_active'] ? 'Active' : 'Inactive'); ?>">
                    <i class="fas fa-circle me-2"></i><?php echo htmlspecialchars($patient['is_active'] ? 'Active' : 'Inactive'); ?>
                </span>
            </div>
            
            <div class="profile-section">
                <h5><i class="fas fa-user"></i>Personal Information</h5>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['full_name']); ?></p>
                <p><strong>Age:</strong> <?php echo htmlspecialchars($patient['age']); ?> years</p>
                <p><strong>Gender:</strong> <?php echo htmlspecialchars($patient['gender']); ?></p>
                <p><strong>Registration Date:</strong> <?php echo date('d/m/Y', strtotime($patient['registration_date'])); ?></p>
            </div>

            <div class="profile-section">
                <h5><i class="fas fa-map-marker-alt"></i>Address</h5>
                <?php if (!empty($address_parts['region']) || !empty($address_parts['ward']) || !empty($address_parts['street'])): ?>
                    <?php if (!empty($address_parts['region'])): ?>
                        <p><strong>Region:</strong> <?php echo htmlspecialchars($address_parts['region']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($address_parts['ward'])): ?>
                        <p><strong>Ward:</strong> <?php echo htmlspecialchars($address_parts['ward']); ?></p>
                    <?php endif; ?>
                    <?php if (!empty($address_parts['street'])): ?>
                        <p><strong>Street:</strong> <?php echo htmlspecialchars($address_parts['street']); ?></p>
                    <?php endif; ?>
                <?php else: ?>
                    <p><strong>Address:</strong> <?php echo !empty($patient['address']) ? htmlspecialchars($patient['address']) : 'Not specified'; ?></p>
                <?php endif; ?>
            </div>

            <div class="profile-section">
                <h5><i class="fas fa-phone"></i>Emergency Contact</h5>
                <p><strong>Name:</strong> <?php echo htmlspecialchars($patient['emergency_contact']); ?></p>
                <p><strong>Phone:</strong> 
                    <a href="tel:<?php echo htmlspecialchars($patient['emergency_phone']); ?>" style="color: var(--forest-green); text-decoration: none;">
                        <?php echo htmlspecialchars($patient['emergency_phone']); ?>
                    </a>
                </p>
            </div>

            <div class="profile-section">
                <h5><i class="fas fa-microchip"></i>Device Information</h5>
                <p><strong>Device ID:</strong> <?php echo htmlspecialchars($patient['device_id']); ?></p>
                <?php if (!empty($patient['doctor_name'])): ?>
                    <p><strong>Assigned Doctor:</strong> Dr. <?php echo htmlspecialchars($patient['doctor_name']); ?></p>
                <?php endif; ?>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content">
            <h2><i class="fas fa-heartbeat me-3"></i>Real-time Monitoring</h2>

            <!-- Last Update Info -->
            <?php if ($latest_vitals): ?>
                <div class="last-update">
                    <i class="fas fa-clock me-2"></i>
                    Last updated: <?php echo date('d/m/Y H:i:s', strtotime($latest_vitals['timestamp'])); ?>
                </div>
            <?php endif; ?>

            <!-- Alerts Container -->
            <div id="alerts">
                <?php if (!empty($recent_alerts)): ?>
                    <?php foreach($recent_alerts as $alert): ?>
                        <div class="alert alert-<?php echo $alert['severity'] === 'Critical' ? 'danger' : 'warning'; ?> alert-dismissible fade show" role="alert">
                            <i class="fas fa-exclamation-triangle me-2"></i>
                            <strong><?php echo htmlspecialchars($alert['alert_type']); ?>:</strong> 
                            <?php echo htmlspecialchars($alert['message']); ?>
                            <small class="d-block mt-1">
                                <?php echo date('d/m/Y H:i:s', strtotime($alert['created_at'])); ?>
                            </small>
                            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>

            <!-- Vital Signs -->
            <div class="row mb-4">
                <div class="col-lg-4 col-md-6">
                    <div class="vital-sign heart-rate <?php if($latest_vitals) echo ($latest_vitals['heart_rate'] < 60 || $latest_vitals['heart_rate'] > 100) ? 'critical' : (($latest_vitals['heart_rate'] < 66 || $latest_vitals['heart_rate'] > 90) ? 'warning' : 'normal'); ?>">
                        <h5><i class="fas fa-heart me-2"></i>Heart Rate</h5>
                        <h2 id="heart-rate"><?php echo $latest_vitals ? htmlspecialchars($latest_vitals['heart_rate']) . ' BPM' : '-- BPM'; ?></h2>
                    </div>
                </div>
                <div class="col-lg-4 col-md-6">
                    <div class="vital-sign spo2 <?php if($latest_vitals) echo ($latest_vitals['spo2'] < 95) ? 'critical' : (($latest_vitals['spo2'] < 97) ? 'warning' : 'normal'); ?>">
                        <h5><i class="fas fa-lungs me-2"></i>SpO2</h5>
                        <h2 id="spo2"><?php echo $latest_vitals ? htmlspecialchars($latest_vitals['spo2']) . '%' : '--%'; ?></h2>
                    </div>
                </div>
                <div class="col-lg-4 col-md-12">
                    <div class="vital-sign temperature <?php if($latest_vitals) echo ($latest_vitals['temperature'] < 36.5 || $latest_vitals['temperature'] > 37.5) ? 'critical' : (($latest_vitals['temperature'] < 36.8 || $latest_vitals['temperature'] > 37.2) ? 'warning' : 'normal'); ?>">
                        <h5><i class="fas fa-thermometer-half me-2"></i>Temperature</h5>
                        <h2 id="temperature"><?php echo $latest_vitals ? htmlspecialchars($latest_vitals['temperature']) . '°C' : '--°C'; ?></h2>
                    </div>
                </div>
            </div>

            <!-- Charts -->
            <div class="row">
                <div class="col-lg-6 col-md-12 mb-4">
                    <div class="chart-card">
                        <h5><i class="fas fa-chart-line me-2"></i>Heart Rate Trend (24h)</h5>
                        <div class="chart-container">
                            <?php if (!empty($chart_data)): ?>
                                <canvas id="heartRateChart"></canvas>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-chart-line"></i>
                                    <h5>No Data Available</h5>
                                    <p>Heart rate data will appear here once readings are collected</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-12 mb-4">
                    <div class="chart-card">
                        <h5><i class="fas fa-chart-line me-2"></i>SpO2 Trend (24h)</h5>
                        <div class="chart-container">
                            <?php if (!empty($chart_data)): ?>
                                <canvas id="spo2Chart"></canvas>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-lungs"></i>
                                    <h5>No Data Available</h5>
                                    <p>SpO2 data will appear here once readings are collected</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-12 mb-4">
                    <div class="chart-card">
                        <h5><i class="fas fa-chart-line me-2"></i>Temperature Trend (24h)</h5>
                        <div class="chart-container">
                            <?php if (!empty($chart_data)): ?>
                                <canvas id="temperatureChart"></canvas>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-thermometer-half"></i>
                                    <h5>No Data Available</h5>
                                    <p>Temperature data will appear here once readings are collected</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="col-lg-6 col-md-12 mb-4">
                    <div class="chart-card">
                        <h5><i class="fas fa-exclamation-triangle me-2"></i>Recent Alerts</h5>
                        <div class="chart-container">
                            <?php if (!empty($recent_alerts)): ?>
                                <div style="max-height: 280px; overflow-y: auto; padding: 10px;">
                                    <?php foreach($recent_alerts as $index => $alert): ?>
                                        <div class="alert alert-<?php echo $alert['severity'] === 'Critical' ? 'danger' : 'warning'; ?> mb-2" style="padding: 10px; font-size: 0.9rem;">
                                            <strong><?php echo htmlspecialchars($alert['alert_type']); ?></strong><br>
                                            <small><?php echo htmlspecialchars($alert['message']); ?></small><br>
                                            <small class="text-muted"><?php echo date('H:i:s', strtotime($alert['created_at'])); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <div class="no-data">
                                    <i class="fas fa-shield-alt"></i>
                                    <h5>No Recent Alerts</h5>
                                    <p>All vitals are within normal ranges</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        <?php if (!empty($chart_data)): ?>
        // Prepare chart data from PHP
        const chartData = <?php echo json_encode($chart_data); ?>;
        const labels = chartData.map(item => {
            const date = new Date(item.timestamp);
            return date.toLocaleTimeString('en-US', {
                hour12: false,
                hour: '2-digit',
                minute: '2-digit'
            });
        });
        const heartRateData = chartData.map(item => parseFloat(item.heart_rate));
        const spo2Data = chartData.map(item => parseFloat(item.spo2));
        const temperatureData = chartData.map(item => parseFloat(item.temperature));

        // Chart configuration
        const chartOptions = {
            responsive: true,
            maintainAspectRatio: false,
            scales: {
                y: {
                    beginAtZero: true,
                    grid: {
                        color: 'rgba(53, 137, 39, 0.1)',
                        drawBorder: false
                    },
                    ticks: {
                        color: '#358927',
                        font: {
                            weight: '600'
                        }
                    }
                },
                x: {
                    grid: {
                        display: false
                    },
                    ticks: {
                        maxTicksLimit: 8,
                        autoSkip: true,
                        maxRotation: 0,
                        color: '#358927',
                        font: {
                            weight: '600'
                        }
                    }
                }
            },
            plugins: {
                legend: {
                    display: false
                }
            },
            elements: {
                point: {
                    radius: 4,
                    hoverRadius: 8,
                    backgroundColor: '#ffffff',
                    borderWidth: 3
                },
                line: {
                    tension: 0.4
                }
            }
        };

        // Initialize charts only if we have data
        if (document.getElementById('heartRateChart')) {
            new Chart(document.getElementById('heartRateChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Heart Rate (BPM)',
                        borderColor: '#e74c3c',
                        backgroundColor: 'rgba(231, 76, 60, 0.1)',
                        borderWidth: 3,
                        data: heartRateData,
                        fill: true
                    }]
                },
                options: chartOptions
            });
        }

        if (document.getElementById('spo2Chart')) {
            new Chart(document.getElementById('spo2Chart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'SpO2 (%)',
                        borderColor: '#3498db',
                        backgroundColor: 'rgba(52, 152, 219, 0.1)',
                        borderWidth: 3,
                        data: spo2Data,
                        fill: true
                    }]
                },
                options: chartOptions
            });
        }

        if (document.getElementById('temperatureChart')) {
            new Chart(document.getElementById('temperatureChart').getContext('2d'), {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'Temperature (°C)',
                        borderColor: '#2ecc71',
                        backgroundColor: 'rgba(46, 204, 113, 0.1)',
                        borderWidth: 3,
                        data: temperatureData,
                        fill: true
                    }]
                },
                options: chartOptions
            });
        }
        <?php endif; ?>

        // Auto-refresh page every 30 seconds to get new data
        setTimeout(function() {
            location.reload();
        }, 30000);
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>