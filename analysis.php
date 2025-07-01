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
$result = mysqli_query($conn, "SELECT vs.status, COUNT(DISTINCT vs.patient_id) as count 
                             FROM vital_signs vs 
                             INNER JOIN patients p ON vs.patient_id = p.id 
                             WHERE p.doctor_id = " . $_SESSION['id'] . " 
                             GROUP BY vs.status");
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

// Include the navbar
include "navbar.php";
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Analysis - Patient Monitoring System</title>
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
        }

        /* Override Bootstrap navbar colors */
        .navbar {
            background: linear-gradient(135deg, var(--forest-green) 0%, #2d7a23 100%) !important;
            box-shadow: 0 2px 10px var(--shadow);
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

        .container {
            background: var(--white);
            border-radius: 20px;
            margin-top: 20px;
            margin-bottom: 20px;
            padding: 30px;
            box-shadow: 0 10px 30px var(--shadow);
            border: 1px solid rgba(53, 137, 39, 0.1);
        }

        h2 {
            color: var(--forest-green);
            font-weight: 700;
            text-align: center;
            margin-bottom: 30px;
            font-size: 2.2rem;
            position: relative;
        }

        h2::after {
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

        .stats-card {
            background: linear-gradient(135deg, var(--white), var(--light-gray));
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px var(--shadow);
            border: 2px solid transparent;
            border-left: 4px solid var(--forest-green);
            transition: all 0.3s ease;
        }

        .stats-card:hover {
            border-color: var(--wattle-green);
            transform: translateY(-2px);
            box-shadow: 0 8px 20px rgba(53, 137, 39, 0.2);
        }

        .stats-card h4 {
            color: var(--forest-green);
            font-weight: 600;
            margin-bottom: 20px;
            font-size: 1.3rem;
            text-align: center;
        }

        .chart-container {
            background: var(--white);
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 25px;
            box-shadow: 0 5px 15px var(--shadow);
            border: 2px solid rgba(53, 137, 39, 0.1);
        }

        .chart-container h4 {
            color: var(--forest-green);
            font-weight: 600;
            margin-bottom: 20px;
            text-align: center;
            font-size: 1.3rem;
        }

        .table {
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 10px rgba(53, 137, 39, 0.1);
        }

        .table thead {
            background: linear-gradient(135deg, var(--forest-green), #2d7a23);
            color: var(--white);
        }

        .table thead th {
            border: none;
            font-weight: 600;
            text-transform: uppercase;
            font-size: 0.9rem;
            letter-spacing: 0.5px;
            padding: 15px;
        }

        .table tbody td {
            padding: 12px 15px;
            border-bottom: 1px solid rgba(53, 137, 39, 0.1);
            font-weight: 500;
        }

        .table tbody tr:hover {
            background: rgba(53, 137, 39, 0.05);
        }

        .table tbody tr:last-child td {
            border-bottom: none;
        }

        .vital-stats {
            font-size: 1.8rem;
            font-weight: 700;
            margin: 15px 0;
            color: var(--forest-green);
        }

        .status-badge {
            padding: 6px 12px;
            border-radius: 20px;
            color: var(--white);
            font-size: 0.85rem;
            font-weight: 600;
            margin: 3px;
            display: inline-block;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .status-Active { 
            background: linear-gradient(135deg, #2ecc71, #27ae60);
            box-shadow: 0 2px 8px rgba(46, 204, 113, 0.3);
        }
        
        .status-Critical { 
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            box-shadow: 0 2px 8px rgba(231, 76, 60, 0.3);
        }
        
        .status-Stable { 
            background: linear-gradient(135deg, #3498db, #2980b9);
            box-shadow: 0 2px 8px rgba(52, 152, 219, 0.3);
        }
        
        .status-Recovering { 
            background: linear-gradient(135deg, #f1c40f, #f39c12);
            box-shadow: 0 2px 8px rgba(241, 196, 15, 0.3);
        }

        .alert-card {
            background: var(--white);
            border-radius: 15px;
            padding: 20px;
            margin-bottom: 15px;
            box-shadow: 0 3px 10px var(--shadow);
            border: 2px solid rgba(53, 137, 39, 0.1);
            transition: all 0.3s ease;
        }

        .alert-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 6px 15px rgba(53, 137, 39, 0.2);
        }

        .alert-card .card-title {
            color: var(--forest-green);
            font-weight: 600;
            font-size: 1.1rem;
            margin-bottom: 10px;
        }

        .badge {
            font-size: 0.8rem;
            font-weight: 600;
            padding: 8px 12px;
            border-radius: 15px;
        }

        .bg-warning {
            background: linear-gradient(135deg, #f1c40f, #f39c12) !important;
        }

        .bg-danger {
            background: linear-gradient(135deg, #e74c3c, #c0392b) !important;
        }

        .bg-dark {
            background: linear-gradient(135deg, #2c3e50, #34495e) !important;
        }

        .fas {
            color: var(--forest-green);
            margin-right: 8px;
        }

        /* Chart customization */
        .chart-container canvas {
            border-radius: 10px;
        }

        /* Responsive design */
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                padding: 20px;
                border-radius: 15px;
            }
            
            h2 {
                font-size: 1.8rem;
            }
            
            .stats-card, .chart-container {
                padding: 20px;
                margin-bottom: 20px;
            }
            
            .table {
                font-size: 0.9rem;
            }
            
            .table thead th,
            .table tbody td {
                padding: 10px;
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

        .stats-card, .chart-container, .alert-card {
            animation: fadeIn 0.6s ease-out;
        }

        /* Empty state styling */
        .empty-state {
            text-align: center;
            padding: 40px;
            color: var(--forest-green);
        }

        .empty-state i {
            font-size: 48px;
            opacity: 0.6;
            margin-bottom: 15px;
        }

        .empty-state h5 {
            color: var(--forest-green);
            font-weight: 600;
            margin-bottom: 10px;
        }

        .empty-state p {
            color: #6c757d;
            font-size: 0.9rem;
        }
    </style>
</head>
<body>
    <div class="container">
        <h2><i class="fas fa-chart-line me-3"></i>Patient Analysis Dashboard</h2>

        <!-- Overall Statistics -->
        <div class="row mb-4">
            <div class="col-lg-6 col-md-12">
                <div class="stats-card">
                    <h4><i class="fas fa-user-check me-2"></i>Patient Status Distribution</h4>
                    <?php if(!empty($stats['status'])): ?>
                        <canvas id="statusChart" style="max-height: 300px;"></canvas>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-chart-pie"></i>
                            <h5>No Status Data Available</h5>
                            <p>Patient status information will appear here once data is collected.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
            <div class="col-lg-6 col-md-12">
                <div class="stats-card">
                    <h4><i class="fas fa-exclamation-triangle me-2"></i>Alert Distribution (Last 24h)</h4>
                    <?php if(!empty($alert_stats)): ?>
                        <canvas id="alertChart" style="max-height: 300px;"></canvas>
                    <?php else: ?>
                        <div class="empty-state">
                            <i class="fas fa-bell"></i>
                            <h5>No Alerts Today</h5>
                            <p>All patients are within normal parameters.</p>
                        </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>

        <!-- Patient Statistics -->
        <div class="stats-card mb-4">
            <h4><i class="fas fa-heartbeat me-2"></i>Patient Vital Signs Statistics (Last 24 Hours)</h4>
            <?php if(!empty($patient_stats)): ?>
                <div class="table-responsive">
                    <table class="table table-hover">
                        <thead>
                            <tr>
                                <th><i class="fas fa-user me-2"></i>Patient Name</th>
                                <th><i class="fas fa-heart me-2"></i>Avg Heart Rate</th>
                                <th><i class="fas fa-lungs me-2"></i>Avg SpO2</th>
                                <th><i class="fas fa-thermometer-half me-2"></i>Avg Temperature</th>
                                <th><i class="fas fa-clipboard-list me-2"></i>Readings Count</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($patient_stats as $stat): ?>
                            <tr>
                                <td><strong><?php echo htmlspecialchars($stat['full_name']); ?></strong></td>
                                <td><?php echo number_format($stat['avg_heart_rate'], 1); ?> <small class="text-muted">BPM</small></td>
                                <td><?php echo number_format($stat['avg_spo2'], 1); ?><small class="text-muted">%</small></td>
                                <td><?php echo number_format($stat['avg_temperature'], 1); ?><small class="text-muted">°C</small></td>
                                <td><span class="badge bg-success"><?php echo $stat['readings_count']; ?></span></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-chart-line"></i>
                    <h5>No Vital Signs Data</h5>
                    <p>Patient vital signs data will appear here once readings are collected.</p>
                </div>
            <?php endif; ?>
        </div>

        <!-- Alert Summary -->
        <div class="stats-card">
            <h4><i class="fas fa-bell me-2"></i>Alert Summary (Last 24 Hours)</h4>
            <?php if(!empty($alert_stats)): ?>
                <div class="row">
                    <?php
                    $severity_colors = [
                        'Warning' => 'bg-warning',
                        'Critical' => 'bg-danger',
                        'Emergency' => 'bg-dark'
                    ];
                    foreach($alert_stats as $alert): ?>
                    <div class="col-lg-4 col-md-6 mb-3">
                        <div class="alert-card">
                            <h5 class="card-title">
                                <i class="fas fa-exclamation-circle me-2"></i>
                                <?php echo htmlspecialchars($alert['alert_type']); ?>
                            </h5>
                            <span class="badge <?php echo $severity_colors[$alert['severity']]; ?>">
                                <?php echo htmlspecialchars($alert['severity']); ?>: <?php echo $alert['count']; ?>
                            </span>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-state">
                    <i class="fas fa-shield-alt"></i>
                    <h5>No Alerts Today</h5>
                    <p>All patients are within safe parameters. Great job!</p>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <script>
        // Chart.js default colors
        const chartColors = {
            forest: '#358927',
            forestDark: '#2d7a23',
            wattle: '#D7DE50',
            success: '#2ecc71',
            danger: '#e74c3c',
            warning: '#f1c40f',
            info: '#3498db'
        };

        // Status Distribution Chart
        <?php if(!empty($stats['status'])): ?>
        new Chart(document.getElementById('statusChart').getContext('2d'), {
            type: 'doughnut',
            data: {
                labels: <?php echo json_encode(array_keys($stats['status'])); ?>,
                datasets: [{
                    data: <?php echo json_encode(array_values($stats['status'])); ?>,
                    backgroundColor: [
                        chartColors.success,
                        chartColors.danger,
                        chartColors.info,
                        chartColors.warning
                    ],
                    borderWidth: 2,
                    borderColor: '#ffffff'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: {
                            padding: 20,
                            font: {
                                size: 12,
                                weight: '600'
                            }
                        }
                    }
                }
            }
        });
        <?php endif; ?>

        // Alert Distribution Chart
        <?php if(!empty($alert_stats)): ?>
        new Chart(document.getElementById('alertChart').getContext('2d'), {
            type: 'bar',
            data: {
                labels: <?php echo json_encode(array_unique(array_column($alert_stats, 'alert_type'))); ?>,
                datasets: [{
                    label: 'Number of Alerts',
                    data: <?php echo json_encode(array_column($alert_stats, 'count')); ?>,
                    backgroundColor: chartColors.forest,
                    borderColor: chartColors.forestDark,
                    borderWidth: 2,
                    borderRadius: 8,
                    borderSkipped: false,
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            stepSize: 1,
                            font: {
                                weight: '600'
                            }
                        },
                        grid: {
                            color: 'rgba(53, 137, 39, 0.1)'
                        }
                    },
                    x: {
                        ticks: {
                            font: {
                                weight: '600'
                            }
                        },
                        grid: {
                            display: false
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
        <?php endif; ?>
    </script>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>