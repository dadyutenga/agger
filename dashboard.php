<?php
session_start();
require_once "config.php";
require_once "session.php";

// Check if user is logged in and is a doctor
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// Strict check for doctor access only
if(!isset($_SESSION["user_type"]) || $_SESSION["user_type"] !== "doctor"){
    // If not a doctor, destroy session and redirect to login
    session_destroy();
    header("location: index.php?error=unauthorized");
    exit;
}

// Get all patients for the logged-in doctor
$sql = "SELECT p.*, 
        (SELECT status FROM vital_signs WHERE patient_id = p.id ORDER BY timestamp DESC LIMIT 1) as latest_status,
        (SELECT heart_rate FROM vital_signs WHERE patient_id = p.id ORDER BY timestamp DESC LIMIT 1) as latest_heart_rate,
        (SELECT spo2 FROM vital_signs WHERE patient_id = p.id ORDER BY timestamp DESC LIMIT 1) as latest_spo2,
        (SELECT temperature FROM vital_signs WHERE patient_id = p.id ORDER BY timestamp DESC LIMIT 1) as latest_temperature
        FROM patients p 
        WHERE p.doctor_id = ? AND p.is_active = TRUE 
        ORDER BY p.registration_date DESC";

$patients = [];
if($stmt = mysqli_prepare($conn, $sql)){
    mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    
    while($row = mysqli_fetch_array($result)){
        $patients[] = $row;
    }
}

// Get statistics
$total_patients = count($patients);
$critical_patients = 0;
$stable_patients = 0;
$recovering_patients = 0;

foreach($patients as $patient) {
    switch($patient['latest_status']) {
        case 'Critical':
            $critical_patients++;
            break;
        case 'Stable':
            $stable_patients++;
            break;
        case 'Recovering':
            $recovering_patients++;
            break;
    }
}

// Function to get chat URL based on user type and context
function getChatUrl($conn, $user_type, $user_id, $patient_id = null) {
    if ($user_type === "doctor") {
        if ($patient_id) {
            // Get caretaker ID for this patient
            $sql = "SELECT device_id FROM patients WHERE id = ?";
            $stmt = mysqli_prepare($conn, $sql);
            mysqli_stmt_bind_param($stmt, "i", $patient_id);
            mysqli_stmt_execute($stmt);
            $result = mysqli_stmt_get_result($stmt);
            if ($row = mysqli_fetch_assoc($result)) {
                return "chat.php?receiver_type=caretaker&receiver_id=" . urlencode($row['device_id']);
            }
        }
        return "chat.php"; // Show all caretakers list
    } else {
        // For caretaker, always chat with the assigned doctor
        $sql = "SELECT d.id FROM doctors d 
                JOIN patients p ON d.id = p.doctor_id 
                WHERE p.device_id = ?";
        $stmt = mysqli_prepare($conn, $sql);
        mysqli_stmt_bind_param($stmt, "s", $_SESSION['username']);
        mysqli_stmt_execute($stmt);
        $result = mysqli_stmt_get_result($stmt);
        if ($row = mysqli_fetch_assoc($result)) {
            return "chat.php?receiver_type=doctor&receiver_id=" . $row['id'];
        }
        return "chat.php";
    }
}

// Add this before including navbar.php
$chat_url = getChatUrl($conn, $_SESSION["user_type"], $_SESSION["id"]);
$_SESSION['chat_url'] = $chat_url; // Store in session for navbar.php to use

