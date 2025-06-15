<?php
session_start();
require_once "config.php";

// Check if user is logged in
if(!isset($_SESSION["loggedin"]) || $_SESSION["loggedin"] !== true){
    header("location: index.php");
    exit;
}

// Get overall statistics
$stats = array();

// Total patients
$result = mysqli_query($conn, "SELECT COUNT(*) as total FROM patients WHERE doctor_id = " . $_SESSION['id']);
$row = mysqli_fetch_assoc($result);
$stats['total_patients'] = $row['total'];

// Patients by status
$result = mysqli_query($conn, "SELECT status, COUNT(*) as count FROM patients WHERE doctor_id = " . $_SESSION['id'] . " GROUP BY status");
$stats['status'] = array();
while($row = mysqli_fetch_assoc($result)) {
    $stats['status'][$row['status']] = $row['count'];
}

// Get average vital signs for last 24 hours
$sql = "SELECT 
    p.id,
    p.full_name,
    AVG(v.heart_rate) as avg_heart_rate,
    AVG(v.spo2) as avg_spo2,
    AVG(v.temperature) as avg_temperature,
    COUNT(*) as readings_count
FROM patients p
LEFT JOIN vital_signs v ON p.id = v.patient_id
WHERE p.doctor_id = ? AND v.timestamp >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY p.id, p.full_name";

$patient_stats = array();
if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($result)) {
        $patient_stats[] = $row;
    }
}

// Get alerts statistics
$sql = "SELECT 
    alert_type,
    severity,
    COUNT(*) as count
FROM alerts a
JOIN patients p ON a.patient_id = p.id
WHERE p.doctor_id = ? AND a.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY alert_type, severity";

$alert_stats = array();
if($stmt = mysqli_prepare($conn, $sql)) {
    mysqli_stmt_bind_param($stmt, "i", $_SESSION['id']);
    mysqli_stmt_execute($stmt);
    $result = mysqli_stmt_get_result($stmt);
    while($row = mysqli_fetch_assoc($result)) {
        $alert_stats[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analysis - Patient Monitoring System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        .stats-card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .chart-container {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .vital-stats {
            font-size: 24px;
            font-weight: bold;
            margin: 10px 0;
        }
        .status-badge {
            padding: 5px 10px;
            border-radius: 15px;
            color: white;
            font-size: 14px;
            margin: 2px;
        }
        .status-Active { background-color: #2ecc71; }
        .status-Critical { background-color: #e74c3c; }
        .status-Stable { background-color: #3498db; }
        .status-Recovering { background-color: #f1c40f; }
    </style>
</head>
<body class="bg-light">
    <?php include 'navbar.php'; ?>

    <div class="container py-4">
        <h2 class="mb-4">Patient Analysis Dashboard</h2>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-md-6">
                <div class="stats-card">
                    <h4>Patient Status Distribution</h4>
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
            <div class="col-md-6">
                <div class="stats-card">
                    <h4>Alert Distribution (Last 24h)</h4>
                    <canvas id="alertChart"></canvas>
                </div>
            </div>
        </div>

        <!-- Patient Statistics -->
        <div class="stats-card mb-4">
            <h4>Patient Statistics (Last 24 Hours)</h4>
            <div class="table-responsive">
                <table class="table table-hover">
                    <thead>
                        <tr>
                            <th>Patient Name</th>
                            <th>Avg Heart Rate</th>
                            <th>Avg SpO2</th>
                            <th>Avg Temperature</th>
                            <th>Readings Count</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach($patient_stats as $stat): ?>
                        <tr>
                            <td><?php echo htmlspecialchars($stat['full_name']); ?></td>
                            <td><?php echo number_format($stat['avg_heart_rate'], 1); ?> BPM</td>
                            <td><?php echo number_format($stat['avg_spo2'], 1); ?>%</td>
                            <td><?php echo number_format($stat['avg_temperature'], 1); ?>°C</td>
                            <td><?php echo $stat['readings_count']; ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Alert Summary -->
        <div class="stats-card">
            <h4>Alert Summary (Last 24 Hours)</h4>
            <div class="row">
                <?php
                $severity_colors = [
                    'Warning' => 'bg-warning',
                    'Critical' => 'bg-danger',
                    'Emergency' => 'bg-dark'
                ];
                foreach($alert_stats as $alert): ?>
                <div class="col-md-4 mb-3">
                    <div class="card">
                        <div class="card-body">
                            <h5 class="card-title"><?php echo $alert['alert_type']; ?></h5>
                            <span class="badge <?php echo $severity_colors[$alert['severity']]; ?>">
                                <?php echo $alert['severity']; ?>: <?php echo $alert['count']; ?>
                            </span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    <script>
        // Status Distribution Chart
        new Chart(document.getElementById('statusChart').getContext('2d'), {
            type: 'pie',
            data: {
                labels: <?php echo json_encode(array_keys($stats['status'])); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($stats['status'])); ?>,
                    backgroundColor: [
                        '#2ecc71', // Active
                        '#e74c3c', // Critical
                        '#3498db', // Stable
                        '#f1c40f'  // Recovering
                    ]
                }]
            },
            options: {
                responsive: true,
                plugins: {
                    legend: {
                        position: 'bottom'
                    }
                }
            }
        });

        // Alert Distribution Chart
        new Chart(document.getElementById('alertChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_unique(array_column($alert_stats, 'alert_type'))); ?>,
                datasets: [{
                    label: 'Alerts',
                    data: <?php echo json_encode(array_column($alert_stats, 'count')); ?>,
                    backgroundColor: '#3498db'
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1
                        }
                    }
                },
                plugins: {
                    legend: {
                        display: false
                    }
                }
            }
        });
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html> 