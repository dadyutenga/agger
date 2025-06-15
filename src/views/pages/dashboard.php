<?php
session_start();
require_once "config.php";

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Dashboard - Patient Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
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
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container mt-4">
        <div class="row mb-4">
            <div class="col-md-4">
                <div class="stats-container">
                    <h4>Total Patients</h4>
                    <h2><?php
                        $result = mysqli_query($conn, "SELECT COUNT(*) as total FROM patients WHERE doctor_id = " . $_SESSION['id']);
                        $row = mysqli_fetch_assoc($result);
                        echo $row['total'];
                    ?></h2>
                </div>
            </div>
            <div class="col-md-4">
                <div class="stats-container">
                    <h4>Critical Patients</h4>
                    <h2><?php
                        $result = mysqli_query($conn, "SELECT COUNT(*) as critical FROM patients p 
                            JOIN vital_signs v ON p.id = v.patient_id 
                            WHERE p.doctor_id = " . $_SESSION['id'] . " 
                            AND (v.heart_rate > 100 OR v.heart_rate < 60 OR v.spo2 < 95)");
                        $row = mysqli_fetch_assoc($result);
                        echo $row['critical'];
                    ?></h2>
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
            $sql = "SELECT p.*, 
                    MAX(v.heart_rate) as latest_heart_rate,
                    MAX(v.spo2) as latest_spo2,
                    MAX(v.temperature) as latest_temperature
                    FROM patients p
                    LEFT JOIN vital_signs v ON p.id = v.patient_id
                    WHERE p.doctor_id = ?
                    GROUP BY p.id";
            
            if($stmt = mysqli_prepare($conn, $sql)){
                mysqli_stmt_bind_param($stmt, "i", $_SESSION["id"]);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                
                while($row = mysqli_fetch_array($result)){
                    ?>
                    <div class="col-md-4">
                        <div class="patient-card" onclick="window.location='patient_details.php?id=<?php echo $row['id']; ?>'">
                            <div class="text-center">
                                <img src="<?php echo $row['image_path'] ?? 'uploads/default.png'; ?>" class="patient-image">
                                <h5><?php echo htmlspecialchars($row['full_name']); ?></h5>
                                <p class="text-muted">
                                    Age: <?php echo $row['age']; ?> | 
                                    Reg: <?php echo date('d/m/Y', strtotime($row['registration_date'])); ?>
                                </p>
                            </div>
                            
                            <div class="vital-signs-row d-flex justify-content-between align-items-center mb-3 p-2 bg-light rounded">
                                <div class="vital-sign text-center">
                                    <small class="text-muted d-block">Heart Rate</small>
                                    <span class="value"><?php echo $row['latest_heart_rate'] ?? 'N/A'; ?> BPM</span>
                                </div>
                                <div class="vital-sign text-center">
                                    <small class="text-muted d-block">SpO2</small>
                                    <span class="value"><?php echo $row['latest_spo2'] ?? 'N/A'; ?>%</span>
                                </div>
                                <div class="vital-sign text-center">
                                    <small class="text-muted d-block">Temperature</small>
                                    <span class="value"><?php echo $row['latest_temperature'] ?? 'N/A'; ?>°C</span>
                                </div>
                            </div>

                            <div class="mt-3">
                                <small class="text-muted">
                                    Location: <?php echo htmlspecialchars($row['region'] . ', ' . $row['ward'] . ', ' . $row['street']); ?>
                                </small>
                            </div>
                        </div>
                    </div>
                    <?php
                }
                mysqli_stmt_close($stmt);
            }
            ?>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 