// Include the navbar
include "navbar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Doctor Dashboard - Patient Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
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
        }

        /* Override Bootstrap navbar colors completely */
        .navbar {
            background: linear-gradient(135deg, var(--forest-green) 0%, #2d7a23 100%) !important;
            box-shadow: 0 2px 10px var(--shadow);
        }

        .navbar-brand, .navbar-nav .nav-link {
            color: var(--white) !important;
            font-weight: 500;
        }

        

       

        .navbar-toggler {
            border-color: var(--white) !important;
        }

        .navbar-toggler:hover, .navbar-toggler:focus {
            border-color: var(--white) !important;
            box-shadow: none !important;
        }

        .navbar-toggler-icon {
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 30 30'%3e%3cpath stroke='rgba%28255, 255, 255, 0.85%29' stroke-linecap='round' stroke-miterlimit='10' stroke-width='2' d='M4 7h22M4 15h22M4 23h22'/%3e%3c/svg%3e");
        }

        /* Remove Bootstrap's default link colors completely */
        a, a:hover, a:focus, a:active, a:visited {
            color: inherit !important;
            text-decoration: none !important;
        }

        /* Override all link colors in navbar specifically */
        .navbar a, .navbar a:hover, .navbar a:focus, .navbar a:active, .navbar a:visited {
            color: var(--white) !important;
        }

        .navbar a:hover, .navbar a:focus {
            color: var(--white) !important;
            background-color: transparent !important;
        }

        /* Remove any blue colors from Bootstrap buttons and links */
        .btn-primary, .bg-primary {
            background-color: var(--forest-green) !important;
            border-color: var(--forest-green) !important;
        }

        .btn-primary:hover, .btn-primary:focus, .btn-primary:active {
            background-color: var(--forest-green) !important;
            border-color: var(--forest-green) !important;
            box-shadow: none !important;
        }

        .text-primary {
            color: var(--forest-green) !important;
        }

        /* Active/Selected states */
        .navbar-nav .nav-item.active .nav-link,
        .navbar-nav .nav-item.active .nav-link:hover,
        .navbar-nav .nav-item.active .nav-link:focus {
            background-color: rgba(255, 255, 255, 0.1) !important;
            border-radius: 5px !important;
            color: var(--white) !important;
        }

        /* Dropdown menu colors */
        .dropdown-menu {
            border: 1px solid rgba(53, 137, 39, 0.1) !important;
            box-shadow: 0 5px 15px var(--shadow) !important;
            background-color: var(--white) !important;
        }

        .dropdown-item {
            color: var(--dark-text) !important;
        }

        .dropdown-item:hover, .dropdown-item:focus {
            background-color: var(--white) !important;
            color: var(--dark-text) !important;
        }

        .dropdown-item:active {
            background-color: var(--white) !important;
            color: var(--dark-text) !important;
        }

        /* Remove Bootstrap focus outline completely */
        .navbar-nav .nav-link:focus,
        .navbar-brand:focus,
        .navbar-toggler:focus,
        *:focus {
            box-shadow: none !important;
            outline: none !important;
            border-color: transparent !important;
        }

        /* Override any remaining blue focus states */
        .focus, :focus {
            box-shadow: none !important;
            outline: none !important;
        }

        /* Remove Bootstrap's link hover effects */
        .nav-link:hover, .nav-link:focus {
            color: var(--white) !important;
        }

        .container {
            background: var(--white);
            border-radius: 20px;
            margin-top: 20px;
            margin-bottom: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px var(--shadow);
            border: 1px solid rgba(53, 137, 39, 0.1);
        }

        .stats-container {
            background: linear-gradient(135deg, var(--white), var(--light-gray));
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px var(--shadow);
            border: 2px solid transparent;
            border-left: 4px solid var(--forest-green);
        }

        .stats-container:hover {
            transform: none;
            box-shadow: 0 5px 15px var(--shadow);
            border-color: transparent;
            border-left: 4px solid var(--forest-green);
        }

        .stats-container h4 {
            color: var(--forest-green);
            font-weight: 600;
            margin-bottom: 10px;
            font-size: 1rem;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .stats-container h2 {
            color: var(--dark-text);
            font-weight: 700;
            font-size: 2.2rem;
            margin: 0;
        }

        .patient-card {
            background: var(--white);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 5px 15px var(--shadow);
            cursor: pointer;
            border: 2px solid transparent;
            border-left: 4px solid var(--forest-green);
        }

        .patient-card:hover {
            transform: none;
            box-shadow: 0 5px 15px var(--shadow);
            border-color: transparent;
            border-left: 4px solid var(--forest-green);
        }

        .patient-image {
            width: 120px;
            height: 120px;
            border-radius: 60px;
            object-fit: cover;
            margin-bottom: 15px;
            border: 3px solid var(--wattle-green);
            box-shadow: 0 4px 10px var(--shadow);
        }

        .patient-card h5 {
            color: var(--forest-green);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .patient-card .text-muted {
            color: #666 !important;
        }

        .vital-signs-row {
            background: linear-gradient(135deg, rgba(53, 137, 39, 0.05), rgba(215, 222, 80, 0.05));
            border: 1px solid rgba(53, 137, 39, 0.1);
            border-radius: 10px;
            padding: 15px;
            margin-bottom: 15px;
        }

        .vital-sign {
            flex: 1;
            padding: 8px;
            text-align: center;
        }

        .vital-sign:not(:last-child) {
            border-right: 1px solid rgba(53, 137, 39, 0.1);
        }

        .vital-sign small {
            color: var(--forest-green);
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.7rem;
            letter-spacing: 0.5px;
        }

        .vital-sign .value {
            font-size: 1.1rem;
            font-weight: 700;
            color: var(--dark-text);
            margin-top: 5px;
            display: block;
        }

        h2.mb-4 {
            color: var(--forest-green);
            font-weight: 700;
            text-align: center;
            margin-bottom: 30px !important;
            font-size: 2.2rem;
            position: relative;
        }

        h2.mb-4::after {
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

        /* Location text styling */
        .patient-card .mt-3 small {
            color: #888;
            background: rgba(53, 137, 39, 0.05);
            padding: 5px 10px;
            border-radius: 8px;
            display: inline-block;
            font-size: 0.8rem;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 20px;
                border-radius: 15px;
            }
            
            .stats-container, .patient-card {
                padding: 20px;
                border-radius: 12px;
            }
            
            h2.mb-4 {
                font-size: 1.8rem;
            }
            
            .vital-signs-row {
                flex-direction: column;
            }
            
            .vital-sign {
                border-right: none !important;
                border-bottom: 1px solid rgba(53, 137, 39, 0.1);
                padding: 10px;
            }
            
            .vital-sign:last-child {
                border-bottom: none;
            }
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-container">
                    <h4><i class="fas fa-users me-2"></i>Total Patients</h4>
                    <h2><?php echo $total_patients; ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-container">
                    <h4><i class="fas fa-exclamation-triangle me-2"></i>Critical Patients</h4>
                    <h2><?php echo $critical_patients; ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-container">
                    <h4><i class="fas fa-heart me-2"></i>Stable Patients</h4>
                    <h2><?php echo $stable_patients; ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-container">
                    <h4><i class="fas fa-chart-line me-2"></i>Recovering Patients</h4>
                    <h2><?php echo $recovering_patients; ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-container">
                    <h4><i class="fas fa-calendar-plus me-2"></i>New Today</h4>
                    <h2><?php
                        $result = mysqli_query($conn, "SELECT COUNT(*) as new FROM patients 
                            WHERE doctor_id = " . $_SESSION['id'] . " 
                            AND DATE(registration_date) = CURDATE()");
                        $row = mysqli_fetch_assoc($result);
                        echo $row['new'];
                    ?></h2>
                </div>
            </div>
        </div>

        <h2 class="mb-4"><i class="fas fa-clipboard-list me-3"></i>Patients Overview</h2>
        
        <div class="row">
            <?php
            foreach($patients as $patient) {
                ?>
                <div class="col-md-4">
                    <div class="patient-card" onclick="window.location='patient_details.php?id=<?php echo $patient['id']; ?>'">
                        <div class="text-center">
                            <img src="<?php echo $patient['image_path'] ?? 'uploads/default.png'; ?>" class="patient-image">
                            <h5><i class="fas fa-user me-2"></i><?php echo htmlspecialchars($patient['full_name']); ?></h5>
                            <p class="text-muted">
                                <i class="fas fa-birthday-cake me-1"></i>Age: <?php echo $patient['age']; ?> | 
                                <i class="fas fa-calendar me-1"></i>Reg: <?php echo date('d/m/Y', strtotime($patient['registration_date'])); ?>
                            </p>
                        </div>
                        
                        <div class="vital-signs-row d-flex justify-content-between align-items-center">
                            <div class="vital-sign">
                                <small class="d-block"><i class="fas fa-info-circle me-1"></i>Status</small>
                                <span class="value"><?php echo htmlspecialchars($patient['latest_status']); ?></span>
                            </div>
                            <div class="vital-sign">
                                <small class="d-block"><i class="fas fa-heartbeat me-1"></i>Heart Rate</small>
                                <span class="value"><?php echo $patient['latest_heart_rate'] ?? 'N/A'; ?> BPM</span>
                            </div>
                            <div class="vital-sign">
                                <small class="d-block"><i class="fas fa-lungs me-1"></i>SpO2</small>
                                <span class="value"><?php echo $patient['latest_spo2'] ?? 'N/A'; ?>%</span>
                            </div>
                            <div class="vital-sign">
                                <small class="d-block"><i class="fas fa-thermometer-half me-1"></i>Temperature</small>
                                <span class="value"><?php echo $patient['latest_temperature'] ?? 'N/A'; ?>°C</span>
                            </div>
                        </div>

                        <div class="mt-3">
                            <small class="text-muted">
                                <i class="fas fa-map-marker-alt me-1"></i>Location: <?php echo htmlspecialchars($patient['region'] . ', ' . $patient['ward'] . ', ' . $patient['street']); ?>
                            </small>
                        </div>
                    </div>
                </div>
                <?php
            }
            ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
