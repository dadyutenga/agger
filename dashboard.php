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
    <link href="css/style.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/boxicons@2.0.7/css/boxicons.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .sidebar {
            height: 100vh;
            background: #2c3e50;
            color: white;
            position: fixed;
            left: 0;
            top: 0;
            width: 250px;
            padding: 20px;
        }
        .main-content {
            margin-left: 250px;
            padding: 20px;
        }
        .patient-card {
            background: white;
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 15px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }
        .patient-card:hover {
            transform: translateY(-5px);
        }
        .patient-image {
            width: 120px;
            height: 120px;
            border-radius: 60px;
            object-fit: cover;
            margin-bottom: 15px;
        }
        .vital-signs-row {
            background: #f8f9fa;
            border: 1px solid #e9ecef;
        }
        .vital-sign {
            flex: 1;
            padding: 8px;
        }
        .vital-sign:not(:last-child) {
            border-right: 1px solid #e9ecef;
        }
        .vital-sign .value {
            font-size: 1.1rem;
            font-weight: bold;
            color: #2c3e50;
        }
        .stats-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-container">
                    <h4>Total Patients</h4>
                    <h2><?php echo $total_patients; ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-container">
                    <h4>Critical Patients</h4>
                    <h2><?php echo $critical_patients; ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-container">
                    <h4>Stable Patients</h4>
                    <h2><?php echo $stable_patients; ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-container">
                    <h4>Recovering Patients</h4>
                    <h2><?php echo $recovering_patients; ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-container">
                    <h4>New Today</h4>
                    <h2><?php
                        $result = mysqli_query($conn, "SELECT COUNT(*) as new FROM patients 
                            WHERE doctor_id = " . $_SESSION['id'] . " 
                            AND DATE(created_at) = CURDATE()");
                        $row = mysqli_fetch_assoc($result);
                        echo $row['new'];
                    ?></h2>
                </div>
            </div>
        </div>

        <h2 class="mb-4">Patients Overview</h2>
        
        <div class="row">
            <?php
            foreach($patients as $patient) {
                ?>
                <div class="col-md-4">
                    <div class="patient-card" onclick="window.location='patient_details.php?id=<?php echo $patient['id']; ?>'">
                        <div class="text-center">
                            <img src="<?php echo $patient['image_path'] ?? 'uploads/default.png'; ?>" class="patient-image">
                            <h5><?php echo htmlspecialchars($patient['full_name']); ?></h5>
                            <p class="text-muted">
                                Age: <?php echo $patient['age']; ?> | 
                                Reg: <?php echo date('d/m/Y', strtotime($patient['registration_date'])); ?>
                            </p>
                        </div>
                        
                        <div class="vital-signs-row d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                            <div class="vital-sign text-center">
                                <small class="text-muted d-block">Status</small>
                                <span class="value"><?php echo htmlspecialchars($patient['latest_status']); ?></span>
                            </div>
                            <div class="vital-sign text-center">
                                <small class="text-muted d-block">Heart Rate</small>
                                <span class="value"><?php echo $patient['latest_heart_rate'] ?? 'N/A'; ?> BPM</span>
                            </div>
                            <div class="vital-sign text-center">
                                <small class="text-muted d-block">SpO2</small>
                                <span class="value"><?php echo $patient['latest_spo2'] ?? 'N/A'; ?>%</span>
                            </div>
                            <div class="vital-sign text-center">
                                <small class="text-muted d-block">Temperature</small>
                                <span class="value"><?php echo $patient['latest_temperature'] ?? 'N/A'; ?>°C</span>
                            </div>
                        </div>

                        <div class="mt-3">
                            <small class="text-muted">
                                Location: <?php echo htmlspecialchars($patient['region'] . ', ' . $patient['ward'] . ', ' . $patient['street']); ?>
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